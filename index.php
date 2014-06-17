<?php
//setup for benchmarking
include('./benchmark.inc');

// This file will generate and return the main page of the site
$CONF = array(); // ideally Global Vars and defaults should init in just one location
$CONF['Self'] = 'index.php';

include('./config.php');

selector();

?>