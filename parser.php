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
	private $events;
	
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
		$db = $sess->db();
		// Get the fist non parsed link
		/*
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
		
		/* ONLINE
		$url = 'Joseph_Clay_Stiles_Blackburn';
		// Pull article from Wikipedia
		$data = json_decode($this->wiki->request($url,'parse','text'),true);
		
		$toParse = strip_tags($data['parse']['text']['*']);
		*/
		
		/* OFFLINE */
		$toParse = file_get_contents('data/joseph.txt');
		
		$lines = explode("\n",$toParse);
		for($i=0;$i<count($lines);$i++){
			if(strlen(trim($lines[$i]))<1) continue;
			if(trim($lines[$i])=='[edit] References') break;
			echo '<hr />'.$lines[$i];
			// I would like to get date ranges
			// as well as unique dates.
			// Ranges tend to be year - year, or full date - full date
			// unique dates tend to be (YYYY-MM-DD) although it might vary
			$res = array();
			preg_match('@\(([0-9]{2,4})-([0-9]{1,2})-([0-9]{1,2})\)@',$lines[$i],$res);
			if(isset($lines[$i-1]) && count($res)>0 && strlen($lines[$i-1])<255){
				$this->add($lines[$i-1],$this->format($res[1],$res[2],$res[3]));
				continue;
			}
			// Kind of divides sentences 
			preg_match_all('@(?:\.|,|:|;)?([^.,:;]+)(?:\.|,|:|;)?@',$lines[$i],$res);
			
			$res = $res[1];
			
			var_dump($res);
			
			for($a=0;$a<count($res);$a++){
				echo '<br /><span style="color:blue">['.$res[$a].']</span>';
				// New matches container
				$ev = array();
				// Text until from YYYY to YYYY (Should be run on sentences only)
				preg_match('@(.+) from (january|february|march|april|may|june|july|augost|september|october|november|december)?( [0-9]{1,2})?(?:, )?([0-9]{2,4})(?:,)? to (january|february|march|april|may|june|july|augost|september|october|november|december)?( [0-9]{1,2})?(?:, )?([0-9]{2,4})@',$res[$a],$ev);
				if(count($ev)>0){
					$month = 0;
					if($ev[3]) $month = date('m',strtotime($ev[3]));
					echo '<br /><span style="color:green">['.$ev[0].']</span>';
					
					$this->add('Start: '.$ev[1],$this->format($ev[4],$ev[3],$ev[2]));
					
					$month = 0;
					if($ev[6]) $month = date('m',strtotime($ev[6]));
					
					$this->add('End: '.$ev[1],$this->format($ev[7],$ev[6],$ev[5]));
					var_dump($ev);
					continue;
				}
				
				preg_match('@(.+) in ([0-9]{2,4})@',$res[$a],$ev);
				if(count($ev)>0){
					echo '<br /><span style="color:green">['.$ev[0].']</span>';
					
					$this->add($ev[1],$this->format($ev[2]));
					
					var_dump($ev);
					continue;
				}
				
			}
			// Kind of gets text from punctuation to year.
			//preg_match('@(?:\.|,|;|and)(.{1,100})([0-9]{2,4})@',$lines[$i],$res);
			echo '<br />';
		}
		
		echo '<hr /><h3>Parsed data:</h3>';

		var_dump($this->events);
		
		echo '<hr />Exec time: '.$sess->execTime();
		
		die();
	}
	
	/**
	 * Adds an event to the internal list
	 */
	function add($name, $date){
		// Remove he/she/it from start
		$name = trim($name);
		if(substr(strtolower($name),0,3) == 'he ' || substr(strtolower($name),0,3) == 'it') $name = substr($name, 3);
		if(substr(strtolower($name),0,4) == 'she ') $name = substr($name, 4);
		echo '<br /><span style="color:red">{Added: '.$name.' in '.$date.'}</span><br />';
		$this->events[] = array(
			'name' => $name,
			'date' => $date
		);
	}
	
	/**
	 * Returns date parsed in MySQL format
	 */
	 function format($year, $month=0, $day=0, $hour=0, $minutes=0, $seconds=0){
	 	// Ensure trailing 0
	 	$month = str_pad($month,2,'0',STR_PAD_LEFT);
		$day = str_pad($day,2,'0',STR_PAD_LEFT);
		$hour = str_pad($hour,2,'0',STR_PAD_LEFT);
		$minutes = str_pad($minutes,2,'0',STR_PAD_LEFT);
		$seconds = str_pad($seconds,2,'0',STR_PAD_LEFT);
	 	// Y-m-d H:i:s
	 	return "$year-$month-$day $hour:$minutes:$seconds";
	 }
};
// Same as crawl here
$parse = new Parser();