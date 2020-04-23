<?php

namespace Guebbit\Images;

class Tinyjpg extends Guebbimage{
	//Keychain DI
	protected $kc;
	// (10kb) limite entro cui non converto le foto con Tinyjpg
	public $minimum_size_treshold=10000;
	// (10mb) limite oltre cui non converto le foto con Tinyjpg
	public $maximum_size_treshold=10000000;	//TODO


	public function setKeychain(\Guebbit\Helpers\Keychain $kc){
		$this->kc=$kc;
	}

	protected function check(){
		if(!class_exists("SimpleImage")){
			throw new \Exception(static::ERROR_MISSING_CLASS."SimpleImage");
			return false;
		}
		if(!class_exists("\Tinify\Tinify")){
			throw new \Exception(static::ERROR_MISSING_CLASS."Tinify");
			return false;
		}
		return true;
	}

	protected function wrapper($convertFunction){
		//prendo le chiavi necessarie

		$keys=$this->kc->needMerged(count($this->files));
		$k=0;

		//se ci sono stati errori
		if($this->kc->errors()){
			foreach($this->kc->errors() as $error)
				$this->output["error"][]=static::ERROR_GENERIC."keychain FAIL: ".$error;
			return false;
		}else{
			foreach($this->files as $id => $file){
				//controllo che la chiave ci sia
				if(!array_key_exists($k,$keys))
					$this->output["error"][]=static::ERROR_GENERIC."keychain wrong request ".$k;
				//gestisco le eccezioni
				try {

					\Tinify\setKey($keys[$k]); //chiavi usa e getta

					// se è stata richiesta una modifica di qualche tipo, la applico e poi cambio
					// $this->files[$id]["from"] con la posizione del nuovo file
					if($this->settings["image"]["size"])
						$this->files[$id]["from"]=$this->single_resize($id);
					//idem con il watermark
					if($this->settings["image"]["watermark"]["image"])
						$this->files[$id]["from"]=$this->single_watermark($id);

					//eseguo la funzione in base alle impostazioni richieste
					$convertFunction($this->files[$id]["from"], $file["to"]);

				} catch(\Tinify\AccountException $e) {
				    // Verify your API key and account limit.
					$this->output["error"][]=static::ERROR_GENERIC."[account] ".$e->getMessage();
				} catch(\Tinify\ClientException $e) {
				    // Check your source image and request options.
					$this->output["error"][]=static::ERROR_GENERIC."[request] ".$e->getMessage();
				} catch(\Tinify\ServerException $e) {
				    // Temporary issue with the Tinify API.
					$this->output["error"][]=static::ERROR_GENERIC."[server] ".$e->getMessage();
				} catch(\Tinify\ConnectionException $e) {
				    // A network connection error occurred.
					$this->output["error"][]=static::ERROR_GENERIC."[connection] ".$e->getMessage();
				} catch(Exception $e) {
				    // Something else went wrong, unrelated to the Tinify API.
					$this->output["error"][]=static::ERROR_GENERIC."[generic] ".$e->getMessage();
				}

				$k++; //avanzo alla chiave successiva
				if($this->settings["debug"])
					$this->output["debug"]["time"][]=[
						$file["from"],
						$file["to"],
						(microtime(true)-$this->time)
					];
			}
		}
		return true;
	}



	public function convert(){
		switch ($this->method) {
			case 'instagram_post':

				// 1:1 ratio
				$this->output["error"][]="Tinyjpg al momento da errori su 'method => cover'";
				/*
				$this->wrapper(function($from,$to){
					if($this->isUrl($from))
						$source=\Tinify\fromUrl($from);
					else
						$source=\Tinify\fromFile($from);
					//devo cambiare l'aspect ratio, quindi taglio la parte che "straborda"
					$temp = getimagesize($from);
					$temp = ($temp[0] > $temp[1] ? $temp[1] : $temp[0]);

					//effettuo il resize fit
					$source->resize([
						"method" => "cover",
						"width" => $temp,
						"height" => $temp
					])->toFile($to);
					return true;
				});
				*/

			break;
			case 'instagram_story':

				// 9:16 ratio
				$this->output["error"][]="Tinyjpg al momento da errori su 'method => cover'";

			break;
			default:

				$this->wrapper(function($from,$to){
					//se il file è molto piccolo, evito di stare a convertirlo
					if(filesize($from) < $this->minimum_size_treshold){
						//se il percorso è diverso
						if($from != $to) copy($from, $to);
						return true;
					}
					if($this->isUrl($from))
						$source=\Tinify\fromUrl($from);
					else
						$source=\Tinify\fromFile($from);
					$source->toFile($to);
					return true;
				});

			break;
		}
		return $this;
	}
}
