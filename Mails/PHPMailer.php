<?php
namespace Guebbit\Mails;

class PHPMailer extends Sendmail{

	protected function check(){
		if(!class_exists("\PHPMailer\PHPMailer\PHPMailer")){
			throw new \Exception(static::ERROR_MISSING_CLASS."PHPMailer");
			return false;
		}
		return true;
	}

	//classi
	protected function _send(string $toEmail, string $toName){
		//Create a new PHPMailer instance
		$mailer = new \PHPMailer\PHPMailer\PHPMailer(true);		// Passing `true` enables exceptions
		try {
			//Tell PHPMailer to use SMTP
			$mailer->isSMTP();
			//Enable SMTP debugging
			// 0 = off (for production use)
			// 1 = client messages
			// 2 = client and server messages
			if($this->settings["debug"])
				$mailer->SMTPDebug = 2;
			else
				$mailer->SMTPDebug = 0;
			//Set the hostname of the mail server
			$mailer->Host = $this->credentials["host"];
			// use
			// $mailer->Host = gethostbyname('smtp.gmail.com');
			// if your network does not support SMTP over IPv6
			//Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission |  (porte: 587, 465, 25, 26)
			$mailer->Port = $this->credentials["port"];
			//Set the encryption system to use - ssl (deprecated) or tls
			$mailer->SMTPSecure = 'tls';
			//Whether to use SMTP authentication
			$mailer->SMTPAuth = true;

			if(!$this->settings["secure"]){
				// TODO certificato non aggiornato o nome certificato errato
				// https://github.com/PHPMailer/PHPMailer/issues/1176
				$mailer->SMTPOptions = array(
					'ssl' => array(
						'verify_peer' => false,
						'verify_peer_name' => false,
						'allow_self_signed' => true
					)
				);
			}

			// Set email format to HTML
			$mailer->isHTML(true);
			//Username to use for SMTP authentication - use full email address for gmail
			$mailer->Username = $this->credentials["username"];
			//Password to use for SMTP authentication
			$mailer->Password = $this->credentials["password"];
			//Set who the message is to be sent from
			$mailer->setFrom($this->_data["from_mail"], $this->_data["from_name"]);
			//Set an alternative reply-to address
			//TODO $mailer->addReplyTo('ciopo.andrea91@gmail.com', 'First Last');
			//Set who the message is to be sent to
			$mailer->addAddress($toEmail, $toName);
			//Set the subject line
			$mailer->Subject = $this->_data["subject"];
			//Read an HTML message body from an external file, convert referenced images to embedded,
			//convert HTML into a basic plain-text alternative body
			$mailer->Body = $this->_data["body"]["html"];
			//Replace the plain text body with one created manually
			$mailer->AltBody = $this->_data["body"]["plain"];
			//Attach a file
			//TODO $mailer->addAttachment('composer.json');
			//send the message, check for errors
			if(!$mailer->send()){
				$this->output["error"][]="Mailer Error: " . $mailer->ErrorInfo;
				return false;
			}
			return true;
		} catch (Exception $e) {
			$this->output["error"][]="Mailer Error: " . $mailer->ErrorInfo;
		}
		return false;
	}
}
