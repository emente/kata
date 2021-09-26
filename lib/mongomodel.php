<?php

/**
 * schnell zusammengehacktes mongomodel
 * @package kata_model
 */
class MongoModel {

    /**
     * which connection to use of the ones defines inside config/database.php
     * 
     * @var string
     */
    public $connection = 'default';

    /**
     * whether to use a specific table for this model. false if not specific, otherwise the tablename
     * 
     * @var string
     */
    public $useTable = false;

    /**
     * which fieldname to use for primary key. is 'id' by default, override it in your
     * model wo 'table_id' or 'tableId' as you like.
     * 
     * @var string 
     */
    public $useIndex = 'id';

    /**
     * convenience method for writeLog
     * 
     * @param string $what what to log
     * @param string $where errorlevel (0-2)
     */
    function log($what, $where) {
        writeLog($what, $where);
    }

    private $link = false;
    private $config;

    private function getCollection($altTableName = null) {
        if (!$this->link) {
            require_once ROOT . 'config' . DS . 'database.php';
            if (!class_exists('DATABASE_CONFIG')) {
                throw new Exception('Incorrect config/database.php');
            }

            $dbvars = get_class_vars('DATABASE_CONFIG');
            if (empty($dbvars[$this->connection])) {
                throw new DatabaseConnectException("Cant find configdata for database-connection '$connName'");
            }

            $this->config = $dbvars[$this->connection];

            if (!empty($this->config['options'])) {
                $opts = $this->config['options'];
            } else {
                $opts = array(
                );
            }
            if (!empty($this->config['login'])) {
                $opts['username'] = $this->config['login'];
            }
            if (!empty($this->config['password'])) {
                $opts['password'] = $this->config['password'];
            }
            $opts['connect'] = true;
			
            $this->link = new Mongo('mongodb://'.$this->config['host'], $opts);

			
            if (!empty($this->config['slaveOkay'])) {
                $this->setSlaveOkay(true);
            }
        }

        $dbName = $this->config['database'];
        $db = $this->link->$dbName;

        if (false === $altTableName) {
            return;
        }

        $tableName = $this->getPrefix() . $this->useTable;
        if (!empty($altTableName)) {
            $tableName = $this->getPrefix() . $altTableName;
        }
        return $db->$tableName;
    }

    function getTables() {
        $this->getCollection(false);

        //FIXME
        $dbName = $this->config['database'];
        $db = $this->link->$dbName;

        $names = array();

        $cols = $db->listCollections();
        foreach ($cols as $col) {
            $names[] = $col->getName();
        }

        return $names;
    }

    /**
     * return the prefix configured for this connection
     *
     * @return string
     */
    public function getPrefix() {
        if (isset($this->config['prefix'])) {
            return $this->config['prefix'];
        }
        return '';
    }

    /**
     *
     * @param string $id	index to use
     * @param string $tableName collection to use
     * @param bool $readOne only read one
     * @param array $fields fields to return
     * @param int $limit limit to amount of entries
     * @param array $sortby sort by fields
     * @return array
     */
    function read($id = null, $tableName = null, $readOne = false, $fields = array(), $limit = null, $sortby = null) {
        $col = $this->getCollection($tableName);
        if (is_null($id)) {
            $id = array();
        } else {
            if (!is_array($id)) {
                $id = array('_id' => $id);
            }
        }

        if ($readOne) {
            $row = $col->findOne($id, $fields);
			
            if (isset($row['_id'])) {
                $row[$this->useIndex] = (string) $row['_id'];
                unset($row['_id']);
            }
            return $row;
        }

        $return = array();

        $rows = $col->find($id, $fields);
		
        if ($limit) {
            $rows = $rows->limit($limit);
        }
        if ($sortby) {
            $rows = $rows->sort($sortby);
        }
        while ($row = $rows->getNext()) {
            $id = (string) $row['_id'];
            $row[$this->useIndex] = $id;
            unset($row['_id']);

            $return[$id] = $row;
        }

        return $return;
    }

    /**
     * insert a record into the database.
     *
     * <code>
     * $this->create(array('fooId'=>1,'int1'=>10,'int2'=>20));
     * </code>
     *
     * @param array $fields name=>value pairs to be inserted into the table
     * @param string $tableName insert into this table (if ommitted: use tablename of this model, including prefix)
     */
    function create($fields = null, $tableName = null) {
        if (isset($fields[$this->useIndex])) {
            $fields['_id'] = (string) $fields[$this->useIndex];
            unset($fields[$this->useIndex]);
        }

        $col = $this->getCollection($tableName);
        return $col->save($fields, array('safe' => true));
    }

