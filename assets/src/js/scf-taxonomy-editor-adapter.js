/**
 * Maps DataForm field ids to the input names used by the taxonomy save routine.
 */
export const TAXONOMY_FIELD_NAMES = {
	plural_label: 'acf_taxonomy[labels][name]',
	singular_label: 'acf_taxonomy[labels][singular_name]',
	taxonomy: 'acf_taxonomy[taxonomy]',
	public: 'acf_taxonomy[public]',
	hierarchical: 'acf_taxonomy[hierarchical]',
};

/**
 * Returns the input name for a DataForm field id.
 *
 * @param {string} key The DataForm field id.
 * @return {string|undefined} The input name.
 */
export const getTaxonomyInputName = ( key ) => TAXONOMY_FIELD_NAMES[ key ];

/**
 * Maps a DataForm edits object to input names and values.
 *
 * @param {Object} edits Field values keyed by DataForm field id.
 * @return {Object} Values keyed by input name.
 */
export const getTaxonomyEdits = ( edits ) =>
	Object.entries( edits || {} ).reduce( ( values, [ key, value ] ) => {
		const name = getTaxonomyInputName( key );
		if ( name ) {
			values[ name ] = value;
		}
		return values;
	}, {} );

/**
 * Flattens stored taxonomy settings into DataForm data.
 *
 * @param {Object} taxonomy The stored taxonomy settings.
 * @return {Object} DataForm data.
 */
export const getTaxonomyFormData = ( taxonomy ) => {
	const data = taxonomy || {};

	return {
		plural_label: data.labels ? data.labels.name : '',
		singular_label: data.labels ? data.labels.singular_name : '',
		taxonomy: data.taxonomy || '',
		public: Boolean( data.public ),
		hierarchical: Boolean( data.hierarchical ),
	};
};

/**
 * Returns the form field definitions for the taxonomy editor.
 *
 * @param {Function} translate Translation function.
 * @return {Array} DataForm field definitions.
 */
export const getTaxonomyFields = ( translate ) => [
	{
		id: 'plural_label',
		label: translate( 'Plural Label', 'secure-custom-fields' ),
		type: 'text',
		Edit: 'text',
	},
	{
		id: 'singular_label',
		label: translate( 'Singular Label', 'secure-custom-fields' ),
		type: 'text',
		Edit: 'text',
	},
	{
		id: 'taxonomy',
		label: translate( 'Taxonomy Key', 'secure-custom-fields' ),
		type: 'text',
		Edit: 'text',
	},
	{
		id: 'public',
		label: translate( 'Public', 'secure-custom-fields' ),
		type: 'boolean',
		Edit: 'toggle',
	},
	{
		id: 'hierarchical',
		label: translate( 'Hierarchical', 'secure-custom-fields' ),
		type: 'boolean',
		Edit: 'toggle',
	},
];
