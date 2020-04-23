<?php
/*
*						TODO
*		- gestire errori derivanti dai mancati permessi
*		- Possibilità di inviare query dirette
*		- possibilità di inviare più $table e fare join
*/
//ATTENZIONE: la security va fatta a livello del database con i permessi (riguardo a chi può modificare o rimuovere da quale tabella)

namespace Guebbit\Database;

use Guebbit\Base;

class Dbcall extends Base{
	// ----------- variabili della classe -----------
    protected $cn=false;			// connessione al database
	protected $table=false;

	// ----------- costants -----------
	//static::CONSTANT_NAME
	public const ERROR_NO_REQUESTED 	= "No columns requested";
	public const ERROR_DATABASE_EXPLAIN = "Database error: ";

    public function __construct($conn=false, $settings=[]){
		$this->settings=array_replace_recursive([
			"tablename"		=> false,
			"userid"		=> false,	//ID dell'utente che sta utilizzando il database
			"tables" => [	// array di flag di default (determinano il comportamento di alcune funzioni)
				"default" => [
					"idRecordName" => "id"
				],
				/*
				"[tablename]" => [
					"idRecordName" => "id",
					"columns" => [	//array of settings
						"[columnname]" => [
							"int" => true,		//\PDO::PARAM_STR (default), if true = \PDO::PARAM_INT
						]
					],
					"flags" => [	//array of flags
						"output",		// è richiesto qualcosa indietro dalla query
						"control",		// TODO aggiorna eventuali userins, usermod, datains e datamod (se "true" e non ci sono, da errore)
						"archive"		// TODO cambia il comportamento nel caso non vengano mai modificati\rimossi dei valori ma solo archiviati
					]
				]
				/**/
			],
		],$settings);
		$this->table = $this->sanitize($this->settings["tablename"]);
		if($conn) $this->connectTo($conn);
		return $this;
	}

	// -------------------- CONNECT --------------------
	//mi connetto al database
	public function connect($db_host, $db_name, $db_user, $db_pass, $timezone=false){
    	$this->cn = new \PDO("mysql:host=".$db_host.";charset=utf8;dbname=".$db_name, $db_user, $db_pass);
        if(($timezone)&&(!$this->cn->query("SET time_zone='.$timezone.'")))
                                              $this->output["error"][]=static::UNKNOWN_ERROR;
		// Silent Mode (\PDO::ERRMODE_SILENT) – sets the internal error code but does not interrupt the script’s execution (this is the default setting)
		// Warning Mode (\PDO::ERRMODE_WARNING) – sets the error code and triggers an E_WARNING message
		// Exception Mode (\PDO::ERRMODE_EXCEPTION) – sets the error code and throws a \PDOException object
		//$cn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return true;
    }
    //passo una connessione già esistente
    public function connectTo($connection){
        $this->cn=$connection;
        return true;
    }

	/**
	* 	Gestione tabelle, crea @param array output
	* 	@param array le colonne richieste
	*		se tra questi c'è "id" o l'id della tabella, l'array output invece di essere progressivo è basato sull'id del record
	* 	@param array eventuali filtri [ "colonna" => "valore" ]
	* 	@return bool se ha funzionato o meno
	**/
	public function load($requested=false, $search=false){ 	//$this->table
		$params=array();
		$query=$query2="";
		//cosa voglio selezionare
		if($requested){
			//filtro eventuali duplicati
			$requested=array_unique($requested);
			if(is_array($requested)){
				//chiedo di default l'id
				$query.=$this->find_id($this->table).", ";
				foreach($requested as $recordName){
					$query.=$this->sanitize($recordName).", ";
				}
				$query=substr($query,0,-2);
			}else{
				//mysql, es. "tablename, tablename2"
				$query.=$requested;
			}
			//eventuale filtro
			if($search){
				if(is_array($search)){
					$query2="WHERE ";
					foreach($search as $recordName => $recordValue){
						//JOLLY: con id intendo l'id della tabella, qualunque esso sia
						if($recordName=="id"){
							$recordName=$this->find_id($this->table);
						}
						if($recordValue){
							//così rimangono in ordine
							$query2.=$this->sanitize($recordName)." = ? AND ";
							if(isset($this->settings["tables"][$this->table]["columns"][$recordValue]["int"])){
								$params[]=[
									$this->sanitize($recordValue),
									\PDO::PARAM_INT
								];
							}else{
								$params[]=[
									$this->sanitize($recordValue),
									\PDO::PARAM_STR
								];
							}
						}else{
							//JOLLY: con "null" intendo valore vuoto
							$query2.=$recordName." IS NULL AND ";
						}
					}
					$query2=substr($query2,0,-5);
				}else{
					//mysql, es. "WHERE id=5"
					$query2.=" ".$search;
				}
			}
		}else{
			$this->output["error"]=static::ERROR_NO_REQUESTED;
		}
		if(!$this->output["error"]){
			$this->execute([[
				"query"  => "SELECT ".$query." FROM ".$this->table." ".$query2,
				"params" => $params
			]],["requested-id"]);
			return true;
		}
		return false;
	}


