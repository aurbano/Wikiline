<?php
/**
   * WikipediaAPI
   * 
   * 
   * @package    Wikiline
   */
include('lib/session.php');			// Session management
include('lib/wikipedia.api.php');	// Load Wikipedia php API

class Parser{
	
	
	public function Parser(){
		// Automatically parse an article
		$this->parse();
	}
	
	/**
       * 
       * Opens last non parsed link from table parse, and looks for dates in it.
	   * it inserts the events found in the table events.
       *
       * @return boolean
       */
	public function parse(){
		// Pull the next non parsed article from the database, and look for dates in it
		// Create a DB connection
		global $sess;
		// Get the fist non parsed link
		$db = $sess->db();
		try{
			// Get next id and url, and update the parsed date
			$next = $db->queryUniqueObject('SELECT id, url FROM parse WHERE parsed IS NULL');
			print_r($next);
			if(!$next) die('No links to parse');
			$db->preparedQuery('UPDATE parse SET parsed = FROM_UNIXTIME(?) WHERE id = ? LIMIT 1',array(time(),$next->id));
		}catch(Exception $e){
			die($e->getMessage());	
		}
		// Now that we have the url, pull its content from Wikipedia
		
	}
	/**
       * 
       * Inserts new event in the Database
	   *
       * @param string $title The title of the event
	   * @param string $desc Description, maximum 5000 characters.
	   * @param int $page The page ID in parse table
	   * @param string $start Event start date, it must be a valid parameter for strtotime()
	   * @param string $end Event end date, it must be a valid parameter for strtotime(). Optional
	   * @param string $pic Event photo URL. Optional
	   * @param string $lang 2 letter language code
       * @return boolean
       */
	public function addEvent($title,$desc,$page,$start,$end=NULL,$pic=NULL,$lang='en'){
		if(strlen($title)<1 || strlen($desc)<1 || strlen($start)<1)  throw Exception('Wrong arguments');
		global $sess;
		$db = $sess->db();
		return $db->preparedInsert('events', array('title'=>$title,'desc'=>$desc,'page'=>$page,'start'=>$db->date($start),'end'=>$db->date($end),'pic'=>$pic,'lang'=>$lang),true); // In case primary or unique key are duplicated
	}
};
// Same as crawl here
$parse = new Parser();