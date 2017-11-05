<?php

/**
* Name: jsxc
* Description: Facebook-like chat with end-to-end encrypted conversation, video calls, multi-user rooms, XMPP and internal server backend.
* Version: 0.5
* Author: leberwurscht <leberwurscht@hoegners.de>, Rabuzarus <rabuzarus@t-online.de>
*
*/

//
// Copyright 2012 "Leberwurscht" <leberwurscht@hoegners.de>
//
// This file is dual-licensed under the MIT license (see MIT.txt) and the AGPL license (see jappix/COPYING).
//

/*

Problem:
* jabber password should not be stored on server
* jabber password should not be sent between server and browser as soon as the user is logged in
* jabber password should not be reconstructible from communication between server and browser as soon as the user is logged in

Solution:
Only store an encrypted version of the jabber password on the server. The encryption key is only available to the browser
and not to the server (at least as soon as the user is logged in). It can be stored using the jappix setDB function.

This encryption key could be the friendica password, but then this password would be stored in the browser in cleartext.
It is better to use a hash of the password.
The server should not be able to reconstruct the password, so we can't take the same hash the server stores. But we can
 use hash("some_prefix"+password). This will however not work with OpenID logins, for this type of login the password must
be queried manually.

Problem:
How to discover the jabber addresses of the friendica contacts?

Solution:
Each Friendica site with this addon provides a /jsxc/ module page. We go through our contacts and retrieve
this information every week using a cron hook.

Problem:
We do not want to make the jabber address public.

Solution:
When two friendica users connect using DFRN, the relation gets a DFRN ID and a keypair is generated.
Using this keypair, we can provide the jabber address only to contacts:

Alice:
  signed_address = openssl_*_encrypt(alice_jabber_address)
send signed_address to Bob, who does
  trusted_address = openssl_*_decrypt(signed_address)
  save trusted_address
  encrypted_address = openssl_*_encrypt(bob_jabber_address)
reply with encrypted_address to Alice, who does
  decrypted_address = openssl_*_decrypt(encrypted_address)
  save decrypted_address

Interface for this:
GET /jsxc/?role=%s&signed_address=%s&dfrn_id=%s

Response:
json({"status":"ok", "encrypted_address":"%s"})

*/
use Friendica\Core\Config;
use Friendica\Core\PConfig;

function jsxc_install() {
	register_hook('plugin_settings', 'addon/jsxc/jsxc.php', 'jsxc_settings');
	register_hook('plugin_settings_post', 'addon/jsxc/jsxc.php', 'jsxc_settings_post');

	register_hook('page_end', 'addon/jsxc/jsxc.php', 'jsxc_script');
	register_hook('authenticate', 'addon/jsxc/jsxc.php', 'jsxc_login');

	register_hook('cron', 'addon/jsxc/jsxc.php', 'jsxc_cron');

	// Jappix source download as required by AGPL.
	register_hook('about_hook', 'addon/jsxc/jsxc.php', 'jsxc_download_source');

	// Set standard configuration.
	$info_text = Config::get("jsxc", "infotext");
//	if (!$info_text) {
//		Config::set("jsxc", "infotext",
//			"To get the chat working, you need to know a BOSH host which works with your Jabber account. ".
//			"An example of a BOSH server that works for all accounts is https://bind.jappix.com/, but keep ".
//			"in mind that the BOSH server can read along all chat messages. If you know that your Jabber ".
//			"server also provides an own BOSH server, it is much better to use this one!"
//		);
//	}

	$bosh_proxy = Config::get("jsxc", "bosh_proxy");
	if ($bosh_proxy === "") {
		Config::set("jsxc", "bosh_proxy", "1");
	}

	// Set addon version so that safe updates are possible later.
	$addon_version = Config::get("jsxc", "version");
	if ($addon_version === "") {
		Config::set("jsxc", "version", "1");
	}
}


function jsxc_uninstall() {
	unregister_hook('plugin_settings', 'addon/jsxc/jsxc.php', 'jsxc_settings');
	unregister_hook('plugin_settings_post', 'addon/jsxc/jsxc.php', 'jsxc_settings_post');

	unregister_hook('page_end', 'addon/jsxc/jsxc.php', 'jsxc_script');
	unregister_hook('authenticate', 'addon/jsxc/jsxc.php', 'jsxc_login');

	unregister_hook('cron', 'addon/jsxc/jsxc.php', 'jsxc_cron');

	unregister_hook('about_hook', 'addon/jsxc/jsxc.php', 'jsxc_download_source');
}

