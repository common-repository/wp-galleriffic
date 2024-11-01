<?php
/*
Plugin Name: wp-galleriffic
Version: 1.3
Plugin URI: http://petermolnar.eu/wordpress/wp-galleriffic
Description: Adds Galleriffic gallery to Wordpress, based on Galleriffic ( http://www.twospy.com/galleriffic/ )
Author: Peter Molnar
Author URI: http://petermolnar.eu/
License: GPL2
*/

/*  Copyright 2010-2011 Peter Molnar  (email : hello@petermolnar.eu )

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

/* upload dir of (current) WordPress site for Multisite compatibility */
$wp_upload_dir = wp_upload_dir();


/**
 *  checks for SSL connection
*/
if ( ! function_exists ( 'replace_if_ssl' ) ) {
	function replace_if_ssl ( $string ) {
	        if ( isset($_SERVER['HTTPS']) && ( ( strtolower($_SERVER['HTTPS']) == 'on' )  || ( $_SERVER['HTTPS'] == '1' ) ) )
	                return str_replace ( 'http://' , 'https://' , $string );
	        else
	                return $string;
	}
}

/* fix */
if ( ! defined( 'WP_PLUGIN_URL_' ) )
{
        if ( defined( 'WP_PLUGIN_URL' ) )
                define( 'WP_PLUGIN_URL_' , replace_if_ssl ( WP_PLUGIN_URL ) );
        else
                define( 'WP_PLUGIN_URL_', replace_if_ssl ( get_option( 'siteurl' ) ) . '/wp-content/plugins' );
}

if ( ! defined( 'WP_PLUGIN_DIR' ) )
	define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );

/* wp-galleriffic constants */
define ( 'WP_GALLERIFFIC_PARAM' , 'wp-galleriffic' );
define ( 'WP_GALLERIFFIC_OPTION_GROUP' , 'wp-galleriffic-params' );
define ( 'WP_GALLERIFFIC_URL' , WP_PLUGIN_URL_ . '/' . WP_GALLERIFFIC_PARAM  );
define ( 'WP_GALLERIFFIC_DIR' , WP_PLUGIN_DIR . '/' . WP_GALLERIFFIC_PARAM );
define ( 'WP_GALLERIFFIC_CACHE_DIR' , $wp_upload_dir['basedir'] . '/cache' );
define ( 'WP_GALLERIFFIC_CACHE_URL' , $wp_upload_dir['baseurl'] . '/cache' );
define ( 'WP_GALLERIFFIC_THUMB' , WP_GALLERIFFIC_PARAM . '-thumb' );
define ( 'WP_GALLERIFFIC_PREVIEW' , WP_GALLERIFFIC_PARAM . '-preview' );
define ( 'WP_GALLERIFFIC_FLICKR_BASE' , 'http://api.flickr.com/services/rest/?' );
define ( 'WP_GALLERIFFIC_FLICKR_PHOTOSET' , 'flickr.photosets.getPhotos' );
define ( 'WP_GALLERIFFIC_FLICKR_PHOTOINFO' , 'flickr.photos.getInfo' );
define ( 'WP_GALLERIFFIC_FLICKR_FORMAT' , 'php_serial' );
define ( 'WP_GALLERIFFIC_FLICKR_IMGBASE' , 'http://farm%FARM%.static.flickr.com/%SERVER%/%ID%_%SECRET%' );

