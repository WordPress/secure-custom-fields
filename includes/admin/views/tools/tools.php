<?php
/**
 * View to output admin tools for both archive and single
 *
 * @package wordpress/secure-custom-fields
 */

$class = $active ? 'single' : 'grid';
$tool  = $active ? ' tool-' . $active : '';
?>
<div id="acf-admin-tools" class="wrap<?php echo esc_attr( $tool ); ?>">

	<h1><?php esc_html_e( 'Tools', 'secure-custom-fields' ); ?> <?php
	if ( $active ) :
		?>
		<a class="page-title-action" href="<?php echo esc_url( acf_get_admin_tools_url() ); ?>"><?php esc_html_e( 'Back to all tools', 'secure-custom-fields' ); ?></a><?php endif; ?></h1>

	<div class="acf-meta-box-wrap -<?php echo esc_attr( $class ); ?>">
		<?php do_meta_boxes( $screen_id, 'normal', '' ); ?>
	</div>
</div>
