<?php
/**
 * List table integration class
 *
 * @package Advanced_Post_Duplicator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * List table integration class
 */
class APD_List_Table {

	/**
	 * Instance of this class
	 *
	 * @var APD_List_Table
	 */
	private static $instance = null;

	/**
	 * Get instance of this class
	 *
	 * @return APD_List_Table
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
		add_filter( 'post_row_actions', array( $this, 'add_duplicate_link' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'add_duplicate_link' ), 10, 2 );
		add_action( 'admin_action_apd_duplicate_post', array( $this, 'handle_duplicate_action' ) );
		add_action( 'admin_notices', array( $this, 'show_duplicate_notice' ) );
		
		// Register bulk actions for common post types directly
		$this->register_common_bulk_actions();
		
		// Add bulk duplicate action using current_screen for dynamic post types
		add_action( 'load-edit.php', array( $this, 'register_bulk_actions' ) );
		add_action( 'admin_init', array( $this, 'register_all_bulk_actions' ) );
	}

	/**
	 * Register bulk actions for common post types directly
	 */
	private function register_common_bulk_actions() {
		$common_post_types = array( 'post', 'page' );
		
		// Add WooCommerce product if WooCommerce is active
		if ( class_exists( 'WooCommerce' ) ) {
			$common_post_types[] = 'product';
		}
		
		foreach ( $common_post_types as $post_type ) {
			add_filter( "bulk_actions-edit-{$post_type}", array( $this, 'add_bulk_duplicate_action' ), 20 );
			add_filter( "handle_bulk_actions-edit-{$post_type}", array( $this, 'handle_bulk_duplicate' ), 10, 3 );
		}
	}

