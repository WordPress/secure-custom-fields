/**
 * Admin Commands
 *
 * Core WordPress commands for Secure Custom Fields administration.
 * This file registers navigation commands for all primary SCF admin screens,
 * enabling quick access through the WordPress commands interface (Cmd+K / Ctrl+K).
 *
 * @since SCF 6.5.0
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { dispatch, select } from '@wordpress/data';
import { addQueryArgs } from '@wordpress/url';
import {
	layout,
	plus,
	postList,
	category,
	settings,
	tool,
	upload,
	download,
} from '@wordpress/icons';

/**
 * Register admin commands for SCF
 */
const registerAdminCommands = () => {
	if ( ! select( 'core/commands') || ! dispatch( 'core/commands' ) ) {
		return;
	}

	const registeredCommands = select( 'core/commands' ).getCommands();
	const commandStore = dispatch( 'core/commands' );

	const viewCommands = [
		{
			name: 'field-groups',
			label: __( 'Field Groups', 'secure-custom-fields' ),
			url: 'edit.php',
			urlArgs: { post_type: 'acf-field-group' },
			icon: layout,
			keywords: [
				'acf',
				'custom fields',
				'field editor',
				'manage fields',
			],
		},
		{
			name: 'post-types',
			label: __( 'Post Types', 'secure-custom-fields' ),
			url: 'edit.php',
			urlArgs: { post_type: 'acf-post-type' },
			icon: postList,
			keywords: [ 'cpt', 'content types', 'manage post types' ],
		},
		{
			name: 'taxonomies',
			label: __( 'Taxonomies', 'secure-custom-fields' ),
			url: 'edit.php',
			urlArgs: { post_type: 'acf-taxonomy' },
			icon: category,
			keywords: [ 'categories', 'tags', 'terms', 'custom taxonomies' ],
		},
		{
			name: 'options-pages',
			label: __( 'Options Pages', 'secure-custom-fields' ),
			url: 'edit.php',
			urlArgs: { post_type: 'acf-ui-options-page' },
			icon: settings,
			keywords: [ 'settings', 'global options', 'site options' ],
		},
		{
			name: 'tools',
			label: __( 'SCF Tools', 'secure-custom-fields' ),
			url: 'admin.php',
			urlArgs: { page: 'acf-tools' },
			icon: tool,
			keywords: [ 'utilities', 'import export', 'json' ],
		},
		{
			name: 'import',
			label: __( 'Import SCF Data', 'secure-custom-fields' ),
			url: 'admin.php',
			urlArgs: { page: 'acf-tools', tool: 'import' },
			icon: upload,
			keywords: [ 'upload', 'json', 'migration', 'transfer' ],
		},
		{
			name: 'export',
			label: __( 'Export SCF Data', 'secure-custom-fields' ),
			url: 'admin.php',
			urlArgs: { page: 'acf-tools', tool: 'export' },
			icon: download,
			keywords: [ 'download', 'json', 'backup', 'migration' ],
		},
	];

	// Create commands - not in SCF's admin menu, so not duplicated.
	const createCommands = [
		{
			name: 'new-field-group',
			label: __( 'Create New Field Group', 'secure-custom-fields' ),
			url: 'post-new.php',
			urlArgs: { post_type: 'acf-field-group' },
			icon: plus,
			keywords: [
				'add',
				'new',
				'create',
				'field group',
				'custom fields',
			],
		},
		{
			name: 'new-post-type',
			label: __( 'Create New Post Type', 'secure-custom-fields' ),
			url: 'post-new.php',
			urlArgs: { post_type: 'acf-post-type' },
			icon: plus,
			keywords: [ 'add', 'new', 'create', 'cpt', 'content type' ],
		},
		{
			name: 'new-taxonomy',
			label: __( 'Create New Taxonomy', 'secure-custom-fields' ),
			url: 'post-new.php',
			urlArgs: { post_type: 'acf-taxonomy' },
			icon: plus,
			keywords: [
				'add',
				'new',
				'create',
				'taxonomy',
				'categories',
				'tags',
			],
		},
		{
			name: 'new-options-page',
			label: __( 'Create New Options Page', 'secure-custom-fields' ),
			url: 'post-new.php',
			urlArgs: { post_type: 'acf-ui-options-page' },
			icon: plus,
			keywords: [ 'add', 'new', 'create', 'options', 'settings page' ],
		},
	];

	const registerCommand = ( command ) => {
		commandStore.registerCommand( {
			name: 'scf/' + command.name,
			label: command.label,
			icon: command.icon,
			keywords: command.keywords,
			callback: ( { close } ) => {
				document.location = addQueryArgs(
					command.url,
					command.urlArgs
				);
				close();
			},
		} );
	};

	// WordPress 6.9+ adds Command Palette commands for all admin menu items.
	// For older versions, we need to register them manually. The most reliable way to
	// detect this is to check if the commands are already registered.
	viewCommands.forEach( ( command ) => {
		const commandUrl = addQueryArgs( command.url, command.urlArgs );
		// WordPress stores destination URLs in the command *name*, appended to
		// the menu slug (which is also a relative URL), resulting in somewhat
		// peculiar naming, e.g.
		// edit.php?post_type=acf-field-group-edit.php?post_type=acf-ui-options-page
		if ( registeredCommands.some( ( cmd ) => cmd.name.endsWith( commandUrl ) ) ) {
			return;
		}
		registerCommand( command );
	} );

	// "Create New" commands are not automatically registered by WordPress,
	// so we always register them.
	createCommands.forEach( registerCommand );
};

if ( 'requestIdleCallback' in window ) {
	window.requestIdleCallback( registerAdminCommands, { timeout: 500 } );
} else {
	setTimeout( registerAdminCommands, 500 );
}
