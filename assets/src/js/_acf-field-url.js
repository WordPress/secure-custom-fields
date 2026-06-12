( function ( $, undefined ) {
	var Field = acf.Field.extend( {
		type: 'url',

		events: {
			'keyup input[type="url"]': 'onkeyup',
		},

		$control: function () {
			return this.$( '.acf-input-wrap' );
		},

		$input: function () {
			return this.$( 'input[type="url"]' );
		},

		initialize: function () {
			this.render();
		},

		isValid: function () {
			// vars
			var val = this.val();

			// bail early if no val
			if ( ! val ) {
				return false;
			}

			// url
			if ( val.indexOf( '://' ) !== -1 ) {
				return true;
			}

			// protocol relative url
			if ( val.indexOf( '//' ) === 0 ) {
				return true;
			}

			// return
			return false;
		},

		render: function () {
			// vars
			var control = this.$control()[ 0 ];

			// bail early if no control
			if ( ! control ) {
				return;
			}

			// add class
			if ( this.isValid() ) {
				control.classList.add( '-valid' );
			} else {
				control.classList.remove( '-valid' );
			}
		},

		onkeyup: function ( e, $el ) {
			this.render();
		},
	} );

	acf.registerFieldType( Field );
} )( jQuery );
