<?php
require '/home/emilio/repo/perseed_platform/library/init_autoloader.php';

define('APPLICATION_NAME', 'Google FIT API PHP Quickstart');
define('CLIENT_SECRET_PATH', '/home/emilio/repo/googleAPI/client_secret.json');
define('CREDENTIALS', '/home/emilio/.credentials.json');
define('SCOPES', implode(' ', array(Google_Service_Fitness::FITNESS_ACTIVITY_READ)));

setcookie('start', date('Y-m-d'));
setcookie('end', date('Y-m-d'));

if (isset($_GET['start']))
    setcookie('start', $_GET['start']);
if (isset($_GET['end']))
    setcookie('end', $_GET['end']);


$client = new Google_Client();
$client->setAuthConfigFile(CLIENT_SECRET_PATH);
$client->addScope(Google_Service_Fitness::FITNESS_ACTIVITY_READ);
$client->addScope(Google_Service_Fitness::FITNESS_BODY_READ);
$client->setAccessType('offline');

// Check file or database
if (file_exists($credentialsPath)) {
    $accessToken = file_get_contents(CREDENTIALS);
} else {
    // If code is set, store it into file or db
    if (isset($_GET['code'])) {
        $client->authenticate($_GET['code']);
        $accessToken = $client->getAccessToken();
        file_put_contents(CREDENTIALS, $accessToken);
    }

    if (!$client->getAccessToken()) {
        $authUrl = $client->createAuthUrl();
    }
}

$client->setAccessToken($accessToken);

// Refresh the token if it's expired.
if ($client->isAccessTokenExpired()) {
  $client->refreshToken($client->getRefreshToken());
  file_put_contents(CREDENTIALS, $client->getAccessToken());
}


// If user is not set, display login button
if (isset($authUrl))
    echo '<a class="login" href="'.$authUrl.'"><img src="https://moqups.com/help/assets/google-login-button.png" /></a>';
else {

    // Start and end dates
    $start = $_COOKIE['start'];
    $end = $_COOKIE['end'];

    $fitness_service = new Google_Service_Fitness($client);

    $dataSources = $fitness_service->users_dataSources;
    $dataSets = $fitness_service->users_dataSources_datasets;
    $listDataSources = $dataSources->listUsersDataSources("me");

    $result = array();
    $result['token'] = $client->getAccessToken();

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
            };
        }
        $result['activity'][$start] = $step_count;
        $start = date ("Y-m-d", strtotime("+1 days", strtotime($start)));
        $listDataSources = $dataSources->listUsersDataSources("me");
    }
    var_dump($result);
}

$client->revokeToken();
