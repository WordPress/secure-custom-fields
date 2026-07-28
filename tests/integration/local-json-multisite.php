<?php
/**
 * Disposable multisite integration scenarios for Local JSON write authorization.
 *
 * This file is executed through separate `wp eval-file` processes. It is not
 * loaded by PHPUnit and must only run in the disposable wp-env created by
 * local-json-multisite.sh.
 *
 * @package wordpress/secure-custom-fields
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

// WP-CLI does not run WordPress's normal `init` action before every command.
if ( ! acf_get_data( 'acf_did_init' ) ) {
	acf_init();
}

/**
 * Fails the current scenario when a condition is false.
 *
 * @param boolean $condition Whether the assertion passed.
 * @param string  $message   Failure message.
 * @return void
 */
function scf_local_json_integration_assert( $condition, $message ) {
	if ( ! $condition ) {
		WP_CLI::error( $message );
	}
}

/**
 * Returns all filesystem paths used by the disposable scenarios.
 *
 * @return array
 */
function scf_local_json_integration_paths() {
	$theme_path = get_theme_root() . '/scf-local-json-integration';
	$uploads    = wp_upload_dir();
	$site_root  = $uploads['basedir'] . '/scf-local-json-integration';

	return array(
		'theme'   => $theme_path,
		'shared'  => $theme_path . '/acf-json',
		'allowed' => $site_root . '/allowed',
	);
}

/**
 * Builds inert field group data for a JSON fixture or save operation.
 *
 * @param string $key       Field group key.
 * @param string $title     Field group title.
 * @param string $field_key Optional field key.
 * @return array
 */
function scf_local_json_integration_group( $key, $title, $field_key = '' ) {
	$fields = array();

	if ( $field_key ) {
		$fields[] = array(
			'key'   => $field_key,
			'label' => 'Canary text',
			'name'  => 'scf_multisite_canary_text',
			'type'  => 'text',
		);
	}

	return array(
		'ID'       => 0,
		'key'      => $key,
		'title'    => $title,
		'fields'   => $fields,
		'location' => array(
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'post',
				),
			),
		),
		'active'   => true,
		'modified' => 1700000000,
	);
}

/**
 * Writes an inert Local JSON field group fixture.
 *
 * @param string $file      Destination file.
 * @param string $key       Field group key.
 * @param string $title     Field group title.
 * @param string $field_key Optional field key.
 * @return void
 */
function scf_local_json_integration_write_group( $file, $key, $title, $field_key = '' ) {
	$data = scf_local_json_integration_group( $key, $title, $field_key );
	unset( $data['ID'] );

	$result = file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Disposable integration fixture.
		$file,
		wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL
	);

	scf_local_json_integration_assert( false !== $result, 'Could not write fixture: ' . $file );
}

/**
 * Adds a save-path filter for one scenario process.
 *
 * @param array $paths Paths to return.
 * @return void
 */
function scf_local_json_integration_use_save_paths( $paths ) {
	add_filter(
		'acf/json/save_paths',
		static function () use ( $paths ) {
			return $paths;
		},
		1000
	);
}

/**
 * Asserts that the current user is the site-A-only administrator.
 *
 * @return void
 */
function scf_local_json_integration_assert_site_admin() {
	scf_local_json_integration_assert( is_multisite(), 'The integration environment must be multisite.' );
	scf_local_json_integration_assert( current_user_can( 'manage_options' ), 'The site-A actor must have manage_options.' );
	scf_local_json_integration_assert( ! is_super_admin(), 'The site-A actor must not be a network super admin.' );

	$other_site_found = false;
	foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {
		if ( get_current_blog_id() === (int) $site->blog_id ) {
			continue;
		}

		$other_site_found = true;
		scf_local_json_integration_assert(
			! is_user_member_of_blog( get_current_user_id(), (int) $site->blog_id ),
			'The site-A actor must not be a member of site B.'
		);
	}

	scf_local_json_integration_assert( $other_site_found, 'The network must contain site B.' );
}

