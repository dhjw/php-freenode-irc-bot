<?php
// dad jokes via https://api-ninjas.com/api/dadjokes
$api_ninjas_key = '';

$custom_triggers[] = ['!dad', 'function:dadjoke', true, '!dad - output a random dad joke'];

function dadjoke()
{
	global $target, $curl_info, $api_ninjas_key;
	$r = json_decode(curlget([CURLOPT_URL => 'https://api.api-ninjas.com/v1/dadjokes?limit=1', CURLOPT_HTTPHEADER => ["X-Api-Key: $api_ninjas_key"]]));
	if ($curl_info['RESPONSE_CODE'] == 200 && isset($r[0]->joke)) {
		$joke = str_replace(["\r\n", "\n", "\t"], ' ', $r[0]->joke);
		$joke = str_replace(['“', '”'], '"', $joke);
		$joke = str_replace(['’', '‘'], "'", $joke);
		$joke = trim(preg_replace('/\s+/', ' ', $joke));
		$joke = str_shorten($joke);
		send("PRIVMSG $target :$joke\n");
	} elseif (isset($r->error)) {
		send("PRIVMSG $target :API Error: $r->error\n");
	} else {
		send("PRIVMSG $target :API Error ({$curl_info['RESPONSE_CODE']})\n");
	}
}