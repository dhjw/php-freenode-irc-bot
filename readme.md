## PHP FreeNode IRC Bot
A simple but powerful FreeNode IRC bot written in PHP

### How to use
- Install php with cURL support, e.g. on ubuntu `sudo apt install php7.2 php7.2-curl`
- Clone this repository. `git clone https://github.com/dhjw/ircbot` or just download bot.php and settings-example.php
- Copy `settings-example.php` to `settings-<instance>.php`
- Create a FreeNode account for the bot (required)
- Install pastebinit (recommended)
- Get a bitly token (optional)
- Edit the `settings-<instance>.php` file to contain your settings, username and password
- Run the bot with `php bot.php <instance>` or `php bot.php <instance> test` for test mode. On Linux it is recommended to run the bot in `screen` so closing the terminal won't kill the bot