function jsxc_plugin_admin(&$a, &$o) {

	$cron_run       = Config::get("jsxc", "last_cron_execution");
	$bosh_address   = Config::get("jsxc", "bosh_address");
	$default_server = Config::get("jsxc", "default_server");
	$info_text      = Config::get("jsxc", "infotext");

	$bosh_proxy   = intval(Config::get("jsxc", "bosh_proxy"));
	$default_user = intval(Config::get("jsxc", "default_user"));

	$bosh_proxy   = intval($bosh_proxy)   ? ' checked="checked"' : '';
	$default_user = intval($default_user) ? ' checked="checked"' : '';

	$tpl = get_markup_template( "admin.tpl", "addon/jsxc/" );
	$o = replace_macros($tpl, array(
		'$cron_run'        => $cron_run,
		'$is_bosh_proxy'   => $bosh_proxy,
		'$bosh_address'    => $bosh_address,
		'$default_server'  => $default_server,
		'$is_default_user' => $default_user,
		'$info_text'       => htmlentities($info_text)
	));
}

function jsxc_plugin_admin_post(&$a) {
	// Set info text
	$submit = $_REQUEST['jsxc-admin-settings'];
	if ($submit) {
		$info_text      = $_REQUEST['jsxc-infotext'];
		$bosh_address   = $_REQUEST['jsxc-address'];
		$default_server = $_REQUEST['jsxc-server'];

		$bosh_proxy   = intval($_REQUEST['jsxc-proxy']);
		$default_user = intval($_REQUEST['jsxc-defaultuser']);

		Config::set("jsxc", "infotext",       $info_text);
		Config::set("jsxc", "bosh_proxy",     $bosh_proxy);
		Config::set("jsxc", "bosh_address",   $bosh_address);
		Config::set("jsxc", "default_server", $default_server);
		Config::set("jsxc", "default_user",   $default_user);
	}
}

function jsxc_module() {}

function jsxc_init(&$a) {
	// Module page where other Friendica sites can submit Jabber addresses to and also can query Jabber addresses
	// of local users.
killme();
	$dfrn_id = $_REQUEST["dfrn_id"];
	if (!$dfrn_id) {
		killme();
	}

	$role = $_REQUEST["role"];
	if ($role == "pub") {
		$contact = q("SELECT * FROM `contact` WHERE LENGTH(`pubkey`) AND `dfrn-id` = '%s' LIMIT 1",
			dbesc($dfrn_id)
		);
		if (!dbm::is_result($contact)) {
			killme();
		}

		$encrypt_func = openssl_public_encrypt;
		$decrypt_func = openssl_public_decrypt;
		$key = $contact[0]["pubkey"];
	} elseif ($role == "prv") {
		$contact = q("SELECT * FROM `contact` WHERE LENGTH(`prvkey`) AND `issued-id` = '%s' LIMIT 1",
			dbesc($dfrn_id)
		);
		if (!dbm::is_result($contact)) {
			killme();
		}

		$encrypt_func = openssl_private_encrypt;
		$decrypt_func = openssl_private_decrypt;
		$key = $contact[0]["prvkey"];
	} else {
		killme();
	}

	$uid = $contact[0]["uid"];

	// Save the Jabber address we received.
	try {
		$signed_address_hex = $_REQUEST["signed_address"];
		$signed_address = hex2bin($signed_address_hex);

		$trusted_address = "";
		$decrypt_func($signed_address, $trusted_address, $key);

		$now = intval(time());
		PConfig::set($uid, "jsxc", "id:$dfrn_id", "$now:$trusted_address");
	} catch (Exception $e) {
	}

	// Do not return an address if user deactivated plugin.
	$activated = PConfig::get($uid, 'jsxc', 'activate');
	if (!$activated) {
		killme();
	}

	// Return the requested Jabber address.
	try {
		$username = PConfig::get($uid, 'jsxc', 'username');
		$server   = PConfig::get($uid, 'jsxc', 'server');

		$address = "$username@$server";
		$encrypted_address = "";

		$encrypt_func($address, $encrypted_address, $key);

		$encrypted_address_hex = bin2hex($encrypted_address);

		$answer = array(
			"status" => "ok",
			"encrypted_address" => $encrypted_address_hex
		);

		$answer_json = json_encode($answer);
		echo $answer_json;
		killme();
	} catch (Exception $e) {
		killme();
	}
}

