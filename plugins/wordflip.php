<?php
// respond to people who say certain words with an imgflip image

// requires imgflip.com username and password, set below:
$wordflip_imgflip_user="";
$wordflip_imgflip_pass="";

// seconds to wait between outputs for each nick for each set of words
$wordflip_delay=10800;

// output running total of words seen since bot started after each detection
$wordflip_show_counts=true;

// only match words surrounded by non-word characters. no partial matches. e.g. 'test' won't be detected in 'latest'
$wordflip_strict=true;

// case-insensitive matching
$wordflip_nocase=true;

// 'template' must be a numeric image ID. recommend to search imgflip.com using the site search feature at top of page then locate a 'user-generated' image and look at the link
// 'template' may also be omitted or set to an empty string if you just want a canned text response
// if you just want to count detected words, omit 'responses' for a setting
// use {NICK} in text to replace with user nick
$wordflip_settings=[
	[
		'words'=>['nigger','nbombtest'],
		'responses'=>[
			[
				'template'=>51115958,
				'text'=>'The black community frowns upon your shenanigans, {NICK}'
			],
			// example second possible response, sent at random
			//[
			//	'template'=>51115958,
			//	'text'=>'The black community is very upset with you, {NICK}'
			//],
		],

	],
	// example second words, etc.
	[
		'words'=>['faggot','fbombtest'],
		'responses'=>[
			[
				'template'=>13956859,
				'text'=>'You rang, {NICK}?'
			],
		],
	],
];

// end config

if(empty($bot_start_time)) $bot_start_time=time();
register_loop_function('plugin_wordflip');
function plugin_wordflip(){
	global $data,$ex,$nick,$incnick,$channel,$bot_start_time,$wordflip_settings,$wordflip_imgflip_user,$wordflip_imgflip_pass,$wordflip_delay,$wordflip_counts,$wordflip_show_counts,$wordflip_strict,$wordflip_nocase,$wordflip_triggerlog;
	// skip non-channel msgs
	if(!preg_match("/[^ ]* PRIVMSG $channel/",$data)) return;
	$text=rtrim(substr($data,strpos(ltrim($data,':'),':')+2));
	// loop each setting
	foreach($wordflip_settings as $setting_index=>$setting){
		// loop each word in setting
		foreach($setting['words'] as $word){
			$match=false;
			if(!empty($wordflip_strict)){
				if(!empty($wordflip_nocase)) $match=preg_match("/\\b".preg_quote($word)."\\b/i",$text);
				else $match=preg_match("/\\b".preg_quote($word)."\\b/",$text);
			} else {
				if(!empty($wordflip_nocase)) $match=stripos($text,$word)!==false;
				else $match=strpos($text,$word)!==false;
			}
			if($match){
				echo "wordflip word \"$word\" detected\n";
				// show totals if enabled
				if(!empty($wordflip_show_counts)){
					if(!isset($wordflip_counts)) $wordflip_counts=[];
					if(!isset($wordflip_counts[$word])) $wordflip_counts[$word]=0;
					$wordflip_counts[$word]++;
					echo "wordflip total detections since bot started ".wordflip_timeago(time()-$bot_start_time)." ago: ";
					foreach($wordflip_counts as $word=>$count) echo "$word:$count ";
					echo "\n";
				}
				// check if user triggered this setting within delay time, if so skip
				if(!isset($wordflip_triggerlog)) $wordflip_triggerlog=[];
				foreach(array_reverse($wordflip_triggerlog) as $log_index=>$log){
					list($log_setting_index,$log_nick,$log_time)=$log;
					// remove old log entries
					if(time()-$log_time>$wordflip_delay){
						// echo "wordflip entry [$log_setting_index,$log_time,'$log_nick'] expired, removing\n";
						unset($wordflip_triggerlog[$log_index]);
						$wordflip_triggerlog=array_values($wordflip_triggerlog);
					}
				}
				foreach($wordflip_triggerlog as $log){
					list($log_setting_index,$log_nick,$log_time)=$log;
					// skip if have a match and repeat within delay
					if($log_setting_index==$setting_index && $log_nick == $incnick){
						echo "wordflip skipping response because responded to same word(s) by $incnick less than {$wordflip_delay}s ago\n";
						continue(2);
					}
				}
				// add to log
				$wordflip_triggerlog[]=[$setting_index,$incnick,time()];
				// get random response
				if(!empty($setting['responses'])){
					$response_index=count($setting['responses'])>1?rand(0,count($setting['responses'])-1):0;
					if(!empty($setting['responses'][$response_index]['template'])){
						// create meme and get url
						$r=curlget([
							CURLOPT_URL=>'https://api.imgflip.com/caption_image',
							CURLOPT_POST=>1,
							CURLOPT_POSTFIELDS=>http_build_query([
								'username'=>$wordflip_imgflip_user,
								'password'=>$wordflip_imgflip_pass,
								'template_id'=>$setting['responses'][$response_index]['template'],
								'text1'=>str_replace('{NICK}',$incnick,$setting['responses'][$response_index]['text'])
							])
						]);
						$r=json_decode($r);
						if(!empty($r) && isset($r->success) && $r->success===true) send("PRIVMSG $channel :$incnick: {$r->data->url}\n");
						else echo "imgflip.com error. response=".print_r($r,true);
					} elseif(!empty($setting['responses'][$response_index]['text'])){
						// just output the text
						send("PRIVMSG $channel :$incnick: ".str_replace('{NICK}',$incnick,$setting['responses'][$response_index]['text'])."\n");
					} else echo "wordflip no template or text configured for this response\n";
				} else echo "wordflip no responses configured for this word\n";
			}
		}
	}
}

function wordflip_timeago($s){
	$d=floor($s/86400); $s-=$d*86400;
	$h=floor($s/3600); $s-=$h*3600;
	$m=floor($s/60); $s-=$m*60;
	return ($d>0?$d.'d':'').($h>0?$h.'h':'').($m>0?$m.'m':'').($d==0&&$h==0&&$m==0&&$s>0?$s.'s':'');
}