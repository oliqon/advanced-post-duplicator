<?php
/**
 * Editor integration class
 *
 * @package Advanced_Post_Duplicator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editor integration class
 */
class APD_Editor_Integration {

	/**
	 * Instance of this class
	 *
	 * @var APD_Editor_Integration
	 */
	private static $instance = null;

	/**
	 * Get instance of this class
	 *
	 * @return APD_Editor_Integration
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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_scripts' ) );
		add_action( 'admin_footer', array( $this, 'add_classic_editor_button' ) );
		add_action( 'admin_init', array( $this, 'handle_classic_editor_duplicate' ) );
		add_filter( 'admin_post_thumbnail_html', array( $this, 'add_featured_image_duplicate_link' ), 10, 2 );
		
		// Add prominent duplicate button in publish metabox
		add_action( 'post_submitbox_misc_actions', array( $this, 'add_publish_metabox_button' ) );
	}

	/**
	 * Enqueue editor scripts
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_editor_scripts( $hook ) {
		global $post;

		// Only load on post edit pages
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		$dependencies = array( 'jquery' );
		
		// Add Block Editor dependencies if Block Editor is active
		if ( $this->is_block_editor() ) {
			$dependencies = array_merge(
				$dependencies,
				array(
					'wp-element',
					'wp-components',
					'wp-plugins',
					'wp-edit-post',
					'wp-data',
					'wp-i18n',
				)
			);
		}

		wp_enqueue_script(
			'apd-editor-js',
			APD_PLUGIN_URL . 'admin/assets/js/admin.js',
			$dependencies,
			APD_VERSION,
			true
		);

		wp_localize_script(
			'apd-editor-js',
			'apdEditor',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'apd_editor_duplicate' ),
				'postId'       => $post->ID,
				'duplicateUrl' => wp_nonce_url(
					add_query_arg(
						array(
							'action' => 'apd_duplicate_post',
							'post'   => $post->ID,
						),
						admin_url( 'admin.php' )
					),
					'apd_duplicate_post_' . $post->ID
				),
				'isBlockEditor' => $this->is_block_editor(),
			)
		);

		wp_enqueue_style(
			'apd-admin-css',
			APD_PLUGIN_URL . 'admin/assets/css/admin.css',
			array(),
			APD_VERSION
		);
	}

	/**
	 * Check if Block Editor is being used
	 *
	 * @return bool
	 */
	private function is_block_editor() {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && method_exists( $screen, 'is_block_editor' ) ) {
				return $screen->is_block_editor();
			}
		}
		return false;
	}

	/**
	 * Add duplicate button to Classic Editor
	 */
	public function add_classic_editor_button() {
		global $post;

		if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		// Only show on edit pages, not new posts
		if ( isset( $_GET['post'] ) && ! $this->is_block_editor() ) {
			$post_type_object = get_post_type_object( $post->post_type );
			if ( ! $post_type_object ) {
				return;
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
			?>
			<script type="text/javascript">
			jQuery(document).ready(function($) {
				// Add duplicate button to Classic Editor
				if ($('#post-status-select').length) {
					var duplicateButton = $('<a>', {
						href: '<?php echo esc_js( $duplicate_url ); ?>',
						class: 'button apd-duplicate-button',
						text: '<?php echo esc_js( sprintf( __( 'Duplicate %s', 'advanced-post-duplicator' ), $post_type_object->labels->singular_name ) ); ?>',
						style: 'margin-left: 10px;'
					});
					$('#post-status-select').after(duplicateButton);
				}
			});
			</script>
			<?php
		}
	}

	/**
	 * Handle Classic Editor duplicate action
	 */
	public function handle_classic_editor_duplicate() {
		// This is handled by APD_List_Table, but we keep this method for consistency
	}

	/**
	 * Add duplicate link near featured image (optional enhancement)
	 *
	 * @param string $content Featured image HTML
	 * @param int    $post_id Post ID
	 * @return string Modified content
	 */
	public function add_featured_image_duplicate_link( $content, $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $content;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $content;
		}

		$post_type_object = get_post_type_object( $post->post_type );
		if ( ! $post_type_object ) {
			return $content;
		}

		$duplicate_url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'apd_duplicate_post',
					'post'   => $post_id,
				),
				admin_url( 'admin.php' )
			),
			'apd_duplicate_post_' . $post_id
		);

		$content .= '<p class="apd-duplicate-link-wrapper">';
		$content .= sprintf(
			'<a href="%s" class="apd-duplicate-link">%s</a>',
			esc_url( $duplicate_url ),
			esc_html( sprintf( __( 'Duplicate %s', 'advanced-post-duplicator' ), $post_type_object->labels->singular_name ) )
		);
		$content .= '</p>';

		return $content;
	}

	/**
	 * Add duplicate button in publish metabox
	 * This adds a prominent duplicate button similar to WooCommerce
	 */
	public function add_publish_metabox_button() {
		global $post;

		// Only show on edit pages, not new posts
		if ( ! isset( $_GET['post'] ) || empty( $post ) || ! $post->ID ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		// Exclude certain post types
		$excluded_types = array( 'attachment', 'revision', 'nav_menu_item' );
		if ( in_array( $post->post_type, $excluded_types, true ) ) {
			return;
		}

		$post_type_object = get_post_type_object( $post->post_type );
		if ( ! $post_type_object ) {
			return;
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
		?>
		<div class="misc-pub-section apd-duplicate-section">
			<span class="dashicons dashicons-admin-page" style="margin-top: 3px;"></span>
			<a href="<?php echo esc_url( $duplicate_url ); ?>" class="apd-duplicate-publish-button">
				<?php echo esc_html( sprintf( __( 'Duplicate %s', 'advanced-post-duplicator' ), $post_type_label ) ); ?>
			</a>
		</div>
		<?php
	}
}

