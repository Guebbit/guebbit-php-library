<?php

namespace Guebbit\Google;

use Guebbit\Base;

class Google extends Base{
	protected $google_url_lenght_limit=2048;	//deciso da google
	protected $google_key="AIzaSyACyqv2JOOuwjt7Ob5COia6oE1vb2FDQUs";
	// ----------- costants -----------
	//self::CONSTANT_NAME
	public const ERROR_URL_LIMIT_EXCEEDED = "Superato il limite di caratteri per Google Minimap";
}
