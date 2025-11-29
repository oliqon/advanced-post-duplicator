<?php
/**
 * Taxonomy migration for cross-site duplication
 *
 * @package Advanced_Post_Duplicator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Taxonomy migrator class
 */
class APD_Taxonomy_Migrator {

	/**
	 * Copy taxonomies from source to destination site
	 *
	 * @param int    $source_post_id Source post ID
	 * @param int    $destination_post_id Destination post ID
	 * @param int    $source_site_id Source site ID
	 * @param int    $destination_site_id Destination site ID
	 * @param string $post_type Post type
	 * @return array Array of term mappings (old_term_id => new_term_id)
	 */
	public static function migrate_taxonomies( $source_post_id, $destination_post_id, $source_site_id, $destination_site_id, $post_type ) {
		$term_mappings = array();

		// Get all taxonomies for this post type
		$taxonomies = get_object_taxonomies( $post_type );

		foreach ( $taxonomies as $taxonomy ) {
			// Get terms from source site
			$source_terms = APD_Multisite::switch_and_execute(
				$source_site_id,
				function() use ( $source_post_id, $taxonomy ) {
					return wp_get_post_terms( $source_post_id, $taxonomy, array( 'fields' => 'all' ) );
				}
			);

			if ( empty( $source_terms ) || is_wp_error( $source_terms ) ) {
				continue;
			}

			$destination_term_ids = array();

			foreach ( $source_terms as $term ) {
				$new_term_id = self::copy_term( $term, $taxonomy, $destination_site_id, $source_site_id );
				
				if ( $new_term_id && ! is_wp_error( $new_term_id ) ) {
					$term_mappings[ $term->term_id ] = $new_term_id;
					$destination_term_ids[] = $new_term_id;
				}
			}

			// Set terms on destination post
			if ( ! empty( $destination_term_ids ) ) {
				APD_Multisite::switch_and_execute(
					$destination_site_id,
					function() use ( $destination_post_id, $taxonomy, $destination_term_ids ) {
						wp_set_post_terms( $destination_post_id, $destination_term_ids, $taxonomy );
					}
				);
			}
		}

		return $term_mappings;
	}

	/**
	 * Copy a single term to destination site
	 *
	 * @param WP_Term $term Term object
	 * @param string  $taxonomy Taxonomy name
	 * @param int     $destination_site_id Destination site ID
	 * @param int     $source_site_id Source site ID (for parent lookup)
	 * @return int|WP_Error New term ID or error
	 */
	private static function copy_term( $term, $taxonomy, $destination_site_id, $source_site_id ) {
		// Check if term already exists on destination
		$existing_term = APD_Multisite::switch_and_execute(
			$destination_site_id,
			function() use ( $term, $taxonomy ) {
				return term_exists( $term->slug, $taxonomy );
			}
		);

		if ( $existing_term ) {
			return (int) $existing_term['term_id'];
		}

		// Get parent term if exists
		$parent_id = 0;
		if ( $term->parent > 0 ) {
			$parent_term = APD_Multisite::switch_and_execute(
				$source_site_id,
				function() use ( $term, $taxonomy ) {
					return get_term( $term->parent, $taxonomy );
				}
			);

			if ( $parent_term && ! is_wp_error( $parent_term ) ) {
				$parent_id = self::copy_term( $parent_term, $taxonomy, $destination_site_id, $source_site_id );
			}
		}

		// Create term on destination site
		$new_term = APD_Multisite::switch_and_execute(
			$destination_site_id,
			function() use ( $term, $taxonomy, $parent_id ) {
				$args = array(
					'description' => $term->description,
					'parent'      => $parent_id,
				);

				$result = wp_insert_term( $term->name, $taxonomy, $args );

				if ( is_wp_error( $result ) ) {
					// Term might already exist with different slug, try to get it
					$existing = term_exists( $term->name, $taxonomy );
					if ( $existing ) {
						return $existing['term_id'];
					}
					return $result;
				}

				return $result['term_id'];
			}
		);

		if ( ! is_wp_error( $new_term ) ) {
			// Copy term meta
			$term_meta = APD_Multisite::switch_and_execute(
				$source_site_id,
				function() use ( $term ) {
					return get_term_meta( $term->term_id );
				}
			);

			if ( is_array( $term_meta ) ) {
				APD_Multisite::switch_and_execute(
					$destination_site_id,
					function() use ( $new_term, $term_meta ) {
						foreach ( $term_meta as $key => $values ) {
							foreach ( $values as $value ) {
								add_term_meta( $new_term, $key, maybe_unserialize( $value ) );
							}
						}
					}
				);
			}
		}

		return $new_term;
	}
}

