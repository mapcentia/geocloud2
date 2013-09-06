<?php
///////////////////////////////////////////////////////////////////////////////
// 05/23/2009
// VDaemon PHP Library version 3.1.0
//
// Copyright (C) 2002-2009 Alexander Orlov
//
///////////////////////////////////////////////////////////////////////////////

define('VD_VERSION', '3.1.0');

//-----------------------------------------------------------------------------
//				 Errors
//-----------------------------------------------------------------------------

define('VD_E_INVALID_HTML',				'Invalid HTML syntax');
define('VD_E_UNNAMED_FORM',				'ID or NAME attribute of the form must be specified');
define('VD_E_UNCLOSED_FORM',			'Closing form tag </form> for %s form is not found');
define('VD_E_NESTED_FORM',				'FORM can\'t be nested to another FORM or be contained by VLLABEL or VLGROUP');
define('VD_E_FORMLESS_SUMMARY',			'Summary must be associated with a VDaemon form (form attribute is missing)');
define('VD_E_FORMLESS_LABEL',			'Label must be associated with a VDaemon form (form attribute is missing)');
define('VD_E_FORMLESS_VALIDATOR',		'Validator must be located inside VDaemon form');
define('VD_E_UNCLOSED_TAG',				'%s tag is not closed yet when closing FORM tag found');
define('VD_E_UNCLOSED_LABEL',			'Closing tag </vllabel> is missing');
define('VD_E_UNCLOSED_GROUP',			'Closing tag </vlgroup> is missing');
define('VD_E_INVALID_FORM_ATTRIBUTE',	'Can not find form specified in Form attribute');
define('VD_E_VALIDATOR_NAME',			'Validator Name must be unique');
define('VD_E_FORM_METHOD',				'Only POST method is allowed for VDaemon Form');
define('VD_E_INVALID_DISABLEBUTTONS',	'Invalid DISABLEBUTTONS attribute value. Possible values are ALL, SUBMIT or NONE');
define('VD_E_INVALID_VALIDATIONMODE',	'Invalid VALIDATIONMODE attribute value. Possible values are ONCHANGE or ONSUBMIT');
define('VD_E_UNTYPED_VALIDATOR',		'Type attribute of a validator must be specified');
define('VD_E_VALIDATOR_TYPE',			'Invalid Validator type');
define('VD_E_CONTROL_MISSED',			'Control attribute of a validator must be specified');
define('VD_E_VALIDATOR_CONTROL',		'Input element referenced by Control attribute not found');
define('VD_E_CONTROL_INVALID',			'Invalid Control attribute');
define('VD_E_VALIDTYPE_MISSED',			'This type of Validator must have a ValidType attribute');
define('VD_E_VALIDTYPE_INVALID',		'Invalid ValidType attribute value');
define('VD_E_MINLENGTH_INVALID',		'Invalid MinLength attribute value');
define('VD_E_MAXLENGTH_INVALID',		'Invalid MaxLength attribute value');
define('VD_E_MINVALUE_MISSED',			'Range Validator must have a MinValue attribute');
define('VD_E_MINVALUE_INVALID',			'Invalid MinValue attribute value');
define('VD_E_MAXVALUE_MISSED',			'Range Validator must have a MaxValue attribute');
define('VD_E_MAXVALUE_INVALID',			'Invalid MaxValue attribute value');
define('VD_E_DATEORDER_INVALID',		'Invalid DateOrder attribute value');
define('VD_E_TIMEFORMAT_INVALID',		'Invalid TIMEFORMAT attribute value');
define('VD_E_GROUPCHAR_INVALID_LENGTH',	'Invalid GROUPCHAR attribute value (must be empty value or exactly one symbol)');
define('VD_E_GROUPCHAR_INVALID',		'Grouping symbol can\'t be digit (0-9) or sign (+ or -)');
define('VD_E_DECIMALCHAR_INVALID_LENGTH','Invalid DECIMALCHAR attribute value (must be exactly one symbol)');
define('VD_E_DECIMALCHAR_INVALID',		'Decimal symbol can\'t be digit (0-9) or sign (+ or -)');
define('VD_E_GROUP_DECIMAL_CHARS',		'Grouping and Decimal symbols conflict (they must be different symbols)');
define('VD_E_OPERATOR_MISSED',			'Compare Validator must have an Operator attribute');
define('VD_E_OPERATOR_INVALID',			'Invalid Operator attribute value');
define('VD_E_COMPAREVALUE_MISSED',		'Compare Validator must have either CompareValue or CompareControl attribute');
define('VD_E_COMPAREVALUE_INVALID',		'Invalid CompareValue attribute value');
define('VD_E_COMPARECONTROL_NOT_FOUND',	'Input element referenced by CompareControl attribute not found');
define('VD_E_COMPARECONTROL_INVALID',	'Invalid CompareControl attribute');
define('VD_E_FORMAT_MISSED',			'Format Validator must have a FORMAT attribute');
define('VD_E_FORMAT_INVALID',			'Invalid FORMAT attribute value');
define('VD_E_REGEXP_MISSED',			'RegExp Validator must have a RegExp attribute');
define('VD_E_FUNCTION_MISSED',			'Custom Validator must have a Function attribute');
define('VD_E_FUNCTION_INVALID',			'Invalid Function attribute value');
define('VD_E_FUNCTION_NOT_FOUND',		'Custom validation function %% not found');
define('VD_E_GROUP_EMPTY',				'Group Validator must contain at least one Validator');
define('VD_E_VALIDATORS_MISSED',		'Label must have a Validators attribute');
define('VD_E_VALIDATOR_NOT_FOUND',		'Validator referenced by Validators attribute not found');
define('VD_E_DISPLAYMODE_INVALID',		'Invalid DisplayMode attribute value');
define('VD_E_SERIALIZE',				'Can\'t serialize validators information.');
define('VD_E_UNSERIALIZE',				'Can\'t unserialize validators information.');
define('VD_E_POST_SECURITY',			'Page is accessed using POST method, but validators information isn\'t defined.');
define('VD_E_JS_NOT_FOUND',				'VDaemon can\'t find vdaemon.js file.');
define('VD_E_INVALID_INSTALL_DIR',		'VDaemon is installed to the wrong place. It must be installed to any folder under website root.');
define('VD_E_SERVERPATH',				'Web server environment variable \'PATH_TRANSLATED\' is not defined.');
define('VD_E_DIRECT_CALL',				'VDaemon must not be included using web address (http://www.mysite.com/vdaemon.php). Use server path instead.');

//-----------------------------------------------------------------------------
//				 Library Code
//-----------------------------------------------------------------------------

ob_start();

define('PATH_TO_VDAEMON', dirname(__FILE__).'/');
require_once(PATH_TO_VDAEMON . 'config.php');

// vdaemon.php called directly
if (basename($_SERVER['PHP_SELF']) == 'vdaemon.php') {
	VDDirectCall();
}

if (!session_id()) {
	session_start();
}
header("Cache-control: private");

require_once(PATH_TO_VDAEMON . 'XML/XML_HTMLSax.php');

$sPageCharset = 'iso-8859-1';
$oVDaemonStatus = null;
$_VDAEMON = array();
$bVDZlibLoaded = false;

VDLoadExtensions();

if (isset($_SESSION['VDaemonData']['STATUS'])) {
	$oVDaemonStatus = @unserialize($_SESSION['VDaemonData']['STATUS']);
	$_VDAEMON = $_SESSION['VDaemonData']['POST'][$oVDaemonStatus->sForm];
	if (VDAEMON_SAVE_DATA) {
		// remove only validation status data
		unset($_SESSION['VDaemonData']['STATUS']);
	} else {
		// remove all VDaemon session data
		unset($_SESSION['VDaemonData']);
	}
}

VDValidate();

//-----------------------------------------------------------------------------
//				 PHP functions
//-----------------------------------------------------------------------------

if (!defined('CASE_LOWER')) {
	define('CASE_LOWER', 0);
}

if (!defined('CASE_UPPER')) {
	define('CASE_UPPER', 1);
}

/**
 * Replace array_change_key_case()
 *
 * @category	PHP
 * @package		PHP_Compat
 * @link		http://php.net/function.array_change_key_case
 * @author		Stephan Schmidt <schst@php.net>
 * @author		Aidan Lister <aidan@php.net>
 * @version		$Revision: 1.10 $
 * @since		PHP 4.2.0
 * @require		PHP 4.0.0 (user_error)
 */
if (!function_exists('array_change_key_case')) {
	function array_change_key_case($input, $case = CASE_LOWER)
	{
		if (!is_array($input)) {
			user_error('array_change_key_case(): The argument should be an array',
				E_USER_WARNING);
			return false;
		}

		$output   = array ();
		$keys	 = array_keys($input);
		$casefunc = ($case == CASE_LOWER) ? 'strtolower' : 'strtoupper';

		foreach ($keys as $key) {
			$output[$casefunc($key)] = $input[$key];
		}

		return $output;
	}
}

if (!defined('ENT_NOQUOTES')) {
	define('ENT_NOQUOTES', 0);
}

if (!defined('ENT_COMPAT')) {
	define('ENT_COMPAT', 2);
}

if (!defined('ENT_QUOTES')) {
	define('ENT_QUOTES', 3);
}

/**
 * Replace html_entity_decode()
 *
 * @category	PHP
 * @package		PHP_Compat
 * @link		http://php.net/function.html_entity_decode
 * @author		David Irvine <dave@codexweb.co.za>
 * @author		Aidan Lister <aidan@php.net>
 * @version		$Revision: 1.7 $
 * @since		PHP 4.3.0
 * @internal	Setting the charset will not do anything
 * @require		PHP 4.0.0 (user_error)
 */
if (!function_exists('html_entity_decode')) {
	function html_entity_decode($string, $quote_style = ENT_COMPAT, $charset = null)
	{
		static $trans_tbl;
		if (empty($trans_tbl)) {
			$trans_tbl = array_flip(get_html_translation_table(HTML_ENTITIES));
			// Add single quote to translation table;
			$trans_tbl['&#039;'] = '\'';
		}

		if (!is_int($quote_style)) {
			user_error('html_entity_decode() expects parameter 2 to be long, ' .
				gettype($quote_style) . ' given', E_USER_WARNING);
			return;
		}

		$tt = $trans_tbl;
		// Not translating double quotes
		if ($quote_style & ENT_NOQUOTES) {
			// Remove double quote from translation table
			unset($tt['&quot;']);
		}

		return strtr($string, $tt);
	}
}

//-----------------------------------------------------------------------------
//				 VDaemon functions
//-----------------------------------------------------------------------------

function VDGetSessionData($sForm)
{
	$aData = isset($_SESSION['VDaemonData']['POST'][$sForm]) ? $_SESSION['VDaemonData']['POST'][$sForm] : null;
	return $aData;
}

function VDClearSessionData($sForm)
{
	if (isset($_SESSION['VDaemonData']['POST'][$sForm])) {
		unset($_SESSION['VDaemonData']['POST'][$sForm]);
	}
}

function VDGetCopyString()
{
	return '<span style="font-family: Verdana, Tahoma, Arial, Sans-Serif; font-size: 9px">Powered by '
		 . '<a href="http://www.x-code.com/vdaemon_web_form_validation.php">VDaemon v'. VD_VERSION
		 . '</a> &copy; 2002-2009 <a  href="http://www.x-code.com/index.php">X-code.com</a></span>';
}

function VDEchoCopy()
{
	$sCopyStr = VDGetCopyString();

echo <<<END
<head>
<title>VDaemon - PHP Form Validation Library</title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body>
  <p><div align="center">$sCopyStr</div></p>
</body>
</html>
END;
}

function VDEnd()
{
	$sBuffer = ob_get_contents();
	ob_end_clean();
	echo VDCallback($sBuffer);
}

function VDCallback($sBuffer)
{
	$sResult = $sBuffer;

	if (!defined('VDAEMON_PARSE') || (VDAEMON_PARSE != false && strtolower(VDAEMON_PARSE) != 'false')) {
		$oPage =& new CVDPage($sBuffer);
		$sResult = $oPage->ProcessPage();
	}

	return $sResult;
}

function VDDirectCall()
{
	global $sVDaemonSecurityKey;

	if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST'
	&& isset($_POST['form']) && isset($_POST['post']) && isset($_POST['sesid']) && isset($_POST['hash'])) {
		$sForm = VDFormat($_POST['form']);
		$sPost = VDFormat($_POST['post']);
		$sSesID = VDFormat($_POST['sesid']);
		$sHash = VDFormat($_POST['hash']);
		if (strcasecmp($sHash, md5($sVDaemonSecurityKey . $sSesID)) == 0) {
			if (session_id()) {
				session_write_close();
			}
			session_id($sSesID);
			session_start();

			$old_data = @(array)$_SESSION['VDaemonData']['POST'][$sForm];
			$_SESSION['VDaemonData']['POST'][$sForm] = (array)unserialize($sPost) + $old_data;
			if (isset($_POST['status'])) {
				$sStatus = VDFormat($_POST['status']);
				$_SESSION['VDaemonData']['STATUS'] = $sStatus;
			}
			session_write_close();
		} else {
			echo "Invalid hash";
		}
	} else {
		//VDEchoCopy();
		echo VDErrorMessage(VD_E_DIRECT_CALL);
	}
	exit;
}

