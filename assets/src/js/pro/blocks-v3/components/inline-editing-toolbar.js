/**
 * InlineEditingToolbar Component
 * Main inline editing toolbar for ACF blocks
 * Handles field selection and editing for inline editable elements
 */

import { useState, useEffect, useMemo, createPortal } from '@wordpress/element';
import { Button, Modal } from '@wordpress/components';
import { PopoverWrapper } from './popover-wrapper';
import { BlockForm } from './block-form';

/**
 * InlineEditingToolbar component
 * Displays a toolbar with field buttons for inline editing
 *
 * @param {Object} props - Component props
 * @param {Object} props.blockIcon - Block icon configuration
 * @param {Array} props.blockFieldInfo - Array of field information
 * @param {React.RefObject} props.acfFormRef - Reference to ACF form
 * @param {Function} props.setInlineEditingToolbarHasFocus - Setter for toolbar focus state
 * @param {Element|null} props.currentContentEditableElement - Current content editable element
 * @param {Element|null} props.currentInlineEditingElement - Current inline editing element
 * @param {string|null} props.currentInlineEditingElementUid - Current element UID
 * @param {Document|HTMLIFrameElement} props.gutenbergIframeOrDocument - Document or iframe reference
 * @param {Function} props.setCurrentBlockFormContainer - Setter for form container
 * @param {boolean} props.contentEditableChangeInProgress - Whether content change is in progress
 * @returns {JSX.Element|null} - Rendered toolbar or null
 */
