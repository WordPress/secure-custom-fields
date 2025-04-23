/**
 * SCF Core Command Palette integration
 *
 * Core WordPress admin commands for Secure Custom Fields.
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

	// Core command definitions for SCF admin pages
	const commands = [
		{ 
			name: 'field-groups', 
			label: __('Field Groups', 'secure-custom-fields'), 
			url: `${adminUrl}edit.php?post_type=acf-field-group`,
			icon: 'layout',
			description: __('SCF: View and manage custom field groups', 'secure-custom-fields'),
			keywords: ['acf', 'custom fields', 'field editor', 'manage fields']
		},
		{ 
			name: 'new-field-group', 
			label: __('Create New Field Group', 'secure-custom-fields'), 
			url: `${adminUrl}post-new.php?post_type=acf-field-group`,
			icon: 'plus',
			description: __('SCF: Create a new field group to organize custom fields', 'secure-custom-fields'),
			keywords: ['add', 'new', 'create', 'field group', 'custom fields']
		},
		{ 
			name: 'post-types', 
			label: __('Post Types', 'secure-custom-fields'), 
			url: `${adminUrl}edit.php?post_type=acf-post-type`,
			icon: 'admin-post',
			description: __('SCF: Manage custom post types', 'secure-custom-fields'),
			keywords: ['cpt', 'content types', 'manage post types']
		},
		{ 
			name: 'new-post-type', 
			label: __('Create New Post Type', 'secure-custom-fields'), 
			url: `${adminUrl}post-new.php?post_type=acf-post-type`,
			icon: 'plus',
			description: __('SCF: Create a new custom post type', 'secure-custom-fields'),
			keywords: ['add', 'new', 'create', 'cpt', 'content type']
		},
		{ 
			name: 'taxonomies', 
			label: __('Taxonomies', 'secure-custom-fields'), 
			url: `${adminUrl}edit.php?post_type=acf-taxonomy`,
			icon: 'category',
			description: __('SCF: Manage custom taxonomies for organizing content', 'secure-custom-fields'),
			keywords: ['categories', 'tags', 'terms', 'custom taxonomies']
		},
		{ 
			name: 'new-taxonomy', 
			label: __('Create New Taxonomy', 'secure-custom-fields'), 
			url: `${adminUrl}post-new.php?post_type=acf-taxonomy`,
			icon: 'plus',
			description: __('SCF: Create a new custom taxonomy', 'secure-custom-fields'),
			keywords: ['add', 'new', 'create', 'taxonomy', 'categories', 'tags']
		},
		{ 
			name: 'options-pages', 
			label: __('Options Pages', 'secure-custom-fields'), 
			url: `${adminUrl}edit.php?post_type=acf-ui-options-page`,
			icon: 'admin-settings',
			description: __('SCF: Manage custom options pages for global settings', 'secure-custom-fields'),
			keywords: ['settings', 'global options', 'site options']
		},
		{ 
			name: 'new-options-page', 
			label: __('Create New Options Page', 'secure-custom-fields'), 
			url: `${adminUrl}post-new.php?post_type=acf-ui-options-page`,
			icon: 'plus',
			description: __('SCF: Create a new custom options page', 'secure-custom-fields'),
			keywords: ['add', 'new', 'create', 'options', 'settings page']
		},
		{ 
			name: 'tools', 
			label: __('SCF Tools', 'secure-custom-fields'), 
			url: `${adminUrl}admin.php?page=acf-tools`,
			icon: 'admin-tools',
			description: __('SCF: Access SCF utility tools', 'secure-custom-fields'),
			keywords: ['utilities', 'import export', 'json']
		},
		{ 
			name: 'import', 
			label: __('Import SCF Data', 'secure-custom-fields'), 
			url: `${adminUrl}admin.php?page=acf-tools&tool=import`,
			icon: 'upload',
			description: __('SCF: Import field groups, post types, taxonomies, and options pages', 'secure-custom-fields'),
			keywords: ['upload', 'json', 'migration', 'transfer']
		},
		{ 
			name: 'export', 
			label: __('Export SCF Data', 'secure-custom-fields'), 
			url: `${adminUrl}admin.php?page=acf-tools&tool=export`,
			icon: 'download',
			description: __('SCF: Export field groups, post types, taxonomies, and options pages', 'secure-custom-fields'),
			keywords: ['download', 'json', 'backup', 'migration']
		}
	];
	
	// Register each command
	commands.forEach(command => {
		commandStore.registerCommand({
			name: 'scf/' + command.name,
			label: command.label,
			icon: createElement(Icon, { icon: command.icon }),
			context: 'admin',
			description: command.description,
			keywords: command.keywords,
			callback: ({ close }) => {
				document.location = command.url;
				close();
			}
		});
	});
}); 