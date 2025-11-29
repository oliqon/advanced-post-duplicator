<?php
/**
 * Core duplication logic
 *
 * @package Advanced_Post_Duplicator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Duplicator class
 */
class APD_Duplicator {

	/**
	 * Duplicate a post
	 *
	 * @param int $post_id Post ID to duplicate
	 * @return int|WP_Error New post ID on success, WP_Error on failure
	 */
	public static function duplicate( $post_id ) {
		// Verify post exists
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post not found.', 'advanced-post-duplicator' ) );
		}

		// Get settings
		$settings = get_option( 'apd_settings', array() );

		// Prepare post data
		$post_data = array(
			'post_title'   => self::get_duplicate_title( $post->post_title ),
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_status'  => self::get_duplicate_status( $post->post_status, $settings ),
			'post_type'    => $post->post_type,
			'post_author'  => get_current_user_id(),
			'post_date'    => self::get_duplicate_date( $post->post_date, $settings ),
		);

		// Insert the duplicate post
		$new_post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $new_post_id ) ) {
			return $new_post_id;
		}

		// Copy post meta
		self::copy_post_meta( $post_id, $new_post_id );

		// Copy taxonomies
		self::copy_taxonomies( $post_id, $new_post_id, $post->post_type );

		// Copy featured image
		self::copy_featured_image( $post_id, $new_post_id );

		// Store reference to original post
		update_post_meta( $new_post_id, '_apd_duplicated_from', $post_id );

		// Action hook for other plugins to extend
		do_action( 'apd_after_duplicate', $new_post_id, $post_id );

		return $new_post_id;
	}

	/**
	 * Get duplicate post title
	 *
	 * @param string $original_title Original post title
	 * @return string New post title
	 */
	private static function get_duplicate_title( $original_title ) {
		return $original_title . ' ' . __( '(Copy)', 'advanced-post-duplicator' );
	}

	/**
	 * Get duplicate post status
	 *
	 * @param string $original_status Original post status
	 * @param array  $settings Plugin settings
	 * @return string New post status
	 */
	private static function get_duplicate_status( $original_status, $settings ) {
		$status_setting = isset( $settings['post_status'] ) ? $settings['post_status'] : 'same';

		if ( 'same' === $status_setting ) {
			return $original_status;
		}

		return $status_setting;
	}

	/**
	 * Get duplicate post date
	 *
	 * @param string $original_date Original post date
	 * @param array  $settings Plugin settings
	 * @return string New post date
	 */
	private static function get_duplicate_date( $original_date, $settings ) {
		$date_setting = isset( $settings['post_date'] ) ? $settings['post_date'] : 'duplicate';

		if ( 'current' === $date_setting ) {
			$date = current_time( 'mysql' );
		} else {
			$date = $original_date;
		}

		// Apply offset if enabled
		if ( isset( $settings['offset_date'] ) && $settings['offset_date'] ) {
			$offset_direction = isset( $settings['offset_direction'] ) ? $settings['offset_direction'] : 'older';
			$offset_days      = isset( $settings['offset_days'] ) ? (int) $settings['offset_days'] : 0;
			$offset_hours     = isset( $settings['offset_hours'] ) ? (int) $settings['offset_hours'] : 0;
			$offset_minutes   = isset( $settings['offset_minutes'] ) ? (int) $settings['offset_minutes'] : 0;
			$offset_seconds   = isset( $settings['offset_seconds'] ) ? (int) $settings['offset_seconds'] : 0;

			$timestamp = strtotime( $date );
			$offset    = ( $offset_days * DAY_IN_SECONDS ) +
						( $offset_hours * HOUR_IN_SECONDS ) +
						( $offset_minutes * MINUTE_IN_SECONDS ) +
						$offset_seconds;

			if ( 'older' === $offset_direction ) {
				$timestamp -= $offset;
			} else {
				$timestamp += $offset;
			}

			$date = date( 'Y-m-d H:i:s', $timestamp );
		}

		return $date;
	}

	/**
	 * Copy post meta from original to duplicate
	 *
	 * @param int $original_id Original post ID
	 * @param int $duplicate_id Duplicate post ID
	 */
	private static function copy_post_meta( $original_id, $duplicate_id ) {
		$meta_keys = get_post_meta( $original_id );
		
		// Meta keys to skip during duplication
		$skip_keys = array(
			'_apd_duplicated_from',
			'_edit_lock',
			'_edit_last',
			'_wp_old_slug',
			'_wp_page_template', // Will be copied separately if needed
		);

		// Allow filtering of skip keys
		$skip_keys = apply_filters( 'apd_skip_meta_keys', $skip_keys, $original_id, $duplicate_id );
		
		foreach ( $meta_keys as $key => $values ) {
			// Skip certain meta keys
			if ( in_array( $key, $skip_keys, true ) ) {
				continue;
			}

			foreach ( $values as $value ) {
				$value = maybe_unserialize( $value );
				
				// Handle serialized data with post ID references (like Elementor templates)
				$value = self::update_meta_references( $value, $original_id, $duplicate_id, $key );
				
				add_post_meta( $duplicate_id, $key, $value );
			}
		}

		// Copy page template for pages
		$template = get_page_template_slug( $original_id );
		if ( $template ) {
			update_post_meta( $duplicate_id, '_wp_page_template', $template );
		}

		// WooCommerce specific: Reset product stock and SKU
		$post = get_post( $original_id );
		if ( 'product' === $post->post_type && class_exists( 'WooCommerce' ) ) {
			self::handle_woocommerce_product( $original_id, $duplicate_id );
		}

		// Elementor specific: Update template references
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			self::handle_elementor_data( $original_id, $duplicate_id );
		}
	}

	/**
	 * Update meta value references (like Elementor templates)
	 *
	 * @param mixed  $value Meta value
	 * @param int    $original_id Original post ID
	 * @param int    $duplicate_id Duplicate post ID
	 * @param string $meta_key Meta key
	 * @return mixed Updated value
	 */
	private static function update_meta_references( $value, $original_id, $duplicate_id, $meta_key ) {
		// Handle arrays recursively
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = self::update_meta_references( $v, $original_id, $duplicate_id, $meta_key );
			}
		} elseif ( is_string( $value ) ) {
			// Replace post ID references in strings (useful for Elementor template IDs)
			$value = str_replace( $original_id, $duplicate_id, $value );
		}

		return $value;
	}

	/**
	 * Handle WooCommerce product specific duplication
	 *
	 * @param int $original_id Original product ID
	 * @param int $duplicate_id Duplicate product ID
	 */
	private static function handle_woocommerce_product( $original_id, $duplicate_id ) {
		// Get original SKU
		$original_sku = get_post_meta( $original_id, '_sku', true );
		
		// Generate new unique SKU
		if ( $original_sku ) {
			$new_sku = $original_sku . '-copy-' . time();
			
			// Ensure SKU is unique
			$counter = 1;
			$sku_exists = true;
			
			// Check if SKU already exists (WooCommerce way)
			if ( function_exists( 'wc_product_has_unique_sku' ) ) {
				$sku_exists = ! wc_product_has_unique_sku( $duplicate_id, $new_sku );
			} else {
				// Fallback: manual check
				global $wpdb;
				$existing_id = $wpdb->get_var( $wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} 
					WHERE meta_key = '_sku' AND meta_value = %s AND post_id != %d",
					$new_sku,
					$duplicate_id
				) );
				$sku_exists = ! empty( $existing_id );
			}
			
			while ( $sku_exists ) {
				$new_sku = $original_sku . '-copy-' . time() . '-' . $counter;
				
				if ( function_exists( 'wc_product_has_unique_sku' ) ) {
					$sku_exists = ! wc_product_has_unique_sku( $duplicate_id, $new_sku );
				} else {
					global $wpdb;
					$existing_id = $wpdb->get_var( $wpdb->prepare(
						"SELECT post_id FROM {$wpdb->postmeta} 
						WHERE meta_key = '_sku' AND meta_value = %s AND post_id != %d",
						$new_sku,
						$duplicate_id
					) );
					$sku_exists = ! empty( $existing_id );
				}
				
				$counter++;
				
				// Safety limit to prevent infinite loop
				if ( $counter > 100 ) {
					$new_sku = $original_sku . '-copy-' . uniqid();
					break;
				}
			}
			
			update_post_meta( $duplicate_id, '_sku', $new_sku );
		}

		// Reset stock management for duplicated product
		$manage_stock = get_post_meta( $original_id, '_manage_stock', true );
		if ( 'yes' === $manage_stock ) {
			// Reset stock quantity
			update_post_meta( $duplicate_id, '_stock', '0' );
			update_post_meta( $duplicate_id, '_stock_status', 'outofstock' );
		}

		// Reset download counts and permissions
		delete_post_meta( $duplicate_id, '_download_count' );
		
		// Copy product attributes
		$attributes = get_post_meta( $original_id, '_product_attributes', true );
		if ( $attributes ) {
			update_post_meta( $duplicate_id, '_product_attributes', $attributes );
		}

		// Copy product gallery
		$gallery = get_post_meta( $original_id, '_product_image_gallery', true );
		if ( $gallery ) {
			update_post_meta( $duplicate_id, '_product_image_gallery', $gallery );
		}

		// Copy linked products
		$upsell_ids = get_post_meta( $original_id, '_upsell_ids', true );
		if ( $upsell_ids ) {
			update_post_meta( $duplicate_id, '_upsell_ids', $upsell_ids );
		}

		$crosssell_ids = get_post_meta( $original_id, '_crosssell_ids', true );
		if ( $crosssell_ids ) {
			update_post_meta( $duplicate_id, '_crosssell_ids', $crosssell_ids );
		}

		// Note: Product variations need to be handled separately via WooCommerce hooks
		do_action( 'apd_after_duplicate_woocommerce_product', $duplicate_id, $original_id );
	}

	/**
	 * Handle Elementor page builder data
	 *
	 * @param int $original_id Original post ID
	 * @param int $duplicate_id Duplicate post ID
	 */
	private static function handle_elementor_data( $original_id, $duplicate_id ) {
		// Elementor stores page builder data in _elementor_data meta
		$elementor_data = get_post_meta( $original_id, '_elementor_data', true );
		
		if ( $elementor_data ) {
			// Update any template IDs or widget IDs that reference the original post
			if ( is_string( $elementor_data ) ) {
				$elementor_data = json_decode( $elementor_data, true );
			}
			
			if ( is_array( $elementor_data ) ) {
				$elementor_data = self::update_elementor_references( $elementor_data, $original_id, $duplicate_id );
				
				// Save updated Elementor data
				update_post_meta( $duplicate_id, '_elementor_data', wp_slash( wp_json_encode( $elementor_data ) ) );
			} else {
				// If it's still a string, just update simple references
				update_post_meta( $duplicate_id, '_elementor_data', $elementor_data );
			}
		}

		// Copy Elementor page settings
		$elementor_page_settings = get_post_meta( $original_id, '_elementor_page_settings', true );
		if ( $elementor_page_settings ) {
			update_post_meta( $duplicate_id, '_elementor_page_settings', $elementor_page_settings );
		}

		// Copy Elementor template type
		$elementor_template_type = get_post_meta( $original_id, '_elementor_template_type', true );
		if ( $elementor_template_type ) {
			update_post_meta( $duplicate_id, '_elementor_template_type', $elementor_template_type );
		}

		// Copy Elementor version
		$elementor_version = get_post_meta( $original_id, '_elementor_version', true );
		if ( $elementor_version ) {
			update_post_meta( $duplicate_id, '_elementor_version', $elementor_version );
		}

		// Copy Elementor edit mode
		$elementor_edit_mode = get_post_meta( $original_id, '_elementor_edit_mode', true );
		if ( $elementor_edit_mode ) {
			update_post_meta( $duplicate_id, '_elementor_edit_mode', $elementor_edit_mode );
		}

		do_action( 'apd_after_duplicate_elementor', $duplicate_id, $original_id );
	}

	/**
	 * Recursively update Elementor widget references
	 *
	 * @param array $data Elementor data array
	 * @param int   $original_id Original post ID
	 * @param int   $duplicate_id Duplicate post ID
	 * @return array Updated data
	 */
	private static function update_elementor_references( $data, $original_id, $duplicate_id ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = self::update_elementor_references( $value, $original_id, $duplicate_id );
			} elseif ( is_string( $value ) || is_numeric( $value ) ) {
				// Update widget IDs, template IDs, and post IDs in strings
				if ( 'id' === $key && (int) $value === $original_id ) {
					$data[ $key ] = $duplicate_id;
				} elseif ( 'post_id' === $key && (int) $value === $original_id ) {
					$data[ $key ] = $duplicate_id;
				} elseif ( is_string( $value ) && strpos( $value, (string) $original_id ) !== false ) {
					// Update any string references to the post ID
					$data[ $key ] = str_replace( $original_id, $duplicate_id, $value );
				}
			}
		}

		return $data;
	}

	/**
	 * Copy taxonomies from original to duplicate
	 *
	 * @param int    $original_id Original post ID
	 * @param int    $duplicate_id Duplicate post ID
	 * @param string $post_type Post type
	 */
	private static function copy_taxonomies( $original_id, $duplicate_id, $post_type ) {
		$taxonomies = get_object_taxonomies( $post_type );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_post_terms( $original_id, $taxonomy, array( 'fields' => 'slugs' ) );
			
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				wp_set_post_terms( $duplicate_id, $terms, $taxonomy );
			}
		}
	}

	/**
	 * Copy featured image from original to duplicate
	 *
	 * @param int $original_id Original post ID
	 * @param int $duplicate_id Duplicate post ID
	 */
	private static function copy_featured_image( $original_id, $duplicate_id ) {
		$thumbnail_id = get_post_thumbnail_id( $original_id );
		
		if ( $thumbnail_id ) {
			set_post_thumbnail( $duplicate_id, $thumbnail_id );
		}
	}
}

