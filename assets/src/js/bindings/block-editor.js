/**
 * WordPress dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import {
	InspectorControls,
	useBlockBindingsUtils,
} from '@wordpress/block-editor';
import { PanelBody, MenuGroup, FormTokenField } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';
import { store as editorStore } from '@wordpress/editor';
import { store as blockEditorStore } from '@wordpress/block-editor';

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
		const { updateBlockBindings } = useBlockBindingsUtils();

		const { postType, postId } = useSelect( ( select ) => {
			const { getCurrentPostType, getCurrentPostId } =
				select( editorStore );
			return {
				postType: getCurrentPostType(),
				postId: getCurrentPostId(),
			};
		}, [] );

		const fields = useSelect(
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
				return record?.acf;
			},
			[ postType, postId ]
		);

		const fieldsSuggestions = Object.keys( fields );

		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ __(
							'Connect to a field',
							'secure-custom-fields'
						) }
						initialOpen={ true }
					>
						<MenuGroup>
							{ bindableAttributes.map( ( attribute ) => (
								<FormTokenField
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									__experimentalShowHowTo={ false }
									__experimentalExpandOnFocus={ true }
									__experimentalAutoSelectFirstMatch={ true }
									label={ attribute }
									maxLength={ 1 }
									onChange={ ( value ) => {
										updateBlockBindings( {
											[ attribute ]: {
												source: 'acf/field',
												args: {
													key: value[ 0 ],
												},
											},
										} );
									} }
									suggestions={ fieldsSuggestions }
									value={ [] }
									key={ `scf-field-${ attribute }` }
								/>
							) ) }
						</MenuGroup>
					</PanelBody>
				</InspectorControls>
			</>
		);
	};
}, 'withCustomControls' );

addFilter(
	'editor.BlockEdit',
	'secure-custom-fields/with-custom-controls',
	withCustomControls
);
