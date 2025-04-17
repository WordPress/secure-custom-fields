/**
 * SCF Command Palette integration
 *
 * Uses WordPress Commands API to add Secure Custom Fields commands to the WordPress command palette.
 * Enhances the user experience with icons, descriptions, and keywords for better discoverability.
 * 
 * @since 6.5.0
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
	
	// Command definitions for SCF admin pages with improved metadata
	const commands = [
		{ 
			name: 'field-groups', 
			label: __('Field Groups', 'secure-custom-fields'), 
			url: 'edit.php?post_type=acf-field-group',
			icon: 'layout',
			description: __('SCF: View and manage custom field groups', 'secure-custom-fields'),
			keywords: ['acf', 'custom fields', 'field editor', 'manage fields']
		},
		{ 
			name: 'new-field-group', 
			label: __('Create New Field Group', 'secure-custom-fields'), 
			url: 'post-new.php?post_type=acf-field-group',
			icon: 'plus',
			description: __('SCF: Create a new field group to organize custom fields', 'secure-custom-fields'),
			keywords: ['add', 'new', 'create', 'field group', 'custom fields']
		},
		{ 
			name: 'post-types', 
			label: __('Post Types', 'secure-custom-fields'), 
			url: 'admin.php?page=acf-post-types',
			icon: 'admin-post',
			description: __('SCF: Manage custom post types', 'secure-custom-fields'),
			keywords: ['cpt', 'content types', 'manage post types']
		},
		{ 
			name: 'new-post-type', 
			label: __('Create New Post Type', 'secure-custom-fields'), 
			url: 'admin.php?page=acf-post-type',
			icon: 'plus',
			description: __('SCF: Create a new custom post type', 'secure-custom-fields'),
			keywords: ['add', 'new', 'create', 'cpt', 'content type']
		},
		{ 
			name: 'taxonomies', 
			label: __('Taxonomies', 'secure-custom-fields'), 
			url: 'admin.php?page=acf-taxonomies',
			icon: 'category',
			description: __('SCF: Manage custom taxonomies for organizing content', 'secure-custom-fields'),
			keywords: ['categories', 'tags', 'terms', 'custom taxonomies']
		},
		{ 
			name: 'new-taxonomy', 
			label: __('Create New Taxonomy', 'secure-custom-fields'), 
			url: 'admin.php?page=acf-taxonomy',
			icon: 'plus',
			description: __('SCF: Create a new custom taxonomy', 'secure-custom-fields'),
			keywords: ['add', 'new', 'create', 'taxonomy', 'categories', 'tags']
		},
		{ 
			name: 'options-pages', 
			label: __('Options Pages', 'secure-custom-fields'), 
			url: 'admin.php?page=acf-options-pages',
			icon: 'admin-settings',
			description: __('SCF: Manage custom options pages for global settings', 'secure-custom-fields'),
			keywords: ['settings', 'global options', 'site options']
		},
		{ 
			name: 'new-options-page', 
			label: __('Create New Options Page', 'secure-custom-fields'), 
			url: 'admin.php?page=acf-ui-options-page',
			icon: 'plus',
			description: __('SCF: Create a new custom options page', 'secure-custom-fields'),
			keywords: ['add', 'new', 'create', 'options', 'settings page']
		},
		{ 
			name: 'tools', 
			label: __('SCF Tools', 'secure-custom-fields'), 
			url: 'admin.php?page=acf-tools',
			icon: 'admin-tools',
			description: __('SCF: Access SCF utility tools', 'secure-custom-fields'),
			keywords: ['utilities', 'import export', 'json']
		},
		{ 
			name: 'import', 
			label: __('Import SCF Data', 'secure-custom-fields'), 
			url: 'admin.php?page=acf-tools&tool=import',
			icon: 'upload',
			description: __('SCF: Import field groups, post types, taxonomies, and options pages', 'secure-custom-fields'),
			keywords: ['upload', 'json', 'migration', 'transfer']
		},
		{ 
			name: 'export', 
			label: __('Export SCF Data', 'secure-custom-fields'), 
			url: 'admin.php?page=acf-tools&tool=export',
			icon: 'download',
			description: __('SCF: Export field groups, post types, taxonomies, and options pages', 'secure-custom-fields'),
			keywords: ['download', 'json', 'backup', 'migration']
		}
	];
	
	// Register each command with enhanced metadata
	commands.forEach(command => {
		commandStore.registerCommand({
			name: 'scf/' + command.name,
			label: command.label,
			icon: command.icon,
			context: 'admin',
			description: command.description,
			keywords: command.keywords,
			callback: () => {
				window.location.href = command.url;
			}
		});
	});
});