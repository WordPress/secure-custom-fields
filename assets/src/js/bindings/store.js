/**
 * SCF Field Metadata Store
 *
 * Manages field metadata for block bindings using WordPress data store.
 */

import { createReduxStore, register } from '@wordpress/data';

const DEFAULT_STATE = {
	fieldMetadata: {},
};

const actions = {
	setFieldMetadata( fields ) {
		return {
			type: 'SET_FIELD_METADATA',
			fields,
		};
	},
	addFieldMetadata( fields ) {
		return {
			type: 'ADD_FIELD_METADATA',
			fields,
		};
	},
	clearFieldMetadata() {
		return {
			type: 'CLEAR_FIELD_METADATA',
		};
	},
};

const reducer = ( state = DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_FIELD_METADATA':
			return {
				...state,
				fieldMetadata: action.fields,
			};
		case 'ADD_FIELD_METADATA':
			return {
				...state,
				fieldMetadata: {
					...state.fieldMetadata,
					...action.fields,
				},
			};
		case 'CLEAR_FIELD_METADATA':
			return {
				...state,
				fieldMetadata: {},
			};
		default:
			return state;
	}
};

const selectors = {
	getFieldMetadata( state, fieldKey ) {
		return state.fieldMetadata[ fieldKey ] || null;
	},
	getAllFieldMetadata( state ) {
		return state.fieldMetadata;
	},
	hasFieldMetadata( state, fieldKey ) {
		return !! state.fieldMetadata[ fieldKey ];
	},
};

export const STORE_NAME = 'secure-custom-fields/field-metadata';

const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
} );

register( store );
