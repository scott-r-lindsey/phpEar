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
    public $source_suffix     = '';
    public $source_regex      = false;
    public $failimg           = 'missing.png';
    public $cleanup           = 3600;
    public $cachettl          = 43200;
    public $max_y             = 600;
    public $max_x             = 600;
    public $cachedir          = 'cache/';
    public $png_quality       = 9;
    public $jpeg_quality      = 85;
    public $dir_mode          = 0775;

    private $format           = false;
    private $local_raw        = false;
    private $missing          = false;
    private $cwd              = false;

    public function phpEar(){
    }
    public function run(){
        $this->validate();

        $this->cwd      = dirname(__FILE__); // I think I won't rely on cwd really

        $folder         = dirname($_SERVER['SCRIPT_FILENAME']);                     // the full path to here
        $urlfolder      = substr($folder, strlen($_SERVER['DOCUMENT_ROOT']));       // '/images/'
        $file           = substr($_SERVER['REQUEST_URI'], strlen($urlfolder) +1);   // '9780061962165/y150.png'
        $altmax         = false;
        $postprocess    = false;

        if (preg_match('/^\/([^\/]+)\/([x|y])([\d]+)\.(png|jpg|gif)$/', $file, $matches)){
            list($match, $name, $xy, $size, $format) = $matches;
            $this->incomingPath = "$xy$size.$format";
        }
        else if (preg_match('/^\/([^\/]+)\/([x|y])([\d]+)-([\d]+)\.(png|jpg|jpeg|gif)$/i', $file, $matches)){
            list($match, $name, $xy, $size, $altmax, $format) = $matches;
            $this->incomingPath = "$xy$size-$altmax.$format";
        }
        else{
            $this->fail();
        }

        $this->format       = $this->parseFormat($format);
        $this->local_raw    = $this->ds($this->fetchFile($name));
        
        if ($this->missing){
            $this->local_cooked = $this->ds($this->cachedir . '/missing/' . $this->incomingPath);
        }
        else{
            $this->local_cooked = $this->ds($this->cachedir . '/' . $name . '/' . $this->incomingPath);
        }

        if (    (! file_exists($this->local_cooked)) or 
                ( filemtime($this->local_cooked) != filemtime($this->local_cooked))){
                // if the file does not exist, or else it does and the timestamp is wrong...

            $cooked_dir = dirname($this->local_cooked);

            if (! file_exists($cooked_dir)){
                ($this->mkpath ($cooked_dir, 0777));
            }

            $img = $this->getSized($xy, $size, $altmax);

            if ('png' == $this->format){
                imagepng($img, $this->local_cooked, $this->png_quality) ||
                    $this->fail('png creation error');
            }
            else if ('gif' == $this->format){
                imagegif($img, $this->local_cooked) ||
                    $this->fail('gif creation error');
            }
            else if ('jpeg' == $this->format){
                imagejpeg($img, $this->local_cooked, $this->jpeg_quality) ||
                    $this->fail('jpeg creation error');
            }

            $time = filemtime($this->local_raw);

            (chmod ($this->local_cooked, 0775)) ||
                $this->fail('chmod error');

            touch ($file, $time, $time);
        }
        else if ( filemtime($this->local_cooked) == filemtime($this->local_cooked)){
            // this is a file that's been marked non-executable but seems to be good
            (chmod ($this->local_cooked, 0775)) ||
                $this->fail('chmod error');
        }

        $postprocess = true;

        if ($postprocess){
            header('Connection: close');
            ignore_user_abort(true);
        }

        header('Content-type:  image/' . $this->format);
        header('Content-Length: ' . filesize($this->local_cooked));
        readfile($this->local_cooked);

        if ($postprocess){
            flush();
            fclose(STDOUT);
            $this->cleanup();
        }
    }
    private function ds ($str){
        // de-slash, and make absolute
        if ('/' != substr($str, 0, 1)){
            $str = $this->cwd . '/' . $str;
        }
        return str_replace('//', '/', $str);
    }
    private function getSized($xy, $size, $altmax ){

        $dims = getimagesize($this->local_raw);

        if ('image/jpeg' == $dims['mime']){
            $src_img = imagecreatefromjpeg($this->local_raw);
        }
        else if ('image/gif' == $dims['mime']){
            $src_img = imagecreatefromgif($this->local_raw);
        }
        else if ('image/png' == $dims['mime']){
            $src_img = imagecreatefrompng($this->local_raw);
        }
        
        $old_x      = imageSX($src_img);
        $old_y      = imageSY($src_img);

        if ('x' == $xy){
            $x = $size;
            if ($this->max_x < $x){
                $x = $this->max_x;
            }
            $y = round($old_y * ($x/$old_x));
        }
        else{
            $y = $size;
            if ($this->max_y < $y){
                $y = $this->max_y;
            }
            $x = round($old_x * ($y/$old_y));
        }

        if ($altmax){
            if ($xy == 'x'){
                if ($y > $altmax){
                    $y = $altmax;
                    $x = round($old_x * ($y/$old_y));
                }
            }
            else{
                if ($x > $altmax){
                    $x = $altmax;
                    $y = round($old_y * ($x/$old_x));
                }
            }
        }

        $new_img = imagecreatetruecolor($x, $y);
        imagecopyresampled($new_img, $src_img, 0, 0, 0, 0, $x, $y, $old_x, $old_y);

        return $new_img;
    }
    private function parseFormat($format){
        $format = strtolower($format);

        if (('jpeg' == $format) || ('jpg' == $format)){         return 'jpeg'; }
        else if ('png' == $format){                             return 'png'; }
        else if ('gif' == $format){                             return 'gif'; }

        $this->fail('Could not parse format.');
    }
    private function validate(){
        // sanity check args
        // make sure we can open "alt" image
        // check write permissions
        // 
 
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

            if ( $file = $this->netFetch($source)){
               return $file;
            }
        }
        else{
            if ((file_exists($source)) && (@getimagesize($source))){
                return $source;
            }
        }
        $this->missing = true;
        return $this->failimg;
    }
    private function netFetch($source){
        $local = $this->cachedir . '/source/' . 
            preg_replace('/^https?:\/\/([^\/]+)\//i', '', $source);

        if (!file_exists(dirname($local))){
            ($this->mkpath (dirname($local), 0777));
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
    function mkpath($path) {

        $path = str_replace("\\", "/", $path);
        $dirs = explode("/", $path);
        $path = '';

        foreach ($dirs as $d){
            $path .= $d .'/';
            if (!file_exists($path)){
                mkdir($path, $this->dir_mode) || ($this->fail('Could not create dir ' . $path));
            }
        }
    }
    private function cleanup(){
        touch ($this->cwd . '/control/proc-start');

        // stub
        // do some work!
        
        touch ($this->cwd . '/control/proc-end');
    }
}
