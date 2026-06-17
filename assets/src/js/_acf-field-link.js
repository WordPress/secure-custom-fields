( function ( $, undefined ) {
	var Field = acf.Field.extend( {
		type: 'link',

		events: {
			'click a[data-name="add"]': 'onClickEdit',
			'click a[data-name="edit"]': 'onClickEdit',
			'click a[data-name="remove"]': 'onClickRemove',
			'change .link-node': 'onChange',
		},

		$control: function () {
			return this.$( '.acf-link' );
		},

		$node: function () {
			return this.$( '.link-node' );
		},

		getValue: function () {
			// vars
			var node = this.$node()[ 0 ];

			// return false if empty
			if ( ! node || ! node.getAttribute( 'href' ) ) {
				return false;
			}

			// return
			return {
				title: node.innerHTML,
				url: node.getAttribute( 'href' ),
				target: node.getAttribute( 'target' ) || '',
			};
		},

		setValue: function ( val ) {
			// default
			val = acf.parseArgs( val, {
				title: '',
				url: '',
				target: '',
			} );

			// vars
			var el = this.$el[ 0 ];
			var div = this.$control()[ 0 ];
			var node = this.$node()[ 0 ];

			// remove class
			div.classList.remove( '-value', '-external' );

			// add class
			if ( val.url ) div.classList.add( '-value' );
			if ( val.target === '_blank' ) div.classList.add( '-external' );

			// update text
			el.querySelector( '.link-title' ).innerHTML = val.title;
			var linkUrl = el.querySelector( '.link-url' );
			linkUrl.setAttribute( 'href', val.url );
			linkUrl.textContent = val.url;

			// update node
			node.innerHTML = val.title;
			node.setAttribute( 'href', val.url );
			node.setAttribute( 'target', val.target );

			// update inputs
			el.querySelector( '.input-title' ).value = val.title;
			el.querySelector( '.input-target' ).value = val.target;
			var inputUrl = el.querySelector( '.input-url' );
			inputUrl.value = val.url;
			inputUrl.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		},

		onClickEdit: function ( e, $el ) {
			acf.wpLink.open( this.$node() );
		},

		onClickRemove: function ( e, $el ) {
			this.setValue( false );
		},

		onChange: function ( e, $el ) {
			// get the changed value
			var val = this.getValue();

			// update inputs
			this.setValue( val );
		},
	} );

	acf.registerFieldType( Field );

	// manager
	acf.wpLink = new acf.Model( {
		getNodeValue: function () {
			var $node = this.get( 'node' );
			return {
				title: acf.decode( $node.html() ),
				url: $node.attr( 'href' ),
				target: $node.attr( 'target' ),
			};
		},

		setNodeValue: function ( val ) {
			var $node = this.get( 'node' );
			$node.text( val.title );
			$node.attr( 'href', val.url );
			$node.attr( 'target', val.target );
			$node.trigger( 'change' );
		},

		getInputValue: function () {
			return {
				title: $( '#wp-link-text' ).val(),
				url: $( '#wp-link-url' ).val(),
				target: $( '#wp-link-target' ).prop( 'checked' )
					? '_blank'
					: '',
			};
		},

		setInputValue: function ( val ) {
			$( '#wp-link-text' ).val( val.title );
			$( '#wp-link-url' ).val( val.url );
			$( '#wp-link-target' ).prop( 'checked', val.target === '_blank' );
		},

		open: function ( $node ) {
			// add events
			this.on( 'wplink-open', 'onOpen' );
			this.on( 'wplink-close', 'onClose' );

			// set node
			this.set( 'node', $node );

			// create textarea
			var $textarea = $(
				'<textarea id="acf-link-textarea" style="display:none;"></textarea>'
			);
			$( 'body' ).append( $textarea );

			// vars
			var val = this.getNodeValue();

			// open popup
			wpLink.open( 'acf-link-textarea', val.url, val.title, null );
		},

		onOpen: function () {
			// always show title (WP will hide title if empty)
			$( '#wp-link-wrap' ).addClass( 'has-text-field' );

			// set inputs
			var val = this.getNodeValue();
			this.setInputValue( val );

			// Update button text.
			if ( val.url && wpLinkL10n ) {
				$( '#wp-link-submit' ).val( wpLinkL10n.update );
			}
		},

		close: function () {
			wpLink.close();
		},

		onClose: function () {
			// Bail early if no node.
			// Needed due to WP triggering this event twice.
			if ( ! this.has( 'node' ) ) {
				return false;
			}

			// Determine context.
			var $submit = $( '#wp-link-submit' );
			var isSubmit = $submit.is( ':hover' ) || $submit.is( ':focus' );

			// Set value
			if ( isSubmit ) {
				var val = this.getInputValue();
				this.setNodeValue( val );
			}

			// Cleanup.
			this.off( 'wplink-open' );
			this.off( 'wplink-close' );
			$( '#acf-link-textarea' ).remove();
			this.set( 'node', null );
		},
	} );
} )( jQuery );
