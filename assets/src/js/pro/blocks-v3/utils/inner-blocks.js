/**
 * InnerBlocks helpers for SCF blocks.
 *
 * Shared by the v1/v2 block parser (_acf-blocks.js) and the v3 JSX parser.
 */

/* global acf */

/**
 * Applies the block type's `default_block` and `direct_insert` settings to
 * InnerBlocks props, unless the template already set them.
 *
 * @since SCF 6.9.10
 *
 * @param {Object}       props     InnerBlocks props parsed from the template.
 * @param {Object|false} blockType The registered block type, if known.
 * @return {Object} The props.
 */
export function applyInnerBlocksDefaults( props, blockType ) {
	if ( ! blockType ) {
		return props;
	}

	if (
		props.defaultBlock === undefined &&
		blockType.default_block &&
		Object.keys( blockType.default_block ).length
	) {
		props.defaultBlock = blockType.default_block;
	}

	if ( props.directInsert === undefined && blockType.direct_insert ) {
		props.directInsert = blockType.direct_insert;
	}

	return props;
}

/**
 * Returns true on WordPress 6.2 and earlier, where InnerBlocks only reads the
 * experimental `__experimentalDefaultBlock` and `__experimentalDirectInsert` props.
 *
 * @return {boolean} Whether the experimental prop names are needed.
 */
function needsExperimentalInnerBlocksProps() {
	const wpVersion = acf.get( 'wp_version' );
	if ( ! wpVersion ) {
		return false;
	}

	const [ major, minor ] = String( wpVersion ).split( '.' ).map( Number );
	return major < 6 || ( major === 6 && minor <= 2 );
}

/**
 * Returns InnerBlocks settings, adding the experimental prop names on
 * WordPress versions that need them.
 *
 * @since SCF 6.9.10
 *
 * @param {Object} props InnerBlocks props.
 * @return {Object} InnerBlocks settings.
 */
export function getInnerBlocksSettings( props ) {
	if ( ! needsExperimentalInnerBlocksProps() ) {
		return props;
	}

	return {
		...props,
		__experimentalDefaultBlock: props.defaultBlock,
		__experimentalDirectInsert: props.directInsert,
	};
}
