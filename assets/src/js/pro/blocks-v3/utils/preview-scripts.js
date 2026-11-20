/**
 * Preview script guard for SCF blocks.
 *
 * Block preview HTML can contain field values entered by other, possibly
 * lower-privileged, users, so `<script>` tags in it don't run in the block
 * editor unless the block type or the site opts in.
 *
 * Shared by the v1/v2 block parser (_acf-blocks.js) and the v3 JSX parser.
 */

/* global acf, jQuery */

// Script types the browser (and jQuery's DOM manipulation) will execute.
const EXECUTABLE_SCRIPT_TYPES = /^$|^module$|\/(?:java|ecma)script/i;

// Block names that already logged a warning, so each block warns only once.
const warnedBlocks = new Set();

/**
 * Returns true if preview scripts may run for the given block type.
 *
 * @since SCF 6.9.10
 *
 * @param {Object|false} blockType The registered block type, if known.
 * @return {boolean} Whether preview scripts may run.
 */
export function previewScriptsAllowed( blockType ) {
	return !! (
		acf.get( 'allowDangerousPreviewScriptsInBlocks' ) ||
		( blockType && blockType.allow_dangerous_preview_scripts )
	);
}

/**
 * Returns true if the given script element would be executed by the browser.
 *
 * @since SCF 6.9.10
 *
 * @param {Element} script The script element.
 * @return {boolean} Whether the script is executable.
 */
export function isExecutableScript( script ) {
	const type = script.getAttribute( 'type' ) || '';
	return EXECUTABLE_SCRIPT_TYPES.test( type.trim() );
}

/**
 * Logs a console warning, once per block, that a preview script was not run.
 *
 * @since SCF 6.9.10
 *
 * @param {string} blockName The block name.
 */
function warnPreviewScriptBlocked( blockName ) {
	const name = blockName || 'unknown';
	if ( warnedBlocks.has( name ) ) {
		return;
	}
	warnedBlocks.add( name );

	// eslint-disable-next-line no-console
	console.warn(
		`SCF: A <script> in the preview of the "${ name }" block was not run. Scripts in block preview HTML are disabled by default because it can contain content from other users. Load your script with the block instead ("script" or "editorScript" in block.json, or "enqueue_script" or "enqueue_assets" in acf_register_block_type()) and use the "render_block_preview" JS action. To allow scripts in the preview anyway, set "allowDangerousPreviewScripts" under the "acf" key in this block's block.json ("allow_dangerous_preview_scripts" in acf_register_block_type()), or use the "acf/blocks/allow_dangerous_preview_scripts" filter for all blocks.`
	);
}

/**
 * Returns false if a DOM node must not be rendered in the JSX preview.
 *
 * Any `<script>` node is dropped unless preview scripts are allowed. A warning
 * is logged only for scripts that would have run.
 *
 * @since SCF 6.9.10
 *
 * @param {Node}         node      The DOM node.
 * @param {Object|false} blockType The registered block type, if known.
 * @param {string}       blockName The block name, for the warning.
 * @return {boolean} Whether the node may be rendered.
 */
export function shouldRenderPreviewNode( node, blockType, blockName ) {
	if (
		node.nodeName.toLowerCase() !== 'script' ||
		previewScriptsAllowed( blockType )
	) {
		return true;
	}

	if ( isExecutableScript( node ) ) {
		warnPreviewScriptBlocked( blockName );
	}

	return false;
}

/**
 * Removes executable `<script>` elements from a jQuery collection, both top-level
 * and nested, unless preview scripts are allowed.
 *
 * @since SCF 6.9.10
 *
 * @param {jQuery}       $el       The parsed preview HTML.
 * @param {Object|false} blockType The registered block type, if known.
 * @param {string}       blockName The block name, for the warning.
 * @return {jQuery} The collection without executable scripts.
 */
export function removeExecutablePreviewScripts( $el, blockType, blockName ) {
	if ( previewScriptsAllowed( blockType ) ) {
		return $el;
	}

	const isExecutable = ( index, element ) => isExecutableScript( element );
	const $topLevel = $el.filter( 'script' ).filter( isExecutable );
	const $nested = $el.find( 'script' ).filter( isExecutable );

	if ( ! $topLevel.length && ! $nested.length ) {
		return $el;
	}

	warnPreviewScriptBlocked( blockName );
	$nested.remove();
	return $el.not( $topLevel );
}
