( function ( $, undefined ) {
	var Field = acf.models.DatePickerField.extend( {
		type: 'date_time_picker',

		$control: function () {
			return this.$( '.acf-date-time-picker' );
		},

		$inputNative: function () {
			return this.$( 'input[type="datetime-local"]' );
		},

		syncNative: function () {
			var val = this.$inputNative().val();

			if ( val ) {
				// The stored format is Y-m-d H:i:s, the input reports Y-m-dTH:i(:s).
				val = val.replace( 'T', ' ' );

				// The input can report HH:MM, but the stored value always has seconds.
				if ( /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test( val ) ) {
					val += ':00';
				}
			}

			acf.val( this.$input(), val );
		},

		toNative: function ( val ) {
			// Y-m-d H:i:s to Y-m-dTH:i:s
			return val.indexOf( ' ' ) !== -1 ? val.replace( ' ', 'T' ) : val;
		},

		setValue: function ( val ) {
			// The native input only needs the ISO value, the parent handles the rest.
			var $native = this.$inputNative();
			if ( $native.length ) {
				acf.val( this.$input(), val );
				$native.val( val ? this.toNative( val ) : '' );
				return;
			}

			acf.val( this.$input(), val );

			const $inputText = this.$inputText();
			if ( val && $inputText.length ) {
				try {
					const parts = val.split( ' ' );
					const dateValue = parts[ 0 ] || '';
					const timeValue = parts[ 1 ] || '';
					const date = $.datepicker.parseDate(
						'yy-mm-dd',
						dateValue
					);
					const dateFormat =
						this.get( 'date_format' ) ||
						$inputText.datetimepicker( 'option', 'dateFormat' );
					const timeFormat =
						this.get( 'time_format' ) ||
						$inputText.datetimepicker( 'option', 'timeFormat' );
					const dateText = $.datepicker.formatDate(
						dateFormat,
						date
					);
					let timeText = timeValue;
					const matches = timeValue.match(
						/^(\d{2}):(\d{2}):(\d{2})$/
					);

					if ( matches && $.datepicker.formatTime ) {
						timeText = $.datepicker.formatTime( timeFormat, {
							hour: parseInt( matches[ 1 ], 10 ),
							minute: parseInt( matches[ 2 ], 10 ),
							second: parseInt( matches[ 3 ], 10 ),
						} );
					}

					$inputText.val( dateText + ' ' + timeText );
				} catch ( e ) {
					$inputText.val( val );
				}
			} else {
				$inputText.val( '' );
			}
		},

		initialize: function () {
			// Native input: the browser provides the picker, we only keep the
			// hidden input in sync with the ISO value the input reports.
			if ( this.$control().data( 'native' ) === 1 ) {
				return this.initializeNative();
			}

			// vars
			var $input = this.$input();
			var $inputText = this.$inputText();

			// args
			var args = {
				dateFormat: this.get( 'date_format' ),
				timeFormat: this.get( 'time_format' ),
				altField: $input,
				altFieldTimeOnly: false,
				altFormat: 'yy-mm-dd',
				altTimeFormat: 'HH:mm:ss',
				changeYear: true,
				yearRange: '-100:+100',
				changeMonth: true,
				showButtonPanel: true,
				firstDay: this.get( 'first_day' ),
				controlType: 'select',
				oneLine: true,
			};

			// filter
			args = acf.applyFilters( 'date_time_picker_args', args, this );

			// add date time picker
			acf.newDateTimePicker( $inputText, args );

			// Check if default to today is enabled and field is empty
			if (
				$inputText.data( 'default-to-today' ) === 1 &&
				! $input.val()
			) {
				// Get current date
				const currentDate = new Date();

				// Format display date and time
				const displayDate = $.datepicker.formatDate(
					args.dateFormat,
					currentDate
				);
				const displayTime = $.datepicker.formatTime( args.timeFormat, {
					hour: currentDate.getHours(),
					minute: currentDate.getMinutes(),
					second: currentDate.getSeconds(),
				} );

				// Set the display input value (what user sees)
				$inputText.val( `${ displayDate } ${ displayTime }` );

				// Format hidden field date and time (for database storage)
				const hiddenDate = $.datepicker.formatDate(
					'yy-mm-dd',
					currentDate
				);
				const hiddenTime = $.datepicker.formatTime( 'hh:mm:ss', {
					hour: currentDate.getHours(),
					minute: currentDate.getMinutes(),
					second: currentDate.getSeconds(),
				} );

				// Set the hidden input value (what gets saved)
				$input.val( `${ hiddenDate } ${ hiddenTime }` );
			}

			// action
			acf.doAction( 'date_time_picker_init', $inputText, args, this );
		},

		initializeNative: function () {
			var self = this;

			this.$inputNative().on( 'change input', function () {
				self.syncNative();
			} );
		},
	} );

	acf.registerFieldType( Field );

	// manager
	var dateTimePickerManager = new acf.Model( {
		priority: 5,
		wait: 'ready',
		initialize: function () {
			// vars
			var locale = acf.get( 'locale' );
			var rtl = acf.get( 'rtl' );
			var l10n = acf.get( 'dateTimePickerL10n' );

			// bail early if no l10n
			if ( ! l10n ) {
				return false;
			}

			// bail early if no datepicker library
			if ( typeof $.timepicker === 'undefined' ) {
				return false;
			}

			// rtl
			l10n.isRTL = rtl;

			// append
			$.timepicker.regional[ locale ] = l10n;
			$.timepicker.setDefaults( l10n );
		},
	} );

	// add
	acf.newDateTimePicker = function ( $input, args ) {
		// bail early if no datepicker library
		if ( typeof $.timepicker === 'undefined' ) {
			return false;
		}

		// defaults
		args = args || {};

		// initialize
		$input.datetimepicker( args );

		// wrap the datepicker (only if it hasn't already been wrapped)
		if ( $( 'body > #ui-datepicker-div' ).exists() ) {
			$( 'body > #ui-datepicker-div' ).wrap(
				'<div class="acf-ui-datepicker" />'
			);
		}
	};
} )( jQuery );
