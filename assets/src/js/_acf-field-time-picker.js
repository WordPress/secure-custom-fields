( function ( $, undefined ) {
	var Field = acf.models.DatePickerField.extend( {
		type: 'time_picker',

		$control: function () {
			return this.$( '.acf-time-picker' );
		},

		$inputNative: function () {
			return this.$( 'input[type="time"]' );
		},

		syncNative: function () {
			var val = this.$inputNative().val();

			// The input can report HH:MM, but the stored value is always H:i:s.
			if ( /^\d{2}:\d{2}$/.test( val ) ) {
				val += ':00';
			}

			acf.val( this.$input(), val );
		},

		setValue: function ( val ) {
			acf.val( this.$input(), val );

			// Native input only needs the stored value, it is already ISO.
			var $native = this.$inputNative();
			if ( $native.length ) {
				$native.val( val || '' );
				return;
			}

			const $inputText = this.$inputText();
			if ( val && $inputText.length ) {
				try {
					const timeFormat =
						this.get( 'time_format' ) ||
						$inputText.timepicker( 'option', 'timeFormat' );
					const matches = val.match( /^(\d{2}):(\d{2}):(\d{2})$/ );

					if ( matches && $.datepicker && $.datepicker.formatTime ) {
						const timeText = $.datepicker.formatTime( timeFormat, {
							hour: parseInt( matches[ 1 ], 10 ),
							minute: parseInt( matches[ 2 ], 10 ),
							second: parseInt( matches[ 3 ], 10 ),
						} );
						$inputText.val( timeText );
					} else {
						$inputText.val( val );
					}
				} catch ( e ) {
					$inputText.val( val );
				}
			} else {
				$inputText.val( '' );
			}
		},

		initialize: function () {
			// Native input: the browser provides the picker, we only keep the
			// hidden input in sync with the value the input reports.
			if ( this.$control().data( 'native' ) === 1 ) {
				return this.initializeNative();
			}

			// vars
			var $input = this.$input();
			var $inputText = this.$inputText();

			// args
			var args = {
				timeFormat: this.get( 'time_format' ),
				altField: $input,
				altFieldTimeOnly: false,
				altTimeFormat: 'HH:mm:ss',
				showButtonPanel: true,
				controlType: 'select',
				oneLine: true,
				closeText: acf.get( 'dateTimePickerL10n' ).selectText,
				timeOnly: true,
			};

			// add custom 'Close = Select' functionality
			args.onClose = function ( value, dp_instance, t_instance ) {
				// vars
				var $close = dp_instance.dpDiv.find( '.ui-datepicker-close' );

				// if clicking close button
				if ( ! value && $close.is( ':hover' ) ) {
					t_instance._updateDateTime();
				}
			};

			// filter
			args = acf.applyFilters( 'time_picker_args', args, this );

			// add date time picker
			acf.newTimePicker( $inputText, args );

			// action
			acf.doAction( 'time_picker_init', $inputText, args, this );
		},

		initializeNative: function () {
			var self = this;

			this.$inputNative().on( 'change input', function () {
				self.syncNative();
			} );
		},
	} );

	acf.registerFieldType( Field );

	// add
	acf.newTimePicker = function ( $input, args ) {
		// bail early if no datepicker library
		if ( typeof $.timepicker === 'undefined' ) {
			return false;
		}

		// defaults
		args = args || {};

		// initialize
		$input.timepicker( args );

		// wrap the datepicker (only if it hasn't already been wrapped)
		if ( $( 'body > #ui-datepicker-div' ).exists() ) {
			$( 'body > #ui-datepicker-div' ).wrap(
				'<div class="acf-ui-datepicker" />'
			);
		}
	};
} )( jQuery );
