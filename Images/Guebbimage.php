<?php

/*
							ATTENZIONE
La conversione è un processo lungo, anche mettendo max_execution_time
bisogna stare attenti a non fare troppe richieste.
La cosa migliore sarebbe un'immagine per volta e fare il loading con javascript
*/

namespace Guebbit\Images;

use Guebbit\Base;

abstract class Guebbimage extends Base{
	public $initial_settings=array();	// array delle opzioni decise dall'utente all'inizializzazione
	public $defaults=[
		"debug" => false,
		"max_execution_time" => 200,	//maximum seconds for php scripts before fail
		"image" => [
			"size" => false,
			"quality" => 100,
			"watermark" => [
				"image" => false,
				"options" => [
					"position" => "bottom right",	//watermark position
					"opacity" => 1,					//watermark opacity
					"size" => "10%",				//watermark size (% rispetto all'immagine contenitore)
					"padding" => "2%",				//watermark padding from border
					"pos_paddings" => [
						"bottom right" => [-1, -1],
						"bottom left" => [1, -1],
						"top right" => [-1, 1],
						"top left" => [1, 1],
						"center" => [0, 0]
					]
				],
			]
		]
	];

	public $files=array();			// array dei files (può cambiare)
	public $history=array();		// array dei files (non può cambiare)
	protected $method=false;

	// ----------- costants -----------
	//static::CONSTANT_NAME
	public const ERROR_UNKNOWN = "Unknown error";
	public const ERROR_GENERIC = "Contact support. Error: ";
	public const ERROR_MISSING_CLASS = "Classe mancante: ";
	public const ERROR_MISSING_FILE = "File mancante: ";

    public function __construct($settings=[]){
		$this->initial_settings=$settings;
		$this->settings=array_replace_recursive($this->defaults, $this->initial_settings);

		//controllo che ci siano tutte le dipendenze necessarie
		$this->check();

		ini_set('max_execution_time', $this->settings["max_execution_time"]);
		$this->time=microtime(true);
		return $this;
	}


	/**
	* 	Eseguo i vari controlli per capire se la classe ha tutte le dipendenze di cui necessita
	* 	@return bool se ok o meno
	**/
	protected function check(){
		if(!class_exists("\SimpleImage")){
			throw new \Exception(static::ERROR_MISSING_CLASS."SimpleImage");
			return false;
		}
		return true;
	}


	/**
	* 	A meno di possedere un servizio a pagamento, è necessario utilizzare la classe keychain
	* 	@param array $fromFileArray = array dei path dei file da convertire
	* 	@param string $toPath = se presente, è la cartella in cui mettere i file convertiti,
	*							altrimenti sovrascrivo i file vecchi.
	*	ATTENZIONE: NON mantengo il percorso dei vecchi file, verranno messi tutti nella stessa cartella (se specificata)
	* 	@return bool se ha funzionato o meno
	**/
	public function prepare($fromFileArray, $toPath=false){
		//resetto
		$this->history=$this->files=[];
		//se non specificato, sovrascrivo l'immagine
		foreach($fromFileArray as $fromFile){
			$fromPath=dirname($fromFile);
			$filename=basename($fromFile);
			$targetFolder=($toPath ? $toPath : $fromPath);
			//controllo innanzitutto che sia un'immagine
			//poi entro nella cartella e, se non esiste, la creo
			//sopprimo il warning nel caso la cartella non vada bene, perché l'errore lo gestisco in un altro modo
			if(file_exists($fromFile)){
				if(getimagesize($fromFile) && (file_exists($targetFolder) || @mkdir($targetFolder, 0777, true))){
					$this->files[]=[
						"from" => 	$fromFile,
						"to" => 	$targetFolder.DIRECTORY_SEPARATOR.basename($filename)
					];
				}else{
					// errore permessi o path errato (crea solo la 1° cartella se non c'è, non tutte)
					$this->output["error"][]=static::ERROR_GENERIC."mkdir fail";
				}
			}else{
				$this->output["error"][]=static::ERROR_MISSING_FILE.": ".$fromFile;
			}

		}
		return $this;
	}


	/**
	* 	Gestisce controlli e necessità comuni a tutte le funzioni di conversione.
	* 	@param function $convertFunction = la funzione che contiene le varie azioni di conversione
	**/
	abstract protected function wrapper($convertFunction);

	/**
	*	Switch con le varie funzioni in base alla conversione richiesta con $this->method
	* 	Deve solo convertire. Opzioni varie, preliminari e controlli fatti in $this->prepare,
	*	ulteriori necessità sistemate in $this->wrapper
	**/
	abstract public function convert();



	// --------------------------------------------
	// -------- SINGLE IMAGE FUNCTION--------------
	// --------------------------------------------

	/**
	* 	Copio una singola immagine nella posizione indicata
	* 	@param integer $id = l'id del $this->files
	**/
	protected function single_move($id){
		if($this->files[$id]["from"] != $this->files[$id]["to"])
			copy($this->files[$id]["from"], $this->files[$id]["to"]);
		return true;
	}

