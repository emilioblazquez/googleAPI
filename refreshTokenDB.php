<?php
require '/home/emilio/repo/perseed_platform/library/init_autoloader.php';

define('APPLICATION_NAME', 'Google FIT API PHP Quickstart');
define('CLIENT_SECRET_PATH', '/home/emilio/repo/googleAPI/client_secret.json');

$start = $end = date('Y-m-d');

$servername = "localhost";
$username = "root";
$password = "[PASSWORD]";
$dbname = "googleToken";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

function updateToken($db, $id, $token, $refreshToken)
{
  $result = $db->query("SELECT COUNT(*) FROM users WHERE user_id = $id;");
  $row = $result->fetch_row();

  // New user, insert
  if($row[0] === '0') {
    $result = $db->query("INSERT INTO users (user_id, token, refresh_token) VALUES($id, '$token', '$refreshToken')");
  }
  // Existing user, update
  else
    $db->query("UPDATE users SET token='$token', refresh_token='$refreshToken' WHERE user_id=$id");
}

function getToken($db, $id)
{
  $result = $db->query("SELECT token FROM users WHERE user_id = $id;");
  $row = $result->fetch_row();

  return $row[0] === '' ? null : $row[0];
}

// Get info stored in cookies
if (isset($_COOKIE['start']))
  $start = $_COOKIE['start'];
if (isset($_COOKIE['end']))
  $end = $_COOKIE['end'];

// Url arguments always prevails
if (isset($_GET['start']))
  $start = $_GET['start'];
if (isset($_GET['end']))
  $end = $_GET['end'];

try {
  $client = new Google_Client();
  $client->setAuthConfigFile(CLIENT_SECRET_PATH);
  $client->addScope(Google_Service_Fitness::FITNESS_ACTIVITY_READ);
  $client->addScope(Google_Service_Fitness::FITNESS_BODY_READ);
  $client->setAccessType('offline');

  if (isset($_GET['code'])) {
    $client->authenticate($_GET['code']);
    $refreshToken = $client->getRefreshToken();
    $_SESSION['token'] = $refreshToken;
    updateToken($conn, 1, $client->getAccessToken(), $refreshToken);
  } else{
    // Extract Token from db and configure client
    $token = getToken($conn, 1);
    if (!isset($token)) {
      setcookie('start', $_GET['start']);
      setcookie('end', $_GET['end']);
      header('Location: ' . $client->createAuthUrl());
      die();
    }
    $client->setAccessToken($token);
  }
} catch(Google_Auth_Exception $e) {
  var_dump("Caught Google service Exception ".$e->getCode(). " message is ".$e->getMessage());
  var_dump("Stack trace is ".$e->getTraceAsString());
}

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

$refreshToken = $client->getRefreshToken();
// Update token
updateToken($conn, 1, $client->getAccessToken(), $refreshToken);
var_dump($result);

// Delete cookies for next execution (for the sake of it)
setcookie('start', '', time()-1000);
setcookie('end', '', time()-1000);
