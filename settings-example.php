<?php
// settings
$admins = ['dw1']; // account names
// $host = 'irc.freenode.net:6667';
$host = 'ssl://irc.freenode.net:7000'; // ssl
$channel = '##examplechan';
$nick = 'somebot'; // default nick
$test_channel = '##exampletest'; // run script as "php bot.php <instance> test" for test mode
$test_nick = 'somebot[beta]';
$user = 'your_username'; // Freenode account - required, for SASL
$pass = 'your_password';
$ident = 'bot'; // ident@...
$ircname = 'a happy little bot by '.$admins[0]; // "real name" in /whois
$altchars=['_','^','-','`']; // for alt nicks
$custom_connect_ip=false;
$connect_ip='1.2.3.4'; // source IP, ipv4 or ipv6
$custom_curl_iface=false;
$curl_iface=$connect_ip; // can be interface e.g. eth0 or ip
$stream_timeout=320;
$youtube_api_key='';
$bitly_token='';
$geo_key=''; // ipinfodb.com
$bible_key=''; // bibles.org
$gcloud_translate_keyfile=''; // e.g. translate.json, per step 1 at https://cloud.google.com/translate/docs/getting-started, put in current folder
$imgur_client_id='';
$currencylayer_key='';
$omdb_key='';
$wolfram_appid='';

# replace in retrieved titles
$title_replaces=[
	$connect_ip=>'6.9.6.9', // for privacy (ip can still be determined by web logs)
	gethostbyaddr($connect_ip)=>'example.com'
];

# nicks to ignore urls from
$ignore_nicks=[
	// 'otherbot'
];

# urls to ignore. case insensitive, automatic wildcard at beginning and end
$ignore_urls=[
	// 'https://example.com',
];

// blacklisted host strings and IPs. auto-quieted
$host_blacklist_enabled=false;
$host_blacklist_strings=[];
$host_blacklist_ips=[ // can be CIDR ranges e.g. to blacklist entire ISPs based on https://bgp.he.net results
	// '1.2.3.4',
];
$host_blacklist_time=86400; // quiet time in seconds

// flood protection
$flood_protection_on=true;
$flood_max_buffer_size=20; // number of lines to keep in buffer, must meet or exceed maxes set below
$flood_max_conseq_lines=20;
$flood_max_conseq_time=600; // secs to +q for
$flood_max_dupe_lines=3;
$flood_max_dupe_time=600;

// more options
$allow_invalid_certs=false; // allow connections to sites with invalid ssl certificates
$title_bold=false; // bold url titles. requires channel not have mode +c which strips colors
$title_og=false; // use social media <meta property="og:title" ...> titles instead of <title> tags, if available
$voice_bot=false; // voice the bot. requires +o channel access
$disable_sasl=false;
$disable_nickserv=false; // note: nickserv not used if sasl enabled
$disable_help=false;
$disable_triggers=false;
$disable_titles=false;

// custom triggers (trigger in channel or pm will cause specific string to be output to channel or pm or a custom function to execute)
// array of arrays [ trigger, string to output (or function:name), respond via PM true or false (default true. if false always posts to channel), help text ]
// with custom function
// - $args holds all arguments sent with the trigger in a trimmed string
// - with PM true $target global holds the target whether channel or user, with PM false $target always holds channel, respond with e.g. send("PRIVMSG $target :<text>\n");
$custom_triggers=[
	['!rules-example', 'Read the channel rules at https://example.com', true, '!rules-example - Read the channel rules'],
	['!func-example', 'function:example_words', true, '!func-example - Output a random word']
];

function example_words(){
	global $target,$args;
	echo "!func-example / example_words() called by $target. args=$args\n";
	$words=['quick','brown','fox','jumps','over','lazy','dog'];
	$out=$words[rand(0,count($words)-1)];
	send("PRIVMSG $target :$out\n");
}

// custom loop (runs on every line received from the server, after connection)
// function custom_loop($data){
// 	// code
//	echo "[custom loop] data=$data\n";
// }