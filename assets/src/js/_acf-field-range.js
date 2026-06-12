( function ( $, undefined ) {
	var Field = acf.Field.extend( {
		type: 'range',

		events: {
			'input input[type="range"]': 'onChange',
			'change input': 'onChange',
		},

		$input: function () {
			return this.$( 'input[type="range"]' );
		},

		$inputAlt: function () {
			return this.$( 'input[type="number"]' );
		},

		setValue: function ( val ) {
			this.busy = true;

			// Update range input (with change).
			acf.val( this.$input(), val );

			// Update alt input (without change).
			// Read in input value to inherit min/max validation.
			acf.val( this.$inputAlt(), this.$input()[ 0 ].value, true );

			this.busy = false;
		},

		onChange: function ( e, $el ) {
			if ( ! this.busy ) {
				this.setValue( $el[ 0 ].value );
			}
		},
	} );

	acf.registerFieldType( Field );
} )( jQuery );
