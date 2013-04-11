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
 * ClassName: Query
 * Inherits: Nothing
 *
 * Description:
 * This class can contain all needed information to create a full
 * query for a database. It only contains the meta-data, so there is
 * nothing driver-specific(e.g mysql-stuff) inside of it. If an object
 * is given to a database-handler (like the class Mysql) the handler is
 * capable of building a complete working query.
 */
class Query{

	/**
	 * @var (string-array) which kind of query-types are supported
	 */
	private $allowed_types = array('select','insert', 'update', 'delete', 'function');

	/**
	 * @var (string) the type of query
	 */
	private $type = null;

	/**
	 * @var (string) which table should be affected
	 */
	private $table = null;

	/**
	 * @var (string) which function should be called
	 */
	private $function = null;

	/**
	 * @var (assoc-array) criterias for the conditions
	 */
	private $criterias = array();

	/**
	 * @var (assoc-array) the columns and values which should be
	 * inserted/updated
	 */
	private $values = array();

	/**
	 * @var (string-array) the parameters for a function (order is
	 * important!)
	 */
	private $parameters = array();

	/**
	 * @var (assoc-array) how to sort the result
	 */
	private $orders = array();

	/**
	 * @var (integer-array) the number of items in the result
	 * (start,count)
	 */
	private $limit = array(0,500);

	/**
	 * Function: __construct
	 *
	 * Description:
	 * Get an instance of a query. This contains all the basic
	 * information like the affected table or function and the type.
	 *
	 * @param $type (string) the type of the query
	 * @param $table_function (string) the affected table or function
	 * based on the type of the query
	 */
	public function __construct($type, $table_function){
		if(!in_array(strtolower($type), $this->allowed_types))
			throw new Exception('No valid type given. Only ' . implode(', ', $this->allowed_types) . ' supported. Given was: ' . $type);
		if($table_function == '')
			throw new Exception('No table name given');
		$this->type = $type;
		if($type == 'function'){
			$this->function = $table_function;
		}else{
			$this->table = $table_function;
		}
	}

	/**
	 * Function: addValue
	 *
	 * Description:
	 * Adds a value for the insert and update type.
	 *
	 * @param $key (string) the column/key
	 * @param $value (string) the according value (can be zero or empty
	 * string, but nothing else "empty")
	 */
	public function addValue($key, $value){
		if(empty($key) || empty($value) && !is_numeric($value) && $value !== '')
			return false;
		$this->values[$key] = $value;
	}

	/**
	 * Function: addValues
	 *
	 * Description:
	 * Adds multiple values for the insert and update type. Alias for
	 * addValue.
	 *
	 * @param $values (assoc-array) the columns/keys => values
	 */
	public function addValues($values){
		if(!is_array($values))
			return false;
		foreach($values as $key=>$value)
			$this->addValue($key, $value);
	}

	/**
	 * Function: addParameter
	 *
	 * Description:
	 * Adds a parameter for a function call! ATTENTION! Order is
	 * important!
	 *
	 * @param $values (mixed) the columns/keys => values (can be
	 * zero or empty string, but nothing else "empty")
	 */
	public function addParameter($parameter){
		if(empty($parameter) && !is_numeric($parameter) && $parameter !== '')
			return false;
		$this->parameters[] = $parameter;
	}

	/**
	 * Function: addParameters
	 *
	 * Description:
	 * Adds multiple parameters for a function call! ATTENTION! Order is
	 * important!
	 *
	 * @param $values (assoc-array) the parameters (can be zero or empty
	 * string, but nothing else "empty")
	 */
	public function addParameters($parameters){
		if(!is_array($parameters))
			return false;
		foreach($parameters as $parameter)
			$this->addParameter($parameter);
	}

	/**
	 * Function: addCriteria
	 *
	 * Description:
	 * Adds a criteria for delete/update/insert queries.
	 *
	 * @param $key (string) The column/key
	 * @param $value (string) the value
	 */
	public function addCriteria($key, $value){
		if(empty($key) || empty($value) && !is_numeric($value) && $value !== '')
			return false;
		$this->criterias[$key] = $value;
	}

	/**
	 * Function: addCriterias
	 *
	 * Description:
	 * Adds multiple criterias for delete/update/insert queries.
	 *
	 * @param $values (assoc-array) The column/key => values array
	 */
	public function addCriterias($values){
		if(!array($values))
			return false;
		foreach($values as $key=>$value)
			$this->addCriteria($key, $value);
	}

	/**
	 * Function: addOrder
	 *
	 * Description:
	 * Adds a order for the result-set.
	 *
	 * @param $values (string) The column/key
	 * @param $direction (string/optional) Either ASC or DESC.
	 * Default: ASC
	 */
	public function addOrder($column, $direction = 'ASC'){
		if(empty($column) || ($direction != 'ASC' && $direction != 'DESC' && $direction != '') )
			return false;
		if($direction == '')
			$direction = 'ASC';
		$this->orders[$column] = $direction;
	}

	/**
	 * Function: addOrders
	 *
	 * Description:
	 * Adds multiple orders for the result-set.
	 *
	 * @param $values (assoc-array) The column/key => direction array
	 */
	public function addOrders($values){
		if(!is_array($values))
			return false;
		foreach($values as $column => $direction)
			$this->addOrder($column, $direction);
	}

	/**
	 * Function: setLimit
	 *
	 * Description:
	 * Sets the limit of the result-query. 2 Parameters are needed:
	 * where to start and how far to go.
	 *
	 * @param $count (integer) the amount of result-lines
	 * @param $start (integer) from which result to start
	 */
	public function setLimit($count, $start){
		$this->limit[0] = $start;
		$this->limit[1] = $count;
	}

	/**
	 * Function: getType
	 *
	 * Description:
	 * Returns the type of the query.
	 *
	 * @return (string) the type of the query
	 */
	public function getType(){
		return $this->type;
	}

	/**
	 * Function: getTable
	 *
	 * Description:
	 * Returns the tablename.
	 *
	 * @return (string) the tablename
	 */
	public function getTable(){
		return $this->table;
	}

	/**
	 * Function: getFunction
	 *
	 * Description:
	 * Returns the name of the function/procedure
	 *
	 * @return (string) the name of the function/procedure
	 */
	public function getFunction(){
		return $this->function;
	}

	/**
	 * Function: getValues
	 *
	 * Description:
	 * Returns the columns/values (assoc-array) for insert/update 
	 * queries.
	 *
	 * @return (assoc-array) the columns/values
	 */
	public function getValues(){
		return $this->values;
	}

	/**
	 * Function: getParameters
	 *
	 * Description:
	 * Returns the parameters (string-array) for function queries.
	 *
	 * @return (string-array) the parameters
	 */
	public function getParameters(){
		return $this->parameters;
	}

	/**
	 * Function: getCriterias
	 *
	 * Description:
	 * Returns the columns/values (assoc-array) for queries with
	 * conditions.
	 *
	 * @return (assoc-array) the criterias
	 */
	public function getCriterias(){
		return $this->criterias;
	}

	/**
	 * Function: getLimit
	 *
	 * Description:
	 * Returns the limit for a query with results.
	 *
	 * @return (integer-array) the limit (start, count)
	 */
	public function getLimit(){
		return $this->limit;
	}

	/**
	 * Function: getOrders
	 *
	 * Description:
	 * Returns the orders (assoc-array) for queries with results.
	 *
	 * @return (assoc-array) the orders
	 */
	public function getOrders(){
		return $this->orders;
	}
}

?>
