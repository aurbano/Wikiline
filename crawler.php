<?php
// Link crawler for Wikipedia
class Crawler{
	var $APIurl = 'http://en.wikipedia.org/w/api.php';
	var $db;
	var $special = array('Main','User','Wikipedia','File','MediaWiki','Template','Help','Category','Portal','Book','Education Program','TimedText','Special','Media','Talk','WP','Project','WT','Project talk','Image','Image talk','Main talk','User talk','Wikipedia talk','File talk','MediaWiki talk','Template talk','Help talk','Category talk','Portal talk','Book talk','Education Program talk','TimedText talk','Special talk','Media talk');
	
	public function db(){
		if($this->db) return $this->db;
		include('lib/db.class.php');
		// Working with a local database for now
		return $this->db = new DB('timeline','localhost','time','hWwnZbAT6dME9vde');	
	}
	
	function request($page, $action, $prop, $lang='en', $format='json'){
		// Sends a request and returns the output if any
		$params = 'action='.$action.'&format='.$format.'&page='.$page.'&prop='.$prop.'&uselang='.$lang;
		$url = $this->APIurl.'?'.$params;
		
		$ch=curl_init();
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_AUTOREFERER,true);
		curl_setopt($ch,CURLOPT_USERAGENT,'Wikiline Crawler');
		
		ob_start();
			curl_exec($ch);
			curl_close($ch);
		$fetch = ob_get_contents();
		ob_end_clean();
		return $fetch;
	}
	
	// Opens last non crawled link from the parse table, and parses all its links
	// It then stores them in the db for the next crawl
	function crawl(){
		// Get the fist non parsed link
		$db = $this->db();
		
		try{
			$next = $db->queryUniqueObject('SELECT id, url FROM parse WHERE last IS NULL');
			print_r($next);
			if(!$next) die('No more links to parse');
			$db->preparedQuery('UPDATE parse SET last = FROM_UNIXTIME(?) WHERE id = ? LIMIT 1',array(time(),$next->id));
		}catch(Exception $e){
			die($e->getMessage());	
		}
		
		
		$crawl = str_replace(' ','_',$next->url);
		$data = json_decode($this->request($crawl,'parse','links'),true);
		
		// Just for commodity
		$links = $data['parse']['links'];
		
		$total = count($links);
		if($total < 1) return false;
		// Iterate through elements, and store them
		$stmt = $db->db->prepare('INSERT IGNORE INTO parse(url,added) VALUES(:url,FROM_UNIXTIME(:added))');
		for($i=0;$i<$total;$i++){
			$insert = false;
			// Skip wikipedia or category links
			$parts = explode(':',$links[$i]['*'],2);
			if(in_array($parts[0],$this->special)) continue;
			if(strlen($links[$i]['*'])<1 || $links[$i]['*']=='0') continue;
			print_r($links[$i]['*']);
			$insert[':url'] = $links[$i]['*'];
			$insert[':added'] = time();
			try{
				$stmt->execute($insert);		
			}catch(Exception $e){ die($e->getMessage()); }
		}
	}
};
// This part should be probably changed in the future
$wiki = new Crawler();
// Every time crawl is called, it crawls the next uncrawled article
echo $wiki->crawl();