Wikiline
========

A global timeline created from Wikipedia

<h3>Executing</h3>
WikiLine requires MySQL, first create a new database called 'timeline'. Assign a new user called 'time' to it, with password 'hWwnZbAT6dME9vde' (For development)

On the release server everything will run automatically from cron jobs, but on development you have to run the crawler and parser manually. To start it up you must manually insert a Wikipedia entry on the database (Only the article ID, for example _Thomas_edison_), then run _crawler.php_

<h3>Workflow</h3>
All new work should be done on a new branch, once it works fine we send in a pull request. For automatic merges just go on, if the merge cannot be resolved automatically go in shell mode to fix the merge manually.

All code in the master branch should be fully functional.

[![Analytics](https://ga-beacon.appspot.com/UA-3181088-16/wikiline/readme)](https://github.com/aurbano)
