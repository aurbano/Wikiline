/**
 * Timeline class, for interacting and generating the Timeline
 * the real timeline should be a group of views, each spanning some years
 * to allow horizontal scrolling without drawing too many elements
 *
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
	events : new Array(),
	/**
	 * Rows array, holds the offset of each row
	 * if the website resizes vertically, for now, ignore
	 */
	rows : new Array(),
	rowHeight : 50,
	/**
	 * Stores DOM references, trying to improve rendering speed
	 */
	refs : new Array(),
	/**
	 * Zoom level, currently it will be distance in px between ticks
	 */
	zoom : 100,
	/**
	 * Draw resolution 
	 */
	resolution : 10,
	/**
	 * Kind of an overflow method for jQuery selector
	 * checks local cache and calls jquery if not found
	 */
	$ : function(selector){
		if(Timeline.refs[selector] !== undefined) return Timeline.refs[selector];
		Timeline.refs[selector] = $(selector);
		return Timeline.refs[selector];
	},
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
			Timeline.$('#timeline').append('<div class="tick" style="left:'+(i*space)+'px"><span>'+(Timeline.interval.start+Timeline.resolution*i)+'</span></div>');
		}
	},
	/**
	 * Determines how many rows to use, and initializes the array
	 */
	createRows : function(){
		// Should be called once everything has been resized
		var height = Timeline.$('#events').height()-10,
			numRows = Math.floor(height/Timeline.rowHeight);		
		for(i=0;i<numRows;i++){
			Timeline.rows[i] = 0;
		}
		console.log('Generated '+i+' rows for events');
	},
	/**
	 * Main function, should be called on document ready 
	 */
	main : function(){
		//Build references
		Timeline.refs['#timeline-viewport'] = $('#timeline-viewport');
		Timeline.refs['#timeline'] = $('#timeline');
		Timeline.refs['#events'] = $('#events');
		
		// Initialize timeline
		Timeline.resize();
		Timeline.drawTicks();
		Timeline.createRows();
		// Display data
		Timeline.loadEvents();
	},
	/**
	 * Draws an event on the timeline 
	 * @param Object An object with all event properties
	 */
	drawEvent : function(evt){
		// Notify
		console.log('Drawing ['+Timeline.dateToString(evt.date)+'] '+evt.title);
		// Test boundaries (just in case, although probably impossible to get wrong)
		if(evt.date.y < Timeline.interval.start || evt.date.y > Timeline.interval.end){
			console.log('Event '+evt.title+' out of bounds');
			return;
		}

		/*
		 * Calculating the offset: First the date is converted to a decimal number
		 * in years, where the decimals represent months, days... etc
		 * We will then try to find the row that best suits this offset, although it could
		 * vary.
		 */
		var year = Timeline.dateToDigit(evt.date) - Timeline.interval.start,
			max = Timeline.interval.end - Timeline.interval.start,
			offset = year * $('#events').width() / max,
			nearestRow = 0,
			nearestDiff = 1000;
		console.log('	Offset = '+offset);

		// Calculate nearest row
		for(i=0;i<Timeline.rows.length;i++){
			if(Timeline.rows[i] < offset){
				nearestRow = i;
				break;
			}
			if(Math.abs(offset - Timeline.rows[i]) < nearestDiff){
				nearestRow = i;
				nearestDiff = Math.abs(Timeline.rows[i] - offset);
			}
		}
		
		// Move right if necessary
		if(Timeline.rows[nearestRow] < offset) Timeline.rows[nearestRow] = offset;
		
		// Now draw in that row
		
		var id = evt.title.replace(' ','_');
		
		// Now draw the element
		var obj = $('<a href="#" class="event row'+nearestRow+'" style="top:'+(nearestRow*Timeline.rowHeight)+'px; left:'+(Timeline.rows[nearestRow])+'px; background:'+Timeline.randColor()+'" id="'+id+'" title="'+evt.title+'"><h3>'+evt.entity+'</h3><article>'+evt.title+'</article><time>'+Timeline.dateToString(evt.date)+'</time></a>').appendTo('#events');
		
		// Update that row's offset
		Timeline.rows[nearestRow] = obj.offset().left + obj.width() + 5;
				
	},
	/**
	 * Converts a date object to a digit
	 * @param Object Expects a date object
	 * @return int Date in years 
	 */
	dateToDigit : function(date){
		// For now everything below minutes is trivial
		return parseInt(date.y) + parseInt(date.m)/12 + parseInt(date.d)/372 + parseInt(date.h)/8928;
	},
	/**
	 * Converts a date object to a string for representation
	 * @param Object Expects a date object
	 * @return string Date in string format
	 */
	dateToString : function(date){
		return date.d + '/' + date.m + '/' + date.y;
	},
	/**
	 * Load events from the database 
	 */
	loadEvents : function(){
		$.post("loadEvents.php", { start : Timeline.interval.start, end : Timeline.interval.end  },
			function(data){
				console.log(data)
				if(data.msg.length > 0) alert(data.msg);
				
				if(!data.done) return;
				
				if(data.done == true){
					var distance = 0;
					$.each(data.items, function(i, val) {
						// Store event
						Timeline.events.push(val);
						// Draw
						Timeline.drawEvent(val);
					});
				}
				//if(callback !== undefined) callback.call();
			},
		"json");
	},
	/**
	 * Returns a random color
	 */
	randColor : function(){
		return '#'+Math.round(0xffffff * Math.random()).toString(16);
	},
	/**
	 * Maintain viewport in full size
	 */
	resize : function(){
		Timeline.$('#timeline-viewport').width($(window).width());
		Timeline.$('#timeline-viewport').height($(window).height());
		
		var ticks = Math.ceil((Timeline.interval.end - Timeline.interval.start)/Timeline.resolution);
		
		Timeline.$('#timeline').width(Timeline.zoom * ticks);
		Timeline.$('#events').width(Timeline.zoom * ticks);
		
		Timeline.$('#events').height($(window).height() - Timeline.$('#timeline').height() - 5);
	}
};
