#!/usr/bin/php -q
<?PHP

$debug = ( (isset($argv[1]) ) ? $argv[1] : 0);

# Get a token
$client_id     = '';
$client_secret = '';
$username      = '';
$password      = '';
$vars          = '';

$server_output = get_token($vars, "https://pbx.example.com/ns-api/oauth2/token/", $client_id, $client_secret, $username, $password);

if( $debug >= 2 ) print_r($server_output);

$token = $server_output->access_token;

$url = "https://pbx.example.com/ns-api/";

$vars['object']     = "subscriber";
$vars['format']     = "json";
$vars['action']     = "read";
$vars['last_name']  = "PVM";
$subscribers = get_data($vars, $url, $token);

if ( count($subscribers) == 0 ) {
 if ( $debug >= 1 ) echo "No Persistent VM subscribers found... Exiting\n";
 exit;
}

if ( $debug >= 2 ) print_r($subscribers);

date_default_timezone_set('UTC');

$now = date("Y-m-d H:i:s");

foreach ( $subscribers as $subscriber ) {

 $subscriber->destination = end(preg_split('/ /',$subscriber->last_name));

 unset($vars);
 $vars['object']    = "audio";
 $vars['format']    = "json";
 $vars['action']    = "read";
 $vars['domain']    = $subscriber->domain;
 $vars['user']      = $subscriber->user;
 $vars['type']      = "vmail/new";

 if ( $debug >= 2 ) print_r($vars);

 $subscriber->vmails = get_data($vars, $url, $token);

 if ( count($subscriber->vmails) ) {
  if ( $debug >= 1 ) echo "I have an unread voicemail in $subscriber->domain : $subscriber->user : $subscriber->last_name, $subscriber->first_name\n";

  foreach ( $subscriber->vmails as $vmail ) {
   $vmail->uid              = $subscriber->user . "@" . $subscriber->domain;
   $vmail->spectrum_domain  = $subscriber->domain;
   $vmail->spectrum_call_to = $subscriber->first_name;
   $voicemails_to_check[]   = $vmail;
  }
 }

}

if ( !isset($voicemails_to_check) ) {
 if ( $debug >= 2 ) echo "Nothing to do so exiting\n";
 exit;
}

if ( $debug >= 2 ) print_r($subscribers);
if ( $debug >= 2 ) print_r($voicemails_to_check);
if ( $debug >= 2 ) echo "I have all that is needed, starting to loop through to make calls.\n";

unset($vmail);

foreach ( $voicemails_to_check as $vmail ) {

 unset($vars);
 $vars['object']      = "call";
 $vars['format']      = "json";
 $vars['action']      = "call";
 $vars['callid']      = $vmail->index;
 $vars['uid']         = $vmail->uid;
 $vars['destination'] = $vmail->spectrum_call_to;

 if ( $debug >= 2 ) print_r($vars);

 if ( !isset($called[$vmail->spectrum_call_to]['called']) ) {
  if ( $debug >= 1 ) echo " Calling $vmail->spectrum_domain -> $vmail->spectrum_call_to\n";
  $call = get_data($vars, $url, $token);
  $called[$vmail->spectrum_call_to]['called'] = 1;
  if ( $debug >= 2 ) print_r($call);
 }

}

exit;

function get_data($vars, $url, $token) {

 global $debug;

 $ch = curl_init();
 curl_setopt($ch, CURLOPT_URL, $url);
 curl_setopt($ch, CURLOPT_POST, 1);
 curl_setopt($ch, CURLOPT_POSTFIELDS,$vars);  //Post Fields
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

 # Temporary while Stefano is working on the NGIX SSL cert
 curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

 $headers[] = "Authorization: Bearer $token";

 curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

 $server_output = curl_exec ($ch);
 $success = curl_getinfo($ch, CURLINFO_HTTP_CODE);

 if($server_output === false ) {
  echo date('c') . " API returned a '$success' '" . curl_error($ch) . "' when trying to obtain data from $url\n";
  exit;
 }

 curl_close ($ch);

 if ( $debug >= 10 ) echo "$server_output)\n";

 return json_decode($server_output);

}

function get_token($vars, $url="https://pbx.example.com/ns-api/oauth2/token/", $client_id, $client_secret, $username, $password) {

 $vars = Array('format' => 'json', 'grant_type' => 'password', 'client_id' => $client_id, 'client_secret' => $client_secret, 'username' => $username, 'password' => $password);

 $ch = curl_init();
 curl_setopt($ch, CURLOPT_URL, $url);
 curl_setopt($ch, CURLOPT_POST, 1);
 curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);  //Post Fields
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

 # Temporary while Stefano is working on the NGIX SSL cert
 curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

 $returner = curl_exec($ch);

 $success = curl_getinfo($ch, CURLINFO_HTTP_CODE);

 if($returner === false || $success != "200") {
  echo date('c') . " API returned a '$success' '" . curl_error($ch) . "' when trying to obtain a token from $url\n";
  exit;
 }

 curl_close ($ch);

 $returner = json_decode($returner);

 return $returner;

}

?>
