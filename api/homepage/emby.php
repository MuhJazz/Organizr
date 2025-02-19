<?php

trait EmbyHomepageItem
{
	public function embySettingsArray($infoOnly = false)
	{
		$homepageInformation = [
			'name' => 'Emby',
			'enabled' => strpos('personal', $this->config['license']) !== false,
			'image' => 'plugins/images/tabs/emby.png',
			'category' => 'Media Server',
			'settingsArray' => __FUNCTION__
		];
		if ($infoOnly) {
			return $homepageInformation;
		}
		$homepageSettings = [
			'debug' => true,
			'settings' => [
				'Enable' => [
					$this->settingsOption('enable', 'homepageEmbyEnabled'),
					$this->settingsOption('auth', 'homepageEmbyAuth'),
				],
				'Connection' => [
					$this->settingsOption('url', 'embyURL'),
					$this->settingsOption('token', 'embyToken'),
					$this->settingsOption('disable-cert-check', 'embyDisableCertCheck'),
					$this->settingsOption('use-custom-certificate', 'embyUseCustomCertificate'),
				],
				'Active Streams' => [
					$this->settingsOption('enable', 'homepageEmbyStreams'),
					$this->settingsOption('auth', 'homepageEmbyStreamsAuth'),
					$this->settingsOption('switch', 'homepageShowStreamNames', ['label' => 'User Information']),
					$this->settingsOption('auth', 'homepageShowStreamNamesAuth'),
					$this->settingsOption('refresh', 'homepageStreamRefresh'),
				],
				'Recent Items' => [
					$this->settingsOption('enable', 'homepageEmbyRecent'),
					$this->settingsOption('auth', 'homepageEmbyRecentAuth'),
					$this->settingsOption('limit', 'homepageRecentLimit'),
					$this->settingsOption('refresh', 'homepageRecentRefresh'),
				],
				'Misc Options' => [
					$this->settingsOption('input', 'homepageEmbyLink', ['label' => 'Emby Homepage Link URL', 'help' => 'Available variables: {id} {serverId}']),
					$this->settingsOption('input', 'embyTabName', ['label' => 'Emby Tab Name', 'placeholder' => 'Only use if you have Emby in a reverse proxy']),
					$this->settingsOption('input', 'embyTabURL', ['label' => 'Emby Tab WAN URL', 'placeholder' => 'Only use if you have Emby in a reverse proxy']),
					$this->settingsOption('image-cache-quality', 'cacheImageSize'),
				],
				'Test Connection' => [
					$this->settingsOption('blank', null, ['label' => 'Please Save before Testing']),
					$this->settingsOption('test', 'emby'),
				]
			]
		];
		return array_merge($homepageInformation, $homepageSettings);
	}

	public function testConnectionEmby()
	{
		if (!$this->homepageItemPermissions($this->embyHomepagePermissions('test'), true)) {
			return false;
		}
		$url = $this->qualifyURL($this->config['embyURL']);
		$url = $url . "/Users?api_key=" . $this->config['embyToken'];
		$options = $this->requestOptions($url, null, $this->config['embyDisableCertCheck'], $this->config['embyUseCustomCertificate']);
		try {
			$response = Requests::get($url, [], $options);
			if ($response->success) {
				$this->setAPIResponse('success', 'API Connection succeeded', 200);
				return true;
			} else {
				$this->setAPIResponse('error', 'Emby Connection Error', 500);
				return true;
			}
		} catch (Requests_Exception $e) {
			$this->setAPIResponse('error', $e->getMessage(), 500);
			return false;
		}
	}