/**
 * Returns the Local JSON component.
 *
 * @return ACF_Local_JSON
 */
function scf_local_json_integration_json() {
	$json = acf_get_instance( 'ACF_Local_JSON' );
	scf_local_json_integration_assert( $json instanceof ACF_Local_JSON, 'Local JSON is not available.' );
	return $json;
}

/**
 * Runs the disposable filesystem setup phase.
 *
 * @return void
 */
function scf_local_json_integration_setup() {
	scf_local_json_integration_assert( is_multisite(), 'wp-env was not started as multisite.' );

	$paths = scf_local_json_integration_paths();
	foreach (
		array(
			$paths['theme'],
			$paths['shared'],
			$paths['allowed'],
		) as $directory
	) {
		scf_local_json_integration_assert( wp_mkdir_p( $directory ), 'Could not create directory: ' . $directory );
	}

	$style = "/*\nTheme Name: SCF Local JSON Integration\nVersion: 1.0.0\n*/\n";
	scf_local_json_integration_assert(
		false !== file_put_contents( $paths['theme'] . '/style.css', $style ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Disposable theme fixture.
		'Could not create the disposable theme stylesheet.'
	);
	scf_local_json_integration_assert(
		false !== file_put_contents( $paths['theme'] . '/index.php', "<?php\n// Intentionally empty integration theme.\n" ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Disposable theme fixture.
		'Could not create the disposable theme index.'
	);

	scf_local_json_integration_write_group(
		$paths['shared'] . '/group_scf_multisite_canary.json',
		'group_scf_multisite_canary',
		'Trusted multisite canary',
		'field_scf_multisite_canary_text'
	);
	scf_local_json_integration_write_group(
		$paths['shared'] . '/group_scf_multisite_overwrite.json',
		'group_scf_multisite_overwrite',
		'Shared overwrite sentinel'
	);
	scf_local_json_integration_write_group(
		$paths['shared'] . '/group_scf_multisite_delete.json',
		'group_scf_multisite_delete',
		'Shared delete sentinel'
	);

	WP_CLI::success( 'Created the disposable shared theme and inert Local JSON fixtures.' );
}

/**
 * Proves site-A database persistence while default Local JSON creation is denied.
 *
 * @return void
 */
function scf_local_json_integration_deny_create() {
	scf_local_json_integration_assert_site_admin();

	$key       = 'group_scf_multisite_create';
	$field_key = 'field_scf_multisite_create_text';
	$paths     = scf_local_json_integration_paths();
	$saved     = acf_update_field_group(
		array(
			'key'      => $key,
			'title'    => 'Site A database canary',
			'location' => array(),
			'active'   => true,
		)
	);

	scf_local_json_integration_assert( ! empty( $saved['ID'] ), 'The field group was not saved in site A.' );

	$field = acf_update_field(
		array(
			'key'    => $field_key,
			'label'  => 'Database canary text',
			'name'   => 'scf_multisite_database_canary',
			'type'   => 'text',
			'parent' => $saved['ID'],
		)
	);
	scf_local_json_integration_assert( ! empty( $field['ID'] ), 'The text field was not saved in site A.' );

	// Save the group again after its field exists, matching the normal update flow.
	$saved = acf_update_field_group( $saved );
	scf_local_json_integration_assert( ! empty( $saved['ID'] ), 'The field group update did not persist.' );
	scf_local_json_integration_assert(
		! file_exists( $paths['shared'] . '/' . $key . '.json' ),
		'The site-A-only administrator created a file in the shared theme.'
	);

	$import_key = 'group_scf_multisite_import';
	$imported   = acf_import_internal_post_type(
		scf_local_json_integration_group( $import_key, 'Site A imported database canary' ),
		'acf-field-group'
	);
	scf_local_json_integration_assert( ! empty( $imported['ID'] ), 'The imported field group was not saved in site A.' );
	scf_local_json_integration_assert(
		! file_exists( $paths['shared'] . '/' . $import_key . '.json' ),
		'The import wrote a file in the shared theme.'
	);

	WP_CLI::success( 'Denied shared creation for updates and imports while preserving site-A database saves.' );
}

/**
 * Verifies the previous phase in a clean WordPress bootstrap.
 *
 * @return void
 */
function scf_local_json_integration_verify_database() {
	scf_local_json_integration_assert_site_admin();

	$paths    = scf_local_json_integration_paths();
	$group    = acf_get_field_group( 'group_scf_multisite_create' );
	$field    = acf_get_field( 'field_scf_multisite_create_text' );
	$imported = acf_get_field_group( 'group_scf_multisite_import' );

	scf_local_json_integration_assert( is_array( $group ), 'The saved field group is missing from site A.' );
	scf_local_json_integration_assert( 'Site A database canary' === $group['title'], 'The site-A field group changed.' );
	scf_local_json_integration_assert( is_array( $field ), 'The saved text field is missing from site A.' );
	scf_local_json_integration_assert( 'text' === $field['type'], 'The site-A field type changed.' );
	scf_local_json_integration_assert( is_array( $imported ), 'The imported field group is missing from site A.' );
	scf_local_json_integration_assert(
		'Site A imported database canary' === $imported['title'],
		'The imported site-A field group changed.'
	);
	scf_local_json_integration_assert(
		! file_exists( $paths['shared'] . '/group_scf_multisite_create.json' ),
		'The denied Local JSON file appeared in a clean bootstrap.'
	);
	scf_local_json_integration_assert(
		! file_exists( $paths['shared'] . '/group_scf_multisite_import.json' ),
		'The denied import Local JSON file appeared in a clean bootstrap.'
	);

	WP_CLI::success( 'Confirmed site-A database persistence in a clean bootstrap.' );
}

/**
 * Denies shared overwrite and delete operations for the site-A-only admin.
 *
 * @return void
 */
function scf_local_json_integration_deny_overwrite_delete() {
	scf_local_json_integration_assert_site_admin();

	$paths          = scf_local_json_integration_paths();
	$overwrite_file = $paths['shared'] . '/group_scf_multisite_overwrite.json';
	$delete_file    = $paths['shared'] . '/group_scf_multisite_delete.json';
	$before         = file_get_contents( $overwrite_file );

	$result = acf_write_json_field_group(
		scf_local_json_integration_group(
			'group_scf_multisite_overwrite',
			'Site A attempted overwrite'
		)
	);
	scf_local_json_integration_assert( false === $result, 'The shared overwrite unexpectedly succeeded.' );
	scf_local_json_integration_assert( file_get_contents( $overwrite_file ) === $before, 'The shared overwrite sentinel changed.' );

	acf_delete_json_field_group( 'group_scf_multisite_delete' );
	scf_local_json_integration_assert( file_exists( $delete_file ), 'The shared delete sentinel was removed.' );
	scf_local_json_integration_assert(
		'Shared delete sentinel' === json_decode( file_get_contents( $delete_file ), true )['title'],
		'The shared delete sentinel changed.'
	);

	WP_CLI::success( 'Denied shared overwrite and delete operations.' );
}

/**
 * Proves that trusted, pre-existing JSON still loads on the current site.
 *
 * @return void
 */
function scf_local_json_integration_load_trusted() {
	$paths = scf_local_json_integration_paths();
	$json  = scf_local_json_integration_json();
	$files = $json->scan_files();

	scf_local_json_integration_assert(
		realpath( get_stylesheet_directory() ) === realpath( $paths['theme'] ),
		'The current site is not using the disposable shared theme.'
	);
	scf_local_json_integration_assert(
		isset( $files['group_scf_multisite_canary'] ),
		'The trusted canary was not discovered.'
	);

	$json->include_fields();
	$group = acf_get_local_field_group( 'group_scf_multisite_canary' );
	$field = acf_get_local_field( 'field_scf_multisite_canary_text' );

	scf_local_json_integration_assert( is_array( $group ), 'The trusted field group was not loaded.' );
	scf_local_json_integration_assert( 'Trusted multisite canary' === $group['title'], 'The trusted group changed.' );
	scf_local_json_integration_assert( is_array( $field ), 'The trusted text field was not loaded.' );
	scf_local_json_integration_assert( 'text' === $field['type'], 'The trusted field type changed.' );

	foreach (
		array(
			'group_scf_multisite_overwrite' => 'Shared overwrite sentinel',
			'group_scf_multisite_delete'    => 'Shared delete sentinel',
		) as $key => $title
	) {
		$loaded = acf_get_local_field_group( $key );
		scf_local_json_integration_assert( is_array( $loaded ), 'A shared sentinel did not load: ' . $key );
		scf_local_json_integration_assert( $title === $loaded['title'], 'A shared sentinel was mutated: ' . $key );
	}

	WP_CLI::success( 'Loaded trusted Local JSON from the shared theme.' );
}

/**
 * Proves a save path inside the site's uploads directory supports create, overwrite, and delete.
 *
 * @return void
 */
function scf_local_json_integration_uploads_opt_in() {
	scf_local_json_integration_assert_site_admin();

	$paths = scf_local_json_integration_paths();
	$key   = 'group_scf_multisite_opt_in';
	$file  = $paths['allowed'] . '/' . $key . '.json';

	// A non-canonical configuration must still resolve to its authorized directory.
	scf_local_json_integration_use_save_paths( array( $paths['allowed'] . '/../allowed/' ) );

	$json = scf_local_json_integration_json();
	scf_local_json_integration_assert(
		$json->save_file( $key, scf_local_json_integration_group( $key, 'Opt-in create' ) ),
		'The uploads save path did not allow creation.'
	);
	scf_local_json_integration_assert(
		$json->save_file( $key, scf_local_json_integration_group( $key, 'Opt-in overwrite' ) ),
		'The uploads save path did not allow overwrite.'
	);
	scf_local_json_integration_assert(
		'Opt-in overwrite' === json_decode( file_get_contents( $file ), true )['title'],
		'The uploads save path overwrite did not reach its target.'
	);

	$json->delete_file( $key );
	scf_local_json_integration_assert( ! file_exists( $file ), 'The uploads save path did not allow deletion.' );
	WP_CLI::success( 'Allowed create, overwrite, and delete through a save path inside the site uploads directory.' );
}

/**
 * Denies writes into another site's uploads directory from the main site.
 *
 * @return void
 */
function scf_local_json_integration_deny_other_site_uploads() {
	scf_local_json_integration_assert_site_admin();

	$uploads   = wp_upload_dir();
	$other_dir = '';

	foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {
		if ( get_current_blog_id() !== (int) $site->blog_id ) {
			$other_dir = $uploads['basedir'] . '/sites/' . (int) $site->blog_id . '/scf-local-json-integration';
			break;
		}
	}

	scf_local_json_integration_assert( '' !== $other_dir, 'The network must contain site B.' );
	scf_local_json_integration_assert( wp_mkdir_p( $other_dir ), 'Could not create the site-B uploads fixture: ' . $other_dir );

	$key = 'group_scf_multisite_cross_site';
	scf_local_json_integration_use_save_paths( array( $other_dir ) );

	$json   = scf_local_json_integration_json();
	$result = $json->save_file( $key, scf_local_json_integration_group( $key, 'Cross-site denied' ) );

	scf_local_json_integration_assert( false === $result, 'A site admin wrote into another site\'s uploads directory.' );
	scf_local_json_integration_assert(
		! file_exists( $other_dir . '/' . $key . '.json' ),
		'A cross-site uploads file was created.'
	);

	WP_CLI::success( 'Denied writes into another site\'s uploads directory.' );
}

/**
 * Applies the same uploads containment policy without a user.
 *
 * @return void
 */
function scf_local_json_integration_user_zero() {
	scf_local_json_integration_assert( 0 === get_current_user_id(), 'This phase must run without a WordPress user.' );

	$paths = scf_local_json_integration_paths();
	$json  = scf_local_json_integration_json();
	$key   = 'group_scf_multisite_user_zero_denied';

	$result = $json->save_file( $key, scf_local_json_integration_group( $key, 'User-zero denied' ) );
	scf_local_json_integration_assert( false === $result, 'A no-user process bypassed the shared theme deny policy.' );
	scf_local_json_integration_assert(
		! file_exists( $paths['shared'] . '/' . $key . '.json' ),
		'A no-user process wrote to the shared theme.'
	);

	scf_local_json_integration_use_save_paths( array( $paths['allowed'] ) );

	$key    = 'group_scf_multisite_user_zero_allowed';
	$result = $json->save_file( $key, scf_local_json_integration_group( $key, 'User-zero opt-in' ) );
	scf_local_json_integration_assert( true === $result, 'A no-user process could not write inside the site uploads directory.' );
	scf_local_json_integration_assert(
		file_exists( $paths['allowed'] . '/' . $key . '.json' ),
		'The no-user uploads file is missing.'
	);

	WP_CLI::success( 'Applied the uploads containment policy to a no-user process.' );
}

/**
 * Proves a network super admin retains the legacy write behavior.
 *
 * @return void
 */
function scf_local_json_integration_super_admin() {
	scf_local_json_integration_assert( is_super_admin(), 'This phase must run as a network super admin.' );
	scf_local_json_integration_assert( current_user_can( 'manage_options' ), 'The super admin lacks manage_options.' );

	$paths = scf_local_json_integration_paths();
	$key   = 'group_scf_multisite_super_admin';
	$file  = $paths['shared'] . '/' . $key . '.json';

	$json = scf_local_json_integration_json();
	scf_local_json_integration_assert(
		$json->save_file( $key, scf_local_json_integration_group( $key, 'Super admin create' ) ),
		'The super admin could not create shared Local JSON.'
	);
	scf_local_json_integration_assert(
		$json->save_file( $key, scf_local_json_integration_group( $key, 'Super admin overwrite' ) ),
		'The super admin could not overwrite shared Local JSON.'
	);
	scf_local_json_integration_assert(
		'Super admin overwrite' === json_decode( file_get_contents( $file ), true )['title'],
		'The super admin overwrite did not reach the shared file.'
	);

	$json->delete_file( $key );
	scf_local_json_integration_assert( ! file_exists( $file ), 'The super admin could not delete shared Local JSON.' );

	WP_CLI::success( 'Preserved shared Local JSON writes for the network super admin.' );
}

$scf_local_json_integration_phase  = isset( $args[0] ) ? $args[0] : '';
$scf_local_json_integration_phases = array(
	'setup'                   => 'scf_local_json_integration_setup',
	'deny-create'             => 'scf_local_json_integration_deny_create',
	'verify-database'         => 'scf_local_json_integration_verify_database',
	'deny-overwrite-delete'   => 'scf_local_json_integration_deny_overwrite_delete',
	'load-trusted'            => 'scf_local_json_integration_load_trusted',
	'uploads-opt-in'          => 'scf_local_json_integration_uploads_opt_in',
	'deny-other-site-uploads' => 'scf_local_json_integration_deny_other_site_uploads',
	'user-zero'               => 'scf_local_json_integration_user_zero',
	'super-admin'             => 'scf_local_json_integration_super_admin',
);

if ( ! isset( $scf_local_json_integration_phases[ $scf_local_json_integration_phase ] ) ) {
	WP_CLI::error( 'Unknown Local JSON multisite integration phase: ' . $scf_local_json_integration_phase );
}

call_user_func( $scf_local_json_integration_phases[ $scf_local_json_integration_phase ] );
