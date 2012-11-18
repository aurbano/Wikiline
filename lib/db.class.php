<?php
/*	An SQL Wrapper for PHP built using PDO
	Built by Alejandro U. Alvarez
	http://urbanoalvarez.es
 */
class DB{
    
	
	private $defaultDebug = false;	// Put this variable to true if you want ALL queries to be debugged by default
	private $mtStart;				//INTERNAL: The start time, in miliseconds.
	private $nbQueries;				//INTERNAL: The number of executed queries.
	private $lastResult;			// INTERNAL: The last result ressource of a query()
	public $db;					// Holds the connection
	
	// Constructor, sets up connection
	function DB($base, $server, $user, $pass){
		$this->mtStart    = $this->getMicroTime();
		$this->nbQueries  = 0;
		$this->lastResult = NULL;
		try{
			// Proxy UNIOVI
			$this->db = new PDO("mysql:host=$server;dbname=$base;charset=UTF-8", $user, $pass);
			$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
			if(!$this->db) throw new Exception('Connection not established');
		}catch(Exception $e){
			die($e);
		}
	}
	
	// Perform a query on the database
    function query($query, $debug = -1){
		$this->nbQueries++;
		$this->lastResult = $this->db->query($query);
		
		$this->debug($debug, $query, $this->lastResult);
		
		return $this->lastResult;
    }
	
    // Execute, returns affected rows
    function execute($query, $debug = -1){
    	return $this->db->exec($query);
		$this->debug($debug, $query, $this->lastResult);
    }
	
	// Prepared statements!
	// Table is the destination table
	// Data is a 2 dim array as: ['field'] = 'value'
	// Returns the row count
	function preparedInsert($table, $data, $ignore = false){
		if(sizeof($data)<1) return false;
		// Setup:
		while(list($key, $value) = each($data)){
			$fields .= ','.$key;
			$insert[':'.$key] = $value;
		}
		if($ignore) $ignore = 'IGNORE';
		$fields = substr($fields,1);
		$stmt = $this->db->prepare("INSERT {$ignore} INTO {$table}({$fields}) VALUES(:".str_replace(',',',:',$fields).")");
		$stmt->execute($insert);
		return $this->db->lastInsertId();
	}
	
	// Prepared query
	function preparedQuery($query,$data){
		$stmt = $this->db->prepare($query);
		$stmt->execute($data);
		$this->lastResult = $stmt;
		return $this->lastResult;
	}
	
	// Return the next value for a query with one column only
	function fetchNext($result=NULL){
		if($result == NULL) $result = $this->lastResult;
		$line = $result->fetch(PDO::FETCH_NUM);
		return $line[0];
	}
   	
	// Return results as objects
    function fetchNextObject($result = NULL){
		if($result == NULL) $result = $this->lastResult;
		
		if($result == NULL || $this->numRows($result) < 1) return NULL;
		else return $result->fetchObject();
    }
    
	// Return number of rows
    function numRows($result = NULL){
		if($result == NULL) return $this->lastResult->rowCount();
		else return $result->rowCount();
    }
    
	// Return object for query
    function queryUniqueObject($query, $debug = -1){
		$query = "$query LIMIT 1";
		
		$this->nbQueries++;
		$result = $this->query($query);
		
		$this->debug($debug, $query, $result);
		
		return $result->fetch(PDO::FETCH_OBJ);
    }
	
	// Returns unique value for query
    function queryUniqueValue($query, $debug = -1){
		$query = "$query LIMIT 1";
		
		$this->nbQueries++;
		$result = $this->query($query);
		$line = $result->fetch(PDO::FETCH_NUM);
		
		$this->debug($debug, $query, $result);
		
		return $line[0];
    }
    
	// Returns highest value of column for a condition $where
    function maxOf($column, $table, $where){
    	return $this->queryUniqueValue("SELECT MAX(`$column`) FROM `$table` WHERE $where");
    }
    
	// Returns max in column
    function maxOfAll($column, $table){
		return $this->queryUniqueValue("SELECT MAX(`$column`) FROM `$table`");
    }
	
	// Count of rows matching condition $where
    function countOf($table, $where){
		return $this->queryUniqueValue("SELECT COUNT(*) FROM `$table` WHERE $where");
    }
	
