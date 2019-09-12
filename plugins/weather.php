<?php
// weather via openweathermap
$openweathermap_api_key='';

$custom_triggers[]=['!weather', 'function:plugin_openweathermap', true, "!weather <location> - Check the weather"];

function plugin_openweathermap(){
	global $target,$args,$openweathermap_api_key;
	echo "!weather called, q=$args\n";
	if(strlen($args)==5 && is_numeric($args)){
		// US zip code
		$r=curlget([CURLOPT_URL=>"https://api.openweathermap.org/data/2.5/weather?APPID=$openweathermap_api_key&zip=$args&units=imperial"]);
	} else {
		// query
		$r=curlget([CURLOPT_URL=>"https://api.openweathermap.org/data/2.5/weather?APPID=$openweathermap_api_key&q=".urlencode($args)."&units=imperial"]);
	}
	$r=json_decode($r);
	print_r($r);
	if(!empty($r) && !empty($r->cod) && $r->cod==200){
		// get celsius
		$celsius=round(($r->main->temp-32)/1.8);
		$response="{$r->name} ({$r->sys->country}) ".round($r->main->temp)."F/{$celsius}C ".ucfirst($r->weather[0]->description);
	} elseif(!empty($r) && isset($r->cod)){
		$response="{$r->cod}: {$r->message}";
	} else {
		$response='no response';
	}
	send("PRIVMSG $target :$response\n");
}