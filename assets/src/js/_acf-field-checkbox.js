( function ( $, undefined ) {
	var Field = acf.Field.extend( {
		type: 'checkbox',

		events: {
			'change input': 'onChange',
			'click .acf-add-checkbox': 'onClickAdd',
			'click .acf-checkbox-toggle': 'onClickToggle',
			'click .acf-checkbox-custom': 'onClickCustom',
			'keydown input[type="checkbox"]': 'onKeyDownInput',
		},

		$control: function () {
			return this.$( '.acf-checkbox-list' );
		},

		$toggle: function () {
			return this.$( '.acf-checkbox-toggle' );
		},

		$input: function () {
			return this.$( 'input[type="hidden"]' );
		},

		$inputs: function () {
			return this.$( 'input[type="checkbox"]' ).not(
				'.acf-checkbox-toggle'
			);
		},

		setValue: function ( val ) {
			if ( ! Array.isArray( val ) ) {
				val = val ? [ val ] : [];
			}

			const inputs = Array.from( this.$inputs() );
			inputs.forEach( function ( input ) {
				const checked = val.includes( input.value );
				input.checked = checked;

				const label = input.closest( 'label' );
				if ( ! label ) {
					return;
				}

				if ( checked ) {
					label.classList.add( 'selected' );
				} else {
					label.classList.remove( 'selected' );
				}
			} );

			const toggle = this.$toggle()[ 0 ];
			if ( toggle ) {
				toggle.checked = inputs.every( function ( input ) {
					return input.checked;
				} );
			}
		},

		getValue: function () {
			var val = [];
			this.$el[ 0 ]
				.querySelectorAll( ':checked' )
				.forEach( function ( input ) {
					val.push( input.value );
				} );
			return val.length ? val : false;
		},

		onChange: function ( e, $el ) {
			// Vars.
			var input = $el[ 0 ];
			var checked = input.checked;
			var label = input.closest( 'label' );
			var toggle = this.$toggle()[ 0 ];

			// Add or remove "selected" class.
			if ( label ) {
				if ( checked ) {
					label.classList.add( 'selected' );
				} else {
					label.classList.remove( 'selected' );
				}
			}

			// Update toggle state if all inputs are checked.
			if ( toggle ) {
				var inputs = Array.from( this.$inputs() );

				// all checked
				toggle.checked = inputs.every( function ( item ) {
					return item.checked;
				} );
			}
		},

		onClickAdd: function ( e, $el ) {
			var html =
				'<li><input class="acf-checkbox-custom" type="checkbox" checked="checked" /><input type="text" name="' +
				this.getInputName() +
				'[]" /></li>';
			var li = $el[ 0 ].closest( 'li' );
			li.insertAdjacentHTML( 'beforebegin', html );
			var texts =
				li.parentElement.querySelectorAll( 'input[type="text"]' );
			if ( texts.length ) {
				texts[ texts.length - 1 ].focus();
			}
		},

		onClickToggle: function ( e, $el ) {
			var inputs = Array.from( this.$inputs() );
			var checked = $el[ 0 ].checked;

			// Set all states first so listeners never observe a partial state.
			inputs.forEach( function ( input ) {
				input.checked = checked;
			} );
			inputs.forEach( function ( input ) {
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
		},

		onClickCustom: function ( e, $el ) {
			var input = $el[ 0 ];
			var checked = input.checked;
			var text = input.nextElementSibling;

			// bail early if no adjacent text input
			if ( ! text || ! text.matches( 'input[type="text"]' ) ) {
				return;
			}

			// checked
			if ( checked ) {
				text.disabled = false;

				// not checked
			} else {
				text.disabled = true;

				// remove
				if ( text.value == '' ) {
					input.closest( 'li' ).remove();
				}
			}
		},
		onKeyDownInput: function ( e, $el ) {
			// Check if Enter key (keyCode 13) was pressed
			if ( e.which === 13 ) {
				// Prevent default form submission
				e.preventDefault();

				// Toggle the checkbox state and trigger change event
				var input = $el[ 0 ];
				input.checked = ! input.checked;
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );

				// If this is the "Select All" toggle checkbox, run the toggle logic
				if ( input.classList.contains( 'acf-checkbox-toggle' ) ) {
					this.onClickToggle( e, $el );
				}
			}
		},
	} );

	acf.registerFieldType( Field );
} )( jQuery );
