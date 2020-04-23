<?php

/*
							ATTENZIONE
La conversione è un processo lungo, anche mettendo max_execution_time
bisogna stare attenti a non fare troppe richieste.
La cosa migliore sarebbe un'immagine per volta e fare il loading con javascript
*/

namespace Guebbit;

//use Guebbit\Lorem\Ipsum;
//use Guebbit\Lorem\Ipsum as Test;
//use function Guebbit\Lorem\Ipsum;

abstract class Base{
	// ----------- costants -----------
	//static::CONSTANT_NAME
	public const ERROR_UNKNOWN 			= "Unknown error";
	public const ERROR_SECURITY 		= "Security error";
	public const ERROR_CN_ERROR			= "Failed database connection";
	public const ERROR_404_FOLDER		= "404 - Folder not found";
	public const ERROR_404_FILE			= "404 - File not found";
	public const ERROR_NO_PARAMETERS	= "No parameters sent";
	public const ERROR_MISSING_CLASS	= "Classe mancante: ";
	public const ERROR_GENERIC			= "Generic Error: ";
	public const ERROR_DATABASE_EXPLAIN = "Database error: ";

	// ----------- variables -----------
	protected $settings=array();		// array delle opzioni
	protected $output=[	// array di possibili messaggi
		"info" 		=> [],
		"error" 	=> [],		// errori
		"warning" 	=> [],
		"requested" => [],		//requested data
		"debug"		=> []		//debug infos
	];


	//controllo se si tratta di un url oppure no
	public function isUrl($string){
		return isUrl($string);
	}
	public function sanitize($string, $mode=false) {
		return sanitize($string, $mode=false);
	}
	// secure random hash
	public function secure_hash($n){
		return secure_hash($n);
	}



	// ----------------- DATABASE -----------------
	/**
	* 	Mi connetto al database
	* 	@param string $db_host, $db_name, $db_user, $db_pass: i dati per entrare nel database
	*	@param string $timezone: ?
	* 	@return bool se ha funzionato o meno
	**/
	public function connect($db_host, $db_name, $db_user, $db_pass, $timezone=false){
    	$this->cn = new PDO("mysql:host=".$db_host.";charset=utf8;dbname=".$db_name, $db_user, $db_pass);
        if(($timezone)&&(!$this->cn->query("SET time_zone='.$timezone.'"))){
			$this->error[]=static::ERROR_UNKNOWN;
			return false;
		}
		// Silent Mode (\PDO::ERRMODE_SILENT) – sets the internal error code but does not interrupt the script’s execution (this is the default setting)
		// Warning Mode (\PDO::ERRMODE_WARNING) – sets the error code and triggers an E_WARNING message
		// Exception Mode (\PDO::ERRMODE_EXCEPTION) – sets the error code and throws a PDOException object
		//$cn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return true;
    }
    //passo una connessione già esistente
    public function connectTo($connection){
        $this->cn=$connection;
        return true;
    }



	// ----------------- GETTER & SETTER -----------------
	/**
	* 	Aggiungo settings in un 2° momento
	* 	@param array $settings array associativo di settings che verrà unito a quelle dell'oggetto
	**/
	public function set_settings($settings=[]){
		$this->settings=array_replace_recursive($this->settings, $settings);
		return $this;
	}
	public function get_settings(){
		return $this->settings;
	}


	// ----------------- OUTPUT -----------------
	//restituisco i risultati
	public function output($type=false){
		if(!$type)
			return $this->output;
		if(empty($this->output[$type]))
			return [];
		return $this->output[$type];
	}
	public function json_output($type=false){
		return json_encode($this->output($type));
	}


	// ----------------- DEBUGS -----------------
	// (add_output) aggiungo un messaggio
	public function add_output($type, $message){
		$this->output[$type][]=$message;
		return $this;
	}
	public function add_error($message){
		$this->add_output("error", $message);
		return $this;
	}
	public function add_warning($message){
		$this->add_output("warning", $message);
		return $this;
	}
	public function add_debug($cat, $message){
		$this->output["debug"][$cat][]=$message;
		return $this;
	}
}
