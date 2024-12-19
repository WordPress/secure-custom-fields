( function ( $ ) {
	/**
	 *  CloneDisplayFieldSetting
	 *
	 *  Extra logic for this field setting
	 *
	 *  @date	18/4/18
	 *  @since	ACF 5.6.9
	 *
	 *  @param	void
	 *  @return	void
	 */

	var CloneDisplayFieldSetting = acf.FieldSetting.extend( {
		type: 'clone',
		name: 'display',
		render: function () {
			// vars
			var display = this.field.val();

			// set data attribute used by CSS to hide/show
			this.$fieldObject.attr( 'data-display', display );
		},
	} );

	acf.registerFieldSetting( CloneDisplayFieldSetting );

	/**
	 *  ClonePrefixLabelFieldSetting
	 *
	 *  Extra logic for this field setting
	 *
	 *  @date	18/4/18
	 *  @since	ACF 5.6.9
	 *
	 *  @param	void
	 *  @return	void
	 */

	var ClonePrefixLabelFieldSetting = acf.FieldSetting.extend( {
		type: 'clone',
		name: 'prefix_label',
		render: function () {
			// vars
			var prefix = '';

			// if checked
			if ( this.field.val() ) {
				prefix = this.fieldObject.prop( 'label' ) + ' ';
			}

			// update HTML
			this.$( 'code' ).html( prefix + '%field_label%' );
		},
	} );

	acf.registerFieldSetting( ClonePrefixLabelFieldSetting );

	/**
	 *  ClonePrefixNameFieldSetting
	 *
	 *  Extra logic for this field setting
	 *
	 *  @date	18/4/18
	 *  @since	ACF 5.6.9
	 *
	 *  @param	void
	 *  @return	void
	 */

	var ClonePrefixNameFieldSetting = acf.FieldSetting.extend( {
		type: 'clone',
		name: 'prefix_name',
		render: function () {
			// vars
			var prefix = '';

			// if checked
			if ( this.field.val() ) {
				prefix = this.fieldObject.prop( 'name' ) + '_';
			}

			// update HTML
			this.$( 'code' ).html( prefix + '%field_name%' );
		},
	} );

	acf.registerFieldSetting( ClonePrefixNameFieldSetting );

	/**
	 *  cloneFieldSelectHelper
	 *
	 *  Customizes the clone field setting Select2 isntance
	 *
	 *  @date	18/4/18
	 *  @since	ACF 5.6.9
	 *
	 *  @param	void
	 *  @return	void
	 */

	var cloneFieldSelectHelper = new acf.Model( {
		filters: {
			select2_args: 'select2Args',
		},

		select2Args: function ( options, $select, data, $el, instance ) {
			// check
			if ( data.ajaxAction == 'acf/fields/clone/query' ) {
				// remain open on select
				options.closeOnSelect = false;

				// customize ajaxData function
				instance.data.ajaxData = this.ajaxData;
			}

			// return
			return options;
		},

		ajaxData: function ( data ) {
			// find current fields
			data.fields = {};

			// loop
			acf.getFieldObjects().map( function ( fieldObject ) {
				// append
				data.fields[ fieldObject.prop( 'key' ) ] = {
					key: fieldObject.prop( 'key' ),
					type: fieldObject.prop( 'type' ),
					label: fieldObject.prop( 'label' ),
					ancestors: fieldObject.getParents().length,
				};
			} );

			// append title
			data.title = $( '#title' ).val();

			// return
			return data;
		},
	} );
} )( jQuery );
