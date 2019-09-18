<?php
// output a random string when someone is banned or kicked

// run on ban?
$onbankick_ban_enabled=true;

// run on kick?
$onbankick_kick_enabled=true;

// on ban messages
$onbankick_ban_msgs=[
	"Another one bites the dust.",
	"You may no longer visit the channel.",
];

// on kick messages. may include {NICK} to output user's nick
$onbankick_kick_msgs=[
	"gtfo {NICK}.",
	"boom.",
];

// end config

register_loop_function('plugin_onbankick');
function plugin_onbankick(){
	global $data,$channel,$onbankick_ban_enabled,$onbankick_kick_enabled,$onbankick_ban_msgs,$onbankick_kick_msgs;
	
	if($onbankick_ban_enabled && preg_match("/^:[^ ]* MODE $channel \+b/",$data)){
		send("PRIVMSG $channel :".$onbankick_ban_msgs[rand(0,count($onbankick_ban_msgs)-1)]."\n");
		return;
	}

	if($onbankick_kick_enabled && preg_match("/^:[^ ]* KICK/",$data)){
		$tmp=explode(' ',$data);
		$nick=$tmp[3];
		$msg=str_replace('{NICK}',$nick,$onbankick_kick_msgs[rand(0,count($onbankick_kick_msgs)-1)]);
		send("PRIVMSG $channel :$msg\n");
	}
}