function VDValidate()
{
	global $oVDaemonStatus;
	global $_VDAEMON;
	global $sVDaemonSecurityKey;
	$sErrMsg = '';
	$aValidators = array();

	if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
		if ($_VDAEMON && VDAEMON_SIMULATE_SELFSUBMIT) {
			$_POST = $_VDAEMON;
		}
		return;
	}
	if (!isset($_POST['VDaemonValidators'])) {
		if (VDAEMON_POST_SECURITY) {
			$sErrMsg = VD_E_POST_SECURITY;
		} else {
			return;
		}
	} else {
		$sValue = VDGetValue('VDaemonValidators');
		$sValue = VDSecurityDecode($sValue);
		if (!$sValue) {
			$sErrMsg = VD_E_UNSERIALIZE;
		} else {
			$oRuntime = @unserialize($sValue);
			if (!$oRuntime || strtolower(get_class($oRuntime)) != 'cvdvalruntime' || !is_array($oRuntime->aNodes)) {
				$sErrMsg = VD_E_UNSERIALIZE;
			} else {
				foreach ($oRuntime->aNodes as $nIdx => $mTmp) {
					if (strtolower(get_class($oRuntime->aNodes[$nIdx])) != 'xmlnode') {
						$sErrMsg = VD_E_UNSERIALIZE;
						break;
					}

					$oVal =& new CVDValidator($oRuntime->aNodes[$nIdx]);
					if ($aErr = $oVal->CheckSyntax(null)) {
						$sErrMsg = $aErr[0];
						break;
					}

					$aValidators[] =& $oVal;
				}
			}
		}
	}

	if ($sErrMsg) {
		echo VDErrorMessage($sErrMsg);
		exit;
	}

	unset($_POST['VDaemonValidators']);
	$_VDAEMON = $_POST;

	$oVDaemonStatus = new CVDFormStatus();	  // can't use =& because $oVDaemonStatus is global
	$oVDaemonStatus->sForm = $oRuntime->sForm;
	$oVDaemonStatus->bValid = true;
	foreach ($aValidators as $nIdx => $mTmp) {
		$oValStatus =& $aValidators[$nIdx]->Validate();
		$oVDaemonStatus->bValid = $oVDaemonStatus->bValid && $oValStatus->bValid;
		$oVDaemonStatus->aValidators[] =& $oValStatus;
	}

	$bSelfSubmit = $oRuntime->sDomain == $_SERVER['HTTP_HOST']
		&& $oRuntime->sPage == $_SERVER['PHP_SELF']
		&& $oRuntime->sArgs == VDGetCurrentArgs();

	// Store data to session
	$sSID = '';
	if (!$oVDaemonStatus->bValid || VDAEMON_SAVE_DATA) {
		if ($oRuntime->sDomain != $_SERVER['HTTP_HOST']) {
			$aPost = array(
				'form'   => $oRuntime->sForm,
				'post'   => serialize($_VDAEMON),
				'sesid'  => $oRuntime->sSesID,
				'hash'   => md5($sVDaemonSecurityKey . $oRuntime->sSesID)
			);
			if (!$oVDaemonStatus->bValid) {
				$aPost['status'] = serialize($oVDaemonStatus);
			}

			$sPost = '';
			foreach ($aPost as $sKey => $sValue) {
				$sPost .= $sKey . '=' . urlencode($sValue) . '&';
			}
			$sPost = rtrim($sPost, "&");

			// close session for preventing possible session lock
			session_write_close();
			$ch = curl_init($oRuntime->sProtocol . $oRuntime->sDomain . $oRuntime->sVDaemonPath);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $sPost);
			$sRet = curl_exec($ch);
			curl_close($ch);
			// restart session
			session_start();
		} else {
			$old_data = @(array)$_SESSION['VDaemonData']['POST'][$oRuntime->sForm];
			$_SESSION['VDaemonData']['POST'][$oRuntime->sForm] = (array)$_VDAEMON + $old_data;
			if (!$oVDaemonStatus->bValid && !$bSelfSubmit) {
				$_SESSION['VDaemonData']['STATUS'] = serialize($oVDaemonStatus);
			}
			$sSID = SID;
		}
	}

	// redirect to the form page
	if (!$oVDaemonStatus->bValid && !$bSelfSubmit) {
		session_write_close();

		$oRuntime->sArgs .= ($oRuntime->sArgs && $sSID) ? '&' : '';
		$oRuntime->sArgs .= $sSID ? $sSID : '';
		$oRuntime->sArgs .= ($oRuntime->sArgs && $oRuntime->sAnchor) ? '&' : '';

		$sLink = $oRuntime->sProtocol . $oRuntime->sDomain . $oRuntime->sPage;
		$sLink .=  $oRuntime->sArgs ? '?' . $oRuntime->sArgs : '';
		$sLink .=  $oRuntime->sAnchor ? '#' . $oRuntime->sAnchor : '';

		header("location: $sLink");
		exit;
	}
}

function VDGetValue($aPhpName, $bSession = false, $sForm = '', $bQuotes = false)
{
	global $_VDAEMON;
	$sValue = null;

	$sName = is_array($aPhpName) ? $aPhpName[0] : $aPhpName;
	if (!$bSession) {
		if (isset($_FILES[$sName])) {
			@$mRef =& $_FILES[$sName]['name'];
		} else {
			@$mRef =& $_POST[$sName];
		}
	} else {
		if (!$sForm) {
			@$mRef =& $_VDAEMON[$sName];
		} elseif (isset($_SESSION['VDaemonData']['POST'][$sForm][$sName])) {
			@$mRef =& $_SESSION['VDaemonData']['POST'][$sForm][$sName];
		}
	}

	if (is_array($aPhpName)) {
		foreach ($aPhpName as $nIdx => $sPhpIdx) {
			if ($nIdx == 0) {
				continue;
			} elseif ($sPhpIdx === '') {
				break;
			} elseif (isset($mRef) && is_array($mRef)) {
				$mRef =& $mRef[$sPhpIdx];
			} else {
				unset($mRef);
				break;
			}
		}
	}

	if (isset($mRef)) {
		$sValue = $mRef;
		if (!is_array($sValue)) {
			$sValue = VDFormat($sValue, $bQuotes);
		}
	}

	return $sValue;
}

function VDFormat($sValue, $bQuotes = false)
{
	$sValue = trim($sValue);
	if ($bQuotes xor get_magic_quotes_gpc()) {
		$sValue = $bQuotes ? addslashes($sValue) : stripslashes($sValue);
	}

	return $sValue;
}

function VDGetPhpControlName($sName)
{
	$aResult = array();

	$nPosL = strpos($sName, '[');
	if ($nPosL === 0) {
		return null;
	}
	$nPosR = strpos($sName, ']', $nPosL);
	$aResult[0] = $nPosL && $nPosR ? substr($sName, 0, $nPosL) : $sName;
	$aResult[0] = VDHtmlDecode($aResult[0]);
	$aResult[0] = preg_replace('/\[/', '_', $aResult[0], 1);
	$aResult[0] = preg_replace('/\./', '_', $aResult[0], 1);

	while ($nPosL && $nPosR) {
		$sIdx = substr($sName, $nPosL + 1, $nPosR - $nPosL - 1);
		$sIdx = VDHtmlDecode($sIdx);
		$sIdx = VDEscape($sIdx);
		if (preg_match('/^0$|^[1-9][0-9]*$/', $sIdx)) { // decimal int
			$sIdx = intval($sIdx);
		}
		$aResult[] = $sIdx;

		$nPosL = strpos($sName, '[', $nPosR);
		if ($nPosL !== $nPosR + 1) {
			$nPosL = false;
		} else {
			$nPosR = strpos($sName, ']', $nPosL);
		}
	}

	return $aResult;
}

function VDGetCurrentArgs()
{
	$sArgs = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
	$nPos = strpos($sArgs, '#');
	if ($nPos !== false) {
		$sArgs = substr($sArgs, 0, $nPos);
	}

	$sSessName = session_name();
	$sArgs = preg_replace("/$sSessName=[^&]*&?/", '', $sArgs);
	if (substr($sArgs, -1) == '&') {
		$sArgs = substr($sArgs, 0, strlen($sArgs) - 1);
	}

	return $sArgs;
}

function VDHtmlDecode($sValue)
{
	global $sPageCharset;

	$sValue = @html_entity_decode($sValue, ENT_QUOTES, $sPageCharset);
	return $sValue;
}

function VDHtmlEncode($sValue)
{
	$sValue = str_replace(array('"', '<', '>'), array('&quot;', '&lt;', '&gt;'), $sValue);
	return $sValue;
}

function VDEscape($sValue)
{
	$sValue = str_replace('\\', '\\\\', $sValue);
	$sValue = str_replace('"', '\\"', $sValue);
	$sValue = str_replace("'", "\\'", $sValue);

	return $sValue;
}

function VDPregEscape($sValue)
{
	$sValue = preg_quote($sValue, '/');
	$sValue = str_replace(' ', '\\s', $sValue);

	return $sValue;
}

function VDErrorMessage($sErrMsg)
{
	return '<p style="font-family:Verdana,Arial,Helvetica;font-size:12px;margin-left:15px;margin-right:15px;"><b>VDaemon Error:</b> ' . $sErrMsg . "</p>\n";
}

function VDGetWebPath()
{
	$sVDaemonServerPath = PATH_TO_VDAEMON;
	$sScriptWebPath = $_SERVER['PHP_SELF'];
	if (isset($_SERVER['PATH_TRANSLATED'])) {
		$sScriptServerPath = realpath($_SERVER['PATH_TRANSLATED']);
	} elseif (isset($_SERVER['SCRIPT_FILENAME'])) {
		$sScriptServerPath = realpath($_SERVER['SCRIPT_FILENAME']);
	} else {
		echo VDErrorMessage(VD_E_SERVERPATH);
		exit;
	}

	while (strcasecmp(basename($sScriptWebPath), basename($sScriptServerPath)) == 0) {
		$sScriptWebPath = dirname($sScriptWebPath);
		$sScriptServerPath = dirname($sScriptServerPath);
	}

	if (strncasecmp($sVDaemonServerPath, $sScriptServerPath, strlen($sScriptServerPath)) == 0) {
		$sVDaemonWebPath = substr($sVDaemonServerPath, strlen($sScriptServerPath));
		if ($sScriptWebPath != '/' && $sScriptWebPath != '\\') {
			$sVDaemonWebPath = $sScriptWebPath . $sVDaemonWebPath;
		}
		if (strtoupper(substr(PHP_OS, 0, 3) == 'WIN')) {
			$sVDaemonWebPath = str_replace('\\', '/', $sVDaemonWebPath);
		}
	} else {
		echo VDErrorMessage(VD_E_INVALID_INSTALL_DIR);
		exit;
	}

	return $sVDaemonWebPath;
}

function VDGetJSPath()
{
	if (defined('PATH_TO_VDAEMON_JS')) {
		return PATH_TO_VDAEMON_JS;
	}

	$sJSPath = realpath(PATH_TO_VDAEMON . 'vdaemon.js');
	if (!file_exists($sJSPath)) {
		echo VDErrorMessage(VD_E_JS_NOT_FOUND);
		exit;
	}

	$sJSPath = VDGetWebPath() . 'vdaemon.js';
	return $sJSPath;
}

//--------------------------------------------------------------------------
//				  class CVDFormStatus
//--------------------------------------------------------------------------

class CVDFormStatus
{
	var $sForm;
	var $bValid;
	var $aValidators;

	function __sleep()
	{
		$this->sForm = urlencode($this->sForm);
		return array('sForm','bValid','aValidators');
	}

	function __wakeup()
	{
		$this->sForm = urldecode($this->sForm);
		return array('sForm','bValid','aValidators');
	}

	function GetValidatorState($sName)
	{
		$bResult = -1;
		if ($sName) {
			foreach ($this->aValidators as $nIdx => $mTmp) {
				$bResult = $this->aValidators[$nIdx]->GetValidatorState($sName, false);
				if ($bResult != -1) {
					break;
				}
			}
		}

		return $bResult;
	}
}

//--------------------------------------------------------------------------
//				  class CVDValidatorStatus
//--------------------------------------------------------------------------

class CVDValidatorStatus
{
	var $sName;
	var $bValid;
	var $sErrMsg;
	var $aValidators;

	function CVDValidatorStatus()
	{
		$this->aValidators = array();
	}

	function __sleep()
	{
		$this->sErrMsg = urlencode($this->sErrMsg);
		return array('sName', 'bValid','sErrMsg','aValidators');
	}

	function __wakeup()
	{
		$this->sErrMsg = urldecode($this->sErrMsg);
		return array('sName', 'bValid','sErrMsg','aValidators');
	}

	function GetErrMsg($sPre, $sPost)
	{
		$sResult = '';
		if (!$this->bValid) {
			if ($this->sErrMsg) {
				$sResult .= $sPre . $this->sErrMsg . $sPost;
			}
			foreach ($this->aValidators as $nIdx => $mTmp) {
				$sResult .= $this->aValidators[$nIdx]->GetErrMsg($sPre, $sPost);
			}
		}

		return $sResult;
	}

	function GetValidatorState($sName, $bParentState)
	{
		$bResult = -1;
		if ($this->sName == $sName) {
			$bResult = $bParentState || $this->bValid;
		} else {
			foreach ($this->aValidators as $nIdx => $mTmp) {
				$bResult = $this->aValidators[$nIdx]->GetValidatorState($sName, $this->bValid);
				if ($bResult != -1) {
					break;
				}
			}
		}

		return $bResult;
	}
}

//--------------------------------------------------------------------------
//				  class XmlNode
//--------------------------------------------------------------------------

class XmlNode
{
	var $sName;
	var $aAttrs = array();
	var $aSubNodes = array();
	var $nStart;
	var $nEnd;
	var $bWithoutRoot;

	function XmlNode($sName, $aAttrs = null, $nStart = 0, $nEnd = 0)
	{
		$this->sName = $sName;
		$this->nStart = $nStart;
		$this->nEnd = $nEnd;
		$this->bWithoutRoot = false;
		if (is_array($aAttrs)) {
			$this->aAttrs = $aAttrs;
		}
	}

	function __sleep()
	{
		$this->sName = urlencode($this->sName);
		foreach ($this->aAttrs as $sKey => $sValue) {
			$this->aAttrs[$sKey] = urlencode($this->aAttrs[$sKey]);
		}

		return array('sName','aAttrs','aSubNodes');
	}

	function __wakeup()
	{
		$this->sName = urldecode($this->sName);
		foreach ($this->aAttrs as $sKey => $sValue) {
			$this->aAttrs[$sKey] = urldecode($this->aAttrs[$sKey]);
		}

		return array('sName','aAttrs','aSubNodes');
	}

	function &AddSubNode($sName, $aAttrs = null, $nStart = 0, $nEnd = 0)
	{
		$oSubNode =& new XmlNode($sName, $aAttrs, $nStart, $nEnd);
		$this->aSubNodes[] =& $oSubNode;

		return $oSubNode;
	}

