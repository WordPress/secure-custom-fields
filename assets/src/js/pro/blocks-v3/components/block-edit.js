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
	BlockControls,
	InspectorControls,
	useBlockProps,
	useBlockEditContext,
} from '@wordpress/block-editor';
import {
	Button,
	ToolbarGroup,
	ToolbarButton,
	Placeholder,
	Spinner,
	Modal,
} from '@wordpress/components';
import { BlockPlaceholder } from './block-placeholder';
import { BlockForm } from './block-form';
import { BlockPreview } from './block-preview';
import { ErrorBoundary, BlockPreviewErrorFallback } from './error-boundary';
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
	const [ blockFormHtml, setBlockFormHtml ] = useState( '' );
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

	const acfFormRef = useRef( null );
	const previewRef = useRef( null );
	const debounceRef = useRef( null );

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

				setBlockFormHtml( response.data.form );

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
			setBlockFormHtml( data.html );
		} else if ( data.html ) {
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

		fetchBlockData( {
			theAttributes: attributes,
			theClientId: clientId,
			theContext: context,
			isSelected: isSelected,
		} );

		window.addEventListener( 'click', trackUserInteraction );
		window.addEventListener( 'keydown', trackUserInteraction );

		return () => {
			window.removeEventListener( 'click', trackUserInteraction );
			window.removeEventListener( 'keydown', trackUserInteraction );
		};
	}, [] );

	// Update hasAcfError attribute based on validation errors
	useEffect( () => {
		setAttributes(
			validationErrors ? { hasAcfError: true } : { hasAcfError: false }
		);
	}, [ validationErrors, setAttributes ] );

	// Listen for validation error events from other blocks
	useEffect( () => {
		const handleErrorEvent = () => {
			lockPostSaving( clientId );
			setShowValidationErrors( true );
		};

		document.addEventListener( 'acf/block/has-error', handleErrorEvent );

		return () => {
			document.removeEventListener(
				'acf/block/has-error',
				handleErrorEvent
			);
			unlockPostSaving( clientId );
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
		}
	}, [ blockPreviewHtml ] );

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
		previewRef,
		hasFetchedOnce,
	} = props;

	const { clientId } = useBlockEditContext();
	const inspectorControlsRef = useRef();
	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const modalFormContainerRef = useRef();
	const [ currentFormContainer, setCurrentFormContainer ] = useState();

	// Set current form container when modal opens
	useEffect( () => {
		if ( isModalOpen && modalFormContainerRef?.current ) {
			setCurrentFormContainer( modalFormContainerRef.current );
		}
	}, [ isModalOpen, modalFormContainerRef ] );

	// Update form container when inspector panel is available
	useEffect( () => {
		if ( isSelected && inspectorControlsRef?.current ) {
			setCurrentFormContainer( inspectorControlsRef.current );
		} else if ( isSelected && ! inspectorControlsRef?.current ) {
			// Wait for inspector to be available
			setTimeout( () => {
				setCurrentFormContainer( inspectorControlsRef.current );
			}, 1 );
		} else if ( ! isSelected ) {
			setCurrentFormContainer( null );
		}
	}, [ isSelected, inspectorControlsRef, inspectorControlsRef.current ] );

	useEffect( () => {
		if (
			isSelected &&
			validationErrors &&
			showValidationErrors &&
			blockType?.hide_fields_in_sidebar
		) {
			setIsModalOpen( true );
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

	// Determine portal target
	let portalTarget = null;
	if ( currentFormContainer ) {
		portalTarget = currentFormContainer;
	} else if ( inspectorControlsRef?.current ) {
		portalTarget = inspectorControlsRef.current;
	}

	return (
		<>
			{ /* Block toolbar controls */ }
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						className="components-icon-button components-toolbar__control"
						label={ acf.__( 'Edit Block' ) }
						icon="edit"
						onClick={ () => {
							setIsModalOpen( true );
						} }
					/>
				</ToolbarGroup>
			</BlockControls>

			{ /* Inspector panel container */ }
			<InspectorControls>
				<div style={ { padding: '16px' } }>
					<Button
						className="acf-blocks-open-expanded-editor-btn"
						variant="secondary"
						onClick={ () => {
							setIsModalOpen( true );
						} }
						text={ acf.__( 'Open Expanded Editor' ) }
						icon="edit"
					/>
				</div>
				<InspectorBlockFormContainer
					inspectorBlockFormRef={ inspectorControlsRef }
					setCurrentBlockFormContainer={ setCurrentFormContainer }
				/>
			</InspectorControls>

			{ /* Render form via portal when container is available */ }
			{ portalTarget &&
				createPortal(
					<>
						<BlockForm
							$={ $ }
							clientId={ clientId }
							blockFormHtml={ blockFormHtml }
							onMount={ () => {
								if ( ! hasFetchedOnce ) {
									blockFetcher( {
										theAttributes: attributes,
										theClientId: clientId,
										theContext: context,
										isSelected: isSelected,
									} );
								}
							} }
							onChange={ function ( $form ) {
								const serializedData = acf.serialize(
									$form,
									`acf-block_${ clientId }`
								);
								if ( serializedData ) {
									// Normalize flexible content data for validation
									const normalizedData =
										acf.normalizeFlexibleContentData(
											serializedData
										);
									setTheSerializedAcfData(
										JSON.stringify( normalizedData )
									);
								}
							} }
							validationErrors={ validationErrors }
							showValidationErrors={ showValidationErrors }
							acfFormRef={ acfFormRef }
							theSerializedAcfData={ theSerializedAcfData }
							userHasInteractedWithForm={
								userHasInteractedWithForm
							}
							setCurrentBlockFormContainer={
								setCurrentFormContainer
							}
							attributes={ attributes }
							hideFieldsInSidebar={
								blockType?.hide_fields_in_sidebar &&
								( ! currentFormContainer ||
									inspectorControlsRef.current ===
										currentFormContainer )
							}
						/>
					</>,
					currentFormContainer || inspectorControlsRef.current
				) }
			<>
				{ /* Modal for editing block fields */ }
				{ isModalOpen && (
					<Modal
						className="acf-block-form-modal"
						isFullScreen={ true }
						title={ blockType.title }
						onRequestClose={ () => {
							setCurrentFormContainer( null );
							setIsModalOpen( false );
						} }
					>
						<div
							className="acf-modal-block-form-container"
							ref={ modalFormContainerRef }
						/>
					</Modal>
				) }
			</>

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
								setBlockFormModalOpen={ setIsModalOpen }
								error={ error }
							/>
						) }
						onError={ ( error, errorInfo ) => {
							acf.debug(
								'Block preview error caught:',
								error,
								errorInfo
							);
						} }
						resetKeys={ [ blockPreviewHtml ] }
						onReset={ ( { reason, next, prev } ) => {
							acf.debug( 'Error boundary reset:', reason );
							if ( reason === 'keys' ) {
								acf.debug(
									'Preview HTML changed from',
									prev,
									'to',
									next
								);
							}
						} }
					>
						{ /* Show placeholder when no HTML */ }
						{ blockPreviewHtml === 'acf-block-preview-no-html' ? (
							<BlockPlaceholder
								setBlockFormModalOpen={ setIsModalOpen }
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
							acf.parseJSX( blockPreviewHtml ) }
					</ErrorBoundary>
				</BlockPreview>
			</>
		</>
	);
}