function jsxc_settings(&$a, &$s) {
	// addon settings for a user

	$activate       = PConfig::get(local_user(), 'jsxc', 'activate');
	$dontinsertchat = PConfig::get(local_user(), 'jsxc', 'dontinsertchat');

	$defaultbosh = Config::get("jsxc", "bosh_address");

	if ($defaultbosh != "") {
		PConfig::set(local_user(), 'jsxc', 'bosh', $defaultbosh);
	}

	$username      = PConfig::get(local_user(), 'jsxc', 'username');
	$server        = PConfig::get(local_user(), 'jsxc', 'server');
	$bosh          = PConfig::get(local_user(), 'jsxc', 'bosh');
	$password      = PConfig::get(local_user(), 'jsxc', 'password');
	$autosubscribe = PConfig::get(local_user(), 'jsxc', 'autosubscribe');
	$autoapprove   = PConfig::get(local_user(), 'jsxc', 'autoapprove');

	$encrypt = intval(PConfig::get(local_user(), 'jsxc', 'encrypt'));

	if ($server == "") {
		$server = Config::get("jsxc", "default_server");
	}

	if (($username == "") && Config::get("jsxc", "default_user")) {
		$username = $a->user["nickname"];
	}

	$info_text = Config::get("jsxc", "infotext");
	$info_text = htmlentities($info_text);
	$info_text = str_replace("\n", "<br />", $info_text);

	// Count contacts.
	$r = q("SELECT COUNT(1) AS `cnt` FROM `pconfig` WHERE `uid` = %d AND `cat` = 'jsxc' AND `k` LIKE 'id:%%'", local_user());
	if (dbm::is_result($r)) {
		$contact_cnt = $r[0]["cnt"];
	} else {
		$contact_cnt = 0;
	}

	// Count jabber addresses.
	$r = q("SELECT COUNT(1) AS `cnt` FROM `pconfig` WHERE `uid` = %d AND `cat` = 'jsxc' AND `k` LIKE 'id:%%' AND `v` LIKE '%%@%%'", local_user());
	if (dbm::is_result($r)) {
		$address_cnt = $r[0]["cnt"];
	} else {
		$address_cnt = 0;
	}

	if (!$activate) {
		// load scripts if not yet activated so that password can be saved
//		$a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jappixmini/jappix/php/get.php?t=js&amp;g=mini.xml"></script>'."\r\n";
//		$a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jappixmini/jappix/php/get.php?t=js&amp;f=presence.js~caps.js~name.js~roster.js"></script>'."\r\n";
//
//		$a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jappixmini/lib.js"></script>'."\r\n";

		$a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jsxc/jsxc/lib/jquery.fullscreen.js"></script>'."\r\n";
		$a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jsxc/jsxc/lib/jquery.slimscroll.js"></script>'."\r\n";
		$a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jsxc/jsxc/lib/jsxc.dep.js"></script>'."\r\n";
		$a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jsxc/jsxc/jsxc.js"></script>'."\r\n";
	}

	$a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jsxc/js/password.js"></script>'."\r\n";

	$tpl = get_markup_template( "settings.tpl", "addon/jsxc/" );
	$s .= replace_macros($tpl, array(
		'$heading' => "JSXC",

		'$username'    => htmlentities($username),
		'$server'      => htmlentities($server),
		'$defaultbosh' => $defaultbosh == "" ? true : false,
		'$bosh'        => htmlentities($bosh),
		'$password'    => $password,
		'$info_text'   => $info_text,
		'$address_cnt' => $address_cnt,
		'$submit'      => t('Save Settings'),
		'$add_contact' => t('Add contact'),

		'$activated'        => intval($activate)         ? 'checked="checked"'   : '',
		'$insertchat'       => !(intval($dontinsertchat) ? 'checked="checked"'   : ''),
		'$encrypt_checked'  => $encrypt                  ? 'checked="checked"'   : '',
		'$encrypt_disabled' => !$encrypt                 ? 'disabled="disabled"' : '',
		'$autoapprove'      => intval($autoapprove)      ? 'checked="checked"'   : '',
		'$autosubscribe'    => intval($autosubscribe)    ? 'checked="checked"'   : '',

		'$activate_label'      => t('Activate addon'),
		'$no_insert_label'     => t('Do <em>not</em> insert the jsxc Chat-Widget into the webinterface'),
		'$username_label'      => t('Jabber username'),
		'$server_label'        => t('Jabber server'),
		'$bosh_label'          => t('Jabber BOSH host'),
		'$password_label'      => t('Jabber password'),
		'$encrypt_label'       => t('Encrypt Jabber password with Friendica password (recommended)'),
		'$autosubscribe_label' => t('Subscribe to Friendica contacts automatically'),
		'$autoapprove_label'   => t('Approve subscription requests from Friendica contacts automatically'),
		'$f_password_label'    => t('Friendica password'),
		'$purge_label'         => t('Purge internal list of jabber addresses of contacts'),
	));
}