	/**
	* 	Eseguo un resize dell'immagine richiesta
	* 	@param integer $id = l'id del $this->files
	* 	@return string la posizione della nuova immagine
	**/
	protected function single_resize($id){
		try {
			//Creo l'immagine con la nuova forma, nel luogo indicato
			$image=new \SimpleImage();
			$image
				->fromFile($this->files[$id]["from"])
				->bestFit($this->settings["image"]["size"],$this->settings["image"]["size"])
				->toFile($this->files[$id]["to"], mime_content_type($this->files[$id]["from"]), $this->settings["image"]["quality"]);
			$this->files[$id]["from"]=$this->files[$id]["to"];
		} catch(Exception $err) {
			$this->output["error"][]=static::ERROR_GENERIC."[SimpleImage resize] (".$this->files[$id]["from"].") ".$err->getMessage();
			$this->single_move($id);
		}
		return $this->files[$id]["to"];
	}


	//eseguo sulla singola immagine
	protected function single_watermark($id){
		//Creo l'immagine con la nuova forma, nel luogo indicato
		try {
			$image = new \SimpleImage();
			$wtmrk = new \SimpleImage();
			$image
				->fromFile($this->files[$id]["from"]);
				// ------ WATERMARK ------
				$w_height=$w_width=$this->settings["image"]["watermark"]["options"]["size"];
				//se si tratta di una %
				if(!is_int($this->settings["image"]["watermark"]["options"]["size"])){
					$w_width=ceil(($image->getWidth()*(int)$w_width)/100);
					$w_height=ceil(($image->getHeight()*(int)$w_height)/100);
				}
				$wtmrk->fromFile($this->settings["image"]["watermark"]["image"])
					->bestFit($w_width,$w_height);
				//il padding con il bordo
				$temp=$this->settings["image"]["watermark"]["options"]["padding"];
				//se si tratta di una %
				if(!is_int($this->settings["image"]["watermark"]["options"]["padding"]))
					$temp=ceil((($image->getWidth()+$image->getHeight())*(int)$temp)/200);
			//se è una % o un numero da 1 a 100, converto:
			if(!is_float($this->settings["image"]["watermark"]["options"]["opacity"]))
				$this->settings["image"]["watermark"]["options"]["opacity"]=(int)$this->settings["image"]["watermark"]["options"]["opacity"]/100;
			$image
				->overlay(
					$wtmrk,	//SimpleImage watermark object
					$this->settings["image"]["watermark"]["options"]["position"],
					$this->settings["image"]["watermark"]["options"]["opacity"],
					//paddings
					$temp*$this->settings["image"]["watermark"]["options"]["pos_paddings"][$this->settings["image"]["watermark"]["options"]["position"]][0],
					$temp*$this->settings["image"]["watermark"]["options"]["pos_paddings"][$this->settings["image"]["watermark"]["options"]["position"]][1]
				)
				//PNG da problemi e tendenzialmente non serve, quindi converto in jpg
				->toFile($this->files[$id]["to"], "image/jpeg", $this->settings["image"]["quality"]);
				return $this->files[$id]["to"];
		} catch(Exception $err) {
			// Handle errors
			$this->output["error"][]=static::ERROR_GENERIC."[SimpleImage watermark] (".$this->files[$id]["from"]." - ".$this->settings["image"]["watermark"]["image"].") ".$err->getMessage();
			$this->single_move($id);
		}

		return false;
	}


	// --------------------------------------------
	// ------------- USER OPTIONS -----------------
	// --------------------------------------------

	/**
	* 	cambio la destinazione dei nuovi file
	* 	@param string $path = destinazione
	**/
	public function setDestination($path){
		// Nel caso non esista, creo il nuovo path
		if(file_exists($path) || @mkdir($path, 0777, true))
			foreach($this->files as $id => $file)
				$this->files[$id]["to"]=$path.DIRECTORY_SEPARATOR.basename($this->files[$id]["to"]);
		else
			$this->output["error"][]=static::ERROR_GENERIC."mkdir fail";
		return $this;
	}

	/**
	*	resetto le varie impostazioni
	**/
	public function reset(){
		$this->files=$this->history;
		$this->settings=array_replace_recursive($this->defaults, $this->initial_settings);
		//echo "<pre>".print_r($this->settings,1)."</pre>";
		return $this;
	}

	/**
	* 	ridimensionamento delle nuove immagini
	* 	@param integer $size = pixels. If not specified = reset
	**/
	public function resize($size=false){
		$this->settings["image"]["size"]=$size;
		return $this;
	}

	/**
	* 	ridimensionamento delle nuove immagini
	* 	@param integer $size = pixels. If not specified = reset
	**/
	public function quality($quality=100){
		$this->settings["image"]["quality"]=$quality;
		return $this;
	}


	//immetto le impostazioni riguardo i watermark per le prossime immagini finché non viene richiamata
	public function watermark($watermark, $settings=[]){
		//resetto le impostazioni iniziali
		$this->settings["image"]["watermark"]["options"]=array_replace_recursive($this->defaults["image"]["watermark"]["options"],$settings);
		//inserisco le nuove
		$this->settings["image"]["watermark"]["image"]=$watermark;
		$this->settings["image"]["watermark"]["options"]=array_replace_recursive($this->settings["image"]["watermark"]["options"],$settings);
		return $this;
	}

	public function instagram($mode="post"){
		switch ($post) {
			case 'post':
				$this->method="instagram_post";
			break;
			case 'story':
				$this->method="instagram_story";
			break;
			default:break;
		}
	}
}
