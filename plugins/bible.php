<?php
// look up a kjv bible verse on https://api.bible
// api key
$bible_key='';

$custom_triggers[]=['!kjv', 'function:bible', true, '!kjv - look up a bible verse'];
function bible(){
	global $target,$channel,$args,$users,$bible_key,$bible_books;
	$args=trim($args);
	$book=substr($args,0,strrpos($args,' '));
	$chap_verse=explode(':',substr($args,strrpos($args,' ')+1));
	if(empty($args) || empty($book) || (empty($chap_verse[0]) || empty($chap_verse[1])) || (!is_numeric($chap_verse[0]) || !is_numeric($chap_verse[1]))) return(send("PRIVMSG $target :Provide a book, chapter and verse, e.g. !kjv gen 1:1\n"));
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
	// find the verse searched for by bookid
	$r=curlget([CURLOPT_URL=>"https://api.scripture.api.bible/v1/bibles/de4e12af7f28f599-02/passages/$bookid.$chap_verse[0].$chap_verse[1]",CURLOPT_HTTPHEADER=>["api-key: $bible_key"]]);
	if(empty($r)) return(send("PRIVMSG $target :Error getting verse: No response\n"));
	$r=json_decode($r);
	if(isset($r->error) || !isset($r->data) || !isset($r->data->content)) return(send("PRIVMSG $target :Error getting verse".(isset($r->error)?": {$r->error}":'')."\n"));
	$r->data->content=str_replace('</span>','</span> ',$r->data->content);
	$r->data->content=preg_replace("/<span class=\"add\">(.*?)<\/span>/","<span class=\"add\">\x1F$1\x1F</span>",$r->data->content);
	$r->data->content=strip_tags($r->data->content);
	$r->data->content=substr($r->data->content,strpos($r->data->content,' ')+1);
	$r->data->content=preg_replace("/\s+/",' ',$r->data->content);
	foreach(['.',':',';'] as $v) $r->data->content=str_replace(" $v",$v,$r->data->content);
	$r->data->content=str_replace('¶','',$r->data->content);
	send("PRIVMSG $target :".trim($r->data->content)."\n");
}