function jsxc_settings_post(&$a, &$b) {
	// Save addon settings for a user.

	if (!local_user()) {
		return;
	}
	$uid = local_user();

	if ($_POST['jsxc-submit']) {
		$encrypt = intval($b['jsxc-encrypt']);
		if ($encrypt) {
			// Check that Jabber password was encrypted with correct Friendica password.
			$friendica_password = trim($b['jsxc-friendica-password']);
			$encrypted = hash('whirlpool', $friendica_password);
			$r = q("SELECT * FROM `user` WHERE `uid` = $uid AND `password` = '%s'",
				dbesc($encrypted)
			);
			if (!dbm::is_result($r)) {
				info("Wrong friendica password!");
				return;
			}
		}

		$purge = intval($b['jsxc-purge']);

		$username = trim($b['jsxc-username']);
		$old_username = PConfig::get($uid, 'jsxc', 'username');
		if ($username != $old_username) {
			$purge = 1;
		}

		$server = trim($b['jsxc-server']);
		$old_server = PConfig::get($uid, 'jsxc', 'server');
		if ($server != $old_server) {
			$purge = 1;
		}

		PConfig::set($uid, 'jsxc', 'username', $username);
		PConfig::set($uid, 'jsxc', 'server', $server);
		PConfig::set($uid, 'jsxc', 'bosh', trim($b['jsxc-bosh']));
		PConfig::set($uid, 'jsxc', 'password', trim($b['jsxc-encrypted-password']));
		PConfig::set($uid, 'jsxc', 'autosubscribe', intval($b['jsxc-autosubscribe']));
		PConfig::set($uid, 'jsxc', 'autoapprove', intval($b['jsxc-autoapprove']));
		PConfig::set($uid, 'jsxc', 'activate', intval($b['jsxc-activate']));
		PConfig::set($uid, 'jsxc', 'dontinsertchat', intval($b['jsxc-dont-insertchat']));
		PConfig::set($uid, 'jsxc', 'encrypt', $encrypt);
		info('jsxc settings saved.');

		if ($purge) {
			q("DELETE FROM `pconfig` WHERE `uid` = $uid AND `cat` = 'jsxc' AND `k` LIKE 'id:%%'");
			info('List of addresses purged.');
		}
	}
}

