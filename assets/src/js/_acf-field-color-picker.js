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

		setValue: function ( val ) {
			// update input (with change)
			acf.val( this.$input(), val );

			// update iris
			this.$inputText().iris( 'color', val );
		},

		initialize: function () {
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

		onDuplicate: function ( e, $el, $duplicate ) {
			// The wpColorPicker library does not provide a destroy method.
			// Manually reset DOM by replacing elements back to their original state.
			$colorPicker = $duplicate.find( '.wp-picker-container' );
			$inputText = $duplicate.find( 'input[type="text"]' );
			$colorPicker.replaceWith( $inputText );
		},
	} );

	acf.registerFieldType( Field );
} )( jQuery );