	public function embyHomepagePermissions($key = null)
	{
		$permissions = [
			'test' => [
				'enabled' => [
					'homepageEmbyEnabled',
				],
				'auth' => [
					'homepageEmbyAuth',
				],
				'not_empty' => [
					'embyURL',
					'embyToken'
				]
			],
			'streams' => [
				'enabled' => [
					'homepageEmbyEnabled',
					'homepageEmbyStreams'
				],
				'auth' => [
					'homepageEmbyAuth',
					'homepageEmbyStreamsAuth'
				],
				'not_empty' => [
					'embyURL',
					'embyToken'
				]
			],
			'recent' => [
				'enabled' => [
					'homepageEmbyEnabled',
					'homepageEmbyRecent'
				],
				'auth' => [
					'homepageEmbyAuth',
					'homepageEmbyRecentAuth'
				],
				'not_empty' => [
					'embyURL',
					'embyToken'
				]
			],
			'metadata' => [
				'enabled' => [
					'homepageEmbyEnabled'
				],
				'auth' => [
					'homepageEmbyAuth'
				],
				'not_empty' => [
					'embyURL',
					'embyToken'
				]
			]
		];
		return $this->homepageCheckKeyPermissions($key, $permissions);
	}

	public function homepageOrderembynowplaying()
	{
		if ($this->homepageItemPermissions($this->embyHomepagePermissions('streams'))) {
			return '
				<div id="' . __FUNCTION__ . '">
					<div class="white-box homepage-loading-box"><h2 class="text-center" lang="en">Loading Now Playing...</h2></div>
					<script>
						// Emby Stream
						homepageStream("emby", "' . $this->config['homepageStreamRefresh'] . '");
						// End Emby Stream
					</script>
				</div>
				';
		}
	}

	public function homepageOrderembyrecent()
	{
		if ($this->homepageItemPermissions($this->embyHomepagePermissions('recent'))) {
			return '
				<div id="' . __FUNCTION__ . '">
					<div class="white-box homepage-loading-box"><h2 class="text-center" lang="en">Loading Recent...</h2></div>
					<script>
						// Emby Recent
						homepageRecent("emby", "' . $this->config['homepageRecentRefresh'] . '");
						// End Emby Recent
					</script>
				</div>
				';
		}
	}

	public function getEmbyHomepageStreams()
	{
		if (!$this->homepageItemPermissions($this->embyHomepagePermissions('streams'), true)) {
			return false;
		}
		$url = $this->qualifyURL($this->config['embyURL']);
		$url = $url . '/Sessions?api_key=' . $this->config['embyToken'] . '&Fields=Overview,People,Genres,CriticRating,Studios,Taglines';
		$options = $this->requestOptions($url, $this->config['homepageStreamRefresh'], $this->config['embyDisableCertCheck'], $this->config['embyUseCustomCertificate']);
		try {
			$response = Requests::get($url, [], $options);
			if ($response->success) {
				$items = array();
				$emby = json_decode($response->body, true);
				foreach ($emby as $child) {
					if (isset($child['NowPlayingItem']) || isset($child['Name'])) {
						$items[] = $this->resolveEmbyItem($child);
					}
				}
				$api['content'] = array_filter($items);
				$this->setAPIResponse('success', null, 200, $api);
				return $api;
			} else {
				$this->setAPIResponse('error', 'Emby Error Occurred', 500);
				return false;
			}
		} catch (Requests_Exception $e) {
			$this->writeLog('error', 'Emby Connect Function - Error: ' . $e->getMessage(), 'SYSTEM');
			$this->setAPIResponse('error', $e->getMessage(), 500);
			return false;
		}
	}

