<?php
//il record delle chiavi parte da 0, mai usato, a 500 (default) che è il limite massimo e non può più essere usato
// Non funziona "prendi -> usa -> aggiorna" ma "prendi -> aggiorna", gli dico in anticipo di quanti usi ho bisogno.
// Perché altrimenti in caso di lunghi utilizzi e persone che lo usano simultaneamente, si svacca tutto.

namespace Guebbit\Html;

use function Guebbit\media_load;

function create_image(string $image, string $text="", bool $med=false){
	$string = '<img '.$text;
	//se ho la thumbnail
	if(file_exists(media_load($image, "thumbnail"))){
		$string .= ' src="'.media_load($image, "thumbnail").'" ';
		if($med && file_exists(media_load($image, "medium")))
			$string .= ' data-src="'.media_load($image, "medium").'" ';
		else
			$string .= ' data-src="'.$image.'" ';
	}else{
		if($med && file_exists(media_load($image, "medium")))
			$string .= ' src="'.media_load($image, "medium").'" ';
		else
			$string .= ' src="'.$image.'" ';
	}
	return $string." />";
}

function create_picture(string $image, string $text="", bool $med=false){
	$string = '<picture>';
	//mobile
	if(file_exists(media_load($image, "medium")))
		$string .= '<source srcset="'.media_load($image, "medium").'" media="(max-width: 600px)">';
	//desktop
	if($med && file_exists(media_load($image, "medium")))
		$string .= '<source srcset="'.media_load($image, "medium").'" media="(min-width: 993px)">';
	else
		$string .= '<source srcset="'.$image.'" media="(min-width: 993px)">';
	//thumbnail
	if(file_exists(media_load($image, "thumbnail")))
		$string .= '<source srcset="'.media_load($image, "thumbnail").'">';
	$string .= create_image($image, $text, $med);
	$string .= '</picture>';
	return $string;
}


function create_alts(string $alt="", string $title=""){
	return 'alt="'.stripslashes($alt).'" title="'.stripslashes($title).'"';
}
