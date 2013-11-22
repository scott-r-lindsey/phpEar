# PHPEar

PHPEar can be described as a very simple web image proxy environment with two major objectives:

1. To serve images in a specifed size and format
2. To cache the images served thus far for a specified period of time

## Usage

At the moment, the proxy URLs that retrieve the manipulated cached images need to match the following two patterns:

1. `/^([^\/]+)\/([x|y])([\d]+)\.(png|jpg|jpeg|gif)$/`

2. `/^([^\/]+)\/([x|y])([\d]+)-([\d]+)\.(png|jpg|jpeg|gif)$/i`

Please note that the x and y will specify the constraint to which the file will be proportionally scaled.

## Installation and Configuration

Minimum installation and configuration requirements:

1. /config.php - Although there are many parameters that can be configured, there are only two required fields:


// REQUIRED
// source_prefix is either a system path or a url.  The "name" portion of
// the incoming url will be concatenated to the end of this value.
`$config['source_prefix']    = 'http://hostname/image-container-folder/';`

// REQUIRED
// "failimg" is a graphic to display when the incoming url maps to an image that
// does not exist.  By default relative to this directory, but can be absolute.
`$config['failimg']         = '../missing.png';`


2. .htaccess - A copy of the suggested file configuration is provided in the file [sample-htaccess](./sample-htaccess)

3. /cache folder - This folder needs to be configured so that all the apache user is able to create directories and write files to it







