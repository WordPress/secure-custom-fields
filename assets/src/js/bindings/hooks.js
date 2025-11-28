/**
 * Custom hooks for block bindings
 */

import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';
import { store as editorStore } from '@wordpress/editor';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

import { extractPostTypeFromTemplate, formatFieldGroupsData } from './utils';
import { addFieldMetadata } from './fieldMetadataCache';

/**
 * Custom hook to detect if we're in the site editor and get the template info.
 *
 * @since 6.7.0
 * @return {Object} Object containing isSiteEditor flag and templatePostType.
 */
export function useSiteEditorContext() {
	return useSelect( ( select ) => {
		const { getCurrentPostType, getCurrentPostId } = select( editorStore );
		const { getEditedEntityRecord } = select( coreDataStore );

		const postType = getCurrentPostType();
		const postId = getCurrentPostId();

		const isSiteEditor = postType === 'wp_template';

		if ( ! isSiteEditor ) {
			return {
				isSiteEditor: false,
				templatePostType: null,
			};
		}

		const template = getEditedEntityRecord(
			'postType',
			'wp_template',
			postId
		);

		const templatePostType = extractPostTypeFromTemplate(
			template?.slug || ''
		);

		return {
			isSiteEditor: true,
			templatePostType,
		};
	}, [] );
}

/**
 * Custom hook to get SCF fields for the current post editor context.
 *
 * @since 6.7.0
 * @return {Object} Object containing the fields map.
 */
export function usePostEditorFields() {
	return useSelect( ( select ) => {
		const { getCurrentPostType, getCurrentPostId } = select( editorStore );
		const { getEditedEntityRecord } = select( coreDataStore );

		const postType = getCurrentPostType();
		const postId = getCurrentPostId();

		if ( ! postType || ! postId || postType === 'wp_template' ) {
			return {};
		}

		const record = getEditedEntityRecord( 'postType', postType, postId );

		// Extract fields that have '_source' counterparts
		const sourcedFields = {};
		if ( record?.acf ) {
			Object.entries( record.acf ).forEach( ( [ key, value ] ) => {
				if ( key.endsWith( '_source' ) ) {
					const baseFieldName = key.replace( '_source', '' );
					if ( Object.hasOwn( record.acf, baseFieldName ) ) {
						sourcedFields[ baseFieldName ] = value;
					}
				}
			} );
		}

		return sourcedFields;
	}, [] );
}

/**
 * Custom hook to fetch and manage SCF field groups from the REST API.
 *
 * @since 6.7.0
 * @param {string|null} postType The post type to fetch fields for.
 * @return {Object} Object containing fields, isLoading, and error.
 */
export function useSiteEditorFields( postType ) {
	const [ fields, setFields ] = useState( {} );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		if ( ! postType ) {
			setFields( {} );
			setIsLoading( false );
			setError( null );
			return;
		}

		let isCancelled = false;
		setIsLoading( true );
		setError( null );

		const fetchFields = async () => {
			try {
				const path = addQueryArgs( `/wp/v2/types/${ postType }`, {
					context: 'edit',
				} );

				const postTypeData = await apiFetch( { path } );

				if ( isCancelled ) {
					return;
				}

				const fieldsMap = formatFieldGroupsData(
					postTypeData.scf_field_groups
				);

				// Store field metadata in the data store
				addFieldMetadata( fieldsMap );

				setFields( fieldsMap );
				setIsLoading( false );
			} catch ( err ) {
				if ( ! isCancelled ) {
					setError( err );
					setIsLoading( false );
				}
			}
		};

		fetchFields();

		// Cleanup function to prevent state updates after unmount
		return () => {
			isCancelled = true;
		};
	}, [ postType ] );

	return { fields, isLoading, error };
}

/**
 * Custom hook to manage block bindings state.
 *
 * @since 6.7.0
 * @param {Object} blockAttributes The block attributes object.
 * @return {Object} Object containing boundFields and sync function.
 */
export function useBoundFields( blockAttributes ) {
	const [ boundFields, setBoundFields ] = useState( {} );

	useEffect( () => {
		const currentBindings = blockAttributes?.metadata?.bindings || {};
		const newBoundFields = {};

		Object.keys( currentBindings ).forEach( ( attribute ) => {
			if ( currentBindings[ attribute ]?.args?.key ) {
				newBoundFields[ attribute ] =
					currentBindings[ attribute ].args.key;
			}
		} );

		setBoundFields( newBoundFields );
	}, [ blockAttributes?.metadata?.bindings ] );

	return { boundFields, setBoundFields };
}
