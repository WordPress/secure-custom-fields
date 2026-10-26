<?php
/**
 * Inline Editing Block Template
 *
 * The wrapper repeats the field values in data attributes, so tests can wait
 * until the editor shows the preview rendered for a given value.
 *
 * @package scf-test-plugins
 */

$block_title    = (string) get_field( 'title' );
$block_subtitle = (string) get_field( 'subtitle' );
?>
<div class="inline-editing-block" data-rendered-title="<?php echo esc_attr( $block_title ); ?>" data-rendered-subtitle="<?php echo esc_attr( $block_subtitle ); ?>">
	<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- acf_inline_text_editing_attrs() returns escaped attributes. ?>
	<h3 class="inline-editing-block__title" <?php echo acf_inline_text_editing_attrs( 'title' ); ?>><?php echo esc_html( $block_title ); ?></h3>
	<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- acf_inline_text_editing_attrs() returns escaped attributes. ?>
	<p class="inline-editing-block__subtitle" <?php echo acf_inline_text_editing_attrs( 'subtitle' ); ?>><?php echo esc_html( $block_subtitle ); ?></p>
</div>
