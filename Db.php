<?php

/**
 *
 * @author Ivan Villareal
 * @category foundation
 * @package Db
 * @version $Id$
 */

/**
 * Db Abstraction Class
 * 
 * @category foundation
 * @package Db
 *
 */
class Db
{
	static private $instance = null;
	private	$_link;
	private $_affectedRows;
	private $_lastQueryStatus;
	
	/**
	* Instantiate the object
	**/
	public function __construct( $user, $pass, $dbName, $host = 'localhost')
	{
	   $this->_dbName = $dbName;
		$this->_link = mysql_connect($host, $user, $pass, true);
		mysql_select_db($dbName, $this->_link) or die('Could not select database');
		if (mysql_error()) {
			printf("Connect failed: %s\n", mysql_error());
			exit();
		} else {
		    $sql = "SET NAMES `utf8`";
            mysql_query($sql, $this->_link);
            mysql_query("SET CHARACTER SET 'utf8';", $this->_link);
		}
		
	}

	public function conn()
	{
		return $this->_link;
	}

	private function error($msg='')
	{
		echo $msg;
	}
	
	public function getAffectedRows()
	{
		return $this->_affectedRows;
	}
	
	public function getQueryId()
	{
		return $this->_queryId;
	}

    public function getMetadata($table)
    {
        return mysql_List_fields(DB_NAME, $table);
    }


	public function escape($string) 
	{
		if(get_magic_quotes_gpc()) $string = stripslashes($string);
		return mysql_real_escape_string($string);
	}
	
	/**
	* Perform a query
	*
	* @param string $sql
	*/
	public function query($sql)
	{
		$this->_lastQueryStatus = @mysql_query($sql, $this->_link) or die('Query failed: ' . mysql_error($this->_link) . '<br> SQL: '. $sql);
		if (!$this->_lastQueryStatus) {
			$this->error("<b>MySQL Query fail: </b> $sql");
		} 
		$this->_affectedRows    = @mysql_affected_rows(); 
		return $this->_lastQueryStatus;
	}

	public function deleteRow($table, $where = '1')
	{
		$sql = "DELETE FROM $table WHERE $where LIMIT 1";
		$res = $this->query($sql);
		return $res;
	}
	
	public function fetchAssoc($sql) 
	{
		$result = $this->query($sql);
		$table = array();
		while ($row = mysql_fetch_assoc($result)) {
			$table[] = $row;
		}
		//$result = mysql_fetch_assoc($result);
		return $table;
	}
	
	public function fetchRow($table, $where = '1', $order = '', $fields = '*')
	{
		$sql = "SELECT $fields FROM $table WHERE $where $order LIMIT 1";
		$result = $this->query($sql);
		return mysql_fetch_assoc($result);
	}
    
    public function fetchRows($table, $where = '1', $order = '', $fields = '*', $limit=0)
	{
		$sql = "SELECT $fields FROM $table WHERE $where $order";
        
        if($limit > 0) $sql .= " LIMIT $limit";
        
        $result = $this->query($sql);
        
        $table = array();        
		while ($row = mysql_fetch_assoc($result)) {
			$table[] = $row;
		}
		//$result = mysql_fetch_assoc($result);
		return $table;
	}
	
	/**
	 * Get column information from a result and return as an object
	 * 
	 * @param $table
	 * @param $field
	 * @param $where
	 * @return object
	 */
	public function fetchField($table, $field, $where = '1')
	{
	    $sql = "SELECT `$field` FROM `$table` WHERE $where";
	    $result = $this->query($sql);
	    $field = mysql_fetch_row($result);
	    return $field[0];
	}

        /**
         * method to get the last id from a table
         * this is usefull, when I need to know the last value,
         * withouth making an insert.
         */
        public function getLastInsertedId($table, $primary)
        {
            $sql = "SELECT `$primary` FROM `$table` ORDER BY `$primary` DESC";
            $result = $this->query($sql);
            $row = mysql_fetch_row($result);
            $field = $row[0];
            $field = is_numeric($field) ? $field : 0;
            return $field;
        }
	
	public function fetchCount($table, $join = '', $where = '', $groupBy = '')
	{
	    if ($table == false) {
	        $sql = $join;
	    } else {
	        $select = "SELECT COUNT(*) FROM $table";
	        //$sql = $join ? $sql . " $join " . $where : $sql . $where;
	        $sql = "$select $join $where $groupBy";
	    }
           //echo $sql;die(); 
	    $query = $this->query($sql);
	    if ($groupBy == '') {
    	    $row = mysql_fetch_row($query);
    	    $result = $row[0];
	    } else {
            $result = mysql_num_rows($query);	        
	    }
	    return $result;
	}
	
