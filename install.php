<?php
/*
 * Nucleus: PHP/MySQL Weblog CMS (http://nucleuscms.org/)
 * Copyright (C) 2002-2007 The Nucleus Group
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * (see nucleus/documentation/index.html#license for more info)
 * 
 * This script will install the Nucleus tables in your SQL-database, 
 * and initialize the data in those tables.
 *
 * Below is a friendly way of letting users on non-php systems know that Nucleus won't run there.
 * ?><div style="font-size: xx-large;">If you see this text in your browser when you open <i>install.php</i>, your web server is not able to run PHP-scripts, and therefor Nucleus will not be able to run there. </div><div style="display: none"><?php
 */

/**
 * @license http://nucleuscms.org/license.txt GNU General Public License
 * @copyright Copyright (C) 2002-2007 The Nucleus Group
 * @version $Id$
 */

/*
	This part of the install.php code allows for customization of the install process.
	When distributing plugins or skins together with a Nucleus installation, the
	configuration below will instruct to install them

	-- Start Of Configurable Part --
*/

include('./install_lang_english.php');

// array with names of plugins to install. Plugin files must be present in the nucleus/plugin/
// directory.
//
// example:
//     array('NP_TrackBack', 'NP_MemberGoodies')
$aConfPlugsToInstall = array('NP_SkinFiles','NP_SecurityEnforcer','NP_Text');


// array with skins to install. skins must be present under the skins/ directory with
// a subdirectory having the same name that contains a skinbackup.xml file
//
// example:
//     array('base','rsd')
$aConfSkinsToImport = array(
    'atom',
    'rss2.0',
    'rsd',
    'default',
);

/*
	-- End Of Configurable Part --
*/

// don't give warnings for uninitialized vars
error_reporting(E_ERROR | E_WARNING | E_PARSE);

// make sure there's no unnecessary escaping:
set_magic_quotes_runtime(0);

// if there are some plugins or skins to import, do not include vars
// in globalfunctions.php again... so set a flag
if ((count($aConfPlugsToInstall) > 0) || (count($aConfSkinsToImport) > 0) ) {
	global $CONF;
	$CONF['installscript'] = 1;
}

// compatibility script for php < 4.1.0
// ToDo: remove this here and from the core
if (phpversion() >= '4.1.0') {
	include_once('nucleus/libs/vars4.1.0.php');
} else {
	include_once('nucleus/libs/vars4.0.6.php');
}

// include core classes that are needed for login & plugin handling
include_once('nucleus/libs/mysql.php');
// added for 3.5 sql_* wrapper
global $MYSQL_HANDLER;
//set the handler if different from mysql (or mysqli)
//$MYSQL_HANDLER = array('pdo','mysql');
if (!isset($MYSQL_HANDLER))
	$MYSQL_HANDLER = array('mysql','');
include_once('nucleus/libs/sql/'.$MYSQL_HANDLER[0].'.php');
// end new for 3.5 sql_* wrapper

// check if mysql support is installed
// this check may not make sense, as is, in a version past 3.5x
	if (!function_exists('mysql_query') ) {
		_doError(_ERROR1);
	}

	if (postVar('action') == 'go') {
		doInstall();
	} else {
		showInstallForm();
	}

	exit;
	
/*
 * Show the form for the installation settings
 */	
