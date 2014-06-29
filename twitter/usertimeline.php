<?php
namespace Twitter;


class UserTimeline {

	const HTTP_CODE_OK = 200;
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

	public function resultList($sinceTweetID = false) {

		$maxTweetID = ''; // as string since Tweet IDs are 64bit integers

		while ($maxTweetID !== false) {
			$GETList = [
				'count' => $this->fetchBatchSize,
				'screen_name' => $this->screenName
			];

			// only get tweets AFTER a given Tweet ID?
			if ($sinceTweetID !== false) $GETList['since_id'] = $sinceTweetID;

			// if a subsequent tweet fetch API call, only get tweets older than the last one digested
			if ($maxTweetID) $GETList['max_id'] = bcsub($maxTweetID,'1');

			// make request
			list($responseHTTPCode,$responseBody) = $this->execOAuthRequest(
				self::USER_TIMELINE_API_HTTP_METHOD,
				self::USER_TIMELINE_API_URL,
				$GETList
			);

			if ($responseHTTPCode != self::HTTP_CODE_OK) {
				// response error
				throw new \Exception('Twitter fetch user timeline error');
			}

			// yield results
			$maxTweetID = false;
			foreach (json_decode($responseBody,true) as $tweetData) {
				yield $this->resultListParseTweet($tweetData);

				// save current tweet ID for next API fetch block
				$maxTweetID = $tweetData['id_str'];
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
			// if no data in entity collection, skip
			if (!$entityCollection) continue;

			// parse entity based on its type
			if ($entityType == 'hashtags') {
				foreach ($entityCollection as $entityItem) {
					// add hash tag entity
					$tweetEntities[] = [
						'type' => 'hashtag',
						'text' => $entityItem['text'],
						'indices' => $entityItem['indices']
					];
				}

			} elseif ($entityType == 'urls') {
				foreach ($entityCollection as $entityItem) {
					// add url entity
					$tweetEntities[] = [
						'type' => 'url',
						'text' => $entityItem['url'],
						'url' => trim($entityItem['expanded_url']),
						'indices' => $entityItem['indices']
					];
				}

			} elseif ($entityType == 'user_mentions') {
				foreach ($entityCollection as $entityItem) {
					// add user mention
					$tweetEntities[] = [
						'type' => 'user',
						'text' => $entityItem['screen_name'],
						'userID' => $entityItem['id_str'],
						'userFullName' => trim($entityItem['name']),
						'indices' => $entityItem['indices']
					];
				}

			} elseif ($entityType == 'media') {
				foreach ($entityCollection as $entityItem) {
					// add twitter media item
					$tweetEntities[] = [
						'type' => 'media',
						'text' => $entityItem['url'],
						'url' => trim($entityItem['expanded_url']),
						'indices' => $entityItem['indices']
					];
				}
			}
		}

		// order parsed entites by their position in the tweet text
		usort($tweetEntities,function($a,$b) {

			$a = $a['indices'][0];
			$b = $b['indices'][0];

			if ($a == $b) return 0;
			return ($a < $b) ? -1 : 1;
		});

		// return parsed tweet data item:
		// - tweet ID, created unix timestamp, publishing user details
		// - tweet text, reply to ID, is retweet flag
		// - tweet entities
		$isReplyTo = ($tweetSource['in_reply_to_status_id_str'] !== null);

		return [
			'ID' => $tweetData['id_str'],
			'created' => strtotime($tweetData['created_at']),
			'userID' => $tweetSource['user']['id_str'],
			'userFullName' => $tweetSource['user']['name'],
			'userScreenName' => $tweetSource['user']['screen_name'],
			'text' => trim(preg_replace('/ +/',' ',$tweetSource['text'])),
			'replyToID' => ($isReplyTo) ? $tweetSource['in_reply_to_status_id_str'] : false,
			'replyToUserID' => ($isReplyTo) ? $tweetSource['in_reply_to_user_id_str'] : false,
			'replyToUserScreenName' => ($isReplyTo) ? $tweetSource['in_reply_to_screen_name'] : false,
			'retweetCreated' => ($isRetweet) ? strtotime($tweetSource['created_at']) : false,
			'entityList' => $tweetEntities
		];
	}

	private function execOAuthRequest($HTTPMethod,$URL,array $GETList = [],array $POSTList = []) {

		$getURLEncodedQuerystring = function(array $parameterList,$isPOST = false) {

			if (!$parameterList) return '';

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
				CURLOPT_URL => $URL . $getURLEncodedQuerystring($GETList)
				//CURLOPT_SSL_VERIFYPEER => false
			]
		);

		if ($POSTList) {
			// add POST data to request
			curl_setopt(
				$curlConn,CURLOPT_POSTFIELDS,
				$getURLEncodedQuerystring($POSTList,true)
			);
		}

		// make request, close curl session
		$responseBody = curl_exec($curlConn);
		$responseHTTPCode = curl_getinfo($curlConn,CURLINFO_HTTP_CODE);
		curl_close($curlConn);

		// return HTTP code and response body
		return [$responseHTTPCode,$responseBody];
	}

	private function buildOAuthHTTPAuthorizationHeader($HTTPMethod,$URL,array $GETList = [],array $POSTList = []) {

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
		$OAuthParameterList['oauth_signature'] = $this->buildOAuthHTTPAuthorizationHeaderSignature(
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

	private function buildOAuthHTTPAuthorizationHeaderSignature($HTTPMethod,$URL,array $parameterList) {

		$getRawURLEncodeList = function(array $dataList) {

			foreach ($dataList as &$dataItem) $dataItem = rawurlencode($dataItem);
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
		foreach ($parameterList as $key => $value) $parameterDataList[] = $key . '=' . $value;

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
