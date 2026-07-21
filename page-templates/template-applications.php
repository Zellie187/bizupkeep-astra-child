<?php
/**
 * Template Name: BizUpKeep My Applications
 * Description:   Client Portal application status page - one row per
 *                 application (workflow instance) the logged-in
 *                 client has, across all three workflow types, with
 *                 its own status and, while AwaitingPayment, a "Pay
 *                 Now" link into WooCommerce. See
 *                 bizupkeep_child_applications_sections() and the
 *                 "Payment" section in functions.php for how payment
 *                 capture/confirmation actually works. This page is
 *                 only reachable while logged in - see
 *                 bizupkeep_child_guard_client_portal().
 *
 * @package BizUpKeep_Astra_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$sections = bizupkeep_child_applications_sections( get_current_user_id() );
?>

<main id="bizupkeep-applications" class="bizupkeep-applications">
	<div class="bizupkeep-applications-inner">

		<h1><?php esc_html_e( 'My Applications', 'bizupkeep-astra-child' ); ?></h1>

		<?php if ( isset( $_GET['names_resubmitted'] ) ) : ?>
			<p class="bizupkeep-status-pill"><?php esc_html_e( 'New names submitted - your application is back in review.', 'bizupkeep-astra-child' ); ?></p>
		<?php elseif ( isset( $_GET['resubmit_error'] ) ) : ?>
			<p class="bizupkeep-status-pill"><?php esc_html_e( 'Something went wrong submitting your new names - please try again.', 'bizupkeep-astra-child' ); ?></p>
		<?php endif; ?>

		<?php if ( array() === $sections ) : ?>

			<p><?php esc_html_e( "You don't have any applications in progress yet.", 'bizupkeep-astra-child' ); ?></p>
			<p>
				<a href="<?php echo esc_url( home_url( '/apply/' ) ); ?>" class="bizupkeep-btn bizupkeep-btn-primary">
					<?php esc_html_e( 'Start an Application', 'bizupkeep-astra-child' ); ?>
				</a>
			</p>

		<?php else : ?>

			<table class="bizupkeep-applications-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Type', 'bizupkeep-astra-child' ); ?></th>
						<th><?php esc_html_e( 'Company', 'bizupkeep-astra-child' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bizupkeep-astra-child' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sections as $section ) : ?>
						<tr>
							<td>
								<?php echo esc_html( $section['workflow_type_label'] ); ?>
								<?php if ( '' !== $section['filing_years'] ) : ?>
									<br><small><?php echo esc_html( $section['filing_years'] ); ?></small>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $section['company_name'] ); ?></td>
							<td><span class="bizupkeep-status-pill"><?php echo esc_html( $section['status_label'] ); ?></span></td>
							<td>
								<?php if ( null !== $section['quote_amount'] ) : ?>
									<p class="bizupkeep-quote-amount">
										<?php
										echo wp_kses_post(
											class_exists( 'WooCommerce' )
												? wc_price( $section['quote_amount'] )
												: 'R' . number_format( $section['quote_amount'], 2 )
										);
										?>
									</p>
								<?php endif; ?>
								<?php if ( null !== $section['pay_url'] ) : ?>
									<a href="<?php echo esc_url( $section['pay_url'] ); ?>" class="bizupkeep-btn bizupkeep-btn-primary">
										<?php esc_html_e( 'Pay Now', 'bizupkeep-astra-child' ); ?>
									</a>
								<?php endif; ?>
							</td>
						</tr>
						<?php if ( null !== $section['quote_amount'] && '' !== $section['quote_notes'] ) : ?>
							<tr class="bizupkeep-quote-notes-row">
								<td colspan="4">
									<p><strong><?php esc_html_e( 'Quote notes:', 'bizupkeep-astra-child' ); ?></strong> <?php echo esc_html( $section['quote_notes'] ); ?></p>
								</td>
							</tr>
						<?php endif; ?>
						<?php if ( $section['needs_resubmission'] ) : ?>
							<tr class="bizupkeep-resubmit-row">
								<td colspan="4">
									<div class="bizupkeep-resubmit-names">
										<?php if ( '' !== $section['rejection_reason'] ) : ?>
											<p><strong><?php esc_html_e( "Reviewer's notes:", 'bizupkeep-astra-child' ); ?></strong> <?php echo esc_html( $section['rejection_reason'] ); ?></p>
										<?php endif; ?>
										<p><?php esc_html_e( 'CIPC did not approve your proposed company name(s). Please submit new options below.', 'bizupkeep-astra-child' ); ?></p>
										<form method="post" class="bizupkeep-resubmit-form">
											<?php wp_nonce_field( 'bizupkeep_resubmit_names', 'bizupkeep_resubmit_nonce' ); ?>
											<input type="hidden" name="resubmit_workflow_uuid" value="<?php echo esc_attr( $section['workflow_uuid'] ); ?>">

											<label for="bizupkeep-resubmit-name-1-<?php echo esc_attr( $section['workflow_uuid'] ); ?>">
												<?php esc_html_e( 'Proposed Company Name (1st choice)', 'bizupkeep-astra-child' ); ?>
											</label>
											<input type="text" id="bizupkeep-resubmit-name-1-<?php echo esc_attr( $section['workflow_uuid'] ); ?>" name="resubmit_proposed_name[]">

											<label for="bizupkeep-resubmit-name-2-<?php echo esc_attr( $section['workflow_uuid'] ); ?>">
												<?php esc_html_e( '2nd choice (optional)', 'bizupkeep-astra-child' ); ?>
											</label>
											<input type="text" id="bizupkeep-resubmit-name-2-<?php echo esc_attr( $section['workflow_uuid'] ); ?>" name="resubmit_proposed_name[]">

											<label for="bizupkeep-resubmit-name-3-<?php echo esc_attr( $section['workflow_uuid'] ); ?>">
												<?php esc_html_e( '3rd choice (optional)', 'bizupkeep-astra-child' ); ?>
											</label>
											<input type="text" id="bizupkeep-resubmit-name-3-<?php echo esc_attr( $section['workflow_uuid'] ); ?>" name="resubmit_proposed_name[]">

											<label for="bizupkeep-resubmit-name-4-<?php echo esc_attr( $section['workflow_uuid'] ); ?>">
												<?php esc_html_e( '4th choice (optional)', 'bizupkeep-astra-child' ); ?>
											</label>
											<input type="text" id="bizupkeep-resubmit-name-4-<?php echo esc_attr( $section['workflow_uuid'] ); ?>" name="resubmit_proposed_name[]">

											<p>
												<button type="submit" class="bizupkeep-btn bizupkeep-btn-primary">
													<?php esc_html_e( 'Resubmit Names', 'bizupkeep-astra-child' ); ?>
												</button>
											</p>
										</form>
									</div>
								</td>
							</tr>
						<?php endif; ?>
					<?php endforeach; ?>
				</tbody>
			</table>

		<?php endif; ?>

	</div>
</main>

<?php
get_footer();
