<?php

require_once('include.php');

function show_help(){
	echo "Usage:\n";
	echo "\tFirst arg\tAction type, possible: sync, syncuser\n";
	echo "\tSecond arg\tUsername for syncuser\n\n";
	echo "Example:\n";
	echo "php -e main.php sync\n";
	echo "php -e main.php syncuser nt-account\n\n";
	die();
}

$l = new Logger();
$conf = Config::getInstance();
$sync = new LdapSync();

if(count($argv) == 2 && $argv[1] == 'sync'){

	try{
		$users = array();
		$groups = explode(',', $conf->groups_to_sync);
		foreach($groups as $group){
			$users = array_merge($users,$sync->getUsersOfGroup($conf->base_ou_for_groups, 'CN=' . $group));
		}
		
		$sync->syncAllUsers($users);
		
		//var_dump($users);
		//$sync->syncAllUserWithFilter($conf->base_ou_for_groups);
	}catch(Exception $e){
		echo $e->getMessage();
		$l->error($e->getMessage());
	}

}elseif(count($argv) == 3 && $argv[1] == 'syncuser'){

	try{
		if($sync->syncUser($argv[2]) == false)
			echo "User not found in LDAP!\n";
	}catch(Exception $e){
		$l->error($e->getMessage());
	}

}elseif(count($argv) == 2 && $argv[1] == 'debug'){
	
}else{
	show_help();
}


?>
