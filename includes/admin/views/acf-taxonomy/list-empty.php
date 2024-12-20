<?php
/**
 * The empty list state for an ACF taxonomy.
 *
 * @package wordpress/secure-custom-fields
 */

?>
<script>document.body.classList.add('acf-no-taxonomies');</script>
<div class="acf-no-taxonomies-wrapper">
	<div class="acf-no-taxonomies-inner">
		<img src="<?php echo esc_url( acf_get_url( 'assets/images/empty-taxonomies.svg' ) ); ?>" />
		<h2><?php esc_html_e( 'Add Your First Taxonomy', 'secure-custom-fields' ); ?></h2>
		<p><?php esc_html_e( 'Create custom taxonomies to classify post type content', 'secure-custom-fields' ); ?></p>
		<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=acf-taxonomy' ) ); ?>" class="acf-btn"><i class="acf-icon acf-icon-plus"></i> <?php esc_html_e( 'Add Taxonomy', 'secure-custom-fields' ); ?></a>
	</div>
</div>
