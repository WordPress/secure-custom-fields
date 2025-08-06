/**
 * Custom Post Type Commands
 *
 * Dynamic commands for user-created custom post types in Secure Custom Fields.
 * This file generates navigation commands for each registered post type that
 * the current user has access to, creating "View All", "Add New", and "Edit" commands.
 *
 * Post type data is provided via acf.data.customPostTypes, which is populated
 * by the PHP side after capability checks ensure the user has appropriate access.
 *
 * @since SCF 6.5.0
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { createElement } from '@wordpress/element';
import { Icon } from '@wordpress/components';
import { dispatch } from '@wordpress/data';
import { addQueryArgs } from '@wordpress/url';
import { page, plus, edit } from '@wordpress/icons';

/**
 * Register custom post type commands
 */
const registerPostTypeCommands = () => {
	// Only proceed when WordPress commands API and there are custom post types accessible
	if (
		! dispatch( 'core/commands' ) ||
		! window.acf?.data?.customPostTypes?.length
	) {
		return;
	}

	const commandStore = dispatch( 'core/commands' );
	const adminUrl = window.acf.data.admin_url || '';
	const postTypes = window.acf.data.customPostTypes;

	postTypes.forEach( ( postType ) => {
		// Skip invalid post types or those missing required labels
		if ( ! postType?.name || ! postType?.all_items || ! postType?.add_new_item ) {
			return;
		}

		// Register "View All" command for this post type
		commandStore.registerCommand( {
			name: `scf/cpt-${ postType.name }`,
			label: postType.all_items,
			icon: createElement( Icon, { icon: page } ),
			context: 'admin',
			description: postType.all_items,
			keywords: [
				'post type',
				'content',
				'cpt',
				postType.name,
				postType.label,
			].filter( Boolean ),
			callback: ( { close } ) => {
				document.location = addQueryArgs(adminUrl + 'edit.php', {
					post_type: postType.name
				});
				close();
			},
		} );

		// Register "Add New" command for this post type
		commandStore.registerCommand( {
			name: `scf/new-${ postType.name }`,
			label: postType.add_new_item,
			icon: createElement( Icon, { icon: plus } ),
			context: 'admin',
			description: postType.add_new_item,
			keywords: [
				'add',
				'new',
				'create',
				'content',
				postType.name,
				postType.label,
			],
			callback: ( { close } ) => {
				document.location = addQueryArgs(adminUrl + 'post-new.php', {
					post_type: postType.name
				});
				close();
			},
		} );

		// Register "Edit Post Type" command for registered CPTs
		commandStore.registerCommand( {
			name: `scf/edit-${ postType.name }`,
			label: sprintf(__('Edit post type: %s', 'secure-custom-fields'), postType.label),
			icon: createElement( Icon, { icon: edit } ),
			context: 'admin',
			description: sprintf(__('Edit the %s post type settings', 'secure-custom-fields'), postType.label),
			keywords: [
				'edit',
				'modify',
				'post type',
				'cpt',
				'settings',
				postType.name,
				postType.label,
			],
			callback: ( { close } ) => {
				document.location = addQueryArgs(adminUrl + 'post.php', {
					post: postType.id,
					action: 'edit'
				});
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
