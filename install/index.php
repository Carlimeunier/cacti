<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

define('IN_CACTI_INSTALL', 1);

include_once('../include/global.php');
include_once('../lib/utility.php');
include_once('./functions.php');

set_default_action();

if (get_request_var('action') == 'testdb') {
	test_database_connection();
	exit;
}

/* allow the upgrade script to run for as long as it needs to */
ini_set('max_execution_time', '0');

$cacti_versions = array('0.8', '0.8.1', '0.8.2', '0.8.2a', '0.8.3', '0.8.3a', '0.8.4', '0.8.5', '0.8.5a',
	'0.8.6', '0.8.6a', '0.8.6b', '0.8.6c', '0.8.6d', '0.8.6e', '0.8.6f', '0.8.6g', '0.8.6h', '0.8.6i', '0.8.6j', '0.8.6k',
	'0.8.7', '0.8.7a', '0.8.7b', '0.8.7c', '0.8.7d', '0.8.7e', '0.8.7f', '0.8.7g', '0.8.7h', '0.8.7i',
	'0.8.8', '0.8.8a', '0.8.8b', '0.8.8c', '0.8.8d', '0.8.8e', '0.8.8f', '0.8.8g', '0.8.8h', '1.0.0');

$old_cacti_version = db_fetch_cell('SELECT cacti FROM version');

/* try to find current (old) version in the array */
$old_version_index = array_search($old_cacti_version, $cacti_versions);

/* do a version check */
if ($old_cacti_version == $config['cacti_version']) {
	print '<p style="font-family: Verdana, Arial; font-size: 16px; font-weight: bold; color: red;">' . __('Error') . '</p>
		<p style="font-family: Verdana, Arial; font-size: 12px;">' 
		. __('This installation is already up-to-date. Click <a href="%s">here</a> to use Cacti.', '../index.php') . '</p>';
	exit;
}elseif (preg_match('/^0\.6/', $old_cacti_version)) {
	print '<p style="font-family: Verdana, Arial; font-size: 16px; font-weight: bold; color: red;">' . __('Error') . '</p>
		<p style="font-family: Verdana, Arial; font-size: 12px;">' 
		. __('You are attempting to install Cacti %s onto a 0.6.x database. To continue, you must create a new database, import "cacti.sql" into it, and update "include/config.php" to point to the new database.', $config['cacti_version']) . '</p>';
	exit;
}elseif (empty($old_cacti_version)) {
	print '<p style="font-family: Verdana, Arial; font-size: 16px; font-weight: bold; color: red;">' . __('Error') . '</p>
		<p style="font-family: Verdana, Arial; font-size: 12px;">' 
		. __("You have created a new database, but have not yet imported the 'cacti.sql' file. At the command line, execute the following to continue:</p><p><pre>mysql -u %s -p %s < cacti.sql</pre></p><p>This error may also be generated if the cacti database user does not have correct permissions on the Cacti database. Please ensure that the Cacti database user has the ability to SELECT, INSERT, DELETE, UPDATE, CREATE, ALTER, DROP, INDEX on the Cacti database.", $database_username, $database_default) . '</p>';

	print '<p>' . __("In Cacti %s, you must also import MySQL timezone information into MySQL and grant the Cacti user SELECT access to the mysql.time_zone_name table", $config['cacti_version']) . '</p>';

	if ($config['cacti_server_os'] == 'unix') {
		print '<p>' . __("On Linux/UNIX, do the following:") . '</p><p><pre>' . __("mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root -p mysql") . "\n";
	}else{
		print '<p>' . __("On Windows, you must follow the instructions here <a target='_blank' href='https://dev.mysql.com/downloads/timezones.html'>Time zone description table</a>.  Once that is complete, you can issue the following command to grant the Cacti user access to the tables:") . '</p><p><pre>';
	}
	print __("GRANT SELECT ON mysql.time_zone_name to '%s'@'localhost' IDENTIFIED BY '%s'", $database_username, $database_password) . '</pre></p>';

	exit;
}

/* default value for this variable */
if (!isset_request_var('install_type')) {
	set_request_var('install_type', '0');
}

/* defaults for the install type dropdown */
if ($old_cacti_version == 'new_install') {
	$default_install_type = '1';
}else{
	$default_install_type = '3';
}

