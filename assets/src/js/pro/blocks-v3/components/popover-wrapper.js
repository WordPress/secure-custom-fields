/**
 * PopoverWrapper Component
 * Custom Popover wrapper that handles inline editing toolbar behavior
 * - Escape key to close
 * - Click outside to close
 * - Focus management
 * - Hiding primary block toolbar when active
 */

import { useEffect, useRef } from '@wordpress/element';
import { Popover } from '@wordpress/components';

/**
 * PopoverWrapper component
 * Wraps the WordPress Popover component with custom event handlers
 *
 * @param {Object} props - Component props
 * @param {React.ReactNode} props.children - Child elements to render inside popover
 * @param {string} props.className - CSS class for the popover
 * @param {Element|null} props.anchor - Anchor element for positioning
 * @param {string} props.placement - Placement relative to anchor (e.g., 'top-start')
 * @param {Function} props.onClose - Callback when popover should close
 * @param {boolean|string} props.focusOnMount - Whether to focus on mount
 * @param {string} props.variant - Popover variant (e.g., 'unstyled')
 * @param {boolean} props.animate - Whether to animate popover
 * @param {Document|HTMLIFrameElement} props.gutenbergIframeOrDocument - Document or iframe reference
 * @param {boolean} props.hidePrimaryBlockToolbar - Whether to hide the primary block toolbar
 * @returns {JSX.Element} - Wrapped Popover component
 */
export const PopoverWrapper = ( {
	children,
	className,
	anchor,
	placement,
	onClose,
	focusOnMount,
	variant,
	animate = false,
	gutenbergIframeOrDocument,
	hidePrimaryBlockToolbar = false,
} ) => {
	const popoverRef = useRef();

	useEffect( () => {
		const doc = gutenbergIframeOrDocument?.contentDocument
			? gutenbergIframeOrDocument.contentDocument
			: gutenbergIframeOrDocument || document;

		const handleEscapeKey = ( event ) => {
			if ( event.key === 'Escape' ) {
				onClose?.( event );
			}
		};

		const handleClickOutside = ( event ) => {
			if (
				! anchor ||
				event?.key ||
				popoverRef?.current?.contains( event.target ) ||
				event.target === anchor
			) {
				return;
			}

			if ( onClose?.( event ) ) {
				doc.removeEventListener( 'mousedown', handleClickOutside );
				document.removeEventListener( 'mousedown', handleClickOutside );
			}
		};

		setTimeout( () => {
			document.addEventListener( 'keydown', handleEscapeKey );
			doc.addEventListener( 'mousedown', handleClickOutside );
			document.addEventListener( 'mousedown', handleClickOutside );
		} );

		return () => {
			document.removeEventListener( 'keydown', handleEscapeKey );
			doc.removeEventListener( 'mousedown', handleClickOutside );
			document.removeEventListener( 'mousedown', handleClickOutside );
		};
	}, [] );

	return (
		<Popover
			ref={ popoverRef }
			style={ { position: 'absolute', top: 200, zIndex: 59899 } }
			focusOnMount={ focusOnMount }
			variant={ variant }
			anchor={ anchor }
			className={ className }
			placement={ placement }
			animate={ animate }
		>
			{ hidePrimaryBlockToolbar && (
				<style>
					{ `
					.components-popover.block-editor-block-popover.block-editor-block-list__block-popover{
						display: none!important;
					}
				` }
				</style>
			) }
			{ children }
		</Popover>
	);
};
