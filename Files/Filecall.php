<?php

namespace Guebbit\Files;

use Guebbit\Base;

class Filecall extends Base{
	// ----------- costants -----------
	//static::CONSTANT_NAME
	public const ERROR_WRONG_FILE	= "Wrong file type";


	// ----------- getters and setters ----------
	public function set_target($targetFolder=[]){
		$this->targetFolder=$targetFolder;
		return $this;
	}
	public function get_target(){
		return $this->targetFolder;
	}

}
