/* global acf, ajaxurl, jQuery */
( () => {
	'use strict';

	const STORE_NAME = 'acf/fields';
	const COMPLEX_FIELD_TYPES = [
		'repeater',
		'group',
		'flexible_content',
		'clone',
	];
	const AJAX_LOOKUP_FIELD_TYPES = new Set( [
		'post_object',
		'page_link',
		'relationship',
		'taxonomy',
		'user',
	] );
	const REPEATER_ROW_STATUS_NAME_PATTERN = new RegExp(
		`\\[acf_(${ [
			'added',
			'changed',
			'deleted',
			'reordered',
			'inserted',
		].join( '|' ) })]$`
	);

	const DEFAULT_STATE = {
		context: { postId: 0, postType: '' },
		fields: {},
		values: {},
		savedValues: {},
		nameToKey: {},
		fieldGroups: [],
		initialized: false,
		syncing: false,
	};

	const cloneValue = ( value ) => {
		if ( null === value || 'object' !== typeof value ) {
			return value;
		}

		return JSON.parse( JSON.stringify( value ) );
	};

	const isComplexFieldType = ( type ) => COMPLEX_FIELD_TYPES.includes( type );

	const buildNameToKeyMap = ( fields ) => {
		const nameToKey = {};

		for ( const fieldKey of Object.keys( fields ) ) {
			const field = fields[ fieldKey ];
			if ( field.name ) {
				nameToKey[ field.name ] = fieldKey;
			}
		}

		return nameToKey;
	};

	const resolveFieldKey = ( state, keyOrName ) => {
		if ( ! keyOrName ) {
			return undefined;
		}

		return state.fields[ keyOrName ]
			? keyOrName
			: state.nameToKey[ keyOrName ];
	};

	const getNestedValue = ( value, path ) => {
		let currentValue = value;

		for ( const pathPart of path ) {
			if ( null === currentValue || undefined === currentValue ) {
				return undefined;
			}
			currentValue = currentValue[ pathPart ];
		}

		return currentValue;
	};

	const setNestedValue = ( value, path, nextValue ) => {
		if ( 0 === path.length ) {
			return nextValue;
		}

		const pathPart = path[ 0 ];
		const currentValue = value ?? {};
		const clonedValue = Array.isArray( currentValue )
			? [ ...currentValue ]
			: { ...currentValue };

		clonedValue[ pathPart ] = setNestedValue(
			clonedValue[ pathPart ],
			path.slice( 1 ),
			nextValue
		);

		return clonedValue;
	};

	const reducer = ( state = DEFAULT_STATE, action ) => {
		switch ( action.type ) {
			case 'INITIALIZE_STORE': {
				const fields = action.fields ?? {};
				const values = action.values ?? {};

				return {
					...state,
					context: action.context ?? state.context,
					fields,
					values,
					savedValues: cloneValue( values ),
					nameToKey: buildNameToKeyMap( fields ),
					fieldGroups: action.fieldGroups ?? [],
					initialized: true,
				};
			}

			case 'REGISTER_FIELD_GROUP': {
				const fields = { ...state.fields, ...( action.fields ?? {} ) };
				const incomingValues = action.values ?? {};
				const values = { ...state.values };
				const savedValues = { ...state.savedValues };

				for ( const fieldKey of Object.keys( incomingValues ) ) {
					if ( ! ( fieldKey in values ) ) {
						values[ fieldKey ] = incomingValues[ fieldKey ];
					}
					if ( ! ( fieldKey in savedValues ) ) {
						savedValues[ fieldKey ] = cloneValue(
							incomingValues[ fieldKey ]
						);
					}
				}

				const existingGroupKeys = new Set(
					state.fieldGroups.map( ( fieldGroup ) => fieldGroup.key )
				);
				const fieldGroups = ( action.fieldGroups ?? [] ).filter(
					( fieldGroup ) => ! existingGroupKeys.has( fieldGroup.key )
				);
				const context =
					0 === state.context.postId && action.context
						? action.context
						: state.context;

				return {
					...state,
					context,
					fields,
					values,
					savedValues,
					nameToKey: buildNameToKeyMap( fields ),
					fieldGroups: [ ...state.fieldGroups, ...fieldGroups ],
					initialized: true,
				};
			}

			case 'SET_FIELD_VALUE': {
				const fieldKey = resolveFieldKey( state, action.fieldKey );
				if ( ! fieldKey ) {
					return state;
				}

				return {
					...state,
					values: { ...state.values, [ fieldKey ]: action.value },
				};
			}

			case 'SET_VALUES': {
				const values = {};

				for ( const keyOrName of Object.keys( action.values ) ) {
					const fieldKey = resolveFieldKey( state, keyOrName );
					if ( fieldKey ) {
						values[ fieldKey ] = action.values[ keyOrName ];
					}
				}

				return { ...state, values: { ...state.values, ...values } };
			}

			case 'SET_SUB_FIELD_VALUE': {
				const parentKey = resolveFieldKey( state, action.parentKey );
				if ( ! parentKey ) {
					return state;
				}

				const parentValue = state.values[ parentKey ] ?? {};
				const nextParentValue = setNestedValue(
					parentValue,
					action.path,
					action.value
				);

				return {
					...state,
					values: { ...state.values, [ parentKey ]: nextParentValue },
				};
			}

			case 'ADD_REPEATER_ROW': {
				const fieldKey = resolveFieldKey( state, action.fieldKey );
				if ( ! fieldKey ) {
					return state;
				}

				const currentRows = Array.isArray( state.values[ fieldKey ] )
					? state.values[ fieldKey ]
					: [];
				const rows = [ ...currentRows ];
				const index = Math.max(
					0,
					Math.min( action.index ?? rows.length, rows.length )
				);

				rows.splice( index, 0, action.rowData ?? {} );

				return {
					...state,
					values: { ...state.values, [ fieldKey ]: rows },
				};
			}

			case 'REMOVE_REPEATER_ROW': {
				const fieldKey = resolveFieldKey( state, action.fieldKey );
				if ( ! fieldKey ) {
					return state;
				}

				const rows = state.values[ fieldKey ];
				if ( ! Array.isArray( rows ) ) {
					return state;
				}
				if ( action.rowIndex < 0 || action.rowIndex >= rows.length ) {
					return state;
				}

				const nextRows = [ ...rows ];
				nextRows.splice( action.rowIndex, 1 );

				return {
					...state,
					values: { ...state.values, [ fieldKey ]: nextRows },
				};
			}

			case 'MOVE_REPEATER_ROW': {
				const fieldKey = resolveFieldKey( state, action.fieldKey );
				if ( ! fieldKey ) {
					return state;
				}

				const rows = state.values[ fieldKey ];
				if ( ! Array.isArray( rows ) ) {
					return state;
				}
				if (
					action.fromIndex < 0 ||
					action.fromIndex >= rows.length ||
					action.toIndex < 0 ||
					action.toIndex >= rows.length
				) {
					return state;
				}

				const nextRows = [ ...rows ];
				const [ movedRow ] = nextRows.splice( action.fromIndex, 1 );
				nextRows.splice( action.toIndex, 0, movedRow );

				return {
					...state,
					values: { ...state.values, [ fieldKey ]: nextRows },
				};
			}

			case 'MARK_AS_SAVED':
				return { ...state, savedValues: cloneValue( state.values ) };

			case 'SET_SYNCING':
				return { ...state, syncing: action.isSyncing };

			default:
				return state;
		}
	};

	const selectors = {
		getFieldValue: ( state, keyOrName ) => {
			const fieldKey = resolveFieldKey( state, keyOrName );
			return fieldKey ? state.values[ fieldKey ] : undefined;
		},
		getFieldValueByKey: ( state, fieldKey ) => state.values[ fieldKey ],
		getFieldValueByName: ( state, fieldName ) => {
			const fieldKey = state.nameToKey[ fieldName ];
			return fieldKey ? state.values[ fieldKey ] : undefined;
		},
		getAllValues: ( state ) => state.values,
		getAllValuesByName: ( state ) => {
			const valuesByName = {};

			for ( const fieldName of Object.keys( state.nameToKey ) ) {
				const fieldKey = state.nameToKey[ fieldName ];
				if ( fieldKey in state.values ) {
					valuesByName[ fieldName ] = state.values[ fieldKey ];
				}
			}

			return valuesByName;
		},
		getChangedValues: ( state ) => {
			const changedValues = {};

			for ( const fieldKey of Object.keys( state.values ) ) {
				if (
					JSON.stringify( state.values[ fieldKey ] ) !==
					JSON.stringify( state.savedValues[ fieldKey ] )
				) {
					changedValues[ fieldKey ] = state.values[ fieldKey ];
				}
			}

			return changedValues;
		},
		getField: ( state, keyOrName ) => {
			const fieldKey = resolveFieldKey( state, keyOrName );
			return fieldKey ? state.fields[ fieldKey ] : undefined;
		},
		getFields: ( state ) => state.fields,
		getFieldsByGroup: ( state, fieldGroupKey ) => {
			const fields = {};

			for ( const fieldKey of Object.keys( state.fields ) ) {
				if (
					state.fields[ fieldKey ].fieldGroupKey === fieldGroupKey
				) {
					fields[ fieldKey ] = state.fields[ fieldKey ];
				}
			}

			return fields;
		},
		getFieldKeyByName: ( state, fieldName ) => state.nameToKey[ fieldName ],
		getSubFieldValue: ( state, parentKey, ...path ) => {
			const fieldKey = resolveFieldKey( state, parentKey );
			return fieldKey
				? getNestedValue( state.values[ fieldKey ], path )
				: undefined;
		},
		isInitialized: ( state ) => state.initialized,
		isSyncing: ( state ) => state.syncing,
		hasChanges: ( state ) =>
			JSON.stringify( state.values ) !==
			JSON.stringify( state.savedValues ),
		isDirty: ( state, keyOrName ) => {
			const fieldKey = resolveFieldKey( state, keyOrName );

			return !! (
				fieldKey &&
				JSON.stringify( state.values[ fieldKey ] ) !==
					JSON.stringify( state.savedValues[ fieldKey ] )
			);
		},
		getContext: ( state ) => state.context,
		getFieldGroups: ( state ) => state.fieldGroups,
	};

	const actions = {
		initializeStore: ( storeData ) => ( {
			type: 'INITIALIZE_STORE',
			context: storeData.context,
			fields: storeData.fields,
			values: storeData.values,
			fieldGroups: storeData.fieldGroups,
		} ),
		registerFieldGroup: ( storeData ) => ( {
			type: 'REGISTER_FIELD_GROUP',
			context: storeData.context,
			fields: storeData.fields,
			values: storeData.values,
			fieldGroups: storeData.fieldGroups,
		} ),
		setFieldValue: ( fieldKey, value ) => ( {
			type: 'SET_FIELD_VALUE',
			fieldKey,
			value,
		} ),
		setValues: ( values ) => ( { type: 'SET_VALUES', values } ),
		setSubFieldValue: ( parentKey, ...pathAndValue ) => {
			const value = pathAndValue.pop();
			return {
				type: 'SET_SUB_FIELD_VALUE',
				parentKey,
				path: pathAndValue,
				value,
			};
		},
		addRepeaterRow: ( fieldKey, rowData, index ) => ( {
			type: 'ADD_REPEATER_ROW',
			fieldKey,
			rowData,
			index,
		} ),
		removeRepeaterRow: ( fieldKey, rowIndex ) => ( {
			type: 'REMOVE_REPEATER_ROW',
			fieldKey,
			rowIndex,
		} ),
		moveRepeaterRow: ( fieldKey, fromIndex, toIndex ) => ( {
			type: 'MOVE_REPEATER_ROW',
			fieldKey,
			fromIndex,
			toIndex,
		} ),
		markAsSaved: () => ( { type: 'MARK_AS_SAVED' } ),
		setSyncing: ( isSyncing ) => ( { type: 'SET_SYNCING', isSyncing } ),
	};

	if ( window.wp?.data?.createReduxStore ) {
		wp.data.register(
			wp.data.createReduxStore( STORE_NAME, {
				reducer,
				selectors,
				actions,
			} )
		);
	}

	const normalizeLookupValue = ( value ) => {
		if (
			null === value ||
			undefined === value ||
			'' === value ||
			false === value
		) {
			return [];
		}

		if ( Array.isArray( value ) ) {
			return value.filter(
				( item ) => null !== item && undefined !== item && '' !== item
			);
		}

		return [ value ];
	};

	const getMissingLookupValues = ( field, value ) => {
		const fieldType = field.get( 'type' );
		if ( ! AJAX_LOOKUP_FIELD_TYPES.has( fieldType ) ) {
			return [];
		}

		const values = normalizeLookupValue( value );
		if ( ! values.length ) {
			return [];
		}

		if ( 'relationship' === fieldType ) {
			const renderedIds = new Set();
			field.$el.find( '.choices-list .acf-rel-item' ).each( function () {
				renderedIds.add( String( jQuery( this ).data( 'id' ) ) );
			} );

			return values.filter(
				( item ) => ! renderedIds.has( String( item ) )
			);
		}

		const $select = field.$el.find( 'select' ).first();
		if ( ! $select.length ) {
			return [];
		}

		const renderedIds = new Set();
		$select.find( 'option' ).each( function () {
			renderedIds.add( String( jQuery( this ).val() ) );
		} );

		return values.filter( ( item ) => ! renderedIds.has( String( item ) ) );
	};

	const appendLookupOptions = ( field, options ) => {
		if ( ! options.length ) {
			return;
		}

		if ( 'relationship' === field.get( 'type' ) ) {
			const $choicesList = field.$el.find( '.choices-list' ).first();
			if ( ! $choicesList.length ) {
				return;
			}

			for ( const option of options ) {
				const id = String( option.id );
				const alreadyExists = $choicesList
					.find( '.acf-rel-item' )
					.toArray()
					.some(
						( element ) =>
							String( jQuery( element ).data( 'id' ) ) === id
					);

				if ( alreadyExists ) {
					continue;
				}

				const $item = jQuery(
					'<li><span tabindex="0" class="acf-rel-item acf-rel-item-add"></span></li>'
				);
				$item
					.find( '.acf-rel-item' )
					.attr( 'data-id', id )
					.text( option.text );
				$choicesList.append( $item );
			}

			return;
		}

		const $select = field.$el.find( 'select' ).first();
		if ( ! $select.length ) {
			return;
		}

		for ( const option of options ) {
			const id = String( option.id );
			const alreadyExists = $select
				.find( 'option' )
				.toArray()
				.some( ( element ) => String( element.value ) === id );

			if ( ! alreadyExists ) {
				$select.append(
					jQuery( '<option></option>' )
						.attr( 'value', id )
						.text( option.text )
				);
			}
		}
	};

	const extractOptionFromAjaxResponse = ( response, requestedId ) => {
		const results = response?.results;
		if ( ! Array.isArray( results ) ) {
			return null;
		}

		const requestedIdString = String( requestedId );
		for ( const result of results ) {
			if ( ! result || 'object' !== typeof result ) {
				continue;
			}

			if ( Array.isArray( result.children ) ) {
				for ( const child of result.children ) {
					if ( ! child || 'object' !== typeof child ) {
						continue;
					}
					if (
						String( child.id ) === requestedIdString &&
						'string' === typeof child.text
					) {
						return { id: child.id, text: child.text };
					}
				}
			} else if (
				String( result.id ) === requestedIdString &&
				'string' === typeof result.text
			) {
				return { id: result.id, text: result.text };
			}
		}

		return null;
	};

	const fetchLookupOption = async ( field, value ) => {
		const fieldType = field.get( 'type' );
		const fieldKey = field.get( 'key' );
		const nonce = field.get( 'nonce' );

		if ( ! fieldType || ! fieldKey || ! nonce || ! window.ajaxurl ) {
			return null;
		}

		const body = new FormData();
		body.append( 'action', `acf/fields/${ fieldType }/query` );
		body.append( 'field_key', fieldKey );
		body.append( 'nonce', nonce );
		body.append( 'include', String( value ) );

		try {
			const response = await fetch( ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				body,
			} );

			if ( ! response.ok ) {
				return null;
			}

			return extractOptionFromAjaxResponse(
				await response.json(),
				value
			);
		} catch ( error ) {
			return null;
		}
	};

	const readComplexValue = ( field ) => {
		const fieldType = field.get( 'type' );

		if ( 'group' === fieldType || 'clone' === fieldType ) {
			return readGroupValue( field );
		}
		if ( 'repeater' === fieldType ) {
			return readRepeaterValue( field );
		}
		if ( 'flexible_content' === fieldType ) {
			return readFlexibleContentValue( field );
		}

		return undefined;
	};

	const readGroupValue = ( field ) => {
		const value = {};
		const childFields = acf.getFields( { parent: field.$el } );

		for ( const childField of childFields ) {
			const childKey = childField.get( 'key' );
			const childType = childField.get( 'type' );
			value[ childKey ] = isComplexFieldType( childType )
				? readComplexValue( childField )
				: childField.val();
		}

		return value;
	};

	const readRepeaterValue = ( field ) => {
		const value = [];

		field.$el
			.find(
				'> .acf-input > .acf-repeater > table > tbody > tr.acf-row:not(.acf-clone)'
			)
			.each( function () {
				const $row = jQuery( this );
				const rowValue = {};
				const childFields = acf.getFields( { parent: $row } );

				for ( const childField of childFields ) {
					const childKey = childField.get( 'key' );
					const childType = childField.get( 'type' );
					rowValue[ childKey ] = isComplexFieldType( childType )
						? readComplexValue( childField )
						: childField.val();
				}

				value.push( rowValue );
			} );

		return value;
	};

	const readFlexibleContentValue = ( field ) => {
		const value = [];

		field.$el
			.find(
				'> .acf-input > .acf-flexible-content > .values > .layout:not(.acf-clone)'
			)
			.each( function () {
				const $layout = jQuery( this );
				const layoutValue = { acf_fc_layout: $layout.data( 'layout' ) };
				const childFields = acf.getFields( { parent: $layout } );

				for ( const childField of childFields ) {
					const childKey = childField.get( 'key' );
					const childType = childField.get( 'type' );
					layoutValue[ childKey ] = isComplexFieldType( childType )
						? readComplexValue( childField )
						: childField.val();
				}

				value.push( layoutValue );
			} );

		return value;
	};

	const removeLayoutOrRow = ( $element ) => {
		acf.doAction( 'remove', $element );
		$element.remove();
	};

	const writeFieldValue = ( field, value ) => {
		const fieldType = field.get( 'type' );

		if ( ! isComplexFieldType( fieldType ) ) {
			field.val( value );
			return;
		}

		if ( 'group' === fieldType || 'clone' === fieldType ) {
			writeGroupValue( field, value );
		} else if ( 'repeater' === fieldType ) {
			writeRepeaterValue( field, value );
		} else if ( 'flexible_content' === fieldType ) {
			writeFlexibleContentValue( field, value );
		}
	};

	const writeGroupValue = ( field, value ) => {
		if ( value && 'object' === typeof value && ! Array.isArray( value ) ) {
			writeChildValues( field.$el, value );
		}
	};

	const writeRepeaterValue = ( field, value ) => {
		if ( field.get( 'pagination' ) ) {
			return;
		}

		const rows = Array.isArray( value ) ? value : [];
		const getRows = () =>
			field.$el.find(
				'> .acf-input > .acf-repeater > table > tbody > tr.acf-row:not(.acf-clone)'
			);
		const currentRowCount = getRows().length;

		for ( let index = currentRowCount - 1; index >= rows.length; index-- ) {
			removeLayoutOrRow( getRows().eq( index ) );
		}
		for ( let index = currentRowCount; index < rows.length; index++ ) {
			field.add();
		}

		getRows().each( function ( index ) {
			const rowValue = rows[ index ];
			if ( rowValue && 'object' === typeof rowValue ) {
				writeChildValues( jQuery( this ), rowValue );
			}
		} );
	};

	const writeFlexibleContentValue = ( field, value ) => {
		const layouts = Array.isArray( value ) ? value : [];
		const $currentLayouts = field.$layouts();
		const layoutShapeChanged =
			$currentLayouts.length !== layouts.length ||
			! $currentLayouts.toArray().every( ( element, index ) => {
				return (
					jQuery( element ).data( 'layout' ) ===
					layouts[ index ]?.acf_fc_layout
				);
			} );

		if ( layoutShapeChanged ) {
			$currentLayouts.each( function () {
				removeLayoutOrRow( jQuery( this ) );
			} );

			for ( const layoutValue of layouts ) {
				const layoutName = layoutValue?.acf_fc_layout;
				if ( 'string' === typeof layoutName && layoutName ) {
					field.add( { layout: layoutName } );
				}
			}
		}

		field.$layouts().each( function ( index ) {
			const layoutValue = layouts[ index ];
			if ( layoutValue && 'object' === typeof layoutValue ) {
				writeChildValues( jQuery( this ), layoutValue );
			}
		} );
	};

	const writeChildValues = ( $parent, values ) => {
		for ( const childField of acf.getFields( { parent: $parent } ) ) {
			const childKey = childField.get( 'key' );
			if ( childKey in values ) {
				writeFieldValue( childField, values[ childKey ] );
			}
		}
	};

	if ( window.wp?.data?.select && window.wp?.data?.dispatch ) {
		let isSyncingDomAndStore = false;
		let previousStoreValues = null;

		const getTopLevelFieldKey = ( field ) => {
			const parents = field.parents();
			return parents.length
				? parents[ parents.length - 1 ].get( 'key' )
				: field.get( 'key' );
		};

		const writeStoreValueToField = ( fieldKey, value ) => {
			const $field = acf.findField( fieldKey );
			if ( ! $field.length ) {
				return;
			}

			const field = acf.getField( $field );
			if ( field ) {
				writeFieldValue( field, value );
			}
		};

		const fetchMissingLookupOptions = async ( changedKeys, values ) => {
			const optionsByFieldKey = {};
			const requests = [];

			for ( const fieldKey of changedKeys ) {
				const $field = acf.findField( fieldKey );
				if ( ! $field.length ) {
					continue;
				}

				const field = acf.getField( $field );
				if (
					! field ||
					! AJAX_LOOKUP_FIELD_TYPES.has( field.get( 'type' ) )
				) {
					continue;
				}

				const missingValues = getMissingLookupValues(
					field,
					values[ fieldKey ]
				);
				if ( ! missingValues.length ) {
					continue;
				}

				optionsByFieldKey[ fieldKey ] = [];
				for ( const missingValue of missingValues ) {
					requests.push(
						fetchLookupOption( field, missingValue ).then(
							( option ) => {
								if ( option ) {
									optionsByFieldKey[ fieldKey ].push(
										option
									);
								}
							}
						)
					);
				}
			}

			await Promise.all( requests );
			return optionsByFieldKey;
		};

		const syncDomFromStore = () => {
			if ( isSyncingDomAndStore ) {
				return;
			}

			const store = wp.data.select( STORE_NAME );
			if ( ! store.isInitialized() ) {
				return;
			}

			const values = store.getAllValues();
			if ( values === previousStoreValues ) {
				return;
			}

			const previousValues = previousStoreValues ?? {};
			const changedKeys = Object.keys( values ).filter(
				( fieldKey ) =>
					values[ fieldKey ] !== previousValues[ fieldKey ]
			);
			previousStoreValues = values;

			if ( ! changedKeys.length ) {
				return;
			}

			let needsLookupOptions = false;
			for ( const fieldKey of changedKeys ) {
				const $field = acf.findField( fieldKey );
				if ( ! $field.length ) {
					continue;
				}

				const field = acf.getField( $field );
				if (
					field &&
					AJAX_LOOKUP_FIELD_TYPES.has( field.get( 'type' ) ) &&
					getMissingLookupValues( field, values[ fieldKey ] ).length
				) {
					needsLookupOptions = true;
					break;
				}
			}

			if ( needsLookupOptions ) {
				isSyncingDomAndStore = true;
				fetchMissingLookupOptions( changedKeys, values )
					.then( ( optionsByFieldKey ) => {
						try {
							for ( const [ fieldKey, options ] of Object.entries(
								optionsByFieldKey
							) ) {
								const $field = acf.findField( fieldKey );
								if ( ! $field.length ) {
									continue;
								}

								const field = acf.getField( $field );
								if ( field ) {
									appendLookupOptions( field, options );
								}
							}

							for ( const fieldKey of changedKeys ) {
								writeStoreValueToField(
									fieldKey,
									values[ fieldKey ]
								);
							}
						} finally {
							isSyncingDomAndStore = false;
							previousStoreValues = values;

							if (
								wp.data.select( STORE_NAME ).getAllValues() !==
								values
							) {
								queueMicrotask( syncDomFromStore );
							}
						}
					} )
					.catch( () => {
						isSyncingDomAndStore = false;
					} );
				return;
			}

			isSyncingDomAndStore = true;
			try {
				for ( const fieldKey of changedKeys ) {
					writeStoreValueToField( fieldKey, values[ fieldKey ] );
				}
			} finally {
				isSyncingDomAndStore = false;
			}
		};

		new acf.Model( {
			id: 'datastoreSync',
			wait: 'prepare',

			initialize() {
				if ( ! acf.isGutenbergPostEditor() ) {
					return;
				}

				this.initializeStore();
				this.subscribeToStore();
				this.listenToDOM();
				this.setupConvenienceAPI();
			},

			initializeStore() {
				const storeData = acf.get( 'storeData' );
				if ( ! storeData ) {
					return;
				}

				wp.data.dispatch( STORE_NAME ).initializeStore( storeData );
				this.reconcileWithDOM();
				previousStoreValues = wp.data
					.select( STORE_NAME )
					.getAllValues();
			},

			reconcileWithDOM() {
				const fields = acf.getFields();

				if ( ! fields?.length ) {
					return;
				}

				const store = wp.data.select( STORE_NAME );
				const values = {};
				for ( const field of fields ) {
					const fieldKey = field.get( 'key' );
					if ( ! fieldKey || ! store.getField( fieldKey ) ) {
						continue;
					}

					const storeValue = store.getFieldValue( fieldKey );
					if ( undefined === storeValue ) {
						continue;
					}

					const fieldType = field.get( 'type' );
					const domValue = isComplexFieldType( fieldType )
						? readComplexValue( field )
						: field.val();

					if (
						JSON.stringify( domValue ) !==
						JSON.stringify( storeValue )
					) {
						values[ fieldKey ] = domValue;
					}
				}

				if ( Object.keys( values ).length ) {
					wp.data.dispatch( STORE_NAME ).setValues( values );
				}
			},

			subscribeToStore() {
				this.storeUnsubscribe = wp.data.subscribe( syncDomFromStore );
			},

			listenToDOM() {
				acf.addAction( 'change_field', this.onFieldChange, 10, this );
				acf.addAction(
					'append_field',
					this.onFieldStructureChange,
					10,
					this
				);
				acf.addAction(
					'remove_field',
					this.onFieldStructureChange,
					10,
					this
				);
				acf.addAction(
					'sortstop_field',
					this.onFieldStructureChange,
					10,
					this
				);
				acf.addAction(
					'refresh_post_screen',
					this.onRefreshPostScreen,
					10,
					this
				);
			},

			onFieldChange( field ) {
				if ( isSyncingDomAndStore ) {
					return;
				}
				if ( ! wp.data.select( STORE_NAME ).isInitialized() ) {
					return;
				}

				const fieldKey = field.get( 'key' );
				if ( ! fieldKey ) {
					return;
				}

				isSyncingDomAndStore = true;
				try {
					const parentField = field.parent();
					if (
						parentField &&
						isComplexFieldType( parentField.get( 'type' ) )
					) {
						const topLevelKey = getTopLevelFieldKey( field );
						if ( topLevelKey ) {
							const $topLevelField = acf.findField( topLevelKey );
							if ( $topLevelField.length ) {
								const topLevelField =
									acf.getField( $topLevelField );
								if ( topLevelField ) {
									wp.data
										.dispatch( STORE_NAME )
										.setFieldValue(
											topLevelKey,
											readComplexValue( topLevelField )
										);
								}
							}
						}
					} else if ( isComplexFieldType( field.get( 'type' ) ) ) {
						wp.data
							.dispatch( STORE_NAME )
							.setFieldValue(
								fieldKey,
								readComplexValue( field )
							);
					} else {
						wp.data
							.dispatch( STORE_NAME )
							.setFieldValue( fieldKey, field.val() );
					}
				} finally {
					isSyncingDomAndStore = false;
					previousStoreValues = wp.data
						.select( STORE_NAME )
						.getAllValues();
				}
			},

			onFieldStructureChange( field ) {
				if ( isSyncingDomAndStore ) {
					return;
				}
				if ( ! wp.data.select( STORE_NAME ).isInitialized() ) {
					return;
				}

				const topLevelKey = getTopLevelFieldKey( field );
				if ( ! topLevelKey ) {
					return;
				}

				const $topLevelField = acf.findField( topLevelKey );
				if ( ! $topLevelField.length ) {
					return;
				}

				const topLevelField = acf.getField( $topLevelField );
				if (
					! topLevelField ||
					! isComplexFieldType( topLevelField.get( 'type' ) )
				) {
					return;
				}

				isSyncingDomAndStore = true;
				try {
					wp.data
						.dispatch( STORE_NAME )
						.setFieldValue(
							topLevelKey,
							readComplexValue( topLevelField )
						);
				} finally {
					isSyncingDomAndStore = false;
					previousStoreValues = wp.data
						.select( STORE_NAME )
						.getAllValues();
				}
			},

			onRefreshPostScreen( response ) {
				if ( ! response?.storeData ) {
					return;
				}

				isSyncingDomAndStore = true;
				try {
					wp.data
						.dispatch( STORE_NAME )
						.registerFieldGroup( response.storeData );
					this.reconcileWithDOM();
				} finally {
					isSyncingDomAndStore = false;
					previousStoreValues = wp.data
						.select( STORE_NAME )
						.getAllValues();
				}
			},

			setupConvenienceAPI() {
				acf.store = {
					get( fieldKey ) {
						if ( wp.data.select( STORE_NAME ).isInitialized() ) {
							return wp.data
								.select( STORE_NAME )
								.getFieldValue( fieldKey );
						}

						return undefined;
					},
					set( fieldKey, value ) {
						if ( wp.data.select( STORE_NAME ).isInitialized() ) {
							wp.data
								.dispatch( STORE_NAME )
								.setFieldValue( fieldKey, value );
						}
					},
					subscribe( fieldKey, callback ) {
						let previousValue = wp.data
							.select( STORE_NAME )
							.getFieldValue( fieldKey );

						return wp.data.subscribe( () => {
							const nextValue = wp.data
								.select( STORE_NAME )
								.getFieldValue( fieldKey );
							if ( nextValue !== previousValue ) {
								const oldValue = previousValue;
								previousValue = nextValue;
								callback( nextValue, oldValue );
							}
						} );
					},
				};
			},
		} );
	}

	const collectPaginatedRepeaterRowsForSave = ( fieldKey ) => {
		const $field = acf.findField( fieldKey );
		if ( ! $field.length ) {
			return {};
		}

		const $rows = $field.find(
			'> .acf-input > .acf-repeater > table > tbody > tr.acf-row:not(.acf-clone)'
		);
		const rowsById = {};

		$rows.each( function () {
			const $row = jQuery( this );
			const rowId = $row.data( 'id' );
			if ( ! rowId ) {
				return;
			}

			const rowValue = {};
			for ( const childField of acf.getFields( { parent: $row } ) ) {
				const childKey = childField.get( 'key' );
				const childType = childField.get( 'type' );
				rowValue[ childKey ] = isComplexFieldType( childType )
					? readComplexValue( childField )
					: childField.val();
			}

			$row.find( 'input.acf-row-status' ).each( function () {
				const matches = ( jQuery( this ).attr( 'name' ) ?? '' ).match(
					REPEATER_ROW_STATUS_NAME_PATTERN
				);
				if ( matches ) {
					rowValue[ `acf_${ matches[ 1 ] }` ] = jQuery( this ).val();
				}
			} );

			rowsById[ rowId ] = rowValue;
		} );

		return rowsById;
	};

	let lastPostedAcfJson = null;
	let lastObservedAcfJson = null;
	let isApplyingEditorMetaToStore = false;

	acf.gutenbergEditPost = function () {
		if ( ! acf.isGutenbergPostEditor() ) {
			return;
		}

		const store = wp.data.select( STORE_NAME );
		if ( ! store || ! store.isInitialized() ) {
			return;
		}

		const values = { ...store.getAllValues() };
		for ( const fieldKey of Object.keys( values ) ) {
			const field = store.getField( fieldKey );
			if ( 'repeater' === field?.type && field.pagination ) {
				values[ fieldKey ] =
					collectPaginatedRepeaterRowsForSave( fieldKey );
			}
		}

		const acfJson = JSON.stringify( values );
		lastPostedAcfJson = acfJson;
		lastObservedAcfJson = acfJson;
		wp.data
			.dispatch( 'core/editor' )
			.editPost( { meta: { _acf: acfJson } } );
	};

	if ( window.wp?.data?.subscribe ) {
		wp.data.subscribe( () => {
			if ( isApplyingEditorMetaToStore ) {
				return;
			}

			const store = wp.data.select( STORE_NAME );
			if ( ! store?.isInitialized() ) {
				return;
			}

			const editor = wp.data.select( 'core/editor' );
			if ( ! editor?.getEditedPostAttribute ) {
				return;
			}

			const meta = editor.getEditedPostAttribute( 'meta' );
			const acfJson =
				meta && 'string' === typeof meta._acf ? meta._acf : null;
			if ( ! acfJson || acfJson === lastObservedAcfJson ) {
				return;
			}
			if ( acfJson === lastPostedAcfJson ) {
				lastObservedAcfJson = acfJson;
				return;
			}

			let values;
			try {
				values = JSON.parse( acfJson );
			} catch ( error ) {
				lastObservedAcfJson = acfJson;
				return;
			}

			if (
				values &&
				'object' === typeof values &&
				! Array.isArray( values )
			) {
				lastObservedAcfJson = acfJson;
				isApplyingEditorMetaToStore = true;
				try {
					wp.data.dispatch( STORE_NAME ).setValues( values );
				} finally {
					isApplyingEditorMetaToStore = false;
				}
			} else {
				lastObservedAcfJson = acfJson;
			}
		} );
	}
} )();
