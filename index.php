<?php

error_reporting(E_ALL | E_STRICT);
include ('config.php');

$folder         = dirname($_SERVER['SCRIPT_FILENAME']);                     // the full path to here
$urlfolder      = substr($folder, strlen($_SERVER['DOCUMENT_ROOT']));       // '/covers/'
$file           = substr($_SERVER['REQUEST_URI'], strlen($urlfolder) +1);   // '9780061962165/y150.png'

$matches        = array();

if (preg_match('/^\/([^\/]+)\/([x|y])([\d]+)\.(png|jpg|gif)$/', $file, $matches)){
    list($match, $name, $xy, $size, $format) = $matches;
}
else if (preg_match('/^\/([^\/]+)\/([x|y])([\d]+)-([\d]+)\.(png|jpg|gif)$/', $file, $matches)){
    list($match, $name, $xy, $size, $altmax, $format) = $matches;
}
else{
    fail();
}


function fail(){
    print "too bad so sad";
}
