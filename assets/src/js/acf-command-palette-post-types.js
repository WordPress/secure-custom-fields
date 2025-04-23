/**
 * SCF Dynamic Post Type Command Palette integration
 *
 * Dynamic commands for user-created custom post types in Secure Custom Fields.
 * 
 * @since 6.5.0
 */

// Register commands when WordPress is ready
wp.domReady(() => {
	// Make sure required WordPress dependencies are available
	if (!wp.data || !wp.data.dispatch || !wp.data.dispatch('core/commands')) {
		return;
	}

	// Wait for ACF to be ready
	if (typeof acf === 'undefined') {
		return;
	}

	// Access essential WordPress functions and data
	const { __ } = wp.i18n;
	const { createElement } = wp.element;
	const { Icon } = wp.components;
	const commandStore = wp.data.dispatch('core/commands');
	
	// Get data from ACF object
	const adminUrl = acf?.data?.admin_url || '';
	const postTypes = acf?.data?.customPostTypes || [];

	// Skip if no custom post types
	if (!postTypes || postTypes.length === 0) {
		return;
	}

	// Add commands for each custom post type
	postTypes.forEach(postType => {
		// Skip invalid post types
		if (!postType?.name) return;
		
		// Get labels
		const pluralLabel = postType.label || postType.name;
		const singularLabel = postType.singular_label || pluralLabel;
		
		// Add command to view all posts of this type
		commandStore.registerCommand({
			name: `scf/cpt-${postType.name}`,
			label: pluralLabel,
			icon: createElement(Icon, { icon: 'admin-page' }),
			context: 'admin',
			description: __('SCF: View all', 'secure-custom-fields') + ` ${pluralLabel}`,
			keywords: ['post type', 'content', 'cpt', postType.name, postType.label || ''],
			callback: ({ close }) => {
				document.location = `${adminUrl}edit.php?post_type=${postType.name}`;
				close();
			}
		});
		
		// Add "new post" command (all included post types are editable by current user)
		commandStore.registerCommand({
			name: `scf/new-${postType.name}`,
			label: __('Add New', 'secure-custom-fields') + ` ${singularLabel}`,
			icon: createElement(Icon, { icon: 'plus' }),
			context: 'admin',
			description: __('SCF: Create a new', 'secure-custom-fields') + ` ${singularLabel}`,
			keywords: ['add', 'new', 'create', 'content', postType.name, postType.label || ''],
			callback: ({ close }) => {
				document.location = `${adminUrl}post-new.php?post_type=${postType.name}`;
				close();
			}
		});
	});
}); 