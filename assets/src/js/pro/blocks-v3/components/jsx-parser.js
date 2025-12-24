/**
 * JSX Parser for ACF Blocks - Based on 6.7.0.2
 * Converts HTML strings to React/JSX elements for rendering in the block editor
 */

import jQuery from 'jquery';
import { createElement, createRef } from '@wordpress/element';

const useInnerBlocksProps =
	wp.blockEditor.__experimentalUseInnerBlocksProps ||
	wp.blockEditor.useInnerBlocksProps;

/**
 * Gets the JSX-compatible name for an HTML attribute
 */
function getJSXNameReplacement( attrName ) {
	return acf.isget( acf, 'jsxNameReplacements', attrName ) || attrName;
}

/**
 * ACF InnerBlocks wrapper component
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
 */
function parseStyleAttribute( styleString ) {
	const styleObj = {};
	if ( ! styleString ) return styleObj;

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
	if ( customParsed ) return customParsed;

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
			if ( attrName.indexOf( 'data-' ) === 0 ) break;

			attrName = getJSXNameReplacement( attrName );
			const firstChar = attrValue.charAt( 0 );
			if ( firstChar === '[' || firstChar === '{' ) {
				attrValue = JSON.parse( attrValue );
			}
			if ( attrValue === 'true' || attrValue === 'false' ) {
				attrValue = attrValue === 'true';
			}
	}

	return { name: attrName, value: attrValue };
}

/**
 * Main parseNodeToJSX function - Based on 6.7.0.2's function `m`
 * This is the exact logic from 6.7.0.2
 */
function parseNodeToJSX(
	node,
	depth = 0,
	setCurrentInlineEditingElementUid = null,
	onContentEditableChange = null,
	setCurrentContentEditableElement = null,
	blockFieldInfo = null
) {
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

	if ( ! componentType ) return null;

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
			setCurrentInlineEditingElementUid &&
				setCurrentInlineEditingElementUid(
					node.attributes.getNamedItem( 'data-acf-inline-fields-uid' )
						.value
				);
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
				setCurrentInlineEditingElementUid &&
					setCurrentInlineEditingElementUid(
						node.attributes.getNamedItem(
							'data-acf-inline-fields-uid'
						).value
					);
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
					setCurrentInlineEditingElementUid &&
						setCurrentInlineEditingElementUid(
							node.attributes.getNamedItem(
								'data-acf-inline-fields-uid'
							).value
						);
				}
			}
			if ( event.key === 'Enter' ) {
				event.stopPropagation();
				const link = event.target.closest( 'a' );
				if ( link && link.tagName === 'A' ) {
					event.preventDefault();
					acf.debug( `Navigation prevented for ${ link.href }` );
				}
				setCurrentInlineEditingElementUid &&
					setCurrentInlineEditingElementUid(
						node.attributes.getNamedItem(
							'data-acf-inline-fields-uid'
						).value
					);
			}
		};
	}

	// Handle data-acf-inline-contenteditable attributes
	if ( node.hasAttribute( 'data-acf-inline-contenteditable' ) ) {
		const fieldSlug = node.attributes.getNamedItem(
			'data-acf-inline-contenteditable-field-slug'
		).value;

		const editableFields = blockFieldInfo
			? blockFieldInfo.filter(
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
			// Add key to give React stable identity for this element
			props.key = 'contenteditable-' + fieldSlug;

			props.onFocus = ( event ) => {
				const link = event.target.closest( 'a' );
				if ( link && link.tagName === 'A' ) {
					event.preventDefault();
					acf.debug( `Navigation prevented for ${ link.href }` );
				}
				event.stopPropagation();
				setCurrentContentEditableElement &&
					setCurrentContentEditableElement(
						node.attributes.getNamedItem(
							'data-acf-inline-contenteditable-field-slug'
						).value
					);
				if ( node.hasAttribute( 'data-acf-inline-fields' ) ) {
					setCurrentInlineEditingElementUid &&
						setCurrentInlineEditingElementUid(
							node.attributes.getNamedItem(
								'data-acf-inline-fields-uid'
							).value
						);
				} else {
					setCurrentInlineEditingElementUid &&
						setCurrentInlineEditingElementUid( null );
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
				setCurrentInlineEditingElementUid &&
					setCurrentInlineEditingElementUid( null );
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
				parseNodeToJSX(
					childNode,
					depth + 1,
					setCurrentInlineEditingElementUid,
					onContentEditableChange,
					setCurrentContentEditableElement,
					blockFieldInfo
				)
			);
		}
	} );

	const element = createElement.apply( this, elementArgs );

	// Debug logging for contentEditable elements
	if ( props.contentEditable ) {
		console.log( '🔵 Created contentEditable element:', {
			componentType,
			key: props.key,
			textContent: element?.props?.children,
		} );
	}

	return element;
}

/**
 * Main parseJSX function exposed on the acf global object
 * Wrapper function that matches 6.7.0.2's `h` function
 */
export function parseJSX(
	htmlString,
	setCurrentInlineEditingElementUid = null,
	onContentEditableChange = null,
	setCurrentContentEditableElement = null,
	blockFieldInfo = null,
	$ = jQuery
) {
	console.log( '🟣 parseJSX called, React version:', React?.version );

	// Wrap in div to ensure valid HTML structure
	htmlString = '<div>' + htmlString + '</div>';

	// Handle self-closing InnerBlocks tags
	htmlString = htmlString.replace(
		/<InnerBlocks([^>]+)?\/>/,
		'<InnerBlocks$1></InnerBlocks>'
	);

	// Parse with jQuery, convert to React, and extract children from wrapper div
	const parsedElement = parseNodeToJSX(
		$( htmlString )[ 0 ],
		0,
		setCurrentInlineEditingElementUid,
		onContentEditableChange,
		setCurrentContentEditableElement,
		blockFieldInfo
	);
	return parsedElement.props.children;
}

// Expose parseJSX function on acf global object for backward compatibility
acf.parseJSX = parseJSX;
