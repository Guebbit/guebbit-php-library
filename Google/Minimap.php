<?php
//il record delle chiavi parte da 0, mai usato, a 500 (default) che è il limite massimo e non può più essere usato
// Non funziona "prendi -> usa -> aggiorna" ma "prendi -> aggiorna", gli dico in anticipo di quanti usi ho bisogno.
// Perché altrimenti in caso di lunghi utilizzi e persone che lo usano simultaneamente, si svacca tutto.

namespace Guebbit\Google;

class Minimap extends Google{
	protected $google_url="https://maps.googleapis.com/maps/api/staticmap";
	protected $center;
	protected $zoom=8;
	protected $size=600;
	protected $defaultPin=false;
	protected $path=false;
	protected $pins=[];

    public function __construct($settings=[]){
		//TODO fare la versione "rapida"
		return $this;
	}

	/**
	*	Dove voglio salvare l'immagine creata
	*	@param string $path
	**/
	public function set_path($path){
		$this->path=$path;
		return $this;
	}
	public function get_path(){
		return $this->path;
	}

	/**
	*	Immagine pin di default
	*	@param string $url = image URL
	**/
	public function set_pin($url){
		$this->defaultPin=$url;
		return $this;
	}
	public function get_pin(){
		return $this->defaultPin;
	}

	/**
	*	Determino il centro della mappa
	*	@param mixed $coordinate = può essere string o array
	**/
	public function set_size(){
		$this->size=0;
		return $this;
	}
	public function get_size(){
		return $this->size;
	}


	/**
	*	Determino il centro della mappa
	*	@param mixed $coordinate = può essere string o array
	**/
	public function set_zoom(){
		$this->center=0;
		return $this;
	}
	public function get_zoom(){
		return $this->center;
	}

	/**
	*	Determino il centro della mappa
	*	@param mixed $coordinate = può essere string o array
	**/
	public function set_center($coordinate){
		if(is_array($coordinate))
			$coordinate=$coordinate[0].",".$coordinate[1];
		$this->center=$coordinate;
		return $this;
	}
	public function get_center(){
		return $this->center;
	}

	/**
	*	Inserisco un pin sulla mappa
	*	@param mixed $coordinate = può essere string o array
	*	@param mixed $icon = url a immagine che userò come pin
	**/
	public function insertPlace($coordinate, string $icon=null){
		if(is_array($coordinate))
			$coordinate=$coordinate[0].",".$coordinate[1];
		$this->pins[]=[
			$coordinate,
			$icon
		];
		return $this;
	}

	/**
	*	Faccio i controlli necessari poi creo la mappa.
	*	@param @return string posizione del pin
	**/
	public function create(){
		if(empty($this->pins)) return false;
		if(!$this->path) return false;

		//default center è il primo pin inserito
		$center=$this->center;
		if(!$center) $center=$this->pins[0][0];

		//la vera funzione di creazione dell'immagine
		return $this->_create($this->path.$this->create_name().".jpg", $center, $this->zoom, $this->size, $this->pins);
	}

	/**
	*	Inserisco un pin sulla mappa
	*	@param string $path = dove e con che nome voglio salvare l'immagine
	*	@param string $center = coordinate del centro della mappa
	*	@param string $zoom = quanto è zoommata la mappa (valori di google maps API)
	*	@param string $size = dimensioni in pixel dell'immagine
	**/
	protected function _create($path, $center, $zoom, $size, $pins){
		//se esiste già non sto a fare la chiamata a Google
		if(file_exists($path))
			return $path;

		//se non esiste, la creo e poi ritorno la posizione (in cui sarà)
		$url = $this->google_url;
		$url.= "?center=".$center;
		$url.= "&zoom=".$zoom;
		$url.= "&size=".$size."x".$size;
		$url.= "&key=".$this->google_key;
		foreach($this->pins as $pin)
			$url.= "&markers=icon:".urlencode($pin[1]). "|" .$pin[0];

		//se supero il limite consentito, non faccio nemmeno la chiamata
		if(strlen($this->google_url_lenght_limit) > $this->google_url_lenght_limit)
			throw new \Exception(static::ERROR_URL_LIMIT_EXCEEDED);

		file_put_contents($path, file_get_contents($url));
		return $path;
	}

	/**
	*	Creo un nome hashando il centro e le posizioni richieste,
	*	così so se l'ho già creato o no
	**/
	protected function create_name(){
		$name="";
		if($this->center)
			$name.=$this->center;
		foreach($this->pins as $pin)
			$name.=$pin[0];
		return hash('ripemd160', $name);
	}

}
