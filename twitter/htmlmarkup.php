<?php
namespace Twitter;


class HTMLMarkup {
	const TWITTER_BASE_URL = 'https://twitter.com/';
	const TWITTER_SEARCH_URL = 'https://twitter.com/search?q=';

	private $URLMaxDisplayLength = false;


	public function setURLMaxDisplayLength($length) {
		$this->URLMaxDisplayLength = $length;
	}

	public function execute(array $tweetData) {
		$tweetText = htmlspecialchars($tweetData['text']);

		// work over the entity list for the tweet and apply to text
		foreach ($tweetData['entityList'] as $entityItem) {
			$entityType = $entityItem['type'];

			if ($entityType == 'hashtag') {
				$tweetText = $this->getAppliedEntityHashtag($tweetText,$entityItem);

			} elseif ($entityType == 'url') {
				$tweetText = $this->getAppliedEntityURLMedia('url',$tweetText,$entityItem);

			} elseif ($entityType == 'user') {
				$tweetText = $this->getAppliedEntityUserMention($tweetText,$entityItem);

			} elseif ($entityType == 'media') {
				$tweetText = $this->getAppliedEntityURLMedia('media',$tweetText,$entityItem);
			}
		}

		return '<p>' . $tweetText . '</p>';
	}

	private function getAppliedEntityHashtag($text,array $entity) {
		$hashtag = '#' . $entity['text'];

		return $this->getSingleStringReplace(
			htmlspecialchars($hashtag),
			sprintf(
				'<a class="hashtag" href="%s%s">#<span class="text">%s</span></a>',
				self::TWITTER_SEARCH_URL,
				urlencode($hashtag),
				htmlspecialchars($entity['text'])
			),
			$text
		);
	}

	private function getAppliedEntityURLMedia($type,$text,array $entity) {
		// save target url, remove leading 'https?://' from display url
		$url = $entity['url'];
		$urlHTML = htmlspecialchars($url);
		$urlDisplay = preg_replace('/^https?:\/\//','',$url);
		$hasTruncate = false;

		// truncate display url length if required
		if (
			($this->URLMaxDisplayLength !== false) &&
			(strlen($urlDisplay) > $this->URLMaxDisplayLength)
		) {
			$urlDisplay = substr($urlDisplay,0,$this->URLMaxDisplayLength) . '…';
			$hasTruncate = true;
		}

		return $this->getSingleStringReplace(
			htmlspecialchars($entity['text']),
			sprintf(
				'<a class="%s" href="%s"%s>%s</a>',
				$type,$urlHTML,
				($hasTruncate) ? ' title="' . $urlHTML . '"' : '',
				htmlspecialchars($urlDisplay)
			),
			$text
		);
	}

	private function getAppliedEntityUserMention($text,array $entity) {
		$screenNameHTML = htmlspecialchars($entity['text']);

		return $this->getSingleStringReplace(
			'@' . $screenNameHTML,
			sprintf(
				'<a class="user" href="%s%s" title="%s">@<span class="text">%s</span></a>',
				self::TWITTER_BASE_URL,
				urlencode($entity['text']),
				htmlspecialchars($entity['userFullName']),
				$screenNameHTML
			),
			$text
		);
	}

	private function getSingleStringReplace($search,$replace,$text) {
		$position = strpos($text,$search);
		return ($position !== false)
			? substr_replace($text,$replace,$position,strlen($search))
			: $text; // not found
	}
}
