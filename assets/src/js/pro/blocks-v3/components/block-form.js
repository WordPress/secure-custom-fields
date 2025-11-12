/**
 * BlockForm Component
 * Renders the ACF fields form inside a block and handles form changes, validation, and remounting
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import { lockPostSaving, unlockPostSaving } from '../utils/post-locking';

/**
 * BlockForm component
 * Manages ACF field forms within Gutenberg blocks, including validation and change detection
 *
 * @param {Object} props - Component props
 * @param {jQuery} props.$ - jQuery instance
 * @param {string} props.clientId - Block client ID
 * @param {string} props.blockFormHtml - HTML markup for the ACF form
 * @param {Function} props.onMount - Callback when form is mounted
 * @param {Function} props.onChange - Callback when form data changes
 * @param {Array} props.validationErrors - Array of validation error objects
 * @param {boolean} props.showValidationErrors - Whether to display validation errors
 * @param {React.RefObject} props.acfFormRef - Ref to the form container element
 * @param {boolean} props.userHasInteractedWithForm - Whether user has interacted with the form
 * @param {Object} props.attributes - Block attributes
 * @returns {JSX.Element} - Rendered form component
 */
export const BlockForm = ( {
	$,
	clientId,
	blockFormHtml,
	onMount,
	onChange,
	validationErrors,
	showValidationErrors,
	acfFormRef,
	userHasInteractedWithForm,
	attributes,
	hideFieldsInSidebar,
} ) => {
	const [ formHtml, setFormHtml ] = useState( blockFormHtml );
	const [ pendingChange, setPendingChange ] = useState( false );
	const debounceTimer = useRef( null );
	const [ userInteracted, setUserInteracted ] = useState( false );
	const [ initialValuesCaptured, setInitialValuesCaptured ] =
		useState( false );

	// Call onMount when component first mounts
	useEffect( () => {
		onMount();
	}, [] );

	// Trigger onChange when there's a pending change
	useEffect( () => {
		if ( pendingChange ) {
			// For the first change, capture default values even without interaction
			if (
				! initialValuesCaptured ||
				userHasInteractedWithForm ||
				userInteracted
			) {
				onChange( pendingChange );
				setPendingChange( false );
				if ( ! initialValuesCaptured ) {
					setInitialValuesCaptured( true );
				}
			}
		}
	}, [
		pendingChange,
		userHasInteractedWithForm,
		userInteracted,
		initialValuesCaptured,
		setPendingChange,
		onChange,
	] );

	// Update form HTML when blockFormHtml prop changes
	useEffect( () => {
		if ( ! formHtml && blockFormHtml ) {
			setFormHtml( blockFormHtml );
		}
	}, [ blockFormHtml ] );

	// Handle validation errors
	useEffect( () => {
		if ( ! acfFormRef?.current ) return;

		const validator = acf.getBlockFormValidator(
			$( acfFormRef.current ).find( '.acf-block-fields' )
		);

		validator.clearErrors();
		validator.set( 'notice', null );

		acf.doAction( 'blocks/validation/pre_apply', validationErrors );

		if ( validationErrors ) {
			if ( showValidationErrors ) {
				lockPostSaving( clientId );
				validator.$el.find( '.acf-notice' ).remove();
				validator.addErrors( validationErrors );
				validator.showErrors( 'after' );
			}
		} else {
			// Handle successful validation
			if (
				validator.$el.find( '.acf-notice' ).length > 0 &&
				showValidationErrors
			) {
				validator.$el.find( '.acf-notice' ).remove();
				validator.addErrors( [
					{ message: acf.__( 'Validation successful' ) },
				] );
				validator.showErrors( 'after' );
				validator.get( 'notice' ).update( {
					type: 'success',
					text: acf.__( 'Validation successful' ),
					timeout: 1000,
				} );
				validator.set( 'notice', null );

				setTimeout( () => {
					validator.$el.find( '.acf-notice' ).remove();
				}, 1001 );

				const noticeDispatch = wp.data.dispatch( 'core/notices' );

				/**
				 * Recursively checks for ACF errors in blocks
				 * @param {Array} blocks - Array of block objects
				 * @returns {Promise<boolean>} - True if error found
				 */
				function checkForErrors( blocks ) {
					return new Promise( function ( resolve ) {
						blocks.forEach( ( block ) => {
							if ( block.innerBlocks.length > 0 ) {
								checkForErrors( block.innerBlocks ).then(
									( hasError ) => {
										if ( hasError ) return resolve( true );
									}
								);
							}

							if (
								block.attributes.hasAcfError &&
								block.clientId !== clientId
							) {
								return resolve( true );
							}
						} );
						return resolve( false );
					} );
				}

				checkForErrors(
					wp.data.select( 'core/block-editor' ).getBlocks()
				).then( ( hasError ) => {
					if ( hasError ) {
						noticeDispatch.createErrorNotice(
							acf.__(
								'An ACF Block on this page requires attention before you can save.'
							),
							{ id: 'acf-blocks-validation', isDismissible: true }
						);
					} else {
						noticeDispatch.removeNotice( 'acf-blocks-validation' );
					}
				} );
			}

			unlockPostSaving( clientId );
		}

		acf.doAction( 'blocks/validation/post_apply', validationErrors );
	}, [ validationErrors, clientId, showValidationErrors ] );

	// Handle form remounting and change detection
	useEffect( () => {
		if ( ! acfFormRef?.current || ! formHtml ) return;

		acf.debug( 'Remounting ACF Form' );

		const formElement = acfFormRef.current;
		const $form = $( formElement );
		let isActive = true;

		acf.doAction( 'remount', $form );
		if ( ! initialValuesCaptured ) {
			onChange( $form );
			setInitialValuesCaptured( true );
		}

		const handleChange = () => {
			onChange( $form );
		};

		const scheduleChange = () => {
			if ( ! isActive ) return;

			const inputs = formElement.querySelectorAll( 'input, textarea' );
			const selects = formElement.querySelectorAll( 'select' );

			inputs.forEach( ( input ) => {
				input.removeEventListener( 'input', handleChange );
				input.addEventListener( 'input', handleChange );
			} );

			selects.forEach( ( select ) => {
				select.removeEventListener( 'change', handleChange );
				select.addEventListener( 'change', handleChange );
			} );

			clearTimeout( debounceTimer.current );
			debounceTimer.current = setTimeout( () => {
				if ( isActive ) {
					setPendingChange( $form );
				}
			}, 300 );
		};

		// Observe DOM changes to detect field additions/removals
		const domObserver = new MutationObserver( scheduleChange );

		// Observe iframe content changes (for WYSIWYG editors)
		const iframeObserver = new MutationObserver( () => {
			if ( isActive ) {
				setUserInteracted( true );
				scheduleChange();
			}
		} );

		const observerConfig = {
			attributes: true,
			childList: true,
			subtree: true,
			characterData: true,
		};

		domObserver.observe( formElement, observerConfig );

		// Watch for changes in iframes (WYSIWYG fields)
		[ ...formElement.querySelectorAll( 'iframe' ) ].forEach( ( iframe ) => {
			if ( iframe && iframe.contentDocument ) {
				const iframeBody = iframe.contentDocument.body;
				if ( iframeBody ) {
					iframeObserver.observe( iframeBody, observerConfig );
				}
			}
		} );

		// Attach event listeners to form inputs
		formElement
			.querySelectorAll( 'input, textarea' )
			.forEach( ( input ) => {
				input.addEventListener( 'input', handleChange );
			} );

		formElement.querySelectorAll( 'select' ).forEach( ( select ) => {
			select.addEventListener( 'change', handleChange );
		} );

		// Cleanup function
		return () => {
			isActive = false;
			domObserver.disconnect();
			iframeObserver.disconnect();
			clearTimeout( debounceTimer.current );

			if ( formElement ) {
				formElement
					.querySelectorAll( 'input, textarea' )
					.forEach( ( input ) => {
						input.removeEventListener( 'input', handleChange );
					} );

				formElement
					.querySelectorAll( 'select' )
					.forEach( ( select ) => {
						select.removeEventListener( 'change', handleChange );
					} );
			}
		};
	}, [ acfFormRef, attributes, formHtml ] );

	return (
		<div
			ref={ acfFormRef }
			className="acf-block-component acf-block-panel"
			style={ { display: hideFieldsInSidebar ? 'none' : null } }
			dangerouslySetInnerHTML={ {
				__html: acf.applyFilters(
					'blocks/form/render',
					formHtml,
					true
				),
			} }
		/>
	);
};