function showInstallForm() {
	// 0. pre check if all necessary files exist
	doCheckFiles();

	?>
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title><?php echo _TITLE; ?></title>
		<style type="text/css"><!--
			@import url('nucleus/documentation/styles/manual.css');
		--></style>
		<script type="text/javascript"><!--
			var submitcount = 0;

			// function to make sure the submit button only gets pressed once
			function checkSubmit() {
				if (submitcount == 0) {
					submitcount++;
					return true;
				} else {
					return false;
				}
			}
		--></script>
	</head>
	<body>
		<div style="text-align:center"><img src="./nucleus/styles/logo.gif" alt="<?php echo _ALT_NUCLEUS_CMS_LOGO; ?>" /></div> <!-- Nucleus logo -->
		<form method="post" action="install.php">

		<h1><?php echo _HEADER1; ?></h1>

		<?php echo _TEXT1; ?>

		<h1><?php echo _HEADER2; ?></h1>

		<?php echo _TEXT2; ?>

		<ul>
			<li>PHP:

<?php
	echo phpversion();
	$minVersion = '4.0.6';

	if (phpversion() < $minVersion) {
		echo ' <span class="warning">', _TEXT2_WARN , $minVersion, '</span>';
	} elseif (phpversion() < '5') {
		echo ' <span class="warning">' . _TEXT2_WARN3 . '</span>';
	}
?>

			</li>
			<li>MySQL:

<?php
	// Tturn on output buffer
	// Needed to repress the output of the sql function that are
	// not part of php (in this case the @ operator doesn't work) 
	ob_start();
	// note: this piece of code is taken from phpMyAdmin
	$conn = sql_connect_args('localhost','','');
	$result = @sql_query('SELECT VERSION() AS version',$conn);

	if ($result != FALSE && sql_num_rows($result) > 0) {
		$row = sql_fetch_array($result);
		$match = explode('.', $row['version']);
	} else {
		$result = @sql_query('SHOW VARIABLES LIKE \'version\'',$conn);

		if ($result != FALSE && @sql_num_rows($result) > 0) {
			$row = sql_fetch_row($result);
			$match = explode('.', $row[1]);
		} else {
			//$output = shell_exec('mysql -V');
			$output = (function_exists('shell_exec')) ? @shell_exec('mysql -V') : '0.0.0';
   			preg_match('@[0-9]+\.[0-9]+\.[0-9]+@', $output, $version);
			$match = explode('.', $version[0]);

			if ($match[0] == '') {
				$match[0] = '0';
				$match[1] = '0';
				$match[2] = '0';
			}
		}
	}
	@sql_disconnect($conn);
	//End and clean output buffer
	ob_end_clean();
	$mysqlVersion = implode($match, '.');
	$minVersion = '3.23';

	if ($mysqlVersion == '0.0.0') {
		echo _NOTIFICATION1;
	}
	else {
		echo $mysqlVersion;
	}
	
	if ($mysqlVersion < $minVersion) {
		echo ' <strong>', _TEXT2_WARN2 , $minVersion, '</strong>';
	}
?>

			</li>
		</ul>

<?php
	// tell people how they can have their config file filled out automatically
	if (@file_exists('config.php') && @!is_writable('config.php') ) {
?>

		<h1><?php echo _HEADER3; ?></h1>

		<?php echo _TEXT3;

} ?>

		<h1><?php echo _HEADER4; ?></h1>

		<?php echo _TEXT4; ?>

		<fieldset>
			<legend><?php echo _TEXT4_TAB_HEAD; ?></legend>
			<table>
				<tr>
					<td><label for="if_mySQL_host"><?php echo _TEXT4_TAB_FIELD1; ?>:</label></td>
					<td><input id="if_mySQL_host" name="mySQL_host" value="<?php echo htmlspecialchars(@ini_get('mysql.default_host') )?>" /></td>
				</tr>
				<tr>
					<td><label for="if_mySQL_user"><?php echo _TEXT4_TAB_FIELD2; ?>:</label></td>
					<td><input id="if_mySQL_user" name="mySQL_user" /></td>
				</tr>
				<tr>
					<td><label for="if_mySQL_password"><?php echo _TEXT4_TAB_FIELD3; ?>:</label></td>
					<td><input id="if_mySQL_password" name="mySQL_password" type="password" /></td>
				</tr>
				<tr>
					<td><label for="if_mySQL_database"><?php echo _TEXT4_TAB_FIELD4; ?>:</label></td>
					<td><input id="if_mySQL_database" name="mySQL_database" /> (<input name="mySQL_create" value="1" type="checkbox" id="mySQL_create" /><label for="mySQL_create"><?php echo _TEXT4_TAB_FIELD4_ADD; ?></label>)</td>
				</tr>
			</table>
		</fieldset>

		<fieldset>
			<legend><?php echo _TEXT4_TAB2_HEAD; ?></legend>
			<table>
				<tr>
					<td><input name="mySQL_usePrefix" value="1" type="checkbox" id="mySQL_usePrefix" /><label for="mySQL_usePrefix"><?php echo _TEXT4_TAB2_FIELD; ?>:</label></td>
					<td><input name="mySQL_tablePrefix" value="" /></td>
				</tr>
			</table>

			<?php echo _TEXT4_TAB2_ADD; ?>

		</fieldset>

		<h1><?php echo _HEADER5; ?></h1>

		<?php echo _TEXT5; ?>

<?php

	// no need to this all! dirname(__FILE__) is all we need -- moraes
	/*
	// discover full path
	$fullPath = serverVar('PATH_TRANSLATED');

	if ($fullPath == '') {
		$fullPath = serverVar('SCRIPT_FILENAME');
	}

	$basePath = str_replace('install.php', '', $fullPath);
	$basePath = replaceDoubleBackslash($basePath);
	$basePath = replaceDoubleBackslash($basePath);

	// add slash at end if necessary
	if (!endsWithSlash($basePath) ) {
		$basePath .= '/';
	}
	*/

	$basePath = dirname(str_replace('\\', '/', __FILE__)) . '/';
?>

		<fieldset>
			<legend><?php echo _TEXT5_TAB_HEAD; ?></legend>
			<table>
				<tr>
					<td><label for="if_IndexURL"><?php echo _TEXT5_TAB_FIELD1;?>:</label></td>
					<td><input id="if_IndexURL" name="IndexURL" size="60" value="<?php
						$url = 'http://' . serverVar('HTTP_HOST') . serverVar('PHP_SELF');
						$url = str_replace('install.php', '', $url);
						$url = replaceDoubleBackslash($url);

						// add slash at end if necessary
						if (!endsWithSlash($url) ) {
							$url .= '/';
						}

						echo $url; ?>" /></td>
				</tr>
				<tr>
					<td><label for="if_AdminURL"><?php echo _TEXT5_TAB_FIELD2;?>:</label></td>
					<td><input id="if_AdminURL" name="AdminURL" size="60" value="<?php
						if ($url) {
							echo $url, 'nucleus/';
						} ?>" /></td>
				</tr>
				<tr>
					<td><label for="if_AdminPath"><?php echo _TEXT5_TAB_FIELD3;?>:</label></td>
					<td><input id="if_AdminPath" name="AdminPath" size="60" value="<?php
						if($basePath) {
							echo $basePath, 'nucleus/';
						} ?>" /></td>
				</tr>
				<tr>
					<td><label for="if_MediaURL"><?php echo _TEXT5_TAB_FIELD4;?>:</label></td>
					<td><input id="if_MediaURL" name="MediaURL" size="60" value="<?php
						if ($url) {
							echo $url, 'media/';
						} ?>" /></td>
				</tr>
				<tr>
					<td><label for="if_MediaPath"><?php echo _TEXT5_TAB_FIELD5;?>:</label></td>
					<td><input id="if_MediaPath" name="MediaPath" size="60" value="<?php
						if ($basePath) {
							echo $basePath, 'media/';
						} ?>" /></td>
				</tr>
				<tr>
					<td><label for="if_SkinsURL"><?php echo _TEXT5_TAB_FIELD6;?>:</label></td>
					<td><input id="if_SkinsURL" name="SkinsURL" size="60" value="<?php
						if ($url) {
							echo $url, 'skins/';
						} ?>" />
						<br />(used by imported skins)
					</td>
				</tr>
				<tr>
					<td><label for="if_SkinsPath"><?php echo _TEXT5_TAB_FIELD7;?>:</label></td>
					<td><input id="if_SkinsPath" name="SkinsPath" size="60" value="<?php
						if ($basePath) {
							echo $basePath, 'skins/';
						} ?>" />
						<br />(<?php echo _TEXT5_TAB_FIELD7_2;?>)
					</td>
				</tr>
				<tr>
					<td><label for="if_PluginURL"><?php echo _TEXT5_TAB_FIELD8;?>:</label></td>
					<td><input id="if_PluginURL" name="PluginURL" size="60" value="<?php
						if ($url) {
							echo $url, 'nucleus/plugins/';
						} ?>" /></td>
				</tr>
				<tr>
					<td><label for="if_ActionURL"><?php echo _TEXT5_TAB_FIELD9;?>:</label></td>
					<td><input id="if_ActionURL" name="ActionURL" size="60" value="<?php
						if ($url) {
							echo $url, 'action.php';
						} ?>" />
						<br />(<?php echo _TEXT5_TAB_FIELD9_2;?>)
					</td>
				</tr>
			</table>
		</fieldset>

		<?php echo _TEXT5_2; ?>

		<h1><?php echo _HEADER6; ?></h1>

		<?php echo _TEXT6; ?>

		<fieldset>
			<legend><?php echo _TEXT6_TAB_HEAD; ?></legend>
			<table>
				<tr>
					<td><label for="if_User_name"><?php echo _TEXT6_TAB_FIELD1; ?>:</label></td>
					<td><input id="if_User_name" name="User_name" value="" /> <small>(<?php echo _TEXT6_TAB_FIELD1_2; ?>)</small></td>
				</tr>
				<tr>
					<td><label for="if_User_realname"><?php echo _TEXT6_TAB_FIELD2; ?>:</label></td>
					<td><input id="if_User_realname" name="User_realname" value="" /></td>
				</tr>
				<tr>
					<td><label for="if_User_password"><?php echo _TEXT6_TAB_FIELD3; ?>:</label></td>
					<td><input id="if_User_password" name="User_password" type="password" value="" /></td>
				</tr>
				<tr>
					<td><label for="if_User_password2"><?php echo _TEXT6_TAB_FIELD4; ?>:</label></td>
					<td><input id="if_User_password2" name="User_password2" type="password" value="" /></td>
				</tr>
				<tr>
					<td><label for="if_User_email"><?php echo _TEXT6_TAB_FIELD5; ?>:</label></td>
					<td><input id="if_User_email" name="User_email" value="" /> <small>(<?php echo _TEXT6_TAB_FIELD5_2; ?>)</small></td>
				</tr>
			</table>
		</fieldset>

		<h1><?php echo _HEADER7; ?></h1>

		<?php echo _TEXT7; ?>

		<fieldset>
			<legend><?php echo _TEXT7_TAB_HEAD; ?></legend>
			<table>
				<tr>
					<td><label for="if_Blog_name"><?php echo _TEXT7_TAB_FIELD1; ?>:</label></td>
					<td><input id="if_Blog_name" name="Blog_name" size="60" value="My Nucleus CMS" /></td>
				</tr>
				<tr>
					<td><label for="if_Blog_shortname"><?php echo _TEXT7_TAB_FIELD2; ?>:</label></td>
					<td><input id="if_Blog_shortname" name="Blog_shortname" value="mynucleuscms" /> <small>(<?php echo _TEXT7_TAB_FIELD2_2; ?>)</small></td>
				</tr>
			</table>
		</fieldset>

		<h1><?php echo _HEADER8; ?></h1>

		<fieldset>
			<legend><?php echo _TEXT8_TAB_HEADER; ?></legend>
			<table>
				<tr>
					<td><input name="Weblog_ping" value="1" type="checkbox" id="Weblog_ping" /><label for="Weblog_ping"><?php echo _TEXT8_TAB_FIELD1; ?></label></td>
				</tr>
			</table>
		</fieldset>

		<h1><?php echo _HEADER9; ?></h1>

		<?php echo _TEXT9; ?>

		<p><input name="action" value="go" type="hidden" /> <input type="submit" value="<?php echo _BUTTON1; ?>" onclick="return checkSubmit();" /></p>

		</form>
	</body>
</html>

<?php }

