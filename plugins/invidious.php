<?php
// invidious youtube api search plugin
$custom_triggers[]=['!inv', 'function:plugin_invidious', true, '!inv - search YouTube and output Invidious link'];

function plugin_invidious(){
	global $args,$num_file_get_retries,$privto,$youtube_api_key;
	// essentially a copy of the !yt search feature with return instead of continue
	if(empty($args)){
		send("PRIVMSG $privto :Provide a query.\n");
		return;
	}
	for($i=$num_file_get_retries;$i>0;$i--){
		$tmp=file_get_contents("https://www.googleapis.com/youtube/v3/search?q=".urlencode($args)."&part=snippet&maxResults=1&type=video&key=$youtube_api_key");
		$tmp=json_decode($tmp);
		if(!empty($tmp)) break; else if($i>1) sleep(1);
	}
	$v=$tmp->items[0]->id->videoId;
	if(empty($tmp)){
		send("PRIVMSG $privto :[ Temporary YouTube API error ]\n");
		return;
	} elseif(empty($v)){
		send("PRIVMSG $privto :There were no results matching the query.\n");
		return;
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
	send("PRIVMSG $privto :https://invidio.us/watch?v=$v | $title$ytextra\n");
	return;
}
