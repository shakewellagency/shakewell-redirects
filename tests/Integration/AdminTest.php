<?php
/**
 * Admin saving: nonce, capability and validation.
 *
 * @package Shakewell\Redirects
 */

namespace Shakewell\Redirects\Tests;

use Shakewell\Redirects\Admin;
use Shakewell\Redirects\Store;

final class AdminTest extends BaseCase {

	private function submit( int $id, array $fields, bool $nonce = true ): void {
		$_POST = array(
			'shakewell_redirects_source'  => $fields['source'] ?? '/old/',
			'shakewell_redirects_target'  => $fields['target'] ?? '/new/',
			'shakewell_redirects_code'    => $fields['code'] ?? '301',
			'shakewell_redirects_match'   => $fields['match'] ?? 'exact',
			'shakewell_redirects_enabled' => '1',
		);
		if ( $nonce ) {
			$_POST['shakewell_redirects_nonce'] = wp_create_nonce( 'shakewell_redirects_save' );
		}
		Admin::save( $id );
	}

	private function editor_screen_post(): int {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		return $this->make( array( 'source' => '/orig/' ) );
	}

	public function test_valid_submission_with_nonce_is_saved(): void {
		$id = $this->editor_screen_post();
		$this->submit( $id, array( 'source' => '/changed/', 'code' => '302' ) );
		$this->assertSame( '/changed/', get_post_meta( $id, Store::key( 'source' ), true ) );
		$this->assertSame( 302, Store::row( $id )['code'] );
	}

	public function test_missing_nonce_is_refused(): void {
		$id = $this->editor_screen_post();
		$this->submit( $id, array( 'source' => '/changed/' ), false );
		$this->assertSame( '/orig/', get_post_meta( $id, Store::key( 'source' ), true ) );
	}

	public function test_user_without_capability_is_refused(): void {
		$id = $this->make( array( 'source' => '/orig/' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->submit( $id, array( 'source' => '/changed/' ) );
		$this->assertSame( '/orig/', get_post_meta( $id, Store::key( 'source' ), true ) );
	}

	public function test_contributor_cannot_manage_redirects(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$this->assertFalse( current_user_can( 'edit_post', $this->make() ) );
	}

	public function test_invalid_submission_is_not_stored_becomes_draft_and_shows_the_error(): void {
		$id = $this->editor_screen_post();
		$this->submit( $id, array( 'target' => 'javascript:alert(1)' ) );
		$this->assertSame( '/orig/', get_post_meta( $id, Store::key( 'source' ), true ) );
		$this->assertSame( 'draft', get_post_status( $id ) );
		ob_start();
		Admin::notices();
		$this->assertStringContainsString( 'notice-error', ob_get_clean() );
	}

	public function test_warning_is_shown_but_the_redirect_is_saved(): void {
		$this->set_permalink_structure( '/%postname%/' );
		self::factory()->post->create( array( 'post_type' => 'page', 'post_name' => 'live-page' ) );
		$id = $this->editor_screen_post();
		$this->submit( $id, array( 'source' => '/live-page/' ) );
		$this->assertSame( '/live-page/', get_post_meta( $id, Store::key( 'source' ), true ) );
		ob_start();
		Admin::notices();
		$this->assertStringContainsString( 'notice-warning', ob_get_clean() );
	}

	public function test_manage_capability_is_filterable(): void {
		add_filter( 'shakewell_redirects_manage_capability', static fn() => 'manage_options' );
		unregister_post_type( Store::CPT );
		Store::register();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertFalse( current_user_can( 'edit_post', $this->make() ) );
	}

	public function test_import_requires_manage_options_by_default(): void {
		$this->assertSame( 'manage_options', Admin::import_cap() );
	}

	public function test_import_form_refuses_a_missing_nonce(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->expectException( \WPDieException::class );
		Admin::handle_import();
	}

	public function test_import_form_refuses_an_editor_with_a_valid_nonce(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'shakewell_redirects_import' );
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'not allowed' );
		Admin::handle_import();
	}

	public function test_export_refuses_a_missing_nonce(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->expectException( \WPDieException::class );
		Admin::handle_export();
	}
}
