<?php
/**
 * Template Name: BizUpKeep Homepage
 * Description:   Custom homepage template: hero, how-it-works, packages,
 *                 why-us, FAQ, and a footer CTA with contact details.
 *                 Copy sourced from the approved
 *                 bizupkeep-homepage-copy.md pass - see that file's
 *                 history for context on any future copy changes.
 *
 * @package BizUpKeep_Astra_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="bizupkeep-homepage" class="bizupkeep-homepage">

	<!-- ============ Hero ============ -->
	<section class="bizupkeep-hero">
		<div class="bizupkeep-hero-inner">
			<p class="bizupkeep-hero-eyebrow">
				<?php esc_html_e( 'Company Registration & Compliance, Simplified', 'bizupkeep-astra-child' ); ?>
			</p>
			<h1 class="bizupkeep-hero-title">
				<?php esc_html_e( 'Register Your Company From R600 — Fully Online, Fully Compliant', 'bizupkeep-astra-child' ); ?>
			</h1>
			<p class="bizupkeep-hero-subtitle">
				<?php esc_html_e( 'CIPC-compliant company registration and business compliance services, handled online from start to finish. No jargon, no chasing paperwork - just tell us what you need.', 'bizupkeep-astra-child' ); ?>
			</p>
			<a href="<?php echo esc_url( home_url( '/apply/' ) ); ?>" class="bizupkeep-btn bizupkeep-btn-primary bizupkeep-btn-large">
				<?php esc_html_e( 'Start Your Application', 'bizupkeep-astra-child' ); ?>
			</a>
		</div>
	</section>

	<!-- ============ How It Works ============ -->
	<section class="bizupkeep-how-it-works">
		<div class="bizupkeep-how-it-works-inner">
			<h2 class="bizupkeep-section-title"><?php esc_html_e( 'How It Works', 'bizupkeep-astra-child' ); ?></h2>

			<div class="bizupkeep-steps-grid">
				<div class="bizupkeep-step-card">
					<span class="bizupkeep-step-number">1</span>
					<h3><?php esc_html_e( 'Apply', 'bizupkeep-astra-child' ); ?></h3>
					<p class="bizupkeep-step-tagline"><?php esc_html_e( 'Tell us what you need', 'bizupkeep-astra-child' ); ?></p>
					<p><?php esc_html_e( 'Select your service and complete a short online form.', 'bizupkeep-astra-child' ); ?></p>
				</div>
				<div class="bizupkeep-step-card">
					<span class="bizupkeep-step-number">2</span>
					<h3><?php esc_html_e( 'Pay & Register', 'bizupkeep-astra-child' ); ?></h3>
					<p class="bizupkeep-step-tagline"><?php esc_html_e( 'Secure checkout, instant account', 'bizupkeep-astra-child' ); ?></p>
					<p><?php esc_html_e( 'Pay online and create your account in one step.', 'bizupkeep-astra-child' ); ?></p>
				</div>
				<div class="bizupkeep-step-card">
					<span class="bizupkeep-step-number">3</span>
					<h3><?php esc_html_e( 'Submit Documents', 'bizupkeep-astra-child' ); ?></h3>
					<p class="bizupkeep-step-tagline"><?php esc_html_e( 'Upload what we need', 'bizupkeep-astra-child' ); ?></p>
					<p><?php esc_html_e( 'Log in and send us your supporting documents securely.', 'bizupkeep-astra-child' ); ?></p>
				</div>
				<div class="bizupkeep-step-card">
					<span class="bizupkeep-step-number">4</span>
					<h3><?php esc_html_e( 'Receive Your Registration', 'bizupkeep-astra-child' ); ?></h3>
					<p class="bizupkeep-step-tagline"><?php esc_html_e( 'Documents delivered', 'bizupkeep-astra-child' ); ?></p>
					<p><?php esc_html_e( 'Once processed, your registration documents are sent to you.', 'bizupkeep-astra-child' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<!-- ============ Packages / Pricing ============ -->
	<section class="bizupkeep-services">
		<div class="bizupkeep-services-inner">
			<h2 class="bizupkeep-section-title"><?php esc_html_e( 'Simple, Transparent Pricing', 'bizupkeep-astra-child' ); ?></h2>
			<p class="bizupkeep-section-subtext"><?php esc_html_e( 'No hidden CIPC fees, no surprise add-ons.', 'bizupkeep-astra-child' ); ?></p>

			<div class="bizupkeep-packages-grid">

				<div class="bizupkeep-package-card">
					<h3 class="bizupkeep-package-title"><?php esc_html_e( 'New Company Registration', 'bizupkeep-astra-child' ); ?></h3>
					<p class="bizupkeep-package-price">R600</p>
					<p><?php esc_html_e( 'Register your Pty Ltd with CIPC — name reservation, registration, and your SARS tax number, all included.', 'bizupkeep-astra-child' ); ?></p>
					<ul class="bizupkeep-package-features">
						<li><?php esc_html_e( 'CIPC company name reservation', 'bizupkeep-astra-child' ); ?></li>
						<li><?php esc_html_e( 'Private Company (Pty) Ltd registration', 'bizupkeep-astra-child' ); ?></li>
						<li><?php esc_html_e( 'SARS income tax number (auto-issued)', 'bizupkeep-astra-child' ); ?></li>
						<li><?php esc_html_e( 'Registration certificate', 'bizupkeep-astra-child' ); ?></li>
						<li><?php esc_html_e( 'Typically 3–5 working days, depending on CIPC processing times', 'bizupkeep-astra-child' ); ?></li>
					</ul>
					<p class="bizupkeep-package-cta">
						<a href="<?php echo esc_url( add_query_arg( 'type', 'new_registration', home_url( '/apply/' ) ) ); ?>" class="bizupkeep-btn bizupkeep-btn-primary"><?php esc_html_e( 'Apply Now', 'bizupkeep-astra-child' ); ?></a>
					</p>
				</div>

				<div class="bizupkeep-package-card">
					<h3 class="bizupkeep-package-title"><?php esc_html_e( 'Company Amendments', 'bizupkeep-astra-child' ); ?></h3>
					<p class="bizupkeep-package-price"><?php esc_html_e( 'From R250', 'bizupkeep-astra-child' ); ?></p>
					<p><?php esc_html_e( 'Keep your company details up to date with CIPC.', 'bizupkeep-astra-child' ); ?></p>
					<ul class="bizupkeep-package-features">
						<li><?php esc_html_e( 'Director change — R250', 'bizupkeep-astra-child' ); ?></li>
						<li><?php esc_html_e( 'Name change — R250', 'bizupkeep-astra-child' ); ?></li>
						<li><?php esc_html_e( 'Address change — R250', 'bizupkeep-astra-child' ); ?></li>
						<li><?php esc_html_e( 'Any two changes — R400', 'bizupkeep-astra-child' ); ?></li>
						<li><?php esc_html_e( 'All three changes — R550', 'bizupkeep-astra-child' ); ?></li>
					</ul>
					<p class="bizupkeep-package-cta">
						<a href="<?php echo esc_url( add_query_arg( 'type', 'company_amendment', home_url( '/apply/' ) ) ); ?>" class="bizupkeep-btn bizupkeep-btn-primary"><?php esc_html_e( 'Apply Now', 'bizupkeep-astra-child' ); ?></a>
					</p>
				</div>

				<div class="bizupkeep-package-card">
					<h3 class="bizupkeep-package-title"><?php esc_html_e( 'Annual Returns', 'bizupkeep-astra-child' ); ?></h3>
					<p class="bizupkeep-package-price"><?php esc_html_e( 'From R200', 'bizupkeep-astra-child' ); ?></p>
					<p><?php esc_html_e( 'Stay compliant and avoid CIPC penalties or deregistration.', 'bizupkeep-astra-child' ); ?></p>
					<ul class="bizupkeep-package-features">
						<li><?php esc_html_e( 'Priced by company turnover', 'bizupkeep-astra-child' ); ?></li>
						<li><?php esc_html_e( 'We handle the full CIPC submission', 'bizupkeep-astra-child' ); ?></li>
					</ul>
					<p class="bizupkeep-package-cta">
						<a href="<?php echo esc_url( add_query_arg( 'type', 'annual_return', home_url( '/apply/' ) ) ); ?>" class="bizupkeep-btn bizupkeep-btn-primary"><?php esc_html_e( 'Apply Now', 'bizupkeep-astra-child' ); ?></a>
					</p>
				</div>

				<div class="bizupkeep-package-card">
					<h3 class="bizupkeep-package-title"><?php esc_html_e( 'Bookkeeping Services', 'bizupkeep-astra-child' ); ?></h3>
					<p class="bizupkeep-package-price"><?php esc_html_e( 'From R300', 'bizupkeep-astra-child' ); ?></p>
					<p><?php esc_html_e( 'Basic bookkeeping, done for you — with the option to export your data to Xero, QuickBooks, or Sage.', 'bizupkeep-astra-child' ); ?></p>
					<ul class="bizupkeep-package-features">
						<li><?php esc_html_e( 'Income and expense capture', 'bizupkeep-astra-child' ); ?></li>
						<li><?php esc_html_e( 'Standard chart of accounts', 'bizupkeep-astra-child' ); ?></li>
						<li><?php esc_html_e( 'Export-ready data, any time', 'bizupkeep-astra-child' ); ?></li>
					</ul>
					<p class="bizupkeep-package-cta">
						<a href="<?php echo esc_url( home_url( '/apply/' ) ); ?>" class="bizupkeep-btn bizupkeep-btn-primary"><?php esc_html_e( 'Apply Now', 'bizupkeep-astra-child' ); ?></a>
					</p>
				</div>

			</div>
		</div>
	</section>

	<!-- ============ Why BizUpKeep ============ -->
	<section class="bizupkeep-why-us">
		<div class="bizupkeep-why-us-inner">
			<h2 class="bizupkeep-section-title"><?php esc_html_e( 'Why Work With Us', 'bizupkeep-astra-child' ); ?></h2>

			<div class="bizupkeep-steps-grid">
				<div class="bizupkeep-step-card">
					<h3><?php esc_html_e( 'CIPC-Compliant, Every Time', 'bizupkeep-astra-child' ); ?></h3>
					<p><?php esc_html_e( "We handle the process correctly the first time, so your registration doesn't get delayed or rejected.", 'bizupkeep-astra-child' ); ?></p>
				</div>
				<div class="bizupkeep-step-card">
					<h3><?php esc_html_e( 'POPIA-Compliant Document Handling', 'bizupkeep-astra-child' ); ?></h3>
					<p><?php esc_html_e( 'Your personal and company information is handled securely and in line with South African privacy law.', 'bizupkeep-astra-child' ); ?></p>
				</div>
				<div class="bizupkeep-step-card">
					<h3><?php esc_html_e( 'ICB-Registered Bookkeeping', 'bizupkeep-astra-child' ); ?></h3>
					<p><?php esc_html_e( 'Bookkeeping support from an ICB-registered professional, certified on Sage, QuickBooks and Xero — not outsourced, not guesswork.', 'bizupkeep-astra-child' ); ?></p>
				</div>
				<div class="bizupkeep-step-card">
					<h3><?php esc_html_e( 'Small Business, Personal Attention', 'bizupkeep-astra-child' ); ?></h3>
					<p><?php esc_html_e( 'As a small, focused company, we give every client proper attention to detail — and grow alongside your business, not just process you as another number.', 'bizupkeep-astra-child' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<!-- ============ FAQ ============ -->
	<section class="bizupkeep-faq">
		<div class="bizupkeep-faq-inner">
			<h2 class="bizupkeep-section-title"><?php esc_html_e( 'Frequently Asked Questions', 'bizupkeep-astra-child' ); ?></h2>

			<details class="bizupkeep-faq-item" open>
				<summary><?php esc_html_e( 'How long does company registration take?', 'bizupkeep-astra-child' ); ?></summary>
				<p><?php esc_html_e( 'Turnaround varies depending on the application, but most registrations are completed within 3–5 working days, subject to CIPC processing times and backlogs.', 'bizupkeep-astra-child' ); ?></p>
			</details>

			<details class="bizupkeep-faq-item">
				<summary><?php esc_html_e( 'What documents do I need to register a company?', 'bizupkeep-astra-child' ); ?></summary>
				<p><?php esc_html_e( "You'll need a certified copy of your South African ID (or passport for foreign directors), and proof of residential address not older than 3 months. If there's more than one director or shareholder, we'll need the same for each of them.", 'bizupkeep-astra-child' ); ?></p>
			</details>

			<details class="bizupkeep-faq-item">
				<summary><?php esc_html_e( 'How do I pay?', 'bizupkeep-astra-child' ); ?></summary>
				<p><?php esc_html_e( "Payment is made securely online during checkout, once you've completed your application form.", 'bizupkeep-astra-child' ); ?></p>
			</details>

			<details class="bizupkeep-faq-item">
				<summary><?php esc_html_e( "How will I know what's happening with my application?", 'bizupkeep-astra-child' ); ?></summary>
				<p><?php esc_html_e( "Once you've registered and paid, you can log into your account to see your order status.", 'bizupkeep-astra-child' ); ?></p>
			</details>

			<details class="bizupkeep-faq-item">
				<summary><?php esc_html_e( 'What happens after I submit my documents?', 'bizupkeep-astra-child' ); ?></summary>
				<p><?php esc_html_e( 'We review your documents and submit your application to CIPC. Once approved, your registration certificate and related documents are sent to you.', 'bizupkeep-astra-child' ); ?></p>
			</details>
		</div>
	</section>

	<?php
	// Elementor/manually-edited page content, if any was ever added to
	// this page in the block editor - kept for backward compatibility,
	// renders nothing extra on a page with no saved content.
	if ( have_posts() ) :
		while ( have_posts() ) :
			the_post();
			if ( trim( (string) get_the_content() ) !== '' ) :
				?>
				<section class="bizupkeep-content-area">
					<div class="bizupkeep-content-inner">
						<?php the_content(); ?>
					</div>
				</section>
				<?php
			endif;
		endwhile;
	endif;
	?>

	<!-- ============ Footer CTA + Contact ============ -->
	<section class="bizupkeep-cta">
		<div class="bizupkeep-cta-inner">
			<h2><?php esc_html_e( 'Ready to Register Your Company?', 'bizupkeep-astra-child' ); ?></h2>
			<a href="<?php echo esc_url( home_url( '/apply/' ) ); ?>" class="bizupkeep-btn bizupkeep-btn-primary bizupkeep-btn-large">
				<?php esc_html_e( 'Start Your Application', 'bizupkeep-astra-child' ); ?>
			</a>

			<div class="bizupkeep-cta-contact">
				<p>
					<?php esc_html_e( 'Phone:', 'bizupkeep-astra-child' ); ?>
					<a href="tel:+27615138895">061 513 8895</a>
				</p>
				<p>
					<?php esc_html_e( 'Email:', 'bizupkeep-astra-child' ); ?>
					<a href="mailto:info@bizupkeep.co.za">info@bizupkeep.co.za</a>
				</p>
				<p>
					<?php esc_html_e( 'WhatsApp:', 'bizupkeep-astra-child' ); ?>
					<a href="https://wa.me/27615138895">061 513 8895</a>
					<?php esc_html_e( '(calls & WhatsApp)', 'bizupkeep-astra-child' ); ?>
				</p>
			</div>

			<p class="bizupkeep-cta-links">
				<a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php esc_html_e( 'Privacy Policy', 'bizupkeep-astra-child' ); ?></a>
				·
				<a href="<?php echo esc_url( home_url( '/terms-and-conditions/' ) ); ?>"><?php esc_html_e( 'Terms & Conditions', 'bizupkeep-astra-child' ); ?></a>
			</p>
		</div>
	</section>

</main>

<?php
get_footer();
