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
	private $wiki;
	private $events;
	private $entity;
	private $lines;
	
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
		// Get the fist non parsed link
		/*try{
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
		$url = str_replace(' ','_',$next->url);*/
		
		$this->entity = 1;
		$this->month = 3;
		$this->day = 4;
		$url = 'March_4';
		
		/*//* ONLINE
		// Pull article from Wikipedia
		$data = json_decode($this->wiki->request($url,'parse','text'),true);
		
		$toParse = $data['parse']['text']['*'];
		
		$this->times['download'] = $sess->execTime();
		
		//*/
		
		/* OFFLINE */
		$toParse = file_get_contents('data/march.txt');
		
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
		
		$this->events[] = array(
			'name' => $name,
			'entity' => $entity,
			'date' => $date,
			'context' => '',
			'type' => $type,
			'tags' => $tags
		);
		
		var_dump($this->events[]);
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
$dayParse = new DayParser();