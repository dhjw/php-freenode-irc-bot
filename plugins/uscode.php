<?php
// detect us code references in conversation and post a short description and link
register_loop_function('plugin_uscode');
function plugin_uscode(){
	global $data,$ex,$nick,$incnick,$channel;
	// skip non-channel msgs
	if(!preg_match("/[^ ]* PRIVMSG $channel/",$data)) return;
	$text=rtrim(substr($data,strpos(ltrim($data,':'),':')+2));
	preg_match_all('/\b(\d{1,2}a?) (?:usc|u\.?s\.? ?code ?§?) ?(\d{1,5})?\b/iu',$text,$m);
	// skip if trigger called
	if(substr($text,0,1)=='!') return;
	// skip if text contains link already
	if(preg_match('#https?://#',$text)) return;
	if(empty($m[0])) return;
	for($i=0;$i<count($m[0]);$i++){
		$link="https://www.law.cornell.edu/uscode/text/{$m[1][$i]}";
		if(!empty($m[2][$i])) $link.="/{$m[2][$i]}";
		// get title
		$html=file_get_contents($link);
		$dom=new DOMDocument();
		if($dom->loadHTML('<?xml version="1.0" encoding="UTF-8"?>' . $html)){
			$list=$dom->getElementsByTagName("title");
			if($list->length>0) $title=$list->item(0)->textContent;
			$title=substr($title,0,strpos($title,'|'));
			$title=html_entity_decode($title,ENT_QUOTES | ENT_HTML5,'UTF-8');
			$title=str_replace('  ',' ',$title);
			$title=str_replace('U.S. Code §','USC',$title);
			$title=trim($title);
			if(empty($title) || preg_match("/^Page not found/",$title)){
				send("PRIVMSG $channel :{$m[1][$i]} USC ".(!empty($m[2][$i])?"{$m[2][$i]} ":'')."- Not found\n");
				return 2;
			}
		}
		send("PRIVMSG $channel :$title $link\n");
	}
	return 2;
}