<?php
/**
 * Media handling for cross-site duplication
 *
 * @package Advanced_Post_Duplicator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Media handler class
 */
class APD_Media_Handler {

	/**
	 * Copy media file from source site to destination site
	 *
	 * @param int $attachment_id Source attachment ID
	 * @param int $destination_site_id Destination site ID
	 * @param int $source_site_id Source site ID (optional, defaults to current)
	 * @return int|WP_Error New attachment ID or error
	 */
	public static function copy_media_to_site( $attachment_id, $destination_site_id, $source_site_id = 0 ) {
		$current_site_id = get_current_blog_id();
		$source_site_id = $source_site_id ? $source_site_id : $current_site_id;

		// Get source attachment and file info while on source site
		$source_data = APD_Multisite::switch_and_execute(
			$source_site_id,
			function() use ( $attachment_id ) {
				$attachment = get_post( $attachment_id );
				if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
					return new WP_Error( 'invalid_attachment', __( 'Invalid attachment ID.', 'advanced-post-duplicator' ) );
				}

				$file_path = get_attached_file( $attachment_id );
				if ( ! $file_path || ! file_exists( $file_path ) ) {
					return new WP_Error( 'file_not_found', __( 'Media file not found.', 'advanced-post-duplicator' ) );
				}

				return array(
					'attachment' => $attachment,
					'file_path'  => $file_path,
					'metadata'   => wp_get_attachment_metadata( $attachment_id ),
					'meta_keys'  => get_post_meta( $attachment_id ),
				);
			}
		);

		if ( is_wp_error( $source_data ) ) {
			return $source_data;
		}

		$source_attachment = $source_data['attachment'];
		$file_path = $source_data['file_path'];
		$metadata = $source_data['metadata'];
		$source_meta_keys = $source_data['meta_keys'];
		$file_name = basename( $file_path );

		// Switch to destination site
		switch_to_blog( $destination_site_id );

		// Check if file already exists
		$existing_attachment = self::find_existing_attachment( $file_name, $metadata );
		if ( $existing_attachment ) {
			restore_current_blog();
			return $existing_attachment;
		}

		// Prepare upload directory
		$upload_dir = wp_upload_dir();
		if ( $upload_dir['error'] ) {
			restore_current_blog();
			return new WP_Error( 'upload_dir_error', $upload_dir['error'] );
		}

		// Maintain directory structure (year/month)
		$subdir = '';
		if ( isset( $metadata['file'] ) ) {
			$subdir = dirname( $metadata['file'] );
			$subdir = str_replace( basename( $metadata['file'] ), '', $subdir );
			$subdir = rtrim( $subdir, '/' );
		} else {
			$subdir = date( 'Y/m', strtotime( $source_attachment->post_date ) );
		}

		$destination_dir = $upload_dir['basedir'] . '/' . $subdir;
		if ( ! file_exists( $destination_dir ) ) {
			wp_mkdir_p( $destination_dir );
		}

		$destination_path = $destination_dir . '/' . $file_name;

		// Copy file
		if ( ! copy( $file_path, $destination_path ) ) {
			restore_current_blog();
			return new WP_Error( 'copy_failed', __( 'Failed to copy media file.', 'advanced-post-duplicator' ) );
		}

		// Copy thumbnails if they exist
		if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$source_dir = dirname( $file_path );
			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				if ( isset( $size_data['file'] ) ) {
					$thumb_source = $source_dir . '/' . $size_data['file'];
					$thumb_dest = $destination_dir . '/' . $size_data['file'];
					if ( file_exists( $thumb_source ) ) {
						copy( $thumb_source, $thumb_dest );
					}
				}
			}
		}

		// Create attachment post
		$attachment_data = array(
			'post_title'     => $source_attachment->post_title,
			'post_content'   => $source_attachment->post_content,
			'post_excerpt'   => $source_attachment->post_excerpt,
			'post_status'    => 'inherit',
			'post_mime_type' => $source_attachment->post_mime_type,
			'guid'           => $upload_dir['baseurl'] . '/' . $subdir . '/' . $file_name,
		);

		$new_attachment_id = wp_insert_attachment( $attachment_data, $destination_path );

		if ( is_wp_error( $new_attachment_id ) ) {
			// Clean up copied file
			unlink( $destination_path );
			restore_current_blog();
			return $new_attachment_id;
		}

		// Generate attachment metadata
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $new_attachment_id, $destination_path );
		wp_update_attachment_metadata( $new_attachment_id, $attach_data );

		// Copy custom fields
		foreach ( $source_meta_keys as $key => $values ) {
			if ( ! in_array( $key, array( '_wp_attached_file', '_wp_attachment_metadata' ), true ) ) {
				foreach ( $values as $value ) {
					add_post_meta( $new_attachment_id, $key, maybe_unserialize( $value ) );
				}
			}
		}

		restore_current_blog();

		return $new_attachment_id;
	}

	/**
	 * Find existing attachment by filename and metadata
	 *
	 * @param string $filename Filename
	 * @param array  $metadata Attachment metadata
	 * @return int|false Attachment ID or false
	 */
	private static function find_existing_attachment( $filename, $metadata ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} 
			WHERE post_type = 'attachment' 
			AND guid LIKE %s",
			'%' . $wpdb->esc_like( $filename ) . '%'
		);

		$attachment_id = $wpdb->get_var( $query );

		if ( $attachment_id ) {
			$existing_file = get_attached_file( $attachment_id );
			$existing_meta = wp_get_attachment_metadata( $attachment_id );

			// Check if file sizes match
			if ( isset( $metadata['width'], $metadata['height'] ) 
				&& isset( $existing_meta['width'], $existing_meta['height'] )
				&& $metadata['width'] === $existing_meta['width']
				&& $metadata['height'] === $existing_meta['height']
			) {
				return (int) $attachment_id;
			}
		}

		return false;
	}

	/**
	 * Update media references in post content
	 *
	 * @param string $content Post content
	 * @param array  $media_map Array mapping old attachment IDs to new ones
	 * @param int    $source_site_id Source site ID (for getting old URLs)
	 * @param int    $destination_site_id Destination site ID (for getting new URLs)
	 * @return string Updated content
	 */
	public static function update_media_references( $content, $media_map, $source_site_id, $destination_site_id ) {
		if ( empty( $media_map ) ) {
			return $content;
		}

		// Get old URLs from source site
		$url_map = array();
		APD_Multisite::switch_and_execute(
			$source_site_id,
			function() use ( $media_map, &$url_map ) {
				foreach ( $media_map as $old_id => $new_id ) {
					$old_url = wp_get_attachment_url( $old_id );
					if ( $old_url ) {
						$url_map[ $old_url ] = $new_id;
					}
				}
			}
		);

		// Get new URLs from destination site and update content
		APD_Multisite::switch_and_execute(
			$destination_site_id,
			function() use ( &$content, $url_map, $media_map ) {
				foreach ( $url_map as $old_url => $new_id ) {
					$new_url = wp_get_attachment_url( $new_id );
					if ( $new_url ) {
						$content = str_replace( $old_url, $new_url, $content );
					}
				}

				// Update in shortcodes - replace old IDs with new IDs
				foreach ( $media_map as $old_id => $new_id ) {
					$content = preg_replace(
						'/(\[gallery[^\]]*ids=["\'])([^"\']*)\b' . $old_id . '\b([^"\']*)(["\'])/',
						'$1$2' . $new_id . '$3$4',
						$content
					);
				}
			}
		);

		return $content;
	}
}

