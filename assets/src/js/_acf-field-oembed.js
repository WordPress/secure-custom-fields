( function ( $, undefined ) {
	var Field = acf.Field.extend( {
		type: 'oembed',

		events: {
			'click [data-name="clear-button"]': 'onClickClear',
			'keypress .input-search': 'onKeypressSearch',
			'keyup .input-search': 'onKeyupSearch',
			'change .input-search': 'onChangeSearch',
		},

		$control: function () {
			return this.$( '.acf-oembed' );
		},

		$input: function () {
			return this.$( '.input-value' );
		},

		$search: function () {
			return this.$( '.input-search' );
		},

		getValue: function () {
			return this.$input()[ 0 ].value;
		},

		getSearchVal: function () {
			return this.$search()[ 0 ].value;
		},

		setValue: function ( val ) {
			// class
			var control = this.$control()[ 0 ];
			if ( val ) {
				control.classList.add( 'has-value' );
			} else {
				control.classList.remove( 'has-value' );
			}

			acf.val( this.$input(), val );
			this.$search()[ 0 ].value = val || '';

			if ( val ) {
				this.maybeSearch();
			} else {
				this.$el[ 0 ].querySelector( '.canvas-media' ).innerHTML = '';
			}
		},

		showLoading: function ( show ) {
			acf.showLoading( this.$( '.canvas' ) );
		},

		hideLoading: function () {
			acf.hideLoading( this.$( '.canvas' ) );
		},

		maybeSearch: function () {
			// vars
			var prevUrl = this.val();
			var url = this.getSearchVal();

			// no value
			if ( ! url ) {
				return this.clear();
			}

			// fix missing 'http://' - causes the oembed code to error and fail
			if ( url.substr( 0, 4 ) != 'http' ) {
				url = 'http://' + url;
			}

			// bail early if no change
			if ( url === prevUrl ) return;

			// clear existing timeout
			var timeout = this.get( 'timeout' );
			if ( timeout ) {
				clearTimeout( timeout );
			}

			// set new timeout
			var callback = this.search.bind( this, url );
			this.set( 'timeout', setTimeout( callback, 300 ) );
		},

		search: function ( url ) {
			const ajaxData = {
				action: 'acf/fields/oembed/search',
				s: url,
				field_key: this.get( 'key' ),
				nonce: this.get( 'nonce' ),
			};

			// clear existing timeout
			let xhr = this.get( 'xhr' );
			if ( xhr ) {
				xhr.abort();
			}

			// loading
			this.showLoading();

			// query
			xhr = $.ajax( {
				url: acf.get( 'ajaxurl' ),
				data: acf.prepareForAjax( ajaxData ),
				type: 'post',
				dataType: 'json',
				context: this,
				success: function ( json ) {
					// error
					if ( ! json || ! json.html ) {
						json = {
							url: false,
							html: '',
						};
					}

					// update vars
					this.val( json.url );

					// jQuery .html() is kept: oEmbed provider markup may
					// contain <script> tags, which innerHTML would not run.
					this.$( '.canvas-media' ).html( json.html );
				},
				complete: function () {
					this.hideLoading();
				},
			} );

			this.set( 'xhr', xhr );
		},

		clear: function () {
			this.val( '' );
			this.$search()[ 0 ].value = '';
			this.$el[ 0 ].querySelector( '.canvas-media' ).innerHTML = '';
		},

		onClickClear: function ( e, $el ) {
			this.clear();
		},

		onKeypressSearch: function ( e, $el ) {
			if ( e.which == 13 ) {
				e.preventDefault();
				this.maybeSearch();
			}
		},

		onKeyupSearch: function ( e, $el ) {
			if ( $el[ 0 ].value ) {
				this.maybeSearch();
			}
		},

		onChangeSearch: function ( e, $el ) {
			this.maybeSearch();
		},
	} );

	acf.registerFieldType( Field );
} )( jQuery );
