/**
 * BlockToolbarFields Component
 * Renders field buttons in the WordPress block toolbar (top toolbar)
 * Allows quick access to edit specific fields from the block toolbar
 */

import { useState, useRef } from '@wordpress/element';
import { BlockControls } from '@wordpress/blockEditor';
import { ToolbarGroup, ToolbarButton, Modal } from '@wordpress/components';
import { PopoverWrapper } from './popover-wrapper';

/**
 * BlockToolbarFields component
 * Displays field editing buttons in the WordPress block toolbar
 *
 * @param {Object} props - Component props
 * @param {Array} props.blockToolbarFields - Array of field names or field config objects to show in toolbar
 * @param {Array} props.blockFieldInfo - Array of all field information
 * @param {Function} props.setCurrentBlockFormContainer - Setter for form container ref
 * @param {Document|HTMLIFrameElement} props.gutenbergIframeOrDocument - Document or iframe reference
 * @param {Function} props.setBlockFormModalOpen - Setter for block form modal state
 * @param {boolean} props.blockFormModalOpen - Whether block form modal is open
 * @returns {JSX.Element} - Rendered toolbar controls
 */
export const BlockToolbarFields = ( {
	blockToolbarFields,
	blockFieldInfo,
	setCurrentBlockFormContainer,
	gutenbergIframeOrDocument,
	setBlockFormModalOpen,
	blockFormModalOpen,
} ) => {
	const [ selectedFieldKey, setSelectedFieldKey ] = useState( null );
	const [ selectedFieldButtonRef, setSelectedFieldButtonRef ] = useState();
	const [ usePopover, setUsePopover ] = useState( true );
	const fieldPopoverContainerRef = useRef();

	/**
	 * Get field type class name from field name
	 */
	const getFieldTypeClassName = ( fieldName ) => {
		if ( ! fieldName || ! blockFieldInfo ) return '';
		const field = blockFieldInfo.find( ( f ) => f.name === fieldName );
		return field?.type ? field.type.replace( /_/g, '-' ) : '';
	};

	/**
	 * Get field info object from field name
	 */
	const getFieldInfo = ( fieldName ) => {
		return fieldName && blockFieldInfo
			? blockFieldInfo.find( ( f ) => f.name === fieldName )
			: null;
	};

	/**
	 * Get field label from field name
	 */
	const getFieldLabel = ( fieldName ) => {
		if ( ! fieldName || ! blockFieldInfo ) return fieldName || '';
		const field = blockFieldInfo.find( ( f ) => f.name === fieldName );
		// Fallback to fieldName when label not found to ensure toolbar shows a title
		return field?.label || fieldName || '';
	};
	return (
		<BlockControls>
			{ /* Edit Block button */ }
			<ToolbarGroup>
				<ToolbarButton
					className="components-icon-button components-toolbar__control acf-edit-block-button"
					label={ acf.__( 'Edit Block' ) }
					icon="edit"
					onClick={ () => {
						setBlockFormModalOpen( true );
					} }
					isPressed={ blockFormModalOpen }
				/>
			</ToolbarGroup>

			{ /* Field buttons */ }
			{ blockToolbarFields.length > 0 && (
				<ToolbarGroup>
					{ /* Style to show selected field in form */ }
					{ ( () => {
						const styleContent = `[data-name="${ selectedFieldKey }"]{ display: block!important; }`;
						return <style>{ styleContent }</style>;
					} )() }

					{ /* Render field buttons */ }
					{ blockToolbarFields &&
						blockToolbarFields.map( ( field ) => {
							let fieldName = '';
							let fieldIconSvg = null;
							let fieldLabel = null;

							// Handle field config object or simple string
							if ( typeof field === 'object' ) {
								fieldName = field.fieldName
									? field.fieldName
									: field.index;
								fieldIconSvg = field.fieldIcon
									? window.atob( field.fieldIcon )
									: null;
								fieldLabel = field.fieldLabel
									? field.fieldLabel
									: fieldName;
							} else {
								fieldName = field;
							}

							return (
								<ToolbarButton
									key={ fieldName }
									icon={
										fieldIconSvg ? (
											<i
												dangerouslySetInnerHTML={ {
													__html: fieldIconSvg,
												} }
											/>
										) : (
											<i
												className={ `field-type-icon field-type-icon-${ getFieldTypeClassName(
													fieldName
												) }` }
											/>
										)
									}
									label={
										fieldLabel || getFieldLabel( fieldName )
									}
									isPressed={ fieldName === selectedFieldKey }
									ref={
										fieldName === selectedFieldKey
											? setSelectedFieldButtonRef
											: null
									}
									onMouseDown={ () => {
										if ( selectedFieldKey === fieldName ) {
											setSelectedFieldKey( null );
										} else {
											setSelectedFieldKey( null );
											setTimeout( () => {
												const fieldInfo =
													getFieldInfo( fieldName );
												// Use modal for complex field types
												if (
													fieldInfo?.type ===
														'flexible_content' ||
													fieldInfo?.type ===
														'repeater'
												) {
													setUsePopover( false );
												} else {
													setUsePopover( true );
												}
												setSelectedFieldKey(
													fieldName
												);
											} );
										}
									} }
								/>
							);
						} ) }

					{ /* Popover for simple field types */ }
					{ selectedFieldKey &&
						selectedFieldButtonRef &&
						usePopover && (
							<PopoverWrapper
								focusOnMount={ false }
								className="acf-inline-fields-popover"
								anchor={ selectedFieldButtonRef }
								animate={ true }
								onClose={ ( event ) => {
									// Don't close on certain events
									if (
										event.key !== 'Escape' &&
										( event?.target.closest(
											'.media-modal'
										) ||
											event?.target.closest(
												'.acf-tooltip'
											) ||
											( event?.target &&
												fieldPopoverContainerRef?.current &&
												fieldPopoverContainerRef?.current.contains(
													event.target
												) ) ||
											( selectedFieldButtonRef?.current &&
												selectedFieldButtonRef?.current.contains(
													event?.target
												) ) )
									) {
										return false;
									}
									setSelectedFieldKey( null );
									return true;
								} }
								variant="unstyled"
								gutenbergIframeOrDocument={
									gutenbergIframeOrDocument
								}
							>
								<div ref={ fieldPopoverContainerRef }>
									<div
										className="acf-inline-fields-popover-inner"
										style={ { minWidth: '300px' } }
										ref={ setCurrentBlockFormContainer }
									/>
								</div>
							</PopoverWrapper>
						) }

					{ /* Modal for complex field types */ }
					{ selectedFieldKey &&
						selectedFieldButtonRef &&
						! usePopover && (
							<Modal
								className="acf-block-form-modal"
								isFullScreen={ true }
								title={
									getFieldInfo( selectedFieldKey )?.label
								}
								onRequestClose={ () => {
									setSelectedFieldKey( null );
								} }
							>
								<div ref={ fieldPopoverContainerRef }>
									<div
										className="acf-inline-fields-popover-inner"
										style={ { minWidth: '300px' } }
										ref={ setCurrentBlockFormContainer }
									/>
								</div>
							</Modal>
						) }
				</ToolbarGroup>
			) }
		</BlockControls>
	);
};
