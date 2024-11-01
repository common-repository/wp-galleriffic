<?php
/*
Part of wp-galleriffic WordPress plugin

/*  Copyright 2010  Peter Molnar  (email : hello@petermolnar.eu )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

function get_intvalue( $name ) {
	if ( ! (empty($_GET[$name]) ) )
		return intval($_GET[$name]);
	else
		return 0;
}

function get_cropvalue( $name ) {
	if ( ! (empty($_GET[$name]) ) && strstr( $_GET[$name] , ':' ) )
		return explode( ':' , $_GET[$name] );
	else
		return false;
}

function get_boolvalue( $name ) {
	if ( isset($_GET[$name]) )
		return true;
	else
		return false;
}

function sendheader ( $status , $data ) {

	switch ($status) {
		case 400 :
			$str = 'HTTP/1.1 400 Bad Request';
			break;
		case 404 :
			$str = 'HTTP/1.1 404 Not Found';
			break;
		case 304 :
			$str = 'HTTP/1.1 304 Not Modified';
			break;
		case 500 :
			$str = 'HTTP/1.1 500 Internal Server Error';
			break;
		case 200 :
		default :
			$str = 'HTTP/1.1 200 OK';
			break;
	}

	header($str);
	print $data;
	exit( 0 );
}


/**
 * Code itself
 */

/** if we don't have imagick, fall back to GD
 * Imagick is preferred because of sharpening and exif capabilities
 */
//if ( ! extension_loaded ('imagick') )
	$GD = true;

$docroot = str_replace($_SERVER["SCRIPT_NAME"],'',$_SERVER["SCRIPT_FILENAME"]);
$docroot .= (substr($docroot, -1) == '/' ? '' : '/');
require_once( $docroot . 'wp-blog-header.php' );

$wp_upload_dir = wp_upload_dir();

/* older wordpress fix */
if ( ! defined( 'WP_PLUGIN_URL' ) )
	define( 'WP_PLUGIN_URL', get_option( 'siteurl' ) . '/wp-content/plugins' );
if ( ! defined( 'WP_PLUGIN_DIR' ) )
	define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );

/* wp-galleriffic constants */
define ( 'WP_GALLERIFFIC_PARAM' , 'wp-galleriffic' );
define ( 'WP_GALLERIFFIC_OPTION_GROUP' , 'wp-galleriffic-params' );
define ( 'WP_GALLERIFFIC_URL' , WP_PLUGIN_URL . '/' . WP_GALLERIFFIC_PARAM  );
define ( 'WP_GALLERIFFIC_DIR' , WP_PLUGIN_DIR . '/' . WP_GALLERIFFIC_PARAM );
define ( 'WP_GALLERIFFIC_CACHE_DIR' , $wp_upload_dir['basedir'] . '/cache' );
define ( 'WP_GALLERIFFIC_CACHE_URL' , $wp_upload_dir['baseurl'] . '/cache' );
define ( 'WP_GALLERIFFIC_THUMB' , WP_GALLERIFFIC_PARAM . '-thumb' );
define ( 'WP_GALLERIFFIC_PREVIEW' , WP_GALLERIFFIC_PARAM . '-preview' );
define ( 'WP_GALLERIFFIC_FLICKR_METHOD' , 'flickr.photosets.getPhotos' );
define ( 'WP_GALLERIFFIC_FLICKR_FORMAT' , 'php_serial' );
define ( 'WP_GALLERIFFIC_FLICKR_BASE' , 'http://farm%FARM%.static.flickr.com/%SERVER%/%ID%_%SECRET%' );

if (!is_dir(WP_GALLERIFFIC_CACHE_DIR))
	wp_mkdir_p(WP_GALLERIFFIC_CACHE_DIR);

if (!is_readable(WP_GALLERIFFIC_CACHE_DIR))
	sendheader ( 500 , 'Error: the cache directory is not readable' );

if (!is_writeable(WP_GALLERIFFIC_CACHE_DIR))
	sendheader ( 500 , 'Error: the cache directory is not writable' );

if ( empty($_GET['source']) )
	sendheader ( 400 , 'Error: no image was specified' );

if (! isset($_GET['out']) )
	sendheader ( 400 , 'Error: no target was specified' );

	/* grab temporary image */
	$image = WP_GALLERIFFIC_CACHE_DIR . '/' . uniqid('flickrtmp_');
	file_put_contents ( $image , file_get_contents ( $_GET['source'] ) );

	/* define output */
	$out = WP_GALLERIFFIC_CACHE_DIR . '/' . $_GET['out'];

	/* get values from URI */
	$width['max'] = get_intvalue ( 'width' );
	$height['max'] = get_intvalue ( 'height' );
	$exif = get_boolvalue ( 'exif' );

	/* read grabbed image size and type */
	$size = getimagesize($image);

	/* define sizes */
	$width['orig'] = $size[0];
	$height['orig'] = $size[1];

	$offset['x'] = 0;
	$offset['y'] = 0;

	$ratio['x'] = $width['max'] / $width['orig'];
	$ratio['y'] = $height['max'] / $height['orig'];

	/* Resize the image based on width */
	if ($ratio['x'] * $height['orig'] < $height['max'])
	{
		$width['tn'] = $width['max'];
		$height['tn'] = ceil($ratio['x'] * $height['orig']);
	}
	/* Resize the image based on height */
	else
	{
		$height['tn'] = $height['max'];
		$width['tn'] = ceil($ratio['y'] * $width['orig']);
	}

	/* use GD lib */
	if ($GD)
	{

		$dst = imagecreatetruecolor($width['tn'], $height['tn']);

		switch ($size['mime'])
		{
			case 'image/gif':
				$creation = 'imagecreatefromgif';
				$output = 'imagepng';
				$quality = 0;
				break;
			case 'image/x-png':
			case 'image/png':
				$creation = 'imagecreatefrompng';
				$output = 'imagepng';
				$quality = 0;
				break;
			default:
				$creation = 'imagecreatefromjpeg';
				$output = 'imagejpeg';
				$quality = 96;
				break;
		}

		$src = $creation($image);

		if (in_array($size['mime'], array('image/gif', 'image/png')))
		{
			imagealphablending($dst, false);
			imagesavealpha($dst, true);
		}

		imagecopyresampled($dst, $src, 0, 0, $offset['x'], $offset['y'], $width['tn'], $height['tn'], $width['orig'], $height['orig']);
		$output($dst, $out, $quality);

		ob_start();
			$output($dst, null, $quality);
			$data = ob_get_contents();
		ob_end_clean();

		imagedestroy($src);
		imagedestroy($dst);
	}
	/* use imagick */
	else
	{
		try
		{
			$dst = new Imagick();

			$dst->pingImage($image);
			$dst->readImage($image);

			$format = strtolower($dst->getImageFormat());
			$dst->setImageFormat( $format );
			$dst->cropImage ( $width['calc'] , $height['calc'] , $offset['x'] , $offset['y'] );

			if ($exif)
				$dst->scaleImage( $width['tn'], $height['tn'] );
			else
				$dst->thumbnailImage( $width['tn'], $height['tn'] );

			$dst->writeImage($out);
			$dst->clear();
			$dst->destroy();
			$data = file_get_contents($out);
		}
		catch(Exception $e)
		{
			print $e->getMessage();
		}
	}

	unlink ( $image );

	header("Content-type: ".$size['mime']);
	header('Content-Length: ' . strlen($data));
	sendheader ( 200 , $data );

?>