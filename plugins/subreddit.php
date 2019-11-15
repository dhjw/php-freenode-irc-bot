<?php
// read subreddit and post to channel
// scans the first X pages of a subreddit and outputs posts with the configured score
// requires sqlite3 php extension, e.g. to install on ubuntu with php 7.3: sudo apt-get install php7.3-sqlite3
// see also https://www.php.net/manual/en/book.sqlite3.php

// config
$plugin_subreddit_options=[
	'trigger'=>'!news',
	'trigger_help_text'=>'!news - Check for new reddit posts',
	'trigger_enabled'=>true,
	'default_prefix'=>'☍', // character(s) to display before message on irc
	'default_min_score'=>15, // minimum score for posts
	'default_link_target'=>true, // link to target url if there is one, not reddit; true or false
	'default_never_link'=>[], // if link_target enabled, don't link to these subs. array, e.g. ['sub1','sub2']
	'time_since_last_msg'=>240, // dont output new posts until noone has talked for this many seconds
	'time_between_runs'=>300, // seconds between checks for new posts. also requires activity like a server ping to activate
	'max_posts_per_run'=>5, // maximum number of posts to output each cycle; can reduce initial flood when skip_first_run_posts is off
	'num_pages_to_check'=>2, // number of pages in each subreddit to check. 1 or 2 is recommended
	'skip_first_run_posts'=>true, // dont output posts on first run for a sub, skip all found posts; otherwise may post 25 * num_pages
	'subreddits'=>[
		[
			'subreddit'=>'news'
		],
		// example of adding a second sub and overriding defaults
		//[
		//	'subreddit'=>'example',
		//	'prefix'=>'⯈',
		//	'min_score'=>20,
		//	'link_target'=>false,
		//	'never_link'=>['badsub']
		//],
	]
];

// end config, begin script

if(!extension_loaded('sqlite3')) exit("Install the PHP SQLite3 extension, e.g. with sudo apt-get install php7.x-sqlite3\n");

// open and automatically create sqlite database in current folder if doesn't exist
$plugin_subreddit_db=new SQLite3(dirname(__FILE__).'/'.basename(__FILE__,'.php')."-{$argv[1]}.db");
$plugin_subreddit_db->busyTimeout(0);
$r=$plugin_subreddit_db->querySingle("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='data';");
if($r==0){
	echo "Creating database file ".dirname(__FILE__).'/'.basename(__FILE__,'.php')."-{$argv[1]}.db\n";
	$plugin_subreddit_db->query("CREATE TABLE data ( subreddit varchar(21) NOT NULL, post_id varchar(9) NOT NULL, time varchar(12) NOT NULL );");
	$plugin_subreddit_db->query("CREATE INDEX subreddit on data(subreddit);");
	$plugin_subreddit_db->query("CREATE UNIQUE INDEX post_id on data(post_id);");
	$plugin_subreddit_db->query("CREATE INDEX time on data(time);");
}

// check if any subs have no data. if so, set to skip if enabled
$plugin_subreddit_options['skips']=[];
if(!empty($plugin_subreddit_options['skip_first_run_posts'])){
	foreach($plugin_subreddit_options['subreddits'] as $s){
		$r=$plugin_subreddit_db->querySingle("SELECT count(*) FROM data WHERE subreddit='{$s['subreddit']}'");
		if($r==0) $plugin_subreddit_options['skips'][$s['subreddit']]=true;
	}
}

if(!empty($plugin_subreddit_options['trigger_enabled'])) $custom_triggers[]=[$plugin_subreddit_options['trigger'], 'function:plugin_subreddit_trigger', false, $plugin_subreddit_options['trigger_help_text']];

function plugin_subreddit_trigger(){
	plugin_subreddit(true);
}

function plugin_subreddit_maintenance(){
	global $plugin_subreddit_db,$plugin_subreddit_options;
	$plugin_subreddit_options['last_maint']=time();
	echo "Running subreddit plugin maintenance\n";
	$plugin_subreddit_db->query("DELETE FROM data WHERE time < '".(time()-31536000)."';") or die("database error\n");
	$plugin_subreddit_db->query("VACUUM;") or die("database error\n");
}

