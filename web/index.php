<?php
/**
 * Copyright (C) 2013 Luginbühl Timon, Müller Lukas, Swisscom AG
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * For more informations see the license file or see <http://www.gnu.org/licenses/>.
 */
 
// JUST FOR DEVELOPMENT, DEBUGGING AND TESTING PURPOSES!
error_reporting(E_ALL);
ini_set('display_errors', TRUE);

	session_start();
	require_once('include.php');
	$conf = Config::getInstance();
	$c = new Controller();

	// check the authentification method and handle the login procedure
	if($conf->auth_type == 'kerberos' && isset($_SERVER['REMOTE_USER']) ){
		$nt_account = explode('@', $_SERVER['REMOTE_USER']);
		$nt_account = $nt_account[0];
		$_SESSION['user']['username'] = $nt_account;
		// check if user exists in the smschallenge DB 
		// pw checked with kerberos auth !
		if(User::model()->findByAttributes(array('username' => $nt_account )) === NULL){
			$c->actionError("Forbidden! You are not in the SMSChallenge Database.");
		}
		// otherwise go ahead
	}elseif($conf->auth_type == 'internal'){
		if(!isset($_SERVER['PHP_AUTH_USER']) || isset($_SESSION['newlogin'])){
			session_unset($_SESSION['newlogin']);
			header("WWW-Authenticate: Basic realm=\"SMSChallenge - Login\"");
			header("HTTP/1.0 401 Unauthorized");
			exit(0);
		}elseif(isset($_SERVER['PHP_AUTH_PW'])){
			$_SESSION['user']['username'] = $_SERVER['PHP_AUTH_USER'];
			$username = $_SERVER['PHP_AUTH_USER'];
			$user = User::model()->findByAttributes(array('username' => $username ));
			// check if user exists in the smschallenge DB 
			if($user !== null){
				// check password 
				$query = new Query('function', 'check_password');
				$query->addParameters(array($username, $_SERVER['PHP_AUTH_PW']));
				$storage = call_user_func(array($conf->dataProvider, 'getInstance'), array());
				if($storage->query($query) == 0){
					$c->actionError("Wrong Login credentials!");
				}
			}else{
				$c->actionError("Forbidden! You are not in the SMSChallenge Database.");
			}
		}else{
			$c->actionError("Forbidden!");
		}
	}else{
		$c->actionError("No authentication method found!");
	}


$admins = explode(',', $conf->web_admins);
if(in_array(strtolower($_SESSION['user']['username']), $admins)){
	$_SESSION['user']['isAdmin'] = true;
}else{
	$_SESSION['user']['isAdmin'] = false;
}


if (isset($_GET['action'])){
	$c->runAction($_GET['action']);
}else{
	$c->runAction();
}

?>
