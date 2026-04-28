/**
 * BlockEdit Component
 * Main component for editing ACF blocks in the Gutenberg editor
 * Handles form fetching, validation, preview rendering, and user interactions
 */
import md5 from 'md5';

import {
	useState,
	useEffect,
	useRef,
	createPortal,
	useMemo,
} from '@wordpress/element';

import {
	InspectorControls,
	useBlockProps,
	useBlockEditContext,
} from '@wordpress/block-editor';
import {
	Button,
	Placeholder,
	Spinner,
	Modal,
	PanelBody,
} from '@wordpress/components';
import { BlockPlaceholder } from './block-placeholder';
import { BlockForm } from './block-form';
import { BlockPreview } from './block-preview';
import { ErrorBoundary, BlockPreviewErrorFallback } from './error-boundary';
import { BlockToolbarFields } from './block-toolbar-fields';
import { InlineEditingToolbar } from './inline-editing-toolbar';
import { PopoverWrapper } from './popover-wrapper';
import {
	lockPostSaving,
	unlockPostSaving,
	sortObjectKeys,
	lockPostSavingByName,
	unlockPostSavingByName,
} from '../utils/post-locking';

/**
 * InspectorBlockFormContainer
 * Small helper component that manages the inspector panel container ref
 * Sets the current form container when the inspector panel is available
 *
 * @param {Object} props
 * @param {React.RefObject} props.inspectorBlockFormRef - Ref to inspector container
 * @param {Function} props.setCurrentBlockFormContainer - Setter for current container
 */
const InspectorBlockFormContainer = ( {
	inspectorBlockFormRef,
	setCurrentBlockFormContainer,
} ) => {
	useEffect( () => {
		setCurrentBlockFormContainer( inspectorBlockFormRef.current );
	}, [] );

	return <div ref={ inspectorBlockFormRef } />;
};

const isBlockEditorInspectorSidebarOpen = () => {
	const interfaceStore = wp.data.select( 'core/interface' );

	return (
		typeof interfaceStore?.getActiveComplementaryArea === 'function' &&
		interfaceStore.getActiveComplementaryArea( 'core' ) ===
			'edit-post/block'
	);
};

const useBlockEditorInspectorSidebarOpen = () => {
	const [ isOpen, setIsOpen ] = useState( () =>
		isBlockEditorInspectorSidebarOpen()
	);

	useEffect( () => {
		if ( typeof wp.data.subscribe !== 'function' ) {
			return;
		}

		const unsubscribe = wp.data.subscribe( () => {
			setIsOpen( isBlockEditorInspectorSidebarOpen() );
		} );

		return () => {
			unsubscribe();
		};
	}, [] );

	return isOpen;
};

/**
 * Main BlockEdit component wrapper
 * Manages block data fetching and initial setup
 *
 * @param {Object} props - Component props
 * @param {Object} props.attributes - Block attributes
 * @param {Function} props.setAttributes - Function to update block attributes
 * @param {Object} props.context - Block context
 * @param {boolean} props.isSelected - Whether block is currently selected
 * @param {jQuery} props.$ - jQuery instance
 * @param {Object} props.blockType - ACF block type configuration
 * @returns {JSX.Element} - Rendered block editor
 */