    /**
     * delete the row whose id is matching
     *
     * <code>
     * $this->delete(array('rowId'=>10));
     * $this->delete(array(
     * 	'rowId'=>20,
     * 	'and',
     *  'parentId'=>10
     * ));
     * </code>
     *
     * @param mixed $id primary key of row to delete
     * @param string $tableName delete from this table (if ommitted: use tablename of this model, including prefix)
     */
    function delete($id = null, $tableName = null) {
        if (is_bool($id)) {
            throw new InvalidArgumentException('delete with bool condition, seems odd');
        }
        if (empty($id)) {
            throw new InvalidArgumentException('delete with empty condition, seems odd');
        }
        if (!is_array($id)) {
            $id = array('_id' => $id);
        }
        if (isset($id[$this->useIndex])) {
            $id['_id'] = (string) $id[$this->useIndex];
            unset($id[$this->useIndex]);
        }

        $col = $this->getCollection($tableName);
        $col->remove($id, array('safe' => true));
    }

    /**
     * update a row whose id is matching. 
     * 
     * <code>
     * $this->update(array(
     * 	'fooId'=>10,
     *  'data1'=>20
     * ));
     * </code>
     *
     * @param mixed $id primary array of fields suitable to construct a WHERE clause
     * @param array $fields name=>value pairs of new values
     * @param string $tableName update data in this table (if ommitted: use tablename of this model, including prefix)
     */
    function update($id, $fields, $tableName = null) {
        if (empty($id)) {
            throw new InvalidArgumentException('update with empty id, seems odd');
        }
        if (empty($fields)) {
            throw new InvalidArgumentException('insert without fields, seems odd');
        }
        if (!is_array($fields)) {
            throw new InvalidArgumentException('fields must be an array');
        }

        if (!is_array($id)) {
            $id = array('_id' => $id);
        }
        if (isset($id[$this->useIndex])) {
            $id['_id'] = (string) $id[$this->useIndex];
            unset($id[$this->useIndex]);
        }

        $col = $this->getCollection($tableName);
        $col->update($id, $fields, array('upsert' => true));
    }

    /**
     *
     * @param string $selector	fields to select entries to count
     * @param string $tableName collection to use
     * @return int number of matching entries
     */
    function count($selector, $tableName = null) {
        $col = $this->getCollection($tableName);
        return $col->count($selector);
    }

    /**
     * REPLACE works exactly like Insert, but removes previous entries.
     *
     * Warning: if an old row in the table has the same value as a new row for a PRIMARY KEY or a UNIQUE index,
     * the old row is deleted before the new row is inserted. In short: It may be that more than 1 row is deleted.
     *
     * <code>
     * $this->replace(array(
     * 	'fooId'=>10,
     *  'data1'=>20
     * ));
     * </code>
     *
     * @param mixed $id primary key of row to replace
     * @param array $fields name=>value pairs of new values
     * @param string $table replace from this table (if ommitted: use tablename of this model, including prefix)
     */
    function replace($fields = null, $tableName = null) {
        if (empty($fields)) {
            throw new InvalidArgumentException('insert without fields, seems odd');
        }
        if (!is_array($fields)) {
            throw new InvalidArgumentException('fields must be an array');
        }

        if (!is_array($id)) {
            $id = array('_id' => $id);
        }
        if (isset($id[$this->useIndex])) {
            $id['_id'] = (string) $id[$this->useIndex];
            unset($id[$this->useIndex]);
        }

        $col = $this->getCollection();
        $col->remove($id['_id']);
        $col->insert($fields);
    }

    /**
     * drop complete collection
     * 
     * @param type $tableName name of the collection
     * @return type success
     */
    function drop($tableName = null) {
        $col = $this->getCollection($tableName);
        return $this->link->dropCollection($col->__toString());
    }

    /**
     *
     * @param type $col
     * @param type $index indexes to set
     * @param type $opts optional options
     * @return type 
     */
    function ensureIndex($col, $index, $opts = array()) {
        if (empty($index)) {
            throw new InvalidArgumentException('enshureIndex without indexes, seems odd');
        }
        if (!is_array($index)) {
            throw new InvalidArgumentException('$index must be an array');
        }
        if (!is_array($opts)) {
            throw new InvalidArgumentException('$opts must be an array');
        }

        $col = $this->getCollection($col);
        return $col->ensureIndex($index, $opts);
    }

    function __destroy() {
        if ($this->link) {
            $this->link->close();
        }
    }
    
}