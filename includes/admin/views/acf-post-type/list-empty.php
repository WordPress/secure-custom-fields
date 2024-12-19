<?php
/**
 * The empty list state for an ACF post type.
 *
 * @package wordpress/secure-custom-fields
 */

?><script>document.body.classList.add('acf-no-post-types');</script>
<div class="acf-no-post-types-wrapper">
	<div class="acf-no-post-types-inner">
		<img src="<?php echo esc_url( acf_get_url( 'assets/images/empty-post-types.svg' ) ); ?>" />
		<h2><?php esc_html_e( 'Add Your First Post Type', 'secure-custom-fields' ); ?></h2>
		<p><?php esc_html_e( 'Expand the functionality of WordPress beyond standard posts and pages with custom post types.', 'secure-custom-fields' ); ?></p>
		<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=acf-post-type' ) ); ?>" class="acf-btn"><i class="acf-icon acf-icon-plus"></i> <?php esc_html_e( 'Add Post Type', 'secure-custom-fields' ); ?></a>
	</div>
</div>
