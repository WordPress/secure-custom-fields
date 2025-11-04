/**
 * withFullHeight Higher-Order Component
 * Adds full height toggle control to ACF blocks
 */

const { Fragment, Component } = wp.element;
const { BlockControls } = wp.blockEditor;

// Full height control (experimental)
const BlockFullHeightAlignmentControl =
	wp.blockEditor.__experimentalBlockFullHeightAligmentControl ||
	wp.blockEditor.__experimentalBlockFullHeightAlignmentControl ||
	wp.blockEditor.BlockFullHeightAlignmentControl;

/**
 * Higher-order component that adds full height toggle controls
 * Allows blocks to expand to full available height
 *
 * @param {React.Component} BlockComponent - The component to wrap
 * @returns {React.Component} - Enhanced component with full height controls
 */
export const withFullHeight = ( BlockComponent ) => {
	// If control is not available, return original component
	if ( ! BlockFullHeightAlignmentControl ) {
		return BlockComponent;
	}

	return class extends Component {
		render() {
			const { attributes, setAttributes } = this.props;
			const { fullHeight } = attributes;

			return (
				<Fragment>
					<BlockControls group="block">
						<BlockFullHeightAlignmentControl
							isActive={ fullHeight }
							onToggle={ function ( newValue ) {
								setAttributes( { fullHeight: newValue } );
							} }
						/>
					</BlockControls>
					<BlockComponent { ...this.props } />
				</Fragment>
			);
		}
	};
};
