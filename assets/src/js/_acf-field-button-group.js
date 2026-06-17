import { update } from '@wordpress/icons';

( function ( $, undefined ) {
	const Field = acf.Field.extend( {
		type: 'button_group',

		events: {
			'click input[type="radio"]': 'onClick',
			'keydown label': 'onKeyDown',
		},

		$control: function () {
			return this.$( '.acf-button-group' );
		},

		$input: function () {
			return this.$( 'input:checked' );
		},
		initialize: function () {
			this.updateButtonStates();
		},

		setValue: function ( val ) {
			const input = this.$el[ 0 ].querySelector(
				'input[value="' + val + '"]'
			);
			if ( input ) {
				input.checked = true;
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
			this.updateButtonStates();
		},

		updateButtonStates: function () {
			const control = this.$control()[ 0 ];
			if ( ! control ) {
				return;
			}
			const labels = control.querySelectorAll( 'label' );
			const input = this.$input()[ 0 ];
			labels.forEach( ( label ) => {
				label.classList.remove( 'selected' );
				label.setAttribute( 'aria-checked', 'false' );
				label.setAttribute( 'tabindex', '-1' );
			} );
			if ( input ) {
				// If there's a checked input, mark its parent label as selected
				const inputLabel = input.closest( 'label' );
				if ( inputLabel ) {
					inputLabel.classList.add( 'selected' );
					inputLabel.setAttribute( 'aria-checked', 'true' );
					inputLabel.setAttribute( 'tabindex', '0' );
				}
			} else if ( labels.length ) {
				labels[ 0 ].setAttribute( 'tabindex', '0' );
			}
		},
		onClick: function ( e, $el ) {
			this.selectButton( $el[ 0 ].closest( 'label' ) );
		},
		onKeyDown: function ( event, $label ) {
			const key = event.which;
			const label = $label[ 0 ];

			// Space or Enter: select the button
			if ( key === 13 || key === 32 ) {
				event.preventDefault();
				this.selectButton( label );
				return;
			}

			// Arrow keys: move focus between buttons
			if ( key === 37 || key === 39 || key === 38 || key === 40 ) {
				event.preventDefault();
				const labels = Array.from(
					this.$control()[ 0 ].querySelectorAll( 'label' )
				);
				const currentIndex = labels.indexOf( label );
				let nextIndex;

				// Left/Up arrow: move to previous, wrap to last if at start
				if ( key === 37 || key === 38 ) {
					nextIndex =
						currentIndex > 0 ? currentIndex - 1 : labels.length - 1;
				}
				// Right/Down arrow: move to next, wrap to first if at end
				else {
					nextIndex =
						currentIndex < labels.length - 1 ? currentIndex + 1 : 0;
				}

				const nextLabel = labels[ nextIndex ];
				labels.forEach( ( item ) => {
					item.setAttribute( 'tabindex', '-1' );
				} );
				nextLabel.setAttribute( 'tabindex', '0' );
				nextLabel.focus();
			}
		},

		selectButton: function ( element ) {
			const inputRadio = element
				? element.querySelector( 'input[type="radio"]' )
				: null;
			const isSelected =
				element && element.classList.contains( 'selected' );
			if ( inputRadio ) {
				inputRadio.checked = true;
				inputRadio.dispatchEvent(
					new Event( 'change', { bubbles: true } )
				);
				if ( this.get( 'allow_null' ) && isSelected ) {
					inputRadio.checked = false;
					inputRadio.dispatchEvent(
						new Event( 'change', { bubbles: true } )
					);
				}
			}
			this.updateButtonStates();
		},
	} );

	acf.registerFieldType( Field );
} )( jQuery );
