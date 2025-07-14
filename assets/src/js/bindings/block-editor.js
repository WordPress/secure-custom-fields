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

const BLOCK_BINDINGS_CONFIG = {
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
 * Gets the bindable attributes for a given block.
 *
 * @param {string} blockName The name of the block.
 *
 * @return {string[]} The bindable attributes for the block.
 */
function getBindableAttributes( blockName ) {
	const config = BLOCK_BINDINGS_CONFIG[ blockName ];
	return config ? Object.keys( config ) : [];
}

/**
 * Add custom controls to all blocks
 */
const withCustomControls = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const bindableAttributes = getBindableAttributes( props.name );
		const { updateBlockBindings, removeAllBlockBindings } =
			useBlockBindingsUtils();

		// Get ACF fields for current post
		const fields = useSelect( ( select ) => {
			const { getEditedEntityRecord } = select( coreDataStore );
			const { getCurrentPostType, getCurrentPostId } =
				select( editorStore );

			const postType = getCurrentPostType();
			const postId = getCurrentPostId();

			if ( ! postType || ! postId ) return {};

			const record = getEditedEntityRecord(
				'postType',
				postType,
				postId
			);

			// Extract fields that end with '_source' (simplified)
			const sourcedFields = {};
			Object.entries( record?.acf || {} ).forEach( ( [ key, value ] ) => {
				if ( key.endsWith( '_source' ) ) {
					const baseFieldName = key.replace( '_source', '' );
					if ( record?.acf.hasOwnProperty( baseFieldName ) ) {
						sourcedFields[ baseFieldName ] = value;
					}
				}
			} );
			return sourcedFields;
		}, [] );

		// Get filtered field options for an attribute
		const getFieldOptions = useCallback(
			( attribute = null ) => {
				if ( ! fields || Object.keys( fields ).length === 0 ) return [];

				const blockConfig = BLOCK_BINDINGS_CONFIG[ props.name ];
				let allowedTypes = null;

				if ( blockConfig ) {
					allowedTypes = attribute
						? blockConfig[ attribute ]
						: Object.values( blockConfig ).flat();
				}

				return Object.entries( fields )
					.filter(
						( [ , fieldConfig ] ) =>
							! allowedTypes ||
							allowedTypes.includes( fieldConfig.type )
					)
					.map( ( [ fieldName, fieldConfig ] ) => ( {
						value: fieldName,
						label: fieldConfig.label,
					} ) );
			},
			[ fields, props.name ]
		);

		// Check if all attributes use the same field types (for "all attributes" mode)
		const canUseAllAttributesMode = useMemo( () => {
			if ( ! bindableAttributes || bindableAttributes.length <= 1 )
				return false;

			const blockConfig = BLOCK_BINDINGS_CONFIG[ props.name ];
			if ( ! blockConfig ) return false;

			const firstAttributeTypes =
				blockConfig[ bindableAttributes[ 0 ] ] || [];
			return bindableAttributes.every( ( attr ) => {
				const attrTypes = blockConfig[ attr ] || [];
				return (
					attrTypes.length === firstAttributeTypes.length &&
					attrTypes.every( ( type ) =>
						firstAttributeTypes.includes( type )
					)
				);
			} );
		}, [ bindableAttributes, props.name ] );

		// Track bound fields
		const [ boundFields, setBoundFields ] = useState( {} );

		// Sync with current bindings
		useEffect( () => {
			const currentBindings = props.attributes?.metadata?.bindings || {};
			const newBoundFields = {};

			Object.keys( currentBindings ).forEach( ( attribute ) => {
				if ( currentBindings[ attribute ]?.args?.key ) {
					newBoundFields[ attribute ] =
						currentBindings[ attribute ].args.key;
				}
			} );

			setBoundFields( newBoundFields );
		}, [ props.attributes?.metadata?.bindings ] );

		// Handle field selection
		const handleFieldChange = useCallback(
			( attribute, value ) => {
				if ( Array.isArray( attribute ) ) {
					// Handle multiple attributes at once
					const newBoundFields = { ...boundFields };
					const bindings = {};

					attribute.forEach( ( attr ) => {
						newBoundFields[ attr ] = value;
						bindings[ attr ] = value
							? {
									source: 'acf/field',
									args: { key: value },
							  }
							: undefined;
					} );

					setBoundFields( newBoundFields );
					updateBlockBindings( bindings );
				} else {
					// Handle single attribute
					setBoundFields( ( prev ) => ( {
						...prev,
						[ attribute ]: value,
					} ) );
					updateBlockBindings( {
						[ attribute ]: value
							? {
									source: 'acf/field',
									args: { key: value },
							  }
							: undefined,
					} );
				}
			},
			[ boundFields, updateBlockBindings ]
		);

		// Handle reset
		const handleReset = useCallback( () => {
			removeAllBlockBindings();
			setBoundFields( {} );
		}, [ removeAllBlockBindings ] );

		// Don't show if no fields or attributes
		const fieldOptions = getFieldOptions();
		if ( fieldOptions.length === 0 || ! bindableAttributes ) {
			return <BlockEdit { ...props } />;
		}

		return (
			<>
				<InspectorControls { ...props }>
					<ToolsPanel
						label={ __(
							'Connect to a field',
							'secure-custom-fields'
						) }
						resetAll={ handleReset }
					>
						{ canUseAllAttributesMode ? (
							<ToolsPanelItem
								hasValue={ () =>
									!! boundFields[ bindableAttributes[ 0 ] ]
								}
								label={ __(
									'All attributes',
									'secure-custom-fields'
								) }
								onDeselect={ () =>
									handleFieldChange(
										bindableAttributes,
										null
									)
								}
								isShownByDefault={ true }
							>
								<ComboboxControl
									label={ __(
										'Field',
										'secure-custom-fields'
									) }
									placeholder={ __(
										'Select a field',
										'secure-custom-fields'
									) }
									options={ getFieldOptions() }
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
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							</ToolsPanelItem>
						) : (
							bindableAttributes.map( ( attribute ) => (
								<ToolsPanelItem
									key={ `scf-field-${ attribute }` }
									hasValue={ () =>
										!! boundFields[ attribute ]
									}
									label={ attribute }
									onDeselect={ () =>
										handleFieldChange( attribute, null )
									}
									isShownByDefault={ true }
								>
									<ComboboxControl
										label={ attribute }
										placeholder={ __(
											'Select a field',
											'secure-custom-fields'
										) }
										options={ getFieldOptions( attribute ) }
										value={ boundFields[ attribute ] || '' }
										onChange={ ( value ) =>
											handleFieldChange(
												attribute,
												value
											)
										}
										__next40pxDefaultSize
										__nextHasNoMarginBottom
									/>
								</ToolsPanelItem>
							) )
						) }
					</ToolsPanel>
				</InspectorControls>
				<BlockEdit { ...props } />
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
