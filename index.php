<?php
// Start HTML doc
include("html_header.php");
?>
</head>
<body>
<div class="desc">
<?php
include("inc/topbar.php");
echo '<p class="desc">Sharing of geographical information made really, really easy!</p>';

$twitterObj = new EpiTwitter("JxkgXselACc4c46J31vGNA", "IPSvsBdEM7vMHiIDVZpMIiGU4OkrnW7vOvhtYoM");

if ($_GET["oauth_token"]) {
	//echo "Requesting twitter";
	$_SESSION["oauth_token"] = $_GET['oauth_token'];
	$twitterObj->setToken($_SESSION["oauth_token"]);
	$token = $twitterObj->getAccessToken();

	if ((!$_SESSION["tok"]) || (!$_SESSION["sec"])) {
		$_SESSION["tok"] = $token->oauth_token;
		$_SESSION["sec"] = $token->oauth_token_secret;
	}

	$twitterObj->setToken($_SESSION["tok"], $_SESSION["sec"]);
	$twitterInfo = $twitterObj->get_accountVerify_credentials();
	$twitterInfo->response;
	//print_r($twitterInfo);
	$_SESSION['header_html'] = "<p class='desc'>Hi {$twitterInfo->name}</p>";
	$_SESSION['screen_name'] = postgis::toAscii($twitterInfo->screen_name,array(),"_");
	$_SESSION['id'] = $twitterInfo->id;
}
if (!$_SESSION["oauth_token"] || (!$_SESSION['screen_name'])) {
	echo "<form>
		  <input class=\"btn\" TYPE=\"button\" value=\"Authorize with Twitter\" ONCLICK=\"window.location.href='{$twitterObj->getAuthorizationUrl()}'\"><div style='margin:10px'><p><i>or</i></p></div>";
    // We test id a db exist with IP
   $testDb = new databases();
    if ($testDb->doesDbExist("_".postgis::toAscii($_SERVER['REMOTE_ADDR']))) {
    	$_SESSION['screen_name'] = "_".postgis::toAscii($_SERVER['REMOTE_ADDR']);
    	echo "<form>
		  <input class=\"btn\" TYPE=\"button\" value=\"Take me to my geo cloud\" ONCLICK=\"window.location.href='store/"."_".postgis::toAscii($_SERVER['REMOTE_ADDR'])."'\"> 
		</form><div style='margin:0px'><p><i>Your ip address will be used for authorization</i></p></div>";
    }
    else{
		echo "<input class=\"btn\" TYPE=\"button\" value=\"Just give me my geo cloud\" ONCLICK=\"window.location.href='createstore/'\"> 
		</form><div style='margin:0px'><p><i>Your ip address will be used for authorization</i></p></div>";
		}
		?>
<p class="desc-small" style="margin-top:10px">Access and edit your geographical information from anywhere using you favorite GIS application - or just use the build in online editor.</p>
<p class="desc-small">Notify your co-workers and clients via Twitter when you update and insert new information.</p>
<p class="desc-small">No need to sign up! Just use Twitter to get started.</p>
<iframe style="border:1px solid silver;" width="560" height="349" src="http://www.youtube.com/embed/Ifv4WmJwmnY" frameborder="0" allowfullscreen></iframe>
		<?php
}
if (($_SESSION["oauth_token"] && $_SESSION['screen_name'])) {
	echo $_SESSION['header_html'];
	$user = new users($_SESSION['screen_name'],$_SESSION['id'],$_SESSION["tok"],$_SESSION["sec"]);
	if ($user) {
		if ($user->getHasCloud()) {
			echo "<form>
		  <input class=\"btn\" TYPE=\"button\" value=\"Take me to my geo cloud\" ONCLICK=\"window.location.href='store/{$_SESSION['screen_name']}'\"> 
		</form>";
		}
		else {
		echo "<form>
		  <input class=\"btn\" TYPE=\"button\" value=\"Get your geo cloud right now\" ONCLICK=\"window.location.href='createstore/'\"> 
		</form>";

		}
	}
	else {
		echo '<p class="desc">Something went wrong! Try refresh your browser</a></p>';
	}
}
echo "</div>";
// End HTML doc
include("html_footer.php");
?>
