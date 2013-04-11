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
 * ClassName: Ldap
 * Inherits: Nothing
 *
 * Description:
 * This class provides an easy way to talk to an LDAP-Server. As there
 * is only "view" needed for SMSChallenge only search is implemented.
 */
Class Ldap{

	/**
	 * @var (Ldap-Object) Singleton instance of itself
	 */
	private static $instance = null;

	/**
	 * @var (Connection-resource) Ressource for LDAP-Connection
	 */
	private $connection = null;

	/**
	 * @var (string) The base dn where the search starts
	 */
	private $base_dn = null;

	/**
	 * @var (string-array) LDAP-Attributes which should be requested for
	 * the current schema.
	 */
	private $searchAttributes = array();

	/**
	 * Function: __construct
	 *
	 * Description:
	 * Make constructor private so only one object can be created.
	 */
	private function __construct(){ }

	/**
	 * Function: __clone
	 *
	 * Description:
	 * Make clone private so only one object can be created.
	 */
	private function __clone(){ }

	/**
	 * Function: __destruct
	 *
	 * Description:
	 * If the object is destructed the connection should be closed.
	 */
	public function __destruct(){
		ldap_close($this->connection);
	}

	/**
	 * Function: getInstance
	 *
	 * Description:
	 * Get the singleton instance of this class
	 *
	 * @return (Ldap object) the singleton instance
	 */
	public static function getInstance(){
		if (is_null(self::$instance)) {
			self::$instance = new Ldap();
			self::$instance->connect();
		}
		return self::$instance;
	}

	/**
	 * Function: isConnected
	 *
	 * Description:
	 * Checks whetever a connection is existing or not.
	 *
	 * @return (bool) whetever connection is established or not
	 */
	public function isConnected(){
		return ($this->connection !== null) ? true : false;
	}

	/**
	 * Function: connect
	 *
	 * Description:
	 * Connects and binds to the LDAP-server
	 *
	 * @return (bool) whetever the connection was established
	 */
	public function connect(){
		$c = Config::getInstance();
		$this->connection = ldap_connect($c->ldap_server, $c->ldap_port);
		ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($this->connection, LDAP_OPT_REFERRALS, 0);
		if(!ldap_bind($this->connection, $c->ldap_bind_username, $c->ldap_bind_password)){
			$error='';
			ldap_get_option($this->connection, LDAP_OPT_ERROR_STRING, $error);
			throw new Exception($error);
		}
		$this->base_dn = $c->ldap_base_dn;
		return true;
	}

	/**
	 * Function: parseLine
	 *
	 * Description:
	 * Loads the schema based on the argument given from the config and
	 * saves the attributes internally in the array
	 *
	 * @param $schema (string) the name of the schema
	 * @return (void) nothing
	 */
	public function setSchema($schema){
		$c = Config::getInstance();
		$schemaName = 'ldap_schema_' . $schema;
		$this->searchAttributes = explode(',', str_replace(' ', '', $c->$schemaName));
	}

	/**
	 * Function: isValidUser
	 *
	 * Description:
	 * This function is pretty specific for the LDAP-enviroment used
	 * while developing. It checks if the attrbute "distinguishedname"
	 * does contains the word "Personal". Also if no email is given and
	 * the id is 99999999 the user is invalid.
	 * It is "deactivated" so far. In case you need to make checks on
	 * some of the attributes you can do this here.
	 *
	 * @param $item (assoc-array) the assoc-array of an user
	 * @return (bool) Whetever the user is valid or not
	 */
	public function isValidUser($item){
		//deactivate
		return true;
		if(strpos($item['distinguishedname'], "Personal") === false)
			return false;
		if(!isset($item['mail']) || $item['employeeid'] == 9999999)
			return false;
		return true;
	}

	/**
	 * Function: stripResult
	 *
	 * Description:
	 * The LDAP-Extension returns quite some overhead in the
	 * assoc-array. This function strips all unneeded parts of the array
	 * like the count-entries.
	 *
	 * @param $results (assoc-array) the raw assoc-array from ldap
	 * @return (assoc-array) The stripped array
	 */
	private function stripResult($results){
		$return = array();
		foreach($results as $i=>$result){
			if(!is_array($result))
				continue;
			foreach($result as $key=>$values){
				if(!is_numeric($key) && $key != 'count'){
					if(is_array($values) && $values['count']==1 && $key != 'member'){
						$return[$i][$key] = $values[0];
					}else{
						$return[$i][$key] = $values;
					}
				}
			}
		}
		return $return;
	}

	/**
	 * Function: search
	 *
	 * Description:
	 * Searches for LDAP-entries based on the given filter and returns
	 * them in a stripped form.
	 *
	 * @param $arg (string) the query (filter)
	 * @param $base_dn (string/optional) the base_dn from where it
	 * should be searched
	 * @return (assoc-array) The result-set in stripped form
	 */
	public function search($arg, $base_dn = null){
		if(!empty($this->searchAttributes)){
			$this->searchAttributes = array_merge($this->searchAttributes, array('distinguishedname', 'dn', 'cn'));
			$res = ldap_search($this->connection, ($base_dn === null) ? $this->base_dn : $base_dn , $arg, $this->searchAttributes);
		}else{
			$res = ldap_search($this->connection, ($base_dn === null) ? $this->base_dn : $base_dn , $arg);
		}
		if($res === false)
			return false;
		$result = ldap_get_entries($this->connection, $res);
		$result = $this->stripResult($result);
		if(count($result)==0)
			return false;
		return $result;
	}

	/**
	 * Function: isGroup
	 *
	 * Description:
	 * Checks based on the cn-name if the given element is a group or
	 * not.
	 *
	 * @param $cn_name (string) the cn of the element
	 * @param $base_dn (string/optional) the base_dn from where it
	 * should be searched
	 * @return (bool) returns true if it's a group
	 */
	public function isGroup($cn_name, $base_dn = null){
		$result = ldap_search($this->connection, ($base_dn === null) ? $this->base_dn : $base_dn, '(' . $cn_name . ')');
		if($result === false)
			return false;
		$info = ldap_get_entries($this->connection, $result);
		if($info['count']!=1)
			return false;
		if(is_array($info[0]['objectclass']) && in_array('group', $info[0]['objectclass']))
			return true;
		return false;
	}
}

?>
