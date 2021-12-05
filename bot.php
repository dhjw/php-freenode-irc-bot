#!/usr/bin/php
<?php
// PHP Freenode IRC bot by dw1
chdir(dirname(__FILE__));
set_time_limit(0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
if(empty($argv[1])) exit("Usage: ./bot.php <instance> [test mode]\nnote: settings-<instance>.php must exist\n");
if(!include("./settings-{$argv[1]}.php")) exit("./settings-{$argv[1]}.php not found. Create it.\n");

if(empty($user_agent)) $user_agent='Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
$num_file_get_retries=2;

// test mode
if(!empty($argv[2])){
	$datafile=__DIR__."/{$argv[1]}.data.test.json";
	$channel=$test_channel;
	$nick=$test_nick;
} else $datafile=__DIR__."/{$argv[1]}.data.json";

// load data
$instance_hash=md5(file_get_contents(dirname(__FILE__).'/bot.php'));
$botdata=json_decode(file_get_contents($datafile));
if(isset($botdata->nick)) $nick=$botdata->nick;
if(empty($network) || !in_array($network,['freenode','rizon','gamesurge','libera','other'])){
	echo "Missing or invalid \$network setting. Using default Freenode.\n";
	$network='freenode';
}

$helptxt="*** $nick $channel !help ***\n\nglobal commands:\n";
if(isset($custom_triggers)) foreach($custom_triggers as $v) if(isset($v[3])) $helptxt.=" {$v[3]}\n";
$helptxt.=" !w <term> - search Wikipedia and output a link if something is found
 !g <query> - create and output a Google search link
 !g- <query> - create and output a LMGTFY search link
 !i <query> - create and output a Google Images link\n";
if(!empty($youtube_api_key)) $helptxt.=" !yt <query> - search YouTube and output a link to the first result\n";
if(!empty($omdb_key)) $helptxt.=" !m <query or IMDb id e.g. tt123456> - search OMDB and output media info if found\n";
if(!empty($currencylayer_key)) $helptxt.=" !cc <amount> <from_currency> <to_currency> - currency converter\n";
if(!empty($wolfram_appid)) $helptxt.=" !wa <query> - query Wolfram Alpha\n";
$helptxt.=" !ud <term> [definition #] - query Urban Dictionary with optional definition number\n";
if(!empty($gcloud_translate_keyfile)) $helptxt.=" !tr <string> or e.g. !tr en-fr <string> - translate text to english or between other languages. see http://bit.ly/iso639-1\n";
$helptxt.=" !flip - flip a coin (call heads or tails first!)
 !rand <min> <max> [num] - get random numbers with optional number of numbers
 !8 or !8ball - magic 8-ball\n";
if(file_exists('/usr/games/fortune')) $helptxt.=" !f or !fortune - fortune\n";
$helptxt.=" !insult [target] - deliver a Shakespearian insult to the channel with an optional target
\nadmin commands:
 !s or !say <text> - output text to channel
 !e or !emote <text> - emote text to channel
 !t or !topic <message> - change channel topic
 !k or !kick <nick> [message] - kick a single user with an optional message\n";
if($network=='freenode') $helptxt.=" !r or !remove <nick> [message] - remove a single user with an optional message (quiet, no 'kick' notice to client)\n";
$helptxt.=" !b or !ban <nick or hostmask> [message] - ban by nick (*!*@mask) or hostmask. if by nick, also remove user with optional message
 !ub or !unban <hostmasks> - unban by hostmask\n";
if($network=='freenode' || $network=='libera') $helptxt.= " !q or !quiet [mins] <nick or hostmask> - quiet by nick (*!*@mask) or hostmask for optional [mins] or default no expiry\n";
if($network=='freenode') $helptxt.= " !rq or !removequiet [mins] <nick> [message] - remove user then quiet for optional [mins] with optional [message]\n";
if($network=='freenode' || $network=='libera') $helptxt.= " !uq or !unquiet <hostmasks> - unquiet by hostmask\n";
if($network=='freenode') $helptxt.= " !fyc [mins] <nick or hostmask> - ban by hostmask with redirect to ##fix_your_connection for [mins] or default 60 mins\n";
$helptxt.=" !nick <nick> - Change the bot's nick
 !invite <nick> - invite to channel
 !restart [message] - reload bot with optional quit message
 !update [message] - update bot with the latest from github and reload with optional quit message
 !die [message] - kill bot with optional quit message

note: commands may be used in channel or pm. separate multiple hostmasks with spaces. bans,".($network=='freenode'?' quiets,':'')." invites occur in $channel.";

// update help paste only if changed
echo "Checking if help text changed.. ";
if(!isset($botdata->help_text) || (isset($botdata->help_text) && $botdata->help_text<>$helptxt)) echo "yes, creating new paste\n";
else {
	$help_url=$botdata->help_url_short;
	echo "no, help_url=$help_url\n";
}

if(!isset($help_url)){
	file_put_contents("./help-$nick",$helptxt);
	$help_url=trim(shell_exec("pastebinit ./help-$nick"));
	unlink("./help-$nick");
	if(strpos($help_url,'http')===false){
	        echo "ERROR: Failed to paste help file! Is pastebinit installed? Help file disabled.\n";
	        $help_url='';
	}
	echo "help url=$help_url\n";
	if(!empty($help_url)){
		$botdata->help_url=$help_url;
		$help_url=make_bitly_url($help_url);
		echo "short help url=$help_url\n";
		$botdata->help_url_short=$help_url;
		$botdata->help_text=$helptxt;
		file_put_contents($datafile,json_encode($botdata));
	}
}

// main loop
if(isset($connect_ip) && strpos($connect_ip,':')!==false) $connect_ip="[$connect_ip]"; // add brackets to ipv6
if(isset($curl_iface) && strpos($curl_iface,':')!==false) $curl_iface="[$curl_iface]";
if(($user=='your_username' || $pass=='your_password' || empty($user) || empty($pass)) && (empty($disable_sasl) || empty($disable_nickserv))){
	echo "Username or password not set. Disabling authentication.\n";
	$disable_sasl=true;
	$disable_nickserv=true;
}
if($network=='gamesurge' && empty($disable_sasl)){
	echo "GameSurge network doesn't support SASL, disabling.\n";
	$disable_sasl=true;
}
if(empty($ircname)) $ircname=$user;
if(empty($ident)) $ident='bot';
if(empty($gcloud_translate_max_chars)) $gcloud_translate_max_chars=50000;
if(empty($ignore_urls)) $ignore_urls=[];
$ignore_urls=array_merge($ignore_urls,['google.com/search','google.com/images','scholar.google.com']);
if(empty($skip_dupe_output)) $skip_dupe_output=false;
if(!empty($op_bot)) $always_opped=true;
$orignick=$nick;
$last_nick_change=0;
$opped=false;
$connect=1;
$opqueue=[];
$doopdop_lock=false;
$check_lock=false;
$lasttime=0;
$users=[]; // user state data (nick, ident, host)
$flood_lines=[];
$base_msg_len=60;
if(!isset($custom_loop_functions)) $custom_loop_functions=[];

while(1){
	if($connect){
		$in_channel=0;
		// connect loop
		while(1){
			echo "Connecting...\n";
			$botmask='';
			if($custom_connect_ip) $socket_options=['socket'=>['bindto'=>"$connect_ip:0"]]; else $socket_options=[];
			if($network=='freenode') $socket_options['ssl']=['verifypeer'=>'false'];
			$socket_context=stream_context_create($socket_options);
			$socket=stream_socket_client($host,$errno,$errstr,15,STREAM_CLIENT_CONNECT,$socket_context);
			echo "* connect errno=$errno errstr=$errstr\n";
			if(!$socket || $errno<>0){ sleep(15); continue; }
			stream_set_timeout($socket,$stream_timeout);
			$connect_time=time();
			if(empty($disable_sasl)){
				echo "Authenticating with SASL\n";
				send("CAP LS\n");
				while($data=fgets($socket)){
					echo $data;
					$ex=explode(' ',trim($data));
					if(strpos($data,'CAP * LS')!==false && strpos($data,'sasl')!==false) send("CAP REQ :multi-prefix sasl\n");
					if(strpos($data,'CAP * ACK')!==false) send("AUTHENTICATE PLAIN\n");
					if(strpos($data,'AUTHENTICATE +')!==false || strpos($data,'AUTHENTICATE :+')!==false) send("AUTHENTICATE ".base64_encode("\0$user\0$pass")."\n");
					// if($ex[1]=='900') $botmask=substr($ex[3],strpos($ex[3],'@')+1);
					if(strpos($data,'SASL authentication successful')!==false){ send("CAP END\n"); break; }
					if(empty($data) || strpos($data,"ERROR")!==false){ echo "ERROR authenticating with SASL, restarting in 5s..\n"; sleep(5); dorestart(null,false); }
				}
			}
			send("NICK $nick\n");
			send("USER $ident $user $user :{$ircname}\n"); // first $user can be changed to modify ident and account login still works
			if(!empty($pass)) send("PASS $pass\n");
			if($network=='freenode' || $network=='libera'){
				send("CAP REQ account-notify\n");
				send("CAP REQ extended-join\n");
				send("CAP END\n");
			} elseif(empty($disable_sasl)) send("CAP END\n");

			// set up and wait til end of motd
			while($data=fgets($socket)){
				echo $data;
				$ex=explode(' ',trim($data));
				if($ex[0]=="PING"){
					send_no_filter("PONG ".rtrim($ex[1])."\n");
					continue;
				}
				if($ex[1]=='433'){
					echo "Nick in use.. changing and reconnecting\n";
					$nick=$orignick.$altchars[rand(0,count($altchars)-1)];
					continue(2);
				}
				if($ex[1]=='376' || $ex[1]=='422') break; // end
				if(empty($data) || strpos($data,"ERROR")!==false){ echo "ERROR waiting for MOTD, restarting in 5s..\n"; sleep(5); dorestart(null,false); }
			}

			if(!empty($disable_sasl) && empty($disable_nickserv)){
				if($network=='gamesurge'){
					echo "Authenticating with AuthServ\n";
					send("PRIVMSG AuthServ@Services.GameSurge.net :auth $user $pass\n");
				} else {
					echo "Authenticating with NickServ\n";
					send("PRIVMSG NickServ :IDENTIFY $user $pass\n");
				}
				sleep(2); // helps ensure cloak is applied on join
			}
			if(!empty($perform_on_connect)){
				$cs=explode(';',$perform_on_connect);
				foreach($cs as $c){
					send(trim(str_replace('$nick',$nick,$c))."\n");
					sleep(1);
				}
			}
			send("WHOIS $nick\n"); // botmask detection
			sleep(1);
			send("JOIN $channel\n");
			$connect=false;
			break;
		}
	}

	// main loop
	while($data=fgets($socket)) {
		echo $data;
		$time=time();
		$ex=explode(' ',$data);
		$incnick=substr($ex[0],1,strpos($ex[0],'!')-1);
		if($ex[1]=='PRIVMSG'){
			if(isme()) continue;
			preg_match('/^:[^\ ]*?\ PRIVMSG\ [^\ ]*?\ :(([^\ ]*?)(\ .*)?)\r\n$/',$data,$m);
			$msg=trim($m[1]);
			$trigger=$m[2];
			if(!empty(trim($m[3]))) $args=trim($m[3]); else $args='';
			if($ex[2]==$nick) $privto=$incnick; else $privto=$channel; // for PM response
			$baselen=$base_msg_len+strlen($privto); // for str_shorten max length
		} else {
			$trigger='';
			$args='';
			$msg='';
			$privto='';
			$baselen=$base_msg_len;
		}
		// echo "msg=\"$msg\"\ntrigger=\"$trigger\"\nargs=\"$args\"\n";

		// custom loop functions
		foreach($custom_loop_functions as $f) if($f()==2) continue(2);

		// ongoing checks
		if($time - $lasttime > 2 && $time - $connect_time > 10 && !$check_lock){
			$check_lock=true;
			$lasttime=$time;
			$botdata=json_decode(file_get_contents($datafile));
			// unban expired fyc
			if(isset($botdata->fyc) && !empty($botdata->fyc)){
				$botdata->fyc=(array)$botdata->fyc;
				// check if timeout
				$tounban=[];
				foreach($botdata->fyc as $k=>$f){
					list($ftime,$fdur,$fhost)=explode('|',$f);
					if(time()-$ftime >= $fdur){
						$tounban[]=$fhost;
						unset($botdata->fyc[$k]);
					}
				}
				if(!empty($tounban)){
					$botdata->fyc=array_values($botdata->fyc);
					$opqueue[]=['-b',$tounban];
					file_put_contents($datafile,json_encode($botdata));
					getops();
				}
			}
			// unquiet expired q (TODO: merge with above fyc expiries)
			if(isset($botdata->tq) && !empty($botdata->tq)){
				$botdata->tq=(array)$botdata->tq;
				// check if timeout
				$tounban=[];
				foreach($botdata->tq as $k=>$f){
					list($ftime,$fdur,$fhost)=explode('|',$f);
					if(time()-$ftime >= $fdur){
						if(strpos($fhost,'!')===false && strpos($fhost,'$a:')===false) $fhost.='!*@*';
						$tounban[]=$fhost;
						unset($botdata->tq[$k]);
					}
				}
				if(!empty($tounban)){
					$botdata->tq=array_values($botdata->tq);
					file_put_contents($datafile,json_encode($botdata));
					if($network=='freenode'){
						foreach($tounban as $who) send("PRIVMSG chanserv :UNQUIET $channel $who\n");
					} elseif($network=='libera' || $network=='other'){
						$opqueue[]=['-q',$tounban];
						getops();
					}
				}
			}
			$check_lock=false;
		}

		// ignore specified nicks with up to one non-alpha char
		if(isset($ignore_nicks) && is_array($ignore_nicks) && !empty($incnick)) foreach($ignore_nicks as $n){
			if(preg_match("/^".preg_quote($n)."[^a-zA-Z]?$/",$incnick)){
				echo "Ignoring $incnick\n";
				continue(2);
			}
		}

		// get botmask from WHOIS on connect
		if($ex[1]=='311'){
			if($ex[2]==$nick){
				$botmask=$ex[5];
				echo "Detected botmask: $botmask\n";
				$base_msg_len=strlen(":$nick!~$ident@$botmask PRIVMSG  :\r\n");
			}
		}

		// recover main nick
		if($nick<>$orignick && $time-$connect_time>=10 && $time-$last_nick_change>=10){
			send(":$nick NICK $orignick\n");
			$last_nick_change=$time;
			continue;
		}

		// nick changes
		if($ex[1]=='NICK'){
			if(isme()){
				$newnick=trim(ltrim($ex[2],':'));
				echo "Changed bot nick to $newnick\n";
				$nick=$newnick;
				$orignick=$nick;
				$botdata=json_decode(file_get_contents($datafile));
				$botdata->nick=$nick;
				file_put_contents($datafile,json_encode($botdata));
				if(($network=='freenode' || $network=='libera') && (empty($disable_nickserv) || empty($disable_sasl))) send("PRIVMSG NickServ GROUP\n");
				elseif($network=='rizon' && (empty($disable_nickserv) || empty($disable_sasl))) send("PRIVMSG NickServ GROUP $user $pass\n");
				$base_msg_len=strlen(":$nick!~$ident@$botmask PRIVMSG  :\r\n");
				continue;
			} else {
				list($tmpnick)=parsemask($ex[0]);
				$id=search_multi($users,'nick',$tmpnick);
				if(!empty($id))	$users[$id]['nick']=rtrim(substr($ex[2],1));
				else echo "ERROR: Nick changed but not in \$users. This should not happen!\n";
				if($network=='rizon') send("WHO $tmpnick\n"); // check for account again
				continue;
			}
		}

		// ping pong
		if($ex[0]=="PING"){
			send_no_filter("PONG ".rtrim($ex[1])."\n");
			if(!$in_channel) send("JOIN $channel\n");
			continue;
		}

		// got ops, run op queue
		if(preg_match('/^:ChanServ!ChanServ@services[^\ ]* MODE '.preg_quote("$channel +o $nick").'$/', rtrim($data))){
			echo "Got ops, running op queue\n";
			print_r($opqueue);
			$opped=true;
			$getops_lock=false;
			doopdop();
			continue;
		}

		// end of NAMES list, joined main channel so do a WHO now
		if($ex[1]=='366'){
			$in_channel=1;
			if(in_array($network,['freenode','gamesurge','libera'])) send("WHO $channel %hna\n"); else send("WHO $channel\n");
			continue;
		}

		// parse WHO listing
		if($ex[1]=='352'){ // rfc1459 - rizon
			if(strpos($ex[8],'r')!==false) $a=$ex[7]; else $a='0';
			$id=search_multi($users,'nick',$ex[7]);
			if(empty($id)) $users[]=['nick'=>$ex[7],'host'=>$ex[5],'account'=>$a];
			else {
				$users[$id]['host']=$ex[5];
				$users[$id]['account']=$a;
			}
			if($host_blacklist_enabled) check_blacklist($ex[4],$ex[3]);
			// check_dnsbl($ex[7],$ex[5],true);
			continue;
		}
		if($ex[1]=='354'){ // freenode, gamesurge, libera
			$id=search_multi($users,'nick',$ex[4]);
			if(empty($id)) $users[]=['nick'=>$ex[4],'host'=>$ex[3],'account'=>ltrim(rtrim($ex[5]),':')];
			else {
				$users[$id]['host']=$ex[3];
				$users[$id]['account']=ltrim(rtrim($ex[5]),':');
			}
			if($host_blacklist_enabled) check_blacklist($ex[4],$ex[3]);
			// check_dnsbl($ex[7],$ex[5],true);
			continue;
		}

		// 315 end of WHO list
		if($ex[1]=='315'){
			if(empty($first_join_done)){
				echo "Join to $channel complete.\n";
				if(!empty($op_bot)) send("PRIVMSG ChanServ :OP $channel $nick\n");
				if(!empty($voice_bot)) send("PRIVMSG ChanServ :VOICE $channel $nick\n");
				$first_join_done=true;
			}
			continue;
		}

		// Update $users on JOIN, PART, QUIT, KICK, NICK
		if($ex[1]=='JOIN' && !isme()){
			// just add user to array because they shouldnt be there already
			// parse ex0 for username and hostmask
			list($tmpnick,$tmphost)=parsemask($ex[0]);
			if($network=='freenode' || $network=='libera'){ // extended-join with account
				if($ex[3]=='*') $ex[3]='0';
				$users[]=['nick'=>$tmpnick,'host'=>$tmphost,'account'=>$ex[3]];
			} else {
				$users[]=['nick'=>$tmpnick,'host'=>$tmphost,'account'=>'0'];
				if($network=='gamesurge') send("WHO $tmpnick %hna\n"); else send("WHO $tmpnick\n");
			}
			if($host_blacklist_enabled) check_blacklist($tmpnick,$tmphost);
			// if(!isadmin()) check_dnsbl($tmpnick,$tmphost); else echo "dnsbl check skipped: isadmin\n";
			continue;
		}

		if($ex[1]=='PART' || $ex[1]=='QUIT' || $ex[1]=='KICK'){
			if(($ex[1]=='PART' && isme()) || ($ex[1]=='KICK' && $ex[3]==$nick)){ // left channel, rejoin
				$in_channel=0;
				send("JOIN $channel\n");
				continue;
			}
			if($ex[1]=='KICK') $tmpnick=$ex[3]; else list($tmpnick)=parsemask($ex[0]);
			$id=search_multi($users,'nick',$tmpnick);
			if(!empty($id)){
				unset($users[$id]);
				$users=array_values($users);
			}
			continue;
		}

		if($ex[1]=='ACCOUNT'){
			// find user and update account
			list($tmpnick)=parsemask($ex[0]);
			$id=search_multi($users,'nick',$tmpnick);
			if(!empty($id))	$users[$id]['account']=rtrim($ex[2]);
			else echo "ERROR: Account changed but not in \$users. This should not happen!\n";
			continue;
		}

		// admin triggers
		if(!empty($trigger) && substr($trigger,0,1)=='!' && isadmin()){
			if ($trigger=='!s' || $trigger=='!say') {
				send( "PRIVMSG $channel :$args \n");
				continue;
			} elseif ($trigger=='!e' || $trigger=='!emote') {
				send( "PRIVMSG $channel :".pack('C',0x01)."ACTION $args".pack('C',0x01)."\n");
				continue;
			} elseif($trigger=='!ban' || $trigger=='!b'){
				// if there's a space get the ban reason and use it for remove
				if(strpos($args,' ')!==false) $reason=substr($args,strpos($args,' ')+1); else $reason="Goodbye.";
				list($mask)=explode(' ',$args);
				// if contains $ or @, ban by mask, else build mask from nick
				if(strpos($mask,'@')===false && strpos($mask,'$')===false){
					$tmpnick=$mask;
					$id=search_multi($users,'nick',$mask);
					if(!$id){
						if($ex[2]==$nick) $tmp=$incnick; else $tmp=$channel; // allow PM response
						send("PRIVMSG $tmp :Nick not found in channel.\n");
						continue;
					}
					if(($network=='freenode' || $network=='libera') && $users[$id]['account']<>'0') $mask='$a:'.$users[$id]['account'];
					else $mask="*!*@".$users[$id]['host'];
				} else $tmpnick='';
				$mask=str_replace('@gateway/web/freenode/ip.','@',$mask);
				echo "Ban $mask\n";
				$opqueue[]=['+b',[$mask,$reason,$tmpnick]];
				getops();
			} elseif($trigger=='!unban' || $trigger=='!ub'){
				$opqueue[]=['-b',explode(' ',$args)];
				getops();
			} elseif(($trigger=='!quiet' || $trigger=='!q') && ($network=='freenode' || $network=='libera' || $network=='other')){
				$arr=explode(' ',$args);
				if(is_numeric($arr[0])){
					$timed=1;
					$tqtime=$arr[0]*60;
					unset($arr[0]);
					$arr=array_values($arr);
				} else $timed=false;
				if(empty($arr)) continue; // ensure there's data
				foreach($arr as $who){
					// check if nick or mask
					if(strpos($who,'@')===false && strpos($who,'$')===false){
						$id=search_multi($users,'nick',$who);
						if(!$id){
							if($ex[2]==$nick) $tmp=$incnick; else $tmp=$channel; // allow PM response
							send("PRIVMSG $tmp :Nick not found in channel.\n");
							continue;
						}
						// if has account use it else create mask
						if(($network=='freenode' || $network=='libera') && $users[$id]['account']<>'0') $who='$a:'.$users[$id]['account'];
						else $who="*!*@".$users[$id]['host'];
					}
					echo "Quiet $who, timed=$timed tqtime=$tqtime\n";
					if($network=='freenode') $who=str_replace('@gateway/web/freenode/ip.','@',$who);
					if($timed) timedquiet($tqtime,$who);
					else {
						if($network == 'freenode'){
							send("PRIVMSG chanserv :QUIET $channel $who\n");
						} elseif ($network=='libera' || $network=='other'){
							$opqueue[]=['+q',$who];
							getops();
						}
					}
				}
				continue;
			} elseif(($trigger=='!removequiet' || $trigger=='!rq') && $network=='freenode'){ // shadowquiet when channel +z
				$arr=explode(' ',$args);
				if(is_numeric($arr[0])){
					$timed=1;
					$tqtime=$arr[0]*60;
					unset($arr[0]);
					$arr=array_values($arr);
				} else $timed=false;
				if(empty($arr)) continue; // ensure there's data
				$who=$arr[0];
				unset($arr[0]);
				$arr=array_values($arr);
				$m=trim(implode(' ',$arr));
				// check if nick or mask
				if(strpos($who,'@')===false && strpos($who,'$')===false){
					$id=search_multi($users,'nick',$who);
					if(!$id){
						if($ex[2]==$nick) $tmp=$incnick; else $tmp=$channel; // allow PM response
						send("PRIVMSG $tmp :Nick not found in channel.\n");
						continue;
					} else $thenick=$who;
					// if has account use it else create mask
					if($network=='freenode' && $users[$id]['account']<>'0') $who='$a:'.$users[$id]['account'];
					else $who="*!*@".$users[$id]['host'];
				}
				echo "Quiet $who, timed=$timed tqtime=$tqtime\n";
				if($network=='freenode') $who=str_replace('@gateway/web/freenode/ip.','@',$who);
				$opqueue[]=['remove_quiet',$who,['nick'=>$thenick,'msg'=>$m,'timed'=>$timed,'tqtime'=>$tqtime]];
				getops();
				continue;
			} elseif(($trigger=='!unquiet' || $trigger=='!uq')){
				if($network=='freenode'){
					send("PRIVMSG chanserv :UNQUIET $channel $args\n");
					continue;
				} elseif ($network=='libera' || $network=='other'){
					$opqueue[]=['-q',explode(' ',$args)];
					getops();
					continue;
				}
			} elseif($trigger=='!fyc' && $network=='freenode'){
				// check if mins provided
				$arr=explode(' ',$args);
				if(is_numeric($arr[0])){
					if(!isset($arr[1])) continue; // ensure theres more than just mins
					$fyctime=$arr[0];
					unset($arr[0]);
					$arr=array_values($arr);
				} else $fyctime=60;
				list($mask)=$arr;
				// if contains $ or @, ban by mask, else build mask from nick
				if(strpos($mask,'@')===false && strpos($mask,'$')===false){
					$id=search_multi($users,'nick',$mask);
					if(!$id){
						if($ex[2]==$nick) $tmp=$incnick; else $tmp=$channel; // allow PM response
						send("PRIVMSG $tmp :Nick not found in channel.\n");
						continue;
					}
					$mask="*!*@".$users[$id]['host'];
				}
				echo "FixYourConnection $mask, fyctime=$fyctime\n";
				$opqueue[]=['fyc',[$mask,$fyctime]];
				getops();
				continue;
			} elseif($trigger=='!t' || $trigger=='!topic'){
				if(in_array($network,['freenode','gamesurge','rizon','libera'])) send("PRIVMSG ChanServ :TOPIC $channel $args\n");
				else {
					$opqueue[]=['topic',null,['msg'=>$args]];
					getops();
				}
				continue;
			} elseif($trigger=='!die'){
				send("QUIT :".(!empty($args)?$args:'shutdown')."\n");
				exit;
			} elseif($trigger=='!k' || $trigger=='!kick'){
				$arr=explode(' ',$args);
				if(empty($arr)) continue;
				if($arr[1]) $m=substr($args,strpos($args,' ')+1); else $m=false;
				$opqueue[]=['kick',$arr[0],['msg'=>$m]];
				getops();
				continue;
			}  elseif(($trigger=='!r' || $trigger=='!remove') && $network=='freenode'){
				$arr=explode(' ',$args);
				if(empty($arr)) continue;
				if($arr[1]) $m=substr($args,strpos($args,' ')+1); else $m=false;
				echo "Remove {$arr[0]}, msg={$m}\n";
				$opqueue[]=['remove',$arr[0],['msg'=>$m]];
				getops();
				continue;
			} elseif($trigger=='!nick'){
				if(empty($args)) continue;
				send("NICK $args\n");
				continue;
			} elseif($trigger=='!invite'){
				$arr=explode(' ',$args);
				$opqueue[]=['invite',$arr[0]];
				getops();
				continue;
			} elseif($trigger=='!restart'){
				dorestart($args);
			} elseif($trigger=='!update'){
				$r=curlget([CURLOPT_URL=>'https://raw.githubusercontent.com/dhjw/php-freenode-irc-bot/master/bot.php']);
				if(empty($r)){
					send("PRIVMSG $privto :Error downloading update\n");
					continue;
				}
				if($instance_hash==md5($r)){
					send("PRIVMSG $privto :Already up to date\n");
					continue;
				}
				if(file_get_contents(dirname(__FILE__).'/bot.php')<>$r && !file_put_contents(dirname(__FILE__).'/bot.php',$r)){
					send("PRIVMSG $privto :Error writing updated bot.php\n");
					continue;
				}
				send("PRIVMSG $privto :Update installed. See https://bit.ly/bupd8 for changes. Restarting\n");
				dorestart(!empty($args)?$args:'update');
			} elseif($trigger=='!raw'){
				send("$args\n");
				continue;
			}
		}

		// custom triggers
		if(!empty($trigger) && isset($custom_triggers)){
			foreach($custom_triggers as $k=>$v){
				@list($trig,$text,$pm)=$v;
				if(!isset($pm)) $pm=true;
				if($pm) $target=$privto; else $target=$channel;
				if($trigger==$trig){
					echo "Custom trigger $trig called\n";
					if(substr($text,0,9)=='function:'){
						$func=substr($text,9);
						$func();
					} else send("PRIVMSG $target :$text\n");
					continue(2);
				}
			}
		}

		// global triggers
		if(!empty($trigger) && substr($trigger,0,1)=='!' && !$disable_triggers){
			if($ex[2]==$nick) $privto=$incnick; else $privto=$channel; // allow PM response
			if($trigger=='!help'){
				// foreach(explode("\n",$helptxt) as $line){ send("PRIVMSG $incnick :$line\n"); sleep(1); }
				if(!empty($help_url) && empty($disable_help)) send("PRIVMSG $incnick :Please visit $help_url\n");
				else send("PRIVMSG $privto :Help disabled\n");
				continue;
			} elseif($trigger=='!w' || $trigger=='!wiki'){
				if(empty($args)) continue;
				$u="https://en.wikipedia.org/w/index.php?search=".urlencode($args);
				for($i=$num_file_get_retries;$i>0;$i--){
					$noextract=false;
					$nooutput=false;
					echo "Searching Wikipedia.. ";
					$response=curlget([CURLOPT_URL=>$u]);
					if(empty($response)){
						echo "no response/connect failed, retrying\n";
						sleep(1);
						$nooutput=true;
						continue;
					}
					$url=$curl_info['EFFECTIVE_URL'];

					if(strstr($response,'wgInternalRedirectTargetUrl')!==false){
						echo "getting internal/actual wiki url.. ";
						$tmp=substr($response,strpos($response,'wgInternalRedirectTargetUrl')+30);
						$tmp=substr($tmp,0,strpos($tmp,'"'));
						echo "found $tmp\n";
						if(!empty($tmp)) $url="https://en.wikipedia.org$tmp";
					}

					$noextract=false;
					$nooutput=false;
					if(strpos($response,'mw-search-nonefound')!==false || strpos($response,'mw-search-createlink')!==false){
						send("PRIVMSG $privto :There were no results matching the query.\n");
						$noextract=true;
						$nooutput=true;
						break;
					} elseif(strpos($response,'disambigbox')!==false){
						if(strpos($url,'disambiguation')===false) $url.=' (disambiguation)';
						$noextract=true;
						break;
					}
					if(!$noextract) $e=get_wiki_extract(substr($url,strrpos($url,'/')+1));
					break;
				}
				if(!empty($e) && !$noextract) $url="\"$e\" $url";
				if(!$nooutput) send("PRIVMSG $privto :$url\n");
				continue;
				// Google
			} elseif($trigger=='!g' || $trigger=='!i' || $trigger=='!g-' || $trigger=='!google'){
				if(empty($args)) continue;
				if($trigger=='!g-'){
					send("PRIVMSG $privto :http://lmgtfy.com/?q=".urlencode($args)."\n");
					continue;
				}
				if($trigger=='!g' || $trigger=='!google') $tmp='search'; else $tmp='images';
				send("PRIVMSG $privto :https://www.google.com/$tmp?q=".urlencode($args)."\n");
				continue;
			} elseif($trigger=='!ddg' || $trigger=='!ddi' || $trigger=='!dg' || $trigger=='!di'){
				// DDG
				if(empty($args)) continue;
				if($trigger=='!ddi' || $trigger=='!di') $tmp="&iax=1&ia=images"; else $tmp='';
				send("PRIVMSG $privto :https://duckduckgo.com/?q=".urlencode($args)."$tmp\n");
				continue;
			} elseif($trigger=='!yt'){
				if(empty($args)){
					send("PRIVMSG $privto :Provide a query.\n");
					continue;
				}
				for($i=$num_file_get_retries;$i>0;$i--){
					$tmp=file_get_contents("https://www.googleapis.com/youtube/v3/search?q=".urlencode($args)."&part=snippet&maxResults=1&type=video&key=$youtube_api_key");
					$tmp=json_decode($tmp);
					if(!empty($tmp)) break; else if($i>1) sleep(1);
				}
				$v=$tmp->items[0]->id->videoId;
				if(empty($tmp)){
					send("PRIVMSG $privto :[ Temporary YouTube API error ]\n");
					continue;
				} elseif(empty($v)){
					send("PRIVMSG $privto :There were no results matching the query.\n");
					continue;
				}
				for($i=$num_file_get_retries;$i>0;$i--){
					$tmp2=file_get_contents("https://www.googleapis.com/youtube/v3/videos?id={$v}&part=contentDetails,statistics&key=$youtube_api_key");
					$tmp2=json_decode($tmp2);
					print_r($tmp2);
					if(!empty($tmp2)) break; else if($i>1) sleep(1);
				}
				$ytextra='';
				$dur=covtime($tmp2->items[0]->contentDetails->duration);
				if($dur<>'0:00') $ytextra.=" | $dur";
				$ytextra.=" | {$tmp->items[0]->snippet->channelTitle}";
				$ytextra.=" | ".number_format($tmp2->items[0]->statistics->viewCount)." views";
				$title=html_entity_decode($tmp->items[0]->snippet->title,ENT_QUOTES);
				send("PRIVMSG $privto :https://youtu.be/$v | $title$ytextra\n");
				continue;
			}// OMDB, check for movie or series only (no episode or game)
			elseif($trigger=='!m'){
				echo "Searching OMDB.. ";
				ini_set('default_socket_timeout',30);
				// by id only
				$tmp=rtrim($ex[4]);
				if(substr($tmp,0,2)=='tt'){
					$cmd="http://www.omdbapi.com/?i=".urlencode($tmp)."&apikey={$omdb_key}";
					echo "by id\n";
					for($i=$num_file_get_retries;$i>0;$i--){
						$tmp=curlget([CURLOPT_URL=>$cmd]);
						$tmp=json_decode($tmp);
						print_r($tmp);
						if(!empty($tmp)) break; else if($i>1) sleep(1);
					}
					if(empty($tmp)){ send("PRIVMSG $privto :OMDB API error.\n"); continue; }
					if($tmp->Type=='movie') $tmp3=''; else $tmp3=" {$tmp->Type}";
					if($tmp->Response=='True') send("PRIVMSG $privto :\xe2\x96\xb6 {$tmp->Title} ({$tmp->Year}$tmp3) | {$tmp->Genre} | {$tmp->Actors} | \"{$tmp->Plot}\" http://www.imdb.com/title/{$tmp->imdbID}/ [{$tmp->imdbRating}]\n"); elseif($tmp->Response=='False') send("PRIVMSG $privto :{$tmp->Error}\n"); else send("PRIVMSG $privto :OMDB API error.\n");
					continue;
				}
				// search movies and series
				// check if final parameter is a year 1800 to 2200
				if(count($ex)>5){ // only if 2 words provided
					$tmp=rtrim($ex[count($ex)-1]);
					if(is_numeric($tmp) && ($tmp>1800 && $tmp<2200)){
						echo "year detected. appending api query and truncating msg\n";
						$tmp2="&y=$tmp";
						$args=substr($args,0,strrpos($args,' '));
					} else $tmp2='';
				} else $tmp2='';
				// call with year first, without year after
				while(1){
					foreach(['movie','series'] as $k=>$t){ // multiple calls are needed
						$cmd="http://www.omdbapi.com/?apikey={$omdb_key}&type=$t$tmp2&t=".urlencode($args);
						echo "url=$cmd\n";
						for($i=$num_file_get_retries;$i>0;$i--){
							$tmp=curlget([CURLOPT_URL=>$cmd]);
							$tmp=json_decode($tmp);
							if(!empty($tmp)) break; else if($i>1) sleep(1);
						}
						if(empty($tmp)){ send("PRIVMSG $privto :OMDB API error ($k)\n"); continue; }
						if($tmp->Response=='True') break(2);
						//usleep(100000);
					}
					if(!empty($tmp2)){ echo "now trying without year\n"; $tmp2=''; } else break;
				}
				if($tmp->Response=='False'){ send("PRIVMSG $privto :Media not found.\n"); continue; }
				if($tmp->Type=='movie') $tmp3=''; else $tmp3=" {$tmp->Type}";
				if(isset($tmp->Response)) send("PRIVMSG $privto :\xe2\x96\xb6 {$tmp->Title} ({$tmp->Year}$tmp3) | {$tmp->Genre} | {$tmp->Actors} | \"{$tmp->Plot}\" http://www.imdb.com/title/{$tmp->imdbID}/ [{$tmp->imdbRating}]\n"); else send("PRIVMSG $privto :OMDB API error.\n");
				continue;
			} elseif($trigger=='!tr' || $trigger=='!translate'){
				// google translate, requires gcloud commandline tool to be installed
				echo "Translating.. ";
				// check limit
				$ym=date("Y-m");
				if(!isset($botdata->translate_char_cnt)) $botdata->translate_char_cnt=[];
				$botdata->translate_char_cnt=(array) $botdata->translate_char_cnt;
				if(!isset($botdata->translate_char_cnt[$ym])) $botdata->translate_char_cnt[$ym]=0;
				echo "quota={$botdata->translate_char_cnt[$ym]}/$gcloud_translate_max_chars\n";
				if($botdata->translate_char_cnt[$ym]+strlen($args)>$gcloud_translate_max_chars){
					send("PRIVMSG $privto :Monthly translate limit exceeded\n");
					continue;
				}
				// get a token
				passthru("gcloud auth activate-service-account --key-file=$gcloud_translate_keyfile");
				$tmp2=rtrim(shell_exec("gcloud auth print-access-token"));
				$words=explode(' ',$args);
				if(strpos($words[0],'-')!==false && strlen($words[0])==5){
					list($source,$target)=explode('-',$words[0]);
					if($source=='jp') $source='ja'; if($target=='jp') $target='ja';
					unset($words[0]);
					$words=array_values($words);
					$args=implode(' ',$words);
					$lang=get_lang($source)." to ".get_lang($target);
				} else {
					$source='';
					$target='en';
				}
				$tmp=json_encode(['q'=>$args,'source'=>$source,'target'=>$target]);
				$tmp=curlget([
					CURLOPT_URL=>'https://translation.googleapis.com/language/translate/v2',
					CURLOPT_CUSTOMREQUEST=>'POST',
					CURLOPT_POSTFIELDS=>$tmp,
					CURLOPT_HTTPHEADER=>[
						'Content-Type: application/json',
						'Authorization: Bearer '.$tmp2
					]
				]);
				$tmp=json_decode($tmp);
				if(isset($tmp->data->translations[0])){
					if(isset($tmp->data->translations[0]->detectedSourceLanguage)) $lang=get_lang($tmp->data->translations[0]->detectedSourceLanguage);
					send("PRIVMSG $privto :($lang) ".html_entity_decode($tmp->data->translations[0]->translatedText,ENT_QUOTES | ENT_HTML5,'UTF-8')."\n");
				} else {
					send("PRIVMSG $privto :Could not translate.\n");
				}
				$botdata->translate_char_cnt[$ym]+=strlen($args);
				file_put_contents($datafile,json_encode($botdata));
				continue;
			} elseif($trigger=='!cc'){
				// currency converter
				echo "Converting currency..\n";
				$ex=explode(' ',trim(str_ireplace(' in ',' ',$data)));
				if(empty($ex[4]) || empty($ex[5]) || empty($ex[6]) || !empty($ex[7])){ send("PRIVMSG $privto :Usage: !cc <amount> <from_currency> <to_currency>\n"); continue; }
				$ex[count($ex)-1]=rtrim($ex[count($ex)-1]); // todo: do this globally at beginning
				$ex[4]=(float)preg_replace('/[^0-9.]/','',$ex[4]); // strip non numeric
				$ex[5]=strtoupper(preg_replace('/[^a-zA-Z]/','',$ex[5])); // strip non alpha
				$ex[6]=strtoupper(preg_replace('/[^a-zA-Z]/','',$ex[6]));
				if($ex[5]=='BTC') $tmp1=strlen(substr(strrchr($ex[4],'.'), 1)); else $tmp1=2; // precision1
				if($ex[6]=='BTC'){ $tmp2=strlen(substr(strrchr($ex[4],'.'), 1)); if($tmp2<5) $tmp2=5; } else $tmp2=2; // precision2
				echo "ex4={$ex[4]} from={$ex[5]} to={$ex[6]} precision=$tmp1 time=$time cclast=$cclast\n";
				if($ex[5]==$ex[6]){ send("PRIVMSG $privto :A wise guy, eh?\n"); continue; }
				if(empty($cccache) || $time-$cclast>=300){ // cache results for 5 mins
					$cmd="http://www.apilayer.net/api/live?access_key={$currencylayer_key}&format=1";
					for($i=$num_file_get_retries;$i>0;$i--){
						$tmp=file_get_contents($cmd);
						$tmp=json_decode($tmp);
						if(!empty($tmp)) break; else if($i>1) sleep(1);
					}
					if(empty($tmp)){ send("PRIVMSG $privto :Finance API error.\n"); continue; }
					if($tmp->success){ echo "got success, caching\n"; $cccache=$tmp; $cclast=$time; } else echo "got error, not caching\n";
				} else $tmp=$cccache;
				if(isset($tmp->quotes)){
					if(!isset($tmp->quotes->{'USD'.$ex[5]})){ send("PRIVMSG $privto :Currency {$ex[5]} not found.\n"); continue; }
					if(!isset($tmp->quotes->{'USD'.$ex[6]})){ send("PRIVMSG $privto :Currency {$ex[6]} not found.\n"); continue; }
					$tmp3=$tmp->quotes->{'USD'.$ex[5]} / $tmp->quotes->{'USD'.$ex[6]}; // build rate from USD
					echo "rate=$tmp3\n";
					send("PRIVMSG $privto :".number_format($ex[4],$tmp1)." {$ex[5]} = ".number_format(($ex[4]/$tmp3),$tmp2)." {$ex[6]} (".make_bitly_url("https://finance.yahoo.com/quote/{$ex[5]}{$ex[6]}=X").")\n");
				} else send("PRIVMSG $privto :Finance API error.\n");
				continue;
			} elseif($trigger=='!wa'){
				// wolfram alpha
				$u="http://api.wolframalpha.com/v2/query?input=".urlencode($args)."&output=plaintext&appid={$wolfram_appid}";
				try {
					$xml=new SimpleXMLElement(file_get_contents($u));
				} catch(Exception $e){
					send("PRIVMSG $privto :API error, try again\n");
					print_r($e);
					continue;
				}
				if(!empty($xml) && !empty($xml->pod[1]->subpod->plaintext)){
					print_r([$xml->pod[0],$xml->pod[1]]);
					if($xml->pod[1]->subpod->plaintext=='(data not available)') send("PRIVMSG $privto :Data not available.\n");
					else {
						$o=str_shorten(trim(str_replace("\n",' • ',$xml->pod[1]->subpod->plaintext)),999,['nowordcut'=>1]);
						echo "o=\"$o\"\n";
						// turn fraction into decimal
						if(preg_match('#^(\d+)/(\d+)(?: \(irreducible\))?#',$o,$m)){
							if(extension_loaded('bcmath')){
								bcscale(64);
								$o=rtrim(bcdiv($m[1],$m[2]),'0');
								if(strpos($o,'.')!==false){
									list($a,$b)=explode('.',$o);
									if(strlen($b)>63) $b=substr($b,0,63).'...';
									$o="$a.$b";
								}
							} else echo "Can't reduce Wolfram fraction result to decimal because bcmath extension not loaded.\n";
						}
						send("PRIVMSG $privto :$o\n");
					}
				} else send("PRIVMSG $privto :Data not available.\n");
				continue;
			} elseif($trigger=='!ud'){
				// urban dictionary
				if(empty($args)){ send("PRIVMSG $privto :Provide a term to define.\n"); continue; }
				$a=explode(' ',$args);
				if(is_numeric($a[count($a)-1])){
					$num=$a[count($a)-1]-1;
					unset($a[count($a)-1]);
					$q=implode(' ',$a);
				} else {
					$num=0;
					$q=$args;
				}
				echo "Searching Urban Dictionary.. q=$q num=$num\n";
				$r=curlget([CURLOPT_URL=>'http://api.urbandictionary.com/v0/define?term='.urlencode($q)]);
				$r=json_decode($r);
				if(empty($r) || empty($r->list[0])){ send("PRIVMSG $privto :Term not found.\n"); continue; }
				if(empty($r->list[$num])){ send("PRIVMSG $privto :Definition not found.\n"); continue; }
				$d=str_replace(["\r","\n","\t"],' ',$r->list[$num]->definition);
				$d=trim(preg_replace('/\s+/',' ',str_replace(["[","]"],'',$d)));
				$d=str_replace(' .','.',$d);
				$d=str_replace(' ,',',',$d);
				$d=str_shorten($d,360);
				$d="\"$d\"";
				if(strtolower($r->list[$num]->word)<>strtolower($q)) $d="({$r->list[$num]->word}) $d";
				$d.=' '.make_bitly_url($r->list[0]->permalink);
				send("PRIVMSG $privto :$d\n");
			} elseif($trigger=='!flip'){
				$tmp=get_true_random(0,1);
				if($tmp==0) $tmp='heads'; else $tmp='tails';
				send("PRIVMSG $privto :".pack('C',0x01)."ACTION flips a coin, which lands \x02$tmp\x02 side up.".pack('C',0x01)."\n");
				continue;
			} elseif($trigger=='!8' || $trigger=='!8ball'){
				$answers=["It is certain","It is decidedly so","Without a doubt","Yes definitely","You may rely on it","As I see it, yes","Most likely","Outlook good","Yes","Signs point to yes","Signs point to no","No","Nope","Absolutely not","Heck no","Don't count on it","My reply is no","My sources say no","Outlook not so good","Very doubtful"];
				$tmp=get_true_random(0,count($answers)-1);
				send("PRIVMSG $privto :{$answers[$tmp]}\n");
				continue;
			} elseif($trigger=='!f' || $trigger=='!fortune'){
				// expects /usr/games/fortune to be installed
				echo "Getting fortune..\n";
				$args=trim(preg_replace('#[^[:alnum:][:space:]-/]#u','',$args));
				for($i=0;$i<2;$i++){
				        $f=trim(preg_replace('/\s+/',' ',str_replace("\n",' ',shell_exec("/usr/games/fortune -s '$args' 2>&1"))));
				        if($f=='No fortunes found'){ echo "Fortune type not found, getting from all.\n"; $args=''; continue; }
				        break;
				}
				send("PRIVMSG $privto :$f\n");
				continue;
			} elseif($trigger=='!rand'){
				echo "Getting random numbers, min={$ex[4]} max={$ex[5]} cnt={$ex[6]}\n";
				if(!is_numeric($ex[4]) || !is_numeric(trim($ex[5]))){
					send("PRIVMSG $privto :Please provide two numbers for min and max. e.g. !rand 1 5\n");
					continue;
				}
				send("PRIVMSG $privto :".get_true_random($ex[4],$ex[5],!empty($ex[6])?$ex[6]:1)."\n");
				continue;
			} elseif($trigger=='!insult'){
				if(!empty(trim($ex[4]))) $target=trim($ex[4]).': '; else $target='';
				send("PRIVMSG $channel :$target".s_insult()."\n");
				continue;
			}
		}

		// URL Titles
		if($ex[1]=='PRIVMSG' && $ex[2]==$channel && !isme() && !$disable_titles){
			preg_match_all('#\bhttps?://(?:\b[a-z\d-]{1,63}\b\.)+[a-z]+(?:[/?\#](?:([^\s`!\[\]{}();\'"<>«»“”‘’]+)|\((?1)?\))+(?<![.,]))?#i',$msg,$m); // get urls, only capture parenthesis if both are found, ignore trailing periods and commas
			if(!empty($m[0])) $urls=array_unique($m[0]); else $urls=[];
			foreach($urls as $u){
				$u=rtrim($u,pack('C',0x01)); // trim for ACTIONs
				foreach($ignore_urls as $v) if(preg_match('#^\w*://(?:[a-zA-Z0-9-]+\.){0,}'.preg_quote($v).'#',$u)){
					echo "Ignored URL $v\n";
					continue(2);
				}
				$u_tries=0;
				$invidious_mirror=false;
				$og_title=false;
				$og_desc=false;
				while(1){ // extra loop for retries
					echo "Checking URL: $u\n";
					$html='';

					// imgur via api
					// single image post ids are usually accessible as an image rather than an album so first check if an image then an album
					if(!empty($imgur_client_id) && preg_match('#^https?://(i\.)?imgur\.com/(?:(?:gallery|a|r)/)?(\w+)(?:/(\w+))?#',$u,$m)){
						echo "getting from imgur api..\n";
						if(!empty($m[3])) $id=$m[3]; else $id=$m[2];
						$r=json_decode(curlget([CURLOPT_URL=>"https://api.imgur.com/3/image/$id",CURLOPT_HTTPHEADER=>["Authorization: Client-ID $imgur_client_id"]]));
						if(empty($r)) $r=json_decode(curlget([CURLOPT_URL=>"https://api.imgur.com/3/album/$id",CURLOPT_HTTPHEADER=>["Authorization: Client-ID $imgur_client_id"]]));
						if(!empty($r) && !empty($r->data->section) && empty($r->data->title) && empty($r->data->description)) $r=json_decode(curlget([CURLOPT_URL=>"https://api.imgur.com/3/gallery/r/{$r->data->section}/$id",CURLOPT_HTTPHEADER=>["Authorization: Client-ID $imgur_client_id"]])); // subreddit image. title and desc may always be empty but included to be safe
						if(!empty($r) && $r->success==1){
							// for i.* direct links default to image description, else default to post title
							if(!empty($m[1])) if(!empty($r->data->description)) $d=$r->data->description; else $d=$r->data->title; else if(!empty($r->data->title)) $d=$r->data->title; else $d=$r->data->description;
							// single image posts without an id the same as the first image (i.e. upload two images then delete one) should read first image description
							if(empty($d) && !empty($r->data->is_album) && count($r->data->images)==1) $d=$r->data->images[0]->description;
							$o=!empty($r->data->nsfw)?'NSFW':'';
							if(!empty($d)){
								$d=str_replace(["\r","\n","\t"],' ',$d);
								$d=preg_replace('/\s+/',' ',$d);
								$d=trim(strip_tags($d));
								$o=str_shorten((!empty($o)?' - ':'').$d,280);
							}
							if(!empty($o)){
								$o="[ $o ]";
								if($title_bold) $o="\x02$o\x02";
								send("PRIVMSG $channel :$o\n");
							} else echo (!empty($m[1])?'image':'post')." exists but no description, skipping output\n";
							continue(2);
						}
					}

					// youtube via api, w/invidious mirror support
					invidious:
					if(!empty($youtube_api_key)){
						$yt='';
						if(preg_match('#^https?://(?:www\.|m\.|music\.)?(?:youtube\.com|invidio\.us)/(?:watch\?.*v=|embed/)([a-zA-Z0-9-_]+)#',$u,$m) || preg_match('#^https?://(?:youtu\.be|invidio\.us)/([a-zA-Z0-9-_]+)/?(?:$|\?)#',$u,$m)) $yt='v';
						elseif(preg_match('#^https?://(?:www\.|m\.)?(?:youtube\.com|invidio\.us)/channel/([a-zA-Z0-9-_]+)/?(\w*)#',$u,$m)) $yt='c';
						elseif(preg_match('#^https?://(?:www\.|m\.)?(?:youtube\.com|invidio\.us)/user/([a-zA-Z0-9-_]+)/?(\w*)#',$u,$m)) $yt='u';
						elseif(preg_match('#^https?://(?:www\.|m\.)?(?:youtube\.com|invidio\.us)/playlist\?.*list=([a-zA-Z0-9-_]+)#',$u,$m)) $yt='p';
						if(empty($yt)){ // custom channel URLs like /example or /c/example require scraping as no API endpoint
							if(preg_match('#^https?://(?:www\.|m\.)?(?:youtube\.com|invidio\.us)/(?:c/)?([a-zA-Z0-9-_]+)/?(\w*)#',$u,$m)){
								$html=curlget([CURLOPT_URL=>"https://www.youtube.com/{$m[1]}".(!empty($m[2])?"/{$m[2]}":'')]); // force load from youtube so indvidio.us works
								$dom=new DOMDocument();
								if($dom->loadHTML('<?xml version="1.0" encoding="UTF-8"?>'.$html)){
									$list=$dom->getElementsByTagName('link');
									foreach($list as $l) if(!empty($l->attributes->getNamedItem('rel')) && $l->attributes->getNamedItem('rel')->value=='canonical'){
										if(preg_match('#^https?://(?:www\.|m\.)?youtube\.com/channel/([a-zA-Z0-9-_]+)#',$l->attributes->getNamedItem('href')->value,$m2)){
											$m[1]=$m2[1];
											$yt='c';
											break;
										}
									}
								}
							}
						}
						if(!empty($yt)){
							if($yt=='v') $r=file_get_contents("https://www.googleapis.com/youtube/v3/videos?id={$m[1]}&part=snippet,contentDetails&maxResults=1&type=video&key=$youtube_api_key");
							elseif($yt=='c' || $yt=='u') $r=file_get_contents("https://www.googleapis.com/youtube/v3/channels?".($yt=='c'?'id':'forUsername')."={$m[1]}&part=id,snippet&maxResults=1&key=$youtube_api_key");
							elseif($yt=='p') $r=file_get_contents("https://www.googleapis.com/youtube/v3/playlists?id={$m[1]}&part=snippet&key=$youtube_api_key");
							$r=json_decode($r);
							$s=false;
							if(empty($r)){
								send("PRIVMSG $channel :[ Temporary YouTube API error ]\n");
								continue(2);
							} elseif(empty($r->items)){
								if($yt=='v' && preg_match('#^https?://invidio\.us#',$u)) $s=true; // skip if invidious short url vid not found so other site pages work
								else {
									send("PRIVMSG $channel :".($yt=='v'?'Video':($yt=='c'?'Channel':($yt=='u'?'User':($yt=='p'?'Playlist':''))))." does not exist.\n");
									continue(2);
								}
							}
							if(!$s){
								$x='';
								if($yt=='v'){
									$d=covtime($r->items[0]->contentDetails->duration); // todo: text for live (P0D) & waiting to start (?)
									if($d<>'0:00') $x.=" - $d";
								} elseif($yt=='c' || $yt=='u'){
									if(!empty($m[2]) && in_array($m[2],['videos','playlists','community','channels','search'])){ // not home/featured or about
										$x=' - '.ucfirst($m[2]);
									} elseif(!empty($r->items[0]->snippet->description)){
										$d=str_replace(["\r\n","\n","\t","\xC2\xA0"],' ',$r->items[0]->snippet->description);
										$x=' - '.str_shorten(trim(preg_replace('/\s+/',' ',$d)),148);
									}
								}
								$t="[ {$r->items[0]->snippet->title}$x ]";
								if($title_bold) $t="\x02$t\x02";
								send("PRIVMSG $channel :$t\n");
								continue(2);
							}
						}
					}
					if($invidious_mirror) goto invidious_continue; // mirror didnt hit api

					// wikipedia
					// todo: extracts on subdomains other than en.wikipedia.org, auto-translate?
					if(preg_match("#^(?:https?://(?:[^/]*?\.)?wiki(?:p|m)edia\.org/wiki/(.*)|https?://upload\.wikimedia\.org)#",$u,$m)){
						// handle file urls whether on upload.wikimedia.org thumb or full, direct or url hash
						$f='';
						if(preg_match("#^https?://upload\.wikimedia\.org/wikipedia/.*/thumb/.*/(.*)/.*#",$u,$m2)) $f=$m2[1];
						elseif(preg_match("#^https?://upload\.wikimedia\.org/wikipedia/commons/.*/(.*\.(?:\w){3})#",$u,$m2)) $f=$m2[1];
						elseif(preg_match("#^https?://(?:[^/]*?\.)?wiki(?:p|m)edia\.org/wiki/File:(.*)#",$u,$m2)) $f=$m2[1];
						elseif(preg_match("#^https?://(?:[^/]*?\.)?wikipedia\.org/wiki/[^\#]*(?:\#/media/File:(.*))#",$u,$m2)) $f=$m2[1];
						if(!empty($f)){
							if(strpos($f,'%')!==false) $f=urldecode($f);
							echo "wikipedia media file: $f\n";
							$r=curlget([CURLOPT_URL=>'https://en.wikipedia.org/w/api.php?action=query&format=json&prop=imageinfo&titles=File:'.urlencode($f).'&iiprop=extmetadata']);
							$r=json_decode($r,true);
							if(!empty($r) && !empty($r['query']) && !empty($r['query']['pages'])){
								// not sure a file can have more than one desc/page, so just grab first one
								$k=array_keys($r['query']['pages']);
								if(!empty($r['query']['pages'][$k[0]])){
									$e=$r['query']['pages'][$k[0]]['imageinfo'][0]['extmetadata']['ImageDescription']['value'];
									$e=strip_tags($e);
									$e=str_replace(["\r\n","\n","\t","\xC2\xA0"],' ',$e); // nbsp
									$e=preg_replace('/\s+/',' ',$e);
									$e=trim($e);
									$e=str_shorten($e,280);
								}
								if(!empty($e)){
									$e="[ $e ]";
									if($title_bold) $e="\x02$e\x02";
									send( "PRIVMSG $channel :$e\n");
									continue(2);
								}
							}
						} elseif(!empty($m[1])){ // not a file, not upload.wikimedia.org, has /wiki/.*
							if(!preg_match("/^Category:/",$m[1])){
								$e=get_wiki_extract($m[1],320);
								// no bolding
								if(!empty($e)){
									send( "PRIVMSG $channel :\"$e\"\n"); // else send( "PRIVMSG $channel :Wiki
									continue(2);
								}
							}
						}
					}

					// reddit image
					if(strpos($u,'.redd.it/')!==false){
						echo "getting reddit image title\n";
						$q=substr($u,strpos($u,'.redd.it')+1);
						if(strpos($q,'?')!==false) $q=substr($q,0,strpos($q,'?'));
						for($i=2;$i>0;$i--){ // 2 tries
							$j=json_decode(curlget([CURLOPT_URL=>"https://www.reddit.com/search.json?q=site:redd.it+url:$q"]));
							if(isset($j->data) && isset($j->data->children) && isset($j->data->children[0])){
								$t="[ {$j->data->children[0]->data->title} ]";
								if($title_bold) $t="\x02$t\x02";
								send("PRIVMSG $channel :$t\n");
								continue(3);
							}
						}
					}

					// reddit comment
					if(preg_match("#reddit.com/r/.*?/comments/.*?/.*?/(.+)[/]?#",$u,$m)){
						if(strpos($m[1],'?')!==false) $m[1]=substr($m[1],0,strpos($m[1],'?')); // id
						$m[1]=rtrim($m[1],'/');
						echo "getting reddit comment. id={$m[1]}\n";
						if(strpos($u,'?')!==false) $u=substr($u,0,strpos($u,'?'));
						for($i=2;$i>0;$i--){ // 2 tries
							$j=json_decode(curlget([CURLOPT_URL=>"$u.json",CURLOPT_HTTPHEADER=>["Cookie: _options=%7B%22pref_quarantine_optin%22%3A%20true%7D"]]));
							if(!empty($j)){
								if(!is_array($j) || !isset($j[1]->data->children[0]->data->id)){ echo "unknown error. response=".print_r($j,true); break; }
								if($j[1]->data->children[0]->data->id<>$m[1]){ echo "error, comment id doesn't match\n"; break; }
								$a=$j[1]->data->children[0]->data->author;
								$e=html_entity_decode($j[1]->data->children[0]->data->body_html,ENT_QUOTES); // 'body' has weird format sometimes, predecode for &amp;quot;
								$e=preg_replace('#<blockquote>.*?</blockquote>#ms',' (...) ',$e);
								$e=preg_replace('#<code>(.*?)</code>#ms'," $1 ",$e);
								$e=str_replace('<li>',' • ',$e);
								$e=format_extract($e,280);
								if(!empty($e)){
									$t="[ $a: \"$e\" ]";
									if($title_bold) $t="\x02$t\x02";
									send("PRIVMSG $channel :$t\n");
									continue(3);
								} else echo "error parsing reddit comment from html\n";
							} else echo "error getting reddit comment\n";
							if($i<>1) sleep(1);
						}
					}

					// reddit title
					if(preg_match("#reddit.com/r/.*?/comments/.+[/]?#",$u,$m)){
						echo "getting reddit post title\n";
						if(strpos($u,'?')!==false) $u=substr($u,0,strpos($u,'?'));
						for($i=2;$i>0;$i--){ // 2 tries
							$j=json_decode(curlget([CURLOPT_URL=>"$u.json",CURLOPT_HTTPHEADER=>["Cookie: _options=%7B%22pref_quarantine_optin%22%3A%20true%7D"]]));
							if(!empty($j)){
								if(!is_array($j) || !isset($j[0]->data->children[0]->data->title)){ echo "unknown error. response=".print_r($j,true); break; }
								$t=$j[0]->data->children[0]->data->title;
								$t=format_extract($t,280,['keep_quotes'=>1]);
								if(!empty($t)){
									$t="[ $t ]";
									if($title_bold) $t="\x02$t\x02";
									send("PRIVMSG $channel :$t\n");
									continue(3);
								} else echo "error parsing reddit title from html\n";
							} else echo "error getting reddit title\n";
							if($i<>1) sleep(1);
						}
					}

					// reddit general - ignore quarantine
					if(preg_match("#reddit.com/r/.*#",$u)){
						if(preg_match("#reddit.com/r/[^/]*$#",$u)) $u.='/';
						preg_match("#https?://.*?\.?reddit.com(/.*)#",$u,$m);
						$u="https://old.reddit.com{$m[1]}";
						$header=["Cookie: _options={%22pref_quarantine_optin%22:true}"];
					}

					// imdb
					if(preg_match('#https?://(?:www.)?imdb.com/title/(tt\d*)(?:/)?(?:\?.*?)?$#',$u,$m)){
						echo "Found imdb link id {$m[1]}\n";
						// same as !m by id, except no imdb link in output
						$cmd="http://www.omdbapi.com/?i=".urlencode($m[1])."&apikey={$omdb_key}";
						echo "cmd=$cmd\n";
						for($i=$num_file_get_retries;$i>0;$i--){
							$tmp=file_get_contents($cmd);
							$tmp=json_decode($tmp);
							print_r($tmp);
							if(!empty($tmp)) break; else if($i>1) sleep(1);
						}
						if(empty($tmp)){ send("PRIVMSG $channel :OMDB API error.\n"); continue(2); }
						if($tmp->Type=='movie') $tmp3=''; else $tmp3=" {$tmp->Type}";
						if($tmp->Response=='True') send("PRIVMSG $channel :\xe2\x96\xb6 {$tmp->Title} ({$tmp->Year}$tmp3) | {$tmp->Genre} | {$tmp->Actors} | \"{$tmp->Plot}\" [{$tmp->imdbRating}]\n"); elseif($tmp->Response=='False') send("PRIVMSG $channel :{$tmp->Error}\n"); else send("PRIVMSG $channel :OMDB API error.\n");
						continue(2);
					}

					// outline.com
					if(preg_match('#(?:https://)?outline\.com/([a-zA-Z0-9]*)(?:$|\?)#',$u,$m)){
						echo "outline.com url detected\n";
						if(!empty($m[1])){
							$u="https://outline.com/stat1k/{$m[1]}.html";
							$outline=true;
						} else $outline=false;
					} else $outline=false;

					// twitter via API
					if(!empty($twitter_consumer_key)){
						// tweet
						if(preg_match('#^https?://(?:mobile\.)?twitter\.com/(?:\#!/)?(?:\w+)/status(?:es)?/(\d+)#',$u,$m)){
							echo "getting tweet via API.. ";
							if(!empty($m[1])){
								$r=twitter_api('/statuses/show.json',['id'=>$m[1],'tweet_mode'=>'extended']);
								if(!empty($r) && !empty($r->full_text) && !empty($r->user->name)){
									echo "ok\n";
									$t=$r->full_text;
									// replace twitter media URLs that lead back to twitter anyway
									$mcnt=0;
									$mtyp='';
									foreach($r->extended_entities->media as $v){
										$mcnt++;
										$mtyp=$v->type;
										$t=str_replace($v->url,' ',$t);
										if(isset($v->additional_media_info) && isset($v->additional_media_info->call_to_actions) && isset($v->additional_media_info->call_to_actions->watch_now)) $mtyp='video'; // weird embeds that show as photos but are actually videos
									}
									if($mtyp=='photo') $mtyp='image';
									elseif($mtyp=='animated_gif') $mtyp='gif';
									if($mcnt>0) $t.=' '.($mcnt==1?"($mtyp)":"($mcnt {$mtyp}s)");
									// add a hint for external links
									foreach($r->entities->urls as $v){
										$h=get_url_hint($v->expanded_url);
										$t=str_replace($v->url,"{$v->url} ($h)",$t);
									}
									$t=str_replace(["\r\n","\n","\t"],' ',$t);
									$t=html_entity_decode($t,ENT_QUOTES | ENT_HTML5,'UTF-8');
									$t=trim(preg_replace('/\s+/',' ',$t));
									$t='[ '.str_shorten("{$r->user->name}: $t").' ]';
									if($title_bold) $t="\x02$t\x02";
									send("PRIVMSG $channel :$t\n");
								} else {
									echo "failed. result=".print_r($r,true);
									send("PRIVMSG $channel :Tweet not found.\n");
								}
								continue(2); // always abort, won't be a non-tweet URL
							}
						// bio
						} elseif(preg_match("#^https?://(?:mobile\.)?twitter\.com/(\w*)(?:[\?\#].*)?$#",$u,$m)){
							echo "getting twitter bio via API.. ";
							if(!empty($m[1])){
								$r=twitter_api('/users/show.json',['screen_name'=>$m[1]]);
								if(!empty($r) && empty($r->errors)){
									echo "ok\n";
									$t="{$r->name}";
									if(!empty($r->description)){
										$d=$r->description;
										foreach($r->entities->description->urls as $v){
											$h=get_url_hint($v->expanded_url);
											$d=str_replace($v->url,"{$v->url} ($h)",$d);
										}
										$d=str_replace(["\r\n","\n","\t"],' ',$d);
										$d=html_entity_decode($d,ENT_QUOTES | ENT_HTML5,'UTF-8');
										$d=trim(preg_replace('/\s+/',' ',$d));
										$t.=" | $d";
									}
									if(!empty($r->url)){
										$u=$r->entities->url->urls[0]->expanded_url;
										$u=preg_replace('#^(https?://[^/]*?)/$#',"$1",$u); // strip trailing slash on domain-only links
										$t.=" | $u";
									}
									$t="[ $t ]";
									if($title_bold) $t="\x02$t\x02";
									send("PRIVMSG $channel :$t\n");
									continue(2); // only abort if found, else might be a non-profile URL
								} else {
									echo "failed. result=".print_r($r,true);
									// todo: output error and skip on standard url retry using an outside-loop var
									// send("PRIVMSG $channel :Twitter user not found.\n");
								}
							}
						}
					}

					// instagram
					if(preg_match('#https?://(?:www\.)?instagram\.com/p/([A-Za-z0-9-_]*)#',$u,$m)){
						echo "getting instagram post info\n";
						if(!empty($m[1])){
							$t='';
							$r=@json_decode(file_get_contents("https://www.instagram.com/p/$m[1]/?__a=1"));
							if(!empty($r) && !empty($r->graphql->shortcode_media)){
								$m=$r->graphql->shortcode_media;
								$i=0; $v=0;
								if($m->__typename=='GraphImage') $i=1;
								elseif($m->__typename=='GraphVideo') $v=1;
								elseif($m->__typename=='GraphSidecar'){
									foreach($m->edge_sidecar_to_children->edges as $a){
										if($a->node->__typename=='GraphImage') $i++;
										elseif($a->node->__typename=='GraphVideo') $v++;
									}
								}
								if($i>0 || $v>0){
									 if($i>0 && $v>0) $p="$i image".($i>1?'s':'').", $v video".($v>1?'s':'');
									 else {
										 if($i>0) $p=$i==1?'image':"$i images";
										 elseif($v>0) $p=$v==1?'video':"$v videos";
									 }
								} else $p='';
								$c=$m->edge_media_to_caption->edges[0]->node->text;
								// $n=$m->owner->username;
								$f=$r->graphql->shortcode_media->owner->full_name;
								if(!empty($n)){
									if(!empty($c)){
										$t=str_replace(["\r\n","\n","\t","\xC2\xA0"],' ',"$f: $c");
										$t=trim(preg_replace('/\s+/',' ',$t));
										$t=str_shorten($t,280);
									} else $t="$n:";
									if(!empty($p)) $t.=" ($p)";
									$t="[ $t ]";
									if($title_bold) $t="\x02$t\x02";
									send("PRIVMSG $channel :$t\n");
									continue(2);
								}
							}
						}
					}

					// parler posts
					if(preg_match('#^https?://(?:share\.par\.pw/post/|parler\.com/post-view\?q=)(\w*)#',$u,$m)){
						$html=curlget([CURLOPT_URL=>"https://share.par.pw/post/{$m[1]}"]);
						$dom=new DOMDocument();
						if($dom->loadHTML('<?xml version="1.0" encoding="UTF-8"?>'.$html)){
							$x=new DOMXPath($dom);
							list($n)=$x->query('//*[@id="ud--name"]');
							$a=$n->textContent;
							list($n)=$x->query('//*[@id="post--content"]/p');
							$b=$n->textContent;
							if(!empty($a) && !empty($b)){
								$t=strip_tags(html_entity_decode("$a: $b",ENT_QUOTES | ENT_HTML5,'UTF-8'));
								$t=str_replace(["\r\n","\n","\t","\xC2\xA0"],' ',$t);
								$t=trim(preg_replace('/\s+/',' ',$t));
								$t=str_shorten($t,424,['less'=>13]);
								// seems to randomly show post image or require login, require login for all links, randomly show author avatar if no image, may not display link/media even if it exists, and cant tell between links or media..  which is dumb. so we'll just add (link/media) when its clear there's a link or media and skip avatar images
								list($n)=$x->query('//*[@id="media-placeholder--wrapper"]');
								$c=$n->textContent;
								if(!empty($c)){
									if(strpos($c,'you must be logged in')!==false) $t.=' (link/media)'; // $t.=' (media-login)';
									else {
										list($n)=$x->query('//*[@id="ud--avatar"]/img/@src');
										$ai=$n->textContent;
										list($n)=$x->query('//*[@id="media--placeholder"]/@src');
										$pi=$n->textContent;
										if($pi<>$ai) $t.=' (link/media)'; // not avatar of author
									}
								}
								$t='[ '.trim($t).' ]';
								if($title_bold) $t="\x02$t\x02";
								send("PRIVMSG $channel :$t\n");
								continue(2);
							} else {
								send("PRIVMSG $channel :[ Post not found ]\n");
								continue(2);
							}
						} else echo "Error parsing Parler HTML\n";
					}

					// parler profile
					if(preg_match('#^https?://parler\.com/profile/(\w*)/(\w*)#',$u,$m)){
							$t="[ @{$m[1]} - ".ucfirst($m[2])." ]";
							if($title_bold) $t="\x02$t\x02";
							send("PRIVMSG $channel :$t\n");
							continue(2);
					}

					// twitch via api
					if(!empty($twitch_client_id) && preg_match('#https?://(?:www\.)?twitch\.tv/(\w+)(/\w+)?#',$u,$m)){
						// get token, don't revalidate because won't be revoked - https://dev.twitch.tv/docs/authentication
						echo "Getting Twitch token.. ";
						if(empty($twitch_token) || $twitch_token_expires<time()){
							$r=json_decode(curlget([CURLOPT_URL=>"https://id.twitch.tv/oauth2/token?client_id=$twitch_client_id=&client_secret=$twitch_client_secret&grant_type=client_credentials",CURLOPT_POST=>1,CURLOPT_HTTPHEADER=>["Client-ID: $twitch_client_id"]]));
							if(!empty($r) && !empty($r->access_token)){
								echo "ok.\n";
								$twitch_token=$r->access_token;
								$twitch_token_expires=time()+$r->expires_in-30;
							} else {
								if(isset($r->message)) echo "error: {$r->message}\n"; else echo "error, r=".print_r($r,true);
								$t='[ API error ]';
								if($title_bold) $t="\x02$t\x02";
								send("PRIVMSG $channel :$t\n");
								$twitch_token='';
								$twitch_token_expires=0;
								continue(2);
							}
						} else echo "ok.\n";
						if(!empty($twitch_token)){
							// get user info - https://dev.twitch.tv/docs/api/reference#get-users
							echo "Getting user info for \"{$m[1]}\".. ";
							$r=json_decode(curlget([CURLOPT_URL=>"https://api.twitch.tv/helix/users?login={$m[1]}",CURLOPT_HTTPHEADER=>["Client-ID: $twitch_client_id","Authorization: Bearer $twitch_token"]]));
							if(!empty($r) && isset($r->data)){
								if(isset($r->data[0])){
									echo "ok.\n";
									$un=$r->data[0]->display_name;
									$ud=$r->data[0]->description; // shorten
									if(!empty($m[2])){
										// just show subdir
										$t="[ $un: ".ucfirst(substr($m[2],1))." ]";
										if($title_bold) $t="\x02$t\x02";
										send("PRIVMSG $channel :$t\n");
										continue(2);
									} else {
										// get live stream info - https://dev.twitch.tv/docs/api/reference#get-streams-metadata
										echo "Getting live stream info.. ";
										$r=json_decode(curlget([CURLOPT_URL=>"https://api.twitch.tv/helix/streams?user_login={$m[1]}",CURLOPT_HTTPHEADER=>["Client-ID: $twitch_client_id","Authorization: Bearer $twitch_token"]]));
										if(!empty($r) && isset($r->data)){
											if(count($r->data)>0){
												// check for live stream
												foreach($r->data as $d) if($d->type=='live'){
													echo "ok.\n";
													$t=str_replace(["\r\n","\n","\t","\xC2\xA0"],' ',$d->title);
													$t=trim(preg_replace('/\s+/',' ',$t));
													$t=str_shorten($t,424);
													$t="[ $t ]";
													if($title_bold) $t="\x02$t\x02";
													send("PRIVMSG $channel :$t\n");
													continue(3);
												}
											}
											// no streams, show user info
											echo "not streaming\n";
											$t=str_replace(["\r\n","\n","\t","\xC2\xA0"],' ',"$un: $ud");
											$t=trim(preg_replace('/\s+/',' ',$t));
											$t=str_shorten($t,424);
											$t="[ $t ]";
											if($title_bold) $t="\x02$t\x02";
											send("PRIVMSG $channel :$t\n");
											continue(2);
										} else {
											if(isset($r->message)) echo "error: {$r->message}\n"; else echo "error, r=".print_r($r,true);
										}
									}
								} else {
									echo "not found, abort\n";
								}
							} else {
								// api or connection error, shouldnt usually happen, continue silently
								if(isset($r->message)) echo "error: {$r->message}\n"; else echo "error, r=".print_r($r,true);
								continue(2);
							}
						}
					}

					// brighteon videos
					if(preg_match('#https?://(?:www\.)?brighteon\.com#',$u)) $og_title=true;

					// gab social
					if(preg_match('#https://(?:www\.)?gab\.com/[^/]+/posts/(\d+)#',$u)){
						$gab_post=true;
						$og_desc=true;
					} else $gab_post=false;

					// telegram (todo: use api to get message details i.e. whether has a video or image)
					if(preg_match("#^https?://t\.me/#",$u,$m)){
						$og_desc=true;
					}

					// skips
					$pathinfo=pathinfo($u);
					if(in_array($pathinfo['extension'],['gif','gifv','mp4','webm','jpg','jpeg','png','csv','pdf','xls','doc','txt','xml','json','zip','gz','bz2','7z','jar'])){ echo "skipping url due to extension \"{$pathinfo['extension']}\"\n"; continue(2); }

					if(!isset($header)) $header=[];

					if(!empty($tor_enabled) && (preg_match('#^https?://(?:.*?)\.onion(?:$|/)#',$u) || !empty($tor_all))){
						echo "getting url title via tor\n";
						$html=curlget([CURLOPT_URL=>$u,CURLOPT_PROXYTYPE=>7,CURLOPT_PROXY=>"http://$tor_host:$tor_port",CURLOPT_CONNECTTIMEOUT=>60,CURLOPT_TIMEOUT=>60,CURLOPT_HTTPHEADER=>$header]);
						if(empty($html)){
							if(strpos($curl_error,"Failed to connect to $tor_host port $tor_port")!==false) send("PRIVMSG $channel :Tor error - is it running?\n");
							elseif(strpos($curl_error,"Connection timed out after")!==false) send("PRIVMSG $channel :Tor connection timed out\n");
							// else send("PRIVMSG $channel :Tor error or site down\n");
							continue(2);
						}
					} else $html=curlget([CURLOPT_URL=>$u,CURLOPT_HTTPHEADER=>$header]);
					// echo "response[2048/".strlen($html)."]=".print_r(substr($html,0,2048),true)."\n";
					if(empty($html)){
						if(strpos($curl_error,'SSL certificate problem')!==false){
							echo "set \$allow_invalid_certs=true; in settings to skip certificate checking\n";
							$title='[ SSL certificate problem ]';
							if($title_bold) $title="\x02$title\x02";
							send("PRIVMSG $channel :$title\n");
							continue(2);
						}
						echo "Error: response blank\n";
						continue(2);
					}
					$title='';
					if(empty($html)) $html=str_replace('<<','&lt;&lt;',$html); // rottentomatoes bad title html
					$dom=new DOMDocument();
					if($dom->loadHTML('<?xml version="1.0" encoding="UTF-8"?>' . $html)){
						if(!empty($title_og || $og_title)){
							$list=$dom->getElementsByTagName("meta");
							foreach($list as $l)
								if(!empty($l->attributes->getNamedItem('property')))
									if($l->attributes->getNamedItem('property')->value=='og:title' && !empty($l->attributes->getNamedItem('content')->value))
										$title=$l->attributes->getNamedItem('content')->value;
						}
						if($og_desc){
							$list=$dom->getElementsByTagName("meta");
							foreach($list as $l)
								if(!empty($l->attributes->getNamedItem('property')))
									if($l->attributes->getNamedItem('property')->value=='og:description' && !empty($l->attributes->getNamedItem('content')->value))
										$title=$l->attributes->getNamedItem('content')->value;
							if($gab_post){
								$title=rtrim(preg_replace('/'.preg_quote(": '",'/').'/',': ',$title,1),"'");
							}
						}
						if(empty($title)){
							$list=$dom->getElementsByTagName("title");
							if($list->length>0) $title=$list->item(0)->textContent;
						}
					}
					$orig_title=$title;
					// if potential invidious mirror rewrite URL and jump back to yt/invidious. if not an api URL will continue past here and parse already-fetched html. yewtu.be has a captcha so handle manually
					if(!empty($youtube_api_key) && (preg_match('/ - Invidious$/',$title) || preg_match('#^https://yewtu.be/#',$u)) && empty($invidious_mirror) && !preg_match('#^https://invidio\.us#',$u)){
						$u=preg_replace('#^https?://.*?/(.*)#','https://invidio.us/$1',$u);
						$invidious_mirror=true;
						goto invidious;
					}
					invidious_continue:
					// echo "orig title= ".print_r($title,true)."\n";
					$title=html_entity_decode($title,ENT_QUOTES | ENT_HTML5,'UTF-8');
					// strip numeric entities that don't seem to display right on IRC when converted
					$title=preg_replace('/(&#[0-9]+;)/','',$title);
					$title=str_replace(["\r\n","\n","\t","\xC2\xA0"],' ',$title);
					$title=trim(preg_replace('/\s+/',' ',$title));
					$notitletitles=['imgur: the simple image sharer','Imgur','Imgur: The most awesome images on the Internet','Login • Instagram','Access denied .* used Cloudflare to restrict access','Amazon.* Something Went Wrong.*','Sorry! Something went wrong!','Bloomberg - Are you a robot?','Attention Required! | Cloudflare','Access denied','Access Denied'];
					foreach($notitletitles as $ntt){
						if(preg_match('/^'.str_replace('\.\*','.*',preg_quote($ntt)).'$/',$title)){
							echo "Skipping output of title: $title\n";
							continue(3);
						}
					}
					foreach($title_replaces as $k=>$v) $title=str_replace($k,$v,$title);
					// if(!$title) $title='No title found.';
					if(strpos($u,'//twitter.com/')!==false) $title=str_replace_one(' on Twitter: "',': "',$title);
					if($title && $outline){
						preg_match('#<span class="publication">.*?>(.*)›.*?</span>#',$html,$m);
						if(!empty($m[1])) $title.=' - '.trim($m[1]);
					}
					$title=str_shorten($title,438);
					if($title){
						$title="[ $title ]";
						if($title_bold) $title="\x02$title\x02";
						send( "PRIVMSG $channel :$title\n");
						break;
					} else {
						if(strpos($u,'//twitter.com/')!==false){ // retry twitter
							$u_tries++;
							if($u_tries==3){ echo "No title found.\n"; break; }
							else { echo "No title found, retrying..\n"; sleep(1); }
						} else break;
					}
				}
			}
		}
		// flood protection
		if($flood_protection_on){
			// process all PRIVMSG to $channel
			if($ex[1]=='PRIVMSG' && $ex[2]==$channel){
				list($tmpnick,$tmphost)=parsemask($ex[0]);
				$flood_lines[]=[$tmphost,$msg,microtime()];
				if(count($flood_lines)>$flood_max_buffer_size) $tmp=array_shift($flood_lines);

				// if X consequtive lines by one person, quiet for X secs
				if(count($flood_lines)>=$flood_max_conseq_lines){
					$flooding=true;
					$index=count($flood_lines)-1;
					for($i=1;$i<=($flood_max_conseq_lines-1);$i++){
						$index2=$index-$i;
						if($flood_lines[$index2][0]<>$flood_lines[$index][0]) $flooding=false;
					}
					if($flooding && !isme() && !isadmin()){
						$tmphost=str_replace('@gateway/web/freenode/ip.','@',$tmphost);
						timedquiet($flood_max_conseq_time,"*!*@$tmphost");
					}
				}
				// todo: if X within X micro seconds, quiet

				// if X of the same lines in a row by one person, quiet for 15 mins
				if(count($flood_lines)>=$flood_max_dupe_lines){
					$flooding=true;
					$index=count($flood_lines)-1;
					for($i=1;$i<=($flood_max_dupe_lines-1);$i++){
						$index2=$index-$i;
						if($flood_lines[$index2][0]<>$flood_lines[$index][0] || $flood_lines[($index2)][1]<>$flood_lines[$index][1]) $flooding=false;
					}
					if($flooding && !isme() && !isadmin()){
						$tmphost=str_replace('@gateway/web/freenode/ip.','@',$tmphost);
						timedquiet($flood_max_dupe_time,"*!*@$tmphost");
						// $flood_lines=[];
					}
				}

			}
		}

		if(empty($data) || ($ex[1]=='NOTICE' && strpos($data,':Server Terminating. Received SIGTERM')!==false) || (isme() && $ex[1]=='QUIT' && strpos($data,':Ping timeout')!==false)) break;
	}
	echo "Stream closed or timed out, reconnecting..\n";
	$connect=1;
}
// End Loop

function curlget($opts=[]){
	global $custom_curl_iface,$curl_iface,$user_agent,$allow_invalid_certs,$curl_response,$curl_info,$curl_error;
	$curl_response='';
	$ch=curl_init();
	curl_setopt($ch,CURLOPT_IPRESOLVE,CURL_IPRESOLVE_V4);
	curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,15);
	curl_setopt($ch,CURLOPT_TIMEOUT,15);
	if($custom_curl_iface) curl_setopt($ch,CURLOPT_INTERFACE,$curl_iface);
	curl_setopt($ch,CURLOPT_USERAGENT,$user_agent);
	curl_setopt($ch,CURLOPT_COOKIEFILE,'cookiefile.txt');
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
	curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
	// curl_setopt($ch,CURLOPT_VERBOSE,1);
	// curl_setopt($ch,CURLOPT_HEADER,1);
	curl_setopt($ch,CURLOPT_MAXREDIRS,7);
	if(!empty($allow_invalid_certs)) curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
	curl_setopt($ch,CURLOPT_ENCODING,''); // avoid gzipped result per http://stackoverflow.com/a/28295417
	$default_header=[];
	/*$default_header=[ // seem to help some servers
		'Connection: keep-alive',
		'Upgrade-Insecure-Requests: 1',
		'Accept-Language: en'
	];
	curl_setopt($ch,CURLOPT_HTTPHEADER,$header);*/
	// partially read big connections per https://stackoverflow.com/a/17641159
	curl_setopt($ch,CURLOPT_WRITEFUNCTION,function($handle,$data){
		global $curl_response;
		$curl_response.=$data;
		if(strlen($curl_response)>1048576){ // up to 768KB required for amazon link titles
			echo "aborting download at 1MB\n";
			return 0;
		} else return strlen($data);
	});
	if(!empty($opts[CURLOPT_HTTPHEADER])) $opts[CURLOPT_HTTPHEADER]=array_merge($default_header,$opts[CURLOPT_HTTPHEADER]);
	curl_setopt_array($ch,$opts);
	curl_exec($ch);
	$curl_info=[
		'EFFECTIVE_URL'=>curl_getinfo($ch,CURLINFO_EFFECTIVE_URL) // for loose-matching !wiki
	];
	$curl_error=curl_error($ch);
	if(!empty($curl_error)) echo "curl error: $curl_error\n";
	curl_close($ch);
	return $curl_response;
}

