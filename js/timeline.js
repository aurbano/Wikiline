/**
 * Timeline class, for interacting and generating the Timeline
 * @author Alejandro U. Alvarez <alejandro@urbanoalvarez.es> 
 */

var Timeline = {
	/**
	 * Date interval 
	 */
	interval : {
		start : 1800,
		end : 2000
	},
	/**
	 * Events buffer 
	 */
	events : {},
	/**
	 * Draw resolution 
	 */
	resolution : 20,
	/**
	 * Display ticks on timeline 
	 */
	drawTicks : function(){
		// Remove old ticks
		$('#timeline .tick').remove();
		// Determine tick number
		var ticks = Math.ceil((Timeline.interval.end - Timeline.interval.start)/Timeline.resolution),
		// Determine tick separation
			space = Math.floor($('#timeline').width()/ticks);
		for(var i=0;i<ticks;i++){
			$('#timeline').append('<div class="tick" style="left:'+(i*space)+'px"><span>'+(Timeline.interval.start+Timeline.resolution*i)+'</span></div>');
		}
	},
	/**
	 * Main function, should be called on document ready 
	 */
	main : function(){
		Timeline.drawTicks();
		Timeline.loadEvents();
		Timeline.drawEvent('');
	},
	/**
	 * Draws an event on the timeline 
	 * @param Object An object with all event properties
	 */
	drawEvent : function(evt){
		// Sample event
		evt = {
			title : 'Born',
			entity : 'Thomas Edison',
			type : 'politics',
			date : {
				year : 1847,
				month : 02,
				day : 11,
				hour : 00,
				minute : 00,
				seconds : 00
			}
		};
		// Test boundaries
		if(evt.date.year < Timeline.interval.start || evt.date.year > Timeline.interval.end){
			console.log('Event '+evt.title+' out of bounds');
			return;
		}
		/*
		 * Calculating the offset: First the date is converted to a decimal number
		 * in years, where the decimals represent months, days... etc
		 */
		var year = Timeline.dateToDigit(evt.date) - Timeline.interval.start,
			max = Timeline.interval.end - Timeline.interval.start,
			offset = year * $('#timeline').width() / max;
		
		// Now draw the element
		$('#timeline').append('<div class="item '+evt.type+'" style="left:'+offset+'px;"><div class="info up"><div class="content"><h3>'+evt.title+'</h3><article>'+evt.entity+'<time>'+Timeline.dateToString(evt.date)+'</time></article></div></div></div>');
	},
	/**
	 * Converts a date object to a digit
	 * @param Object Expects a date object
	 * @return int Date in years 
	 */
	dateToDigit : function(date){
		// For now everything below minutes is trivial
		return date.year + date.month/12 + date.day/372 + date.hour/8928;
	},
	/**
	 * Converts a date object to a string for representation
	 * @param Object Expects a date object
	 * @return string Date in string format
	 */
	dateToString : function(date){
		return date.day + '/' + date.month + '/' + date.year;
	},
	/**
	 * Load events from the database 
	 */
	loadEvents : function(){
		var day = '01',
			month = '01';
		$.post("loadEvents.php", { start : day+'/'+month+'/'+Timeline.interval.start, end : day+'/'+month+'/'+Timeline.interval.end  },
		  function(data){
		  		console.log(data);
		  }, "json");
	}
};