function jsxc_script(&$a, &$s) {
	// Adds the script to the page header which starts jsxc.

	if (!local_user()) {
		return;
	}

	if ($_GET["mode"] == "minimal") {
		return;
	}

	$activate = PConfig::get(local_user(), 'jsxc', 'activate');
	$dontinsertchat = PConfig::get(local_user(), 'jsxc', 'dontinsertchat');
	if (!$activate || $dontinsertchat) {
		return;
	}

	$a->page['htmlhead'] .= '<link href="' . $a->get_baseurl() . '/addon/jsxc/jsxc/css/jquery-ui.min.css" media="all" rel="stylesheet" type="text/css" />'."\r\n";
	$a->page['htmlhead'] .= '<link href="' . $a->get_baseurl() . '/addon/jsxc/jsxc/css/magnific-popup.css" media="all" rel="stylesheet" type="text/css" />'."\r\n";
	$a->page['htmlhead'] .= '<link href="' . $a->get_baseurl() . '/addon/jsxc/jsxc/css/jsxc.css" media="all" rel="stylesheet" type="text/css" />'."\r\n";
	
	$a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jsxc/jsxc/lib/jquery.fullscreen.js"></script>'."\r\n";
	$a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jsxc/jsxc/lib/jquery.slimscroll.js"></script>'."\r\n";
	$a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jsxc/jsxc/lib/jsxc.dep.js"></script>'."\r\n";
	$a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jsxc/jsxc/jsxc.js"></script>'."\r\n";

	$a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jsxc/js/lib.js"></script>'."\r\n";

	$username = PConfig::get(local_user(), 'jsxc', 'username');
	$username = str_replace("'", "\\'", $username);
	$server = PConfig::get(local_user(), 'jsxc', 'server');
	$server = str_replace("'", "\\'", $server);
	$bosh = PConfig::get(local_user(), 'jsxc', 'bosh');
	$bosh = str_replace("'", "\\'", $bosh);
	$encrypt = PConfig::get(local_user(), 'jsxc', 'encrypt');
	$encrypt = intval($encrypt);
	$password = PConfig::get(local_user(), 'jsxc', 'password');
	$password = str_replace("'", "\\'", $password);

	$autoapprove = PConfig::get(local_user(), 'jsxc', 'autoapprove');
	$autoapprove = intval($autoapprove);
	$autosubscribe = PConfig::get(local_user(), 'jsxc', 'autosubscribe');
	$autosubscribe = intval($autosubscribe);

	// Set proxy if necessary.
	$use_proxy = Config::get('jsxc', 'bosh_proxy');
	if ($use_proxy) {
		$proxy = $a->get_baseurl().'/addon/jsxc/proxy.php';
	} else {
		$proxy = "";
	}

	// Get a list of jabber accounts of the contacts.
	$contacts = array();
	$uid = local_user();
	$rows = q("SELECT * FROM `pconfig` WHERE `uid` = $uid AND `cat` = 'jsxc' AND `k` LIKE 'id:%%'");
	foreach ($rows as $row) {
		$key = $row['k'];
		$pos = strpos($key, ":");
		$dfrn_id = substr($key, $pos+1);
		$r = q("SELECT `name` FROM `contact` WHERE `uid` = $uid AND (`dfrn-id` = '%s' OR `issued-id` = '%s')",
			dbesc($dfrn_id),
			dbesc($dfrn_id)
		);
		if (dbm::is_result($r)) {
			$name = $r[0]["name"];
		}

		$value = $row['v'];
		$pos = strpos($value, ":");
		$address = substr($value, $pos+1);
		if (!$address) {
			continue;
		}
		if (!$name) {
			$name = $address;
		}

		$contacts[$address] = $name;
	}
	$contacts_json = json_encode($contacts);
	$contacts_hash = sha1($contacts_json);

	// Get nickname.
	$r = q("SELECT `username` FROM `user` WHERE `uid` = $uid");
	$nickname = json_encode($r[0]["username"]);
	$groupchats = Config::get('jsxc', 'groupchats');
	//If $groupchats has no value jappix_addon_start will produce a syntax error.
	if (empty($groupchats)) {
		$groupchats = "{}";
	}

	// Add javascript to start JSXC Mini.
	$a->page['htmlhead'] .= "<script type=\"text/javascript\">
		jQuery(document).ready(function() {
			jsxc_addon_start('$server', '$username', '$proxy', '$bosh', $encrypt, '$password', $nickname, $contacts_json, '$contacts_hash', $autoapprove, $autosubscribe, $groupchats);
		});
	</script>";

	return;
}

function jsxc_login(&$a, &$o) {
	// Create client secret on login to be able to encrypt jabber passwords.

	// For setDB and str_sha1, needed by jsxc_addon_set_client_secret.
	//$a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jappixmini/jappix/php/get.php?t=js&amp;f=datastore.js~jsjac.js"></script>'."\r\n";

	// For jsxc_addon_set_client_secret.
	$a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jsxc/lib.js"></script>'."\r\n";

	// Save hash of password.
	$o = str_replace("<form ", "<form onsubmit=\"jsxc_addon_set_client_secret(this.elements['id_password'].value);return true;\" ", $o);
}

