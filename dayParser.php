<?php
/**
   * WikipediaAPI
   * 
   * 
   * @package    Wikiline
   */
include('lib/session.php');			// Session management
include('lib/wikipedia.api.php');	// Load Wikipedia php API

class DayParser{
	// Wikipedia API
	private $wiki;
	// Placeholder for the extracted events
	private $events;
	// List of entities and their IDs
	private $entities = array();
	// Current date
	private $day;
	private $month;
	
	var	$initial = array('born','created');
	var	$final = array('died','death','closed');
	var $times = array();
	
	public function DayParser(){
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
		
		if(!isset($_SESSION['month'])) $_SESSION['month'] = 1;
		if(!isset($_SESSION['day'])) $_SESSION['day'] = 1;
		
		$this->month = $_SESSION['month'];
		$this->day = $_SESSION['day'];
		
		$url = ucfirst(date("F", mktime(0, 0, 0, $this->month, 10))).'_'.$this->day;
		
		echo '<h1>'.$url.'</h1>';
		
		//* ONLINE
		// Pull article from Wikipedia
		$data = json_decode($this->wiki->request($url,'parse','text'),true);
		
		$toParse = $data['parse']['text']['*'];
		
		//*/
		
		/* OFFLINE */
		//$toParse = file_get_contents('data/march.txt');
		
		// Time counter
		$this->times['download'] = $sess->execTime();
		
		// Parser
		$events = array();
		preg_match_all('@\<li\>(.+)\</li\>@',$toParse,$events);
		
		$events = $events[1];
		
		$total = count($events);
		for($i=0;$i<$total;$i++){
			$p = explode(' – ',$events[$i],2);
			if(count($p)<2) continue;
			
			$year = strip_tags($p[0]);
			$event = strip_tags($p[1]);
			
			$links = array();
			preg_match_all('@\<a\shref="([\w/_\.-]*(\?\S+)?)"@siU',$p[1],$links, PREG_SET_ORDER);
			
			$found = count($links);
			$tags = array();			
			for($a=0;$a<$found;$a++){ $tags[] = substr($links[$a][1],6); }
			
			// Default type
			$type = 0;
			
			// Entity
			$entity = '';
			if(isset($tags[0])) $entity = $tags[0];
			
			// Detect born/dead dates
			$years = array();
			if(preg_match('@\(([b|d])\.\s(-)?([0-9]{1,4})\)@',$event,$years)){
				// Name of person
				$n = explode(',',$event,2);
				if(strlen($n[0])>0) $entity = $n[0];
				
				$type = 3;
				if($years[1]=='b') $type = 4;
			}
			/*
			 *  0	->	Normal event	(Not in any of the categories below)
			 * 	1	->	Start of event	(i.e. Start of a war)
			 * 	2	->	End of event	(It will refer to the inmediate before)
			 * 	3	->	Start of entity (birth)
			 * 	4	->	End of entity	(death)
			 */
			
			// Store in local
			$this->add($event,$this->format($year,$this->month,$this->day),$type,$tags,$entity);
		}
		
		//var_dump($events);
		
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
	function add($name, $date, $type=0, $tags=false, $entity = false){
		$name = trim($name);
		// Invalid dates out:
		if(strlen($date)<1 || strlen($name) <1){
			echo '<br /><span style="color:violet">Invalid</span>';
			return false;
		}
		
		$new = array(
			'name' => $name,
			'entity' => $entity,
			'date' => $date,
			'context' => '',
			'type' => $type,
			'tags' => $tags
		);
		
		$this->events[] = $new;
	}
	
	/**
	 * Returns date parsed in MySQL format
	 * it also validates each component's value
	 */
	 function format($year, $month=0, $day=0, $hour=0, $minutes=0, $seconds=0){
	 	// Validate year
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
		$year = str_pad($year,4,'0',STR_PAD_LEFT);
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
				events(entity, refers, type, date, title, lang)
			VALUES
				(:entity, :refers, :type, :date, :title, :lang)"
		);
		$relationsQuery = $db->db->prepare("
			INSERT INTO
				relations(eventId, entityId)
			VALUES
				(:eventId, :entityId)"
		);
		
		$inserted = 0; // Counter
		$lastID = NULL;
		$context = array(); // References to IDs in context table
		
		for($i=0;$i<$total;$i++){
			$refer = NULL;
			
			// Check if event doesnt exist yet
			$exists = $db->preparedQuery("SELECT id FROM events WHERE title LIKE '?' LIMIT 1",array($this->events[$i]['name']));
			if($db->numRows($exists)>0) continue;
			
			// Begin importing the elements
			$element = array(
				':entity' => $this->entityID($this->events[$i]['entity']),
				':refers' => $refer,
				':type' => $this->events[$i]['type'],
				':date' => $this->events[$i]['date'],
				':title' => $this->events[$i]['name'],
				':lang' => 'en'
			);
			try{
				$eventsQuery->execute($element);
				// Get last inserted ID
				$eventId = $db->lastInsertedId();
				if($eventId>0){
					$inserted++;
					$lastID = $eventId;
					// Add tags if any
					$tags = count($this->events[$i]['tags']);
					if($tags>0){
						// Add the tags
						for($a=0;$a<$tags;$a++){
							$ent = $this->entityID($this->events[$i]['tags'][$a]);
							$relation = array(
								':eventId' => $eventId,
								':entityId' => $ent
							);
							$relationsQuery->execute($relation);
						}
					}
				}else{
					$lastID = NULL;
				}
			}catch(Exception $e){
				if($e->getCode()!=='23000'){
					echo  "\n\tError 1 [{$e->getCode()}]: {$e->getMessage()}";
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
		// -------------------------------------------------------- //
		// Generate next iteration date
		$maxDays = array(31,29,31,30,31,30,31,31,30,31,30,31);
		$this->day++;
		if($this->day > $maxDays[$this->month - 1]){
			$this->day = 1;
			$this->month++;
			if($this->month > 12) die('DONE');
		}
		echo '<a href="dayParser.php">Next</a>: '.ucfirst(date("F", mktime(0, 0, 0, $this->month, 10))).'_'.$this->day;
		// Next iteration
		$_SESSION['day'] = $this->day;
		$_SESSION['month'] = $this->month;
	}
	
	/**
	 * Given an entity it returns its Wikiline ID
	 * @param String $entity The entity url
	 */
	function entityID($entity){
		$entity = strtolower($entity);
		// Check local copy
		if(in_array($entity,$this->entities)) return $this->entities[$entity];
		
		// Check DB
		global $sess;
		$db = $sess->db();
		$entityID = $db->queryUniqueValue('SELECT id FROM `entities` WHERE `name` LIKE \''.addslashes($entity).'\'');
		if(!$entityID){
			// Create new entity
			$db->preparedInsert('entities',array('name'=>$entity));
			$entityID = $db->lastInsertedId();
		}
		// Store in local cache
		$this->entities[$entity] = $entityID;	
		return $entityID;
	}
};
// Same as crawl here
$dayParse = new DayParser();