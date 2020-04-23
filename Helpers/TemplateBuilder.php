<?php
namespace Guebbit\Helpers;

use Guebbit\Base;

class TemplateBuilder extends Base{
	/*
	*	I vari placeholders
	*	["name" => "[[NOME]]"]
	*	"nome con cui li identifico" => "stringa che vado a cercare nel testo"
	*/
	protected $_placeholders=array();
	/*
	*	Una semplice stringa
	*	Appartenente ad un file o ad un HTML, non importa
	*/
	protected $_template;

	/**
	* 	Eseguo la sostituzione del placeholder all'interno del template
	* 	Se inserisco @param array $placeholders e @param array $template,
	*	eseguo subito un fill con $raw=true
	*
	*	Se presente solo 1 dei 2, mi limito ad inserirlo e basta
	**/
	public function __construct($template=false, $placeholders=false){
		if($placeholders && $template){
			$this->template($template);
			$this->fill($placeholders, true);
		}
		return $this;

		if($placeholders)
			$this->placeholders($placeholders);
		if($template)
			$this->template($template);
		return $this;
	}

	//inserimento diretto placeholders
	public function placeholders(array $array){
		$this->_placeholders=array_replace_recursive($this->_placeholders,$array);
		return $this;
	}

	//inserimento diretto del template
	public function template(string $template){
		$this->_template=$template;
		return $this;
	}

	//caricamento del template da un file
	public function load_template(string $template){
		$this->_template=file_get_contents($template);
		return $this;
	}


	/**
	* 	Eseguo la sostituzione del placeholder all'interno del template
	* 	@param array $array = verrà spezzato in un array di $placeholder e $fill
	* 		@param string $placeholder = testo da trovare nel file
	* 		@param string $fill = testo con cui sostituire il placeholder
	*	@param bool $raw = true indica che posso cercare direttamente il $placeholder inserito
	*						senza cercarlo nell'array dei $this->placeholders
	**/
	public function fill(array $array, bool $raw=true){
		foreach($array as $key => $label){
			$this->_fill($key, $label, $raw);
		}
		return $this;
	}

	protected function _fill($placeholder, $fill, $raw=true){
		if(isset($this->_placeholders[$placeholder]))
			$placeholder=$this->_placeholders[$placeholder];
		else if(!$raw) return false;
		$this->_template=str_replace($placeholder, $fill, $this->_template);
		return true;
	}

	/**
	* 	Indico che ho terminato di modificare il template,
	*	quindi ciclo tutti i placeholders rimasti inutilizzati e li svuoto
	**/
	public function end(){
		foreach($this->_placeholders as $key => $placeholder)
			$this->_template=str_replace($placeholder, "", $this->_template);
	}

	public function print(){
		return $this->_template;
	}

	public function reset(){
		$this->_placeholders=array();
		$this->_template=array();
		$this->settings=array();
		return $this;
	}
}
