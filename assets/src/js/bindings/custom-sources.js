/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockBindingsSource } from '@wordpress/blocks';
import { store as coreDataStore } from '@wordpress/core-data';

/**
 * Register SCF Custom Fields block binding source.
 *
 * This allows blocks to bind to custom fields managed by
 * the Secure Custom Fields plugin.
 */
console.log( 'Registering SCF Custom Fields block binding source...' );
registerBlockBindingsSource( {
	name: 'scf/experimental-field',
	label: 'SCF Custom Fields',
	getValues: function ( { context, clientId, bindings, select } ) {
		const { getEditedEntityRecord, getMedia } = select( coreDataStore );
		let fields;
		if ( context?.postType && context?.postId ) {
			fields = getEditedEntityRecord(
				'postType',
				context?.postType,
				context?.postId
			);
		}

		const result = {};

		// Process each binding attribute (id, url, alt, title, content)
		Object.keys( bindings ).forEach( ( attribute ) => {
			const fieldName = bindings[ attribute ]?.args?.field;
			if ( fieldName && fields?.acf ) {
				const fieldValue = fields.acf[ fieldName ];

				// Handle image fields which return objects with specific properties
				if ( typeof fieldValue === 'object' && fieldValue !== null ) {
					// Directly map the attribute to the corresponding property in the image object
					if ( attribute in fieldValue ) {
						result[ attribute ] = fieldValue[ attribute ];
					} else if ( attribute === 'content' && fieldValue.url ) {
						// If content is requested for an image field, use url
						result[ attribute ] = fieldValue.url;
					} else {
						result[ attribute ] = '';
					}
				}
				// Handle when field value is just an image ID number
				else if ( typeof fieldValue === 'number' ) {
					// Use core data store to get full image details
					const imageObj = getMedia( fieldValue );

					if ( imageObj ) {
						if ( attribute === 'id' ) {
							result[ attribute ] = imageObj.id;
						} else if (
							attribute === 'url' ||
							attribute === 'content'
						) {
							result[ attribute ] = imageObj.source_url;
						} else if ( attribute === 'alt' ) {
							result[ attribute ] = imageObj.alt_text || '';
						} else if ( attribute === 'title' ) {
							result[ attribute ] =
								imageObj.title?.rendered || '';
						} else {
							result[ attribute ] = '';
						}
					} else {
						// Image data not yet available
						result[ attribute ] = '';
					}
				} else {
					// For simple field values
					result[ attribute ] = fieldValue || '';
				}
			} else {
				result[ attribute ] = '';
			}
		} );

		return result;
	},
} );