	function &FindSubNode($sName)
	{
		$oReturn = false;

		if ($this->sName == $sName) {
			$oReturn =& $this;
		} else {
			foreach ($this->aSubNodes as $sKey => $oSubNode) {
				if (is_object($this->aSubNodes[$sKey])) {
					$oReturn =& $this->aSubNodes[$sKey]->FindSubNode($sName);
					if ($oReturn !== false) {
						return $oReturn;
					}
				}
			}
		}

		return $oReturn;
	}

	function Serialize()
	{
		if ($this->bWithoutRoot) {
			$sXml = $this->SerializeWithoutRoot();
		} else {
			$sXml = '<' . $this->sName;

			foreach ($this->aAttrs as $sName => $sValue) {
				if ($sValue === true) {
					$sValue = 'true';
				}
				if (strtolower(substr($sName, 0, 2)) != 'on') {
					$sValue = VDHtmlEncode($sValue);
				}
				$sXml .= ' ' . $sName . '="' . $sValue . '"';
			}

			if (count($this->aSubNodes) > 0) {
				$sXml .= '>';
				foreach ($this->aSubNodes as $sValue) {
					if (is_object($sValue)) {
						$sXml .= $sValue->Serialize();
					} else {
						$sXml .= $sValue;
					}
				}

				$sXml .= '</' . $this->sName . '>';
			} else {
				$sXml .= ' />';
			}
		}

		return $sXml;
	}

	function SerializeWithoutRoot()
	{
		$sXml = '';
		foreach ($this->aSubNodes as $sValue) {
			if (is_object($sValue)) {
				$sXml .= $sValue->Serialize();
			} else {
				$sXml .= $sValue;
			}
		}

		return $sXml;
	}
}

//--------------------------------------------------------------------------
//				  class CVDPage
//--------------------------------------------------------------------------

class CVDPage
{
	var $sHtml;
	var $aForms;
	var $aFormlessNodes;
	var $oScriptNode;
	var $oEndScriptNode;

	var $sError;
	var $aDepthNodes;
	var $nDepth;
	var $nCharNodeStart;
	var $nCharNodeEnd;
	var $bScript;
	var $sForm;
	var $oLabel;
	var $bTextarea;
	var $bSelect;
	var $bOption;
	var $nIdCount;
	var $aValNodes;
	var $nValDepth;

	function CVDPage($sSource)
	{
		$this->sHtml = $sSource;
		$this->aForms = array();
		$this->aFormlessNodes = array();
		$this->sError = '';
	}

	function ProcessPage()
	{
		$this->ParsePage();
		if ($this->sError != '') {
			return $this->sError;
		}

		$this->CheckSyntax();
		if ($this->sError != '') {
			return $this->sError;
		}

		$this->Prepare();

		return $this->aDepthNodes[0]->SerializeWithoutRoot();
	}

	function ParsePage()
	{
		$oSaxParser =& new XML_HTMLSax();
		$oSaxParser->set_object($this);
		$oSaxParser->set_element_handler('StartElement','EndElement');
		$oSaxParser->set_data_handler('CharacterData');

		$this->aDepthNodes = array();
		$this->aDepthNodes[0] =& new XmlNode('ROOT', array());
		$this->nDepth = 1;
		$this->nCharNodeStart = 0;
		$this->nCharNodeEnd = 0;
		$this->bScript = false;
		$this->sForm = '';
		$this->oLabel = null;
		$this->bTextarea = false;
		$this->bSelect = false;
		$this->bOption = false;
		$this->nIdCount = 1;
		$this->aValNodes = array();
		$this->nValDepth = 0;

		$oSaxParser->parse($this->sHtml);
		$this->AddCharNode();

		if ($this->sError == '') {
			if ($this->sForm != '') {
				$this->MakeErrorMessage(sprintf(VD_E_UNCLOSED_FORM, $this->sForm), null);
			}
			foreach ($this->aFormlessNodes as $nIdx => $mTmp) {
				$oFlNode =& $this->aFormlessNodes[$nIdx];
				$this->MakeErrorMessage(VD_E_INVALID_FORM_ATTRIBUTE, $oFlNode);
			}
		}

		unset($oSaxParser);
	}

	function StartElement(&$oParser, $sName, $aAttrs)
	{
		if ($this->sError) {
			return;
		}

		if (!$this->bScript && !$this->bTextarea) {
			$sNameL = strtolower($sName);
			$aAttrsL = array_change_key_case($aAttrs, CASE_LOWER);

			switch ($sNameL) {
				case 'script':
					$this->bScript = true;
					break;

				case 'meta':
					if (isset($aAttrsL['http-equiv']) &&
					strcasecmp($aAttrsL['http-equiv'], 'Content-Type') == 0 &&
					isset($aAttrsL['content']) &&
					preg_match('/charset\s*=\s*([\w-]+)/i', $aAttrsL['content'], $aMatches)) {
						global $sPageCharset;
						$sPageCharset = $aMatches[1];
					}
					break;

				case 'body':
					if (!isset($this->oScriptNode)) {
						$this->nCharNodeEnd = $oParser->get_current_position();
						$this->oScriptNode =& $this->AddCustomNode('');
					}
					break;

				case 'form':
					if (!isset($this->oScriptNode)) {
						$this->oScriptNode =& $this->AddCustomNode('');
					}

					$oNode =& $this->AddElementNode($oParser, $sNameL, $aAttrsL);
					$this->nDepth++;
					if ($this->nDepth != 2) {
						$this->MakeErrorMessage(VD_E_NESTED_FORM, $oNode);
					} elseif (isset($aAttrsL['runat']) && strcasecmp($aAttrsL['runat'], 'vdaemon') == 0) {
						if ((isset($aAttrsL['name']) && $aAttrsL['name'] != '') ||
						(isset($aAttrsL['id']) && $aAttrsL['id'] != '')) {
							$this->sForm = isset($aAttrsL['id']) ? $aAttrsL['id'] : $aAttrsL['name'];
							$this->aForms[$this->sForm] =& new CVDForm($oNode);
							$this->aValNodes[$this->nValDepth] =& $this->aForms[$this->sForm];
							$this->nValDepth++;

							foreach ($this->aFormlessNodes as $nIdx => $mTmp) {
								$oFlNode =& $this->aFormlessNodes[$nIdx];
								if ($oFlNode->aAttrs['form'] == $this->sForm) {
									if ($oFlNode->sName == 'vlsummary') {
										$this->aForms[$this->sForm]->AddSummary($oFlNode, $this->MakeId());
									} elseif ($oFlNode->sName == 'vllabel') {
										$this->aForms[$this->sForm]->AddLabel($oFlNode, $this->MakeId());
									}

									unset($this->aFormlessNodes[$nIdx]);
								}
							}
						} else {
							$this->MakeErrorMessage(VD_E_UNNAMED_FORM, $oNode);
						}
					}
					break;

				case 'vlsummary':
					if (!$this->bSelect) {
						$oNode =& $this->AddElementNode($oParser, $sNameL, $aAttrsL);
						if (isset($aAttrsL['form'])) {
							if (isset($this->aForms[$aAttrsL['form']])) {
								$this->aForms[$aAttrsL['form']]->AddSummary($oNode, $this->MakeId());
							} else {
								$this->aFormlessNodes[] =& $oNode;
							}
						} else {
							if ($this->sForm != '') {
								$this->aForms[$this->sForm]->AddSummary($oNode, $this->MakeId());
							} else {
								$this->MakeErrorMessage(VD_E_FORMLESS_SUMMARY, $oNode);
							}
						}
					}
					break;

				case 'vllabel':
					if (!$this->bSelect) {
						$oNode =& $this->AddElementNode($oParser, $sNameL, $aAttrsL);
						$this->nDepth++;
						$this->oLabel =& $oNode;
						if (isset($aAttrsL['form'])) {
							if (isset($this->aForms[$aAttrsL['form']])) {
								$this->aForms[$aAttrsL['form']]->AddLabel($oNode, $this->MakeId());
							} else {
								$this->aFormlessNodes[] =& $oNode;
							}
						} else {
							if ($this->sForm != '') {
								$this->aForms[$this->sForm]->AddLabel($oNode, $this->MakeId());
							} else {
								$this->MakeErrorMessage(VD_E_FORMLESS_LABEL, $oNode);
							}
						}
					}
					break;

				case 'vlvalidator':
					if (!$this->bSelect) {
						$oNode =& $this->AddElementNode($oParser, $sNameL, $aAttrsL);
						if ($this->sForm == '') {
							$this->MakeErrorMessage(VD_E_FORMLESS_VALIDATOR, $oNode);
						} else {
							$sErrMsg = $this->aForms[$this->sForm]->CheckValidator($oNode);
							if ($sErrMsg) {
								$this->MakeErrorMessage($sErrMsg, $oNode);
							} else {
								$this->aValNodes[$this->nValDepth - 1]->AddValidator($oNode);
							}
						}
					}
					break;

				case 'vlgroup':
					if (!$this->bSelect) {
						$oNode =& $this->AddElementNode($oParser, $sNameL, $aAttrsL);
						$this->nDepth++;
						if ($this->sForm != '') {
							$sErrMsg = $this->aForms[$this->sForm]->CheckValidator($oNode);
							if ($sErrMsg) {
								$this->MakeErrorMessage($sErrMsg, $oNode);
							} else {
								$oValidator =& $this->aValNodes[$this->nValDepth - 1]->AddValidator($oNode);
								$this->aValNodes[$this->nValDepth] =& $oValidator;
								$this->nValDepth++;
							}
						} else {
							$this->MakeErrorMessage(VD_E_FORMLESS_VALIDATOR, $oNode);
						}
					}
					break;

				case 'input':
					if ($this->sForm != '' && !$this->bSelect) {
						$oNode =& $this->AddElementNode($oParser, $sNameL, $aAttrsL);
						$this->aForms[$this->sForm]->AddControl($oNode);
					}
					break;

				case 'textarea':
					if ($this->sForm != '' && !$this->bSelect) {
						$oNode =& $this->AddElementNode($oParser, $sNameL, $aAttrsL);
						$this->aForms[$this->sForm]->AddControl($oNode);
						$this->nDepth++;
						$this->bTextarea = true;
					}
					break;

				case 'select':
					if ($this->sForm != '' && !$this->bSelect) {
						$oNode =& $this->AddElementNode($oParser, $sNameL, $aAttrsL);
						$this->aForms[$this->sForm]->AddControl($oNode);
						$this->nDepth++;
						$this->bSelect = true;
					}
					break;

				case 'option':
					if ($this->bSelect) {
						if ($this->bOption) {
							$this->AddCharNode();
							$this->nDepth--;
						}
						$this->AddElementNode($oParser, $sNameL, $aAttrsL);
						$this->nDepth++;
						$this->bOption = true;
					}
					break;
			}
		}

		$this->nCharNodeEnd = $oParser->get_current_position();
	}

	function EndElement(&$oParser, $sName)
	{
		if ($this->sError) {
			return;
		}

		$sNameL = strtolower($sName);
		if ($sNameL == 'script' && !$this->bTextarea) {
			$this->bScript = false;
		} elseif ($sNameL == 'textarea' && $this->bTextarea && !$this->bScript) {
			$this->AddCharNode();
			$this->nCharNodeStart = $oParser->get_current_position();
			$this->nDepth--;
			$this->bTextarea = false;
		} elseif (!$this->bScript && !$this->bTextarea) {
			switch ($sNameL) {
				case 'script':
					$this->bScript = false;
					break;

				case 'head':
					if (!isset($this->oScriptNode)) {
						$this->oScriptNode =& $this->AddCustomNode('');
					}
					break;

				case 'body':
					if (!isset($this->oEndScriptNode)) {
						$this->oEndScriptNode =& $this->AddCustomNode('');
					}
					break;

				case 'form':
					if ($this->nDepth != 2) {
						$oNode =& $this->aDepthNodes[$this->nDepth - 1];
						if ($oNode->sName == 'option') {
							$oNode =& $this->aDepthNodes[$this->nDepth - 2];
						}
						$this->MakeErrorMessage(sprintf(VD_E_UNCLOSED_TAG, strtoupper($oNode->sName)), $oNode);
					}

					$this->AddCharNode();
					$this->nCharNodeStart = $oParser->get_current_position();
					$this->nDepth--;
					if ($this->sForm != '') {
						$this->sForm = '';
						$this->nValDepth--;
					}
					break;

				case 'vllabel':
					if ($this->oLabel) {
						$this->AddCharNode();
						$this->nCharNodeStart = $oParser->get_current_position();
						$this->nDepth--;
						unset($this->oLabel);
						$this->oLabel = null;
					}
					break;

				case 'vlgroup':
					if ($this->nValDepth > 1) {
						$this->AddCharNode();
						$this->nCharNodeStart = $oParser->get_current_position();
						$this->nDepth--;
						$this->nValDepth--;
					}
					break;

				case 'select':
					if ($this->bSelect) {
						$this->AddCharNode();
						$this->nCharNodeStart = $oParser->get_current_position();
						if ($this->bOption) {
							$this->nDepth--;
							$this->bOption = false;
						}
						$this->nDepth--;
						$this->bSelect = false;
					}
					break;

				case 'option':
					if ($this->bOption) {
						$this->AddCharNode();
						$this->nCharNodeStart = $oParser->get_current_position();
						$this->nDepth--;
						$this->bOption = false;
					}
					break;
			}
		}

		$this->nCharNodeEnd = $oParser->get_current_position();
	}

	function CharacterData(&$oParser, $sData)
	{
		if ($this->sError) {
			return;
		}

		$this->nCharNodeEnd = $oParser->get_current_position();
	}

	function AddCharNode()
	{
		if ($this->nCharNodeEnd > $this->nCharNodeStart) {
			$this->aDepthNodes[$this->nDepth - 1]->aSubNodes[] =
				substr($this->sHtml, $this->nCharNodeStart, $this->nCharNodeEnd - $this->nCharNodeStart);
			$this->nCharNodeStart = $this->nCharNodeEnd;
		}
	}

	function &AddCustomNode($sText)
	{
		$this->AddCharNode();
		$this->aDepthNodes[$this->nDepth - 1]->aSubNodes[] =& $sText;

		return $sText;
	}

