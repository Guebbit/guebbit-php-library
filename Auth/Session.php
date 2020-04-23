<?php
/*
			TODO
 - mail di conferma della registrazione (delay dell'auteticazione post registrazione)
 - studiare secret_key e come renderla sicura?
 - creare sessione OAUTH
 - sistemare "auth_cookie" in maniera che il login non svanisca mai, però se richieste aree "delicate" venga richiesto
 - dividere la classe Session in "con database" e "senza database"

//https://github.com/ezimuel/PHP-Secure-Session/blob/master/src/SecureHandler.php
//https://github.com/ezimuel/PHP-Secure-Session/blob/master/test/demo/index.php

//https://www.pontikis.net/blog/create-cookies-php-javascript
*/

namespace Guebbit\Auth;

use Guebbit\Base;

class Session extends Base{
	// ----------- variabili della classe -----------

	//Per distinguere sessioni/cookies "normali;
	protected $domain="";
	//sicurezza (cambiare per ogni sito)
	protected $fingerprint="2c17122c120f2623cbdc4f83";
	//hash $domain + $fingerprint
	protected $name="";

    protected $cn=false;		// connessione al database
	protected $https=false;		// true = miglior protezione (ma utilizzabile solo su https)
	public $cookie_expiration_time=365*24*60*60;	//scade tra 1 anno (attenzione quando sarà il 2038)
	public $settings;			//array delle opzioni

	protected $user_class;

	protected $version="0.1";

	protected $admin_level=9;
	protected $max_auth=3;
	protected $page_auth=false;

	/**
	*	----------- variabili di sessione che vengono utilizzate di default -----------
	*	$_SESSION[]["auth"];	0 - non ancora identificato 			(nemmeno come anonimo)
	*							1 - nessuna autenticazione				(solo aree libere)
	*							2 - autenticato tramite cookie / oauth	(eventualmente ristretto finché non effettua il login)
	*							3 - autenticato tramite login 			(massima certezza)
	*
	*	$_SESSION[]["level"];	0 - anonimo
	*							1 - utente da confermare
	*							2 - utente registrato
	*							9 - amministratore
	*							TODO sistema semplicistico, eliminare e mettere degli 0/1 sui singoli permessi
	*							ATTENZIONE: se l'auth!=3 (massimo) il level non conta nulla
	*
	*	$_SESSION[][$this->fingerprint]: sicurezza
	*	$_SESSION[]['HTTP_USER_AGENT']: sicurezza
	**/

	// ----------- costants -----------
	//self::CONSTANT_NAME
	public const ERROR_EMPTY_LOGIN 		= "Empty fields";
	public const ERROR_TAKEN_LOGIN 		= "Username or Email already taken";
	public const WARNING_ALREADY_LOGGED = "Already logged";


	/**
	*	Costruzione della sessione
	*
	*
	**/
	public function __construct($conn=false, $settings=[]){
		// ------ settings varie ------
		//ini_set('session.gc_maxlifetime', 86400); //expire in 24 ore
		$this->settings=array_replace_recursive([
			"username" 		=> "Anonimo",     			// default username degli ospiti
			"domain" 		=> $_SERVER['SERVER_NAME'],	// dominio del sito ($_SERVER not safe ma non ci importa in teoria, unico safe è inserirlo da per se)
			"session_name" 	=> false,					//default = domain
			"user_class" => [
				//requested columns from database
				"requested" => [
					"username",
				]
			]
		],$settings);

		if($this->settings["domain"])
			$this->domain = $this->settings["domain"];
		if(!$this->settings["session_name"])
			$this->settings["session_name"] = $this->settings["domain"];

		$this->name = hash('sha512', $this->fingerprint.$this->settings["session_name"]);

		$this->user_class = false;
		// user_class necessita del database
		if($conn){
			$this->user_class = new Users();
			$this->connectTo($conn);
		}

		return $this;
	}

	public function start(){
		//se qualcosa va storto, distruggo tutto
		if(!$this->session_start())
			$this->logout();
		$this->auto_login();
	}
	public function inject(){
		$this->session_inject();
	}

	protected function get_currentpage(){
		return htmlspecialchars(pathinfo($_SERVER["PHP_SELF"], PATHINFO_FILENAME));
	}

	protected function check_errors(){
		return count($this->output["error"]) > 0;
	}



