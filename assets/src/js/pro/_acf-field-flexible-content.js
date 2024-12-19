( function ( $ ) {
	var Field = acf.Field.extend( {
		type: 'flexible_content',
		wait: '',

		events: {
			'click [data-name="add-layout"]': 'onClickAdd',
			'click [data-name="duplicate-layout"]': 'onClickDuplicate',
			'click [data-name="remove-layout"]': 'onClickRemove',
			'click [data-name="collapse-layout"]': 'onClickCollapse',
			showField: 'onShow',
			unloadField: 'onUnload',
			mouseover: 'onHover',
		},

		$control: function () {
			return this.$( '.acf-flexible-content:first' );
		},

		$layoutsWrap: function () {
			return this.$( '.acf-flexible-content:first > .values' );
		},

		$layouts: function () {
			return this.$( '.acf-flexible-content:first > .values > .layout' );
		},

		$layout: function ( index ) {
			return this.$(
				'.acf-flexible-content:first > .values > .layout:eq(' +
					index +
					')'
			);
		},

		$clonesWrap: function () {
			return this.$( '.acf-flexible-content:first > .clones' );
		},

		$clones: function () {
			return this.$( '.acf-flexible-content:first > .clones  > .layout' );
		},

		$clone: function ( name ) {
			return this.$(
				'.acf-flexible-content:first > .clones  > .layout[data-layout="' +
					name +
					'"]'
			);
		},

		$actions: function () {
			return this.$( '.acf-actions:last' );
		},

		$button: function () {
			return this.$( '.acf-actions:last .button' );
		},

		$popup: function () {
			return this.$( '.tmpl-popup:last' );
		},

		getPopupHTML: function () {
			var html = this.$popup().html();
			var $html = $( html );
			var self = this;

			// modify popup
			$html.find( '[data-layout]' ).each( function () {
				var $a = $( this );
				var min = $a.data( 'min' ) || 0;
				var max = $a.data( 'max' ) || 0;
				var name = $a.data( 'layout' ) || '';
				var count = self.countLayouts( name );

				// max
				if ( max && count >= max ) {
					$a.addClass( 'disabled' );
					return;
				}

				// min
				if ( min && count < min ) {
					var required = min - count;
					var title = acf.__(
						'{required} {label} {identifier} required (min {min})'
					);
					var identifier = acf._n( 'layout', 'layouts', required );

					// translate
					title = title.replace( '{required}', required );
					title = title.replace( '{label}', name ); // 5.5.0
					title = title.replace( '{identifier}', identifier );
					title = title.replace( '{min}', min );

					// badge
					$a.append(
						'<span class="badge" title="' +
							title +
							'">' +
							required +
							'</span>'
					);
				}
			} );

			// update
			html = $html.outerHTML();

			return html;
		},

		getValue: function () {
			return this.$layouts().length;
		},

		allowRemove: function () {
			var min = parseInt( this.get( 'min' ) );
			return ! min || min < this.val();
		},

		allowAdd: function () {
			var max = parseInt( this.get( 'max' ) );
			return ! max || max > this.val();
		},

		isFull: function () {
			var max = parseInt( this.get( 'max' ) );
			return max && this.val() >= max;
		},

		addSortable: function ( self ) {
			// bail early if max 1 row
			if ( this.get( 'max' ) == 1 ) {
				return;
			}

			// add sortable
			this.$layoutsWrap().sortable( {
				items: '> .layout',
				handle: '> .acf-fc-layout-handle',
				forceHelperSize: true,
				forcePlaceholderSize: true,
				scroll: true,
				stop: function ( event, ui ) {
					self.render();
				},
				update: function ( event, ui ) {
					self.$input().trigger( 'change' );
				},
			} );
		},

		addCollapsed: function () {
			var indexes = preference.load( this.get( 'key' ) );

			// bail early if no collapsed
			if ( ! indexes ) {
				return false;
			}

			// loop
			this.$layouts().each( function ( i ) {
				if ( indexes.indexOf( i ) > -1 ) {
					$( this ).addClass( '-collapsed' );
				}
			} );
		},

		addUnscopedEvents: function ( self ) {
			// invalidField
			this.on( 'invalidField', '.layout', function ( e ) {
				self.onInvalidField( e, $( this ) );
			} );
		},

		initialize: function () {
			// add unscoped events
			this.addUnscopedEvents( this );

			// add collapsed
			this.addCollapsed();

			// disable clone
			acf.disable( this.$clonesWrap(), this.cid );

			// render
			this.render();
		},

		render: function () {
			// update order number
			this.$layouts().each( function ( i ) {
				$( this )
					.find( '.acf-fc-layout-order:first' )
					.html( i + 1 );
			} );

			// empty
			if ( this.val() == 0 ) {
				this.$control().addClass( '-empty' );
			} else {
				this.$control().removeClass( '-empty' );
			}

			// max
			if ( this.isFull() ) {
				this.$button().addClass( 'disabled' );
			} else {
				this.$button().removeClass( 'disabled' );
			}
		},

		onShow: function ( e, $el, context ) {
			// get sub fields
			var fields = acf.getFields( {
				is: ':visible',
				parent: this.$el,
			} );

			// trigger action
			// - ignore context, no need to pass through 'conditional_logic'
			// - this is just for fields like google_map to render itself
			acf.doAction( 'show_fields', fields );
		},

		countLayouts: function ( name ) {
			return this.$layouts().filter( function () {
				return $( this ).data( 'layout' ) === name;
			} ).length;
		},

		countLayoutsByName: function ( currentLayout ) {
			const layoutMax = currentLayout.data( 'max' );
			if ( ! layoutMax ) {
				return true;
			}
			const name = currentLayout.data( 'layout' ) || '';
			const count = this.countLayouts( name );

			if( count >= layoutMax ) {
				let text = acf.__(
					'This field has a limit of {max} {label} {identifier}'
				);
				const identifier = acf._n( 'layout', 'layouts', layoutMax );
				const layoutLabel = '"' + currentLayout.data( 'label' ) + '"';
				text = text.replace( '{max}', layoutMax );
				text = text.replace( '{label}', layoutLabel );
				text = text.replace( '{identifier}', identifier );

				this.showNotice( {
					text: text,
					type: 'warning',
				} );

				return false;
			}

			return true;
		},

		validateAdd: function () {
			// return true if allowed
			if ( this.allowAdd() ) {
				return true;
			}

			var max = this.get( 'max' );
			var text = acf.__(
				'This field has a limit of {max} {label} {identifier}'
			);
			var identifier = acf._n( 'layout', 'layouts', max );

			text = text.replace( '{max}', max );
			text = text.replace( '{label}', '' );
			text = text.replace( '{identifier}', identifier );

			this.showNotice( {
				text: text,
				type: 'warning',
			} );

			return false;
		},

		onClickAdd: function ( e, $el ) {
			// validate
			if ( ! this.validateAdd() ) {
				return false;
			}

			// within layout
			var $layout = null;
			if ( $el.hasClass( 'acf-icon' ) ) {
				$layout = $el.closest( '.layout' );
				$layout.addClass( '-hover' );
			}

			// new popup
			var popup = new Popup( {
				target: $el,
				targetConfirm: false,
				text: this.getPopupHTML(),
				context: this,
				confirm: function ( e, $el ) {
					// check disabled
					if ( $el.hasClass( 'disabled' ) ) {
						return;
					}

					// add
					this.add( {
						layout: $el.data( 'layout' ),
						before: $layout,
					} );
				},
				cancel: function () {
					if ( $layout ) {
						$layout.removeClass( '-hover' );
					}
				},
			} );

			// add extra event
			popup.on( 'click', '[data-layout]', 'onConfirm' );
		},

		add: function ( args ) {
			// defaults
			args = acf.parseArgs( args, {
				layout: '',
				before: false,
			} );

			// validate
			if ( ! this.allowAdd() ) {
				return false;
			}

			// add row
			var $el = acf.duplicate( {
				target: this.$clone( args.layout ),
				append: this.proxy( function ( $el, $el2 ) {
					// append
					if ( args.before ) {
						args.before.before( $el2 );
					} else {
						this.$layoutsWrap().append( $el2 );
					}

					// enable
					acf.enable( $el2, this.cid );

					// render
					this.render();
				} ),
			} );

			// trigger change for validation errors
			this.$input().trigger( 'change' );

			return $el;
		},

		onClickDuplicate: function ( e, $el ) {
			var $layout = $el.closest( '.layout' );
			// Validate each layout's max count.
			if ( ! this.countLayoutsByName( $layout.first() ) ) {
				return false;
			}

			// Validate with warning.
			if ( ! this.validateAdd() ) {
				return false;
			}

			// get layout and duplicate it.
			this.duplicateLayout( $layout );
		},

		duplicateLayout: function ( $layout ) {
			// Validate without warning.
			if ( ! this.allowAdd() ) {
				return false;
			}

			var fieldKey = this.get( 'key' );

			// Duplicate layout.
			var $el = acf.duplicate( {
				target: $layout,

				// Provide a custom renaming callback to avoid renaming parent row attributes.
				rename: function ( name, value, search, replace ) {
					// Rename id attributes from "field_1-search" to "field_1-replace".
					if ( name === 'id' || name === 'for' ) {
						return value.replace(
							fieldKey + '-' + search,
							fieldKey + '-' + replace
						);

						// Rename name and for attributes from "[field_1][search]" to "[field_1][replace]".
					} else {
						return value.replace(
							fieldKey + '][' + search,
							fieldKey + '][' + replace
						);
					}
				},
				before: function ( $el ) {
					acf.doAction( 'unmount', $el );
				},
				after: function ( $el, $el2 ) {
					acf.doAction( 'remount', $el );
				},
			} );

			// trigger change for validation errors
			this.$input().trigger( 'change' );

			// Update order numbers.
			this.render();

			// Draw focus to layout.
			acf.focusAttention( $el );

			// Return new layout.
			return $el;
		},

		validateRemove: function () {
			// return true if allowed
			if ( this.allowRemove() ) {
				return true;
			}

			var min = this.get( 'min' );
			var text = acf.__(
				'This field requires at least {min} {label} {identifier}'
			);
			var identifier = acf._n( 'layout', 'layouts', min );

			// replace
			text = text.replace( '{min}', min );
			text = text.replace( '{label}', '' );
			text = text.replace( '{identifier}', identifier );

			// add notice
			this.showNotice( {
				text: text,
				type: 'warning',
			} );

			return false;
		},

		onClickRemove: function ( e, $el ) {
			var $layout = $el.closest( '.layout' );

			// Bypass confirmation when holding down "shift" key.
			if ( e.shiftKey ) {
				return this.removeLayout( $layout );
			}

			// add class
			$layout.addClass( '-hover' );

			// add tooltip
			var tooltip = acf.newTooltip( {
				confirmRemove: true,
				target: $el,
				context: this,
				confirm: function () {
					this.removeLayout( $layout );
				},
				cancel: function () {
					$layout.removeClass( '-hover' );
				},
			} );
		},

		removeLayout: function ( $layout ) {
			// reference
			var self = this;

			var endHeight = this.getValue() == 1 ? 60 : 0;

			// remove
			acf.remove( {
				target: $layout,
				endHeight: endHeight,
				complete: function () {
					// trigger change to allow attachment save
					self.$input().trigger( 'change' );

					// render
					self.render();
				},
			} );
		},

		onClickCollapse: function ( e, $el ) {
			var $layout = $el.closest( '.layout' );

			// toggle
			if ( this.isLayoutClosed( $layout ) ) {
				this.openLayout( $layout );
			} else {
				this.closeLayout( $layout );
			}
		},

		isLayoutClosed: function ( $layout ) {
			return $layout.hasClass( '-collapsed' );
		},

		openLayout: function ( $layout ) {
			$layout.removeClass( '-collapsed' );
			acf.doAction( 'show', $layout, 'collapse' );
		},

		closeLayout: function ( $layout ) {
			$layout.addClass( '-collapsed' );
			acf.doAction( 'hide', $layout, 'collapse' );

			// render
			// - no change could happen if layout was already closed. Only render when closing
			this.renderLayout( $layout );
		},

		renderLayout: function ( $layout ) {
			var $input = $layout.children( 'input' );
			var prefix = $input.attr( 'name' ).replace( '[acf_fc_layout]', '' );

			// ajax data
			var ajaxData = {
				action: 'acf/fields/flexible_content/layout_title',
				field_key: this.get( 'key' ),
				i: $layout.index(),
				layout: $layout.data( 'layout' ),
				value: acf.serialize( $layout, prefix ),
			};

			// ajax
			$.ajax( {
				url: acf.get( 'ajaxurl' ),
				data: acf.prepareForAjax( ajaxData ),
				dataType: 'html',
				type: 'post',
				success: function ( html ) {
					if ( html ) {
						$layout
							.children( '.acf-fc-layout-handle' )
							.html( html );
					}
				},
			} );
		},

		onUnload: function () {
			var indexes = [];

			// loop
			this.$layouts().each( function ( i ) {
				if ( $( this ).hasClass( '-collapsed' ) ) {
					indexes.push( i );
				}
			} );

			// allow null
			indexes = indexes.length ? indexes : null;

			// set
			preference.save( this.get( 'key' ), indexes );
		},

		onInvalidField: function ( e, $layout ) {
			// open if is collapsed
			if ( this.isLayoutClosed( $layout ) ) {
				this.openLayout( $layout );
			}
		},

		onHover: function () {
			// add sortable
			this.addSortable( this );

			// remove event
			this.off( 'mouseover' );
		},
	} );

	acf.registerFieldType( Field );

	/**
	 *  Popup
	 *
	 *  description
	 *
	 *  @date	7/4/18
	 *  @since	ACF 5.6.9
	 *
	 *  @param	type $var Description. Default.
	 *  @return	type Description.
	 */

	var Popup = acf.models.TooltipConfirm.extend( {
		events: {
			'click [data-layout]': 'onConfirm',
			'click [data-event="cancel"]': 'onCancel',
		},

		render: function () {
			// set HTML
			this.html( this.get( 'text' ) );

			// add class
			this.$el.addClass( 'acf-fc-popup' );
		},
	} );

	/**
	 *  conditions
	 *
	 *  description
	 *
	 *  @date	9/4/18
	 *  @since	ACF 5.6.9
	 *
	 *  @param	type $var Description. Default.
	 *  @return	type Description.
	 */

	// register existing conditions
	acf.registerConditionForFieldType( 'hasValue', 'flexible_content' );
	acf.registerConditionForFieldType( 'hasNoValue', 'flexible_content' );
	acf.registerConditionForFieldType( 'lessThan', 'flexible_content' );
	acf.registerConditionForFieldType( 'greaterThan', 'flexible_content' );

	// state
	var preference = new acf.Model( {
		name: 'this.collapsedLayouts',

		key: function ( key, context ) {
			var count = this.get( key + context ) || 0;

			// update
			count++;
			this.set( key + context, count, true );

			// modify fieldKey
			if ( count > 1 ) {
				key += '-' + count;
			}

			return key;
		},

		load: function ( key ) {
			var key = this.key( key, 'load' );
			var data = acf.getPreference( this.name );

			if ( data && data[ key ] ) {
				return data[ key ];
			} else {
				return false;
			}
		},

		save: function ( key, value ) {
			var key = this.key( key, 'save' );
			var data = acf.getPreference( this.name ) || {};

			// delete
			if ( value === null ) {
				delete data[ key ];

				// append
			} else {
				data[ key ] = value;
			}

			// allow null
			if ( $.isEmptyObject( data ) ) {
				data = null;
			}

			// save
			acf.setPreference( this.name, data );
		},
	} );
} )( jQuery );
