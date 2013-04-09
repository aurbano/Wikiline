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
	private $entity;
	private $lines;
	
	var	$initial = array('born','created');
	var	$final = array('died','death','closed');
	var $times = array();
	
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
			$next = $db->queryUniqueObject('SELECT id, url FROM parse WHERE last IS NULL');
			print_r($next);
			if(!$next) die('No links to parse');
			//$db->preparedQuery('UPDATE parse SET parsed = FROM_UNIXTIME(?) WHERE id = ? LIMIT 1',array(time(),$next->id));
		}catch(Exception $e){
			die($e->getMessage());	
		}
		$this->entity = $next->id;
		// Make sure the crawl url is properly formatted
		$url = str_replace(' ','_',$next->url);
		
		///* ONLINE
		// Pull article from Wikipedia
		$data = json_decode($this->wiki->request($url,'parse','text'),true);
		
		$this->times['download'] = $sess->execTime();
		
		$toParse = strip_tags($data['parse']['text']['*']);
		//*/
		
		/* OFFLINE */
		//$toParse = file_get_contents('data/joseph.txt');
		
		$this->lines = explode("\n",$toParse);
		for($i=0;$i<count($this->lines);$i++){
			if(strlen(trim($this->lines[$i]))<1) continue;
			if(trim($this->lines[$i])=='[edit] References') break;
			echo '<hr />'.$this->lines[$i];
			
			$res = array();
			// Get dates in standard format, assuming that the previous line is the title
			preg_match('@\(([0-9]{2,4})-([0-9]{1,2})-([0-9]{1,2})\)@',$this->lines[$i],$res);
			if(isset($this->lines[$i-1]) && count($res)>0 && strlen($this->lines[$i-1])<255){
				// Born and dead are special types
				
				$type = 0;
				if(in_array(strtolower(trim($this->lines[$i-1])),$this->initial)) $type = 3;
				elseif(in_array(strtolower(trim($this->lines[$i-1])),$this->final)) $type = 4;
				$this->add($this->lines[$i-1],$this->format($res[1],$res[2],$res[3]),$i,$type);
				continue;
			}
			// Sentence divider
			preg_match_all('@(?:\.|,|:|;)?([^.,:;]+)(?:\.|,|:|;)?@',$this->lines[$i],$res);
			
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
		
		echo 'Entity: '.$this->entity.' ('.$url.')';

		var_dump($this->events);
		
		$this->times['process'] = $sess->execTime() - $this->times['download'];
		
		$this->times['import'] = $sess->execTime();
		
		echo '<hr /><h3>Begin import</h3>';
		$this->import();
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
	 
	 /**
	  * Imports all parsed events
	  */
	 function import(){
	 	global $sess;
	 	$total = count($this->events);
	 	if($total<1) return false;
		echo '<h2>Importing '.$total.' elements</h2>';
		$db = $sess->db();
		// Prepare queries
		$eventsQuery = $db->db->prepare("
			INSERT INTO
				events(entity, refers, type, date, title, context, lang)
			VALUES
				(:entity, :refers, :type, :date, :title, :context, :lang)"
		);
		// Counter
		$inserted = 0;
		$lastID = NULL;
		$context = array(); // References to IDs in context table
		for($i=0;$i<$total;$i++){
			$refer = NULL;
			// If the event is the end of something, refer to it
			if($this->events[$i]['type'] == 2 && $lastID > 0){
				$refer = $lastID; // Refers to the last inserted ID. Start-End events should be always in a row.
			}
			// Check the context
			if(!isset($context[$this->events[$i]['context']])){
				// We need to add a new context entry
				$context[$this->events[$i]['context']] = $this->addContext($this->lines[$this->events[$i]['context']]);
			}
			// Begin importing the elements
			$element = array(
				':entity' => $this->entity,
				':refers' => $refer,
				':type' => $this->events[$i]['type'],
				':date' => $this->events[$i]['date'],
				':title' => $this->events[$i]['name'],
				':context' => $context[$this->events[$i]['context']],
				':lang' => 'en'
			);
			try{
				$eventsQuery->execute($element);
				// Get last inserted ID
				if($db->lastInsertedId()>0){
					$lastID = $db->lastInsertedId();
					$inserted++;
				}else{
					$lastID = NULL;
				}
			}catch(Exception $e){
				if($e->getCode()!=='23000'){
					$log .= "\n\tError 1 [{$e->getCode()}]: {$e->getMessage()}";
				}
				echo $log;
				die();
			}
		}
		echo '<br />Imported '.$inserted.' elements in a total of '.$sess->execTime();
		echo '<hr /><h3>Profiling</h3>';
		$this->times['import'] = $sess->execTime() - $this->times['import'];
		$totalTime = $sess->execTime();
		echo "<pre>
				Total:			$totalTime
				Downloading:		{$this->times['process']}
			  	Parsing:		{$this->times['download']}
			  	Importing:		{$this->times['import']}</pre>";
	}

	/**
	 * Stores a context paragraph in the database
	 * and returns it's id
	 */
	function addContext($text){
		global $sess;
		$db = $sess->db();
		return $db->preparedInsert('context', array('text'=>$text));
	}
};
// Same as crawl here
$parse = new Parser();