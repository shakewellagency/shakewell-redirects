<?php
/**
 * Removes the plugin's data on deletion, but only when asked to.
 *
 * Redirects are editors' content, so they stay unless SHAKEWELL_REDIRECTS_DELETE_DATA is true in wp-config.php
 * or the option shakewell_redirects_delete_data is set (wp option update shakewell_redirects_delete_data 1).
 *
 * @package Shakewell\Redirects
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ( defined( 'SHAKEWELL_REDIRECTS_DELETE_DATA' ) && SHAKEWELL_REDIRECTS_DELETE_DATA ) || get_option( 'shakewell_redirects_delete_data' ) ) {
	$shakewell_redirects_ids = get_posts(
		array(
			'post_type'      => 'shakewell_redirect',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $shakewell_redirects_ids as $shakewell_redirects_id ) {
		wp_delete_post( $shakewell_redirects_id, true ); // Also removes the post's meta.
	}
	delete_transient( 'shakewell_redirects_live' );
	delete_option( 'shakewell_redirects_delete_data' );
}
