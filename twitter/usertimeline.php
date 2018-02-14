<?php
namespace Twitter;


class UserTimeline {

	const HTTP_OK = 200;
	const OAUTH_VERSION = '1.0';
	const OAUTH_SIGNATURE_METHOD = 'HMAC-SHA1';
	const OAUTH_HMAC_ALGO = 'SHA1';

	const USER_TIMELINE_API_URL = 'https://api.twitter.com/1.1/statuses/user_timeline.json';
	const USER_TIMELINE_API_HTTP_METHOD = 'GET';

	private $APIKey;
	private $APISecret;
	private $accessToken;
	private $accessTokenSecret;
	private $screenName;

	private $fetchBatchSize = 10;
	private $extendedTweetMode = false;


	public function __construct(
		$APIKey,$APISecret,
		$accessToken,$accessTokenSecret,
		$screenName
	) {

		$this->APIKey = $APIKey;
		$this->APISecret = $APISecret;
		$this->accessToken = $accessToken;
		$this->accessTokenSecret = $accessTokenSecret;
		$this->screenName = $screenName;
	}

	public function setFetchBatchSize($size) {

		$this->fetchBatchSize = $size;
	}

	public function setExtendedTweetMode($enabled) {

		$this->extendedTweetMode = $enabled;
	}

	public function resultList($sinceTweetID = false) {

		$lastTweetID = 0;

		while ($lastTweetID !== false) {
			$GETList = [
				'count' => $this->fetchBatchSize,
				'screen_name' => $this->screenName
			];

			// after tweets in extended (more than 140chars) mode?
			if ($this->extendedTweetMode) {
				$GETList['tweet_mode'] = 'extended';
			}

			// only get tweets AFTER a given tweet ID?
			if ($sinceTweetID !== false) {
				$GETList['since_id'] = $sinceTweetID;
			}

			// if a subsequent tweet fetch API call, only get tweets older than the last one digested
			if ($lastTweetID) {
				$GETList['max_id'] = ($lastTweetID - 1);
			}

			// make request
			list($responseHTTPCode,$responseBody) = $this->execOAuthRequest(
				self::USER_TIMELINE_API_HTTP_METHOD,
				self::USER_TIMELINE_API_URL,
				$GETList
			);

			if ($responseHTTPCode != self::HTTP_OK) {
				// response error
				throw new \Exception('Twitter fetch user timeline error');
			}

			// yield results
			$lastTweetID = false;
			foreach (json_decode($responseBody,true) as $tweetData) {
				yield $this->resultListParseTweet($tweetData);

				// save current tweet ID for next API fetch block
				$lastTweetID = $tweetData['id'];
			}
		}
	}

	private function resultListParseTweet(array $tweetData) {

		// get retweet status
		$isRetweet = isset($tweetData['retweeted_status']);
		$tweetSource = ($isRetweet) ? $tweetData['retweeted_status'] : $tweetData;

		// parse tweet entities - hashtags/urls/user mentions/media (optional)
		$tweetEntities = [];
		foreach ($tweetSource['entities'] as $entityType => $entityCollection) {
			if (!$entityCollection) {
				// if no data in entity collection, skip
				continue;
			}

			// parse entity based on its type
			if ($entityType == 'hashtags') {
				foreach ($entityCollection as $entityItem) {
					// add hash tag entity
					$tweetEntities[] = [
						'type' => 'hashtag',
						'indices' => $entityItem['indices'],
						'text' => $entityItem['text']
					];
				}

			} elseif ($entityType == 'urls') {
				foreach ($entityCollection as $entityItem) {
					// add url entity
					$tweetEntities[] = [
						'type' => 'url',
						'indices' => $entityItem['indices'],
						'text' => $entityItem['url'],
						'url' => trim($entityItem['expanded_url']),
					];
				}

			} elseif ($entityType == 'user_mentions') {
				foreach ($entityCollection as $entityItem) {
					// add user mention
					$tweetEntities[] = [
						'type' => 'user',
						'indices' => $entityItem['indices'],
						'text' => $entityItem['screen_name'],
						'userID' => $entityItem['id'],
						'userFullName' => trim($entityItem['name'])
					];
				}

			} elseif ($entityType == 'media') {
				foreach ($entityCollection as $entityItem) {
					// add twitter media item
					$tweetEntities[] = [
						'type' => 'media',
						'indices' => $entityItem['indices'],
						'text' => $entityItem['url'],
						'url' => trim($entityItem['expanded_url'])
					];
				}
			}
		}

		// order entities by their position in the tweet text
		usort($tweetEntities,function($a,$b) {

			$a = $a['indices'][0];
			$b = $b['indices'][0];

			if ($a == $b) {
				return 0;
			}

			return ($a < $b) ? -1 : 1;
		});

		// return parsed tweet data item:
		// - tweet ID, created unix timestamp, publishing user details
		// - tweet text, reply to ID, is retweet flag
		// - tweet entities
		$isReplyTo = ($tweetSource['in_reply_to_status_id'] !== null);

		// fetch either default (140 chars or less), or extended text for tweet
		$tweetText = ($this->extendedTweetMode)
			? $tweetSource['full_text']
			: $tweetSource['text'];

		return [
			'ID' => $tweetData['id'],
			'created' => strtotime($tweetData['created_at']),
			'userID' => $tweetSource['user']['id'],
			'userFullName' => $tweetSource['user']['name'],
			'userScreenName' => $tweetSource['user']['screen_name'],
			'text' => trim(
				preg_replace(
					'/ +/',' ',
					htmlspecialchars_decode($tweetText)
				)
			),
			'replyToID' => ($isReplyTo) ? $tweetSource['in_reply_to_status_id'] : false,
			'replyToUserID' => ($isReplyTo) ? $tweetSource['in_reply_to_user_id'] : false,
			'replyToUserScreenName' => ($isReplyTo) ? $tweetSource['in_reply_to_screen_name'] : false,
			'retweetCreated' => ($isRetweet) ? strtotime($tweetSource['created_at']) : false,
			'entityList' => $tweetEntities
		];
	}

