<?php
$custom_triggers[]=['!dns', 'function:dns', true, '!dns <host or IP> - do a DNS lookup'];

function dns(){
	global $target,$args;
	if(filter_var($args,FILTER_VALIDATE_IP)){
		$r=gethostbyaddr($args);
		if($r==$args) return(send("PRIVMSG $target :IP has no reverse PTR record\n"));
	} else {
		$r=gethostbyname($args);
		if($r==$args) return(send("PRIVMSG $target :Hostname has no IP record\n"));
	}
	if(empty($r)) return(send("PRIVMSG $target :Error resolving\n"));
	return(send("PRIVMSG $target :$r\n"));
}
