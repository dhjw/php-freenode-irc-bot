<?php
// geolocation with ipinfodb.com

$geo_key='';

$custom_triggers[]=['!geo', 'function:geoip', true, '!geo <host or IP> - get geolocation of host or IP'];
function geoip(){
	global $data,$target,$channel,$args,$geo_key;
	if(empty($args) || strpos($args,' ')!==false) return;
	ini_set('default_socket_timeout', 12);
	$args=gethostbyname($args);
	echo "ip=$args\n";
	if(!filter_var($args, FILTER_VALIDATE_IP)){
		send("PRIVMSG $target :Invalid IP or lookup failed.\n");
		return;
	}
	if(!filter_var($args, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)){
		send("PRIVMSG $target :You're right where you are.\n");
		return;
	}
	$result=file_get_contents("http://api.ipinfodb.com/v3/ip-city/?key=$geo_key&ip=".urlencode($args));
	echo "result=$result\n";
	list($status,$null,$ip,$cc,$country,$state,$city,$zip,$loc1,$loc2)=explode(';',$result);
	$out=[];
	if(!empty($city)) $out[]=$city;
	if(!empty($state)) $out[]=$state;
	if(!empty($country)) $out[]=$country;
	if(empty($result) || $status<>'OK' || $country=='-'){
		send("PRIVMSG $target :Error retrieving location.\n");
		return;
	}
	if(!empty($loc1) && !empty($loc2)) $tmp2=" (".make_short_url("https://www.google.com/maps?q=$loc1+$loc2").")"; else $tmp2='';
	send("PRIVMSG $target :Location: ".implode(', ',$out).$tmp2."\n");

}
