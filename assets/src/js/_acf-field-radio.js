( function ( $, undefined ) {
	var Field = acf.Field.extend( {
		type: 'radio',

		events: {
			'click input[type="radio"]': 'onClick',
			'keydown input[type="radio"]': 'onKeyDownInput',
		},

		$control: function () {
			return this.$( '.acf-radio-list' );
		},

		$input: function () {
			return this.$( 'input:checked' );
		},

		$inputText: function () {
			return this.$( 'input[type="text"]' );
		},

		setValue: function ( val ) {
			const el = this.$el[ 0 ];

			el.querySelectorAll( '.selected' ).forEach( function ( node ) {
				node.classList.remove( 'selected' );
			} );
			el.querySelectorAll( 'input[type="radio"]' ).forEach(
				function ( radio ) {
					radio.checked = false;
				}
			);

			if ( val !== false && val !== null && val !== '' ) {
				const input = Array.from(
					el.querySelectorAll( 'input[type="radio"]' )
				).find( function ( radio ) {
					return radio.value === val;
				} );

				if ( input ) {
					input.checked = true;
					const label = input.closest( 'label' );
					if ( label ) {
						label.classList.add( 'selected' );
					}

					if ( this.get( 'other_choice' ) ) {
						if ( val === 'other' ) {
							this.$inputText()[ 0 ].disabled = false;
						} else {
							this.$inputText()[ 0 ].disabled = true;
						}
					}
				}
			}
		},

		getValue: function () {
			var input = this.$input()[ 0 ];
			var val = input ? input.value : undefined;
			if ( val === 'other' && this.get( 'other_choice' ) ) {
				val = this.$inputText()[ 0 ].value;
			}
			return val;
		},

		onClick: function ( e, $el ) {
			// vars
			var input = $el[ 0 ];
			var label = input.closest( 'label' );
			var selected = label && label.classList.contains( 'selected' );
			var val = input.value;

			// remove previous selected
			this.$el[ 0 ]
				.querySelectorAll( '.selected' )
				.forEach( function ( node ) {
					node.classList.remove( 'selected' );
				} );

			// add active class
			if ( label ) {
				label.classList.add( 'selected' );
			}

			// allow null
			if ( this.get( 'allow_null' ) && selected ) {
				if ( label ) {
					label.classList.remove( 'selected' );
				}
				input.checked = false;
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				val = false;
			}

			// other
			if ( this.get( 'other_choice' ) ) {
				// enable
				if ( val === 'other' ) {
					this.$inputText()[ 0 ].disabled = false;

					// disable
				} else {
					this.$inputText()[ 0 ].disabled = true;
				}
			}
		},
		onKeyDownInput: function ( event, $input ) {
			if ( event.which === 13 ) {
				event.preventDefault();
				$input[ 0 ].checked = true;
				$input[ 0 ].dispatchEvent(
					new Event( 'change', { bubbles: true } )
				);
			}
		},
	} );

	acf.registerFieldType( Field );
} )( jQuery );
