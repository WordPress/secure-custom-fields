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

		getValue: function () {
			var val = [];
			this.$( ':checked' ).each( function () {
				val.push( $( this ).val() );
			} );
			return val.length ? val : false;
		},

		onChange: function ( e, $el ) {
			// Vars.
			var checked = $el.prop( 'checked' );
			var $label = $el.parent( 'label' );
			var $toggle = this.$toggle();

			// Add or remove "selected" class.
			if ( checked ) {
				$label.addClass( 'selected' );
			} else {
				$label.removeClass( 'selected' );
			}

			// Update toggle state if all inputs are checked.
			if ( $toggle.length ) {
				var $inputs = this.$inputs();

				// all checked
				if ( $inputs.not( ':checked' ).length == 0 ) {
					$toggle.prop( 'checked', true );
				} else {
					$toggle.prop( 'checked', false );
				}
			}
		},

		onClickAdd: function ( e, $el ) {
			var html =
				'<li><input class="acf-checkbox-custom" type="checkbox" checked="checked" /><input type="text" name="' +
				this.getInputName() +
				'[]" /></li>';
			$el.parent( 'li' ).before( html );
			$el.parent( 'li' )
				.parent()
				.find( 'input[type="text"]' )
				.last()
				.trigger( 'focus' );
		},

		onClickToggle: function ( e, $el ) {
			var $inputs = this.$inputs();
			var checked = $el.prop( 'checked' );
			$inputs.prop( 'checked', checked ).trigger( 'change' );
		},

		onClickCustom: function ( e, $el ) {
			var checked = $el.prop( 'checked' );
			var $text = $el.next( 'input[type="text"]' );

			// checked
			if ( checked ) {
				$text.prop( 'disabled', false );

				// not checked
			} else {
				$text.prop( 'disabled', true );

				// remove
				if ( $text.val() == '' ) {
					$el.parent( 'li' ).remove();
				}
			}
		},
		onKeyDownInput: function ( e, $el ) {
			// Check if Enter key (keyCode 13) was pressed
			if ( e.which === 13 ) {
				// Prevent default form submission
				e.preventDefault();

				// Toggle the checkbox state and trigger change event
				$el.prop( 'checked', ! $el.prop( 'checked' ) ).trigger(
					'change'
				);

				// If this is the "Select All" toggle checkbox, run the toggle logic
				if ( $el.is( '.acf-checkbox-toggle' ) ) {
					this.onClickToggle( e, $el );
				}
			}
		},
	} );

	acf.registerFieldType( Field );
} )( jQuery );
