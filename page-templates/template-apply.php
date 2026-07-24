<?php
/**
 * Template Name: BizUpKeep Apply
 * Description:   Application intake page - lets a logged-in client start
 *                 a New Company Registration, a Company Amendment
 *                 (director/name/address change, any combination), or an
 *                 Annual Return filing. All three share this one page;
 *                 bizupkeep_child_render_application_type_fields() picks
 *                 which fields to render, and JS
 *                 (assets/js/custom.js) toggles which section is visible
 *                 based on the type radio buttons. Submission is handled
 *                 by bizupkeep_child_handle_apply_submission() in
 *                 functions.php (runs on template_redirect, before this
 *                 template ever renders, so it can redirect on success).
 *
 * @package BizUpKeep_Astra_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="bizupkeep-apply" class="bizupkeep-apply">
	<div class="bizupkeep-apply-inner">

		<?php if ( isset( $_GET['submitted'] ) ) : ?>

			<span class="bizupkeep-status-pill"><?php esc_html_e( 'Application Received', 'bizupkeep-astra-child' ); ?></span>
			<h1><?php esc_html_e( "Thanks - we've received your application.", 'bizupkeep-astra-child' ); ?></h1>
			<p><?php esc_html_e( 'One of our consultants will be in touch shortly. You can track its status from your Client Portal.', 'bizupkeep-astra-child' ); ?></p>
			<p>
				<a href="<?php echo esc_url( home_url( '/client-portal/client-portal-applications/' ) ); ?>" class="bizupkeep-btn bizupkeep-btn-primary">
					<?php esc_html_e( 'View My Applications', 'bizupkeep-astra-child' ); ?>
				</a>
			</p>

		<?php elseif ( ! is_user_logged_in() ) : ?>

			<h1><?php esc_html_e( 'Start Your Application', 'bizupkeep-astra-child' ); ?></h1>
			<p><?php esc_html_e( 'Log in or create a free account to start your application.', 'bizupkeep-astra-child' ); ?></p>
			<p>
				<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="bizupkeep-btn bizupkeep-btn-primary">
					<?php esc_html_e( 'Log In', 'bizupkeep-astra-child' ); ?>
				</a>
				<a href="<?php echo esc_url( wp_registration_url() ); ?>" class="bizupkeep-btn">
					<?php esc_html_e( 'Create an Account', 'bizupkeep-astra-child' ); ?>
				</a>
			</p>

		<?php else : ?>

			<h1><?php esc_html_e( 'Start Your Application', 'bizupkeep-astra-child' ); ?></h1>
			<p><?php esc_html_e( 'Tell us what you need - the form below adjusts to match.', 'bizupkeep-astra-child' ); ?></p>

			<?php if ( isset( $_GET['apply_error'] ) ) : ?>
				<p class="bizupkeep-status-pill"><?php esc_html_e( 'Something went wrong - please check the form and try again.', 'bizupkeep-astra-child' ); ?></p>
			<?php endif; ?>

			<?php
			$companies      = bizupkeep_child_client_companies_for_form( get_current_user_id() );
			$valid_types    = array( 'new_registration', 'company_amendment', 'annual_return' );
			$selected_type  = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'new_registration';
			$selected_type  = in_array( $selected_type, $valid_types, true ) ? $selected_type : 'new_registration';
			?>

			<form method="post" class="bizupkeep-upload-form bizupkeep-apply-form" id="bizupkeep-apply-form">
				<?php wp_nonce_field( 'bizupkeep_apply', 'bizupkeep_apply_nonce' ); ?>

				<fieldset class="bizupkeep-type-picker">
					<legend><?php esc_html_e( 'What do you need?', 'bizupkeep-astra-child' ); ?></legend>

					<label class="bizupkeep-type-option">
						<input type="radio" name="application_type" value="new_registration" <?php checked( $selected_type, 'new_registration' ); ?>>
						<?php esc_html_e( 'New Company Registration', 'bizupkeep-astra-child' ); ?>
					</label>
					<label class="bizupkeep-type-option">
						<input type="radio" name="application_type" value="company_amendment" <?php checked( $selected_type, 'company_amendment' ); ?>>
						<?php esc_html_e( 'Company Amendment', 'bizupkeep-astra-child' ); ?>
					</label>
					<label class="bizupkeep-type-option">
						<input type="radio" name="application_type" value="annual_return" <?php checked( $selected_type, 'annual_return' ); ?>>
						<?php esc_html_e( 'Annual Return', 'bizupkeep-astra-child' ); ?>
					</label>
				</fieldset>

				<!-- ============ New Company Registration ============ -->
				<section class="bizupkeep-apply-section" data-application-type="new_registration" <?php echo 'new_registration' === $selected_type ? '' : 'hidden'; ?>>

					<h2><?php esc_html_e( 'Company Details', 'bizupkeep-astra-child' ); ?></h2>
					<p class="bizupkeep-field-hint"><?php esc_html_e( 'Private Company (Pty) Ltd only - Close Corporations can no longer be registered.', 'bizupkeep-astra-child' ); ?></p>

					<p>
						<label for="bizupkeep-name-1"><?php esc_html_e( 'Proposed Company Name (1st choice)', 'bizupkeep-astra-child' ); ?></label>
						<input type="text" id="bizupkeep-name-1" name="proposed_name[]">
					</p>
					<p>
						<label for="bizupkeep-name-2"><?php esc_html_e( '2nd choice (optional)', 'bizupkeep-astra-child' ); ?></label>
						<input type="text" id="bizupkeep-name-2" name="proposed_name[]">
					</p>
					<p>
						<label for="bizupkeep-name-3"><?php esc_html_e( '3rd choice (optional)', 'bizupkeep-astra-child' ); ?></label>
						<input type="text" id="bizupkeep-name-3" name="proposed_name[]">
					</p>
					<p>
						<label for="bizupkeep-name-4"><?php esc_html_e( '4th choice (optional)', 'bizupkeep-astra-child' ); ?></label>
						<input type="text" id="bizupkeep-name-4" name="proposed_name[]">
					</p>

					<h3><?php esc_html_e( 'Registered Address', 'bizupkeep-astra-child' ); ?></h3>
					<?php bizupkeep_child_render_address_fields( 'company_address' ); ?>

					<h3><?php esc_html_e( 'Directors', 'bizupkeep-astra-child' ); ?></h3>
					<p class="bizupkeep-field-hint"><?php esc_html_e( 'At least one director is required. Up to 10 allowed.', 'bizupkeep-astra-child' ); ?></p>

					<div class="bizupkeep-repeater" data-repeater="new-director" data-max="10" data-template-id="bizupkeep-director-template">
						<div class="bizupkeep-repeater-blocks">
							<?php bizupkeep_child_render_director_fields( 'director', 0 ); ?>
						</div>
						<button type="button" class="bizupkeep-btn bizupkeep-repeater-add"><?php esc_html_e( '+ Add Director', 'bizupkeep-astra-child' ); ?></button>
					</div>

					<template id="bizupkeep-director-template">
						<?php bizupkeep_child_render_director_fields( 'director', '__INDEX__' ); ?>
					</template>

				</section>

				<!-- ============ Company Amendment ============ -->
				<section class="bizupkeep-apply-section" data-application-type="company_amendment" <?php echo 'company_amendment' === $selected_type ? '' : 'hidden'; ?>>

					<h2><?php esc_html_e( 'Which Company?', 'bizupkeep-astra-child' ); ?></h2>

					<?php bizupkeep_child_render_company_picker( 'amendment', $companies ); ?>

					<h2><?php esc_html_e( 'What Would You Like to Change?', 'bizupkeep-astra-child' ); ?></h2>

					<label class="bizupkeep-type-option">
						<input type="checkbox" name="amendment_types[]" value="director" class="bizupkeep-amendment-type-toggle" data-reveals="amendment-director">
						<?php esc_html_e( 'Director Amendment', 'bizupkeep-astra-child' ); ?>
					</label>
					<label class="bizupkeep-type-option">
						<input type="checkbox" name="amendment_types[]" value="name" class="bizupkeep-amendment-type-toggle" data-reveals="amendment-name">
						<?php esc_html_e( 'Name Change', 'bizupkeep-astra-child' ); ?>
					</label>
					<label class="bizupkeep-type-option">
						<input type="checkbox" name="amendment_types[]" value="address" class="bizupkeep-amendment-type-toggle" data-reveals="amendment-address">
						<?php esc_html_e( 'Address Change', 'bizupkeep-astra-child' ); ?>
					</label>

					<div class="bizupkeep-amendment-subsection" data-amendment-subsection="amendment-name" hidden>
						<h3><?php esc_html_e( 'Proposed New Name', 'bizupkeep-astra-child' ); ?></h3>
						<p>
							<label for="bizupkeep-amend-name-1"><?php esc_html_e( '1st choice', 'bizupkeep-astra-child' ); ?></label>
							<input type="text" id="bizupkeep-amend-name-1" name="amendment_proposed_name[]">
						</p>
						<p>
							<label for="bizupkeep-amend-name-2"><?php esc_html_e( '2nd choice (optional)', 'bizupkeep-astra-child' ); ?></label>
							<input type="text" id="bizupkeep-amend-name-2" name="amendment_proposed_name[]">
						</p>
						<p>
							<label for="bizupkeep-amend-name-3"><?php esc_html_e( '3rd choice (optional)', 'bizupkeep-astra-child' ); ?></label>
							<input type="text" id="bizupkeep-amend-name-3" name="amendment_proposed_name[]">
						</p>
						<p>
							<label for="bizupkeep-amend-name-4"><?php esc_html_e( '4th choice (optional)', 'bizupkeep-astra-child' ); ?></label>
							<input type="text" id="bizupkeep-amend-name-4" name="amendment_proposed_name[]">
						</p>
					</div>

					<div class="bizupkeep-amendment-subsection" data-amendment-subsection="amendment-address" hidden>
						<h3><?php esc_html_e( 'New Registered Address', 'bizupkeep-astra-child' ); ?></h3>
						<?php bizupkeep_child_render_address_fields( 'amendment_address' ); ?>
					</div>

					<div class="bizupkeep-amendment-subsection" data-amendment-subsection="amendment-director" hidden>
						<h3><?php esc_html_e( 'Director Changes', 'bizupkeep-astra-child' ); ?></h3>

						<?php foreach ( $companies as $company ) : ?>
							<div class="bizupkeep-existing-directors" data-company-uuid="<?php echo esc_attr( $company['uuid'] ); ?>" data-existing-directors-for="amendment" hidden>
								<?php if ( empty( $company['directors'] ) ) : ?>
									<p class="bizupkeep-field-hint"><?php esc_html_e( 'No existing directors on file for this company.', 'bizupkeep-astra-child' ); ?></p>
								<?php else : ?>
									<p class="bizupkeep-field-hint"><?php esc_html_e( 'Tick to remove a director:', 'bizupkeep-astra-child' ); ?></p>
									<?php foreach ( $company['directors'] as $director ) : ?>
										<label class="bizupkeep-type-option">
											<input type="checkbox" name="director_remove[]" value="<?php echo esc_attr( $director['uuid'] ); ?>">
											<?php echo esc_html( $director['full_name'] ); ?>
										</label>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>

						<p class="bizupkeep-field-hint"><?php esc_html_e( 'Add new director(s):', 'bizupkeep-astra-child' ); ?></p>

						<div class="bizupkeep-repeater" data-repeater="amendment-director" data-max="10" data-template-id="bizupkeep-amendment-director-template">
							<div class="bizupkeep-repeater-blocks">
								<?php bizupkeep_child_render_director_fields( 'amendment_director', 0 ); ?>
							</div>
							<button type="button" class="bizupkeep-btn bizupkeep-repeater-add"><?php esc_html_e( '+ Add Director', 'bizupkeep-astra-child' ); ?></button>
						</div>

						<template id="bizupkeep-amendment-director-template">
							<?php bizupkeep_child_render_director_fields( 'amendment_director', '__INDEX__' ); ?>
						</template>
					</div>

				</section>

				<!-- ============ Annual Return ============ -->
				<section class="bizupkeep-apply-section" data-application-type="annual_return" <?php echo 'annual_return' === $selected_type ? '' : 'hidden'; ?>>

					<h2><?php esc_html_e( 'Annual Return', 'bizupkeep-astra-child' ); ?></h2>

					<?php bizupkeep_child_render_company_picker( 'return', $companies ); ?>

					<h3><?php esc_html_e( 'Which Financial Year(s)?', 'bizupkeep-astra-child' ); ?></h3>
					<p class="bizupkeep-field-hint"><?php esc_html_e( 'Behind on more than one year? Add each outstanding year separately - we\'ll quote for all of them together.', 'bizupkeep-astra-child' ); ?></p>

					<div class="bizupkeep-repeater" data-repeater="filing" data-max="10" data-template-id="bizupkeep-filing-template">
						<div class="bizupkeep-repeater-blocks">
							<?php bizupkeep_child_render_filing_fields( 'filing', 0 ); ?>
						</div>
						<button type="button" class="bizupkeep-btn bizupkeep-repeater-add"><?php esc_html_e( '+ Add Financial Year', 'bizupkeep-astra-child' ); ?></button>
					</div>

					<template id="bizupkeep-filing-template">
						<?php bizupkeep_child_render_filing_fields( 'filing', '__INDEX__' ); ?>
					</template>

				</section>

				<p>
					<label for="bizupkeep-notes"><?php esc_html_e( 'Anything else we should know? (optional)', 'bizupkeep-astra-child' ); ?></label>
					<textarea id="bizupkeep-notes" name="notes" rows="4"></textarea>
				</p>

				<p>
					<button type="submit" class="bizupkeep-btn bizupkeep-btn-primary">
						<?php esc_html_e( 'Submit Application', 'bizupkeep-astra-child' ); ?>
					</button>
				</p>
			</form>

		<?php endif; ?>

	</div>
</main>

<?php
get_footer();
