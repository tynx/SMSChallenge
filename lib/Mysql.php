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
 * ClassName: Mysql
 * Inherits: Nothing
 *
 * Description:
 * This class takes a query-object and builds based on the information
 * given the according SQL-statement. Also it validates the given query.
 * After that the query is run and given back. Also this class handles
 * the whole connection to the MySQL-Server. The needed information for
 * that are taken out of the config directly.
 * 
 * Notice that scientific notation of floats aren't recognized as floats!
 * Because the salt can look like an scientific notation of a number, 
 * this was causing troubles and is now blocked!
 * 
 * 
 */
class Mysql{

	/**
	 * @var (Ldap-Object) Singleton instance of itself
	 */
	private static $instance;

	/**
	 * @var (Connection-resource) Ressource for MySQL-Connection
	 */
	private $connection=null;

	/**
	 * @var (string) the name of the database
	 */
	private $db=null;

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
	 * Function: getInstance
	 *
	 * Description:
	 * Get the singleton instance of this class
	 *
	 * @return (Mysql object) the singleton instance
	 */
	public static function getInstance(){
		if (!isset(self::$instance)) {
			self::$instance = new Mysql();
			self::$instance->connect();
		}
		return self::$instance;
	}

	/**
	 * Function: __destruct
	 *
	 * Description:
	 * If the object is destructed the connection should be closed.
	 */
	public function __destruct(){
		$this->disconnect();
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
		if($this->connection !== null)
			return true;
		return false;
	}

	/**
	 * Function: connect
	 *
	 * Description:
	 * Connects to MySQL server and selects the according db
	 *
	 * @return (bool) whetever the connection was established
	 */
	public function connect(){
		if($this->isConnected())
			return true;
		
		$c = Config::getInstance();
		$this->db = $c->mysql_database;
		$this->connection = mysql_connect($c->mysql_host, $c->mysql_username, $c->mysql_password);
		if (!$this->isConnected()) {
			throw new Exception('Was not able to establish connection: '.mysql_error());
		}
		$db_selected = mysql_select_db($this->db, $this->connection);
		if (!$db_selected || !$this->isConnected()) {
			throw new Exception('Can not use ' . $this->db . ': '.mysql_error());
		}
		return true;
	}

	/**
	 * Function: disconnect
	 *
	 * Description:
	 * Disconnects from MySQL-Server an sets the internal var to NULL to
	 * make sure we reconnect for sure if needed.
	 *
	 * @return (bool) whetever the connection was established
	 */
	public function disconnect(){
		if($this->connection !== NULL)
			mysql_close($this->connection);
		$this->connection = NULL;
	}

	/**
	 * Function: buildCondition
	 *
	 * Description:
	 * Builds the "WHERE"-conditions based on the given query-object
	 *
	 * @param $query (query-object) The query object from the the query should
	 * be built.
	 * @return (string) the mysql-condition string
	 */
	private function buildCondition($query){
		$sql = 'WHERE ';
		$i=0;
		foreach($query->getCriterias() as $key=>$value){
			if($i>0)
				$sql .= 'AND ';
			$sql .= '`' . $query->getTable() . '`.`' . $key . '` = ';
			$sql .= (is_numeric($value) && preg_match('/^([0-9\.,-]+)$/', $value)) ? $value : '\'' . $value . '\' ';
			$i++;
		}
		return $sql;
	}

	/**
	 * Function: buildOrder
	 *
	 * Description:
	 * Builds the "ORDER"-part based on the given query-object
	 *
	 * @param $query (query-object) The query object from the the query should
	 * be built.
	 * @return (string) the mysql-order string
	 */
	private function buildOrder($query){
		$sql = 'ORDER BY ';
		$i=0;
		foreach($query->getOrders() as $column => $direction){
			if($column != "" && ($direction == 'ASC' || $direction == 'DESC')){
				$sql .= '`' . $column . '` ' . $direction . ', ';
				$i++;
			}
		}
		if($i>0)
			return substr($sql, 0, -2);
		return '';
	}

	/**
	 * Function: buildSelectSQL
	 *
	 * Description:
	 * Builds a "SELECT"-query based on the given query-object. It uses
	 * function like buildCondition and buildOrder for that.
	 *
	 * @param $query (query-object) The query object from the the query should
	 * be built.
	 * @return (string) the mysql-select-query
	 */
	private function buildSelectSQL($query){
		$sql = 'SELECT * ';
		$sql .= 'FROM `' . $this->db . '`.`' . $query->getTable() . '` ';
		if(count($query->getCriterias())>0)
			$sql .= $this->buildCondition($query);
		if(count($query->getOrders())>0)
			$sql .= $this->buildOrder($query);
		$sql .= ' LIMIT ' . implode(',', $query->getLimit()) . ';';
		return $sql;
	}

