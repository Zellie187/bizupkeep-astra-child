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
		<div class="bizupkeep-hero-blobs" aria-hidden="true">
			<span class="bizupkeep-hero-blob bizupkeep-hero-blob--peach"></span>
			<span class="bizupkeep-hero-blob bizupkeep-hero-blob--teal"></span>
			<span class="bizupkeep-hero-blob bizupkeep-hero-blob--violet"></span>
		</div>
		<div class="bizupkeep-hero-inner">
			<div class="bizupkeep-hero-copy">
				<p class="bizupkeep-hero-eyebrow">
					<span class="bizupkeep-hero-eyebrow-dot" aria-hidden="true"></span>
					<?php esc_html_e( 'Company Registration & Compliance, Simplified', 'bizupkeep-astra-child' ); ?>
				</p>
				<h1 class="bizupkeep-hero-title">
					<?php esc_html_e( 'Register Your Company ', 'bizupkeep-astra-child' ); ?><span class="bizupkeep-hero-title-accent"><?php esc_html_e( 'From R600', 'bizupkeep-astra-child' ); ?></span><?php esc_html_e( ' — Fully Online', 'bizupkeep-astra-child' ); ?>
				</h1>
				<p class="bizupkeep-hero-subtitle">
					<?php esc_html_e( 'CIPC-compliant company registration and business compliance services, handled online from start to finish. No jargon, no chasing paperwork - just tell us what you need.', 'bizupkeep-astra-child' ); ?>
				</p>
				<div class="bizupkeep-hero-ctas">
					<a href="<?php echo esc_url( home_url( '/apply/' ) ); ?>" class="bizupkeep-btn bizupkeep-btn-primary bizupkeep-btn-large">
						<?php esc_html_e( 'Start Your Application', 'bizupkeep-astra-child' ); ?>
					</a>
					<a href="#bizupkeep-homepage-pricing" class="bizupkeep-btn bizupkeep-btn-secondary bizupkeep-btn-large">
						<?php esc_html_e( 'See Pricing', 'bizupkeep-astra-child' ); ?>
					</a>
				</div>
			</div>

			<!-- Decorative "application tracker" mock-up - a stylised
			     representation of the client portal, not a real
			     screenshot or live data. -->
			<div class="bizupkeep-hero-visual" aria-hidden="true">
				<div class="bizupkeep-mock-badge bizupkeep-mock-badge--coral">
					<span class="bizupkeep-mock-badge-icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
					</span>
					<?php esc_html_e( 'Registered in days', 'bizupkeep-astra-child' ); ?>
				</div>
				<div class="bizupkeep-mock-card">
					<span class="bizupkeep-mock-example-label"><?php esc_html_e( 'Example dashboard', 'bizupkeep-astra-child' ); ?></span>
					<div class="bizupkeep-mock-card-header">
						<span class="bizupkeep-mock-card-title"><?php esc_html_e( 'My Applications', 'bizupkeep-astra-child' ); ?></span>
						<span class="bizupkeep-mock-pill"><?php esc_html_e( 'On Track', 'bizupkeep-astra-child' ); ?></span>
					</div>
					<div class="bizupkeep-mock-row">
						<span class="bizupkeep-mock-row-icon is-done"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg></span>
						<span class="bizupkeep-mock-row-text">
							<span class="bizupkeep-mock-row-title"><?php esc_html_e( 'Name reservation', 'bizupkeep-astra-child' ); ?></span>
							<span class="bizupkeep-mock-row-sub"><?php esc_html_e( 'Approved by CIPC', 'bizupkeep-astra-child' ); ?></span>
						</span>
					</div>
					<div class="bizupkeep-mock-row">
						<span class="bizupkeep-mock-row-icon is-done"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg></span>
						<span class="bizupkeep-mock-row-text">
							<span class="bizupkeep-mock-row-title"><?php esc_html_e( 'Documents submitted', 'bizupkeep-astra-child' ); ?></span>
							<span class="bizupkeep-mock-row-sub"><?php esc_html_e( 'Verified', 'bizupkeep-astra-child' ); ?></span>
						</span>
					</div>
					<div class="bizupkeep-mock-row">
						<span class="bizupkeep-mock-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></span>
						<span class="bizupkeep-mock-row-text">
							<span class="bizupkeep-mock-row-title"><?php esc_html_e( 'Registration certificate', 'bizupkeep-astra-child' ); ?></span>
							<span class="bizupkeep-mock-row-sub"><?php esc_html_e( 'Processing', 'bizupkeep-astra-child' ); ?></span>
						</span>
					</div>
				</div>
				<div class="bizupkeep-mock-badge bizupkeep-mock-badge--violet">
					<span class="bizupkeep-mock-badge-icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 4v5"/></svg>
					</span>
					<?php esc_html_e( 'POPIA-safe docs', 'bizupkeep-astra-child' ); ?>
				</div>
			</div>
		</div>
	</section>

	<!-- ============ How It Works ============ -->
	<section class="bizupkeep-how-it-works">
		<div class="bizupkeep-how-it-works-inner">
			<span class="bizupkeep-section-kicker"><?php esc_html_e( 'How It Works', 'bizupkeep-astra-child' ); ?></span>
			<h2 class="bizupkeep-section-title"><?php esc_html_e( 'Four steps to a registered company', 'bizupkeep-astra-child' ); ?></h2>

			<div class="bizupkeep-steps-grid">
				<div class="bizupkeep-step-card">
					<span class="bizupkeep-step-number">01</span>
					<span class="bizupkeep-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M12 18v-6M9 15h6"/></svg></span>
					<h3><?php esc_html_e( 'Apply', 'bizupkeep-astra-child' ); ?></h3>
					<p class="bizupkeep-step-tagline"><?php esc_html_e( 'Tell us what you need', 'bizupkeep-astra-child' ); ?></p>
					<p><?php esc_html_e( 'Select your service and complete a short online form.', 'bizupkeep-astra-child' ); ?></p>
				</div>
				<div class="bizupkeep-step-card">
					<span class="bizupkeep-step-number">02</span>
					<span class="bizupkeep-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></span>
					<h3><?php esc_html_e( 'Pay & Register', 'bizupkeep-astra-child' ); ?></h3>
					<p class="bizupkeep-step-tagline"><?php esc_html_e( 'Secure checkout, instant account', 'bizupkeep-astra-child' ); ?></p>
					<p><?php esc_html_e( 'Pay online and create your account in one step.', 'bizupkeep-astra-child' ); ?></p>
				</div>
				<div class="bizupkeep-step-card">
					<span class="bizupkeep-step-number">03</span>
					<span class="bizupkeep-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M17 8l-5-5-5 5M12 3v12"/></svg></span>
					<h3><?php esc_html_e( 'Submit Documents', 'bizupkeep-astra-child' ); ?></h3>
					<p class="bizupkeep-step-tagline"><?php esc_html_e( 'Upload what we need', 'bizupkeep-astra-child' ); ?></p>
					<p><?php esc_html_e( 'Log in and send us your supporting documents securely.', 'bizupkeep-astra-child' ); ?></p>
				</div>
				<div class="bizupkeep-step-card">
					<span class="bizupkeep-step-number">04</span>
					<span class="bizupkeep-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg></span>
					<h3><?php esc_html_e( 'Receive Your Registration', 'bizupkeep-astra-child' ); ?></h3>
					<p class="bizupkeep-step-tagline"><?php esc_html_e( 'Documents delivered', 'bizupkeep-astra-child' ); ?></p>
					<p><?php esc_html_e( 'Once processed, your registration documents are sent to you.', 'bizupkeep-astra-child' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<!-- ============ Packages / Pricing ============ -->
	<section id="bizupkeep-homepage-pricing" class="bizupkeep-services">
		<div class="bizupkeep-services-inner">
			<span class="bizupkeep-section-kicker"><?php esc_html_e( 'Pricing', 'bizupkeep-astra-child' ); ?></span>
			<h2 class="bizupkeep-section-title"><?php esc_html_e( 'Simple, Transparent Pricing', 'bizupkeep-astra-child' ); ?></h2>
			<p class="bizupkeep-section-subtext"><?php esc_html_e( 'No hidden CIPC fees, no surprise add-ons.', 'bizupkeep-astra-child' ); ?></p>

			<div class="bizupkeep-packages-grid">

				<div class="bizupkeep-package-card">
					<span class="bizupkeep-package-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg></span>
					<h3 class="bizupkeep-package-title"><?php esc_html_e( 'New Company Registration', 'bizupkeep-astra-child' ); ?></h3>
					<p class="bizupkeep-package-price">R600</p>
					<p class="bizupkeep-package-desc"><?php esc_html_e( 'Register your Pty Ltd with CIPC — name reservation, registration, and your SARS tax number, all included.', 'bizupkeep-astra-child' ); ?></p>
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
					<span class="bizupkeep-package-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.1 2.1 0 013 3L12 15l-4 1 1-4z"/></svg></span>
					<h3 class="bizupkeep-package-title"><?php esc_html_e( 'Company Amendments', 'bizupkeep-astra-child' ); ?></h3>
					<p class="bizupkeep-package-price"><?php esc_html_e( 'From R250', 'bizupkeep-astra-child' ); ?></p>
					<p class="bizupkeep-package-desc"><?php esc_html_e( 'Keep your company details up to date with CIPC.', 'bizupkeep-astra-child' ); ?></p>
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
					<span class="bizupkeep-package-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></span>
					<h3 class="bizupkeep-package-title"><?php esc_html_e( 'Annual Returns', 'bizupkeep-astra-child' ); ?></h3>
					<p class="bizupkeep-package-price"><?php esc_html_e( 'From R200', 'bizupkeep-astra-child' ); ?></p>
					<p class="bizupkeep-package-desc"><?php esc_html_e( 'Stay compliant and avoid CIPC penalties or deregistration.', 'bizupkeep-astra-child' ); ?></p>
					<ul class="bizupkeep-package-features">
						<li><?php esc_html_e( 'Priced by company turnover', 'bizupkeep-astra-child' ); ?></li>
						<li><?php esc_html_e( 'We handle the full CIPC submission', 'bizupkeep-astra-child' ); ?></li>
					</ul>
					<p class="bizupkeep-package-cta">
						<a href="<?php echo esc_url( add_query_arg( 'type', 'annual_return', home_url( '/apply/' ) ) ); ?>" class="bizupkeep-btn bizupkeep-btn-primary"><?php esc_html_e( 'Apply Now', 'bizupkeep-astra-child' ); ?></a>
					</p>
				</div>

			</div>
		</div>
	</section>

	<!-- ============ Why BizUpKeep ============ -->
	<section class="bizupkeep-why-us">
		<div class="bizupkeep-why-us-inner">
			<span class="bizupkeep-section-kicker"><?php esc_html_e( 'Why BizUpKeep', 'bizupkeep-astra-child' ); ?></span>
			<h2 class="bizupkeep-section-title"><?php esc_html_e( 'Why Work With Us', 'bizupkeep-astra-child' ); ?></h2>

			<div class="bizupkeep-steps-grid">
				<div class="bizupkeep-step-card">
					<span class="bizupkeep-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/><path d="M9 12l2 2 4-4"/></svg></span>
					<h3><?php esc_html_e( 'CIPC-Compliant, Every Time', 'bizupkeep-astra-child' ); ?></h3>
					<p><?php esc_html_e( "We handle the process correctly the first time, so your registration doesn't get delayed or rejected.", 'bizupkeep-astra-child' ); ?></p>
				</div>
				<div class="bizupkeep-step-card">
					<span class="bizupkeep-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/></svg></span>
					<h3><?php esc_html_e( 'POPIA-Compliant Document Handling', 'bizupkeep-astra-child' ); ?></h3>
					<p><?php esc_html_e( 'Your personal and company information is handled securely and in line with South African privacy law.', 'bizupkeep-astra-child' ); ?></p>
				</div>
				<div class="bizupkeep-step-card">
					<span class="bizupkeep-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="5"/><path d="M6 21l1.5-6.5L12 16l4.5-1.5L18 21"/></svg></span>
					<h3><?php esc_html_e( 'ICB-Registered Bookkeeping', 'bizupkeep-astra-child' ); ?></h3>
					<p><?php esc_html_e( 'Bookkeeping support from an ICB-registered professional, certified on Sage, QuickBooks and Xero — not outsourced, not guesswork.', 'bizupkeep-astra-child' ); ?></p>
				</div>
				<div class="bizupkeep-step-card">
					<span class="bizupkeep-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></span>
					<h3><?php esc_html_e( 'Small Business, Personal Attention', 'bizupkeep-astra-child' ); ?></h3>
					<p><?php esc_html_e( 'As a small, focused company, we give every client proper attention to detail — and grow alongside your business, not just process you as another number.', 'bizupkeep-astra-child' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<!-- ============ FAQ ============ -->
	<section id="bizupkeep-homepage-faq" class="bizupkeep-faq">
		<div class="bizupkeep-faq-inner">
			<span class="bizupkeep-section-kicker"><?php esc_html_e( 'FAQ', 'bizupkeep-astra-child' ); ?></span>
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

	<?php
	// The "Ready to Register?" CTA that used to live here (then briefly
	// moved into footer.php itself) has been removed outright - contact
	// details and legal links still live in the shared footer.
	?>

</main>

<?php
get_footer();
