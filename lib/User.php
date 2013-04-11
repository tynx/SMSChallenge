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
 * ClassName: User
 * Inherits: ActiveRecord
 *
 * Description:
 * This class is for the table "user" and acts as a model via the
 * Active-Record
 */
Class User Extends ActiveRecord{

	/**
	 * @var (string) the new password is saved temporarely here
	 */
	private $newPasswd = null;

	/**
	 * Function: model
	 *
	 * Description:
	 * Calls the parent function with this classname.
	 *
	 * @param $className (string/otional) the name of the class
	 * @return (Log-object) the new object of this class
	 */
	public static function model($className=__CLASS__){
		return parent::model($className);
	}

	/**
	 * Function: getTableName
	 *
	 * Description:
	 * This function returns simply the name of the table which this
	 * model is handling.
	 *
	 * @return (string) the name of the table this model represents
	 */
	public function getTableName(){
		return 'user';
	}

	/**
	 * Function: getMapping
	 *
	 * Description:
	 * Returns the array of the remapping LDAP->internal columns
	 *
	 * @return (assoc-array) the remappings
	 */
	public function getMapping(){
		return array(
			'cn'=>'username',
			'sn'=>'surName',
			'givenname'=>'givenName',
			'department'=>'department',
			'mobile'=>'mobileNumber',
			'telephonenumber'=>'telephoneNumber',
			'mail'=>'email',
			'manager'=>'manager',
			'dn'=>'dn',
			'employeeid'=>'employeeID',
			'language'=>'defaultLanguage',
			'extensionattribute7'=>'employeeType',
			'wwwhomepage'=>'web',
			'title'=>'title',
			'streetaddress'=>'street',
			'physicaldeliveryofficename'=>'office',
			'extensionattribute2'=>'room',
			'postalcode'=>'postalcode',
			'l'=>'city',
			'Notes'=>'Notes',
		);
	}

	/**
	 * Function permissionsStatusString
	 *
	 * Description:
	 * Returns the permissionStatus in form of a string (it's saved as
	 * an integer in the db).
	 *
	 * @return (string) human readable permission status
	 */
	public function permissionStatusString(){
		if($this->permissionStatus == 0)
			return "Normal";
		if($this->permissionStatus == 1)
			return "Explicit denied";
		if($this->permissionStatus == 2)
			return "Explicit allowed";
	}

	/**
	 * Function: __set
	 *
	 * Description:
	 * Overwritten, because we want to save the password seperately.
	 */
	public function __set($key, $value){
		if($key == 'password'){
			$this->newPasswd = $value;
		}else{
			parent::__set($key, $value);
		}
		return true;
	}

	/**
	 * Function: save
	 *
	 * Description:
	 * Overwritten, because we want to save the password seperately.
	 */
	public function save(){
		parent::save();
		if($this->newPasswd != null){
			$query = new Query('function', 'set_password');
			$query->addParameters(array($this->username, $this->newPasswd));
			$this->storage->query($query);
		}
	}
}

?>
