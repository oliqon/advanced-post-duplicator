<?php
/**
 * Uninstall script for Advanced Post Duplicator
 *
 * This file is executed when the plugin is uninstalled.
 *
 * @package Advanced_Post_Duplicator
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options
delete_option( 'apd_settings' );
delete_option( 'apd_cross_site_logs' );

// For multisite, delete network options
if ( is_multisite() ) {
	delete_site_option( 'apd_settings' );
	delete_site_option( 'apd_cross_site_logs' );
}

// Note: We do NOT delete the _apd_duplicated_from meta keys from posts
// as users may want to keep track of which posts were duplicated.
// If you want to remove these, uncomment the following code:

/*
global $wpdb;

// Delete meta keys from all posts
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s)",
		'_apd_duplicated_from',
		'_apd_duplicated_from_site'
	)
);
*/

