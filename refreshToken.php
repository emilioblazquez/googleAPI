<?php
require '/home/emilio/repo/perseed_platform/library/init_autoloader.php';

define('APPLICATION_NAME', 'Google FIT API PHP Quickstart');
define('CLIENT_SECRET_PATH', '/home/emilio/repo/googleAPI/client_secret.json');
define('SCOPES', implode(' ', array(Google_Service_Fitness::FITNESS_ACTIVITY_READ)));

setcookie('start', date('Y-m-d'));
setcookie('end', date('Y-m-d'));

if (isset($_GET['start']))
    setcookie('start', $_GET['start']);
if (isset($_GET['end']))
    setcookie('end', $_GET['end']);

$authUrl = null;

try {
    $client = new Google_Client();
    $client->setAuthConfigFile(CLIENT_SECRET_PATH);
    $client->addScope(Google_Service_Fitness::FITNESS_ACTIVITY_READ);
    $client->addScope(Google_Service_Fitness::FITNESS_BODY_READ);
    $client->setAccessType('offline');

    //$access_token = getFromDb();

    if (isset($_GET['code'])) {
        $client->authenticate($_GET['code']);
    	$refreshToken = $client->getRefreshToken();
    	$_SESSION['token'] = $refreshToken;
    } else{
	// Extract Token from session and configure client
	if (!isset($_SESSION['token'])) {
	    header('Location: ' . $client->createAuthUrl());
        die();
	}
	$client->setAccessToken($_SESSION['token']);
    }
} catch(Google_Auth_Exception $e) {
    var_dump("Caught Google service Exception ".$e->getCode(). " message is ".$e->getMessage());
    var_dump("Stack trace is ".$e->getTraceAsString());
}

// Start and end dates
$start = $_COOKIE['start'];
$end = $_COOKIE['end'];

$fitness_service = new Google_Service_Fitness($client);

$dataSources = $fitness_service->users_dataSources;
$dataSets = $fitness_service->users_dataSources_datasets;
$listDataSources = $dataSources->listUsersDataSources("me");

$result = array();

// Loop over days
while (strtotime($start) <= strtotime($end)) {
    $startTime = strtotime($start . ' 00:00:00');
    $endTime = strtotime($start . ' 23:59:59');
    while($listDataSources->valid()) {
        $dataSourceItem = $listDataSources->next();
        if ($dataSourceItem['dataStreamId'] == "derived:com.google.step_count.delta:com.google.android.gms:estimated_steps") {
            $dataStreamId = $dataSourceItem['dataStreamId'];
            $listDatasets = $dataSets->get("me", $dataStreamId, $startTime.'000000000'.'-'.$endTime.'000000000');
            $step_count = 0;
            while($listDatasets->valid()) {
                $dataSet = $listDatasets->next();
                $dataSetValues = $dataSet['value'];
                if ($dataSetValues && is_array($dataSetValues)) {
                    foreach($dataSetValues as $dataSetValue) {
                        $step_count += $dataSetValue['intVal'];
                    }
                }
            }
        }
    }
    $result['activity'][$start] = $step_count;
    $start = date ("Y-m-d", strtotime("+1 days", strtotime($start)));
    $listDataSources = $dataSources->listUsersDataSources("me");
}

var_dump($result);
$refreshToken = $client->getRefreshToken();
$_SESSION['token'] = $refreshToken;
