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
	 * Minimum offset between drawn events
	 */
	combine : 140,
	/**
	 * Events buffer 
	 */
	events : new Array(),
	/**
	 * Draw resolution 
	 */
	resolution : 10,
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
		Timeline.resize();
		Timeline.drawTicks();
		Timeline.loadEvents();
	},
	lastEvent : {
		offset : 0,
		obj : null
	},	
	/**
	 * Draws an event on the timeline 
	 * @param Object An object with all event properties
	 */
	drawEvent : function(evt){
		console.log('Drawing event:');
		console.log(evt);
		// Test boundaries
		if(evt.date.y < Timeline.interval.start || evt.date.y > Timeline.interval.end){
			console.log('Event '+evt.title+' out of bounds');
			return;
		}

		/*
		 * Calculating the offset: First the date is converted to a decimal number
		 * in years, where the decimals represent months, days... etc
		 */
		var year = Timeline.dateToDigit(evt.date) - Timeline.interval.start,
			max = Timeline.interval.end - Timeline.interval.start,
			offset = year * $('#events').width() / max;
		
		// Decide whether create a new item or mix with previous
		if(Timeline.lastEvent.obj !== null){
			// Now check offset
			if(offset - Timeline.lastEvent.offset < Timeline.combine){
				console.log('Combining with last, offset = '+Timeline.lastEvent.offset);
				// Combine events
				Timeline.lastEvent.obj.append(Timeline.createEvent(evt));
				return;
			}
		}
		
		var height = 500 + Math.random()*300;
		
		// Now draw the element
		var obj = $('<div class="item '+evt.type+'" style="left:'+offset+'px;"><div class="info up" style="height:'+height+'px; top:-'+height+'px"><div class="content">'+Timeline.createEvent(evt)+'</div></div></div>').appendTo('#events').find('.content');
		
		// Store data for next drawing
		Timeline.lastEvent.offset = offset;
		Timeline.lastEvent.obj = obj;
		
		return offset;
	},
	/**
	 *
	 */
	createEvent : function(evt){
		var id = evt.title.replace(' ','_');
		return '<div class="event" id="'+id+'"><h3>'+evt.entity+'</h3><article>'+evt.title+'</article><time>'+Timeline.dateToString(evt.date)+'</time></div>';
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
	 * Maintain viewport in full size
	 */
	resize : function(){
		$('#timeline-viewport').width($(window).width());
		$('#timeline-viewport').height($(window).height());
		
		$('#events').height($(window).height() - $('#timeline').height() - 5);
	}
};
