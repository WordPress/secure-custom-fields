/**
 * SCF Command Palette integration
 *
 * Uses WordPress Commands API to add Secure Custom Fields commands to the WordPress command palette.
 * Enhances the user experience with icons, descriptions, and keywords for better discoverability.
 * 
 * @since 6.5.0
 */

/**
 * Register SCF commands in the command palette
 */
const registerSCFCommands = () => {
	// Make sure required WordPress dependencies are available
	if (!wp.data || !wp.data.dispatch || !wp.data.dispatch('core/commands')) {
		console.warn('Secure Custom Fields: WordPress Commands API not available');
		return;
	}
	
	// Get the commands store
	const commandStore = wp.data.dispatch('core/commands');
	
	// Register a command group for SCF
	commandStore.registerCommand({
		name: 'scf-group',
		label: wp.i18n.__('Secure Custom Fields', 'secure-custom-fields'),
		group: 'secure-custom-fields',
	});

	// Register commands for each post type using properly namespaced WordPress data
	const postTypes = wp.scf?.commandData?.customPostTypes || [];
	const adminUrl = wp.scf?.commandData?.adminUrl || '';

	postTypes.forEach((postType) => {
		commandStore.registerCommand({
			name: `scf-list-${postType.name}`,
			label: wp.i18n.__(`List ${postType.label}`, 'secure-custom-fields'),
			callback: () => {
				window.location.href = `${adminUrl}edit.php?post_type=${postType.name}`;
			},
			group: 'secure-custom-fields',
		});

		commandStore.registerCommand({
			name: `scf-new-${postType.name}`,
			label: wp.i18n.__(`New ${postType.singular_label}`, 'secure-custom-fields'),
			callback: () => {
				window.location.href = `${adminUrl}post-new.php?post_type=${postType.name}`;
			},
			group: 'secure-custom-fields',
		});
	});
}

// Register commands when DOM is ready
wp.domReady(registerSCFCommands);