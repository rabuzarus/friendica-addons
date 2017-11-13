<?php

/*

Jappix - An open social platform
This is a PHP BOSH proxy

-------------------------------------------------

This file is dual-licensed under the MIT license (see MIT.txt) and the AGPL license (see jappix/COPYING).
Authors: Vanaryon, Leberwurscht

*/

// PHP base
define('JAPPIX_BASE', './jappix');

// Get the configuration
require_once('./jappix/php/functions.php');
require_once('./jappix/php/read-main.php');
require_once('./jappix/php/read-hosts.php');

// Optimize the page rendering
hideErrors();
compressThis();

// Not allowed?
if(!BOSHProxy()) {
	header('Status: 403 Forbidden', true, 403);

	deb("no Bosh Proxy"); //remove this
	exit('HTTP/1.1 403 Forbidden');
}

//deb("Header: ".print_r(apache_request_headers(), true));

// custom BOSH host
$HOST_BOSH = HOST_BOSH;
if(isset($_GET['host_bosh']) && $_GET['host_bosh']) {
	$host_bosh = $_GET['host_bosh'];
	if (substr($host_bosh, 0, 7)==="http://" || substr($host_bosh, 0, 8)==="https://") {
		$HOST_BOSH = $host_bosh;
	}
//	deb('Host Bosh = '.print_r($host_bosh, true)); //remove this
}

// OPTIONS method?
if($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
	// CORS headers
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
	header('Access-Control-Allow-Headers: Content-Type');
	header('Access-Control-Max-Age: 31536000');

	deb("Request_Method options was set"); //remove this
	exit;
}

// Read POST content
$data = file_get_contents('php://input');

deb("[DATA] ".print_r($data, true)); //remove this

// POST method?
if($data) {
	// CORS headers
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: Content-Type');

//	deb("[Data] ".print_r($data, true)); // remove this;
	$method = 'POST';
}

// GET method?
else if(isset($_GET['data']) && $_GET['data'] && isset($_GET['callback']) && $_GET['callback']) {
	$method = 'GET';
	$data = $_GET['data'];
	deb("Callback is used"); // remove this
	$callback = $_GET['callback'];
}

// Invalid method?
else {
	header('Status: 400 Bad Request', true, 400); deb("Invalid Method");//remove this
	exit('HTTP/1.1 400 Bad Request');
}

// HTTP headers
$headers = array('User-Agent: Jappix (BOSH PHP Proxy)', 'Connection: keep-alive', 'Content-Type: text/xml; charset=utf-8', 'Content-Length: '.strlen($data));

// CURL is better if available
if(function_exists('curl_init'))
	$use_curl = true;
else
	$use_curl = false;

// CURL caused problems for me
$use_curl = false;

//deb("Used Method: ".print_r($method, true)); //remove this

// CURL stream functions
if($use_curl) {
	// Initialize CURL
	$connection = curl_init($HOST_BOSH);
	
	// Set the CURL settings
	curl_setopt($connection, CURLOPT_HEADER, 0);
	curl_setopt($connection, CURLOPT_POST, 1);
	curl_setopt($connection, CURLOPT_POSTFIELDS, $data);
	curl_setopt($connection, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($connection, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($connection, CURLOPT_VERBOSE, 0);
	curl_setopt($connection, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($connection, CURLOPT_TIMEOUT, 30);
	curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
	
	// Get the CURL output
	$output = curl_exec($connection);
}

// Built-in stream functions
else {
	// HTTP parameters
	$parameters = array('http' => array(
					'method' => 'POST',
					'content' => $data
				      )
		      );

	$parameters['http']['header'] = $headers;

	// Change default timeout
	ini_set('default_socket_timeout', 30);

	// Create the connection
	$stream = @stream_context_create($parameters);
	$connection = @fopen($HOST_BOSH, 'rb', false, $stream);

//	deb("The stream: ".print_r($stream, true));

	// Failed to connect!
	if($connection == false) {
		header('Status: 502 Proxy Error', true, 502); deb("Connection failed - Proxy error"); // remove this
		exit('HTTP/1.1 502 Proxy Error');
	}

	// Allow stream blocking to handle incoming BOSH data
	@stream_set_blocking($connection, true);

//	deb("The connection: ".print_r($connection, true)); // remove this
	// Get the output content
	$output = @stream_get_contents($connection);
}

// Cache headers
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// POST output
if($method == 'POST') {
	// XML header
	header('Content-Type: text/xml; charset=utf-8');

	deb("[SENT]: ".print_r($output, true)); // remove this

	if(!$output)
		echo('<body xmlns=\'http://jabber.org/protocol/httpbind\' type=\'terminate\'/>');
	else
		echo($output);
}

// GET output
if($method == 'GET') {
	// JSON header
	header('Content-type: application/json');
	
	// Encode output to JSON
	$json_output = json_encode($output);

	deb('[RECV] '.print_r($callback, true).'({"reply":'.print_r($json_output, true).'});'); // remove this

	if(($output == false) || ($output == '') || ($json_output == 'null'))
		echo($callback.'({"reply":"<body xmlns=\'http:\/\/jabber.org\/protocol\/httpbind\' type=\'terminate\'\/>"});');
	else
		echo($callback.'({"reply":'.$json_output.'});');
}


// Close the connection
if($use_curl)
	curl_close($connection);
else
	@fclose($connection);


function deb($s) {
//	require_once 'include/datetime.php';

	$logfile = "jsxc.log";
//	$date = datetime_convert('UTC', 'UTC', 'now', 'Y-m-d\TH:i:s\Z');
	$date = $date = date('Y-m-d\TH:i:s\Z', time());

	$content = $date." ".$s. "\n";
	@file_put_contents($logfile, $content, FILE_APPEND);
}
?>
