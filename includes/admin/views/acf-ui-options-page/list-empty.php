<?php
/**
 * The empty list state for an ACF Options Page
 *
 * @package wordpress/secure-custom-fields
 */

?>
<script>document.body.classList.add('acf-no-options-pages');</script>
<div class="acf-no-options-pages-wrapper">
	<div class="acf-no-options-pages-inner">
		<img src="<?php echo esc_url( acf_get_url( 'assets/images/empty-post-types.svg' ) ); ?>" />

		<?php
		$acf_options_pages_title = __( 'Add Your First Options Page', 'secure-custom-fields' );
		?>

		<h2><?php echo esc_html( $acf_options_pages_title ); ?></h2>
		<p><?php echo acf_esc_html( __( 'SCF options pages are custom admin pages for managing global settings via fields. You can create multiple pages and sub-pages.', 'secure-custom-fields' ) ); ?></p>

		<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=acf-ui-options-page' ) ); ?>" class="acf-btn"><i class="acf-icon acf-icon-plus"></i> <?php esc_html_e( 'Add Options Page', 'secure-custom-fields' ); ?></a>
	</div>
</div>