/*
 * Add a table prefix if it is used
 * 
 * @param 	$unPrefixed
 * 			table name with prefix
 */	
function tableName($unPrefixed) {
	global $mysql_usePrefix, $mysql_prefix;

	if ($mysql_usePrefix == 1) {
		return $mysql_prefix . $unPrefixed;
	} else {
		return $unPrefixed;
	}
}

/*
 * The installation process itself
 */	
function doInstall() {
	global $mysql_usePrefix, $mysql_prefix, $weblog_ping;

	// 0. put all POST-vars into vars
	$mysql_host = postVar('mySQL_host');
	$mysql_user = postVar('mySQL_user');
	$mysql_password = postVar('mySQL_password');
	$mysql_database = postVar('mySQL_database');
	$mysql_create = postVar('mySQL_create');
	$mysql_usePrefix = postVar('mySQL_usePrefix');
	$mysql_prefix = postVar('mySQL_tablePrefix');
	$config_indexurl = postVar('IndexURL');
	$config_adminurl = postVar('AdminURL');
	$config_adminpath = postVar('AdminPath');
	$config_mediaurl = postVar('MediaURL');
	$config_skinsurl = postVar('SkinsURL');
	$config_pluginurl = postVar('PluginURL');
	$config_actionurl = postVar('ActionURL');
	$config_mediapath = postVar('MediaPath');
	$config_skinspath = postVar('SkinsPath');
	$user_name = postVar('User_name');
	$user_realname = postVar('User_realname');
	$user_password = postVar('User_password');
	$user_password2 = postVar('User_password2');
	$user_email = postVar('User_email');
	$blog_name = postVar('Blog_name');
	$blog_shortname = postVar('Blog_shortname');
	$config_adminemail = $user_email;
	$config_sitename = $blog_name;
	$weblog_ping = postVar('Weblog_ping');

	$config_indexurl = replaceDoubleBackslash($config_indexurl);
	$config_adminurl = replaceDoubleBackslash($config_adminurl);
	$config_mediaurl = replaceDoubleBackslash($config_mediaurl);
	$config_skinsurl = replaceDoubleBackslash($config_skinsurl);
	$config_pluginurl = replaceDoubleBackslash($config_pluginurl);
	$config_actionurl = replaceDoubleBackslash($config_actionurl);
	$config_adminpath = replaceDoubleBackslash($config_adminpath);
	$config_skinspath = replaceDoubleBackslash($config_skinspath);

	// 1. check all the data
	$errors = array();

	if (!$mysql_database) {
		array_push($errors, _ERROR2);
	}

	if (($mysql_usePrefix == 1) && (strlen($mysql_prefix) == 0) ) {
		array_push($errors, _ERROR3);
	}

	if (($mysql_usePrefix == 1) && (!preg_match('/^[a-zA-Z0-9_]+$/', $mysql_prefix) ) ) {
		array_push($errors, _ERROR4);
	}

	// TODO: add action.php check
	if (!endsWithSlash($config_indexurl) || !endsWithSlash($config_adminurl) || !endsWithSlash($config_mediaurl) || !endsWithSlash($config_pluginurl) || !endsWithSlash($config_skinsurl) ) {
		array_push($errors, _ERROR5);
	}

	if (!endsWithSlash($config_adminpath) ) {
		array_push($errors, _ERROR6);
	}

	if (!endsWithSlash($config_mediapath) ) {
		array_push($errors, _ERROR7);
	}

	if (!endsWithSlash($config_skinspath) ) {
		array_push($errors, _ERROR8);
	}

	if (!is_dir($config_adminpath) ) {
		array_push($errors, _ERROR9);
	}

	if (!_isValidMailAddress($user_email) ) {
		array_push($errors, _ERROR10);
	}

	if (!_isValidDisplayName($user_name) ) {
		array_push($errors, _ERROR11);
	}

	if (!$user_password || !$user_password2) {
		array_push($errors, _ERROR12);
	}

	if ($user_password != $user_password2) {
		array_push($errors, _ERROR13);
	}

	if (!_isValidShortName($blog_shortname) ) {
		array_push($errors, _ERROR14);
	}

	if (sizeof($errors) > 0) {
		showErrorMessages($errors);
	}

	// 2. try to log in to mySQL

	global $MYSQL_CONN;
	// this will need to be changed if we ever allow
	$MYSQL_CONN = @sql_connect_args($mysql_host, $mysql_user, $mysql_password);

	if ($MYSQL_CONN == false) {
		_doError(_ERROR15 . ': ' . sql_error() );
	}

	// 3. try to create database (if needed)
	if ($mysql_create == 1) {
		sql_query('CREATE DATABASE ' . $mysql_database,$MYSQL_CONN) or _doError(_ERROR16 . ': ' . sql_error($MYSQL_CONN) );
	}

	// 4. try to select database
	sql_select_db($mysql_database,$MYSQL_CONN) or _doError(_ERROR17);

	// 5. execute queries
	$filename = 'install.sql';
	$fd = fopen($filename, 'r');
	$queries = fread($fd, filesize($filename) );
	fclose($fd);

	$queries = split("(;\n|;\r)", $queries);

	$aTableNames = array(
		'nucleus_actionlog',
		'nucleus_ban',
		'nucleus_blog',
		'nucleus_category',
		'nucleus_comment',
		'nucleus_config',
		'nucleus_item',
		'nucleus_karma',
		'nucleus_member',
		'nucleus_plugin',
		'nucleus_skin',
		'nucleus_template',
		'nucleus_team',
		'nucleus_activation',
		'nucleus_tickets'
		);
// these are unneeded (one of the replacements above takes care of them)
//			'nucleus_plugin_event',
//			'nucleus_plugin_option',
//			'nucleus_plugin_option_desc',
//			'nucleus_skin_desc',
//			'nucleus_template_desc',

	$aTableNamesPrefixed = array(
		$mysql_prefix . 'nucleus_actionlog',
		$mysql_prefix . 'nucleus_ban',
		$mysql_prefix . 'nucleus_blog',
		$mysql_prefix . 'nucleus_category',
		$mysql_prefix . 'nucleus_comment',
		$mysql_prefix . 'nucleus_config',
		$mysql_prefix . 'nucleus_item',
		$mysql_prefix . 'nucleus_karma',
		$mysql_prefix . 'nucleus_member',
		$mysql_prefix . 'nucleus_plugin',
		$mysql_prefix . 'nucleus_skin',
		$mysql_prefix . 'nucleus_template',
		$mysql_prefix . 'nucleus_team',
		$mysql_prefix . 'nucleus_activation',
		$mysql_prefix . 'nucleus_tickets'
		);
// these are unneeded (one of the replacements above takes care of them)
//			$mysql_prefix . 'nucleus_plugin_event',
//			$mysql_prefix . 'nucleus_plugin_option',
//			$mysql_prefix . 'nucleus_plugin_option_desc',
//			$mysql_prefix . 'nucleus_skin_desc',
//			$mysql_prefix . 'nucleus_template_desc',

	$count = count($queries);

	for ($idx = 0; $idx < $count; $idx++) {
		$query = trim($queries[$idx]);
		// echo "QUERY = <small>" . htmlspecialchars($query) . "</small><p>";

		if ($query) {

			if ($mysql_usePrefix == 1) {
					$query = str_replace($aTableNames, $aTableNamesPrefixed, $query);
			}

			sql_query($query,$MYSQL_CONN) or _doError(_ERROR30 . ' (<small>' . htmlspecialchars($query) . '</small>): ' . sql_error($MYSQL_CONN) );
		}
	}

	// 5a make first post
	$newpost = "INSERT INTO ". tableName('nucleus_item') ." VALUES (1, '" . _1ST_POST_TITLE . "', '" . _1ST_POST . "', '" . _1ST_POST2 . "', 1, 1, '2005-08-15 11:04:26', 0, 0, 0, 1, 0, 1);";
	sql_query($newpost,$MYSQL_CONN) or _doError(_ERROR18 . ' (<small>' . htmlspecialchars($newpost) . '</small>): ' . sql_error($MYSQL_CONN) );

	// 6. update global settings
	updateConfig('IndexURL', $config_indexurl);
	updateConfig('AdminURL', $config_adminurl);
	updateConfig('MediaURL', $config_mediaurl);
	updateConfig('SkinsURL', $config_skinsurl);
	updateConfig('PluginURL', $config_pluginurl);
	updateConfig('ActionURL', $config_actionurl);
	updateConfig('AdminEmail', $config_adminemail);
	updateConfig('SiteName', $config_sitename);

	// 7. update GOD member
	$query = 'UPDATE ' . tableName('nucleus_member')
			. " SET mname='" . addslashes($user_name) . "',"
			. " mrealname='" . addslashes($user_realname) . "',"
			. " mpassword='" . md5(addslashes($user_password) ) . "',"
			. " murl='" . addslashes($config_indexurl) . "',"
			. " memail='" . addslashes($user_email) . "',"
			. " madmin=1, mcanlogin=1"
			. " WHERE mnumber=1";

	sql_query($query,$MYSQL_CONN) or _doError(_ERROR19 . ': ' . sql_error($MYSQL_CONN) );

	// 8. update weblog settings
	$query = 'UPDATE ' . tableName('nucleus_blog')
			. " SET bname='" . addslashes($blog_name) . "',"
			. " bshortname='" . addslashes($blog_shortname) . "',"
			. " burl='" . addslashes($config_indexurl) . "'"
			. " WHERE bnumber=1";

	sql_query($query,$MYSQL_CONN) or _doError(_ERROR20 . ': ' . sql_error($MYSQL_CONN) );

	// 9. update item date
	$query = 'UPDATE ' . tableName('nucleus_item')
			. " SET itime='" . date('Y-m-d H:i:s', time() ) ."'"
			. " WHERE inumber=1";

	sql_query($query,$MYSQL_CONN) or _doError(_ERROR21 . ': ' . sql_error($MYSQL_CONN) );

	global $aConfPlugsToInstall, $aConfSkinsToImport;
	$aSkinErrors = array();
	$aPlugErrors = array();

	if ((count($aConfPlugsToInstall) > 0) || (count($aConfSkinsToImport) > 0) ) {
		// 10. set global variables
		global $MYSQL_HOST, $MYSQL_USER, $MYSQL_PASSWORD, $MYSQL_DATABASE, $MYSQL_PREFIX;

		$MYSQL_HOST = $mysql_host;
		$MYSQL_USER = $mysql_user;
		$MYSQL_PASSWORD = $mysql_password;
		$MYSQL_DATABASE = $mysql_database;
		$MYSQL_PREFIX = ($mysql_usePrefix == 1)?$mysql_prefix:'';

		global $DIR_NUCLEUS, $DIR_MEDIA, $DIR_SKINS, $DIR_PLUGINS, $DIR_LANG, $DIR_LIBS;

		$DIR_NUCLEUS = $config_adminpath;
		$DIR_MEDIA = $config_mediapath;
		$DIR_SKINS = $config_skinspath;
		$DIR_PLUGINS = $DIR_NUCLEUS . 'plugins/';
		$DIR_LANG = $DIR_NUCLEUS . 'language/';
		$DIR_LIBS = $DIR_NUCLEUS . 'libs/';

		// close database connection (needs to be closed if we want to include globalfunctions.php)
		sql_close($MYSQL_CONN);

		$manager = '';
		include_once($DIR_LIBS . 'globalfunctions.php');

		// 11. install custom skins
		$aSkinErrors = installCustomSkins($manager);
        $defskinQue  = 'SELECT `sdnumber` as result FROM ' . sql_table('skin_desc') . ' WHERE `sdname` = "default"';
        $defSkinID   = quickQuery($defskinQue);
        $updateQuery = 'UPDATE ' . sql_table('blog') . ' SET `bdefskin` = ' . intval($defSkinID) . ' WHERE `bnumber` = 1';
        sql_query($updateQuery);
        $updateQuery = 'UPDATE ' . sql_table('config') . ' SET `value` = ' . intval($defSkinID). ' WHERE `name` = "BaseSkin"';
        sql_query($updateQuery);

		// 12. install NP_Ping, if decided
		if ($weblog_ping == 1) {
			global $aConfPlugsToInstall;
			array_push($aConfPlugsToInstall, "NP_Ping");
		}

		// 13. install custom plugins
		$aPlugErrors = installCustomPlugs($manager);
	}

	// 14. Write config file ourselves (if possible)
	$bConfigWritten = 0;

	if (@file_exists('config.php') && is_writable('config.php') && $fp = @fopen('config.php', 'w') ) {
		$config_data = '<' . '?php' . "\n\n";
		//$config_data .= "\n"; (extraneous, just added extra \n to previous line
		$config_data .= "	// mySQL connection information\n";
		$config_data .= "	\$MYSQL_HOST = '" . $mysql_host . "';\n";
		$config_data .= "	\$MYSQL_USER = '" . $mysql_user . "';\n";
		$config_data .= "	\$MYSQL_PASSWORD = '" . $mysql_password . "';\n";
		$config_data .= "	\$MYSQL_DATABASE = '" . $mysql_database . "';\n";
		$config_data .= "	\$MYSQL_PREFIX = '" . (($mysql_usePrefix == 1)?$mysql_prefix:'') . "';\n";
		$config_data .= "	// new in 3.50. first element is db handler, the second is the db driver used by the handler\n";
		$config_data .= "	// default is \$MYSQL_HANDLER = array('mysql','mysql');\n";
		$config_data .= "	//\$MYSQL_HANDLER = array('mysql','mysql');\n";
		$config_data .= "	//\$MYSQL_HANDLER = array('pdo','mysql');\n";
		$config_data .= "	\$MYSQL_HANDLER = array('".$MYSQL_HANDLER[0]."','".$MYSQL_HANDLER[1]."');\n";
		$config_data .= "\n";
		$config_data .= "	// main nucleus directory\n";
		$config_data .= "	\$DIR_NUCLEUS = '" . $config_adminpath . "';\n";
		$config_data .= "\n";
		$config_data .= "	// path to media dir\n";
		$config_data .= "	\$DIR_MEDIA = '" . $config_mediapath . "';\n";
		$config_data .= "\n";
		$config_data .= "	// extra skin files for imported skins\n";
		$config_data .= "	\$DIR_SKINS = '" . $config_skinspath . "';\n";
		$config_data .= "\n";
		$config_data .= "	// these dirs are normally sub dirs of the nucleus dir, but \n";
		$config_data .= "	// you can redefine them if you wish\n";
		$config_data .= "	\$DIR_PLUGINS = \$DIR_NUCLEUS . 'plugins/';\n";
		$config_data .= "	\$DIR_LANG = \$DIR_NUCLEUS . 'language/';\n";
		$config_data .= "	\$DIR_LIBS = \$DIR_NUCLEUS . 'libs/';\n";
		$config_data .= "\n";
		$config_data .= "	// include libs\n";
		$config_data .= "	include(\$DIR_LIBS.'globalfunctions.php');\n";
		$config_data .= "?" . ">";

		$result = @fputs($fp, $config_data, strlen($config_data) );
		fclose($fp);

		if ($result) {
			$bConfigWritten = 1;
		}
	}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title><?php echo _TITLE; ?></title>
	<style>@import url('nucleus/styles/manual.css');</style>
</head>
<body>
	<div style='text-align:center'><img src='./nucleus/styles/logo.gif' /></div> <!-- Nucleus logo -->

<?php
	$aAllErrors = array_merge($aSkinErrors, $aPlugErrors);

	if (count($aAllErrors) > 0) {
		echo '<h1>' . _TITLE2 . '</h1>';
		echo '<ul><li>' . implode('</li><li>', $aAllErrors) . '</li></ul>';
	}

	if (!$bConfigWritten) { ?>
		<h1><?php echo _TITLE3; ?></h1>

		<? echo _TEXT10; ?>

		<pre><code>&lt;?php
	// mySQL connection information
	$MYSQL_HOST = '<b><?php echo $mysql_host?></b>';
	$MYSQL_USER = '<b><?php echo $mysql_user?></b>';
	$MYSQL_PASSWORD = '<i><b>xxxxxxxxxxx</b></i>';
	$MYSQL_DATABASE = '<b><?php echo $mysql_database?></b>';
	$MYSQL_PREFIX = '<b><?php echo ($mysql_usePrefix == 1)?$mysql_prefix:''?></b>';
	
	// new in 3.50. first element is db handler, the second is the db driver used by the handler
	// default is $MYSQL_HANDLER = array('mysql','mysql');
	//$MYSQL_HANDLER = array('mysql','mysql');
	//$MYSQL_HANDLER = array('pdo','mysql');
	$MYSQL_HANDLER = array('mysql','');

	// main nucleus directory
	$DIR_NUCLEUS = '<b><?php echo $config_adminpath?></b>';

	// path to media dir
	$DIR_MEDIA = '<b><?php echo $config_mediapath?></b>';

	// extra skin files for imported skins
	$DIR_SKINS = '<b><?php echo $config_skinspath?></b>';

	// these dirs are normally sub dirs of the nucleus dir, but
	// you can redefine them if you wish
	$DIR_PLUGINS = $DIR_NUCLEUS . 'plugins/';
	$DIR_LANG = $DIR_NUCLEUS . 'language/';
	$DIR_LIBS = $DIR_NUCLEUS . 'libs/';

	// include libs
	include($DIR_LIBS.'globalfunctions.php');
?&gt;</code></pre>

	<?php echo _TEXT11; ?>

	<div class="note">
	<?php echo _TEXT12; ?>
	</div>

<?php } else { ?>

	<h1><?php echo _TITLE4; ?></h1>

	<?php echo _TEXT13; ?>

<?php } ?>

	<h1><?php echo _TITLE5; ?></h1>
	
	<?php echo _TEXT14; ?>

	<ul>
		<li><?php echo _TEXT14_L1; ?></li>
		<li><?php echo _TEXT14_L2; ?></li>
	</ul>
	
	<h1><?php echo _HEADER10; ?></h1>

	<?php echo _TEXT15; ?>

	<ul>
		<li><?php echo _TEXT15_L1; ?></li>
		<li><?php echo _TEXT15_L2; ?></li>
	</ul>

	<?php echo _TEXT16; ?>

	<h1><?php echo _HEADER11; ?></h1>

	<p><?php echo _TEXT16_H; ?>
		<ul>
			<li><a href="<?php echo $config_adminurl?>"><?php echo _TEXT16_L1; ?></a></li>
			<li><a href="<?php echo $config_indexurl?>"><?php echo _TEXT16_L2; ?></a></li>
		</ul>
	</p>

</body>
</html>

<?php
}

