<?php
/*

{
   "results" : [
      {
         "address_components" : [
            {
               "long_name" : "9",
               "short_name" : "9",
               "types" : [ "street_number" ]
            },
            {
               "long_name" : "Via Londra",
               "short_name" : "Via Londra",
               "types" : [ "route" ]
            },
            {
               "long_name" : "Carpi",
               "short_name" : "Carpi",
               "types" : [ "locality", "political" ]
            },
            {
               "long_name" : "Carpi",
               "short_name" : "Carpi",
               "types" : [ "administrative_area_level_3", "political" ]
            },
            {
               "long_name" : "Provincia di Modena",
               "short_name" : "MO",
               "types" : [ "administrative_area_level_2", "political" ]
            },
            {
               "long_name" : "Emilia-Romagna",
               "short_name" : "Emilia-Romagna",
               "types" : [ "administrative_area_level_1", "political" ]
            },
            {
               "long_name" : "Italia",
               "short_name" : "IT",
               "types" : [ "country", "political" ]
            },
            {
               "long_name" : "41012",
               "short_name" : "41012",
               "types" : [ "postal_code" ]
            }
         ],
         "formatted_address" : "Via Londra, 9, 41012 Carpi MO, Italia",
         "geometry" : {
            "location" : {
               "lat" : 44.777038,
               "lng" : 10.8645466
            },
            "location_type" : "RANGE_INTERPOLATED",
            "viewport" : {
               "northeast" : {
                  "lat" : 44.7783869802915,
                  "lng" : 10.8658955802915
               },
               "southwest" : {
                  "lat" : 44.7756890197085,
                  "lng" : 10.8631976197085
               }
            }
         },
         "place_id" : "EiRWaWEgTG9uZHJhLCA5LCA0MTAxMiBDYXJwaSBNTywgSXRhbHkiGhIYChQKEgm_FJfg1fJ_RxEgJtbN7A9DthAJ",
         "types" : [ "street_address" ]
      }
   ],
   "status" : "OK"
}

*/

namespace Guebbit\Google;

class Geocode extends Google{
	protected $google_url="https://maps.google.com/maps/api/geocode";
	protected $center;
	protected $zoom=6;
	protected $size=600;
	protected $defaultPin=false;
	protected $path=false;
	protected $pins=[];

    public function __construct($settings=[]){
		//TODO fare la versione "rapida"
		return $this;
	}

	public function get_geolocal($address){
		$address=urlencode(str_replace(' ','+',$address));
		$url = $this->google_url.'/json?key='.GOOGLE_KEYS_WEB.'&address='.str_replace(' ','+',$address);
		return json_decode(file_get_contents($url));
	}

	/**
	*	Shorthands
	*	@param object $result: uno dei risultati dell'output
	*	@return string
	**/
	protected function get_lat($result){
		if(!isset($result->geometry->location->lat))
			return false;
		return $result->geometry->location->lat;
	}
	protected function get_lng($result){
		if(!isset($result->geometry->location->lng))
			return false;
		return $result->geometry->location->lng;
	}

	/**
	*	Coordinate a partire da un indirizzo
	*	@param string address
	*	@return array [lat, lng]
	**/
	public function get_coordinates($address){
		$output=$this->get_geolocal($address);
		return [
			$this->get_lat($output->results[0]),
			$this->get_lng($output->results[0])
		];
	}

	/**
	*	Inserisco un pin sulla mappa
	*	@param string $path = dove e con che nome voglio salvare l'immagine
	*	@param string $center = coordinate del centro della mappa
	*	@param string $zoom = quanto è zoommata la mappa (valori di google maps API)
	*	@param string $size = dimensioni in pixel dell'immagine
	**/
	protected function _create($path, $center, $zoom, $size, $pins){
		//se esiste già non sto a fare la chiamata a Google
		if(file_exists($path))
			return $path;

		//se non esiste, la creo e poi ritorno la posizione (in cui sarà)
		$url = $this->google_url;
		$url.= "?center=".$center;
		$url.= "&zoom=".$zoom;
		$url.= "&size=".$size."x".$size;
		$url.= "&key=".$this->google_key;
		foreach($this->pins as $pin)
			$url.= "&markers=icon:".urlencode($pin[1]). "|" .$pin[0];

		//se supero il limite consentito, non faccio nemmeno la chiamata
		if(strlen($this->google_url_lenght_limit) > $this->google_url_lenght_limit)
			throw new \Exception(static::ERROR_URL_LIMIT_EXCEEDED);

		file_put_contents($path, file_get_contents($url));
		return $path;
	}

}
