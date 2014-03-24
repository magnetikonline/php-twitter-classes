<?php
require('twitter/usertimeline.php');
require('twitter/htmlmarkup.php');


// note: create a new Twitter app at: https://apps.twitter.com/ for these values
$userTimeline = new Twitter\UserTimeline(
	'API key',
	'API secret',
	'Access token',
	'Access token secret',
	'twitter-screen-name'
);

$htmlMarkup = new Twitter\HTMLMarkup();
$htmlMarkup->setURLDisplayLength(50);


$fetchCount = 0;
foreach ($userTimeline->resultList() as $resultItem) {
	print_r($resultItem);
	echo("\n" . $htmlMarkup->execute($resultItem) . "\n\n");

	if ($fetchCount++ > 5) break;
}