/**
 *  Install custom plugins
 */
function installCustomPlugs(&$manager) {
	global $aConfPlugsToInstall, $DIR_LIBS;

	$aErrors = array();

	if (count($aConfPlugsToInstall) == 0) {
		return $aErrors;
	}

	$res = sql_query('SELECT * FROM ' . sql_table('plugin') );
	$numCurrent = sql_num_rows($res);

	foreach ($aConfPlugsToInstall as $plugName) {
		// do this before calling getPlugin (in case the plugin id is used there)
		$query = 'INSERT INTO ' . sql_table('plugin') . ' (porder, pfile) VALUES (' . (++$numCurrent) . ', "' . addslashes($plugName) . '")';
		sql_query($query);

		// get and install the plugin
		$manager->clearCachedInfo('installedPlugins');
		$plugin =& $manager->getPlugin($plugName);
		$plugin->plugid = $numCurrent;

		if (!$plugin) {
			sql_query('DELETE FROM ' . sql_table('plugin') . ' WHERE pfile=\'' . addslashes($plugName) . '\'');
			$numCurrent--;
			array_push($aErrors, _ERROR22 . $plugName);
			continue;
		}

		$plugin->install();
	}

	// SYNC PLUGIN EVENT LIST
	sql_query('DELETE FROM ' . sql_table('plugin_event') );

	// loop over all installed plugins
	$res = sql_query('SELECT pid, pfile FROM ' . sql_table('plugin') );

	while($o = sql_fetch_object($res) ) {
		$pid = $o->pid;
		$plug =& $manager->getPlugin($o->pfile);

		if ($plug) {
			$eventList = $plug->getEventList();

			foreach ($eventList as $eventName) {
				sql_query('INSERT INTO ' . sql_table('plugin_event') . ' (pid, event) VALUES (' . $pid . ', \'' . $eventName . '\')');
			}
		}
	}

	return $aErrors;
}