	// --------------------------- CONNECTION ---------------------------
	//passo una connessione già esistente
    public function connectTo($connection){
        $this->cn=$connection;
		$this->user_class = new Users();
		$this->user_class->connectTo($connection);
        return true;
    }
	public function connect($db_host, $db_name, $db_user, $db_pass, $timezone=false){
		$this->cn = new PDO("mysql:host=".$db_host.";charset=utf8;dbname=".$db_name, $db_user, $db_pass);
		if(($timezone)&&(!$this->cn->query("SET time_zone='.$timezone.'"))){
			$this->add_error(self::ERROR_UNKNOWN);
			return false;
		}
		$this->user_class = new Users();
		$this->user_class->connectTo($this->cn);
		return true;
	}



	// --------------------------- SESSION ---------------------------
	//creazione della sessione
	protected function session_start(){
		session_start();
		// create
		if(!isset($_SESSION[$this->name]))
			$_SESSION[$this->name]=[];
		// prevenzione "fixation attacks"
		if(!isset($_SESSION[$this->name][$this->fingerprint])){
			session_regenerate_id();	// da fare ogni volta che c'è un cambio di autenticazione
			$_SESSION[$this->name][$this->fingerprint]=true;
		}
		// ulteriore prevenzione
		if(isset($_SESSION[$this->name]['HTTP_USER_AGENT']) &&
			($_SESSION[$this->name]['HTTP_USER_AGENT'] != md5($_SERVER['HTTP_USER_AGENT'].$this->fingerprint)))
			return false;
		//
		$_SESSION[$this->name]['HTTP_USER_AGENT']=md5($_SERVER['HTTP_USER_AGENT'].$this->fingerprint);
		return true;
	}
	protected function session_inject(){
		// create
		if(!isset($_SESSION[$this->name]))
			$_SESSION[$this->name]=[];
		$this->auto_login();
		return true;
	}


	public function destroy_session(){
		//unsetto tutte le variabili di sessione
		$_SESSION = array();
		unset($_SESSION);
		//distruggo la sessione
		session_destroy();
		session_unset();
	}



	/**
	*	creo la sessione
	*	@param identifier $id
	*	@param integer $level
	*	@param integer $auth
	**/
	protected function create_session_auth($id, $level, $auth){
		$_SESSION[$this->name]["id"]=$id;
		$_SESSION[$this->name]["level"]=(int)$level;
		$_SESSION[$this->name]["auth"]=(int)$auth;
	}

	protected function addon_session_auth($data, $where=false){
		if(!$where)
			foreach($data as $name => $my_data)
				$_SESSION[$this->name][$name]=$my_data;
		else
			foreach($data as $name => $my_data)
				$_SESSION[$this->name][$where][$name]=$my_data;
	}

	protected function create_user_session_anon(){
		//quando si registrerà sarà un utente nuovo a cui mergerò alcuni dati raccolti.
		//Non potrà fare le cose che può fare uno pseudo-utente.
		//						id a prova a di collisioni, per eventuali identificazioni senza l'uso di un DB
		$this->auth_session_anon($this->secure_hash(40), 0, 1);
		$this->cookie_creation("anon");
		return true;
	}



	protected function check_auth($level=2){
		return ($this->get_auth() > $level);
	}

	// controllo che l'utente abbia i permessi adatti, che deciderò di volta in volta
	public function check_permissions($auth, $level){
		//l'admin va dove gli pare
		if( ($this->admin_level == $this->get_level()) &&
			($this->max_auth == $this->get_auth()))
			return true;
		//custom
		if( ($level <= $this->get_level()) &&
			($auth <= $this->get_auth()))
			return true;
		return false;
	}


	public function get_session(){
		if(isset($_SESSION[$this->name]))
			return $_SESSION[$this->name];
		return false;
	}
	public function get_auth(){
		if(!$this->get_session())
			return false;
		//default
		if(!isset($_SESSION[$this->name]["auth"]))
			$_SESSION[$this->name]["auth"]=0;
		return $_SESSION[$this->name]["auth"];
	}
	public function get_level(){
		if(!$this->get_session())
			return false;
		//default
		if(!isset($_SESSION[$this->name]["level"]))
			$_SESSION[$this->name]["level"]=0;
		return $_SESSION[$this->name]["level"];
	}
	public function get_userid(){
		return $_SESSION[$this->name]["id"];
	}
	public function get_adminlevel(){
		return $this->admin_level;
	}
	public function get_maxauth(){
		return $this->max_auth;
	}

