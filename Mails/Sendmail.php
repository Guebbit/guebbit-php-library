<?php


namespace Guebbit\Mails;

use Guebbit\Base;

abstract class Sendmail extends Base{
	// ----------- variables -----------
	protected $credentials=[
		"host" => 		false,
		"port" =>		25,
		"username" => 	false,
		"password" => 	false,
		"name" => 		false,
		"encryption" => false
	];
	protected $_data = [];

	public const ERROR_MISSING_MESSAGE	= "Nessun messaggio inserito";
	public const ERROR_MISSING_SUBJECT	= "Nessun soggetto";
	public const ERROR_NO_DESTINATION	= "A chi inviare la mail?";

	public function __construct($credentials, $settings=[]){
		$this->credentials=array_replace_recursive($this->credentials, $credentials);
		$this->settings=array_replace_recursive([
			"debug"			=> false,
			"secure"		=> true,
			//from, chi invia la mail
			"from" => [
				"mail" => 		$this->credentials["username"],
				"name" => 		$this->credentials["name"],
			]
		],$settings);
		$this->_data["from_mail"]=$this->settings["from"]["mail"];
		$this->_data["from_name"]=$this->settings["from"]["name"];

		//controllo le dependancies
		$this->check();

		return $this;
	}

	public function message(string $subject, string $html, string $text=""){
		$this->_data["subject"] = $subject;
		$this->_data["body"]["html"] = $html;
		$this->_data["body"]["plain"] = $text;
		return $this;
	}
	public function send(string $toEmail, string $toName=""){
		//controlli
		if(!$this->_data["body"]["html"] && !$this->_data["body"]["text"])
			throw new \Exception(static::ERROR_MISSING_MESSAGE);
		if(!$this->_data["subject"])
			throw new \Exception(static::ERROR_MISSING_SUBJECT);
		if(!$toEmail)
			throw new \Exception(static::ERROR_NO_DESTINATION);
		$this->_send($toEmail, $toName);
	}

	abstract protected function _send(string $toEmail, string $toName);

}
