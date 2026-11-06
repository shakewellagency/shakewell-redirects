<?php
/**
 * Front-end matching and redirecting.
 *
 * @package Shakewell\Redirects
 */

namespace Shakewell\Redirects\Tests;

final class ServeTest extends BaseCase {

	public function test_exact_match_redirects_with_stored_target_and_code(): void {
		$this->make( array( 'source' => '/subscribe/', 'target' => '/subscribe-opt-in-list/' ) );
		$this->assertSame( array( 'location' => '/subscribe-opt-in-list/', 'status' => 301 ), $this->visit( '/subscribe/' ) );
	}

	public function test_302_is_sent_as_302(): void {
		$this->make( array( 'code' => 302 ) );
		$this->assertSame( 302, $this->visit( '/old/' )['status'] );
	}

	public function test_match_ignores_case(): void {
		$this->make( array( 'source' => '/Venues/EST/' ) );
		$this->assertNotNull( $this->visit( '/venues/est/' ) );
	}

	public function test_match_ignores_trailing_slash_both_ways(): void {
		$this->make( array( 'source' => '/a/b' ) );
		$this->assertNotNull( $this->visit( '/a/b/' ) );
		$this->make( array( 'source' => '/c/d/' ) );
		$this->assertNotNull( $this->visit( '/c/d' ) );
	}

	public function test_query_string_is_ignored_for_matching_and_not_forwarded(): void {
		$this->make();
		$this->assertSame( '/new/', $this->visit( '/old/?utm_source=x' )['location'] );
	}

	public function test_exact_rule_does_not_match_children(): void {
		$this->make();
		$this->assertNull( $this->visit( '/old/child/' ) );
	}

	public function test_prefix_rule_sends_everything_under_it_to_the_target_without_the_remainder(): void {
		$this->make( array( 'source' => '/whatson/rnbfridays/', 'target' => '/whatson/nightlife/ivy/', 'match' => 'prefix' ) );
		$this->assertSame( '/whatson/nightlife/ivy/', $this->visit( '/whatson/rnbfridays/january19/' )['location'] );
		$this->assertSame( '/whatson/nightlife/ivy/', $this->visit( '/whatson/rnbfridays/' )['location'] );
	}

	public function test_prefix_rule_does_not_match_a_sibling_with_the_same_start(): void {
		$this->make( array( 'source' => '/whatson/rnb/', 'match' => 'prefix' ) );
		$this->assertNull( $this->visit( '/whatson/rnbfridays/' ) );
	}

	public function test_exact_beats_prefix(): void {
		$this->make( array( 'source' => '/a/', 'target' => '/prefix/', 'match' => 'prefix' ) );
		$this->make( array( 'source' => '/a/b/', 'target' => '/exact/' ) );
		$this->assertSame( '/exact/', $this->visit( '/a/b/' )['location'] );
	}

	public function test_longest_prefix_wins(): void {
		$this->make( array( 'source' => '/a/', 'target' => '/short/', 'match' => 'prefix' ) );
		$this->make( array( 'source' => '/a/b/', 'target' => '/long/', 'match' => 'prefix' ) );
		$this->assertSame( '/long/', $this->visit( '/a/b/c/' )['location'] );
		$this->assertSame( '/short/', $this->visit( '/a/x/' )['location'] );
	}

	public function test_external_absolute_target_is_used_as_written(): void {
		$this->make( array( 'source' => '/melbournecup/', 'target' => 'https://melbournecupday.merivale.com' ) );
		$this->assertSame( 'https://melbournecupday.merivale.com', $this->visit( '/melbournecup/' )['location'] );
	}

	public function test_front_end_can_be_switched_off_with_the_filter(): void {
		$this->make();
		add_filter( 'shakewell_redirects_serve', '__return_false' );
		$this->assertNull( $this->visit( '/old/' ) );
	}

	public function test_disabled_draft_and_trashed_redirects_do_not_fire(): void {
		$this->make( array( 'source' => '/off/', 'enabled' => false ) );
		$this->make( array( 'source' => '/draft/' ), 'draft' );
		wp_trash_post( $this->make( array( 'source' => '/trash/' ) ) );
		$this->assertNull( $this->visit( '/off/' ) );
		$this->assertNull( $this->visit( '/draft/' ) );
		$this->assertNull( $this->visit( '/trash/' ) );
	}
}
