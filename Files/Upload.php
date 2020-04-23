<?php

/*
https://stackoverflow.com/questions/38509334/full-secure-image-upload-script


Level 3: Read first 100 bytes and check if they have any bytes in the following range: ASCII 0-8, 12-31 (decimal).
Level 4: Check for magic numbers in the header (first 10-20 bytes of the file). You can find some of the files header bytes from here:
http://en.wikipedia.org/wiki/Magic_number_%28programming%29#Examples
You might want to run "is_uploaded_file" on the $_FILES['my_files']['tmp_name'] as well. See
http://php.net/manual/en/function.is-uploaded-file.php

*/



namespace Guebbit\Files;

use Guebbit\Base;
use function Guebbit\Files\file_info;

class Upload extends Filecall{
	//array di path/filename dove guardare (Download) o dove uploadare (Upload)
	protected $targetFolder="";

	// ----------- costants -----------
	//self::CONSTANT_NAME
	public const ERROR_NO_UPLOAD	= "No files uploaded";
	public const ERROR_WRONG_FILE	= "Wrong file type";
	public const ERROR_SIZE_LIMIT	= "Exceeded filesize limit";
	public const ERROR_NUM_LIMIT	= "Exceeded file number limit";
	public const ERROR_MOVE_FAIL	= "Failed to move uploaded file";
	public const ERROR_INVALID_PARAMS = "Invalid parameters";

    public function __construct($settings=[]){
		$this->settings=array_replace_recursive([
			/**
			*	Store files outside of domain (inaccessible by anyone except the server)
			*	same folder: dirname(__FILE__).DIRECTORY_SEPARATOR
			**/
			"root"				=> "/",
			"max_upload_size" 	=> 100000000,	//100MB
			"max_upload_num" 	=> false,		//max number of files
			/**
			*	Livello di sicurezza dell'upload.
			*	Usare database o altri sistemi per accedere al file col nuovo nome
			*
			*	0:
			*		don't change filename
			*	1:
			*		change filename
			*	2:
			*		change filename
			*		remove extension (WARNING: scaricare il file senza nome da filezilla lo corrompe)
			**/
			"security" => 1,
			/**
			*	whitelist sui file che è possibile uploadare
			**/
			"permitted"	=> [
				"image/jpeg",
				"image/png",
				"image/gif"
			]
		], $settings);
		return $this;
	}







	/**
	*	Type of error
	*	@param integer $error
	*	@return
	**/
	protected function file_error_value($error){
		switch ($error) {
			case UPLOAD_ERR_OK:
				return false;
			break;
			case UPLOAD_ERR_NO_FILE:
				return $this->add_error(self::ERROR_NO_UPLOAD);
			break;
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return $this->add_error(self::ERROR_SIZE_LIMIT);
			break;
			default:
				return $this->add_error(self::ERROR_UNKNOWN);
			break;
		}
		return false;
	}

	/**
	*	secure way to get mimetype
	*	@param FILE $file
	*	@return string mime
	**/
	protected function get_mimetype($file){
		$realpath = realpath($file);
		// Use the Fileinfo PECL extension (PHP 5.3+)
		if ( $realpath
			&& function_exists( 'finfo_file' )
			&& function_exists( 'finfo_open' )
			&& defined( 'FILEINFO_MIME_TYPE' ))
			return finfo_file( finfo_open( FILEINFO_MIME_TYPE ), $realpath );
		//normal way
		if ( function_exists( 'mime_content_type' ) )
			return mime_content_type( $realpath );
		return false;
	}


	protected function check_file($file){
		// Undefined | Multiple Files | $_FILES Corruption Attack
		// If this request falls under any of them, treat it invalid.
		if (!isset($file['error']) || is_array($file['error'])){
			$this->add_error(self::ERROR_INVALID_PARAMS);
			return false;
		}
		// Type of error (false = no errors)
		if($temp = $this->file_error_value($file['error']))
			$this->add_error($temp);

		// DO NOT TRUST $_FILES[xxx]['mime'] VALUE
		// Check MIME Type by yourself.
		$temp = $this->get_mimetype($file["tmp_name"]);
		//$pattern_for_images = "#^(image/)[^\s\n<]+$#i";
		if(!empty($this->settings["permitted"]) && !in_array($temp, $this->settings["permitted"])){
			$this->add_error(self::ERROR_WRONG_FILE.": ".$temp);
			return false;
		}

		return true;
	}

