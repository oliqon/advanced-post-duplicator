<?php
/**
 * Core cross-site duplication logic
 *
 * @package Advanced_Post_Duplicator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cross-site duplicator class
 */
class APD_Cross_Site_Duplicator {

	/**
	 * Duplicate a post from one site to another
	 *
	 * @param int    $source_post_id Source post ID
	 * @param int    $source_site_id Source site ID
	 * @param int    $destination_site_id Destination site ID
	 * @param array  $options Duplication options
	 * @return int|WP_Error New post ID on success, WP_Error on failure
	 */
	public static function duplicate_post( $source_post_id, $source_site_id, $destination_site_id, $options = array() ) {
		// Default options
		$defaults = array(
			'copy_media'           => true,
			'slug_suffix'          => '',
			'post_status'          => 'draft',
			'preserve_date'        => false,
		);

		$options = wp_parse_args( $options, $defaults );

		// Get source post
		$source_post = APD_Multisite::switch_and_execute(
			$source_site_id,
			function() use ( $source_post_id ) {
				return get_post( $source_post_id );
			}
		);

		if ( ! $source_post ) {
			return new WP_Error( 'post_not_found', __( 'Source post not found.', 'advanced-post-duplicator' ) );
		}

		// Determine post status
		$post_status = $options['post_status'];
		if ( 'same' === $post_status ) {
			$post_status = $source_post->post_status;
		}

		// Determine post date
		$post_date = current_time( 'mysql' );
		if ( $options['preserve_date'] ) {
			$post_date = $source_post->post_date;
		}

		// Resolve slug
		$slug = APD_Slug_Resolver::resolve_slug(
			$source_post->post_name,
			$destination_site_id,
			$source_post->post_type
		);

		if ( ! empty( $options['slug_suffix'] ) ) {
			$slug = APD_Slug_Resolver::resolve_slug_with_suffix(
				$source_post->post_name,
				$options['slug_suffix'],
				$destination_site_id,
				$source_post->post_type
			);
		}

		// Prepare post data
		$post_data = array(
			'post_title'   => $source_post->post_title,
			'post_content' => $source_post->post_content,
			'post_excerpt' => $source_post->post_excerpt,
			'post_status'  => $post_status,
			'post_type'    => $source_post->post_type,
			'post_author'  => get_current_user_id(),
			'post_date'    => $post_date,
			'post_name'    => $slug,
			'comment_status' => $source_post->comment_status,
			'ping_status'    => $source_post->ping_status,
		);

		// Handle parent for hierarchical post types
		if ( $source_post->post_parent > 0 ) {
			// Note: Parent would need to be duplicated first or mapped
			// For now, we'll skip parent relationship
		}

		// Create post on destination site
		$new_post_id = APD_Multisite::switch_and_execute(
			$destination_site_id,
			function() use ( $post_data ) {
				return wp_insert_post( $post_data, true );
			}
		);

		if ( is_wp_error( $new_post_id ) ) {
			return $new_post_id;
		}

		// Copy taxonomies
		$term_mappings = APD_Taxonomy_Migrator::migrate_taxonomies(
			$source_post_id,
			$new_post_id,
			$source_site_id,
			$destination_site_id,
			$source_post->post_type
		);

		// Copy meta data
		APD_Meta_Migrator::migrate_meta(
			$source_post_id,
			$new_post_id,
			$source_site_id,
			$destination_site_id
		);

		// Handle media
		$media_map = array();
		if ( $options['copy_media'] ) {
			$media_map = self::copy_media_for_post(
				$source_post_id,
				$new_post_id,
				$source_site_id,
				$destination_site_id
			);
		}

		// Update media references in content if media was copied
		if ( ! empty( $media_map ) ) {
			$updated_content = APD_Media_Handler::update_media_references(
				$source_post->post_content,
				$media_map,
				$source_site_id,
				$destination_site_id
			);

			// Update post content with new media URLs
			APD_Multisite::switch_and_execute(
				$destination_site_id,
				function() use ( $new_post_id, $updated_content ) {
					wp_update_post( array(
						'ID'           => $new_post_id,
						'post_content' => $updated_content,
					) );
				}
			);
		}

		// Copy featured image
		$featured_image_id = APD_Multisite::switch_and_execute(
			$source_site_id,
			function() use ( $source_post_id ) {
				return get_post_thumbnail_id( $source_post_id );
			}
		);

		if ( $featured_image_id ) {
			if ( $options['copy_media'] ) {
				// Use copied media if available
				if ( isset( $media_map[ $featured_image_id ] ) ) {
					$new_featured_id = $media_map[ $featured_image_id ];
				} else {
					$new_featured_id = APD_Media_Handler::copy_media_to_site( $featured_image_id, $destination_site_id, $source_site_id );
				}

				if ( $new_featured_id && ! is_wp_error( $new_featured_id ) ) {
					APD_Multisite::switch_and_execute(
						$destination_site_id,
						function() use ( $new_post_id, $new_featured_id ) {
							set_post_thumbnail( $new_post_id, $new_featured_id );
						}
					);
				}
			}
		}

		// Store reference to original post
		APD_Multisite::switch_and_execute(
			$destination_site_id,
			function() use ( $new_post_id, $source_site_id, $source_post_id ) {
				update_post_meta( $new_post_id, '_apd_duplicated_from', $source_post_id );
				update_post_meta( $new_post_id, '_apd_duplicated_from_site', $source_site_id );
			}
		);

		// Action hook for other plugins
		do_action( 'apd_after_cross_site_duplicate', $new_post_id, $source_post_id, $source_site_id, $destination_site_id );

		return $new_post_id;
	}

