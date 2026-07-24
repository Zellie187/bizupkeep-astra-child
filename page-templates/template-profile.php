<?php
/**
 * Template Name: BizUpKeep My Profile
 * Description:   Client Portal profile page - lets the logged-in
 *                 client view and edit their BizHub profile (first
 *                 name, last name, phone) and see their WordPress
 *                 account's email/username read-only. Submission is
 *                 handled by bizupkeep_child_handle_profile_update()
 *                 in functions.php (runs on template_redirect, before
 *                 this template ever renders, so it can redirect on
 *                 success/failure). This page is only reachable while
 *                 logged in - see bizupkeep_child_guard_client_portal().
 *
 * @package BizUpKeep_Astra_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$profile = bizupkeep_child_profile_data( get_current_user_id() );
?>

<main id="bizupkeep-profile" class="bizupkeep-profile">
	<div class="bizupkeep-profile-inner">

		<h1><?php esc_html_e( 'My Profile', 'bizupkeep-astra-child' ); ?></h1>

		<?php if ( isset( $_GET['profile_updated'] ) ) : ?>
			<p class="bizupkeep-status-pill"><?php esc_html_e( 'Profile updated.', 'bizupkeep-astra-child' ); ?></p>
		<?php elseif ( isset( $_GET['profile_error'] ) ) : ?>
			<p class="bizupkeep-status-pill"><?php esc_html_e( 'Something went wrong updating your profile - please check your details and try again.', 'bizupkeep-astra-child' ); ?></p>
		<?php endif; ?>

		<?php if ( null === $profile ) : ?>

			<p><?php esc_html_e( 'Your profile could not be loaded. Please try again shortly.', 'bizupkeep-astra-child' ); ?></p>

		<?php else : ?>

			<form method="post" class="bizupkeep-upload-form bizupkeep-profile-form">
				<?php wp_nonce_field( 'bizupkeep_update_profile', 'bizupkeep_profile_nonce' ); ?>

				<label for="bizupkeep-profile-first-name"><?php esc_html_e( 'First Name', 'bizupkeep-astra-child' ); ?></label>
				<input type="text" id="bizupkeep-profile-first-name" name="first_name" value="<?php echo esc_attr( $profile['first_name'] ); ?>" required>

				<label for="bizupkeep-profile-last-name"><?php esc_html_e( 'Last Name', 'bizupkeep-astra-child' ); ?></label>
				<input type="text" id="bizupkeep-profile-last-name" name="last_name" value="<?php echo esc_attr( $profile['last_name'] ); ?>" required>

				<label for="bizupkeep-profile-phone"><?php esc_html_e( 'Phone', 'bizupkeep-astra-child' ); ?></label>
				<input type="tel" id="bizupkeep-profile-phone" name="phone" value="<?php echo esc_attr( $profile['phone'] ); ?>">

				<label for="bizupkeep-profile-email"><?php esc_html_e( 'Email', 'bizupkeep-astra-child' ); ?></label>
				<input type="email" id="bizupkeep-profile-email" value="<?php echo esc_attr( $profile['email'] ); ?>" disabled>
				<p class="bizupkeep-field-hint"><?php esc_html_e( 'Your email is tied to your account login and can\'t be changed here.', 'bizupkeep-astra-child' ); ?></p>

				<label for="bizupkeep-profile-username"><?php esc_html_e( 'Username', 'bizupkeep-astra-child' ); ?></label>
				<input type="text" id="bizupkeep-profile-username" value="<?php echo esc_attr( $profile['username'] ); ?>" disabled>

				<p>
					<button type="submit" class="bizupkeep-btn bizupkeep-btn-primary">
						<?php esc_html_e( 'Save Changes', 'bizupkeep-astra-child' ); ?>
					</button>
				</p>
			</form>

			<p>
				<a href="<?php echo esc_url( bizupkeep_child_password_change_url() ); ?>" class="bizupkeep-btn bizupkeep-btn-secondary">
					<?php esc_html_e( 'Change Password', 'bizupkeep-astra-child' ); ?>
				</a>
			</p>

		<?php endif; ?>

	</div>
</main>

<?php
get_footer();