function isadmin(){
	// todo: verify admins that send commands without being in channel user list
	global $admins,$incnick,$users;
	$r=search_multi($users,'nick',$incnick);
	if($r===false) return false;
	if(in_array($users[$r]['account'],$admins,true)) return true; else return false;
}
function isme(){
	global $ex,$nick;
	if(substr($ex[0],1,strpos($ex[0],'!')-1)==$nick) return true; else return false;
}

function doopdop(){
	global $datafile,$nick,$channel,$opped,$opqueue,$doopdop_lock,$always_opped;
	if($doopdop_lock || empty($opqueue)) return;
	$doopdop_lock=true;
	foreach($opqueue as $oq){
		list($what,$who,$opts)=$oq;
		// kick
		if($what=='kick'){
			if($opts['msg']) $msg=' :'.$opts['msg']; else $msg='';
			send("KICK $channel $who$msg\n");
			if(empty($always_opped)) send("MODE $channel -o $nick\n");
		} elseif($what=='remove'){
			if($opts['msg']) $msg=' :'.$opts['msg']; else $msg='';
			send("REMOVE $channel $who$msg\n");
			if(empty($always_opped)) send("MODE $channel -o $nick\n");
		} elseif($what=='remove_quiet'){
			if($opts['msg']) $msg=' :'.$opts['msg']; else $msg='';
			send("REMOVE $channel {$opts['nick']}$msg\n");
			if(empty($always_opped)) send("MODE $channel -o $nick\n");
			if($opts['timed']) timedquiet($opts['tqtime'],$who);
			else send("PRIVMSG chanserv :QUIET $channel $who\n");
		} elseif($what=='topic'){
			if(empty($opts['msg'])) continue;
			send("TOPIC $channel :{$opts['msg']}\n");
			if(empty($always_opped)) send("MODE $channel -o $nick\n");
		} elseif($what=='invite'){
			if(empty($who)) continue;
			send("INVITE $who $channel\n");
			if(empty($always_opped)) send("MODE $channel -o $nick\n");
		} elseif($what=='+q'){
			if(empty($always_opped)) send("MODE $channel +q-o $who $nick\n");
			else send("MODE $channel +q $who\n");
		} elseif($what=='-q'){
			if(empty($always_opped)){
				if(count($who)>3) $who=array_slice($who,0,3);
				$mode='-'.str_repeat('q',count($who)).'o';
				send("MODE $channel $mode ".implode(' ',$who)." $nick\n");
			} else {
				if(count($who)>4) $who=array_slice($who,0,4);
				$mode='-'.str_repeat('q',count($who));
				send("MODE $channel $mode ".implode(' ',$who)."\n");
			}
		} elseif($what=='+b'){
			list($tmpmask,$tmpreason,$tmpnick,$tmptime)=$who;
			if(empty($always_opped)){
				if(!empty($tmpnick)){
					send("MODE $channel +b $tmpmask\n");
					send("KICK $channel $tmpnick :$tmpreason\n");
					send("MODE $channel -o $nick\n");
				} else send("MODE $channel +b-o $tmpmask $nick\n"); // todo: find nick by mask and kick
			} else {
				send("MODE $channel +b $tmpmask\n");
				if(!empty($tmpnick)) send("KICK $channel $tmpnick :$tmpreason\n");
			}
		} elseif($what=='-b'){
			if(empty($always_opped)){
				if(count($who)>3) $who=array_slice($who,0,3);
				$mode='-'.str_repeat('b',count($who)).'o';
				send("MODE $channel $mode ".implode(' ',$who)." $nick\n");
			} else {
				if(count($who)>4) $who=array_slice($who,0,4);
				$mode='-'.str_repeat('b',count($who));
				send("MODE $channel $mode ".implode(' ',$who)."\n");
			}
		} elseif($what=='fyc'){
			list($tmpmask,$tmptime)=$who;
			$fyctime=$tmptime*60;
			$tmpmask.='$##fix_your_connection';
			if($fyctime>0){ // 0 = no time limit
				$botdata=json_decode(file_get_contents($datafile));
				if(!isset($botdata->fyc)) $botdata->fyc=[]; else $botdata->fyc=(array) $botdata->fyc;
				foreach($botdata->fyc as $fyc){ $fyc=explode('|',$fyc); echo "fyc[2]={$fyc[2]} tmpmask=$tmpmask"; if($fyc[2]==$tmpmask){ echo "dupe\n"; continue(2); } }
				$botdata->fyc[]=time()."|$fyctime|".$tmpmask;
				file_put_contents($datafile,json_encode($botdata));
			}
			if(empty($always_opped)) send("MODE $channel +b-o $tmpmask $nick\n");
			else send("MODE $channel +b $tmpmask\n");
		}
	}
	$opped=false;
	sleep(2);
	$opqueue=[];
	$doopdop_lock=false;
}

