<?php
/**
 * Pure matching and validation rules. No database access except the live-page warning.
 *
 * @package Shakewell\Redirects
 */

namespace Shakewell\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * A redirect is an array: source, target, code, match, enabled, plus live (enabled and published) on stored ones.
 */
final class Rules {

	public const CODES = array( 301, 302, 307, 308 );

	/**
	 * Normalises a path for matching: no query or fragment, decoded, lower case, one leading slash, no trailing slash.
	 *
	 * @param string $path Request or source path.
	 */
	public static function norm( string $path ): string {
		$path = rawurldecode( (string) preg_replace( '/[?#].*$/s', '', $path ) );
		return '/' . trim( mb_strtolower( $path ), '/' );
	}

	/**
	 * Finds the rule for a path. An exact match wins; otherwise the longest prefix.
	 *
	 * @param string $path  Request path.
	 * @param array  $rules Redirects to search.
	 * @return array|null
	 */
	public static function find( string $path, array $rules ): ?array {
		$path = self::norm( $path );
		$best = null;
		$len  = -1;
		foreach ( $rules as $rule ) {
			$source = self::norm( $rule['source'] );
			if ( 'exact' === $rule['match'] ) {
				if ( $source === $path ) {
					return $rule;
				}
				continue;
			}
			$under = '/' === $source || $source === $path || str_starts_with( $path, $source . '/' );
			if ( $under && strlen( $source ) > $len ) {
				$best = $rule;
				$len  = strlen( $source );
			}
		}
		return $best;
	}

	/**
	 * The path of a target on this site, or null for an external one.
	 *
	 * @param string $target Redirect target.
	 */
	public static function local_path( string $target ): ?string {
		if ( str_starts_with( $target, '/' ) ) {
			return $target;
		}
		$host = wp_parse_url( $target, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $host && $home && 0 === strcasecmp( $host, $home ) ) {
			$path = (string) wp_parse_url( $target, PHP_URL_PATH );
			return '' === $path ? '/' : $path;
		}
		return null;
	}

	/**
	 * Validates one redirect against the others already stored (or pending in an import).
	 *
	 * @param array $f      Fields: source, target, code, match, enabled (bool).
	 * @param array $others Other redirects, each with a live flag.
	 * @return array{errors: string[], warnings: string[]}
	 */
	public static function validate( array $f, array $others ): array {
		$errors   = array();
		$warnings = array();

		if ( ! preg_match( '#^/(?!/)[^\s?\#\\\\\x00-\x1f]*$#', $f['source'] ) ) {
			$errors[] = __( 'The source must be a path that starts with a single "/" and has no spaces, "?" or "#".', 'shakewell-redirects' );
		} elseif ( 'prefix' === $f['match'] && '/' === self::norm( $f['source'] ) ) {
			$errors[] = __( 'A prefix rule on "/" would redirect the whole site.', 'shakewell-redirects' );
		}

		$relative = (bool) preg_match( '#^/(?![/\\\\])[^\s\\\\\x00-\x1f]*$#', $f['target'] );
		$absolute = (bool) preg_match( '#^https?://[^\s\\\\\x00-\x1f]+$#i', $f['target'] ) && wp_parse_url( $f['target'], PHP_URL_HOST );
		if ( ! $relative && ! $absolute ) {
			$errors[] = __( 'The target must be a path that starts with a single "/" or a full http(s) URL, with no spaces.', 'shakewell-redirects' );
		}
		if ( ! in_array( $f['code'], self::CODES, true ) ) {
			$errors[] = __( 'The status code must be 301, 302, 307 or 308.', 'shakewell-redirects' );
		}
		if ( ! in_array( $f['match'], array( 'exact', 'prefix' ), true ) ) {
			$errors[] = __( 'The match type must be exact or prefix.', 'shakewell-redirects' );
		}
		if ( ! is_bool( $f['enabled'] ) ) {
			$errors[] = __( 'Enabled must be yes or no.', 'shakewell-redirects' );
		}
		if ( $errors ) {
			return compact( 'errors', 'warnings' );
		}

		foreach ( $others as $o ) {
			if ( $o['match'] === $f['match'] && self::norm( $o['source'] ) === self::norm( $f['source'] ) ) {
				/* translators: %s: source path */
				$errors[] = sprintf( __( 'A %1$s redirect for %2$s already exists.', 'shakewell-redirects' ), $f['match'], $f['source'] );
				return compact( 'errors', 'warnings' );
			}
		}

		if ( $f['enabled'] ) {
			$self       = $f;
			$self['me'] = true;
			$live       = array_values( array_filter( $others, static fn( $o ) => ! empty( $o['live'] ) ) );
			$local      = self::local_path( $f['target'] );
			$hit        = null === $local ? null : self::find( $local, array_merge( $live, array( $self ) ) );
			if ( $hit && ! empty( $hit['me'] ) ) {
				$errors[] = __( 'The target would be redirected again by this same rule (a loop).', 'shakewell-redirects' );
			} elseif ( $hit ) {
				/* translators: %s: source path of the other redirect */
				$errors[] = sprintf( __( 'The target is itself redirected by %s. Point at the final URL to avoid a double hop or loop.', 'shakewell-redirects' ), $hit['source'] );
			}
			foreach ( $live as $o ) {
				$o_local = self::local_path( $o['target'] );
				if ( null !== $o_local && self::find( $o_local, array( $self ) ) ) {
					/* translators: %s: source path of the other redirect */
					$errors[] = sprintf( __( 'The redirect %s points at this source, which would make a double hop or loop.', 'shakewell-redirects' ), $o['source'] );
				}
			}
		}

		if ( ! $errors ) {
			$id = url_to_postid( home_url( $f['source'] ) );
			if ( $id && 'publish' === get_post_status( $id ) && Store::CPT !== get_post_type( $id ) ) {
				/* translators: %s: title of the published page */
				$warnings[] = sprintf( __( 'This source is the URL of the published page "%s". The redirect will hide that page.', 'shakewell-redirects' ), get_the_title( $id ) );
			}
		}
		return compact( 'errors', 'warnings' );
	}
}