/**
 *  Install custom skins
 *  Prepares the installation of custom skins
 */
function installCustomSkins(&$manager) {
	global $aConfSkinsToImport, $DIR_LIBS, $DIR_SKINS;

	$aErrors = array();
	global $manager;
	if (empty($manager)) {
	    $manager = new MANAGER;
	}

	if (count($aConfSkinsToImport) == 0) {
		return $aErrors;
	}

	// load skinie class
	include_once($DIR_LIBS . 'skinie.php');

	$importer = new SKINIMPORT();

	foreach ($aConfSkinsToImport as $skinName) {
		$importer->reset();
		$skinFile = $DIR_SKINS . $skinName . '/skinbackup.xml';

		if (!@file_exists($skinFile) ) {
			array_push($aErrors, _ERROR23_1 . $skinFile . ' : ' . _ERROR23_2);
			continue;
		}

		$error = $importer->readFile($skinFile);

		if ($error) {
			array_push($aErrors, _ERROR24 . $skinName . ' : ' . $error);
			continue;
		}

		$error = $importer->writeToDatabase(1);

		if ($error) {
			array_push($aErrors, _ERROR24 . $skinName . ' : ' . $error);
			continue;
		}
	}

	return $aErrors;
}

/**
 *  Check if some important files of the Nucleus CMS installation are available
 *  Give an error if one or more files are not accessible
 */
