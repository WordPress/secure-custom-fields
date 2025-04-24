/**
 * SCF Dynamic Post Type Command Palette integration
 *
 * Dynamic commands for user-created custom post types in Secure Custom Fields.
 * This file generates commands for each registered post type that the current user
 * has access to, creating both "View All" and "Add New" commands for each type.
 *
 * Post type data is provided via acf.data.customPostTypes, which is populated
 * by the PHP side after capability checks ensure the user has appropriate access.
 *
 * @since 6.5.0
 */

wp.domReady( () => {
	// Make sure required WordPress dependencies are available
	if (
		! wp.data ||
		! wp.data.dispatch ||
		! wp.data.dispatch( 'core/commands' )
	) {
		return;
	}

	// Wait for ACF to be ready
	if ( typeof acf === 'undefined' ) {
		return;
	}

	const { __, sprintf } = wp.i18n;
	const { createElement } = wp.element;
	const { Icon } = wp.components;
	const commandStore = wp.data.dispatch( 'core/commands' );

	const adminUrl = acf?.data?.admin_url || '';
	const postTypes = acf?.data?.customPostTypes || [];

	// Skip if no custom post types
	if ( ! postTypes || postTypes.length === 0 ) {
		return;
	}

	postTypes.forEach( ( postType ) => {
		// Skip invalid post types
		if ( ! postType?.name ) return;

		const pluralLabel = postType.label || postType.name;
		const singularLabel = postType.singular_label || pluralLabel;

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
				postType.label || '',
			],
			callback: ( { close } ) => {
				document.location = `${ adminUrl }edit.php?post_type=${ encodeURIComponent(
					postType.name
				) }`;
				close();
			},
		} );

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
				postType.label || '',
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
