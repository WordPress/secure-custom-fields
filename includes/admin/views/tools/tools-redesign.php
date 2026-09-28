<?php
/**
 * View to output the redesigned admin tools screen.
 *
 * @package wordpress/secure-custom-fields
 */

$active = acf_maybe_get_GET( 'tool' );
$tool   = $active ? ' tool-' . $active : '';
?>
<div id="acf-admin-tools" class="wrap<?php echo esc_attr( $tool ); ?>">

	<h1><?php esc_html_e( 'Tools', 'secure-custom-fields' ); ?></h1>

	<div id="scf-tools-redesign-root" class="scf-tools-redesign"></div>
</div>
