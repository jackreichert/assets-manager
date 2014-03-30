=== Assets Manager ===
Contributors: jackreichert
Donate link: http://www.jackreichert.com/the-human-fund/
Tags: uploads, file share, file management, asset management, assets
Requires at least: 3.5
Tested up to: 3.8
Stable tag: trunk
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Assets Manager for WordPress is a self hosted file sharing tool,  enable / disable links, set expiration and make files you share password protected.

== Description ==

Assets Manager is a self-hosted file sharing tool. Born out of the need for a file sharing tool that was not blocked by high security firewalls, such as many existing file sharing services are, Assets Manager was developed. When you upload a file, or set of files, Assets Manager generates obscured links to the files so that you can control how those files are shared.

= Features =
* Set an exiration period for when the file link will expire.
* Disable links after they've been shared (no more fretting when sending out emails).
* Force anyone trying to access a link to log into your site.

For more information check out the full blogpost about [Assets Manager](http://www.jackreichert.com/2014/01/12/introducing-assets-manager-for-wordpress/). 
Questions? Comments? Requests? [Contact me](http://www.jackreichert.com/contact/).

== Installation ==

1. Upload `plugin-name.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Resave your 'Pretty Permalinks' structure under "Settings > Permalinks".

= To create an asset set =

1. Under Assets Manager menu 'Add New'
2. Drag files to upload to where it says 'Drop fiels here' or select files to upload.
3. Select settings for each file.
4. Add a title (**note:** you will not be able to upload without a title).
5. Hit upload.
6. Publish post (**note:** links will not be available until the Asset Set as been published).

== Frequently Asked Questions ==

= Why aren't the links working? =

You may need to reset the permalinks by going to Settings > Permalinks and pressing the "Save Changes" button.

= Why would I want to diable a link? =

Let's say there is incorrect information in the file, or there is an updated version, now you can disable the link sent out, shared, published and send out a new one.


= Are these files searchable? =

The asset sets are blocked from being searchable in WordPress. This means that global searches of the site will not bring up any of the uploaded assets. This does not block search engines from finding them if they are linked to from someplace else. But it does make finding files harder if you do not have a direct link to the file.

= Can I upload a bunch of files and share them all with one link? =

Yes you can. Assets Manager generates a page that contains all of the links in the asset set. This page can then be shared. Note: if you have disabled or expired files they will not be listed on this page, if a file is "secure" and the visitor is not logged in the file link will not appear as well.

= Can I reorder the list of files on the public facing assets set page? =

Sure, just drag and drop. No need to save. All reordering happens via AJAX automatically.

= What about foo bar? =

Answer to foo bar dilemma.

== Screenshots ==

1. Add a title.
2. Add new files.
3. Upload.
4. Publish.
5. Share.

== Changelog ==

= 0.2 =

* Bug js and php bug fixes

= 0.1 =

* This is the first version.

== Upgrade Notice ==

= 0.1 = 

* This is the first version.

== Roadmap ==

= Future features I'm working on: =

* **Sha1:** If you upload a file that already exists it will link that file to your post instead of keeping multiple versions of the file.
* **File replacement:** After uploading and even sharing a file you'll be able to replace the file behind the active link with a file of the same MIME type.

Special thanks to @binmind for his extensive QA testing of the company’s plugin, his testing was crucial for development of the proof of concept and making sure everything was working as it should.