	/**
	 * Function: buildInsertSQL
	 *
	 * Description:
	 * Builds a "INSERT"-query based on the given query-object.
	 *
	 * @param $query (query-object) The query object from the the query should
	 * be built.
	 * @return (string) the mysql-insert-query
	 */
	private function buildInsertSQL($query){
		$sql = 'INSERT INTO ';
		$sql .= '`' . $this->db . '`.`' . $query->getTable() . '` ';

		$sql .= '(`' . implode('`, `', array_keys($query->getValues()));
		$sql .= '`) VALUES (';

		$i=0;
		foreach($query->getValues() as $column=>$value){
			if($i>0){
				$sql .= ', ';
			}
			if(is_numeric($value) && preg_match('/^([0-9\.,-]+)$/', $value)){
				$sql .= $value;
			}else{
				$sql .= '\'' . mysql_real_escape_string($value) . '\'';
			}
			$i++;
		}
		
		$sql .= ');';
		return $sql;
	}

	/**
	 * Function: buildUpdateSQL
	 *
	 * Description:
	 * Builds a "UPDATE"-query based on the given query-object. It uses
	 * functions as "buildCondition" to do the job.
	 *
	 * @param $query (query-object) The query object from the the query should
	 * be built.
	 * @return (string) the mysql-update-query
	 */
	private function buildUpdateSQL($query){
		$sql = 'UPDATE ';
		$sql .= '`' . $this->db . '`.`' . $query->getTable() . '` ';

		$sql .= 'SET ';

		$i=0;
		foreach($query->getValues() as $column=>$value){
			if($i>0){
				$sql .= ', ';
			}
			$sql .= '`' . $query->getTable() . '`.`' . $column . '` = ';
			if(is_numeric($value) && preg_match('/^([0-9\.,-]+)$/', $value)){
				$sql .= $value;
			}else{
				$sql .= '\'' . mysql_real_escape_string($value) . '\'';
			}
			$i++;
		}
		
		$sql .= ' ' . $this->buildCondition($query) . ';';
		return $sql;
	}

	/**
	 * Function: buildDeleteSQL
	 *
	 * Description:
	 * Builds a "DELETE"-query based on the given query-object. It uses
	 * functions as "buildCondition" to do the job.
	 *
	 * @param $query (query-object) The query object from the the query should
	 * be built.
	 * @return (string) the mysql-delete-query
	 */
	private function buildDeleteSQL($query){
		$sql = 'DELETE FROM ';
		$sql .= '`' . $this->db . '`.`' . $query->getTable() . '` ';
		$sql .= $this->buildCondition($query);
		$sql .= ';';
		return $sql;
	}

	/**
	 * Function: buildFunctionSQL
	 *
	 * Description:
	 * Builds a function-query (for calling an stored
	 * function/procedure).
	 *
	 * @param $query (query-object) The query object from the the query should
	 * be built.
	 * @return (string) the mysql-function-query
	 */
	private function buildFunctionSQL($query){		
		$sql = 'SELECT ';
		$sql .= '`' . $this->db . '`.`' .  $query->getFunction() . '`(';
		$i = 0;
		foreach($query->getParameters() as $parameter){
			if($i>0){
				$sql .= ', ';
			}
			$sql .= '\'' . mysql_real_escape_string($parameter) . '\'';
			$i++;
		}
		$sql .= ') AS `return_value`;';
		return $sql;
	}

	/**
	 * Function: runQuery
	 *
	 * Description
	 * Runs a given query agains the mysql-db. Returns all rows or a
	 * bool if something went wrong or there is no row to return.
	 *
	 * @param $sql (string) the query which should be run
	 * @return (mixed) the selected rows or bool for success/failure
	 */
	private function runQuery($sql){
		$records = array();
		$result = mysql_query($sql, $this->connection);
		if(is_bool($result)){
			if($result === false)
				throw new Exception('Error while running sql-query: ' . mysql_error());
			$id = mysql_insert_id();
			if($id===0)
				return true;
			return mysql_insert_id();
		}
		while($row = mysql_fetch_assoc($result)){
			$records[] = $row;
		}
		return $records;
	}

	/**
	 * Function: query
	 *
	 * Description
	 * Runs a given query object, runs according functions based on the
	 * type of the query and returns the return values of the db.
	 *
	 * @param $query (query-object) the object-query which should be run
	 * @return (mixed) the selected rows or bool for success/failure
	 */
	public function query($query){
		if($query->getType() == 'select'){
			$sql = $this->buildSelectSQL($query);
		}elseif($query->getType()=='insert'){
			$sql = $this->buildInsertSQL($query);
		}elseif($query->getType()=='update'){
			$sql = $this->buildUpdateSQL($query);
		}elseif($query->getType()=='delete'){
			$sql = $this->buildDeleteSQL($query);
		}elseif($query->getType()=='function'){
			$sql = $this->buildFunctionSQL($query);
		}else{
			throw new Exception("Not supported query type: " . $query->getType());
		}

		// return just the return value of the function
		$records = $this->runQuery($sql);
		if($query->getType() == 'function'){
			$return_value = $records[0]['return_value'];
			if(is_numeric($return_value))
				return (int)$return_value;
			else
				return $return_value;
		}
		return $records;
	}

}

?>
