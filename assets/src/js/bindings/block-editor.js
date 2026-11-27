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
	ExternalLink,
	PanelBody,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { BINDING_SOURCE } from './constants';
import {
	getAllowedFieldTypes,
	getBindableAttributes,
	getBindingsEditorSettings,
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
 * Explains why the post's SCF fields can't be connected to this block.
 *
 * Shown in the post editor when the post has field groups but none of their
 * fields can be bound to the selected block.
 *
 * @since 6.9.10
 * @param {Object} props           Component props.
 * @param {string} props.blockName The block name.
 * @return {Element} The hint panel.
 */
function NoCompatibleFieldsHint( { blockName } ) {
	const { fieldGroupsUrl } = getBindingsEditorSettings();
	const fieldTypes = ( getAllowedFieldTypes( blockName ) || [] )
		.map( ( type ) => type.replace( /_/g, ' ' ) )
		.join( ', ' );

	return (
		<PanelBody
			title={ __( 'Connect to a field', 'secure-custom-fields' ) }
			initialOpen
		>
			<p>
				{ __(
					'No fields on this post can be connected to this block.',
					'secure-custom-fields'
				) }
			</p>
			<p>
				{ __(
					'To connect a field, turn on Show in REST API in the field group settings, and Allow Access to Value in Editor UI in the field settings.',
					'secure-custom-fields'
				) }
			</p>
			{ fieldTypes && (
				<p>
					{ sprintf(
						/* translators: %s: comma-separated list of field types, for example "text, textarea, number". */
						__(
							'This block supports these field types: %s.',
							'secure-custom-fields'
						),
						fieldTypes
					) }
				</p>
			) }
			{ fieldGroupsUrl && (
				<ExternalLink href={ fieldGroupsUrl }>
					{ __( 'Manage field groups', 'secure-custom-fields' ) }
				</ExternalLink>
			) }
		</PanelBody>
	);
}

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
				// An empty value for attributes that aren't bound has nothing to remove. Core's
				// updateBlockBindings() would store it as an `undefined` binding, which breaks the block.
				const currentBindings =
					props.attributes?.metadata?.bindings || {};
				const attributes = Array.isArray( attribute )
					? attribute
					: [ attribute ];
				if (
					! value &&
					! attributes.some( ( attr ) => currentBindings[ attr ] )
				) {
					return;
				}

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
			[
				boundFields,
				setBoundFields,
				updateBlockBindings,
				props.attributes?.metadata?.bindings,
			]
		);

		// Handle reset all bindings
		const handleReset = useCallback( () => {
			removeAllBlockBindings();
			setBoundFields( {} );
		}, [ removeAllBlockBindings, setBoundFields ] );

		// Whether any field can be bound to at least one of this block's attributes.
		const hasCompatibleFields = useMemo(
			() => getAttributeFieldOptions().length > 0,
			[ getAttributeFieldOptions ]
		);

		const hasBindableAttributes =
			!! bindableAttributes && bindableAttributes.length > 0;

		// Determine if we should show the panel
		const shouldShowPanel = useMemo( () => {
			// In site editor, show panel if block has bindable attributes
			if ( isSiteEditor ) {
				return hasBindableAttributes;
			}
			// In post editor, only show if this block can bind one of the fields
			return hasBindableAttributes && hasCompatibleFields;
		}, [ isSiteEditor, hasBindableAttributes, hasCompatibleFields ] );

		// In the post editor, explain an empty field list when the post has field groups.
		if (
			! shouldShowPanel &&
			! isSiteEditor &&
			hasBindableAttributes &&
			getBindingsEditorSettings().hasFieldGroups
		) {
			return (
				<>
					<InspectorControls { ...props }>
						<NoCompatibleFieldsHint blockName={ props.name } />
					</InspectorControls>
					<BlockEdit { ...props } />
				</>
			);
		}

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
