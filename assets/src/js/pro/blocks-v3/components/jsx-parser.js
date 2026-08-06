/**
 * JSX Parser for ACF Blocks - Based on 6.7.0.2
 * Converts HTML strings to React/JSX elements for rendering in the block editor
 */

/* global acf, React, Text */

// eslint-disable-next-line import/no-unresolved -- WordPress provides jquery as an external script dependency.
import jQuery from 'jquery';
import { createElement, createRef } from '@wordpress/element';

const useInnerBlocksProps =
	wp.blockEditor.__experimentalUseInnerBlocksProps ||
	wp.blockEditor.useInnerBlocksProps;

/**
 * Gets the JSX-compatible name for an HTML attribute
 *
 * @param {string} attrName Attribute name.
 * @return {string} JSX-compatible attribute name.
 */
function getJSXNameReplacement( attrName ) {
	return acf.isget( acf, 'jsxNameReplacements', attrName ) || attrName;
}

/**
 * ACF InnerBlocks wrapper component
 *
 * @param {Object} props Component props.
 * @return {JSX.Element} InnerBlocks wrapper element.
 */
function ACFInnerBlocksComponent( props ) {
	const { className = 'acf-innerblocks-container' } = props;
	const innerBlocksProps = useInnerBlocksProps( { className }, props );
	return createElement( 'div', {
		...innerBlocksProps,
		children: innerBlocksProps.children,
	} );
}

/**
 * Script component for handling <script> tags
 */
class ScriptComponent extends React.Component {
	render() {
		return createElement( 'div', {
			ref: ( element ) => ( this.el = element ),
		} );
	}

	setHTML( scriptContent ) {
		jQuery( this.el ).html( `<script>${ scriptContent }</script>` );
	}

	componentDidUpdate() {
		this.setHTML( this.props.children );
	}

	componentDidMount() {
		this.setHTML( this.props.children );
	}
}

/**
 * Helper function to parse style attribute string into object
 *
 * @param {string} styleString Style attribute value.
 * @return {Object} Parsed style object.
 */
function parseStyleAttribute( styleString ) {
	const styleObj = {};
	if ( ! styleString ) {
		return styleObj;
	}

	styleString.split( ';' ).forEach( ( rule ) => {
		const colonIndex = rule.indexOf( ':' );
		if ( colonIndex > 0 ) {
			let property = rule.substr( 0, colonIndex ).trim();
			const value = rule.substr( colonIndex + 1 ).trim();

			// Convert to camelCase
			if ( property.charAt( 0 ) !== '-' ) {
				property = acf.strCamelCase( property );
			}
			styleObj[ property ] = value;
		}
	} );

	return styleObj;
}

/**
 * Parses and transforms a DOM attribute to React props format
 * Based on 6.7.0.2's implementation
 *
 * @param {Attr} attribute DOM attribute.
 * @return {Object} React prop name and value.
 */
function parseAttribute( attribute ) {
	let attrName = attribute.name;
	let attrValue = attribute.value;

	// Allow custom filtering
	const customParsed = acf.applyFilters(
		'acf_blocks_parse_node_attr',
		false,
		attribute
	);
	if ( customParsed ) {
		return customParsed;
	}

	switch ( attrName ) {
		case 'class':
			attrName = 'className';
			break;
		case 'style':
			const styleObj = {};
			attrValue.split( ';' ).forEach( ( rule ) => {
				const colonIndex = rule.indexOf( ':' );
				if ( colonIndex > 0 ) {
					let property = rule.substr( 0, colonIndex ).trim();
					const value = rule.substr( colonIndex + 1 ).trim();
					if ( property.charAt( 0 ) !== '-' ) {
						property = acf.strCamelCase( property );
					}
					styleObj[ property ] = value;
				}
			} );
			attrValue = styleObj;
			break;
		default:
			// Skip data- attributes processing, keep them as-is
			if ( attrName.indexOf( 'data-' ) === 0 ) {
				break;
			}

			attrName = getJSXNameReplacement( attrName );
			// Ignore values that merely start with a JSON-like character but
			// aren't valid JSON (e.g. an oEmbed title beginning with "[" or "{").
			const firstChar = attrValue.charAt( 0 );
			if ( firstChar === '[' || firstChar === '{' ) {
				try {
					attrValue = JSON.parse( attrValue );
				} catch ( err ) {}
			}
			if ( attrValue === 'true' || attrValue === 'false' ) {
				attrValue = attrValue === 'true';
			}
	}

	return { name: attrName, value: attrValue };
}

/**
 * Main parseNodeToJSX function - Based on 6.7.0.2's function `O`
 * Recursively converts DOM nodes to React/JSX elements
 *
 * @param {Node}   node  DOM node.
 * @param {number} depth Recursion depth.
 * @return {JSX.Element|null} Parsed JSX element.
 */