if (!class_exists('WPGalleriffic')) {

	/**
	 * main class for wp-galleriffic
	 *
	 */
	class WPGalleriffic {

		/* for options array */
		var $options = array();
		/* for default options array */
		var $defaults = array();
		/* sizes, will be needed at gallery generation */
		var $sizes = array ( 'thumb' , 'preview' );
		/* the images collected for the current gallery */
		var $images = array();

		/**
		* galleriffic constructor
		*
		*/
		function __construct() {

			/* register options */
			$this->get_options();

			/* add image sizes */
			$crop = $this->options['thumbCrop'] ? true : false;
			add_image_size( WP_GALLERIFFIC_THUMB,	$this->options['thumbSize'],	$this->options['thumbSize'] ,	$crop);
			add_image_size( WP_GALLERIFFIC_PREVIEW,	$this->options['imgSize'],		$this->options['imgSize'],		false);

			/* add CSS only for admin */
			if ( is_admin() )
			{
				wp_enqueue_style( 'wp-galleriffic.admin.css' , WP_GALLERIFFIC_URL . '/css/wp-galleriffic.admin.css', false, '0.1');
			}
			elseif ( !is_feed() )
			{
				/* default CSS */
				if ($this->options['defaultCSS'])
					wp_enqueue_style( 'wp-galleriffic.default.css' , WP_GALLERIFFIC_URL . '/css/wp-galleriffic.default.css', false, '0.1');

				/* load JS if header option selected */
				if ( $this->options['jsLoadType'] == 0 )
					$this->load_js();
			}

			/* check for cache dir */
			if (!is_dir ( WP_GALLERIFFIC_CACHE_DIR ) )
				wp_mkdir_p ( WP_GALLERIFFIC_CACHE_DIR );

			/* on activation */
			register_activation_hook(__FILE__ , array( $this , 'activate') );

			/* on uninstall */
			register_uninstall_hook(__FILE__ , array( $this , 'uninstall') );

			/* init plugin in the admin section */
			add_action('admin_menu', array( $this , 'admin_init') );

			/* register shortcode */
			add_shortcode( WP_GALLERIFFIC_PARAM , array( $this , 'shortcode') );

		}

		/**
		 * activation hook: save default settings in order to eliminate bugs.
		 *
		 */
		function activate ( ) {
			$this->save_settings();
		}

		/**
		 * init function for admin section
		 *
		 */
		function admin_init () {
			/* register options */
			 register_setting( WP_GALLERIFFIC_OPTION_GROUP , WP_GALLERIFFIC_PARAM );
			 add_option( WP_GALLERIFFIC_PARAM, $this->options , '' , 'no');

			/* save parameter updates, if there are any */
			if ( isset($_POST[WP_GALLERIFFIC_PARAM . '-save']) )
			{
				$this->save_settings () ;
				header("Location: options-general.php?page=wp-galleriffic-options&saved=true");
			}

			/* add the options page to admin section for privileged for admin users */
			add_options_page('Edit wp-galleriffic options', __('WP-Galleriffic', WP_GALLERIFFIC_PARAM ), 10, 'wp-galleriffic-options', array ( $this , 'admin_panel' ) );
		}

		/**
		 * settings panel at admin section
		 *
		 */
		function admin_panel ( ) {

			/**
			 * security
			 */
			if( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ){
				die( );
			}

			/**
			 * if options were saved
			 */
			if ($_GET['saved']=='true') :
			?>

			<div id='setting-error-settings_updated' class='updated settings-error'><p><strong>Settings saved.</strong></p></div>

			<?php
			endif;

			/**
			 * the admin panel itself
			 */
			?>

			<div class="wrap">
			<h2><?php _e( ' wp-galleriffic settings', WP_GALLERIFFIC_PARAM ) ; ?></h2>
			<form method="post" action="#">
				<fieldset class="grid50">
					<legend><?php _e('Flickr gallery', WP_GALLERIFFIC_PARAM); ?></legend>
				<dl>

					<dt>
						<label for="flickrThumbsource"><?php _e('Flickr image size for thumbnail', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<select name="flickrThumbsource" id="flickrThumbsource">
							<?php $this->flickr_sizes ( $this->options['flickrThumbsource'] ) ?>
						</select>
						<span class="description"><?php _e('Select source Flickr image size for thumbnails.', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->flickr_sizes( $this->defaults['flickrThumbsource'] , true ) ; ?></span>
					</dd>

					<dt>
						<label for="flickrPreviewsource"><?php _e('Flickr image size for preview', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<select name="flickrPreviewsource" id="flickrPreviewsource">
							<?php $this->flickr_sizes ( $this->options['flickrPreviewsource'] ) ?>
						</select>
						<span class="description"><?php _e('Select source Flickr image size for preview.', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->flickr_sizes( $this->defaults['flickrPreviewsource'] , true ) ; ?></span>
					</dd>

					<dt>
						<label for="flickrAPI"><?php _e('Flick API key', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="text" name="flickrAPI" id="flickrAPI" value="<?php echo $this->options['flickrAPI']; ?>" />
						<span class="description"><?php _e('Key to access Flickr. The default is a key registered by the author of the wp-galleriffic plugin.', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php echo $this->defaults['flickrAPI']; ?></span>
					</dd>
					<!--
					<dt>
						<label for="flickrPrivacyFilter"><?php _e('Flickr privacy filter', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<select name="flickrPrivacyFilter" id="flickrPrivacyFilter">
							<?php $this->flickr_privacy ( $this->options['flickrPrivacyFilter'] ) ?>
						</select>
						<span class="description"><?php _e('Limit the showed pictures by Flickr privacy settings', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->flickr_privacy( $this->defaults['flickrPrivacyFilter'] , true ) ; ?></span>
					</dd>
					-->

					<dt>
						<label for="flickrResize"><?php _e('Resize Flick images?', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="flickrResize" id="flickrResize" value="1" <?php checked($this->options['flickrResize'],true); ?> />
						<span class="description"><?php _e('Resize Flickr images and serve them from local cache.', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->print_bool( $this->defaults['flickrResize']); ?></span>
					</dd>

					<dt>
						<label for="flickrCacheValidTime"><?php _e('Valid Flickr request cache time', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="number" name="flickrCacheValidTime" id="flickrCacheValidTime" value="<?php echo $this->options['flickrCacheValidTime']; ?>" />
						<span class="description"><?php _e('The plugin try to cache the Flickr request, if WordPress caching is enabled. This saves bandwidth as load the site much faster, but the changes on the Flickr side will not be recognized until this time (in seconds) has passed. WARNING: 0 means infinite, do not use it! If you use `Detailed flickr info`, using a cache plugin is highly recommended.', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php echo $this->defaults['flickrCacheValidTime']; ?></span>
					</dd>

				</dl>
				</fieldset>

				<fieldset class="grid50">
				<legend><?php _e('Local gallery',WP_GALLERIFFIC_PARAM); ?></legend>
				<dl>

					<dt>
						<label for="thumbSize"><?php _e('Thumbnail max. size', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="number" name="thumbSize" id="thumbSize" value="<?php echo $this->options['thumbSize']; ?>" />
						<span class="description"><?php _e('Maximum size of larger side of thumbnail in pixel.', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php echo $this->defaults['thumbSize']; ?></span>
					</dd>

					<dt>
						<label for="imgSize"><?php _e('Image max size', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="number" name="imgSize" id="imgSize" value="<?php echo $this->options['imgSize']; ?>" />
						<span class="description"><?php _e('Maximum size of larger side of large images.', WP_GALLERIFFIC_PARAM); ?></span>

						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php echo $this->defaults['imgSize']; ?></span>
					</dd>

					<dt>
						<label for="thumbCrop"><?php _e('Enable thumbnail cropping', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="thumbCrop" id="thumbCrop" value="1" <?php checked($this->options['thumbCrop'],true); ?> />
						<span class="description"><?php _e('Enable to cropping thumbnails to 1:1 ratio (square)', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->print_bool( $this->defaults['thumbCrop']); ?></span>
					</dd>

					<dt>
						<label for="imgTitleSource"><?php _e('Image "title" tag source data', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<select name="imgTitleSource" id="imgTitleSource">
							<?php $this->title_sources ( $this->options['imgTitleSource'] ) ?>
						</select>
						<span class="description"><?php _e('Select source for image title ( link & image "title" tags).', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->title_sources( $this->defaults['imgTitleSource'] , true ) ; ?></span>
					</dd>

					<dt>
						<label for="imgAltSource"><?php _e('Image "alttext" tag source data', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<select name="imgAltSource" id="imgAltSource">
							<?php $this->title_sources ( $this->options['imgAltSource'] ) ?>
						</select>
						<span class="description"><?php _e('Select source for image alternative text ( image "alttext" tag).', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->title_sources( $this->defaults['imgAltSource'] , true ) ; ?></span>
					</dd>

					<dt>
						<label for="imgCaptionSource"><?php _e('Image "figcaption" tag source data', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<select name="imgCaptionSource" id="imgCaptionSource">
							<?php $this->title_sources ( $this->options['imgCaptionSource'] ) ?>
						</select>
						<span class="description"><?php _e('Select source for image title ( image "figcaption" tags).', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->title_sources( $this->defaults['imgCaptionSource'] , true ) ; ?></span>
					</dd>

					<dt>
						<label for="imgDescSource"><?php _e('Image description source data', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<select name="imgDescSource" id="imgDescSource">
							<?php $this->title_sources ( $this->options['imgDescSource'] ) ?>
						</select>
						<span class="description"><?php _e('Select source for image description. This will be added to "alt" and "figcaption" data.', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->title_sources( $this->defaults['imgDescSource'] , true ) ; ?></span>
					</dd>

				</dl>
				</fieldset>

				<fieldset class="grid50 clearcolumns">
					<legend><?php _e('Slideshow',WP_GALLERIFFIC_PARAM); ?></legend>
				<dl>
					<dt>
						<label for="delay"><?php _e('Delay', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="number" name="delay" id="delay" value="<?php echo $this->options['delay']; ?>" />
						<span class="description"><?php _e('Specifies the visibility time for in image in slideshow mode.', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php echo $this->defaults['delay']; ?></span>
					</dd>

					<dt>
						<label for="defaultTransitionDuration"><?php _e('Effect speed', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="number" name="defaultTransitionDuration" id="defaultTransitionDuration" value="<?php echo $this->options['defaultTransitionDuration']; ?>" />
						<span class="description"><?php _e('If using the default transitions, specifies the duration of the transitions', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php echo $this->defaults['defaultTransitionDuration']; ?></span>
					</dd>

					<dt>
						<label for="renderSSControls"><?php _e('Show slideshow Play and Pause links', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="renderSSControls" id="renderSSControls" value="1" <?php checked($this->options['renderSSControls'],true); ?> />
						<span class="description"><?php _e('Specifies whether the slideshow Play and Pause links should be rendered', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->print_bool( $this->defaults['renderSSControls'] ); ?></span>
					</dd>

					<dt>
						<label for="autoStart"><?php _e('Enable slideshow autostart', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="autoStart" id="autoStart" value="1" <?php checked($this->options['autoStart'],true); ?> />
						<span class="description"><?php _e('Specifies whether the slideshow should be playing or paused when the page first loads', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->print_bool( $this->defaults['autoStart'] ); ?></span>
					</dd>

				</dl>
				</fieldset>

				<fieldset class="grid50">
					<legend><?php _e('Layout',WP_GALLERIFFIC_PARAM); ?></legend>
				<dl>

					<dt>
						<label for="numThumbs"><?php _e('Thumbs/page', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="number" name="numThumbs" id="numThumbs" value="<?php echo $this->options['numThumbs']; ?>" />
						<span class="description"><?php _e('The number of thumbnails to show per page', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php echo $this->defaults['numThumbs']; ?></span>
					</dd>

					<dt>
						<label for="maxPagesToShow"><?php _e('Maximum number of pages', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="number" name="maxPagesToShow" id="maxPagesToShow" value="<?php echo $this->options['maxPagesToShow']; ?>" />
						<span class="description"><?php _e('The maximum number of pages to display in either the top or bottom pager', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php echo $this->defaults['maxPagesToShow']; ?></span>
					</dd>

					<dt>
						<label for="enableOpacity"><?php _e('Enable JavaScript opacity', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="enableOpacity" id="enableOpacity" value="1" <?php checked($this->options['enableOpacity'],true); ?> />
						<span class="description"><?php _e('Enabled javascript thumbnail opacity handler', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->print_bool( $this->defaults['enableOpacity']); ?></span>
					</dd>

					<dt>
						<label for="mouseOutOpacity"><?php _e('Inactive thumbnail opacity', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="number" step="0.05" name="mouseOutOpacity" id="mouseOutOpacity" value="<?php echo $this->options['mouseOutOpacity']; ?>" />
						<span class="description"><?php _e('The opacity of the currently no active thumbnails in percents (100 means no opacity).', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php echo $this->defaults['mouseOutOpacity']; ?></span>
					</dd>

					<dt>
						<label for="enableTopPager"><?php _e('Enable pager on top', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="enableTopPager" id="enableTopPager" value="1" <?php checked($this->options['enableTopPager'],true); ?> />
						<span class="description"><?php _e('Show pagination on the top', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->print_bool( $this->defaults['enableTopPager']); ?></span>
					</dd>

					<dt>
						<label for="enableBottomPager"><?php _e('Enable pager on bottom', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="enableBottomPager" id="enableBottomPager" value="1" <?php checked($this->options['enableBottomPager'],true); ?> />
						<span class="description"><?php _e('Show pagination on the bottom', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->print_bool( $this->defaults['enableBottomPager'] ); ?></span>
					</dd>

					<dt>
						<label for="defaultCSS"><?php _e('Use default CSS', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="defaultCSS" id="defaultCSS" value="1" <?php checked($this->options['defaultCSS'],true); ?> />
						<span class="description"><?php _e('Enable use of CSS provided with the plugins.', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->print_bool( $this->defaults['defaultCSS'] ); ?></span>
					</dd>

					<dt>
						<label for="cssautofix"><?php _e('Enable CSS autofix', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="cssautofix" id="cssautofix" value="1" <?php checked($this->options['cssautofix'],true); ?> />
						<span class="description"><?php _e('Enable CSS fix for aligning large pictures to the vertical center and fix duplicate images error. The image container will be the size of image + size of border in height and ( size of image + size of border ) *1.1 in width. This hack can be avoided with correctly coded CSS.', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->print_bool( $this->defaults['cssautofix'] ); ?></span>
					</dd>

				</dl>
				</fieldset>

				<fieldset class="grid50 clearcolumns">
					<legend><?php _e('Texts', WP_GALLERIFFIC_PARAM); ?></legend>
				<dl>

					<dt>
						<label for="playLinkText"><?php _e('Text of `Play` link', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="text" name="playLinkText" id="playLinkText" value="<?php echo $this->options['playLinkText']; ?>" />
						<span class="description"><?php _e('Text displayed to start the stopped slideshow.', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php echo $this->defaults['playLinkText']; ?></span>
					</dd>

					<dt>
						<label for="pauseLinkText"><?php _e('Text of `Pause` link', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="text" name="pauseLinkText" id="pauseLinkText" value="<?php echo $this->options['pauseLinkText']; ?>" />
						<span class="description"><?php _e('Text displayed to pause the ongoing slideshow.', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php echo $this->defaults['pauseLinkText']; ?></span>
					</dd>

					<dt>
						<label for="nextPageLinkText"><?php _e('Text of `Next page` link', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="text" name="nextPageLinkText" id="nextPageLinkText" value="<?php echo $this->options['nextPageLinkText']; ?>" />
						<span class="description"><?php _e('Text displayed after the pagination to change to the next page of thumbnails.', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php echo $this->defaults['nextPageLinkText']; ?></span>
					</dd>

					<dt>
						<label for="prevPageLinkText"><?php _e('Text of `Previous page` link', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="text" name="prevPageLinkText" id="prevPageLinkText" value="<?php echo $this->options['prevPageLinkText']; ?>" />
						<span class="description"><?php _e('Text displayed before the pagination to change to the previous page of thumbnails.', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php echo $this->defaults['prevPageLinkText']; ?></span>
					</dd>

				</dl>
				</fieldset>

				<fieldset class="grid50">
					<legend><?php _e('System Settings', WP_GALLERIFFIC_PARAM); ?></legend>
				<dl>

					<dt>
						<label for="preloadAhead"><?php _e('Number of images to preload', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="number" name="preloadAhead" id="preloadAhead" value="<?php echo $this->options['preloadAhead']; ?>" />
						<span class="description"><?php _e('Preload this number of images in the background for smoothless playback. (-1 means unlimited, use it with care!)', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php echo $this->defaults['preloadAhead']; ?></span>
					</dd>

					<dt>
						<label for="enableHistory"><?php _e('Enable browser history', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="enableHistory" id="enableHistory" value="1" <?php checked($this->options['enableHistory'],true); ?> />
						<span class="description"><?php _e('Specifies whether the url hash and the browser history cache should update when the current slideshow image changes', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->print_bool( $this->defaults['enableHistory'] ); ?></span>
					</dd>

					<dt>
						<label for="enableKeyboardNavigation"><?php _e('Enable keyboard navigation', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="enableKeyboardNavigation" id="enableKeyboardNavigation" value="1" <?php checked($this->options['enableKeyboardNavigation'],true); ?> />
						<span class="description"><?php _e('Navigate through images with arrow keys.', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->print_bool( $this->defaults['enableKeyboardNavigation'] ); ?></span>
					</dd>

					<dt>
						<label for="recreateMissing"><?php _e('Re-create missing images?', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="recreateMissing" id="recreateMissing" value="1" <?php checked($this->options['recreateMissing'],true); ?> />
						<span class="description"><?php _e('Check for missing image sizes and recreate them. Warning: this can seriously slow down the plugin!', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->print_bool( $this->defaults['recreateMissing'] ); ?></span>
					</dd>

					<dt>
						<label for="html5jsSource"><?php _e('Select source for HTML5 JS', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<select name="html5jsSource" id="html5jsSource">
							<?php $this->html5js_sources ( $this->options['html5jsSource'] ) ?>
						</select>
						<span class="description"><?php _e('This JavaScript is loaded to fix HTML5 elements for Internet Explorer versions before 10, and other old browsers without HTML5 support.<br /><br />Set it to `not to load` if your theme (or any other source) already uses it, otherwise will be loaded with the rest of the required JavaScripts.', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->html5js_sources( $this->defaults['html5jsSource'] , true ) ; ?></span>
					</dd>

					<dt>
						<label for="jsLoadType"><?php _e('JavaScript loads', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<select name="jsLoadType" id="jsLoadType">
							<?php $this->js_positions ( $this->options['jsLoadType'] ) ?>
						</select>
						<span class="description"><?php _e('Select if required JavaScripts should be loaded always or only on pages where wp-galleriffic shortcode is used.<br />NOTE: wp_head() function is required for option 1, wp_footer() for option 2 in your theme in order to work.', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->js_positions( $this->defaults['jsLoadType'] , true ) ; ?></span>
					</dd>

					<!--
					<dt>
						<label for="flickrDetailedInfo"><?php _e('Detailed image info', WP_GALLERIFFIC_PARAM); ?></label>
					</dt>
					<dd>
						<input type="checkbox" name="flickrDetailedInfo" id="flickrDetailedInfo" value="1" <?php checked($this->options['flickrDetailedInfo'],true); ?> />
						<span class="description"><?php _e('Retreive detailed info (title, description, tags, etc.) from Flickr. WARNING: this is going to be really slow without an object cache plugin.', WP_GALLERIFFIC_PARAM); ?></span>
						<span class="default"><?php _e('Default ', WP_GALLERIFFIC_PARAM); ?>: <?php $this->print_bool( $this->defaults['flickrDetailedInfo'] ); ?></span>
					</dd>
					-->

				</dl>
				</fieldset>

				<?php settings_fields( WP_GALLERIFFIC_OPTION_GROUP ); ?>
				<p class="clearcolumns"><input class="button-primary" type="submit" name="<?php echo WP_GALLERIFFIC_PARAM; ?>-save" id="<?php echo WP_GALLERIFFIC_PARAM; ?>-save" value="Save Changes" /></p>
			</form>
			<?php

		}

		/**
		 * generate resized cache images for Flickr image, thumbnail & preview
		 *
		 * @param $img
		 * 	$img contains informations of image, the important ones in this case:
		 *		$img['thumb'][0] for URL of thumbnail source image
		 *		$img['title'] for the title of the source image
		 * 		$img['preview'][0] for URL of preview source image
		 *
		 * @return
		 * 	comes back with the same array as the input, but replaced with modified values
		 */
		function flickr_cache_image ( $img ) {

			/* generate nice title for image from grapped img title */
			$imgslug = sanitize_title( $img['title'] );

			/* tmp file location */
			$imgtmp = WP_GALLERIFFIC_CACHE_DIR . '/' . $imgslug;

			foreach ($this->sizes as $sizetype )
			{
				if ( $sizetype == 'thumb' )
				{
					$optname = 'thumbSize';
					/* clear exif, don't waste bandwidth */
					$exif = '';
				}
				elseif ( $sizetype == 'preview' )
				{
					$optname = 'imgSize';
					/* keep exif */
					$exif = '&amp;exif';
				}

				$imgname = $imgslug . '-' . $this->options[ $optname ] . '.jpg';
				$imgout = WP_GALLERIFFIC_CACHE_DIR . '/' . $imgname;

				$query = WP_GALLERIFFIC_URL . '/image.php?'
					. 'width=' . $this->options[ $optname ]
					. '&amp;height=' . $this->options[ $optname ]
					. $exif
					. '&amp;out=' . $imgname
					. '&amp;source=' . $img[ $sizetype ][ 0 ];

				if ( file_exists( $imgout ))
				{
					$img[ $sizetype ][ 0 ] = WP_GALLERIFFIC_CACHE_URL . '/' . $imgname;
				}
				else
				{
					$img[ $sizetype ][ 0 ] = $query;
				}

			}

			return $img;
		}

		/**
		 * Flickr API requests handler
		 *
		 * @param $this->options
		 * 	array with the required parameters for Flickr request
		 *
		 */
		function flickr_request ( $options ) {

			$encoded_params = array();
			foreach ($options as $k => $v){
				$encoded_params[] = urlencode($k).'='.urlencode($v);
			}
			$flickrurl = WP_GALLERIFFIC_FLICKR_BASE . implode('&', $encoded_params);
			$cachekey = WP_GALLERIFFIC_PARAM . '-' . md5( $flickrurl );

			$request = wp_cache_get( $cachekey );

			if ( ! $request )
			{
				$request = unserialize ( file_get_contents( $flickrurl ) );
				wp_cache_add( $cachekey, $request, '', $this->options['flickrCacheValidTime'] );
			}
			return $request;
		}

		/**
		 * Flickr image sizes
		 *
		 * @param $current
		 * 	the active or required size's identifier
		 *
		 * @param $returntext
		 * 	boolean: is true, the description will be returned of $current size
		 *
		 * @return
		 * 	prints either description of $current
		 * 	or option list for a <select> input field with $current set as active
		 *
		 */
		function flickr_sizes ( $current , $returntext = false ) {

			$e = array (
				's' => 'square, 75x75',
				't' => 'thumbnail, 100 on longest side',
				'm' => 'small, 240 on longest side',
				'-' => 'medium, 500 on longest side',
				'z' => 'large medium, 640 on longest side',
				'b' => 'large, 1024 on longest side',
				'o' => 'original image'
			);

			$this->print_select_options ( $e , $current , $returntext );

		}

		/**
		 * flickr_privacy
		 *
		 * @param $current
		 * 	the active or required privacy identifier
		 *
		 * @param $returntext
		 * 	boolean: is true, the description will be returned of $current privacy
		 *
		 * @return
		 * 	prints either description of $current
		 * 	or option list for a <select> input field with $current set as active
		 *
		 */
		function flickr_privacy ( $current , $returntext = false ) {

			$e = array (
				1 => 'public photos',
				2 => 'photos visible to friends',
				3 => 'photos visible to family',
				4 => 'photos visible to friends & family',
				5 => 'completely private photos',
			);

			$this->print_select_options ( $e , $current , $returntext );

		}

		/**
		 * parameters array with default values;
		 *
		 * @param $def
		 * 	is false, the function returns with the current settings, if true, the defaults will be returned
		 *
		 */
		function get_options () {
			$defaults = array (
				'thumbSize'=>40,
				'imgSize'=>560,
				'thumbCrop'=>true,
				'delay'=>3000,
				'numThumbs'=>22,
				'preloadAhead'=>6,
				'enableTopPager'=>true,
				'enableBottomPager'=>false,
				'maxPagesToShow'=>7,
				'renderSSControls'=>true,
				'renderNavControls'=>false,
				'playLinkText'=>'Start slideshow',
				'pauseLinkText'=>'Pause slideshow',
				'nextPageLinkText'=>'Next &rsaquo;',
				'prevPageLinkText'=>'&lsaquo; Prev',
				'enableHistory'=>false,
				'enableKeyboardNavigation'=>true,
				'autoStart'=>true,
				'syncTransitions'=>false,
				'defaultTransitionDuration'=>1500,
				'mouseOutOpacity'=>0.6,
				'cssautofix'=>false,
				'defaultCSS'=>false,
				'flickrAPI'=>'83f1fed4ea8153f9fc3f70be1293166f',
				'flickrThumbsource' => 's',
				'flickrPreviewsource' => '-',
				'flickrResize' =>false,
				'recreateMissing' =>false,
				'flickrCacheValidTime'=>60,
				'flickrDetailedInfo'=>false,
				'imgTitleSource'=>'title',
				'imgCaptionSource'=>'caption',
				'imgAltSource'=>'alttext',
				'imgDescSource'=>'description',
				'enableOpacity'=>false,
				'flickrPrivacyFilter' => 1,
				'html5jsSource' => 1,
				'jsLoadType' => 0,
				/* prev/next link is full of bugs in the JS, these are not yet active
				'prevLinkText'=>'Previous',
				'nextLinkText'=>'Next',*/
			);
			$this->defaults = $defaults;

			$this->options = get_option( WP_GALLERIFFIC_PARAM , $defaults );
		}

		/**
		 * HTML5 JS source selector
		 *
		 * @param $current
		 * 	the active or required identifier
		 *
		 * @param $returntext
		 * 	boolean: is true, the description will be returned of $current size
		 *
		 * @return
		 * 	prints either description of $current
		 * 	or option list for a <select> input field with $current set as active
		 *
		 */
		function html5js_sources ( $current , $returntext = false ) {

			$e = array (
				0 => 'do not load HTML JS  (no pre-IE9 compatibility)',
				1 => 'load Google trunk version',
				2 => 'load locally bundled version (3.3.1)',
			);

			$this->print_select_options ( $e , $current , $returntext );

		}

		/**
		 * JS load type selector
		 *
		 * @param $current
		 * 	the active or required identifier
		 *
		 * @param $returntext
		 * 	boolean: is true, the description will be returned of $current size
		 *
		 * @return
		 * 	prints either description of $current
		 * 	or option list for a <select> input field with $current set as active
		 *
		 */
		function js_positions ( $current , $returntext = false ) {

			$e = array (
				0 => 'always load galleriffic CSS and JS (incl. html5 js)',
				1 => 'load galleriffic CSS and JS only with shortcode (incl. html5 js)',
			);

			$this->print_select_options ( $e , $current , $returntext );

		}

		/**
		 * create the image list from post attachments
		 *
		 */
		function list_images_of_post ( &$post ) {
			$this->images = array();
			$imgid = 0;

			/* get the attachments for current post */
			$attachments = get_children( array (
				'post_parent'=>$post->ID,
				'post_type'=>'attachment',
				'post_mime_type'=>'image',
				'orderby'=>'menu_order',
				'order'=>'asc'
			) );

			if ( !empty($attachments) )
			{
				foreach ( $attachments as $aid => $attachment )
				{
					$img = array();

					$_post = & get_post($aid);

					/* the titles can be set by config */
					$img['title'] = $this->title_sources( $this->options['imgTitleSource'] , $_post );
					$img['alttext'] = $this->title_sources( $this->options['imgAltSource'] , $_post );
					$img['caption'] = $this->title_sources( $this->options['imgCaptionSource'] , $_post );
					$img['description'] = $this->title_sources( $this->options['imgDescSource'] , $_post );

					/* get the image uris */
					$img['preview'] = wp_get_attachment_image_src( $aid, WP_GALLERIFFIC_PREVIEW );
					$img['thumb'] = wp_get_attachment_image_src( $aid, WP_GALLERIFFIC_THUMB );

					/* check for missing images, if enabled */
					if ( $this->options['recreateMissing'] )
					{
						if ( !$img['preview'] || !$img['thumb'] )
						{
							require_once( ABSPATH . '/wp-admin/includes/image.php' );
							wp_generate_attachment_metadata( $aid , get_attached_file( $aid ) );
						}
					}

					$this->images[$imgid] = $img;
					$imgid++;
				}
			}
		}

		/**
		 *
		 */
		function list_images_of_set ( $set ) {
			$this->images = array();
			$imgid = 0;

			$flickrparams = array(
				'api_key'	=> $this->options['flickrAPI'],
				'method'	=> WP_GALLERIFFIC_FLICKR_PHOTOSET,
				'photoset_id'	=> $set,
				'format'	=> WP_GALLERIFFIC_FLICKR_FORMAT,
				'privacy_filter'	=> $this->options['privacyFilter'],
				'media' => 'photos',
			);

			$flickrset = $this->flickr_request ( $flickrparams );

			$replaces = array (
				'%FARM%',
				'%SERVER%',
				'%ID%',
				'%SECRET%'
			);

			foreach ($flickrset['photoset']['photo'] as $item)
			{
				/* lot detailed Flickr data could be required for each image,
				  but it takes ages, so not implemented yet
				*/
				$img['title'] = $item['title'];
				$img['alttext'] = $img['caption'] = $img['description'] = '';

				$replacements = array (
					$item['farm'],
					$item['server'],
					$item['id'],
					$item['secret']
				);

				$base = str_replace ( $replaces, $replacements, WP_GALLERIFFIC_FLICKR_IMGBASE );


				foreach ($this->sizes as $flickrsize )
				{
					$optname = 'flickr_' . $flickrsize . 'source';

					switch ( $this->options[ $optname ] )
					{
						case '-':
							$img[ $flickrsize ][0] = $base . '.jpg';
							break;
						default :
							$img[ $flickrsize ][0] = $base . '_' . $this->options[ $optname ] . '.jpg';
					}
				}

				/* if resize required */
				if ( $this->options['flickrResize'] )
				{
					$img = $this->flickr_cache_image( $img );
				}

				$this->images[$imgid] = $img;
				$imgid++;
			}
		}

		/**
		 * create js param list from options
		 *
		 */
		function list_thumbnails ()
		{
			$thumbs = '';
			foreach ($this->images as $id=>$img)
			{
				if (!empty($img['description']))
					$description = '<span class="thumb-description">'. $img['description'] .'</span>';

				/* don't leave space between the <li></li> elements if you use display:inline-block CSS */
				$thumbs .= '<li>
						<figure>
							<a class="thumb" href="'. $img['preview'][0] .'">
								<img src="'. $img['thumb'][0] .'" title="'. strip_tags($img['title']) .'" alt="'. strip_tags($img['alttext']) . strip_tags($img['description']) .'" />
							</a>
							<figcaption class="caption thumb-caption">'. strip_tags($img['caption']) . $description .'</figcaption>
						</figure>
					</li>';
			}
			return $thumbs;
		}

		function load_js () {

			/* jquery is vital */
			wp_enqueue_script('jquery');

			/*
			 HTML5 fix for the brilliant IE
			 only if enabled (fix for themes using the same js)
			*/
			if ( $this->options['html5jsSource'] != 0 )
			{
				switch ( $this->options['html5jsSource'] )
				{
					/* use trunk version from GoogleCode */
					case 0:
						$s = 'http://html5shim.googlecode.com/svn/trunk/html5.js';
						break;
					/* use bundled version */
					case 1:
						$s = WP_PLUGIN_URL_ . '/js/html5.js';
						break;
				}
				wp_enqueue_script( 'html5.js' , $s , array('jquery') );
			}

			/* history handler JS is only needed when enabled */
			if ( $this->options['enableHistory'])
				wp_enqueue_script( 'jquery.history.js' , WP_GALLERIFFIC_URL . '/js/jquery.history.js' , 'jquery' , '2.0' );

			/* opacity handler JS is only needed when enabled */
			if ( $this->options['enableOpacity'])
				wp_enqueue_script( 'jquery.opacityrollover.js' , WP_GALLERIFFIC_URL . '/js/jquery.opacityrollover.js' , 'jquery' , '2.0' );

			/* main galleriffic script */
			wp_enqueue_script( 'jquery.galleriffic.min.js' , WP_GALLERIFFIC_URL . '/js/jquery.galleriffic.min.js' , array('jquery') , '2.0' );

		}

		/**
		 * create js param list from options
		 *
		 */
		function options_to_js ( $tabs=0 ) {
			$return = false;

			foreach ( $this->options as $key => $value) {
				if ( is_bool ( $this->defaults[$key] ) )
					$value = empty ( $value ) ? 'false' : 'true';
				elseif ( !is_int ( $this->defaults[$key] ) )
					$value = "'" . $value . "'";

				$return .= str_pad( $key . ": " . $value . ",\n", $tabs , "	");
			}

			return $return;
		}

		/**
		 * prints `true` or `false` depending on a bool variable.
		 *
		 * @param $val
		 * 	The boolen variable to print status of.
		 *
		 */
		function print_bool ( $val ) {
			$bool = $val? 'true' : 'false';
			echo $bool;
		}

		/**
		 * select field processor
		 *
		 * @param sizes
		 * 	array to build <option> values of
		 *
		 * @param $current
		 * 	the current resize type
		 *
		 * @param $returntext
		 * 	boolean: is true, the description will be returned of $current type
		 *
		 * @return
		 * 	prints either description of $current
		 * 	or option list for a <select> input field with $current set as active
		 *
		 */
		function print_select_options ( $sizes, $current, $returntext=false ) {

			if ( $returntext )
			{
				_e( $sizes[ $current ] , WP_GALLERIFFIC_PARAM);
				return;
			}

			foreach ($sizes as $ext=>$name)
			{
				?>
				<option value="<?php echo $ext ?>" <?php selected( $ext , $current ); ?>>
					<?php _e( $name , WP_GALLERIFFIC_PARAM); ?>
				</option>
				<?php
			}

		}

		/**
		 * save settings function
		 *
		 */
		function save_settings () {

			/**
			 * update params from $_POST
			 */
			foreach ($this->options as $name=>$optionvalue)
			{
				if (!empty($_POST[$name]))
				{
					$update = $_POST[$name];
					if (strlen($update)!=0 && !is_numeric($update))
						$update = stripslashes($update);
				}
				elseif ( ( empty($_POST[$name]) && is_bool ($this->defaults[$name]) ) || is_numeric( $update ) )
				{
					$update = 0;
				}
				else
				{
					$update = $this->defaults[$name];
				}
				$this->options[$name] = $update;
			}
			update_option( WP_GALLERIFFIC_PARAM , $this->options );
		}

		/**
		 * shortcode function
		 *
		 * @param $atts
		 *	array of passed attributes in shortcode, for example [wp-galleriffic set=ID]
		 *
		 * @param $content
		 * 	optional content between [wp-galleriffic][/wp-galleriffic]
		 *
		 * @return
		 * 	returns with the HTML code to print out
		 */
		function shortcode( $atts ,  $content = null ) {

			if ( $this->options['jsLoadType'] == 1 )
				$this->load_js();

			global $wp_upload_dir;
			extract( shortcode_atts(array(
				'set' => '',
				'postid' => '',
				'galleriffictest' => 0,
			), $atts));

			$images = array();

			/**
			 * normal gallery
			 */
			if ( empty ( $set ) )
			{
				if ( empty ( $postid ) )
				{
					global $post;
				}
				else
				{
					$post = get_post( $postid );
				}
				$this->list_images_of_post ( $post );
				$postid = $post->ID;
			}
			/**
			 * Flickr gallery
			 */
			else
			{
				$this->list_images_of_set ( $set );
				$postid = $set;
			}

			/*
			 * thumbnails creation
			 */
			$thumbs = $this->list_thumbnails();


			/* HTML layout */
			$output = '
			<!-- Begin WP-Galleriffic by Peter Molnar (hello@petermolnar.eu), http://petermolnar.eu/wordpress/wp-galleriffic/  -->
			<section class="wp-galleriffic">
				<nav id="thumbs-'.$postid.'" class="thumbs-container">
					<ul class="thumbs noscript">
					' . $thumbs . '
					</ul>
				</nav>
				<div class="slideshow-container">
					<figure id="slideshow-'.$postid.'" class="slideshow"></figure>
				</div>
				<div class="loading-container">
					<figure id="loading-' . $postid . '" class="loading"></figure>
				</div>
				<div id="controls-' . $postid . '" class="controls"></div>
				<div id="caption-' . $postid . '" class="main-caption"></div>
			</section>
			';

			/* nor the opacity, nor the history handler is enabled by default */
			$onSlideChange = $enableOpacity = $historyEnabled = '';

			if ($this->options['enableOpacity'])
			{
				/* the event for slide change, used in Galleriffic JS */
				$onSlideChange = "
					onSlideChange: function(prevIndex, nextIndex) {
						this.find('ul').children()
							.eq(prevIndex).fadeTo('fast', onMouseOutOpacity).end()
							.eq(nextIndex).fadeTo('fast', 1.0);
					}";

				/* opacityrollover JS initiator */
				$enableOpacity = "
					var onMouseOutOpacity = ". $this->options['mouseOutOpacity'] ."
					$('.thumbs-container ul li').opacityrollover({
						mouseOutOpacity: onMouseOutOpacity,
						mouseOverOpacity: 1.0,
						fadeSpeed: 'fast',
						exemptionSelector: '.selected'
					});";
			}

			if ($this->options['enableHistory'])
			{
				/* JS history initiator */
				$historyEnabled = "
					$.historyInit(pageload, 'advanced.html');

					$(\"a[rel='history']\").live('click', function(e) {
						if (e.button != 0) return true;
						var hash = this.href;
						hash = hash.replace(/^.*#/, '');
						$.historyLoad(hash);
						return false;
					});";
			}

			$output .= "
				<script type='text/javascript'>
					jQuery(document).ready(function($) {
						var gallery = $('#thumbs-" . $postid . "').galleriffic({
							". $this->options_to_js(6) . "
							imageContainerSel: '#slideshow-" . $postid . "',
							controlsContainerSel: '#controls-" . $postid . "',
							captionContainerSel: '#caption-" . $postid . "',
							loadingContainerSel: '#loading-" . $postid . "',
							" . $onSlideChange . "
						});

						function pageload(hash) {
							if(hash) {
								$.galleriffic.gotoImage(hash);
							} else {
								gallery.gotoIndex(0);
							}
						}

						". $enableOpacity ."
						". $historyEnabled ."
			";

			/**
			 * this is best-practise. I've tested a lot, played a lot with the numbers, but
			 * this fixed the image duplication for me in all the cases I could create.
			 * No, I don't have explanation for the multiplication, it just has to be there.
			 */
			if ( $this->options['cssautofix'] )
			{
				$width = $this->options['imgSize'];
				$height = $width = round($this->options['imgSize'] * 1.06);

				$output .= '
						$(".slideshow,.loading").css({
							"line-height" : "' . $height . 'px",
							"width" : "' . $width . 'px",
							"height" : "' . $height . 'px",
						});
						$(".slideshow img,.loading img").css({
							"vertical-align" : "middle",
						});
					';
			}

			$output .= "
					});
				</script>
			<!-- End WP-Galleriffic  -->
			";

			return $output;
		}

		/**
		 * Title source selection
		 *
		 * @param $current
		 * 	the active or required identifier
		 *
		 * @param $action
		 * 	special element, can be 'false', 'true' or pointer to a WP post's data
		 *
		 * @return
		 * 	prints either description of $current
		 * 	or option list for a <select> input field with $current set as active
		 *
		 */
		function title_sources ( &$current , $action = false ) {

			/* select data from a post for an element */
			if ( !( $action === false || $action === true ) )
			{
				//$sources['title'] = attribute_escape($action->post_title);
				//$sources['alttext'] = get_post_meta($action->id, '_wp_attachment_image_alt', true);
				//$sources['caption'] = attribute_escape($action->post_excerpt);
				//$sources['description'] = attribute_escape($action->post_content);

				switch ( $current )
				{
					case 'title':
						$val = attribute_escape($action->post_title);
						break;
					case 'alttext':
						$val = get_post_meta($action->id, '_wp_attachment_image_alt', true);
						break;
					case 'caption':
						$val = attribute_escape($action->post_excerpt);
						break;
					case 'description':
						$val = attribute_escape($action->post_content);
						break;
					default:
						$val = '';
						break;
				}

				return $val;
			}
			else
			{
				$e['empty'] = 'leave it empty';
				$e['title'] = 'use image title';
				$e['alttext'] = 'use image alttext';
				$e['caption'] = 'use image caption';
				$e['description'] = 'use image description';

				$this->print_select_options ( $e , $current , $action );

				return true;

			}

		}

		/**
		 * clean up at uninstall
		 *
		 */
		function uninstall ( ) {
			delete_option( WP_GALLERIFFIC_PARAM );
		}

	}
}

/**
 * instantiate the class
 */
$wp_galleriffic = new WPGalleriffic();


?>
