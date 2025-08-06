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
import { createElement } from '@wordpress/element';
import { Icon } from '@wordpress/components';
import { dispatch } from '@wordpress/data';
import { addQueryArgs } from '@wordpress/url';
import { layout, plus, postList, category, settings, tool, upload, download } from '@wordpress/icons';

/**
 * Register admin commands for SCF
 */
const registerAdminCommands = () => {
	if ( ! dispatch( 'core/commands' ) || ! window.acf?.data ) {
		return;
	}

	const commandStore = dispatch( 'core/commands' );
	const adminUrl = window.acf?.data?.admin_url || '';

	const commands = [
		{
			name: 'field-groups',
			label: __( 'Field Groups', 'secure-custom-fields' ),
			url: 'edit.php',
			urlArgs: { post_type: 'acf-field-group' },
			icon: layout,
			description: __(
				'SCF: View and manage custom field groups',
				'secure-custom-fields'
			),
			keywords: [
				'acf',
				'custom fields',
				'field editor',
				'manage fields',
			],
		},
		{
			name: 'new-field-group',
			label: __( 'Create New Field Group', 'secure-custom-fields' ),
			url: 'post-new.php',
			urlArgs: { post_type: 'acf-field-group' },
			icon: plus,
			description: __(
				'SCF: Create a new field group to organize custom fields',
				'secure-custom-fields'
			),
			keywords: [
				'add',
				'new',
				'create',
				'field group',
				'custom fields',
			],
		},
		{
			name: 'post-types',
			label: __( 'Post Types', 'secure-custom-fields' ),
			url: 'edit.php',
			urlArgs: { post_type: 'acf-post-type' },
			icon: postList,
			description: __(
				'SCF: Manage custom post types',
				'secure-custom-fields'
			),
			keywords: [ 'cpt', 'content types', 'manage post types' ],
		},
		{
			name: 'new-post-type',
			label: __( 'Create New Post Type', 'secure-custom-fields' ),
			url: 'post-new.php',
			urlArgs: { post_type: 'acf-post-type' },
			icon: plus,
			description: __(
				'SCF: Create a new custom post type',
				'secure-custom-fields'
			),
			keywords: [ 'add', 'new', 'create', 'cpt', 'content type' ],
		},
		{
			name: 'taxonomies',
			label: __( 'Taxonomies', 'secure-custom-fields' ),
			url: 'edit.php',
			urlArgs: { post_type: 'acf-taxonomy' },
			icon: category,
			description: __(
				'SCF: Manage custom taxonomies for organizing content',
				'secure-custom-fields'
			),
			keywords: [ 'categories', 'tags', 'terms', 'custom taxonomies' ],
		},
		{
			name: 'new-taxonomy',
			label: __( 'Create New Taxonomy', 'secure-custom-fields' ),
			url: 'post-new.php',
			urlArgs: { post_type: 'acf-taxonomy' },
			icon: plus,
			description: __(
				'SCF: Create a new custom taxonomy',
				'secure-custom-fields'
			),
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
			name: 'options-pages',
			label: __( 'Options Pages', 'secure-custom-fields' ),
			url: 'edit.php',
			urlArgs: { post_type: 'acf-ui-options-page' },
			icon: settings,
			description: __(
				'SCF: Manage custom options pages for global settings',
				'secure-custom-fields'
			),
			keywords: [ 'settings', 'global options', 'site options' ],
		},
		{
			name: 'new-options-page',
			label: __( 'Create New Options Page', 'secure-custom-fields' ),
			url: 'post-new.php',
			urlArgs: { post_type: 'acf-ui-options-page' },
			icon: plus,
			description: __(
				'SCF: Create a new custom options page',
				'secure-custom-fields'
			),
			keywords: [ 'add', 'new', 'create', 'options', 'settings page' ],
		},
		{
			name: 'tools',
			label: __( 'SCF Tools', 'secure-custom-fields' ),
			url: 'admin.php',
			urlArgs: { page: 'acf-tools' },
			icon: tool,
			description: __(
				'SCF: Access SCF utility tools',
				'secure-custom-fields'
			),
			keywords: [ 'utilities', 'import export', 'json' ],
		},
		{
			name: 'import',
			label: __( 'Import SCF Data', 'secure-custom-fields' ),
			url: 'admin.php',
			urlArgs: { page: 'acf-tools', tool: 'import' },
			icon: upload,
			description: __(
				'SCF: Import field groups, post types, taxonomies, and options pages',
				'secure-custom-fields'
			),
			keywords: [ 'upload', 'json', 'migration', 'transfer' ],
		},
		{
			name: 'export',
			label: __( 'Export SCF Data', 'secure-custom-fields' ),
			url: 'admin.php',
			urlArgs: { page: 'acf-tools', tool: 'export' },
			icon: download,
			description: __(
				'SCF: Export field groups, post types, taxonomies, and options pages',
				'secure-custom-fields'
			),
			keywords: [ 'download', 'json', 'backup', 'migration' ],
		},
	];

	commands.forEach( ( command ) => {
		commandStore.registerCommand( {
			name: 'scf/' + command.name,
			label: command.label,
			icon: createElement( Icon, { icon: command.icon } ),
			context: 'admin',
			description: command.description,
			keywords: command.keywords,
			callback: ( { close } ) => {
				document.location = command.urlArgs
					? addQueryArgs( adminUrl + command.url, command.urlArgs )
					: adminUrl + command.url;
				close();
			},
		} );
	} );
};

if ( 'requestIdleCallback' in window ) {
	window.requestIdleCallback( registerAdminCommands, { timeout: 500 } );
} else {
	setTimeout( registerAdminCommands, 500 );
}
