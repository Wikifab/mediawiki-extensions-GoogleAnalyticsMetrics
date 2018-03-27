# Description

Google Ananlytics Metrics allow you to use Google's API and then you can published some statistic. 
However on the 1.29 version of Mediawiki some classes and stuff have been removed so we change few things. 
We need to display the counter view per page. 
 
# Installation

1. Clone GoogleAnalyticsMetrics into the 'extensions' directory of your mediawiki installation and call it 'GoogleAnalyticsMetrics'.

2. Add the folling Line to your LocalSettings.php file :

    wfLoadExtension('GoogleAnalyticsMetrics');

3. Add those lines too to your LocalSettings.php file with your own values : 

For this step, please follow the instructions at https://developers.google.com/analytics/devguides/config/mgmt/v3/quickstart/service-php

	$wgGoogleAnalyticsMetricsAllowed ='*'; // the "*" allow all metrics 
	$wgGoogleAnalyticsMetricsServiceAccountPath ='Your/Path/To/YourJsonFileName.json';
	$wgGoogleAnalyticsMetricsEmail='your client_email in your json file';
	$wgGoogleAnalyticsMetricsViewID = 'This is your account's id you can find directly on Google Analytics in your settings.';
	$wgGoogleAnalyticsMetricsDevelopersKey = 'your private Key in your json file';
	$wgGoogleAnalyticsMetricsAppName = 'The name of you application';

You might have trouble getting it to work and get this error "User does not have sufficient permissions for this account". In which case, for $wgGoogleAnalyticsMetricsViewID, use the table id instead of your account's id, which you can find on the management page under view (parameters -> view id)

	// Load the Google API PHP Client Library.
	require_once __DIR__ . '/vendor/autoload.php';
	
#How to use it ? 

For instance if you want to display the page view's counter you just have to add this line on the page you want it : 

	{{#googleanalyticsmetrics:pageviews}} 
	
# Dependencies	

This extension works with the Extension:GoogleAnalytics.

# MediaWiki Versions

This extension has been tested on MediaWiki version 1.29.