	// -------------------- COOKIES --------------------
	/**
	* 	se questo cookie viene rubato è possibile autenticarsi al posto della persona, quindi NON è una vera autenticazione.
	*	Non deve avere autorizzazioni sensibili, prima bisogna loggarsi in modo classico
	* 	@return bool true = successo nell'autenticazione
	**/
	protected function auth_cookie(){
		//non avrebbe senso farlo in quanto si è già autenticati al massimo
		if($this->get_auth() > 2)
			return true;

		//echo "<pre>".print_r($_COOKIE,1)."</pre>";die("tenere d'occhio");
		//se non ci sono i cookies che mi servono
		if(!isset($_COOKIE[$this->name]["id"]))
			return false;
		if(!isset($_COOKIE[$this->name]["type"]))
			return false;

		switch ($_COOKIE[$this->name]["type"]) {
			case 'user':
				if($this->auth_cookie_user($this->sanitize($_COOKIE[$this->name]["id"]))){
					$this->cookie_creation("user");
					return true;
				}
			break;
			case 'anon':
				if($this->auth_session_anon($this->sanitize($_COOKIE[$this->name]["id"]))){
					$this->cookie_creation("anon");		//rinnovo il cookie
					return true;
				}
			break;
			default:break;
		}
		return false;
	}

	protected function auth_cookie_user($id){

		//L'utente non è autenticato, è presente un cookie e mi indica che si tratta di un utente
		//cerco l'id nel database
		$user = $this->user_class->find_user_byid($id);

		//se i dati non sono corretti: distruggo tutto
		if(!$user)
			$this->logout();
		return $this->auth_session_user($id, 2, $user["level"],
			array_intersect_key($user,
				array_flip($this->settings["user_class"]["requested"])
			)
		);
	}
	protected function auth_session_user($id, $auth, $level, $data=[]){
		$this->create_session_auth($id, $level, $auth);
		$this->addon_session_auth($data, "user_data");
		session_regenerate_id();
		return true;
	}



	protected function auth_session_anon($id){
		//L'utente non è autenticato, è presente un cookie e mi indica che si tratta di un anonimo
		$this->create_session_auth($id, 0, 1);
		$this->addon_session_auth([
			"username" => $this->settings["username"]
		], "user_data");
		session_regenerate_id();
		return true;
	}

	//creo il cookie di autenticazione (NON è una vera autenticazione ma solo un aiuto, visto che i cookie NON possono essere veramente sicuri)
	//cookie are sent in the header (occhio all'errore headers already sent)
	protected function cookie_creation($word){
		//$word mi aiuta a gestire i diversi cookie
		//l'ultimo parametro, '/', è il path del cookie: necessario per chiamarlo via ajax
		setcookie(	$this->name."[id]",
					$_SESSION[$this->name]["id"],
					time()+$this->cookie_expiration_time,
					"/",
					$this->domain,
					$this->https,
					true
				);
		setcookie(	$this->name."[type]",
					$word,
					time()+$this->cookie_expiration_time,
					"/",
					$this->domain,
					$this->https,
					true
				);
		//https://www.php.net/manual/en/function.setcookie.php
		//setcookie(name, value, expire, path, domain, secure, httponly);
		return $this;
	}

	public function destroy_cookie(){
		//distruggo i cookie, se presenti (questo meglio di unset)
		if(isset($_COOKIE[$this->name])){
			foreach($_COOKIE[$this->name] as $index => $value){
				//here is no direct way to directly delete a cookie
				unset($_COOKIE[$this->name][$index]);
				setcookie(	$this->name."[".$index."]",
							"",
							time()-3600,
							"/",
							$this->domain,
							$this->https,
							true
						);
			}
		}
	}



	// -------------------- LOGIN & LOGOUT --------------------
	//distruggo la sessione
	public function logout(){
		$this->destroy_session();
		$this->destroy_cookie();
		return true;
	}

	protected function auto_login(){
		//non ha senso se sono già autenticato
		if($this->check_auth())
			return true;
		//se non sono già autenticato in qualche modo, controllo se ci sono dei cookie e cerco di autenticarmi con essi
		if(($this->get_auth() > 0) || $this->auth_cookie())
			return false;

		//non è ancora stato autenticato, creo un utente anonimo
		return $this->create_user_session_anon();
	}










