/**
 * Block binding configuration
 *
 * Defines which SCF field types can be bound to specific block attributes.
 */
export const BLOCK_BINDINGS_CONFIG = {
	'core/paragraph': {
		content: [ 'text', 'textarea', 'date_picker', 'number', 'range' ],
	},
	'core/heading': {
		content: [ 'text', 'textarea', 'date_picker', 'number', 'range' ],
	},
	'core/image': {
		id: [ 'image' ],
		url: [ 'image' ],
		title: [ 'image' ],
		alt: [ 'image' ],
	},
	'core/button': {
		url: [ 'url' ],
		text: [ 'text', 'checkbox', 'select', 'date_picker' ],
		linkTarget: [ 'text', 'checkbox', 'select' ],
		rel: [ 'text', 'checkbox', 'select' ],
	},
};

/**
 * Binding source identifier
 */
export const BINDING_SOURCE = 'acf/field';
