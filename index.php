<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Wikiline - Global timeline</title>
<link rel="stylesheet" type="text/css" media="all" href="layout.css" />
<link rel="stylesheet" type="text/css" media="all" href="timeline.css" />
</head>

<body>
<header>
	<h1>Wikiline</h1>
</header>
<div id="timeline-viewport">
	<div id="timeline">
		<!--<div class="item entertainment" style="left:100px;">
			<div class="info up">
				<div class="content">
					<h3>A very important event</h3>
					<article>Entity</article>
				</div>
			</div>
		</div>
		<div class="item politics" style="left:300px;">
			<div class="info down">
				<div class="content">
					<h3>Event 2</h3>
					<article>Entity</article>
				</div>
			</div>
		</div>-->
		<hr class="line" />
	</div>
</div>
<footer>Wikiline - Global timeline</footer>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
<script src="js/timeline.js"></script>
<script type="text/javascript">
$(document).ready(function(){
	Timeline.main();
});
</script>
</body>
</html>