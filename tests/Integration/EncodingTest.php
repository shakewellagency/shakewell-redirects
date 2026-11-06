<?php
/**
 * Percent-encoded sources and targets are stored and served exactly as entered.
 *
 * @package Shakewell\Redirects
 */

namespace Shakewell\Redirects\Tests;

use Shakewell\Redirects\Importer;
use Shakewell\Redirects\Rules;
use Shakewell\Redirects\Store;

/**
 * Changing a URL by one character changes what search engines see, so storage must not rewrite them.
 */
final class EncodingTest extends BaseCase {

	private const SOURCE = '/whatson/collective-dinner%E2%80%93ballz-deep/';
	private const TARGET = '/contact/enquiry/?enquiry-type=Lost%20%20Property';

	public function test_stored_meta_keeps_percent_encoding(): void {
		$id = $this->make(
			array(
				'source' => self::SOURCE,
				'target' => self::TARGET,
			)
		);
		$this->assertSame( self::SOURCE, get_post_meta( $id, Store::key( 'source' ), true ) );
		$this->assertSame( self::TARGET, get_post_meta( $id, Store::key( 'target' ), true ) );
	}

	public function test_encoded_request_matches_and_target_is_sent_unchanged(): void {
		$this->make(
			array(
				'source' => self::SOURCE,
				'target' => self::TARGET,
			)
		);
		$sent = $this->visit( self::SOURCE );
		$this->assertNotNull( $sent );
		$this->assertSame( self::TARGET, $sent['location'] );
	}

	public function test_import_keeps_percent_encoding(): void {
		$file = wp_tempnam( 'redirects.csv' );
		file_put_contents( $file, "source,target,code,match,enabled\n" . self::SOURCE . ',' . self::TARGET . ",301,exact,1\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$res = Importer::run( $file, false );
		$this->assertSame( array(), $res['errors'] );
		$this->assertSame( array( self::SOURCE ), array_column( Store::live(), 'source' ) );
		$this->assertSame( array( self::TARGET ), array_column( Store::live(), 'target' ) );
	}

	public function test_clean_drops_control_characters_only(): void {
		$this->assertSame( self::TARGET, Rules::clean( " \t" . self::TARGET . "\r\n" ) );
	}
}
