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
	
	var	$initial = array('born','created');
	var	$final = array('died','death','closed');
	
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
		try{
			// Get next id and url, and update the parsed date
			$next = $db->queryUniqueObject('SELECT id, url FROM parse WHERE parsed IS NULL');
			print_r($next);
			if(!$next) die('No links to parse');
			//$db->preparedQuery('UPDATE parse SET parsed = FROM_UNIXTIME(?) WHERE id = ? LIMIT 1',array(time(),$next->id));
		}catch(Exception $e){
			die($e->getMessage());	
		}
		$entity = $next->id;
		// Make sure the crawl url is properly formatted
		$url = str_replace(' ','_',$next->url);
		
		///* ONLINE
		// Pull article from Wikipedia
		$data = json_decode($this->wiki->request($url,'parse','text'),true);
		
		$toParse = strip_tags($data['parse']['text']['*']);
		//*/
		
		/* OFFLINE */
		//$toParse = file_get_contents('data/joseph.txt');
		
		$lines = explode("\n",$toParse);
		for($i=0;$i<count($lines);$i++){
			if(strlen(trim($lines[$i]))<1) continue;
			if(trim($lines[$i])=='[edit] References') break;
			echo '<hr />'.$lines[$i];
			
			$res = array();
			// Get dates in standard format, assuming that the previous line is the title
			preg_match('@\(([0-9]{2,4})-([0-9]{1,2})-([0-9]{1,2})\)@',$lines[$i],$res);
			if(isset($lines[$i-1]) && count($res)>0 && strlen($lines[$i-1])<255){
				// Born and dead are special types
				
				$type = 0;
				if(in_array(strtolower(trim($lines[$i-1])),$this->initial)) $type = 3;
				elseif(in_array(strtolower(trim($lines[$i-1])),$this->final)) $type = 4;
				$this->add($lines[$i-1],$this->format($res[1],$res[2],$res[3]),$i,$type);
				continue;
			}
			// Sentence divider
			preg_match_all('@(?:\.|,|:|;)?([^.,:;]+)(?:\.|,|:|;)?@',$lines[$i],$res);
			
			$res = $res[1];
			
			//var_dump($res);
			
			for($a=0;$a<count($res);$a++){
				echo '<br /><span style="color:blue">['.$res[$a].']</span>';
				// New matches container
				$ev = array();
				// Text until Month DD 
				preg_match('@(.+) (?:from) (january|february|march|april|may|june|july|augost|september|october|november|december|[0-9]{1,2})(?: )?([0-9]{1,2})?@i',$res[$a],$ev);
				if(count($ev)>0 && isset($res[$a+1]) && preg_match('@(?:[0-9]{2,4})@',$res[$a+1])){
					$month = 0;
					if(isset($ev[2]) && $ev[2]) $month = date('m',strtotime($ev[2]));
					
					if(!isset($ev[3])) $ev[3] = 0;
					
					$name = $ev[1];
					echo __LINE__;
					$this->add($ev[1],$this->format($res[$a+1],$month,$ev[3]),$i,1);
					
					var_dump($ev);
					
					
					// Check for end
					if(preg_match('@(.+)(?:to) (january|february|march|april|may|june|july|augost|september|october|november|december|[0-9]{1,2})(?: )?([0-9]{1,2})?@i',$res[$a+2],$ev)){
						if(count($ev)>0 && isset($res[$a+3]) && preg_match('@(?:[0-9]{2,4})@',$res[$a+3])){
							$month = 0;
							if(isset($ev[2]) && $ev[2]) $month = date('m',strtotime($ev[2]));
							if(!isset($ev[3])) $ev[3] = 0;
							echo __LINE__;
							$this->add($name,$this->format($res[$a+3],$month,$ev[3]),$i,2);
							//$a = $a+3;
							var_dump($ev);
						}else{
							echo '[End not found]';
							//$a++;
						}
					}//else $a++;
					continue;
				}
				
				// Text until from YYYY to YYYY (Should be run on sentences only)
				preg_match('@(.+) from (january|february|march|april|may|june|july|augost|september|october|november|december)?( [0-9]{1,2})?(?:, )?([0-9]{2,4})(?:,)? to (january|february|march|april|may|june|july|augost|september|october|november|december)?( [0-9]{1,2})?(?:, )?([0-9]{2,4})@i',$res[$a],$ev);
				if(count($ev)>0){
					$month = 0;
					if($ev[3]) $month = date('m',strtotime($ev[3]));
					
					echo __LINE__;
					$this->add($ev[1],$this->format($ev[4],$month,$ev[2]),$i,1);
					
					$month = 0;
					if($ev[6]) $month = date('m',strtotime($ev[6]));
					echo __LINE__;
					$this->add($ev[1],$this->format($ev[7],$month,$ev[5]),$i,2);
					var_dump($ev);
					continue;
				}
				
				preg_match('@(.+)(?:in|on)(january|february|march|april|may|june|july|augost|september|october|november|december|[0-9]{1,2})? ([0-9]{1,4})@i',$res[$a],$ev);
				if(count($ev)>0){
					
					$month = 0;
					if($ev[2]) $month = date('m',strtotime($ev[2]));
					
					$day = 0;
					$year = $ev[3];
					if($ev[3]<32 && preg_match('@(?:[0-9]{2,4})@',$res[$a+1])){
						$year = $res[$a+1];
						$day = $ev[3];
					}
					
					echo __LINE__;
					if(strlen(trim($ev[1]))<1 && isset($res[$a+1])) $ev[1] = $res[$a+1];
					$this->add($ev[1],$this->format($year,$month,$day),$i);
					
					var_dump($ev);
					continue;
				}
				
				preg_match('@(.+)(?:in|on) (january|february|march|april|may|june|july|augost|september|october|november|december|[0-9]{1,2})? ([0-9]{1,4})@i',$res[$a],$ev);
				if(count($ev)>0){
					
					$month = 0;
					if($ev[2]) $month = date('m',strtotime($ev[2]));
					
					$day = 0;
					$year = $ev[3];
					if($ev[3]<32 && preg_match('@(?:[0-9]{2,4})@',$res[$a+1])){
						$year = $res[$a+1];
						$day = $ev[3];
					}
					
					echo __LINE__;
					if(strlen(trim($ev[1]))<1 && isset($res[$a+1])) $ev[1] = $res[$a+1];
					$this->add($ev[1],$this->format($year,$month,$day),$i);
					
					var_dump($ev);
					continue;
				}
				
			}
			echo '<br />';
		}
		
		echo '<hr /><h3>Parsed data:</h3>';
		
		echo 'Entity: '.$entity.' ('.$url.')';

		var_dump($this->events);
		
		echo '<hr />Exec time: '.$sess->execTime();
		
		die();
	}
	
	/**
	 * Adds an event to the internal list. Context is the paragraph from where it was extracted
	 * it should be used for human rectification of the dates and data associated.
	 * Types:
	 * 	0	->	Normal event	(Not in any of the categories below)
	 * 	1	->	Start of event	(i.e. Start of a war)
	 * 	2	->	End of event	(It will refer to the inmediate before)
	 * 	3	->	Start of entity (birth)
	 * 	4	->	End of entity	(death)
	 */
	function add($name, $date, $context, $type=0){
		$name = trim($name);
		// Invalid dates out:
		if(strlen($date)<1 || strlen($name) <1){
			echo '<br /><span style="color:violet">Invalid</span>';
			return false;
		}
		// Unwanted first words
		$unwanted = array('he','she','it','and','but','or');
		$words = explode(' ',$name);
		$i=0;
		$length=0;
		while(isset($words[$i]) && in_array(strtolower($words[$i]),$unwanted)){
			$name = substr($name,strlen($words[$i])+1);
			$i++;
		}
		//$name = substr($name,$length);
		echo '<br /><span style="color:red">{Added: <strong>'.$name.'</strong> in '.$date.'}</span><br />';
		$this->events[] = array(
			'name' => $name,
			'date' => $date,
			'context' => $context,
			'type' => $type
		);
	}
	
	/**
	 * Returns date parsed in MySQL format
	 * it also validates each component's value
	 */
	 function format($year, $month=0, $day=0, $hour=0, $minutes=0, $seconds=0){
	 	// Validate year
	 	echo '<br /><span style="color:green">[Received: '."$year-$month-$day $hour:$minutes:$seconds".']</span>';
	 	$year = preg_replace('@\D@', '', $year);
		if($year > 3000) return false;
		if($month<0) $month = 0;
		if($day<0) $day = 0;
		if($hour<0) $hour = 0;
		if($minutes<0) $minutes = 0;
		if($seconds<0) $seconds = 0;
		if($hour>23) $hour = 23;
		if($minutes>59) $month = 59;
		if($seconds>59) $seconds = 59;
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