/**
 * External dependencies
 */
import { applyFilters } from '@wordpress/hooks';

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

/**
 * Filter name for extending the bindable block configuration.
 *
 * Third parties use this to make additional blocks and attributes bindable in
 * the SCF controls, and to allow their own field types for a block attribute.
 * A field type also has to be exposed over REST and have its
 * Allow Access to Value in Editor UI setting on before it can be picked.
 *
 * Core decides which block attributes actually support bindings. Adding a
 * block here only affects the SCF interface; without matching core support the
 * binding will not resolve on the front end.
 *
 * @since SCF 6.9.6
 */
export const BLOCK_BINDINGS_CONFIG_FILTER = 'scf/block-bindings-config';

/**
 * Gets the bindable block configuration.
 *
 * The filter is applied on every read rather than once at import time, so a
 * script that registers its filter after this bundle has loaded still has an
 * effect.
 *
 * @since SCF 6.9.6
 *
 * @return {Object} The filtered configuration, keyed by block name.
 */
export function getBlockBindingsConfig() {
	return applyFilters( BLOCK_BINDINGS_CONFIG_FILTER, BLOCK_BINDINGS_CONFIG );
}
