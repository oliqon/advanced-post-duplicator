<?php
/**
 * Settings page class
 *
 * @package Advanced_Post_Duplicator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class
 */
class APD_Settings {

	/**
	 * Instance of this class
	 *
	 * @var APD_Settings
	 */
	private static $instance = null;

	/**
	 * Get instance of this class
	 *
	 * @return APD_Settings
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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'Post Duplicator Settings', 'advanced-post-duplicator' ),
			__( 'Post Duplicator', 'advanced-post-duplicator' ),
			'manage_options',
			'post-duplicator-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting(
			'apd_settings_group',
			'apd_settings',
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input Settings input
	 * @return array Sanitized settings
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// Post status
		$allowed_statuses = array( 'same', 'draft', 'pending', 'publish' );
		$sanitized['post_status'] = isset( $input['post_status'] ) && in_array( $input['post_status'], $allowed_statuses, true )
			? $input['post_status']
			: 'same';

		// Post date
		$allowed_dates = array( 'duplicate', 'current' );
		$sanitized['post_date'] = isset( $input['post_date'] ) && in_array( $input['post_date'], $allowed_dates, true )
			? $input['post_date']
			: 'duplicate';

		// Offset date
		$sanitized['offset_date'] = isset( $input['offset_date'] ) ? 1 : 0;
		
		// Offset values
		$sanitized['offset_days']    = isset( $input['offset_days'] ) ? absint( $input['offset_days'] ) : 0;
		$sanitized['offset_hours']   = isset( $input['offset_hours'] ) ? absint( $input['offset_hours'] ) : 0;
		$sanitized['offset_minutes'] = isset( $input['offset_minutes'] ) ? absint( $input['offset_minutes'] ) : 0;
		$sanitized['offset_seconds'] = isset( $input['offset_seconds'] ) ? absint( $input['offset_seconds'] ) : 0;

		// Offset direction
		$allowed_directions = array( 'older', 'newer' );
		$sanitized['offset_direction'] = isset( $input['offset_direction'] ) && in_array( $input['offset_direction'], $allowed_directions, true )
			? $input['offset_direction']
			: 'older';

		return $sanitized;
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'settings_page_post-duplicator-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'apd-admin-css',
			APD_PLUGIN_URL . 'admin/assets/css/admin.css',
			array(),
			APD_VERSION
		);

		wp_enqueue_script(
			'apd-admin-js',
			APD_PLUGIN_URL . 'admin/assets/js/admin.js',
			array( 'jquery' ),
			APD_VERSION,
			true
		);
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get current tab
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';

		// Get available tabs
		$tabs = apply_filters( 'apd_settings_tabs', array( 'general' => __( 'General Settings', 'advanced-post-duplicator' ) ) );

		// Save settings message
		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error(
				'apd_messages',
				'apd_message',
				__( 'Settings saved successfully.', 'advanced-post-duplicator' ),
				'success'
			);
		}

		settings_errors( 'apd_messages' );

		$settings = get_option( 'apd_settings', array() );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_key, admin_url( 'options-general.php?page=post-duplicator-settings' ) ) ); ?>" 
					   class="nav-tab <?php echo $current_tab === $tab_key ? esc_attr( 'nav-tab-active' ) : ''; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php if ( 'general' === $current_tab ) : ?>
				<p><?php esc_html_e( 'Customize the settings for duplicated posts.', 'advanced-post-duplicator' ); ?></p>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'apd_settings_group' );
				wp_nonce_field( 'apd_settings_nonce', 'apd_settings_nonce_field' );
				?>

				<table class="form-table" role="presentation">
					<tbody>
						<!-- Post Status -->
						<tr>
							<th scope="row">
								<label for="apd_post_status"><?php esc_html_e( 'Post Status', 'advanced-post-duplicator' ); ?></label>
							</th>
							<td>
												<select name="apd_settings[post_status]" id="apd_post_status">
									<?php
									$post_status_value = isset( $settings['post_status'] ) ? $settings['post_status'] : 'same';
									?>
									<option value="same" <?php selected( $post_status_value, 'same' ); ?>>
										<?php esc_html_e( 'Same as original', 'advanced-post-duplicator' ); ?>
									</option>
									<option value="draft" <?php selected( $post_status_value, 'draft' ); ?>>
										<?php esc_html_e( 'Draft', 'advanced-post-duplicator' ); ?>
									</option>
									<option value="pending" <?php selected( $post_status_value, 'pending' ); ?>>
										<?php esc_html_e( 'Pending', 'advanced-post-duplicator' ); ?>
									</option>
									<option value="publish" <?php selected( $post_status_value, 'publish' ); ?>>
										<?php esc_html_e( 'Published', 'advanced-post-duplicator' ); ?>
									</option>
								</select>
							</td>
						</tr>

						<!-- Post Date -->
						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'Post Date', 'advanced-post-duplicator' ); ?></label>
							</th>
							<td>
								<fieldset>
									<?php
									$post_date_value = isset( $settings['post_date'] ) ? $settings['post_date'] : 'duplicate';
									?>
									<label>
										<input type="radio" name="apd_settings[post_date]" value="duplicate" <?php checked( $post_date_value, 'duplicate' ); ?>>
										<?php esc_html_e( 'Duplicate Timestamp', 'advanced-post-duplicator' ); ?>
									</label>
									<br>
									<label>
										<input type="radio" name="apd_settings[post_date]" value="current" <?php checked( $post_date_value, 'current' ); ?>>
										<?php esc_html_e( 'Current Time', 'advanced-post-duplicator' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>

						<!-- Offset Date -->
						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'Offset Date', 'advanced-post-duplicator' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" name="apd_settings[offset_date]" value="1" <?php checked( isset( $settings['offset_date'] ) ? $settings['offset_date'] : 0, 1 ); ?> class="apd-offset-date-toggle">
									<?php esc_html_e( 'Enable date offset', 'advanced-post-duplicator' ); ?>
								</label>
								
								<div class="apd-offset-fields" style="<?php echo isset( $settings['offset_date'] ) && $settings['offset_date'] ? '' : 'display:none;'; ?>">
									<p>
										<input type="number" name="apd_settings[offset_days]" id="apd_offset_days" min="0" value="<?php echo esc_attr( isset( $settings['offset_days'] ) ? $settings['offset_days'] : 1 ); ?>" class="small-text">
										<label for="apd_offset_days"><?php esc_html_e( 'days', 'advanced-post-duplicator' ); ?></label>
										
										<input type="number" name="apd_settings[offset_hours]" id="apd_offset_hours" min="0" value="<?php echo esc_attr( isset( $settings['offset_hours'] ) ? $settings['offset_hours'] : 1 ); ?>" class="small-text">
										<label for="apd_offset_hours"><?php esc_html_e( 'hours', 'advanced-post-duplicator' ); ?></label>
										
										<input type="number" name="apd_settings[offset_minutes]" id="apd_offset_minutes" min="0" value="<?php echo esc_attr( isset( $settings['offset_minutes'] ) ? $settings['offset_minutes'] : 1 ); ?>" class="small-text">
										<label for="apd_offset_minutes"><?php esc_html_e( 'minutes', 'advanced-post-duplicator' ); ?></label>
										
										<input type="number" name="apd_settings[offset_seconds]" id="apd_offset_seconds" min="0" value="<?php echo esc_attr( isset( $settings['offset_seconds'] ) ? $settings['offset_seconds'] : 0 ); ?>" class="small-text">
										<label for="apd_offset_seconds"><?php esc_html_e( 'seconds', 'advanced-post-duplicator' ); ?></label>
										
										<select name="apd_settings[offset_direction]" id="apd_offset_direction">
											<?php
											$offset_direction_value = isset( $settings['offset_direction'] ) ? $settings['offset_direction'] : 'older';
											?>
											<option value="older" <?php selected( $offset_direction_value, 'older' ); ?>>
												<?php esc_html_e( 'older', 'advanced-post-duplicator' ); ?>
											</option>
											<option value="newer" <?php selected( $offset_direction_value, 'newer' ); ?>>
												<?php esc_html_e( 'newer', 'advanced-post-duplicator' ); ?>
											</option>
										</select>
									</p>
								</div>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Save Changes', 'advanced-post-duplicator' ) ); ?>
			</form>
			<?php else : ?>
				<?php do_action( 'apd_settings_tab_content_' . $current_tab ); ?>
			<?php endif; ?>
		</div>
		<?php
	}
}

