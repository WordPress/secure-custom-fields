/**
 * ACF Block Type Registration - Version 3
 * Handles registration of ACF blocks (version 3) with WordPress Gutenberg
 * Includes attribute setup, higher-order component composition, and block filtering
 */

import jQuery from 'jquery';
import { BlockEdit } from './components/block-edit';
import { withAlignText } from './high-order-components/with-align-text';
import { withAlignContent } from './high-order-components/with-align-content';
import { withFullHeight } from './high-order-components/with-full-height';

const { InnerBlocks } = wp.blockEditor;
const { Component } = wp.element;
const { createHigherOrderComponent } = wp.compose;

// Registry to store registered block configurations
const registeredBlocks = {};

/**
 * Adds an attribute to the block configuration
 *
 * @param {Object} attributes - Existing attributes object
 * @param {string} attributeName - Name of the attribute to add
 * @param {string} attributeType - Type of the attribute (string, boolean, etc.)
 * @returns {Object} - Updated attributes object
 */
const addAttribute = ( attributes, attributeName, attributeType ) => {
	attributes[ attributeName ] = { type: attributeType };
	return attributes;
};

/**
 * Checks if block should be registered for current post type
 *
 * @param {Object} blockConfig - Block configuration
 * @returns {boolean} - True if block should be registered
 */
