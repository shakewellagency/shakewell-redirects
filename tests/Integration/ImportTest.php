<?php
/**
 * CSV import and export.
 *
 * @package Shakewell\Redirects
 */

namespace Shakewell\Redirects\Tests;

use Shakewell\Redirects\Importer;
use Shakewell\Redirects\Store;

final class ImportTest extends BaseCase {

	private const FIXTURE = __DIR__ . '/../fixtures/rules.csv';

	private function csv( string $body ): string {
		$file = wp_tempnam( 'redirects.csv' );
		file_put_contents( $file, "source,target,code,match,enabled\n" . $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $file;
	}

	public function test_valid_file_imports_every_row_with_the_right_fields(): void {
		$res = Importer::run( self::FIXTURE, false );
		$this->assertSame( array(), $res['errors'] );
		$this->assertSame( 4, $res['created'] );
		$this->assertSame( 'https://melbournecupday.merivale.com', Store::live()[ array_search( '/melbournecup/', wp_list_pluck( Store::live(), 'source' ), true ) ]['target'] );
	}

	public function test_imported_rules_behave_like_the_fixture(): void {
		Importer::run( self::FIXTURE, false );
		$this->assertSame( array( 'location' => '/venues/est/', 'status' => 302 ), $this->visit( '/venues/est/menu' ) );
		$this->assertSame( '/whatson/nightlife/ivy/', $this->visit( '/whatson/rnbfridays/january19/' )['location'] );
	}

	public function test_dry_run_reports_but_creates_nothing(): void {
		$res = Importer::run( self::FIXTURE, true );
		$this->assertSame( 4, $res['created'] );
		$this->assertSame( array(), Store::all() );
	}

	public function test_one_invalid_row_imports_nothing_and_names_the_line(): void {
		$res = Importer::run( $this->csv( "/ok/,/fine/,301,exact,1\n/bad/,javascript:alert(1),301,exact,1\n" ), false );
		$this->assertSame( 0, $res['created'] );
		$this->assertSame( 3, $res['errors'][0][0] );
		$this->assertSame( array(), Store::all() );
	}

	public function test_every_invalid_row_is_reported(): void {
		$res = Importer::run( $this->csv( "x,/a/,301,exact,1\n/b/,/c/,999,exact,1\n/d/,/e/,301,sideways,1\n/f/,/g/,301,exact,maybe\n" ), true );
		$this->assertSame( array( 2, 3, 4, 5 ), array_values( array_unique( wp_list_pluck( $res['errors'], 0 ) ) ) );
	}

	public function test_duplicate_inside_the_file_is_refused(): void {
		$res = Importer::run( $this->csv( "/a/,/b/,301,exact,1\n/a/,/c/,301,exact,1\n" ), false );
		$this->assertCount( 1, $res['errors'] );
		$this->assertSame( array(), Store::all() );
	}

	public function test_chain_inside_the_file_is_refused(): void {
		$res = Importer::run( $this->csv( "/a/,/b/,301,exact,1\n/b/,/c/,301,exact,1\n" ), false );
		$this->assertCount( 1, $res['errors'] );
	}

	public function test_row_clashing_with_a_stored_redirect_is_refused(): void {
		$this->make( array( 'source' => '/a/' ) );
		$res = Importer::run( $this->csv( "/a/,/z/,301,exact,1\n" ), false );
		$this->assertCount( 1, $res['errors'] );
	}

	public function test_blank_code_match_and_enabled_use_defaults(): void {
		Importer::run( $this->csv( "/a/,/b/,,,\n" ), false );
		$this->assertSame( array( 'source' => '/a/', 'target' => '/b/', 'code' => 301, 'match' => 'exact' ), Store::live()[0] );
	}

	public function test_missing_file_and_missing_header_are_reported(): void {
		$this->assertNotEmpty( Importer::run( '/nonexistent.csv', false )['errors'] );
		$file = wp_tempnam( 'bad.csv' );
		file_put_contents( $file, "a,b\n1,2\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->assertNotEmpty( Importer::run( $file, false )['errors'] );
	}

	public function test_import_fires_the_changed_action_once_with_all_paths(): void {
		$calls = array();
		add_action( 'shakewell_redirects_changed', static function ( $p ) use ( &$calls ) {
			$calls[] = $p;
		} );
		Importer::run( self::FIXTURE, false );
		$this->assertCount( 1, $calls );
		$this->assertCount( 4, $calls[0] );
	}

	public function test_export_round_trips_the_fixture(): void {
		Importer::run( self::FIXTURE, false );
		$this->assertSame( trim( (string) file_get_contents( self::FIXTURE ) ), trim( Importer::export() ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}
