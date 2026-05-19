( () => {
	'use strict';

	const STORE_NAME = 'acf/fields';

	const fieldSupportsBindings = ( field ) => {
		return (
			false !== field.supportsBindings && false !== field.allowInBindings
		);
	};

	const isScalarValue = ( value ) => {
		const valueType = typeof value;
		return (
			'string' === valueType ||
			'number' === valueType ||
			'boolean' === valueType
		);
	};

	const valueToBindingString = ( value ) => {
		if ( null === value || undefined === value ) {
			return null;
		}

		if ( isScalarValue( value ) ) {
			return String( value );
		}

		if ( Array.isArray( value ) && value.every( isScalarValue ) ) {
			return value.join( ', ' );
		}

		return null;
	};

	if ( ! window.wp?.blocks?.registerBlockBindingsSource ) {
		// eslint-disable-next-line no-console
		console.warn(
			'SCF: wp.blocks.registerBlockBindingsSource is unavailable; SCF block bindings will not be registered. Requires WordPress 6.7+.'
		);
		return;
	}

	wp.blocks.registerBlockBindingsSource( {
		name: 'acf/field',
		usesContext: [ 'postType', 'postId' ],
		getValues( { select, bindings } ) {
			const store = select( STORE_NAME );

			if ( ! store.isInitialized() ) {
				return {};
			}

			const values = {};
			for ( const attributeName of Object.keys( bindings ) ) {
				const fieldKey = bindings[ attributeName ].args?.key;
				if ( ! fieldKey ) {
					continue;
				}

				const value = valueToBindingString(
					store.getFieldValue( fieldKey )
				);
				if ( null !== value ) {
					values[ attributeName ] = value;
				}
			}

			return values;
		},
		setValues( { select, dispatch, bindings } ) {
			const store = select( STORE_NAME );
			if ( ! store.isInitialized() ) {
				return;
			}

			for ( const attributeName of Object.keys( bindings ) ) {
				const binding = bindings[ attributeName ];
				const fieldKey = binding.args?.key;
				if (
					! fieldKey ||
					undefined === binding.newValue ||
					! isScalarValue( binding.newValue )
				) {
					continue;
				}

				const field = store.getField( fieldKey );
				if ( field && fieldSupportsBindings( field ) ) {
					dispatch( STORE_NAME ).setFieldValue(
						fieldKey,
						binding.newValue
					);
				}
			}
		},
		canUserEditValue( { select, args } ) {
			if ( ! args?.key ) {
				return false;
			}

			const store = select( STORE_NAME );
			if ( ! store.isInitialized() ) {
				return false;
			}

			const field = store.getField( args.key );
			if ( ! field || ! fieldSupportsBindings( field ) ) {
				return false;
			}

			const value = store.getFieldValue( args.key );
			return ! (
				null !== value &&
				undefined !== value &&
				! isScalarValue( value )
			);
		},
		getFieldsList( { select } ) {
			const store = select( STORE_NAME );
			if ( ! store.isInitialized() ) {
				return [];
			}

			const fields = store.getFields();
			const list = [];
			for ( const fieldKey of Object.keys( fields ) ) {
				const field = fields[ fieldKey ];
				if ( fieldSupportsBindings( field ) ) {
					list.push( {
						label: field.label,
						args: { key: field.key },
						type: 'string',
					} );
				}
			}

			return list;
		},
	} );
} )();
