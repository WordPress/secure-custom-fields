/**
 * Pure helpers for the create taxonomy sidebar panel.
 *
 * Kept free of React and WordPress globals so the payload and validation
 * rules can be unit tested directly.
 *
 * @since SCF 6.9.6
 */

import { __ } from '@wordpress/i18n';

/**
 * Builds a taxonomy key from a label.
 *
 * Matches the key rules the classic editor enforces: lower case letters,
 * numbers, underscores and dashes, capped at 32 characters.
 *
 * @since SCF 6.9.6
 *
 * @param {string} label The label to convert.
 * @return {string} The generated taxonomy key.
 */
export function slugifyTaxonomyKey( label ) {
	return String( label ?? '' )
		.trim()
		.toLowerCase()
		.replace( /[^a-z0-9_-]+/g, '-' )
		.replace( /-+/g, '-' )
		.replace( /^-|-$/g, '' )
		.substring( 0, 32 );
}

/**
 * Validates the create taxonomy form values.
 *
 * @since SCF 6.9.6
 *
 * @param {Object} values The form values.
 * @return {Object} A map of field name to error message. Empty when valid.
 */
export function validateTaxonomyForm( values ) {
	const errors = {};
	const taxonomy = String( values.taxonomy ?? '' ).trim();

	if ( ! String( values.singularLabel ?? '' ).trim() ) {
		errors.singularLabel = __(
			'The singular label is required.',
			'secure-custom-fields'
		);
	}

	if ( ! String( values.pluralLabel ?? '' ).trim() ) {
		errors.pluralLabel = __(
			'The plural label is required.',
			'secure-custom-fields'
		);
	}

	if ( ! taxonomy ) {
		errors.taxonomy = __(
			'The taxonomy key is required.',
			'secure-custom-fields'
		);
	} else if ( taxonomy.length > 32 ) {
		errors.taxonomy = __(
			'The taxonomy key may be 32 characters or fewer.',
			'secure-custom-fields'
		);
	} else if ( ! /^[a-z0-9_-]+$/.test( taxonomy ) ) {
		errors.taxonomy = __(
			'The taxonomy key must only contain lower case letters, numbers, underscores or dashes.',
			'secure-custom-fields'
		);
	}

	return errors;
}

/**
 * Builds the REST payload for creating a taxonomy.
 *
 * @since SCF 6.9.6
 *
 * @param {Object} values   The form values.
 * @param {string} postType The post type the taxonomy should be bound to.
 * @return {Object} The request body.
 */
export function buildTaxonomyPayload( values, postType ) {
	return {
		singular_label: String( values.singularLabel ?? '' ).trim(),
		plural_label: String( values.pluralLabel ?? '' ).trim(),
		taxonomy: String( values.taxonomy ?? '' ).trim(),
		hierarchical: Boolean( values.hierarchical ),
		object_type: postType ? [ postType ] : [],
	};
}
