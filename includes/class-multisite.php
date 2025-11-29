<?php
/**
 * Multisite detection and helper functions
 *
 * @package Advanced_Post_Duplicator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Multisite helper class
 */
class APD_Multisite {

	/**
	 * Check if WordPress Multisite is enabled
	 *
	 * @return bool
	 */
	public static function is_multisite() {
		return is_multisite();
	}

	/**
	 * Check if current user has permission to perform cross-site duplication
	 *
	 * @return bool
	 */
	public static function user_can_cross_site_duplicate() {
		if ( ! self::is_multisite() ) {
			return false;
		}

		// Super admins can always duplicate
		if ( is_super_admin() ) {
			return true;
		}

		// Site admins need network access capability
		return current_user_can( 'manage_network' );
	}

	/**
	 * Get all sites in the network
	 *
	 * @param bool $include_current Whether to include current site
	 * @return array Array of site objects with id, blogname, domain, path
	 */
	public static function get_network_sites( $include_current = true ) {
		if ( ! self::is_multisite() ) {
			return array();
		}

		$args = array(
			'number' => 999,
			'archived' => 0,
			'spam' => 0,
			'deleted' => 0,
		);

		$sites = get_sites( $args );
		$result = array();

		$current_blog_id = get_current_blog_id();

		foreach ( $sites as $site ) {
			$site_id = (int) $site->blog_id;

			// Skip current site if requested
			if ( ! $include_current && $site_id === $current_blog_id ) {
				continue;
			}

			switch_to_blog( $site_id );

			$result[] = array(
				'id'       => $site_id,
				'blogname' => get_option( 'blogname' ),
				'domain'   => $site->domain,
				'path'     => $site->path,
				'site_url' => get_site_url( $site_id ),
				'admin_url' => get_admin_url( $site_id ),
			);

			restore_current_blog();
		}

		return $result;
	}

	/**
	 * Get site information by ID
	 *
	 * @param int $site_id Site ID
	 * @return array|false Site information or false if not found
	 */
	public static function get_site_info( $site_id ) {
		if ( ! self::is_multisite() ) {
			return false;
		}

		$site = get_site( $site_id );
		if ( ! $site ) {
			return false;
		}

		switch_to_blog( $site_id );

		$info = array(
			'id'       => (int) $site_id,
			'blogname' => get_option( 'blogname' ),
			'domain'   => $site->domain,
			'path'     => $site->path,
			'site_url' => get_site_url( $site_id ),
			'admin_url' => get_admin_url( $site_id ),
		);

		restore_current_blog();

		return $info;
	}

	/**
	 * Check if plugin is network activated
	 *
	 * @return bool
	 */
	public static function is_network_activated() {
		if ( ! self::is_multisite() ) {
			return false;
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		return is_plugin_active_for_network( APD_PLUGIN_BASENAME );
	}

	/**
	 * Switch to a site and execute a callback
	 *
	 * @param int      $site_id  Site ID to switch to
	 * @param callable $callback Callback function to execute
	 * @return mixed Result of callback
	 */
	public static function switch_and_execute( $site_id, $callback ) {
		if ( ! self::is_multisite() ) {
			return false;
		}

		$current_blog_id = get_current_blog_id();

		switch_to_blog( $site_id );

		$result = call_user_func( $callback );

		restore_current_blog();

		return $result;
	}

	/**
	 * Get current site ID
	 *
	 * @return int Current site/blog ID
	 */
	public static function get_current_site_id() {
		return get_current_blog_id();
	}
}

