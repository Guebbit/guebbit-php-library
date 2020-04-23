<?php

namespace Guebbit\Files;

use Guebbit\Base;

/**
* 	Ottengo informazioni estensive su un percorso / url
* 	@param string $path: percorso
*	@return array: le informazioni acquisite
**/
function file_info($path){
	$temp = getimagesize($path);
	$temp=[
		"url"		=> $path,
		"width" 	=> $temp[0] ?? false,
		"height" 	=> $temp[1] ?? false,
		"type" 		=> $temp[2] ?? false,	//IMAGETYPE_XXX constants
		"mime" 		=> $temp["mime"],
		"size"		=> @filesize($path),	//se null, è un url e non posso saperlo
	];

	//more secure
	$file_info = new \finfo(FILEINFO_MIME_TYPE);
	$temp["mime"] = $file_info->buffer(file_get_contents($path));
	$file_info = new \finfo(FILEINFO_MIME_ENCODING);
	$temp["encoding"] = $file_info->buffer(file_get_contents($path));

	//checks
	if(!$temp["size"])	$temp["size"]=false;

	// add: dirname, basename, extension, filename
	return array_merge($temp, pathinfo($path),[
		//valori di default che verranno ovverridati
		"extension" => ""
	]);
}

/**
* 	Rimuovo directory e tutto quello che contiene
* 	@param string $dir: percorso
**/
function remove_directory($dir){
	if(!is_dir($dir))
		return false;
	$objects = scandir($dir);
	foreach ($objects as $object)
		if ($object != "." && $object != "..")
			if (is_dir($dir."/".$object))
				rrmdir($dir."/".$object);
			else
				unlink($dir."/".$object);
	rmdir($dir);
	return true;
}
