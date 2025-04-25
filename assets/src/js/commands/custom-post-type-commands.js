/**
 * Custom Post Type Commands
 *
 * Dynamic commands for user-created custom post types in Secure Custom Fields.
 * This file generates navigation commands for each registered post type that
 * the current user has access to, creating both "View All" and "Add New" commands.
 *
 * Post type data is provided via acf.data.customPostTypes, which is populated
 * by the PHP side after capability checks ensure the user has appropriate access.
 *
 * @since 6.5.0
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { createElement } from '@wordpress/element';
import { Icon } from '@wordpress/components';
import { dispatch } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';

/**
 * Register custom post type commands
 */
domReady( () => {
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
		// Skip invalid post types
		if ( ! postType?.name ) {
			return;
		}

		const pluralLabel = postType.label || postType.name;
		const singularLabel = postType.singular_label || pluralLabel;

		// Register "View All" command for this post type
		commandStore.registerCommand( {
			name: `scf/cpt-${ postType.name }`,
			label: pluralLabel,
			icon: createElement( Icon, { icon: 'admin-page' } ),
			context: 'admin',
			description:
				/* translators: %s: Post type plural label */
				sprintf(
					__( 'SCF: View all %s', 'secure-custom-fields' ),
					pluralLabel
				),
			keywords: [
				'post type',
				'content',
				'cpt',
				postType.name,
				...( postType.label ? [ postType.label ] : [] ),
			],
			callback: ( { close } ) => {
				document.location = `${ adminUrl }edit.php?post_type=${ encodeURIComponent(
					postType.name
				) }`;
				close();
			},
		} );

		// Register "Add New" command for this post type
		commandStore.registerCommand( {
			name: `scf/new-${ postType.name }`,
			label:
				/* translators: %s: Post type singular label */
				sprintf(
					__( 'Add New %s', 'secure-custom-fields' ),
					singularLabel
				),
			icon: createElement( Icon, { icon: 'plus' } ),
			context: 'admin',
			description:
				/* translators: %s: Post type singular label */
				sprintf(
					__( 'SCF: Create a new %s', 'secure-custom-fields' ),
					singularLabel
				),
			keywords: [
				'add',
				'new',
				'create',
				'content',
				postType.name,
				...( postType.label ? [ postType.label ] : [] ),
			],
			callback: ( { close } ) => {
				document.location = `${ adminUrl }post-new.php?post_type=${ encodeURIComponent(
					postType.name
				) }`;
				close();
			},
		} );
	} );
} );
