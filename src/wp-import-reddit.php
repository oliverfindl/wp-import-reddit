#!/usr/bin/php

<?php
	/**
	 * wp-import-reddit v1.0.0 (2019-02-10)
	 * Copyright 2019 Oliver Findl
	 * @license MIT
	 */

	define("WP_LOAD_PATH", dirname(__DIR__) . "/wp-load.php"); // path to wp-load.php file
	define("WP_POST_STATUS", "publish"); // post status, format: publish|draft|pending|private
	define("WP_AUTHOR_ID", 1); // author id from wp, format: integer
	define("WP_CATEGORY_IDS", [1]); // array of category ids from wp, format: array of integers

	define("DB_FILE_PATH",  __DIR__ . "/wp-import-reddit.sqlite3"); // path to cron sqlite3 database file

	define("SINCE_TIME", strtotime("-1 day")); // posts since time for import, format: timestamp
	define("UNTIL_TIME", strtotime("-1 hour")); // posts until time for import, format: timestamp

	define("REGEXP_URL", "/^https?:\/\/(?:www\.)?(i\.redd\.it|(?:i\.)?imgur\.com|(?:media\.)?giphy\.com|gph\.is|gfycat\.com|youtu(?:be\.com|\.be))\//"); // regular expression for filtering posts based on url of original media, format: false|regexp

	/* DO NOT MODIFY ANY CONTENT BELOW */

	define("SINCE_TIME_PARSED", parseDate(SINCE_TIME));
	define("UNTIL_TIME_PARSED", parseDate(UNTIL_TIME));

	if(strtolower(php_sapi_name()) !== "cli") {
		print("[ERROR] Script execution allowed only from CLI." . PHP_EOL);
		exit(1);
	}

	$sources = array_slice($argv, 1);

	if(empty($sources)) {
		print("[ERROR] Subreddits not defined, please define them via script arguments." . PHP_EOL);
		exit(1);
	}

	try {
		$dbh = new PDO("sqlite:" . DB_FILE_PATH, null, null, [
			PDO::ATTR_PERSISTENT => true
		]);
		$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	} catch(PDOException $e) {
		echo "Connection failed: " . $e->getMessage() . PHP_EOL;
		exit(1);
	}

	$dbh->exec("
		BEGIN;
		CREATE TABLE IF NOT EXISTS sources (
			id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
			source TEXT NOT NULL UNIQUE
		);
		CREATE TABLE IF NOT EXISTS posts (
			id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
			wp_id INTEGER NOT NULL UNIQUE,
			external_id TEXT NOT NULL UNIQUE,
			source_id INTEGER NOT NULL,
			datetime TEXT NOT NULL,
			FOREIGN KEY (source_id) REFERENCES sources(id)
		);
		COMMIT;
	");

	$sources = array_map("trim", $sources);
	$sources = array_map("strtolower", $sources);

	while(!empty($sources)) {
		$source = array_shift($sources);

		$sth = $dbh->prepare("INSERT OR IGNORE INTO sources (source) VALUES (LOWER(:source));");
		$sth->execute([
			":source" => $source
		]);

		$sth = $dbh->prepare("SELECT id FROM sources WHERE LOWER(source) = LOWER(:source) LIMIT 1;");
		$sth->execute([
			":source" => $source
		]);
		$source_id = $sth->fetch(PDO::FETCH_ASSOC);
		$source_id = $source_id["id"];

		$sth = $dbh->prepare("SELECT id, external_id FROM posts WHERE LOWER(source_id) = LOWER(:source_id);");
		$sth->execute([
			":source_id" => $source_id
		]);
		$external_ids = $sth->fetchAll(PDO::FETCH_KEY_PAIR);
		$external_ids = array_values($external_ids);

		$after = "";
		$done = false;
		while(empty($done)) {
			$url = "https://www.reddit.com/r/{$source}/new.json?limit=100&after=" . (!empty($after) ? $after : "");
			print("downloading: {$url}" . PHP_EOL);

			$data = @file_get_contents($url);
			$data = @json_decode($data, true);

			if(empty($data)) {
				print("[ERROR] Empty response from {$source}." . PHP_EOL);
				exit(1);
			}
			$after = $data["data"]["after"];

			foreach($data["data"]["children"] as $child) {
				$child["data"]["created_utc"] = parseDate($child["data"]["created_utc"]);

				if($child["data"]["created_utc"] > UNTIL_TIME_PARSED || !empty(REGEXP_URL) && !preg_match(REGEXP_URL, $child["data"]["url"])) {
					continue;
				}

				if(in_array($child["data"]["id"], $external_ids) || $child["data"]["created_utc"] < SINCE_TIME_PARSED) {
					$done = true;
					break;
				}

				foreach($child["data"] as $key => $value) {
					if(!in_array($key, ["id", "subreddit_id", "title", "permalink", "subreddit", "created_utc"])) {
						unset($child["data"][$key]);
					}
				}

				$posts[] = $child["data"];
			}

			if(empty($after)) {
				$done = true;
			}
		}
	}

	if(!empty($posts)) {
		require_once(WP_LOAD_PATH);

		usort($posts, "compareByDate");

		foreach($posts as $post) {
			$post = array_map("trim", $post);

			$post_id = wp_insert_post([
				"post_type" => "post",
				"post_title" => $post["title"],
				"post_content" => "<blockquote class=\"reddit-card\"><a target=\"_blank\" href=\"https://www.reddit.com" . $post["permalink"] . "\">" . $post["title"] . "</a> from <a target=\"_blank\" href=\"https://www.reddit.com/r/" . $post["subreddit"] . "\">" . $post["subreddit"] . "</a></blockquote>",
				"post_date" => date("Y-m-d H:i:s", $post["created_utc"]),
				"post_status" => WP_POST_STATUS,
				"post_author" => WP_AUTHOR_ID,
				"post_category" => WP_CATEGORY_IDS
			]);

			if(!empty($post_id)) {
				$sth = $dbh->prepare("INSERT INTO posts (wp_id, external_id, source_id, datetime) VALUES (:wp_id, :external_id, :source_id, datetime(\"now\"));");
				$sth->execute([
					":wp_id" => $post_id,
					":external_id" => $post["id"],
					":source_id" => $source_id
				]);

				print("inserted post: {$post_id}" . PHP_EOL);
			}
		}
	}

	$dbh->exec("VACUUM;");

	print("All done!" . PHP_EOL);
	exit(0);

	function parseDate($value = 0): int { // input can be integer or string
		return is_numeric($value) ? intval($value) : strtotime($value);
	}

	function compareByDate(array $a = [], array $b = []): int {
		return $a["created_utc"] <=> $b["created_utc"];
	}
?>
