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
import { dispatch, resolveSelect, select } from '@wordpress/data';
import { addQueryArgs } from '@wordpress/url';
import { page, plus, edit } from '@wordpress/icons';

/**
 * Register custom post type commands
 */
const registerPostTypeCommands = async () => {
	if ( ! resolveSelect( 'core' ) || ! dispatch( 'core/commands' ) ) {
		return;
	}

	const postTypes = await resolveSelect( 'core' ).getPostTypes( {
		per_page: -1,
		source: 'scf',
	} );

	const commandStore = dispatch( 'core/commands' );
	const registeredCommands = select( 'core/commands' ).getCommands();

	postTypes.forEach( ( postType ) => {
		const viewAllCommandUrl = addQueryArgs( 'edit.php', {
			post_type: postType.slug,
		} );

		// WordPress stores destination URLs in the command *name*, appended to
		// the menu slug (which is also a relative URL), resulting in somewhat
		// peculiar naming, e.g.
		// edit.php?post_type=movie-post-new.php?post_type=movie
		if (
			! registeredCommands.some( ( cmd ) =>
				cmd.name.endsWith( viewAllCommandUrl )
			)
		) {
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
					document.location = viewAllCommandUrl;
					close();
				},
			} );
		}

		const addNewCommandUrl = addQueryArgs( 'post-new.php', {
			post_type: postType.slug,
		} );

		if (
			! registeredCommands.some( ( cmd ) =>
				cmd.name.endsWith( addNewCommandUrl )
			)
		) {
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
					document.location = addNewCommandUrl;
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
					post: postType.scf_post_id,
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