function getops(){
	global $socket,$channel,$opped,$getops_lock,$always_opped;
	if($always_opped) return doopdop(); // just run the queue
	if($getops_lock==true) return;
	$getops_lock=true;
	send("PRIVMSG ChanServ :OP $channel\n");
	// wait for ops in main loop
}

$last_send='';
function send($a){
        global $socket,$stream_timeout,$skip_dupe_output,$last_send;
        if($skip_dupe_output){ if($a==$last_send) return; else $last_send=$a; }
        echo "> $a";
        fputs($socket,"$a");
        if(timedout()) return false;
}

function send_no_filter($a){
        global $socket,$stream_timeout;
        echo "> $a";
        fputs($socket,"$a");
        if(timedout()) return false;
}

function timedout(){
	global $socket;
	$meta=stream_get_meta_data($socket);
	if($meta['timed_out']) return true; else return false;
}

// http://dev.bitly.com/links.html#v3_shorten
function make_bitly_url($url){
	global $bitly_token;
	if(empty($bitly_token)){
		echo "Warning: Can't make bitly URL. Get a token at https://bitly.com and add it to the settings file.\n";
		return $url;
	}
	$r=json_decode(curlget([
		CURLOPT_URL=>'https://api-ssl.bitly.com/v4/shorten',
		CURLOPT_CUSTOMREQUEST=>'POST',
		CURLOPT_POSTFIELDS=>json_encode(['long_url'=>$url]),
		CURLOPT_HTTPHEADER=>[
			'Authorization: Bearer '.$bitly_token,
			'Content-Type: application/json',
			'Accept: application/json'
		]
	]));
	if(!isset($r->id) || empty($r->id)){
		echo 'Bitly error. Response: '.print_r($r,true);
		return $url;
	}
	return 'https://'.$r->id;
}

