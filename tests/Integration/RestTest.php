<?php
/**
 * The public REST list.
 *
 * @package Shakewell\Redirects
 */

namespace Shakewell\Redirects\Tests;

use WP_REST_Request;

final class RestTest extends BaseCase {

	private function get( array $headers = array() ): \WP_REST_Response {
		$GLOBALS['wp_rest_server'] = null;
		$req                       = new WP_REST_Request( 'GET', '/shakewell-redirects/v1/redirects' );
		foreach ( $headers as $k => $v ) {
			$req->set_header( $k, $v );
		}
		return rest_do_request( $req );
	}

	public function test_lists_enabled_redirects_with_only_four_fields_and_no_login(): void {
		wp_set_current_user( 0 );
		$this->make( array( 'source' => '/whatson/rnbfridays/', 'target' => '/ivy/', 'code' => 302, 'match' => 'prefix' ) );
		$res = $this->get();
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( array( array( 'source' => '/whatson/rnbfridays/', 'target' => '/ivy/', 'code' => 302, 'match' => 'prefix' ) ), $res->get_data() );
	}

	public function test_disabled_draft_and_trashed_are_not_listed(): void {
		$this->make( array( 'source' => '/off/', 'enabled' => false ) );
		$this->make( array( 'source' => '/draft/' ), 'draft' );
		wp_trash_post( $this->make( array( 'source' => '/trash/' ) ) );
		$this->make( array( 'source' => '/on/' ) );
		$this->assertSame( array( '/on/' ), wp_list_pluck( $this->get()->get_data(), 'source' ) );
	}

	public function test_sends_cache_headers_and_etag(): void {
		$this->make();
		$h = $this->get()->get_headers();
		$this->assertStringContainsString( 'public', $h['Cache-Control'] );
		$this->assertNotEmpty( $h['ETag'] );
	}

	public function test_matching_etag_gets_304(): void {
		$this->make();
		$etag = $this->get()->get_headers()['ETag'];
		$this->assertSame( 304, $this->get( array( 'If-None-Match' => $etag ) )->get_status() );
	}
}
