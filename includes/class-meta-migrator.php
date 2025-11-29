<?php
/**
 * Meta data migration for cross-site duplication
 *
 * @package Advanced_Post_Duplicator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta migrator class
 */
class APD_Meta_Migrator {

	/**
	 * Copy post meta from source to destination
	 *
	 * @param int    $source_post_id Source post ID
	 * @param int    $destination_post_id Destination post ID
	 * @param int    $source_site_id Source site ID
	 * @param int    $destination_site_id Destination site ID
	 * @param array  $exclude_keys Meta keys to exclude
	 * @return void
	 */
	public static function migrate_meta( $source_post_id, $destination_post_id, $source_site_id, $destination_site_id, $exclude_keys = array() ) {
		// Get all meta from source
		$meta_keys = APD_Multisite::switch_and_execute(
			$source_site_id,
			function() use ( $source_post_id ) {
				return get_post_meta( $source_post_id );
			}
		);

		$default_exclude = array(
			'_apd_duplicated_from',
			'_edit_lock',
			'_edit_last',
			'_wp_old_slug',
		);

		$exclude_keys = array_merge( $default_exclude, $exclude_keys );

		foreach ( $meta_keys as $key => $values ) {
			// Skip excluded keys
			if ( in_array( $key, $exclude_keys, true ) ) {
				continue;
			}

			foreach ( $values as $value ) {
				$value = maybe_unserialize( $value );

				// Handle special meta keys
				$value = self::process_meta_value( $key, $value, $source_site_id, $destination_site_id );

				APD_Multisite::switch_and_execute(
					$destination_site_id,
					function() use ( $destination_post_id, $key, $value ) {
						add_post_meta( $destination_post_id, $key, $value );
					}
				);
			}
		}
	}

	/**
	 * Process meta value to update site-specific references
	 *
	 * @param string $key Meta key
	 * @param mixed  $value Meta value
	 * @param int    $source_site_id Source site ID
	 * @param int    $destination_site_id Destination site ID
	 * @return mixed Processed value
	 */
	private static function process_meta_value( $key, $value, $source_site_id, $destination_site_id ) {
		// Handle Elementor data
		if ( '_elementor_data' === $key && defined( 'ELEMENTOR_VERSION' ) ) {
			$value = self::process_elementor_data( $value, $source_site_id, $destination_site_id );
		}

		// Handle WooCommerce product data (check by meta key patterns)
		if ( class_exists( 'WooCommerce' ) && strpos( $key, '_product' ) !== false ) {
			$value = self::process_woocommerce_meta( $key, $value, $source_site_id, $destination_site_id );
		}

		// Handle arrays and objects - update any site ID references
		if ( is_array( $value ) || is_object( $value ) ) {
			$value = self::update_site_references( $value, $source_site_id, $destination_site_id );
		} elseif ( is_string( $value ) ) {
			// Update site URLs in strings
			$source_url = get_site_url( $source_site_id );
			$destination_url = get_site_url( $destination_site_id );
			$value = str_replace( $source_url, $destination_url, $value );
		}

		return $value;
	}

	/**
	 * Process Elementor data
	 *
	 * @param mixed $data Elementor data
	 * @param int   $source_site_id Source site ID
	 * @param int   $destination_site_id Destination site ID
	 * @return mixed Processed data
	 */
	private static function process_elementor_data( $data, $source_site_id, $destination_site_id ) {
		if ( is_string( $data ) ) {
			$data = json_decode( $data, true );
		}

		if ( ! is_array( $data ) ) {
			return $data;
		}

		// Recursively update Elementor widget data
		$data = self::update_elementor_references( $data, $source_site_id, $destination_site_id );

		if ( is_array( $data ) ) {
			return wp_slash( wp_json_encode( $data ) );
		}

		return $data;
	}

	/**
	 * Recursively update Elementor references
	 *
	 * @param array $data Elementor data array
	 * @param int   $source_site_id Source site ID
	 * @param int   $destination_site_id Destination site ID
	 * @return array Updated data
	 */
	private static function update_elementor_references( $data, $source_site_id, $destination_site_id ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = self::update_elementor_references( $value, $source_site_id, $destination_site_id );
			} elseif ( is_string( $value ) ) {
				// Update URLs
				$source_url = get_site_url( $source_site_id );
				$destination_url = get_site_url( $destination_site_id );
				$data[ $key ] = str_replace( $source_url, $destination_url, $value );
			}
		}

		return $data;
	}

	/**
	 * Process WooCommerce meta
	 *
	 * @param string $key Meta key
	 * @param mixed  $value Meta value
	 * @param int    $source_site_id Source site ID
	 * @param int    $destination_site_id Destination site ID
	 * @return mixed Processed value
	 */
	private static function process_woocommerce_meta( $key, $value, $source_site_id, $destination_site_id ) {
		// Skip SKU - it will be regenerated
		if ( '_sku' === $key ) {
			return '';
		}

		// Skip stock - it will be reset
		if ( '_stock' === $key ) {
			return '0';
		}

		if ( '_stock_status' === $key ) {
			return 'outofstock';
		}

		return $value;
	}

	/**
	 * Recursively update site references in data structures
	 *
	 * @param mixed $data Data to process
	 * @param int   $source_site_id Source site ID
	 * @param int   $destination_site_id Destination site ID
	 * @return mixed Updated data
	 */
	private static function update_site_references( $data, $source_site_id, $destination_site_id ) {
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = self::update_site_references( $value, $source_site_id, $destination_site_id );
			}
		} elseif ( is_object( $data ) ) {
			foreach ( get_object_vars( $data ) as $key => $value ) {
				$data->$key = self::update_site_references( $value, $source_site_id, $destination_site_id );
			}
		} elseif ( is_string( $data ) ) {
			$source_url = get_site_url( $source_site_id );
			$destination_url = get_site_url( $destination_site_id );
			$data = str_replace( $source_url, $destination_url, $data );
		}

		return $data;
	}
}

