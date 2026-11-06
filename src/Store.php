<?php
/**
 * Storage: the redirect post type, its meta, the cached live list and the change action.
 *
 * @package Shakewell\Redirects
 */

namespace Shakewell\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Redirects are posts of a non-public type. Fields live in registered post meta.
 */
final class Store {

	public const CPT       = 'shakewell_redirect';
	public const TRANSIENT = 'shakewell_redirects_live';
	public const FIELDS    = array( 'source', 'target', 'code', 'match', 'enabled' );

	/**
	 * Sources collected while a batch (an import) runs, or null when not batching.
	 *
	 * @var string[]|null
	 */
	private static ?array $queue = null;

	/**
	 * Source values seen just before they were overwritten, by post id.
	 *
	 * @var array<int, string>
	 */
	private static array $old = array();

	/**
	 * Registers the post type, the meta and the change hooks.
	 */
	public static function boot(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_action( 'update_post_meta', array( self::class, 'remember_old_source' ), 10, 3 );
		add_action( 'save_post_' . self::CPT, array( self::class, 'on_change' ), 20 );
		add_action( 'trashed_post', array( self::class, 'on_change' ) );
		add_action( 'untrashed_post', array( self::class, 'on_change' ) );
		add_action( 'before_delete_post', array( self::class, 'remember_before_delete' ), 10, 2 );
		add_action( 'deleted_post', array( self::class, 'on_deleted' ), 10, 2 );
	}

