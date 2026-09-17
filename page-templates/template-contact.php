<?php
/**
 * Template Name: BizUpKeep Contact Us
 * Description:   General enquiry form - unlike template-apply.php, this
 *                 doesn't start a CIPC workflow or take payment; it just
 *                 captures a name/phone/email/message and emails it to
 *                 staff (see bizupkeep_child_handle_contact_submission())
 *                 so a consultant can call the client back. Used by the
 *                 Bookkeeping Services package card on the homepage,
 *                 which doesn't fit the New Registration/Amendment/
 *                 Annual Return application flow the way the other three
 *                 packages do.
 *
 * @package BizUpKeep_Astra_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="bizupkeep-contact" class="bizupkeep-contact">
	<div class="bizupkeep-contact-inner">

		<?php if ( isset( $_GET['submitted'] ) ) : ?>

			<span class="bizupkeep-status-pill"><?php esc_html_e( 'Message Received', 'bizupkeep-astra-child' ); ?></span>
			<h1><?php esc_html_e( "Thanks - we've got your details.", 'bizupkeep-astra-child' ); ?></h1>
			<p><?php esc_html_e( "One of our consultants will phone you shortly to talk through what you need.", 'bizupkeep-astra-child' ); ?></p>
			<p>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="bizupkeep-btn bizupkeep-btn-primary">
					<?php esc_html_e( 'Back to Home', 'bizupkeep-astra-child' ); ?>
				</a>
			</p>

		<?php else : ?>

			<h1><?php esc_html_e( 'Request a Call Back', 'bizupkeep-astra-child' ); ?></h1>
			<p><?php esc_html_e( 'Leave your details below and a consultant will phone you to discuss what you need.', 'bizupkeep-astra-child' ); ?></p>

			<?php if ( isset( $_GET['contact_error'] ) ) : ?>
				<p class="bizupkeep-status-pill"><?php esc_html_e( 'Something went wrong - please check the form and try again.', 'bizupkeep-astra-child' ); ?></p>
			<?php endif; ?>

			<form method="post" class="bizupkeep-upload-form bizupkeep-contact-form" id="bizupkeep-contact-form">
				<?php wp_nonce_field( 'bizupkeep_contact', 'bizupkeep_contact_nonce' ); ?>

				<p>
					<label for="bizupkeep-contact-name"><?php esc_html_e( 'Full Name', 'bizupkeep-astra-child' ); ?></label>
					<input type="text" id="bizupkeep-contact-name" name="contact_name" required>
				</p>
				<p>
					<label for="bizupkeep-contact-phone"><?php esc_html_e( 'Phone Number', 'bizupkeep-astra-child' ); ?></label>
					<input type="tel" id="bizupkeep-contact-phone" name="contact_phone" required>
				</p>
				<p>
					<label for="bizupkeep-contact-email"><?php esc_html_e( 'Email', 'bizupkeep-astra-child' ); ?></label>
					<input type="email" id="bizupkeep-contact-email" name="contact_email" required>
				</p>
				<p>
					<label for="bizupkeep-contact-message"><?php esc_html_e( 'What do you need help with? (optional)', 'bizupkeep-astra-child' ); ?></label>
					<textarea id="bizupkeep-contact-message" name="contact_message" rows="4"></textarea>
				</p>

				<p>
					<button type="submit" class="bizupkeep-btn bizupkeep-btn-primary">
						<?php esc_html_e( 'Request a Call Back', 'bizupkeep-astra-child' ); ?>
					</button>
				</p>
			</form>

		<?php endif; ?>

	</div>
</main>

<?php
get_footer();