	function &AddElementNode(&$oParser, $sName, $aAttrs)
	{
		$nStart = $this->nCharNodeEnd;
		$nEnd = $oParser->get_current_position();

		$this->AddCharNode();
		$this->nCharNodeStart = $nEnd;
		$oNode =& new XmlNode($sName, $aAttrs, $nStart, $nEnd);

		if ($this->oLabel) {
			$this->MakeErrorMessage(VD_E_UNCLOSED_LABEL, $this->oLabel);
		} else {
			$this->aDepthNodes[$this->nDepth] =& $oNode;
			$this->aDepthNodes[$this->nDepth - 1]->aSubNodes[] =& $oNode;
		}

		return $oNode;
	}

	function CheckSyntax()
	{
		foreach ($this->aForms as $sFormName => $mTmp) {
			$this->aForms[$sFormName]->CheckSyntax($this);
		}
	}

	function GetClientScript()
	{
		$sJSPath = VDGetJSPath();
		$sScript = '';
		foreach ($this->aForms as $sName => $mTmp) {
			$sScript .= $this->aForms[$sName]->GetClientScript();
		}

$sScript = <<<END
<script type="text/JavaScript" src="$sJSPath"></script>
<script type="text/JavaScript">
<!--//--><![CDATA[//><!--

var f,v,i,l,s;
$sScript
//--><!]]>
</script>

END;
		return $sScript;
	}

	function Prepare()
	{
		$bClientValidate = false;
		foreach ($this->aForms as $sName => $mTmp) {
			$bClientValidate = $bClientValidate || $this->aForms[$sName]->ClientValidate();
		}

		if ($bClientValidate && isset($this->oScriptNode)) {
			$sScript = $this->GetClientScript();
			$this->oScriptNode = $sScript;

$sScript = <<<END
<script type="text/javascript">
<!--//--><![CDATA[//><!--
VDBindHandlers();
//--><!]]>
</script>

END;
			if (isset($this->oEndScriptNode)) {
				$this->oEndScriptNode = $sScript;
			} else {
				$this->aDepthNodes[0]->aSubNodes[] = $sScript;
			}
		}

		foreach ($this->aForms as $sName => $mTmp) {
			$this->aForms[$sName]->Prepare();
		}
	}

	function MakeId()
	{
		$sId = 'VDaemonID_' . $this->nIdCount;
		$this->nIdCount++;

		return $sId;
	}

	function MakeErrorMessage($sErrMsg, $oErrNode)
	{
		$sError = VDErrorMessage($sErrMsg);

		if ($oErrNode && $oErrNode->nStart < $oErrNode->nEnd) {
			$sPrefix = '';
			$sBuffer = substr($this->sHtml, 0, $oErrNode->nStart);
			for ($nIdx = 0; $nIdx < 3 && $sBuffer != ''; $nIdx++) {
				$nPos = strrpos($sBuffer, "\n");
				if ($nPos === false) {
					$nPos = 0;
				}
				$sPrefix = substr($sBuffer, $nPos) . $sPrefix;
				$sBuffer = substr($sBuffer, 0, $nPos);
			}
			$sPrefix = htmlspecialchars(ltrim($sPrefix, "\r\n"));

			$sSuffix = '';
			$sBuffer = substr($this->sHtml, $oErrNode->nEnd);
			for ($nIdx = 0; $nIdx < 3 && $sBuffer != ''; $nIdx++) {
				$nPos = strpos($sBuffer, "\n");
				if ($nPos === false) {
					$nPos = strlen($sBuffer) - 1;
				}
				$sSuffix .= substr($sBuffer, 0, $nPos + 1);
				$sBuffer = substr($sBuffer, $nPos + 1);
			}
			$sSuffix = htmlspecialchars(rtrim($sSuffix, "\r\n"));

			$sBody = substr($this->sHtml, $oErrNode->nStart, $oErrNode->nEnd - $oErrNode->nStart);
			$sBody = '<b>' . htmlspecialchars($sBody) . '</b>';
			$sError .= '<pre style="font-family:\'Courier New\',Courier,mono;font-size:11px;margin-left:15px;margin-right:15px;padding:5px;border:1px solid #999999;background-color:#CCCCCC;">';
			$sError .= $sPrefix . $sBody . $sSuffix;
			$sError .= "</pre>\n";
		}

		$this->sError .= $sError;
	}
}

//--------------------------------------------------------------------------
//				  class CVDForm
//--------------------------------------------------------------------------

class CVDForm
{
	var $sName;
	var $oNode;
	var $aSummaries;
	var $aLabels;
	var $aValidators;
	var $aControls;
	var $bClientValidate;
	var $nIdxCount;
	var $aInputIndex; // Indexing inputs by id and name

	function CVDForm(&$oNode)
	{
		$this->oNode =& $oNode;
		$this->sName = isset($oNode->aAttrs['id']) ? $oNode->aAttrs['id'] : $oNode->aAttrs['name'];
		$this->nIdxCount = 0;
		$this->aSummaries = array();
		$this->aLabels = array();
		$this->aValidators = array();
		$this->aControls = array();
		$this->aInputIndex = array();
	}

	function AddSummary(&$oNode, $sId)
	{
		$this->aSummaries[] =& new CVDSummary($oNode, $sId);
	}

	function AddLabel(&$oNode, $sId)
	{
		$this->aLabels[] =& new CVDLabel($oNode, $sId);
	}

	function CheckValidator(&$oNode)
	{
		$sName = isset($oNode->aAttrs['name']) ? $oNode->aAttrs['name'] : '';
		if ($this->ValidatorExists($sName)) {
			return VD_E_VALIDATOR_NAME;
		}

		return '';
	}

	function &AddValidator(&$oNode)
	{
		$oValidator =& new CVDValidator($oNode);
		$this->aValidators[] =& $oValidator;

		return $oValidator;
	}

	function AddControl(&$oNode)
	{
		if (isset($oNode->aAttrs['name']) && ($aPhpName = VDGetPhpControlName($oNode->aAttrs['name']))) {
			if (!isset($this->aControls[$aPhpName[0]])) {
				$this->aControls[$aPhpName[0]] =& new CVDControl($aPhpName[0]);
			}

			$nIndex = -1;
			if (!isset($oNode->aAttrs['type']) ||
			!in_array(strtolower($oNode->aAttrs['type']), array('reset', 'button', 'image'))) {
				$nIndex = $this->nIdxCount;
				$this->nIdxCount++;
			}

			$oInput =& $this->aControls[$aPhpName[0]]->AddInput($oNode, $aPhpName, $nIndex);
			if ($oInput->nIndex >= 0) {
				if (isset($oInput->oNode->aAttrs['id'])) {
					$sId = VDHtmlDecode($oInput->oNode->aAttrs['id']);
					$this->SetInputIndex($sId, $oInput);
				}
				if (isset($oInput->oNode->aAttrs['name'])) {
					$sName = VDHtmlDecode($oInput->oNode->aAttrs['name']);
					if (!isset($sId) || $sId != $sName) {
						$this->SetInputIndex($sName, $oInput);
					}
				}
			}
		}
	}

	function SetInputIndex($sKey, &$oInput) {
		if (!isset($this->aInputIndex[$sKey])) {
			$this->aInputIndex[$sKey] = array();
		}
		$this->aInputIndex[$sKey][] =& $oInput;
	}

	function &GetInputIndex($sKey) {
		$aResult = array();
		if (isset($this->aInputIndex[$sKey])) {
			$aResult =& $this->aInputIndex[$sKey];
		}
		return $aResult;
	}

	function ValidatorExists($sName)
	{
		$bResult = false;

		if ($sName) {
			foreach ($this->aValidators as $nIdx => $oValidator) {
				$bResult = $this->aValidators[$nIdx]->ValidatorExists($sName);
				if ($bResult) {
					break;
				}
			}
		}

		return $bResult;
	}

	function ControlExists($aCtrlName)
	{
		$bResult = false;

		if (is_array($aCtrlName) && $aCtrlName[0] && isset($this->aControls[$aCtrlName[0]])) {
			foreach ($aCtrlName as $nIdx => $mTmp) {
				$bResult = true;
				if ($nIdx == 0) {
					continue;
				} else {
					if ($aCtrlName[$nIdx] !== '' && !is_int($aCtrlName[$nIdx])) {
						$bResult = false;
						foreach ($this->aControls[$aCtrlName[0]]->aInputs as $oInput) {
							if (isset($oInput->aPhpName[$nIdx]) && $oInput->aPhpName[$nIdx] === $aCtrlName[$nIdx]) {
								$bResult = true;
								break;
							}
						}

						if (!$bResult) {
							break;
						}
					}
				}
			}
		}

		return $bResult;
	}

	function GetInputClasses($sFor)
	{
		$aResult = array();

		$sFor = VDHtmlDecode($sFor);
		foreach ($this->aControls as $sKey => $mTmp) {
			foreach ($this->aControls[$sKey]->aInputs as $oInput) {
				if ($oInput->nIndex >= 0 &&
				(isset($oInput->oNode->aAttrs['name'])
				&& VDHtmlDecode($oInput->oNode->aAttrs['name']) == $sFor) ||
				(isset($oInput->oNode->aAttrs['id'])
				&& VDHtmlDecode($oInput->oNode->aAttrs['id']) == $sFor)) {
					$sClass = isset($oInput->oNode->aAttrs['class']) ? VDEscape($oInput->oNode->aAttrs['class']) : '';
					$aResult[$oInput->nIndex] = $sClass;
				}
			}
		}
		ksort($aResult);

		return $aResult;
	}

	function SetInputErrClass($sFor, $sClass)
	{
		$sFor = VDHtmlDecode($sFor);
		if (isset($this->aInputIndex[$sFor])) {
			foreach ($this->aInputIndex[$sFor] as $nIdx => $mTmp) {
				$this->aInputIndex[$sFor][$nIdx]->oNode->aAttrs['class'] = $sClass;
			}
		}
	}

	function CheckSyntax(&$oPage)
	{
		if (!isset($this->oNode->aAttrs['method']) ||
		strcasecmp($this->oNode->aAttrs['method'], 'post') != 0) {
			$oPage->MakeErrorMessage(VD_E_FORM_METHOD, $this->oNode);
		}
		if (isset($this->oNode->aAttrs['disablebuttons']) &&
		!in_array(strtolower($this->oNode->aAttrs['disablebuttons']), array('all', 'submit', 'none'))) {
			$oPage->MakeErrorMessage(VD_E_INVALID_DISABLEBUTTONS, $this->oNode);
		}
		if (isset($this->oNode->aAttrs['validationmode']) &&
		!in_array(strtolower($this->oNode->aAttrs['validationmode']), array('onchange', 'onsubmit'))) {
			$oPage->MakeErrorMessage(VD_E_INVALID_VALIDATIONMODE, $this->oNode);
		}

		foreach ($this->aValidators as $sName => $mTmp) {
			$aErrors = $this->aValidators[$sName]->CheckSyntax($this);
			foreach ($aErrors as $sError) {
				$oPage->MakeErrorMessage($sError, $this->aValidators[$sName]->oNode);
			}
		}
		foreach ($this->aLabels as $nIdx => $mTmp) {
			$this->aLabels[$nIdx]->CheckSyntax($oPage, $this);
		}
		foreach ($this->aSummaries as $nIdx => $mTmp) {
			$this->aSummaries[$nIdx]->CheckSyntax($oPage);
		}
	}

	function ClientValidate()
	{
		if (!isset($this->bClientValidate)) {
			if (isset($this->oNode->aAttrs['clientvalidate']) &&
			strtolower($this->oNode->aAttrs['clientvalidate']) == 'false') {
				$this->bClientValidate = false;
			} else {
				$bVals = false;
				foreach ($this->aValidators as $sName => $mTmp) {
					$bVals = $bVals || $this->aValidators[$sName]->ClientValidate();
				}

				$this->bClientValidate = $bVals;
			}
		}

		return $this->bClientValidate;
	}

	function GetClientScript()
	{
		$sScript = '';
		if ($this->ClientValidate()) {
			$sName = VDEscape($this->sName);
			$sButtons = isset($this->oNode->aAttrs['disablebuttons']) ?
				strtolower($this->oNode->aAttrs['disablebuttons']) : 'none';
			$sMode = isset($this->oNode->aAttrs['validationmode']) ?
				strtolower($this->oNode->aAttrs['validationmode']) : 'onsubmit';
			$sScript = "\nf=new Object(); f.name=\"$sName\"; f.disablebuttons=\"$sButtons\";" .
				" f.validationmode=\"$sMode\"; f.validators=new Array(); f.labels=new Array();" .
				" f.summaries=new Array(); v=new Array(); v[0]=f; i=1;\n";

			foreach ($this->aValidators as $sName => $mTmp) {
				$sScript .= $this->aValidators[$sName]->GetClientScript();
			}
			foreach ($this->aLabels as $nIdx => $mTmp) {
				$sScript .= $this->aLabels[$nIdx]->GetClientScript($this);
			}
			foreach ($this->aSummaries as $nIdx => $mTmp) {
				$sScript .= $this->aSummaries[$nIdx]->GetClientScript();
			}

			$sScript .= "vdAllForms[f.name]=f;\n";
		}

		return $sScript;
	}

	function Prepare()
	{
		global $oVDaemonStatus;

		foreach ($this->aLabels as $nIdx => $mTmp) {
			$this->aLabels[$nIdx]->Prepare($this);
		}
		foreach ($this->aSummaries as $nIdx => $mTmp) {
			$this->aSummaries[$nIdx]->Prepare($this);
		}

		foreach ($this->aControls as $sName => $mTmp) {
			$this->aControls[$sName]->Prepare();
		}

		$sPopulate = isset($this->oNode->aAttrs['populate']) ? strtolower($this->oNode->aAttrs['populate']) : 'true';
		if ($sPopulate != 'false') {
			$sForm = $sPopulate == 'always' ? $this->sName : '';
			$bPopulate = $sPopulate == 'always' && isset($_SESSION['VDaemonData']['POST'][$sForm]);
			if ($oVDaemonStatus && $this->sName == $oVDaemonStatus->sForm && !$oVDaemonStatus->bValid) {
				$bPopulate = true;
				$sForm = '';
			}
			if ($bPopulate) {
				foreach ($this->aControls as $sName => $mTmp) {
					$this->aControls[$sName]->Populate($sForm);
				}
			}
		}

		$oRuntime =& new CVDValRuntime();
		$oRuntime->Fill($this);

		$this->oNode->aSubNodes[] = "\n";
		$sValue = serialize($oRuntime);
		$sValue = VDSecurityEncode($sValue);
		if (!$sValue) {
			echo VDErrorMessage(VD_E_SERIALIZE);
			exit;
		}

		$oSubNode =& $this->oNode->AddSubNode('div', array('style' => 'display:none'));
		$oSubNode->AddSubNode('input', array('type' => 'hidden',
											 'name' => 'VDaemonValidators',
											 'value' => $sValue));
		$this->oNode->aSubNodes[] = "\n";

		unset($this->oNode->aAttrs['clientvalidate']);
		unset($this->oNode->aAttrs['runat']);
		unset($this->oNode->aAttrs['populate']);
		unset($this->oNode->aAttrs['disablebuttons']);
		unset($this->oNode->aAttrs['validationmode']);
	}
}