	/**
	 * Add duplicate link to row actions
	 *
	 * @param array   $actions Existing actions
	 * @param WP_Post $post    Post object
	 * @return array Modified actions
	 */
	public function add_duplicate_link( $actions, $post ) {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$post_type_object = get_post_type_object( $post->post_type );
		if ( ! $post_type_object ) {
			return $actions;
		}

		$duplicate_url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'apd_duplicate_post',
					'post'   => $post->ID,
				),
				admin_url( 'admin.php' )
			),
			'apd_duplicate_post_' . $post->ID
		);

		$post_type_label = $post_type_object->labels->singular_name;
		
		$actions['apd_duplicate'] = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			esc_url( $duplicate_url ),
			esc_attr( sprintf( __( 'Duplicate %s', 'advanced-post-duplicator' ), $post_type_label ) ),
			esc_html( sprintf( __( 'Duplicate %s', 'advanced-post-duplicator' ), $post_type_label ) )
		);

		return $actions;
	}

	/**
	 * Handle duplicate action
	 */
	public function handle_duplicate_action() {
		if ( ! isset( $_GET['post'] ) || ! isset( $_GET['action'] ) || 'apd_duplicate_post' !== $_GET['action'] ) {
			wp_die( __( 'Invalid request.', 'advanced-post-duplicator' ) );
		}

		$post_id = absint( $_GET['post'] );

		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'apd_duplicate_post_' . $post_id ) ) {
			wp_die( __( 'Security check failed.', 'advanced-post-duplicator' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( __( 'You do not have permission to duplicate this post.', 'advanced-post-duplicator' ) );
		}

		// Duplicate the post
		require_once APD_PLUGIN_DIR . 'includes/class-duplicator.php';
		$new_post_id = APD_Duplicator::duplicate( $post_id );

		if ( is_wp_error( $new_post_id ) ) {
			wp_die( $new_post_id->get_error_message() );
		}

		// Redirect to edit page of new post
		$post = get_post( $new_post_id );
		$redirect_url = add_query_arg(
			array(
				'post'   => $new_post_id,
				'action' => 'edit',
				'apd_duplicated' => '1',
			),
			admin_url( 'post.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Show duplicate success notice
	 */
	public function show_duplicate_notice() {
		// Single post duplicate notice
		if ( isset( $_GET['apd_duplicated'] ) && '1' === $_GET['apd_duplicated'] ) {
			$class = 'notice notice-success is-dismissible';
			$message = __( 'Post duplicated successfully!', 'advanced-post-duplicator' );

			printf(
				'<div class="%1$s"><p>%2$s</p></div>',
				esc_attr( $class ),
				esc_html( $message )
			);
		}

		// Bulk duplicate notice
		if ( isset( $_GET['apd_bulk_duplicated'] ) ) {
			$duplicated_count = absint( $_GET['apd_bulk_duplicated'] );
			
			if ( $duplicated_count > 0 ) {
				$class = 'notice notice-success is-dismissible';
				$message = sprintf(
					_n(
						'%d post duplicated successfully!',
						'%d posts duplicated successfully!',
						$duplicated_count,
						'advanced-post-duplicator'
					),
					$duplicated_count
				);

				printf(
					'<div class="%1$s"><p>%2$s</p></div>',
					esc_attr( $class ),
					esc_html( $message )
				);
			}
		}

		// Bulk duplicate errors notice
		if ( isset( $_GET['apd_bulk_errors'] ) ) {
			$errors_count = absint( $_GET['apd_bulk_errors'] );
			
			if ( $errors_count > 0 ) {
				$class = 'notice notice-error is-dismissible';
				$message = sprintf(
					_n(
						'%d post could not be duplicated.',
						'%d posts could not be duplicated.',
						$errors_count,
						'advanced-post-duplicator'
					),
					$errors_count
				);

				printf(
					'<div class="%1$s"><p>%2$s</p></div>',
					esc_attr( $class ),
					esc_html( $message )
				);
			}
		}
	}

	/**
	 * Register bulk actions for all post types (admin_init hook)
	 */
	public function register_all_bulk_actions() {
		// Get all post types that have admin UI
		$post_types = get_post_types( array( 'show_ui' => true ), 'names' );
		
		// Exclude built-in types that shouldn't be duplicated
		$excluded_types = array( 'attachment', 'revision', 'nav_menu_item' );
		
		foreach ( $post_types as $post_type ) {
			// Skip excluded post types
			if ( in_array( $post_type, $excluded_types, true ) ) {
				continue;
			}

			// Add bulk duplicate option to each post type with priority 20
			add_filter( "bulk_actions-edit-{$post_type}", array( $this, 'add_bulk_duplicate_action' ), 20 );
			add_filter( "handle_bulk_actions-edit-{$post_type}", array( $this, 'handle_bulk_duplicate' ), 10, 3 );
		}
	}

	/**
	 * Register bulk actions for current screen (load-edit.php hook)
	 */
	public function register_bulk_actions() {
		// Get current screen
		$screen = get_current_screen();
		
		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}

		$post_type = $screen->post_type;
		
		if ( ! $post_type ) {
			return;
		}

		// Exclude built-in types that shouldn't be duplicated
		$excluded_types = array( 'attachment', 'revision', 'nav_menu_item' );
		
		if ( in_array( $post_type, $excluded_types, true ) ) {
			return;
		}

		// Ensure filters are registered for this post type with priority 20
		add_filter( "bulk_actions-edit-{$post_type}", array( $this, 'add_bulk_duplicate_action' ), 20 );
		add_filter( "handle_bulk_actions-edit-{$post_type}", array( $this, 'handle_bulk_duplicate' ), 10, 3 );
	}

	/**
	 * Add bulk duplicate action to bulk actions dropdown
	 *
	 * @param array $actions Existing bulk actions
	 * @return array Modified bulk actions
	 */
	public function add_bulk_duplicate_action( $actions ) {
		// Add duplicate action to bulk actions
		$actions['apd_duplicate'] = __( 'Duplicate', 'advanced-post-duplicator' );
		return $actions;
	}

	/**
	 * Handle bulk duplicate action
	 *
	 * @param string $redirect_to Redirect URL
	 * @param string $action      Action name
	 * @param array  $post_ids    Post IDs to duplicate
	 * @return string Modified redirect URL
	 */
	public function handle_bulk_duplicate( $redirect_to, $action, $post_ids ) {
		// Check if this is our bulk duplicate action
		if ( 'apd_duplicate' !== $action ) {
			return $redirect_to;
		}

		// Get post IDs from request - WordPress may pass them in the filter parameter or in $_REQUEST
		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			// Try multiple possible locations for post IDs
			if ( isset( $_REQUEST['post'] ) && is_array( $_REQUEST['post'] ) && ! empty( $_REQUEST['post'] ) ) {
				$post_ids = array_map( 'absint', $_REQUEST['post'] );
			} elseif ( isset( $_GET['post'] ) && is_array( $_GET['post'] ) && ! empty( $_GET['post'] ) ) {
				$post_ids = array_map( 'absint', $_GET['post'] );
			} elseif ( isset( $_POST['post'] ) && is_array( $_POST['post'] ) && ! empty( $_POST['post'] ) ) {
				$post_ids = array_map( 'absint', $_POST['post'] );
			}
		}

		// Validate we have post IDs
		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			// Return redirect without modification if no post IDs found
			return $redirect_to;
		}

		// Verify nonce - WordPress uses 'bulk-posts' for bulk actions
		check_admin_referer( 'bulk-posts' );

		// Remove query args that might cause issues
		$redirect_to = remove_query_arg( array( 'apd_bulk_duplicated', 'apd_bulk_errors', 'apd_duplicated' ), $redirect_to );

		// Require duplicator class
		require_once APD_PLUGIN_DIR . 'includes/class-duplicator.php';

		$duplicated_count = 0;
		$errors_count = 0;
		$duplicated_ids = array();

		// Process each post
		foreach ( $post_ids as $post_id ) {
			$post_id = absint( $post_id );

			// Check permissions
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				$errors_count++;
				continue;
			}

			// Duplicate the post
			$new_post_id = APD_Duplicator::duplicate( $post_id );

			if ( is_wp_error( $new_post_id ) ) {
				$errors_count++;
				continue;
			}

			// Success - add to counts and IDs
			$duplicated_count++;
			$duplicated_ids[] = $new_post_id;

			// Allow other plugins/theme to hook into each successful duplicate
			do_action( 'apd_after_bulk_duplicate_item', $new_post_id, $post_id );
		}

		// Action hook after all duplicates are processed
		do_action( 'apd_after_bulk_duplicate', $duplicated_ids, $post_ids );

		// Build redirect URL with results
		$redirect_to = add_query_arg(
			array(
				'apd_bulk_duplicated' => $duplicated_count,
				'apd_bulk_errors'     => $errors_count,
			),
			$redirect_to
		);

		return $redirect_to;
	}
}

