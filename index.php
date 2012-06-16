<?php

/*
 * phpEAR
 *
 * By Scott Lindsey
 *
*/

/* ----------------------------------------------------------------------------------------------------------- */
error_reporting(E_ALL | E_STRICT);

try{
    $config = array();
    include ('config.php');

    $ear = new phpEar();

    foreach ($config as $key => $value){
        $ear->$key = $value;
    }
    $ear->run();
}
catch (exception $e){
    echo "Message: " . $e->getMessage() . "<br />";
    echo "File: " . $e->getFile() . "<br />";
    echo "Line: " . $e->getLine();
}

/* ----------------------------------------------------------------------------------------------------------- */
class phpEar{

    public $source_prefix     = false;
    public $source_suffix     = false;
    public $source_regex      = false;
    public $cachettl          = 0;
    public $max_y             = 648;
    public $max_x             = 800;
    public $cachedir          = 'cache/';
    public $failimg           = 'notavailable.png';

    private $format             = false;
    private $local_image        = false;

    public function phpEar(){
    }
    public function run(){
        $this->validate();

        $folder         = dirname($_SERVER['SCRIPT_FILENAME']);                     // the full path to here
        $urlfolder      = substr($folder, strlen($_SERVER['DOCUMENT_ROOT']));       // '/covers/'
        $file           = substr($_SERVER['REQUEST_URI'], strlen($urlfolder) +1);   // '9780061962165/y150.png'

        if (preg_match('/^\/([^\/]+)\/([x|y])([\d]+)\.(png|jpg|gif)$/', $file, $matches)){
            list($match, $name, $xy, $size, $format) = $matches;
            $this->incomingPath = "$name/$xy$size.$format";
        }
        else if (preg_match('/^\/([^\/]+)\/([x|y])([\d]+)-([\d]+)\.(png|jpg|jpeg|gif)$/i', $file, $matches)){
            list($match, $name, $xy, $size, $altmax, $format) = $matches;
            $this->incomingPath = "$name/$xy$size-$altmax.$format";
        }
        else{
            $this->fail();
        }

        $this->format       = $this->parseFormat($format);
        $this->local_image  = $this->fetchFile($name);

    }
    private function parseFormat($format){
        $format = strtolower($format);

        if (('jpeg' == $format) || ('jpg' == $format)){         return 'jpeg'; }
        else if ('png' == $format){                             return 'png'; }
        else if ('gif' == $format){                             return 'gif'; }

        $this->fail('Could not parse format.');
    }
    private function validate(){
    }
    private function fail($message){
        $err = error_get_last();
        throw new Exception($message . ', ' . $err['message']);
    }
    private function fetchFile($name){

        $source = $this->source_prefix . $name . $this->source_suffix;
        if ($this->source_regex){
            list($regx, $replace) = $this->source_regex;
            $source = preg_replace($regx, $replace, $source);
        }

        if (    (strncmp($this->source_prefix, 'http://', 7)) ||
                (strncmp($this->source_prefix, 'https://', 8))){

            return $this->netFetch($source);
        }
        else{
            if ((file_exists($source)) && (@getimagesize($source))){
                return $file_exists;
            }
        }
        return $this->fail_image;

        print "source is $source";
    }
    private function netFetch($source){
        $local = $this->cachedir . '/source/' . 
            preg_replace('/^https?:\/\/([^\/]+)\//i', '', $source);

        if (!file_exists(dirname($local))){
            (mkdir (dirname($local), 0777, true)) || 
                ($this->fail('Could not create dir ' . dirname($local)));
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_FILETIME, true);
        curl_setopt($ch, CURLOPT_URL, $source);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if (file_exists($local)){
            curl_setopt($ch, CURLOPT_TIMEVALUE, filemtime($local));
            curl_setopt($ch, CURLOPT_TIMECONDITION, CURL_TIMECOND_IFMODSINCE);
        }
        $data = curl_exec($ch);

        if ($data){
            file_put_contents($local, $data);
            $time = curl_getinfo($ch, CURLINFO_FILETIME);
            touch ($local, $time, $time);
        }
        curl_close($ch);
        
        if (file_exists($local)){
            if (!@getimagesize($local)){
                unlink($local);
                return false;
            }
            return $local;
        }
        return false;
    }
}
    
/*

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
*/
