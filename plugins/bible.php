<?php
// look up a kjv bible verse on https://api.bible
// api key
$bible_key='';

$custom_triggers[]=['!kjv', 'function:bible', true, '!kjv - look up a bible verse'];
function bible(){
	global $target,$channel,$args,$users,$bible_key,$bible_books,$baselen;

	preg_match('/^([a-zA-Z0-9\ ]+) (\d+):(\d+)(?:-(\d+))?/',trim($args),$m);
	if(empty($m[1]) || empty($m[2]) || empty($m[3]) || (!empty($m[4]) && $m[4]<=$m[3])){
		send("PRIVMSG $target :Example usage: !kjv matt 1:1-3\n");
		return;
	}
	$book=$m[1];
	$chap=$m[2];
	if(empty($m[4])) $verses=[$m[3]]; else {
		$verses=[];
		for($i=$m[3];$i<=$m[4];$i++) $verses[]=$i;
	}
	if(count($verses)>5){
		send("PRIVMSG $target :Max 5 verses at a time\n");
		return;
	}

	// get book ids if we dont have them already
	if(empty($bible_books)){
		$r=curlget([CURLOPT_URL=>'https://api.scripture.api.bible/v1/bibles/de4e12af7f28f599-02/books',CURLOPT_HTTPHEADER=>["api-key: $bible_key"]]);
		if(empty($r)) return(send("PRIVMSG $target :Error getting bible books\n"));
		$r=json_decode($r);
		if(isset($r->error) || !is_array($r->data)) return(send("PRIVMSG $target :Error getting bible books".(isset($r->error)?": {$r->error}":'')."\n"));
		$bible_books=$r;
	}

	// find the book searched for with loose matching on id and name
	foreach($bible_books->data as $book2) if(preg_match("/^".preg_quote($book)."/i",$book2->id) || preg_match("/^".preg_quote($book)."/i",$book2->name)){ $bookid=$book2->id; break; }
	if(!isset($bookid)) return(send("PRIVMSG $target :Bible book \"$book\" not found\n"));

	// find the verses searched for by bookid
	$text='';
	foreach($verses as $k=>$verse){
		$r=curlget([CURLOPT_URL=>"https://api.scripture.api.bible/v1/bibles/de4e12af7f28f599-02/passages/$bookid.$chap.$verse",CURLOPT_HTTPHEADER=>["api-key: $bible_key"]]);
		if(empty($r)&&empty($text)) return(send("PRIVMSG $target :Error getting verse: No response\n"));
		$r=json_decode($r);
		if((isset($r->error) || !isset($r->data) || !isset($r->data->content)) && empty($text)) return(send("PRIVMSG $target :Error getting verse".(isset($r->error)?": {$r->error}":'')."\n"));
		$r->data->content=str_replace('</span>','</span> ',$r->data->content);
		$r->data->content=preg_replace("/<span class=\"add\">(.*?)<\/span>/","<span class=\"add\">\x1F$1\x1F</span>",$r->data->content);
		$r->data->content=strip_tags($r->data->content);
		$r->data->content=substr($r->data->content,strpos($r->data->content,' ')+1);
		$r->data->content=preg_replace("/\s+/",' ',$r->data->content);
		foreach(['.',':',';'] as $v) $r->data->content=str_replace(" $v",$v,$r->data->content);
		$r->data->content=str_replace('¶','',$r->data->content);
		$text.=(count($verses)>1?($k>0?' ':'')."$verse ":'').trim($r->data->content);
	}
	while(1){
		$s=trim(str_shorten($text,999,['nodots'=>true])); // max length
		send("PRIVMSG $target :$s\n");
		if($s==trim($text)) break;
		$text=substr($text,strlen($s));
	}
}
