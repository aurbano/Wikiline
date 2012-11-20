<?php
/**
   * WikipediaAPI
   * 
   * 
   * @package    Wikiline
   */
include('lib/session.php');			// Session management
include('lib/wikipedia.api.php');	// Load Wikipedia php API

class Crawler{
	/** Wikipedia API handle
	  */
	var $wiki;
	/** Special links to be excluded from parsing.
	  */
	var $special = array('Main','User','Wikipedia','File','MediaWiki','Template','Help','Category','Portal','Book','Education Program','TimedText','Special','Media','Talk','WP','Project','WT','Project talk','Image','Image talk','Main talk','User talk','Wikipedia talk','File talk','MediaWiki talk','Template talk','Help talk','Category talk','Portal talk','Book talk','Education Program talk','TimedText talk','Special talk','Media talk');
	
	/**
       * 
       * Constructor, it automatically starts crawling
       *
       * @return DB
       */
	public function Crawler(){
		// Start the Wikipedia API
		$this->wiki = new WikipediaAPI();
		
		// Start crawling next article
		return $this->crawl();
	}
	
	/**
       * 
       * Opens last non crawled link from the parse table, and parses all its links
	   * It then stores them in the db for the next crawl
       *
       * @return boolean
       */
	function crawl(){
		global $sess;
		// Get the fist non parsed link
		$db = $sess->db();
		
		try{
			$next = $db->queryUniqueObject('SELECT id, url FROM parse WHERE last IS NULL');
			print_r($next);
			if(!$next) die('No more links to parse');
			$db->preparedQuery('UPDATE parse SET last = ? WHERE id = ? LIMIT 1',array($db->date(),$next->id));
		}catch(Exception $e){
			die($e->getMessage());	
		}
		
		// Make sure the crawl url is properly formatted
		$url = str_replace(' ','_',$next->url);
		$data = json_decode($this->wiki->request($url,'parse','links'),true);
		
		// Just for commodity
		$links = $data['parse']['links'];
		
		$total = count($links);
		if($total < 1) die('No links :(');
		// Iterate through elements, and store them
		$stmt = $db->db->prepare('INSERT IGNORE INTO parse(url,added) VALUES(:url,:added)');
		for($i=0;$i<$total;$i++){
			$insert = false;
			// Skip wikipedia or category links
			$parts = explode(':',$links[$i]['*'],2);
			if(in_array($parts[0],$this->special)) continue;
			if(strlen($links[$i]['*'])<1 || $links[$i]['*']=='0') continue;
			$insert[':url'] = $links[$i]['*'];
			$insert[':added'] = $db->date();
			try{
				// The or die is just in case something goes wrong (Very rare)
				$stmt->execute($insert) or die(print_r($stmt->errorInfo(), true));
			}catch(Exception $e){ die($e->getMessage()); }
		}
		return true;
	}
};
// This part should be probably changed in the future
$wiki = new Crawler();