	/**
	 * Copy all media associated with a post
	 *
	 * @param int $source_post_id Source post ID
	 * @param int $new_post_id New post ID
	 * @param int $source_site_id Source site ID
	 * @param int $destination_site_id Destination site ID
	 * @return array Media mapping (old_id => new_id)
	 */
	private static function copy_media_for_post( $source_post_id, $new_post_id, $source_site_id, $destination_site_id ) {
		$media_map = array();

		// Get all attachments for this post
		$attachments = APD_Multisite::switch_and_execute(
			$source_site_id,
			function() use ( $source_post_id ) {
				return get_attached_media( '', $source_post_id );
			}
		);

		foreach ( $attachments as $attachment ) {
			$new_attachment_id = APD_Media_Handler::copy_media_to_site( $attachment->ID, $destination_site_id, $source_site_id );

			if ( $new_attachment_id && ! is_wp_error( $new_attachment_id ) ) {
				$media_map[ $attachment->ID ] = $new_attachment_id;

				// Update attachment parent
				APD_Multisite::switch_and_execute(
					$destination_site_id,
					function() use ( $new_attachment_id, $new_post_id ) {
						wp_update_post( array(
							'ID'          => $new_attachment_id,
							'post_parent' => $new_post_id,
						) );
					}
				);
			}
		}

		// Also find media referenced in content (images, etc.)
		$content_media = self::extract_media_from_content( $source_post_id, $source_site_id );

		foreach ( $content_media as $media_id ) {
			if ( ! isset( $media_map[ $media_id ] ) ) {
				$new_media_id = APD_Media_Handler::copy_media_to_site( $media_id, $destination_site_id, $source_site_id );
				if ( $new_media_id && ! is_wp_error( $new_media_id ) ) {
					$media_map[ $media_id ] = $new_media_id;
				}
			}
		}

		return $media_map;
	}

	/**
	 * Extract media IDs from post content
	 *
	 * @param int $post_id Post ID
	 * @param int $site_id Site ID
	 * @return array Array of attachment IDs
	 */
	private static function extract_media_from_content( $post_id, $site_id ) {
		$media_ids = array();

		$post = APD_Multisite::switch_and_execute(
			$site_id,
			function() use ( $post_id ) {
				return get_post( $post_id );
			}
		);

		if ( ! $post ) {
			return $media_ids;
		}

		$content = $post->post_content;

		// Extract image IDs from img tags
		preg_match_all( '/wp-image-(\d+)/', $content, $matches );
		if ( ! empty( $matches[1] ) ) {
			$media_ids = array_merge( $media_ids, $matches[1] );
		}

		// Extract gallery shortcode IDs
		preg_match_all( '/\[gallery.*ids=["\']([^"\']+)["\'].*\]/', $content, $gallery_matches );
		if ( ! empty( $gallery_matches[1] ) ) {
			foreach ( $gallery_matches[1] as $ids_string ) {
				$ids = explode( ',', $ids_string );
				$media_ids = array_merge( $media_ids, array_map( 'intval', $ids ) );
			}
		}

		// Extract from block editor media blocks
		preg_match_all( '/"id":(\d+)/', $content, $block_matches );
		if ( ! empty( $block_matches[1] ) ) {
			$media_ids = array_merge( $media_ids, $block_matches[1] );
		}

		return array_unique( array_map( 'intval', $media_ids ) );
	}
}

