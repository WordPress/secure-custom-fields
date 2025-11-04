/**
 * JSX Parser for ACF Blocks
 * Converts HTML strings to React/JSX elements for rendering in the block editor
 */

import jQuery from 'jquery';

const { createElement, createRef, Component } = wp.element;
const useInnerBlocksProps =
	wp.blockEditor.__experimentalUseInnerBlocksProps ||
	wp.blockEditor.useInnerBlocksProps;

/**
 * Gets the JSX-compatible name for an HTML attribute
 * Maps HTML attribute names to React prop names
 *
 * @param {string} attrName - HTML attribute name
 * @returns {string} - JSX/React prop name
 */
function getJSXNameReplacement( attrName ) {
	return acf.isget( acf, 'jsxNameReplacements', attrName ) || attrName;
}

/**
 * Script component for handling <script> tags in parsed content
 * Uses jQuery to safely inject and execute script content
 */
class ScriptComponent extends Component {
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
 * Gets the component type for a given node name
 * Handles special cases like InnerBlocks, script tags, and comments
 *
 * @param {string} nodeName - Lowercase node name
 * @returns {string|Function|null} - Component type or null
 */
function getComponentType( nodeName ) {
	switch ( nodeName ) {
		case 'innerblocks':
			return 'ACFInnerBlocks';
		case 'script':
			return ScriptComponent;
		case '#comment':
			return null;
		default:
			return getJSXNameReplacement( nodeName );
	}
}

/**
 * ACF InnerBlocks wrapper component
 * Provides a container for WordPress InnerBlocks with proper props
 *
 * @param {Object} props - Component props
 * @returns {JSX.Element} - Wrapped InnerBlocks component
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
 * Parses and transforms a DOM attribute to React props format
 * Handles special cases: class -> className, style string -> style object, JSON values, booleans
 *
 * @param {Attr} attribute - DOM attribute object with name and value
 * @returns {Object} - Transformed attribute {name, value}
 */
function parseAttribute( attribute ) {
	let attrName = attribute.name;
	let attrValue = attribute.value;

	// Allow custom filtering via ACF hooks
	const customParsed = acf.applyFilters(
		'acf_blocks_parse_node_attr',
		false,
		attribute
	);
	if ( customParsed ) return customParsed;

	switch ( attrName ) {
		case 'class':
			// Convert HTML class to React className
			attrName = 'className';
			break;

		case 'style':
			// Parse inline CSS string to JavaScript style object
			const styleObject = {};
			attrValue.split( ';' ).forEach( ( declaration ) => {
				const colonIndex = declaration.indexOf( ':' );
				if ( colonIndex > 0 ) {
					let property = declaration.substr( 0, colonIndex ).trim();
					const value = declaration.substr( colonIndex + 1 ).trim();

					// Convert kebab-case to camelCase (except CSS variables starting with -)
					if ( property.charAt( 0 ) !== '-' ) {
						property = acf.strCamelCase( property );
					}

					styleObject[ property ] = value;
				}
			} );
			attrValue = styleObject;
			break;

		default:
			// Preserve data- attributes as-is
			if ( attrName.indexOf( 'data-' ) === 0 ) break;

			// Apply JSX name transformations (e.g., onclick -> onClick)
			attrName = getJSXNameReplacement( attrName );

			// Parse JSON array/object values
			const firstChar = attrValue.charAt( 0 );
			if ( firstChar === '[' || firstChar === '{' ) {
				attrValue = JSON.parse( attrValue );
			}

			// Convert string booleans to actual booleans
			if ( attrValue === 'true' || attrValue === 'false' ) {
				attrValue = attrValue === 'true';
			}
	}

	return { name: attrName, value: attrValue };
}

/**
 * Recursively parses a DOM node and converts it to React/JSX elements
 *
 * @param {Node} node - The DOM node to parse
 * @param {number} depth - Current recursion depth (0-based)
 * @returns {JSX.Element|null} - React element or null if node should be skipped
 */
function parseNodeToJSX( node, depth = 0 ) {
	// Determine the component type for this node
	const componentType = getComponentType( node.nodeName.toLowerCase() );

	if ( ! componentType ) return null;

	const props = {};

	// Add ref to first-level elements (except ACFInnerBlocks)
	if ( depth === 1 && componentType !== 'ACFInnerBlocks' ) {
		props.ref = createRef();
	}

	// Parse all attributes and add to props
	acf.arrayArgs( node.attributes )
		.map( parseAttribute )
		.forEach( ( { name, value } ) => {
			props[ name ] = value;
		} );

	// Handle special ACFInnerBlocks component
	if ( componentType === 'ACFInnerBlocks' ) {
		return createElement( ACFInnerBlocksComponent, { ...props } );
	}

	// Build element array: [type, props, ...children]
	const elementArray = [ componentType, props ];

	// Recursively process child nodes
	acf.arrayArgs( node.childNodes ).forEach( ( childNode ) => {
		if ( childNode instanceof Text ) {
			const textContent = childNode.textContent;
			if ( textContent ) {
				elementArray.push( textContent );
			}
		} else {
			elementArray.push( parseNodeToJSX( childNode, depth + 1 ) );
		}
	} );

	// Create and return React element
	return createElement.apply( this, elementArray );
}

/**
 * Main parseJSX function exposed on the acf global object
 * Converts HTML string to React elements for use in ACF blocks
 *
 * @param {string} htmlString - HTML markup to parse
 * @returns {Array|JSX.Element} - React children from parsed HTML
 */
export function parseJSX( htmlString ) {
	// Wrap in div to ensure valid HTML structure
	htmlString = '<div>' + htmlString + '</div>';

	// Handle self-closing InnerBlocks tags (not valid HTML, but used in ACF)
	htmlString = htmlString.replace(
		/<InnerBlocks([^>]+)?\/>/,
		'<InnerBlocks$1></InnerBlocks>'
	);

	// Parse with jQuery, convert to React, and extract children from wrapper div
	const parsedElement = parseNodeToJSX( jQuery( htmlString )[ 0 ], 0 );
	return parsedElement.props.children;
}

// Expose parseJSX function on acf global object for backward compatibility
acf.parseJSX = parseJSX;
