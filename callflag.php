<?php

define("SERVER", "server-fqnd");
define("SUPERUSER", "superuser");
define("PASSWORD", "password");
define("CLIENTID", "id");
define("CLIENTSECRET", "secret");

session_start();
header("Content-Type: text/xml");
echo '<?xml version="1.0" encoding="UTF-8" standalone="no"?>';


  $http_response = "";
  
  // get NS-API token
  $token = __getToken();
	
  // get caller user and domain
  $user=$_REQUEST["AccountUser"];
  $domain=$_REQUEST["AccountDomain"];

  // NS-API data structure to get last call
  $query = array(
    'object' => 'cdr2',
    'action'=> 'read',
    'uid'    => "{$user}@{$domain}",
    'limit'  => '1',
    'format' => 'json',
  );

  // do API call and decode json
  $lastcall = __doCurl("https://".SERVER."/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);
  $lastcall = json_decode($lastcall,true);

  // gets the CDR_ID returned from the API call
  $cdr_id = $lastcall[0]["cdr_id"];

  // NS-API data structure to set flag
  $query = array(
    'object' => 'cdr2',
    'action'=> 'update',
    'uid'    => "{$user}@{$domain}",
	'cdr_id'  => "{$cdr_id}",
	'notes' => 'flag',
  );

  __doCurl("https://".SERVER."/ns-api/", CURLOPT_POST, "Authorization: Bearer " . $token, $query, null, $http_response);

// announce the flag is set
echo "<Play>http://" . SERVER . "/callflagset.wav</Play>";
exit();



function __getToken()
{
/* First Step is to get a new Access token to given server.*/
$query = array(
        'grant_type'    => 'password',
        'username'        => SUPERUSER,
        'password'        => PASSWORD,
        'client_id'        => CLIENTID,
        'client_secret'        => CLIENTSECRET,
);

$postFields = http_build_query($query);
$http_response = "";

$curl_result = __doCurl("https://".SERVER."/ns-api/oauth2/token", CURLOPT_POST, NULL, NULL, $postFields, $http_response);

if (!$curl_result){
    echo "error doing curl getting key";
    exit;
}

$token = json_decode($curl_result, /*assoc*/true);

if (!isset($token['access_token'])) {
    echo "failure getting access token";
    exit;
}

return $token['access_token'];
}

function __doCurl($url, $method, $authorization, $query, $postFields, $http_response)
{
    $start= microtime(true);
    $curl_options = array(
            CURLOPT_URL => $url . ($query ? '?' . http_build_query($query) : ''),
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FORBID_REUSE => true,
            CURLOPT_TIMEOUT => 60
    );

    $headers = array();
    if ($authorization != NULL)
    {
        if ("bus:bus" == $authorization)
            $curl_options[CURLOPT_USERPWD]=$authorization;
        else
            $headers[$authorization]=$authorization;
    }


    $curl_options[$method] = true;
    if ($postFields != NULL )
    {
        $curl_options[CURLOPT_POSTFIELDS] = $postFields;
    }

    if (sizeof($headers)>0)
        $curl_options[CURLOPT_HTTPHEADER] = $headers;

    $curl_handle = curl_init();
    curl_setopt_array($curl_handle, $curl_options);
    $curl_result = curl_exec($curl_handle);
    $http_response = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
    //print_r($http_response);
    curl_close($curl_handle);
    $end = microtime(true);
    if (!$curl_result)
        return NULL;
    else if ($http_response >= 400)
        return NULL;
    else
        return $curl_result;
}



?>
