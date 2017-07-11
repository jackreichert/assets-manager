=== Assets Manager ===
Contributors: jackreichert
Donate link: http://www.jackreichert.com/buy-me-a-beer/
Tags: uploads, file sharing, file management, asset management, assets, share file, content, links, admin, social
Requires at least: 3.7
Tested up to: 4.7
Stable tag: trunk
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Assets Manager for WordPress is a self hosted file sharing tool,  enable / disable links, set expiration and make files you share password protected.

== Description ==

Assets Manager is a self-hosted file sharing tool. Born out of the need for a file sharing tool that was not blocked by high security firewalls, such as many existing file sharing services are, Assets Manager was developed. When you upload a file, or set of files, Assets Manager generates obscured links to the files so that you can control how those files are shared.

[Here’s how it works.](http://www.jackreichert.com/2015/11/15/how-assets-manager-replaced-our-sharefile/)

= Features =
* Set an expiration period for when the file link will expire.
* Disable links after they've been shared (no more fretting when sending out emails).
* Force anyone trying to access a link to log into your site.
* Creates landing page for each asset post type collecting files uploaded together into one link.

For more information check out the full blogpost about [Assets Manager](http://www.jackreichert.com/2014/01/12/introducing-assets-manager-for-wordpress/).
Questions? Comments? Requests? [Contact me](http://www.jackreichert.com/contact/).

== Installation ==

1. Upload `plugin-name.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Resave your 'Pretty Permalinks' structure under "Settings > Permalinks".

= To create an asset set =

1. Under Assets Manager menu 'Add New'
2. Drag files to upload to where it says 'Drop files here' or select files to upload.
3. Select settings for each file.
4. Add a title (**note:** you will not be able to upload without a title).
5. Hit upload.
6. Publish post (**note:** links will not be available until the Asset Set as been published).

== Frequently Asked Questions ==

= Why would I want to disable a link? =

Let's say there is incorrect information in the file, or there is an updated version, now you can disable the link sent out, shared, published and send out a new one.

= Are these files searchable? =

The asset sets are blocked from being searchable in WordPress. This means that global searches of the site will not bring up any of the uploaded assets. This does not block search engines from finding them if they are linked to from someplace else. But it does make finding files harder if you do not have a direct link to the file.

= Can I upload a bunch of files and share them all with one link? =

Yes you can. Assets Manager generates a page that contains all of the links in the asset set. This page can then be shared. Note: if you have disabled or expired files they will not be listed on this page, if a file is "secure" and the visitor is not logged in the file link will not appear as well.

= Can I reorder the list of files on the public facing assets set page? =

Sure, just drag and drop. No need to save. All reordering happens via AJAX automatically.

= Does this work with nginx? =

Sure, in some installs you may need to add this to your conf file, I needed this to serve images correctly:
`location ~ ^/asset/(.*)$ {
	try_files $uri $uri/ /index.php?$query_string;
}`

= What about foo bar? =

Answer to foo bar dilemma.

== Screenshots ==

1. Add a title.
2. Add new files.
3. Attach files.
4. Publish.
5. Share.

== Changelog ==
= 1.0.2 =
* Fixed issue with hooks firing when they shouldn’t.

= 1.0.1 =
* Improved error handling for fopen in case file was deleted.

= 1.0 =
* Complete refactor of entire codebase.
* Leverages a better object oriented architecture, fopen, and wp.media.
* You can now change the asset base permalink.

= 0.6.2 =
Fixed js typo that was preventing reordering feature

= 0.6.1 =
Added period before file download extension

= 0.6 =
Implemented a better way to serve files

= 0.5 =
Fixed ssl issues

= 0.4 =
Fixed issue where filename was not filename chosen

= 0.3 =
Fixed issue where period was replacing wrong text

= 0.2.9 =
Removed style that hides .nav-links

= 0.2.8 =
Fixed additional HR added to posts (props @AEsco11)

= 0.2.7 =
* Refactored file serving to handled certain extensions that were buggy. (props @AEsco11)

= 0.2.6 =
* Tested up to 4.0
* Added flush_rewrite_rules() to prevent need for re-saving permalinks on activate

= 0.2.5 =
* Changed action to prevent "headers sent" error

= 0.2.4 =
* Removed echo to prevent "headers sent" error

= 0.2.3 =
* Changed action to prevent "headers sent" error

= 0.2.2 =
* Changed priority for action to prevent "headers sent" error

= 0.2.1 =

* php bugfix, compatable with 3.9

= 0.2 =

* Bug js and php bug fixes

= 0.1 =

* This is the first version.

== Upgrade Notice ==

* email assets (at) jackreichert (dot) com if you notice any issues upgrading.

== Thanks ==
Special thanks to @binmind for his extensive QA testing of the company’s plugin, his testing was crucial for development of the proof of concept and making sure everything was working as it should.
