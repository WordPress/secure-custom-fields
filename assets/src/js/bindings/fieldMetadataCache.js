/**
 * SCF Field Metadata Cache
 *
 * @since 6.7.0
 * Simple cache for field metadata used in block bindings.
 */

let fieldMetadataCache = {};

/**
 * Set field metadata, replacing all existing data.
 *
 * @param {Object} fields - Field metadata object keyed by field key.
 */
export const setFieldMetadata = ( fields ) => {
	fieldMetadataCache = fields || {};
};

/**
 * Add field metadata, merging with existing data.
 *
 * @param {Object} fields - Field metadata object keyed by field key.
 */
export const addFieldMetadata = ( fields ) => {
	fieldMetadataCache = { ...fieldMetadataCache, ...fields };
};

/**
 * Clear all field metadata.
 */
export const clearFieldMetadata = () => {
	fieldMetadataCache = {};
};

/**
 * Get field metadata for a specific field.
 *
 * @param {string} fieldKey - The field key to retrieve metadata for.
 * @return {Object|null} Field metadata object or null if not found.
 */
export const getFieldMetadata = ( fieldKey ) => {
	return fieldMetadataCache[ fieldKey ] || null;
};

/**
 * Get all field metadata.
 *
 * @return {Object} All field metadata.
 */
export const getAllFieldMetadata = () => {
	return fieldMetadataCache;
};

/**
 * Check if field metadata exists for a given key.
 *
 * @param {string} fieldKey - The field key to check.
 * @return {boolean} True if metadata exists, false otherwise.
 */
export const hasFieldMetadata = ( fieldKey ) => {
	return !! fieldMetadataCache[ fieldKey ];
};
