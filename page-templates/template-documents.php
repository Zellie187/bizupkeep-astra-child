<?php
/**
 * Template Name: BizUpKeep My Documents
 * Description:   Client Portal document collection page - one section
 *                 per company the logged-in client has an application
 *                 for, each with its current workflow status, already-
 *                 uploaded documents, and an upload form while the
 *                 workflow is waiting on documents. Upload handling is
 *                 in bizupkeep_child_handle_document_upload() in
 *                 functions.php (runs on template_redirect, before
 *                 this template renders, so it can redirect on
 *                 success/failure). This page is only reachable while
 *                 logged in - see bizupkeep_child_guard_client_portal().
 *
 * @package BizUpKeep_Astra_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$sections = bizupkeep_child_documents_sections( get_current_user_id() );
?>

<main id="bizupkeep-documents" class="bizupkeep-documents">
	<div class="bizupkeep-documents-inner">

		<h1><?php esc_html_e( 'My Documents', 'bizupkeep-astra-child' ); ?></h1>

		<?php if ( isset( $_GET['uploaded'] ) ) : ?>
			<p class="bizupkeep-status-pill"><?php esc_html_e( 'Document uploaded.', 'bizupkeep-astra-child' ); ?></p>
		<?php elseif ( isset( $_GET['upload_error'] ) ) : ?>
			<p class="bizupkeep-status-pill"><?php esc_html_e( "Something went wrong with that upload - please check the file (PDF, JPG or PNG, max 5MB) and try again.", 'bizupkeep-astra-child' ); ?></p>
		<?php endif; ?>

		<?php if ( array() === $sections ) : ?>

			<p><?php esc_html_e( "You don't have any company registrations in progress yet.", 'bizupkeep-astra-child' ); ?></p>
			<p>
				<a href="<?php echo esc_url( home_url( '/apply/' ) ); ?>" class="bizupkeep-btn bizupkeep-btn-primary">
					<?php esc_html_e( 'Start an Application', 'bizupkeep-astra-child' ); ?>
				</a>
			</p>

		<?php else : ?>

			<?php foreach ( $sections as $section ) : ?>
				<section class="bizupkeep-company-documents">
					<h2><?php echo esc_html( $section['company_name'] ); ?></h2>
					<span class="bizupkeep-status-pill"><?php echo esc_html( $section['status_label'] ); ?></span>

					<?php if ( array() !== $section['documents'] ) : ?>
						<table class="bizupkeep-documents-table">
							<tbody>
								<?php foreach ( $section['documents'] as $document ) : ?>
									<tr>
										<td><?php echo esc_html( $document['category_label'] ); ?></td>
										<td><?php echo esc_html( $document['name'] ); ?></td>
										<td><?php echo esc_html( $document['uploaded_at'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>

					<?php if ( $section['can_upload'] ) : ?>
						<form method="post" enctype="multipart/form-data" class="bizupkeep-upload-form">
							<?php wp_nonce_field( 'bizupkeep_upload_document', 'bizupkeep_upload_nonce' ); ?>
							<input type="hidden" name="company_uuid" value="<?php echo esc_attr( $section['company_uuid'] ); ?>">

							<label for="bizupkeep-category-<?php echo esc_attr( $section['company_uuid'] ); ?>">
								<?php esc_html_e( 'Document Type', 'bizupkeep-astra-child' ); ?>
							</label>
							<select id="bizupkeep-category-<?php echo esc_attr( $section['company_uuid'] ); ?>" name="category" required>
								<option value=""><?php esc_html_e( 'Select an option', 'bizupkeep-astra-child' ); ?></option>
								<option value="id_document"><?php esc_html_e( 'ID Document', 'bizupkeep-astra-child' ); ?></option>
								<option value="proof_of_address"><?php esc_html_e( 'Proof of Address', 'bizupkeep-astra-child' ); ?></option>
							</select>

							<label for="bizupkeep-file-<?php echo esc_attr( $section['company_uuid'] ); ?>">
								<?php esc_html_e( 'File (PDF, JPG or PNG, max 5MB)', 'bizupkeep-astra-child' ); ?>
							</label>
							<input type="file" id="bizupkeep-file-<?php echo esc_attr( $section['company_uuid'] ); ?>" name="document" accept=".pdf,.jpg,.jpeg,.png" required>

							<p>
								<button type="submit" class="bizupkeep-btn bizupkeep-btn-primary">
									<?php esc_html_e( 'Upload', 'bizupkeep-astra-child' ); ?>
								</button>
							</p>
						</form>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>

		<?php endif; ?>

	</div>
</main>

<?php
get_footer();