	/**
	* 	Gestione tabelle, modifica una tabella in base ad un array associativo
	* 	@param array [id => [colonna => valore-modificato]]
	* 	@param array flags, eventuali istruzioni di utilizzo
	* 	@return bool se ha funzionato o meno
	**/
	//nessuna ricerca a parte la modifica via ID per motivi di sicurezza
	public function edit($parameters,$flags=[]){ 	//$this->table

		//cerco se esistono impostazioni particolari per questa [table] in questa modalità
		if(array_key_exists($this->table,$this->settings["tables"]) && array_key_exists("flags",$this->settings["tables"][$this->table]))
			$flags=array_merge($this->settings["tables"][$this->table]["flags"],$flags);
		$queryArray=[];
		// preparazione dati
		if($parameters){
			//per ogni riga
			foreach($parameters as $id => $this_parameter){
				$params=array();
				$query="";
				//per ogni colonna
				foreach($this_parameter as $recordName => $recordValue){
					if($recordValue){
						// controllo le impostazioni dei parametri che vado ad inserire (sicurezza)
						if(isset($this->settings["tables"][$this->table]["columns"][$recordValue]["int"])){
							$params[]=[
								$this->sanitize($recordValue),
								\PDO::PARAM_INT
							];
						}else{
							$params[]=[
								$this->sanitize($recordValue),
								\PDO::PARAM_STR
							];
						}
						$query.=$this->sanitize($recordName)." = ?, ";
					}else{
						//JOLLY: con "null" intendo valore vuoto
						$query.=$this->sanitize($recordName)." = NULL, ";
					}
				}
				//inserisco eventualmente le info di inserimento / modifica
				if(in_array("control", $flags) && $this->settings["userid"]){
					$query.="userins = ?, ";
					$params[]=[
						$this->sanitize($recordValue),
						\PDO::PARAM_STR
					];
				}
				$params[]=[
					$this->sanitize($id),
					\PDO::PARAM_STR
				];
				$queryArray[]=[
					"query"  => "UPDATE ".$this->table." SET ".substr($query,0,-2)." WHERE ".$this->find_id($this->table)." = ?",
					"params" => $params
				];
			}
		}else{
			$this->output["error"]=static::ERROR_NO_PARAMETERS;
		}

		if(!$this->output["error"]){
			$this->execute($queryArray);
			return true;
		}
		return false;
	}




	/**
	* 	Gestione tabelle, aggiunge delle righe alla tabella
	* 	@param array [id => [colonna => valore-modificato]], l'id in questo caso viene ignorato
	* 	@param array flags, eventuali istruzioni di utilizzo
	* 	@return bool se ha funzionato o meno
	**/
	public function add($parameters,$flags=[]){ 	//$this->table
		//cerco se esistono impostazioni particolari per questa [table] in questa modalità
		if(array_key_exists($this->table,$this->settings["tables"]) && array_key_exists("flags",$this->settings["tables"][$this->table]))
			$flags=array_merge($this->settings["tables"][$this->table]["flags"],$flags);

		$queryArray=[];
		// preparazione dati
		if($parameters){
			//per ogni riga
			foreach($parameters as $id => $this_parameter){
				$params=array();
				$query=$query2="";
				//per ogni colonna
				foreach($this_parameter as $recordName => $recordValue){
					if($recordValue){
						$query.=$this->sanitize($recordName).", ";
						// controllo le impostazioni dei parametri che vado ad inserire (sicurezza)
						if(isset($this->settings["tables"][$this->table]["columns"][$recordValue]["int"])){
							$params[]=[
								$this->sanitize($recordValue),
								\PDO::PARAM_INT
							];
						}else{
							$params[]=[
								$this->sanitize($recordValue),
								\PDO::PARAM_STR
							];
						}
						$query2.="?, ";
					}
				}
				//inserisco eventualmente le info di inserimento / modifica
				if(in_array("control", $flags) && $this->settings["userid"]){
					$query.="userins, ";
					$query2.="?, ";
					$params[]=[
						$this->settings["userid"],
						\PDO::PARAM_STR
					];
				}
				$queryArray[]=[
					"query"  => "INSERT INTO ".$this->table." (".substr($query,0,-2).") VALUES (".substr($query2,0,-2).")",
					"params" => $params
				];
			}
		}else{
			$this->output["error"]=static::ERROR_NO_PARAMETERS;
		}

		if(!$this->output["error"]){
			$this->execute($queryArray,["last_id"]);
			return true;
		}
		return false;
	}



