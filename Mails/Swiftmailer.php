<?php
namespace Guebbit\Mails;

class Swiftmailer extends Sendmail{

	protected function check(){
		if(!class_exists("\Swift_SmtpTransport")){
			throw new \Exception(static::ERROR_MISSING_CLASS."Swift_SmtpTransport");
			return false;
		}
		if(!class_exists("\Swift_Mailer")){
			throw new \Exception(static::ERROR_MISSING_CLASS."Swift_Mailer");
			return false;
		}
		if(!class_exists("\Swift_Message")){
			throw new \Exception(static::ERROR_MISSING_CLASS."Swift_Message");
			return false;
		}
		return true;
	}

	protected function _send(string $toEmail, string $toName){
		// Create the Transport

		$transport = (
			new \Swift_SmtpTransport($this->credentials["host"],
			$this->credentials["port"],
			$this->credentials["encryption"])
		)
		  ->setUsername($this->credentials["username"])
		  ->setPassword($this->credentials["password"]);


		// Create the Mailer using your created Transport
		$mailer = new \Swift_Mailer($transport);
		// Create the message
		$message = (new \Swift_Message())
		// Give the message a subject
		    ->setSubject($this->_data["subject"])
		// Set the From address with an associative array
		    ->setFrom([$this->_data["from_mail"] => $this->_data["from_name"]])
		// Set the To addresses with an associative array (setTo/setCc/setBcc)
		    ->setTo([$toEmail => $toName])
		// Give it a body
		    ->setBody($this->_data["body"]["html"], 'text/html')
		// And optionally an alternative body
		    ->addPart($this->_data["body"]["plain"], 'text/plain')
		// Optionally add any attachments (on this path)
		    //TODO ->attach(Swift_Attachment::fromPath('composer.json'))
		;
		//Invio il messaggio
		if(!$mailer->send($message)){
			$this->output["error"][]="Mailer Error: " . $mailer->ErrorInfo;
			return false;
		}
		return true;
	}
}
