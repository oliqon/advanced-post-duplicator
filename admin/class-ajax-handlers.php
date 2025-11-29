<?php
/**
 * AJAX handlers for cross-site duplication
 *
 * @package Advanced_Post_Duplicator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX handlers class
 */
class APD_Ajax_Handlers {

	/**
	 * Instance of this class
	 *
	 * @var APD_Ajax_Handlers
	 */
	private static $instance = null;

	/**
	 * Get instance of this class
	 *
	 * @return APD_Ajax_Handlers
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'wp_ajax_apd_get_site_posts', array( $this, 'get_site_posts' ) );
		add_action( 'wp_ajax_apd_duplicate_cross_site', array( $this, 'duplicate_cross_site' ) );
		add_action( 'wp_ajax_apd_get_duplication_logs', array( $this, 'get_duplication_logs' ) );
	}

	/**
	 * Get posts from a specific site
	 */
	public function get_site_posts() {
		check_ajax_referer( 'apd_cross_site_nonce', 'nonce' );

		if ( ! APD_Multisite::user_can_cross_site_duplicate() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'advanced-post-duplicator' ) ) );
		}

		$site_id = isset( $_POST['site_id'] ) ? absint( $_POST['site_id'] ) : 0;
		$post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : 'post';
		$search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = 20;

		if ( ! $site_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid site ID.', 'advanced-post-duplicator' ) ) );
		}

		$posts = APD_Multisite::switch_and_execute(
			$site_id,
			function() use ( $post_type, $search, $page, $per_page ) {
				$args = array(
					'post_type'      => $post_type,
					'posts_per_page' => $per_page,
					'paged'          => $page,
					'post_status'    => 'any',
					'orderby'        => 'date',
					'order'          => 'DESC',
				);

				if ( ! empty( $search ) ) {
					$args['s'] = $search;
				}

				$query = new WP_Query( $args );
				$results = array();

				foreach ( $query->posts as $post ) {
					$results[] = array(
						'id'    => $post->ID,
						'title' => $post->post_title,
						'date'  => $post->post_date,
						'status' => $post->post_status,
					);
				}

				return array(
					'posts'      => $results,
					'total'      => $query->found_posts,
					'total_pages' => $query->max_num_pages,
				);
			}
		);

		wp_send_json_success( $posts );
	}

	/**
	 * Handle cross-site duplication
	 */
	public function duplicate_cross_site() {
		check_ajax_referer( 'apd_cross_site_nonce', 'nonce' );

		if ( ! APD_Multisite::user_can_cross_site_duplicate() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'advanced-post-duplicator' ) ) );
		}

		$source_site_id = isset( $_POST['source_site_id'] ) ? absint( $_POST['source_site_id'] ) : 0;
		$destination_site_id = isset( $_POST['destination_site_id'] ) ? absint( $_POST['destination_site_id'] ) : 0;
		$post_ids = isset( $_POST['post_ids'] ) ? array_map( 'absint', (array) $_POST['post_ids'] ) : array();
		$copy_media = isset( $_POST['copy_media'] ) ? (bool) $_POST['copy_media'] : true;
		$post_status = isset( $_POST['post_status'] ) ? sanitize_text_field( $_POST['post_status'] ) : 'draft';
		$slug_suffix = isset( $_POST['slug_suffix'] ) ? sanitize_text_field( $_POST['slug_suffix'] ) : '';

		if ( ! $source_site_id || ! $destination_site_id || empty( $post_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'advanced-post-duplicator' ) ) );
		}

		$results = array(
			'success' => array(),
			'errors'  => array(),
		);

		$options = array(
			'copy_media'    => $copy_media,
			'post_status'   => $post_status,
			'slug_suffix'   => $slug_suffix,
			'preserve_date' => false,
		);

		foreach ( $post_ids as $post_id ) {
			$new_post_id = APD_Cross_Site_Duplicator::duplicate_post(
				$post_id,
				$source_site_id,
				$destination_site_id,
				$options
			);

			if ( is_wp_error( $new_post_id ) ) {
				$results['errors'][] = array(
					'source_post_id' => $post_id,
					'message'        => $new_post_id->get_error_message(),
				);

				APD_Error_Handler::log_error(
					sprintf( __( 'Failed to duplicate post %d from site %d to site %d', 'advanced-post-duplicator' ), $post_id, $source_site_id, $destination_site_id ),
					array(
						'source_post_id'      => $post_id,
						'source_site_id'      => $source_site_id,
						'destination_site_id' => $destination_site_id,
						'error'               => $new_post_id->get_error_message(),
					),
					$destination_site_id
				);
			} else {
				$results['success'][] = array(
					'source_post_id'    => $post_id,
					'destination_post_id' => $new_post_id,
				);

				APD_Error_Handler::log_success(
					sprintf( __( 'Successfully duplicated post %d from site %d to site %d (new ID: %d)', 'advanced-post-duplicator' ), $post_id, $source_site_id, $destination_site_id, $new_post_id ),
					array(
						'source_post_id'      => $post_id,
						'destination_post_id' => $new_post_id,
						'source_site_id'      => $source_site_id,
						'destination_site_id' => $destination_site_id,
					)
				);
			}
		}

		wp_send_json_success( $results );
	}

	/**
	 * Get duplication logs
	 */
	public function get_duplication_logs() {
		check_ajax_referer( 'apd_cross_site_nonce', 'nonce' );

		if ( ! APD_Multisite::user_can_cross_site_duplicate() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'advanced-post-duplicator' ) ) );
		}

		$limit = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 50;
		$logs = APD_Error_Handler::get_logs( $limit );

		wp_send_json_success( $logs );
	}
}

