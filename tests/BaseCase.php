<?php
/**
 * Shared helpers for the integration tests.
 *
 * @package Shakewell\Redirects
 */

namespace Shakewell\Redirects\Tests;

use Shakewell\Redirects\Store;
use WP_UnitTestCase;

/**
 * Creates redirects and visits paths.
 */
abstract class BaseCase extends WP_UnitTestCase {

	/**
	 * Creates a stored redirect.
	 *
	 * @param array  $over   Fields to override.
	 * @param string $status Post status.
	 */
	protected function make( array $over = array(), string $status = 'publish' ): int {
		$f = array_merge(
			array(
				'source'  => '/old/',
				'target'  => '/new/',
				'code'    => 301,
				'match'   => 'exact',
				'enabled' => true,
			),
			$over
		);
		return self::factory()->post->create(
			array(
				'post_type'   => Store::CPT,
				'post_status' => $status,
				'meta_input'  => Store::meta_input( $f ),
			)
		);
	}

	/**
	 * Runs the front-end redirect for a request URI and returns what it would send, or null.
	 *
	 * @param string $uri Request URI.
	 */
	protected function visit( string $uri ): ?array {
		$_SERVER['REQUEST_URI'] = $uri;
		$sent                   = null;
		$capture                = static function ( $location, $status = 0 ) use ( &$sent ) {
			$sent = array(
				'location' => $location,
				'status'   => $status,
			);
			return false; // Stops wp_redirect sending headers and exiting.
		};
		add_filter( 'wp_redirect', $capture, 10, 2 );
		\Shakewell\Redirects\Plugin::serve();
		remove_filter( 'wp_redirect', $capture, 10 );
		return $sent;
	}
}
