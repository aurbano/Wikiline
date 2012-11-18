<?php
/**
   * Database abstraction layer
   * this should ensure that the application doesn't rely on any database software or type
   * making it easier to migrate from one database type to another
   * 
   * Right now it's build aroung PHP PDO
   * 
   * @package	Wikiline
   * @author	Alejandro U. Alvarez <alejandro@urbanoalvarez.es>
   */
class DB{
    private $defaultDebug = false;	// If set to true, all queries will be debugged.
	private $mtStart;				// The start time, in miliseconds.
	private $nbQueries;				// The number of executed queries.
	private $lastResult;			// The last result ressource of a query()
	public $db;						// Connection holder
	
	 /** Constructor, starts the connection (If possible)
      * @param $base The database name
	  * @param $server Server, usually localhost
	  * @param $user Username
	  * @param $pass Password
      */
	function DB($base, $server, $user, $pass){
		$this->mtStart    = $this->getMicroTime();
		$this->nbQueries  = 0;
		$this->lastResult = NULL;
		try{
			$this->db = new PDO("mysql:host=$server;dbname=$base;charset=UTF-8", $user, $pass);
			$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
			if(!$this->db) throw new Exception('Connection not established');
		}catch(Exception $e){
			die($e);
		}
	}
	
	 /** Performs a query
      * @param string $query The query
	  * @param $debug If set to true, it will print debug information about the query
	  * @return MySQL result
      */
    function query($query, $debug = -1){
		$this->nbQueries++;
		$this->lastResult = $this->db->query($query);
		
		$this->debug($debug, $query, $this->lastResult);
		
		return $this->lastResult;
    }
	
   /** Executes a query. Intended for queries that don't return data, like UPDATE or DELETE
      * @param string $query The query
	  * @param $debug If set to true, it will print debug information about the query
	  * @return boolean
      */
    function execute($query, $debug = -1){
		$this->debug($debug, $query, $this->lastResult);
    	return $this->db->exec($query);
    }
	
	 /** Execute one prepared insert
      * @param string $table Table where the insert will be performed
	  * @param array $data An associative array with column=>data format.
	  * @param boolean $ignore False by default, if true it will use INSERT IGNORE
	  * @return int
      */
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
	
	 /** Use a prepared query. They are the best way to safely query a database.
	  *  In the query, put ? where you need variables. Then pass the values of those variables
	  *  inside an array, in the order the ? appear.
      * @param string $query Query containing ? for variables
	  * @param array $data An array with the data to be used
	  * @return MySQL result
      */
	function preparedQuery($query,$data){
		$stmt = $this->db->prepare($query);
		$stmt->execute($data);
		$this->lastResult = $stmt;
		return $this->lastResult;
	}
	
	/** Return the next value for a query with one column only
      * @param $result MySQL result handle
	  * @return array
      */
	function fetchNext($result=NULL){
		if($result == NULL) $result = $this->lastResult;
		$line = $result->fetch(PDO::FETCH_NUM);
		return $line[0];
	}
   	
	/** Returns next object from query. Works great with a while loop to fetch all
	  * results. For example: while($data = $db->fetchNextObject()){...}
	  * The returned object has as properties the columns returned from the query
      * @param $result MySQL result handle
	  * @return Object
      */
    function fetchNextObject($result = NULL){
		if($result == NULL) $result = $this->lastResult;
		
		if($result == NULL || $this->numRows($result) < 1) return NULL;
		else return $result->fetchObject();
    }
    
	/** Returns the number of rows from a query.
      * @param $result MySQL result handle
	  * @return int
      */
    function numRows($result = NULL){
		if($result == NULL) return $this->lastResult->rowCount();
		else return $result->rowCount();
    }
    
	/** Queries the database, appending LIMIT 1 to the query. Then returns an object
	  * holding the result.
	  * The returned object has as properties the columns returned from the query
      * @param string $query The MySQL query
	  * @return Object
      */
    function queryUniqueObject($query, $debug = -1){
		$query = "$query LIMIT 1";
		
		$this->nbQueries++;
		$result = $this->query($query);
		
		$this->debug($debug, $query, $result);
		
		return $result->fetch(PDO::FETCH_OBJ);
    }
	
	/** Queries the database, appending LIMIT 1 to the query. Then returns the result.
	  * It expects only one column to be returned.
      * @param string $query The MySQL query
	  * @return value
      */
    function queryUniqueValue($query, $debug = -1){
		$query = "$query LIMIT 1";
		
		$this->nbQueries++;
		$result = $this->query($query);
		$line = $result->fetch(PDO::FETCH_NUM);
		
		$this->debug($debug, $query, $result);
		
		return $line[0];
    }
    
	/** Returns highest value of column for a condition $where
      * @param string $column The column
	  * @param string $table The table
	  * @param string $where Conditions. For example "id>10"
	  * @return value
      */
    function maxOf($column, $table, $where){
    	return $this->queryUniqueValue("SELECT MAX(`$column`) FROM `$table` WHERE $where");
    }
    
	/** Returns highest value of column
      * @param string $column The column
	  * @param string $table The table
	  * @return value
      */
    function maxOfAll($column, $table){
		return $this->queryUniqueValue("SELECT MAX(`$column`) FROM `$table`");
    }
	
	/** Returns count of rows matching condition $where
      * @param string $table The table
	  * @param string $where Conditions. For example "id>10"
	  * @return int
      */
    function countOf($table, $where){
		return $this->queryUniqueValue("SELECT COUNT(*) FROM `$table` WHERE $where");
    }
	
	/** Count of rows in a table
      * @param string $table The table
	  * @return int
      */
    function countOfAll($table){
		return $this->queryUniqueValue("SELECT COUNT(*) FROM `$table`");
    }
	
    /** Debug function. Returns useful information about the query
      * @param $debug Debug or not
	  * @param string $query The query
	  * @param $result The result of the query, used to display information about it.
	  * @return int
      */
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
    function debugQuery($query, $reason = "Debug", $return = false){
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
	function debugResult($result){
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
   
   	 /** Returns execution time
      */
    function getExecTime(){
    	return round(($this->getMicroTime() - $this->mtStart) * 1000) / 1000;
    }
    
	 /** Returns page query count
      */
    function getQueriesCount(){
    	return $this->nbQueries;
    }
   
   	 /** Reset fetch
	   * MUST FIX!
      */
    function resetFetch($result){
		/*if($this->numRows($result) > 0)
		mysql_data_seek($result, 0);*/
    }
    
	/** Return last inserted ID
      */
    function lastInsertedId(){
		return $this->db->lastInsertId();
    }
    
	/** Close the MySQL connection
      */
    function close(){
    	$this->db = null;
    }
	
	/** Format a date to MySQL datetime format. With no parameters returns current datetime.
	  * @param $date The date, in any desired format.
	  * @param boolean $timestamp If true, date is in unix timestamp format. If false it's not
	  * @return The date in MySQL datetime format
      */
	function date($date=false,$timestamp=false){
		// Convert a date to mysql format
		if(!$date) $date = time();
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