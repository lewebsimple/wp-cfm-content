=== WP-CFM Content ===
Contributors: lewebsimple
Tags: configuration, settings, configuration management, features, wordpress, wp-cli
Requires at least: 4.7
Tested up to: 5.8.2
Requires PHP: 5.6
Stable tag: 0.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Manage and deploy WordPress content changes with WP-CFM.

== Description ==

WP-CFM Content lets you copy content to / from the filesystem. Easily deploy content changes without needing to copy the entire database.

= Use at your own risk! =

This plugin isn't meant to be used for migrating content from one site to another as is expects post & term IDs to be matching.

== Changelog ==

= 0.2.0 =
* Deprecated "wpcfm_content/enabled"
* Added "wpcfm_content/post_type/enabled" / "wpcfm_content/post_type/enabled/${post_type}" filters
* Added "wpcfm_content/terms/enabled" / "wpcfm_content/terms/enabled/${taxonomy}" filters

= 0.1.2 =
* Fix SQL errors in postmeta query with esc_sql

= 0.1.1 =
* Add composer.json

= 0.1.0 =
* Bundle all posts, postmeta and terms by post type
