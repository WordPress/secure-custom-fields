const md5 = require( 'md5' );

( ( $, undefined ) => {
	// Dependencies.
	const {
		BlockControls,
		InspectorControls,
		InnerBlocks,
		useBlockProps,
		AlignmentToolbar,
		BlockVerticalAlignmentToolbar,
	} = wp.blockEditor;

	const { ToolbarGroup, ToolbarButton, Placeholder, Spinner } = wp.components;
	const { Fragment } = wp.element;
	const { Component } = React;
	const { useSelect } = wp.data;
	const { createHigherOrderComponent } = wp.compose;

	// Potentially experimental dependencies.
	const BlockAlignmentMatrixToolbar =
		wp.blockEditor.__experimentalBlockAlignmentMatrixToolbar || wp.blockEditor.BlockAlignmentMatrixToolbar;
	// Gutenberg v10.x begins transition from Toolbar components to Control components.
	const BlockAlignmentMatrixControl =
		wp.blockEditor.__experimentalBlockAlignmentMatrixControl || wp.blockEditor.BlockAlignmentMatrixControl;
	const BlockFullHeightAlignmentControl =
		wp.blockEditor.__experimentalBlockFullHeightAligmentControl ||
		wp.blockEditor.__experimentalBlockFullHeightAlignmentControl ||
		wp.blockEditor.BlockFullHeightAlignmentControl;
	const useInnerBlocksProps = wp.blockEditor.__experimentalUseInnerBlocksProps || wp.blockEditor.useInnerBlocksProps;

	/**
	 * Storage for registered block types.
	 *
	 * @since ACF 5.8.0
	 * @var object
	 */
	const blockTypes = {};

	/**
	 * Data storage for Block Instances and their DynamicHTML components.
	 * This is temporarily stored on the ACF object, but this will be replaced later.
	 * Developers should not rely on reading or using any aspect of acf.blockInstances.
	 *
	 * @since ACF 6.3
	 */
	acf.blockInstances = {};

	/**
	 * Returns a block type for the given name.
	 *
	 * @date	20/2/19
	 * @since	ACF 5.8.0
	 *
	 * @param	string name The block name.
	 * @return	(object|false)
	 */
	function getBlockType( name ) {
		return blockTypes[ name ] || false;
	}

	/**
	 * Returns a block version for a given block name
	 *
	 * @date 8/6/22
	 * @since ACF 6.0
	 *
	 * @param string name The block name
	 * @return int
	 */
	function getBlockVersion( name ) {
		const blockType = getBlockType( name );
		return blockType.acf_block_version || 1;
	}

	/**
	 * Returns a block's validate property. Default true.
	 *
	 * @since ACF 6.3
	 *
	 * @param string name The block name
	 * @return boolean
	 */
	function blockSupportsValidation( name ) {
		const blockType = getBlockType( name );
		return blockType.validate;
	}

	/**
	 * Returns true if a block (identified by client ID) is nested in a query loop block.
	 *
	 * @date 17/1/22
	 * @since ACF 5.12
	 *
	 * @param {string} clientId A block client ID
	 * @return boolean
	 */
	function isBlockInQueryLoop( clientId ) {
		const parents = wp.data.select( 'core/block-editor' ).getBlockParents( clientId );
		const parentsData = wp.data.select( 'core/block-editor' ).getBlocksByClientId( parents );
		return parentsData.filter( ( block ) => block.name === 'core/query' ).length;
	}

	/**
	 * Returns true if we're currently inside the WP 5.9+ site editor.
	 *
	 * @date 08/02/22
	 * @since ACF 5.12
	 *
	 * @return boolean
	 */
	function isSiteEditor() {
		return typeof pagenow === 'string' && pagenow === 'site-editor';
	}

	/**
	 * Returns true if the block editor is currently showing the desktop device type preview.
	 *
	 * This function will always return true in the site editor as it uses the
	 * edit-post store rather than the edit-site store.
	 *
	 * @date 15/02/22
	 * @since ACF 5.12
	 *
	 * @return boolean
	 */
	function isDesktopPreviewDeviceType() {
		const editPostStore = select( 'core/edit-post' );

		// Return true if the edit post store isn't available (such as in the widget editor)
		if ( ! editPostStore ) return true;

		// Check if function exists (experimental or not) and return true if it's Desktop, or doesn't exist.
		if ( editPostStore.__experimentalGetPreviewDeviceType ) {
			return 'Desktop' === editPostStore.__experimentalGetPreviewDeviceType();
		} else if ( editPostStore.getPreviewDeviceType ) {
			return 'Desktop' === editPostStore.getPreviewDeviceType();
		} else {
			return true;
		}
	}

	/**
	 * Returns true if the block editor is currently in template edit mode.
	 *
	 * @date 16/02/22
	 * @since ACF 5.12
	 *
	 * @return boolean
	 */
	function isEditingTemplate() {
		const editPostStore = select( 'core/edit-post' );

		// Return false if the edit post store isn't available (such as in the widget editor)
		if ( ! editPostStore ) return false;

		// Return false if the function doesn't exist
		if ( ! editPostStore.isEditingTemplate ) return false;

		return editPostStore.isEditingTemplate();
	}

	/**
	 * Returns true if we're currently inside an iFramed non-desktop device preview type (WP5.9+)
	 *
	 * @date 15/02/22
	 * @since ACF 5.12
	 *
	 * @return boolean
	 */
	function isiFramedMobileDevicePreview() {
		return $( 'iframe[name=editor-canvas]' ).length && ! isDesktopPreviewDeviceType();
	}

	/**
	 * Registers a block type.
	 *
	 * @date	19/2/19
	 * @since	ACF 5.8.0
	 *
	 * @param	object blockType The block type settings localized from PHP.
	 * @return	object The result from wp.blocks.registerBlockType().
	 */
	function registerBlockType( blockType ) {
		// Bail early if is excluded post_type.
		const allowedTypes = blockType.post_types || [];
		if ( allowedTypes.length ) {
			// Always allow block to appear on "Edit reusable Block" screen.
			allowedTypes.push( 'wp_block' );

			// Check post type.
			const postType = acf.get( 'postType' );
			if ( ! allowedTypes.includes( postType ) ) {
				return false;
			}
		}

		// Handle svg HTML.
		if ( typeof blockType.icon === 'string' && blockType.icon.substr( 0, 4 ) === '<svg' ) {
			const iconHTML = blockType.icon;
			blockType.icon = <Div>{ iconHTML }</Div>;
		}

		// Remove icon if empty to allow for default "block".
		// Avoids JS error preventing block from being registered.
		if ( ! blockType.icon ) {
			delete blockType.icon;
		}

		// Check category exists and fallback to "common".
		const category = wp.blocks
			.getCategories()
			.filter( ( { slug } ) => slug === blockType.category )
			.pop();
		if ( ! category ) {
			//console.warn( `The block "${blockType.name}" is registered with an unknown category "${blockType.category}".` );
			blockType.category = 'common';
		}

		// Merge in block settings before local additions.
		blockType = acf.parseArgs( blockType, {
			title: '',
			name: '',
			category: '',
			api_version: 2,
			acf_block_version: 1,
		} );

		// Remove all empty attribute defaults from PHP values to allow serialisation.
		// https://github.com/WordPress/gutenberg/issues/7342
		for ( const key in blockType.attributes ) {
			if ( 'default' in blockType.attributes[ key ] && blockType.attributes[ key ].default.length === 0 ) {
				delete blockType.attributes[ key ].default;
			}
		}

		// Apply anchor supports to avoid block editor default writing to ID.
		if ( blockType.supports.anchor ) {
			blockType.attributes.anchor = {
				type: 'string',
			};
		}

		// Append edit and save functions.
		let ThisBlockEdit = BlockEdit;
		let ThisBlockSave = BlockSave;

		// Apply alignText functionality.
		if ( blockType.supports.alignText || blockType.supports.align_text ) {
			blockType.attributes = addBackCompatAttribute( blockType.attributes, 'align_text', 'string' );
			ThisBlockEdit = withAlignTextComponent( ThisBlockEdit, blockType );
		}

		// Apply alignContent functionality.
		if ( blockType.supports.alignContent || blockType.supports.align_content ) {
			blockType.attributes = addBackCompatAttribute( blockType.attributes, 'align_content', 'string' );
			ThisBlockEdit = withAlignContentComponent( ThisBlockEdit, blockType );
		}

		// Apply fullHeight functionality.
		if ( blockType.supports.fullHeight || blockType.supports.full_height ) {
			blockType.attributes = addBackCompatAttribute( blockType.attributes, 'full_height', 'boolean' );
			ThisBlockEdit = withFullHeightComponent( ThisBlockEdit, blockType.blockType );
		}

		// Set edit and save functions.
		blockType.edit = ( props ) => {
			// Ensure we remove our save lock if a block is removed.
			wp.element.useEffect( () => {
				return () => {
					if ( ! wp.data.dispatch( 'core/editor' ) ) return;
					wp.data.dispatch( 'core/editor' ).unlockPostSaving( 'acf/block/' + props.clientId );
				};
			}, [] );

			return <ThisBlockEdit { ...props } />;
		};
		blockType.save = () => <ThisBlockSave />;

		// Add to storage.
		blockTypes[ blockType.name ] = blockType;

		// Register with WP.
		const result = wp.blocks.registerBlockType( blockType.name, blockType );

		// Fix bug in 'core/anchor/attribute' filter overwriting attribute.
		// Required for < WP5.9
		// See https://github.com/WordPress/gutenberg/issues/15240
		if ( result.attributes.anchor ) {
			result.attributes.anchor = {
				type: 'string',
			};
		}

		// Return result.
		return result;
	}

	/**
	 * Returns the wp.data.select() response with backwards compatibility.
	 *
	 * @date	17/06/2020
	 * @since	ACF 5.9.0
	 *
	 * @param	string selector The selector name.
	 * @return	mixed
	 */
	function select( selector ) {
		if ( selector === 'core/block-editor' ) {
			return wp.data.select( 'core/block-editor' ) || wp.data.select( 'core/editor' );
		}
		return wp.data.select( selector );
	}

	/**
	 * Returns the wp.data.dispatch() response with backwards compatibility.
	 *
	 * @date	17/06/2020
	 * @since	ACF 5.9.0
	 *
	 * @param	string selector The selector name.
	 * @return	mixed
	 */
	function dispatch( selector ) {
		return wp.data.dispatch( selector );
	}

	/**
	 * Returns an array of all blocks for the given args.
	 *
	 * @date	27/2/19
	 * @since	ACF 5.7.13
	 *
	 * @param	{object} args An object of key=>value pairs used to filter results.
	 * @return	array.
	 */
	function getBlocks( args ) {
		let blocks = [];

		// Local function to recurse through all child blocks and add to the blocks array.
		const recurseBlocks = ( block ) => {
			blocks.push( block );
			select( 'core/block-editor' ).getBlocks( block.clientId ).forEach( recurseBlocks );
		};

		// Trigger initial recursion for parent level blocks.
		select( 'core/block-editor' ).getBlocks().forEach( recurseBlocks );

		// Loop over args and filter.
		for ( const k in args ) {
			blocks = blocks.filter( ( { attributes } ) => attributes[ k ] === args[ k ] );
		}

		// Return results.
		return blocks;
	}

	/**
	 * Storage for the AJAX queue.
	 *
	 * @const {array}
	 */
	const ajaxQueue = {};

	/**
	 * Storage for cached AJAX requests for block content.
	 *
	 * @since ACF 5.12
	 * @const {array}
	 */
	const fetchCache = {};

	/**
	 * Fetches a JSON result from the AJAX API.
	 *
	 * @date	28/2/19
	 * @since	ACF 5.7.13
	 *
	 * @param	object block The block props.
	 * @query	object The query args used in AJAX callback.
	 * @return	object The AJAX promise.
	 */
	function fetchBlock( args ) {
		const { attributes = {}, context = {}, query = {}, clientId = null, delay = 0 } = args;

		// Build a unique queue ID from block data, including the clientId for edit forms.
		const queueId = md5( JSON.stringify( { ...attributes, ...context, ...query } ) );

		const data = ajaxQueue[ queueId ] || {
			query: {},
			timeout: false,
			promise: $.Deferred(),
			started: false,
		};

		// Append query args to storage.
		data.query = { ...data.query, ...query };

		if ( data.started ) return data.promise;

		// Set fresh timeout.
		clearTimeout( data.timeout );
		data.timeout = setTimeout( () => {
			data.started = true;
			if ( fetchCache[ queueId ] ) {
				ajaxQueue[ queueId ] = null;
				data.promise.resolve.apply( fetchCache[ queueId ][ 0 ], fetchCache[ queueId ][ 1 ] );
			} else {
				$.ajax( {
					url: acf.get( 'ajaxurl' ),
					dataType: 'json',
					type: 'post',
					cache: false,
					data: acf.prepareForAjax( {
						action: 'acf/ajax/fetch-block',
						block: JSON.stringify( attributes ),
						clientId: clientId,
						context: JSON.stringify( context ),
						query: data.query,
					} ),
				} )
					.always( () => {
						// Clean up queue after AJAX request is complete.
						ajaxQueue[ queueId ] = null;
					} )
					.done( function () {
						fetchCache[ queueId ] = [ this, arguments ];
						data.promise.resolve.apply( this, arguments );
					} )
					.fail( function () {
						data.promise.reject.apply( this, arguments );
					} );
			}
		}, delay );

		// Update storage.
		ajaxQueue[ queueId ] = data;

		// Return promise.
		return data.promise;
	}

	/**
	 * Returns true if both object are the same.
	 *
	 * @date	19/05/2020
	 * @since	ACF 5.9.0
	 *
	 * @param	object obj1
	 * @param	object obj2
	 * @return	bool
	 */
	function compareObjects( obj1, obj2 ) {
		return JSON.stringify( obj1 ) === JSON.stringify( obj2 );
	}

	/**
	 * Converts HTML into a React element.
	 *
	 * @date	19/05/2020
	 * @since	ACF 5.9.0
	 *
	 * @param	string html The HTML to convert.
	 * @param	int acfBlockVersion The ACF block version number.
	 * @return	object Result of React.createElement().
	 */
	acf.parseJSX = ( html, acfBlockVersion ) => {
		// Apply a temporary wrapper for the jQuery parse to prevent text nodes triggering errors.
		html = '<div>' + html + '</div>';
		// Correctly balance InnerBlocks tags for jQuery's initial parse.
		html = html.replace( /<InnerBlocks([^>]+)?\/>/, '<InnerBlocks$1></InnerBlocks>' );
		return parseNode( $( html )[ 0 ], acfBlockVersion, 0 ).props.children;
	};

	/**
	 * Converts a DOM node into a React element.
	 *
	 * @date	19/05/2020
	 * @since	ACF 5.9.0
	 *
	 * @param	DOM node The DOM node.
	 * @param	int acfBlockVersion The ACF block version number.
	 * @param	int level The recursion level.
	 * @return	object Result of React.createElement().
	 */
	function parseNode( node, acfBlockVersion, level = 0 ) {
		// Get node name.
		const nodeName = parseNodeName( node.nodeName.toLowerCase(), acfBlockVersion );
		if ( ! nodeName ) {
			return null;
		}

		// Get node attributes in React friendly format.
		const nodeAttrs = {};

		if ( level === 1 && nodeName !== 'ACFInnerBlocks' ) {
			// Top level (after stripping away the container div), create a ref for passing through to ACF's JS API.
			nodeAttrs.ref = React.createRef();
		}

		acf.arrayArgs( node.attributes )
			.map( parseNodeAttr )
			.forEach( ( { name, value } ) => {
				nodeAttrs[ name ] = value;
			} );

		if ( 'ACFInnerBlocks' === nodeName ) {
			return <ACFInnerBlocks { ...nodeAttrs } />;
		}

		// Define args for React.createElement().
		const args = [ nodeName, nodeAttrs ];
		acf.arrayArgs( node.childNodes ).forEach( ( child ) => {
			if ( child instanceof Text ) {
				const text = child.textContent;
				if ( text ) {
					args.push( text );
				}
			} else {
				args.push( parseNode( child, acfBlockVersion, level + 1 ) );
			}
		} );

		// Return element.
		return React.createElement.apply( this, args );
	}

	/**
	 * Converts a node or attribute name into it's JSX compliant name
	 *
	 * @date     05/07/2021
	 * @since    ACF 5.9.8
	 *
	 * @param    string name The node or attribute name.
	 * @return  string
	 */
	function getJSXName( name ) {
		const replacement = acf.isget( acf, 'jsxNameReplacements', name );
		if ( replacement ) return replacement;
		return name;
	}

	/**
	 * Converts the given name into a React friendly name or component.
	 *
	 * @date	19/05/2020
	 * @since	ACF 5.9.0
	 *
	 * @param	string name The node name in lowercase.
	 * @param	int acfBlockVersion The ACF block version number.
	 * @return	mixed
	 */
	function parseNodeName( name, acfBlockVersion ) {
		switch ( name ) {
			case 'innerblocks':
				if ( acfBlockVersion < 2 ) {
					return InnerBlocks;
				}
				return 'ACFInnerBlocks';
			case 'script':
				return Script;
			case '#comment':
				return null;
			default:
				// Replace names for JSX counterparts.
				name = getJSXName( name );
		}
		return name;
	}

	/**
	 * Functional component for ACFInnerBlocks.
	 *
	 * @since ACF 6.0.0
	 *
	 * @param obj props element properties.
	 * @return DOM element
	 */
	function ACFInnerBlocks( props ) {
		const { className = 'acf-innerblocks-container' } = props;
		const innerBlockProps = useInnerBlocksProps( { className: className }, props );

		return <div { ...innerBlockProps }>{ innerBlockProps.children }</div>;
	}

	/**
	 * Converts the given attribute into a React friendly name and value object.
	 *
	 * @date	19/05/2020
	 * @since	ACF 5.9.0
	 *
	 * @param	obj nodeAttr The node attribute.
	 * @return	obj
	 */
	function parseNodeAttr( nodeAttr ) {
		let name = nodeAttr.name;
		let value = nodeAttr.value;

		// Allow overrides for third party libraries who might use specific attributes.
		let shortcut = acf.applyFilters( 'acf_blocks_parse_node_attr', false, nodeAttr );

		if ( shortcut ) return shortcut;

		switch ( name ) {
			// Class.
			case 'class':
				name = 'className';
				break;

			// Style.
			case 'style':
				const css = {};
				value.split( ';' ).forEach( ( s ) => {
					const pos = s.indexOf( ':' );
					if ( pos > 0 ) {
						let ruleName = s.substr( 0, pos ).trim();
						const ruleValue = s.substr( pos + 1 ).trim();

						// Rename core properties, but not CSS variables.
						if ( ruleName.charAt( 0 ) !== '-' ) {
							ruleName = acf.strCamelCase( ruleName );
						}
						css[ ruleName ] = ruleValue;
					}
				} );
				value = css;
				break;

			// Default.
			default:
				// No formatting needed for "data-x" attributes.
				if ( name.indexOf( 'data-' ) === 0 ) {
					break;
				}

				// Replace names for JSX counterparts.
				name = getJSXName( name );

				// Convert JSON values.
				const c1 = value.charAt( 0 );
				if ( c1 === '[' || c1 === '{' ) {
					value = JSON.parse( value );
				}

				// Convert bool values.
				if ( value === 'true' || value === 'false' ) {
					value = value === 'true';
				}
				break;
		}
		return {
			name,
			value,
		};
	}

	/**
	 * Higher Order Component used to set default block attribute values.
	 *
	 * By modifying block attributes directly, instead of defining defaults in registerBlockType(),
	 * WordPress will include them always within the saved block serialized JSON.
	 *
	 * @date	31/07/2020
	 * @since	ACF 5.9.0
	 *
	 * @param	Component BlockListBlock The BlockListBlock Component.
	 * @return	Component
	 */
	const withDefaultAttributes = createHigherOrderComponent(
		( BlockListBlock ) =>
			class WrappedBlockEdit extends Component {
				constructor( props ) {
					super( props );

					// Extract vars.
					const { name, attributes } = this.props;

					// Only run on ACF Blocks.
					const blockType = getBlockType( name );
					if ( ! blockType ) {
						return;
					}

					// Check and remove any empty string attributes to match PHP behaviour.
					Object.keys( attributes ).forEach( ( key ) => {
						if ( attributes[ key ] === '' ) {
							delete attributes[ key ];
						}
					} );

					// Backward compatibility attribute replacement.
					const upgrades = {
						full_height: 'fullHeight',
						align_content: 'alignContent',
						align_text: 'alignText',
					};

					Object.keys( upgrades ).forEach( ( key ) => {
						if ( attributes[ key ] !== undefined ) {
							attributes[ upgrades[ key ] ] = attributes[ key ];
						} else if ( attributes[ upgrades[ key ] ] === undefined ) {
							//Check for a default
							if ( blockType[ key ] !== undefined ) {
								attributes[ upgrades[ key ] ] = blockType[ key ];
							}
						}
						delete blockType[ key ];
						delete attributes[ key ];
					} );

					// Set default attributes for those undefined.
					for ( let attribute in blockType.attributes ) {
						if ( attributes[ attribute ] === undefined && blockType[ attribute ] !== undefined ) {
							attributes[ attribute ] = blockType[ attribute ];
						}
					}
				}
				render() {
					return <BlockListBlock { ...this.props } />;
				}
			},
		'withDefaultAttributes'
	);
	wp.hooks.addFilter( 'editor.BlockListBlock', 'acf/with-default-attributes', withDefaultAttributes );

	/**
	 * The BlockSave functional component.
	 *
	 * @date	08/07/2020
	 * @since	ACF 5.9.0
	 */
	function BlockSave() {
		return <InnerBlocks.Content />;
	}

	/**
	 * The BlockEdit component.
	 *
	 * @date	19/2/19
	 * @since	ACF 5.7.12
	 */
	class BlockEdit extends Component {
		constructor( props ) {
			super( props );
			this.setup();
		}

		setup() {
			const { name, attributes, clientId } = this.props;
			const blockType = getBlockType( name );

			// Restrict current mode.
			function restrictMode( modes ) {
				if ( ! modes.includes( attributes.mode ) ) {
					attributes.mode = modes[ 0 ];
				}
			}

			if (
				isBlockInQueryLoop( clientId ) ||
				isSiteEditor() ||
				isiFramedMobileDevicePreview() ||
				isEditingTemplate()
			) {
				restrictMode( [ 'preview' ] );
			} else {
				switch ( blockType.mode ) {
					case 'edit':
						restrictMode( [ 'edit', 'preview' ] );
						break;
					case 'preview':
						restrictMode( [ 'preview', 'edit' ] );
						break;
					default:
						restrictMode( [ 'auto' ] );
						break;
				}
			}
		}

		render() {
			const { name, attributes, setAttributes, clientId } = this.props;
			const blockType = getBlockType( name );
			const forcePreview =
				isBlockInQueryLoop( clientId ) ||
				isSiteEditor() ||
				isiFramedMobileDevicePreview() ||
				isEditingTemplate();
			let { mode } = attributes;

			if ( forcePreview ) {
				mode = 'preview';
			}

			// Show toggle only for edit/preview modes and for blocks not in a query loop/FSE.
			let showToggle = blockType.supports.mode;
			if ( mode === 'auto' || forcePreview ) {
				showToggle = false;
			}

			// Configure toggle variables.
			const toggleText = mode === 'preview' ? acf.__( 'Switch to Edit' ) : acf.__( 'Switch to Preview' );
			const toggleIcon = mode === 'preview' ? 'edit' : 'welcome-view-site';
			function toggleMode() {
				setAttributes( {
					mode: mode === 'preview' ? 'edit' : 'preview',
				} );
			}

			// Return template.
			return (
				<Fragment>
					<BlockControls>
						{ showToggle && (
							<ToolbarGroup>
								<ToolbarButton
									className="components-icon-button components-toolbar__control"
									label={ toggleText }
									icon={ toggleIcon }
									onClick={ toggleMode }
								/>
							</ToolbarGroup>
						) }
					</BlockControls>

					<InspectorControls>
						{ mode === 'preview' && (
							<div className="acf-block-component acf-block-panel">
								<BlockForm { ...this.props } />
							</div>
						) }
					</InspectorControls>

					<BlockBody { ...this.props } />
				</Fragment>
			);
		}
	}

	/**
	 * The BlockBody functional component.
	 *
	 * @since	ACF 5.7.12
	 */
	function BlockBody( props ) {
		const { attributes, isSelected, name, clientId } = props;
		const { mode } = attributes;

		const index = useSelect( ( select ) => {
			const rootClientId = select( 'core/block-editor' ).getBlockRootClientId( clientId );
			return select( 'core/block-editor' ).getBlockIndex( clientId, rootClientId );
		} );

		let showForm = true;
		let additionalClasses = 'acf-block-component acf-block-body';

		if ( ( mode === 'auto' && ! isSelected ) || mode === 'preview' ) {
			additionalClasses += ' acf-block-preview';
			showForm = false;
		}

		// Setup block cache if required, and update mode.
		if ( ! ( clientId in acf.blockInstances ) ) {
			acf.blockInstances[ clientId ] = {
				validation_errors: false,
				mode: mode,
			};
		}
		acf.blockInstances[ clientId ].mode = mode;

		if ( ! isSelected ) {
			if ( blockSupportsValidation( name ) && acf.blockInstances[ clientId ].validation_errors ) {
				additionalClasses += ' acf-block-has-validation-error';
			}
			acf.blockInstances[ clientId ].has_been_deselected = true;
		}

		if ( getBlockVersion( name ) > 1 ) {
			return (
				<div { ...useBlockProps( { className: additionalClasses } ) }>
					{ showForm ? (
						<BlockForm { ...props } index={ index } />
					) : (
						<BlockPreview { ...props } index={ index } />
					) }
				</div>
			);
		} else {
			return (
				<div { ...useBlockProps() }>
					<div className="acf-block-component acf-block-body">
						{ showForm ? (
							<BlockForm { ...props } index={ index } />
						) : (
							<BlockPreview { ...props } index={ index } />
						) }
					</div>
				</div>
			);
		}
	}

	/**
	 * A react component to append HTMl.
	 *
	 * @date	19/2/19
	 * @since	ACF 5.7.12
	 *
	 * @param	string children The html to insert.
	 * @return	void
	 */
	class Div extends Component {
		render() {
			return <div dangerouslySetInnerHTML={ { __html: this.props.children } } />;
		}
	}

	/**
	 * A react Component for inline scripts.
	 *
	 * This Component uses a combination of React references and jQuery to append the
	 * inline <script> HTML each time the component is rendered.
	 *
	 * @date	29/05/2020
	 * @since	ACF 5.9.0
	 *
	 * @param	type Var Description.
	 * @return	type Description.
	 */
	class Script extends Component {
		render() {
			return <div ref={ ( el ) => ( this.el = el ) } />;
		}
		setHTML( html ) {
			$( this.el ).html( `<script>${ html }</script>` );
		}
		componentDidUpdate() {
			this.setHTML( this.props.children );
		}
		componentDidMount() {
			this.setHTML( this.props.children );
		}
	}

	/**
	 * DynamicHTML Class.
	 *
	 * A react componenet to load and insert dynamic HTML.
	 *
	 * @date	19/2/19
	 * @since	ACF 5.7.12
	 *
	 * @param	void
	 * @return	void
	 */
	class DynamicHTML extends Component {
		constructor( props ) {
			super( props );

			// Bind callbacks.
			this.setRef = this.setRef.bind( this );

			// Define default props and call setup().
			this.id = '';
			this.el = false;
			this.subscribed = true;
			this.renderMethod = 'jQuery';
			this.passedValidation = false;
			this.setup( props );

			// Load state.
			this.loadState();
		}

		setup( props ) {
			const constructor = this.constructor.name;
			const clientId = props.clientId;
			if ( ! ( clientId in acf.blockInstances ) ) {
				acf.blockInstances[ clientId ] = {
					validation_errors: false,
					mode: props.mode,
				};
			}
			if ( ! ( constructor in acf.blockInstances[ clientId ] ) ) {
				acf.blockInstances[ clientId ][ constructor ] = {};
			}
		}

		fetch() {
			// Do nothing.
		}

		maybePreload( blockId, clientId, form ) {
			acf.debug( 'Preload check', blockId, clientId, form );
			if ( ! isBlockInQueryLoop( this.props.clientId ) ) {
				const preloadedBlocks = acf.get( 'preloadedBlocks' );
				const modeText = form ? 'form' : 'preview';

				if ( preloadedBlocks && preloadedBlocks[ blockId ] ) {
					// Ensure we only preload the correct block state (form or preview).
					if (
						( form && ! preloadedBlocks[ blockId ].form ) ||
						( ! form && preloadedBlocks[ blockId ].form )
					) {
						acf.debug( 'Preload failed: state not preloaded.' );
						return false;
					}

					// Set HTML to the preloaded version.
					preloadedBlocks[ blockId ].html = preloadedBlocks[ blockId ].html.replaceAll( blockId, clientId );

					// Replace blockId in errors.
					if ( preloadedBlocks[ blockId ].validation && preloadedBlocks[ blockId ].validation.errors ) {
						preloadedBlocks[ blockId ].validation.errors = preloadedBlocks[ blockId ].validation.errors.map(
							( error ) => {
								error.input = error.input.replaceAll( blockId, clientId );
								return error;
							}
						);
					}

					// Return preloaded object.
					acf.debug( 'Preload successful', preloadedBlocks[ blockId ] );
					return preloadedBlocks[ blockId ];
				}
			}
			acf.debug( 'Preload failed: not preloaded.' );
			return false;
		}

		loadState() {
			const client = acf.blockInstances[ this.props.clientId ] || {};
			this.state = client[ this.constructor.name ] || {};
		}

		setState( state ) {
			acf.blockInstances[ this.props.clientId ][ this.constructor.name ] = {
				...this.state,
				...state,
			};

			// Update component state if subscribed.
			// - Allows AJAX callback to update store without modifying state of an unmounted component.
			if ( this.subscribed ) {
				super.setState( state );
			}

			acf.debug(
				'SetState',
				Object.assign( {}, this ),
				this.props.clientId,
				this.constructor.name,
				Object.assign( {}, acf.blockInstances[ this.props.clientId ][ this.constructor.name ] )
			);
		}

		setHtml( html ) {
			html = html ? html.trim() : '';

			// Bail early if html has not changed.
			if ( html === this.state.html ) {
				return;
			}

			// Update state.
			const state = {
				html,
			};

			if ( this.renderMethod === 'jsx' ) {
				state.jsx = acf.parseJSX( html, getBlockVersion( this.props.name ) );

				// Handle templates which don't contain any valid JSX parsable elements.
				if ( ! state.jsx ) {
					console.warn(
						'Your ACF block template contains no valid HTML elements. Appending a empty div to prevent React JS errors.'
					);
					state.html += '<div></div>';
					state.jsx = acf.parseJSX( state.html, getBlockVersion( this.props.name ) );
				}

				// If we've got an object (as an array) find the first valid React ref.
				if ( Array.isArray( state.jsx ) ) {
					let refElement = state.jsx.find( ( element ) => React.isValidElement( element ) );
					state.ref = refElement.ref;
				} else {
					state.ref = state.jsx.ref;
				}
				state.$el = $( this.el );
			} else {
				state.$el = $( html );
			}
			this.setState( state );
		}

		setRef( el ) {
			this.el = el;
		}

		render() {
			// Render JSX.
			if ( this.state.jsx ) {
				// If we're a v2+ block, use the jsx element itself as our ref.
				if ( getBlockVersion( this.props.name ) > 1 ) {
					this.setRef( this.state.jsx );
					return this.state.jsx;
				} else {
					return <div ref={ this.setRef }>{ this.state.jsx }</div>;
				}
			}

			// Return HTML.
			return (
				<div ref={ this.setRef }>
					<Placeholder>
						<Spinner />
					</Placeholder>
				</div>
			);
		}

		shouldComponentUpdate( { index }, { html } ) {
			if ( index !== this.props.index ) {
				this.componentWillMove();
			}
			return html !== this.state.html;
		}

		display( context ) {
			// This method is called after setting new HTML and the Component render.
			// The jQuery render method simply needs to move $el into place.
			if ( this.renderMethod === 'jQuery' ) {
				const $el = this.state.$el;
				const $prevParent = $el.parent();
				const $thisParent = $( this.el );

				// Move $el into place.
				$thisParent.html( $el );

				// Special case for reusable blocks.
				// Multiple instances of the same reusable block share the same block id.
				// This causes all instances to share the same state (cool), which unfortunately
				// pulls $el back and forth between the last rendered reusable block.
				// This simple fix leaves a "clone" behind :)
				if ( $prevParent.length && $prevParent[ 0 ] !== $thisParent[ 0 ] ) {
					$prevParent.html( $el.clone() );
				}
			}

			// Lock block if required.
			if ( this.getValidationErrors() && this.isNotNewlyAdded() ) {
				this.lockBlockForSaving();
			} else {
				this.unlockBlockForSaving();
			}

			// Call context specific method.
			switch ( context ) {
				case 'append':
					this.componentDidAppend();
					break;
				case 'remount':
					this.componentDidRemount();
					break;
			}
		}

		validate() {
			// Do nothing.
		}

		componentDidMount() {
			// Fetch on first load.
			if ( this.state.html === undefined ) {
				this.fetch();

				// Or remount existing HTML.
			} else {
				this.display( 'remount' );
			}
		}

		componentDidUpdate( prevProps, prevState ) {
			// HTML has changed.
			this.display( 'append' );
		}

		componentDidAppend() {
			acf.doAction( 'append', this.state.$el );
		}

		componentWillUnmount() {
			acf.doAction( 'unmount', this.state.$el );

			// Unsubscribe this component from state.
			this.subscribed = false;
		}

		componentDidRemount() {
			this.subscribed = true;

			// Use setTimeout to avoid incorrect timing of events.
			// React will unmount and mount components in DOM order.
			// This means a new component can be mounted before an old one is unmounted.
			// ACF shares $el across new/old components which is un-React-like.
			// This timout ensures that unmounting occurs before remounting.
			setTimeout( () => {
				acf.doAction( 'remount', this.state.$el );
			} );
		}

		componentWillMove() {
			acf.doAction( 'unmount', this.state.$el );
			setTimeout( () => {
				acf.doAction( 'remount', this.state.$el );
			} );
		}

		isNotNewlyAdded() {
			return acf.blockInstances[ this.props.clientId ].has_been_deselected || false;
		}

		hasShownValidation() {
			return acf.blockInstances[ this.props.clientId ].shown_validation || false;
		}

		setShownValidation() {
			acf.blockInstances[ this.props.clientId ].shown_validation = true;
		}

		setValidationErrors( errors ) {
			acf.blockInstances[ this.props.clientId ].validation_errors = errors;
		}

		getValidationErrors() {
			return acf.blockInstances[ this.props.clientId ].validation_errors;
		}

		getMode() {
			return acf.blockInstances[ this.props.clientId ].mode;
		}

		lockBlockForSaving() {
			if ( ! wp.data.dispatch( 'core/editor' ) ) return;
			wp.data.dispatch( 'core/editor' ).lockPostSaving( 'acf/block/' + this.props.clientId );
		}

		unlockBlockForSaving() {
			if ( ! wp.data.dispatch( 'core/editor' ) ) return;
			wp.data.dispatch( 'core/editor' ).unlockPostSaving( 'acf/block/' + this.props.clientId );
		}

		displayValidation( $formEl ) {
			if ( ! blockSupportsValidation( this.props.name ) ) {
				acf.debug( 'Block does not support validation' );
				return;
			}
			if ( ! $formEl || $formEl.hasClass( 'acf-empty-block-fields' ) ) {
				acf.debug( 'There is no edit form available to validate.' );
				return;
			}

			const errors = this.getValidationErrors();
			acf.debug( 'Starting handle validation', Object.assign( {}, this ), Object.assign( {}, $formEl ), errors );

			this.setShownValidation();

			let validator = acf.getBlockFormValidator( $formEl );
			validator.clearErrors();

			acf.doAction( 'blocks/validation/pre_apply', errors );
			if ( errors ) {
				validator.addErrors( errors );
				validator.showErrors( 'after' );

				this.lockBlockForSaving();
			} else {
				// remove previous error message
				if ( validator.has( 'notice' ) ) {
					validator.get( 'notice' ).update( {
						type: 'success',
						text: acf.__( 'Validation successful' ),
						timeout: 1000,
					} );
					validator.set( 'notice', null );
				}

				this.unlockBlockForSaving();
			}
			acf.doAction( 'blocks/validation/post_apply', errors );
		}
	}

	/**
	 * BlockForm Class.
	 *
	 * A react componenet to handle the block form.
	 *
	 * @date	19/2/19
	 * @since	ACF 5.7.12
	 *
	 * @param	string id the block id.
	 * @return	void
	 */
	class BlockForm extends DynamicHTML {
		setup( props ) {
			this.id = `BlockForm-${ props.clientId }`;
			super.setup( props );
		}

		fetch( validate_only = false, data = false ) {
			// Extract props.
			const { context, clientId, name } = this.props;
			let { attributes } = this.props;

			let query = { form: true };
			if ( validate_only ) {
				query = { validate: true };
				attributes.data = data;
			}

			const hash = createBlockAttributesHash( attributes, context );

			acf.debug( 'BlockForm fetch', attributes, query );

			// Try preloaded data first.
			const preloaded = this.maybePreload( hash, clientId, true );

			if ( preloaded ) {
				this.setHtml( acf.applyFilters( 'blocks/form/render', preloaded.html, true ) );
				if ( preloaded.validation ) this.setValidationErrors( preloaded.validation.errors );
				return;
			}

			if ( ! blockSupportsValidation( name ) ) {
				query.validate = false;
			}

			// Request AJAX and update HTML on complete.
			fetchBlock( {
				attributes,
				context,
				clientId,
				query,
			} ).done( ( { data } ) => {
				acf.debug( 'fetch block form promise' );

				if ( ! data ) {
					this.setHtml( `<div class="acf-block-fields acf-fields acf-empty-block-fields">${acf.__( 'Error loading block form' )}</div>` );
					return;
				}

				if ( data.form ) {
					this.setHtml(
						acf.applyFilters( 'blocks/form/render', data.form.replaceAll( data.clientId, clientId ), false )
					);
				}

				if ( data.validation ) this.setValidationErrors( data.validation.errors );

				if ( this.isNotNewlyAdded() ) {
					acf.debug( "Block has already shown it's invalid. The form needs to show validation errors" );
					this.validate();
				}
			} );
		}

		validate( loadState = true ) {
			if ( loadState ) {
				this.loadState();
			}

			acf.debug( 'BlockForm calling validate with state', Object.assign( {}, this ) );
			super.displayValidation( this.state.$el );
		}

		shouldComponentUpdate( nextProps, nextState ) {
			if (
				blockSupportsValidation( this.props.name ) &&
				this.state.$el &&
				this.isNotNewlyAdded() &&
				! this.hasShownValidation()
			) {
				this.validate( false ); // Shouldn't update state in shouldComponentUpdate.
			}

			return super.shouldComponentUpdate( nextProps, nextState );
		}

		componentWillUnmount() {
			super.componentWillUnmount();

			//TODO: either delete this, or clear validations here (if that's a sensible idea)

			acf.debug( 'BlockForm Component did unmount' );
		}

		componentDidRemount() {
			super.componentDidRemount();

			acf.debug( 'BlockForm component did remount' );

			const { $el } = this.state;

			if ( blockSupportsValidation( this.props.name ) && this.isNotNewlyAdded() ) {
				acf.debug( "Block has already shown it's invalid. The form needs to show validation errors" );
				this.validate();
			}

			// Make sure our on append events are registered.
			if ( $el.data( 'acf-events-added' ) !== true ) {
				this.componentDidAppend();
			}
		}

		componentDidAppend() {
			super.componentDidAppend();

			acf.debug( 'BlockForm component did append' );

			// Extract props.
			const { attributes, setAttributes, clientId, name } = this.props;
			const thisBlockForm = this;
			const { $el } = this.state;

			// Callback for updating block data and validation status if we're in an edit only mode.
			function serializeData( silent = false ) {
				const data = acf.serialize( $el, `acf-block_${ clientId }` );

				if ( silent ) {
					attributes.data = data;
				} else {
					setAttributes( {
						data,
					} );
				}

				if ( blockSupportsValidation( name ) && ! silent && thisBlockForm.getMode() === 'edit' ) {
					acf.debug( 'No block preview currently available. Need to trigger a validation only fetch.' );
					thisBlockForm.fetch( true, data );
				}
			}

			// Add events.
			let timeout = false;
			$el.on( 'change keyup', () => {
				clearTimeout( timeout );
				timeout = setTimeout( serializeData, 300 );
			} );

			// Log initialization for remount check on the persistent element.
			$el.data( 'acf-events-added', true );

			// Ensure newly added block is saved with data.
			// Do it silently to avoid triggering a preview render.
			if ( ! attributes.data ) {
				serializeData( true );
			}
		}
	}

	/**
	 * BlockPreview Class.
	 *
	 * A react componenet to handle the block preview.
	 *
	 * @date	19/2/19
	 * @since	ACF 5.7.12
	 *
	 * @param	string id the block id.
	 * @return	void
	 */
	class BlockPreview extends DynamicHTML {
		setup( props ) {
			const blockType = getBlockType( props.name );
			const contextPostId = acf.isget( this.props, 'context', 'postId' );

			this.id = `BlockPreview-${ props.clientId }`;

			super.setup( props );

			// Apply the contextPostId to the ID if set to stop query loop ID duplication.
			if ( contextPostId ) {
				this.id = `BlockPreview-${ props.clientId }-${ contextPostId }`;
			}

			if ( blockType.supports.jsx ) {
				this.renderMethod = 'jsx';
			}
		}

		fetch( args = {} ) {
			const {
				attributes = this.props.attributes,
				clientId = this.props.clientId,
				context = this.props.context,
				delay = 0,
			} = args;

			const { name } = this.props;

			// Remember attributes used to fetch HTML.
			this.setState( {
				prevAttributes: attributes,
				prevContext: context,
			} );

			const hash = createBlockAttributesHash( attributes, context );

			// Try preloaded data first.
			let preloaded = this.maybePreload( hash, clientId, false );

			if ( preloaded ) {
				if ( getBlockVersion( name ) == 1 ) {
					preloaded.html = '<div class="acf-block-preview">' + preloaded.html + '</div>';
				}
				this.setHtml( acf.applyFilters( 'blocks/preview/render', preloaded.html, true ) );
				if ( preloaded.validation ) this.setValidationErrors( preloaded.validation.errors );
				return;
			}

			let query = { preview: true };

			if ( ! blockSupportsValidation( name ) ) {
				query.validate = false;
			}

			// Request AJAX and update HTML on complete.
			fetchBlock( {
				attributes,
				context,
				clientId,
				query,
				delay,
			} ).done( ( { data } ) => {
				if ( ! data ) {
					this.setHtml( `<div class="acf-block-fields acf-fields acf-empty-block-fields">${acf.__( 'Error previewing block' )}</div>` );
					return;
				}

				let replaceHtml = data.preview.replaceAll( data.clientId, clientId );
				if ( getBlockVersion( name ) == 1 ) {
					replaceHtml = '<div class="acf-block-preview">' + replaceHtml + '</div>';
				}
				acf.debug( 'fetch block render promise' );
				this.setHtml( acf.applyFilters( 'blocks/preview/render', replaceHtml, false ) );
				if ( data.validation ) {
					this.setValidationErrors( data.validation.errors );
				}
				if ( this.isNotNewlyAdded() ) {
					this.validate();
				}
			} );
		}

		validate() {
			// Check we've got a block form for this instance.
			const client = acf.blockInstances[ this.props.clientId ] || {};
			const blockFormState = client.BlockForm || false;
			if ( blockFormState ) {
				super.displayValidation( blockFormState.$el );
			}
		}

		componentDidAppend() {
			super.componentDidAppend();
			this.renderBlockPreviewEvent();
		}

		shouldComponentUpdate( nextProps, nextState ) {
			const nextAttributes = nextProps.attributes;
			const thisAttributes = this.props.attributes;

			// Update preview if block data has changed.
			if (
				! compareObjects( nextAttributes, thisAttributes ) ||
				! compareObjects( nextProps.context, this.props.context )
			) {
				let delay = 0;

				// Delay fetch when editing className or anchor to simulate consistent logic to custom fields.
				if ( nextAttributes.className !== thisAttributes.className ) {
					delay = 300;
				}
				if ( nextAttributes.anchor !== thisAttributes.anchor ) {
					delay = 300;
				}

				acf.debug( 'Triggering fetch from block preview shouldComponentUpdate' );

				this.fetch( {
					attributes: nextAttributes,
					context: nextProps.context,
					delay,
				} );
			}
			return super.shouldComponentUpdate( nextProps, nextState );
		}

		renderBlockPreviewEvent() {
			// Extract props.
			const { attributes, name } = this.props;
			const { $el, ref } = this.state;
			var blockElement;

			// Generate action friendly type.
			const type = attributes.name.replace( 'acf/', '' );

			if ( ref && ref.current ) {
				// We've got a react ref from a JSX container. Use the parent as the blockElement
				blockElement = $( ref.current ).parent();
			} else if ( getBlockVersion( name ) == 1 ) {
				blockElement = $el;
			} else {
				blockElement = $el.parents( '.acf-block-preview' );
			}

			// Do action.
			acf.doAction( 'render_block_preview', blockElement, attributes );
			acf.doAction( `render_block_preview/type=${ type }`, blockElement, attributes );
		}

		componentDidRemount() {
			super.componentDidRemount();

			acf.debug(
				'Checking if fetch is required in BlockPreview componentDidRemount',
				Object.assign( {}, this.state.prevAttributes ),
				Object.assign( {}, this.props.attributes ),
				Object.assign( {}, this.state.prevContext ),
				Object.assign( {}, this.props.context )
			);

			// Update preview if data has changed since last render (changing from "edit" to "preview").
			if (
				! compareObjects( this.state.prevAttributes, this.props.attributes ) ||
				! compareObjects( this.state.prevContext, this.props.context )
			) {
				acf.debug( 'Triggering block preview fetch from componentDidRemount' );
				this.fetch();
			}

			// Fire the block preview event so blocks can reinit JS elements.
			// React reusing DOM elements covers any potential race condition from the above fetch.
			this.renderBlockPreviewEvent();
		}
	}

	/**
	 * Initializes ACF Blocks logic and registration.
	 *
	 * @since ACF 5.9.0
	 */
	function initialize() {
		// Add support for WordPress versions before 5.2.
		if ( ! wp.blockEditor ) {
			wp.blockEditor = wp.editor;
		}

		// Register block types.
		const blockTypes = acf.get( 'blockTypes' );
		if ( blockTypes ) {
			blockTypes.map( registerBlockType );
		}
	}

	// Run the initialize callback during the "prepare" action.
	// This ensures that all localized data is available and that blocks are registered before the WP editor has been instantiated.
	acf.addAction( 'prepare', initialize );

	/**
	 * Returns a valid vertical alignment.
	 *
	 * @date	07/08/2020
	 * @since	ACF 5.9.0
	 *
	 * @param	string align A vertical alignment.
	 * @return	string
	 */
	function validateVerticalAlignment( align ) {
		const ALIGNMENTS = [ 'top', 'center', 'bottom' ];
		const DEFAULT = 'top';
		return ALIGNMENTS.includes( align ) ? align : DEFAULT;
	}

	/**
	 * Returns a valid horizontal alignment.
	 *
	 * @date	07/08/2020
	 * @since	ACF 5.9.0
	 *
	 * @param	string align A horizontal alignment.
	 * @return	string
	 */
	function validateHorizontalAlignment( align ) {
		const ALIGNMENTS = [ 'left', 'center', 'right' ];
		const DEFAULT = acf.get( 'rtl' ) ? 'right' : 'left';
		return ALIGNMENTS.includes( align ) ? align : DEFAULT;
	}

	/**
	 * Returns a valid matrix alignment.
	 *
	 * Written for "upgrade-path" compatibility from vertical alignment to matrix alignment.
	 *
	 * @date	07/08/2020
	 * @since	ACF 5.9.0
	 *
	 * @param	string align A matrix alignment.
	 * @return	string
	 */
	function validateMatrixAlignment( align ) {
		const DEFAULT = 'center center';
		if ( align ) {
			const [ y, x ] = align.split( ' ' );
			return `${ validateVerticalAlignment( y ) } ${ validateHorizontalAlignment( x ) }`;
		}
		return DEFAULT;
	}

	/**
	 * A higher order component adding alignContent editing functionality.
	 *
	 * @date	08/07/2020
	 * @since	ACF 5.9.0
	 *
	 * @param	component OriginalBlockEdit The original BlockEdit component.
	 * @param	object blockType The block type settings.
	 * @return	component
	 */
	function withAlignContentComponent( OriginalBlockEdit, blockType ) {
		// Determine alignment vars
		let type = blockType.supports.align_content || blockType.supports.alignContent;
		let AlignmentComponent;
		let validateAlignment;
		switch ( type ) {
			case 'matrix':
				AlignmentComponent = BlockAlignmentMatrixControl || BlockAlignmentMatrixToolbar;
				validateAlignment = validateMatrixAlignment;
				break;
			default:
				AlignmentComponent = BlockVerticalAlignmentToolbar;
				validateAlignment = validateVerticalAlignment;
				break;
		}

		// Ensure alignment component exists.
		if ( AlignmentComponent === undefined ) {
			console.warn( `The "${ type }" alignment component was not found.` );
			return OriginalBlockEdit;
		}

		// Ensure correct block attribute data is sent in intial preview AJAX request.
		blockType.alignContent = validateAlignment( blockType.alignContent );

		// Return wrapped component.
		return class WrappedBlockEdit extends Component {
			render() {
				const { attributes, setAttributes } = this.props;
				const { alignContent } = attributes;
				function onChangeAlignContent( alignContent ) {
					setAttributes( {
						alignContent: validateAlignment( alignContent ),
					} );
				}
				return (
					<Fragment>
						<BlockControls group="block">
							<AlignmentComponent
								label={ acf.__( 'Change content alignment' ) }
								value={ validateAlignment( alignContent ) }
								onChange={ onChangeAlignContent }
							/>
						</BlockControls>
						<OriginalBlockEdit { ...this.props } />
					</Fragment>
				);
			}
		};
	}

	/**
	 * A higher order component adding alignText editing functionality.
	 *
	 * @date	08/07/2020
	 * @since	ACF 5.9.0
	 *
	 * @param	component OriginalBlockEdit The original BlockEdit component.
	 * @param	object blockType The block type settings.
	 * @return	component
	 */
	function withAlignTextComponent( OriginalBlockEdit, blockType ) {
		const validateAlignment = validateHorizontalAlignment;

		// Ensure correct block attribute data is sent in intial preview AJAX request.
		blockType.alignText = validateAlignment( blockType.alignText );

		// Return wrapped component.
		return class WrappedBlockEdit extends Component {
			render() {
				const { attributes, setAttributes } = this.props;
				const { alignText } = attributes;

				function onChangeAlignText( alignText ) {
					setAttributes( {
						alignText: validateAlignment( alignText ),
					} );
				}

				return (
					<Fragment>
						<BlockControls group="block">
							<AlignmentToolbar value={ validateAlignment( alignText ) } onChange={ onChangeAlignText } />
						</BlockControls>
						<OriginalBlockEdit { ...this.props } />
					</Fragment>
				);
			}
		};
	}

	/**
	 * A higher order component adding full height support.
	 *
	 * @date	19/07/2021
	 * @since	ACF 5.10.0
	 *
	 * @param	component OriginalBlockEdit The original BlockEdit component.
	 * @param	object blockType The block type settings.
	 * @return	component
	 */
	function withFullHeightComponent( OriginalBlockEdit, blockType ) {
		if ( ! BlockFullHeightAlignmentControl ) return OriginalBlockEdit;

		// Return wrapped component.
		return class WrappedBlockEdit extends Component {
			render() {
				const { attributes, setAttributes } = this.props;
				const { fullHeight } = attributes;

				function onToggleFullHeight( fullHeight ) {
					setAttributes( {
						fullHeight,
					} );
				}

				return (
					<Fragment>
						<BlockControls group="block">
							<BlockFullHeightAlignmentControl isActive={ fullHeight } onToggle={ onToggleFullHeight } />
						</BlockControls>
						<OriginalBlockEdit { ...this.props } />
					</Fragment>
				);
			}
		};
	}

	/**
	 * Appends a backwards compatibility attribute for conversion.
	 *
	 * @since	ACF 6.0
	 *
	 * @param	object attributes The block type attributes.
	 * @return	object
	 */
	function addBackCompatAttribute( attributes, new_attribute, type ) {
		attributes[ new_attribute ] = {
			type: type,
		};
		return attributes;
	}

	/**
	 * Create a block hash from attributes
	 *
	 * @since ACF 6.0
	 *
	 * @param object attributes The block type attributes.
	 * @param object context The current block context object.
	 * @return string
	 */
	function createBlockAttributesHash( attributes, context ) {
		attributes[ '_acf_context' ] = sortObjectByKey( context );
		return md5( JSON.stringify( sortObjectByKey( attributes ) ) );
	}

	/**
	 * Key sort an object
	 *
	 * @since ACF 6.3.1
	 *
	 * @param object toSort The object to be sorted
	 * @return object
	 */
	function sortObjectByKey( toSort ) {
		return Object.keys( toSort )
			.sort()
			.reduce( ( acc, currValue ) => {
				acc[ currValue ] = toSort[ currValue ];
				return acc;
			}, {} );
	}
} )( jQuery );
