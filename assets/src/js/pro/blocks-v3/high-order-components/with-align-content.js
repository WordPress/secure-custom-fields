/**
 * withAlignContent Higher-Order Component
 * Adds content alignment toolbar controls to ACF blocks
 * Supports both vertical alignment and matrix alignment (horizontal + vertical)
 */

const { Fragment, Component } = wp.element;
const { BlockControls, BlockVerticalAlignmentToolbar } = wp.blockEditor;

// Matrix alignment control (experimental)
const BlockAlignmentMatrixControl =
	wp.blockEditor.__experimentalBlockAlignmentMatrixControl ||
	wp.blockEditor.BlockAlignmentMatrixControl;

const BlockAlignmentMatrixToolbar =
	wp.blockEditor.__experimentalBlockAlignmentMatrixToolbar ||
	wp.blockEditor.BlockAlignmentMatrixToolbar;

/**
 * Normalizes vertical alignment value
 *
 * @param {string} alignment - Alignment value
 * @returns {string} - Normalized alignment (top, center, or bottom)
 */
const normalizeVerticalAlignment = ( alignment ) => {
	return [ 'top', 'center', 'bottom' ].includes( alignment )
		? alignment
		: 'top';
};

/**
 * Gets the default horizontal alignment based on RTL setting
 *
 * @param {string} alignment - Current alignment value
 * @returns {string} - Normalized alignment value (left, center, or right)
 */
const getDefaultHorizontalAlignment = ( alignment ) => {
	const defaultAlign = acf.get( 'rtl' ) ? 'right' : 'left';
	return [ 'left', 'center', 'right' ].includes( alignment )
		? alignment
		: defaultAlign;
};

/**
 * Normalizes matrix alignment value (vertical + horizontal)
 * Format: "top left", "center center", etc.
 *
 * @param {string} alignment - Alignment value
 * @returns {string} - Normalized matrix alignment
 */
const normalizeMatrixAlignment = ( alignment ) => {
	if ( alignment ) {
		const [ vertical, horizontal ] = alignment.split( ' ' );
		return `${ normalizeVerticalAlignment(
			vertical
		) } ${ getDefaultHorizontalAlignment( horizontal ) }`;
	}
	return 'center center';
};

/**
 * Higher-order component that adds content alignment controls
 * Supports either vertical-only or matrix (2D) alignment based on block config
 *
 * @param {React.Component} BlockComponent - The component to wrap
 * @param {Object} blockConfig - ACF block configuration
 * @returns {React.Component} - Enhanced component with content alignment controls
 */
export const withAlignContent = ( BlockComponent, blockConfig ) => {
	let AlignmentControl;
	let normalizeAlignment;

	// Determine which alignment control to use based on block supports
	if (
		blockConfig.supports.align_content === 'matrix' ||
		blockConfig.supports.alignContent === 'matrix'
	) {
		// Use matrix control (horizontal + vertical)
		AlignmentControl =
			BlockAlignmentMatrixControl || BlockAlignmentMatrixToolbar;
		normalizeAlignment = normalizeMatrixAlignment;
	} else {
		// Use vertical-only control
		AlignmentControl = BlockVerticalAlignmentToolbar;
		normalizeAlignment = normalizeVerticalAlignment;
	}

	// If alignment control is not available, return original component
	if ( AlignmentControl === undefined ) {
		return BlockComponent;
	}

	// Set default alignment on block config
	blockConfig.alignContent = normalizeAlignment( blockConfig.alignContent );

	return class extends Component {
		render() {
			const { attributes, setAttributes } = this.props;
			const { alignContent } = attributes;

			return (
				<Fragment>
					<BlockControls group="block">
						<AlignmentControl
							label={ acf.__( 'Change content alignment' ) }
							value={ normalizeAlignment( alignContent ) }
							onChange={ function ( newAlignment ) {
								setAttributes( {
									alignContent:
										normalizeAlignment( newAlignment ),
								} );
							} }
						/>
					</BlockControls>
					<BlockComponent { ...this.props } />
				</Fragment>
			);
		}
	};
};
