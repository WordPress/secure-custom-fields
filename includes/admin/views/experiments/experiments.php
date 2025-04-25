<?php
/**
 * Admin Experiments View
 *
 * @package    Secure Custom Fields
 * @since      6.4.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current screen and experiments.
$screen      = get_current_screen();
$experiments = acf()->admin_experiments->get_experiments();
?>
<div class="wrap" id="scf-admin-experiments">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Experiments', 'secure-custom-fields' ); ?></h1>
	<hr class="wp-header-end">

	<div class="scf-experiments-list">
		<div class="scf-experiments-header">
			<p><?php esc_html_e( 'Enable or disable beta features. These features are in development and may change in future releases.', 'secure-custom-fields' ); ?></p>
		</div>
		
		<?php if ( empty( $experiments ) ) : ?>
			<div class="scf-no-experiments">
				<p><?php esc_html_e( 'No beta features are currently available.', 'secure-custom-fields' ); ?></p>
			</div>
		<?php else : ?>
		<form method="post" action="">
			<?php wp_nonce_field( 'scf_experiments_update', 'scf_experiments_nonce' ); ?>
			<table class="widefat scf-experiments-table">
				<thead>
					<tr>
						<th class="scf-experiment-status"><?php esc_html_e( 'Enabled', 'secure-custom-fields' ); ?></th>
						<th class="scf-experiment-info"><?php esc_html_e( 'Experiment', 'secure-custom-fields' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $experiments as $experiment ) : ?>
						<tr>
							<td class="scf-experiment-status">
								<input type="checkbox" 
									id="scf_experiment_<?php echo esc_attr( $experiment->name ); ?>" 
									name="scf_experiments[<?php echo esc_attr( $experiment->name ); ?>]" 
									value="1"
									<?php checked( $experiment->is_enabled() ); ?>
								/>
							</td>
							<td class="scf-experiment-info">
								<label for="scf_experiment_<?php echo esc_attr( $experiment->name ); ?>">
									<strong><?php echo esc_html( $experiment->title ); ?></strong>
								</label>
								<p class="description"><?php echo esc_html( $experiment->description ); ?></p>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="submit">
				<input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Changes', 'secure-custom-fields' ); ?>" />
			</p>
		</form>
		<?php endif; ?>
	</div>
</div>

<style>
.scf-experiments-list {
	max-width: 800px;
	margin-top: 20px;
}
.scf-experiments-header {
	margin-bottom: 20px;
}
.scf-experiments-table {
	border-spacing: 0;
	width: 100%;
	clear: both;
	margin: 0;
}
.scf-experiments-table th {
	padding: 8px 10px;
}
.scf-experiments-table td {
	padding: 15px 10px;
	vertical-align: top;
}
.scf-experiment-status {
	width: 60px;
	text-align: center;
}
.scf-experiment-info label {
	font-size: 14px;
	line-height: 1.3;
}
.scf-experiment-info .description {
	margin: 4px 0 0;
	color: #646970;
}
.scf-no-experiments {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	margin-top: 20px;
	text-align: center;
}
</style> 