export const InlineEditingToolbar = ( {
	blockIcon,
	blockFieldInfo,
	acfFormRef,
	setInlineEditingToolbarHasFocus,
	currentContentEditableElement,
	currentInlineEditingElement,
	currentInlineEditingElementUid,
	gutenbergIframeOrDocument,
	setCurrentBlockFormContainer,
	contentEditableChangeInProgress,
} ) => {
	const [ isFieldPopoverOpen, setIsFieldPopoverOpen ] = useState( false );
	const [ isFieldModalOpen, setIsFieldModalOpen ] = useState( false );
	const [ selectedFieldKey, setSelectedFieldKey ] = useState( null );
	const [ popoverAnchor, setPopoverAnchor ] = useState( null );
	const fieldPopoverContainerRef = useState( null );

	// Parse inline fields from data attribute
	const inlineFieldsAttr = currentInlineEditingElement
		? currentInlineEditingElement.getAttribute( 'data-acf-inline-fields' )
		: null;
	let inlineFields = [];
	try {
		inlineFields = JSON.parse( inlineFieldsAttr || '[]' );
	} catch ( e ) {
		acf.debug(
			'Inline fields were not a properly formatted JSON array',
			inlineFieldsAttr
		);
	}

	/**
	 * Get field type by field name
	 */
	const getFieldType = ( fieldName ) => {
		const field = blockFieldInfo.find( ( f ) => f.name === fieldName );
		return field?.type || null;
	};

	/**
	 * Get field info by field name
	 */
	const getFieldInfo = ( fieldName ) => {
		return blockFieldInfo.find( ( f ) => f.name === fieldName ) || null;
	};

	/**
	 * Get field label by field name
	 */
	const getFieldLabel = ( fieldName ) => {
		const field = getFieldInfo( fieldName );
		return field?.label || fieldName;
	};

	/**
	 * Check if field type requires modal
	 */
	const isComplexFieldType = ( fieldType ) => {
		return [ 'flexible_content', 'repeater', 'group' ].includes(
			fieldType
		);
	};

	/**
	 * Get toolbar icon from data attribute or field or default
	 */
	const toolbarIcon = useMemo( () => {
		// Check for custom toolbar icon in data attribute
		const customIcon = currentInlineEditingElement?.getAttribute(
			'data-acf-toolbar-icon'
		);
		if ( customIcon ) {
			// Decode base64 SVG if present
			try {
				if ( customIcon.startsWith( 'data:image/svg+xml;base64,' ) ) {
					return customIcon;
				}
			} catch ( e ) {
				// Ignore
			}
		}

		// Use field icon if available
		if ( currentContentEditableElement ) {
			const fieldSlug = currentContentEditableElement.getAttribute(
				'data-acf-inline-contenteditable-field-slug'
			);
			const field = getFieldInfo( fieldSlug );
			if ( field?.icon ) {
				return field.icon;
			}
		}

		// Default icon
		return blockIcon || 'edit';
	}, [
		currentInlineEditingElement,
		currentContentEditableElement,
		blockIcon,
	] );

	/**
	 * Get toolbar title from data attribute or element type or field label
	 */
	const toolbarTitle = useMemo( () => {
		// Check for custom toolbar title in data attribute
		const customTitle = currentInlineEditingElement?.getAttribute(
			'data-acf-toolbar-title'
		);
		if ( customTitle ) {
			return customTitle;
		}

		// Use content editable field label if available
		if ( currentContentEditableElement ) {
			const fieldSlug = currentContentEditableElement.getAttribute(
				'data-acf-inline-contenteditable-field-slug'
			);
			return getFieldLabel( fieldSlug );
		}

		// Use element type (DIV, P, SPAN, etc.) if no field is selected
		if ( currentInlineEditingElement ) {
			const tagName = currentInlineEditingElement.tagName;
			return tagName.charAt( 0 ) + tagName.slice( 1 ).toLowerCase();
		}

		return acf.__( 'Edit' );
	}, [
		currentInlineEditingElement,
		currentContentEditableElement,
		blockFieldInfo,
	] );

	/**
	 * Handle field button click
	 */
	const handleFieldButtonClick = ( fieldName, buttonElement ) => {
		const fieldType = getFieldType( fieldName );

		// Set selected field
		setSelectedFieldKey( fieldName );

		// Open modal for complex field types
		if ( isComplexFieldType( fieldType ) ) {
			setIsFieldModalOpen( true );
			setIsFieldPopoverOpen( false );
		} else {
			// Open popover for simple fields
			setPopoverAnchor( buttonElement );
			setIsFieldPopoverOpen( true );
		}
	};

	/**
	 * Close field popover/modal
	 */
	const closeFieldEditor = () => {
		setIsFieldPopoverOpen( false );
		setIsFieldModalOpen( false );
		setSelectedFieldKey( null );
		setPopoverAnchor( null );
	};

	// Clean up when toolbar loses focus
	useEffect( () => {
		setInlineEditingToolbarHasFocus( true );
		return () => {
			setInlineEditingToolbarHasFocus( false );
		};
	}, [] );

	if ( ! currentInlineEditingElement || ! currentInlineEditingElementUid ) {
		return null;
	}

	return (
		<div className="acf-inline-editing-toolbar-content">
			<div className="acf-inline-editing-toolbar-header">
				<div className="acf-inline-editing-toolbar-icon">
					{ typeof toolbarIcon === 'string' &&
					toolbarIcon.startsWith( 'data:image' ) ? (
						<img src={ toolbarIcon } alt="" />
					) : (
						<span className={ `dashicons dashicons-${ toolbarIcon }` } />
					) }
				</div>
				<div className="acf-inline-editing-toolbar-title">
					{ toolbarTitle }
				</div>
			</div>

			{ inlineFields.length > 0 && (
				<div className="acf-inline-editing-toolbar-fields">
					{ inlineFields.map( ( fieldName ) => {
						const field = getFieldInfo( fieldName );
						if ( ! field ) {
							return null;
						}

						return (
							<Button
								key={ fieldName }
								className="acf-toolbar-button"
								onClick={ ( e ) => {
									handleFieldButtonClick(
										fieldName,
										e.currentTarget
									);
								} }
								icon={ field.icon || 'edit' }
								label={ field.label }
								showTooltip={ true }
							/>
						);
					} ) }
				</div>
			) }

			{ /* Field popover for simple fields */ }
			{ isFieldPopoverOpen && popoverAnchor && selectedFieldKey && (
				<PopoverWrapper
					className="acf-inline-fields-popover"
					anchor={ popoverAnchor }
					placement="bottom-start"
					onClose={ closeFieldEditor }
					focusOnMount={ false }
					variant="unstyled"
					gutenbergIframeOrDocument={ gutenbergIframeOrDocument }
				>
					<div className="acf-inline-fields-popover-inner">
						{ acfFormRef?.current &&
							createPortal(
								<div style={ { padding: '16px' } }>
									{ /* Render specific field from form */ }
									<div
										dangerouslySetInnerHTML={ {
											__html: acfFormRef.current.querySelector(
												`[data-name="${ selectedFieldKey }"]`
											)?.outerHTML,
										} }
									/>
								</div>,
								fieldPopoverContainerRef
							) }
					</div>
				</PopoverWrapper>
			) }

			{ /* Field modal for complex fields */ }
			{ isFieldModalOpen && selectedFieldKey && (
				<Modal
					className="acf-inline-field-modal"
					title={ getFieldLabel( selectedFieldKey ) }
					onRequestClose={ closeFieldEditor }
				>
					<div className="acf-inline-field-modal-content">
						{ acfFormRef?.current && (
							<div
								dangerouslySetInnerHTML={ {
									__html: acfFormRef.current.querySelector(
										`[data-name="${ selectedFieldKey }"]`
									)?.outerHTML,
								} }
							/>
						) }
					</div>
				</Modal>
			) }
		</div>
	);
};
