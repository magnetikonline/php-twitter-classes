<?php
require('twitter/usertimeline.php');
require('twitter/htmlmarkup.php');


// note: create a Twitter app at https://apps.twitter.com/ for these values
$userTimeline = new Twitter\UserTimeline(
	'API_KEY',
	'API_SECRET',
	'ACCESS_TOKEN',
	'ACCESS_TOKEN_SECRET',
	'TWITTER_SCREEN_NAME'
);

$userTimeline->setExtendedTweetMode(true);

$HTMLMarkup = new Twitter\HTMLMarkup();
$HTMLMarkup->setURLMaxDisplayLength(50);


$fetchCount = 0;
foreach ($userTimeline->resultList() as $resultItem) {
	print_r($resultItem);
	echo("\n" . $HTMLMarkup->execute($resultItem) . "\n\n");

	if ($fetchCount++ > 5) {
		break;
	}
}