function doCheckFiles() {
	$missingfiles = array();
	$files = array(
		'install.sql',
		'index.php',
		'action.php',
		'nucleus/index.php',
		'nucleus/libs/globalfunctions.php',
		'nucleus/libs/ADMIN.php',
		'nucleus/libs/BLOG.php',
		'nucleus/libs/COMMENT.php',
		'nucleus/libs/COMMENTS.php',
		'nucleus/libs/ITEM.php',
		'nucleus/libs/MEMBER.php',
		'nucleus/libs/SKIN.php',
		'nucleus/libs/TEMPLATE.php',
		'nucleus/libs/MEDIA.php',
		'nucleus/libs/ACTIONLOG.php',
		'nucleus/media.php'
		);

	$count = count($files);

	for ($i = 0; $i < $count; $i++) {
		if (!is_readable($files[$i]) ) {
			array_push($missingfiles, _ERROR25_1 . $files[$i] . _ERROR25_2);
		}
	}

	if (count($missingfiles) > 0) {
		showErrorMessages($missingfiles);
	}
}

/**
 *  Updates the configuration in the database
 * 
 *  @param	$name
 * 			name of the config var
 *  @param	$val
 * 			new value of the config var	
 */
function updateConfig($name, $val) {
	global $MYSQL_CONN;
	$name = addslashes($name);
	$val = trim(addslashes($val) );

	$query = 'UPDATE ' . tableName('nucleus_config')
			. " SET value='$val'"
			. " WHERE name='$name'";

	sql_query($query,$MYSQL_CONN) or _doError(_ERROR26 . ': ' . sql_error($MYSQL_CONN) );
	return sql_insert_id($MYSQL_CONN);
}