export const BlockEdit = ( props ) => {
	const { attributes, setAttributes, context, isSelected, $, blockType } =
		props;

	const shouldValidate = blockType.validate;
	const { clientId } = useBlockEditContext();

	const preloadedData = useMemo( () => {
		return checkPreloadedData(
			generateAttributesHash( attributes, context ),
			clientId,
			isSelected
		);
	}, [] );

	const [ validationErrors, setValidationErrors ] = useState( () => {
		return preloadedData?.validation?.errors ?? null;
	} );

	const [ showValidationErrors, setShowValidationErrors ] = useState( null );
	const [ theSerializedAcfData, setTheSerializedAcfData ] = useState( null );
	const [ blockFormHtml, setBlockFormHtml ] = useState(
		() => preloadedData?.form ?? ''
	);
	const [ blockPreviewHtml, setBlockPreviewHtml ] = useState( () => {
		if ( preloadedData?.html ) {
			return acf.applyFilters(
				'blocks/preview/render',
				preloadedData.html,
				true
			);
		}
		return 'acf-block-preview-loading';
	} );
	const [ userHasInteractedWithForm, setUserHasInteractedWithForm ] =
		useState( false );
	const [ hasFetchedOnce, setHasFetchedOnce ] = useState( false );
	const [ ajaxRequest, setAjaxRequest ] = useState();
	const [ isFetchingBlock, setIsFetchingBlock ] = useState( false );

	// New state for inline editing features
	const [ blockToolbarFields, setBlockToolbarFields ] = useState( [] );
	const [ blockFieldInfo, setBlockFieldInfo ] = useState(
		() => preloadedData?.fields ?? null
	);
	const [ gutenbergIframeOrDocument, setGutenbergIframeOrDocument ] =
		useState( () => {
			const iframe = document.querySelector( '[name="editor-canvas"]' );
			return iframe
				? iframe.contentDocument || iframe.contentWindow.document
				: document;
		} );
	const [ currentInlineEditingElement, setCurrentInlineEditingElement ] =
		useState( null );
	const [
		currentInlineEditingElementUid,
		setCurrentInlineEditingElementUid,
	] = useState( null );
	const [ currentContentEditableElement, setCurrentContentEditableElement ] =
		useState( null );
	const [ inlineEditingToolbarHasFocus, setInlineEditingToolbarHasFocus ] =
		useState( false );
	const [
		contentEditableChangeInProgress,
		setContentEditableChangeInProgress,
	] = useState( false );
	const [ acfDynamicStylesElement, setAcfDynamicStylesElement ] =
		useState( null );
	const [
		freezeInlineToolbarDuringReRender,
		setFreezeInlineToolbarDuringReRender,
	] = useState( '' );
	const blockEditorInspectorSidebarOpen =
		useBlockEditorInspectorSidebarOpen();

	const acfFormRef = useRef( null );
	const previewRef = useRef( null );
	const debounceRef = useRef( null );

	// Initialize acf.blockEdit namespace for jsx-parser to use
	if ( ! acf.blockEdit ) {
		acf.blockEdit = {};
	}
	acf.blockEdit.setCurrentInlineEditingElementUid =
		setCurrentInlineEditingElementUid;
	acf.blockEdit.setCurrentInlineEditingElement =
		setCurrentInlineEditingElement;
	acf.blockEdit.setCurrentContentEditableElement =
		setCurrentContentEditableElement;
	acf.blockEdit.getBlockFieldInfo = () => blockFieldInfo;

	const attributesWithoutError = useMemo( () => {
		const { hasAcfError, ...rest } = attributes;
		return rest;
	}, [ attributes ] );

	/**
	 * Fetches block data from server (form HTML, preview HTML, validation)
	 *
	 * @param {Object} params - Fetch parameters
	 * @param {Object} params.theAttributes - Block attributes to fetch for
	 * @param {string} params.theClientId - Block client ID
	 * @param {Object} params.theContext - Block context
	 * @param {boolean} params.isSelected - Whether block is selected
	 */
	function fetchBlockData( {
		theAttributes,
		theClientId,
		theContext,
		isSelected,
	} ) {
		if ( ! theAttributes ) return;

		// NEW: Abort any pending request
		if ( ajaxRequest ) {
			ajaxRequest.abort();
		}

		// Generate hash of attributes for preload cache lookup
		const attributesHash = generateAttributesHash( theAttributes, context );

		// Check for preloaded block data
		const preloadedData = checkPreloadedData(
			attributesHash,
			theClientId,
			isSelected
		);

		if ( preloadedData ) {
			handlePreloadedData( preloadedData );
			unlockPostSavingByName( 'acf-fetching-block' );
			setIsFetchingBlock( false );
			return;
		}

		// Prepare query options
		const queryOptions = { preview: true, form: true, validate: true };
		if ( ! blockFormHtml ) {
			queryOptions.validate = false;
		}
		if ( ! shouldValidate ) {
			queryOptions.validate = false;
		}

		const blockData = { ...theAttributes };

		lockPostSavingByName( 'acf-fetching-block' );
		setIsFetchingBlock( true );

		// Fetch block data via AJAX
		const request = $.ajax( {
			url: acf.get( 'ajaxurl' ),
			dataType: 'json',
			type: 'post',
			cache: false,
			data: acf.prepareForAjax( {
				action: 'acf/ajax/fetch-block',
				block: JSON.stringify( blockData ),
				clientId: theClientId,
				context: JSON.stringify( theContext ),
				query: queryOptions,
			} ),
		} )
			.done( ( response ) => {
				unlockPostSavingByName( 'acf-fetching-block' );
				setIsFetchingBlock( false );

				setBlockFormHtml( response.data.form );

				// Handle new field metadata for inline editing
				if ( response.data.fields ) {
					setBlockFieldInfo( response.data.fields );
				}

				// Handle block toolbar fields configuration
				if ( response.data.blockToolbarFields ) {
					setBlockToolbarFields( response.data.blockToolbarFields );
				}

				if ( response.data.preview ) {
					setBlockPreviewHtml(
						acf.applyFilters(
							'blocks/preview/render',
							response.data.preview,
							false
						)
					);
				} else {
					setBlockPreviewHtml(
						acf.applyFilters(
							'blocks/preview/render',
							'acf-block-preview-no-html',
							false
						)
					);
				}

				if (
					response.data?.validation &&
					! response.data.validation.valid &&
					response.data.validation.errors
				) {
					setValidationErrors( response.data.validation.errors );
				} else {
					setValidationErrors( null );
				}

				setHasFetchedOnce( true );
			} )
			.fail( function () {
				setHasFetchedOnce( true );
				unlockPostSavingByName( 'acf-fetching-block' );
				setIsFetchingBlock( false );
			} );
		setAjaxRequest( request );
	}

	/**
	 * Generates a hash of block attributes for caching
	 *
	 * @param {Object} attrs - Block attributes
	 * @param {Object} ctx - Block context
	 * @returns {string} - MD5 hash of serialized attributes
	 */
	function generateAttributesHash( attrs, ctx ) {
		delete attrs.hasAcfError;
		attrs._acf_context = sortObjectKeys( ctx );
		return md5( JSON.stringify( sortObjectKeys( attrs ) ) );
	}

	/**
	 * Checks if block data was preloaded and returns it
	 *
	 * @param {string} hash - Attributes hash
	 * @param {string} clientId - Block client ID
	 * @param {boolean} selected - Whether block is selected
	 * @returns {Object|boolean} - Preloaded data or false
	 */
	function checkPreloadedData( hash, clientId, selected ) {
		if ( selected ) return false;

		acf.debug( 'Preload check', hash, clientId );

		// Don't preload blocks inside Query Loop blocks
		if ( isInQueryLoop( clientId ) ) {
			return false;
		}

		const preloadedBlocks = acf.get( 'preloadedBlocks' );
		if ( ! preloadedBlocks || ! preloadedBlocks[ hash ] ) {
			acf.debug( 'Preload failed: not preloaded.' );
			return false;
		}

		const data = preloadedBlocks[ hash ];

		// Replace placeholder client ID with actual client ID
		data.html = data.html.replaceAll( hash, clientId );
		data.form = data.form.replaceAll( hash, clientId );

		if ( data?.validation && data?.validation.errors ) {
			data.validation.errors = data.validation.errors.map( ( error ) => {
				error.input = error.input.replaceAll( hash, clientId );
				return error;
			} );
		}

		acf.debug( 'Preload successful', data );
		return data;
	}

	/**
	 * Checks if block is inside a Query Loop block
	 *
	 * @param {string} clientId - Block client ID
	 * @returns {boolean} - True if inside Query Loop
	 */
	function isInQueryLoop( clientId ) {
		const parentIds = wp.data
			.select( 'core/block-editor' )
			.getBlockParents( clientId );

		return (
			wp.data
				.select( 'core/block-editor' )
				.getBlocksByClientId( parentIds )
				.filter( ( block ) => block.name === 'core/query' ).length > 0
		);
	}

	/**
	 * Handles preloaded block data
	 *
	 * @param {Object} data - Preloaded data
	 */
	function handlePreloadedData( data ) {
		if ( data.form ) {
			setBlockFormHtml( data.form );
		}

		if ( data.html ) {
			setBlockPreviewHtml(
				acf.applyFilters( 'blocks/preview/render', data.html, true )
			);
		} else {
			setBlockPreviewHtml(
				acf.applyFilters(
					'blocks/preview/render',
					'acf-block-preview-no-html',
					true
				)
			);
		}

		// Handle block toolbar fields from preloaded data
		if ( data?.blockToolbarFields ) {
			setBlockToolbarFields( data.blockToolbarFields );
		}

		// Handle field info from preloaded data
		if ( data?.fields ) {
			setBlockFieldInfo( data.fields );
		}

		if (
			data?.validation &&
			! data.validation.valid &&
			data.validation.errors
		) {
			setValidationErrors( data.validation.errors );
		} else {
			setValidationErrors( null );
		}
	}

	// Initial fetch on mount and when selection changes
	useEffect( () => {
		function trackUserInteraction() {
			setUserHasInteractedWithForm( true );
			window.removeEventListener( 'click', trackUserInteraction );
			window.removeEventListener( 'keydown', trackUserInteraction );
		}

		window.addEventListener( 'click', trackUserInteraction );
		window.addEventListener( 'keydown', trackUserInteraction );

		return () => {
			window.removeEventListener( 'click', trackUserInteraction );
			window.removeEventListener( 'keydown', trackUserInteraction );
		};
	}, [] );

	useEffect( () => {
		if ( isSelected ) {
			return;
		}

		setCurrentInlineEditingElementUid( null );
		setCurrentInlineEditingElement( null );
		setCurrentContentEditableElement( null );
	}, [ isSelected ] );

	// Update hasAcfError attribute based on validation errors
	useEffect( () => {
		setAttributes(
			validationErrors ? { hasAcfError: true } : { hasAcfError: false }
		);
	}, [ validationErrors, setAttributes ] );

	// Listen for validation error events from other blocks
	useEffect( () => {
		const handleErrorEvent = ( event ) => {
			// Only handle if this event is for this specific block
			if ( clientId === event.detail.acfBlocksWithValidationErrors ) {
				lockPostSaving( clientId );
				setShowValidationErrors( true );
				setCurrentInlineEditingElementUid( null );
			}
		};

		document.addEventListener( 'acf/block/has-error', handleErrorEvent );

		return () => {
			document.removeEventListener(
				'acf/block/has-error',
				handleErrorEvent
			);
		};
	}, [] );

	// Cleanup: unlock post saving on unmount
	useEffect(
		() => () => {
			unlockPostSaving( props.clientId );
		},
		[]
	);

	// Handle form data changes with debouncing
	useEffect( () => {
		clearTimeout( debounceRef.current );
		lockPostSavingByName( 'acf-fetching-block' );
		setIsFetchingBlock( true );

		debounceRef.current = setTimeout( () => {
			const parsedData = JSON.parse( theSerializedAcfData );

			if ( ! parsedData ) {
				return void fetchBlockData( {
					theAttributes: attributesWithoutError,
					theClientId: clientId,
					theContext: context,
					isSelected: isSelected,
				} );
			}

			if (
				theSerializedAcfData ===
				JSON.stringify( attributesWithoutError.data )
			) {
				return void fetchBlockData( {
					theAttributes: attributesWithoutError,
					theClientId: clientId,
					theContext: context,
					isSelected: isSelected,
				} );
			}

			// Use original attributes (with hasAcfError) when updating
			const updatedAttributes = {
				...attributes,
				data: { ...parsedData },
			};
			setAttributes( updatedAttributes );
		}, 200 );

		// Cleanup function to unlock post saving
		return () => {
			clearTimeout( debounceRef.current );
			unlockPostSavingByName( 'acf-fetching-block' );
			setIsFetchingBlock( false );
		};
	}, [ theSerializedAcfData, attributesWithoutError ] );

	// Trigger ACF actions when preview is rendered
	useEffect( () => {
		if ( previewRef.current && blockPreviewHtml ) {
			const blockName = attributes.name.replace( 'acf/', '' );
			const $preview = $( previewRef.current );

			acf.doAction( 'render_block_preview', $preview, attributes );
			acf.doAction(
				`render_block_preview/type=${ blockName }`,
				$preview,
				attributes
			);

			// If there's an active inline editing element, re-initialize it after preview renders
			if ( currentInlineEditingElementUid ) {
				const inlineElement = previewRef?.current.querySelector(
					`[data-acf-inline-fields-uid="${ currentInlineEditingElementUid }"]`
				);
				setCurrentInlineEditingElement( inlineElement );
			}
		}
	}, [ blockPreviewHtml ] );

	useEffect( () => {
		if (
			( currentContentEditableElement &&
				! inlineEditingToolbarHasFocus ) ||
			! currentInlineEditingElement
		) {
			return;
		}

		const toolbar = document.querySelector( '.acf-inline-editing-toolbar' );

		if ( toolbar?.style?.cssText ) {
			setFreezeInlineToolbarDuringReRender(
				toolbar.style.cssText.replaceAll( ';', '!important;' )
			);
		}

		setTimeout( () => {
			setCurrentInlineEditingElement( currentInlineEditingElement );
			setTimeout( () => {
				setFreezeInlineToolbarDuringReRender( null );
			}, 0 );
		}, 0 );
	}, [ currentInlineEditingElement ] );

	return (
		<BlockEditInner
			{ ...props }
			validationErrors={ validationErrors }
			showValidationErrors={ showValidationErrors }
			theSerializedAcfData={ theSerializedAcfData }
			setTheSerializedAcfData={ setTheSerializedAcfData }
			acfFormRef={ acfFormRef }
			blockFormHtml={ blockFormHtml }
			blockPreviewHtml={ blockPreviewHtml }
			blockFetcher={ fetchBlockData }
			userHasInteractedWithForm={ userHasInteractedWithForm }
			setUserHasInteractedWithForm={ setUserHasInteractedWithForm }
			previewRef={ previewRef }
			hasFetchedOnce={ hasFetchedOnce }
			blockToolbarFields={ blockToolbarFields }
			blockFieldInfo={ blockFieldInfo }
			gutenbergIframeOrDocument={ gutenbergIframeOrDocument }
			setGutenbergIframeOrDocument={ setGutenbergIframeOrDocument }
			currentInlineEditingElement={ currentInlineEditingElement }
			setCurrentInlineEditingElement={ setCurrentInlineEditingElement }
			currentInlineEditingElementUid={ currentInlineEditingElementUid }
			setCurrentInlineEditingElementUid={
				setCurrentInlineEditingElementUid
			}
			currentContentEditableElement={ currentContentEditableElement }
			setCurrentContentEditableElement={
				setCurrentContentEditableElement
			}
			inlineEditingToolbarHasFocus={ inlineEditingToolbarHasFocus }
			setInlineEditingToolbarHasFocus={ setInlineEditingToolbarHasFocus }
			contentEditableChangeInProgress={ contentEditableChangeInProgress }
			setContentEditableChangeInProgress={
				setContentEditableChangeInProgress
			}
			acfDynamicStylesElement={ acfDynamicStylesElement }
			setAcfDynamicStylesElement={ setAcfDynamicStylesElement }
			blockEditorInspectorSidebarOpen={ blockEditorInspectorSidebarOpen }
			freezeInlineToolbarDuringReRender={
				freezeInlineToolbarDuringReRender
			}
			isFetchingBlock={ isFetchingBlock }
		/>
	);
};

