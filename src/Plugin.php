<?php
/**
 * Plugin bootstrap and front-end redirecting.
 *
 * @package Shakewell\Redirects
 */

namespace Shakewell\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin into WordPress.
 */
final class Plugin {

	/**
	 * Registers hooks. Called on plugins_loaded.
	 */
	public static function boot(): void {
		Store::boot();
		Rest::boot();
		add_action( 'template_redirect', array( self::class, 'serve' ), 0 );
		if ( is_admin() ) {
			Admin::boot();
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'shakewell-redirects', Cli::class );
		}
	}

	/**
	 * Redirects the current request if a rule matches.
	 *
	 * Off when SHAKEWELL_REDIRECTS_DISABLE is true or the shakewell_redirects_serve filter returns false
	 * (headless sites do the redirecting in the front end).
	 */
	public static function serve(): void {
		$on = ! ( defined( 'SHAKEWELL_REDIRECTS_DISABLE' ) && SHAKEWELL_REDIRECTS_DISABLE );
		if ( ! apply_filters( 'shakewell_redirects_serve', $on ) ) {
			return;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only compared with stored paths, never output.
		$path = (string) wp_parse_url( (string) $uri, PHP_URL_PATH );
		$home = rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( '' !== $home && str_starts_with( $path, $home . '/' ) ) {
			$path = substr( $path, strlen( $home ) );
		}
		$hit = Rules::find( $path, Store::live() );
		// wp_redirect, not wp_safe_redirect: external targets are editor-entered and validated on save.
		if ( $hit && wp_redirect( $hit['target'], $hit['code'], 'Shakewell Redirects' ) ) { // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}
	}
}
