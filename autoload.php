<?php
require_once(dirname(__FILE__)."/Base.php");
require_once(dirname(__FILE__)."/Helpers.php");

require_once(dirname(__FILE__)."/Helpers/TemplateBuilder.php");

require_once(dirname(__FILE__)."/Auth/vendor/autoload.php");
require_once(dirname(__FILE__)."/Auth/Session.php");

require_once(dirname(__FILE__)."/Database/Dbcall.php");
require_once(dirname(__FILE__)."/Database/Users.php");

require_once(dirname(__FILE__)."/Files/Helpers.php");
require_once(dirname(__FILE__)."/Files/Filecall.php");
require_once(dirname(__FILE__)."/Files/Download.php");
require_once(dirname(__FILE__)."/Files/Upload.php");

require_once(dirname(__FILE__)."/Google/Google.php");
require_once(dirname(__FILE__)."/Google/Geocode.php");
require_once(dirname(__FILE__)."/Google/Minimap.php");

require_once(dirname(__FILE__)."/Html/Helpers.php");

require_once(dirname(__FILE__)."/Images/Guebbimage.php");
require_once(dirname(__FILE__)."/Images/ImageOptimizer.php");
require_once(dirname(__FILE__)."/Images/Tinyjpg.php");

require_once(dirname(__FILE__)."/Mails/Sendmail.php");
require_once(dirname(__FILE__)."/Mails/PHPMailer.php");
require_once(dirname(__FILE__)."/Mails/Swiftmailer.php");
