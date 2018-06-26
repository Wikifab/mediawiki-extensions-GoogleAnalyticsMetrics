<?php

class GoogleAnalyticsMetricsHooks {

	/**
	 * Sets up the parser function
	 *
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		$parser->setFunctionHook( 'googleanalyticsmetrics',
			'GoogleAnalyticsMetricsHooks::googleAnalyticsMetrics' );
	}

	/**
	 * Handles the googleanalyticsmetrics parser function
	 *
	 * @global string|array $wgGoogleAnalyticsMetricsAllowed
	 * @param Parser $parser Unused
	 * @param string $metric
	 * @param string $startDate
	 * @param string $endDate
	 * @return string
	 */
	public static function googleAnalyticsMetrics( Parser &$parser, $metric, $startDate = null,
		$endDate = null ) {
		global $wgGoogleAnalyticsMetricsAllowed;

		// Setting the defaults above would not allow an empty start parameter
		if ( !$startDate ) {
			// This is the earliest date Analytics accepts
			$startDate = '2005-01-01';
		}
		if ( !$endDate ) {
			$endDate = 'today';
		}
		if ( $wgGoogleAnalyticsMetricsAllowed !== '*' && !in_array( $metric,
				$wgGoogleAnalyticsMetricsAllowed ) ) {
			return self::getWrappedError( 'The requested metric is forbidden.' );
		}
		return self::getMetric( $metric, $startDate, $endDate );
	}

	public static function getMetricWithTitle( $title, $metric, $startDate, $endDate ) {

		global $wgGoogleAnalyticsMetricsViewID, $wgGoogleAnalyticsMetricsExpiry, $wgArticlePath, $wgScriptPath;
		// We store the ID in the cache, but that is not a waste, since if the ID changes that
		// data is no longer valid.
		$responseMetricIndex = 0;
		$responseMetricWiki = 0;
		if (! $title) {
			return null;
		}
		$title = $title->getDBKey();
		$path1 = $wgArticlePath ? str_replace('$1', $title, $wgArticlePath) : '/wiki/' . $title;
		$path2 = $wgScriptPath . '/index.php/' . $title;
		$request = array( 'ga:'.$wgGoogleAnalyticsMetricsViewID,
				$startDate,
				$endDate,
				'ga:' . $metric,
				array(
						'dimensions' => 'ga:pagePath',
						'filters' => 'ga:pagePath==' .$path1

				) );
		$request2 = array( 'ga:'.$wgGoogleAnalyticsMetricsViewID,
				$startDate,
				$endDate,
				'ga:' . $metric,
				array(
						'dimensions' => 'ga:pagePath',
						'filters' => 'ga:pagePath=='. $path2

				) );

		$responseMetric = GoogleAnalyticsMetricsCache::getCache( $request );
		if ( !$responseMetric ) {
			$service = self::getService();

			try {
				$response = call_user_func_array( array( $service->data_ga, 'get' ), $request );
				$rows = $response->getRows();
				$response2 = call_user_func_array( array( $service->data_ga, 'get' ), $request2 );
				$rows2 = $response2->getRows();
				$rows = is_array($rows) ? $rows : [];
				$rows2 = is_array($rows2) ? $rows2 : [];
				$rows = array_merge($rows,$rows2);
				foreach($rows as $row){
					// as we are using filters in queries, all rows could be counted, we could remove the fellowing checks
				    // Search patterns and replace it with nothing to just have the title's page, do it with index pattern and wiki
				    $patternIndex = '[^\/index.php\/]';
				    $replace = '';
				    $NewRowIndex= preg_replace($patternIndex, $replace, $row[0]);

				    if($NewRowIndex==$title){
                        $responseMetricIndex = $row[1];
                    }
                    //We get through $1 to get the title page and then we check if it's a match to display the true number.
                    $patternWiki =  "#" .str_replace (('$1'), '(.*)', $wgArticlePath) . "#";
                    preg_match($patternWiki, $row[0], $matches);
                    if(isset($matches[1]) && $matches[1]== $title ) {
   				        $responseMetricWiki = $row[1];
                     }
                    // We add both counter to display the sum and have the true one.
                    $responseMetric = $responseMetricIndex + $responseMetricWiki;

				}


				GoogleAnalyticsMetricsCache::setCache( $request, $responseMetric,
					$wgGoogleAnalyticsMetricsExpiry );
			} catch ( Exception $e ) {
			    MWExceptionHandler::logException( $e );
				// Try to at least return something, however old it is
 				$lastValue = GoogleAnalyticsMetricsCache::getCache( $request, true );
				if ( $lastValue ) {
					return $lastValue;
				} else {
				    return self::getWrappedError( 'Error!' );
				}
			}

		}


		return $responseMetric;
	}


	/**
	 * Gets the Analytics metric with the dates provided
	 *
	 * @global string $wgGoogleAnalyticsMetricsViewID
	 * @global int $wgGoogleAnalyticsMetricsExpiry
	 * @param string $metric The name of the Analyitcs metric, without the "ga:" prefix
	 * @param string $startDate Must be a valid date recognized by the Google API
	 * @param string $endDate Must be a valid date recognized by the Google API
	 * @return string
	 */
	public static function getMetric( $metric, $startDate, $endDate ) {
	    global $wgTitle;
		// We store the ID in the cache, but that is not a waste, since if the ID changes that
		// data is no longer valid.
	    return self::getMetricWithTitle( $wgTitle, $metric, $startDate, $endDate);

	}

	/**
	 * Returns the Analytics service, ready for use
	 *
	 * @global string $wgGoogleAnalyticsMetricsEmail
	 * @global string $wgGoogleAnalyticsMetricsPath
	 * @global WebRequest $wgRequest
	 * @return \Google_Service_Analytics
	 */
	private static function getService() {
		//This entire function is copied from GoogleAnalyticsTopPages::getData()
	    global $wgGoogleAnalyticsMetricsEmail,$wgGoogleAnalyticsMetricsDevelopersKey,
	    $wgGoogleAnanlyticsMetricsAppName, $wgGoogleAnalyticsMetricsServiceAccountPath, $wgRequest;


	    $client = new Google_Client();
	    $client->setApplicationName($wgGoogleAnanlyticsMetricsAppName);
	    $client->setAuthConfig($wgGoogleAnalyticsMetricsServiceAccountPath);
	    $client->setDeveloperKey($wgGoogleAnalyticsMetricsDevelopersKey);

        $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
        $client->useApplicationDefaultCredentials();
        $analytics = new Google_Service_Analytics($client);

		$request = $wgRequest;

		// check, if the client is already authenticated
		if ( $request->getSessionData( 'service_token' ) !== null ) {
		    $client->setAccessToken( $request->getSessionData( 'service_token' ) );
		}
		$client->setAuthConfig($wgGoogleAnalyticsMetricsServiceAccountPath);
		$client->useApplicationDefaultCredentials();


		// set the service_token to the session for future requests
 		$request->setSessionData( 'service_token', $client->getAccessToken() );

		// Create the needed Google Analytics service object
		return $analytics;
	}

	/**
	 * Convenience function that returns text wrapped in an error class
	 *
	 * @param string $text
	 * @return string HTML
	 */
	private static function getWrappedError( $text ) {
		return Html::element( 'span', array( 'class' => 'error' ), $text );
	}

	/**
	 *
	 * @param DatabaseUpdater $updater
	 * @return boolean
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( GoogleAnalyticsMetricsCache::TABLE,
			__DIR__ . '/GoogleAnalyticsMetrics.sql', true );
		return true;
	}
}
