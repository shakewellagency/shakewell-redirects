<?php
/**
 * Admin screens: the edit form, list columns, notices and the import/export page.
 *
 * @package Shakewell\Redirects
 */

namespace Shakewell\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Everything here checks a capability and a nonce before it changes or reveals anything.
 */
final class Admin {

	private const NONCE = 'shakewell_redirects_save';

	/**
	 * Registers admin hooks.
	 */
	public static function boot(): void {
		$cpt = Store::CPT;
		add_action( "add_meta_boxes_$cpt", array( self::class, 'add_box' ) );
		add_action( "save_post_$cpt", array( self::class, 'save' ) );
		add_filter( 'redirect_post_location', array( self::class, 'drop_saved_message' ) );
		add_action( 'admin_notices', array( self::class, 'notices' ) );
		add_filter( "manage_{$cpt}_posts_columns", array( self::class, 'columns' ) );
		add_action( "manage_{$cpt}_posts_custom_column", array( self::class, 'column' ), 10, 2 );
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_shakewell_redirects_import', array( self::class, 'handle_import' ) );
		add_action( 'admin_post_shakewell_redirects_export', array( self::class, 'handle_export' ) );
	}

	/**
	 * Capability to import redirects.
	 */
	public static function import_cap(): string {
		return (string) apply_filters( 'shakewell_redirects_import_capability', 'manage_options' );
	}

	/**
	 * Adds the fields box.
	 */
	public static function add_box(): void {
		add_meta_box( 'shakewell-redirects-fields', __( 'Redirect', 'shakewell-redirects' ), array( self::class, 'render_box' ), Store::CPT, 'normal', 'high' );
	}

