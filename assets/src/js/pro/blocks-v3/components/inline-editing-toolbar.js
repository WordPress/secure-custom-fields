/**
 * InlineEditingToolbar Component
 * Main inline editing toolbar for ACF blocks
 * Handles field selection and editing for inline editable elements
 */

import { useState, useEffect, useMemo, useRef } from '@wordpress/element';
import { Toolbar, ToolbarGroup, ToolbarButton, Modal } from '@wordpress/components';
import { PopoverWrapper } from './popover-wrapper';

/**
 * InlineEditingToolbar component
 * Displays a toolbar with field buttons for inline editing
 *
 * @param {Object} props - Component props
 * @param {Object} props.blockIcon - Block icon configuration
 * @param {Array} props.blockFieldInfo - Array of field information
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
	setInlineEditingToolbarHasFocus,
	currentContentEditableElement,
	currentInlineEditingElement,
	currentInlineEditingElementUid,
	gutenbergIframeOrDocument,
	setCurrentBlockFormContainer,
	contentEditableChangeInProgress,
} ) => {
	const [ selectedFieldKey, setSelectedFieldKey ] = useState( null );
	const [ selectedFieldConfig, setSelectedFieldConfig ] = useState();
	const [ selectedFieldButtonRef, setSelectedFieldButtonRef ] = useState();
	const [ usePopover, setUsePopover ] = useState( true );
	const fieldPopoverContainerRef = useRef();

	// Get inline fields from data attribute
	const inlineFieldsAttr = currentInlineEditingElement
		? currentInlineEditingElement.getAttribute( 'data-acf-inline-fields' )
		: null;

	let inlineFields = [];
	try {
		inlineFields = JSON.parse( inlineFieldsAttr );
	} catch ( e ) {
		acf.debug(
			'Inline fields were not a properly formatted JSON array',
			inlineFieldsAttr
		);
	}

	/**
	 * Get field type class name from field name
	 */
	function getFieldTypeClassName( fieldName ) {
		if ( ! fieldName || ! blockFieldInfo ) return '';
		const field = blockFieldInfo.find( ( f ) => f.name === fieldName );
		return field?.type ? field.type.replace( /_/g, '-' ) : '';
	}

	/**
	 * Get field label from field name
	 */
	function getFieldLabel( fieldName ) {
		if ( ! fieldName || ! blockFieldInfo ) return '';
		const field = blockFieldInfo.find( ( f ) => f.name === fieldName );
		return field ? field.label : '';
	}

	// Clear selected field when content editable changes
	useEffect( () => {
		setSelectedFieldKey( null );
	}, [ contentEditableChangeInProgress ] );

	// Generate toolbar icon
	const toolbarIcon = useMemo( () => {
		let icon =
			currentContentEditableElement && ! currentInlineEditingElement
				? currentContentEditableElement.getAttribute(
						'data-acf-toolbar-icon'
				  )
				: null;

		if ( ! icon && currentInlineEditingElement ) {
			icon = currentInlineEditingElement
				? currentInlineEditingElement.getAttribute(
						'data-acf-toolbar-icon'
				  )
				: null;
		}

		if ( icon ) {
			icon = <i dangerouslySetInnerHTML={ { __html: icon } } />;
		}

		if ( ! icon && currentContentEditableElement && ! currentInlineEditingElement ) {
			const fieldSlug = currentContentEditableElement.getAttribute(
				'data-acf-inline-contenteditable-field-slug'
			);
			icon = (
				<i
					className={ `field-type-icon field-type-icon-${ getFieldTypeClassName(
						fieldSlug
					) }` }
				/>
			);
		}

		if ( ! icon ) {
			icon = React.isValidElement( blockIcon ) ? (
				blockIcon
			) : (
				<span className={ `dashicon dashicons dashicons-${ blockIcon }` } />
			);
		}

		return icon;
	}, [ blockFieldInfo, currentContentEditableElement, currentInlineEditingElement ] );

	// Generate toolbar title
	const toolbarTitle = useMemo( () => {
		let fieldName;

		if ( currentContentEditableElement && ! currentInlineEditingElement ) {
			const title = currentContentEditableElement.getAttribute(
				'data-acf-toolbar-title'
			);
			if ( title ) return title;

			fieldName = currentContentEditableElement.getAttribute(
				'data-acf-inline-contenteditable-field-slug'
			);
		} else if ( currentInlineEditingElement ) {
			const title =
				currentInlineEditingElement.getAttribute( 'data-acf-toolbar-title' );
			if ( title ) return title;

			if ( inlineFields.length > 1 ) {
				const elementTypeLabels = {
					A: 'Link',
					DIV: 'Division',
					P: 'Paragraph',
					SPAN: 'Span',
					INPUT: 'Input',
					BUTTON: 'Button',
					IMG: 'Image',
					UL: 'Unordered List',
					OL: 'Ordered List',
					LI: 'List Item',
					H1: 'Heading 1',
					H2: 'Heading 2',
					H3: 'Heading 3',
					H4: 'Heading 4',
					H5: 'Heading 5',
					H6: 'Heading 6',
					TABLE: 'Table',
					TR: 'Table Row',
					TD: 'Table Cell',
					TH: 'Table Header',
					FORM: 'Form',
					TEXTAREA: 'Text Area',
					SELECT: 'Select',
					OPTION: 'Option',
				};
				return elementTypeLabels[ currentInlineEditingElement.tagName ];
			}

			fieldName =
				typeof inlineFields[ 0 ] === 'object'
					? inlineFields[ 0 ].fieldName
					: inlineFields[ 0 ];
		}

		return getFieldLabel( fieldName );
	}, [ currentInlineEditingElement, currentContentEditableElement, blockFieldInfo ] );

	return (
		<>
			{ /* Field popover for simple fields */ }
			{ selectedFieldKey &&
				selectedFieldButtonRef &&
				usePopover &&
				currentInlineEditingElementUid && (
					<PopoverWrapper
						focusOnMount={ false }
						className="acf-inline-fields-popover"
						anchor={ selectedFieldButtonRef }
						onClose={ ( event ) => {
							if ( event.key === 'Escape' ) {
								setSelectedFieldKey( null );
								return true;
							}
							// Don't close if clicking inside the popover or anchor
							if (
								( event?.target &&
									fieldPopoverContainerRef?.current &&
									fieldPopoverContainerRef?.current.contains(
										event.target
									) ) ||
								( selectedFieldButtonRef?.current &&
									selectedFieldButtonRef?.current.contains(
										event?.target
									) )
							) {
								return false;
							}
							return undefined;
						} }
						variant={ usePopover ? 'toolbar' : 'unstyled' }
						gutenbergIframeOrDocument={ gutenbergIframeOrDocument }
						hidePrimaryBlockToolbar={ true }
						animate={ true }
					>
						<div
							ref={ fieldPopoverContainerRef }
							onClick={ () => {
								setInlineEditingToolbarHasFocus( true );
							} }
						>
							<div
								className="acf-inline-fields-popover-inner"
								style={ {
									minWidth: selectedFieldConfig?.popoverMinWidth
										? selectedFieldConfig?.popoverMinWidth
										: '300px',
								} }
								ref={ setCurrentBlockFormContainer }
							/>
						</div>
					</PopoverWrapper>
				) }

			{ /* Modal for complex field types */ }
			{ selectedFieldKey &&
				selectedFieldButtonRef &&
				! usePopover &&
				currentInlineEditingElementUid && (
					<Modal
						className="acf-block-form-modal"
						isFullScreen={ true }
						title={
							blockFieldInfo && selectedFieldKey
								? blockFieldInfo.find(
										( f ) => f.name === selectedFieldKey
								  )?.label
								: ''
						}
						onRequestClose={ () => {
							setSelectedFieldKey( null );
						} }
					>
						<div
							ref={ fieldPopoverContainerRef }
							onClick={ () => {
								setInlineEditingToolbarHasFocus( true );
							} }
						>
							<div
								className="acf-inline-fields-popover-inner"
								style={ {
									minWidth: selectedFieldConfig?.popoverMinWidth
										? selectedFieldConfig?.popoverMinWidth
										: '300px',
								} }
								ref={ setCurrentBlockFormContainer }
							/>
						</div>
					</Modal>
				) }

			{ /* Toolbar */ }
			<Toolbar
				orientation="horizontal"
				className="components-accessible-toolbar block-editor-block-contextual-toolbar"
				style={ { width: 'max-content' } }
			>
				<div className="block-editor-block-toolbar">
					{ /* Toolbar icon and title */ }
					<ToolbarGroup style={ { alignItems: 'center' } }>
						<div
							className="acf-blocks-toolbar-icon components-toolbar-group block-editor-block-toolbar__block-controls"
							label={ toolbarTitle }
						>
							{ toolbarIcon }
							<span>{ toolbarTitle }</span>
						</div>
					</ToolbarGroup>

					{ /* Field buttons */ }
					<ToolbarGroup>
						{ ( () => {
							if ( ! inlineFields || inlineFields.length === 0 ) {
								return null;
							}

							const buttons = inlineFields.map( ( field, index ) => {
								let fieldName = '';
								let fieldIconSvg = null;
								let fieldLabel = null;

								if ( typeof field === 'object' ) {
									fieldName = field.fieldName
										? field.fieldName
										: index;
									fieldIconSvg = field.fieldIcon
										? window.atob( field.fieldIcon )
										: null;
									fieldLabel = field.fieldLabel
										? field.fieldLabel
										: fieldName;
								} else {
									fieldName = field;
								}

								// Use 'edit' icon if field has useExpandedEditor flag
								if ( ! fieldIconSvg && field?.useExpandedEditor ) {
									fieldIconSvg = 'edit';
								}

								return (
									<ToolbarButton
										key={ fieldName }
										disabled={ contentEditableChangeInProgress }
										className="acf-toolbar-button"
										icon={
											fieldIconSvg ? (
												typeof fieldIconSvg === 'string' &&
												fieldIconSvg === 'edit' ? (
													'edit'
												) : (
													<i
														dangerouslySetInnerHTML={ {
															__html: fieldIconSvg,
														} }
													/>
												)
											) : (
												<i
													className={ `field-type-icon field-type-icon-${ getFieldTypeClassName(
														fieldName
													) }` }
												/>
											)
										}
										label={ fieldLabel || getFieldLabel( fieldName ) }
										isPressed={ fieldName === selectedFieldKey }
										ref={
											fieldName === selectedFieldKey
												? setSelectedFieldButtonRef
												: null
										}
										onClick={ () => {
											setInlineEditingToolbarHasFocus( true );
											if ( selectedFieldKey === fieldName ) {
												setSelectedFieldKey( null );
											} else {
												// Determine if we should use modal or popover
												setUsePopover( ! field?.useExpandedEditor );
												setSelectedFieldKey( fieldName );
												setSelectedFieldConfig( field );
											}
										} }
									/>
								);
							} );

							return buttons;
						} )() }
					</ToolbarGroup>
				</div>
			</Toolbar>

			{ /* Style to show selected field */ }
			{ ( () => {
				const styleContent = `[data-name="${ selectedFieldKey }"]{ display: block!important; }`;
				return <style>{ styleContent }</style>;
			} )() }
		</>
	);
};
