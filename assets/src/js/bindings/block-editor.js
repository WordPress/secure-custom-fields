/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import {
	InspectorControls,
	useBlockBindingsUtils,
} from '@wordpress/block-editor';
import {
	ComboboxControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';
import { store as editorStore } from '@wordpress/editor';

// These constant and the function above have been copied from Gutenberg. It should be public, eventually.

const BLOCK_BINDINGS_ALLOWED_BLOCKS = {
	'core/paragraph': [ 'content' ],
	'core/heading': [ 'content' ],
	'core/image': [ 'id', 'url', 'title', 'alt' ],
	'core/button': [ 'url', 'text', 'linkTarget', 'rel' ],
};

const BLOCK_BINDINGS_RELATED_FIELD_TYPES = {
	'core/paragraph': {
		content: [ 'text', 'textarea', 'date_picker', 'number' ],
	},
	'core/heading': {
		content: [ 'text', 'textarea', 'date_picker', 'number' ],
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
 * Gets the bindable attributes for a given block.
 *
 * @param {string} blockName The name of the block.
 *
 * @return {string[]} The bindable attributes for the block.
 */
function getBindableAttributes( blockName ) {
	return BLOCK_BINDINGS_ALLOWED_BLOCKS[ blockName ];
}

/**
 * Add custom controls to all blocks
 */
const withCustomControls = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const bindableAttributes = getBindableAttributes( props.name );
		const { updateBlockBindings, removeAllBlockBindings } =
			useBlockBindingsUtils();

		const { postType, postId } = useSelect( ( select ) => {
			const { getCurrentPostType, getCurrentPostId } =
				select( editorStore );
			return {
				postType: getCurrentPostType(),
				postId: getCurrentPostId(),
			};
		}, [] );

		const fieldsGroups = useSelect(
			( select ) => {
				const { getEditedEntityRecord } = select( coreDataStore );

				if ( ! postType || ! postId ) {
					return undefined;
				}

				const record = getEditedEntityRecord(
					'postType',
					postType,
					postId
				);
				return record?.scf_field_groups;
			},
			[ postType, postId ]
		);

		const currentBindings = props.attributes?.metadata?.bindings || {};

		const fields = useMemo(
			() =>
				fieldsGroups?.reduce( ( acc, fieldGroup ) => {
					const groupFields =
						fieldGroup.fields?.map( ( field ) => ( {
							...field,
							fieldGroupTitle: fieldGroup.title,
							name: field.name,
							label: field.label,
							value: field.value,
						} ) ) || [];

					return [ ...acc, ...groupFields ];
				}, [] ) || [],
			[ fieldsGroups ]
		);

		const fieldsSuggestions = useMemo( () => {
			const blockFieldTypes =
				BLOCK_BINDINGS_RELATED_FIELD_TYPES[ props.name ];

			if ( blockFieldTypes ) {
				// Get all unique field types for this block
				const allAllowedFieldTypes =
					Object.values( blockFieldTypes ).flat();
				const uniqueFieldTypes = [ ...new Set( allAllowedFieldTypes ) ];
				// Filter fields to only include those that match the allowed types for this block
				return fields
					.filter( ( field ) =>
						uniqueFieldTypes.includes( field.type )
					)
					.map( ( field ) => ( {
						value: field.name,
						label: field.label,
						fieldType: field.type,
					} ) );
			} else {
				// If no specific field types are defined for this block, return all fields
				return fields.map( ( field ) => ( {
					value: field.name,
					label: field.label,
					fieldType: field.type,
				} ) );
			}
		}, [ fields, props.name ] );

		// Get field suggestions for a specific attribute
		const getFieldSuggestionsForAttribute = useCallback(
			( attribute ) => {
				const blockFieldTypes =
					BLOCK_BINDINGS_RELATED_FIELD_TYPES[ props.name ];

				if ( blockFieldTypes && blockFieldTypes[ attribute ] ) {
					const allowedFieldTypes = blockFieldTypes[ attribute ];
					return fields
						.filter( ( field ) =>
							allowedFieldTypes.includes( field.type )
						)
						.map( ( field ) => ( {
							value: field.name,
							label: field.label,
						} ) );
				}

				// Fallback to all field suggestions
				return fieldsSuggestions;
			},
			[ fields, fieldsSuggestions, props.name ]
		);

		// Initialize the field state with an empty object to track multiple attributes
		const [ boundFields, setBoundFields ] = useState( {} );

		// Determine if we should show "All attributes" mode:
		// - Block must have multiple bindable attributes
		// - All attributes should use the same field types
		const shouldShowAllAttributesMode = useMemo( () => {
			const blockFieldTypes =
				BLOCK_BINDINGS_RELATED_FIELD_TYPES[ props.name ];

			if (
				! bindableAttributes ||
				bindableAttributes.length <= 1 ||
				! blockFieldTypes
			) {
				return false;
			}

			// Get field types for each attribute
			const attributeFieldTypes = bindableAttributes.map(
				( attr ) => blockFieldTypes[ attr ] || []
			);

			// Check if all attributes have the same field types
			const firstAttributeTypes = attributeFieldTypes[ 0 ];
			const allSameTypes = attributeFieldTypes.every(
				( types ) =>
					types.length === firstAttributeTypes.length &&
					types.every( ( type ) =>
						firstAttributeTypes.includes( type )
					)
			);

			return allSameTypes && firstAttributeTypes.length > 0;
		}, [ bindableAttributes, props.name ] );

		const [ allBoundFields, setAllBoundFields ] = useState(
			shouldShowAllAttributesMode
		);

		// Memoize the stringified currentBindings to avoid unnecessary effect runs
		const currentBindingsKey = useMemo(
			() => JSON.stringify( currentBindings ),
			[ currentBindings ]
		);

		// Initialize bound fields from current bindings when they change
		useEffect( () => {
			if ( Object.keys( currentBindings ).length > 0 ) {
				const initialBoundFields = {};

				// Extract field values from current bindings
				Object.keys( currentBindings ).forEach( ( attribute ) => {
					if ( currentBindings[ attribute ]?.args?.key ) {
						initialBoundFields[ attribute ] =
							currentBindings[ attribute ].args.key;
					}
				} );

				setBoundFields( initialBoundFields );
			} else {
				// Clear bound fields when there are no current bindings
				setBoundFields( {} );
			}
		}, [ currentBindingsKey ] );

		// Update allBoundFields when shouldShowAllAttributesMode changes
		useEffect( () => {
			setAllBoundFields( shouldShowAllAttributesMode );
		}, [ shouldShowAllAttributesMode ] );

		// Memoize the change handler to prevent creating new function on each render
		const handleFieldChange = useCallback(
			( attributes, value ) => {
				// Ensure attributes is always an array.
				const attributeArray = Array.isArray( attributes )
					? attributes
					: [ attributes ];

				if ( attributeArray.length > 1 ) {
					setBoundFields( ( prevState ) => {
						const newState = { ...prevState };
						const bindings = {};

						attributeArray.forEach( ( attr ) => {
							newState[ attr ] = value;
							bindings[ attr ] = {
								source: 'acf/field',
								args: {
									key: value,
								},
							};
						} );

						// Update all bindings at once.
						updateBlockBindings( bindings );

						return newState;
					} );
				} else {
					const singleAttribute = attributeArray[ 0 ];
					setBoundFields( ( prevState ) => ( {
						...prevState,
						[ singleAttribute ]: value,
					} ) );
					updateBlockBindings( {
						[ singleAttribute ]: {
							source: 'acf/field',
							args: {
								key: value,
							},
						},
					} );
				}
			},
			[ updateBlockBindings ]
		);

		const handleReset = useCallback( () => {
			removeAllBlockBindings();
			setBoundFields( {} );
		}, [ removeAllBlockBindings ] );

		if ( fieldsSuggestions.length === 0 || ! bindableAttributes ) {
			return <BlockEdit { ...props } />;
		}

		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<ToolsPanel
						label={ __(
							'Connect to a field',
							'secure-custom-fields'
						) }
						resetAll={ handleReset }
					>
						{ allBoundFields ? (
							<ToolsPanelItem
								hasValue={ () =>
									!! boundFields[ bindableAttributes[ 0 ] ]
								}
								label={ __(
									'All attributes',
									'secure-custom-fields'
								) }
								onDeselect={ () =>
									handleFieldChange( bindableAttributes, '' )
								}
								isShownByDefault={ true }
							>
								<ComboboxControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									__experimentalShowHowTo={ false }
									__experimentalExpandOnFocus={ true }
									__experimentalAutoSelectFirstMatch={ true }
									label={ __(
										'Field',
										'secure-custom-fields'
									) }
									placeholder={ __(
										'Select a field',
										'secure-custom-fields'
									) }
									options={ fieldsSuggestions }
									value={
										boundFields[
											bindableAttributes[ 0 ]
										] || ''
									}
									onChange={ ( value ) =>
										handleFieldChange(
											bindableAttributes,
											value
										)
									}
								/>
							</ToolsPanelItem>
						) : (
							<>
								{ bindableAttributes.map( ( attribute ) => (
									<ToolsPanelItem
										key={ `scf-field-${ attribute }` }
										hasValue={ () =>
											!! boundFields[ attribute ]
										}
										label={ attribute }
										onDeselect={ () =>
											handleFieldChange( attribute, '' )
										}
										isShownByDefault={ true }
									>
										<ComboboxControl
											__next40pxDefaultSize
											__nextHasNoMarginBottom
											__experimentalShowHowTo={ false }
											__experimentalExpandOnFocus={ true }
											__experimentalAutoSelectFirstMatch={
												true
											}
											label={ attribute }
											placeholder={ __(
												'Select a field',
												'secure-custom-fields'
											) }
											options={ getFieldSuggestionsForAttribute(
												attribute
											) }
											value={
												boundFields[ attribute ] || ''
											}
											onChange={ ( value ) =>
												handleFieldChange(
													attribute,
													value
												)
											}
										/>
									</ToolsPanelItem>
								) ) }
							</>
						) }
					</ToolsPanel>
				</InspectorControls>
			</>
		);
	};
}, 'withCustomControls' );

if ( window.scf?.betaFeatures?.connect_fields ) {
	addFilter(
		'editor.BlockEdit',
		'secure-custom-fields/with-custom-controls',
		withCustomControls
	);
}
