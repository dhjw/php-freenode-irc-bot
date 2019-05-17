## PHP Freenode IRC Bot
A simple but powerful [FreeNode](https://freenode.net/) IRC bot written in PHP

### How to use
- Install php with pcntl, cURL, mbstring, xml and json support, e.g. on ubuntu `sudo apt install php php-curl php-mbstring php-xml php-json`
- Clone this repository. `git clone https://github.com/dhjw/php-freenode-irc-bot` or just download [bot.php](https://raw.githubusercontent.com/dhjw/php-freenode-irc-bot/master/bot.php) and [settings-example.php](https://raw.githubusercontent.com/dhjw/php-freenode-irc-bot/master/settings-example.php)
- Copy `settings-example.php` to `settings-<instance>.php`
- Create a Freenode account for the bot to authenticate. See `/msg nickserv help register`
- Install pastebinit for help text (recommended) `sudo apt install pastebinit` or find a binary [here](https://pkgs.org/download/pastebinit)
- [Get a bitly token](https://bitly.com) for short URLs (recommended)
- Edit the `settings-<instance>.php` file to contain your settings, username and password
- Run the bot with `php bot.php <instance>` or `php bot.php <instance> test` for test mode. On Linux it is recommended to run the bot in `screen` so closing the terminal won't kill the bot
- For admin op commands give the bot account +ort access with ChanServ for your channel. `/msg chanserv flags ##example botuser +ort`

## Setting up Services
### YouTube URL info and search via !yt
- Create a project at [console.cloud.google.com](https://console.cloud.google.com/)
- Under APIs and Services enable [YouTube Data API v3](https://developers.google.com/youtube/v3/)
- "Where will you be calling the API from?" CLI Tool
- Under Credentials create an API key
- Add the API key to the `$youtube_api_key` variable in `settings-<instance>.php`
- Usage is free for thousands of queries per day

### (Todo: documentation for many more - see settings file API key variables for supported services)

## Custom Triggers
You can set up custom triggers in `settings-<instance>.php` files. Custom triggers are overridden by admin triggers and override global triggers, which you should probably avoid. See bot !help for a list of triggers.

Examples of custom triggers:
```
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
```
Examples of custom loop functions:
```
register_loop_function('custom_loop_example');
function custom_loop_example(){
	global $data,$time,$channel;
	echo "[custom loop] time=$time data=$data\n";
}
```
## Contact
Hit up `dw1` on Freenode with any questions or bugs.

## License
Do whatever you want with it.
