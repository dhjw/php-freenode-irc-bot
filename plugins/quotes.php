<?php
// quotes plugin
// reads quotes.txt and outputs a random line on demand and automatically
// lines starting with "#" (without quotes) are skipped

// config
$plugin_quotes_auto_enabled=true; // output random quote automatically? true or false
$plugin_quotes_auto_time_since_last_msg=300; // dont output auto quote if someone talked within this many seconds
$plugin_quotes_auto_seconds=7200; // seconds between auto quote output. note: will not run on startup
// end config

// set up trigger for manual calls
$custom_triggers[]=['!quote','function:plugin_quotes_trigger',false,'!quote - output a quote'];

// initialize
$plugin_quotes_last_auto=time();
$plugin_quotes_last_msg=0;

// output a random quote from quotes.txt to the channel
function plugin_quotes_trigger(){
	global $channel,$plugin_quotes_last_auto,$time;
	$quotes=@trim(@file_get_contents(dirname(__FILE__).'/quotes.txt'));
	if(empty($quotes)) return send("PRIVMSG $channel :Error reading quotes file or no quotes in it\n");
	$quotes=explode("\n",$quotes);
	while(1){
		$quote=$quotes[rand(0,count($quotes)-1)];
		if(substr($quote,0,1)<>'#') break;
	}
	send("PRIVMSG $channel :$quote\n");
	// update last auto time even on manual trigger so next auto quote doesn't come too fast
	$plugin_quotes_last_auto=$time;
}

// run on a loop
if(!empty($plugin_quotes_auto_enabled)) register_loop_function('plugin_quotes_auto_loop');
function plugin_quotes_auto_loop(){
	global $plugin_quotes_last_auto,$plugin_quotes_auto_seconds,$plugin_quotes_last_msg,$plugin_quotes_auto_time_since_last_msg,$time,$connect_time,$channel,$data;
	if($time-$connect_time<25) return;
	if(strpos($data," PRIVMSG $channel :")!==false) $plugin_quotes_last_msg=$time;
	if($time-$plugin_quotes_last_auto>=$plugin_quotes_auto_seconds && $time-$plugin_quotes_last_msg>=$plugin_quotes_auto_time_since_last_msg) plugin_quotes_trigger();
}
