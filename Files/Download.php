<?php

namespace Guebbit\Files;

use Guebbit\Base;
use function Guebbit\Files\file_info;

class Download extends Filecall{
	// array di eventuali filtri ["image/jpeg","video/mp4","etc"]
	protected $targetFilters=[];
	// flag, applico o meno la recursione nella scan
	protected $recursive=false;
	//array di path/filename dove guardare (Download) o dove uploadare (Upload)
	protected $targetFolder=[];

	// ----------- costants -----------
	//static::CONSTANT_NAME
	public const ERROR_WRONG_FILE	= "Wrong file type";


	public function __construct($settings=[]){
		$this->settings=array_replace_recursive([
			//"root" => dirname(__FILE__).DIRECTORY_SEPARATOR,
			"root"				=> ""
		], $settings);
		return $this;
	}

	/**
	* 	Carico i file richiesti all'interno di una tabella, crea @param array output
	* 	@return bool se ha funzionato o meno
	**/
	public function load($targetFolder=[], $requested=[], $recursive=false){
		if(!is_array($targetFolder))
			$targetFolder=[$targetFolder];
		if(empty($targetFolder))
			$targetFolder=[""]; //la root

		$this->targetFolder=$targetFolder;
		$this->targetFilters=$requested;
		$this->recursive=$recursive;

		$this->_load();
		return $this;

	}

	protected function _load(){
		foreach($this->targetFolder as $path){
			$root_label="root";
			if(strlen($path) > 1)	$root_label=$path;
			//se esiste
			if(file_exists($this->settings["root"].$path)){
				//il contenuto della cartella.
				//applico reverse così sono in ordine e posso ricavare il percorso
				$folder=array_reverse($this->scan($this->settings["root"].$path, (int)$this->recursive));
				//echo printme($folder);die;
				//per ogni file dentro il path
				if(!empty($this->targetFilters)){
					//seleziono il tipo dei file che mi serve
					foreach($folder as $file){
						// le cartelle non mi interessano
						if(!is_file($file)) continue;
						if(in_array(mime_content_type($file), $this->targetFilters)){
							$pathinfo=file_info($file);
							$this->output["requested"][$root_label][]=array_merge($pathinfo, [
								"width"		=> $pathinfo["width"],
								"height" 	=> $pathinfo["height"],
								"filepath"	=> str_replace(array($this->settings["root"], basename($file)), "", $pathinfo["dirname"].DIRECTORY_SEPARATOR),
								"fileparent"=> basename(str_replace(array($this->settings["root"], basename($file)), "", $pathinfo["dirname"].DIRECTORY_SEPARATOR)),
							]);
						}else{
							$this->output["warning"][]=static::ERROR_WRONG_FILE.": ".$file;
						}
					}
				}else{	//!empty($this->targetFilters)
					//prendo tutti i files (gestito in questo modo per ottimizzare il tempo di esecuzione)
					foreach($folder as $file){
						// le cartelle non mi interessano
						if(!is_file($file)) continue;
						$pathinfo=file_info($file);
						$this->output["requested"][$root_label][]=array_merge($pathinfo, [
							//se non è un'immagine, questi saranno false
							"width"		=> $pathinfo["width"],
							"height" 	=> $pathinfo["height"],
							"filepath"	=> str_replace(array($this->settings["root"], basename($file)), "", $pathinfo["dirname"].DIRECTORY_SEPARATOR),
							"fileparent"=> basename(str_replace(array($this->settings["root"], basename($file)), "", $pathinfo["dirname"].DIRECTORY_SEPARATOR)),
						]);
					}
				} //!empty($this->targetFilters)
			}else{
				$this->output["error"][]=static::ERROR_404_FOLDER;
			}
		}
		if(!$this->output["error"]) return true;
		return false;
	}

	/**
	* 	Scandir avanzato RECURSIVO
	* 	@param string $dir = cartella da scannerizzare
	* 	@param string $recursive 0 = no recursione, X = recursione con deep X, "all" = recursione totale
	*	@param array $results = array con tutti i file e cartelle trovati
	* 	@return array $results
	**/
	public function scan($dir, $recursive=0, &$results = array()){
		$files = scandir($dir);
		foreach($files as $key => $value){
			$path = realpath($dir.DIRECTORY_SEPARATOR.$value);
			if(!is_dir($path)) {
				$results[] = $path;
			} else if($value !== "." && $value !== "..") {
				//decido se applicare la recursione oppure no
				//solo 1° livello (deepness)
				if($recursive===1)	$this->scan($path, 0, $results);
				//totale
				if($recursive===2)	$this->scan($path, $recursive, $results);
				$results[] = $path;
			}
		}
		return $results;
	}

}
