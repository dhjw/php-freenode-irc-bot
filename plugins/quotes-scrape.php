<?php
// scrape quotes from brainyquote
// usage: php quotes-scrape.php <url or search query>
// will create quotes.txt in script folder if doesn't exist, or add to it while filtering duplicates if does exist
// this is not a plugin file. it is used to build quotes.txt with one quote per line for the plugin file quotes.php
// to ignore a quote permanently add "#" (without quotes) to the beginning of the line in quotes.txt

if(empty($argv[1])) exit("provide a topic or search query as first argument, e.g. 'positive' or 'positive person'\n");
chdir(dirname(__FILE__));
array_shift($argv);
$q=implode(' ',$argv);
$html='';

// just load whatever url
if(preg_match("/^http/",$q)){
	$url=$q;
	echo "loading url $url\n";
	$html=@file_get_contents($url);
}
// topic, keyword or search provided
if(empty($html)){
	$url="https://www.brainyquote.com/topics/".str_replace(' ','-',$q)."-quotes";
	echo "loading topic/keyword url $url\n";
	$html=@file_get_contents($url);
}
if(empty($html)){
	echo "error loading topic/keyword url, trying a search\n";
	$id=$q;
	$url="https://www.brainyquote.com/search_results?q=".urlencode($q);
	echo "loading url $url\n";
	$html=@file_get_contents($url);
	if(empty($html)) exit("error getting search html. aborting\n");
}

// get id
$pos=strpos($html,'domainId:');
if($pos===false) exit("could not find id on page\n");
$id=substr($html,$pos+10);
$id=substr($id,0,strpos($id,'"'));
if(substr($id,0,2)=='t:') $mode='topic'; elseif(substr($id,0,2)=='k:') $mode='keyword'; else $mode='search';

// get vid
$pos=strpos($html,'vid:"');
if($pos===false) exit("could not find vid on page\n");
$vid=substr($html,$pos+5);
$vid=substr($vid,0,strpos($vid,'"'));

// read existing quotes from quotes.txt
$allquotes=@trim(@file_get_contents('quotes.txt'));
if(empty($allquotes)) $allquotes=[]; else $allquotes=explode("\n",$allquotes);

// initialize some vars
$newquotes=[];
$quotestmp=[];
$lastquotestmp=null;
$numprocessed=0;

// get quotes on initial page
echo "getting page 1\n";
get_quotes($html);
if($numprocessed==0) exit("no new quotes in result. aborting\n");
echo "got ".count($quotestmp)." new quotes\n";
if(count($quotestmp)>0) print_r($quotestmp);
echo "\n";

// get more pages
$page=2;
while(1){
	echo "getting page $page\n";
	$cmd="curl -ss -H 'User-agent: Mozilla/5.0 (X11; CrOS x86_64 12607.81.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.119 Safari/537.36' -H 'content-type: application/json' --data '{\"typ\":\"$mode\",\"langc\":\"en\",\"v\":\"9.7.6:3684830\",\"ab\":\"a\",\"pg\":$page,\"id\":\"$id\",\"vid\":\"$vid\",\"fdd\":\"d\",\"m\":0}' https://www.brainyquote.com/api/inf";
	$html=trim(shell_exec($cmd));
	$r=json_decode($html);
	if(empty($r)) exit("error, response empty. aborting\n");
	if(!isset($r->content) || !isset($r->qCount)){ echo "error: unexpected or no content returned. try running again\n\n"; break; }
	if($r->qCount==0){ echo "no quotes found in result. finishing\n\n"; break; }
	get_quotes($r->content);
	if($numprocessed==0){ echo "no new quotes in result. finishing\n\n"; break; }
	echo "got ".count($quotestmp)." new quotes\n";
	if(count($quotestmp)>0) print_r($quotestmp);
	echo "\n";
	$page++;
}

// finished getting new quotes. update quotes.txt
echo "got ".count($newquotes)." total new quotes\n";
if(count($newquotes)>0){
	$allquotes=array_merge($newquotes,$allquotes);
	echo "writing updated quotes.txt with ".count($allquotes)." total quotes\n";
	file_put_contents('quotes.txt',implode("\n",$allquotes));
} else echo "skipping update of quotes.txt\n";

// function: get quotes from html
function get_quotes($html){
	global $newquotes,$quotestmp,$lastquotestmp,$allquotes,$q,$mode,$numprocessed;
	$quotestmp=[];
	$allquotestmp=[];
	$numprocessed=0;
	$lines=explode("\n",$html);
	$initem=false;
	foreach($lines as $line){
		if(strpos($line,'grid-item')!==false) $initem=true;
		if(!$initem) continue;
		if(strpos($line,'view quote')!==false){
			$quote=trim(strip_tags($line));
			$quote=html_entity_decode($quote,ENT_QUOTES);
			continue;
		}
		if(strpos($line,'view author')!==false){
			$author=trim(strip_tags($line));
			$author=htmlspecialchars_decode($author,ENT_QUOTES | ENT_COMPAT | ENT_HTML401);
			$quoteline="\"$quote\" $author";
			$allquotestmp[]=$quoteline;
			$numprocessed++;
			// skip if a search and quote doesn't contain exact search query
			if($mode=='search' && stripos($quoteline,$q)===false){
				echo "Warning: [ $quoteline ] doesn't contain exact query \"$q\", skipping\n";
				continue;
			}
			// skip if already in quotes file
			if(array_intersect($allquotes,[$quoteline,"#$quoteline","# $quoteline"])){
				echo "Duplicate: [ $quoteline ] already in quotes.txt, skipping\n";
				continue;
			}
			// skip if already found this run
			if(in_array($quoteline,$newquotes)) continue;
			// add new quote to new quotes
			$newquotes[]=$quoteline;
			$quotestmp[]=$quoteline;
			$initem=false;
			continue;
		}
	}
	// if quotestmp is same as lastquotestmp, abort - searching sometimes returns the same duplicates for endless pages
	if($lastquotestmp!==null && $allquotestmp==$lastquotestmp){
		echo "got all the same quotes as last page, avoiding endless loop\n";
		$numprocessed=0;
		return;
	}
	$lastquotestmp=$allquotestmp;
}
