( function ( $, undefined ) {
	var Field = acf.Field.extend( {
		type: 'color_picker',

		wait: 'load',

		events: {
			duplicateField: 'onDuplicate',
		},

		$control: function () {
			return this.$( '.acf-color-picker' );
		},

		$input: function () {
			return this.$( 'input[type="hidden"]' );
		},

		$inputText: function () {
			return this.$( 'input[type="text"]' );
		},

		$inputNative: function () {
			return this.$( 'input[type="color"]' );
		},

		setValue: function ( val ) {
			// update input (with change)
			acf.val( this.$input(), val );

			// Native input only needs the hex value.
			var $native = this.$inputNative();
			if ( $native.length ) {
				$native.val( val || '' );
				return;
			}

			// update iris
			this.$inputText().iris( 'color', val );
		},

		initialize: function () {
			// Native input: the browser provides the picker, we only keep the
			// hidden input in sync with the hex value the input reports.
			if ( this.$control().data( 'native' ) === 1 ) {
				return this.initializeNative();
			}

			// vars
			var $input = this.$input();
			var $inputText = this.$inputText();

			// event
			var onChange = function ( e ) {
				// timeout is required to ensure the $input val is correct
				setTimeout( function () {
					acf.val( $input, $inputText.val() );
				}, 1 );
			};

			// args
			var args = {
				defaultColor: false,
				palettes: true,
				hide: true,
				change: onChange,
				clear: onChange,
			};
			if ( 'custom' === $inputText.data( 'acf-palette-type' ) ) {
				const paletteColor = $inputText
					.data( 'acf-palette-colors' )
					.match(
						/#(?:[0-9a-fA-F]{3}){1,2}|rgba?\([\s*(\d|.)+\s*,]+\)/g
					);
				if ( paletteColor ) {
					let trimmed = paletteColor.map( ( color ) => color.trim() );
					args.palettes = trimmed;
				}
			}

			// filter
			var args = acf.applyFilters( 'color_picker_args', args, this );
			if ( Array.isArray( args.palettes ) && args.palettes.length > 10 ) {
				// Add class for large custom palette styling
				this.$control().addClass(
					'acf-color-picker-large-custom-palette'
				);
			}
			// initialize
			$inputText.wpColorPicker( args );
		},

		initializeNative: function () {
			var self = this;
			var $native = this.$inputNative();

			$native.on( 'change input', function () {
				acf.val( self.$input(), $native.val() );
			} );
		},

		onDuplicate: function ( e, $el, $duplicate ) {
			// Native inputs carry no generated markup to clean up.
			if ( this.$inputNative().length ) {
				return;
			}

			// The wpColorPicker library does not provide a destroy method.
			// Manually reset DOM by replacing elements back to their original state.
			$colorPicker = $duplicate.find( '.wp-picker-container' );
			$inputText = $duplicate.find( 'input[type="text"]' );
			$colorPicker.replaceWith( $inputText );
		},
	} );

	acf.registerFieldType( Field );
} )( jQuery );
