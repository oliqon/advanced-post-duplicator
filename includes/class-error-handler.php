<?php
/**
 * Error handling and logging class
 *
 * @package Advanced_Post_Duplicator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Error handler class
 */
class APD_Error_Handler {

	/**
	 * Log an error
	 *
	 * @param string $message Error message
	 * @param array  $context Additional context data
	 * @param int    $site_id Site ID where error occurred
	 * @return void
	 */
	public static function log_error( $message, $context = array(), $site_id = 0 ) {
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'message'   => $message,
			'context'   => $context,
			'site_id'   => $site_id ? $site_id : get_current_blog_id(),
		);

		$logs = get_option( 'apd_cross_site_logs', array() );
		$logs[] = $log_entry;

		// Keep only last 100 log entries
		if ( count( $logs ) > 100 ) {
			$logs = array_slice( $logs, -100 );
		}

		update_option( 'apd_cross_site_logs', $logs );
	}

	/**
	 * Log a successful operation
	 *
	 * @param string $message Success message
	 * @param array  $context Additional context data
	 * @return void
	 */
	public static function log_success( $message, $context = array() ) {
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'message'   => $message,
			'context'   => $context,
			'type'      => 'success',
			'site_id'   => get_current_blog_id(),
		);

		$logs = get_option( 'apd_cross_site_logs', array() );
		$logs[] = $log_entry;

		if ( count( $logs ) > 100 ) {
			$logs = array_slice( $logs, -100 );
		}

		update_option( 'apd_cross_site_logs', $logs );
	}

	/**
	 * Get recent logs
	 *
	 * @param int $limit Number of logs to retrieve
	 * @return array Array of log entries
	 */
	public static function get_logs( $limit = 50 ) {
		$logs = get_option( 'apd_cross_site_logs', array() );
		return array_slice( array_reverse( $logs ), 0, $limit );
	}

	/**
	 * Clear all logs
	 *
	 * @return void
	 */
	public static function clear_logs() {
		delete_option( 'apd_cross_site_logs' );
	}

	/**
	 * Get errors only
	 *
	 * @param int $limit Number of errors to retrieve
	 * @return array Array of error log entries
	 */
	public static function get_errors( $limit = 50 ) {
		$all_logs = self::get_logs( $limit * 2 );
		$errors = array();

		foreach ( $all_logs as $log ) {
			if ( ! isset( $log['type'] ) || 'success' !== $log['type'] ) {
				$errors[] = $log;
				if ( count( $errors ) >= $limit ) {
					break;
				}
			}
		}

		return $errors;
	}
}

