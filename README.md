# PHP Twitter utility classes

PHP classes for reading a user's timeline and rendering the results to valid HTML5 markup. Built against the current (as of March 2014) Twitter [REST API v1.1](https://developer.twitter.com/en/docs/api-reference-index) which has now superseded the [disabled v1.0 API](https://blog.twitter.com/developer/en_us/a/2013/api-v1-is-retired.html).

- [Requires](#requires)
- [Twitter\UserTimeline()](#twitterusertimeline)
- [Twitter\HTMLMarkup()](#twitterhtmlmarkup)
- [Example](#example)

## Requires

- PHP 5.5 (using [Generators](https://secure.php.net/manual/en/language.generators.php) for working over timelines).
- [cURL](https://php.net/curl) for HTTP calls.

## Twitter\UserTimeline()

Allows reading of a user's timeline via the REST API v1.1 [GET statuses/user_timeline](https://developer.twitter.com/en/docs/tweets/timelines/api-reference/get-statuses-user_timeline) method and parsing the returned JSON tweet data into usable PHP array structures.

A basic usage example:

```php
<?php
require('twitter/usertimeline.php');
$userTimeline = new Twitter\UserTimeline(
	'API_KEY',
	'API_SECRET',
	'ACCESS_TOKEN',
	'ACCESS_TOKEN_SECRET',
	'TWITTER_SCREEN_NAME'
);

$userTimeline->setExtendedTweetMode(true);
$userTimeline->setFetchBatchSize(50);

$fetchCount = 0;

foreach ($userTimeline->resultList() as $resultItem) {
	print_r($resultItem);

	/*
	Array
	(
		[ID] => 448276129489506304 // 64bit integer
		[created] => 1395712332 // unix timestamp
		[userID] => 26228734
		[userFullName] => Peter Mescalchin
		[userScreenName] => magnetikonline
		[text] => Packaged up my PHP classes on GitHub for reading Twitter user timelines via the v1.1 API and marking up to nice HTML https://t.co/97rrSunyrk
		[replyToID] => // note: false if NOT a reply
		[replyToUserID] => // note: false if NOT a reply
		[replyToUserScreenName] => // note: false if NOT a reply
		[retweetCreated] => // note: false if NOT a retweet
		[entityList] => Array
			(
				[0] => Array
					(
						[type] => url
						[indices] => Array
							(
								[0] => 117
								[1] => 140
							)

						[text] => https://t.co/97rrSunyrk
						[url] => https://github.com/magnetikonline/phptwitterclasses
					)

			)

	)
	*/

	if ($fetchCount++ > 5) {
		break;
	}
}
```

- In order to make [OAuth 1.0a](https://developer.twitter.com/en/docs/basics/authentication/overview/oauth) API requests to Twitter you need a set of API access tokens. These can be generated against your Twitter user account at https://apps.twitter.com/.
- The `Twitter\UserTimeline()->resultList()` method has been implemented as a PHP generator to lazy load the Twitter timeline in batches (up to a current maximum of `3200` tweets).
	- Batch fetch size can be controlled via the `Twitter\UserTimeline()->setFetchBatchSize(SIZE)` method (defaulting to 10 items).
- `Twitter\UserTimeline()->resultList()` accepts an optional `$sinceTweetID` parameter, which will tell the API to only fetch tweets *more recent* than the given tweet ID.
- By default the API will return classic 140 character tweet responses, automatically truncating longer 280 character tweets to maintain backward compatibility.
	- To return full length tweets, use the `Twitter\UserTimeline()->setExtendedTweetMode()` method.
- API v1.1 introduced the concept of [entities](https://developer.twitter.com/en/docs/tweets/data-dictionary/overview/entities-object) for Tweets which gives a collection of external resources associated to a tweet. Types returned here will be one of:
	- `hashtag`.
	- `url`.
	- `user` (user mention).
	- `media` (Twitter photo upload).
- The OAuth 1.0a routines used internally have been bundled up into a set of fairly easy to use [private functions](twitter/usertimeline.php#L201-L313) which should make it simple to extend this with further API method calls if desired. The Twitter API documentation provides a step by step breakdown to the [OAuth request generation process](https://developer.twitter.com/en/docs/basics/authentication/guides/authorizing-a-request) required.

## Twitter\HTMLMarkup()

Takes a data structure emitted by `Twitter\UserTimeline()->resultList()` above and generates a valid HTML5 representation of the tweet.

Simple example:

```php
<?php
require('twitter/htmlmarkup.php');
$resultItem = $userTimeline->resultList()->current();
$HTMLMarkup = new Twitter\HTMLMarkup();
$HTMLMarkup->setURLMaxDisplayLength(50);

echo($HTMLMarkup->execute($resultItem));
```

... will produce (wrapped for readability):

```html
<p>
	Packaged up my PHP classes on GitHub for reading Twitter user timelines via the v1.1 API
	and marking up to nice HTML
	<a class="url"
		href="https://github.com/magnetikonline/phptwitterclasses"
		title="https://github.com/magnetikonline/phptwitterclasses">
			github.com/magnetikonline/phptwitt…
	</a>
</p>
```

- `Twitter\HTMLMarkup->execute()` uses the returned tweet entities to generate anchor tags within the content. Each anchor will have a class assigned of `hashtag`, `url`, `user` and `media`.
- Anchor types of `hashtag` and `user` will have their respective text wrapped in `<span class="text">` elements to allow removal of anchor hover underlines for the preceding `@` and `#` characters via CSS to match default Twitter styling. Example:
	- `<a class="hashtag" href="twitter.com/search">#<span class="text">hashtag</span></a>`
	- `<a class="user" href="twitter.com/username">@<span class="text">username</span></a>`
- Displayed truncation of long URLs can be controlled with the `Twitter\HTMLMarkup->setURLMaxDisplayLength()` method (default of 20 characters). Set `false` to disable truncation.

## Example

See the provided [example.php](example.php) for a demo of fetching a total of five tweets, emitting the PHP array structure of each and marking up the result as HTML5.
