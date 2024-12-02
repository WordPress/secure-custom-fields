<?php
//phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included template file.

$browse_fields_tabs = array( 'popular' => __( 'Popular', 'secure-custom-fields' ) );
$browse_fields_tabs = $browse_fields_tabs + acf_get_field_categories_i18n();

?>
<div class="acf-browse-fields-modal-wrap">
	<div class="acf-modal acf-browse-fields-modal">
		<div class="acf-field-picker">
			<div class="acf-modal-title">
				<h1><?php esc_html_e( 'Select Field Type', 'secure-custom-fields' ); ?></h1>
				<span class="acf-search-field-types-wrap">
					<input class="acf-search-field-types" type="search" placeholder="<?php esc_attr_e( 'Search fields...', 'secure-custom-fields' ); ?>" />
				</span>
			</div>
			<div class="acf-modal-content">
				<?php
				foreach ( $browse_fields_tabs as $name => $label ) {
					acf_render_field_wrap(
						array(
							'type'  => 'tab',
							'label' => $label,
							'key'   => 'acf_browse_fields_tabs',
						)
					);

					printf(
						'<div class="acf-field-types-tab" data-category="%s"></div>',
						esc_attr( $name )
					);
				}
				?>
				<div class="acf-field-type-search-results"></div>
				<div class="acf-field-type-search-no-results">
					<img src="<?php echo esc_url( acf_get_url( 'assets/images/face-sad.svg' ) ); ?>" />
					<p class="acf-no-results-text">
						<?php
						printf(
							/* translators: %s: The invalid search term */
							acf_esc_html( __( "No search results for '%s'", 'secure-custom-fields' ) ),
							'<span class="acf-invalid-search-term"></span>'
						);
						?>
					</p>
					<p>
						<?php
						$browse_popular_link = '<a href="#" class="acf-browse-popular-fields">' . esc_html( __( 'Popular fields', 'secure-custom-fields' ) ) . '</a>';
						printf(
							/* translators: %s: A link to the popular fields used in ACF */
							acf_esc_html( __( 'Try a different search term or browse %s', 'secure-custom-fields' ) ),
							$browse_popular_link //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						);
						?>
					</p>
				</div>
			</div>
			<div class="acf-modal-toolbar acf-field-picker-toolbar">
				<div class="acf-field-picker-label">
					<input class="acf-insert-field-label" type="text" placeholder="<?php esc_attr_e( 'Field Label', 'secure-custom-fields' ); ?>" />
				</div>
				<div class="acf-field-picker-actions">
					<button class="button acf-cancel acf-modal-close"><?php esc_html_e( 'Cancel', 'secure-custom-fields' ); ?></button>
					<button class="acf-btn acf-select-field"><?php esc_html_e( 'Select Field', 'secure-custom-fields' ); ?></button>
				</div>
			</div>
		</div>
		<div class="acf-field-type-preview">
			<div class="field-type-info">
				<h2 class="field-type-name"></h2>
				<p class="field-type-desc"></p>
				<div class="field-type-preview-container">
					<img class="field-type-image" />
				</div>
			</div>
		</div>
	</div>
	<div class="acf-modal-backdrop acf-modal-close"></div>
</div>
