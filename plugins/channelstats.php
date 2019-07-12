<?php
// compare number of users of multiple channels

// default channels (optional, can comment out with // at beginning of line)
$plugin_stats_default_chans=['#freenode','##chat'];

$custom_triggers[]=['!stats', 'function:plugin_stats_trigger', true, "!stats - Check # of users in channels".((isset($plugin_stats_default_chans) && count($plugin_stats_default_chans)==2)?", default {$plugin_stats_default_chans[0]} vs {$plugin_stats_default_chans[1]}":'')];

function plugin_stats_trigger(){
	global $target,$args,$stats_target,$stats_result,$stats_count,$stats_nchans,$plugin_stats_default_chans,$stats_waiting;
	// initiate calls to alis
	if(empty($args) && isset($plugin_stats_default_chans) && count($plugin_stats_default_chans)==2) $args="{$plugin_stats_default_chans[0]} {$plugin_stats_default_chans[1]}";
	echo "plugin_stats_trigger() called. args=$args\n";
	$chans=explode(' ',trim($args));
	if(count($chans)<1){
		send("PRIVMSG $target :Specify 1 or more channels\n");
		return;
	}
	$stats_nchans=count($chans);
	$stats_result='';
	$stats_count=0;
	$stats_target=$target;
	$stats_waiting=true;
	foreach($chans as $k=>$c){
		send("PRIVMSG alis :list $c\n");
		if($k<count($chans)-1) sleep(1);
	}
}

// catch results
register_loop_function('plugin_stats_loop');
function plugin_stats_loop(){
	global $data,$stats_waiting;
	if(substr($data,0,6)==':alis!' && strpos($data,':#')!==false && $stats_waiting){
		global $stats_target,$stats_result,$stats_count,$stats_nchans;
		$sdata=preg_replace('/\s+/',' ',$data);
		$sdata=explode(' ',$sdata);
		$c=ltrim($sdata[3],':');
		$n=$sdata[4];
		echo "channel $c count $n\n";
		$stats_result.="$c $n";
		$stats_count++;
		if($stats_count==$stats_nchans){
			send("PRIVMSG $stats_target :$stats_result\n");
			$stats_waiting=false;
		} else $stats_result.=", ";
	}
}