	/**
	* 	Gestione tabelle, rimuove righe dalla tabella
	* 	@param array $parameters: array di id oppure array di istruzioni su cui poi cercare i valori
	* 	@return bool se ha funzionato o meno
	**/
	public function remove($parameters){ 	//$this->table
		$query="";
		//capisco se si tratta di un array di id o di istruzioni precise
		if(array_keys($parameters) !== range(0, count($parameters) - 1)){
			foreach($parameters as $recordName => $recordValue){
				//JOLLY: con id intendo l'id della tabella, qualunque esso sia
				if($recordName=="id"){
					$recordName=$this->find_id($this->table);
				}
				if($recordValue){
					//così rimangono in ordine
					$query.=$this->sanitize($recordName)." = ? AND ";
					if(isset($this->settings["tables"][$this->table]["columns"][$recordValue]["int"])){
						$params[]=[
							$this->sanitize($recordValue),
							\PDO::PARAM_INT
						];
					}else{
						$params[]=[
							$this->sanitize($recordValue),
							\PDO::PARAM_STR
						];
					}
				}else{
					//JOLLY: con "null" intendo valore vuoto
					$query.=$recordName." IS NULL AND ";
				}
			}
			$query=substr($query,0,-5);
		}else{
			foreach($parameters as $id){
				$query.=$this->find_id($this->table)." = ? OR ";
				$params[]=[
					$this->sanitize($id),
					\PDO::PARAM_STR
				];
			}
			$query=substr($query,0,-4);
		}

		if(!$this->output["error"]){
			$this->execute([[
				"query"  => "DELETE FROM ".$this->table." WHERE ".$query,
				"params" => $params
			]]);
			return true;
		}
		return false;
	}


	/**
	* 	Gestione tabelle, eseguo le query create nei metodi precedenti
	* 	@param array $query array con query e parametri necessari
	* 	@param array flags, eventuali istruzioni di utilizzo
	* 	@return bool se ha funzionato o meno
	**/
	protected function execute($query=[],$flags=[]){
		if(!empty($query)){
			if(!is_array($query))	$query=[["query"=>$query]];
			//tante query insieme, faccio ogni query in modo separato
			foreach($query as $q){
				$this->output["debug"][]=[
					"query" => $q["query"],
					"params" => $q["params"]
				];
				if(in_array("debug", $flags)) continue;	//se debug, non voglio che continui
				$result = $this->cn->prepare($q["query"]);
				foreach($q["params"] as $param => &$value) {
					$result->bindParam($param+1, $value[0], $value[1]);
				}
				$result->execute();
				$temp_error = $this->cn->errorInfo();
				if (!is_null($temp_error[2])) {
					$this->output["error"]=static::ERROR_DATABASE_EXPLAIN.": ".$temp_error[2];
					return false;
				}else{
					//ritorno gli id inseriti
					if(in_array("last_id", $flags)){
						if(!$this->cn->lastInsertId()){
							$this->output["error"]=static::ERROR_UNKNOWN;
						}else{
							$this->output["requested"]["last_id"][]=$this->cn->lastInsertId();
						}
					}
					//in ordine
					if(in_array("requested", $flags)){
						$i=0;
						while($row = $result->fetch(\PDO::FETCH_ASSOC)) {
							foreach($row as $col => $value){
								$this->output["requested"][$i][$col]=$value;
							}
							$i++;
						}
					}
					//basati sull'id
					if(in_array("requested-id", $flags)){
						$idRecordName=$this->find_id($this->table);
						while($row = $result->fetch(\PDO::FETCH_ASSOC)) {
							foreach($row as $col => $value){
								$this->output["requested"][$row[$idRecordName]][$col]=$value;
							}
						}
					}
				}
			}//foreach

			return true;
		}
		return false;
	}

	// -------------------- funzioni generiche --------------------

	//restituisco l'id di una tabella
	protected function find_id($table){
		//se la tabella non ha un id specifico, gli do quello di default
		$idRecordName=$this->settings["tables"]["default"]["idRecordName"];
		//controllo se esiste la tabella in questo array, e returno il risultato
		if(array_key_exists($table,$this->settings["tables"]) && array_key_exists("idRecordName",$this->settings["tables"][$table]))
			$idRecordName=$this->settings["tables"][$table]["idRecordName"];

		return $idRecordName;
	}
}