function jsxc_cron(&$a, $d) {
	// For autosubscribe/autoapprove, we need to maintain a list of jabber addresses of our contacts.
return; // DEV: leave here because we want to test other things - delete this later.
	Config::set("jsxc", "last_cron_execution", $d);

	// Go through list of users with jabber enabled.
	$users = q("SELECT `uid` FROM `pconfig` WHERE `cat` = 'jsxc' AND (`k` = 'autosubscribe' OR `k` = 'autoapprove') AND `v` = '1'");
	logger("jsxc: Update list of contacts' jabber accounts for ".count($users)." users.");

	if (!dbm::is_result($users)) {
		return;
	}

	foreach ($users as $row) {
		$uid = $row["uid"];

		// For each user, go through list of contacts.
		$contacts = q("SELECT * FROM `contact` WHERE `uid` = %d AND ((LENGTH(`dfrn-id`) AND LENGTH(`pubkey`)) OR (LENGTH(`issued-id`) AND LENGTH(`prvkey`))) AND `network` = '%s'",
			intval($uid), dbesc(NETWORK_DFRN));

		foreach ($contacts as $contact_row) {
			$request = $contact_row["request"];
			if (!$request) {
				continue;
			}

			$dfrn_id = $contact_row["dfrn-id"];
			if ($dfrn_id) {
				$key = $contact_row["pubkey"];
				$encrypt_func = openssl_public_encrypt;
				$decrypt_func = openssl_public_decrypt;
				$role = "prv";
			} else {
				$dfrn_id = $contact_row["issued-id"];
				$key = $contact_row["prvkey"];
				$encrypt_func = openssl_private_encrypt;
				$decrypt_func = openssl_private_decrypt;
				$role = "pub";
			}

			// Check if jabber address already present.
			$present = PConfig::get($uid, "jsxc", "id:".$dfrn_id);
			$now = intval(time());
			if ($present) {
				// $present has format "timestamp:jabber_address".
				$p = strpos($present, ":");
				$timestamp = intval(substr($present, 0, $p));

				// Do not re-retrieve jabber address if last retrieval
				// is not older than a week.
				if ($now-$timestamp < 3600*24*7) {
					continue;
				}
			}

			// Construct base retrieval address.
			$pos = strpos($request, "/dfrn_request/");
			if ($pos === false) {
				continue;
			}

			$base = substr($request, 0, $pos)."/jsxc?role=$role";

			// Construct own address.
			$username = PConfig::get($uid, 'jsxc', 'username');
			if (!$username) {
				continue;
			}
			$server = PConfig::get($uid, 'jsxc', 'server');
			if (!$server) {
				continue;
			}

			$address = $username."@".$server;

			// Sign address.
			$signed_address = "";
			$encrypt_func($address, $signed_address, $key);

			// Construct request url.
			$signed_address_hex = bin2hex($signed_address);
			$url = $base."&signed_address=$signed_address_hex&dfrn_id=".urlencode($dfrn_id);

			try {
				// Send request.
				$answer_json = fetch_url($url);

				// Parse answer.
				$answer = json_decode($answer_json);
				if ($answer->status != "ok") {
					throw new Exception();
				}

				$encrypted_address_hex = $answer->encrypted_address;
				if (!$encrypted_address_hex) {
					throw new Exception();
				}

				$encrypted_address = hex2bin($encrypted_address_hex);
				if (!$encrypted_address) {
					throw new Exception();
				}

				// Decrypt address.
				$decrypted_address = "";
				$decrypt_func($encrypted_address, $decrypted_address, $key);
				if (!$decrypted_address) {
					throw new Exception();
				}
			} catch (Exception $e) {
				$decrypted_address = "";
			}

			// Save address.
			PConfig::set($uid, "jsxc", "id:$dfrn_id", "$now:$decrypted_address");
		}
	}
}

function jappixmini_download_source(&$a, &$b) {
	// Jappix Mini source download link on About page.

	$b .= '<h1>Jappix Mini</h1>';
	$b .= '<p>This site uses the jappixmini addon, which includes Jappix Mini by the <a href="'.$a->get_baseurl().'/addon/jappixmini/jappix/AUTHORS">Jappix authors</a> and is distributed under the terms of the <a href="'.$a->get_baseurl().'/addon/jappixmini/jappix/COPYING">GNU Affero General Public License</a>.</p>';
	$b .= '<p>You can download the <a href="'.$a->get_baseurl().'/addon/jappixmini.tgz">source code of the addon</a>. The rest of Friendica is distributed under compatible licenses and can be retrieved from <a href="https://github.com/friendica/friendica">https://github.com/friendica/friendica</a> and <a href="https://github.com/friendica/friendica-addons">https://github.com/friendica/friendica-addons</a></p>';
}
