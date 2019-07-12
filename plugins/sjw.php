<?php
// generate an SJW insult, optionally directed at a specific user. can trigger via PM for stealth insult
$custom_triggers[]=['!sjw', 'function:plugin_sjw', true, '!sjw - generate an SJW insult'];
function plugin_sjw(){
	global $target,$channel,$args,$users;
	if(!empty($args)){
		$id=search_multi($users,'nick',$args);
		if(empty($id)){
			send("PRIVMSG $target :Target user not in channel\n");
			return;
		}
	}
	$words=[
		[ "racist", "xenophobic", "privileged", "white", "woman-hating", "misogynistic", "racist", "chauvinistic", "hateful", "fascist", "racist", "straight", "narrow-minded", "deluded", "marginalizing", "eurocentric" ],
		[ "sexist", "bigoted", "elitist", "oppressive", "ignorant", "patriarchal", "fat-shaming", "male", "hyper-masculine", "mansplaining", "middle-class", "nativist", "close-minded", "euro-centric", "ethno-centric", "elitist", "alt-right" ],
		[ "homophobic", "transphobic", "cisgendered", "islamophobic", "rich", "greedy", "nazi", "intolerant", "heteronormative", "heterosexual", "thin-privileged", "imperialistic", "nationalistic", "anti-semitic", "hate-mongering", "victim-blaming", "man-splaining", "putin-loving" ],
		[ "bigot", "Christian", "Conservative", "Republican", "Catholic", "Protestant", "prude", "zionist", "pig", "Russian Hacker", "nazi", "Neo-Confederate", "Hitler", "neo-nazi", "traditionalist", "subhuman", "rapist", "colonialist", "white-supremacist", "sympathizer", "Nazi", "rape-apologist", "cracker", "whitey", "white-devil", "WASP", "fear-monger", "transphobe", "islamophobe", "nationalist" ]
	];
	send("PRIVMSG $channel :".(!empty($args)?"$args: ":'')."You ".$words[0][rand(0,count($words[0])-1)].', '.$words[1][rand(0,count($words[1])-1)].', '.$words[2][rand(0,count($words[2])-1)].' '.$words[3][rand(0,count($words[3])-1)]."!\n");

}
