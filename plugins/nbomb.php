<?php
// respond to people who say 'nigger' or 'nbomb_test' with an imgflip image

// requires imgflip.com username and password, set below:
$nbomb_imgflip_user="";
$nbomb_imgflip_pass="";
// seconds to wait between outputs for each nick
$nbomb_delay=10800;

register_loop_function('plugin_nbomb');
function plugin_nbomb(){
	global $data,$ex,$nick,$incnick,$channel,$bc_users,$nbomb_imgflip_user,$nbomb_imgflip_pass,$nbomb_delay;
	if(stripos($data,'nigger')!==false || strpos($data,'nbomb_test')!==false){
		if(!isset($bc_users)) $bc_users=[]; // keep track of people already responded to. send only once per user
		if($ex[2]<>$nick){ // ignore if a pm
			// check if already output for this user within X secs
			foreach($bc_users as $k=>$v) if($v[0]==$incnick){ $bc_exists=1; $bc_id=$k; $bc_url=$v[1]; $bc_time=$v[2]; }
			if(!isset($bc_exists) || (isset($bc_exists) && (time()-$bc_time>$nbomb_delay))){
				// create meme and get url
				$r=curlget([
					CURLOPT_URL=>'https://api.imgflip.com/caption_image',
					CURLOPT_POST=>1,
					CURLOPT_POSTFIELDS=>http_build_query([
						'username'=>$nbomb_imgflip_user,
						'password'=>$nbomb_imgflip_pass,
						'template_id'=>51115958,
						'text1'=>"The black community frowns upon your shenanigans, $incnick"
					])
				]);
				$r=json_decode($r);
				print_r($r);
				if(!empty($r) && isset($r->success) && $r->success===true){
					send("PRIVMSG $channel :$incnick: {$r->data->url}\n");
					if(!isset($bc_exists)) $bc_users[]=[$incnick,$r->data->url,time()];
					else $bc_users[$bcid]=[$incnick,$r->data->url,time()];
				}
			}
		}
	}
}
