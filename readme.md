## PHP Freenode IRC Bot
A simple but powerful FreeNode IRC bot written in PHP

### How to use
- Install php with cURL support, e.g. on ubuntu `sudo apt install php7.2 php7.2-curl`
- Clone this repository. `git clone https://github.com/dhjw/php-freenode-irc-bot` or just download [bot.php](https://raw.githubusercontent.com/dhjw/php-freenode-irc-bot/master/bot.php) and [settings-example.php](https://raw.githubusercontent.com/dhjw/php-freenode-irc-bot/master/settings-example.php)
- Copy `settings-example.php` to `settings-<instance>.php`
- Create a Freenode account for the bot to authenticate (required by most/all Freenode servers). See `/msg nickserv help register`
- Install pastebinit (recommended) `sudo apt install pastebinit` or [other](https://pkgs.org/download/pastebinit)
- [Get a bitly token](https://bitly.com) (recommended)
- Edit the `settings-<instance>.php` file to contain your settings, username and password
- Run the bot with `php bot.php <instance>` or `php bot.php <instance> test` for test mode. On Linux it is recommended to run the bot in `screen` so closing the terminal won't kill the bot

## Setting up Services
### YouTube URL info and search via !yt
- Create a project at [console.cloud.google.com](https://console.cloud.google.com/)
- Under APIs and Services enable [YouTube Data API v3](https://developers.google.com/youtube/v3/)
- "Where will you be calling the API from?" CLI Tool
- Under Credentials create an API key
- Add the API key to the `$youtube_api_key` variable in `settings-<instance>.php`
- Usage is free for hundreds of thousands of queries per day
