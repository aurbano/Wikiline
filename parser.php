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
	private $wiki;
	
	public function Parser(){
		$this->wiki = new WikipediaAPI();
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
	function parse(){
		// Pull the next non parsed article from the database, and look for dates in it
		// Create a DB connection
		global $sess;
		// Get the fist non parsed link
		/*$db = $sess->db();
		try{
			// Get next id and url, and update the parsed date
			$next = $db->queryUniqueObject('SELECT id, url FROM parse WHERE parsed IS NULL');
			print_r($next);
			if(!$next) die('No links to parse');
			//$db->preparedQuery('UPDATE parse SET parsed = FROM_UNIXTIME(?) WHERE id = ? LIMIT 1',array(time(),$next->id));
		}catch(Exception $e){
			die($e->getMessage());	
		}
		// Make sure the crawl url is properly formatted
		$url = str_replace(' ','_',$next->url);*/
		$url = 'Joseph_Clay_Stiles_Blackburn';
		// Pull article from Wikipedia
		$data = json_decode($this->wiki->request($url,'parse','text'),true);
		
		// Birth info regex
		$res = array();
		preg_match('@(Joseph (.*) Blackburn) \((january|february|march|april|may|june|july|august|september|october|november|december) ([0-9]{1,2}),? ([0-9]{2,4}) (–|0x2013|-)? (january|february|march|april|may|june|july|august|september|october|november|december) ([0-9]{1,2}),? ([0-9]{2,4})\)@i', strip_tags($data['parse']['text']['*']), $res);
		print_r($res);
		die();
		$total = count($res);
		echo 'total:'.$total;
		$date1 = $res[$total-6].' '.$res[$total-5].', '.$res[$total-4];
		$date2 = $res[$total-3].' '.$res[$total-2].', '.$res[$total-1];
		echo '<br />Event: '.$res[1].':: ';
		echo $date1.' -- '.$date2;
	}
};
// Same as crawl here
$parse = new Parser();