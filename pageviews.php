<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$count=0;
// Include the Google API PHP Client library for the Data API
require 'vendor/autoload.php';

// GA4 Property ID (Replace with your GA4 Property ID)
$propertyId = '************';

// Service Account Credentials JSON File Path
$credentialsPath = '/*******/*******/******/creds.json';

// Initialize the Google API client for the Data API
$client = new Google\Client();
$client->setAuthConfig($credentialsPath);
$client->addScope('https://www.googleapis.com/auth/analytics.readonly');

// Create a new Google_Service_AnalyticsData instance
$analyticsData = new Google\Service\AnalyticsData($client);

// Function to normalize the URL
function normalizeUrl($url) {
    $normalizedUrl = rtrim($url, '/'); // Remove trailing slash
    $normalizedUrl = strtolower($normalizedUrl); // Convert to lowercase
    // You can add more normalization steps if needed (e.g., removing query parameters)
    return $normalizedUrl;
}

// Function to get PageViews for a specific URL

function getPageViewsForUrl($analyticsData, $propertyId, $url) {
    

	
	try {
        // Normalize the URL before making the API request
        $normalizedUrl = normalizeUrl($url);

        $dataRequest = new Google\Service\AnalyticsData\RunReportRequest([
            'property' => 'properties/' . $propertyId,
            'dateRanges' => [
                [
                    'start_date' => getDaysSincePostPublished().'daysAgo',
                    'end_date' => 'today',
                ],
            ],
            'dimensions' => [
                [
                    'name' => 'pagePath',
                ],
            ],
            'metrics' => [
                [
                    'name' => 'screenPageViews',
                ],
            ],
            'dimensionFilter' => [
                'filter' => [
                    'field_name' => 'pagePath',
                    'stringFilter' => [
                        'value' => $normalizedUrl,
                        'matchType' => 'EXACT',
                    ],
                ],
            ],
        ]);

        // Call the runReport() method with both parameters
        $response = $analyticsData->properties->runReport('properties/' . $propertyId, $dataRequest);

        // Check if the response is null or empty
        if (!$response || empty($response->getRows())) {
            return 0; // Return 0 to indicate an error or no data
        }

        // Extract the total page views from the API response
        $pageViews = $response->getRows()[0]->getMetrics()[0]->getValues()[0];

        return $pageViews;
    } catch (Google\Service\Exception $e) {
        // Log any exceptions to the error log
        error_log('Google API Exception: ' . $e->getMessage());
        return 0; // Return 0 to indicate an error
    }
}

try {
  // Call the Google Analytics Data API to get all URLs and their respective page views
  $dataRequest = new Google\Service\AnalyticsData\RunReportRequest([
      'property' => 'properties/' . $propertyId,
      'dateRanges' => [
          [
              'start_date' => getDaysSincePostPublished().'daysAgo',
              'end_date' => 'today',
          ],
      ],
      'dimensions' => [
          [
              'name' => 'pagePath',
          ],
      ],
      'metrics' => [
          [
              'name' => 'screenPageViews',
          ],
      ],
  ]);

  $response = $analyticsData->properties->runReport('properties/' . $propertyId, $dataRequest);

  // Check if the response is null or empty
  if (!$response || empty($response->getRows())) {
      echo 'No data available.';
      exit;
  }

  // Display the PageViews count for each URL

  $URI = $_SERVER['REQUEST_URI'];
  foreach ($response->getRows() as $row) {
      $url = $row->getDimensionValues()[0]->getValue();
      $pageViews = $row->getMetricValues()[0]->getValue();
      if($URI==$url){
        $count=$pageViews;

		add_shortcode('page_views_count', '<p>Page Views: ' . $count . '</p>');
      }
  
    }

} catch (Google\Service\Exception $e) {
  // Log any exceptions to the error log
  error_log('Google API Exception: ' . $e->getMessage());
  echo 'An error occurred while fetching data.';
}

function page_views_count_shortcode() {

	global $count;
    return '<p>Page Views: ' . $count . '</p>';
}
add_shortcode('page_views_count', 'page_views_count_shortcode');	


function getDaysSincePostPublished() {
		$post = get_the_ID();
    if (!$post instanceof WP_Post) {
        $post = get_post($post);
    }

    if (!$post || !in_array($post->post_type, array('post', 'page'))) {
        return -1; // Return -1 to indicate an error or unsupported post type.
    }

    $publishedDate = strtotime($post->post_date);
    $currentDate = current_time('timestamp');

    $daysSincePublished = floor(($currentDate - $publishedDate) / (60 * 60 * 24)) +7;

    return $daysSincePublished;
}
