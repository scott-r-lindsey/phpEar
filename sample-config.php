<?php

/* -------------------------------------------------------------------------- */
#  PHPEar v1.0 By Scott Lindsey
#  6/15/2012
#
#  This is plain PHP which will be included by PHPEar.  Attributes added to 
#  the $config array will be mapped to public variables in the PHPEar object
/* -------------------------------------------------------------------------- */

#
# source_prefix is either a system path or a url.  The "name" portion of 
# the incoming url will be concatenated to the end of this value.
# REQUIRED !!!

#    $config['source_prefix']    = 'http://www.yoursite.com/images/';
    $config['source_prefix']    = '';

#
# source_suffix will be concatenated to source_prefix, after the file name,
# most likely to denote a file type.
# 

#    $config['source_suffix']    = '.jpg';

#
# source_regex is your opportunity to munge the url.  These arguements will
# be fed to preg_replace.  For instance, this: 
# array('/(\d\d\d)(\d).jpg$/', '\2/\1\2.jpg') # has the effect of rewriting 
# "http://site.com/img/1234.jpg" to "http://site.com/img/4/1234.jpg" 

#    $config['source_regex']    = false;

#
# "failimg" is a graphic to display when the incoming url maps to an image that
# does not exist.  By default relative to this directory, but can be absolute.

#    $config['failimg']         = 'missing.png'

#
# PHPEar, when executed, will run a cleanup operation if "cleanup" or more 
# seconds have passed since the last cleanup.  During cleanup, files 
# that are more than cachettl seconds since last check will be marked
# as non-executable, causing them to be rechecked before being served.
# This is ignored if source_prefix is local.
# a value of 0 will disable cleanup.

#    $config['cleanup']         = 3600 // one hour

#
# During cleanup, files will be marked non-executable if the source image
# has not been verifed in cachettl seconds.
# This is ignored if source_prefix is local.
# A value of 0 will cause files to never be expired.

#    $config['cachettl']        = 43200; // 12 hours

#
# During cleanup, files which are not executable and which have not been
# changed (ctime) for greater than expirettl seconds will be erased.
# A value of 0 will cause files to never be erased.

#    $config['expirettl']       = 604800; // 1 week

#
# Maximum height for requested images.  You don't want people ddosing you 
# with requests for enormous images of course.

#    $config['max_y']           = 600;

#
# Maximum width for requested images.  You don't want people ddosing you 
# with requests for enormous images of course.

#    $config['max_x']           = 600;

# You probably don't want to change "cachedir".  If you do, you'll have to 
# modify the .htaccess file.

#    $config['cachedir']        = 'cache';

#
# The name of the folder within the cache directory which stores files 
# retrieved from a remote server.

#    $config['mirrordir']        = '__mirror';

# The name of the folder which contains resized versions of the "missing"
# image file.

#    $config['missingdir']        = '__missing';


# 
# quality field for imagepng().
#

#    $config['png_quality']     = 9;

#
# quality field for imagejpeg().
#

#    $config['jpeg_quality']    = 85;

#
# controldir holds the files that regulate cleanup operations

#    $config['controldir']      = 'control';

#
# dir_mode -- how the directories should be chmod'd within in the cache.

#    $config['dir_mode']      = 0775;

