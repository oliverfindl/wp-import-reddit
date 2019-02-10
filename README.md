# wp-import-reddit

![license](https://img.shields.io/github/license/oliverfindl/wp-import-reddit.svg?style=flat)
[![paypal](https://img.shields.io/badge/donate-paypal-blue.svg?colorB=0070ba&style=flat)](https://paypal.me/oliverfindl)

Simple cron script for [WordPress][WP] CMS used for importing embeds of new posts from [Reddit][R].

> This script is proof of concept. Never was used in production.

---

## Usage

If you completed the [installation](#install) and [setup](#setup) process, you have cron set up and running. Every period of time set in crontab, you should get new posts on your blog if there were any on reddit.

## Requirements

* [PHP 7][PHP-7]
* [PHP PDO extension][PHP-PDO-EXT]
* [WordPress][WP]

## Install

```bash
# change directory to wp root
$ cd /path/to/your/wp-root

# create cron directory if not exists
$ mkdir cron

# change directory to wp cron root
$ cd cron

# clone this repo
$ git clone https://github.com/oliverfindl/wp-import-reddit.git wp-import-reddit-temp

# copy wp-import-reddit files from repo to wp cron root
$ cp wp-import-reddit-temp/src/wp-import-reddit.php .

# delete repo
$ rm -r wp-import-reddit-temp

# add reddits embed dependency library into functions.php file
$ vim ../wp-content/themes/<THEME-NAME>/functions.php
```

```php
// reddits embed dependency library
wp_enqueue_script("reddit-embed", "https://embed.redditmedia.com/widgets/platform.js", array(), null, false);
```

## Setup

```bash
# set preferred options in wp-import-reddit.php file
$ vim wp-import-reddit.php
```

## Options

```php
define("WP_LOAD_PATH", dirname(__DIR__) . "/wp-load.php"); // path to wp-load.php file
define("WP_POST_STATUS", "publish"); // post status, format: publish|draft|pending|private
define("WP_AUTHOR_ID", 1); // author id from wp, format: integer
define("WP_CATEGORY_IDS", [1]); // array of category ids from wp, format: array of integers

define("DB_FILE_PATH",  __DIR__ . "/wp-import-reddit.sqlite3"); // path to cron sqlite3 database file

define("SINCE_TIME", strtotime("-1 day")); // posts since time for import, format: timestamp
define("UNTIL_TIME", strtotime("-1 hour")); // posts until time for import, format: timestamp

define("REGEXP_URL", "/^https?:\/\/(?:www\.)?(i\.redd\.it|(?:i\.)?imgur\.com|(?:media\.)?giphy\.com|gph\.is|gfycat\.com|youtu(?:be\.com|\.be))\//"); // regular expression for filtering posts based on url of original media, format: false|regexp
```

```bash
# run script manually
/path/to/your/wp-root/cron/wp-import-reddit.php anime manga japan

# add script to crontab
$ crontab -e

0 0 * * * /path/to/your/wp-root/cron/wp-import-reddit.php anime manga japan
```

## Uninstall

```bash
# change directory to wp root
$ cd /path/to/your/wp-root

# remove reddits embed dependency library from functions.php file
$ vim wp-content/themes/<THEME-NAME>/functions.php

# change directory to wp cron root
$ cd cron

# remove wp-import-reddit files
$ rm wp-import-reddit.{php,sqlite3}

# remove cron directory if its empty
$ rm -r cron

# remove script from crontab
$ crontab -e
```

---

## License

[MIT](http://opensource.org/licenses/MIT)

[WP]: https://wordpress.org/
[R]: https://www.reddit.com/
[PHP-7]: https://secure.php.net/manual/en/install.php
[PHP-PDO-EXT]: https://secure.php.net/manual/en/book.memcached.php