/**
 * Inner component that handles rendering and portals
 * Separated to manage refs and portal targets properly
 */
function BlockEditInner( props ) {
	const {
		blockType,
		$,
		isSelected,
		attributes,
		context,
		validationErrors,
		showValidationErrors,
		theSerializedAcfData,
		setTheSerializedAcfData,
		acfFormRef,
		blockFormHtml,
		blockPreviewHtml,
		blockFetcher,
		userHasInteractedWithForm,
		setUserHasInteractedWithForm,
		previewRef,
		hasFetchedOnce,
		blockToolbarFields,
		blockFieldInfo,
		gutenbergIframeOrDocument,
		setGutenbergIframeOrDocument,
		currentInlineEditingElement,
		setCurrentInlineEditingElement,
		currentInlineEditingElementUid,
		setCurrentInlineEditingElementUid,
		currentContentEditableElement,
		setCurrentContentEditableElement,
		inlineEditingToolbarHasFocus,
		setInlineEditingToolbarHasFocus,
		contentEditableChangeInProgress,
		setContentEditableChangeInProgress,
		acfDynamicStylesElement,
		setAcfDynamicStylesElement,
		blockEditorInspectorSidebarOpen,
		freezeInlineToolbarDuringReRender,
		isFetchingBlock,
	} = props;

	const { clientId } = useBlockEditContext();
	const inspectorControlsRef = useRef();
	const [ blockFormModalOpen, setBlockFormModalOpen ] = useState( false );
	const modalFormContainerRef = useRef();
	const [ currentFormContainer, setCurrentFormContainer ] = useState();
	const [ canRenderForm, setCanRenderForm ] = useState( false );
	const [ invisibleBlockFormContainer, setInvisibleBlockFormContainer ] =
		useState();

	// Render counter for debugging
	const renderCount = useRef( 0 );
	renderCount.current++;

	// Detect Gutenberg iframe or document
	useEffect( () => {
		const gutenbergIframe = document.querySelector(
			'iframe[name="editor-canvas"]'
		);
		if ( gutenbergIframe?.contentDocument ) {
			setGutenbergIframeOrDocument( gutenbergIframe.contentDocument );
		} else {
			setGutenbergIframeOrDocument( document );
		}
	}, [] );

	// Create/get dynamic styles element for inline field highlighting
	useEffect( () => {
		if ( ! gutenbergIframeOrDocument ) return;

		let styleElement =
			gutenbergIframeOrDocument.getElementById( 'acf-dynamic-styles' );
		if ( ! styleElement ) {
			styleElement = document.createElement( 'style' );
			styleElement.id = 'acf-dynamic-styles';
			gutenbergIframeOrDocument.head.appendChild( styleElement );
		}
		setAcfDynamicStylesElement( styleElement );
	}, [ gutenbergIframeOrDocument ] );

	useEffect( () => {
		let invisibleContainer = document.getElementById(
			'invisible-acf-form-element'
		);

		if ( ! invisibleContainer ) {
			invisibleContainer = document.createElement( 'div' );
			invisibleContainer.id = 'invisible-acf-form-element';
			invisibleContainer.style.display = 'none';
			document.body.appendChild( invisibleContainer );
		}

		setInvisibleBlockFormContainer( invisibleContainer );
	}, [ blockEditorInspectorSidebarOpen ] );

	useEffect( () => {
		if ( blockFormModalOpen && modalFormContainerRef?.current ) {
			setCurrentFormContainer( modalFormContainerRef.current );
			return;
		}

		setCurrentFormContainer(
			blockEditorInspectorSidebarOpen
				? inspectorControlsRef.current
				: invisibleBlockFormContainer
		);
	}, [ blockFormModalOpen, modalFormContainerRef ] );

	useEffect( () => {
		if ( blockEditorInspectorSidebarOpen ) {
			if ( ! blockFormModalOpen ) {
				setCurrentFormContainer( inspectorControlsRef.current );
			}
			return;
		}

		setCurrentFormContainer( invisibleBlockFormContainer );
	}, [
		blockEditorInspectorSidebarOpen,
		invisibleBlockFormContainer,
		blockFormModalOpen,
	] );

	useEffect( () => {
		if ( isSelected && inspectorControlsRef?.current ) {
			setCanRenderForm( true );
		} else if ( isSelected && ! inspectorControlsRef?.current ) {
			setTimeout( () => {
				setCanRenderForm( true );
			}, 1 );
		}
	}, [ isSelected, inspectorControlsRef, inspectorControlsRef.current ] );

	useEffect( () => {
		if (
			isSelected &&
			validationErrors &&
			showValidationErrors &&
			blockType?.hide_fields_in_sidebar
		) {
			setBlockFormModalOpen( true );
		}
	}, [ isSelected, showValidationErrors, validationErrors, blockType ] );

	// Build block CSS classes
	let blockClasses = 'acf-block-component acf-block-body';
	blockClasses += ' acf-block-preview';

	if ( validationErrors && showValidationErrors ) {
		blockClasses += ' acf-block-has-validation-error';
	}

	const blockProps = {
		...useBlockProps( { className: blockClasses, ref: previewRef } ),
	};

	// Update field value from contentEditable changes (matches 6.7.0.2)
	const updateFieldValueFromContentEditable = ( content, fieldSlug ) => {
		if ( ! acfFormRef?.current || ! fieldSlug ) return;

		const fieldWrapper = acfFormRef.current.querySelector(
			`[data-name=${ fieldSlug }]`
		);
		if ( ! fieldWrapper ) return;

		const fieldKey =
			fieldWrapper.attributes.getNamedItem( 'data-key' )?.value;
		if ( ! fieldKey ) return;

		const fieldInput = acfFormRef.current.querySelector(
			`[name="acf-block_${ clientId }[${ fieldKey }]"`
		);
		if ( ! fieldInput ) return;

		// Update field value and trigger serialization (debouncing happens in useEffect)
		if ( content ) {
			setUserHasInteractedWithForm( true );
		}
		setContentEditableChangeInProgress( false );
		fieldInput.value = content;

		const $form = $( acfFormRef?.current );
		const serializedData = acf.serialize(
			$form,
			`acf-block_${ clientId }`
		);
		if ( serializedData ) {
			setTheSerializedAcfData( JSON.stringify( serializedData ) );
		} else {
			setUserHasInteractedWithForm( false );
		}
	};

	// Watch for changes in contentEditable fields using MutationObserver
	useEffect( () => {
		if ( ! gutenbergIframeOrDocument || ! blockPreviewHtml ) return;

		const observer = new MutationObserver( ( mutations ) => {
			for ( const mutation of mutations ) {
				// Handle text content changes
				if ( mutation.type === 'characterData' ) {
					let element = mutation.target.parentNode;
					const blockElement = element?.closest( '[data-block]' );
					const blockId = blockElement?.getAttribute( 'data-block' );

					if ( ! element || ! blockElement || blockId !== clientId )
						return;

					// Find the contentEditable element
					if (
						element &&
						! element.hasAttribute(
							'data-acf-inline-contenteditable'
						)
					) {
						element = element.closest(
							'[data-acf-inline-contenteditable]'
						);
					}

					if (
						element &&
						element.hasAttribute(
							'data-acf-inline-contenteditable'
						)
					) {
						const fieldSlug = element.attributes.getNamedItem(
							'data-acf-inline-contenteditable-field-slug'
						).value;
						let content = element.innerHTML.trim();
						if ( ! content ) content = '';
						updateFieldValueFromContentEditable(
							content,
							fieldSlug
						);
					}
				}
				// Handle attribute or child list changes
				else {
					const element = mutation.target.closest(
						'[data-acf-inline-contenteditable]'
					);
					const blockElement = element?.closest( '[data-block]' );

					if (
						! element ||
						! blockElement ||
						blockElement.getAttribute( 'data-block' ) !== clientId
					)
						return;

					if ( element ) {
						const fieldSlug = element.attributes.getNamedItem(
							'data-acf-inline-contenteditable-field-slug'
						).value;
						let content = element.innerHTML.trim();

						// Handle empty content - remove empty BR tags
						if (
							! content ||
							( element.textContent.trim().length === 0 &&
								element.children.length === 1 &&
								element.firstElementChild &&
								element.firstElementChild.nodeName === 'BR' )
						) {
							element.innerHTML = '';
							content = '';
						}

						updateFieldValueFromContentEditable(
							content,
							fieldSlug
						);
					}
				}
			}
		} );

		// Observe the gutenberg iframe/document for changes
		observer.observe( gutenbergIframeOrDocument, {
			attributes: true,
			childList: true,
			subtree: true,
			characterData: true,
			attributeFilter: [ 'data-acf-inline-contenteditable' ],
		} );

		// Cleanup
		return () => {
			observer.disconnect();
		};
	}, [ blockPreviewHtml, gutenbergIframeOrDocument ] );

	// Callback when a new inline editing element is selected
	const handleNewInlineEditingElementSelected = ( uid ) => {
		setTimeout( () => {
			setCurrentInlineEditingElementUid( uid );
			const element = previewRef?.current.querySelector(
				`[data-acf-inline-fields-uid="${ uid }"]`
			);
			setCurrentInlineEditingElement( element );
			if ( element ) {
				element.scrollIntoView( {
					behavior: 'smooth',
					block: 'nearest',
				} );
			}
		}, 1 );
	};

	// Callback when a new contentEditable element is selected
	const handleNewContentEditableElementSelected = ( fieldSlug ) => {
		if ( fieldSlug ) {
			const element = previewRef?.current.querySelector(
				`[data-acf-inline-contenteditable-field-slug="${ fieldSlug }"]`
			);
			setCurrentContentEditableElement( element );
		} else {
			setCurrentContentEditableElement( null );
		}
	};

	let portalTarget =
		blockEditorInspectorSidebarOpen && inspectorControlsRef?.current
			? inspectorControlsRef.current
			: invisibleBlockFormContainer;

	if ( currentFormContainer ) {
		portalTarget = currentFormContainer;
	}

	// Determine inline editing toolbar anchor (matches 6.7.0.2 logic)
	let inlineEditingToolbarAnchor = null;
	if ( currentInlineEditingElement && currentContentEditableElement ) {
		inlineEditingToolbarAnchor = currentInlineEditingElement;
	} else if (
		currentInlineEditingElement &&
		! currentContentEditableElement
	) {
		inlineEditingToolbarAnchor = currentInlineEditingElement;
	} else if (
		! currentInlineEditingElement &&
		currentContentEditableElement
	) {
		inlineEditingToolbarAnchor = currentContentEditableElement;
	}
	// Ensure anchor is connected to DOM
	if (
		inlineEditingToolbarAnchor &&
		! inlineEditingToolbarAnchor.isConnected
	) {
		inlineEditingToolbarAnchor = null;
	}

	return (
		<>
			{ /* Block toolbar controls with inline editing support */ }
			<BlockToolbarFields
				blockToolbarFields={ blockToolbarFields }
				blockFieldInfo={ blockFieldInfo }
				setCurrentBlockFormContainer={ setCurrentFormContainer }
				gutenbergIframeOrDocument={ gutenbergIframeOrDocument }
				setBlockFormModalOpen={ setBlockFormModalOpen }
				blockFormModalOpen={ blockFormModalOpen }
				invisibleBlockFormContainer={ invisibleBlockFormContainer }
				currentInlineEditingElement={ currentInlineEditingElement }
				setCurrentInlineEditingElement={
					setCurrentInlineEditingElement
				}
				currentInlineEditingElementUid={
					currentInlineEditingElementUid
				}
				hideExpandedEditorBtnInToolbar={
					blockType?.expanded_editor_buttons === false ||
					( Array.isArray( blockType?.expanded_editor_buttons ) &&
						! blockType?.expanded_editor_buttons.includes(
							'toolbar'
						) ) ||
					! blockFieldInfo ||
					blockFieldInfo?.length === 0
				}
				onNewInlineEditingElementSelected={
					handleNewInlineEditingElementSelected
				}
			/>

			{ /* Inspector panel container */ }
			<InspectorControls>
				{ blockFieldInfo?.length > 0 &&
					( blockType?.expanded_editor_buttons === true ||
						( Array.isArray( blockType?.expanded_editor_buttons ) &&
							blockType?.expanded_editor_buttons.includes(
								'sidebar'
							) ) ) && (
						<PanelBody>
							<Button
								className="acf-blocks-open-expanded-editor-btn"
								variant="secondary"
								onClick={ () => {
									setBlockFormModalOpen( true );
								} }
								icon="edit"
							>
								{ blockType?.expanded_editor_button_text ||
									acf.__( 'Open Expanded Editor' ) }
							</Button>
						</PanelBody>
					) }
				<InspectorBlockFormContainer
					inspectorBlockFormRef={ inspectorControlsRef }
					setCurrentBlockFormContainer={ setCurrentFormContainer }
				/>
			</InspectorControls>

			{ /* Render form via portal when container is available */ }
			{ portalTarget &&
				canRenderForm &&
				createPortal(
					<>
						<BlockForm
							$={ $ }
							clientId={ clientId }
							blockFormHtml={ blockFormHtml }
							onChange={ function ( $form ) {
								const serializedData = acf.serialize(
									$form,
									`acf-block_${ clientId }`
								);
								if ( serializedData ) {
									setTheSerializedAcfData(
										JSON.stringify( serializedData )
									);
								}
							} }
							validationErrors={ validationErrors }
							showValidationErrors={ showValidationErrors }
							acfFormRef={ acfFormRef }
							userHasInteractedWithForm={
								userHasInteractedWithForm
							}
							attributes={ attributes }
							hideFieldsInSidebar={
								( blockType?.auto_inline_editing &&
									blockType?.hide_fields_in_sidebar ===
										undefined &&
									inspectorControlsRef.current ===
										currentFormContainer ) ||
								( blockType?.hide_fields_in_sidebar &&
									inspectorControlsRef.current ===
										currentFormContainer )
							}
						/>
						{ freezeInlineToolbarDuringReRender && (
							<style>
								{ `.acf-inline-editing-toolbar{${ freezeInlineToolbarDuringReRender }}` }
							</style>
						) }
					</>,
					portalTarget
				) }
			<>
				{ /* Modal for editing block fields */ }
				{ blockFormModalOpen && (
					<Modal
						className="acf-block-form-modal"
						overlayClassName={ acf.applyFilters(
							'blocks/expanded_editor_overlay_class',
							'acf-expanded-editor-panel-overlay'
						) }
						isFullScreen={ true }
						title={ blockType.title }
						onRequestClose={ () => {
							if ( ! isFetchingBlock || validationErrors ) {
								setBlockFormModalOpen( false );
							}
						} }
						shouldCloseOnEsc={
							! isFetchingBlock || validationErrors
						}
						isDismissible={ false }
						headerActions={ [
							<Button
								key="done"
								variant="primary"
								disabled={
									isFetchingBlock && ! validationErrors
								}
								isBusy={ isFetchingBlock }
								onClick={ () => {
									setCurrentFormContainer( null );
									setBlockFormModalOpen( false );
								} }
							>
								{ acf.__( 'Done' ) }
							</Button>,
						] }
					>
						<div
							className="acf-modal-block-form-container"
							ref={ modalFormContainerRef }
						/>
					</Modal>
				) }
			</>

			{ /* Inline Editing Toolbar */ }
			{ inlineEditingToolbarAnchor && (
				<PopoverWrapper
					key={ currentContentEditableElement }
					focusOnMount={ ( () => {
						const activeElement = document.activeElement;
						return (
							activeElement && activeElement.isContentEditable,
							false
						);
					} )() }
					variant="unstyled"
					anchor={ inlineEditingToolbarAnchor }
					className="acf-inline-editing-toolbar block-editor-block-list__block-popover"
					placement="top-start"
					onClose={ ( event ) => {
						// Don't close if clicking toolbar button
						if (
							event.key !== 'Escape' &&
							event.target.closest( '.acf-toolbar-button' )
						) {
							return false;
						}

						// Handle Escape key
						if ( event.key === 'Escape' ) {
							return (
								! document.querySelector(
									'.acf-inline-fields-popover-inner'
								) &&
								! currentContentEditableElement &&
								( currentInlineEditingElement &&
									currentInlineEditingElement.focus(),
								setCurrentInlineEditingElementUid( null ),
								setCurrentContentEditableElement( null ),
								true )
							);
						}

						// Don't close if clicking on contenteditable element
						if (
							event.target.getAttribute(
								'data-acf-inline-contenteditable'
							)
						) {
							return false;
						}

						// Don't close if clicking inside popover or modal
						const inlineFieldsPopover = event.target.closest(
							'.acf-inline-fields-popover-inner'
						);
						const modal = event.target.closest(
							'.components-modal__content'
						);

						return (
							inlineFieldsPopover ||
								modal ||
								( setCurrentInlineEditingElementUid( null ),
								setCurrentContentEditableElement( null ) ),
							true
						);
					} }
					gutenbergIframeOrDocument={ gutenbergIframeOrDocument }
					hidePrimaryBlockToolbar={ true }
				>
					<InlineEditingToolbar
						key={ currentInlineEditingElementUid }
						blockIcon={ blockType.icon }
						blockFieldInfo={ blockFieldInfo }
						acfFormRef={ acfFormRef }
						setInlineEditingToolbarHasFocus={
							setInlineEditingToolbarHasFocus
						}
						currentContentEditableElement={
							currentContentEditableElement
						}
						currentInlineEditingElement={
							currentInlineEditingElement
						}
						currentInlineEditingElementUid={
							currentInlineEditingElementUid
						}
						gutenbergIframeOrDocument={ gutenbergIframeOrDocument }
						setCurrentBlockFormContainer={ setCurrentFormContainer }
						contentEditableChangeInProgress={
							contentEditableChangeInProgress
						}
					/>
				</PopoverWrapper>
			) }

			{ /* Dynamic styles for inline field highlighting */ }
			{ currentInlineEditingElementUid &&
				acfDynamicStylesElement &&
				createPortal(
					<style>
						{ `
				[data-acf-inline-fields-uid="${ currentInlineEditingElementUid }"]{
					outline: 2px solid var( --wp-admin-theme-color );
					outline-offset: 2px;
				}
			` }
					</style>,
					acfDynamicStylesElement
				) }

			{ /* Block preview */ }
			<>
				<BlockPreview
					blockPreviewHtml={ blockPreviewHtml }
					blockProps={ blockProps }
				>
					<ErrorBoundary
						fallbackRender={ ( { error } ) => (
							<BlockPreviewErrorFallback
								blockLabel={
									blockType?.title || acf.__( 'ACF Block' )
								}
								setBlockFormModalOpen={ setBlockFormModalOpen }
								error={ error }
							/>
						) }
					>
						{ blockPreviewHtml === 'acf-block-preview-no-html' ? (
							<BlockPlaceholder
								setBlockFormModalOpen={ setBlockFormModalOpen }
								blockLabel={ blockType.title }
							/>
						) : null }

						{ /* Show spinner while loading */ }
						{ blockPreviewHtml === 'acf-block-preview-loading' && (
							<Placeholder>
								<Spinner />
							</Placeholder>
						) }

						{ /* Render actual preview HTML */ }
						{ blockPreviewHtml !== 'acf-block-preview-loading' &&
							blockPreviewHtml !== 'acf-block-preview-no-html' &&
							blockPreviewHtml &&
							acf.parseJSX(
								blockPreviewHtml,
								handleNewInlineEditingElementSelected,
								updateFieldValueFromContentEditable,
								handleNewContentEditableElementSelected,
								blockFieldInfo,
								$
							) }
					</ErrorBoundary>
				</BlockPreview>
			</>
		</>
	);
}