/* pre-processing that needs to be done for each step */
if (isset_request_var('step') && get_filter_request_var('step') > 0) {
	$step = get_filter_request_var('step');
	
	switch($step) {
	case '1':
		/* license&welcome - send to checkdependencies */
		$previous_step = 0;
		$step++;
		break;
	case '2':
		$previous_step = 1;
		/* checkdependencies - send to install/upgrade */	
		$step++;
		break;
	case '3':
		$previous_step = 2;
		if (get_filter_request_var('install_type') == '1') {
			/* install - New Primary Server */
			$step = 4;
		}elseif (get_filter_request_var('install_type') == '2') {
			/* install - New Remote Poller */
			$step = 10;
		}elseif (get_filter_request_var('install_type') == '3') {
			/* install/upgrade - if user chooses "Upgrade" send to upgrade */
			$step = 8;
		}
		break;
	case '4':
		$previous_step = 4;
		/* settingscheck - send to settings-install */
		$step = 5;
		break;
	case '5':
		$previous_step = 5;
		/* settings-install - send to template-import */
		$step = 6;
		break;
	case '6':
		$previous_step = 6;
		/* template-import - send to installfinal */
		$step = 7;
		break;
	case '7':
		break;
	case '8':
		$previous_step = 8;
		/* upgrade - if user upgrades send to settingscheck */
		if ($old_version_index <= array_search('0.8.5a', $cacti_versions)) {
			/* upgrade - if user runs old version send to upgrade-oldversion*/
			$step = 9;
		}else{
			$step = 4;
		}
		break;
	case '9':
		$previous_step = 8;
		/* upgrade-oldversion - if user upgrades from old version send to settingscheck */
		$step = 4;
		break;
	case '10':
		$previous_step = 3;
		$step = 4;
		break;
	}
} else {
	$previous_step = 0;
	$step = 1;
}

/* installfinal - Install templates, change cacti version and send to login page */
if ($step == '7') {
	include_once('../lib/data_query.php');
	include_once('../lib/utility.php');
	
	/* look for templates that have been checked for install */
		$install = Array();
		foreach ($_POST as $post => $v) {
			if (substr($post, 0, 4) == 'chk_' && is_numeric(substr($post, 4))) {
				$install[] = substr($post, 4);
			}
		}
		/* install templates */
		$templates = plugin_setup_get_templates(1);
		if (!empty($install)) {
			foreach ($install as $i) {
				plugin_setup_install_template($templates[$i]['filename'], 1, $templates[$i]['interval']);
			}
		}
	
	/* clear session */
	
	setcookie(session_name(),'',time() - 3600,$config['url_path']);

	kill_session_var('sess_config_array');
	kill_session_var('sess_host_cache_array');

	/* pre-fill poller cache with initial data on a new install only */
	if ($old_cacti_version == 'new_install') {
		/* just in case we have hard drive graphs to deal with */
		$host_id = db_fetch_cell("SELECT id FROM host WHERE hostname='127.0.0.1'");

		if (!empty($host_id)) {
			run_data_query($host_id, 6);
		}

		/* it's always a good idea to re-populate the poller cache to make sure everything is refreshed and up-to-date */ 	 
		repopulate_poller_cache(); 	 

		/* fill up the snmpcache */
		snmpagent_cache_rebuilt();
		
		/* generate RSA key pair */
		rsa_check_keypair();
	}
	
	/* change cacti version */
	db_execute('DELETE FROM version');
	db_execute("INSERT INTO version (cacti) VALUES ('" . $config["cacti_version"] . "')");

	/* send to login page */
	header ('Location: ../index.php');
	exit;

/* upgrade */
}elseif (($step == '8') && (get_filter_request_var('install_type') == '3')) {
	/* if the version is not found, die */
	if (!is_int($old_version_index)) {
		print "	<p style='font-family: Verdana, Arial; font-size: 16px; font-weight: bold; color: red;'>" . __('Error') . "</p>
			<p style='font-family: Verdana, Arial; font-size: 12px;'>"
			. __('Invalid Cacti version <strong>%1$s</strong>, cannot upgrade to <strong>%2$s</strong>', $old_cacti_version, $config['cacti_version']) . "</p>";
		exit;
	}

	/* loop from the old version to the current, performing updates for each version in between */
	for ($i=($old_version_index+1); $i<count($cacti_versions); $i++) {
		$upgrade_version = $cacti_versions[$i];
		if ($cacti_versions[$i] == '0.8.1') {
			include ('0_8_to_0_8_1.php');
			upgrade_to_0_8_1();
		}elseif ($cacti_versions[$i] == '0.8.2') {
			include ('0_8_1_to_0_8_2.php');
			upgrade_to_0_8_2();
		}elseif ($cacti_versions[$i] == '0.8.2a') {
			include ('0_8_2_to_0_8_2a.php');
			upgrade_to_0_8_2a();
		}elseif ($cacti_versions[$i] == '0.8.3') {
			include ('0_8_2a_to_0_8_3.php');
			include_once('../lib/utility.php');
			upgrade_to_0_8_3();
		}elseif ($cacti_versions[$i] == '0.8.4') {
			include ('0_8_3_to_0_8_4.php');
			upgrade_to_0_8_4();
		}elseif ($cacti_versions[$i] == '0.8.5') {
			include ('0_8_4_to_0_8_5.php');
			upgrade_to_0_8_5();
		}elseif ($cacti_versions[$i] == '0.8.6') {
			include ('0_8_5a_to_0_8_6.php');
			upgrade_to_0_8_6();
		}elseif ($cacti_versions[$i] == '0.8.6a') {
			include ('0_8_6_to_0_8_6a.php');
			upgrade_to_0_8_6a();
		}elseif ($cacti_versions[$i] == '0.8.6d') {
			include ('0_8_6c_to_0_8_6d.php');
			upgrade_to_0_8_6d();
		}elseif ($cacti_versions[$i] == '0.8.6e') {
			include ('0_8_6d_to_0_8_6e.php');
			upgrade_to_0_8_6e();
		}elseif ($cacti_versions[$i] == '0.8.6g') {
			include ('0_8_6f_to_0_8_6g.php');
			upgrade_to_0_8_6g();
		}elseif ($cacti_versions[$i] == '0.8.6h') {
			include ('0_8_6g_to_0_8_6h.php');
			upgrade_to_0_8_6h();
		}elseif ($cacti_versions[$i] == '0.8.6i') {
			include ('0_8_6h_to_0_8_6i.php');
			upgrade_to_0_8_6i();
		}elseif ($cacti_versions[$i] == '0.8.7') {
			include ('0_8_6j_to_0_8_7.php');
			upgrade_to_0_8_7();
		}elseif ($cacti_versions[$i] == '0.8.7a') {
			include ('0_8_7_to_0_8_7a.php');
			upgrade_to_0_8_7a();
		}elseif ($cacti_versions[$i] == '0.8.7b') {
			include ('0_8_7a_to_0_8_7b.php');
			upgrade_to_0_8_7b();
		}elseif ($cacti_versions[$i] == '0.8.7c') {
			include ('0_8_7b_to_0_8_7c.php');
			upgrade_to_0_8_7c();
		}elseif ($cacti_versions[$i] == '0.8.7d') {
			include ('0_8_7c_to_0_8_7d.php');
			upgrade_to_0_8_7d();
		}elseif ($cacti_versions[$i] == '0.8.7e') {
			include ('0_8_7d_to_0_8_7e.php');
			upgrade_to_0_8_7e();
		}elseif ($cacti_versions[$i] == '0.8.7f') {
			include ('0_8_7e_to_0_8_7f.php');
			upgrade_to_0_8_7f();
		}elseif ($cacti_versions[$i] == '0.8.7g') {
			include ('0_8_7f_to_0_8_7g.php');
			upgrade_to_0_8_7g();
		}elseif ($cacti_versions[$i] == '0.8.7h') {
			include ('0_8_7g_to_0_8_7h.php');
			upgrade_to_0_8_7h();
		}elseif ($cacti_versions[$i] == '0.8.7i') {
			include ('0_8_7h_to_0_8_7i.php');
			upgrade_to_0_8_7i();
		}elseif ($cacti_versions[$i] == '0.8.8') {
			include ('0_8_7i_to_0_8_8.php');
			upgrade_to_0_8_8();
		}elseif ($cacti_versions[$i] == '0.8.8a') {
			include ('0_8_8_to_0_8_8a.php');
			upgrade_to_0_8_8a();
		}elseif ($cacti_versions[$i] == '0.8.8b') {
			include ('0_8_8a_to_0_8_8b.php');
			upgrade_to_0_8_8b();
		}elseif ($cacti_versions[$i] == '0.8.8c') {
			include ('0_8_8b_to_0_8_8c.php');
			upgrade_to_0_8_8c();
		}elseif ($cacti_versions[$i] == '0.8.8d') {
			include ('0_8_8c_to_0_8_8d.php');
			upgrade_to_0_8_8d();
		}elseif ($cacti_versions[$i] == '0.8.8e') {
			include ('0_8_8d_to_0_8_8e.php');
			upgrade_to_0_8_8e();
		}elseif ($cacti_versions[$i] == '0.8.8f') {
			include ('0_8_8e_to_0_8_8f.php');
			upgrade_to_0_8_8f();
		}elseif ($cacti_versions[$i] == '0.8.8g') {
			include ('0_8_8f_to_0_8_8g.php');
			upgrade_to_0_8_8g();
		}elseif ($cacti_versions[$i] == '0.8.8h') {
			include ('0_8_8g_to_0_8_8h.php');
			upgrade_to_0_8_8h();
		}elseif ($cacti_versions[$i] == '1.0.0') {
			include ('0_8_8h_to_1_0_0.php');
			upgrade_to_1_0_0();
		}
	}

	foreach ($plugins_integrated as $plugin) {
		if (api_plugin_is_enabled ($plugin)) {
			api_plugin_remove_hooks ($plugin);
			api_plugin_remove_realms ($plugin);
			db_execute("DELETE FROM plugin_config WHERE directory = '$plugin'");
		}
	}
}