// get url hint e.g. https://one.microsoft.com -> microsoft.com, https://www.telegraph.co.uk -> telegraph.co.uk
function get_url_hint($u){
	if(preg_match('@https?://([^/#?]*)@',$u,$m)) return get_base_domain($m[1]);
	else return false; // shouldnt happen as we always pass urls
}

// get base domain considering public suffix from https://publicsuffix.org/list/
function get_base_domain($d){
	global $public_suffixes;
	$d=strtolower($d);
	if(empty($public_suffixes)){
		// todo: refresh like once a month on bot start, if exists; until then delete public_suffix_list.dat and restart bot
		if(!file_exists('public_suffix_list.dat')){
			echo "Updating public_suffix_list.dat\n";
			$f=file_get_contents('https://publicsuffix.org/list/public_suffix_list.dat');
			if(!empty($f)){
				file_put_contents('public_suffix_list.dat',$f) or die('Error writing file, check permissions.');
				$fp=fopen('public_suffix_list.dat','r');
				$f="// Source: https://publicsuffix.org/list/ (modified) License: https://mozilla.org/MPL/2.0/\n";
				while(!feof($fp)){
					$l=fgets($fp,1024);
					if(substr($l,0,2)=='//' || $l=="\n") continue;
					elseif(substr($l,0,2)=='*.') $l=substr($l,2);
					elseif(substr($l,0,1)=='!') $l=substr($l,1);
					$f.=$l;
				}
				fclose($fp);
				file_put_contents('public_suffix_list.dat',$f);
				unset($f);
			} else {
				echo "Error downloading public_suffix_list.dat\n";
				return $d;
			}
		}
		$public_suffixes=explode("\n",file_get_contents('public_suffix_list.dat')); // store in memory (fastest)
	}
	$l=substr($d,0,strpos($d,'.')); // save last stripped sub/dom
	$c=substr($d,strpos($d,'.')+1); // strip first sub/dom to save an iteration
	$n=substr_count($d,'.');
	for($i=0;$i<=$n;$i++){
		if(in_array($c,$public_suffixes)){
			if(substr($c,0,4)=='www.' && $d<>"www.$c") $c=preg_replace('/^www\./','',$c); // strip www if not main domain
			return "$l.$c";
		}
		$l=substr($c,0,strpos($c,'.'));
		$c=substr($c,strpos($c,'.')+1);
	}
	return $d; // not found
}

