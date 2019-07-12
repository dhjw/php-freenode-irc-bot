<?php
// generate a wingnut insult, optionally directed at a specific user. can trigger via PM for stealth insult
$custom_triggers[]=['!wn', 'function:wninsult', true, '!wn - generate a wingnut insult'];
function wninsult(){
	global $target,$channel,$args,$users;
	if(!empty($args)){
		$id=search_multi($users,'nick',$args);
		if(empty($id)){
			send("PRIVMSG $target :Target user not in channel\n");
			return;
		}
	}
	$words=[
		[ "antifa", "abortionist", "communist", "environmentalist", "gay-agenda-pushing", "gun-grabbing", "homofascist", "leftist", "Marxist", "new age", "politically-correct", "radical", "socialist", "statist" ],
		[ "Bernie bro", "communist", "feminazi", "greenie", "illegal alien", "libtard", "mangina", "millennial", "moonbat", "NPC", "professional victim", "reverse racist", "sheeple", "SJW", "snowflake", "welfare queen" ],
	];
	send("PRIVMSG $channel :".(!empty($args)?"$args: ":'')."You ".$words[0][rand(0,count($words[0])-1)].' '.$words[1][rand(0,count($words[1])-1)]."!\n");

}
