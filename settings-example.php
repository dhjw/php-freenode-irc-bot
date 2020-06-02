<?php
// settings
$admins=['dw1']; // array of account names
// $host='irc.freenode.net:6667';
$host='ssl://irc.freenode.net:7000'; // ssl
$channel='##examplechan';
$nick='somebot'; // default nick
$test_channel='##exampletest'; // run script as "php bot.php <instance> test" for test mode
$test_nick='somebot[beta]';
$user='your_username'; // Freenode account - required, for SASL
$pass='your_password';
$ident='bot'; // ident@...
$ircname='a happy little bot by '.$admins[0]; // "real name" in /whois
$altchars=['_','^','-','`']; // for alt nicks
$custom_connect_ip=false;
$connect_ip='1.2.3.4'; // source IP, ipv4 or ipv6
$custom_curl_iface=false;
$curl_iface=$connect_ip; // can be interface e.g. eth0 or ip
$stream_timeout=320;
$youtube_api_key='';
$bitly_token='';
$gcloud_translate_keyfile=''; // e.g. translate.json, per step 1 at https://cloud.google.com/translate/docs/getting-started, put in current folder
$gcloud_translate_max_chars=50000; // per month, see https://cloud.google.com/translate/pricing
$imgur_client_id='';
$currencylayer_key='';
$omdb_key='';
$wolfram_appid='';
$twitter_consumer_key=""; // twitter api, apply at https://developer.twitter.com (use if twitter titles stop working)
$twitter_consumer_secret="";
$twitter_access_token="";
$twitter_access_token_secret="";

$tor_enabled=false; // handle .onion urls
$tor_all=false; // get all urls through tor (not recommended due to anti-tor measures)
$tor_host='127.0.0.1';
$tor_port=9050;

// replace in retrieved titles
$title_replaces=[
	$connect_ip=>'6.9.6.9', // for privacy (ip can still be determined by web logs)
	gethostbyaddr($connect_ip)=>'example.com'
];

// nicks to ignore urls from
$ignore_nicks=[
	// 'otherbot'
];

// urls to ignore. case insensitive, automatic wildcard at beginning and end
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
$skip_dupe_output=false; // avoid repeating lines which can trigger some flood bots

// see readme.md at https://github.com/dhjw/php-freenode-irc-bot for how to use custom triggers, loop processes and plugins
