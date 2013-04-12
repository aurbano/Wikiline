<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Wikiline - Global timeline</title>
</head>

<body>
<div id="timeline-viewport">
	<div id="events"></div>
	<div id="timeline"></div>
</div>
<link rel="stylesheet" type="text/css" media="all" href="layout.css" />
<link rel="stylesheet" type="text/css" media="all" href="timeline.css" />
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
<script src="js/timeline.js"></script>
<script type="text/javascript">
$(document).ready(function(){
	Timeline.main();
	$(window).resize(function(){
		Timeline.resize();
	});
});
</script>
</body>
</html>