<?php

namespace Guebbit;

/**
*	Inserico facilmente sottocartelle dei files
*	(per "thumbnail" e "medium")
*	@param string $path = che percorso modificare (percorso immagine originale)
*	@param string $mode = cosa aggiungere
*	@return @param string = percorso nuovo
**/
function media_load(string $path, $mode=false){
	if(!$mode)
		return $path;
	return dirname($path).DIRECTORY_SEPARATOR.$mode.DIRECTORY_SEPARATOR.basename($path);
}



/**
* 	combino get_headers e mime_content_type in 1
**/
function get_mime($path){
	if(filter_var($path, FILTER_VALIDATE_URL))
		return get_headers($path,1)["Content-Type"];
	else
		return  mime_content_type($path);
}


/**
*	per evitare casini con la timezone
**/
function get_datetime($date="now", $timezone="Europe/Rome"){
	return new \DateTime($date, new \DateTimeZone($timezone));
}



/**
* 	shorthand a scopo di debug
**/
function printme($data){
	return "<pre>".print_r($data, 1)."</pre>";
}


/**
*	Filter an array based on a white list of keys
*	@param array $array
*	@param array $keys
*	@return array
**/
function array_keys_whitelist(array $array, array $keys) {
	return array_intersect_key($array, array_flip($keys));
}



//controllo se si tratta di un url oppure no
function isUrl($string){
	return (filter_var($string, FILTER_VALIDATE_URL));
}


// secure random hash
function secure_hash($n){
	return bin2hex(random_bytes($n));
}


/**
*
*
**/
function sanitize($string, $mode=false) {
	switch ($mode) {
		case 'alphanumeric':
			return preg_replace('/[^a-zA-Z0-9]/', '', $string);
		break;
		case 'alphanumeric+space':
			return preg_replace('/[^a-zA-Z0-9\s]/', '', $string);
		break;
		case 'filenames':
			// strtolower() guarantees the filename is lowercase (since case does not matter inside the URL, but in the NTFS filename)
			// [^a-z0-9]+ will ensure, the filename only keeps letters and numbers
			// Substitute invalid characters with '-' keeps the filename readable
			return preg_replace( '/[^a-z0-9.]+/', '-', strtolower( $string ) );
		break;
		default:
			return htmlspecialchars(trim($string));

			$string=trim(" ".$string);
			if($string!=""){
				$string=filter_var(html_entity_decode(htmlentities($string)));
				return $string;
			}
			return false;
		break;
	}
}