//--------------------------------------------------------------------------
//				  class CVDSummary
//--------------------------------------------------------------------------

class CVDSummary
{
	var $oNode;
	var $sId;

	function CVDSummary(&$oNode, $sId)
	{
		$this->oNode =& $oNode;
		$this->sId = $sId;
	}

	function CheckSyntax(&$oPage)
	{
		if (isset($this->oNode->aAttrs['displaymode']) &&
		!in_array(strtolower($this->oNode->aAttrs['displaymode']), array('list','bulletlist','paragraph'))) {
			$oPage->MakeErrorMessage(VD_E_DISPLAYMODE_INVALID, $this->oNode);
		}
	}

	function GetClientScript()
	{
		$sScript = '';
		$sScript = "s=new Object(); s.id=\"$this->sId\";";

		$sHeaderText = isset($this->oNode->aAttrs['headertext']) ? VDEscape($this->oNode->aAttrs['headertext']) : '';
		$sScript .= " s.headertext=\"$sHeaderText\";";

		$sDisplayMode = isset($this->oNode->aAttrs['displaymode']) ?
			strtolower($this->oNode->aAttrs['displaymode']) : 'list';
		$sScript .= " s.displaymode=\"$sDisplayMode\";";

		$sShowSummary = (isset($this->oNode->aAttrs['showsummary']) &&
			strtolower($this->oNode->aAttrs['showsummary']) == 'false') ? 'false' : 'true';
		$sScript .= " s.showsummary=$sShowSummary;";

		$sMessageBox = (isset($this->oNode->aAttrs['messagebox']) &&
			strtolower($this->oNode->aAttrs['messagebox']) != 'false') ? 'true' : 'false';
		$sScript .= " s.messagebox=$sMessageBox;";

		$sScript .= " f.summaries[f.summaries.length]=s;\n";

		return $sScript;
	}

	function Prepare(&$oForm)
	{
		global $oVDaemonStatus;
		$bValid = true;

		if ($oVDaemonStatus && $oForm->sName == $oVDaemonStatus->sForm) {
			$bValid = $oVDaemonStatus->bValid;
		}

		$bShowSummary = (isset($this->oNode->aAttrs['showsummary']) &&
			strtolower($this->oNode->aAttrs['showsummary']) == 'false') ? false : true;
		if (!$bValid && $bShowSummary) {
			$sDisplayMode = isset($this->oNode->aAttrs['displaymode']) ?
				strtolower($this->oNode->aAttrs['displaymode']) : 'list';
			switch ($sDisplayMode) {
				case "list":
				default:
					$sHeaderSep = '<br>';
					$sFirst = '';
					$sPre = '';
					$sPost = '<br>';
					$sLast = '';
					break;

				case "bulletlist":
					$sHeaderSep = '';
					$sFirst = '<ul>';
					$sPre = '<li>';
					$sPost = '</li>';
					$sLast = '</ul>';
					break;

				case "paragraph":
					$sHeaderSep = ' ';
					$sFirst = '';
					$sPre = '';
					$sPost = ' ';
					$sLast = '';
					break;
			}

			$sSummary = '';
			foreach ($oVDaemonStatus->aValidators as $oValidator) {
				$sSummary .= $oValidator->GetErrMsg($sPre, $sPost);
			}

			if ($sSummary != '') {
				$sSummary = $sFirst . $sSummary . $sLast;
				if (isset($this->oNode->aAttrs['headertext'])) {
					$sSummary = $this->oNode->aAttrs['headertext'] . $sHeaderSep . $sSummary;
				}
			} elseif (isset($this->oNode->aAttrs['headertext'])) {
				$sSummary = $this->oNode->aAttrs['headertext'];
			}

			if ($sSummary != '') {
				$this->oNode->aSubNodes = array();
				$this->oNode->aSubNodes[] = $sSummary;
			}
		}

		$this->oNode->sName = 'div';
		$this->oNode->aAttrs['id'] = $this->sId;
		if (!$this->oNode->aSubNodes) {
			$this->oNode->aAttrs['style'] = 'display:none';
			//$this->oNode->aSubNodes[] = $bShowSummary ? '&nbsp;' : '';
			$this->oNode->aSubNodes[] = '';
		}

		unset($this->oNode->aAttrs['headertext']);
		unset($this->oNode->aAttrs['displaymode']);
		unset($this->oNode->aAttrs['showsummary']);
		unset($this->oNode->aAttrs['messagebox']);
		unset($this->oNode->aAttrs['form']);
	}
}

//--------------------------------------------------------------------------
//				  class CVDLabel
//--------------------------------------------------------------------------

class CVDLabel
{
	var $oNode;
	var $sId;

	function CVDLabel(&$oNode, $sId)
	{
		$this->oNode =& $oNode;
		$this->sId = $sId;
	}

	function CheckSyntax(&$oPage, &$oForm)
	{
		if (!isset($this->oNode->aAttrs['validators'])) {
			$oPage->MakeErrorMessage(VD_E_VALIDATORS_MISSED, $this->oNode);
		} else {
			$aValidators = explode(',', $this->oNode->aAttrs['validators']);
			foreach ($aValidators as $sValidator) {
				$sValidator = trim($sValidator);
				if (!$oForm->ValidatorExists($sValidator)) {
					$oPage->MakeErrorMessage(VD_E_VALIDATOR_NOT_FOUND, $this->oNode);
				}
			}
		}
	}

	function GetClientScript(&$oForm)
	{
		$sScript = '';
		$sScript = "l=new Object(); l.id=\"$this->sId\";";

		$sOkText = VDEscape($this->oNode->SerializeWithoutRoot());
		$sOkText = preg_replace('/\s+/', ' ', $sOkText);
		$sScript .= " l.oktext=\"$sOkText\";";

		$sErrText = isset($this->oNode->aAttrs['errtext']) ? VDEscape($this->oNode->aAttrs['errtext']) : $sOkText;
		$sScript .= " l.errtext=\"$sErrText\";";

		$sOkClass = isset($this->oNode->aAttrs['class']) ? VDEscape($this->oNode->aAttrs['class']) : '';
		$sScript .= " l.okclass=\"$sOkClass\";";

		$sErrClass = isset($this->oNode->aAttrs['errclass']) ? VDEscape($this->oNode->aAttrs['errclass']) : $sOkClass;
		$sScript .= " l.errclass=\"$sErrClass\";";

		$sScript .= " l.validators=new Array(";
		$aValidators = explode(',', $this->oNode->aAttrs['validators']);
		foreach ($aValidators as $nIdx => $mTmp) {
			$aValidators[$nIdx] = '"' . trim(VDEscape($aValidators[$nIdx])) . '"';
		}
		$sScript .= join(',', $aValidators) . ');';

		if (isset($this->oNode->aAttrs['for']) && isset($this->oNode->aAttrs['cerrclass'])) {
			$sCErrClass = VDEscape($this->oNode->aAttrs['cerrclass']);
			$sScript .= " l.cerrclass=\"$sCErrClass\";";
			$sScript .= " l.cokclass=new Object();";
			$aCOkClasses = $oForm->GetInputClasses($this->oNode->aAttrs['for']);
			foreach ($aCOkClasses as $nIdx => $mTmp) {
				$sScript .= " l.cokclass[$nIdx]=\"{$aCOkClasses[$nIdx]}\";";
			}
		}

		$sScript .= " f.labels[f.labels.length]=l;\n";

		return $sScript;
	}

	function Prepare(&$oForm)
	{
		global $oVDaemonStatus;
		$bValid = true;

		if ($oVDaemonStatus && $oForm->sName == $oVDaemonStatus->sForm && !$oVDaemonStatus->bValid) {
			$aValidators = explode(',', $this->oNode->aAttrs['validators']);
			foreach ($aValidators as $sValidator) {
				$sValidator = trim($sValidator);
				$bState = $oVDaemonStatus->GetValidatorState($sValidator);
				if ($bState != -1) {
					$bValid = $bValid && $bState;
				}
			}
		}

		if (!$bValid) {
			if (isset($this->oNode->aAttrs['errclass'])) {
				$this->oNode->aAttrs['class'] = $this->oNode->aAttrs['errclass'];
			}
			if (isset($this->oNode->aAttrs['errtext'])) {
				$this->oNode->aSubNodes = array();
				$this->oNode->aSubNodes[] = $this->oNode->aAttrs['errtext'];
			}
			if (isset($this->oNode->aAttrs['for']) && isset($this->oNode->aAttrs['cerrclass'])) {
				$oForm->SetInputErrClass($this->oNode->aAttrs['for'], $this->oNode->aAttrs['cerrclass']);
			}
		}

		$this->oNode->sName = 'label';
		$this->oNode->aAttrs['id'] = $this->sId;
		if (!$this->oNode->aSubNodes) {
			$this->oNode->aSubNodes[] = '';
		}

		unset($this->oNode->aAttrs['errtext']);
		unset($this->oNode->aAttrs['errclass']);
		unset($this->oNode->aAttrs['cerrclass']);
		unset($this->oNode->aAttrs['validators']);
		unset($this->oNode->aAttrs['form']);
	}
}

//--------------------------------------------------------------------------
//				  class CVDValidator
//--------------------------------------------------------------------------

class CVDValidator
{
	var $oNode;
	var $bClientValidate;
	var $aValidators;
	var $aCtrlName;

	function CVDValidator(&$oNode)
	{
		$this->oNode =& $oNode;
		$this->oNode->bWithoutRoot = true;
		$this->aValidators = array();
		foreach ($oNode->aSubNodes as $nIdx => $mTmp) {
			$oSubNode =& $oNode->aSubNodes[$nIdx];
			if (is_object($oSubNode) && in_array($oSubNode->sName, array('vlgroup', 'vlvalidator'))) {
				$this->aValidators[] =& new CVDValidator($oSubNode);
			}
		}
	}

	function GetName()
	{
		$sName = isset($this->oNode->aAttrs['name']) ? $this->oNode->aAttrs['name'] : '';
		return $sName;
	}

	function &AddValidator(&$oNode)
	{
		$oValidator =& new CVDValidator($oNode);
		$this->aValidators[] =& $oValidator;

		return $oValidator;
	}

	function ValidatorExists($sName)
	{
		$bResult = false;

		if ($sName) {
			if ($this->GetName() == $sName) {
				$bResult = true;
			} else {
				foreach ($this->aValidators as $nIdx => $oValidator) {
					$bResult = $this->aValidators[$nIdx]->ValidatorExists($sName);
					if ($bResult) {
						break;
					}
				}
			}
		}

		return $bResult;
	}

