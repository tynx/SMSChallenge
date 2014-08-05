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
 * ClassName: LdapSync
 * Inherits: Nothing
 *
 * Description:
 * This class provides functions to sync user against LDAP.
 * You can either sync a single user, all users or all users of a specified group.
 */
Class LdapSync{
	
	
	public function syncAllUsers($group_users){
		$users = User::model()->findAll();
		$ldap = Ldap::getInstance();
		$ldap->setSchema('user');

		// Find all user from ldap which belong to the department.
		//$group_users = $this->getUsersOfGroup($group);

		$successfulUsers=0;
		

		foreach($group_users as $user){

			$info = $ldap->search($user);
			$info = $info[0];


			if($ldap->isValidUser($info)){
				// Create/Update the user
				$u = User::model()->findByAttributes(array('username'=>$info['cn']));
				// We want to create a new user, if he does not exist
				if($u==null)
					$u = New User();

				// Populate the object with the new values
				$u->populate($u->remap($info));

				// Update the boolean synced, and the lastSynced
				$u->synced = true;
				$u->lastSynced=date("Y-m-d H:i:s", time());
				$u->permissionStatus = 0;

				// Save it
				if( $u->save() !== false ){
					$successfulUsers++;
					// Remove the user from the array
					for($i=0;$i<count($users);$i++){
						if($users[$i]->username == $info['cn']){
							array_splice($users, $i, 1);
							break;
						}
					}
				}
			}
		}

		if(count($users)==0){
			$l = new Logger();
			$l->info('Successfully synced ' . $successfulUsers . ' users. No old users in the DB.');
			return;
		}

		$oldUsers=0;
		// Sync the users which are not yet synced
		foreach($users as $user){
			$info = $ldap->search("cn=" . $user->username);
			if($info === false){
				// Handle old users, unexisting users
				$user->synced = false;
				$user->save();
				$oldUsers++;
				continue;
			}

			$user->populate($user->remap($info[0]));
			$user->synced = true;
			$user->lastSynced=date("Y-m-d H:i:s", time());
			$user->save();
			$successfulUsers++;
		}

		$l = new Logger();
		$l->info('Successfully synced ' . $successfulUsers . ' users. There are ' . $oldUsers . ' users which were not synced');

	}

	/**
	 * This functions syncs a single user via the LDAP.
	 * If the user already exists in the DB the information will be updated.
	 * Otherwise a new user will be created.
	 *
	 * @param: 
	 * $username: The username of the user you want to sync.
	 * @return: true or false on error
	 * 
	 */
	public function syncUser($username){
		$l = new Logger();
		$ldap = Ldap::getInstance();
		$ldap->setSchema('user');
		// search via username
		$info = $ldap->search('cn=' . $username);

		// if error or if not exactly one user was found return false
		if(count($info)!=1 || $info == false)
			return false;

		// get user by username
		$u = User::model()->findByAttributes(array('username'=>$info[0]['cn']));

		// We want to create a new user, if he does not exist
		if($u==null)
			$u = New User();

		// Populate the object with the new values
		$u->populate($u->remap($info[0]));

		// Update the boolean synced, and the lastSynced
		$u->synced = true;
		$u->lastSynced=date("Y-m-d H:i:s", time());

		// Save it
		if( $u->save() !== false ){
			$l->info('User ' . $u->username . ' was added/updated successfully.');
			return true;
		}
		return false;
	}

	public function getUsersOfGroup($group_base, $groupname){
		$users=array();
		$ldap = Ldap::getInstance();
		$result = $ldap->search('(' . $groupname . ')', $group_base);

		if(count($result)!=1){
			echo "no valid return...\n";
			return array();
		}
		if(!isset($result[0]['member']) || !is_array($result[0]['member'])){
			return array();
		}
		foreach($result[0]['member'] as $key=>$member){
			if(!is_numeric($key))
				continue;
			$parts = explode(',',$member);
			$cn = $parts[0];
			
			if($ldap->isGroup($cn, $group_base)){
				$users = array_merge($this->getUsersOfGroup($group_base, $cn),$users);
			}else{
				$users[] = $cn;
			}
		}

		return $users;
	}

}

?>
