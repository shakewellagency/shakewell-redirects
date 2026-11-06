<?php
/**
 * Read-only public list of the enabled redirects, for headless front ends.
 *
 * @package Shakewell\Redirects
 */

namespace Shakewell\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * GET /wp-json/shakewell-redirects/v1/redirects
 */
final class Rest {

	/**
	 * Registers the route.
	 */
	public static function boot(): void {
		add_action( 'rest_api_init', array( self::class, 'register' ) );
	}

	/**
	 * Registers the route. It is public on purpose: redirects are public information.
	 */
	public static function register(): void {
		register_rest_route(
			'shakewell-redirects/v1',
			'/redirects',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Returns the list with an ETag, answering 304 when the caller already has it.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public static function get( \WP_REST_Request $request ): \WP_REST_Response {
		$list = Store::live();
		$etag = '"' . md5( (string) wp_json_encode( $list ) ) . '"';
		$res  = new \WP_REST_Response( $list );
		$res->header( 'ETag', $etag );
		$res->header( 'Cache-Control', 'public, max-age=60, stale-while-revalidate=300' );
		if ( $etag === $request->get_header( 'if_none_match' ) ) {
			$res->set_data( null );
			$res->set_status( 304 );
		}
		return $res;
	}
}
