<?php
/**
   * Session
   * This should be called always at the start of every file. It will handle
   * file includes and session management. In case we add users in the future.
   * It also handles database connection
   * 
   * 
   * @package    Wikiline
   */
error_reporting(-1);
session_start();
// Avoids errors in date functions
date_default_timezone_set('America/New_York');

class Session{
	private $db;	// Database handler holder.
	var $mtStart;
	
	public function Session(){
		// Time counter
		list($msec, $sec) = explode(' ', microtime());
		$this->mtStart    = floor($sec / 1000) + $msec;
	}
	
	/**
       * DB Connection
       *
       * @return DB
       */
	public function db(){
		if($this->db) return $this->db;
		include('lib/db.class.php');
		// Working with a local database for now
		return $this->db = new DB('timeline','localhost','time','hWwnZbAT6dME9vde');	
	}
	
	/**
	 * Returns execution time until now
	 */
	 public function execTime(){
	 	return xdebug_time_index();
	 	list($msec, $sec) = explode(' ', microtime());
		return floor($sec / 1000) + $msec - $this->mtStart;
	 }
};

// Create a new Session object
$sess = new Session();