	// Count of rows in table
    function countOfAll($table){
		return $this->queryUniqueValue("SELECT COUNT(*) FROM `$table`");
    }
	
    // Debug MySQL query
    function debug($debug, $query, $result = NULL){
      if ($debug === -1 && $this->defaultDebug === false)
        return;
      if ($debug === false)
        return;

      $reason = ($debug === -1 ? "Default Debug" : "Debug");
      $this->debugQuery($query, $reason);
      if ($result == NULL)
        echo "<p style=\"margin: 2px;\">Number of affected rows: ".$result->rowCount()."</p></div>";
      else
        $this->debugResult($result);
    }
    /** Internal function to output a query for debug purpose.\n
      * Should be followed by a call to debugResult() or an echo of "</div>".
      * @param $query The SQL query to debug.
      * @param $reason The reason why this function is called: "Default Debug", "Debug" or "Error".
      */
    function debugQuery($query, $reason = "Debug", $return = false)
    {
      $color = ($reason == "Error" ? "red" : "orange");
      $ret = "<div style=\"border: solid $color 1px; margin: 2px;\">".
           "<p style=\"margin: 0 0 2px 0; padding: 0; background-color: #DDF;\">".
           "<strong style=\"padding: 0 3px; background-color: $color; color: white;\">$reason:</strong> ".
           "<span style=\"font-family: monospace;\">".htmlentities($query)."</span></p>";
	  if($return) return $ret; else echo $ret;
    }
    /** Internal function to output a table representing the result of a query, for debug purpose.\n
      * Should be preceded by a call to debugQuery().
      * @param $result The resulting table of the query.
      */
    function debugResult($result)
    {
      echo "<table border=\"1\" style=\"margin: 2px;\">".
           "<thead style=\"font-size: 80%\">";
      $numFields = $result->columnCount();
      // BEGIN HEADER
      $tables    = array();
      $nbTables  = -1;
      $lastTable = "";
      $fields    = array();
      $nbFields  = -1;
      while ($column = $result->getColumnMeta()) {
        if ($column->table != $lastTable) {
          $nbTables++;
          $tables[$nbTables] = array("name" => $column->table, "count" => 1);
        } else
          $tables[$nbTables]["count"]++;
        $lastTable = $column->table;
        $nbFields++;
        $fields[$nbFields] = $column->name;
      }
      for ($i = 0; $i <= $nbTables; $i++)
        echo "<th colspan=".$tables[$i]["count"].">".$tables[$i]["name"]."</th>";
      echo "</thead>";
      echo "<thead style=\"font-size: 80%\">";
      for ($i = 0; $i <= $nbFields; $i++)
        echo "<th>".$fields[$i]."</th>";
      echo "</thead>";
      // END HEADER
      while ($row = $result->fetch(PDO::FETCH_NUM)) {
        echo "<tr>";
        for ($i = 0; $i < $numFields; $i++)
          echo "<td>".htmlentities($row[$i])."</td>";
        echo "</tr>";
      }
      echo "</table></div>";
      $this->resetFetch($result);
    }
   
   	// Return exdecution time
    function getExecTime(){
    	return round(($this->getMicroTime() - $this->mtStart) * 1000) / 1000;
    }
    
	// Return query count
    function getQueriesCount(){
    	return $this->nbQueries;
    }
   
   	// Reset fetch
    function resetFetch($result){
		/*if($this->numRows($result) > 0)
		mysql_data_seek($result, 0);*/
    }
    
	// ID of last insert
    function lastInsertedId(){
		return $this->db->lastInsertId();
    }
    
	// Close connection
    function close(){
    	$this->db = null;
    }
	
	function date($date,$timestamp=false){
		// Convert a date to mysql format
		if(!$timestamp) $time = strtotime($date);
		else $time = $date;
		return date("Y-m-d H:i:s", $time);	
	}

    /** Internal method to get the current time.
      * @return The current time in seconds with microseconds (in float format).
      */
    function getMicroTime(){
		list($msec, $sec) = explode(' ', microtime());
		return floor($sec / 1000) + $msec;
    }
  }; // class DB