=== WP-Galleriffic ===
Contributors: cadeyrn
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=8LZ66LGFLMKJW&lc=HU&item_name=Peter%20Molnar%20photographer%2fdeveloper&item_number=petermolnar%2dpaypal%2ddonation&currency_code=USD&bn=PP%2dDonationsBF%3acredit%2epng%3aNonHosted
Tags: jQuery, gallery, flickr, galleriffic
Requires at least: 3.0
Tested up to: 3.4
Stable tag: 1.3.1

WordPress attachment / Flickr image gallery based on Galleriffic JS.


== Description ==
WP-Galleriffic is an image gallery plugin to extend WordPress. The JavaScript code is based on [Galleriffic](http://www.twospy.com/galleriffic/ "Galleriffic"), but modified at some small points for bugfix.

It started as a fork of [Photospace](http://thriveweb.com.au/blog/photospace-wordpress-gallery-plugin/ "Photospace") plugin (v1), but it became very different approach.

There are two ways to use the plugin:

= 1. Attachment image gallery =
The plugin adds two additional image sizes using the built-in functions of WordPress. The sizes can be modified at the plugin's settings panel. The missing images be generated on-demand (for example: images uploaded before the plugin was installed or the sizes have been modified), but for large quantities I'd recommend the [Regenerate Thumbnails](http://wordpress.org/extend/plugins/regenerate-thumbnails/ "Regenerate Thumbnails") plugin.
Versions before 1.0 used an own cache and image resizing module, but this has been eliminated due to better potentials in the core WordPress code, better Multisite compatibility, faster processing and cleaner filesystem.

= 2. Flickr Set gallery =
Flick Set can be added as source to a galleriffic shortcode. An API key is bundled with the plugin, registered for this purpose, but you can change it. It is vital, Flick requires it for the API to work. The display order is the same in Flickr. There is a chance to select the source Flickr image for both the thumbnail and the preview image. Also, if required, the Flickr images can be resized as well with a local copy of the image. The plugin grabs the images from Flickr, resizes them and serves locally.

* Note: if you change the size settings, the previously generated images ones will not be deleted. You have to make a cleanup manually. *

= Usage =
Place `[wp-galleriffic]` shortcode into anywhere in the post for attachment gallery.

For a specified post, add `[wp-galleriffic postid=POST#]`, where POST# is the number of the post with the attachment images to create a gallery from.
For Flickr gallery, add the set ID as: `[wp-galleriffic set=SET#]`, where SET# is the ID of the set. You can get it by navigating to the Set's page at Flickr and take a look at the end of the URL.

== Installation ==
1. Upload contents of `wp-galleriffic.zip` to the `/wp-content/plugins/` directory
2. Activate the plugin through the `Plugins` menu in WordPress
3. Fine tune the settings in `Settings` -> `wp-galleriffic` menu in WordPress

== Frequently Asked Questions ==

= Where can I see a working example? =

My own portfolio uses this plugin, with a modified CSS: [petermolnar.eu](http://petermolnar.eu/ "petermolnar.eu")

= Why are the next / previous image buttons left out?  =

The next/prev buttons are working really buggy this time, and it's so deep inside the JS code I did not have time to fix it yet.

== Screenshots ==

1. Screenshow with `default CSS` enabled ( pictures are copyrighted by the author of the plugin ) * updated with version 1.1 *

== Upgrade Notice ==

= 1.2 =

Default CSS has been modified: added default 0.6 CSS opacity to thumbnails along with CSS3 transitions.

= 1.1 =
*WARNING!*

1.1 is a mayor upgrade.
From version 1.1, the rendered HTML structure was changed and HTML5 elements were added.
*Your modified layouts will very likely not work with this version.*

== Changelog ==

= 1.3.1 =
2012.12.06

* bugfix for PHP 5.4

= 1.3 =
2012.01.22

* added support for selecting Flickr privacy level of images to show private pictures as well

= 1.2 =
2012.01.03

* added possibility to select image title, description, alt and figcaption text from the image data
* opacity handler jQuery extension is now disabled by default, the effect is handled by CSS3 transitions in the default CSS (reason: code was buggy and ate more CPU)

= 1.1 =
2011.12.27

* added shortcode option `postid`, as for example: postid=1990, lists the attachment images into gallery from the specified post
* HTML structure change in order to use more than one gallery per page/post
* HTML5 layout elements added
* refactored `default CSS`
* enqueued [HTML5Shim](http://html5shim.googlecode.com/svn/trunk/html5.js "HTML5Shim") for IE<9 to render HTML5 correctly
* update in `CSS fix` (unneeded parts removed, only the most important left in)
* missing images recreation now disabled by default, can be enabled with an option
* bugfixes ( default values, CSS fix, etc.)
* removed `render caption` option (actually never existed in galleriffic JS, it was a mistake by me :) )

= 1.0 =
2011.11.28

* function-based structure converted to Class-based structure.
* redesigned image processing for Flickr cache
* removed crop function from Flickr resampling: removed "border" settings, removed sharpening
* local attachment images generated with WordPress core functions next to the same places as built-in files
* new readme.txt
* added tons'o comments :)

= 0.4 =
2011.09.24

* First public release
* Duplicated image fix is solved with “enable CSS fix”
* Duplicated caption fix is solved with “this.$captionContainer.empty();”
* Added default CSS

= 0.3 =
2011.09.21.

* Admin panel redesign
* Added “CSS fix” parameter
* Added border color fields and support
* Modifications in default parameter handling
* Added cache flushing on settings change

= 0.2 =
2010.12.01.

* Added Flickr set support.

= 0.1 =
2010.10.20.

* Initial release