	public function getEmbyHomepageRecent()
	{
		if (!$this->homepageItemPermissions($this->embyHomepagePermissions('recent'), true)) {
			return false;
		}
		$url = $this->qualifyURL($this->config['embyURL']);
		$options = $this->requestOptions($url, $this->config['homepageRecentRefresh'], $this->config['embyDisableCertCheck'], $this->config['embyUseCustomCertificate']);
		$username = false;
		$showPlayed = false;
		$userId = 0;
		try {

			if (isset($this->user['username'])) {
				$username = strtolower($this->user['username']);
			}
			// Get A User
			$userIds = $url . "/Users?api_key=" . $this->config['embyToken'];
			$response = Requests::get($userIds, [], $options);
			if ($response->success) {
				$emby = json_decode($response->body, true);
				foreach ($emby as $value) { // Scan for admin user
					if (isset($value['Policy']) && isset($value['Policy']['IsAdministrator']) && $value['Policy']['IsAdministrator']) {
						$userId = $value['Id'];
					}
					if ($username && strtolower($value['Name']) == $username) {
						$userId = $value['Id'];
						$showPlayed = false;
						break;
					}
				}
				$url = $url . '/Users/' . $userId . '/Items/Latest?EnableImages=true&Limit=' . $this->config['homepageRecentLimit'] . '&api_key=' . $this->config['embyToken'] . ($showPlayed ? '' : '&IsPlayed=false') . '&Fields=Overview,People,Genres,CriticRating,Studios,Taglines&IncludeItemTypes=Series,Episode,MusicAlbum,Audio,Movie,Video';
			} else {
				$this->setAPIResponse('error', 'Emby Error Occurred', 500);
				return false;
			}
			$response = Requests::get($url, [], $options);
			if ($response->success) {
				$items = array();
				$emby = json_decode($response->body, true);
				foreach ($emby as $child) {
					if (isset($child['NowPlayingItem']) || isset($child['Name'])) {
						$items[] = $this->resolveEmbyItem($child);
					}
				}
				$api['content'] = array_filter($items);
				$this->setAPIResponse('success', null, 200, $api);
				return $api;
			} else {
				$this->setAPIResponse('error', 'Emby Error Occurred', 500);
				return false;
			}
		} catch (Requests_Exception $e) {
			$this->writeLog('error', 'Emby Connect Function - Error: ' . $e->getMessage(), 'SYSTEM');
			$this->setAPIResponse('error', $e->getMessage(), 500);
			return false;
		}
	}

	public function getEmbyHomepageMetadata($array)
	{
		if (!$this->homepageItemPermissions($this->embyHomepagePermissions('metadata'), true)) {
			return false;
		}
		$key = $array['key'] ?? null;
		if (!$key) {
			$this->setAPIResponse('error', 'Emby Metadata key is not defined', 422);
			return false;
		}
		$url = $this->qualifyURL($this->config['embyURL']);
		$options = $this->requestOptions($url, 60, $this->config['embyDisableCertCheck'], $this->config['embyUseCustomCertificate']);
		$username = false;
		$showPlayed = false;
		$userId = 0;
		try {
			if (isset($this->user['username'])) {
				$username = strtolower($this->user['username']);
			}
			// Get A User
			$userIds = $url . "/Users?api_key=" . $this->config['embyToken'];
			$response = Requests::get($userIds, [], $options);
			if ($response->success) {
				$emby = json_decode($response->body, true);
				foreach ($emby as $value) { // Scan for admin user
					if (isset($value['Policy']) && isset($value['Policy']['IsAdministrator']) && $value['Policy']['IsAdministrator']) {
						$userId = $value['Id'];
					}
					if ($username && strtolower($value['Name']) == $username) {
						$userId = $value['Id'];
						$showPlayed = false;
						break;
					}
				}
				$url = $url . '/Users/' . $userId . '/Items/' . $key . '?EnableImages=true&Limit=' . $this->config['homepageRecentLimit'] . '&api_key=' . $this->config['embyToken'] . ($showPlayed ? '' : '&IsPlayed=false') . '&Fields=Overview,People,Genres,CriticRating,Studios,Taglines';
			} else {
				$this->setAPIResponse('error', 'Emby Error Occurred', 500);
				return false;
			}
			$response = Requests::get($url, [], $options);
			if ($response->success) {
				$items = array();
				$emby = json_decode($response->body, true);
				if (isset($emby['NowPlayingItem']) || isset($emby['Name'])) {
					$items[] = $this->resolveEmbyItem($emby);
				}
				$api['content'] = array_filter($items);
				$this->setAPIResponse('success', null, 200, $api);
				return $api;
			} else {
				$this->setAPIResponse('error', 'Emby Error Occurred', 500);
				return false;
			}
		} catch (Requests_Exception $e) {
			$this->writeLog('error', 'Emby Connect Function - Error: ' . $e->getMessage(), 'SYSTEM');
			$this->setAPIResponse('error', $e->getMessage(), 500);
			return false;
		}
	}

