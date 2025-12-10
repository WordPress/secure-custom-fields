/**
 * WordPress dependencies
 */
import { useCallback, useMemo } from '@wordpress/element';
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

/**
 * Internal dependencies
 */
import { BINDING_SOURCE } from './constants';
import {
	getBindableAttributes,
	getFilteredFieldOptions,
	canUseUnifiedBinding,
	fieldsToOptions,
} from './utils';
import {
	useSiteEditorContext,
	usePostEditorFields,
	useSiteEditorFields,
	useBoundFields,
} from './hooks';

/**
 * Add custom block binding controls to supported blocks.
 *
 * @since 6.5.0
 */
const withCustomControls = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const bindableAttributes = getBindableAttributes( props.name );
		const { updateBlockBindings, removeAllBlockBindings } =
			useBlockBindingsUtils();

		// Get editor context
		const { isSiteEditor, templatePostType } = useSiteEditorContext();

		// Get fields based on editor context
		const postEditorFields = usePostEditorFields();
		const { fields: siteEditorFields } =
			useSiteEditorFields( templatePostType );

		// Use appropriate fields based on context
		const activeFields = isSiteEditor ? siteEditorFields : postEditorFields;

		// Convert fields to options format
		const allFieldOptions = useMemo(
			() => fieldsToOptions( activeFields ),
			[ activeFields ]
		);

		// Track bound fields
		const { boundFields, setBoundFields } = useBoundFields(
			props.attributes
		);

		// Get filtered field options for a specific attribute
		const getAttributeFieldOptions = useCallback(
			( attribute = null ) => {
				return getFilteredFieldOptions(
					allFieldOptions,
					props.name,
					attribute
				);
			},
			[ allFieldOptions, props.name ]
		);

		// Check if all attributes can use unified binding mode
		const canUseAllAttributesMode = useMemo(
			() => canUseUnifiedBinding( props.name, bindableAttributes ),
			[ props.name, bindableAttributes ]
		);

		// Handle field selection changes
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
									source: BINDING_SOURCE,
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
									source: BINDING_SOURCE,
									args: { key: value },
							  }
							: undefined,
					} );
				}
			},
			[ boundFields, setBoundFields, updateBlockBindings ]
		);

		// Handle reset all bindings
		const handleReset = useCallback( () => {
			removeAllBlockBindings();
			setBoundFields( {} );
		}, [ removeAllBlockBindings, setBoundFields ] );

		// Determine if we should show the panel
		const shouldShowPanel = useMemo( () => {
			// In site editor, show panel if block has bindable attributes
			if ( isSiteEditor ) {
				return bindableAttributes && bindableAttributes.length > 0;
			}
			// In post editor, only show if we have fields available
			return (
				allFieldOptions.length > 0 &&
				bindableAttributes &&
				bindableAttributes.length > 0
			);
		}, [ isSiteEditor, allFieldOptions, bindableAttributes ] );

		if ( ! shouldShowPanel ) {
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
								isShownByDefault
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
									options={ getAttributeFieldOptions() }
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
									key={ `scf-binding-${ attribute }` }
									hasValue={ () =>
										!! boundFields[ attribute ]
									}
									label={ attribute }
									onDeselect={ () =>
										handleFieldChange( attribute, null )
									}
									isShownByDefault
								>
									<ComboboxControl
										label={ attribute }
										placeholder={ __(
											'Select a field',
											'secure-custom-fields'
										) }
										options={ getAttributeFieldOptions(
											attribute
										) }
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

addFilter(
	'editor.BlockEdit',
	'secure-custom-fields/with-custom-controls',
	withCustomControls
);
