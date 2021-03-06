<?php
require '/home/emilio/repo/perseed_platform/library/init_autoloader.php';

define('APPLICATION_NAME', 'Google FIT API PHP Quickstart');
define('CLIENT_SECRET_PATH', '/home/emilio/repo/googleAPI/client_secret.json');
define('SCOPES', implode(' ', array(Google_Service_Fitness::FITNESS_ACTIVITY_READ)));

$client = new Google_Client();
$client->setAuthConfigFile(CLIENT_SECRET_PATH);
$client->addScope(Google_Service_Fitness::FITNESS_ACTIVITY_READ);
$client->addScope(Google_Service_Fitness::FITNESS_BODY_READ);

setcookie('start', date('Y-m-d'));
setcookie('end', date('Y-m-d'));

if (isset($_GET['start']))
    setcookie('start', $_GET['start']);
if (isset($_GET['end']))
    setcookie('end', $_GET['end']);

// We received the positive auth callback, get the token and store it in session
if (isset($_GET['code'])) {
    $client->authenticate($_GET['code']);
    $_SESSION['token'] = $client->getAccessToken();
}

// Extract Token from session and configure client
if (isset($_SESSION['token'])) {
    $token = $_SESSION['token'];
    $client->setAccessToken($token);
}

if (!$client->getAccessToken()) {
    $authUrl = $client->createAuthUrl();
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