	// CONNESSIONE NECESSARIA
	//si sta effettuando un login tramite form, unico modo (insieme eventualmente ad form_register) di essere perfettamente autenticati
	public function form_login($ins_credential="",$ins_password=""){
		//non ha senso, è già loggato
		if($this->check_auth()){
			$this->add_warning(self::WARNING_ALREADY_LOGGED);
			return true;
		}
		//connessione mancante
		if(!$this->cn || !$this->user_class){
			$this->add_error(self::ERROR_CN_ERROR);
			return false;
		}
		//L'utente non è autenticato tramite login e si sta effettuando un login (username o email + password)
		//si può loggare via ajax, però la sessione non cambia senza un riavvio
		if($ins_credential=="" || $ins_password==""){
			$this->add_error(self::ERROR_EMPTY_LOGIN);
			return false;
		}

		//sanitizzo
		$ins_credential=$this->sanitize($ins_credential);
		$ins_password=$this->sanitize($ins_password);

		$user = $this->user_class->check_user_credentials($ins_credential, $ins_password);
		//allora è un errore
		if(is_string($user)){
			$this->add_error($user);
			return false;
		}

		$this->auth_session_user($user["id"], 3, $user["level"],
			array_intersect_key($user,
				array_flip($this->settings["user_class"]["requested"])
			)
		);
		$this->cookie_creation("user");

		return $user;
	}
	// CONNESSIONE NECESSARIA
	//L'utente non è autenticato tramite login e si sta effettuando un login tramite secret_key (domain.com?secretkey=codicevario)
	//login veloce e automatico ma non sicuro, quindi conta come un login da cookie, auth=2
	public function secret_login($secretkey="", $full_auth=false){
		//non ha senso, è già loggato
		if($this->check_auth(1))
			return true;
		//connessione mancante
		if(!$this->cn || !$this->user_class)
			return false;
		if($secretkey=="")
			return false;

		$ins_secretkey=$this->sanitize($secretkey);
		$user=$this->user_class->check_user_secret($secretkey);
		//allora è un errore
		if(!$user)
			return false;

		$this->auth_session_user($user["id"], ($full_auth ? 3 : 2), $user["level"],
			array_intersect_key($user,
				array_flip($this->settings["user_class"]["requested"])
			)
		);
		$this->cookie_creation("user");

		return $user;
	}
	// CONNESSIONE NECESSARIA
	public function form_register($ins_email="",$ins_username="",$ins_password=""){
		// TRUE: non ha senso, è già registrato
		if($this->check_auth()){
			$this->add_warning(self::WARNING_ALREADY_LOGGED);
			return true;
		}
		//connessione mancante
		if(!$this->cn || !$this->user_class){
			$this->add_error(self::ERROR_CN_ERROR);
			return false;
		}
		// mancano dei dati obbligatori
		if($ins_email=="" || $ins_username=="" || $ins_password==""){
			$this->add_error(self::ERROR_EMPTY_LOGIN);
			return false;
		}
		// sanitizzo
		$ins_email=$this->sanitize($ins_email);
		$ins_username=$this->sanitize($ins_username);
		// controllo username e email che siano univoci
		if($this->user_class->check_user_exist($ins_username, $ins_email))
			$this->add_error(self::ERROR_TAKEN_LOGIN);

		//FALSE: qualcosa è andato storto
		if($this->check_errors())
			return false;

		//CREO UTENTE
		list($success, $temp_id) = $this->user_class->insert_user(
			$ins_email,
			$ins_username,
			$ins_password
		);
		//TODO: l'unica profilazione posso averla fatta tramite la sessione (che inserisce adesso i dati nel DB)
		if(!$success){
			$this->add_error($success);
			return false;
		}
		if(!$temp_id){
			$this->add_error(self::ERROR_UNKNOWN);
			return false;
		}

		//ottengo le info dell'utente appena inserito
		$user = $this->user_class->find_user_byid($temp_id);

		//faccio come se avesse appena fatto il login dal form, è fully autenticato
		$this->auth_session_user($temp_id, 3, $user["level"],
			array_intersect_key($user,
				array_flip($this->settings["user_class"]["requested"])
			)
		);
		$this->cookie_creation("user");
		return true;
	}












