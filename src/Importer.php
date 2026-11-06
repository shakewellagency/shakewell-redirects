<?php
/**
 * CSV import and export, shared by WP-CLI and the Tools screen.
 *
 * @package Shakewell\Redirects
 */

namespace Shakewell\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Columns: source,target,code,match,enabled. Import is all or nothing.
 */
final class Importer {

	/**
	 * Validates every row, then creates them all (unless a dry run or any row is invalid).
	 *
	 * @param string $file    Path to the CSV file.
	 * @param bool   $dry_run Validate only.
	 * @return array{rows: int, created: int, errors: array, warnings: array}
	 */
	public static function run( string $file, bool $dry_run ): array {
		$result = array(
			'rows'     => 0,
			'created'  => 0,
			'errors'   => array(),
			'warnings' => array(),
		);
		if ( ! is_readable( $file ) ) {
			$result['errors'][] = array( 0, __( 'The file cannot be read.', 'shakewell-redirects' ) );
			return $result;
		}

		$csv = new \SplFileObject( $file );
		$csv->setFlags( \SplFileObject::READ_CSV | \SplFileObject::DROP_NEW_LINE );
		$csv->setCsvControl( ',', '"', '' );

		$header  = null;
		$line    = 0;
		$pending = array();
		$others  = Store::all();

		foreach ( $csv as $cells ) {
			++$line;
			if ( ! is_array( $cells ) || array( null ) === $cells ) {
				continue;
			}
			if ( null === $header ) {
				$cells[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $cells[0] );
				$header   = array_map( static fn( $h ) => strtolower( trim( (string) $h ) ), $cells );
				if ( ! in_array( 'source', $header, true ) || ! in_array( 'target', $header, true ) ) {
					$result['errors'][] = array( $line, __( 'The header row needs at least the columns source and target.', 'shakewell-redirects' ) );
					return $result;
				}
				continue;
			}
			++$result['rows'];
			$row   = array_combine( $header, array_pad( array_slice( $cells, 0, count( $header ) ), count( $header ), '' ) );
			$f     = self::fields( $row );
			$check = Rules::validate( $f, array_merge( $others, $pending ) );
			foreach ( $check['errors'] as $message ) {
				$result['errors'][] = array( $line, $message );
			}
			foreach ( $check['warnings'] as $message ) {
				$result['warnings'][] = array( $line, $message );
			}
			if ( ! $check['errors'] ) {
				$f['live'] = $f['enabled'];
				$pending[] = $f;
			}
		}

		if ( $result['errors'] || $dry_run ) {
			$result['created'] = $result['errors'] ? 0 : count( $pending );
			return $result;
		}

		$created = array();
		Store::begin_batch();
		foreach ( $pending as $f ) {
			$id = wp_insert_post(
				array(
					'post_type'   => Store::CPT,
					'post_status' => 'publish',
					'post_title'  => $f['source'],
					'meta_input'  => Store::meta_input( $f ),
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				foreach ( $created as $done ) {
					wp_delete_post( $done, true );
				}
				Store::end_batch();
				$result['errors'][] = array( 0, $id->get_error_message() );
				return $result;
			}
			$created[] = $id;
		}
		Store::end_batch();
		$result['created'] = count( $created );
		return $result;
	}

	/**
	 * Every stored redirect as CSV text.
	 */
	public static function export(): string {
		$csv = new \SplTempFileObject();
		$csv->fputcsv( Store::FIELDS, ',', '"', '' );
		foreach ( Store::all() as $r ) {
			$csv->fputcsv( array( $r['source'], $r['target'], $r['code'], $r['match'], $r['enabled'] ? '1' : '0' ), ',', '"', '' );
		}
		$csv->rewind();
		$out = '';
		foreach ( $csv as $chunk ) {
			$out .= $chunk;
		}
		return $out;
	}

	/**
	 * Turns a CSV row into validated field types. Blank code, match and enabled use the defaults.
	 *
	 * @param array $row Row keyed by header.
	 */
	private static function fields( array $row ): array {
		$code    = trim( (string) ( $row['code'] ?? '' ) );
		$enabled = strtolower( trim( (string) ( $row['enabled'] ?? '' ) ) );
		$match   = strtolower( trim( (string) ( $row['match'] ?? '' ) ) );
		return array(
			'source'  => Rules::clean( $row['source'] ),
			'target'  => Rules::clean( $row['target'] ),
			'code'    => '' === $code ? 301 : ( ctype_digit( $code ) ? (int) $code : 0 ),
			'match'   => '' === $match ? 'exact' : $match,
			'enabled' => '' === $enabled ? true : ( in_array( $enabled, array( '1', 'true', 'yes' ), true ) ? true : ( in_array( $enabled, array( '0', 'false', 'no' ), true ) ? false : null ) ),
		);
	}
}
