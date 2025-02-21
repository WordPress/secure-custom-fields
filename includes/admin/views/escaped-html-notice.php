<?php

$acf_plugin_name  = 'Secure Custom Fields';
$acf_plugin_name  = '<strong>' . $acf_plugin_name . ' &mdash;</strong>';
$acf_class        = 'notice-error';
$acf_user_can_acf = false;

if ( current_user_can( acf_get_setting( 'capability' ) ) ) {
	$acf_user_can_acf = true;
	$acf_dismiss_url  = add_query_arg( array( 'acf-dismiss-esc-html-notice' => wp_create_nonce( 'acf/dismiss_escaped_html_notice' ) ) );

	// "Show/Hide Details" is a button for accessibility purposes, because it isn't a link. But since the design shows a link, we need to make it look like a link.
	$acf_style_button_as_link = trim(
		'display: inline;
		padding: 0;
		background: none;
		border: none;
		color: #0073aa;
		text-decoration: underline;
		cursor: pointer;'
	);

	$acf_show_details  = '<button style="' . esc_attr( $acf_style_button_as_link ) . '" class="acf-show-more-details">' . __( 'Show&nbsp;details', 'secure-custom-fields' ) . '</button>';
	$acf_show_details .= ' | <a class="acf-dismiss-permanently-button" href="' . esc_url( $acf_dismiss_url ) . '">' . __( 'Dismiss permanently', 'secure-custom-fields' ) . '</a>';
} else {
	$acf_show_details = __( 'Please contact your site administrator or developer for more details.', 'secure-custom-fields' );
}

$acf_error_msg = sprintf(
	/* translators: %1$s - name of the SCF plugin. %2$s - Link to documentation. */
	__( '%1$s SCF automatically escapes unsafe HTML when rendered by <code>the_field</code> or the ACF shortcode. We\'ve detected the output of some of your fields has been modified by this change, but this may not be a breaking change.', 'secure-custom-fields' ),
	$acf_plugin_name
);


?>
<div class="acf-admin-notice notice acf-escaped-html-notice <?php echo esc_attr( $acf_class ); ?>">
	<p style="margin-bottom: 0.5em; padding-bottom: 2px;"><?php echo acf_esc_html( $acf_error_msg ); ?></p>
	<p style="margin: 0.5em 0; padding: 2px;"><?php echo acf_esc_html( $acf_show_details ); ?></p>
	<?php if ( $acf_user_can_acf && ! empty( $acf_escaped ) ) : ?>
		<div class="acf-error-details" style="display: none; list-style: disc; margin-left: 14px;">
			<ul class="acf-error-details" style="display: none; list-style: disc; margin-left: 14px;">
				<?php
				foreach ( $acf_escaped as $acf_field_key => $acf_data ) {
					$acf_error = sprintf(
						/* translators: %1$s - The selector used  %2$s The field name  3%$s The parent function name */
						__( '%1$s (%2$s) - rendered via %3$s', 'secure-custom-fields' ),
						$acf_data['selector'],
						$acf_data['field'],
						$acf_data['function']
					);

					echo '<li>' . esc_html( $acf_error ) . '</li>';
				}
				?>
			</ul>
			<p style="margin: 0.5em 0; padding: 2px;">
				<?php
				$acf_clear_logs_url = add_query_arg( array( 'acf-clear-esc-html-log' => wp_create_nonce( 'acf/clear_escaped_html_log' ) ) );
				// translators: %s - The clear log button opening HTML tag. %s - The closing HTML tag.
				echo acf_esc_html( '<i>' . sprintf( __( 'This data is logged as we detect values that have been changed during output. %1$sClear log and dismiss%2$s after escaping the values in your code. The notice will reappear if we detect changed values again.', 'secure-custom-fields' ), '<a class="acf-clear-log-button" href="' . esc_url( $acf_clear_logs_url ) . '">', '</a>' ) . '</i>' );
				?>
			</p>
		</div>
	<?php endif; ?>
</div>