/**
 *  Replaces doubled backslashs
 * 
 *  @param	$input
 * 			string that could have double backslashs	
 */
function replaceDoubleBackslash($input) {
	return str_replace('\\', '/', $input);
}

/**
 * Checks if a string ends with a slash 
 * 
 *  @param	$s
 * 			string	
 */
function endsWithSlash($s) {
	return (strrpos($s, '/') == strlen($s) - 1);
}

/**
 * Checks if email address is valid
 * 
 *  @param	$address
 * 			address which should be tested	
 */
function _isValidMailAddress($address) {
	if (preg_match("/^[a-zA-Z0-9\._-]+@+[A-Za-z0-9\._-]+\.+[A-Za-z]{2,4}$/", $address) ) {
		return 1;
	} else {
		return 0;
	}
}

/*
 * Check if short blog names and nicknames are allowed
 * Returns true if the given string is a valid shortname
 * logic: only letters and numbers are allowed, no spaces allowed
 * 
 * FIX: function eregi is deprecated since PHP 5.3.0
 * 
 * @param	$name
 * 			name which should be tested	
 */
function _isValidShortName($name) {
	if (preg_match("/^[a-zA-Z0-9]+$/", $name) ) {
		return 1;
	} else {
		return 0;
	}
}

/*
 * Check if a display name is allowed
 * Returns true if the given string is a valid display name
 * 
 * FIX: function eregi is deprecated since PHP 5.3.0
 * 
 * @param	$name
 * 			name which should be tested	
 */
