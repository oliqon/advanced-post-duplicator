<?php
/**
 * Multisite UI class
 *
 * @package Advanced_Post_Duplicator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Multisite UI class
 */
class APD_Multisite_UI {

	/**
	 * Instance of this class
	 *
	 * @var APD_Multisite_UI
	 */
	private static $instance = null;

	/**
	 * Get instance of this class
	 *
	 * @return APD_Multisite_UI
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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'apd_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'apd_settings_tab_content_cross_site', array( $this, 'render_tab_content' ) );
	}

	/**
	 * Add settings tab
	 *
	 * @param array $tabs Existing tabs
	 * @return array Modified tabs
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['cross_site'] = __( 'Cross-Site Duplication', 'advanced-post-duplicator' );
		return $tabs;
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'settings_page_post-duplicator-settings' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'apd-multisite-js',
			APD_PLUGIN_URL . 'admin/assets/js/multisite.js',
			array( 'jquery' ),
			APD_VERSION,
			true
		);

		wp_localize_script(
			'apd-multisite-js',
			'apdMultisite',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'apd_cross_site_nonce' ),
				'sites'   => APD_Multisite::get_network_sites( true ),
			)
		);

		wp_enqueue_style(
			'apd-multisite-css',
			APD_PLUGIN_URL . 'admin/assets/css/multisite.css',
			array(),
			APD_VERSION
		);
	}

	/**
	 * Render tab content
	 */
	public function render_tab_content() {
		if ( ! APD_Multisite::is_multisite() ) {
			?>
			<div class="notice notice-info">
				<p><?php esc_html_e( 'Cross-site duplication is only available on WordPress Multisite networks.', 'advanced-post-duplicator' ); ?></p>
			</div>
			<?php
			return;
		}

		if ( ! APD_Multisite::user_can_cross_site_duplicate() ) {
			?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'You do not have permission to perform cross-site duplication.', 'advanced-post-duplicator' ); ?></p>
			</div>
			<?php
			return;
		}

		$sites = APD_Multisite::get_network_sites( true );
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$excluded_types = array( 'attachment', 'revision', 'nav_menu_item' );
		?>
		<div class="apd-cross-site-duplication">
			<h2><?php esc_html_e( 'Copy Posts Between Sites', 'advanced-post-duplicator' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Select a source site and destination site, then choose posts to duplicate.', 'advanced-post-duplicator' ); ?></p>

			<form id="apd-cross-site-form" method="post">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="apd_source_site"><?php esc_html_e( 'Source Site', 'advanced-post-duplicator' ); ?></label>
							</th>
							<td>
								<select id="apd_source_site" name="source_site_id" required>
									<option value=""><?php esc_html_e( '-- Select Source Site --', 'advanced-post-duplicator' ); ?></option>
									<?php foreach ( $sites as $site ) : ?>
										<option value="<?php echo esc_attr( $site['id'] ); ?>">
											<?php echo esc_html( $site['blogname'] ); ?> (<?php echo esc_html( $site['domain'] . $site['path'] ); ?>)
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="apd_destination_site"><?php esc_html_e( 'Destination Site', 'advanced-post-duplicator' ); ?></label>
							</th>
							<td>
								<select id="apd_destination_site" name="destination_site_id" required>
									<option value=""><?php esc_html_e( '-- Select Destination Site --', 'advanced-post-duplicator' ); ?></option>
									<?php foreach ( $sites as $site ) : ?>
										<option value="<?php echo esc_attr( $site['id'] ); ?>">
											<?php echo esc_html( $site['blogname'] ); ?> (<?php echo esc_html( $site['domain'] . $site['path'] ); ?>)
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr id="apd-post-type-row" style="display: none;">
							<th scope="row">
								<label for="apd_post_type"><?php esc_html_e( 'Post Type', 'advanced-post-duplicator' ); ?></label>
							</th>
							<td>
								<select id="apd_post_type" name="post_type">
									<?php foreach ( $post_types as $post_type ) : ?>
										<?php if ( ! in_array( $post_type->name, $excluded_types, true ) ) : ?>
											<option value="<?php echo esc_attr( $post_type->name ); ?>">
												<?php echo esc_html( $post_type->label ); ?>
											</option>
										<?php endif; ?>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr id="apd-post-search-row" style="display: none;">
							<th scope="row">
								<label for="apd_post_search"><?php esc_html_e( 'Search Posts', 'advanced-post-duplicator' ); ?></label>
							</th>
							<td>
								<input type="text" id="apd_post_search" class="regular-text" placeholder="<?php esc_attr_e( 'Search posts...', 'advanced-post-duplicator' ); ?>">
								<button type="button" class="button" id="apd-search-posts"><?php esc_html_e( 'Search', 'advanced-post-duplicator' ); ?></button>
							</td>
						</tr>

						<tr id="apd-posts-list-row" style="display: none;">
							<th scope="row">
								<?php esc_html_e( 'Select Posts', 'advanced-post-duplicator' ); ?>
							</th>
							<td>
								<div id="apd-posts-list" class="apd-posts-list"></div>
								<div id="apd-posts-pagination" class="apd-pagination"></div>
							</td>
						</tr>

						<tr id="apd-options-row" style="display: none;">
							<th scope="row">
								<?php esc_html_e( 'Options', 'advanced-post-duplicator' ); ?>
							</th>
							<td>
								<fieldset>
									<label>
										<input type="checkbox" name="copy_media" value="1" checked>
										<?php esc_html_e( 'Copy media files', 'advanced-post-duplicator' ); ?>
									</label>
									<br>
									<label>
										<?php esc_html_e( 'Post Status:', 'advanced-post-duplicator' ); ?>
										<select name="post_status">
											<option value="draft"><?php esc_html_e( 'Draft', 'advanced-post-duplicator' ); ?></option>
											<option value="publish"><?php esc_html_e( 'Published', 'advanced-post-duplicator' ); ?></option>
											<option value="pending"><?php esc_html_e( 'Pending', 'advanced-post-duplicator' ); ?></option>
											<option value="same"><?php esc_html_e( 'Same as original', 'advanced-post-duplicator' ); ?></option>
										</select>
									</label>
								</fieldset>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<button type="button" class="button button-primary" id="apd-duplicate-cross-site" disabled>
						<?php esc_html_e( 'Duplicate to Destination Site', 'advanced-post-duplicator' ); ?>
					</button>
					<span class="spinner"></span>
				</p>
			</form>

			<div id="apd-duplication-results" style="display: none;"></div>

			<h3><?php esc_html_e( 'Duplication Logs', 'advanced-post-duplicator' ); ?></h3>
			<div id="apd-logs-container">
				<p><?php esc_html_e( 'Logs will appear here after duplications.', 'advanced-post-duplicator' ); ?></p>
			</div>
		</div>
		<?php
	}
}

