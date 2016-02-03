<?php
require '/home/emilio/repo/perseed_platform/library/init_autoloader.php';

define('APPLICATION_NAME', 'Google FIT API PHP Quickstart');
define('CREDENTIALS_PATH', '~/.credentials/google-fit-php-quickstart.json');
define('CLIENT_SECRET_PATH', __DIR__ . '/client_secret.json');
define('SCOPES', implode(' ', array(
  Google_Service_Fitness::FITNESS_ACTIVITY_READ)
));

if (php_sapi_name() != 'cli') {
  throw new Exception('This application must be run on the command line.');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient() {
  $client = new Google_Client();
  $client->setApplicationName(APPLICATION_NAME);
  $client->setScopes(SCOPES);
  $client->setAuthConfigFile(CLIENT_SECRET_PATH);
  $client->setAccessType('offline');

  // Load previously authorized credentials from a file.
  $credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);
  if (file_exists($credentialsPath)) {
    $accessToken = file_get_contents($credentialsPath);
  } else {
    // Request authorization from the user.
    $authUrl = $client->createAuthUrl();
    printf("Open the following link in your browser:\n%s\n", $authUrl);
    print 'Enter verification code: ';
    $authCode = trim(fgets(STDIN));

    // Exchange authorization code for an access token.
    $accessToken = $client->authenticate($authCode);

    // Store the credentials to disk.
    if(!file_exists(dirname($credentialsPath))) {
      mkdir(dirname($credentialsPath), 0700, true);
    }
    file_put_contents($credentialsPath, $accessToken);
    printf("Credentials saved to %s\n", $credentialsPath);
  }
  $client->setAccessToken($accessToken);

  // Refresh the token if it's expired.
  if ($client->isAccessTokenExpired()) {
    $client->refreshToken($client->getRefreshToken());
    file_put_contents($credentialsPath, $client->getAccessToken());
  }
  return $client;
}

/**
 * Expands the home directory alias '~' to the full path.
 * @param string $path the path to expand.
 * @return string the expanded path.
 */
function expandHomeDirectory($path) {
  $homeDirectory = getenv('HOME');
  if (empty($homeDirectory)) {
    $homeDirectory = getenv("HOMEDRIVE") . getenv("HOMEPATH");
  }
  return str_replace('~', realpath($homeDirectory), $path);
}

// Get the API client and construct the service object.
$client = getClient();
$fitness_service = new Google_Service_Fitness($client);

$dataSources = $fitness_service->users_dataSources;
$dataSets = $fitness_service->users_dataSources_datasets;
$listDataSources = $dataSources->listUsersDataSources("me");
$start = $end = null;

if (count($argv) === 3) {
    $start = $argv[1];
    $end = $argv[2];
}

$dates = array();

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
    $dates[$start] = $step_count;
    $start = date ("Y-m-d", strtotime("+1 days", strtotime($start)));
    $listDataSources = $dataSources->listUsersDataSources("me");
}
var_dump($dates);
