<?php
require('twitter/usertimeline.php');
require('twitter/htmlmarkup.php');


// note: create a Twitter app at https://apps.twitter.com/ for these values
$userTimeline = new Twitter\UserTimeline(
	'API key',
	'API secret',
	'Access token',
	'Access token secret',
	'twitter-screen-name'
);

$HTMLMarkup = new Twitter\HTMLMarkup();
$HTMLMarkup->setURLMaxDisplayLength(50);


$fetchCount = 0;
foreach ($userTimeline->resultList() as $resultItem) {
	print_r($resultItem);
	echo("\n" . $HTMLMarkup->execute($resultItem) . "\n\n");

	if ($fetchCount++ > 5) break;
}