	private function execOAuthRequest($HTTPMethod,$URL,array $GETList = [],array $POSTList = []) {

		$getURLEncodedList = function(array $parameterList,$isPOST = false) {

			if (!$parameterList) {
				return '';
			}

			// convert $parameterList to url encoded key=value pairs
			array_walk($parameterList,function(&$value,$key) {

				$value = urlencode($key) . '=' . urlencode($value);
			});

			return (($isPOST) ? '' : '?') . implode('&',$parameterList);
		};

		$curlConn = curl_init();

		curl_setopt_array(
			$curlConn,[
				CURLOPT_HEADER => false, // no header to be returned in response
				CURLOPT_HTTPHEADER => [
					$this->buildOAuthHTTPAuthorizationHeader($HTTPMethod,$URL,$GETList,$POSTList)
				],
				CURLOPT_POST => ($POSTList) ? true : false,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_URL => $URL . $getURLEncodedList($GETList)
			]
		);

		if ($POSTList) {
			// add POST data to request
			curl_setopt(
				$curlConn,CURLOPT_POSTFIELDS,
				$getURLEncodedList($POSTList,true)
			);
		}

		// make request, close curl session
		$responseBody = curl_exec($curlConn);
		$responseHTTPCode = curl_getinfo($curlConn,CURLINFO_HTTP_CODE);
		curl_close($curlConn);

		// return HTTP code and response body
		return [$responseHTTPCode,$responseBody];
	}

	private function buildOAuthHTTPAuthorizationHeader($HTTPMethod,$URL,array $GETList,array $POSTList) {

		// build source data list in key/value pairs
		$OAuthParameterList = [
			'oauth_consumer_key' => $this->APIKey,
			'oauth_nonce' => md5(microtime() . mt_rand()),
			'oauth_signature_method' => self::OAUTH_SIGNATURE_METHOD,
			'oauth_timestamp' => time(),
			'oauth_token' => $this->accessToken,
			'oauth_version' => self::OAUTH_VERSION
		];

		// create OAuth signature
		$OAuthParameterList['oauth_signature'] = $this->buildOAuthHTTPSignature(
			$HTTPMethod,$URL,
			$OAuthParameterList + $GETList + $POSTList
		);

		// build OAuth authorization header and return
		$authItemList = [];
		foreach ($OAuthParameterList as $key => $value) {
			$authItemList[] = sprintf('%s="%s"',rawurlencode($key),rawurlencode($value));
		}

		return 'Authorization: OAuth ' . implode(', ',$authItemList);
	}

	private function buildOAuthHTTPSignature($HTTPMethod,$URL,array $parameterList) {

		$getRawURLEncodeList = function(array $dataList) {

			foreach ($dataList as &$dataItem) {
				$dataItem = rawurlencode($dataItem);
			}

			return $dataList;
		};

		// rawurlencode each key/value pair
		$parameterList = array_combine(
			$getRawURLEncodeList(array_keys($parameterList)),
			$getRawURLEncodeList(array_values($parameterList))
		);

		// alpha sort on keys and build parameter string data
		uksort($parameterList,'strcmp');

		$parameterDataList = [];
		foreach ($parameterList as $key => $value) {
			$parameterDataList[] = $key . '=' . $value;
		}

		// calculate signature and return
		return base64_encode(hash_hmac(
			// signature base
			self::OAUTH_HMAC_ALGO,
			implode('&',[
				$HTTPMethod,rawurlencode($URL),
				rawurlencode(implode('&',$parameterDataList))
			]),
			// signing key
			rawurlencode($this->APISecret) . '&' . rawurlencode($this->accessTokenSecret),
			true
		));
	}
}
