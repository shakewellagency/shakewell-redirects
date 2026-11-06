<?php
/**
 * Save-time validation rules.
 *
 * @package Shakewell\Redirects
 */

namespace Shakewell\Redirects\Tests;

use Shakewell\Redirects\Rules;
use Shakewell\Redirects\Store;

final class ValidationTest extends BaseCase {

	private function check( array $over, int $exclude = 0 ): array {
		$f = array_merge( array( 'source' => '/x/', 'target' => '/y/', 'code' => 301, 'match' => 'exact', 'enabled' => true ), $over );
		return Rules::validate( $f, Store::all( $exclude ) );
	}

	public function test_a_valid_redirect_has_no_errors_or_warnings(): void {
		$this->assertSame( array( 'errors' => array(), 'warnings' => array() ), $this->check( array() ) );
	}

	/**
	 * @dataProvider bad_sources
	 */
	public function test_source_must_be_a_clean_path( string $source ): void {
		$this->assertNotEmpty( $this->check( array( 'source' => $source ) )['errors'] );
	}

	public static function bad_sources(): array {
		return array( 'no slash' => array( 'x' ), 'protocol relative' => array( '//x' ), 'query' => array( '/x?a=1' ), 'space' => array( '/x y' ), 'full url' => array( 'https://a.test/x' ), 'empty' => array( '' ) );
	}

	/**
	 * @dataProvider bad_targets
	 */
	public function test_target_must_be_a_site_path_or_http_url( string $target ): void {
		$this->assertNotEmpty( $this->check( array( 'target' => $target ) )['errors'] );
	}

	public static function bad_targets(): array {
		return array(
			'javascript' => array( 'javascript:alert(1)' ),
			'protocol relative' => array( '//evil.test' ),
			'backslash trick' => array( '/\\evil.test' ),
			'ftp' => array( 'ftp://a.test/' ),
			'no host' => array( 'https://' ),
			'newline' => array( "/ok\r\nSet-Cookie: a=b" ),
			'empty' => array( '' ),
			'bare word' => array( 'new' ),
		);
	}

	public function test_absolute_http_and_https_targets_are_allowed(): void {
		$this->assertSame( array(), $this->check( array( 'target' => 'http://a.test/p?q=1#h' ) )['errors'] );
		$this->assertSame( array(), $this->check( array( 'target' => 'https://melbournecupday.merivale.com' ) )['errors'] );
	}

	public function test_code_and_match_are_restricted(): void {
		$this->assertNotEmpty( $this->check( array( 'code' => 200 ) )['errors'] );
		$this->assertNotEmpty( $this->check( array( 'match' => 'regex' ) )['errors'] );
	}

	public function test_prefix_on_root_is_refused(): void {
		$this->assertNotEmpty( $this->check( array( 'source' => '/', 'match' => 'prefix' ) )['errors'] );
	}

	public function test_redirect_to_itself_is_refused(): void {
		$this->assertNotEmpty( $this->check( array( 'source' => '/x/', 'target' => '/X' ) )['errors'] );
	}

	public function test_prefix_rule_whose_target_is_under_itself_is_refused(): void {
		$this->assertNotEmpty( $this->check( array( 'source' => '/a/', 'target' => '/a/b/', 'match' => 'prefix' ) )['errors'] );
	}

	public function test_absolute_target_on_this_site_is_checked_for_self_redirect(): void {
		$this->assertNotEmpty( $this->check( array( 'source' => '/x/', 'target' => home_url( '/x/' ) ) )['errors'] );
	}

	public function test_loop_with_another_redirect_is_refused(): void {
		$this->make( array( 'source' => '/a/', 'target' => '/b/' ) );
		$this->assertNotEmpty( $this->check( array( 'source' => '/b/', 'target' => '/a/' ) )['errors'] );
	}

	public function test_double_hop_forward_is_refused(): void {
		$this->make( array( 'source' => '/b/', 'target' => '/c/' ) );
		$this->assertNotEmpty( $this->check( array( 'source' => '/a/', 'target' => '/b/' ) )['errors'] );
	}

	public function test_double_hop_backward_is_refused(): void {
		$this->make( array( 'source' => '/a/', 'target' => '/b/' ) );
		$this->assertNotEmpty( $this->check( array( 'source' => '/b/', 'target' => '/c/' ) )['errors'] );
	}

	public function test_disabled_redirects_do_not_count_as_hops(): void {
		$this->make( array( 'source' => '/b/', 'target' => '/c/', 'enabled' => false ) );
		$this->assertSame( array(), $this->check( array( 'source' => '/a/', 'target' => '/b/' ) )['errors'] );
	}

	public function test_duplicate_source_is_refused_whatever_the_case_or_slash(): void {
		$this->make( array( 'source' => '/Dup/' ) );
		$this->assertNotEmpty( $this->check( array( 'source' => '/dup' ) )['errors'] );
	}

	public function test_editing_a_redirect_does_not_clash_with_itself(): void {
		$id = $this->make( array( 'source' => '/dup/' ) );
		$this->assertSame( array(), $this->check( array( 'source' => '/dup/' ), $id )['errors'] );
	}

	public function test_exact_and_prefix_on_the_same_path_may_coexist(): void {
		$this->make( array( 'source' => '/a/', 'match' => 'prefix', 'target' => '/p/' ) );
		$this->assertSame( array(), $this->check( array( 'source' => '/a/', 'target' => '/e/' ) )['errors'] );
	}

	public function test_source_that_is_a_published_page_warns_but_does_not_block(): void {
		$this->set_permalink_structure( '/%postname%/' );
		self::factory()->post->create( array( 'post_type' => 'page', 'post_name' => 'about-us', 'post_title' => 'About us' ) );
		$res = $this->check( array( 'source' => '/about-us/' ) );
		$this->assertSame( array(), $res['errors'] );
		$this->assertCount( 1, $res['warnings'] );
	}

	public function test_draft_page_does_not_warn(): void {
		$this->set_permalink_structure( '/%postname%/' );
		self::factory()->post->create( array( 'post_type' => 'page', 'post_name' => 'soon', 'post_status' => 'draft' ) );
		$this->assertSame( array(), $this->check( array( 'source' => '/soon/' ) )['warnings'] );
	}
}
