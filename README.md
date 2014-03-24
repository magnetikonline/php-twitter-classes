# PHP Twitter utility classes
PHP classes for reading a users timeline and rendering the results to valid HTML5 markup. Built against the current (as of March 2014) Twitter [REST API v1.1](https://dev.twitter.com/docs/api/1.1) which has now superseded the disabled 1.0 API.

- [Requirements](#requirements)
- [Twitter\UserTimeline()](#twitterusertimeline)
- [Twitter\HTMLMarkup()](#twitterhtmlmarkup)
- [Example](#example)

## Requirements
- PHP 5.5 (using [Generators](http://www.php.net/manual/en/language.generators.php) for working over timelines).
- [BCMath](http://www.php.net/manual/en/book.bc.php) for dealing with large tweet IDs.
- [cURL](https://php.net/curl) for HTTP calls.

## Twitter\UserTimeline()
Allows reading of a user's timeline via the REST API v1.1 [GET statuses/user_timeline](https://dev.twitter.com/docs/api/1.1/get/statuses/user_timeline) method and parsing the returned JSON tweet data into usable PHP array structures.

A basic usage example:

```php
<?php
require('twitter/usertimeline.php');
$userTimeline = new Twitter\UserTimeline(
	'API key',
	'API secret',
	'Access token',
	'Access token secret',
	'twitter-screen-name'
);

$userTimeline->setFetchBatchSize(50);
$fetchCount = 0;

foreach ($userTimeline->resultList() as $resultItem) {
	print_r($resultItem);

	/*
	Array
	(
		[ID] => 448276129489506304
		[created] => 1395712332 // unix timestamp
		[userID] => 26228734
		[userFullName] => Peter Mescalchin
		[userScreenName] => magnetikonline
		[text] => Packaged up my PHP classes on GitHub for reading Twitter user timelines via the v1.1 API and marking up to nice HTML https://t.co/97rrSunyrk
		[replyToID] => // note: false if NOT a retweet
		[retweetCreated] => // note: false if NOT a retweet
		[entityList] => Array
			(
				[0] => Array
					(
						[type] => url
						[text] => https://t.co/97rrSunyrk
						[url] => https://github.com/magnetikonline/phptwitterclasses
						[indices] => Array
							(
								[0] => 117
								[1] => 140
							)

					)

			)

	)
	*/

	if ($fetchCount++ > 5) break;
}
```

- In order to make [OAuth 1.0a](https://dev.twitter.com/docs/auth/oauth/faq) API requests to Twitter you need API keys/access tokens. These can be generated against your Twitter user account at https://apps.twitter.com/, giving you a valid `API key`, `API secret`, `Access token` and `Access token secret`.
- The `Twitter\UserTimeline()->resultList()` method has been implemented as a PHP generator to lazy load the Twitter timeline in batches (up to a current maximum of `3200` tweets). Batch fetch size can be controlled with the `Twitter\UserTimeline()->setFetchBatchSize()` method (default of 10).
- The returned generator array should be easy to understand, the only real notable being that IDs are currently returned as strings due to their magnitude, keeping 32bit PHP instances happy. There is a great amount of meta data returned per each Tweet, here I am just returning what is important to my target application.
- API v1.1 introduced the excellent concept of [entities](https://dev.twitter.com/docs/entities#tweets) for Tweets which gives a collection of external resources associated to a tweet. Types returned here will be one of:
	- `hashtag`
	- `url`
	- `user` (user mention)
	- `media` (Twitter photo upload)
- The OAuth 1.0a routines used internally have been bundled up into fairly easy to use [private methods](twitter/usertimeline.php#L164-L272) which should make it simple to extend this with additional Twitter API method calls if desired. The Twitter API documents give a great step by step breakdown to the [OAuth request generation process](https://dev.twitter.com/docs/auth/authorizing-request) used here.

## Twitter\HTMLMarkup()
Takes a data structure emitted by `Twitter\UserTimeline()->resultList()` above and generates a valid HTML5 representation of the tweet.

Simple example:

```php
<?php
require('twitter/htmlmarkup.php');
$resultItem = $userTimeline->resultList()->current();
$htmlMarkup = new Twitter\HTMLMarkup();
$htmlMarkup->setURLDisplayLength(50);

echo($htmlMarkup->execute($resultItem));
```

... will produce (wrapped for readability):

```html
<p>
	Packaged up my PHP classes on GitHub for reading Twitter user timelines via the v1.1 API
	and marking up to nice HTML
	<a class="url"
		href="https://github.com/magnetikonline/phptwitterclasses"
		title="https://github.com/magnetikonline/phptwitterclasses">
			github.com/magnetikonline/phptwitt...
	</a>
</p>
```

- `Twitter\HTMLMarkup->execute()` uses the returned tweet entities to generate anchor tags within the content. Each anchor will have a class assigned of `hashtag`, `url`, `user` and `media`.
- Anchor types of `hashtag` and `user` will have their `#` and `@` symbols wrapped in `<span>` elements to allow easy removal of anchor hover underlines for these characters via CSS.
- Displayed truncation of long URLs can be controlled with the `Twitter\HTMLMarkup->setURLDisplayLength()` method (default of 20 characters). Set to `false` to disable.

## Example
See the provided [example.php](example.php) for a demo of fetching a total of five tweets, emitting the PHP array structure of each and then marking up the result as HTML5.
