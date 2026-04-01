/**
 * Custom Post Type Commands
 *
 * Dynamic commands for user-created custom post types in Secure Custom Fields.
 * This file generates navigation commands for each registered post type that
 * the current user has access to, creating "View All", "Add New", and "Edit" commands.
 *
 * @since SCF 6.5.0
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { dispatch, resolveSelect } from '@wordpress/data';
import { addQueryArgs } from '@wordpress/url';
import { page, plus, edit } from '@wordpress/icons';

/**
 * Register custom post type commands
 */
const registerPostTypeCommands = async () => {
	if ( ! resolveSelect( 'core') || ! dispatch( 'core/commands' ) ) {
		return;
	}

	const postTypes = await resolveSelect( 'core' ).getPostTypes( {
		per_page: -1,
		source: 'scf'
	} );

	const commandStore = dispatch( 'core/commands' );

	// WordPress 6.9+ adds Command Palette commands for all admin menu items.
	const wpVersion = window.acf.data.wp_version;
	const isWp69Plus =
		wpVersion.localeCompare( '6.9', undefined, { numeric: true } ) >= 0;

	postTypes.forEach( ( postType ) => {
		// Navigation commands are already included in WP 6.9+.
		if ( ! isWp69Plus ) {
			// Register "View All" command for this post type
			commandStore.registerCommand( {
				name: `scf/cpt-${ postType.slug }`,
				label: postType.labels.all_items,
				icon: page,
				context: 'admin',
				keywords: [
					'post type',
					'content',
					'cpt',
					postType.slug,
					postType.name,
				].filter( Boolean ),
				callback: ( { close } ) => {
					document.location = addQueryArgs( 'edit.php', {
						post_type: postType.slug,
					} );
					close();
				},
			} );

			// Register "Add New" command for this post type
			commandStore.registerCommand( {
				name: `scf/new-${ postType.slug }`,
				label: postType.labels.add_new_item,
				icon: plus,
				context: 'admin',
				keywords: [
					'add',
					'new',
					'create',
					'content',
					postType.slug,
					postType.name,
				],
				callback: ( { close } ) => {
					document.location = addQueryArgs( 'post-new.php', {
						post_type: postType.slug,
					} );
					close();
				},
			} );
		}

		// Register "Edit Post Type" command
		commandStore.registerCommand( {
			name: `scf/edit-${ postType.slug }`,
			label: sprintf(
				/* translators: %s: post type label */
				__( 'Edit post type: %s', 'secure-custom-fields' ),
				postType.name
			),
			icon: edit,
			context: 'admin',
			keywords: [
				'edit',
				'modify',
				'post type',
				'cpt',
				'settings',
				postType.slug,
				postType.name,
			],
			callback: ( { close } ) => {
				document.location = addQueryArgs( 'post.php', {
					post: postType.id,
					action: 'edit',
				} );
				close();
			},
		} );
	} );
};

if ( 'requestIdleCallback' in window ) {
	window.requestIdleCallback( registerPostTypeCommands, { timeout: 500 } );
} else {
	setTimeout( registerPostTypeCommands, 500 );
}
