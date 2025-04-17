/**
 * SCF Command Palette integration
 *
 * Uses WordPress Commands API to add Secure Custom Fields commands to the WordPress command palette.
 */

// Register commands when WordPress is ready
wp.domReady(() => {
	// Make sure required WordPress dependencies are available
	if (!wp.data || !wp.data.dispatch || !wp.data.dispatch('core/commands')) {
		console.warn('SCF Command Palette: WordPress Commands API not available');
		return;
	}

	// Access the WordPress i18n functions
	const { __ } = wp.i18n;

	// Get the commands store
	const commandStore = wp.data.dispatch('core/commands');
	
	// Command definitions for SCF admin pages
	const commands = [
		{ name: 'field-groups', label: __('Field Groups', 'secure-custom-fields'), url: 'edit.php?post_type=acf-field-group' },
		{ name: 'new-field-group', label: __('Create New Field Group', 'secure-custom-fields'), url: 'post-new.php?post_type=acf-field-group' },
		{ name: 'post-types', label: __('Post Types', 'secure-custom-fields'), url: 'admin.php?page=acf-post-types' },
		{ name: 'new-post-type', label: __('Create New Post Type', 'secure-custom-fields'), url: 'admin.php?page=acf-post-type' },
		{ name: 'taxonomies', label: __('Taxonomies', 'secure-custom-fields'), url: 'admin.php?page=acf-taxonomies' },
		{ name: 'new-taxonomy', label: __('Create New Taxonomy', 'secure-custom-fields'), url: 'admin.php?page=acf-taxonomy' },
		{ name: 'options-pages', label: __('Options Pages', 'secure-custom-fields'), url: 'admin.php?page=acf-options-pages' },
		{ name: 'new-options-page', label: __('Create New Options Page', 'secure-custom-fields'), url: 'admin.php?page=acf-ui-options-page' },
		{ name: 'tools', label: __('SCF Tools', 'secure-custom-fields'), url: 'admin.php?page=acf-tools' },
		{ name: 'import', label: __('Import SCF Data', 'secure-custom-fields'), url: 'admin.php?page=acf-tools&tool=import' },
		{ name: 'export', label: __('Export SCF Data', 'secure-custom-fields'), url: 'admin.php?page=acf-tools&tool=export' }
	];
	
	// Register each command
	commands.forEach(command => {
		commandStore.registerCommand({
			name: 'scf/' + command.name,
			label: command.label,
			context: 'admin',
			callback: () => {
				window.location.href = command.url;
			}
		});
	});
});