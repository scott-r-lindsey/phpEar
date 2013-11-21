<?php

class PHPEarHelper {

    public static function tiff2jpg( $filePath ) {
        if( class_exists ( 'Imagick' ) ) {
            // read image
            $im = new \Imagick( $filePath );

            // convert to jpg
            $im->setImageCompression(\Imagick::COMPRESSION_JPEG);
            $im->setImageCompressionQuality(300);
            $im->setImageFormat('jpeg');

            //write image on server
            $ext = self::getFileExtension( $filePath );
            $newFilePath = str_replace(".$ext", '.jpg', $filePath);
            $im->writeImage($newFilePath);
            $im->clear();
            $im->destroy();

            return $newFilePath;
        }
        else {
            throw new Exception('Cannot convert image from TIFF file to JPEG for web consumption. Imagick library missing.');
        }
    }

    public static function getFileExtension( $fileName )
    {
        $lastDotPos = strrpos($fileName, '.');
        if ( !$lastDotPos ) return false;
        return substr($fileName, $lastDotPos+1);
    }

} 