function shouldRegisterBlock( blockConfig ) {
	const allowedPostTypes = blockConfig.post_types || [];

	if ( allowedPostTypes.length ) {
		// Always allow in reusable blocks
		allowedPostTypes.push( 'wp_block' );

		const currentPostType = acf.get( 'postType' );
		if ( ! allowedPostTypes.includes( currentPostType ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Processes and normalizes block icon
 *
 * @param {Object} blockConfig - Block configuration
 */
function processBlockIcon( blockConfig ) {
	// Convert SVG string to JSX element
	if (
		typeof blockConfig.icon === 'string' &&
		blockConfig.icon.substr( 0, 4 ) === '<svg'
	) {
		const iconSvg = blockConfig.icon;
		blockConfig.icon = (
			<div dangerouslySetInnerHTML={ { __html: iconSvg } } />
		);
	}

	// Remove icon if empty/invalid
	if ( ! blockConfig.icon ) {
		delete blockConfig.icon;
	}
}

/**
 * Validates and normalizes block category
 * Falls back to 'common' if category doesn't exist
 *
 * @param {Object} blockConfig - Block configuration
 */
function validateBlockCategory( blockConfig ) {
	const categoryExists = wp.blocks
		.getCategories()
		.filter( ( { slug } ) => slug === blockConfig.category )
		.pop();

	if ( ! categoryExists ) {
		blockConfig.category = 'common';
	}
}

/**
 * Sets default values for block configuration
 *
 * @param {Object} blockConfig - Block configuration
 * @returns {Object} - Block configuration with defaults applied
 */
function applyBlockDefaults( blockConfig ) {
	return acf.parseArgs( blockConfig, {
		title: '',
		name: '',
		category: '',
		api_version: 2,
		acf_block_version: 3,
		attributes: {},
		supports: {},
	} );
}

/**
 * Cleans up block attributes
 * Removes empty default values
 *
 * @param {Object} blockConfig - Block configuration
 */
function cleanBlockAttributes( blockConfig ) {
	for ( const attributeName in blockConfig.attributes ) {
		if (
			'default' in blockConfig.attributes[ attributeName ] &&
			blockConfig.attributes[ attributeName ].default.length === 0
		) {
			delete blockConfig.attributes[ attributeName ].default;
		}
	}
}

/**
 * Configures anchor support if enabled
 *
 * @param {Object} blockConfig - Block configuration
 */
function configureAnchorSupport( blockConfig ) {
	if ( blockConfig.supports && blockConfig.supports.anchor ) {
		blockConfig.attributes.anchor = { type: 'string' };
	}
}

/**
 * Applies higher-order components based on block supports
 *
 * @param {React.Component} EditComponent - Base edit component
 * @param {Object} blockConfig - Block configuration
 * @returns {React.Component} - Enhanced edit component
 */
function applyHigherOrderComponents( EditComponent, blockConfig ) {
	let enhancedComponent = EditComponent;

	// Add text alignment support
	if ( blockConfig.supports.alignText || blockConfig.supports.align_text ) {
		blockConfig.attributes = addAttribute(
			blockConfig.attributes,
			'align_text',
			'string'
		);
		enhancedComponent = withAlignText( enhancedComponent, blockConfig );
	}

	// Add content alignment support
	if (
		blockConfig.supports.alignContent ||
		blockConfig.supports.align_content
	) {
		blockConfig.attributes = addAttribute(
			blockConfig.attributes,
			'align_content',
			'string'
		);
		enhancedComponent = withAlignContent( enhancedComponent, blockConfig );
	}

	// Add full height support
	if ( blockConfig.supports.fullHeight || blockConfig.supports.full_height ) {
		blockConfig.attributes = addAttribute(
			blockConfig.attributes,
			'full_height',
			'boolean'
		);
		enhancedComponent = withFullHeight( enhancedComponent );
	}

	return enhancedComponent;
}

/**
 * Registers an ACF block type (version 3) with WordPress
 *
 * @param {Object} blockConfig - ACF block configuration object
 * @returns {Object|boolean} - Registered block type or false if not registered
 */
function registerACFBlockType( blockConfig ) {
	// Check if block should be registered for current post type
	if ( ! shouldRegisterBlock( blockConfig ) ) {
		return false;
	}

	// Process icon
	processBlockIcon( blockConfig );

	// Validate category
	validateBlockCategory( blockConfig );

	// Apply default values
	blockConfig = applyBlockDefaults( blockConfig );

	// Clean up attributes
	cleanBlockAttributes( blockConfig );

	// Configure anchor support
	configureAnchorSupport( blockConfig );

	// Start with base BlockEdit component
	let EditComponent = BlockEdit;

	// Apply higher-order components based on supports
	EditComponent = applyHigherOrderComponents( EditComponent, blockConfig );

	// Create edit function that passes blockConfig and jQuery
	blockConfig.edit = function ( props ) {
		return (
			<EditComponent
				{ ...props }
				blockType={ blockConfig }
				$={ jQuery }
			/>
		);
	};

	// Create save function (ACF blocks save to post content as HTML comments)
	blockConfig.save = () => <InnerBlocks.Content />;

	// Store in registry
	registeredBlocks[ blockConfig.name ] = blockConfig;

	// Register with WordPress
	const registeredBlockType = wp.blocks.registerBlockType(
		blockConfig.name,
		blockConfig
	);

	// Ensure anchor attribute is properly configured
	if (
		registeredBlockType &&
		registeredBlockType.attributes &&
		registeredBlockType.attributes.anchor
	) {
		registeredBlockType.attributes.anchor = { type: 'string' };
	}

	return registeredBlockType;
}

/**
 * Retrieves a registered block configuration by name
 *
 * @param {string} blockName - Name of the block
 * @returns {Object|boolean} - Block configuration or false
 */
function getRegisteredBlock( blockName ) {
	return registeredBlocks[ blockName ] || false;
}

/**
 * Higher-order component to migrate legacy attribute names to new format
 * Handles backward compatibility for align_text -> alignText, etc.
 */
const withDefaultAttributes = createHigherOrderComponent(
	( BlockListBlock ) =>
		class extends Component {
			constructor( props ) {
				super( props );

				const { name, attributes } = this.props;
				const blockConfig = getRegisteredBlock( name );

				if ( ! blockConfig ) return;

				// Remove empty string attributes
				Object.keys( attributes ).forEach( ( key ) => {
					if ( attributes[ key ] === '' ) {
						delete attributes[ key ];
					}
				} );

				// Map old attribute names to new camelCase names
				const attributeMap = {
					full_height: 'fullHeight',
					align_content: 'alignContent',
					align_text: 'alignText',
				};

				Object.keys( attributeMap ).forEach( ( oldKey ) => {
					const newKey = attributeMap[ oldKey ];

					if ( attributes[ oldKey ] !== undefined ) {
						// Migrate old key to new key
						attributes[ newKey ] = attributes[ oldKey ];
					} else if (
						attributes[ newKey ] === undefined &&
						blockConfig[ oldKey ] !== undefined
					) {
						// Set default from block config if not present
						attributes[ newKey ] = blockConfig[ oldKey ];
					}

					// Clean up old attribute names
					delete blockConfig[ oldKey ];
					delete attributes[ oldKey ];
				} );

				// Apply default values from block config for missing attributes
				for ( let key in blockConfig.attributes ) {
					if (
						attributes[ key ] === undefined &&
						blockConfig[ key ] !== undefined
					) {
						attributes[ key ] = blockConfig[ key ];
					}
				}
			}

			render() {
				return <BlockListBlock { ...this.props } />;
			}
		},
	'withDefaultAttributes'
);

/**
 * Initialize ACF blocks on the 'prepare' action
 * Registers all ACF blocks with version 3 or higher
 */
acf.addAction( 'prepare', function () {
	// Ensure wp.blockEditor exists (backward compatibility)
	if ( ! wp.blockEditor ) {
		wp.blockEditor = wp.editor;
	}

	const blockTypes = acf.get( 'blockTypes' );

	if ( blockTypes ) {
		blockTypes.forEach( ( blockType ) => {
			// Only register blocks with version 3 or higher
			if ( parseInt( blockType.acf_block_version ) >= 3 ) {
				registerACFBlockType( blockType );
			}
		} );
	}
} );

/**
 * Register WordPress filter for attribute migration
 * Ensures backward compatibility with legacy attribute names
 */
wp.hooks.addFilter(
	'editor.BlockListBlock',
	'acf/with-default-attributes',
	withDefaultAttributes
);

// Export for testing/external use
export { registerACFBlockType, getRegisteredBlock };