	/**
	* 	Controllo gli errori nei file uploadati
	* 	@param FILES $files da controllare
	* 	@return @param bool se ci sono stati errori o no
	**/
	protected function check_all_files($files=[]){
		//controllo che ci siano
		if(count($files) < 1){
			$this->add_error(self::ERROR_NO_UPLOAD);
			return false;
		}
		//controllo che if iles non siano troppi
		if($this->settings["max_upload_num"] && count($files) > $this->settings["max_upload_num"]){
			$this->add_error(self::ERROR_NUM_LIMIT);
			return false;
		}
		//serious tests (nessuna Exception deve venire ignorata)
		try {
			$files_total_size=0;
			foreach($files as $file){
				$files_total_size+=$file['size'];
				if(!$this->check_file($file))
					return false;
			}
		} catch (Exception $e) {
			$this->add_error(self::ERROR_SECURITY.": ".$e->getMessage());
			return false;
		}
		//controllo che i file non siano troppo pesanti
		if($this->settings["max_upload_size"] && $files_total_size > $this->settings["max_upload_size"]){
			$this->add_error(self::ERROR_SIZE_LIMIT);
			return false;
		}
		//andato tutto bene
		return true;
	}


	/**
	*	Create the folder (if not exists)
	*	@param string $folder
	*	@return boolean
	**/
	protected function create_folder($folder){
		try {
			//entro nella cartella e, se non esiste, la creo				//sopprimo il warning nel caso la cartella non vada bene, perché l'errore lo gestisco in un altro modo
			if(!file_exists($this->settings["root"].$this->targetFolder) && !mkdir($this->settings["root"].$this->targetFolder, 0777))
				throw new \Exception(" ");
		} catch (Exception $e) {
			$this->add_error(self::ERROR_GENERIC."[mkdir] ".$e->getMessage());
			return false;
		}
		return true;
	}

	/**
	* 	Upload all files
	* 	@param FILE $uploaded = $_FILES
	* 	@param string $this->targetFolder = root, cartella in cui mettere tutto
	* 	@return bool se ha funzionato o meno
	**/
	public function upload($uploaded, $targetFolder=false){
		if($targetFolder)
			$this->targetFolder=$targetFolder.DIRECTORY_SEPARATOR;

		if(!$this->create_folder($this->targetFolder))
			return false;

		//there can be multiple input[type=file]
		//(and $_FILES is an Array)
		foreach($uploaded as $files){
			//elimino i files vuoti
			//siccome l'array dei files è di default molto confusionario, lo correggo in un modo più standard
			$files=$this->reArrayFiles($files);
			//echo "<pre>".print_r($files, 1)."</pre>";

			if(!$this->check_all_files($files))
				return false;

			foreach($files as $this_file){
				$temp=$this->single_upload($this_file, $this->targetFolder);
				$this->output["requested"][$temp["filename"]]=$temp;
			}

		}

		return true;
	}



	/**
	* 	Upload single file
	*	WARNING: AFTER $this->reArrayFiles
	* 	@param FILE $file = single file be uploaded
	* 	@return bool se ha funzionato o meno
	**/
	protected function single_upload($file, $path=""){
		// salvo il file, eventualmente cambio il filename per questioni di sicurezza
		// il nome vero verrebbe perso, ma il mime_content_type no.
		$filename = $this->secure_filename($file['name'], $path, $this->settings["security"]);

		//sposto il file nella sua destinazione finale.
		if(!move_uploaded_file($file['tmp_name'], $this->settings["root"].$path.$filename)){
			$this->add_error(self::ERROR_GENERIC." [move_uploaded_file]");
			return false;
		}

		//various info
		return [
			"oldname" 	=> $file['name'],
			"filename"	=> $filename,
			"path"		=> $this->settings["root"].$path,
			"src" 		=> $path.$filename,
			"data" 		=> $file,
			"pathinfo"	=> file_info($this->settings["root"].$path.$filename)
		];
	}



	/**
	*
	*
	**/
	protected function reArrayFiles(&$file_array) {
		$newOrder = [];
		foreach($file_array as $key => $all)
	        foreach($all as $i => $val)
	            $newOrder[$i][$key] = $val;
		return $newOrder;
	}

	/**
	*	Cambio il filename in base alle impostazioni di sicurezza
	**/
	protected function secure_filename($filename, $path, $security=1){
		$filename=$this->sanitize($filename, "filename");
		switch ($security) {
			//case 0: //rimane lo stesso break;
			case 1:
				return $this->change_filename(
					$path.DIRECTORY_SEPARATOR,
					pathinfo($filename)["extension"]
				);
			break;
			case 2:
				return $this->change_filename(
					$path.DIRECTORY_SEPARATOR
				);
			break;
		}
		return $filename;
	}

	/**
	*	Cambio il filename per questioni di sicurezza.
	*	Se esiste già lo cambio in modo recursivo (lo sovrascriverebbe), ma è molto difficile che accada
	**/
	protected function change_filename($where="", $ext=""){
		$filename = time()."_".$this->secure_hash(4);
		if($ext)
			$filename.= ".".$ext;
		if(file_exists($where.$filename))
			$filename = $this->change_filename($where, $ext);
		return $filename;
	}
	/*
	//safe unique name from its binary data.
	sprintf('/path/to/file/%s.%s',
		sha1_file($file['tmp_name']),
		$ext
	)
	*/
}
