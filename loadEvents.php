<?php
/**
 * This file generates JSON responses to AJAX calls made to fetch more events
 * Events will load in chunks, from one date to another date as requested, unless there are too many
 * events, in which case they should be prioritized.
 * 
 * @Author Alejandro U. Alvarez <alejandro@urbanoalvarez.es>
 */

// Types
$types = array('politics','history','science','sports','entertainment');
 
// Create the boundaries
$start = $end = 0;

if(isset($_POST['start'])) $start = $_POST['start'];
if(isset($_POST['end'])) $end = $_POST['end'];

//die(json_encode(array('start'=>date('Y',$start),'end'=>date('Y',$end))));

if($start == 0 && $end == 0){
	// No interval specified
	die(json_encode(array('done'=>false,'msg'=>'No interval specified')));
}

include('lib/session.php');
// Database connection
$db = $sess->db();

// Select events between dates
$events = $db->preparedQuery('
		SELECT
			events.id, entities.name AS entity, type, date, title
		FROM
			events
				LEFT OUTER JOIN entities ON events.entity = entities.id
		WHERE
			YEAR(date) > ? AND YEAR(date) < ? LIMIT 50',
	array($start,$end));

if($db->numRows($events)<1){
	die(json_encode(array('done'=>false,'msg'=>'No events in specified interval ('.$start.'-'.$end).')'));
}

// Prepare return array
$elements = array('done'=>true, 'msg'=>'', 'items');

$i=0;
while($d = $db->fetchNextObject($events)){
	// Date object
	$date = new DateTime($d->date);
	// Prepare each of the items
	$elements['items'][$i]['id'] = $d->id;
	$elements['items'][$i]['entity'] = ucwords(str_replace('_',' ',$d->entity));
	$elements['items'][$i]['type'] = $types[$d->type];
	$elements['items'][$i]['title'] = $d->title;
	// Return the date
	$elements['items'][$i]['date']['y'] = $date->format('Y');
	$elements['items'][$i]['date']['m'] = $date->format('m');
	$elements['items'][$i]['date']['d'] = $date->format('d');
	$elements['items'][$i]['date']['h'] = $date->format('h');
	$elements['items'][$i]['date']['min'] = $date->format('i');
	$elements['items'][$i]['date']['s'] = $date->format('s');
	
	$i++;
}

echo json_encode($elements);
