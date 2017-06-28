<?php

function getCurl( $reqUrl ) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL,$reqUrl);
	$result=curl_exec($ch);
	return $result;
}

function parseWebPath( $filePath, $siteURL ) {
	if ((substr($filePath, 0, 7) !== 'http://') && (substr($filePath, 0, 8) !== 'https://') ) {
		if ( substr($filePath, 0, 1) === '/' ) {
			return $siteURL . substr($filePath,1);
		} else {
			return $siteURL . $filePath;
		}
	}
	return $filePath;
}

function getSitePages( $params ) {  
	$pageHTML = $params['pageHTML'];
	$siteURL = $params['siteURL'];
	$anchors = array();
	if ( isset($params['anchors']) ) {
		$anchors = $params['anchors'];
	}
	$sitePages = array();
	$matches = array();

	$dom = new DomDocument();
	libxml_use_internal_errors(true);
	$dom->loadHTML($pageHTML);
	
	if ( substr($siteURL,-1) !== "/" ) {
		$siteURL = $siteURL . "/";
	}
		
	if ( count($anchors) === 0 ) {
		$sitePages[] = array(
			'name' => 'Home',
			'url' => 'Home',
			'href' => $siteURL,
			'html' => getCurl($siteURL),
			'favicons' => array(
				'favicon' => array('regex' => '/<link(?:.*)rel="(?:shortcut )?icon"(?:.*)href="(.*)"(?:.*)\>/', 'valid' => false),
				'57x57' => array('regex' => '/<link(?:.*)rel="apple-touch-icon"(?:.*)sizes="57x57"(?:.*)href="(.*)"(?:.*)\>/', 'valid' => false),
				'72x72' => array('regex' => '/<link(?:.*)rel="apple-touch-icon"(?:.*)sizes="72x72"(?:.*)href="(.*)"(?:.*)\>/', 'valid' => false),
				'114x114' => array('regex' => '/<link(?:.*)rel="apple-touch-icon"(?:.*)sizes="114x114"(?:.*)href="(.*)"(?:.*)\>/', 'valid' => false),
				'144x144' => array('regex' => '/<link(?:.*)rel="apple-touch-icon"(?:.*)sizes="144x144"(?:.*)href="(.*)"(?:.*)\>/', 'valid' => false)
			)
		);
		$anchors[$siteURL] = true;
		foreach ( $sitePages[0]['favicons'] as $key=>$val ) {
			if ( preg_match($val['regex'], $pageHTML, $matches) ) {
				if ( count($matches) > 1 && $matches[1] !== "" ) {
					$sitePages[0]['favicons'][$key] = parseWebPath( $matches[1], $siteURL );
				}
			}
		}
	}
	
	// Search anchor tags to find more sub-pages...
	foreach( $dom->getElementsByTagName('a') as $item ) {
		$href = $item->getAttribute('href');
		
		if ( (substr(strtolower($href), -4) == '.jpg') || (substr(strtolower($href), -4) == '.png') || (substr(strtolower($href), -5) == '.jpeg') ) {
			$href = false;
		} else if ((substr($href, 0, 7) == 'http://') || (substr($href, 0, 8) == 'https://') || (substr($href, 0, 3) == 'www') || (substr($href, 0, 3) == 'tel')  || (substr($href, 0, 1) == '#') || (substr($href, 0, 6) == 'mailto') ) {
			// determine if external link or just an absolute site path
			if ( strpos($href,$siteURL) === false ) {
				$href = false;
			}
		} else {
			if ( substr($href, 0, 1) === '/' && strlen($href) > 1 ) {
				$href = $siteURL . substr($href, 1);
			} else if ( substr($href, 0, 1) !== '/' && strlen($href) > 1 ) {
				$href = $siteURL . $href;
			} else {
				$href = false;
			}
		}

		// Clean the href if it contains ".php"
		$href = str_replace('.php','',$href);
		
		if ( $href && empty($anchors[$href]) && strpos($href, '#') === false) {
			
			$removedSlash = false;
			
			$anchors[$href] = true;
			
			// Strip trailing slash
			if ( substr($href, -1) === "/" ) {
				$href = substr($href,0,strlen($href)-1);
				$removedSlash = true;
			}
			
			$pageName = explode('/',$href);
			if ( count($pageName) > 1 ) {
				$pageName = $pageName[ count($pageName) - 1 ];
			} else {
				$pageName = $href;
			}
			
			// If we removed the trailing slash earlier, put it back
			if ( $removedSlash ) {
				$href .= "/";
			}
			
			if ( $pageName !== "" && $pageName !== 'index' && $pageName !== 'index.html' ) {
				$sitePages[] = array(
					'name' => str_replace(array('-','.html'),' ',$pageName),
					'url' => $pageName,
					'href' => $href,
					'html' => getCurl($href)
				);
			}
		}
	}
	return $sitePages;
}

