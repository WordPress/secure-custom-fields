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

	var FlexibleContentLayoutFieldSetting = acf.FieldSetting.extend( {
		type: 'flexible_content',
		name: 'fc_layout',

		events: {
			'blur .layout-label': 'onChangeLabel',
			'blur .layout-name': 'onChangeName',
			'click .add-layout': 'onClickAdd',
			'click .acf-field-settings-fc_head': 'onClickEdit',
			'click .acf-field-setting-fc-duplicate': 'onClickDuplicate',
			'click .acf-field-setting-fc-delete': 'onClickDelete',
			'changed:layoutLabel': 'updateLayoutTitles',
			'changed:layoutName': 'updateLayoutTitles',
		},

		$input: function ( name ) {
			return $( '#' + this.getInputId() + '-' + name );
		},

		$list: function () {
			return this.$( '.acf-field-list:first' );
		},

		getInputId: function () {
			return this.fieldObject.getInputId() + '-layouts-' + this.field.get( 'id' );
		},

		// get all sub fields
		getFields: function () {
			return acf.getFieldObjects( { parent: this.$el } );
		},

		// get immediate children
		getChildren: function () {
			return acf.getFieldObjects( { list: this.$list() } );
		},

		initialize: function () {
			// add sortable
			var $tbody = this.$el.parent();
			$tbody.css( 'position', 'relative' );

			if ( ! $tbody.hasClass( 'ui-sortable' ) ) {
				$tbody.sortable( {
					items: '> .acf-field-setting-fc_layout',
					handle: '.acf-fc_draggable',
					forceHelperSize: true,
					forcePlaceholderSize: true,
					scroll: true,
					stop: this.proxy( function ( event, ui ) {
						this.fieldObject.save();
					} ),
				} );
			}

			// add meta to sub fields
			this.updateFieldLayouts();
			this.updateLayoutTitles();
		},

		updateFieldLayouts: function () {
			this.getChildren().map( this.updateFieldLayout, this );
		},

		updateFieldLayout: function ( field ) {
			field.prop( 'parent_layout', this.get( 'id' ) );
		},

		updateLayoutTitles: function () {
			const label = this.get( 'layoutLabel' );
			const name = this.get( 'layoutName' );

			const $layoutHeaderLabelText = this.$el.find( '> .acf-label .acf-fc-layout-label' );

			if ( label ) {
				$layoutHeaderLabelText.text( acf.decode( label ) );
			}

			const $layoutHeaderNameText = this.$el.find( '> .acf-label .acf-fc-layout-name span' );

			if ( name ) {
				$layoutHeaderNameText.text( name );
				$layoutHeaderNameText.parent().css( 'display', '' );
			} else {
				$layoutHeaderNameText.parent().css( 'display', 'none' );
			}
		},

		onClickEdit: function ( e ) {
			const $target = $( e.target );

			if (
				$target.hasClass( 'acf-btn' ) ||
				$target.hasClass( 'copyable' ) ||
				$target.parent().hasClass( 'acf-btn' ) ||
				$target.parent().hasClass( 'copyable' )
			) {
				return;
			}

			this.isOpen() ? this.close() : this.open();
		},

		isOpen: function ( e ) {
			const $settings = this.$el.children( '.acf-field-layout-settings' );
			return $settings.hasClass( 'open' );
		},

		open: function ( element, isAddingLayout ) {
			const $header = element
				? element.children( '.acf-field-settings-fc_head' )
				: this.$el.children( '.acf-field-settings-fc_head' );
			const $settings = element
				? element.children( '.acf-field-layout-settings' )
				: this.$el.children( '.acf-field-layout-settings' );
			const toggle = element
				? element.find( '.toggle-indicator' ).first()
				: this.$el.find( '.toggle-indicator' ).first();

			// action (show)
			acf.doAction( 'show', $settings );

			// open
			if ( isAddingLayout ) {
				$settings.slideDown( {
					complete: function () {
						$settings.find( '.layout-label' ).trigger( 'focus' );
					},
				} );
			} else {
				$settings.slideDown();
			}
			toggle.addClass( 'open' );
			if ( toggle.hasClass( 'closed' ) ) {
				toggle.removeClass( 'closed' );
			}
			$settings.addClass( 'open' );
			$header.addClass( 'open' );
		},

		close: function () {
			const $header = this.$el.children( '.acf-field-settings-fc_head' );
			const $settings = this.$el.children( '.acf-field-layout-settings' );
			const toggle = this.$el.find( '.toggle-indicator' ).first();

			// close
			$settings.slideUp();
			$settings.removeClass( 'open' );
			toggle.removeClass( 'open' );
			$header.removeClass( 'open' );
			if ( ! toggle.hasClass( 'closed' ) ) {
				toggle.addClass( 'closed' );
			}

			// action (hide)
			acf.doAction( 'hide', $settings );
		},

		onChangeLabel: function ( e, $el ) {
			let label = $el.val();
			const safeLabel = acf.encode( label );

			this.set( 'layoutLabel', safeLabel );
			this.$el.attr( 'data-layout-label', safeLabel );

			let $name = this.$input( 'name' );

			// render name
			if ( $name.val() == '' ) {
				acf.val( $name, acf.strSanitize( label ) );
				this.$el.find( '.layout-name' ).trigger( 'blur' );
			}
		},

		onChangeName: function ( e, $el ) {
			const sanitizedName = acf.strSanitize( $el.val(), false );

			$el.val( sanitizedName );
			this.set( 'layoutName', sanitizedName );

			this.$el.attr( 'data-layout-name', sanitizedName );
		},

		onClickAdd: function ( e, $el ) {
			e.preventDefault();
			var prevKey = this.get( 'id' );
			var newKey = acf.uniqid( 'layout_' );

			// duplicate
			$layout = acf.duplicate( {
				$el: this.$el,
				search: prevKey,
				replace: newKey,
				after: function ( $el, $el2 ) {
					var $list = $el2.find( '.acf-field-list:first' );

					// remove sub fields
					$list.children( '.acf-field-object' ).remove();

					// show empty
					$list.addClass( '-empty' );

					// reset layout meta values
					$el2.attr( 'data-layout-label', '' );
					$el2.attr( 'data-layout-name', '' );
					$el2.find( '.acf-fc-meta input' ).val( '' );
					$el2.find( 'label.acf-fc-layout-label' ).html( acf.__( 'Layout' ) );
				},
			} );

			// get layout
			var layout = acf.getFieldSetting( $layout );

			// update hidden input
			layout.$input( 'key' ).val( newKey );
			! this.isOpen() ? this.open( layout.$el, true ) : layout.$el.find( '.layout-label' ).trigger( 'focus' );

			// save
			this.fieldObject.save();
		},

		onClickDuplicate: function ( e, $el ) {
			e.preventDefault();
			var prevKey = this.get( 'id' );
			var newKey = acf.uniqid( 'layout_' );

			// duplicate
			$layout = acf.duplicate( {
				$el: this.$el,
				search: prevKey,
				replace: newKey,
			} );

			// get all fields in new layout similar to fieldManager.onDuplicateField().
			// important to run field.wipe() before making any changes to the "parent_layout" prop
			// to ensure the correct input is modified.
			var children = acf.getFieldObjects( { parent: $layout } );
			if ( children.length ) {
				// loop
				children.map( function ( child ) {
					// wipe field
					child.wipe();

					// if the child is open, re-fire the open method to ensure it's initialised correctly.
					if ( child.isOpen() ) {
						child.open();
					}

					// update parent
					child.updateParent();
				} );

				// action
				acf.doAction( 'duplicate_field_objects', children, this.fieldObject, this.fieldObject );
			}

			// get layout
			var layout = acf.getFieldSetting( $layout );

			// get current label/names so we can prepare to append 'copy'
			var label = layout.get( 'layoutLabel' );
			var name = layout.get( 'layoutName' );
			var end = name.split( '_' ).pop();
			var copy = acf.__( 'copy' );

			// increase suffix "1"
			if ( acf.isNumeric( end ) ) {
				var i = end * 1 + 1;
				label = label.replace( end, i );
				name = name.replace( end, i );

				// increase suffix "(copy1)"
			} else if ( end.indexOf( copy ) === 0 ) {
				var i = end.replace( copy, '' ) * 1;
				i = i ? i + 1 : 2;

				// replace
				label = label.replace( end, copy + i );
				name = name.replace( end, copy + i );

				// add default "(copy)"
			} else {
				label += ' (' + copy + ')';
				name += '_' + copy;
			}

			// update inputs and data attributes which will trigger header label updates too.
			layout.$input( 'label' ).val( label );
			layout.set( 'layoutLabel', label );
			layout.$el.attr( 'data-layout-label', label );
			layout.$input( 'name' ).val( name );
			layout.set( 'layoutName', name );
			layout.$el.attr( 'data-layout-name', name );

			// update hidden input
			layout.$input( 'key' ).val( newKey );
			! this.isOpen() ? this.open( layout.$el, true ) : layout.$el.find( '.layout-label' ).trigger( 'focus' );
			// save
			this.fieldObject.save();
		},

		onClickDelete: function ( e, $el ) {
			e.preventDefault();
			// Bypass confirmation when holding down "shift" key.
			if ( e.shiftKey ) {
				return this.delete();
			}

			// add class
			this.$el.addClass( '-hover' );

			// add tooltip
			var tooltip = acf.newTooltip( {
				confirmRemove: true,
				target: $el,
				context: this,
				confirm: function () {
					this.delete();
				},
				cancel: function () {
					this.$el.removeClass( '-hover' );
				},
			} );
		},

		delete: function () {
			var $siblings = this.$el.siblings( '.acf-field-setting-fc_layout' );

			// validate
			if ( ! $siblings.length ) {
				alert( acf.__( 'Flexible Content requires at least 1 layout' ) );
				return false;
			}

			// delete sub fields
			this.getFields().map( function ( child ) {
				child.delete( {
					animate: false,
				} );
			} );

			// remove tr
			acf.remove( this.$el );

			// save
			this.fieldObject.save();
		},
	} );

	acf.registerFieldSetting( FlexibleContentLayoutFieldSetting );

	/**
	 *  flexibleContentHelper
	 *
	 *  description
	 *
	 *  @date	19/4/18
	 *  @since	ACF 5.6.9
	 *
	 *  @param	type $var Description. Default.
	 *  @return	type Description.
	 */

	var flexibleContentHelper = new acf.Model( {
		actions: {
			sortstop_field_object: 'updateParentLayout',
			change_field_object_parent: 'updateParentLayout',
		},

		updateParentLayout: function ( fieldObject ) {
			var parent = fieldObject.getParent();

			// delete meta
			if ( ! parent || parent.prop( 'type' ) !== 'flexible_content' ) {
				fieldObject.prop( 'parent_layout', null );
				return;
			}

			// get layout
			var $layout = fieldObject.$el.closest( '.acf-field-setting-fc_layout' );
			var layout = acf.getFieldSetting( $layout );

			// check if previous prop exists
			// - if not, set prop to allow following code to trigger 'change' and save the field
			if ( ! fieldObject.has( 'parent_layout' ) ) {
				fieldObject.prop( 'parent_layout', 0 );
			}

			// update meta
			fieldObject.prop( 'parent_layout', layout.get( 'id' ) );
		},
	} );
} )( jQuery );
