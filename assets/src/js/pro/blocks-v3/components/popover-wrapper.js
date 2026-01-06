/**
 * PopoverWrapper Component
 * Custom Popover wrapper that handles inline editing toolbar behavior
 * - Escape key to close
 * - Click outside to close
 * - Focus management
 * - Hiding primary block toolbar when active
 */

import { useEffect } from '@wordpress/element';
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
	useEffect( () => {
		// Get the appropriate document (iframe or regular document)
		const doc = gutenbergIframeOrDocument?.contentDocument
			? gutenbergIframeOrDocument.contentDocument
			: gutenbergIframeOrDocument || document;

		/**
		 * Handle escape key to close popover
		 */
		const handleEscapeKey = ( event ) => {
			if ( event.key === 'Escape' ) {
				onClose?.( event );
			}
		};

		/**
		 * Handle click outside popover to close it
		 */
		const handleClickOutside = ( event ) => {
			// Check if click is outside the popover
			const popoverElement = event.target.closest(
				'.' + className.split( ' ' ).join( '.' )
			);
			if ( ! popoverElement ) {
				onClose?.( event );
			}
		};

		// Add event listeners
		doc.addEventListener( 'keydown', handleEscapeKey, true );
		doc.addEventListener( 'mousedown', handleClickOutside, true );

		// Hide primary block toolbar if requested
		if ( hidePrimaryBlockToolbar ) {
			const toolbar = doc.querySelector(
				'.block-editor-block-list__block.is-selected > .block-editor-block-contextual-toolbar'
			);
			if ( toolbar ) {
				toolbar.style.display = 'none';
			}
		}

		// Cleanup
		return () => {
			doc.removeEventListener( 'keydown', handleEscapeKey, true );
			doc.removeEventListener( 'mousedown', handleClickOutside, true );

			// Restore primary block toolbar
			if ( hidePrimaryBlockToolbar ) {
				const toolbar = doc.querySelector(
					'.block-editor-block-list__block.is-selected > .block-editor-block-contextual-toolbar'
				);
				if ( toolbar ) {
					toolbar.style.display = '';
				}
			}
		};
	}, [
		gutenbergIframeOrDocument,
		onClose,
		className,
		hidePrimaryBlockToolbar,
	] );

	return (
		<Popover
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