if(!function_exists('strposOffset')){
	function strposOffset($search,$string,$offset){
		$arr=explode($search,$string);
		switch($offset){
			case $offset==0: return false; break;
			case $offset>max(array_keys($arr)): return false; break;
			default: return strlen(implode($search,array_slice($arr,0,$offset)));
		}
	}
}

register_loop_function('plugin_subreddit');

function plugin_subreddit($trigger=false){
	global $data,$time,$connect_time,$channel,$test_channel,$plugin_subreddit_db,$plugin_subreddit_options,$curl_info;
	if(strpos($data," PRIVMSG $channel :")!==false) $plugin_subreddit_options['lastmsg']=$time;
	if($time-$connect_time>25 && (($time-$plugin_subreddit_options['lastrun']>$plugin_subreddit_options['time_between_runs'] && $time-$plugin_subreddit_options['lastmsg']>=$plugin_subreddit_options['time_since_last_msg']) || ($trigger && $time-$plugin_subreddit_options['lastrun']>30))){
		$newcnt=0;
		// loop subreddits
		foreach($plugin_subreddit_options['subreddits'] as $item){
			$min_score=isset($item['min_score'])?$item['min_score']:$plugin_subreddit_options['default_min_score'];
			$prefix=isset($item['prefix'])?$item['prefix']:$plugin_subreddit_options['default_prefix'];
			$link_target=!empty($item['link_target'])?$item['link_target']:$plugin_subreddit_options['default_link_target'];
			$never_link=!empty($item['never_link'])?$item['never_link']:$plugin_subreddit_options['default_never_link'];
			echo "Scanning r/{$item['subreddit']}...\n";
			$plugin_subreddit_options['lastrun']=$time;
			$cnt=0;
			for($i=0;$i<$plugin_subreddit_options['num_pages_to_check'];$i++){
				if($cnt==0) $r=json_decode(curlget([CURLOPT_URL=>"https://www.reddit.com/r/{$item['subreddit']}.json",CURLOPT_HTTPHEADER=>["Cookie: _options=%7B%22pref_quarantine_optin%22%3A%20true%7D"]]));
				else {
				        $r2=json_decode(curlget([CURLOPT_URL=>"https://www.reddit.com/r/{$item['subreddit']}.json?after=".$r->data->children[$cnt-1]->data->name,CURLOPT_HTTPHEADER=>["Cookie: _options=%7B%22pref_quarantine_optin%22%3A%20true%7D"]]));
					$r->data->children=array_merge($r->data->children,$r2->data->children);
				}
				$cnt=count($r->data->children);
			}
			if(isset($r2)) unset($r2);
			// skip if needed
			if(!empty($plugin_subreddit_options['skips'][$item['subreddit']])){
				echo "Skipping first run for /r/{$item['subreddit']}. marking all posts as processed.\n";
				foreach($r->data->children as $d) $plugin_subreddit_db->query("INSERT INTO data VALUES('{$item['subreddit']}','".substr($d->data->name,strpos($d->data->name,'_')+1)."','$time');");
				unset($plugin_subreddit_options['skips'][$item['subreddit']]);
				continue;
			}
			if(!empty($r)){
				foreach($r->data->children as $d){
					if($d->data->ups-$d->data->downs<$min_score) continue; // score needed
					$id=substr($d->data->name,strpos($d->data->name,'_')+1);
					$r=$plugin_subreddit_db->querySingle("SELECT COUNT(*) FROM data WHERE post_id = '$id'");
					if($r==0){
						$d->data->url=html_entity_decode($d->data->url);
						$d->data->title=html_entity_decode($d->data->title);
						// strip utm_* query vars, purl & pstr also used later
						$purl=parse_url($d->data->url);
						if(!empty($purl['query'])){
							parse_str($purl['query'],$pstr);
							foreach($pstr as $k=>$v) if(substr($k,0,4)=='utm_') unset($pstr[$k]);
							$q=http_build_query($pstr);
							$d->data->url=$purl['scheme'].'://'.$purl['host'].$purl['path'].(!empty($q)?'?'.$q:'');
						}
						$url='';
						if(empty($link_target)) $url="https://redd.it/$id";
						else {
							// find target url
							if(preg_match('#^https://twitter.com/.*status/.*\?s=.*#U',$d->data->url)) $d->data->url=substr($d->data->url,0,strpos($d->data->url,'?s='));
							if($d->data->permalink==str_replace('https://www.reddit.com','',$d->data->url)) $url="https://redd.it/$id";
							elseif(preg_match("#^https://.*\.reddit\.com/r/[^/]*/comments/([^/]*)/[^/]*/$#U",$d->data->url,$m)) $url="https://redd.it/$m[1]";
							elseif(preg_match("#^https://.*\.reddit\.com/r/[^/]*/comments/([^/]*)/[^/]*/([^/]*)/#U",$d->data->url,$m)) $url="https://www.reddit.com/comments/$m[1]/_/$m[2]";
							elseif(strpos($d->data->url,'//www.youtube.com/')!==false || strpos($d->data->url,'//youtube.com/')!==false){
								$url="https://youtu.be/{$pstr['v']}";
								unset($pstr['v']);
								unset($pstr['feature']);
								$q=http_build_query($pstr);
								$url.=!empty($q)?'?'.$q:'';
							} else {
								// check if a blacklisted sub
								if(!empty($never_link)) foreach($never_link as $s) if(preg_match("#^https://.*\.reddit\.com/r/$s/.*#",$d->data->url)) $url="https://redd.it/$id";
								if(empty($url)){
									$short=['youtu.be','t.co','redd.it','wp.me'];
									$isshort=false;
									foreach($short as $s) if(strpos($d->data->url,"://$s/")!==false){ $isshort=true; break; }
									if(!$isshort){
										// look for short url on site
										$html=curlget([CURLOPT_URL=>$d->data->url]);
										if(substr($curl_info['EFFECTIVE_URL'],0,13)=='https://t.co/'){ // weird t.co 200 redirect
											preg_match('/^<head><noscript><META http-equiv="refresh" content="0;URL=(.*)">/U',$html,$m);
											if(!empty($m[1])) $html=curlget([CURLOPT_URL=>$m[1]]);
										}
										if(!empty($html)){
											if(strpos($d->data->url,'reuters.com/')!==false) preg_match("/\"shareUrl\":\"(.*)\"/U",$html,$m);
											else preg_match("/<link rel=[\"']shortlink[\"'].*href=[\"'](.*)[\"'].*>/U",$html,$m);
											if(!empty($m[1])){
												if(substr($m[1],0,4)=='http') $url=$m[1];
												elseif(substr($m[1],0,1)=='/') $url=substr($d->data->url,0,strposOffset('/',$d->data->url,3)).$m[1]; // shortlink relative of root (zerohedge)
												else $url=$d->data->url;
											} else $url=$d->data->url;
										} else $url=$d->data->url;
									} else $url=$d->data->url;
									// get real domain on wp.me links
									if(preg_match("#^https://wp\.me/.*#",$url)){
										# preg_match("/<article id=\"post-(.*)\"/U",$html,$m);
										preg_match('/<body[^>]*class="[^"]*postid-([^ "]*)/',$html,$m);
										if(!empty($m[1])) $url=substr($d->data->url,0,strposOffset('/',$d->data->url,3))."/?p=$m[1]";
									}
								}
							}
						}
						echo "New post $id {$d->data->ups}\n";
						$txt="$prefix {$d->data->title} $url";
						if($channel <> $test_channel) $plugin_subreddit_db->query("INSERT INTO data VALUES('{$item['subreddit']}','$id','$time');") or die("subreddit plugin database disappeared. restart bot\n");
						send("PRIVMSG $channel :$txt\n");
						$newcnt++;
						if($newcnt==$plugin_subreddit_options['max_posts_per_run']) return;
						sleep(1);
					}
				}
			} else echo "No response from reddit\n";
			unset($r);
		} // end loop
		if($trigger && $newcnt==0) send("PRIVMSG $channel :No news.\n");
		// maintenace once/day
		if($time-$plugin_subreddit_options['last_maint']>86400) plugin_subreddit_maintenance();
	} elseif($trigger && $time-$connect_time<=25){
		$s=25-($time-$connect_time);
		send("PRIVMSG $channel :Initializing, try again in $s second".($s<>1?'s':'')."...\n");
	} elseif($trigger && $time-$plugin_subreddit_options['lastrun']<=30){
		$s=30-($time-$plugin_subreddit_options['lastrun']);
		send("PRIVMSG $channel :Try again in $s second".($s<>1?'s':'')."...\n");
	}
}
