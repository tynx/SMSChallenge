<?php

include('include.php');

$allowedActions = array('adduser');

$c = new Commands();

if(isset($argv[1]) && in_array($argv[1], $allowedActions)){

	if($argv[1]=='adduser')
		$c->createUser();

	echo "\n";
}else{
	echo "Invalid action!!\n\n";
}

?>