	function CheckSyntax($oForm)
	{
		$aErrors = array();

		if ($this->oNode->sName == 'vlgroup') {
			if (isset($this->oNode->aAttrs['operator']) &&
				!in_array(strtolower($this->oNode->aAttrs['operator']), array('or','and','xor'))) {
				$aErrors[] = VD_E_OPERATOR_INVALID;
			}

			foreach ($this->aValidators as $nIdx => $mTmp) {
				$aErrors = array_merge($aErrors, $this->aValidators[$nIdx]->CheckSyntax($oForm));
			}

			if (!$this->aValidators) {
				$aErrors[] = VD_E_GROUP_EMPTY;
			}
		} else {
			if (!isset($this->oNode->aAttrs['type'])) {
				$aErrors[] = VD_E_UNTYPED_VALIDATOR;
			} else {
				$sType = strtolower($this->oNode->aAttrs['type']);
				if (!isset($this->oNode->aAttrs['control'])) {
					if ($sType != 'custom') {
						$aErrors[] = VD_E_CONTROL_MISSED;
					}
				} else {
					$this->aCtrlName = VDGetPhpControlName($this->oNode->aAttrs['control']);
					if ($oForm && !$oForm->ControlExists($this->aCtrlName)) {
						$aErrors[] = VD_E_VALIDATOR_CONTROL;
					} else {
						foreach ($this->aCtrlName as $sIdx) {
							if ($sIdx === '') {
								$aErrors[] = VD_E_CONTROL_INVALID;
								break;
							}
						}
					}
				}

				switch ($sType) {
					default:
						$aErrors[] = VD_E_VALIDATOR_TYPE;
						break;

					case 'required':
						if (isset($this->oNode->aAttrs['minlength']) &&
						!preg_match('/^(0|[1-9]\d*)$/', $this->oNode->aAttrs['minlength'])) {
							$aErrors[] = VD_E_MINLENGTH_INVALID;
						}
						if (isset($this->oNode->aAttrs['maxlength']) &&
						!preg_match('/^(0|[1-9]\d*)$/', $this->oNode->aAttrs['maxlength'])) {
							$aErrors[] = VD_E_MAXLENGTH_INVALID;
						}
						break;

					case 'checktype':
					case 'range':
					case 'compare':
						if (!isset($this->oNode->aAttrs['validtype'])) {
							$aErrors[] = VD_E_VALIDTYPE_MISSED;
						} elseif (!in_array(strtolower($this->oNode->aAttrs['validtype']),
						array('string','integer','float','date','time','datetime','currency'))) {
							$aErrors[] = VD_E_VALIDTYPE_INVALID;
						} elseif (in_array(strtolower($this->oNode->aAttrs['validtype']), array('date','datetime')) &&
						isset($this->oNode->aAttrs['dateorder']) &&
						!in_array(strtolower($this->oNode->aAttrs['dateorder']), array('ymd','dmy','mdy'))) {
							$aErrors[] = VD_E_DATEORDER_INVALID;
						} elseif (in_array(strtolower($this->oNode->aAttrs['validtype']), array('time','datetime')) &&
						isset($this->oNode->aAttrs['timeformat']) &&
						!in_array(strtolower($this->oNode->aAttrs['timeformat']), array('12','24'))) {
							$aErrors[] = VD_E_TIMEFORMAT_INVALID;
						} elseif (in_array(strtolower($this->oNode->aAttrs['validtype']), array('integer','float','currency')) &&
						isset($this->oNode->aAttrs['groupchar']) && strlen($this->oNode->aAttrs['groupchar']) > 1) {
							$aErrors[] = VD_E_GROUPCHAR_INVALID_LENGTH;
						} elseif (in_array(strtolower($this->oNode->aAttrs['validtype']), array('integer','float','currency')) &&
						isset($this->oNode->aAttrs['groupchar']) &&
						preg_match('/[\d+-]/', $this->oNode->aAttrs['groupchar'])) {
							$aErrors[] = VD_E_GROUPCHAR_INVALID;
						} elseif (in_array(strtolower($this->oNode->aAttrs['validtype']), array('float','currency')) &&
						isset($this->oNode->aAttrs['decimalchar']) && strlen($this->oNode->aAttrs['decimalchar']) != 1) {
							$aErrors[] = VD_E_DECIMALCHAR_INVALID_LENGTH;
						} elseif (in_array(strtolower($this->oNode->aAttrs['validtype']), array('float','currency')) &&
						isset($this->oNode->aAttrs['decimalchar']) &&
						preg_match('/[\d+-]/', $this->oNode->aAttrs['decimalchar'])) {
							$aErrors[] = VD_E_DECIMALCHAR_INVALID;
						} elseif (in_array(strtolower($this->oNode->aAttrs['validtype']), array('float','currency')) &&
						isset($this->oNode->aAttrs['groupchar']) &&
						$this->oNode->aAttrs['groupchar'] == (isset($this->oNode->aAttrs['decimalchar']) ? $this->oNode->aAttrs['decimalchar'] : '.')) {
							$aErrors[] = VD_E_GROUP_DECIMAL_CHARS;
						} elseif ($sType == 'range') {
							if (!isset($this->oNode->aAttrs['minvalue'])) {
								$aErrors[] = VD_E_MINVALUE_MISSED;
							} elseif ($this->Convert($this->oNode->aAttrs['minvalue']) === false) {
								$aErrors[] = VD_E_MINVALUE_INVALID;
							}

							if (!isset($this->oNode->aAttrs['maxvalue'])) {
								$aErrors[] = VD_E_MAXVALUE_MISSED;
							} elseif ($this->Convert($this->oNode->aAttrs['maxvalue']) === false) {
								$aErrors[] = VD_E_MAXVALUE_INVALID;
							}
						} elseif ($sType == 'compare') {
							if (!isset($this->oNode->aAttrs['operator'])) {
								$aErrors[] = VD_E_OPERATOR_MISSED;
							} elseif (!in_array(strtolower($this->oNode->aAttrs['operator']), array('e','ne','g','ge','l','le'))) {
								$aErrors[] = VD_E_OPERATOR_INVALID;
							}

							if (isset($this->oNode->aAttrs['comparevalue'])) {
								if ($this->Convert($this->oNode->aAttrs['comparevalue']) === false) {
									$aErrors[] = VD_E_COMPAREVALUE_INVALID;
								}
							} elseif (isset($this->oNode->aAttrs['comparecontrol'])) {
								$aCompareCtrlName = VDGetPhpControlName($this->oNode->aAttrs['comparecontrol']);
								if ($oForm && !$oForm->ControlExists($aCompareCtrlName)) {
									$aErrors[] = VD_E_COMPARECONTROL_NOT_FOUND;
								} else {
									foreach ($aCompareCtrlName as $sIdx) {
										if ($sIdx === '') {
											$aErrors[] = VD_E_COMPARECONTROL_INVALID;
											break;
										}
									}
								}
							} else {
								$aErrors[] = VD_E_COMPAREVALUE_MISSED;
							}
						}
						break;

					case 'format':
						if (!isset($this->oNode->aAttrs['format'])) {
							$aErrors[] = VD_E_FORMAT_MISSED;
						} elseif (!in_array(strtolower($this->oNode->aAttrs['format']),
						array('email','zip_us','zip_us5','zip_us9','zip_canada','zip_uk','phone_us','ip4'))) {
							$aErrors[] = VD_E_FORMAT_INVALID;
						}
						break;

					case 'regexp':
						if (!isset($this->oNode->aAttrs['regexp'])) {
							$aErrors[] = VD_E_REGEXP_MISSED;
						}
						break;

					case 'custom':
						if (!isset($this->oNode->aAttrs['function'])) {
							$aErrors[] = VD_E_FUNCTION_MISSED;
						} elseif (!preg_match('/^[a-zA-Z_]\w*$/', $this->oNode->aAttrs['function'])) {
							$aErrors[] = VD_E_FUNCTION_INVALID;
						} elseif ($oForm == null && !function_exists($this->oNode->aAttrs['function'])) {
							$aErrors[] = str_replace('%%', $this->oNode->aAttrs['function'], VD_E_FUNCTION_NOT_FOUND);
						}
						break;
				}
			}
		}

		return $aErrors;
	}

	function ClientValidate()
	{
		if (!isset($this->bClientValidate)) {
			$bResult = true;

			if (isset($this->oNode->aAttrs['clientvalidate']) &&
				strtolower($this->oNode->aAttrs['clientvalidate']) == 'false') {
				$bResult = false;
			} else {
				$sType = ($this->oNode->sName == 'vlgroup') ? 'group' : strtolower($this->oNode->aAttrs['type']);
				if ($sType == 'custom' && !isset($this->oNode->aAttrs['clientfunction'])) {
					$bResult = false;
				}

				if ($sType == 'group') {
					$bResult = false;
					foreach ($this->aValidators as $nIdx => $mTmp) {
						$bResult = $bResult || $this->aValidators[$nIdx]->ClientValidate();
					}
				}
			}

			$this->bClientValidate = $bResult;
		}

		return $this->bClientValidate;
	}

	function GetClientScript()
	{
		$sScript = '';
		if ($this->ClientValidate()) {
			$sScript = "v[i]=new Object();";

			$sType = ($this->oNode->sName == 'vlgroup') ? 'group' : strtolower($this->oNode->aAttrs['type']);
			$sScript .= " v[i].type=\"$sType\";";

			$sName = isset($this->oNode->aAttrs['name']) ? VDEscape($this->oNode->aAttrs['name']) : '';
			$sScript .= " v[i].name=\"$sName\";";

			$sErrMsg = isset($this->oNode->aAttrs['errmsg']) ? VDEscape($this->oNode->aAttrs['errmsg']) : '';
			$sScript .= " v[i].errmsg=\"$sErrMsg\";";

			if ($sType != 'group' && ($sType != 'custom' || isset($this->aCtrlName))) {
				$bFocus = !isset($this->oNode->aAttrs['setfocus']) || $this->oNode->aAttrs['setfocus'] !== 'false';
				if ($bFocus) {
					$sControl = VDEscape($this->oNode->aAttrs['control']);
					$sScript .= " v[i].fcontrol=\"$sControl\";";
				}
				$sScript .= " v[i].control=new Array();";
				foreach ($this->aCtrlName as $sIndex) {
					$sControl = VDEscape($sIndex);
					$sScript .= " v[i].control[v[i].control.length]=\"$sControl\";";
				}
			}

			if ($sType == 'group') {
				$sOperator = isset($this->oNode->aAttrs['operator']) ? strtolower($this->oNode->aAttrs['operator']) : 'or';
				$sScript .= " v[i].operator=\"$sOperator\";";
				$sScript .= " v[i].validators=new Array(); i++;\n";
				foreach ($this->aValidators as $nIdx => $mTmp) {
					$sScript .= $this->aValidators[$nIdx]->GetClientScript();
				}
				$sScript .= "i=i-1; v[i-1].validators[v[i-1].validators.length]=v[i];\n";
			} else {
				$sCase = (isset($this->oNode->aAttrs['casesensitive']) &&
					strtolower($this->oNode->aAttrs['casesensitive']) != 'false') ? 'true' : 'false';
				$sValidType = isset($this->oNode->aAttrs['validtype']) ? strtolower($this->oNode->aAttrs['validtype']) : '';
				$sDateOrder = isset($this->oNode->aAttrs['dateorder']) ? strtolower($this->oNode->aAttrs['dateorder']) : 'mdy';
				$sTimeFormat = isset($this->oNode->aAttrs['timeformat']) ? strtolower($this->oNode->aAttrs['timeformat']) : '12';
				$sRequired = (isset($this->oNode->aAttrs['required']) &&
					strtolower($this->oNode->aAttrs['required']) != 'false') ? 'true' : 'false';
				$sNegation = (isset($this->oNode->aAttrs['negation']) &&
					strtolower($this->oNode->aAttrs['negation']) != 'false') ? 'true' : 'false';
				$sDecimalChar = isset($this->oNode->aAttrs['decimalchar']) ? $this->oNode->aAttrs['decimalchar'] : '.';
				$sDecimalChar = VDEscape(VDPregEscape($sDecimalChar));
				$sGroupChar = isset($this->oNode->aAttrs['groupchar']) ? $this->oNode->aAttrs['groupchar'] : '';
				$sGroupChar = VDEscape(VDPregEscape($sGroupChar));

				switch ($sType) {
					case 'required':
						$sMinLength = isset($this->oNode->aAttrs['minlength']) ? $this->oNode->aAttrs['minlength'] : '1';
						$sMaxLength = isset($this->oNode->aAttrs['maxlength']) ? $this->oNode->aAttrs['maxlength'] : '-1';
						$sScript .= " v[i].minlength=$sMinLength;";
						$sScript .= " v[i].maxlength=$sMaxLength;";
						$sScript .= " v[i].negation=$sNegation;";
						break;

					case 'checktype':
						$sScript .= " v[i].required=$sRequired;";
						$sScript .= " v[i].negation=$sNegation;";
						$sScript .= " v[i].validtype=\"$sValidType\";";
						if (in_array($sValidType, array('date','datetime'))) {
							$sScript .= " v[i].dateorder=\"$sDateOrder\";";
						}
						if (in_array($sValidType, array('time','datetime'))) {
							$sScript .= " v[i].timeformat=\"$sTimeFormat\";";
						}
						if (in_array($sValidType, array('float','currency'))) {
							$sScript .= " v[i].decimalchar=\"$sDecimalChar\";";
						}
						if (in_array($sValidType, array('integer','float','currency'))) {
							$sScript .= " v[i].groupchar=\"$sGroupChar\";";
						}
						break;

					case 'range':
						$sScript .= " v[i].required=$sRequired;";
						$sScript .= " v[i].negation=$sNegation;";
						$sMin = isset($this->oNode->aAttrs['minvalue']) ? VDEscape($this->oNode->aAttrs['minvalue']) : '';
						$sMax = isset($this->oNode->aAttrs['maxvalue']) ? VDEscape($this->oNode->aAttrs['maxvalue']) : '';
						$sScript .= " v[i].validtype=\"$sValidType\";";
						if (in_array($sValidType, array('date','datetime'))) {
							$sScript .= " v[i].dateorder=\"$sDateOrder\";";
						}
						if (in_array($sValidType, array('time','datetime'))) {
							$sScript .= " v[i].timeformat=\"$sTimeFormat\";";
						}
						if (in_array($sValidType, array('float','currency'))) {
							$sScript .= " v[i].decimalchar=\"$sDecimalChar\";";
						}
						if (in_array($sValidType, array('integer','float','currency'))) {
							$sScript .= " v[i].groupchar=\"$sGroupChar\";";
						}
						$sScript .= " v[i].casesensitive=$sCase;";
						$sScript .= " v[i].minvalue=\"$sMin\";";
						$sScript .= " v[i].maxvalue=\"$sMax\";";
						break;

					case 'compare':
						$sScript .= " v[i].required=$sRequired;";
						$sScript .= " v[i].negation=$sNegation;";
						$sScript .= " v[i].validtype=\"$sValidType\";";
						if (in_array($sValidType, array('date','datetime'))) {
							$sScript .= " v[i].dateorder=\"$sDateOrder\";";
						}
						if (in_array($sValidType, array('time','datetime'))) {
							$sScript .= " v[i].timeformat=\"$sTimeFormat\";";
						}
						if (in_array($sValidType, array('float','currency'))) {
							$sScript .= " v[i].decimalchar=\"$sDecimalChar\";";
						}
						if (in_array($sValidType, array('integer','float','currency'))) {
							$sScript .= " v[i].groupchar=\"$sGroupChar\";";
						}
						$sScript .= " v[i].casesensitive=$sCase;";
						if (isset($this->oNode->aAttrs['comparevalue'])) {
							$sVal = VDEscape($this->oNode->aAttrs['comparevalue']);
							$sScript .= " v[i].comparevalue=\"$sVal\";";
						} else {
							$aCompareCtrlName = VDGetPhpControlName($this->oNode->aAttrs['comparecontrol']);
							$sScript .= " v[i].comparecontrol=new Array();";
							foreach ($aCompareCtrlName as $sIndex) {
								$sVal = VDEscape($sIndex);
								$sScript .= " v[i].comparecontrol[v[i].comparecontrol.length]=\"$sVal\";";
							}
						}
						$sOperator = isset($this->oNode->aAttrs['operator']) ? strtolower($this->oNode->aAttrs['operator']) : 'e';
						$sScript .= " v[i].operator=\"$sOperator\";";
						break;

					case 'format':
						$sScript .= " v[i].required=$sRequired;";
						$sScript .= " v[i].negation=$sNegation;";
						$sFormat = strtolower($this->oNode->aAttrs['format']);
						$sScript .= " v[i].format=\"$sFormat\";";
						break;

					case 'regexp':
						$sScript .= " v[i].required=$sRequired;";
						$sScript .= " v[i].negation=$sNegation;";
						$sRegExp = isset($this->oNode->aAttrs['clientregexp']) ?
							VDEscape($this->oNode->aAttrs['clientregexp']) :
							VDEscape($this->oNode->aAttrs['regexp']);
						$sScript .= " v[i].clientregexp=\"$sRegExp\";";
						break;

					case 'custom':
						$sFunc = isset($this->oNode->aAttrs['clientfunction']) ? $this->oNode->aAttrs['clientfunction'] : '';
						$sScript .= " v[i].clientfunction=\"$sFunc\";";
						break;
				}

				$sScript .= " v[i-1].validators[v[i-1].validators.length]=v[i];\n";
			}
		}

		return $sScript;
	}

