<?php

namespace app\models;

use app\inc\postgis;

class Settings_viewer extends postgis {
	function __construct() {
		parent::__construct();
	}
	private function getArray(){
		$sql = "SELECT viewer FROM settings.viewer";
		$arr = $this->fetchRow($this->execQuery($sql),"assoc");
		return (array)json_decode($arr['viewer']);
	}
	public function update($post){
		$arr = $this->getArray();
		foreach($post as $key=>$value) {
			if (!$value){
				$value=false;
			}
			if (is_numeric($value)){
				$value=(int)$value;
			}
			$arr[$key] = $value;
		}
		$sql = "UPDATE settings.viewer SET viewer='".json_encode($arr)."'";
		$this -> execQuery($sql,"PDO","transaction");
		if (!$this->PDOerror) {
	 		$response['success'] = true;
	 		$response['message'] = "Default extent updated";
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror;
		}
		return $response;
    }
    public function updateApiKey(){
    	$apiKey = md5(microtime().rand());
		$arr = $this->getArray();
		$arr['api_key'] = $apiKey;
		$sql = "UPDATE settings.viewer SET viewer='".json_encode($arr)."'";
		$this -> execQuery($sql,"PDO","transaction");
		if (!$this->PDOerror) {
	 		$response['success'] = true;
	 		$response['message'] = "API key updated";
			$response['key'] = $apiKey;
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror;
		}
		return $response;
	}
	public function updatePw($pw){
		$arr = $this->getArray();
		$arr['pw'] = $this->encryptPw($pw);
		$sql = "UPDATE settings.viewer SET viewer='".json_encode($arr)."'";
		$this -> execQuery($sql,"PDO","transaction");
		if (!$this->PDOerror) {
	 		$response['success'] = true;
	 		$response['message'] = "Password saved";
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror;
		}
		return $response;
	}
	public function get($unsetPw=false) {
		$arr = $this->getArray();
		if ($unsetPw) {
			unset($arr['pw']);
		}
		if (!$this->PDOerror) {
	 		$response['success'] = true;
	 		$response['data'] = $arr;
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror;
		}
		return $response;
	}
	public function encryptPw($pass) {
		$pass=strip_tags($pass);
		$pass=str_replace(" ","",$pass);//remove spaces from password
		$pass=str_replace("%20","",$pass);//remove escaped spaces from password
		$pass=addslashes($pass);//remove spaces from password
		$pass=md5($pass);//encrypt password
		return $pass;
	}
}
