<?php
/**
 * Template Name: BizUpKeep About
 * Description:   About page: who runs BizUpKeep, registration details,
 *                 how the service works, and how to get in touch.
 *                 Deliberately only states facts already confirmed by
 *                 the owner or already claimed elsewhere on the site
 *                 (homepage "Why Work With Us", legal documents). There
 *                 is no testimonials section because there are no
 *                 testimonials yet, and no founder photo or ICB
 *                 membership number until those are supplied - add them
 *                 here when they exist rather than showing placeholders
 *                 on a public page.
 *
 * @package BizUpKeep_Astra_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="bizupkeep-about" class="bizupkeep-about">

	<header class="bizupkeep-about-hero">
		<div class="bizupkeep-about-inner">
			<span class="bizupkeep-section-kicker"><?php esc_html_e( 'About', 'bizupkeep-astra-child' ); ?></span>
			<h1><?php esc_html_e( 'Company registration and bookkeeping, handled by a real person', 'bizupkeep-astra-child' ); ?></h1>
			<p><?php esc_html_e( 'BizUpKeep is the trading name of A2Z Business Administrators. We handle company registrations, amendments and annual returns for South African businesses, online from application to certificate.', 'bizupkeep-astra-child' ); ?></p>
		</div>
	</header>

	<section class="bizupkeep-about-section bizupkeep-about-people">
		<div class="bizupkeep-about-inner">
			<h2><?php esc_html_e( "Who's behind it", 'bizupkeep-astra-child' ); ?></h2>
			<div class="bizupkeep-about-person">
				<h3>Anzelle Kidson</h3>
				<p class="bizupkeep-about-role"><?php esc_html_e( 'Owner and bookkeeper', 'bizupkeep-astra-child' ); ?></p>
				<p><?php esc_html_e( 'Anzelle has been doing this work since 2014 and is the person you deal with when you contact BizUpKeep.', 'bizupkeep-astra-child' ); ?></p>
			</div>
		</div>
	</section>

	<section class="bizupkeep-about-section bizupkeep-about-credentials">
		<div class="bizupkeep-about-inner">
			<h2><?php esc_html_e( 'Company details', 'bizupkeep-astra-child' ); ?></h2>
			<dl class="bizupkeep-about-facts">
				<div>
					<dt><?php esc_html_e( 'Registered company', 'bizupkeep-astra-child' ); ?></dt>
					<dd><?php esc_html_e( 'A2Z Business Administrators t/a BizUpKeep, Reg. No. 2017/182869/07', 'bizupkeep-astra-child' ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Registered address', 'bizupkeep-astra-child' ); ?></dt>
					<dd><?php esc_html_e( '949 Hertzog Street, Rietfontein, Pretoria', 'bizupkeep-astra-child' ); ?></dd>
				</div>
			</dl>
			<p class="bizupkeep-about-note"><?php esc_html_e( 'We work online and do not run a public walk-in office, so please contact us by phone, WhatsApp or email.', 'bizupkeep-astra-child' ); ?></p>
		</div>
	</section>

	<section class="bizupkeep-about-section bizupkeep-about-how">
		<div class="bizupkeep-about-inner">
			<h2><?php esc_html_e( 'How we work', 'bizupkeep-astra-child' ); ?></h2>
			<ul class="bizupkeep-about-points">
				<li>
					<h3><?php esc_html_e( 'We check before we submit', 'bizupkeep-astra-child' ); ?></h3>
					<p><?php esc_html_e( 'We review your documents and submit your application to CIPC ourselves, rather than leaving you to work through the CIPC site alone.', 'bizupkeep-astra-child' ); ?></p>
				</li>
				<li>
					<h3><?php esc_html_e( 'Your documents are handled with care', 'bizupkeep-astra-child' ); ?></h3>
					<p><?php esc_html_e( 'Personal and company information is handled securely and in line with POPIA. Read our Privacy Policy for the detail.', 'bizupkeep-astra-child' ); ?></p>
				</li>
				<li>
					<h3><?php esc_html_e( 'You can always see where things stand', 'bizupkeep-astra-child' ); ?></h3>
					<p><?php esc_html_e( 'Every application is tracked in your Client Portal, where you also upload supporting documents.', 'bizupkeep-astra-child' ); ?></p>
				</li>
			</ul>
		</div>
	</section>

	<section class="bizupkeep-about-section bizupkeep-about-contact">
		<div class="bizupkeep-about-inner">
			<h2><?php esc_html_e( 'Get in touch', 'bizupkeep-astra-child' ); ?></h2>
			<p>
				<?php esc_html_e( 'Phone and WhatsApp:', 'bizupkeep-astra-child' ); ?>
				<a href="tel:+27615138895">061 513 8895</a><br>
				<?php esc_html_e( 'Email:', 'bizupkeep-astra-child' ); ?>
				<a href="mailto:info@bizupkeep.co.za">info@bizupkeep.co.za</a>
			</p>
			<div class="bizupkeep-about-ctas">
				<a class="bizupkeep-btn bizupkeep-btn-primary" href="<?php echo esc_url( home_url( '/apply/' ) ); ?>"><?php esc_html_e( 'Start your application', 'bizupkeep-astra-child' ); ?></a>
				<a class="bizupkeep-btn bizupkeep-btn-secondary" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php esc_html_e( 'Request a call back', 'bizupkeep-astra-child' ); ?></a>
			</div>
		</div>
	</section>

</main>

<?php
get_footer();