	function Convert($sValue)
	{
		$mResult = false;

		if (isset($this->oNode->aAttrs['validtype'])) {
			$sType = strtolower($this->oNode->aAttrs['validtype']);
			$sDecimalChar = isset($this->oNode->aAttrs['decimalchar']) ? $this->oNode->aAttrs['decimalchar'] : '.';
			$sGroupChar = isset($this->oNode->aAttrs['groupchar']) ? $this->oNode->aAttrs['groupchar'] : '';
			switch ($sType) {
				case 'string':
					$mResult = strval($sValue);
					break;

				case 'integer':
					$sSubPattern = $sGroupChar != '' ? VDPregEscape($sGroupChar) . '?' : '';
					$sPattern = '/^\s*[-+]?\d{1,3}(?:' . $sSubPattern . '\d{3})*\s*$/';
					if (preg_match($sPattern, $sValue)) {
						if ($sGroupChar != '') {
							$sValue = str_replace($sGroupChar, '', $sValue);
						}
						$mResult = intval($sValue);
					}
					break;

				case 'float':
					$sSubPattern = $sGroupChar != '' ? VDPregEscape($sGroupChar) . '?' : '';
					$sPattern = '/^\s*[-+]?(\d{1,3}(?:' . $sSubPattern . '\d{3})*)?(' . VDPregEscape($sDecimalChar) . '\d+)?\s*$/';
					if (preg_match($sPattern, $sValue)) {
						if ($sGroupChar != '') {
							$sValue = str_replace($sGroupChar, '', $sValue);
						}
						if ($sDecimalChar != '.') {
							$sValue = str_replace($sDecimalChar, '.', $sValue);
						}
						if (is_numeric($sValue)) {
							$mResult = doubleval($sValue);
						}
					}
					break;

				case 'currency':
					$sSubPattern = $sGroupChar != '' ? VDPregEscape($sGroupChar) . '?' : '';
					$sPattern = '/^\s*[-+]?(\d{1,3}(?:' . $sSubPattern . '\d{3})*)?(' . VDPregEscape($sDecimalChar) . '\d{1,2})?\s*$/';
					if (preg_match($sPattern, $sValue)) {
						if ($sGroupChar != '') {
							$sValue = str_replace($sGroupChar, '', $sValue);
						}
						if ($sDecimalChar != '.') {
							$sValue = str_replace($sDecimalChar, '.', $sValue);
						}
						if (is_numeric($sValue)) {
							$mResult = doubleval($sValue);
						}
					}
					break;

				case 'date':
					$mResult = $this->ConvertDate($sValue);
					break;

				case 'time':
					$mResult = $this->ConvertTime($sValue);
					break;

				case 'datetime':
					if (preg_match('/^\s*([-\d\.\/]+)\s+([\d:]+\s?(?:PM|AM)?)\s*$/i', $sValue, $aMatches)) {
						$sDate = $this->ConvertDate($aMatches[1]);
						$sTime = $this->ConvertTime($aMatches[2]);
						if ($sDate && $sTime) {
							$mResult = $sDate . $sTime;
						}
					}
					break;
			}
		}

		return $mResult;
	}

	function ConvertDate($sValue)
	{
		$sResult = false;
		$sYear = -1;
		$sDateOrder = isset($this->oNode->aAttrs['dateorder']) ? strtolower($this->oNode->aAttrs['dateorder']) : 'mdy';
		if ($sDateOrder == 'ymd') {
			if (preg_match('|^\s*(\d{2}(\d{2})?)([-\./])(\d{1,2})\3(\d{1,2})\s*$|', $sValue, $aMatches)) {
				$nDay = intval($aMatches[5]);
				$nMonth = intval($aMatches[4]);
				$sYear = $aMatches[1];
			}
		} elseif (preg_match('|^\s*(\d{1,2})([-\./])(\d{1,2})\2(\d{2}(\d{2})?)\s*$|', $sValue, $aMatches)) {
			$sYear = $aMatches[4];

			if ($sDateOrder == 'dmy') {
				$nDay = intval($aMatches[1]);
				$nMonth = intval($aMatches[3]);
			} else {
				$nDay = intval($aMatches[3]);
				$nMonth = intval($aMatches[1]);
			}
		}

		if ($sYear != -1) {
			$nYear = intval($sYear);
			if (strlen($sYear) < 3) {
				$nYear += 2000 - ($nYear < 30 ? 0 : 100);
				$sYear = strval($nYear);
			}

			if (checkdate($nMonth, $nDay, $nYear)) {
				if ($nDay < 10) {
					$nDay = '0' . $nDay;
				}
				if ($nMonth < 10) {
					$nMonth = '0' . $nMonth;
				}

				$sResult = $sYear . $nMonth . $nDay;
			}
		}

		return $sResult;
	}

	function ConvertTime($sValue)
	{
		$sResult = false;
		$sTimeFormat = isset($this->oNode->aAttrs['timeformat']) ? strtolower($this->oNode->aAttrs['timeformat']) : '12';
		if ($sTimeFormat == '12') {
			if (preg_match('/^\s*(\d{1,2}):(\d{2})(?::(\d{2}))?\s?(PM|AM)\s*$/i', $sValue, $aMatches)) {
				$nHour = intval($aMatches[1]);
				$nMin = intval($aMatches[2]);
				$nSec = intval($aMatches[3]);
				$sSuf = strtolower($aMatches[4]);

				if ($nHour >= 1 && $nHour <= 12 && $nMin >= 0 && $nMin <= 59 && $nSec >= 0 && $nSec <= 59) {
					if ($nHour == 12) {
						$nHour = $sSuf == 'am' ? 0 : 12;
					} elseif ($sSuf == 'pm') {
						$nHour += 12;
					}
					if ($nHour < 10) {
						$nHour = '0' . $nHour;
					}
					if ($nMin < 10) {
						$nMin = '0' . $nMin;
					}
					if ($nSec < 10) {
						$nSec = '0' . $nSec;
					}
					$sResult = $nHour . $nMin . $nSec;
				}
			}
		} elseif ($sTimeFormat == '24') {
			if (preg_match('/^\s*(\d{1,2}):(\d{2})(?::(\d{2}))?\s*$/', $sValue, $aMatches)) {
				$nHour = intval($aMatches[1]);
				$nMin = intval($aMatches[2]);
				$nSec = intval($aMatches[3]);

				if ($nHour >= 0 && $nHour <= 23 && $nMin >= 0 && $nMin <= 59 && $nSec >= 0 && $nSec <= 59) {
					if ($nHour < 10) {
						$nHour = '0' . $nHour;
					}
					if ($nMin < 10) {
						$nMin = '0' . $nMin;
					}
					if ($nSec < 10) {
						$nSec = '0' . $nSec;
					}
					$sResult = $nHour . $nMin . $nSec;
				}
			}
		}

		return $sResult;
	}

	function Compare($sOperand1, $sOperand2, $sOperator)
	{
		$bResult = true;

		if (($mOp1 = $this->Convert($sOperand1)) === false) {
			$bResult = false;
		} elseif (($mOp2 = $this->Convert($sOperand2)) !== false) {
			$sValidType = strtolower($this->oNode->aAttrs['validtype']);
			$bCase = (isset($this->oNode->aAttrs['casesensitive']) &&
				strtolower($this->oNode->aAttrs['casesensitive']) != 'false') ? true : false;

			if ($sValidType == "string" && !$bCase) {
				$mOp1 = strtolower($mOp1);
				$mOp2 = strtolower($mOp2);
			}

			switch ($sOperator) {
				case "ne":
					$bResult = ($mOp1 != $mOp2);
					break;

				case "g":
					$bResult = ($mOp1 > $mOp2);
					break;

				case "ge":
					$bResult = ($mOp1 >= $mOp2);
					break;

				case "l":
					$bResult = ($mOp1 < $mOp2);
					break;

				case "le":
					$bResult = ($mOp1 <= $mOp2);
					break;

				case "e":
				default:
					$bResult = ($mOp1 == $mOp2);
					break;
			}
		}

		return $bResult;
	}

	function &Validate()
	{
		$oStatus =& new CVDValidatorStatus();
		$oStatus->bValid = true;
		$oStatus->sName = isset($this->oNode->aAttrs['name']) ? $this->oNode->aAttrs['name'] : '';
		$oStatus->sErrMsg = isset($this->oNode->aAttrs['errmsg']) ? $this->oNode->aAttrs['errmsg'] : '';

		$sType = ($this->oNode->sName == 'vlgroup') ? 'group' : strtolower($this->oNode->aAttrs['type']);
		$sCtrlVal = isset($this->aCtrlName) ? VDGetValue($this->aCtrlName) : null;
		$bCase = (isset($this->oNode->aAttrs['casesensitive']) &&
				  strtolower($this->oNode->aAttrs['casesensitive']) != 'false') ? true : false;
		$bRequired = (isset($this->oNode->aAttrs['required']) &&
			strtolower($this->oNode->aAttrs['required']) != 'false') ? true : false;
		$bNegation = (isset($this->oNode->aAttrs['negation']) &&
			strtolower($this->oNode->aAttrs['negation']) != 'false') ? true : false;

		switch ($sType) {
			case "required":
				$nMinLength = isset($this->oNode->aAttrs['minlength']) ? intval($this->oNode->aAttrs['minlength']) : 1;
				$nMaxLength = isset($this->oNode->aAttrs['maxlength']) ? intval($this->oNode->aAttrs['maxlength']) : -1;

				if (is_array($sCtrlVal)) {
					$nLen = 0;
					foreach ($sCtrlVal as $sValue) {
						if ($sValue != '') {
							$nLen++;
						}
					}
				} else {
					$nLen = strlen($sCtrlVal);
				}
				$oStatus->bValid = $nMinLength <= $nLen;
				if ($oStatus->bValid && $nMaxLength != -1) {
					$oStatus->bValid = $nLen <= $nMaxLength;
				}
				if ($bNegation) {
					$oStatus->bValid = !$oStatus->bValid;
				}
				break;

			case "checktype":
				if (!is_array($sCtrlVal)) {
					if ($sCtrlVal == '') {
						$oStatus->bValid = !$bRequired;
					} else {
						$oStatus->bValid = $this->Convert($sCtrlVal) !== false;
						if ($bNegation) {
							$oStatus->bValid = !$oStatus->bValid;
						}
					}
				}
				break;

			case "range":
				if (!is_array($sCtrlVal)) {
					if ($sCtrlVal == '') {
						$oStatus->bValid = !$bRequired;
					} else {
						$sMinVal = isset($this->oNode->aAttrs['minvalue']) ? $this->oNode->aAttrs['minvalue'] : '';
						$sMaxVal = isset($this->oNode->aAttrs['maxvalue']) ? $this->oNode->aAttrs['maxvalue'] : '';
						$oStatus->bValid = $this->Compare($sCtrlVal, $sMinVal, 'ge') && $this->Compare($sCtrlVal, $sMaxVal, 'le');
						if ($bNegation) {
							$oStatus->bValid = !$oStatus->bValid;
						}
					}
				}
				break;

			case "compare":
				if (!is_array($sCtrlVal)) {
					if ($sCtrlVal == '') {
						$oStatus->bValid = !$bRequired;
					} else {
						$sCompareVal = '';
						if (isset($this->oNode->aAttrs['comparevalue'])) {
							$sCompareVal = $this->oNode->aAttrs['comparevalue'];
						} else {
							$aCompareCtrlName = VDGetPhpControlName($this->oNode->aAttrs['comparecontrol']);
							$sCompareVal = VDGetValue($aCompareCtrlName);
						}
						$sOperator = isset($this->oNode->aAttrs['operator']) ? strtolower($this->oNode->aAttrs['operator']) : 'e';

						if (!is_array($sCompareVal)) {
							$oStatus->bValid = $this->Compare($sCtrlVal, $sCompareVal, $sOperator);
							if ($bNegation) {
								$oStatus->bValid = !$oStatus->bValid;
							}
						}
					}
				}
				break;

			case "format":
				if (!is_array($sCtrlVal)) {
					if ($sCtrlVal == '') {
						$oStatus->bValid = !$bRequired;
					} else {
						$sFormat = strtolower($this->oNode->aAttrs['format']);
						switch ($sFormat) {
							case 'email':
								$sRegExp = '/^[\w\'\+-]+(\.[\w\'\+-]+)*@[\w-]+(\.[\w-]+)*\.\w{1,8}$/';
								break;

							case 'zip_us5':
								$sRegExp = '/^\d{5}$/';
								break;

							case 'zip_us9':
								$sRegExp = '/^\d{5}[\s-]\d{4}$/';
								break;

							case 'zip_us':
								$sRegExp = '/^\d{5}([\s-]\d{4})?$/';
								break;

							case 'zip_canada':
								$sRegExp = '/^[a-z]\d[a-z]\s?\d[a-z]\d$/i';
								break;

							case 'zip_uk':
								$sRegExp = '/^[a-z](\d|\d[a-z]|\d{2}|[a-z]\d|[a-z]\d[a-z]|[a-z]\d{2})\s?\d[a-z]{2}$/i';
								break;

							case 'phone_us':
								$sRegExp = '/^(\+?\d{1,3})?[-\s\.]?(\(\d{3}\)|\d{3})[-\s\.]?\d{3}[-\s\.]?\d{4}(([-\s\.]|(\s?(x|ext\.?)))\d{1,5})?$/i';
								break;

							case 'ip4':
								$sRegExp = '/^(([3-9]\d?|[01]\d{0,2}|2\d?|2[0-4]\d|25[0-5])\.){3}([3-9]\d?|[01]\d{0,2}|2\d?|2[0-4]\d|25[0-5])$/';
								break;

							default:
								$sRegExp = '/^$/';
								break;
						}

						$oStatus->bValid = preg_match($sRegExp, $sCtrlVal) == true;
						if ($bNegation) {
							$oStatus->bValid = !$oStatus->bValid;
						}
					}
				}
				break;

			case "regexp":
				if (!is_array($sCtrlVal)) {
					if ($sCtrlVal == '') {
						$oStatus->bValid = !$bRequired;
					} else {
						$sRegExp = isset($this->oNode->aAttrs['regexp']) ? $this->oNode->aAttrs['regexp'] : '';
						$oStatus->bValid = preg_match($sRegExp, $sCtrlVal) == true;
						if ($bNegation) {
							$oStatus->bValid = !$oStatus->bValid;
						}
					}
				}
				break;

			case "custom":
				$sFunc = isset($this->oNode->aAttrs['function']) ? $this->oNode->aAttrs['function'] : '';
				if (function_exists($sFunc)) {
					$sFunc($sCtrlVal, $oStatus);
					$this->oNode->aAttrs['errmsg'] = $oStatus->sErrMsg;
				}
				break;

			case "group":
				$sOperator = isset($this->oNode->aAttrs['operator']) ?
					strtolower($this->oNode->aAttrs['operator']) : 'or';
				unset($oStatus->bValid);

				foreach ($this->aValidators as $nIdx => $mTmp) {
					$oSubStatus =& $this->aValidators[$nIdx]->Validate();
					if ($sOperator != 'xor') {
						$oStatus->aValidators[] =& $oSubStatus;
					}
					if (!isset($oStatus->bValid)) {
						$oStatus->bValid = $oSubStatus->bValid;
					} else {
						switch ($sOperator) {
							case 'and':
								$oStatus->bValid = $oStatus->bValid && $oSubStatus->bValid;
								break;

							case 'or':
								$oStatus->bValid = $oStatus->bValid || $oSubStatus->bValid;
								break;

							case 'xor':
								$oStatus->bValid = ($oStatus->bValid xor $oSubStatus->bValid);
								break;
						}
					}
				}
				break;
		}

		return $oStatus;
	}
}

