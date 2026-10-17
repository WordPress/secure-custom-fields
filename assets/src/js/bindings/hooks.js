/**
 * Custom hooks for block bindings
 */

import { useState, useEffect, useMemo, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';
import { store as editorStore } from '@wordpress/editor';

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
 * @return {Object} Fields, loading/error state, and an explicit refresh callback.
 */
export function useSiteEditorFields( postType ) {
	const args = useMemo(
		() => [ 'root', 'postType', postType, { context: 'edit' } ],
		[ postType ]
	);
	const { invalidateResolution } = useDispatch( coreDataStore );
	const { record, isLoading, error } = useSelect(
		( select ) => {
			if ( ! postType ) {
				return { record: null, isLoading: false, error: null };
			}

			const core = select( coreDataStore );
			const postTypeRecord = core.getEntityRecord( ...args );
			const resolutionError =
				core.getResolutionError( 'getEntityRecord', args ) || null;

			return {
				record: postTypeRecord,
				isLoading:
					! resolutionError &&
					! core.hasFinishedResolution( 'getEntityRecord', args ),
				error: resolutionError,
			};
		},
		[ postType, args ]
	);
	const { fields, error: fieldsError } = useMemo( () => {
		try {
			return {
				fields: formatFieldGroupsData(
					! isLoading && ! error ? record?.scf_field_groups : null
				),
				error,
			};
		} catch ( formatError ) {
			return { fields: {}, error: formatError };
		}
	}, [ record, isLoading, error ] );

	useEffect( () => {
		if ( record && ! isLoading && ! fieldsError ) {
			addFieldMetadata( fields );
		}
	}, [ record, fields, isLoading, fieldsError ] );

	// One consumer must not cancel work shared by the rest of the registry.
	// Explicit invalidation also permits retry without a per-mount retry storm.
	const refresh = useCallback( () => {
		if ( postType ) {
			invalidateResolution( 'getEntityRecord', args );
		}
	}, [ postType, args, invalidateResolution ] );

	return { fields, isLoading, error: fieldsError, refresh };
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
