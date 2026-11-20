/**
 * BlockPlaceholder Component
 * Displays a placeholder UI when block has no preview HTML
 */

import { Placeholder, Button, Icon } from '@wordpress/components';
/**
 * SVG icon for the block placeholder
 * Represents a generic block/form icon
 */
const defaultBlockIcon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		width="24"
		height="24"
		aria-hidden="true"
		focusable="false"
	>
		<path d="M19 8h-1V6h-5v2h-2V6H6v2H5c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2zm.5 10c0 .3-.2.5-.5.5H5c-.3 0-.5-.2-.5-.5v-8c0-.3.2-.5.5-.5h14c.3 0 .5.2.5.5v8z" />
	</svg>
);

/**
 * BlockPlaceholder component
 * Shows when a block has no preview HTML available
 * Prompts user to open the block form to edit fields
 *
 * @param {Object} props - Component props
 * @param {Function} props.setBlockFormModalOpen - Function to open the block form modal
 * @param {string} props.blockLabel - The block's title/label
 * @param {Object|string} props.blockIcon - The block type's icon; string icons fall back to the default icon
 * @param {string} props.buttonText - Custom button text, HTML-escaped by the server
 * @param {string} props.instructions - Optional instructions, such as an error message
 * @returns {JSX.Element} - Rendered placeholder
 */
export const BlockPlaceholder = ( {
	setBlockFormModalOpen,
	blockLabel,
	blockIcon,
	buttonText,
	instructions,
} ) => {
	// Registered block icons may be wrapped in an object with a `src` key.
	let icon = blockIcon?.src || blockIcon;

	// String icons (dashicon names or SVG markup) aren't rendered here.
	if ( typeof icon === 'string' ) {
		icon = null;
	}

	return (
		<Placeholder
			icon={ <Icon icon={ icon || defaultBlockIcon } /> }
			label={ blockLabel }
			instructions={ instructions }
		>
			<Button
				variant="primary"
				onClick={ () => {
					setBlockFormModalOpen( true );
				} }
			>
				{ buttonText
					? acf.decode( buttonText )
					: acf.__( 'Edit Block' ) }
			</Button>
		</Placeholder>
	);
};
