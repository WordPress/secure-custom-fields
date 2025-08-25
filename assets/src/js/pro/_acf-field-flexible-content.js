( function ( $ ) {
	var Field = acf.Field.extend( {
		type: 'flexible_content',
		wait: '',

		events: {
			'click [data-name="add-layout"]': 'onClickAdd',
			'click [data-name="duplicate-layout"]': 'onClickDuplicate',
			'click [data-name="collapse-layout"]': 'onClickCollapse',
			'click [data-name="more-layout-actions"]': 'onClickMoreActions',
			'click .acf-fc-expand-all': 'onClickExpandAll',
			'click .acf-fc-collapse-all': 'onClickCollapseAll',
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

		$moreLayoutActions: function () {
			return this.$( '.tmpl-more-layout-actions:last' );
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

		getMoreLayoutActionsHTML: function () {
			return this.$moreLayoutActions().html();
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
				handle: '> .acf-fc-layout-actions-wrap .acf-fc-layout-handle',
				forceHelperSize: true,
				zIndex: 9999,
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
			$( document ).on( 'click focusin', function ( e ) {
				if (
					! $( e.target ).closest( '.acf-flexible-content .layout' )
						.length
				) {
					self.setActiveLayout( $( [] ) );
				}
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

			// Add event handlers for setting active layout
			const self = this;
			this.$control().on(
				'click focus',
				'> .values > .layout',
				function ( event ) {
					const layout = $( event.target ).closest( '.layout' );
					self.setActiveLayout( layout );
				}
			);

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

		setActiveLayout: function ( $layout ) {
			// Remove active-layout class from all layouts
			$( '.layout' ).removeClass( 'active-layout' );

			// Add active-layout class to the provided layout if it exists
			if ( $layout.length ) {
				$layout.addClass( 'active-layout' );
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

			if ( count >= layoutMax ) {
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
			// Check the context data attribute to determine how to handle the add
			if ( $el.data( 'context' ) === 'layout' ) {
				$layout = $el.closest( '.layout' );
				$layout.addClass( '-hover' );
			} else if ( $el.data( 'context' ) === 'top-actions' ) {
				$layout = $el
					.closest( '.acf-flexible-content' )
					.find( '.values .layout' )
					.first();
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
			this.setActiveLayout( $el );

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

			this.setActiveLayout( $el );
			// Return new layout.
			return $el;
		},

		onClickToggleLayout: function ( event, layout ) {
			const disabledInput = layout.find(
				'.acf-fc-layout-disabled:first'
			);

			if ( layout.attr( 'data-enabled' ) === '1' ) {
				// Disable the layout
				layout.attr( 'data-enabled', '0' );
				disabledInput.val( '1' );
			} else {
				// Enable the layout
				layout.attr( 'data-enabled', '1' );
				disabledInput.val( '0' );
			}

			// Trigger change event to save the state
			this.$input().trigger( 'change' );
		},
		onClickRenameLayout: function ( event, layout ) {
			const currentName = layout
				.find( '.acf-fc-layout-custom-label:first' )
				.val();

			const popupOptions = {
				context: this,
				title: acf.__( 'Rename Layout' ),
				textConfirm: acf.__( 'Rename' ),
				textCancel: acf.__( 'Cancel' ),
				currentName: currentName,
				openedBy: layout
					.find( 'a[data-name="more-layout-actions"]' )
					.first(),
				width: '500px',
				confirm: function ( event, element, newName ) {
					this.renameLayout( layout, newName );
				},
				cancel: function () {
					layout.removeClass( '-hover' );
				},
			};

			// Create new rename popup dialog
			new RenameLayoutPopup( popupOptions );
		},

		renameLayout: function ( layout, newName ) {
			// Set the escaped new name in the hidden input
			layout
				.find( '.acf-fc-layout-custom-label:first' )
				.val( acf.strEscape( newName ) );

			const titleElement = layout.find( '.acf-fc-layout-title:first' );

			// Update the visible title
			titleElement.text( newName );

			if ( newName.length ) {
				// Mark as renamed with custom label
				layout.attr( 'data-renamed', '1' );
			} else {
				// Restore original title if name is empty
				let originalTitle = layout
					.find( '.acf-fc-layout-original-title:first' )
					.text()
					.trim();

				// Remove parentheses from original title
				originalTitle = originalTitle.substring(
					1,
					originalTitle.length - 1
				);

				titleElement.text( originalTitle );
				layout.attr( 'data-renamed', '0' );
			}

			// Trigger change event to save the state
			this.$input().trigger( 'change' );
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
			// Bypass confirmation when holding down "shift" key.
			if ( e.shiftKey ) {
				return this.removeLayout( $el );
			}
			// add class
			$el.addClass( '-hover' );

			// add tooltip
			const tooltipOptions = {
				confirmRemove: true,
				context: this,
				title: acf.__( 'Delete Layout' ),
				text: acf.__( 'Are you sure you want to delete this layout?' ),
				textConfirm: acf.__( 'Delete' ),
				textCancel: acf.__( 'Cancel' ),
				openedBy: $el
					.find( 'a[data-name="more-layout-actions"]' )
					.first(),
				width: '500px',
				confirm: function () {
					this.removeLayout( $el );
				},
				cancel: function () {
					$el.removeClass( '-hover' );
				},
			};
			// Check if layout has a custom label
			const customLabel = $el.data( 'label' );
			if ( customLabel && customLabel.length ) {
				// Customize the popup title and text with the layout label
				tooltipOptions.title = acf
					.__( 'Delete %s' )
					.replace( '%s', acf.strEscape( customLabel ) );
				tooltipOptions.text = acf
					.__( 'Are you sure you want to delete %s?' )
					.replace( '%s', customLabel );
			}

			// Create and show the confirmation popup
			acf.newPopup( tooltipOptions );
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

		onClickExpandAll: function ( e, $el ) {
			e.preventDefault();
			const self = this;
			this.$layouts().each( function () {
				self.openLayout( $( this ) );
			} );
		},

		onClickCollapseAll: function ( e, $el ) {
			e.preventDefault();
			const self = this;
			this.$layouts().each( function () {
				self.closeLayout( $( this ) );
			} );
		},

		onClickMoreActions: function ( e, $el ) {
			const $layout = $el.closest( '.layout' );
			new MoreLayoutActionsPopup( {
				target: $el,
				targetConfirm: false,
				text: this.getMoreLayoutActionsHTML(),
				context: this,
				confirm: function ( e, $el ) {
					// Check if the clicked element is a toggle action
					const action = $el.data( 'action' );
					if ( action === 'remove-layout' ) {
						this.onClickRemove( e, $layout );
					}
					if ( action === 'toggle-layout' ) {
						this.onClickToggleLayout( e, $layout );
					}
					if ( action === 'rename-layout' ) {
						this.onClickRenameLayout( e, $layout );
					}
				},
				cancel: function () {
					$layout
						.find( 'a[data-name="more-layout-actions"]' )
						.first()
						.trigger( 'focus' );
				},
			} );
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
			const $input = $layout.children( 'input' );
			const prefix = $input
				.attr( 'name' )
				.replace( '[acf_fc_layout]', '' );

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
						if ( $layout.data( 'renamed' ) === 1 ) {
							$layout
								.find( '.acf-fc-layout-original-title' )
								.first()
								.html( `(${ html })` );
						} else {
							$layout
								.find( '.acf-fc-layout-title' )
								.first()
								.html( html );
						}
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
			this.position();
		},
		show: function () {
			const $flexibleContent = this.get( 'target' ).closest(
				'.acf-flexible-content'
			);
			$( $flexibleContent ).append( this.$el );
		},
		position: function () {
			const $popup = this.$el;
			const $target = this.get( 'target' );
			const $container = $target.closest( '.acf-flexible-content' );
			positionPopup( $popup, $target, $container, 8 );
		},
	} );

	/**
	 * MoreLayoutActionsPopup
	 *
	 * Popup for showing more layout actions (remove, toggle, rename)
	 */
	const MoreLayoutActionsPopup = acf.models.TooltipConfirm.extend( {
		events: {
			'click [data-action]': 'onConfirm',
			'keydown [role="menu"]': 'onKeyDown',
		},

		render: function () {
			const $layout = this.get( 'target' ).closest( '.layout' );
			this.html( this.get( 'text' ) );
			this.$el.addClass( 'acf-fc-popup acf-more-layout-actions' );

			// Add enable-layout class if layout is disabled
			if ( $layout.attr( 'data-enabled' ) === '0' ) {
				this.$el.addClass( 'enable-layout' );
			} else {
				this.$el.removeClass( 'enable-layout' );
			}

			const self = this;
			setTimeout( function () {
				self.$el.find( 'a' ).first().trigger( 'focus' );
			}, 1 );
		},

		show: function () {
			const $layout = this.get( 'target' ).closest( '.layout' );
			$layout.append( this.$el );
		},

		position: function () {
			const $popup = this.$el;
			const $target = this.get( 'target' );
			const $container = $target.closest( '.layout' );
			positionPopup( $popup, $target, $container, 2 );
		},

		onKeyDown: function ( event, $el ) {
			if (
				[ 'ArrowDown', 'ArrowUp', 'Escape', 'Tab' ].indexOf(
					event.key
				) === -1
			) {
				return;
			}

			event.preventDefault();

			if ( event.key === 'Escape' ) {
				return void this.onCancel( event, $el );
			}

			const $menuItems = this.$el
				.find( '[role="menu"]' )
				.find( '[role="menuitem"]:visible' );
			const $activeElement = $( document.activeElement );
			const menuItemsLength = $menuItems.length;

			let currentIndex = $menuItems.index( $activeElement );
			let nextIndex;

			if (
				event.key === 'ArrowDown' ||
				( event.key === 'Tab' && ! event.shiftKey )
			) {
				nextIndex = ( currentIndex + 1 ) % menuItemsLength;
			} else {
				nextIndex =
					( currentIndex - 1 + menuItemsLength ) % menuItemsLength;
			}

			$menuItems.eq( nextIndex ).trigger( 'focus' );
		},
	} );

	/**
	 * RenameLayoutPopup
	 *
	 * Popup dialog for renaming layout labels
	 */
	const RenameLayoutPopup = acf.models.PopupConfirm.extend( {
		events: {
			'click [data-event="close"]': 'onCancel',
			'click .acf-close-popup': 'onClickClose',
			keydown: 'onPressEscapeClose',
			'click [data-event="confirm"]': 'onConfirm',
			'click .acf-reset-label': 'onReset',
			'submit .acf-rename-layout-form': 'onConfirm',
		},

		tmpl: function () {
			const resetButton =
				this.get( 'currentName' ) === ''
					? ''
					: `<button type="button" data-event="reset-label" class="acf-btn acf-btn-secondary acf-reset-label">${ acf.strEscape(
							acf.__( 'Remove Custom Label' )
					  ) }</button>`;

			return `
        <div id="acf-popup" role="dialog" aria-labelledby="acf-rename-layout-title" tabindex="-1">
          <div class="acf-popup-box acf-box acf-confirm-popup acf-rename-layout-popup">
            <div class="title">
              <h3 id="acf-rename-layout-title">${ this.get( 'title' ) }</h3>
              <a href="#" data-event="close" aria-label="${ acf.strEscape(
					acf.__( 'Close modal' )
				) }">
                <i class="acf-icon -close"></i>
              </a>
            </div>
            <form class="inner acf-rename-layout-form">
              <div class="acf-field">
                <div class="acf-label">
                  <label for="acf-new-layout-label">${ acf.strEscape(
						acf.__( 'New Label' )
					) }</label>
                </div>
                <div class="acf-input">
                  <input id="acf-new-layout-label" type="text" name="acf_new_layout_label" value="${ this.get(
						'currentName'
					) }">
                </div>
              </div>
              <div class="acf-actions">
                ${ resetButton }
                <button type="button" data-event="close" class="acf-btn acf-btn-secondary acf-close-popup">${ acf.strEscape(
					this.get( 'textCancel' )
				) }</button>
                <button type="submit" data-event="confirm" class="acf-btn acf-btn-primary acf-confirm">${ acf.strEscape(
					this.get( 'textConfirm' )
				) }</button>
              </div>
            </form>
          </div>
          <div class="bg" data-event="close"></div>
        </div>`;
		},

		render: function () {
			acf.models.PopupConfirm.prototype.render.apply( this, arguments );
			setTimeout( () => {
				const $input = this.$el.find( 'input#acf-new-layout-label' );
				const textLength = $input.val().length;
				$input.trigger( 'focus' );
				$input[ 0 ].setSelectionRange( textLength, textLength );
			}, 1 );
		},

		onConfirm: function ( event, $el ) {
			event.preventDefault();
			event.stopPropagation();

			const newName = this.$el.find( 'input#acf-new-layout-label' ).val();
			this.close();

			const confirmCallback = this.get( 'confirm' );
			const context = this.get( 'context' ) || this;
			confirmCallback.apply( context, [ event, $el, newName ] );
		},

		onReset: function ( event, $el ) {
			event.preventDefault();
			event.stopPropagation();
			this.$el.find( 'input#acf-new-layout-label' ).val( '' );
			this.onConfirm( event, $el );
		},
	} );

	/**
	 * positionPopup
	 *
	 * Utility function to position popup relative to target within container
	 */
	const positionPopup = function ( $popup, $target, $container, offset ) {
		if ( ! $target.length || ! $container.length ) return;

		const targetOffset = $target.offset();
		const containerOffset = $container.offset();
		const targetWidth = $target.outerWidth();
		const targetHeight = $target.outerHeight();
		const popupWidth = $popup.outerWidth();
		const popupHeight = $popup.outerHeight();
		const isRTL = $( 'body' ).hasClass( 'rtl' );
		const windowScrollTop = $( window ).scrollTop();
		const windowHeight = $( window ).height();

		let left, positionClass;
		let top =
			targetOffset.top - containerOffset.top + targetHeight + offset;
		let isAbove = false;

		// Check if popup would be cut off at bottom of viewport
		if (
			targetOffset.top + targetHeight + popupHeight + offset >
				windowScrollTop + windowHeight &&
			targetOffset.top - popupHeight - offset > windowScrollTop
		) {
			top = targetOffset.top - containerOffset.top - popupHeight - offset;
			isAbove = true;
		}

		if ( isRTL ) {
			left = targetOffset.left - containerOffset.left;
			positionClass = isAbove ? 'bottom-left' : 'top-left';
		} else {
			left =
				targetOffset.left -
				containerOffset.left +
				targetWidth -
				popupWidth;
			positionClass = isAbove ? 'bottom-right' : 'top-right';
		}

		$popup
			.removeClass( 'top-right bottom-right top-left bottom-left' )
			.css( { position: 'absolute', top: top, left: left } )
			.addClass( positionClass );
	};

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