function dorestart($msg,$sendquit=true){
	global $_,$argv;
	echo "Restarting...\n";
	$_=$_SERVER['_'];
	register_shutdown_function(function(){
		global $_,$argv;
		pcntl_exec($_,$argv);
	});
	if(empty($msg)) $msg='restart';
	if($sendquit) send("QUIT :$msg\n");
	exit;
}

// convert youtube v3 api duration e.g. PT1M3S to HH:MM:SS per https://stackoverflow.com/a/35836604
function covtime($yt){
    $yt=str_replace(['P','T'],'',$yt);
    foreach(['D','H','M','S'] as $a){
        $pos=strpos($yt,$a);
        if($pos!==false) ${$a}=substr($yt,0,$pos); else { ${$a}=0; continue; }
        $yt=substr($yt,$pos+1);
    }
    if($D>0){
        $M=str_pad($M,2,'0',STR_PAD_LEFT);
        $S=str_pad($S,2,'0',STR_PAD_LEFT);
        return ($H+(24*$D)).":$M:$S"; // add days to hours
    } elseif($H>0){
        $M=str_pad($M,2,'0',STR_PAD_LEFT);
        $S=str_pad($S,2,'0',STR_PAD_LEFT);
        return "$H:$M:$S";
    } else {
        $S=str_pad($S,2,'0',STR_PAD_LEFT);
        return "$M:$S";
    }
}

