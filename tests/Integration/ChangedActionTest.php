<?php
/**
 * The shakewell_redirects_changed action.
 *
 * @package Shakewell\Redirects
 */

namespace Shakewell\Redirects\Tests;

use Shakewell\Redirects\Store;

final class ChangedActionTest extends BaseCase {

	/**
	 * Every payload the action received.
	 *
	 * @var array
	 */
	private array $calls = array();

	public function set_up(): void {
		parent::set_up();
		$this->calls = array();
		add_action(
			'shakewell_redirects_changed',
			function ( $paths ) {
				$this->calls[] = $paths;
			}
		);
	}

	private function last(): array {
		return end( $this->calls );
	}

	public function test_create_fires_with_the_source(): void {
		$this->make( array( 'source' => '/new-one/' ) );
		$this->assertSame( array( '/new-one/' ), $this->last() );
	}

	public function test_changing_the_source_announces_old_and_new(): void {
		$id = $this->make( array( 'source' => '/before/' ) );
		update_post_meta( $id, Store::key( 'source' ), '/after/' );
		wp_update_post( array( 'ID' => $id, 'post_title' => 'touch' ) );
		$this->assertEqualsCanonicalizing( array( '/before/', '/after/' ), $this->last() );
	}

	public function test_disabling_fires(): void {
		$id = $this->make( array( 'source' => '/toggle/' ) );
		$this->calls = array();
		update_post_meta( $id, Store::key( 'enabled' ), false );
		wp_update_post( array( 'ID' => $id, 'post_title' => 'touch' ) );
		$this->assertSame( array( '/toggle/' ), $this->last() );
	}

	public function test_trash_and_restore_fire(): void {
		$id = $this->make( array( 'source' => '/bin/' ) );
		$this->calls = array();
		wp_trash_post( $id );
		$this->assertContains( array( '/bin/' ), $this->calls );
		$this->calls = array();
		wp_untrash_post( $id );
		$this->assertContains( array( '/bin/' ), $this->calls );
	}

	public function test_delete_fires_with_the_source(): void {
		$id = $this->make( array( 'source' => '/gone/' ) );
		$this->calls = array();
		wp_delete_post( $id, true );
		$this->assertSame( array( '/gone/' ), $this->last() );
	}

	public function test_other_post_types_do_not_fire(): void {
		self::factory()->post->create();
		$this->assertSame( array(), $this->calls );
	}

	public function test_the_live_list_is_refreshed_after_a_change(): void {
		$this->make( array( 'source' => '/one/' ) );
		$this->assertCount( 1, Store::live() );
		$this->make( array( 'source' => '/two/' ) );
		$this->assertCount( 2, Store::live() );
	}
}