function _isValidDisplayName($name) {
	if (preg_match("/^[a-zA-Z0-9]+[a-zA-Z0-9 ]*[a-zA-Z0-9]+$/", $name) ) {
		return 1;
	} else {
		return 0;
	}
}

/*
 * Shows error message
 * 
 * @param	$msg
 * 			error message
 */
function _doError($msg) {
	?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title><?php echo _TITLE; ?></title>
	<style>@import url('nucleus/styles/manual.css');</style>
</head>
<body>
	<div style='text-align:center'><img src='./nucleus/styles/logo.gif' /></div> <!-- Nucleus logo -->
	<h1><?php echo _ERROR27; ?></h1>

	<p><?php echo _ERROR28; ?>: "<?php echo $msg?>";</p>

	<p><a href="install.php" onclick="history.back();return false;"><?php echo _TEXT17; ?></a></p>
</body>
</html>

<?php
	exit;
}

/*
 * Shows error messages
 * 
 * @param	$errors
 * 			array with error messages
 */
function showErrorMessages($errors) {
	?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title><?php echo _TITLE; ?></title>
	<style>@import url('nucleus/styles/manual.css');</style>
</head>
<body>
	<div style='text-align:center'><img src='./nucleus/styles/logo.gif' /></div> <!-- Nucleus logo -->
	<h1><?php echo _ERROR27; ?></h1>

	<p><?php echo _ERROR29; ?>:</p>

	<ul>

<?php
	while($msg = array_shift($errors) ) {
		echo '<li>', $msg, '</li>';
	}
?>

	</ul>

	<p><a href="install.php" onclick="history.back();return false;"><?php echo _TEXT17; ?></a></p>
</body>
</html>

<?php
	exit;
}

/* for the non-php systems that decide to show the contents:
?></div><?php	*/

?>
