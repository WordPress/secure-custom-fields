/**
 * withAlignText Higher-Order Component
 * Adds text alignment toolbar controls to ACF blocks
 */

const { Fragment, Component } = wp.element;
const { BlockControls, AlignmentToolbar } = wp.blockEditor;

/**
 * Gets the default text alignment based on RTL setting
 *
 * @param {string} alignment - Current alignment value
 * @returns {string} - Normalized alignment value (left, center, or right)
 */
const getDefaultAlignment = ( alignment ) => {
	const defaultAlign = acf.get( 'rtl' ) ? 'right' : 'left';
	return [ 'left', 'center', 'right' ].includes( alignment )
		? alignment
		: defaultAlign;
};

/**
 * Higher-order component that adds text alignment controls
 * Wraps a block component and adds AlignmentToolbar to BlockControls
 *
 * @param {React.Component} BlockComponent - The component to wrap
 * @param {Object} blockConfig - ACF block configuration
 * @returns {React.Component} - Enhanced component with text alignment controls
 */
export const withAlignText = ( BlockComponent, blockConfig ) => {
	const normalizeAlignment = getDefaultAlignment;

	// Set default alignment on block config
	blockConfig.alignText = normalizeAlignment( blockConfig.alignText );

	return class extends Component {
		render() {
			const { attributes, setAttributes } = this.props;
			const { alignText } = attributes;

			return (
				<Fragment>
					<BlockControls group="block">
						<AlignmentToolbar
							value={ normalizeAlignment( alignText ) }
							onChange={ function ( newAlignment ) {
								setAttributes( {
									alignText:
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
