<?php
class users extends postgis {
	var $userId;
	var $screenName;
	var $tok;
	var $sec;
	var $pw;
	function __construct($screenName,$userId=NULL,$tok=NULL,$sec=NULL)
	{
		parent::__construct();
		$this->screenName = $screenName;
		$this->postgisdb = "mygeocloud";
		$sql = "SELECT * FROM users WHERE screenname='{$screenName}'";
		$row = $this->fetchRow($this->execQuery($sql));
		if ((!$row['screenname']) && $userId && $tok && $sec) {
	 		$sql = "INSERT INTO users(userid,screenname,tok,sec) VALUES({$userId},'{$screenName}','{$tok}','{$sec}')";
			$result = $this -> execQuery($sql);
			$this->userId = $userId;
			$this->tok = $tok;
			$this->sec = $sec;
		}
		elseif($row['userid'] && $row['tok'] && $row['sec']) {
			$this->userId = $row['userid'];
			$this->tok = $row['tok'];
			$this->sec = $row['sec'];
			$this->pw = $row['pw'];
		}
		else {
			die("Could not init user object");
		}
	}
	public function getHasCloud(){
	    $sql = "select count(*) as count from pg_catalog.pg_database where datname = '{$this->screenName}'";
		$row = $this->fetchRow($this -> execQuery($sql),"assoc");
		if ($row['count']==1) {
			return true;
		}
		else {
			return false;
		}
	}
	public function tweet($text)
	{
		$twitterObj = new EpiTwitter("90k7NC2MBrpEDFI2CDTkYQ", "eJ4rguJKlIZCejlCh4c0lDJR7aj4Afq7SBKTvNOmk38");
		$twitterObj->setToken($this->tok,$this->sec);
		$twitterInfo= $twitterObj->get_accountVerify_credentials();
		$twitterInfo->response;
		$status = $twitterObj->post_statusesUpdate(array('status' => $text));
		$status->response;
	}
	public function encryptPw($pass) {
		$pass=strip_tags($pass);
		$pass=str_replace(" ","",$pass);//remove spaces from password
		$pass=str_replace("%20","",$pass);//remove escaped spaces from password
		$pass=addslashes($pass);//remove spaces from password
		$pass=md5($pass);//encrypt password
		return $pass;
	}
	public function updatePw($pass) {
		$sql = "UPDATE users SET pw='".$this->encryptPw($pass)."' WHERE userid={$this->userId}";
		$this -> execQuery($sql,"PDO","transaction");
		if (!$this->PDOerror) {
	 		$response['success'] = true;
	 		$response['message'] = "Password updated";

		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror;
		}
		return $response;
	}
	public function getPw() {
		return $this->pw;
	}
}
?>
