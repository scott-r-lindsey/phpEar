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

/* ----------------------------------------------------------------------------------------------------------- */
class phpEar{

    public $source_prefix     = false;
    public $source_suffix     = '';
    public $source_regex      = false;
    public $failimg           = 'missing.png';
    public $cleanup           = 3600;
    public $cachettl          = 43200;
    public $expirettl         = 604800;
    public $max_y             = 600;
    public $max_x             = 600;
    public $cachedir          = 'cache/';
    public $png_quality       = 9;
    public $jpeg_quality      = 85;
    public $controldir        = 'control/';
    public $mirrordir         = '__mirror';
    public $missingdir        = '__missing';
    public $dir_mode          = 0775;

    private $format           = false;
    private $local_raw        = false;
    private $missing          = false;
    private $cwd              = false;

    public function phpEar(){
    }
    public function run(){
        $this->cwd      = dirname(__FILE__); // I think I won't rely on cwd really
        $folder         = dirname($_SERVER['SCRIPT_FILENAME']);                     // the full path to here
        $urlfolder      = substr($folder, strlen($_SERVER['DOCUMENT_ROOT']));       // '/images/'
        $file           = substr($_SERVER['REQUEST_URI'], strlen($urlfolder) +1);   // '9780061962165/y150.png'
        $altmax         = false;
        $postprocess    = false;

        $this->validate();

        if (preg_match('/^\/([^\/]+)\/([x|y])([\d]+)\.(png|jpg|gif)$/', $file, $matches)){
            list($match, $name, $xy, $size, $format) = $matches;
            $this->incomingPath = "$xy$size.$format";
        }
        else if (preg_match('/^\/([^\/]+)\/([x|y])([\d]+)-([\d]+)\.(png|jpg|jpeg|gif)$/i', $file, $matches)){
            list($match, $name, $xy, $size, $altmax, $format) = $matches;
            $this->incomingPath = "$xy$size-$altmax.$format";
        }
        else{
            $this->fail('Url was not recognized.');
        }

        $this->format       = $this->parseFormat($format);
        $this->local_raw    = $this->ds($this->fetchFile($name));
        $raw_mtime          = filemtime($this->local_raw);
        
        if ($this->missing){
            $this->local_cooked = $this->ds($this->cachedir . '/' . $this->missingdir . '/' . $this->incomingPath);
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

            $raw_mtime = filemtime($this->local_raw);

            (chmod ($this->local_cooked, 0775)) ||
                $this->fail('chmod error');

            touch ($this->local_cooked, $raw_mtime);
        }
        else if ( filemtime($this->local_cooked) == filemtime($this->local_cooked)){
            // this is a file that's been marked non-executable but seems to be good
            (chmod ($this->local_cooked, 0775)) ||
                $this->fail('chmod error');
            touch ($this->local_cooked, $raw_mtime);
        }

        $postprocess = $this->checkCleanup();

        if ($postprocess){
            header('Connection: close');
            ignore_user_abort(true);
        }

        header('Content-type:  image/' . $this->format);
        header('Content-Length: ' . filesize($this->local_cooked));
        readfile($this->local_cooked);

        if ($postprocess){
            flush();
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

        if (!$this->source_prefix){
            $this->fail('Minimal config: $config[\'source_prefix\'] must be valid.');
        }

        if (    (0 === strncmp($this->source_prefix, 'http://', 7)) ||
                (0 === strncmp($this->source_prefix, 'https://', 8))){

            // could do a dns check or something.  meh.
        }
        else{
            if (!file_exists($this->ds($this->source_prefix))){
                $this->fail('source_prefix "' . $this->source_prefix .'" does not exist or is not readable.');
            }
            else if (!is_readable($this->source_prefix)){
                $this->fail('source_prefix "' . $this->source_prefix .'" does not seem to be readable.');
            }
        }

        // make sure we can open "alt" image
        if (!@getimagesize($this->ds($this->cwd . '/' . $this->failimg))){
            $this->fail('Failed validating image "' . $this->failimg . '"');
        }
        
        // check write permissions on cache
        if (!file_exists($this->ds($this->cachedir))){
            $this->fail('cachedir "' . $this->cachedir .'" does not exist or is not readable.');
        }
        else if (!is_writable($this->ds($this->cachedir))){
            $this->fail('cachedir "' . $this->cachedir .'" does not seem to be writable.');
        }
    }
    private function fail($message){
        $err = error_get_last();
        if ($err['message']){
            throw new Exception($message . "\nError: " . $err['message']);
        }
        else{
            throw new Exception($message);
        }
    }
    private function fetchFile($name){

        $source = $this->source_prefix . $name . $this->source_suffix;
        if ($this->source_regex){
            list($regx, $replace) = $this->source_regex;
            $source = preg_replace($regx, $replace, $source);
        }

        if (    (0 === strncmp($this->source_prefix, 'http://', 7)) ||
                (0 === strncmp($this->source_prefix, 'https://', 8))){

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
        $local = $this->ds($this->cachedir . '/' .  $this->mirrordir . '/' .
            preg_replace('/^https?:\/\/([^\/]+)\//i', '', $source));

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
        $time = curl_getinfo($ch, CURLINFO_FILETIME);

        if ($data){
            file_put_contents($local, $data);
        }
        if (file_exists($local)){
            touch ($local, $time);
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
    private function mkpath($path) {

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
    private function checkCleanup(){
        if (($this->cachettl) && ( filemtime($start)  < (time() - $this->cleanup))){
            touch($this->ds($this->controldir . '/proc-start'));

            return true;
        }
        return false;
    }
    private function cleanup(){
        if (!$this->cachettl){
            return;
        }

        // expires cache by marking files non-executable
        foreach (new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->ds($this->cachedir)),
                RecursiveIteratorIterator::CHILD_FIRST) as $name => $obj){

            if (
                (!$obj->isFile()) || 
                (!$obj->isExecutable()) ||
                ($obj->getCtime() > (time() - $this->cachettl))){

                continue;
            }
            chmod ($name, 0666);
        }

        if (!$this->expirettl){
            return;
        }

        // deletes unused resized files
        foreach (new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->ds($this->cachedir)),
                RecursiveIteratorIterator::CHILD_FIRST) as $name => $obj){

            if (
                ($obj->isFile()) &&
                (!$obj->isExecutable()) &&
                ($obj->getCtime() < (time() - $this->expirettl))){

                unlink($name);
            }
        }
        touch($this->ds($this->controldir . '/proc-end'));
    }
}
