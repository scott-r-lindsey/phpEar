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
<!-- /* --><pre>
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
    public $log               = false;
    public $log_lines         = array();
    public $awssns            = false;
    public $sns_regex         = '';

    private $format           = false;
    private $local_raw        = false;
    private $missing          = false;
    private $cwd              = false;

    public function phpEar(){
    }
    public function run(){
        $this->cwd      = dirname(__FILE__);

        if (    ($this->awssns) &&
                ('POST' == $_SERVER['REQUEST_METHOD']) && 
                (isset($_SERVER['HTTP_X_AMZ_SNS_MESSAGE_TYPE']))){

            return $this->handleSNS();
        }

        $folder         = dirname($_SERVER['SCRIPT_FILENAME']);                     // the full path to here
        $urlfolder      = substr($folder, strlen($_SERVER['DOCUMENT_ROOT']));       // '/images/'
        $file           = substr($_SERVER['REQUEST_URI'], strlen($urlfolder) +1);   // '9780061962165/y150.png'
        $altmax         = false;
        $postprocess    = false;
        $file           = ltrim($file, '/');

        if (false !== strpos($file, 'clear/')){
            return $this->clear($file);
        }

        $this->log('START: ' . $file);
        $this->validate();

        if (preg_match('/^([^\/]+)\/([x|y])([\d]+)\.(png|jpg|jpeg|gif)$/', $file, $matches)){
            list($match, $name, $xy, $size, $format) = $matches;
            $this->incomingPath = "$xy$size.$format";
        }
        else if (preg_match('/^([^\/]+)\/([x|y])([\d]+)-([\d]+)\.(png|jpg|jpeg|gif)$/i', $file, $matches)){
            list($match, $name, $xy, $size, $altmax, $format) = $matches;
            $this->incomingPath = "$xy$size-$altmax.$format";
        }
        else{
            $this->fail('Url (' . $file . ') was not recognized.');
        }

        $this->format       = $this->parseFormat($format);
        $this->local_raw    = $this->ds($this->fetchFile($name));
        $raw_mtime          = filemtime($this->local_raw);
        
        if ($this->missing){
            $this->local_cooked = $this->ds($this->cachedir . '/' . $this->missingdir . '/' . $this->incomingPath);
            $this->log('salvaged good file ' . $this->local_cooked);
        }
        else{
            $this->local_cooked = $this->ds($this->cachedir . '/' . $name . '/' . $this->incomingPath);

            if ($this->local_regex){
                list($regx, $replace) = $this->local_regex;
                $this->local_cooked = preg_replace($regx, $replace, $this->local_cooked);
            }
        }

        if (    (! file_exists($this->local_cooked)) or 
                ( filemtime($this->local_cooked) != filemtime($this->local_raw))){
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
        else if ( filemtime($this->local_cooked) == filemtime($this->local_raw) ){
            // this is a file that's been marked non-executable but seems to be good
            (chmod ($this->local_cooked, 0775)) ||
                $this->fail('chmod error');
            touch ($this->local_cooked, $raw_mtime);
            $this->log('salvaged good file ' . $this->local_cooked);
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
        $this->writeLog();
    }
    public function clear($file){
        $this->log('initiating clear mode');
        $this->log('file: ' . $file);

        if (false !== strpos($file, '?')){
            $file = substr($file, 0, strpos($file, '?'));
        }

        if (!preg_match('/^clear\/([^\/]+)\/(.*)/', $file, $matches)){
            $this->fail('Url (' . $file . ') was not recognized.');
        }

        list($match, $name, $size) = $matches;
        $source = $this->getSourceUrl($name);
        $local_mirror = $this->getLocalMirrorPath($source);
        $local_cooked = $this->ds($this->cachedir . '/' . $name . '/' . $size);

        if ($this->local_regex){
            list($regx, $replace) = $this->local_regex;
            $local_cooked = preg_replace($regx, $replace, $local_cooked);
        }

        $cache_dir = dirname($local_cooked);
        if (file_exists($cache_dir)){
            foreach (array_diff( scandir( $cache_dir ), Array( ".", ".." ) ) as $f){
                $erase[] = $cache_dir .'/'. $f;
            }
        }
        $erase[] = $local_mirror;

        $i = 0;
        header("Content-Type:text/plain");
        foreach ($erase as $e){
            if (file_exists($e)){
                $i++;
                unlink($e);
                $this->log("unlink $e");
            }
        }
        print "deleted $i files\n";
        $this->writeLog();
    }
    public function handleSNS(){

        $body       = @file_get_contents('php://input');
        $data       = json_decode($body, true);
        $type       = $data['Type'];

        if ('SubscriptionConfirmation' == $type){
            $url = $data['SubscribeURL'];
            $this->log('Amazon SNS subscription confirmation: ' . $url);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_exec($ch);
            curl_close($ch);
        }
        else if ('Notification' == $type){
            $this->log('Amazon SNS Notification detected');
            $message = json_decode($data['Message'], true);

            $key = $message['Records'][0]['s3']['object']['key'];

            if ($this->sns_regex){
                $this->log("Amazon SNS clear on resource $key");
                list($regx, $replace) = $this->sns_regex;
                if (preg_match($regx, $key)){
                    $this->log("Amazon SNS clear on resource $key");
                    $file = preg_replace($regx, $replace, $key);
                    $this->clear($file);
                }
                else{
                    $this->log("Amazon SNS regex miss on resource $key");
                }
            }
        }
        $this->writeLog();
    }
    private function ds ($str){
        // de-slash, and make absolute
        if ('/' != substr($str, 0, 1)){
            $str = $this->cwd . '/' . $str;
        }
        return str_replace('//', '/', $str);
    }
    private function tiff2jpg($filePath){
        if( class_exists ( 'Imagick' ) ) {
            $im = new \Imagick( $filePath );
            $im->setImageCompression(\Imagick::COMPRESSION_JPEG);
            $im->setImageCompressionQuality(300);
            $im->setImageFormat('jpeg');
            $str = $im->getImageBlob();
            return $str;
        }
        else {
            throw new Exception('Cannot convert image from TIFF file to JPEG for web consumption. Imagick library missing.');
        }
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
        else if('image/tiff' == $dims['mime'] || 'image/tiff-fx' == $dims['mime']) {
            $src_img = imagecreatefromstring($this->tiff2jpg($this->local_raw) );
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
    private function getSourceUrl($name){
        $source = $this->source_prefix . $name . $this->source_suffix;
        if ($this->source_regex){
            list($regx, $replace) = $this->source_regex;
            $source = preg_replace($regx, $replace, $source);
        }
        return $source;
    }
    private function fetchFile($name){
        $source = $this->getSourceUrl($name);

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
    private function getLocalMirrorPath($source){
        $local = $this->ds($this->cachedir . '/' .  $this->mirrordir . '/' .
            preg_replace('/^https?:\/\/([^\/]+)\//i', '', $source));
        return $local;
    }
    private function netFetch($source){
        $local = $this->getLocalMirrorPath($source);

        if (!file_exists(dirname($local))){
            ($this->mkpath (dirname($local), 0777));
        }

        $ch = curl_init();
        $this->log('fetching ' . $source);
        curl_setopt($ch, CURLOPT_FILETIME, true);
        curl_setopt($ch, CURLOPT_URL, $source);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if (file_exists($local)){
            $this->log('fetching ' . $source . ', time conditional');
            curl_setopt($ch, CURLOPT_TIMEVALUE, filemtime($local));
            curl_setopt($ch, CURLOPT_TIMECONDITION, CURL_TIMECOND_IFMODSINCE);
        }
        $data = curl_exec($ch);
        $time = curl_getinfo($ch, CURLINFO_FILETIME);

        if ($data){
            $this->log('storing downloaded file ' . $local);
            file_put_contents($local, $data);
        }
        else{
            $this->log('dload returned no data');
        }
        if (file_exists($local)){
            touch ($local, $time);
        }
        curl_close($ch);
        
        if (file_exists($local)){
            if (!@getimagesize($local)){
                unlink($local);
                $this->log('deleting corrupt file ' . $local);
                return false;
            }
            return $local;
        }
        return false;
    }
    private function mkpath($path) {
        while (!file_exists($path)){
            $missing[]  = substr($path, strrpos($path, '/')+1);
            $path       = substr($path, 0, strrpos($path, '/'));
        }
        $missing = array_reverse($missing);
        foreach ($missing as $dir){
            $path .= '/' . $dir;
            mkdir($path, $this->dir_mode) || ($this->fail('Could not create dir ' . $path));
        }
    }
    private function checkCleanup(){
        if (!$this->cachettl){
            $this->log('no cachettl set - auto cleanup disabled');
            return;
        }        

        $starttime = filemtime($start = $this->ds($this->controldir . '/proc-start'));
        $nexttime = time() - $this->cleanup;

        if ($starttime < $nexttime){
            touch($start);
            return true;
        }
        $this->log(
            'no cleanup, ' . date(DATE_RFC2822, $starttime) .
            ' >= ' . date(DATE_RFC2822, $nexttime));
        return false;
    }
    private function cleanup(){
        if (!$this->cachettl){
            return;
        }
        $this->log('running cleanup');

        // expires cache by marking files non-executable
        foreach (new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->ds($this->cachedir)),
                RecursiveIteratorIterator::CHILD_FIRST) as $name => $obj){

            if (
                (!$obj->isFile()) || 
                (!$obj->isExecutable()) ||
                ($obj->getCtime() > (time() - $this->cachettl))){

                    //$this->log($name . ': ' . date(DATE_RFC2822, filectime($name)));
                    continue;
            }
            $this->log("marking non-executable file $name");
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

                $this->log("deleting unused file $name");
                unlink($name);
            }
        }
        touch($this->ds($this->controldir . '/proc-end'));
    }
    private function log($message){
        if (!$this->log){return;}
        $this->log_lines[] = $message;
    }
    private function writeLog(){
        if (!$this->log){return;}
        file_put_contents($this->log, implode("\n", $this->log_lines) . "\n", FILE_APPEND);
    }
}
