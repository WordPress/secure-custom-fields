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
	// Register a command group for SCF
	wp.commands.registerCommand({
		name: 'scf-group',
		label: wp.i18n.__('Secure Custom Fields', 'secure-custom-fields'),
		group: 'secure-custom-fields',
	});

	// Register commands for each post type
	const postTypes = window._scfData?.customPostTypes || [];

	postTypes.forEach((postType) => {
		wp.commands.registerCommand({
			name: `scf-list-${postType.name}`,
			label: wp.i18n.__(`List ${postType.label}`, 'secure-custom-fields'),
			callback: () => {
				window.location.href = `${window._scfData.adminUrl}edit.php?post_type=${postType.name}`;
			},
			group: 'secure-custom-fields',
		});

		wp.commands.registerCommand({
			name: `scf-new-${postType.name}`,
			label: wp.i18n.__(`New ${postType.singular_label}`, 'secure-custom-fields'),
			callback: () => {
				window.location.href = `${window._scfData.adminUrl}post-new.php?post_type=${postType.name}`;
			},
			group: 'secure-custom-fields',
		});
	});
}

// Register commands when DOM is ready
wp.domReady(registerSCFCommands);