//--------------------------------------------------------------------------
//				  class CVDControl
//--------------------------------------------------------------------------

class CVDControl
{
	var $sName;
	var $aInputs;

	function CVDControl($sName)
	{
		$this->sName = $sName;
		$this->aInputs = array();
	}

	function &AddInput(&$oNode, $aPhpName, $nIndex = -1)
	{
		$oInput =& new CVDInput($oNode, $aPhpName, $nIndex);
		$this->aInputs[] =& $oInput;

		return $oInput;
	}

	function Prepare()
	{
		foreach ($this->aInputs as $nIdx => $mTmp) {
			$this->aInputs[$nIdx]->Prepare();
		}
	}

	function Populate($sForm = '')
	{
		$oMatrix =& new CVDInputMatrix();
		foreach ($this->aInputs as $nIdx => $mTmp) {
			$aPhpName = $oMatrix->ProcessInput($this->aInputs[$nIdx]->aPhpName);
			$sValue = VDGetValue($aPhpName, true, $sForm);

			if ($this->aInputs[$nIdx]->sType == 'select' && isset($this->aInputs[$nIdx]->oNode->aAttrs['multiple'])) {
				$this->aInputs[$nIdx]->ClearMultiple();
				while ($this->aInputs[$nIdx]->SetValue($sValue)) {
					$oMatrix->Commit();
					$aPhpNameNew = $oMatrix->ProcessInput($this->aInputs[$nIdx]->aPhpName);
					if ($aPhpNameNew == $aPhpName) {
						$sValue = null;
					} else {
						$aPhpName = $aPhpNameNew;
						$sValue = VDGetValue($aPhpName, true, $sForm);
					}
				}
				$oMatrix->Rollback();
			} else {
				if ($this->aInputs[$nIdx]->SetValue($sValue) || is_array($sValue)) {
					$oMatrix->Commit();
				} else {
					$oMatrix->Rollback();
				}
			}
		}
	}
}

//--------------------------------------------------------------------------
//				  class CVDInput
//--------------------------------------------------------------------------

class CVDInput
{
	var $oNode;
	var $nIndex;
	var $sType;
	var $bPopulate;
	var $aPhpName;

	function CVDInput(&$oNode, $aPhpName, $nIndex = -1)
	{
		$this->oNode =& $oNode;
		$this->nIndex = $nIndex;
		$this->aPhpName = $aPhpName;
		switch ($this->oNode->sName) {
			case 'input':
				$this->sType = isset($this->oNode->aAttrs['type']) ? strtolower($this->oNode->aAttrs['type']) : 'text';
				break;

			case 'select':
				$this->sType = 'select';
				break;

			case 'textarea':
				$this->sType = 'textarea';
				break;

			default:
				$this->sType = 'text';
				break;
		}

		if (isset($this->oNode->aAttrs['populate'])) {
			$this->bPopulate = strtolower($this->oNode->aAttrs['populate']) != 'false';
		} else {
			$this->bPopulate = $this->sType == 'password' ? false : true;
		}
	}

	function Prepare()
	{
		if ($this->sType == 'select') {
			foreach ($this->oNode->aSubNodes as $nIdx => $mTmp) {
				$oSubNode =& $this->oNode->aSubNodes[$nIdx];
				if (is_object($oSubNode) && $oSubNode->sName == 'option') {
					if (!$oSubNode->aSubNodes) {
						$oSubNode->aSubNodes[] = '';
					}
					if (!isset($oSubNode->aAttrs['value'])) {
						$sValue = $oSubNode->SerializeWithoutRoot();
						$oSubNode->aAttrs['value'] = trim(strip_tags($sValue));
					}
				}
			}
		} elseif ($this->sType == 'textarea') {
			if (!$this->oNode->aSubNodes) {
				$this->oNode->aSubNodes[] = '';
			}
		}

		unset($this->oNode->aAttrs['populate']);
	}

	function ClearMultiple()
	{
		if ($this->bPopulate && $this->sType == 'select' && isset($this->oNode->aAttrs['multiple'])) {
			foreach ($this->oNode->aSubNodes as $nIdx => $mTmp) {
				$oSubNode =& $this->oNode->aSubNodes[$nIdx];
				if (is_object($oSubNode) && $oSubNode->sName == 'option') {
					unset($oSubNode->aAttrs['selected']);
				}
			}
		}
	}

	function SetValue($sValue)
	{
		// fix unchecked checkbox populate bug
		if ($sValue === null && $this->bPopulate && in_array($this->sType, array('checkbox', 'radio'))) {
			unset($this->oNode->aAttrs['checked']);
		}
		if ($sValue === null || is_array($sValue)) {
			return false;
		}

		$bResult = true;

		switch ($this->sType) {
			case 'text':
			case 'password':
				if ($this->bPopulate) {
					if ($sValue != '') {
						$this->oNode->aAttrs['value'] = VDHtmlEncode($sValue);
					} else {
						unset($this->oNode->aAttrs['value']);
					}
				}
				break;

			case 'hidden':
				if ($this->bPopulate) {
					$this->oNode->aAttrs['value'] = VDHtmlEncode($sValue);
				}
				break;

			case 'textarea':
				if ($this->bPopulate) {
					$this->oNode->aSubNodes = array();
					$this->oNode->aSubNodes[] = VDHtmlEncode($sValue);
				}
				break;

			case 'file':
				$bResult = false;
				break;

			case 'submit':
				if (isset($this->oNode->aAttrs['value'])) {
					$bResult = $this->oNode->aAttrs['value'] == $sValue;
				} else {
					$bResult = in_array(strtolower($sValue), array('submit', 'submit query'));
				}
				break;

			case 'image':
				if (isset($this->oNode->aAttrs['value'])) {
					$bResult = $this->oNode->aAttrs['value'] == $sValue;
				} else {
					$bResult = false;
				}
				break;

			case 'checkbox':
			case 'radio':
				$sInputValue = isset($this->oNode->aAttrs['value']) ? $this->oNode->aAttrs['value'] : 'on';
				$bResult = $sInputValue == $sValue;
				if ($this->bPopulate) {
					if ($bResult) {
						$this->oNode->aAttrs['checked'] = 'true';
					} else {
						unset($this->oNode->aAttrs['checked']);
					}
				}
				break;

			case 'select':
				$bResult = false;
				foreach ($this->oNode->aSubNodes as $nIdx => $mTmp) {
					$oSubNode =& $this->oNode->aSubNodes[$nIdx];
					if (is_object($oSubNode) && $oSubNode->sName == 'option') {
						if ($oSubNode->aAttrs['value'] == $sValue) {
							$bResult = true;
							if ($this->bPopulate) {
								$oSubNode->aAttrs['selected'] = true;
							}
						} elseif ($this->bPopulate && !isset($this->oNode->aAttrs['multiple'])) {
							unset($oSubNode->aAttrs['selected']);
						}
					}
				}
				break;
		}

		return $bResult;
	}
}

//--------------------------------------------------------------------------
//				  class CVDInputMatrix
//--------------------------------------------------------------------------

class CVDInputMatrix
{
	var $aRollback;
	var $aState;
	var $pRef;

	function CVDInputMatrix()
	{
		$this->aState = array();
	}

	function ProcessInput($aPhpName)
	{
		$this->aRollback = $this->aState;
		$this->pRef =& $this->aState;

		foreach ($aPhpName as $nIdx => $mTmp) {
			if ($nIdx == 0) {
				continue;
			} else {
				if ($aPhpName[$nIdx] === '') {
					if (!isset($this->pRef[''])) {
						$this->pRef[''] = 0;
					}
					$aPhpName[$nIdx] = $this->pRef[''];
					$this->pRef['']++;
				} elseif (is_int($aPhpName[$nIdx])) {
					if (!isset($this->pRef[''])) {
						$this->pRef[''] = ($aPhpName[$nIdx] >= 0 ||
							version_compare(phpversion(), '4.3.0', '<')) ? $aPhpName[$nIdx] + 1 : 0;
					} else {
						$this->pRef[''] = max($this->pRef[''], $aPhpName[$nIdx] + 1);
					}
				}

				if (!isset($this->pRef[$aPhpName[$nIdx]])) {
					$this->pRef[$aPhpName[$nIdx]] = array();
				}
				$this->pRef =& $this->pRef[$aPhpName[$nIdx]];
			}
		}

		return $aPhpName;
	}

	function Commit()
	{
		$this->pRef = array();
	}

	function Rollback()
	{
		$this->aState = $this->aRollback;
	}
}

//--------------------------------------------------------------------------
//				  class CVDValRuntime
//--------------------------------------------------------------------------

class CVDValRuntime
{
	var $sProtocol;
	var $sDomain;
	var $sPage;
	var $sArgs;
	var $sAnchor;
	var $sForm;
	var $sSesID;
	var $sVDaemonPath;
	var $aNodes;

	function CVDValRuntime()
	{
		$this->sProtocol = '';
		$this->sDomain = '';
		$this->sPage = '';
		$this->sArgs = '';
		$this->sAnchor = '';
		$this->sForm = '';
		$this->sSesID = '';
		$this->sVDaemonPath = '';
		$this->aNodes = array();
	}

	function Fill(&$oForm)
	{
		$this->sProtocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://';
		$this->sDomain = $_SERVER['HTTP_HOST'];
		$this->sPage = $_SERVER['PHP_SELF'];
		$this->sArgs = VDGetCurrentArgs();
		$this->sAnchor = isset($oForm->oNode->aAttrs['anchor']) ? $oForm->oNode->aAttrs['anchor'] : '';
		$this->sForm = $oForm->sName;
		$this->sSesID = session_id();
		$this->sVDaemonPath = VDGetWebPath() . 'vdaemon.php';

		foreach ($oForm->aValidators as $oVal) {
			$this->aNodes[] =& $this->GetValidatorNode($oVal);
		}
	}

	function &GetValidatorNode(&$oValidator)
	{
		$oNode =& new XmlNode($oValidator->oNode->sName, $oValidator->oNode->aAttrs);
		unset($oNode->aAttrs['clientvalidate']);
		unset($oNode->aAttrs['clientregexp']);
		unset($oNode->aAttrs['clientfunction']);

		foreach ($oValidator->aValidators as $oSubValidator) {
			$oNode->aSubNodes[] =& $this->GetValidatorNode($oSubValidator);
		}

		return $oNode;
	}
}

//--------------------------------------------------------------------------
//				  Security functions
//--------------------------------------------------------------------------

function VDLoadExtensions()
{
	global $bVDZlibLoaded;

	if (extension_loaded('zlib')) {
		$bVDZlibLoaded = true;
	} elseif((bool)ini_get('enable_dl') && !(bool)ini_get('safe_mode')) {
		$bVDZlibLoaded = (strtoupper(substr(PHP_OS, 0, 3) == 'WIN')) ? @dl('php_zlib.dll') : @dl('zlib.so');
	} else {
		$bVDZlibLoaded = false;
	}
}

function VDSecurityEncode($sValue)
{
	global $bVDZlibLoaded;
	global $sVDaemonSecurityKey;

	if ($bVDZlibLoaded) {
		$sValue = gzcompress($sValue, 9);
		if (!$sValue) {
			return false;
		}
		$sValue = base64_encode($sValue);
	}

	$sHash = md5($sVDaemonSecurityKey . $sValue);
	$sValue = $sHash . $sValue;

	return $sValue;
}

function VDSecurityDecode($sValue)
{
	global $bVDZlibLoaded;
	global $sVDaemonSecurityKey;

	$sHash = substr($sValue, 0, 32);
	$sValue = substr($sValue, 32);
	if (strcasecmp($sHash, md5($sVDaemonSecurityKey . $sValue)) != 0) {
		return false;
	}

	if ($bVDZlibLoaded) {
		$sValue = base64_decode($sValue);
		if ($sValue) {
			$sValue = gzuncompress($sValue);
		}
	}

	return $sValue;
}

//--------------------------------------------------------------------------
//				  THE END
//--------------------------------------------------------------------------
?>