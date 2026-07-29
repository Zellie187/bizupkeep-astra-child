<?php
/**
 * Template Name: BizUpKeep My Bookkeeping
 * Description:   Client Portal bookkeeping page - lets a logged-in
 *                 client capture income/expenses against their
 *                 company's chart of accounts, view financial
 *                 statements (Trial Balance / Income Statement /
 *                 Balance Sheet), and export their ledger for Sage,
 *                 Xero or QuickBooks. Backed by the BizUpKeep
 *                 Bookkeeping plugin's services via bizhub()->container(),
 *                 same integration pattern as every other portal page.
 *                 Form submission and CSV export are both handled on
 *                 template_redirect (see functions.php's
 *                 bizupkeep_child_handle_capture_transaction_submission()
 *                 and bizupkeep_child_handle_bookkeeping_export_request())
 *                 so they can redirect/stream before this template ever
 *                 renders. This page is only reachable while logged in -
 *                 see bizupkeep_child_guard_client_portal().
 *
 * @package BizUpKeep_Astra_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$wp_user_id = get_current_user_id();
$companies  = bizupkeep_child_client_companies_for_form( $wp_user_id );

$company_uuid = isset( $_GET['company'] ) ? sanitize_text_field( wp_unslash( $_GET['company'] ) ) : '';

if ( '' === $company_uuid && 1 === count( $companies ) ) {
	$company_uuid = $companies[0]['uuid'];
}

$company = '' !== $company_uuid ? bizupkeep_child_get_owned_company( $wp_user_id, $company_uuid ) : null;

$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';

if ( ! in_array( $tab, array( 'dashboard', 'capture', 'accounts', 'statements', 'export', 'import', 'recurring', 'customers', 'invoices' ), true ) ) {
	$tab = 'dashboard';
}

if ( null !== $company ) {
	bizupkeep_child_bookkeeping_ensure_seeded( $company->getUuid() );
}
?>

<main id="bizupkeep-bookkeeping" class="bizupkeep-bookkeeping">
	<div class="bizupkeep-bookkeeping-inner">

		<h1><?php esc_html_e( 'My Bookkeeping', 'bizupkeep-astra-child' ); ?></h1>

		<?php if ( empty( $companies ) ) : ?>

			<p><?php esc_html_e( 'No companies found on your account yet.', 'bizupkeep-astra-child' ); ?></p>

		<?php else : ?>

			<?php if ( count( $companies ) > 1 ) : ?>
				<form method="get" class="bizupkeep-bookkeeping-company-picker">
					<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
					<label for="bizupkeep-bookkeeping-company"><?php esc_html_e( 'Company', 'bizupkeep-astra-child' ); ?></label>
					<select id="bizupkeep-bookkeeping-company" name="company" onchange="this.form.submit()">
						<?php foreach ( $companies as $c ) : ?>
							<option value="<?php echo esc_attr( $c['uuid'] ); ?>" <?php selected( $c['uuid'], $company_uuid ); ?>>
								<?php echo esc_html( $c['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</form>
			<?php endif; ?>

			<?php if ( null === $company ) : ?>

				<p><?php esc_html_e( 'Please select a company above.', 'bizupkeep-astra-child' ); ?></p>

			<?php else : ?>

				<nav class="bizupkeep-bookkeeping-tabs">
					<?php
					$tabs = array(
						'dashboard'  => __( 'Dashboard', 'bizupkeep-astra-child' ),
						'capture'    => __( 'Capture', 'bizupkeep-astra-child' ),
						'recurring'  => __( 'Recurring', 'bizupkeep-astra-child' ),
						'customers'  => __( 'Customers', 'bizupkeep-astra-child' ),
						'invoices'   => __( 'Invoices', 'bizupkeep-astra-child' ),
						'import'     => __( 'Import', 'bizupkeep-astra-child' ),
						'accounts'   => __( 'Chart of Accounts', 'bizupkeep-astra-child' ),
						'statements' => __( 'Statements', 'bizupkeep-astra-child' ),
						'export'     => __( 'Export', 'bizupkeep-astra-child' ),
					);
					foreach ( $tabs as $tab_key => $tab_label ) :
						$url = add_query_arg( array( 'tab' => $tab_key, 'company' => $company->getUuid() ), get_permalink() );
						?>
						<a href="<?php echo esc_url( $url ); ?>" class="bizupkeep-btn <?php echo $tab === $tab_key ? 'bizupkeep-btn-primary' : 'bizupkeep-btn-secondary'; ?>">
							<?php echo esc_html( $tab_label ); ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<?php if ( isset( $_GET['captured'] ) ) : ?>
					<p class="bizupkeep-status-pill"><?php esc_html_e( 'Transaction captured.', 'bizupkeep-astra-child' ); ?></p>
				<?php elseif ( isset( $_GET['capture_error'] ) ) : ?>
					<p class="bizupkeep-status-pill"><?php esc_html_e( 'Could not capture that transaction - please check your details and try again.', 'bizupkeep-astra-child' ); ?></p>
				<?php elseif ( isset( $_GET['export_error'] ) ) : ?>
					<p class="bizupkeep-status-pill"><?php esc_html_e( 'Could not generate that export - please try again.', 'bizupkeep-astra-child' ); ?></p>
				<?php elseif ( isset( $_GET['import_error'] ) ) : ?>
					<p class="bizupkeep-status-pill"><?php esc_html_e( 'Could not process that statement - please check the file and try again.', 'bizupkeep-astra-child' ); ?></p>
				<?php elseif ( isset( $_GET['categorized'] ) ) : ?>
					<p class="bizupkeep-status-pill"><?php esc_html_e( 'Transaction(s) categorized.', 'bizupkeep-astra-child' ); ?></p>
				<?php elseif ( isset( $_GET['ignored'] ) ) : ?>
					<p class="bizupkeep-status-pill"><?php esc_html_e( 'Transaction ignored.', 'bizupkeep-astra-child' ); ?></p>
				<?php elseif ( isset( $_GET['recurring_created'] ) ) : ?>
					<p class="bizupkeep-status-pill"><?php esc_html_e( 'Recurring transaction created.', 'bizupkeep-astra-child' ); ?></p>
				<?php elseif ( isset( $_GET['recurring_confirmed'] ) ) : ?>
					<p class="bizupkeep-status-pill"><?php esc_html_e( 'Recurring transaction(s) confirmed and posted.', 'bizupkeep-astra-child' ); ?></p>
				<?php elseif ( isset( $_GET['recurring_skipped'] ) ) : ?>
					<p class="bizupkeep-status-pill"><?php esc_html_e( 'Recurring transaction skipped.', 'bizupkeep-astra-child' ); ?></p>
				<?php elseif ( isset( $_GET['recurring_template_updated'] ) ) : ?>
					<p class="bizupkeep-status-pill"><?php esc_html_e( 'Recurring template updated.', 'bizupkeep-astra-child' ); ?></p>
				<?php elseif ( isset( $_GET['recurring_error'] ) ) : ?>
					<p class="bizupkeep-status-pill"><?php esc_html_e( 'Could not complete that recurring transaction action - please try again.', 'bizupkeep-astra-child' ); ?></p>
				<?php elseif ( isset( $_GET['customer_saved'] ) ) : ?>
					<p class="bizupkeep-status-pill"><?php esc_html_e( 'Customer saved.', 'bizupkeep-astra-child' ); ?></p>
				<?php elseif ( isset( $_GET['customer_deleted'] ) ) : ?>
					<p class="bizupkeep-status-pill"><?php esc_html_e( 'Customer deleted.', 'bizupkeep-astra-child' ); ?></p>
				<?php elseif ( isset( $_GET['customer_error'] ) ) : ?>
					<p class="bizupkeep-status-pill"><?php esc_html_e( 'Could not save that customer - please check your details and try again.', 'bizupkeep-astra-child' ); ?></p>
				<?php elseif ( isset( $_GET['invoice_created'] ) ) : ?>
					<p class="bizupkeep-status-pill"><?php esc_html_e( 'Invoice created as a draft.', 'bizupkeep-astra-child' ); ?></p>
				<?php elseif ( isset( $_GET['invoice_sent'] ) ) : ?>
					<p class="bizupkeep-status-pill"><?php esc_html_e( 'Invoice sent.', 'bizupkeep-astra-child' ); ?></p>
				<?php elseif ( isset( $_GET['invoice_paid'] ) ) : ?>
					<p class="bizupkeep-status-pill"><?php esc_html_e( 'Payment recorded.', 'bizupkeep-astra-child' ); ?></p>
				<?php elseif ( isset( $_GET['invoice_voided'] ) ) : ?>
					<p class="bizupkeep-status-pill"><?php esc_html_e( 'Invoice voided.', 'bizupkeep-astra-child' ); ?></p>
				<?php elseif ( isset( $_GET['invoice_error'] ) ) : ?>
					<p class="bizupkeep-status-pill"><?php esc_html_e( 'Could not complete that invoice action - please try again.', 'bizupkeep-astra-child' ); ?></p>
				<?php endif; ?>

				<?php if ( 'dashboard' === $tab ) : ?>
					<?php bizupkeep_child_render_bookkeeping_dashboard_tab( $company ); ?>
				<?php elseif ( 'capture' === $tab ) : ?>
					<?php bizupkeep_child_render_bookkeeping_capture_tab( $company ); ?>
				<?php elseif ( 'recurring' === $tab ) : ?>
					<?php bizupkeep_child_render_bookkeeping_recurring_tab( $company ); ?>
				<?php elseif ( 'customers' === $tab ) : ?>
					<?php bizupkeep_child_render_bookkeeping_customers_tab( $company ); ?>
				<?php elseif ( 'invoices' === $tab ) : ?>
					<?php bizupkeep_child_render_bookkeeping_invoices_tab( $company ); ?>
				<?php elseif ( 'import' === $tab ) : ?>
					<?php bizupkeep_child_render_bookkeeping_import_tab( $company ); ?>
				<?php elseif ( 'accounts' === $tab ) : ?>
					<?php bizupkeep_child_render_bookkeeping_accounts_tab( $company ); ?>
				<?php elseif ( 'statements' === $tab ) : ?>
					<?php bizupkeep_child_render_bookkeeping_statements_tab( $company ); ?>
				<?php elseif ( 'export' === $tab ) : ?>
					<?php bizupkeep_child_render_bookkeeping_export_tab( $company ); ?>
				<?php endif; ?>

			<?php endif; ?>

		<?php endif; ?>

	</div>
</main>

<?php
get_footer();