// search multi-dimensional array and return id
function search_multi($arr,$key,$val){
	foreach($arr as $k=>$v) if($v[$key]==$val) return $k;
	return false;
}

function parsemask($mask){
	$tmp=explode('!',$mask);
	$tmpnick=substr($tmp[0],1);
	$tmp=explode('@',$mask);
	$tmphost=$tmp[1];
	return [$tmpnick,$tmphost];
}

// disabled
function check_dnsbl($nick,$host,$skip=false){
	global $dnsbls,$opqueue;
	$ignores=[]; // nicks to ignore for this
	if(in_array($nick,$ignores)){
		echo "DNSBL: ignoring nick $nick\n";
		return;
	}
	$dnsbls=['all.s5h.net',
			 'cbl.abuseat.org',
			 'dnsbl.sorbs.net',
			 'bl.spamcop.net'];
	// ip check
	if(substr($host,0,8)=='gateway/' && strpos($host,'/ip.')!==false) $ip=gethostbyname(substr($host,strpos($host,'/ip.')+4));
	else $ip=gethostbyname($host);
	if(filter_var($ip,FILTER_VALIDATE_IP)!==false){
		echo "IP $ip detected.\n";
		echo ".. checking against ".count($dnsbls)." DNSBLs\n";
		$rip=implode('.',array_reverse(explode('.',$ip)));
		foreach($dnsbls as $bl){
			$result=dns_get_record("$rip.$bl");
			echo "$bl result: ".print_r($result,true)."\n";
			if(!empty($result)){
				if(!$skip){
					echo "found in dnsbl. taking action.\n";
					$opqueue[]=['+b',["*!*@$ip","IP found in DNSBL. Please don't spam.",$nick]];
					getops();
					// timedquiet($host_blacklist_time,"*!*@$ip");
					dnsbl_msg($nick);
					return;
				} else echo "found in dnsbl, but action skipped.\n";
			} else echo "not found in dnsbl.\n";
		}
	}

}

