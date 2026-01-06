/**
 * BlockPreview Component
 * Simple wrapper component that renders block preview HTML with block props
 */

/**
 * BlockPreview component
 * Wraps block preview content with the appropriate block props from useBlockProps
 *
 * @param {Object} props - Component props
 * @param {React.ReactNode} props.children - Child elements to render
 * @param {Object} props.blockProps - Block props from useBlockProps hook
 * @returns {JSX.Element} - Rendered preview wrapper
 */
export const BlockPreview = ( { children, blockProps } ) => (
	<div { ...blockProps }>{ children }</div>
);
