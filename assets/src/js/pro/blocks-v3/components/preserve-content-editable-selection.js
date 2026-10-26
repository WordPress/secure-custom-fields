/**
 * PreserveContentEditableSelection Component
 * Keeps the caret of an inline text editing element in place while the
 * block preview re-renders.
 *
 * The browser edits the text of a contentEditable element directly, so React
 * still holds the text of the previous preview. When the next preview comes
 * back from the server, React writes the new text into the text node, and
 * writing a text node moves any selection inside it to offset 0 (DOM "replace
 * data"), even when the text is unchanged. The caret then jumps to the start
 * of the element and the next keystrokes land there.
 *
 * This component reads the selection as character offsets before React
 * updates the DOM and puts it back afterwards.
 */

/* global NodeFilter */

import { Component } from '@wordpress/element';

const CONTENT_EDITABLE_SELECTOR = '[data-acf-inline-contenteditable]';

/**
 * Returns the character offset of a DOM position within an element.
 *
 * @param {Element} element The contentEditable element.
 * @param {Node}    node    The node of the position.
 * @param {number}  offset  The offset of the position within the node.
 * @return {number} The number of characters before the position.
 */
function getCharacterOffset( element, node, offset ) {
	const range = element.ownerDocument.createRange();
	range.setStart( element, 0 );
	range.setEnd( node, offset );
	return range.toString().length;
}

/**
 * Returns the DOM position of a character offset within an element.
 * An offset past the end of the text resolves to the end of the text.
 *
 * @param {Element} element         The contentEditable element.
 * @param {number}  characterOffset The number of characters before the position.
 * @return {Object} The position, as { node, offset }.
 */
function getDomPosition( element, characterOffset ) {
	const walker = element.ownerDocument.createTreeWalker(
		element,
		NodeFilter.SHOW_TEXT
	);
	let remaining = characterOffset;
	let lastTextNode = null;

	while ( walker.nextNode() ) {
		lastTextNode = walker.currentNode;
		if ( remaining <= lastTextNode.length ) {
			return { node: lastTextNode, offset: remaining };
		}
		remaining -= lastTextNode.length;
	}

	if ( lastTextNode ) {
		return { node: lastTextNode, offset: lastTextNode.length };
	}

	return { node: element, offset: 0 };
}

/**
 * Returns the selection in the focused contentEditable element of a block.
 *
 * @param {Element} container The block element.
 * @return {Object|null} The element and the selection's anchor and focus as
 *                       character offsets, or null if no contentEditable
 *                       element of the block has the selection.
 */
function getContentEditableSelection( container ) {
	if ( ! container ) {
		return null;
	}

	const element = container.ownerDocument.activeElement;
	if (
		! element ||
		! element.matches( CONTENT_EDITABLE_SELECTOR ) ||
		! container.contains( element )
	) {
		return null;
	}

	const selection = container.ownerDocument.getSelection();
	if (
		! selection ||
		! selection.rangeCount ||
		! element.contains( selection.anchorNode ) ||
		! element.contains( selection.focusNode )
	) {
		return null;
	}

	return {
		element,
		anchor: getCharacterOffset(
			element,
			selection.anchorNode,
			selection.anchorOffset
		),
		focus: getCharacterOffset(
			element,
			selection.focusNode,
			selection.focusOffset
		),
	};
}

/**
 * Wraps the block preview. A class component, because
 * getSnapshotBeforeUpdate() is the only place to read the DOM after rendering
 * but before React updates it.
 *
 * Props:
 * - containerRef: ref to the block element, so only the selection of this
 *   block's own inline editable elements is kept.
 */
export class PreserveContentEditableSelection extends Component {
	getSnapshotBeforeUpdate() {
		return getContentEditableSelection( this.props.containerRef?.current );
	}

	componentDidUpdate( prevProps, prevState, snapshot ) {
		if ( ! snapshot ) {
			return;
		}

		const { element, anchor, focus } = snapshot;
		const { ownerDocument } = element;

		// Leave the selection alone if the element was replaced or lost focus.
		if (
			! element.isConnected ||
			ownerDocument.activeElement !== element
		) {
			return;
		}

		const current = getContentEditableSelection(
			this.props.containerRef?.current
		);
		if ( current && current.anchor === anchor && current.focus === focus ) {
			return;
		}

		const anchorPosition = getDomPosition( element, anchor );
		const focusPosition = getDomPosition( element, focus );
		ownerDocument
			.getSelection()
			.setBaseAndExtent(
				anchorPosition.node,
				anchorPosition.offset,
				focusPosition.node,
				focusPosition.offset
			);
	}

	render() {
		return this.props.children;
	}
}