function checkPage( $page ) {
	$pageHTML = preg_replace( "/\r|\n/", "", $page['html']);
	
	$results = array(
		'Title Tag' => array('value' => 'not valid', 'valid' => false, 'regex' => '/title[.+]*>(.+)<\/title>/', 'type'=>'error'),
		'Description Tag' => array('value' => 'not valid', 'valid' => false, 'regex' => '/meta\s+(?:name="description"(?:[\w\s\d])+content="(([^"])*)")|(?:\s+content="(([^"])*)"(?:[\w\s\d])+name="description")/', 'type'=>'warning'),
		'Canonical Tag' => array('value' => 'not valid', 'valid' => false, 'regex' => '/link\s+(?:rel="canonical"\s+href="(([^"])*)")|(?:\s+href="(([^"])*)"\s+rel="canonical")/', 'type'=>'error')
	);
		
	// <IMG> alt tags
	if ( preg_match_all('/(<img (?!.*?alt=([\'"]).*?\2)[^>]*)(>)/', strtolower($pageHTML), $matches) ) {
		$results['Images have alt'] = array('value' => (count($matches[0]).' missing'), 'valid' => false, 'type'=>'error');
	} else {
		$results['Images have alt'] = array('value' => 'All images OK', 'valid' => true, 'type'=>'error');
	}
		
	// Tracking
	$results['Google Tag Manager'] = array('value' => 'not valid', 'valid' => false, 'regex' => '/<iframe src="\/\/www\.googletagmanager\.com\/ns\.html\?id=(([^"])*)"/', 'type'=>'error', 'type'=>'error');
	
	$matches = array();
	foreach ( $results as $key => $val ) {
		if ( isset($val['regex']) && preg_match($val['regex'], $pageHTML, $matches)) {
			if ( count($matches) > 1 && $matches[1] !== "" ) {
				$results[$key]['value'] = $matches[1];
				$results[$key]['valid'] = true;
			} else if ( count($matches) === 1 && $matches[0] !== "" ) {
				$results[$key]['value'] = $matches[0];
				$results[$key]['valid'] = true;
			}
		}
	}
	
	// Special checking - Canonical Tag
	if ( $results['Canonical Tag']['valid'] && strpos($results['Canonical Tag']['value'], $page['url']) === false && $page['name'] !== 'Home' ) {
		// invalid Canonical Tag
		$results['Canonical Tag']['valid'] = false;
	}
	if ( $results['Canonical Tag']['valid'] === false && preg_match('/content\=\"noindex\,nofollow\"/', strtolower($pageHTML), $matches) ) {
		unset($results['Canonical Tag']);
	}
	if ( $results['Canonical Tag']['valid'] && strpos($results['Canonical Tag']['value'],'http') === false ) {
		$results['Canonical Tag']['valid'] = false;
		$results['Canonical Tag']['value'] = "Must be absolute URL:<br>" . $results['Canonical Tag']['value'];
	}
	
	// Special checking for variable tags
	if ( $results['Google Tag Manager']['valid'] === false && preg_match('/\'https:\/\/www\.googletagmanager\.com\/gtm\.js\?id/', $pageHTML, $matches) ) {
		$results['Google Tag Manager']['valid'] = true;
		$results['Google Tag Manager']['value'] = 'Included.';
	}

	return $results;
}



?>