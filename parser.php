<?php
// Link crawler for Wikipedia
class Parser{
	var $db;
	
	public function db(){
		if($this->db) return $this->db;
		include('lib/db.class.php');
		return $this->db = new DB('timeline','localhost','time','hWwnZbAT6dME9vde');	
	}
	
	// Opens last non crawled link from the parse table, and parses all its links
	// It then stores them in the db for the next crawl
	function parse($text){
		
	}
};
// Same as crawl here
$parse = new Parser();
echo $parse->next();