<?php
/**
 * This file generates JSON responses to AJAX calls made to fetch more events
 * Events will load in chunks, from one date to another date as requested, unless there are too many
 * events, in which case they should be prioritized.
 * 
 * @Author Alejandro U. Alvarez <alejandro@urbanoalvarez.es>
 */
 
// Create the boundaries
$start = $end = 0;

if(isset($_POST['start'])) $start = strtotime($_POST['start']);
if(isset($_POST['end'])) $end = strtotime($_POST['end']);

if($start == 0 && $end == 0){
	// No interval specified
	die(json_encode(array('done'=>false,'msg'=>'No interval specified')));
}

include('lib/session.php');
// Database connection
$db = $sess->db();

// Select events between dates
$events = $db->preparedQuery('SELECT id, entity, type, date, title FROM events WHERE date > FROM_UNIXTIME(?) AND date < FROM_UNIXTIME(?)',array($start,$end));

if($db->numRows($events)<1){
	die(json_encode(array('done'=>false,'msg'=>'No events in specified interval ('.date('Y',$start).'-'.date('Y',$end).')')));
}

// Prepare return array
$elements = array('done'=>true, 'msg'=>'('.date('Y',$start).'-'.date('Y',$end).')', 'items');

$i=0;
while($d = $db->fetchNextObject($events)){
	// Prepare each of the items
	$elements['items'][$i]['id'] = $d->id;
	$elements['items'][$i]['entity'] = $d->entity;
	$elements['items'][$i]['type'] = $d->type;
	$elements['items'][$i]['date'] = $d->date;
	$elements['items'][$i]['title'] = $d->title;
	$i++;
}

echo json_encode($elements);