function dnsbl_msg($nick){
	global $channel;
	send("PRIVMSG $nick :You have been automatically banned in $channel due to abuse from spammers. If this is a mistake please contact an op seen in /msg chanserv access $channel list\n");
}

function check_blacklist($nick,$host){
	global $host_blacklist_strings, $host_blacklist_ips, $channel;
	echo "Checking blacklist, nick: $nick host: $host\n";

	// ip check
	if(substr($host,0,8)=='gateway/' && strpos($host,'/ip.')!==false) $ip=gethostbyname(substr($host,strpos($host,'/ip.')+4));
	else $ip=gethostbyname($host);
	if(filter_var($ip,FILTER_VALIDATE_IP)!==false){
		echo "IP $ip detected.\n";
		echo ".. checking against ".count($host_blacklist_ips)." IP blacklists\n";
		foreach($host_blacklist_ips as $ib){
			if(cidr_match($ip,$ib)){
				echo "* IP $ip matched blacklisted $ib\n";
				// 100115 - shadowban
				// $opqueue[]=['remove_quiet',$who,['nick'=>$thenick,'msg'=>$msg,'timed'=>$timed,'tqtime'=>$tqtime]];
				// getops();
				timedquiet($host_blacklist_time,"*!*@$ip");
				blacklisted_msg($nick);
				return;
			}
		}
	}
	// host check
	echo ".. checking against ".count($host_blacklist_strings)." string blacklists\n";
	foreach($host_blacklist_strings as $sb){
		if(strpos($host,$sb)!==false){
			echo "* Host $host matched blacklisted $sb\n";
			timedquiet($host_blacklist_time,"*!*@$host");
			blacklisted_msg($nick);
			return;
		}
	}
}

function blacklisted_msg($nick){
	global $channel;
	send("PRIVMSG $nick :You have been automatically quieted in $channel due to abuse. If this is a mistake please contact an op seen in /msg chanserv access $channel list\n");
}