	/**
	 * Prints the fields. Values the editor just submitted are shown again if they failed validation.
	 *
	 * @param \WP_Post $post The redirect.
	 */
	public static function render_box( \WP_Post $post ): void {
		$key = 'shakewell_redirects_values_' . get_current_user_id();
		$f   = get_transient( $key );
		if ( is_array( $f ) ) {
			delete_transient( $key );
		} else {
			$row = Store::row( $post->ID );
			$f   = 'auto-draft' === $post->post_status ? array(
				'source'  => '',
				'target'  => '',
				'code'    => 301,
				'match'   => 'exact',
				'enabled' => true,
			) : $row;
		}
		wp_nonce_field( self::NONCE, 'shakewell_redirects_nonce' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="shakewell_redirects_source"><?php esc_html_e( 'Source path', 'shakewell-redirects' ); ?></label></th>
				<td><input type="text" class="regular-text code" id="shakewell_redirects_source" name="shakewell_redirects_source" value="<?php echo esc_attr( $f['source'] ); ?>" aria-describedby="shakewell_redirects_source_help" required>
				<p class="description" id="shakewell_redirects_source_help"><?php esc_html_e( 'The old path, for example /newsletter/. Case, a trailing slash and the query string are ignored when matching.', 'shakewell-redirects' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><label for="shakewell_redirects_target"><?php esc_html_e( 'Target', 'shakewell-redirects' ); ?></label></th>
				<td><input type="text" class="regular-text code" id="shakewell_redirects_target" name="shakewell_redirects_target" value="<?php echo esc_attr( $f['target'] ); ?>" aria-describedby="shakewell_redirects_target_help" required>
				<p class="description" id="shakewell_redirects_target_help"><?php esc_html_e( 'A path on this site (/new-page/) or a full http(s) URL.', 'shakewell-redirects' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><label for="shakewell_redirects_code"><?php esc_html_e( 'Status code', 'shakewell-redirects' ); ?></label></th>
				<td><select id="shakewell_redirects_code" name="shakewell_redirects_code">
					<?php
					$labels = array(
						301 => __( '301 Moved permanently', 'shakewell-redirects' ),
						302 => __( '302 Found (temporary)', 'shakewell-redirects' ),
						307 => __( '307 Temporary redirect', 'shakewell-redirects' ),
						308 => __( '308 Permanent redirect', 'shakewell-redirects' ),
					);
					foreach ( $labels as $code => $label ) {
						printf( '<option value="%d"%s>%s</option>', (int) $code, selected( (int) $f['code'], $code, false ), esc_html( $label ) );
					}
					?>
				</select></td>
			</tr>
			<tr>
				<th scope="row"><label for="shakewell_redirects_match"><?php esc_html_e( 'Match', 'shakewell-redirects' ); ?></label></th>
				<td><select id="shakewell_redirects_match" name="shakewell_redirects_match">
					<option value="exact" <?php selected( $f['match'], 'exact' ); ?>><?php esc_html_e( 'Exact path', 'shakewell-redirects' ); ?></option>
					<option value="prefix" <?php selected( $f['match'], 'prefix' ); ?>><?php esc_html_e( 'Prefix: this path and everything under it', 'shakewell-redirects' ); ?></option>
				</select></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Enabled', 'shakewell-redirects' ); ?></th>
				<td><label><input type="checkbox" name="shakewell_redirects_enabled" value="1" <?php checked( ! empty( $f['enabled'] ) ); ?>> <?php esc_html_e( 'Redirect visitors', 'shakewell-redirects' ); ?></label></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Validates and stores the fields. Invalid input is not stored and the redirect becomes a draft.
	 *
	 * @param int $post_id Post id.
	 */
	public static function save( $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		$nonce = isset( $_POST['shakewell_redirects_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['shakewell_redirects_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// Rules::clean, not sanitize_text_field, so percent-encoded URLs are stored unchanged; validate() refuses unsafe values.
		$text = static fn( string $name ): string => isset( $_POST[ "shakewell_redirects_$name" ] ) ? Rules::clean( wp_unslash( $_POST[ "shakewell_redirects_$name" ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cleaned by Rules::clean and validated by Rules::validate before storage.
		$f    = array(
			'source'  => $text( 'source' ),
			'target'  => $text( 'target' ),
			'code'    => (int) $text( 'code' ),
			'match'   => $text( 'match' ),
			'enabled' => '1' === $text( 'enabled' ),
		);
		$res  = Rules::validate( $f, Store::all( (int) $post_id ) );
		$user = get_current_user_id();
		set_transient( "shakewell_redirects_notice_$user", $res, 120 );
		if ( ! $res['errors'] ) {
			foreach ( Store::meta_input( $f ) as $meta_key => $value ) {
				update_post_meta( $post_id, $meta_key, $value );
			}
			return;
		}
		set_transient( "shakewell_redirects_values_$user", $f, 120 );
		remove_action( 'save_post_' . Store::CPT, array( self::class, 'save' ) );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);
		add_action( 'save_post_' . Store::CPT, array( self::class, 'save' ) );
	}

	/**
	 * Hides core's "Redirect published" message when the save was refused.
	 *
	 * @param string $location Redirect location after saving.
	 */
	public static function drop_saved_message( $location ) {
		$notice = get_transient( 'shakewell_redirects_notice_' . get_current_user_id() );
		return ( is_array( $notice ) && $notice['errors'] ) ? remove_query_arg( 'message', $location ) : $location;
	}

	/**
	 * Shows save and import results once.
	 */
	public static function notices(): void {
		$user = get_current_user_id();
		foreach ( array(
			'notice' => $user,
			'import' => $user,
		) as $type => $uid ) {
			$data = get_transient( "shakewell_redirects_{$type}_$uid" );
			if ( ! is_array( $data ) ) {
				continue;
			}
			delete_transient( "shakewell_redirects_{$type}_$uid" );
			foreach ( array(
				'errors'   => 'error',
				'warnings' => 'warning',
			) as $list => $class ) {
				foreach ( $data[ $list ] as $message ) {
					$message = is_array( $message ) ? sprintf( /* translators: 1: CSV line number, 2: message */ __( 'Line %1$d: %2$s', 'shakewell-redirects' ), $message[0], $message[1] ) : $message;
					printf( '<div class="notice notice-%s"><p>%s</p></div>', esc_attr( $class ), esc_html( $message ) );
				}
			}
			if ( isset( $data['summary'] ) ) {
				printf( '<div class="notice notice-info"><p>%s</p></div>', esc_html( $data['summary'] ) );
			}
		}
	}

	/**
	 * List table columns.
	 */
	public static function columns(): array {
		return array(
			'cb'                         => '<input type="checkbox">',
			'shakewell_redirects_source' => __( 'Source', 'shakewell-redirects' ),
			'shakewell_redirects_target' => __( 'Target', 'shakewell-redirects' ),
			'shakewell_redirects_code'   => __( 'Code', 'shakewell-redirects' ),
			'shakewell_redirects_match'  => __( 'Match', 'shakewell-redirects' ),
			'shakewell_redirects_on'     => __( 'Enabled', 'shakewell-redirects' ),
		);
	}

	/**
	 * Prints one list cell.
	 *
	 * @param string $column  Column id.
	 * @param int    $post_id Post id.
	 */
	public static function column( $column, $post_id ): void {
		$r = Store::row( (int) $post_id );
		switch ( $column ) {
			case 'shakewell_redirects_source':
				printf( '<strong><a href="%s">%s</a></strong>', esc_url( (string) get_edit_post_link( $post_id ) ), esc_html( $r['source'] ? $r['source'] : __( '(no source)', 'shakewell-redirects' ) ) );
				break;
			case 'shakewell_redirects_target':
				echo esc_html( $r['target'] );
				break;
			case 'shakewell_redirects_code':
				echo esc_html( (string) $r['code'] );
				break;
			case 'shakewell_redirects_match':
				echo esc_html( $r['match'] );
				break;
			case 'shakewell_redirects_on':
				echo esc_html( $r['enabled'] ? __( 'Yes', 'shakewell-redirects' ) : __( 'No', 'shakewell-redirects' ) );
				break;
		}
	}

	/**
	 * Adds Tools > Redirect import and export.
	 */
	public static function menu(): void {
		add_management_page( __( 'Redirect import and export', 'shakewell-redirects' ), __( 'Redirect import', 'shakewell-redirects' ), self::import_cap(), 'shakewell-redirects-import', array( self::class, 'render_import' ) );
	}

	/**
	 * Prints the import and export page.
	 */
	public static function render_import(): void {
		if ( ! current_user_can( self::import_cap() ) ) {
			return;
		}
		$export = wp_nonce_url( admin_url( 'admin-post.php?action=shakewell_redirects_export' ), 'shakewell_redirects_export' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Redirect import and export', 'shakewell-redirects' ); ?></h1>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="shakewell_redirects_import">
				<?php wp_nonce_field( 'shakewell_redirects_import' ); ?>
				<p><label for="shakewell_redirects_csv"><?php esc_html_e( 'CSV file with the columns source, target, code, match, enabled', 'shakewell-redirects' ); ?></label><br>
				<input type="file" id="shakewell_redirects_csv" name="csv" accept=".csv,text/csv" required></p>
				<p><label><input type="checkbox" name="dry_run" value="1" checked> <?php esc_html_e( 'Dry run: check the file without importing', 'shakewell-redirects' ); ?></label></p>
				<?php submit_button( __( 'Check or import', 'shakewell-redirects' ) ); ?>
			</form>
			<p><a class="button" href="<?php echo esc_url( $export ); ?>"><?php esc_html_e( 'Export all redirects as CSV', 'shakewell-redirects' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Handles the import form.
	 */
	public static function handle_import(): void {
		check_admin_referer( 'shakewell_redirects_import' );
		if ( ! current_user_can( self::import_cap() ) ) {
			wp_die( esc_html__( 'You are not allowed to import redirects.', 'shakewell-redirects' ), '', array( 'response' => 403 ) );
		}
		$tmp = isset( $_FILES['csv']['tmp_name'] ) ? sanitize_text_field( $_FILES['csv']['tmp_name'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a server path from PHP, checked with is_uploaded_file.
		if ( ! $tmp || ! is_uploaded_file( $tmp ) ) {
			wp_die( esc_html__( 'No file was uploaded.', 'shakewell-redirects' ), '', array( 'response' => 400 ) );
		}
		$dry = ! empty( $_POST['dry_run'] );
		$res = Importer::run( $tmp, $dry );
		if ( $res['errors'] ) {
			$res['summary'] = __( 'Nothing was imported because of the problems above.', 'shakewell-redirects' );
		} else {
			/* translators: %d: number of redirects */
			$res['summary'] = sprintf( $dry ? __( 'Dry run: %d redirect(s) would be imported.', 'shakewell-redirects' ) : __( 'Imported %d redirect(s).', 'shakewell-redirects' ), $res['created'] );
		}
		set_transient( 'shakewell_redirects_import_' . get_current_user_id(), $res, 120 );
		wp_safe_redirect( admin_url( 'tools.php?page=shakewell-redirects-import' ) );
		exit;
	}

	/**
	 * Sends the CSV export.
	 */
	public static function handle_export(): void {
		check_admin_referer( 'shakewell_redirects_export' );
		if ( ! current_user_can( (string) apply_filters( 'shakewell_redirects_manage_capability', 'edit_others_posts' ) ) ) {
			wp_die( esc_html__( 'You are not allowed to export redirects.', 'shakewell-redirects' ), '', array( 'response' => 403 ) );
		}
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="redirects.csv"' );
		echo Importer::export(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, not HTML.
		exit;
	}
}