function parseNodeToJSX( node, depth = 0, callbacks = {} ) {
	const {
		onNewInlineEditingElementSelected,
		onNewContentEditableElementSelected,
		blockFieldInfo,
	} = callbacks;

	const selectInlineEditingElement = ( uid, element = null ) => {
		if ( onNewInlineEditingElementSelected ) {
			onNewInlineEditingElementSelected( uid );
			return;
		}

		if ( acf.blockEdit?.setCurrentInlineEditingElementUid ) {
			acf.blockEdit.setCurrentInlineEditingElementUid( uid );
		}

		if ( acf.blockEdit?.setCurrentInlineEditingElement ) {
			acf.blockEdit.setCurrentInlineEditingElement( element );
		}
	};

	const selectContentEditableElement = ( fieldSlug, element = null ) => {
		if ( onNewContentEditableElementSelected ) {
			onNewContentEditableElementSelected( fieldSlug );
			return;
		}

		if ( acf.blockEdit?.setCurrentContentEditableElement ) {
			acf.blockEdit.setCurrentContentEditableElement( element );
		}
	};

	// Determine component type
	const nodeName = node.nodeName.toLowerCase();
	let componentType;
	switch ( nodeName ) {
		case 'innerblocks':
			componentType = 'ACFInnerBlocks';
			break;
		case 'script':
			componentType = ScriptComponent;
			break;
		case '#comment':
			return null;
		default:
			componentType = getJSXNameReplacement( nodeName );
	}

	if ( ! componentType ) {
		return null;
	}

	const props = {};

	// Add ref to first-level elements (except ACFInnerBlocks)
	if ( depth === 1 && componentType !== 'ACFInnerBlocks' ) {
		props.ref = createRef();
	}

	// Parse attributes
	acf.arrayArgs( node.attributes )
		.map( parseAttribute )
		.forEach( ( { name, value } ) => {
			props[ name ] = value;
		} );

	// Handle data-acf-inline-fields attributes
	if ( node.hasAttribute( 'data-acf-inline-fields' ) ) {
		props.style = {
			...parseStyleAttribute( node.getAttribute( 'style' ) || '' ),
			pointerEvents: 'all',
		};
		props.role = 'button';
		props.tabIndex = 0;

		props.onFocus = ( event ) => {
			event.stopPropagation();
			const uid = node.attributes.getNamedItem(
				'data-acf-inline-fields-uid'
			).value;
			selectInlineEditingElement( uid, event.currentTarget );
		};

		props.onMouseDown = ( event ) => event.stopPropagation();

		props.onClick = ( event ) => {
			event.stopPropagation();
			const link = event.target.closest( 'a' );
			if ( link && link.tagName === 'A' ) {
				event.preventDefault();
				acf.debug( `Navigation prevented for ${ link.href }` );
			}
			if (
				! event.target.hasAttribute( 'data-acf-inline-contenteditable' )
			) {
				const uid = node.attributes.getNamedItem(
					'data-acf-inline-fields-uid'
				).value;
				selectInlineEditingElement( uid, event.currentTarget );
			}
		};

		props.onKeyDown = ( event ) => {
			if ( event.key === 'Tab' && event.shiftKey ) {
				event.preventDefault();
				const toolbar = document.querySelector(
					'.acf-inline-editing-toolbar'
				);
				const button = toolbar?.querySelector( 'button' );
				if ( button ) {
					button.focus();
					const uid = node.attributes.getNamedItem(
						'data-acf-inline-fields-uid'
					).value;
					selectInlineEditingElement( uid, event.currentTarget );
				}
			}
			if ( event.key === 'Enter' ) {
				event.stopPropagation();
				const link = event.target.closest( 'a' );
				if ( link && link.tagName === 'A' ) {
					event.preventDefault();
					acf.debug( `Navigation prevented for ${ link.href }` );
				}
				const uid = node.attributes.getNamedItem(
					'data-acf-inline-fields-uid'
				).value;
				selectInlineEditingElement( uid, event.currentTarget );
			}
		};
	}

	// Handle data-acf-inline-contenteditable attributes
	if ( node.hasAttribute( 'data-acf-inline-contenteditable' ) ) {
		const fieldSlug = node.attributes.getNamedItem(
			'data-acf-inline-contenteditable-field-slug'
		).value;

		const fieldInfo =
			blockFieldInfo || acf.blockEdit?.getBlockFieldInfo?.();
		const editableFields = fieldInfo
			? fieldInfo.filter(
					( field ) =>
						field.name === fieldSlug &&
						( field.type === 'text' || field.type === 'textarea' )
			  )
			: [];

		if ( editableFields.length > 0 ) {
			props.contentEditable = true;
			props.suppressContentEditableWarning = true;
			props.role = 'input';
			props.tabIndex = 0;

			props.onFocus = ( event ) => {
				const link = event.target.closest( 'a' );
				if ( link && link.tagName === 'A' ) {
					event.preventDefault();
					acf.debug( `Navigation prevented for ${ link.href }` );
				}
				event.stopPropagation();
				selectContentEditableElement( fieldSlug, event.currentTarget );
				if ( node.hasAttribute( 'data-acf-inline-fields' ) ) {
					const uid = node.attributes.getNamedItem(
						'data-acf-inline-fields-uid'
					).value;
					selectInlineEditingElement( uid, event.currentTarget );
				} else {
					selectInlineEditingElement( null );
				}
			};

			props.onPaste = ( event ) => {
				event.preventDefault();
				const text = event.clipboardData.getData( 'text/plain' );
				event.currentTarget.textContent =
					event.currentTarget.textContent + text;
			};
		} else {
			// Remove invalid contentEditable attributes
			delete props[ 'data-acf-inline-contenteditable-field-slug' ];
			delete props[ 'data-acf-inline-contenteditable' ];
		}
	}

	// Add click handler to clear selection if clicking outside inline fields
	if (
		! node.hasAttribute( 'data-acf-inline-fields' ) &&
		! node.hasAttribute( 'data-acf-inline-contenteditable' )
	) {
		props.onClick = ( event ) => {
			if ( event.target === event.currentTarget ) {
				selectInlineEditingElement( null );
				selectContentEditableElement( null );
			}
		};
	}

	// Handle ACFInnerBlocks component
	if ( componentType === 'ACFInnerBlocks' ) {
		return createElement( ACFInnerBlocksComponent, { ...props } );
	}

	// Build element with children
	const elementArgs = [ componentType, props ];

	acf.arrayArgs( node.childNodes ).forEach( ( childNode ) => {
		if ( childNode instanceof Text ) {
			const textContent = childNode.textContent;
			if ( textContent ) {
				elementArgs.push( textContent );
			}
		} else {
			elementArgs.push(
				parseNodeToJSX( childNode, depth + 1, callbacks )
			);
		}
	} );

	const element = createElement.apply( this, elementArgs );

	return element;
}