	/**
	 * Easy way to Performs Inserts
	 */
	public function queryInsert($table, $data) 
	{
		$q="INSERT INTO `".$table."` ";
		$v=''; 
		$n='';

		foreach($data as $key=>$val) {
			$n.="`$key`, ";
			if(isset($val->expression)) $v.=$val->expression .", ";
			elseif(strtolower($val)=='null') $v.="NULL, ";
			elseif(strtolower($val)=='now()') $v.="NOW(), ";
			elseif(strtolower($val)=='utc_timestamp()') $v.="UTC_TIMESTAMP(), ";
			else $v.= "'".$this->escape($val)."', ";
		}
		$q .= "(". rtrim($n, ', ') .") VALUES (". rtrim($v, ', ') .");";
		if($this->query($q)){
			//$result = mysql_insert_id();
                        $q = $this->query("SELECT LAST_INSERT_ID() FROM $table");
                        $row = mysql_fetch_row($q);
                        $result = $row[0];
		} else {
			$result = false;
		}
		return $result;
	}
	
	public function expression($string)
	{
		$res = new stdClass();
		$res->expression = $string;
		return $res;
	}

	/**
	 * This method queries for the unique field
	 * if exists updates if not insert, its diferent
	 * from insertunique Method, because there doesn't need
	 * to be keys
	 */
	public function insertUpdate($table, $data, $field, $uniqueValue)
	{
	    $where = is_int($uniqueValue) ? "$field = $uniqueValue" : "$field = '".$this->escape($uniqueValue)."'"; 
	    $e = $this->fetchCount($table, '', 'WHERE '.$where);
            
	    if ($e) {
	        $res = $this->queryUpdate($table, $data, $where);
	    } else {
	        $res = $this->queryInsert($table, $data);
	    }
	    return $res;
	}
	
	public function insertUnique($table, $id = false, $unique, $data) 
	{
	    $q="INSERT INTO `".$table."` ";
		$v=''; 
		$n='';
		$u='';

		foreach($data as $key=>$val) {
			$n.="`$key`, ";
			if(strtolower($val)=='null') { 
			    $v.="NULL, ";
			    if ($unique!=$key)  
			        $u.= "`$key` = NULL, "; 
			} elseif(strtolower($val)=='now()') {
			    $v.="NOW(), ";
			    if ($unique!=$key)
			        $u.= "`$key` = NOW(), ";
			} else {
			     $v.= "'".$this->escape($val)."', ";
			     if ($unique!=$key)
			         $u.= "`$key`='".$this->escape($val)."', ";  
			}
		}
		$u = rtrim($u, ', ');
		$lastInsert = $id ? "$id=LAST_INSERT_ID($id)," : "";
		$q .= "(". rtrim($n, ', ') .") VALUES (". rtrim($v, ', ') .") ON DUPLICATE KEY UPDATE $lastInsert $u";
		//echo $q . "\n"; die();
		if($this->query($q)){
			$result = mysql_insert_id();
		} else {
			$result = false;
		}
		return $result;
	}
	
	/**
	 * Update a row
	 * 
	 * @param $table
	 * @param $data
	 * @param $where
	 * @return unknown_type
	 */
	public function queryUpdate($table, $data, $where='1') {
    $q="UPDATE `".$table."` SET ";

    foreach($data as $key=>$val) {
		if(isset($val->expression)) $q.="`$key` = ".$val->expression .", ";
        elseif(strtolower($val)=='null') $q.= "`$key` = NULL, ";
        elseif(strtolower($val)=='now()') $q.= "`$key` = NOW(), ";
        else $q.= "`$key`='".$this->escape($val)."', ";
    }
    $q = rtrim($q, ', ') . ' WHERE '.$where.';';

    return $this->query($q);
	}

	public function getDbName()
	{
		return $this->_dbName;
	}

	/**
	 * TODO: short description.
	 * 
	 * @param mixed $table  
	 * @param mixed $column 
	 * 
	 * @return Array
	 */
	public function getEnumValues($table, $column)
	{
		$sql = "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
		    WHERE TABLE_NAME = '$table' AND COLUMN_NAME = '$column'";
		$result = $this->query($sql);
		if ($result) {
			$values = mysql_fetch_assoc($result);
			$enum   = explode(",", str_replace("'", "", substr($row['COLUMN_TYPE'], 5, (strlen($row['COLUMN_TYPE'])-6))));
		} else {
			$enum = false;
		}
		return $enum;
	}
}
