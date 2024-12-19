( function ( $ ) {
	/*
	 *  Repeater
	 *
	 *  This field type requires some extra logic for its settings
	 *
	 *  @type	function
	 *  @date	24/10/13
	 *  @since	ACF 5.0.0
	 *
	 *  @param	n/a
	 *  @return	n/a
	 */

	var RepeaterCollapsedFieldSetting = acf.FieldSetting.extend( {
		type: 'repeater',
		name: 'collapsed',
		events: {
			'focus select': 'onFocus',
		},
		onFocus: function ( e, $el ) {
			// vars
			var $select = $el;

			// collapsed
			var choices = [];

			// keep 'null' choice
			choices.push( {
				label: $select.find( 'option[value=""]' ).text(),
				value: '',
			} );

			// find sub fields
			var $list = this.fieldObject.$( '.acf-field-list:first' );
			var fields = acf.getFieldObjects( {
				list: $list,
			} );

			// loop
			fields.map( function ( field ) {
				choices.push( {
					label: field.prop( 'label' ),
					value: field.prop( 'key' ),
				} );
			} );

			// render
			acf.renderSelect( $select, choices );
		},
	} );

	acf.registerFieldSetting( RepeaterCollapsedFieldSetting );
} )( jQuery );