	public function activate_pageauth($page_auth_array=false){
		/*
		"page_auth" 	=> [
			//default = quando la pagina non è tra quelle nella whitelist
			"default" => [
				"auth" => 3,
				"level" => 9
			],
			//devi essere un admin autenticato
			"admin" => [
				"auth" => 3,
				"level" => 9
			],
			//va sempre lasciato con tutto a 0
			"login" => [
				"auth" => 0,
				"level" => 0
			]
		]
		*/
		//array di pagine con impostazioni di accesso in base all'autorizzazione e al livello
		//false = faccio manualmente pagina per pagina
		$this->page_auth=$page_auth_array;
	}
	//in caso di header('Location: login.php'); occhio ai loop infiniti, permetti login.php a tutti!
	public function page_check($current_page=false){
		if(!$current_page)
			$current_page=$this->get_currentpage();
		if(!$this->page_auth)
			return true;

		//l'admin va dove gli pare
		if( ($this->admin_level == $this->get_level()) &&
			($this->max_auth == $this->get_auth()))
			return true;

		//controllo se la pagina attuale ha delle istruzioni
		if(isset($this->page_auth[$current_page]))
			//le ha
			if( ($this->page_auth[$current_page]["level"] <= $this->get_level()) &&
				($this->page_auth[$current_page]["auth"] <= $this->get_auth()))
				return true;
		else
			//non le ha, quindi controllo le opzioni di default
			if( ($this->page_auth["default"]["level"] <= $this->get_level()) &&
				($this->page_auth["default"]["auth"] <= $this->get_auth()))
				return true;

		//qualcosa non va, dovrebbe fermarsi a "default"
		return false;
	}








	//TODO
	public function activate_history($page_auth_array=false){
		//storico movimentazione utente
		//se la pagina non è nell'array $page_auth, l'accesso è garantito di default
		$this->history=[];
	}
	//sono in una nuova pagina, aggiorno la cronologia (pagina, azione e orario (timestamp))
	//posso ricavare quando un'azione è stata fatta e per quanto tempo si è rimasti su una pagina
	public function history_update($action){
		$this->history[time()][]=[
			$action,
			$this->get_currentpage(),
		];
		return true;
	}

}


/**
* true = 	salvo nel database un utente appena approda sul sito,
*			se poi si registrerà sul serio, collegherò i dati con un update, altrimenti
*			lo eliminerò dopo un tot di tempo (TODO)
* false = 	profilerò tramite $_SESSION e $_cookie_expiration al massimo, unirò i dati in una eventuale registrazione,
*			ma non creererò un campo nel database ad ogni  nuovo utente
*			alla registrazione sarà un utente nuovo a cui abbinare i dati nei cookie
**/
//database NECESSARIO
class Session_Smart extends Session{
	public function form_register($ins_email="",$ins_username="",$ins_password=""){
		// TRUE: non ha senso, è già registrato
		if($this->check_auth()){
			$this->add_warning(self::WARNING_ALREADY_LOGGED);
			return true;
		}
		//connessione mancante
		if(!$this->cn || !$this->user_class){
			$this->add_error(self::ERROR_CN_ERROR);
			return false;
		}
		// mancano dei dati obbligatori
		if($ins_email=="" || $ins_username=="" || $ins_password=="")
			$this->add_error(self::ERROR_EMPTY_LOGIN);
		// sanitizzo
		$ins_email=$this->sanitize($ins_email);
		$ins_username=$this->sanitize($ins_username);
		// controllo username e email che siano univoci
		if($this->user_class->check_user_exist($ins_username, $ins_email))
			$this->add_error(self::ERROR_TAKEN_LOGIN);

		//FALSE: qualcosa è andato storto
		if($this->check_errors())
			return false;

		//UPDATO UTENTE
		//un utente già profilato si è registrato, invece di inserire un nuovo utente modifico quello già creato in precedenza per l'anonimo
		list($success, $temp_id) = $this->user_class->insert_user_smart(
			$_SESSION[$this->name]["id"],
			$ins_email,
			$ins_username,
			$ins_password
		);
		if(!$success){
			$this->add_error($success);
			return false;
		}
		if(!$temp_id){
			$this->add_error(self::ERROR_UNKNOWN);
			return false;
		}

		//ottengo le info dell'utente appena inserito
		$user = $this->user_class->find_user_byid($temp_id);

		//faccio come se avesse appena fatto il login dal form, è fully autenticato
		$this->auth_session_user($temp_id, 3, $user["level"],
			array_intersect_key($user,
				array_flip($this->settings["user_class"]["requested"])
			)
		);

		$this->cookie_creation("user");
		return true;
	}

	protected function create_user_session_anon(){
		// se non ci sono dei cookie registro come user anonimo con level=0,
		// così intanto inizio a profilarlo in attesa di una registrazione o login seria
		list($success, $temp_id) = $this->user_class->insert_user_anon($this->settings["username"]);
		if($temp_id)
			$this->auth_session_anon($temp_id, 0, 1);
		else
			$this->add_error($result);
		return true;
	}
}
