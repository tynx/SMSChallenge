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
 * ClassName: ActiveRecord
 * Inherits: Nothing
 *
 * Description:
 * This is the parent of all the models. A model is a representation of
 * a table of a DB. With this class as parent, the child-class is able
 * to perform all the needed operations of a record in the given table,
 * such as insert, delete, update, view.
 */
Class ActiveRecord{

	/**
	 * @var (object) Instance of the dataprovider (e.g. mysql)
	 */
	protected $storage = null;

	/**
	 * @var (string) The name of the table to which the object belongs
	 * to.
	 */
	private $table = null;

	/**
	 * @var (string-array) LDAP->internal columns remapping
	 */
	private $remap = array();

	/**
	 * @var (assoc-array) All the attributes (columns) from the columns
	 * in the datastore are saved her, with the values.
	 */
	private $attributes = array();

	/**
	 * @var (bool) Whetever the current record is already created in the
	 * datastore. This is for choosing between the actions: create or
	 * update.
	 */
	private $isNew = true;

	/**
	 * Function: __construct
	 *
	 * Description:
	 * Fetches the mapping and tablename of the child-class and opens
	 * the datastorage
	 */
	public function __construct(){
		// get settings of child-class
		$this->remap = $this->getMapping();
		$this->table = $this->getTableName();
		// If we don't have a table, we quit
		if($this->table === null)
			throw new Exception('No tablename given');

		// Create/get datastore instance.
		$c = Config::getInstance();
		$this->storage = call_user_func(array($c->dataProvider, 'getInstance'), array());
	}

	/**
	 * Function: __set
	 *
	 * Description:
	 * Sets a value for a column
	 *
	 * @param $key (string) the column name
	 * @param $value (mixed) the value
	 * @return (bool) whetever the attribute is set or not
	 */
	public function __set($key, $value){
		if(empty($key) && !is_numeric($key))
			return false;
		$this->attributes[$key] = $value;
		return true;
	}

	/**
	 * Function: __get
	 *
	 * Description:
	 * gets a value for the according column
	 *
	 * @param $key (string) the column name
	 * @return (mixed) the value of the column
	 */
	public function __get($key){
		if(empty($key) && !is_numeric($key))
			return false;
		return $this->attributes[$key];
	}

	/**
	 * Function: getTableName
	 *
	 * Description:
	 * Returns the table name. This function is empty, because it has
	 * to be overwritten by the child-class.
	 *
	 * @return (string) the table name
	 */
	public function getTableName(){
		return null;
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
		return array();
	}

	/**
	 * Function: setIsNew
	 *
	 * Description:
	 * Sets the internal var to check whetever the record is already
	 * created or not.
	 *
	 * @param $value (bool) If the record is new or not
	 * @return (bool) Whetever the attribut is set or not
	 */
	public function setIsNew($value){
		if($value !== true && $value !== false)
			return false;
		$this->isNew = $value;
		return true;
	}

	/**
	 * Function: isNew
	 *
	 * Description:
	 * Returns whetever the record is already created or not.
	 *
	 * @return (bool) Whetever the record is already created or not
	 */
	public function isNew(){
		return ($this->isNew === true) ? true : false;
	}

	/**
	 * Function: model
	 *
	 * Description:
	 * Returns an empty object of the current class. This function has
	 * to be added to every child-class, so the new object is
	 * accordingly from the class
	 *
	 * @param $className (string/otional) the name of the class
	 * @return (object) the object of the class given via first parameter
	 */
	public static function model($className=__CLASS__){
		return new $className;
	}

	/**
	 * Function: populate
	 *
	 * Description:
	 * This function sets all the values for an object. It makes also
	 * sure to check, whetever (based on the given values) the record
	 * is already created or not. This is done via the ID.
	 *
	 * @param $populate (assoc-array) the array with the values
	 * accordingly to the columnname
	 * @return (bool)  If the populating was successful
	 */
	public function populate($values){
		if(isset($values['id']) && $values['id']!=0)
			$this->setIsNew(false);
		
		if(!array($values))
			return false;
		foreach($values as $key=>$value){
			if(empty($key))
				continue;
			$this->attributes[$key] = $value;
		}
		
		return true;
	}

	/**
	 * Function: save
	 *
	 * Description:
	 * This function saves the record into the DB. It does this by
	 * either doing an insert or an update. So if the record already
	 * exists in the DB, then a update is performed, otherwise an
	 * insert.
	 *
	 * @return (bool) If the save-action was successful
	 */
	public function save(){
		if($this->isNew()){
			$query = new Query('insert', $this->table);
			$query->addValues($this->attributes);
			$this->attributes['id'] = $this->storage->query($query);
			$this->setIsNew(false);
		}else{
			$query = new Query('update', $this->table);
			$query->addCriteria('id', $this->attributes['id']);
			$query->addValues($this->attributes);
			$this->storage->query($query);
		}
		return true;
	}

	/**
	 * Function: delete
	 *
	 * Description:
	 * This function deletes a record out of the DB. First it checks if
	 * the record is really existing in the DB:
	 *
	 * @return (bool) If the action was successful or not.
	 */
	public function delete(){
		if($this->isNew())
			return false;
		$query = new Query('delete', $this->table);
		$query->addCriteria('id', $this->attributes['id']);
		$this->attributes = array();
		$this->storage->query($query);
		return true;
	}


	/**
	 * Function: findByPk
	 *
	 * Description: 
	 * Finds a single record based on the ID.
	 *
	 * @param $id (integer) the ID of the wished record
	 * @return (mixed) Either the object of the record or false(bool) if
	 * not found
	 */
	public function findByPk($id){
		$id = (int)$id;
		if($id !== null){
			$entrie = $this->findByAttributes(array('id'=>$id));
			return $entrie;
		}
		return false;
	}

	/**
	 * Function: findAllByAttributes
	 *
	 * Description:
	 * This function finds all according records based on the given
	 * filters. It is basically an alias for findAll() but without
	 * having to know the order of arguments of findAll().
	 *
	 * @return (mixed) and array of all objects(records) that were found
	 * or false(bool) in case of empty resultset
	 */
	public function findAllByAttributes($values){
		return $this->findAll($values);
	}

	/**
	 * Function: findByAttributes
	 *
	 * Description:
	 * This function finds all according records in a certain order. It
	 * is basically an alias for findAll() but without having to know
	 * the order of arguments of findAll().
	 *
	 * @return (mixed) and array of all objects(records) that were found
	 * or false(bool) in case of empty resultset
	 */
	public function findByAttributes($values){
		$entries = $this->findAll($values);
		if(count($entries)!=1)
			return null;
		return $entries[0];
	}

	/**
	 * Function: findAllByOrder
	 *
	 * Description:
	 * This function finds all according records in a certain order. It
	 * is basically an alias for findAll() but without having to know
	 * the order of arguments of findAll().
	 *
	 * @return (mixed) and array of all objects(records) that were found
	 * or false(bool) in case of empty resultset
	 */
	public function findAllByOrder($order){
		return $this->findAll(null, $order);
	}

	/**
	 * Function: findAll
	 *
	 * Description:
	 * This function selects records from the DB and fills the assoc-
	 * arrays of the DB into object which represent the records. All
	 * the populated objects are given back. 
	 *
	 * @param $values (assoc-array) the values which should be filtered
	 * for with the columnnames of the index
	 * @param $order (assoc-array) the columns which should be ordered
	 * @return (mixed) and array of all objects(records) that were found
	 * or false(bool) in case of empty resultset
	 */
	public function findAll($values=null, $order=null){
		$foundRecords = array();
		$query = new Query('select', $this->getTableName());
		
		if($values !== null)
			$query->addCriterias($values);
		
		if($order !== null)
			$query->addOrders($order);
		
		$entries = $this->storage->query($query);
		foreach($entries as $i=>$entrie){
			$className = $this->model();
			$model = new $className();
			if (!$model->populate($entrie))
				throw new Exception('Couldn\'t populate object.');
			$foundRecords[] = $model;
		}
		if(empty($foundRecords))
			return null;
		return $foundRecords;
	}

	/**
	 * Function: remap
	 *
	 * Description:
	 * Remaps an assoc array. Basically swichtes the keys. This is used
	 * to get from the LDAP-keys to the internal-keys.
	 *
	 * @return (assoc) the remapped array
	 */
	public function remap($ldap_array){
		$final_array = array();
		foreach($ldap_array as $key=>$value){
			if(isset($this->remap[$key]))
				$final_array[$this->remap[$key]] = $value;
		}
		return $final_array;
	}
}

?>
