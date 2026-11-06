<?php
/**
 * WP-CLI commands.
 *
 * @package Shakewell\Redirects
 */

namespace Shakewell\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Import and export redirects as CSV (source,target,code,match,enabled).
 */
final class Cli {

	/**
	 * Imports redirects from a CSV file. All rows are validated first; if any is invalid nothing is imported.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the CSV file.
	 *
	 * [--dry-run]
	 * : Validate and report without creating anything.
	 *
	 * @param array $args  Positional arguments.
	 * @param array $assoc Flags.
	 */
	public function import( $args, $assoc ): void {
		$res = Importer::run( $args[0], ! empty( $assoc['dry-run'] ) );
		foreach ( $res['warnings'] as list( $line, $message ) ) {
			\WP_CLI::warning( "Line $line: $message" );
		}
		foreach ( $res['errors'] as list( $line, $message ) ) {
			\WP_CLI::warning( "Line $line: $message" );
		}
		if ( $res['errors'] ) {
			\WP_CLI::error( sprintf( 'Nothing imported: %d problem(s) in %d row(s).', count( $res['errors'] ), $res['rows'] ) );
		}
		\WP_CLI::success( sprintf( empty( $assoc['dry-run'] ) ? 'Imported %d redirect(s).' : 'Dry run: %d redirect(s) would be imported.', $res['created'] ) );
	}

	/**
	 * Prints all redirects as CSV.
	 */
	public function export(): void {
		\WP_CLI::line( rtrim( Importer::export() ) );
	}
}
