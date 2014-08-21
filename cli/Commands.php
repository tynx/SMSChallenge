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
 
 
/**
 * ClassName: Commands
 * Inherits: Nothing
 *
 * Description:
 * This class handles basic cli IO. It handles creating of users so far.
 * In future it may contain more functionality like editing/deleting
 * users. But maybe it's just overkill...
 */
class Commands{

	/**
	 * Function: promptValue
	 *
	 * Description:
	 * Prints out the title of the current property. This is outsourced
	 * to make the code a bit less messy.
	 *
	 * @param $title (string) The name of the property
	 * @param $required (bool/optional) property required
	 * @param $default (string/optional) the default value 
	 * @return (void) none
	 */
	private function promptValue($title, $required = true, $default = ''){
		if($required)
			echo '*';
		echo $title;
		if($default != '')
			echo '[' . $default . ']';
		echo ': ';
	}

	/**
	 * Function: readLine
	 *
	 * Description:
	 * Reads from the STDIN after printing out the name of the property
	 * and validates it based on the given arguments. It returns
	 * the final line if that was valid.
	 *
	 * @param $title (string) The name of the property asked for
	 * @param $required (bool/optional) if the property asked for is
	 * required (not empty!) default: yes
	 * @param $default (string/optional) the default value if none is
	 * given. default for the default value is: '' (empty string)
	 * @return (string) the final input (may be the default)
	 */
	private function readLine($title, $required = true, $default = ''){
		$valid = false;
		$input = '';
		
		while(!$valid){
			$this->promptValue($title, $required, $default);
			
			$line = trim(fgets(STDIN));
			
			if($line == '' && $default == '' && $required === true){
				continue;
			}elseif($line == '' && $default != '' ){
				$input = $default;
				$valid = true;
			}else{
				$input = $line;
				$valid = true;
			}
		}
		return $input;
	}

	/**
	 * Function: createUser
	 *
	 * Description:
	 * Asks for all needed fields of a user and saves him into the db.
	 */
	public function createUser(){
		$user = new User;

		echo "Creating new user...\nFields with * are required!\n";

		$user->givenName = $this->readLine('GivenName');
		$user->surName = $this->readLine('SurName');
		$user->username = strtoupper($this->readLine('Username', true, strtoupper($user->givenName)));
		$user->email = $this->readLine('Email');
		$user->mobileNumber = $this->readLine('mobileNumber');
		$user->notes = $this->readLine('Notes', false);
		$user->permissionStatus = 2;
		$c = Config::getInstance();

		$user->password = $c->default_password;

		$user->save();

		echo "New user is added to the db.\n";
	}
}


?>
