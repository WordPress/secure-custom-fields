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
			this.$( 'input[value="' + val + '"]' )
				.prop( 'checked', true )
				.trigger( 'change' );
			this.updateButtonStates();
		},

		updateButtonStates: function () {
			const labels = this.$control().find( 'label' );
			const input = this.$input();
			labels
				.removeClass( 'selected' )
				.attr( 'aria-checked', 'false' )
				.attr( 'tabindex', '-1' );
			if ( input.length ) {
				// If there's a checked input, mark its parent label as selected
				input
					.parent( 'label' )
					.addClass( 'selected' )
					.attr( 'aria-checked', 'true' )
					.attr( 'tabindex', '0' );
			} else {
				labels.first().attr( 'tabindex', '0' );
			}
		},
		onClick: function ( e, $el ) {
			this.selectButton( $el.parent( 'label' ) );
		},
		onKeyDown: function ( event, label ) {
			const key = event.which;

			// Space or Enter: select the button
			if ( key === 13 || key === 32 ) {
				event.preventDefault();
				this.selectButton( label );
				return;
			}

			// Arrow keys: move focus between buttons
			if ( key === 37 || key === 39 || key === 38 || key === 40 ) {
				event.preventDefault();
				const labels = this.$control().find( 'label' );
				const currentIndex = labels.index( label );
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

				const nextLabel = labels.eq( nextIndex );
				labels.attr( 'tabindex', '-1' );
				nextLabel.attr( 'tabindex', '0' ).trigger( 'focus' );
			}
		},

		selectButton: function ( element ) {
			const inputRadio = element.find( 'input[type="radio"]' );
			const isSelected = element.hasClass( 'selected' );
			inputRadio.prop( 'checked', true ).trigger( 'change' );
			if ( this.get( 'allow_null' ) && isSelected ) {
				inputRadio.prop( 'checked', false ).trigger( 'change' );
			}
			this.updateButtonStates();
		},
	} );

	acf.registerFieldType( Field );
} )( jQuery );
