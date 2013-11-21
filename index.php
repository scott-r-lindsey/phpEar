<?php
/**
 * PHPEar
 * By Scott Lindsey
*/

require_once 'vendor/autoload.php';

error_reporting(E_ALL | E_STRICT);

try{
    $config = array();
    include ('config.php');

    $ear = new PHPEar();

    foreach ($config as $key => $value){
        $ear->$key = $value;
    }

    $ear->run();
}
catch (exception $e){
?>
<html><head></head><body>
<pre>
______ _   _ ______   _____           
| ___ \ | | || ___ \ |  ___|          
| |_/ / |_| || |_/ / | |__  __ _ _ __ 
|  __/|  _  ||  __/  |  __|/ _` | '__|
| |   | | | || |     | |__| (_| | |   
\_|   \_| |_/\_|     \____/\__,_|_| 

<?php
    echo "Message: " . $e->getMessage() . "<br />";
?>

Documentation: <a href="https://github.com/scott-r-lindsey/phpEar">https://github.com/scott-r-lindsey/phpEar</a>

</pre></body></html>
<?php
}