	/**
	 * Registers the post type and its meta.
	 */
	public static function register(): void {
		$cap  = (string) apply_filters( 'shakewell_redirects_manage_capability', 'edit_others_posts' );
		$caps = array();
		foreach ( array( 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ) as $name ) {
			$caps[ $name ] = $cap;
		}
		register_post_type(
			self::CPT,
			array(
				'labels'              => array(
					'name'          => __( 'Redirects', 'shakewell-redirects' ),
					'singular_name' => __( 'Redirect', 'shakewell-redirects' ),
					'add_new'       => __( 'Add redirect', 'shakewell-redirects' ),
					'add_new_item'  => __( 'Add redirect', 'shakewell-redirects' ),
					'edit_item'     => __( 'Edit redirect', 'shakewell-redirects' ),
					'not_found'     => __( 'No redirects found.', 'shakewell-redirects' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'tools.php',
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => false,
				'capability_type'     => array( 'shakewell_redirect', 'shakewell_redirects' ),
				'capabilities'        => $caps,
				'map_meta_cap'        => true,
			)
		);

		$types = array(
			'source'  => array( 'string', 'sanitize_text_field' ),
			'target'  => array( 'string', 'sanitize_text_field' ),
			'code'    => array( 'integer', 'absint' ),
			'match'   => array( 'string', 'sanitize_key' ),
			'enabled' => array( 'boolean', 'rest_sanitize_boolean' ),
		);
		foreach ( $types as $field => list( $type, $sanitize ) ) {
			register_post_meta(
				self::CPT,
				self::key( $field ),
				array(
					'type'              => $type,
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => $sanitize,
					'auth_callback'     => static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id ),
				)
			);
		}
	}

	/**
	 * Meta key for a field.
	 *
	 * @param string $field One of FIELDS.
	 */
	public static function key( string $field ): string {
		return '_shakewell_redirects_' . $field;
	}

	/**
	 * Fields as the meta array wp_insert_post( meta_input ) takes.
	 *
	 * @param array $f Fields.
	 */
	public static function meta_input( array $f ): array {
		$out = array();
		foreach ( self::FIELDS as $field ) {
			$out[ self::key( $field ) ] = $f[ $field ];
		}
		return $out;
	}

	/**
	 * One stored redirect.
	 *
	 * @param int $id Post id.
	 */
	public static function row( int $id ): array {
		$enabled = (bool) get_post_meta( $id, self::key( 'enabled' ), true );
		return array(
			'id'      => $id,
			'source'  => (string) get_post_meta( $id, self::key( 'source' ), true ),
			'target'  => (string) get_post_meta( $id, self::key( 'target' ), true ),
			'code'    => (int) get_post_meta( $id, self::key( 'code' ), true ),
			'match'   => (string) get_post_meta( $id, self::key( 'match' ), true ),
			'enabled' => $enabled,
			'live'    => $enabled && 'publish' === get_post_status( $id ),
		);
	}

	/**
	 * Every stored redirect that is not trashed.
	 *
	 * @param int $exclude Post id to leave out (the one being edited).
	 */
	public static function all( int $exclude = 0 ): array {
		$ids = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		return array_map( array( self::class, 'row' ), array_values( array_diff( $ids, array( $exclude ) ) ) );
	}

	/**
	 * The enabled, published redirects as served: source, target, code, match. Cached until a change.
	 */
	public static function live(): array {
		$list = get_transient( self::TRANSIENT );
		if ( ! is_array( $list ) ) {
			$list = array();
			foreach ( self::all() as $r ) {
				if ( $r['live'] ) {
					$list[] = array(
						'source' => $r['source'],
						'target' => $r['target'],
						'code'   => $r['code'],
						'match'  => $r['match'],
					);
				}
			}
			usort( $list, static fn( $a, $b ) => strcmp( Rules::norm( $a['source'] ) . $a['match'], Rules::norm( $b['source'] ) . $b['match'] ) );
			set_transient( self::TRANSIENT, $list, WEEK_IN_SECONDS );
		}
		return $list;
	}

	/**
	 * Starts collecting change notices so a bulk operation fires the action once.
	 */
	public static function begin_batch(): void {
		self::$queue = array();
	}

	/**
	 * Ends a batch and fires the change action once for all of it.
	 */
	public static function end_batch(): void {
		$paths       = self::$queue ?? array();
		self::$queue = null;
		self::announce( $paths );
	}

	/**
	 * Remembers the source about to be replaced, so the old path is announced too.
	 *
	 * @param int    $meta_id Meta id (unused).
	 * @param int    $post_id Post id.
	 * @param string $key     Meta key.
	 */
	public static function remember_old_source( $meta_id, $post_id, $key ): void {
		if ( self::key( 'source' ) === $key && self::CPT === get_post_type( $post_id ) ) {
			self::$old[ (int) $post_id ] = (string) get_post_meta( $post_id, $key, true );
		}
	}

	/**
	 * Announces a created, updated, enabled, disabled, trashed or restored redirect.
	 *
	 * @param int $post_id Post id.
	 */
	public static function on_change( $post_id ): void {
		if ( self::CPT !== get_post_type( $post_id ) ) {
			return;
		}
		$paths = array( (string) get_post_meta( $post_id, self::key( 'source' ), true ) );
		if ( isset( self::$old[ $post_id ] ) ) {
			$paths[] = self::$old[ $post_id ];
			unset( self::$old[ $post_id ] );
		}
		self::announce( $paths );
	}

	/**
	 * Keeps the source of a redirect that is about to be deleted for the action.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post.
	 */
	public static function remember_before_delete( $post_id, $post ): void {
		if ( self::CPT === $post->post_type ) {
			self::$old[ (int) $post_id ] = (string) get_post_meta( $post_id, self::key( 'source' ), true );
		}
	}

	/**
	 * Announces a deleted redirect.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post.
	 */
	public static function on_deleted( $post_id, $post ): void {
		if ( self::CPT === $post->post_type ) {
			self::announce( array( self::$old[ (int) $post_id ] ?? '' ) );
			unset( self::$old[ (int) $post_id ] );
		}
	}

	/**
	 * Drops the cache and fires the change action, or queues the paths during a batch.
	 *
	 * @param string[] $paths Source paths that changed.
	 */
	private static function announce( array $paths ): void {
		delete_transient( self::TRANSIENT );
		$paths = array_values( array_unique( array_filter( $paths ) ) );
		if ( null !== self::$queue ) {
			self::$queue = array_merge( self::$queue, $paths );
			return;
		}
		/**
		 * Fires after a redirect is created, updated, enabled, disabled, trashed, restored or deleted.
		 *
		 * @param string[] $paths Source paths affected, including a replaced old source.
		 */
		do_action( 'shakewell_redirects_changed', $paths );
	}
}
