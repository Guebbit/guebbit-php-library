<?php

namespace Guebbit\Images;

//TODO quando potrò farlo, sarà la classe di base
class ImageOptimizer extends Guebbimage{
	/**
	* 	CONVERSIONE: utilizzo wrapper php di varie librerie linux
	*	https://github.com/psliwa/image-optimizer
	*	https://github.com/spatie/image-optimizer
	*		sudo apt-get install jpegoptim
	*		sudo apt-get install optipng
	*		sudo apt-get install pngquant
	*		sudo npm install -g svgo
	*		sudo apt-get install gifsicle
	**/

	protected function check(){
		if(!class_exists("\SimpleImage")){
			throw new \Exception(static::ERROR_MISSING_CLASS."SimpleImage");
			return false;
		}
		if(!class_exists("\ImageOptimizer")){
			throw new \Exception(static::ERROR_MISSING_CLASS."ImageOptimizer");
			return false;
		}
		return true;
	}

	/**
	* 	Gestisce controlli e necessità comuni a tutte le funzioni di conversione.
	* 	@param function $convertFunction = la funzione che contiene le varie azioni di conversione
	**/
	protected function wrapper($convertFunction){
		/*
			class StdoutLogger extends \Psr\Log\AbstractLogger {
				public function log($level, $message, array $context = array()) {
					echo $message."<br />";
				}
			}
			$factory = new \ImageOptimizer\OptimizerFactory(array(), new StdoutLogger());
			$factory->get()->optimize("compressions/guebbit-compressed.png");
		*/
		foreach($this->files as $id => $file){
			try {

				if($this->settings["image"]["size"])
					$this->files[$id]["from"]=$this->single_resize($id);
				if($this->settings["image"]["watermark"]["image"])
					$this->files[$id]["from"]=$this->single_watermark($id);

				$convertFunction($this->files[$id]["from"], $file["to"]);

			} catch( \Exception $e) {
				$this->output["error"][]=static::ERROR_GENERIC.$e->getMessage();
			}

			if($this->settings["debug"])
				$this->output["debug"]["time"][]=[
					$file["from"],
					$file["to"],
					(microtime(true)-$this->time)
				];
		}
		return true;
	}


	/**
	*	Switch con le varie funzioni in base alla conversione richiesta con $this->method
	* 	Deve solo convertire. Opzioni varie, preliminari e controlli fatti in "$this->prepare",
	*	ulteriori necessità sistemate in $this->wrapper
	**/
	public function convert(){
		switch ($this->method) {
			case 'instagram_post':
				// 1:1 ratio
			break;
			case 'instagram_story':
				// 9:16 ratio
			break;
			default:

				$this->wrapper(function($from,$to){
					return true;
				});

			break;
		}
		return $this;
	}
}