// Preserve the legacy parser for v1/v2 blocks, which pass the block version
// as the second argument.
const legacyParseJSX = acf.parseJSX;

/**
 * Main parseJSX function exposed on the acf global object
 * Matches 6.7.0.2's implementation exactly
 *
 * @param {string}   htmlString                         HTML string.
 * @param {Function} onNewInlineEditingElementSelected  Inline element selection callback or jQuery parser.
 * @param {Function} onContentEditableChange            ContentEditable change callback.
 * @param {Function} onNewContentEditableElementSelected ContentEditable selection callback.
 * @param {Array}    blockFieldInfo                     Field metadata.
 * @param {Function} $                                  jQuery-compatible parser.
 * @return {JSX.Element|Array} Parsed JSX children.
 */
export function parseJSX(
	htmlString,
	onNewInlineEditingElementSelected = jQuery,
	onContentEditableChange = null,
	onNewContentEditableElementSelected = null,
	blockFieldInfo = null,
	$ = null
) {
	if (
		typeof onNewInlineEditingElementSelected === 'number' &&
		typeof legacyParseJSX === 'function'
	) {
		return legacyParseJSX( htmlString, onNewInlineEditingElementSelected );
	}

	const isJQueryParser =
		typeof onNewInlineEditingElementSelected === 'function' &&
		( arguments.length === 2 ||
			( onNewInlineEditingElementSelected.fn &&
				onNewInlineEditingElementSelected.fn.jquery ) );
	const parser = isJQueryParser
		? onNewInlineEditingElementSelected
		: $ || jQuery;
	const callbacks = isJQueryParser
		? {}
		: {
				onNewInlineEditingElementSelected,
				onContentEditableChange,
				onNewContentEditableElementSelected,
				blockFieldInfo,
		  };

	// Wrap in div to ensure valid HTML structure
	htmlString = '<div>' + htmlString + '</div>';

	// Handle self-closing InnerBlocks tags
	htmlString = htmlString.replace(
		/<InnerBlocks([^>]+)?\/>/,
		'<InnerBlocks$1></InnerBlocks>'
	);

	// Parse with jQuery, convert to React, and extract children from wrapper div
	const parsedElement = parseNodeToJSX(
		parser( htmlString )[ 0 ],
		0,
		callbacks
	);
	return parsedElement.props.children;
}

acf.parseJSXV3 = parseJSX;
acf.parseJSX = parseJSX;
