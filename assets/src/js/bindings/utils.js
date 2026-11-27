/**
 * Utility functions for block bindings
 */

import { BLOCK_BINDINGS_CONFIG } from './constants';

/**
 * Gets the bindable attributes for a given block.
 *
 * @since 6.7.0
 * @param {string} blockName The name of the block.
 * @return {string[]} The bindable attributes for the block.
 */
export function getBindableAttributes( blockName ) {
	const config = BLOCK_BINDINGS_CONFIG[ blockName ];
	return config ? Object.keys( config ) : [];
}

/**
 * Gets the allowed field types for a specific block attribute.
 *
 * @since 6.7.0
 * @param {string}      blockName The name of the block.
 * @param {string|null} attribute The attribute name, or null for all types.
 * @return {string[]|null} The allowed field types, or null if no restrictions.
 */
export function getAllowedFieldTypes( blockName, attribute = null ) {
	const blockConfig = BLOCK_BINDINGS_CONFIG[ blockName ];

	if ( ! blockConfig ) {
		return null;
	}

	if ( attribute ) {
		return blockConfig[ attribute ] || null;
	}

	// Get all unique field types for the block
	return [ ...new Set( Object.values( blockConfig ).flat() ) ];
}

/**
 * Filters field options based on allowed field types.
 *
 * @since 6.7.0
 * @param {Array}       fieldOptions  Array of field option objects with value, label, and type.
 * @param {string}      blockName     The name of the block.
 * @param {string|null} attribute     The attribute name, or null for all types.
 * @return {Array} Filtered array of field options.
 */
export function getFilteredFieldOptions(
	fieldOptions,
	blockName,
	attribute = null
) {
	if ( ! fieldOptions || fieldOptions.length === 0 ) {
		return [];
	}

	const allowedTypes = getAllowedFieldTypes( blockName, attribute );

	if ( ! allowedTypes ) {
		return fieldOptions;
	}

	return fieldOptions.filter( ( option ) =>
		allowedTypes.includes( option.type )
	);
}

/**
 * Checks if all bindable attributes for a block support the same field types.
 *
 * @since 6.7.0
 * @param {string}   blockName          The name of the block.
 * @param {string[]} bindableAttributes Array of bindable attribute names.
 * @return {boolean} True if all attributes support the same field types.
 */
export function canUseUnifiedBinding( blockName, bindableAttributes ) {
	if ( ! bindableAttributes || bindableAttributes.length <= 1 ) {
		return false;
	}

	const blockConfig = BLOCK_BINDINGS_CONFIG[ blockName ];
	if ( ! blockConfig ) {
		return false;
	}

	const firstAttributeTypes = blockConfig[ bindableAttributes[ 0 ] ] || [];

	return bindableAttributes.every( ( attr ) => {
		const attrTypes = blockConfig[ attr ] || [];
		return (
			attrTypes.length === firstAttributeTypes.length &&
			attrTypes.every( ( type ) => firstAttributeTypes.includes( type ) )
		);
	} );
}

/**
 * Extracts the post type from a template slug.
 *
 * @since 6.7.0
 * @param {string} templateSlug The template slug (e.g., 'single-product', 'archive-post').
 * @return {string|null} The extracted post type, or null if not detected.
 */
export function extractPostTypeFromTemplate( templateSlug ) {
	if ( ! templateSlug ) {
		return null;
	}

	// Handle single templates
	if ( templateSlug.startsWith( 'single-' ) ) {
		return templateSlug.replace( 'single-', '' );
	}

	// Handle archive templates
	if ( templateSlug.startsWith( 'archive-' ) ) {
		return templateSlug.replace( 'archive-', '' );
	}

	// Default single template maps to 'post'
	if ( templateSlug === 'single' ) {
		return 'post';
	}

	return null;
}

/**
 * Formats field data from API response into a usable structure.
 *
 * @since 6.7.0
 * @param {Array} fieldGroups Array of field group objects from the API.
 * @return {Object} Formatted fields map with field name as key.
 */
export function formatFieldGroupsData( fieldGroups ) {
	const fieldsMap = {};

	if ( ! Array.isArray( fieldGroups ) ) {
		return fieldsMap;
	}

	fieldGroups.forEach( ( group ) => {
		if ( Array.isArray( group.fields ) ) {
			group.fields.forEach( ( field ) => {
				fieldsMap[ field.name ] = {
					label: field.label,
					type: field.type,
					allow_in_bindings: field.allow_in_bindings,
				};
			} );
		}
	} );

	return fieldsMap;
}

/**
 * Checks whether a field's value can be output through block bindings.
 *
 * Mirrors the "Allow Access to Value in Editor UI" check in SCF\Blocks\Bindings::get_value():
 * a field bound with this setting off renders empty on the front end. Fields without the
 * flag (older REST responses) are treated as allowed, as they are in PHP.
 *
 * @since 6.9.10
 * @param {Object} fieldConfig The field data.
 * @return {boolean} Whether the field can be bound.
 */
export function isFieldAllowedInBindings( fieldConfig ) {
	return fieldConfig?.allow_in_bindings !== false;
}

/**
 * Converts fields map to options array for ComboboxControl.
 *
 * Fields without a name are left out, as there is no key to bind them by. Fields
 * with "Allow Access to Value in Editor UI" off are left out, as they would render
 * empty on the front end once bound.
 *
 * @since 6.7.0
 * @since 6.9.10 Leaves out fields without a name or not allowed in bindings.
 * @param {Object} fieldsMap Object with field data.
 * @return {Array} Array of option objects with value, label, and type.
 */
export function fieldsToOptions( fieldsMap ) {
	if ( ! fieldsMap || Object.keys( fieldsMap ).length === 0 ) {
		return [];
	}

	return Object.entries( fieldsMap )
		.filter(
			( [ fieldName, fieldConfig ] ) =>
				fieldName && isFieldAllowedInBindings( fieldConfig )
		)
		.map( ( [ fieldName, fieldConfig ] ) => ( {
			value: fieldName,
			label: fieldConfig.label,
			type: fieldConfig.type,
		} ) );
}

/**
 * Gets the bindings data localized by PHP with acf_localize_data().
 *
 * @since 6.9.10
 * @return {Object} Data with hasFieldGroups and fieldGroupsUrl.
 */
export function getBindingsEditorSettings() {
	const settings = window.acf?.get?.( 'scfBindings' );

	return {
		hasFieldGroups: !! settings?.hasFieldGroups,
		fieldGroupsUrl:
			typeof settings?.fieldGroupsUrl === 'string'
				? settings.fieldGroupsUrl
				: '',
	};
}