if (isset_request_var('database_hostname')) {
	$_SESSION['database_type']     = 'mysql';
	$_SESSION['database_default']  = get_nfilter_request_var('database_default');
	$_SESSION['database_hostname'] = get_nfilter_request_var('database_hostname');
	$_SESSION['database_username'] = get_nfilter_request_var('database_username');
	$_SESSION['database_password'] = get_nfilter_request_var('database_password');
	$_SESSION['database_port']     = get_filter_request_var('database_port');
	$_SESSION['database_ssl']      = isset_request_var('database_ssl') ? true:false;
}

$enabled = '1';

?>
<!DOCTYPE html>
<html>
<head>
	<title>Cacti Server Installation/Upgrade</title>
	<meta http-equiv='Content-Type' content='text/html;charset=utf-8'>
	<link href='<?php echo $config['url_path']; ?>include/themes/modern/main.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/themes/modern/jquery.zoom.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/themes/modern/jquery-ui.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/themes/modern/default/style.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/themes/modern/jquery.multiselect.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/themes/modern/jquery.timepicker.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/themes/modern/jquery.colorpicker.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/themes/modern/pace.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/fa/css/font-awesome.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>images/favicon.ico' rel='shortcut icon'>
	<link rel='icon' type='image/gif' href='<?php echo $config['url_path']; ?>images/cacti_logo.gif' sizes='96x96'>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery-migrate.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery-ui.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.cookie.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.storageapi.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jstree.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.hotkeys.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.tablednd.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.zoom.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.multiselect.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.multiselect.filter.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.timepicker.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.colorpicker.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.tablesorter.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.metadata.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.sparkline.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/Chart.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/dygraph-combined.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/pace.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/realtime.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/layout.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/themes/modern/main.js'></script>
	<style type='text/css'>
	input, select {
		font-size: 12px;
		padding: 0.4em;
	}
	</style>
