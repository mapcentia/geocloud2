<?php
///////////////////////////////////////////////////////////////////////////////
// VDaemon PHP Library version 3.1.0
// Copyright (C) 2002-2009 Alexander Orlov
//
// VDaemon configuration file
//
///////////////////////////////////////////////////////////////////////////////

// Security key.
// DO NOT FORGET TO CHANGE DEFAULT VALUE!
$sVDaemonSecurityKey = 'Unique security key. This string may be any length.';

// defines VDaemon's behavior in case of POST request.
if (!defined('VDAEMON_POST_SECURITY')) {
	define('VDAEMON_POST_SECURITY', true);
}

// defines which PEAR.php file will be used by VDaemon
// true  - VDaemon will use bundled PEAR.php file (comes with VDaemon)
// false - VDaemon will try to find PEAR installation on web server
//	       and use PEAR.php file from there. If it will not be found
//	       VDaemon will use bundled PEAR.php file
//
// Set this option to false if you plan to use PEAR and VDaemon together
if (!defined('VDAEMON_USE_BUNDLED_PEAR')) {
	define('VDAEMON_USE_BUNDLED_PEAR', true);
}

// If set to true VDaemon copies $_VDAEMON array to the $_POST on errors
if (!defined('VDAEMON_SIMULATE_SELFSUBMIT')) {
	define('VDAEMON_SIMULATE_SELFSUBMIT', false);
}

// path to vdaemon.js file from your web site root
// PATH_TO_VDAEMON_JS is not defined (commented) by default
// if it is not defined then VDaemon detects path to vdaemon.js automatically

//if (!defined('PATH_TO_VDAEMON_JS')) {
//	define('PATH_TO_VDAEMON_JS', '/vdaemon/vdaemon.js');
//}

// Defines whether VDaemon must save submitted forms data to a session.
if (!defined('VDAEMON_SAVE_DATA')) {
	define('VDAEMON_SAVE_DATA', false);
}