<?php
/**
   * WikipediaAPI
   * This should handle all interactions with Wikipedia
   * 
   * 
   * @package    Wikiline
   * @author     Alejandro U. Alvarez <alejandro@urbanoalvarez.es>
   */
class WikipediaAPI{
	
	private $APIurl = 'wikipedia.org/w/api.php';
	
	/**
       * 
       * Makes a request to Wikipedia's API
       *
       * @param string $page  Wikipedia's page name, what's after /wiki/ in the URL
       * @param string $action  The API action we are calling, more info http://en.wikipedia.org/w/api.php
       * @param string $prop  An optional parameter needed by action
       * @param string $lang  Request language, english by default
       * @param string $format  Response format, json by default
       * @return string
       */
	public function request($page, $action, $prop=false, $lang='en', $format='json'){
		// Sends a request and returns the output if any
		$params = 'action='.$action.'&format='.$format.'&page='.$page.'&prop='.$prop.'&uselang='.$lang;
		$url = 'http://'.$lang.'.'.$this->APIurl.'?'.$params;
		
		$ch=curl_init();
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_AUTOREFERER,true);
		curl_setopt($ch,CURLOPT_USERAGENT,'Wikiline Crawler/1.0 (http://nuostudio.com/wikiline)');
		
		ob_start();
			curl_exec($ch);
			curl_close($ch);
		$fetch = ob_get_contents();
		ob_end_clean();
		return $fetch;
	}
};