</head>

<body>

<form method='post' action='index.php'>

<table style='margin-left:auto;margin-right:auto;width:80%;text-align:center;'>
	<tr><td height='40'></td></tr>
	<tr>
		<td style='width:100%;vertical-align:middle'>
			<table class='cactiTable' style='border:1px solid rgba(98,125,77,1)'>
				<tr class='cactiTableTitleRow'>
					<td class='textHeaderDark'><strong><?php print __('Cacti Installation Wizard'); ?></strong></td>
				</tr>
				<tr class='installArea'>
					<td>
						
					<?php	
					/* license&welcome */
					if ($step == '1') {
						print '<h3>' . __('License Agreement') . '</h3>';

						print '<p>' . __('Thanks for taking the time to download and install Cacti, the complete graphing solution for your network. Before you can start making cool graphs, there are a few pieces of data that Cacti needs to know.') . '</p>';
						print '<p>' . __('Make sure you have read and followed the required steps needed to install Cacti before continuing. Install information can be found for <a href="%1$s">Unix</a> and <a href="%2$s">Win32</a>-based operating systems.', '../docs/html/install_unix.html', '../docs/html/install_windows.html') . '</p>';
						print '<p>' . __('Also, if this is an upgrade, be sure to reading the <a href="%s">Upgrade</a> information file.', '../docs/html/upgrade.html') . '</p>';
						print '<p>' . __('Cacti is licensed under the GNU General Public License, you must agree to its provisions before continuing:') . "</p>";
					?>
						<p class='code'>This program is free software; you can redistribute it and/or
						modify it under the terms of the GNU General Public License
						as published by the Free Software Foundation; either version 2
						of the License, or (at your option) any later version.</p>

						<p class='code'>This program is distributed in the hope that it will be useful,
						but WITHOUT ANY WARRANTY; without even the implied warranty of
						MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
						GNU General Public License for more details.</p>

						<span><input type='checkbox' id='accept' name='accept'></span><span><label for='accept'>Accept GPL License Agreement</label></span><br><br>
					<?php 	
					/* checkdependencies */
					}elseif ($step == '2') { 
						print '<h3>' . __('Pre-installation Checks') .'</h3>';
						print __('Cacti requries several PHP Modules to be installed to work properly. If any of these are not installed, you will be unable to continue the installation until corrected. In addition, for optimal system performance Cacti should be run with certain MySQL system variables set.  Please follow the MySQL recommendations at your discretion.  Always seek the MySQL documentation if you have any questions.') . '<br><br>';

						print '<br>' . __('The following PHP extensions are mandatory, and MUST be installed before continuing your Cacti install.') . '<br><br>';

						html_start_box('<strong> ' . __('Required PHP Modules') . '</strong>', '30', 0, '', '', false);
						html_header( array( __('Name'), __('Required'), __('Installed') ) );
					
						form_selectable_cell(__('PHP Version'), '');
						form_selectable_cell('5.2.0+', '');
						form_selectable_cell((version_compare(PHP_VERSION, '5.2.0', '<') ? "<font color=red>" . PHP_VERSION . "</font>" : "<font color=green>" . PHP_VERSION . "</font>"), '');
						form_end_row();

						$extensions = array( 
							array('name' => 'posix',     'installed' => false),
							array('name' => 'session',   'installed' => false),
							array('name' => 'sockets',   'installed' => false),
							array('name' => 'PDO',       'installed' => false),
							array('name' => 'pdo_mysql', 'installed' => false),
							array('name' => 'xml',       'installed' => false),
							array('name' => 'ldap',      'installed' => false),
							array('name' => 'mbstring',  'installed' => false),
							array('name' => 'pcre',      'installed' => false),
							array('name' => 'json',      'installed' => false),
							array('name' => 'openssl',   'installed' => false),
							array('name' => 'gd',        'installed' => false),
							array('name' => 'zlib',      'installed' => false)
						);

						$ext = verify_php_extensions($extensions);
						$enabled = '1';
						foreach ($ext as $id =>$e) {
							form_alternate_row('line' . $id);
							form_selectable_cell($e['name'], '');
							form_selectable_cell('<font color=green>' . __('Yes') . '</font>', '');
							form_selectable_cell(($e['installed'] ? '<font color=green>' . __('Yes') . '</font>' : '<font color=red>' . __('No') . '</font>'), '');
							form_end_row();
							if (!$e['installed']) $enabled = '0';
						}
						html_end_box(false);

						print '<br>' . __('The following PHP extensions are recommended, and should be installed before continuing your Cacti install.') . '<br><br>';
						$extensions = array(
							array('name' => 'snmp', 'installed' => false),
							array('name' => 'gmp', 'installed' => false)
						);

						$ext = verify_php_extensions($extensions);
						html_start_box('<strong> ' . __('Optional Modules') . '</strong>', '30', 0, '', '', false);
						html_header( array( __('Name'), __('Required'), __('Installed') ) );
						foreach ($ext as $id => $e) {
							form_alternate_row('line' . $id, true);
							form_selectable_cell($e['name'], '');
							form_selectable_cell('<font color=green>' . __('Yes') . '</font>', '');
							form_selectable_cell(($e['installed'] ? '<font color=green>' . __('Yes') . '</font>' : '<font color=red>' . __('No') . '</font>'), '');
							form_end_row();
						}
						html_end_box();

						print '<br>' . __('These MySQL performance tuning settings will help your Cacti system perform better without issues for a longer time.') . '<br><br>';

						html_start_box('<strong> ' . __('Recommended MySQL System Variable Settings') . '</strong>', '30', 0, '', '', false);
						utilities_get_mysql_recommendations();
						html_end_box(false);
					/* install/upgrade */
					}elseif ($step == '3') {
						print '<h3>' . __('Installation Type') . '</h3>';

						print '<h4>' . __('Please select the type of installation') . '</h4>';
						print '<p>' . __('You have three Cacti installation options to choose from:') . '</p>';

						print '<p><ul>';
						print '<li><b><i>' . __('New Primary Server') . '</i></b> - ' . __('Choose this for the Primary Cacti Web Site.') . '</li>';
						print '<li><b><i>' . __('New Remote Poller')  . '</i></b> - ' . __('Remote Pollers are used to access networks that are not readily accessible to the Primary Cacti Web Site.') . '</li>';
						print '<li><b><i>' . __('Upgrade Previous Cacti') . '</i></b> - ' . __('Use this option to upgrade a previous release of Cacti') . '</li>';
						print '</ul></p>';

						print '<p>
							<select id="install_type" name="install_type">
								<option value="1"' . (($default_install_type == '1') ? ' selected' : '') . '>' . __('New Primary Server') . '</option>
								<option value="2"' . (($default_install_type == '2') ? ' selected' : '') . '>' . __('New Remote Poller') . '</option>
								<option value="3"' . (($default_install_type == '3') ? ' selected' : '') . '>' . __('Upgrade Previous Cacti') . '</option>
							</select>
						</p>';

						if ($default_install_type == '3') {
							print '<p> <font color="#FF0000">' . __('WARNING - If you are upgrading from a previous version please close all Cacti browser sessions and clear cache before continuing') . '</font></p>';
						} 
				
						print '<p>' . __('The following information has been determined from Cacti\'s configuration file. If it is not correct, please edit "include/config.php" before continuing.') . '</p>';

						print '<div id="local_database" style="display:none;">';

						print '<h4>' . __('Local Cacti database connection information') . '</h4>';
						print '<p class="code">'
							. __('Database: <b>%s</b>', $database_default) . '<br>'
							. __('Database User: <b>%s</b>', $database_username) . '<br>'
							. __('Database Hostname: <b>%s</b>', $database_hostname) . '<br>'
							. __('Port: <b>%s</b>', $database_port) . '<br>'
							. __('Server Operating System Type: <b>%s</b>', $config['cacti_server_os']) . '<br>'
						. '</p>';

						print '</div>';

						print '<div id="remote_database" style="display:none;">';

						if (is_writable($config['base_path'] . '/include/config.php')) {
							$good_write = true;
						}else{
							$good_write = false;
						}

						if (isset($rdatabase_default) && 
							isset($rdatabase_username) && 
							isset($rdatabase_hostname) &&
							isset($rdatabase_port)) {
							$remote_good = true;
						}else{
							$remote_good = false;
						}

						if ($remote_good && $good_write) {
							print '<h4>' . __('Remote Poller Cacti database connection information') . '</h4>';
							print '<p class="code">'
								. __('Database: <b>%s</b>', $rdatabase_default) . '<br>'
								. __('Database User: <b>%s</b>', $rdatabase_username) . '<br>'
								. __('Database Hostname: <b>%s</b>', $rdatabase_hostname) . '<br>'
								. __('Port: <b>%s</b>', $rdatabase_port) . '<br>'
								. __('Server Operating System Type: <b>%s</b>', $config['cacti_server_os']) . '<br>'
							. '</p>';
						}else{
							print '<h4>' . __('Remote Poller Cacti database connection information') . '</h4>';

							if (!$good_write) {
								print '<p class="textError">' . __('ERROR: Your config.php file must be writable by 
									the web server during install in order to configure the Remote poller.  Once 
									installation is complete, you must set this file to Read Only to prevent 
									possible security issues.');
								print '</p>';
							}
	
							if (!$remote_good) {
								print '<p class="textError">' . __('ERROR: Your Remote Cacti Poller information has not 
									been included in your config.php file.  Please review the config.php.dist, and 
									set the variables: <i>$rdatabase_default, $rdatabase_username</i>, etc.  
									These variables must be set and point back to your Primary Cacti database server.
									Correct this and try again.') . '</p>';
							}
						}

						print '</div>';

						print '<h4 style="height:20px;margin:4px;" id="results"></h4>';

						?>
						<script type="text/javascript">
						$(function() {
							$('#install_type').change(function() {
								switch($(this).val()) {
								case '1':
									$('#local_database').show();
									$('#remote_database').hide();
									$('#test').hide();
									$('#results').html('');
									$('#next').prop('disabled', false);
									break;
								case '2':
									$('#local_database').show();
									$('#remote_database').show();
									$('#results').html('');

									<?php if ($remote_good) {?>
									$('#test').button().show();
									<?php } else { ?>
									$('#next').prop('disabled', true);
									<?php } ?>
									break;
								case '3':
									$('#local_database').hide();
									$('#remote_database').hide();
									$('#test').hide();
									$('#results').html('');
									$('#next').prop('disabled', false);
									break;
								}
							}).change();

							$('#test').click(function() {
								testRemoteDatabase();
							});
						});

						function testRemoteDatabase() {
							strURL  = 'index.php';
							strURL += '?action=testdb';
							strURL += '&database_type=<?php print (isset($rdatabase_type) ? $rdatabase_type:'');?>';
							strURL += '&database_default=<?php print (isset($rdatabase_default) ? $rdatabase_default:'');?>';
							strURL += '&database_username=<?php print (isset($rdatabase_username) ? $rdatabase_username:'');?>';
							strURL += '&database_password=<?php print (isset($rdatabase_password) ? $rdatabase_password:'');?>';
							strURL += '&database_port=<?php print (isset($rdatabase_port) ? $rdatabase_port:'');?>';
							strURL += '&database_ssl=<?php print (isset($rdatabase_sll) ? $rdatabase_ssl:'');?>';

							$.get(strURL, function(data) {
								$('#results').html('<?php print __('Remote Database: ');?>'+data);
							});
						}
						</script>
						<?php
				 	/* settingscheck */
					}elseif ($step == '4') {
						print '<h3>' . __('Critical Paths') . '</h3>';

						print '<p>' . __('Make sure all of these values are correct before continuing.') . '</p>';						
						$i = 0;
						$input = install_file_paths();
						/* find the appropriate value for each 'config name' above by config.php, database,
						 * or a default for fall back */
						while (list($name, $array) = each($input)) {
							if (isset($input[$name])) {
								$current_value = $array['default'];

								/* run a check on the path specified only if specified above, then fill a string with
								the results ('FOUND' or 'NOT FOUND') so they can be displayed on the form */
								$form_check_string = '';

								/* draw the acual header and textbox on the form */
								print '<p><strong>' . $array['friendly_name'] . '</strong>';

								if (!empty($array['friendly_name'])) {
									print ': ' . $array['description'];
								}else{
									print '<strong>' . $array['description'] . '</strong>';
								}

								print '<br>';

								switch ($array['method']) {
								case 'textbox':
									form_text_box($name, $current_value, '', '', '40', 'text');
									break;
								case 'filepath':
									form_filepath_box($name, $current_value, '', '', '40', 'text');
									break;
								case 'drop_array':
									form_dropdown($name, $array['array'], '', '', $current_value, '', '');
									break;
								}

								print '<br></p>';
							}

							$i++;
						}
						
						print '<p><strong><font color="#FF0000">';

						print __('NOTE:') . '</font></strong> ' . __('Once you click "Finish", all of your 
							settings will be saved and your database will be upgraded if this is an upgrade.');
						print __('You can change any of the settings on this screen at a later time by going 
							to "Cacti Settings" from within Cacti.'); 

						print '</p>';
				 	/* settings-install */
					}elseif ($step == '5') { 
						include_once('../lib/data_query.php');
						include_once('../lib/utility.php');

						$i = 0;

						$input = install_file_paths();
						/* get all items on the form and write values for them  */
						while (list($name, $array) = each($input)) {
							if (isset_request_var($name)) {
								db_execute_prepared("REPLACE INTO settings (name,value) VALUES (?, ?)", array($name, get_nfilter_request_var($name)));
							}
						}
							
						/* Print message and error logs */
						print ' <p><b>' . __('Settings installed') . '</b><br><br></p>';
							
						/* Check if /resource is writable */
						print '<p>'. __('Next step is template installation. For Template Installation to work the folders below need to be writable by the webserver.') . '</p>';
						print '<p>' . __('If you dont want to install any templates now you can skip this and import them later.') . '</p>';
													
						if (is_writable('../resource/snmp_queries')) {
							print ' <p>'. $config['base_path'] . '/resource/snmp_queries is <font color="#008000">' . __('writable') . '</font></p>';
						} else {
							print ' <p>'. $config['base_path'] . '/resource/snmp_queries is <font color="#FF0000">' . __('not writable') . '</font></p>';
							$writable=FALSE;
						}
							
						if (is_writable('../resource/script_server')) {
							print ' <p>'. $config['base_path'] . '/resource/script_server is <font color="#008000">' . __('writable') . '</font></p>';
						} else {
							print ' <p>'. $config['base_path'] . '/resource/script_server is <font color="#FF0000">' . __('not writable') . '</font></p>';
							$writable=FALSE;
						}

						if (is_writable('../resource/script_queries')) {
							print ' <p>'. $config['base_path'] . '/resource/script_queries is <font color="#008000">' . __('writable') . '</font></p>';
						} else {
							print ' <p>'. $config['base_path'] . '/resource/script_queries is <font color="#FF0000">' . __('not writable') . '</font></p>';
							$writable=FALSE;
						}

						/* Print help message for unix and windows if directory is not writable */
						if (($config['cacti_server_os'] == "unix") && isset($writable)) {
							print __('Make sure your webserver has read and write access to the entire folder structure.<br> Example: chown -R apache.apache %s/resource/', $config['base_path']) . '<br>';
							print __('For SELINUX-users make sure that you have the correct permissions or set "setenforce 0" temporarily.') . '<br><br>';
						}elseif (($config['cacti_server_os'] == "win32") && isset($writable)){
							print __('Check Permissions');
						}else {
							print '<font color="#008000">' . __('All folders are writable') . '</font><br><br>';
						}

					/* template-import */
					}elseif ($step == '6') {
						print '<p>' . __('Make sure all of these values are correct before continuing.') . '</p>';
						print '<h1>' . __('Template Setup') . '</h1>';
						print __('Templates allow you to monitor and graph a vast assortment of data within Cacti. While the base Cacti install provides basic templates for most devices, you can select a few extra templates below to include in your install.') . '<br><br>';
						print '<form name="chk" method="post" action="start.php">';

						$templates = plugin_setup_get_templates();

						html_start_box('<strong>' . __('Templates') . '</strong>', '100%', '3', 'center', '', '');
						html_header_checkbox( array( __('Name'), __('Description'), __('Author'), __('Homepage') ) );
						foreach ($templates as $id => $p) {
							form_alternate_row('line' . $id, true);
							form_selectable_cell($p['name'], $id);
							form_selectable_cell($p['description'], $id);
							form_selectable_cell($p['author'], $id);
							if ($p['homepage'] != '') {
								form_selectable_cell('<a href="'. $p['homepage'] . '" target=_new>' . $p['homepage'] . '</a>', $id);
							} else {
								form_selectable_cell('', $id);
							}
							form_checkbox_cell($p['name'], $id);
							form_end_row();
							html_end_box(false);
						}
					
					/* upgrade */
					}elseif ($step == '8') {
						print '<p>' . __('Upgrade results:') . '</p>';

						$current_version  = '';
						$upgrade_results = '';
						$failed_sql_query = false;

						$sqltext = array();
						$sqltext[0] = '<span style="color: red; font-weight: bold; font-size: 12px;">' . __('[Fail]') . '</span>&nbsp;';
						$sqltext[1] = '<span style="color: green; font-weight: bold; font-size: 12px;">' . __('[Success]') . '</span>&nbsp;';
						$sqltext[2] = '<span style="color: grey; font-weight: bold; font-size: 12px;">' . __('[Not Ran]') . '</span>&nbsp;';

						if (isset($_SESSION['sess_sql_install_cache'])) {
							while (list($index, $arr1) = each($_SESSION['sess_sql_install_cache'])) {
								while (list($version, $arr2) = each($arr1)) {
									while (list($status, $sql) = each($arr2)) {
										if ($current_version != $version) {
											$version_index = array_search($version, $cacti_versions);
											$upgrade_results .= '<p><strong>' . (isset($cacti_versions{$version_index-1}) ? $cacti_versions{$version_index-1}:'')  . ' -> ' . $cacti_versions{$version_index} . "</strong></p>\n";
										}

										$upgrade_results .= "<p class='code'>" . $sqltext[$status] . nl2br($sql) . "</p>\n";

										/* if there are one or more failures, make a note because we are going to print
										out a warning to the user later on */
										if ($status == 0) {
											$failed_sql_query = true;
										}

										$current_version = $version;
									}
								}
							}

							kill_session_var('sess_sql_install_cache');
						}else{
							print '<em>' . __('No SQL queries have been executed.') . '</em>';
						}

						if ($failed_sql_query == true) {
							print '<p><strong><font color="#FF0000">' . __('WARNING:') . '</font></strong> ' . __('One or more of the SQL queries needed to upgraded your Cacti installation has failed. Please see below for more details. Your Cacti MySQL user must have <strong>SELECT, INSERT, UPDATE, DELETE, ALTER, CREATE, and DROP</strong> permissions. You should try executing the failed queries as "root" to ensure that you do not have a permissions problem.') . "</p>\n";
						}

						print $upgrade_results;

					/* upgrade-oldversion */
					}elseif ($step == '9') {
						print '<p style="font-size: 16px; font-weight: bold; color: red;">' . __('Important Upgrade Notice') . '</p>';
						print '<p>' . __('Before you continue with the installation, you <strong>must</strong> update your <tt>/etc/crontab</tt> file to point to <tt>poller.php</tt> instead of <tt>cmd.php</tt>.') . '</p>';
						print '<p>' . __('See the sample crontab entry below with the change made in red. Your crontab line will look slightly different based upon your setup.') . '</p>';
						print '<p><tt>*/5 * * * * cactiuser php /var/www/html/cacti/<span style="font-weight: bold; color: red;">poller.php</span> &gt; /dev/null 2&gt;&amp;1</tt></p>';
						print '<p>' . __('Once you have made this change, please click Finish to continue.') . '</p>';

					/* remote poller */
					}elseif ($step == '10') {
						print '<p>';
						print __('Before continuing, you must test the database connection to the Primary Cacti Web Server using your information below.  Please enter connection values for your Primary Cacti Web Server and press the \'Test Connection\' button in order to proceed.');
						print '</p>';
						print '<p>';
						print __('<b>Note:</b> the Database hostname can not be either \'localhost\' or the loopback address of this server.');
						print '</p>';
						print '<table class="filterTable">';
						print '<tr><td>' . __('Database Name') . '</td>';
						print "<td><input size='12' type='text' id='database_default' name='database_default' value='" . (isset($_SESSION['database_default']) ? $_SESSION['database_default']: (isset($rdatabase_default) ? $rdatabase_default : 'cacti')) . "'></td></tr>";
						print '<tr><td>' . __('Database Hostname') . '</td>';
						print "<td><input size='30' type='text' id='database_hostname' name='database_hostname' value='" . (isset($_SESSION['database_hostname']) ? $_SESSION['database_hostname']: (isset($rdatabase_hostname) ? $rdatabase_hostname : 'yourhost.yourdomain.com')) . "'></td></tr>";
						print '<tr><td>' . __('Database Username') . '</td>';
						print "<td><input size='12' type='text' id='database_username' name='database_username' value='" . (isset($_SESSION['database_username']) ? $_SESSION['database_username']: (isset($rdatabase_username) ? $rdatabase_username : 'cactiuser')) . "'></td></tr>";
						print '<tr><td>' . __('Database Password') . '</td>';
						print "<td><input size='12' type='password' id='database_password' name='database_password' value=''></td></tr>";
						print '<tr><td>' . __('Database Port') . '</td>';
						print "<td><input size='4' type='text' id='database_port' name='database_port' value='" . (isset($_SESSION['database_port']) ? $_SESSION['database_port']: (isset($rdatabase_port) ? $rdatabase_port : '3306')) . "'></td></tr>";
						print '<tr><td><label for="database_ssl">' . __('Database SSL') . '</label></td>';
						print "<td><input type='checkbox' id='database_ssl' name='database_ssl' " . (isset($_SESSION['database_ssl']) && $_SESSION['database_ssl'] == true ? 'checked' : (isset($rdatabase_ssl) && $rdatabase_ssl == true ? 'checked' : '')) . "></td></tr>";
						print '<tr><td><input id="testdb" type="button" value="' . __('Test Connection') . '"></td><td style="text-align:left" id="message"></td></tr>';
						print '</table>';
					}?>
					</td>
				</tr>
				<tr>
					<td class='saveRow' style='text-align:left'>
						<?php if ($step > 1) {?><input id='previous' type='button' value='<?php print __x('Dialog: previous', 'Previous'); ?>'><?php }?>
						<input id='next' type='submit' value='<?php if ($step == '9'){ print __x('Dialog: complete', 'Finish'); }else{ print __x('Dialog: go to the next page', 'Next'); }?>'>
						<input id='test' type='button' style='display:none' title='<?php print __('Test remote database connection');?>' value='<?php print __x('Dialog: test connection', 'Test Connection'); ?>'>
						<input type='hidden' id='previous_step' name='previous_step' value='<?php print $previous_step;?>'>
					</td>
				<tr>
			</table>
		</td>
	</tr>
</table>

<input type='hidden' name='step' value='<?php print $step;?>'>
<script type='text/javascript'>
var step='<?php print $step;?>';
$(function() {
	$('#next, #previous, #testdb').button();

	if (step == 1) {
		$('#next').button('disable');
	}else if (step == 10) {
		$('#next').button('disable');
	}

	$('#previous').click(function() {
		document.location = '?step='+$('#previous_step').val();
	});

	$('#accept').click(function() {
		if ($(this).is(':checked')) {
			$('#next').button('enable');
		}else{
			$('#next').button('disable');
		}
	});

	if ('<?php print $enabled;?>' == '0') {
		$('#next').button('disable');
	}else{
		$('#next').button('enable');
	}

	$('#testdb').click(function() {
		strURL = 'index.php?action=testdb';
		$.post(strURL, $('input').serializeObject()).done(function(data) {
			$('#message').html(data).show().fadeOut(2000);
			if (data == 'Connection Sucsessful') {
				$('#next').button('enable');
			}
		});
	});

	$('#database_hostname').keyup(function() {
		if ($('#database_hostname').val() == 'localhost') {
			$('#testdb').button('disable');
		}else if ($('#database_hostname').val() == '127.0.0.1') {
			$('#testdb').button('disable');
		}else{
			$('#testdb').button('enable');
		}
	});
});
</script>

</form>

<?php
