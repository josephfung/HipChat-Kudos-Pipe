<?php

/**
 * A helper script to pipe Kudos from your TribeHR account into a hipchat room
 *
 * This script relies on the TribeHR PHP Client, which can be found at
 * https://github.com/tribehr/TribeHR-PHP-Client
 * 
 * Last Edit: May 27, 2012
 * @author Joseph Fung <joseph@tribehr.com>
 * @copyright 2012 TribeHR Corp
 * @version 1.0
 * @license ???
 */

// Set your TribeHR credentials
define('SUBDOMAIN', '########');								// Your TribeHR Sub Domain
define('USER', '#####');										// The username of an Administrator
define('KEY', '######################################');		// The API KEY of the administrator above
define('PROTOCOL', 'https');									// Use HTTPS for live testing

// Set your HipChat details
define('HIPCHAT_TOKEN', '#############################');		// Set these forom the hipchat site
define('HIPCHAT_ROOM_ID', '#####');								// When in HipChat, view "Chat History" then a Room to find the ID in the URL
define('HIPCHAT_FROM', 'TribeHR');								// The name that posts the message to HipChat
define('HIPCHAT_COLOR', 'green');								// The highlight colour of the message

// Include the connector library
require('./TribeHR-PHP-Client/TribeHR.php');

// Let's only process the payload if we're going to actually be getting
// a Kudo. Note - this logic can be easily extende to create a script that
// can handle multiple different WebHook types.
if (isset($_REQUEST['object_id']) && isset($_REQUEST['object']))
{
	// Create my connection to tribehr and fetch the kudos that are specified
	// by the webhood data.
	$TribeHRConnection = new TribeHRConnector(SUBDOMAIN, USER, KEY);
	$TribeHRConnection->setProtocol(PROTOCOL);
	$id = intval($_REQUEST['object_id']);
	try {
		$tc = $TribeHRConnection->sendRequest('/kudos/'.$id.'.xml', 'GET');
	}
	catch (Exception $e) {
		die('Failed to get Request XML using URL /kudos/'.$id.'.xml. Exception error: '. $e->getMessage());
	}

	// Now that we have the information in $tc, convert the data to an array
	// and check to see if this is actually a kudos instance, othewise stop
	$xml = simplexml_load_string($tc->response);
	if($xml->note['is_kudo'])
	{
		// We'll be using this URL repeatedy, so create a variable
		$tribehr_url = 'https://'.SUBDOMAIN.'.mytribehr.com';

		// Create the message that we'll be sending.
		$kudosBody = '<em>'.strip_tags(trim($xml->note['note'])).'</em>';
			
		// Assemble the recipients into a single hyperlinked string
		$r = array();
		foreach($xml->note->user as $user)
		{
			$r[] = '<a href="'. $tribehr_url. '/users/view/'.$user['id'].'">'.$user['full_name'].'</a>';
		}
		$recipients = implode(', ', $r);

		// Prepare the sender name as a url
		$sender = '<a href="' . $tribehr_url . '/users/view/'.$xml->note->poster['id'].'">'.$xml->note->poster['full_name'] . '</a>';
		
		// Assemble the basic message
		$message =  $sender . ' gave kudos to ' . $recipients . ': "' . $kudosBody . '"';

		// Add the values if they exist
		foreach($xml->note->value as $value)
		{
			if(!empty($value['name']))
			{
				$message .= ' (' . strtolower($value['name']) . ')';
			}
		}
		
		// Add the URL to the end, to get people back into TribeHR
		$message .= ' <a href="' . $tribehr_url . '">'.$tribehr_url . '</a>';
		
		// Echo the message as a debugging check
		echo $message;

		// Send the message to hipchat and echo the result as a debugging check
		$result = sendMessageToHipchat($message);
		echo $result;
	}
	else
	{
		die('Not Kudos');
	}
}

// Helper function to send messages to hipchat, as configured based on the
// defines at the header of this file.
function sendMessageToHipchat($message)
{
	// This helper function uses the global settings 
	$payload = array(
		'room_id' => HIPCHAT_ROOM_ID,
		'from' => HIPCHAT_FROM,
		'message' => $message,
		'notify' => 1,
		'color' => HIPCHAT_COLOR,			
	);
	
	$ch = curl_init('https://api.hipchat.com/v1/rooms/message?format=json&auth_token='.HIPCHAT_TOKEN);
	curl_setopt ($ch, CURLOPT_POST, 1);
	curl_setopt ($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$output = curl_exec ($ch);
	curl_close ($ch);
	
	return $output;
}

?>