// http://stackoverflow.com/a/594134
function cidr_match($ip,$range){
	list($subnet,$bits)=explode('/',$range);
	if(empty($bits)) $bits=32;
	$ip=ip2long($ip);
	$subnet=ip2long($subnet);
	// $mask=-1<<(32-$bits);
	// supposedly needed for 64 bit machines per http://tinyurl.com/oxz4lrw
	$mask=(-1<<(32-$bits)) & ip2long('255.255.255.255');
	$subnet &= $mask; // nb: in case the supplied subnet wasn't correctly aligned
	return ($ip & $mask)==$subnet;
}

function timedquiet($secs=0,$mask){
	global $network,$channel,$datafile,$opqueue;
	if($network=='freenode') send("PRIVMSG chanserv :QUIET $channel $mask\n");
	elseif ($network=='libera') {
		$opqueue[]=['+q',$mask];
		getops();
	}
	if(is_numeric($secs) && $secs>0){
		$botdata=json_decode(file_get_contents($datafile));
		if(!isset($botdata->tq)) $botdata->tq=[]; else $botdata->tq=(array) $botdata->tq;
		foreach($botdata->tq as $k=>$tq){ $tq=explode('|',$tq); echo "tq[2]={$tq[2]} mask=$mask"; if($tq[2]==$mask){ echo "removing dupe\n"; unset($botdata->tq[$k]); continue; } }
		$botdata=array_values($botdata);
		$botdata->tq[]=time()."|$secs|$mask";
		file_put_contents($datafile,json_encode($botdata));
	}
}

function get_wiki_extract($q,$len=280){
	$q=urldecode($q);
	$url="https://en.wikipedia.org/w/api.php?action=query&titles=".urlencode($q)."&prop=extracts&format=json&redirects&formatversion=2&explaintext";
	while(1){
		$tmp=curlget([CURLOPT_URL=>$url]);
		if(empty($tmp)){
			echo "No response from Wikipedia, retrying..\n";
			continue;
		}
		break;
	}
	if(!empty($tmp)){
		$tmp=json_decode($tmp);
		$k=$tmp->query->pages[0];
		unset($tmp);
		if(mb_strpos($q,'#')!==false){ // jump to fragment
			$foundfrag=false;
			$frag=trim(str_replace('_',' ',mb_substr($q,mb_strpos($q,'#')+1)));
			$k->extract=str_replace(['======','=====','====','==='],'==',$k->extract);
			// try to find some sections with multiple ids, e.g. https://en.wikipedia.org/wiki/Microphone#Dynamic https://en.wikipedia.org/wiki/Microphone#Dynamic_microphone by removing additional words from fragment - useful for found hidden search-friendly ids with a shorter version in the table of contents e.g. !w dynamic microphone
			$frags=explode(' ',$frag);
			while(1){
				$fragstr=implode(' ',$frags);
				$pos=mb_stripos($k->extract,"\n== $fragstr ==\n");
				if($pos!==false){
					$k->extract=mb_substr($k->extract,$pos);
					$foundfrag=true;
					break;
				} else {
					if(count($frags)==1) break;
					array_pop($frags);
				}
			}
		}
		$arr=explode("\n",trim($k->extract));
		unset($k);
		$unset=false;
		foreach($arr as $k=>$v){ // reformat section headers
			if(substr($v,0,3)=='== '){
				if($foundfrag && $v=="== $fragstr ==") $unset=$k; // remove current header
				else $arr[$k]=trim(str_replace('==','',$v)).': ';
			}
		}
		if($unset!==false){
			unset($arr[$unset]);
			$arr=array_values($arr);
		}
		$e=implode("\n",$arr);
		$e=str_replace(' ()','',$e); // phonetics are often missing from extract
		$e=preg_replace('/\.((?:\w{2,}|A\ ))/',". $1",$e); // wikipedia seems to omit a space between paragraphs often
		$e=format_extract($e,$len);
	}
	return $e;

}

function format_extract($e,$len=280,$opts=[]){
	$e=str_replace(["\n","\t"],' ',$e);
	$e=html_entity_decode($e,ENT_QUOTES);
	$e=preg_replace_callback("/(&#[0-9]+;)/",function($m){ return mb_convert_encoding($m[1],'UTF-8','HTML-ENTITIES'); },$e);
	$e=strip_tags($e);
	$e=preg_replace('/\s+/m',' ',$e);
	$e=str_shorten($e,$len);
	if(!isset($opts['keep_quotes'])) $e=trim(trim($e,'"')); // remove outside quotes because we wrap in quotes
	return $e;
}

function twitter_api($u,$op){ // https://stackoverflow.com/a/12939923
	global $twitter_consumer_key,$twitter_consumer_secret,$twitter_access_token,$twitter_access_token_secret;
	// init params
	$u="https://api.twitter.com/1.1$u";
	$p=array_merge(['oauth_consumer_key'=>$twitter_consumer_key,'oauth_nonce'=>uniqid('',true),'oauth_signature_method'=>'HMAC-SHA1','oauth_token'=>$twitter_access_token,'oauth_timestamp'=>time(),'oauth_version'=>'1.0'],$op);
	// build base string
	$t=[];
	ksort($p);
	foreach($p as $k=>$v) $t[]="$k=".rawurlencode($v);
	$b='GET&'.rawurlencode($u).'&'.rawurlencode(implode('&',$t));
	// sign
	$k=rawurlencode($twitter_consumer_secret).'&'.rawurlencode($twitter_access_token_secret);
	$s=base64_encode(hash_hmac('sha1',$b,$k,true));
	$p['oauth_signature']=$s;
	// build header
	$t='Authorization: OAuth ';
	$t2=[];
	foreach($p as $k=>$v) $t2[]="$k=\"".rawurlencode($v)."\"";
	$t.=implode(', ',$t2);
	$h=[$t];
	// request
	$t=[];
	foreach($op as $k=>$v) $t[]="$k=".rawurlencode($v);
	$r=@json_decode(curlget([CURLOPT_URL=>"$u?".implode('&',$t),CURLOPT_HTTPHEADER=>$h]));
	return $r;
}

function get_true_random($min=1,$max=100,$num=1){
	$max=((int)$max>=1)?(int)$max:100;
	$min=((int)$min<$max)?(int)$min:1;
	$num=((int)$num>=1)?(int)$num:1;
	$r=curlget([CURLOPT_URL=>"http://www.random.org/integers/?num=$num&min=$min&max=$max&col=1&base=10&format=plain&rnd=new"]);
	$r=trim(str_replace("\n",' ',$r));
	return $r;
}

// ISO 639-1 Language Codes
function get_lang($c){
	global $language_codes;
	list($c)=explode('-',$c);
	if(!isset($language_codes)) $language_codes=['en'=>'English','aa'=>'Afar','ab'=>'Abkhazian','af'=>'Afrikaans','am'=>'Amharic','ar'=>'Arabic','as'=>'Assamese','ay'=>'Aymara','az'=>'Azerbaijani','ba'=>'Bashkir','be'=>'Byelorussian','bg'=>'Bulgarian','bh'=>'Bihari','bi'=>'Bislama','bn'=>'Bengali/Bangla','bo'=>'Tibetan','br'=>'Breton','ca'=>'Catalan','co'=>'Corsican','cs'=>'Czech','cy'=>'Welsh','da'=>'Danish','de'=>'German','dz'=>'Bhutani','el'=>'Greek','eo'=>'Esperanto','es'=>'Spanish','et'=>'Estonian','eu'=>'Basque','fa'=>'Persian','fi'=>'Finnish','fj'=>'Fiji','fo'=>'Faeroese','fr'=>'French','fy'=>'Frisian','ga'=>'Irish','gd'=>'Scots/Gaelic','gl'=>'Galician','gn'=>'Guarani','gu'=>'Gujarati','ha'=>'Hausa','hi'=>'Hindi','hr'=>'Croatian','hu'=>'Hungarian','hy'=>'Armenian','ia'=>'Interlingua','id'=>'Indonesian','ie'=>'Interlingue','ik'=>'Inupiak','in'=>'Indonesian','is'=>'Icelandic','it'=>'Italian','iw'=>'Hebrew','ja'=>'Japanese','ji'=>'Yiddish','jw'=>'Javanese','ka'=>'Georgian','kk'=>'Kazakh','kl'=>'Greenlandic','km'=>'Cambodian','kn'=>'Kannada','ko'=>'Korean','ks'=>'Kashmiri','ku'=>'Kurdish','ky'=>'Kirghiz','la'=>'Latin','ln'=>'Lingala','lo'=>'Laothian','lt'=>'Lithuanian','lv'=>'Latvian/Lettish','mg'=>'Malagasy','mi'=>'Maori','mk'=>'Macedonian','ml'=>'Malayalam','mn'=>'Mongolian','mo'=>'Moldavian','mr'=>'Marathi','ms'=>'Malay','mt'=>'Maltese','my'=>'Burmese','na'=>'Nauru','ne'=>'Nepali','nl'=>'Dutch','no'=>'Norwegian','oc'=>'Occitan','om'=>'(Afan)/Oromoor/Oriya','pa'=>'Punjabi','pl'=>'Polish','ps'=>'Pashto/Pushto','pt'=>'Portuguese','qu'=>'Quechua','rm'=>'Rhaeto-Romance','rn'=>'Kirundi','ro'=>'Romanian','ru'=>'Russian','rw'=>'Kinyarwanda','sa'=>'Sanskrit','sd'=>'Sindhi','sg'=>'Sangro','sh'=>'Serbo-Croatian','si'=>'Singhalese','sk'=>'Slovak','sl'=>'Slovenian','sm'=>'Samoan','sn'=>'Shona','so'=>'Somali','sq'=>'Albanian','sr'=>'Serbian','ss'=>'Siswati','st'=>'Sesotho','su'=>'Sundanese','sv'=>'Swedish','sw'=>'Swahili','ta'=>'Tamil','te'=>'Tegulu','tg'=>'Tajik','th'=>'Thai','ti'=>'Tigrinya','tk'=>'Turkmen','tl'=>'Tagalog','tn'=>'Setswana','to'=>'Tonga','tr'=>'Turkish','ts'=>'Tsonga','tt'=>'Tatar','tw'=>'Twi','uk'=>'Ukrainian','ur'=>'Urdu','uz'=>'Uzbek','vi'=>'Vietnamese','vo'=>'Volapuk','wo'=>'Wolof','xh'=>'Xhosa','yo'=>'Yoruba','zh'=>'Chinese','zu'=>'Zulu'];
	if(array_key_exists($c,$language_codes)) return $language_codes[$c]; else return 'Unknown';
}

function s_insult(){
	$words=[['artless','bawdy','beslubbering','bootless','churlish','cockered','clouted','craven','currish','dankish','dissembling','droning','errant','fawning','fobbing','froward','frothy','gleeking','goatish','gorbellied','impertinent','infectious','jarring','loggerheaded','lumpish','mammering','mangled','mewling','paunchy','pribbling','puking','puny','qualling','rank','reeky','roguish','ruttish','saucy','spleeny','spongy','surly','tottering','unmuzzled','vain','venomed','villainous','warped','wayward','weedy','yeasty'],['base-court','bat-fowling','beef-witted','beetle-headed','boil-brained','clapper-clawed','clay-brained','common-kissing','crook-pated','dismal-dreaming','dizzy-eyed','doghearted','dread-bolted','earth-vexing','elf-skinned','fat-kidneyed','fen-sucked','flap-mouthed','fly-bitten','folly-fallen','fool-born','full-gorged','guts-griping','half-faced','hasty-witted','hedge-born','hell-hated','idle-headed','ill-breeding','ill-nurtured','knotty-pated','milk-livered','motley-minded','onion-eyed','plume-plucked','pottle-deep','pox-marked','reeling-ripe','rough-hewn','rude-growing','rump-fed','shard-borne','sheep-biting','spur-galled','swag-bellied','tardy-gaited','tickle-brained','toad-spotted','unchin-snouted','weather-bitten'],['apple-john','baggage','barnacle','bladder','boar-pig','bugbear','bum-bailey','canker-blossom','clack-dish','clotpole','coxcomb','codpiece','death-token','dewberry','flap-dragon','flax-wench','flirt-gill','foot-licker','fustilarian','giglet','gudgeon','haggard','harpy','hedge-pig','horn-beast','hugger-mugger','joithead','lewdster','lout','maggot-pie','malt-worm','mammet','measle','minnow','miscreant','moldwarp','mumble-news','nut-hook','pigeon-egg','pignut','puttock','pumpion','ratsbane','scut','skainsmate','strumpet','varlot','vassal','whey-face','wagtail']];
	return 'Thou '.$words[0][rand(0,count($words[0])-1)].' '.$words[1][rand(0,count($words[1])-1)].' '.$words[2][rand(0,count($words[2])-1)].'!';
}

function str_replace_one($needle,$replace,$haystack){
	$pos=strpos($haystack,$needle);
	if($pos!==false) $newstring=substr_replace($haystack,$replace,$pos,strlen($needle)); else $newstring=$haystack;
	return $newstring;
}

// shorten string to last whole word within x characters and max bytes
function str_shorten($s,$len=999,$opts=[]){
	global $baselen;
	if(!isset($opts['less'])) $opts['less']=0;
	if(!isset($opts['nodots'])) $opts['nodots']=false;
	if(!isset($opts['nowordcut'])) $opts['nowordcut']=false;
	$e=false;
	if(mb_strlen($s)>$len){ // desired max chars
		$s=mb_substr($s,0,$len);
		if(!$opts['nowordcut']) $s=mb_substr($s,0,mb_strrpos($s,' ')+1); // cut to last word
		$e=true;
	}
	$m=502-$baselen-$opts['less']; // max 512 - 4(ellipses) - 4(brackets) - 2(bold) - baselen bytes; todo: fix for non-full-width strings using 'less' for extra data, remove auto bracket calc and use 'less' on all calls
	if($opts['nodots']) $m+=4;
	if(strlen($s)>$m){
		$s=mb_strcut($s,0,$m); // mb-safe cut to bytes
		if(!$opts['nowordcut']) $s=mb_substr($s,0,mb_strrpos($s,' ')+1); // cut to last word
		$e=true;
	}
	if($e) $s=rtrim($s,' ;.,').(!$opts['nodots']?' ...':'');  // trim punc & add ellipses
	return $s;
}

function register_loop_function($f){
	global $custom_loop_functions;
	if(!isset($custom_loop_functions)) $custom_loop_functions=[];
	if(!in_array($f,$custom_loop_functions)){
		echo "Adding custom loop function \"$f\"\n";
		$custom_loop_functions[]=$f;
	} else echo "Skipping duplicate custom loop function \"$f\"\n";
}
