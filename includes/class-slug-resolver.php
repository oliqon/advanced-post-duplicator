<?php
/**
 * Slug conflict resolution class
 *
 * @package Advanced_Post_Duplicator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slug resolver class
 */
class APD_Slug_Resolver {

	/**
	 * Resolve slug conflicts for a post
	 *
	 * @param string $slug Original slug
	 * @param int    $destination_site_id Destination site ID
	 * @param string $post_type Post type
	 * @param int    $exclude_post_id Post ID to exclude from conflict check (for updates)
	 * @return string Unique slug
	 */
	public static function resolve_slug( $slug, $destination_site_id, $post_type, $exclude_post_id = 0 ) {
		$original_slug = $slug;
		$counter = 1;

		while ( self::slug_exists( $slug, $destination_site_id, $post_type, $exclude_post_id ) ) {
			$slug = $original_slug . '-copy-' . $counter;
			$counter++;

			// Safety limit to prevent infinite loops
			if ( $counter > 100 ) {
				$slug = $original_slug . '-copy-' . time();
				break;
			}
		}

		return $slug;
	}

	/**
	 * Check if a slug exists on the destination site
	 *
	 * @param string $slug Post slug to check
	 * @param int    $site_id Site ID
	 * @param string $post_type Post type
	 * @param int    $exclude_post_id Post ID to exclude
	 * @return bool True if slug exists
	 */
	private static function slug_exists( $slug, $site_id, $post_type, $exclude_post_id = 0 ) {
		return APD_Multisite::switch_and_execute(
			$site_id,
			function() use ( $slug, $post_type, $exclude_post_id ) {
				global $wpdb;

				$query = $wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} 
					WHERE post_name = %s 
					AND post_type = %s 
					AND post_status != 'trash'",
					$slug,
					$post_type
				);

				if ( $exclude_post_id > 0 ) {
					$query .= $wpdb->prepare( ' AND ID != %d', $exclude_post_id );
				}

				$existing = $wpdb->get_var( $query );

				return ! empty( $existing );
			}
		);
	}

	/**
	 * Generate unique slug with custom suffix
	 *
	 * @param string $slug Original slug
	 * @param string $suffix Custom suffix (e.g., '-migrated')
	 * @param int    $destination_site_id Destination site ID
	 * @param string $post_type Post type
	 * @return string Unique slug
	 */
	public static function resolve_slug_with_suffix( $slug, $suffix, $destination_site_id, $post_type ) {
		$new_slug = $slug . $suffix;

		if ( ! self::slug_exists( $new_slug, $destination_site_id, $post_type ) ) {
			return $new_slug;
		}

		// If suffix version exists, add counter
		$counter = 1;
		while ( self::slug_exists( $new_slug . '-' . $counter, $destination_site_id, $post_type ) ) {
			$counter++;
			if ( $counter > 100 ) {
				return $slug . $suffix . '-' . time();
			}
		}

		return $new_slug . '-' . $counter;
	}
}