	public function resolveEmbyItem($itemDetails)
	{
		$item = isset($itemDetails['NowPlayingItem']['Id']) ? $itemDetails['NowPlayingItem'] : $itemDetails;
		// Static Height & Width
		$height = $this->getCacheImageSize('h');
		$width = $this->getCacheImageSize('w');
		$nowPlayingHeight = $this->getCacheImageSize('nph');
		$nowPlayingWidth = $this->getCacheImageSize('npw');
		$actorHeight = 450;
		$actorWidth = 300;
		// Cache Directories
		$cacheDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
		$cacheDirectoryWeb = 'data/cache/';
		// Types
		//$embyItem['array-item'] = $item;
		//$embyItem['array-itemdetails'] = $itemDetails;
		switch (@$item['Type']) {
			case 'Series':
				$embyItem['type'] = 'tv';
				$embyItem['title'] = $item['Name'];
				$embyItem['secondaryTitle'] = '';
				$embyItem['summary'] = '';
				$embyItem['ratingKey'] = $item['Id'];
				$embyItem['thumb'] = $item['Id'];
				$embyItem['key'] = $item['Id'] . "-list";
				$embyItem['nowPlayingThumb'] = $item['Id'];
				$embyItem['nowPlayingKey'] = $item['Id'] . "-np";
				$embyItem['metadataKey'] = $item['Id'];
				$embyItem['nowPlayingImageType'] = isset($item['ImageTags']['Thumb']) ? 'Thumb' : (isset($item['BackdropImageTags'][0]) ? 'Backdrop' : '');
				break;
			case 'Episode':
				$embyItem['type'] = 'tv';
				$embyItem['title'] = $item['SeriesName'];
				$embyItem['secondaryTitle'] = '';
				$embyItem['summary'] = '';
				$embyItem['ratingKey'] = $item['Id'];
				$embyItem['thumb'] = (isset($item['SeriesId']) ? $item['SeriesId'] : $item['Id']);
				$embyItem['key'] = (isset($item['SeriesId']) ? $item['SeriesId'] : $item['Id']) . "-list";
				$embyItem['nowPlayingThumb'] = isset($item['ParentThumbItemId']) ? $item['ParentThumbItemId'] : (isset($item['ParentBackdropItemId']) ? $item['ParentBackdropItemId'] : false);
				$embyItem['nowPlayingKey'] = isset($item['ParentThumbItemId']) ? $item['ParentThumbItemId'] . '-np' : (isset($item['ParentBackdropItemId']) ? $item['ParentBackdropItemId'] . '-np' : false);
				$embyItem['metadataKey'] = $item['Id'];
				$embyItem['nowPlayingImageType'] = isset($item['ImageTags']['Thumb']) ? 'Thumb' : (isset($item['ParentBackdropImageTags'][0]) ? 'Backdrop' : '');
				$embyItem['nowPlayingTitle'] = @$item['SeriesName'] . ' - ' . @$item['Name'];
				$embyItem['nowPlayingBottom'] = 'S' . @$item['ParentIndexNumber'] . ' · E' . @$item['IndexNumber'];
				break;
			case 'MusicAlbum':
			case 'Audio':
				$embyItem['type'] = 'music';
				$embyItem['title'] = $item['Name'];
				$embyItem['secondaryTitle'] = '';
				$embyItem['summary'] = '';
				$embyItem['ratingKey'] = $item['Id'];
				$embyItem['thumb'] = $item['Id'];
				$embyItem['key'] = $item['Id'] . "-list";
				$embyItem['nowPlayingThumb'] = (isset($item['AlbumId']) ? $item['AlbumId'] : @$item['ParentBackdropItemId']);
				$embyItem['nowPlayingKey'] = $item['Id'] . "-np";
				$embyItem['metadataKey'] = isset($item['AlbumId']) ? $item['AlbumId'] : $item['Id'];
				$embyItem['nowPlayingImageType'] = (isset($item['ParentBackdropItemId']) ? "Primary" : "Backdrop");
				$embyItem['nowPlayingTitle'] = @$item['AlbumArtist'] . ' - ' . @$item['Name'];
				$embyItem['nowPlayingBottom'] = @$item['Album'];
				break;
			case 'Movie':
				$embyItem['type'] = 'movie';
				$embyItem['title'] = $item['Name'];
				$embyItem['secondaryTitle'] = '';
				$embyItem['summary'] = '';
				$embyItem['ratingKey'] = $item['Id'];
				$embyItem['thumb'] = $item['Id'];
				$embyItem['key'] = $item['Id'] . "-list";
				$embyItem['nowPlayingThumb'] = $item['Id'];
				$embyItem['nowPlayingKey'] = $item['Id'] . "-np";
				$embyItem['metadataKey'] = $item['Id'];
				$embyItem['nowPlayingImageType'] = isset($item['ImageTags']['Thumb']) ? "Thumb" : (isset($item['BackdropImageTags']) ? "Backdrop" : false);
				$embyItem['nowPlayingTitle'] = @$item['Name'];
				$embyItem['nowPlayingBottom'] = @$item['ProductionYear'];
				break;
			case 'Video':
				$embyItem['type'] = 'video';
				$embyItem['title'] = $item['Name'];
				$embyItem['secondaryTitle'] = '';
				$embyItem['summary'] = '';
				$embyItem['ratingKey'] = $item['Id'];
				$embyItem['thumb'] = $item['Id'];
				$embyItem['key'] = $item['Id'] . "-list";
				$embyItem['nowPlayingThumb'] = $item['Id'];
				$embyItem['nowPlayingKey'] = $item['Id'] . "-np";
				$embyItem['metadataKey'] = $item['Id'];
				$embyItem['nowPlayingImageType'] = isset($item['ImageTags']['Thumb']) ? "Thumb" : (isset($item['BackdropImageTags']) ? "Backdrop" : false);
				$embyItem['nowPlayingTitle'] = @$item['Name'];
				$embyItem['nowPlayingBottom'] = @$item['ProductionYear'];
				break;
			default:
				return false;
		}
		$embyItem['uid'] = $item['Id'];
		$embyItem['imageType'] = (isset($item['ImageTags']['Primary']) ? "Primary" : false);
		$embyItem['elapsed'] = isset($itemDetails['PlayState']['PositionTicks']) && $itemDetails['PlayState']['PositionTicks'] !== '0' ? (int)$itemDetails['PlayState']['PositionTicks'] : null;
		$embyItem['duration'] = isset($itemDetails['NowPlayingItem']['RunTimeTicks']) ? (int)$itemDetails['NowPlayingItem']['RunTimeTicks'] : (int)(isset($item['RunTimeTicks']) ? $item['RunTimeTicks'] : '');
		$embyItem['watched'] = ($embyItem['elapsed'] && $embyItem['duration'] ? floor(($embyItem['elapsed'] / $embyItem['duration']) * 100) : 0);
		$embyItem['transcoded'] = isset($itemDetails['TranscodingInfo']['CompletionPercentage']) ? floor((int)$itemDetails['TranscodingInfo']['CompletionPercentage']) : 100;
		$embyItem['stream'] = @$itemDetails['PlayState']['PlayMethod'];
		$embyItem['id'] = $item['ServerId'];
		$embyItem['session'] = @$itemDetails['DeviceId'];
		$embyItem['bandwidth'] = isset($itemDetails['TranscodingInfo']['Bitrate']) ? $itemDetails['TranscodingInfo']['Bitrate'] / 1000 : '';
		$embyItem['bandwidthType'] = 'wan';
		$embyItem['sessionType'] = (@$itemDetails['PlayState']['PlayMethod'] == 'Transcode') ? 'Transcoding' : 'Direct Playing';
		$embyItem['state'] = ((@(string)$itemDetails['PlayState']['IsPaused'] == '1') ? "pause" : "play");
		$embyItem['user'] = ($this->config['homepageShowStreamNames'] && $this->qualifyRequest($this->config['homepageShowStreamNamesAuth'])) ? @(string)$itemDetails['UserName'] : "";
		$embyItem['userThumb'] = '';
		$embyItem['userAddress'] = (isset($itemDetails['RemoteEndPoint']) ? $itemDetails['RemoteEndPoint'] : "x.x.x.x");
		$embyVariablesForLink = [
			'{id}' => $embyItem['uid'],
			'{serverId}' => $embyItem['id']
		];
		$embyItem['address'] = $this->userDefinedIdReplacementLink($this->config['homepageEmbyLink'], $embyVariablesForLink);
		$embyItem['nowPlayingOriginalImage'] = 'api/v2/homepage/image?source=emby&type=' . $embyItem['nowPlayingImageType'] . '&img=' . $embyItem['nowPlayingThumb'] . '&height=' . $nowPlayingHeight . '&width=' . $nowPlayingWidth . '&key=' . $embyItem['nowPlayingKey'] . '$' . $this->randString();
		$embyItem['originalImage'] = 'api/v2/homepage/image?source=emby&type=' . $embyItem['imageType'] . '&img=' . $embyItem['thumb'] . '&height=' . $height . '&width=' . $width . '&key=' . $embyItem['key'] . '$' . $this->randString();
		$embyItem['openTab'] = $this->config['embyTabURL'] && $this->config['embyTabName'] ? true : false;
		$embyItem['tabName'] = $this->config['embyTabName'] ? $this->config['embyTabName'] : '';
		// Stream info
		$embyItem['userStream'] = array(
			'platform' => @(string)$itemDetails['Client'],
			'product' => @(string)$itemDetails['Client'],
			'device' => @(string)$itemDetails['DeviceName'],
			'stream' => @$itemDetails['PlayState']['PlayMethod'],
			'videoResolution' => isset($itemDetails['NowPlayingItem']['MediaStreams'][0]['Width']) ? $itemDetails['NowPlayingItem']['MediaStreams'][0]['Width'] : '',
			'throttled' => false,
			'sourceVideoCodec' => isset($itemDetails['NowPlayingItem']['MediaStreams'][0]) ? $itemDetails['NowPlayingItem']['MediaStreams'][0]['Codec'] : '',
			'videoCodec' => @$itemDetails['TranscodingInfo']['VideoCodec'],
			'audioCodec' => @$itemDetails['TranscodingInfo']['AudioCodec'],
			'sourceAudioCodec' => isset($itemDetails['NowPlayingItem']['MediaStreams'][1]) ? $itemDetails['NowPlayingItem']['MediaStreams'][1]['Codec'] : (isset($itemDetails['NowPlayingItem']['MediaStreams'][0]) ? $itemDetails['NowPlayingItem']['MediaStreams'][0]['Codec'] : ''),
			'videoDecision' => $this->streamType(@$itemDetails['PlayState']['PlayMethod']),
			'audioDecision' => $this->streamType(@$itemDetails['PlayState']['PlayMethod']),
			'container' => isset($itemDetails['NowPlayingItem']['Container']) ? $itemDetails['NowPlayingItem']['Container'] : '',
			'audioChannels' => @$itemDetails['TranscodingInfo']['AudioChannels']
		);
		// Genre catch all
		if (isset($item['Genres'])) {
			$genres = array();
			foreach ($item['Genres'] as $genre) {
				$genres[] = $genre;
			}
		}
		// Actor catch all
		if (isset($item['People'])) {
			$actors = array();
			foreach ($item['People'] as $key => $value) {
				if (@$value['PrimaryImageTag'] && @$value['Role']) {
					if (file_exists($cacheDirectory . (string)$value['Id'] . '-cast.jpg')) {
						$actorImage = $cacheDirectoryWeb . (string)$value['Id'] . '-cast.jpg';
					}
					if (file_exists($cacheDirectory . (string)$value['Id'] . '-cast.jpg') && (time() - 604800) > filemtime($cacheDirectory . (string)$value['Id'] . '-cast.jpg') || !file_exists($cacheDirectory . (string)$value['Id'] . '-cast.jpg')) {
						$actorImage = 'api/v2/homepage/image?source=emby&type=Primary&img=' . (string)$value['Id'] . '&height=' . $actorHeight . '&width=' . $actorWidth . '&key=' . (string)$value['Id'] . '-cast';
					}
					$actors[] = array(
						'name' => (string)$value['Name'],
						'role' => (string)$value['Role'],
						'thumb' => $actorImage
					);
				}
			}
		}
		// Metadata information
		$embyItem['metadata'] = array(
			'guid' => $item['Id'],
			'summary' => @(string)$item['Overview'],
			'rating' => @(string)$item['CommunityRating'],
			'duration' => @(string)$item['RunTimeTicks'],
			'originallyAvailableAt' => @(string)$item['PremiereDate'],
			'year' => (string)isset($item['ProductionYear']) ? $item['ProductionYear'] : '',
			//'studio' => (string)$item['studio'],
			'tagline' => @(string)$item['Taglines'][0],
			'genres' => (isset($item['Genres'])) ? $genres : '',
			'actors' => (isset($item['People'])) ? $actors : ''
		);
		if (file_exists($cacheDirectory . $embyItem['nowPlayingKey'] . '.jpg')) {
			$embyItem['nowPlayingImageURL'] = $cacheDirectoryWeb . $embyItem['nowPlayingKey'] . '.jpg';
		}
		if (file_exists($cacheDirectory . $embyItem['key'] . '.jpg')) {
			$embyItem['imageURL'] = $cacheDirectoryWeb . $embyItem['key'] . '.jpg';
		}
		if (file_exists($cacheDirectory . $embyItem['nowPlayingKey'] . '.jpg') && (time() - 604800) > filemtime($cacheDirectory . $embyItem['nowPlayingKey'] . '.jpg') || !file_exists($cacheDirectory . $embyItem['nowPlayingKey'] . '.jpg')) {
			$embyItem['nowPlayingImageURL'] = 'api/v2/homepage/image?source=emby&type=' . $embyItem['nowPlayingImageType'] . '&img=' . $embyItem['nowPlayingThumb'] . '&height=' . $nowPlayingHeight . '&width=' . $nowPlayingWidth . '&key=' . $embyItem['nowPlayingKey'] . '';
		}
		if (file_exists($cacheDirectory . $embyItem['key'] . '.jpg') && (time() - 604800) > filemtime($cacheDirectory . $embyItem['key'] . '.jpg') || !file_exists($cacheDirectory . $embyItem['key'] . '.jpg')) {
			$embyItem['imageURL'] = 'api/v2/homepage/image?source=emby&type=' . $embyItem['imageType'] . '&img=' . $embyItem['thumb'] . '&height=' . $height . '&width=' . $width . '&key=' . $embyItem['key'] . '';
		}
		if (!$embyItem['nowPlayingThumb']) {
			$embyItem['nowPlayingOriginalImage'] = $embyItem['nowPlayingImageURL'] = "plugins/images/homepage/no-np.png";
			$embyItem['nowPlayingKey'] = "no-np";
		}
		if (!$embyItem['thumb']) {
			$embyItem['originalImage'] = $embyItem['imageURL'] = "plugins/images/homepage/no-list.png";
			$embyItem['key'] = "no-list";
		}
		if (isset($useImage)) {
			$embyItem['useImage'] = $useImage;
		}
		return $embyItem;
	}

}