<?php
/**
 * Template Name: BizUpKeep My Applications
 * Description:   Client Portal application status page - one row per
 *                 company the logged-in client has an application
 *                 for, with its current workflow status and, while
 *                 AwaitingPayment, a "Pay Now" link into WooCommerce.
 *                 See bizupkeep_child_applications_sections() and the
 *                 "Payment (Company Registration workflow's
 *                 AwaitingPayment step)" section in functions.php for
 *                 how payment capture/confirmation actually works.
 *                 This page is only reachable while logged in - see
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

		<?php if ( array() === $sections ) : ?>

			<p><?php esc_html_e( "You don't have any company registrations in progress yet.", 'bizupkeep-astra-child' ); ?></p>
			<p>
				<a href="<?php echo esc_url( home_url( '/apply/' ) ); ?>" class="bizupkeep-btn bizupkeep-btn-primary">
					<?php esc_html_e( 'Start an Application', 'bizupkeep-astra-child' ); ?>
				</a>
			</p>

		<?php else : ?>

			<table class="bizupkeep-applications-table">
				<tbody>
					<?php foreach ( $sections as $section ) : ?>
						<tr>
							<td><?php echo esc_html( $section['company_name'] ); ?></td>
							<td><span class="bizupkeep-status-pill"><?php echo esc_html( $section['status_label'] ); ?></span></td>
							<td>
								<?php if ( null !== $section['pay_url'] ) : ?>
									<a href="<?php echo esc_url( $section['pay_url'] ); ?>" class="bizupkeep-btn bizupkeep-btn-primary">
										<?php esc_html_e( 'Pay Now', 'bizupkeep-astra-child' ); ?>
									</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

		<?php endif; ?>

	</div>
</main>

<?php
get_footer();
