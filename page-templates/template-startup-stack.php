<?php
/**
 * Template Name: BizUpKeep Startup Stack
 * Description:   Landing page for the "Startup Stack" subscription
 *                 bundle (bookkeeping + socials + tech support,
 *                 monthly retainer) - a separate offering from the
 *                 one-off CIPC company registration/compliance
 *                 services on the main homepage. Rebuilt from the
 *                 approved design at
 *                 https://claude.ai/artifact/3jc6SDTtdAu3GYbZV4xJHF,
 *                 with that design's own "Company registration & CIPC
 *                 add-ons" section deliberately left out - those same
 *                 prices already live on the main homepage's Pricing
 *                 section, so repeating them here would duplicate
 *                 content rather than add to it. Uses this theme's
 *                 normal header/footer (not the artifact's own nav/
 *                 footer) so a visitor can still navigate the rest of
 *                 the site from this page.
 *
 * @package BizUpKeep_Astra_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_style(
	'bizupkeep-stack',
	get_stylesheet_directory_uri() . '/assets/css/stack.css',
	array( 'bizupkeep-custom' ),
	BIZUPKEEP_CHILD_VERSION
);

get_header();
?>

<main id="bizupkeep-stack" class="bizupkeep-stack">

	<!-- ============ Hero ============ -->
	<header class="bizupkeep-stack-hero">
		<div class="bizupkeep-stack-hero-grid">
			<div>
				<h1><?php esc_html_e( 'Your bookkeeping, socials & tech — sorted, for less than one part-time hire.', 'bizupkeep-astra-child' ); ?></h1>
				<p><?php esc_html_e( "One person handling your books, your WhatsApp & social presence, and the tech that keeps it running — for South African startups who don't have time to manage three different people to do it.", 'bizupkeep-astra-child' ); ?></p>
				<div class="bizupkeep-stack-hero-ctas">
					<a class="bizupkeep-stack-btn-primary" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php esc_html_e( 'Get your free consultation', 'bizupkeep-astra-child' ); ?></a>
					<a class="bizupkeep-stack-link-quiet" href="#bizupkeep-stack-pricing"><?php esc_html_e( 'See pricing', 'bizupkeep-astra-child' ); ?></a>
				</div>
			</div>
			<div class="bizupkeep-stack-hero-blocks" aria-hidden="true">
				<div class="bizupkeep-stack-hero-block bizupkeep-stack-hb1"><?php esc_html_e( 'Bookkeeping', 'bizupkeep-astra-child' ); ?></div>
				<div class="bizupkeep-stack-hero-block bizupkeep-stack-hb2"><?php esc_html_e( 'Socials', 'bizupkeep-astra-child' ); ?></div>
				<div class="bizupkeep-stack-hero-block bizupkeep-stack-hb3"><?php esc_html_e( 'Tech support', 'bizupkeep-astra-child' ); ?></div>
			</div>
		</div>
	</header>

	<!-- ============ Problem ============ -->
	<section class="bizupkeep-stack-problem">
		<div class="bizupkeep-stack-problem-grid">
			<h2><?php esc_html_e( "You're already doing five jobs.", 'bizupkeep-astra-child' ); ?></h2>
			<div class="bizupkeep-stack-copy">
				<p><?php esc_html_e( 'Bookkeeping, social media, and "why won\'t this software work" tech issues shouldn\'t be three more.', 'bizupkeep-astra-child' ); ?></p>
				<p><?php esc_html_e( "Most founders end up either doing it all themselves at 11pm, or hiring three separate freelancers who don't talk to each other and cost more than they should.", 'bizupkeep-astra-child' ); ?></p>
				<p>
					<strong><?php esc_html_e( 'Startup Stack is the alternative:', 'bizupkeep-astra-child' ); ?></strong>
					<?php esc_html_e( ' one retainer, one person who actually knows your business, three problems solved.', 'bizupkeep-astra-child' ); ?>
				</p>
			</div>
		</div>
	</section>

	<!-- ============ What's in the stack ============ -->
	<section class="bizupkeep-stack-pillars">
		<h2><?php esc_html_e( "What's in the stack", 'bizupkeep-astra-child' ); ?></h2>
		<div class="bizupkeep-stack-layer-stack">
			<div class="bizupkeep-stack-layer bizupkeep-stack-l1">
				<span class="bizupkeep-stack-num" aria-hidden="true">1</span>
				<div>
					<h3><?php esc_html_e( 'Bookkeeping', 'bizupkeep-astra-child' ); ?></h3>
					<p><?php esc_html_e( 'Monthly reconciliation, categorised expenses, clean reports — so you always know where you stand.', 'bizupkeep-astra-child' ); ?></p>
				</div>
			</div>
			<div class="bizupkeep-stack-layer bizupkeep-stack-l2">
				<span class="bizupkeep-stack-num" aria-hidden="true">2</span>
				<div>
					<h3><?php esc_html_e( 'Socials & WhatsApp', 'bizupkeep-astra-child' ); ?></h3>
					<p><?php esc_html_e( 'Your WhatsApp Business and social profiles managed and posted — not just set up and abandoned.', 'bizupkeep-astra-child' ); ?></p>
				</div>
			</div>
			<div class="bizupkeep-stack-layer bizupkeep-stack-l3">
				<span class="bizupkeep-stack-num" aria-hidden="true">3</span>
				<div>
					<h3><?php esc_html_e( 'Tech support', 'bizupkeep-astra-child' ); ?></h3>
					<p><?php esc_html_e( 'The software hiccups and "how do I set this up" moments — handled.', 'bizupkeep-astra-child' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<!-- ============ How it works ============ -->
	<section class="bizupkeep-stack-steps">
		<h2><?php esc_html_e( 'How it works', 'bizupkeep-astra-child' ); ?></h2>
		<ol>
			<li>
				<span class="bizupkeep-stack-step-no" aria-hidden="true">01</span>
				<h3><?php esc_html_e( 'Free consult', 'bizupkeep-astra-child' ); ?></h3>
				<p><?php esc_html_e( "20 minutes, no pressure. We figure out what stage you're at.", 'bizupkeep-astra-child' ); ?></p>
			</li>
			<li>
				<span class="bizupkeep-stack-step-no" aria-hidden="true">02</span>
				<h3><?php esc_html_e( 'We set you up', 'bizupkeep-astra-child' ); ?></h3>
				<p><?php esc_html_e( 'On free or near-free software from day one. No forced upgrades.', 'bizupkeep-astra-child' ); ?></p>
			</li>
			<li>
				<span class="bizupkeep-stack-step-no" aria-hidden="true">03</span>
				<h3><?php esc_html_e( 'Monthly rhythm begins', 'bizupkeep-astra-child' ); ?></h3>
				<p><?php esc_html_e( 'Books reconciled, socials managed, support on hand — every month.', 'bizupkeep-astra-child' ); ?></p>
			</li>
			<li>
				<span class="bizupkeep-stack-step-no" aria-hidden="true">04</span>
				<h3><?php esc_html_e( 'You grow, we scale', 'bizupkeep-astra-child' ); ?></h3>
				<p><?php esc_html_e( 'Upgrade only when your business actually needs more.', 'bizupkeep-astra-child' ); ?></p>
			</li>
		</ol>
	</section>

	<!-- ============ Build your stack (pricing) ============ -->
	<section id="bizupkeep-stack-pricing" class="bizupkeep-stack-pricing">
		<h2><?php esc_html_e( 'Build your stack', 'bizupkeep-astra-child' ); ?></h2>
		<p class="bizupkeep-stack-lede"><?php esc_html_e( "Start with one, or both — and if you want both, you're automatically billed at the bundled Growth rate below, not the two separate prices added up.", 'bizupkeep-astra-child' ); ?></p>
		<p class="bizupkeep-stack-lede"><?php esc_html_e( 'Prices below are the base retainer for each tier. The accounting-software add-on cost varies depending on the plan your business needs, so your exact monthly total is confirmed at checkout before you pay anything.', 'bizupkeep-astra-child' ); ?></p>

		<div class="bizupkeep-stack-build">
			<div class="bizupkeep-stack-card bizupkeep-stack-c1">
				<div class="bizupkeep-stack-tier-name"><?php esc_html_e( 'Bookkeeping Seed', 'bizupkeep-astra-child' ); ?></div>
				<div class="bizupkeep-stack-tier-price">R650<span><?php esc_html_e( '/mo', 'bizupkeep-astra-child' ); ?></span></div>
				<div class="bizupkeep-stack-tier-sub"><?php esc_html_e( '+ free accounting software', 'bizupkeep-astra-child' ); ?></div>
				<ul>
					<li><?php esc_html_e( 'Monthly bookkeeping & reconciliation', 'bizupkeep-astra-child' ); ?></li>
					<li><?php esc_html_e( 'Sales & expense reports', 'bizupkeep-astra-child' ); ?></li>
				</ul>
			</div>
			<div class="bizupkeep-stack-plus" aria-hidden="true">+</div>
			<div class="bizupkeep-stack-card bizupkeep-stack-c2">
				<div class="bizupkeep-stack-tier-name"><?php esc_html_e( 'Social Seed', 'bizupkeep-astra-child' ); ?></div>
				<div class="bizupkeep-stack-tier-price">R750<span><?php esc_html_e( '/mo', 'bizupkeep-astra-child' ); ?></span></div>
				<div class="bizupkeep-stack-tier-sub"><?php esc_html_e( 'stands alone, no bookkeeping', 'bizupkeep-astra-child' ); ?></div>
				<ul>
					<li><?php esc_html_e( 'WhatsApp Business setup & management', 'bizupkeep-astra-child' ); ?></li>
					<li><?php esc_html_e( 'Social posting & community management', 'bizupkeep-astra-child' ); ?></li>
				</ul>
			</div>
		</div>

		<div class="bizupkeep-stack-equals-row" aria-hidden="true">
			<span class="bizupkeep-stack-arrow-line"></span>
			<span class="bizupkeep-stack-eq">=</span>
			<span class="bizupkeep-stack-arrow-line"></span>
		</div>

		<div class="bizupkeep-stack-bundle-card">
			<span class="bizupkeep-stack-badge"><?php esc_html_e( 'Save R350/month', 'bizupkeep-astra-child' ); ?></span>
			<div class="bizupkeep-stack-row">
				<div>
					<div class="bizupkeep-stack-tier-name"><?php esc_html_e( 'Growth — bookkeeping + social, bundled', 'bizupkeep-astra-child' ); ?></div>
					<div class="bizupkeep-stack-tier-price">R1,100<span><?php esc_html_e( '/mo + R239 software', 'bizupkeep-astra-child' ); ?></span></div>
				</div>
				<div class="bizupkeep-stack-save"><?php esc_html_e( 'vs. R1,400 buying both separately', 'bizupkeep-astra-child' ); ?></div>
			</div>
			<ul>
				<li><?php esc_html_e( 'Everything in Bookkeeping Seed', 'bizupkeep-astra-child' ); ?></li>
				<li><?php esc_html_e( 'Everything in Social Seed', 'bizupkeep-astra-child' ); ?></li>
				<li><?php esc_html_e( 'Upgraded accounting software — automatic categorisation, receivables tracking', 'bizupkeep-astra-child' ); ?></li>
				<li><?php esc_html_e( 'Multi-currency support', 'bizupkeep-astra-child' ); ?></li>
			</ul>
		</div>

		<div class="bizupkeep-stack-scale-card">
			<div class="bizupkeep-stack-row">
				<div>
					<div class="bizupkeep-stack-tier-name"><?php esc_html_e( 'Scale — everything, plus tax & tech', 'bizupkeep-astra-child' ); ?></div>
					<div class="bizupkeep-stack-tier-price">R1,600<span><?php esc_html_e( '/mo + R499 software', 'bizupkeep-astra-child' ); ?></span></div>
				</div>
			</div>
			<p><?php esc_html_e( 'Everything in Growth, plus VAT & income tax return prep and ongoing tech support — for startups approaching VAT registration who want a genuine back office.', 'bizupkeep-astra-child' ); ?></p>
		</div>
	</section>

	<!-- ============ Why Startup Stack ============ -->
	<section class="bizupkeep-stack-why">
		<h2><?php esc_html_e( 'Why Startup Stack', 'bizupkeep-astra-child' ); ?></h2>
		<div class="bizupkeep-stack-why-list">
			<div>
				<h3><?php esc_html_e( 'One person, not three', 'bizupkeep-astra-child' ); ?></h3>
				<p><?php esc_html_e( "No coordinating between a bookkeeper, a social media manager, and IT support who've never spoken to each other.", 'bizupkeep-astra-child' ); ?></p>
			</div>
			<div>
				<h3><?php esc_html_e( 'You start free', 'bizupkeep-astra-child' ); ?></h3>
				<p><?php esc_html_e( 'No software costs until you actually need them — ever.', 'bizupkeep-astra-child' ); ?></p>
			</div>
			<div>
				<h3><?php esc_html_e( 'Built for South African startups', 'bizupkeep-astra-child' ); ?></h3>
				<p><?php esc_html_e( 'Local banking integrations, SARS-ready compliance, no confusion about what applies to you.', 'bizupkeep-astra-child' ); ?></p>
			</div>
			<div>
				<h3><?php esc_html_e( 'We grow when you grow', 'bizupkeep-astra-child' ); ?></h3>
				<p><?php esc_html_e( "No tier upgrades pushed on you before you're ready for them.", 'bizupkeep-astra-child' ); ?></p>
			</div>
		</div>
	</section>

	<!-- ============ FAQ ============ -->
	<section class="bizupkeep-stack-faq">
		<h2><?php esc_html_e( 'Questions', 'bizupkeep-astra-child' ); ?></h2>
		<div class="bizupkeep-stack-faq-item">
			<h3><?php esc_html_e( 'Do I have to pay for accounting software from day one?', 'bizupkeep-astra-child' ); ?></h3>
			<p><?php esc_html_e( "No. Every client starts on a genuinely free plan. You only move to a paid tier once your business has outgrown it — and even then, you're only paying the software's actual cost, nothing extra.", 'bizupkeep-astra-child' ); ?></p>
		</div>
		<div class="bizupkeep-stack-faq-item">
			<h3><?php esc_html_e( 'Can I get just bookkeeping, or just socials?', 'bizupkeep-astra-child' ); ?></h3>
			<p><?php esc_html_e( 'Yes — Bookkeeping Seed and Social Seed are both fully standalone. Want both? You\'re automatically moved onto Growth, which bundles the two at a lower combined price than paying for each separately.', 'bizupkeep-astra-child' ); ?></p>
		</div>
		<div class="bizupkeep-stack-faq-item">
			<h3><?php esc_html_e( 'What if I outgrow Startup Stack?', 'bizupkeep-astra-child' ); ?></h3>
			<p><?php esc_html_e( "That's a good problem to have. Startup Stack is part of BizUpKeep, so as your business grows, you move straight into the full-service offering — same trusted team, no disruption.", 'bizupkeep-astra-child' ); ?></p>
		</div>
	</section>

</main>

<?php
get_footer();
