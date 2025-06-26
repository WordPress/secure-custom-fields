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
	BaseControl,
	ComboboxControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';
import { store as editorStore } from '@wordpress/editor';

/**
 * Internal dependencies
 */
import BlockAttributesControlLinkedButton from './components/block-attributes-control-linked-button';

const BLOCK_BINDINGS_ALLOWED_BLOCKS = {
	'core/paragraph': [ 'content' ],
	'core/heading': [ 'content' ],
	'core/image': [ 'id', 'url', 'title', 'alt' ],
	'core/button': [ 'url', 'text', 'linkTarget', 'rel' ],
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

		// Memoize the fields transformation to prevent unnecessary recalculations
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
		// Memoize the fieldsSuggestions to avoid recreating on every render
		const fieldsSuggestions = useMemo( () => {
			if ( props.name === 'core/image' ) {
				// return only the type image fields
				return fields
					.filter( ( field ) => field.type === 'image' )
					.map( ( field ) => ( {
						value: field.name,
						label: field.label,
					} ) );
			} else {
				return fields.map( ( field ) => ( {
					value: field.name,
					label: field.label,
				} ) );
			}
		}, [ fields ] );

		// Initialize the field state with an empty object to track multiple attributes
		const [ boundFields, setBoundFields ] = useState( {} );
		const [ allBoundFields, setAllBoundFields ] = useState(
			props.name === 'core/image'
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

		// Memoize the change handler to prevent creating new function on each render
		const handleFieldChange = useCallback(
			( attributes, value ) => {
				// Ensure attributes is always an array
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

						// Update all bindings at once
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

		// Handle reset for ToolsPanel
		const handleReset = useCallback( () => {
			removeAllBlockBindings();
			setBoundFields( {} );
		}, [ removeAllBlockBindings ] );

		// Check if any fields are bound to determine if reset should be shown
		const hasBoundFields = Object.keys( boundFields ).length > 0;

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
						{ 'core/image' === props.name && (
							<HStack>
								<BaseControl.VisualLabel as="legend">
									{ allBoundFields
										? __(
												'Unlink all attributes',
												'secure-custom-fields'
										  )
										: __(
												'Link all attributes',
												'secure-custom-fields'
										  ) }
								</BaseControl.VisualLabel>
								<BlockAttributesControlLinkedButton
									isLinked={ allBoundFields }
									onClick={ () => {
										setAllBoundFields( ! allBoundFields );
									} }
								/>
							</HStack>
						) }
						{ allBoundFields ? (
							<ToolsPanelItem
								hasValue={ () =>
									!! boundFields[ bindableAttributes[ 0 ] ]
								}
								label={ __( 'All attributes' ) }
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
									label={ __( 'All attributes' ) }
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
											options={ fieldsSuggestions }
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

// Only register the filter if the connect_fields beta feature is enabled
if ( window.scf?.betaFeatures?.connect_fields ) {
	addFilter(
		'editor.BlockEdit',
		'secure-custom-fields/with-custom-controls',
		withCustomControls
	);
}
