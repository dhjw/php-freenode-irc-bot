<?php
// settings
$admins=['dw1']; // array of account names (registered nicks on rizon)
$network='libera'; // supported: libera, rizon, gamesurge, freenode
// $host='irc.libera.chat:6667';
$host='ssl://irc.libera.chat:7000'; // ssl
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
$twitch_client_id=''; // https://dev.twitch.tv
$twitch_client_secret='';
$twitter_consumer_key=''; // https://developer.twitter.com
$twitter_consumer_secret='';
$twitter_access_token='';
$twitter_access_token_secret='';
$twitter_nitter_enabled=true; // overrides API
$twitter_nitter_instance='https://nitter.privacydev.net'; // could change to e.g. http://localhost:8080
$nitter_links_via_twitter=true;

$tor_enabled=false; // handle .onion urls
$tor_all=false; // get all urls through tor (not recommended due to anti-tor measures)
$tor_host='127.0.0.1';
$tor_port=9050;

$curl_impersonate_enabled=false; // https://github.com/lwthiker/curl-impersonate
$curl_impersonate_binary='/usr/local/bin/curl_chrome110';
$curl_impersonate_all=false;
$curl_impersonate_domains=[ // do not include subdomain, multiple tlds are fine as we use public suffix list
	'archive.today',
	'archive.ph',
	'archive.is',
	'archive.li',
	'archive.vn',
	'archive.fo',
	'archive.md',
	'facebook.com',
	'instagram.com',
	'newsmax.com',
];

// replace in retrieved titles
$title_replaces=[
	$connect_ip=>'6.9.6.9', // for privacy (ip can still be determined by web logs)
	gethostbyaddr($connect_ip)=>'example.com'
];

// nicks to ignore. also matches up to one additional non-alpha character
$ignore_nicks=[
	// 'otherbot'
];

// urls to ignore titles for, starting with domain. e.g. 'example.com', 'example.com/path'
$ignore_urls=[
	// 'example.com'
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
$perform_on_connect=''; // raw commands to perform on connect before channel join separated by semicolon, e.g. MODE $nick +i; PRIVMSG someone :some thing
$allow_invalid_certs=false; // allow connections to sites with invalid ssl certificates
$title_bold=false; // bold url titles. requires channel not have mode +c which strips colors
$title_og=false; // use social media <meta property="og:title" ...> titles instead of <title> tags, if available
$voice_bot=false; // ask chanserv to voice the bot on join
$op_bot=false; // ask chanserv to op the bot on join. will also automatically enable $always_opped
$always_opped=false; // set to true if bot is auto-opped on join and should stay opped after admin actions
$disable_sasl=false;
$disable_nickserv=false; // note: nickserv not used if sasl enabled. affects authserv process on gamesurge network
$disable_help=false;
$disable_triggers=false; // disable global triggers, not admin or custom triggers
$disable_titles=false;
$skip_dupe_output=false; // avoid repeating lines which can trigger some flood bots
$title_cache_enabled=false; // shared between all bots. requires php-sqlite3 and uses /run/user tmpfs folder if possible
$title_cache_size=64;

// see readme.md at https://github.com/dhjw/php-freenode-irc-bot for how to use custom triggers, loop processes and plugins
