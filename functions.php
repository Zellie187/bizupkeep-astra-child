<?php
/**
 * BizUpKeep Astra Child theme functions.
 *
 * @package BizUpKeep_Astra_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BizHub\Bookkeeping\Accounts\ChartOfAccountsTemplate as BookkeepingChartOfAccountsTemplate;
use BizHub\Bookkeeping\Contracts\AccountServiceInterface as BookkeepingAccountServiceInterface;
use BizHub\Bookkeeping\Contracts\BankImportServiceInterface;
use BizHub\Bookkeeping\Contracts\CompanySettingsRepositoryInterface;
use BizHub\Bookkeeping\Contracts\FinancialStatementsServiceInterface;
use BizHub\Bookkeeping\Contracts\InvoiceServiceInterface;
use BizHub\Bookkeeping\Contracts\JournalRepositoryInterface as BookkeepingJournalRepositoryInterface;
use BizHub\Bookkeeping\Contracts\RecurringTransactionServiceInterface;
use BizHub\Bookkeeping\Contracts\SubscriptionServiceInterface;
use BizHub\Bookkeeping\Contracts\TransactionCaptureServiceInterface;
use BizHub\Bookkeeping\DTO\CaptureTransactionData;
use BizHub\Bookkeeping\DTO\DateRange as BookkeepingDateRange;
use BizHub\Bookkeeping\DTO\InvoiceLineInput;
use BizHub\Bookkeeping\Entities\ImportMapping;
use BizHub\Bookkeeping\Enums\ImportAmountStyle;
use BizHub\Bookkeeping\Enums\PaymentMethod as BookkeepingPaymentMethod;
use BizHub\Bookkeeping\Enums\RecurringFrequency;
use BizHub\Bookkeeping\Enums\TransactionType as BookkeepingTransactionType;
use BizHub\Bookkeeping\Support\Money as BookkeepingMoney;
use BizHub\Bookkeeping\Exceptions\BookkeepingException;
use BizHub\Bookkeeping\Export\Contracts\LedgerExporterInterface;
use BizHub\Bookkeeping\Export\CsvReader as BookkeepingCsvReader;
use BizHub\Bookkeeping\Export\QuickBooksOnlineExporter;
use BizHub\Bookkeeping\Export\SageExporter;
use BizHub\Bookkeeping\Export\XeroExporter;
use BizHub\ClientPortal\Contracts\ClientServiceInterface;
use BizHub\ClientPortal\DTO\ClientData;
use BizHub\ClientPortal\DTO\ProfileData;
use BizHub\ClientPortal\Exceptions\ClientNotFoundException;
use BizHub\ClientPortal\Services\ProfileService;
use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\Contracts\DirectorRepositoryInterface;
use BizHub\Companies\DTO\AddressData;
use BizHub\Companies\DTO\CompanyData;
use BizHub\Companies\DTO\DirectorData;
use BizHub\Companies\Entities\Company;
use BizHub\Companies\Entities\CompanyStatus;
use BizHub\Documents\Entities\DocumentCategory;
use BizHub\Documents\Services\DocumentService;
use BizHub\Workflow\Contracts\WorkflowRepositoryInterface;
use BizHub\Workflow\Contracts\WorkflowTypeServiceInterface;
use BizHub\Workflow\Entities\WorkflowInstance;
use BizHub\Workflow\Enums\WorkflowStatus;
use BizHub\Workflow\Workflows\AnnualReturn\AnnualReturnDefinition;
use BizHub\Workflow\Workflows\AnnualReturn\AnnualReturnService;
use BizHub\Workflow\Workflows\CompanyAmendment\CompanyAmendmentDefinition;
use BizHub\Workflow\Workflows\CompanyAmendment\CompanyAmendmentService;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationDefinition;
use BizHub\Workflow\Workflows\CompanyRegistration\CompanyRegistrationService;
use BizUpKeep\Core\Contracts\ServiceRepositoryInterface;
use BizUpKeep\Core\Enums\ServiceVatTreatment;

define( 'BIZUPKEEP_CHILD_VERSION', '1.29.0' );
define( 'BIZUPKEEP_CHILD_URI', get_stylesheet_directory_uri() );

/**
 * Enqueue parent and child theme styles/scripts.
 */
function bizupkeep_child_enqueue_assets(): void {
	wp_enqueue_style(
		'astra-parent-style',
		get_template_directory_uri() . '/style.css',
		array(),
		BIZUPKEEP_CHILD_VERSION
	);

	wp_enqueue_style(
		'bizupkeep-child-style',
		get_stylesheet_uri(),
		array( 'astra-parent-style' ),
		BIZUPKEEP_CHILD_VERSION
	);

	wp_enqueue_style(
		'bizupkeep-custom',
		BIZUPKEEP_CHILD_URI . '/assets/css/custom.css',
		array( 'bizupkeep-child-style' ),
		BIZUPKEEP_CHILD_VERSION
	);

	wp_enqueue_script(
		'bizupkeep-custom',
		BIZUPKEEP_CHILD_URI . '/assets/js/custom.js',
		array(),
		BIZUPKEEP_CHILD_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'bizupkeep_child_enqueue_assets' );

/**
 * Register the custom homepage page template.
 *
 * @param array $templates Existing page templates.
 * @return array
 */
function bizupkeep_child_register_page_templates( array $templates ): array {
	$templates['page-templates/template-homepage.php']    = __( 'BizUpKeep Homepage', 'bizupkeep-astra-child' );
	$templates['page-templates/template-apply.php']       = __( 'BizUpKeep Apply', 'bizupkeep-astra-child' );
	$templates['page-templates/template-documents.php']   = __( 'BizUpKeep My Documents', 'bizupkeep-astra-child' );
	$templates['page-templates/template-applications.php'] = __( 'BizUpKeep My Applications', 'bizupkeep-astra-child' );
	$templates['page-templates/template-profile.php']     = __( 'BizUpKeep My Profile', 'bizupkeep-astra-child' );

	return $templates;
}
add_filter( 'theme_page_templates', 'bizupkeep_child_register_page_templates' );

/**
 * Register theme supports.
 */
function bizupkeep_child_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );

	register_nav_menus(
		array(
			'bizupkeep-primary' => __( 'Primary Menu', 'bizupkeep-astra-child' ),
			'bizupkeep-footer'  => __( 'Footer Menu', 'bizupkeep-astra-child' ),
		)
	);
}
add_action( 'after_setup_theme', 'bizupkeep_child_setup' );

/**
 * Register footer widget area.
 */
function bizupkeep_child_widgets_init(): void {
	register_sidebar(
		array(
			'name'          => __( 'Footer Widget Area', 'bizupkeep-astra-child' ),
			'id'            => 'bizupkeep-footer-widgets',
			'before_widget' => '<div class="bizupkeep-footer-widget">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3 class="bizupkeep-footer-widget-title">',
			'after_title'   => '</h3>',
		)
	);
}
add_action( 'widgets_init', 'bizupkeep_child_widgets_init' );

/**
 * Client Portal navigation menu.
 *
 * BizHub's ClientPortal module (see the bizhub plugin's
 * includes/ClientPortal/) is currently backend-only: it exposes REST
 * endpoints (/wp-json/bizhub/v1/companies, /documents, /applications,
 * /profile) but no rendered front-end pages yet. This creates a small
 * set of placeholder Pages - styled with the Member Portal classes
 * already defined in assets/css/custom.css rather than plain text, so
 * they read as part of the site instead of a bolted-on stub - plus a
 * "Client Portal" dropdown (Dashboard/My Companies/My Documents/My
 * Applications/My Profile) added directly into the site's main menu
 * (see bizupkeep_child_sync_client_portal_menu()), rather than a
 * second nav bar of its own. The dashboard entry reuses the existing
 * "Client Portal" page rather than creating a competing one; the
 * other four pages nest underneath it. Swap each page's content for a
 * real template (or a shortcode backed by those REST endpoints, the
 * same pattern [bizupkeep_packages] already uses on the homepage) as
 * the portal front-end gets built - the menu links to the pages by
 * ID, not hardcoded markup, so nothing here needs to change to
 * support that.
 */

add_action( 'after_switch_theme', 'bizupkeep_child_setup_client_portal' );
add_action( 'init', 'bizupkeep_child_maybe_migrate_client_portal_menu' );
add_action( 'init', 'bizupkeep_child_maybe_add_bookkeeping_page' );

/**
 * One-time re-run for sites that activated the theme before "My
 * Bookkeeping" was added to the client portal -
 * bizupkeep_child_setup_client_portal() only runs on after_switch_theme,
 * which doesn't fire again just from deploying updated theme files, so
 * this creates the missing page (and re-syncs the nav menu) once via a
 * stored option flag, mirroring bizupkeep_child_maybe_migrate_client_portal_menu()'s
 * approach for the same underlying problem.
 */
function bizupkeep_child_maybe_add_bookkeeping_page(): void {
	if ( get_option( 'bizupkeep_child_bookkeeping_page_added' ) ) {
		return;
	}

	bizupkeep_child_setup_client_portal();

	update_option( 'bizupkeep_child_bookkeeping_page_added', '1' );
}

/**
 * One-time migration for sites that activated the theme before the
 * separate "Client Portal" utility bar was retired in favour of
 * folding its items into the main menu: bizupkeep_child_setup_client_portal()
 * only runs on after_switch_theme, which doesn't fire again just from
 * deploying updated theme files, so this re-runs it once via a
 * stored option flag to actually move the items over on an already-
 * active site.
 */
function bizupkeep_child_maybe_migrate_client_portal_menu(): void {
	if ( get_option( 'bizupkeep_child_portal_menu_migrated' ) ) {
		return;
	}

	bizupkeep_child_setup_client_portal();

	update_option( 'bizupkeep_child_portal_menu_migrated', '1' );
}

/**
 * Idempotently create the client portal placeholder pages and nav
 * menu. Safe to run on every theme activation: each page and menu
 * item is looked up before creating anything, so re-activating the
 * theme never creates duplicates, and an existing "Client Portal"
 * page is reused (never overwritten) rather than duplicated.
 */
function bizupkeep_child_setup_client_portal(): void {
	$dashboard_id = bizupkeep_child_get_or_create_page(
		'client-portal',
		__( 'Client Portal', 'bizupkeep-astra-child' ),
		bizupkeep_child_portal_placeholder(
			__( 'Your dashboard will show your companies, documents, applications and account activity at a glance.', 'bizupkeep-astra-child' )
		),
		0
	);

	$children = array(
		'client-portal-companies'    => __( 'My Companies', 'bizupkeep-astra-child' ),
		'client-portal-documents'    => __( 'My Documents', 'bizupkeep-astra-child' ),
		'client-portal-applications' => __( 'My Applications', 'bizupkeep-astra-child' ),
		'client-portal-bookkeeping'  => __( 'My Bookkeeping', 'bizupkeep-astra-child' ),
		'client-portal-profile'      => __( 'My Profile', 'bizupkeep-astra-child' ),
	);

	// All five land under one "Client Portal" dropdown instead of each
	// getting its own top-level nav slot - see
	// bizupkeep_child_sync_client_portal_menu() for how the parent item
	// is created and the pages below are nested under it.
	$menu_targets = array(
		array(
			'title'   => __( 'Dashboard', 'bizupkeep-astra-child' ),
			'page_id' => $dashboard_id,
		),
	);

	foreach ( $children as $slug => $title ) {
		$page_id = bizupkeep_child_get_or_create_page(
			$slug,
			$title,
			bizupkeep_child_portal_child_placeholder( $slug, $title ),
			$dashboard_id
		);

		if ( 'client-portal-documents' === $slug && 0 !== $page_id ) {
			update_post_meta( $page_id, '_wp_page_template', 'page-templates/template-documents.php' );
		}

		if ( 'client-portal-applications' === $slug && 0 !== $page_id ) {
			update_post_meta( $page_id, '_wp_page_template', 'page-templates/template-applications.php' );
		}

		if ( 'client-portal-bookkeeping' === $slug && 0 !== $page_id ) {
			update_post_meta( $page_id, '_wp_page_template', 'page-templates/template-bookkeeping.php' );
		}

		if ( 'client-portal-profile' === $slug && 0 !== $page_id ) {
			update_post_meta( $page_id, '_wp_page_template', 'page-templates/template-profile.php' );
		}

		$menu_targets[] = array(
			'title'   => $title,
			'page_id' => $page_id,
		);
	}

	bizupkeep_child_sync_client_portal_menu( $dashboard_id, $menu_targets );
}

/**
 * Look up a page's ID by slug and parent, without creating anything.
 *
 * Deliberately does not use get_page_by_path(): for a single-segment
 * path (no "/"), it only matches top-level pages (post_parent = 0),
 * which would silently miss the nested child pages here. Looking up
 * by post_name + post_parent directly is correct regardless of
 * nesting depth. Returns 0 if no matching page exists.
 */
function bizupkeep_child_find_page( string $slug, int $parent_id = 0 ): int {
	$existing = get_posts(
		array(
			'post_type'   => 'page',
			'name'        => $slug,
			'post_parent' => $parent_id,
			'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
			'numberposts' => 1,
		)
	);

	return empty( $existing ) ? 0 : $existing[0]->ID;
}

/**
 * Find an existing page by slug, or create it.
 */
function bizupkeep_child_get_or_create_page( string $slug, string $title, string $content, int $parent_id = 0 ): int {
	$existing_id = bizupkeep_child_find_page( $slug, $parent_id );

	if ( 0 !== $existing_id ) {
		return $existing_id;
	}

	$page_id = wp_insert_post(
		array(
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_parent'  => $parent_id,
		),
		true
	);

	return is_wp_error( $page_id ) ? 0 : $page_id;
}

/**
 * Placeholder content for the dashboard page, styled with the
 * existing .bizupkeep-status-pill class from assets/css/custom.css.
 */
function bizupkeep_child_portal_placeholder( string $body ): string {
	return sprintf(
		"<!-- wp:html -->\n<p><span class=\"bizupkeep-status-pill\">%s</span></p>\n<p>%s</p>\n<!-- /wp:html -->\n",
		esc_html__( 'Coming Soon', 'bizupkeep-astra-child' ),
		esc_html( $body )
	);
}

/**
 * Placeholder content for a child portal page. Documents and
 * Applications reuse the matching real table classes (with a single
 * empty-state row) so they already look like the finished feature;
 * Companies and Profile get the same status-pill treatment as the
 * dashboard, since there's no matching table class for those yet.
 */
function bizupkeep_child_portal_child_placeholder( string $slug, string $title ): string {
	if ( 'client-portal-documents' === $slug ) {
		return bizupkeep_child_portal_table_placeholder(
			'bizupkeep-documents-table',
			__( 'No documents yet - check back soon.', 'bizupkeep-astra-child' )
		);
	}

	if ( 'client-portal-applications' === $slug ) {
		return bizupkeep_child_portal_table_placeholder(
			'bizupkeep-applications-table',
			__( 'No applications yet - check back soon.', 'bizupkeep-astra-child' )
		);
	}

	return bizupkeep_child_portal_placeholder(
		sprintf(
			/* translators: %s: portal page title, e.g. "My Companies". */
			__( 'The %s section is coming soon.', 'bizupkeep-astra-child' ),
			$title
		)
	);
}

/**
 * A single-column placeholder table using one of the real Member
 * Portal table classes, so the empty state matches what the finished
 * page will look like once it's wired to real data.
 */
function bizupkeep_child_portal_table_placeholder( string $table_class, string $empty_message ): string {
	return sprintf(
		"<!-- wp:html -->\n<table class=\"%s\"><tbody><tr><td>%s</td></tr></tbody></table>\n<!-- /wp:html -->\n",
		esc_attr( $table_class ),
		esc_html( $empty_message )
	);
}

/**
 * Find the menu currently assigned to the 'bizupkeep-primary' theme
 * location (the site's actual main menu, managed by whoever set up
 * Appearance > Menus), or create and assign an empty one if the site
 * doesn't have one yet - e.g. straight after theme activation, before
 * an admin has visited that screen. The Client Portal items are added
 * into this menu rather than a menu of their own, so they show up as
 * a dropdown inside the existing main navigation instead of a second
 * nav bar.
 */
function bizupkeep_child_get_or_create_primary_menu(): int {
	$locations = get_theme_mod( 'nav_menu_locations', array() );

	if ( ! empty( $locations['bizupkeep-primary'] ) ) {
		$menu = wp_get_nav_menu_object( (int) $locations['bizupkeep-primary'] );

		if ( $menu ) {
			return $menu->term_id;
		}
	}

	$menu_name = __( 'Primary Menu', 'bizupkeep-astra-child' );
	$menu      = wp_get_nav_menu_object( $menu_name );

	if ( ! $menu ) {
		$menu_id = wp_create_nav_menu( $menu_name );

		if ( is_wp_error( $menu_id ) ) {
			return 0;
		}
	} else {
		$menu_id = $menu->term_id;
	}

	$locations['bizupkeep-primary'] = $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );

	return $menu_id;
}

/**
 * Add a single top-level "Client Portal" item to the site's main menu,
 * and nest Dashboard/My Companies/My Documents/My Applications/My
 * Profile underneath it as a dropdown - rather than each getting its
 * own top-level slot, or a separate menu/nav-bar of their own.
 *
 * The parent item is a *custom* link (to the dashboard page's URL),
 * not a page-object item pointing at $dashboard_id - Dashboard is
 * also one of the five children below, and a page-object item can
 * only ever represent one menu entry per page, so the parent can't
 * be "the same page-object link as the Dashboard child" without the
 * two colliding into a single item.
 *
 * @param int                                       $dashboard_id The "Client Portal" page's ID, used as
 *                                                                 the parent item's link URL and as the
 *                                                                 target of its own "Dashboard" child entry.
 * @param array<int,array{title:string,page_id:int}> $menu_targets Dashboard + its four sibling pages, in
 *                                                                 the order they should appear.
 */
function bizupkeep_child_sync_client_portal_menu( int $dashboard_id, array $menu_targets ): void {
	$menu_id = bizupkeep_child_get_or_create_primary_menu();

	if ( 0 === $menu_id ) {
		return;
	}

	$existing_items = wp_get_nav_menu_items( $menu_id ) ?: array();
	$dashboard_url  = get_permalink( $dashboard_id );

	if ( false === $dashboard_url ) {
		return;
	}

	// The parent item is identified by its custom URL (matching the
	// dashboard page) rather than its title, so a later copy edit to
	// the label doesn't cause a duplicate parent item to be created.
	$parent_item = null;

	foreach ( $existing_items as $item ) {
		if ( 'custom' === $item->type && 0 === (int) $item->menu_item_parent && untrailingslashit( $item->url ) === untrailingslashit( $dashboard_url ) ) {
			$parent_item = $item;
			break;
		}
	}

	if ( null === $parent_item ) {
		// Appended after whatever's already in the menu (e.g. Home,
		// Services, Contact) rather than forced to position 1, since
		// this is now the site's real main menu, not a menu built
		// from scratch for this item alone.
		$top_level_count = 0;

		foreach ( $existing_items as $item ) {
			if ( 0 === (int) $item->menu_item_parent ) {
				$top_level_count++;
			}
		}

		$parent_menu_item_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => __( 'Client Portal', 'bizupkeep-astra-child' ),
				'menu-item-url'       => $dashboard_url,
				'menu-item-type'      => 'custom',
				'menu-item-status'    => 'publish',
				'menu-item-parent-id' => 0,
				'menu-item-position'  => $top_level_count + 1,
			)
		);

		if ( is_wp_error( $parent_menu_item_id ) ) {
			return;
		}
	} else {
		$parent_menu_item_id = $parent_item->ID;
	}

	// Sites that already ran the old (flat, one-top-level-item-per-page)
	// version of this menu have Dashboard/My Companies/My Documents/
	// My Applications/My Profile each sitting at menu_item_parent = 0 -
	// those existing items are re-parented in place below rather than
	// duplicated, since they're identified by their target page, not
	// their current position in the tree.
	$existing_by_page_id = array();

	foreach ( $existing_items as $item ) {
		if ( 'page' === $item->object && (int) $item->ID !== $parent_menu_item_id ) {
			$existing_by_page_id[ (int) $item->object_id ] = $item;
		}
	}

	$position = 0;

	foreach ( $menu_targets as $target ) {
		if ( 0 === $target['page_id'] ) {
			continue;
		}

		$position++;
		$existing_child = $existing_by_page_id[ $target['page_id'] ] ?? null;

		if (
			null !== $existing_child
			&& (int) $existing_child->menu_item_parent === $parent_menu_item_id
			&& (int) $existing_child->menu_order === $position
		) {
			continue;
		}

		wp_update_nav_menu_item(
			$menu_id,
			$existing_child->ID ?? 0,
			array(
				'menu-item-title'     => $target['title'],
				'menu-item-object-id' => $target['page_id'],
				'menu-item-object'    => 'page',
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
				'menu-item-parent-id' => $parent_menu_item_id,
				'menu-item-position'  => $position,
			)
		);
	}
}

/**
 * Client Portal access control and account linking.
 *
 * Requires login to view the "Client Portal" page or any of its
 * child pages, and ensures the logged-in WordPress user has a linked
 * BizHub Client record - creating one on first visit if it doesn't
 * exist yet, via BizHub's ClientServiceInterface. That's the same
 * service layer ClientDashboardController and the /profile REST
 * endpoint already use, so no new client lifecycle logic is
 * introduced here; this just calls what BizHub already exposes.
 * Covers both existing WordPress users and anyone who registers
 * later, since the check runs on every portal page load rather than
 * only at registration time.
 */
add_action( 'template_redirect', 'bizupkeep_child_guard_client_portal' );

/**
 * Require login for Client Portal pages, and provision a BizHub
 * Client record for the logged-in user if one doesn't exist yet.
 */
function bizupkeep_child_guard_client_portal(): void {
	if ( ! bizupkeep_child_is_client_portal_page() ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	bizupkeep_child_ensure_client_record( get_current_user_id() );
}

/**
 * Determine whether the current request is for the "Client Portal"
 * page or one of its child pages.
 */
function bizupkeep_child_is_client_portal_page(): bool {
	if ( ! is_page() ) {
		return false;
	}

	$portal = bizupkeep_child_find_page( 'client-portal', 0 );

	if ( 0 === $portal ) {
		return false;
	}

	$queried_id = get_queried_object_id();

	return $queried_id === $portal || wp_get_post_parent_id( $queried_id ) === $portal;
}

/**
 * Find or create the BizHub Client record linked to a WordPress
 * user. Safe to call on every portal page load: the lookup is
 * idempotent, and this does nothing if BizHub isn't active. $phone is
 * only used the first time a client record is created for this user -
 * bizupkeep_child_handle_apply_submission() passes through whatever a
 * brand new guest applicant just typed into the apply form's "Your
 * Details" fieldset, since that's otherwise the only place this phone
 * number would ever be captured.
 */
function bizupkeep_child_ensure_client_record( int $wp_user_id, string $phone = '' ): void {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return;
	}

	$clients = bizhub()->container()->get( ClientServiceInterface::class );

	try {
		$clients->getClientByWpUserId( $wp_user_id );

		return;
	} catch ( ClientNotFoundException $e ) {
		// No client record yet for this user - create one below.
	}

	$wp_user = get_userdata( $wp_user_id );

	if ( ! $wp_user ) {
		return;
	}

	list( $first_name, $last_name ) = bizupkeep_child_split_user_name( $wp_user );

	try {
		$clients->createClient(
			new ClientData(
				wp_generate_uuid4(),
				$wp_user_id,
				new ProfileData( $first_name, $last_name, $phone, get_avatar_url( $wp_user_id ) )
			)
		);
	} catch ( InvalidArgumentException $e ) {
		// Another request created it first (race), or invalid data - either way, nothing more to do here.
	}
}

/**
 * Silently register and log in a brand new WordPress account for a
 * guest apply-form submission, using the name/email/phone posted
 * alongside it (template-apply.php's "Your Details" fieldset, shown
 * only when logged out) - so a client never has to go through a
 * separate "create an account" step before applying. Returns the new
 * user's ID on success, logged in via the same auth cookie a normal
 * login sets (so nothing downstream needs to know the difference), or
 * 0 on any failure - missing/invalid fields, or (see below) an email
 * that's already registered.
 *
 * An email already belonging to an existing account is refused
 * outright rather than silently reused or logged into: trusting a
 * bare posted email address to mean "this is that account's owner"
 * would let anyone submit an application as any existing client just
 * by typing their address. The caller shows a specific "please log in
 * first" error for this case - bizupkeep_child_handle_apply_submission()
 * tells the two failure modes apart by checking email_exists() again
 * itself before calling this.
 */
function bizupkeep_child_register_guest_applicant(): int {
	$first_name = isset( $_POST['guest_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_first_name'] ) ) : '';
	$last_name  = isset( $_POST['guest_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_last_name'] ) ) : '';
	$email      = isset( $_POST['guest_email'] ) ? sanitize_email( wp_unslash( $_POST['guest_email'] ) ) : '';

	if ( '' === $first_name || '' === $last_name || '' === $email || ! is_email( $email ) || email_exists( $email ) ) {
		return 0;
	}

	$user_id = wp_insert_user(
		array(
			'user_login'   => bizupkeep_child_unique_username_from_email( $email ),
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 20, true ),
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => trim( "$first_name $last_name" ),
			'role'         => 'subscriber',
		)
	);

	if ( is_wp_error( $user_id ) ) {
		return 0;
	}

	// A reset-your-password link, never the random password itself -
	// WordPress' own standard mechanism for "here's an account we made
	// you, come set a password whenever you like."
	wp_new_user_notification( $user_id, null, 'user' );

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id );

	return $user_id;
}

/**
 * Derive a unique WordPress username from an email address's local
 * part (before the @), falling back to "client" if sanitizing leaves
 * nothing usable, and appending a numeric suffix until it no longer
 * collides with an existing username. Used only by
 * bizupkeep_child_register_guest_applicant(), since usernames still
 * have to be unique even though this account is otherwise identified
 * by email everywhere that matters.
 */
function bizupkeep_child_unique_username_from_email( string $email ): string {
	$base = sanitize_user( (string) strtok( $email, '@' ), true );
	$base = '' !== $base ? $base : 'client';

	$username = $base;
	$suffix   = 1;

	while ( username_exists( $username ) ) {
		++$suffix;
		$username = $base . $suffix;
	}

	return $username;
}

/**
 * Derive a non-empty first/last name pair from a WordPress user,
 * falling back through first_name/last_name meta, display_name, and
 * finally the username - BizHub's Profile entity requires both to be
 * non-empty.
 *
 * @return array{0:string,1:string}
 */
function bizupkeep_child_split_user_name( WP_User $wp_user ): array {
	$first_name = trim( (string) $wp_user->first_name );
	$last_name  = trim( (string) $wp_user->last_name );

	if ( '' !== $first_name && '' !== $last_name ) {
		return array( $first_name, $last_name );
	}

	$parts = preg_split( '/\s+/', trim( (string) $wp_user->display_name ), 2 );

	if ( '' !== ( $parts[0] ?? '' ) ) {
		return array( $parts[0], $parts[1] ?? $wp_user->user_login );
	}

	return array( $wp_user->user_login, $wp_user->user_login );
}

/**
 * Apply page (Company Registration application intake).
 *
 * The header and homepage template's "Start Application" buttons have
 * always linked to /apply/, but no page existed there. This creates
 * that page (idempotently, on theme activation) using the "BizUpKeep
 * Apply" template, and wires its form to BizUpKeep Workflow's
 * CompanyRegistrationService directly from the container, the same way
 * bizupkeep_child_ensure_client_record() already does for Clients.
 *
 * Earlier versions of this handler also created a record in BizHub's
 * older, generic Applications module (ApplicationServiceInterface) -
 * a leftover from before this Workflow-based intake existed. Nothing
 * ever read that record back (not the client portal, not Quality
 * Review), so it was removed; the one real piece of data it carried,
 * the client's free-text notes, now lives in the workflow's own
 * client_notes metadata instead (see
 * bizupkeep_child_submit_new_registration()).
 *
 * Resolving "current user's numeric bizhub_clients.id" needed a small
 * additive fix to BizHub's Client entity/ClientRepository, since
 * neither previously exposed it - see Client::getId() in the bizhub
 * plugin.
 */
add_action( 'after_switch_theme', 'bizupkeep_child_setup_apply_page' );

/**
 * Idempotently create the "Apply" page and assign it the apply
 * template. Safe to run on every theme activation.
 */
function bizupkeep_child_setup_apply_page(): void {
	$apply_id = bizupkeep_child_get_or_create_page( 'apply', __( 'Apply Now', 'bizupkeep-astra-child' ), '', 0 );

	if ( 0 === $apply_id ) {
		return;
	}

	update_post_meta( $apply_id, '_wp_page_template', 'page-templates/template-apply.php' );
}

add_action( 'template_redirect', 'bizupkeep_child_handle_apply_submission' );

/**
 * Handle the Apply form's POST submission. Runs on template_redirect
 * (before the page template renders) so it can redirect on success or
 * failure - the template itself only ever handles GET display.
 *
 * No login is required to submit: a guest is silently registered and
 * logged in first (see bizupkeep_child_register_guest_applicant()),
 * using the name/email/phone posted alongside the rest of the form -
 * an already-logged-in client just uses their existing account as
 * before. Either way, by the time this dispatches to one of the three
 * per-type submit handlers below (New Registration / Company Amendment
 * / Annual Return - see template-apply.php's radio buttons), a real
 * WordPress user is guaranteed, so those handlers don't need to know
 * whether the client just registered or has had an account for years.
 */
function bizupkeep_child_handle_apply_submission(): void {
	if ( ! isset( $_POST['bizupkeep_apply_nonce'] ) ) {
		return;
	}

	if ( ! is_page() || bizupkeep_child_find_page( 'apply', 0 ) !== get_queried_object_id() ) {
		return;
	}

	check_admin_referer( 'bizupkeep_apply', 'bizupkeep_apply_nonce' );

	$guest_phone = isset( $_POST['guest_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_phone'] ) ) : '';

	if ( is_user_logged_in() ) {
		$wp_user_id = get_current_user_id();
	} else {
		$guest_email = isset( $_POST['guest_email'] ) ? sanitize_email( wp_unslash( $_POST['guest_email'] ) ) : '';
		$wp_user_id  = bizupkeep_child_register_guest_applicant();

		if ( 0 === $wp_user_id ) {
			$error = ( '' !== $guest_email && email_exists( $guest_email ) ) ? 'email_exists' : '1';

			wp_safe_redirect( add_query_arg( 'apply_error', $error, get_permalink() ) );
			exit;
		}
	}

	$application_type  = isset( $_POST['application_type'] ) ? sanitize_text_field( wp_unslash( $_POST['application_type'] ) ) : '';
	$notes             = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

	bizupkeep_child_ensure_client_record( $wp_user_id, $guest_phone );

	$submitted = match ( $application_type ) {
		'new_registration'  => bizupkeep_child_submit_new_registration( $wp_user_id, $notes ),
		'company_amendment' => bizupkeep_child_submit_company_amendment( $wp_user_id, $notes ),
		'annual_return'     => bizupkeep_child_submit_annual_return( $wp_user_id, $notes ),
		default              => false,
	};

	if ( ! $submitted ) {
		wp_safe_redirect( add_query_arg( 'apply_error', '1', get_permalink() ) );
		exit;
	}

	wp_safe_redirect( add_query_arg( 'submitted', '1', get_permalink() ) );
	exit;
}

/**
 * Advance a freshly-started Company Registration/Amendment straight
 * from Created to AwaitingPayment in one go, instead of the old
 * "wait for documents first" gate - a client can now pay the moment
 * they submit, and upload supporting documents any time before or
 * after (see bizupkeep_child_applications_sections()'s can_upload
 * logic, which no longer requires PendingDocuments specifically).
 *
 * The workflow engine itself has no document-count requirement of its
 * own to work around here: verify_documents' guard only checks that
 * the caller passes documents_verified === true (see
 * CompanyRegistrationGuard::guardVerifyDocuments()/
 * CompanyAmendmentGuard's equivalent) - "wait for real documents" was
 * always a theme-level policy choice
 * (bizupkeep_child_maybe_verify_documents()), not something the
 * engine enforced. That function is left untouched and still finishes
 * out any application already sitting at PendingDocuments from before
 * this change; it simply never has anything to do for a newly
 * submitted one, since this advances straight past that status.
 *
 * request_documents still fires first purely so the transition
 * history a staff member sees on Quality Review keeps recording it -
 * all three actions land in the same request, just with no real gap
 * in time between them. $requestDocumentsReason defaults to a generic
 * note but lets a caller record something more specific as that first
 * transition's reason - bizupkeep_child_submit_company_amendment()
 * passes the client's free-text "anything else we should know" notes
 * through here, the only place that field has ever been recorded
 * (Company Registration's own notes go into the workflow's
 * client_notes metadata instead - a separate, unrelated mechanism).
 */
function bizupkeep_child_advance_to_awaiting_payment( WorkflowTypeServiceInterface $service, string $workflow_uuid, int $wp_user_id, string $requestDocumentsReason = '' ): void {
	$service->performAction(
		$workflow_uuid,
		'request_documents',
		$wp_user_id,
		'' !== $requestDocumentsReason ? $requestDocumentsReason : __( 'Documents requested automatically at application submission.', 'bizupkeep-astra-child' )
	);

	$service->performAction(
		$workflow_uuid,
		'verify_documents',
		$wp_user_id,
		__( 'Payment requested immediately at submission - supporting documents can be uploaded separately, any time, via My Applications.', 'bizupkeep-astra-child' ),
		array( 'documents_verified' => true )
	);

	$service->performAction(
		$workflow_uuid,
		'request_payment',
		$wp_user_id,
		__( 'Payment requested automatically at application submission.', 'bizupkeep-astra-child' )
	);
}

/**
 * Email a client a direct payment link plus a documents reminder
 * immediately after submitting a Company Registration or Company
 * Amendment application - the same "Pay Now" URL My Applications
 * shows, sent straight to their inbox so they don't have to log in
 * just to find it. A plain wp_mail() call rather than routed through
 * bizupkeep-workflow's own WorkflowNotificationListener/
 * config/notifications.php: that system has no concept of a
 * WooCommerce URL (a theme-level detail) and already fires its own
 * generic per-action notification alongside this one for the in-app
 * feed - this is the richer, actually-clickable counterpart.
 */
function bizupkeep_child_send_submission_payment_email( int $wp_user_id, string $workflow_type_label, string $company_identifier, string $pay_url ): void {
	$wp_user = get_userdata( $wp_user_id );

	if ( ! $wp_user ) {
		return;
	}

	$subject = sprintf(
		/* translators: %s: workflow type label, e.g. "Company Registration" */
		__( 'Payment required - %s application received', 'bizupkeep-astra-child' ),
		$workflow_type_label
	);

	$body = sprintf(
		/* translators: 1: client's first name, 2: workflow type label, 3: company identifier, 4: Pay Now URL, 5: My Applications URL */
		__(
			"Hi %1\$s,\n\nThanks for submitting your %2\$s application for %3\$s.\n\nYou can pay for it right away here:\n%4\$s\n\nYou can also log in and upload your supporting documents (ID document, and any signed forms we generate for you) any time before or after paying, from your Client Portal:\n%5\$s\n\nWe'll be in touch once everything is in.\n",
			'bizupkeep-astra-child'
		),
		'' !== $wp_user->first_name ? $wp_user->first_name : $wp_user->display_name,
		$workflow_type_label,
		$company_identifier,
		$pay_url,
		home_url( '/client-portal/client-portal-applications/' )
	);

	wp_mail( $wp_user->user_email, $subject, $body );
}

/**
 * Create and submit a New Company Registration application. Returns
 * false (rather than throwing) on any failure, so the caller can show
 * a generic "something went wrong" state - this runs from a public-
 * facing form submission, not somewhere an uncaught exception should
 * ever be allowed to surface as a fatal error page.
 *
 * Private Company (Pty) Ltd only - Close Corporations can no longer
 * be registered, so companyType is hardcoded rather than taken from
 * form input (there is no longer a form field for it at all).
 */
function bizupkeep_child_submit_new_registration( int $wp_user_id, string $notes ): bool {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return false;
	}

	$proposed_names = isset( $_POST['proposed_name'] ) && is_array( $_POST['proposed_name'] )
		? bizupkeep_child_parse_proposed_names( $_POST['proposed_name'] )
		: array();

	if ( array() === $proposed_names ) {
		return false;
	}

	$address = isset( $_POST['company_address'] ) && is_array( $_POST['company_address'] )
		? bizupkeep_child_parse_address_input( $_POST['company_address'] )
		: new AddressData( '', '', '', '', '', '' );

	if ( '' === trim( $address->addressLine1 ) || '' === trim( $address->city ) || '' === trim( $address->postalCode ) ) {
		return false;
	}

	$directors = isset( $_POST['director'] ) && is_array( $_POST['director'] )
		? bizupkeep_child_parse_directors_input( $_POST['director'] )
		: array();

	if ( array() === $directors ) {
		return false; // At least one director is required.
	}

	if ( count( $directors ) > 10 ) {
		$directors = array_slice( $directors, 0, 10 );
	}

	$clients = bizhub()->container()->get( ClientServiceInterface::class );

	try {
		$client = $clients->getClientByWpUserId( $wp_user_id );
	} catch ( ClientNotFoundException $e ) {
		return false;
	}

	$client_id = $client->getId();

	if ( null === $client_id ) {
		return false;
	}

	try {
		$company_uuid = wp_generate_uuid4();

		// registrationNumber is required and unique at both the entity
		// and DB level (bizhub_companies.registration_number is
		// NOT NULL + UNIQUE) - but a real CIPC number doesn't exist
		// until the company is actually registered, which is the whole
		// point of the workflow this kicks off. CompanyStatus::CREATED
		// plus a per-company-unique placeholder is the pattern the
		// Companies module already supports for this (see
		// CompanyService::updateCompany(), which swaps in the real
		// number once CIPC issues one).
		//
		// The first proposed name becomes the working company_name
		// (Company only ever has one), with all 4 preferences recorded
		// in both the application comment and the workflow's own
		// "proposed_names" metadata for staff review.
		$companies = bizhub()->container()->get( CompanyServiceInterface::class );

		$companies->createCompany(
			new CompanyData(
				$company_uuid,
				$client_id,
				'PENDING-' . $company_uuid,
				$proposed_names[0],
				__( 'Private Company (Pty) Ltd', 'bizupkeep-astra-child' ),
				CompanyStatus::CREATED,
				$address,
				$directors
			)
		);

		// Not wrapped in a shared DB transaction - the framework's
		// DatabaseInterface doesn't expose one, and neither does any
		// other module in this codebase. A failure here leaves the
		// Company row in place without a WorkflowInstance, which is
		// recoverable manually (the company still exists in
		// CompanyStatus::CREATED) but not automatic.
		//
		// The client's free-text notes go straight into the workflow's
		// own client_notes metadata (matching AnnualReturnService's
		// pattern) rather than the older Applications module, which used
		// to hold them as a comment nothing ever read back - see
		// bizupkeep_child_setup_apply_page()'s docblock for the history.
		$registration = bizhub()->container()->get( CompanyRegistrationService::class );
		$metadata     = array( 'proposed_names' => $proposed_names );

		if ( '' !== trim( $notes ) ) {
			$metadata['client_notes'] = $notes;
		}

		$instance = $registration->start( $company_uuid, $wp_user_id, $metadata );

		// Straight to AwaitingPayment - the client can pay immediately
		// and submit supporting documents separately, any time. See
		// bizupkeep_child_advance_to_awaiting_payment()'s docblock.
		bizupkeep_child_advance_to_awaiting_payment( $registration, $instance->getUuid(), $wp_user_id );

		bizupkeep_child_send_submission_payment_email(
			$wp_user_id,
			__( 'Company Registration', 'bizupkeep-astra-child' ),
			$proposed_names[0],
			bizupkeep_child_registration_payment_url( $instance->getUuid() )
		);
	} catch ( \Throwable $e ) {
		return false;
	}

	return true;
}

/**
 * Start a Company Amendment workflow (director, name, and/or address
 * change, any combination) for one of the client's existing
 * companies. Returns false on any failure, per the same
 * public-form-submission convention as the other two submit handlers.
 *
 * Director/name/address changes are recorded as workflow metadata
 * only (via CompanyAmendmentService::start()) - they are proposed
 * changes pending staff review, not applied to the live Company/
 * Director records immediately. Nothing in this codebase auto-applies
 * an approved change yet (Company Registration's own "Approve" is
 * likewise just a status transition today), so this doesn't invent
 * that behaviour just for amendments.
 */
function bizupkeep_child_submit_company_amendment( int $wp_user_id, string $notes ): bool {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return false;
	}

	$company = bizupkeep_child_resolve_company_for_submission( $wp_user_id, 'amendment', true );

	if ( null === $company ) {
		return false;
	}

	$company_uuid = $company->getUuid();

	$allowed_types    = array(
		CompanyAmendmentDefinition::AMENDMENT_TYPE_DIRECTOR,
		CompanyAmendmentDefinition::AMENDMENT_TYPE_NAME,
		CompanyAmendmentDefinition::AMENDMENT_TYPE_ADDRESS,
	);
	$posted_types     = isset( $_POST['amendment_types'] ) && is_array( $_POST['amendment_types'] )
		? array_map( 'sanitize_text_field', wp_unslash( $_POST['amendment_types'] ) )
		: array();
	$amendment_types  = array_values( array_intersect( $posted_types, $allowed_types ) );

	if ( array() === $amendment_types ) {
		return false;
	}

	$proposed_names = array();

	if ( in_array( CompanyAmendmentDefinition::AMENDMENT_TYPE_NAME, $amendment_types, true ) ) {
		$proposed_names = isset( $_POST['amendment_proposed_name'] ) && is_array( $_POST['amendment_proposed_name'] )
			? bizupkeep_child_parse_proposed_names( $_POST['amendment_proposed_name'] )
			: array();

		if ( array() === $proposed_names ) {
			return false;
		}
	}

	$new_address = array();

	if ( in_array( CompanyAmendmentDefinition::AMENDMENT_TYPE_ADDRESS, $amendment_types, true ) ) {
		$address_data = isset( $_POST['amendment_address'] ) && is_array( $_POST['amendment_address'] )
			? bizupkeep_child_parse_address_input( $_POST['amendment_address'] )
			: new AddressData( '', '', '', '', '', '' );

		if ( '' === trim( $address_data->addressLine1 ) || '' === trim( $address_data->city ) || '' === trim( $address_data->postalCode ) ) {
			return false;
		}

		$new_address = $address_data->toArray();
	}

	$director_changes = array();

	if ( in_array( CompanyAmendmentDefinition::AMENDMENT_TYPE_DIRECTOR, $amendment_types, true ) ) {
		$director_changes = bizupkeep_child_parse_director_changes( $company_uuid );

		if ( array() === $director_changes ) {
			return false;
		}
	}

	try {
		$amendments = bizhub()->container()->get( CompanyAmendmentService::class );
		$instance   = $amendments->start( $company_uuid, $wp_user_id, $amendment_types, $proposed_names, $director_changes, $new_address );

		// Straight to AwaitingPayment - the client can pay immediately
		// and submit supporting documents separately, any time. See
		// bizupkeep_child_advance_to_awaiting_payment()'s docblock.
		bizupkeep_child_advance_to_awaiting_payment( $amendments, $instance->getUuid(), $wp_user_id, $notes );

		bizupkeep_child_send_submission_payment_email(
			$wp_user_id,
			__( 'Company Amendment', 'bizupkeep-astra-child' ),
			$company->getCompanyName(),
			bizupkeep_child_amendment_payment_url( $instance->getUuid() )
		);
	} catch ( \Throwable $e ) {
		return false;
	}

	return true;
}

/**
 * Start an Annual Return workflow for one of the client's existing
 * companies, covering one or more outstanding financial years (each
 * with its own turnover figure - CIPC's filing fee is turnover-banded,
 * and a client behind on several years files and pays for all of them
 * in one application rather than separately). Returns false on any
 * failure, per the same public-form-submission convention as the
 * other two submit handlers.
 *
 * Unlike Company Registration/Amendment, this does NOT immediately
 * fire ACTION_REQUEST_PAYMENT - the application stays in Created until
 * staff check CIPC and send a quote from the Quality Review screen
 * (see AnnualReturnGuard::guardQuoteAmount(), which now requires a
 * quote_amount in context). "Created" is the client-visible "awaiting
 * our review" state for this workflow type - see
 * bizupkeep_child_workflow_status_label().
 */
function bizupkeep_child_submit_annual_return( int $wp_user_id, string $notes ): bool {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return false;
	}

	$company = bizupkeep_child_resolve_company_for_submission( $wp_user_id, 'return' );

	if ( null === $company ) {
		return false;
	}

	$filings = isset( $_POST['filing'] ) && is_array( $_POST['filing'] )
		? bizupkeep_child_parse_filings_input( $_POST['filing'] )
		: array();

	if ( array() === $filings ) {
		return false;
	}

	$metadata = '' !== trim( $notes ) ? array( 'client_notes' => $notes ) : array();

	try {
		$returns = bizhub()->container()->get( AnnualReturnService::class );
		$returns->start( $company->getUuid(), $wp_user_id, $filings, $metadata );
	} catch ( \Throwable $e ) {
		return false;
	}

	return true;
}

/**
 * Resolve which company a Company Amendment/Annual Return application
 * is for, per the "{$prefix}_company_mode" radio the client picked -
 * either one of their existing companies (unchanged from before:
 * looked up and ownership-verified from "{$prefix}_company_uuid"), or
 * a brand new company record for one that isn't registered with us at
 * all yet (from "{$prefix}_new_company[...]"). Returns null on any
 * failure, per the same public-form-submission convention every other
 * submit helper in this file uses.
 *
 * $require_directors is passed straight through to
 * bizupkeep_child_create_external_company() - see its docblock.
 */
function bizupkeep_child_resolve_company_for_submission( int $wp_user_id, string $prefix, bool $require_directors = false ): ?Company {
	$mode = isset( $_POST[ "{$prefix}_company_mode" ] ) ? sanitize_text_field( wp_unslash( $_POST[ "{$prefix}_company_mode" ] ) ) : 'existing';

	if ( 'new' === $mode ) {
		$raw = isset( $_POST[ "{$prefix}_new_company" ] ) && is_array( $_POST[ "{$prefix}_new_company" ] )
			? $_POST[ "{$prefix}_new_company" ]
			: array();

		return bizupkeep_child_create_external_company( $wp_user_id, $raw, $require_directors );
	}

	$company_uuid = isset( $_POST[ "{$prefix}_company_uuid" ] ) ? sanitize_text_field( wp_unslash( $_POST[ "{$prefix}_company_uuid" ] ) ) : '';

	return bizupkeep_child_get_owned_company( $wp_user_id, $company_uuid );
}

/**
 * Create a Company record for a company that exists (at CIPC) but was
 * never registered through us - the client is only filing an
 * amendment or annual return for it, not registering it fresh. Unlike
 * bizupkeep_child_submit_new_registration()'s placeholder
 * 'PENDING-{uuid}' registration number and CompanyStatus::CREATED
 * (used while CIPC registration is still in progress), this uses the
 * real registration number the client provides and
 * CompanyStatus::ACTIVE, since the company is already a going concern
 * - it just isn't one of our own Company Registration workflow's
 * outputs. Once created, it behaves exactly like any other company on
 * the account (shows up in the "existing company" picker for future
 * applications, etc.).
 *
 * $require_directors (Company Amendment only) parses $raw['director']
 * the same way bizupkeep_child_submit_new_registration() parses its
 * own director repeater, and fails the whole submission if none were
 * given - mirroring that function's "at least one director required"
 * rule, since without directors here the company gets created with
 * none on file and bizupkeep_child_render_poa_document() has no one
 * to list as needing to sign. Annual Return's "not registered with
 * us" branch never collects directors (no POA involved), so it
 * leaves this false and $raw['director'] is simply never present.
 *
 * @param array<string,mixed> $raw
 */
function bizupkeep_child_create_external_company( int $wp_user_id, array $raw, bool $require_directors = false ): ?Company {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return null;
	}

	$raw               = wp_unslash( $raw );
	$company_name      = sanitize_text_field( $raw['company_name'] ?? '' );
	$registration_no   = sanitize_text_field( $raw['registration_number'] ?? '' );

	if ( '' === $company_name || '' === $registration_no ) {
		return null;
	}

	$address = isset( $raw['address'] ) && is_array( $raw['address'] )
		? bizupkeep_child_parse_address_input( $raw['address'] )
		: new AddressData( '', '', '', '', '', '' );

	if ( '' === trim( $address->addressLine1 ) || '' === trim( $address->city ) || '' === trim( $address->postalCode ) ) {
		return null;
	}

	$directors = isset( $raw['director'] ) && is_array( $raw['director'] )
		? bizupkeep_child_parse_directors_input( $raw['director'] )
		: array();

	if ( $require_directors && array() === $directors ) {
		return null;
	}

	if ( count( $directors ) > 10 ) {
		$directors = array_slice( $directors, 0, 10 );
	}

	$clients = bizhub()->container()->get( ClientServiceInterface::class );

	try {
		$client = $clients->getClientByWpUserId( $wp_user_id );
	} catch ( ClientNotFoundException $e ) {
		return null;
	}

	$client_id = $client->getId();

	if ( null === $client_id ) {
		return null;
	}

	try {
		$companies = bizhub()->container()->get( CompanyServiceInterface::class );

		return $companies->createCompany(
			new CompanyData(
				wp_generate_uuid4(),
				$client_id,
				$registration_no,
				$company_name,
				__( 'Private Company (Pty) Ltd', 'bizupkeep-astra-child' ),
				CompanyStatus::ACTIVE,
				$address,
				$directors
			)
		);
	} catch ( \Throwable $e ) {
		// Most likely InvalidCompanyException::duplicateRegistrationNumber()
		// (this exact registration number is already on file, e.g. a
		// double form submission) - either way, treated as a generic
		// submission failure like every other guarded step on this form.
		return null;
	}
}

/**
 * Parse a posted address sub-array (e.g. $_POST['company_address'])
 * into an AddressData DTO. Shared by New Registration's company
 * address and Company Amendment's new address.
 *
 * @param array<string,mixed> $raw
 */
function bizupkeep_child_parse_address_input( array $raw ): AddressData {
	$raw = wp_unslash( $raw );

	return new AddressData(
		sanitize_text_field( $raw['address_line_1'] ?? '' ),
		sanitize_text_field( $raw['address_line_2'] ?? '' ),
		sanitize_text_field( $raw['suburb'] ?? '' ),
		sanitize_text_field( $raw['city'] ?? '' ),
		sanitize_text_field( $raw['province'] ?? '' ),
		sanitize_text_field( $raw['postal_code'] ?? '' )
	);
}

/**
 * Parse a posted list of proposed company names (e.g.
 * $_POST['proposed_name'], a 4-entry indexed array from the repeated
 * "1st choice".."4th choice" fields) into a clean, non-empty,
 * in-order list. Shared by New Registration and Company Amendment's
 * name-change section.
 *
 * @param array<int,mixed> $raw
 * @return string[]
 */
function bizupkeep_child_parse_proposed_names( array $raw ): array {
	$raw = wp_unslash( $raw );

	return array_values(
		array_filter(
			array_map( 'sanitize_text_field', $raw ),
			static function ( $name ) {
				return '' !== trim( (string) $name );
			}
		)
	);
}

/**
 * Parse a posted director repeater (e.g. $_POST['director'], an
 * indexed array of {first_name, last_name, id_number, ...} blocks
 * from the "+ Add Director" repeater) into DirectorData DTOs. Blank
 * rows (an "Add Director" block the client never filled in) and rows
 * missing both an ID and passport number (required by the Director
 * entity - see Director::validate()) are silently skipped rather than
 * failing the whole submission over one empty repeater block.
 *
 * @param array<int,array<string,mixed>> $raw
 * @return DirectorData[]
 */
function bizupkeep_child_parse_directors_input( array $raw ): array {
	$raw       = wp_unslash( $raw );
	$directors = array();

	foreach ( $raw as $entry ) {
		if ( ! is_array( $entry ) ) {
			continue;
		}

		$first_name = sanitize_text_field( $entry['first_name'] ?? '' );
		$last_name  = sanitize_text_field( $entry['last_name'] ?? '' );

		if ( '' === $first_name || '' === $last_name ) {
			continue;
		}

		$id_number       = sanitize_text_field( $entry['id_number'] ?? '' );
		$passport_number = sanitize_text_field( $entry['passport_number'] ?? '' );

		if ( '' === $id_number && '' === $passport_number ) {
			continue;
		}

		$phone = sanitize_text_field( $entry['phone'] ?? '' );
		$email = sanitize_email( $entry['email'] ?? '' );

		$address = null;

		if ( ! empty( $entry['address'] ) && is_array( $entry['address'] ) && '' !== trim( (string) ( $entry['address']['address_line_1'] ?? '' ) ) ) {
			$address = new AddressData(
				sanitize_text_field( $entry['address']['address_line_1'] ?? '' ),
				sanitize_text_field( $entry['address']['address_line_2'] ?? '' ),
				sanitize_text_field( $entry['address']['suburb'] ?? '' ),
				sanitize_text_field( $entry['address']['city'] ?? '' ),
				sanitize_text_field( $entry['address']['province'] ?? '' ),
				sanitize_text_field( $entry['address']['postal_code'] ?? '' )
			);
		}

		$directors[] = new DirectorData(
			wp_generate_uuid4(),
			$first_name,
			$last_name,
			'' !== $id_number ? $id_number : null,
			'' !== $passport_number ? $passport_number : null,
			new \DateTimeImmutable(),
			null,
			true,
			'' !== $phone ? $phone : null,
			'' !== $email ? $email : null,
			$address
		);
	}

	return $directors;
}

/**
 * Parse a posted Annual Return filing repeater (e.g. $_POST['filing'],
 * an indexed array of {financial_year, turnover} blocks) into a clean
 * list of ['financial_year' => int, 'turnover' => float] pairs, for
 * AnnualReturnService::start(). Blank rows (no year entered) are
 * silently skipped, matching bizupkeep_child_parse_directors_input()'s
 * "an unused '+ Add' block just disappears" behaviour. Rows repeating
 * a year already seen earlier in the same submission are also
 * skipped - filing the same year twice in one application doesn't
 * mean anything, and would otherwise just confuse
 * AnnualReturnService's duplicate-year check.
 *
 * @param array<int,array<string,mixed>> $raw
 * @return array<int,array{financial_year:int,turnover:float}>
 */
function bizupkeep_child_parse_filings_input( array $raw ): array {
	$raw     = wp_unslash( $raw );
	$filings = array();
	$seen_years = array();

	foreach ( $raw as $entry ) {
		if ( ! is_array( $entry ) ) {
			continue;
		}

		$year = absint( $entry['financial_year'] ?? 0 );

		if ( $year < 2000 || $year > 2100 || in_array( $year, $seen_years, true ) ) {
			continue;
		}

		$turnover = isset( $entry['turnover'] ) ? (float) $entry['turnover'] : 0.0;

		$seen_years[] = $year;
		$filings[]    = array(
			'financial_year' => $year,
			'turnover'        => max( 0.0, $turnover ),
		);
	}

	return $filings;
}

/**
 * Build a Company Amendment's "director_changes" metadata array: a
 * "remove" entry for each posted director UUID that actually belongs
 * to this company (re-verified here, not trusted from the form - a
 * posted UUID for someone else's director is silently dropped, the
 * same ownership pattern used throughout this file), plus an "add"
 * entry for each filled-in row of the "Add new director(s)" repeater.
 *
 * @return array<int,array<string,mixed>>
 */
function bizupkeep_child_parse_director_changes( string $company_uuid ): array {
	$changes = array();

	$remove_uuids = isset( $_POST['director_remove'] ) && is_array( $_POST['director_remove'] )
		? array_map( 'sanitize_text_field', wp_unslash( $_POST['director_remove'] ) )
		: array();

	if ( array() !== $remove_uuids ) {
		$directors = bizhub()->container()->get( DirectorRepositoryInterface::class );

		foreach ( $remove_uuids as $uuid ) {
			$director = $directors->findByUuid( $uuid );

			if ( null !== $director && $director->getCompanyUuid() === $company_uuid ) {
				$changes[] = array(
					'action' => 'remove',
					'uuid'   => $uuid,
					'name'   => $director->getFullName(),
				);
			}
		}
	}

	if ( isset( $_POST['amendment_director'] ) && is_array( $_POST['amendment_director'] ) ) {
		foreach ( bizupkeep_child_parse_directors_input( $_POST['amendment_director'] ) as $director_data ) {
			$changes[] = array(
				'action'          => 'add',
				'first_name'      => $director_data->firstName,
				'last_name'       => $director_data->lastName,
				'id_number'       => $director_data->idNumber,
				'passport_number' => $director_data->passportNumber,
				'phone'           => $director_data->phone,
				'email'           => $director_data->email,
			);
		}
	}

	return $changes;
}

/**
 * Build the data the Apply form needs for the Company Amendment and
 * Annual Return sections: every company belonging to the logged-in
 * client, with its current directors (for the "tick to remove a
 * director" list).
 *
 * @return array<int,array{uuid:string,name:string,directors:array<int,array{uuid:string,full_name:string}>}>
 */
function bizupkeep_child_client_companies_for_form( int $wp_user_id ): array {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return array();
	}

	$clients = bizhub()->container()->get( ClientServiceInterface::class );

	try {
		$client = $clients->getClientByWpUserId( $wp_user_id );
	} catch ( ClientNotFoundException $e ) {
		return array();
	}

	$client_id = $client->getId();

	if ( null === $client_id ) {
		return array();
	}

	$companies = bizhub()->container()->get( CompanyServiceInterface::class );
	$directors = bizhub()->container()->get( DirectorRepositoryInterface::class );

	$result = array();

	foreach ( $companies->getCompaniesForClient( $client_id ) as $company ) {
		$company_directors = array_map(
			static function ( $director ) {
				return array(
					'uuid'      => $director->getUuid(),
					'full_name' => $director->getFullName(),
				);
			},
			$directors->findByCompanyUuid( $company->getUuid() )
		);

		$result[] = array(
			'uuid'      => $company->getUuid(),
			'name'      => $company->getCompanyName(),
			'directors' => $company_directors,
		);
	}

	return $result;
}

/**
 * Render one address field group (address line 1/2, suburb, city,
 * province, postal code) with the given field-name prefix, e.g.
 * bizupkeep_child_render_address_fields( 'company_address' ) renders
 * inputs named company_address[address_line_1] etc. Shared by the
 * company-level address section and each director block's own
 * address sub-group.
 */
function bizupkeep_child_render_address_fields( string $prefix ): void {
	?>
	<p>
		<label><?php esc_html_e( 'Address Line 1', 'bizupkeep-astra-child' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $prefix ); ?>[address_line_1]">
	</p>
	<p>
		<label><?php esc_html_e( 'Address Line 2 (optional)', 'bizupkeep-astra-child' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $prefix ); ?>[address_line_2]">
	</p>
	<p>
		<label><?php esc_html_e( 'Suburb', 'bizupkeep-astra-child' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $prefix ); ?>[suburb]">
	</p>
	<p>
		<label><?php esc_html_e( 'City', 'bizupkeep-astra-child' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $prefix ); ?>[city]">
	</p>
	<p>
		<label><?php esc_html_e( 'Province', 'bizupkeep-astra-child' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $prefix ); ?>[province]">
	</p>
	<p>
		<label><?php esc_html_e( 'Postal Code', 'bizupkeep-astra-child' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $prefix ); ?>[postal_code]">
	</p>
	<?php
}

/**
 * Render the "Which Company?" block shared by the Company Amendment
 * and Annual Return sections: a choice between one of the client's
 * existing companies (a picker, same as before) and a company that
 * isn't registered with us at all (a small name/registration number/
 * address form) - since staff can file an amendment or annual return
 * for a company we never originally registered. $prefix ('amendment'
 * or 'return') namespaces every field name and matches what
 * bizupkeep_child_resolve_company_for_submission() reads back out of
 * $_POST on submit.
 *
 * $include_directors (Company Amendment only - Annual Return has no
 * Power of Attorney and doesn't need this) adds a "Current Directors"
 * repeater to the "not registered with us" branch, collected up front
 * alongside the company's identity rather than behind the "Director
 * Amendment" checkbox: a company created via this branch otherwise
 * has zero directors on file, whatever amendment type(s) get picked,
 * since bizupkeep_child_render_poa_document() reads the company's
 * *current* directors (the people who actually need to sign the
 * POA) - not the "Director Amendment" section's proposed add/remove
 * changes, which are just workflow metadata pending staff review, not
 * yet-real people. See bizupkeep_child_create_external_company(),
 * which is what actually attaches these to the new Company record.
 *
 * @param array<int,array{uuid:string,name:string,directors:array<int,array{uuid:string,full_name:string}>}> $companies
 */
function bizupkeep_child_render_company_picker( string $prefix, array $companies, bool $include_directors = false ): void {
	$has_companies = array() !== $companies;
	?>
	<label class="bizupkeep-type-option">
		<input
			type="radio"
			name="<?php echo esc_attr( $prefix ); ?>_company_mode"
			value="existing"
			class="bizupkeep-company-mode-toggle"
			data-mode-prefix="<?php echo esc_attr( $prefix ); ?>"
			data-reveals="<?php echo esc_attr( $prefix ); ?>-mode-existing"
			<?php checked( $has_companies ); ?>
		>
		<?php esc_html_e( 'An existing company (already registered with us)', 'bizupkeep-astra-child' ); ?>
	</label>
	<label class="bizupkeep-type-option">
		<input
			type="radio"
			name="<?php echo esc_attr( $prefix ); ?>_company_mode"
			value="new"
			class="bizupkeep-company-mode-toggle"
			data-mode-prefix="<?php echo esc_attr( $prefix ); ?>"
			data-reveals="<?php echo esc_attr( $prefix ); ?>-mode-new"
			<?php checked( ! $has_companies ); ?>
		>
		<?php esc_html_e( "A company not registered with us", 'bizupkeep-astra-child' ); ?>
	</label>

	<div data-company-mode-section="<?php echo esc_attr( $prefix ); ?>-mode-existing" <?php echo $has_companies ? '' : 'hidden'; ?>>
		<?php if ( ! $has_companies ) : ?>
			<p class="bizupkeep-field-hint"><?php esc_html_e( "You don't have any companies registered with us yet - use the other option above.", 'bizupkeep-astra-child' ); ?></p>
		<?php else : ?>
			<p>
				<label for="bizupkeep-<?php echo esc_attr( $prefix ); ?>-company"><?php esc_html_e( 'Company', 'bizupkeep-astra-child' ); ?></label>
				<select id="bizupkeep-<?php echo esc_attr( $prefix ); ?>-company" name="<?php echo esc_attr( $prefix ); ?>_company_uuid" class="bizupkeep-company-picker" data-existing-directors-target="<?php echo esc_attr( $prefix ); ?>">
					<option value=""><?php esc_html_e( 'Select a company', 'bizupkeep-astra-child' ); ?></option>
					<?php foreach ( $companies as $company ) : ?>
						<option value="<?php echo esc_attr( $company['uuid'] ); ?>"><?php echo esc_html( $company['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php endif; ?>
	</div>

	<div data-company-mode-section="<?php echo esc_attr( $prefix ); ?>-mode-new" <?php echo $has_companies ? 'hidden' : ''; ?>>
		<p>
			<label for="bizupkeep-<?php echo esc_attr( $prefix ); ?>-new-name"><?php esc_html_e( 'Company Name', 'bizupkeep-astra-child' ); ?></label>
			<input type="text" id="bizupkeep-<?php echo esc_attr( $prefix ); ?>-new-name" name="<?php echo esc_attr( $prefix ); ?>_new_company[company_name]">
		</p>
		<p>
			<label for="bizupkeep-<?php echo esc_attr( $prefix ); ?>-new-regno"><?php esc_html_e( 'CIPC Registration Number', 'bizupkeep-astra-child' ); ?></label>
			<input type="text" id="bizupkeep-<?php echo esc_attr( $prefix ); ?>-new-regno" name="<?php echo esc_attr( $prefix ); ?>_new_company[registration_number]">
		</p>
		<h3><?php esc_html_e( 'Registered Address', 'bizupkeep-astra-child' ); ?></h3>
		<?php bizupkeep_child_render_address_fields( "{$prefix}_new_company[address]" ); ?>

		<?php if ( $include_directors ) : ?>
			<h3><?php esc_html_e( 'Current Directors', 'bizupkeep-astra-child' ); ?></h3>
			<p class="bizupkeep-field-hint"><?php esc_html_e( 'We require the full company profile regardless of changes made to ensure everything is up to date', 'bizupkeep-astra-child' ); ?></p>

			<div class="bizupkeep-repeater" data-repeater="<?php echo esc_attr( $prefix ); ?>-new-company-director" data-max="10" data-template-id="bizupkeep-<?php echo esc_attr( $prefix ); ?>-new-company-director-template">
				<div class="bizupkeep-repeater-blocks">
					<?php bizupkeep_child_render_director_fields( "{$prefix}_new_company[director]", 0 ); ?>
				</div>
				<button type="button" class="bizupkeep-btn bizupkeep-repeater-add"><?php esc_html_e( '+ Add Director', 'bizupkeep-astra-child' ); ?></button>
			</div>

			<template id="bizupkeep-<?php echo esc_attr( $prefix ); ?>-new-company-director-template">
				<?php bizupkeep_child_render_director_fields( "{$prefix}_new_company[director]", '__INDEX__' ); ?>
			</template>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render one director repeater block: name/ID/contact/address fields
 * under "{$prefix}[{$index}][...]", plus a Remove button. $index is a
 * plain int for server-rendered blocks (the one visible on page load)
 * or the literal string "__INDEX__" when rendering the <template> the
 * "+ Add Director" JS clones - see assets/js/custom.js, which replaces
 * "__INDEX__" with the next real index before inserting the clone.
 *
 * @param int|string $index
 */
function bizupkeep_child_render_director_fields( string $prefix, $index ): void {
	$base = sprintf( '%s[%s]', $prefix, $index );
	?>
	<div class="bizupkeep-director-block">
		<p>
			<label><?php esc_html_e( 'First Name', 'bizupkeep-astra-child' ); ?></label>
			<input type="text" name="<?php echo esc_attr( $base ); ?>[first_name]">
		</p>
		<p>
			<label><?php esc_html_e( 'Last Name', 'bizupkeep-astra-child' ); ?></label>
			<input type="text" name="<?php echo esc_attr( $base ); ?>[last_name]">
		</p>
		<p>
			<label><?php esc_html_e( 'SA ID Number', 'bizupkeep-astra-child' ); ?></label>
			<input type="text" name="<?php echo esc_attr( $base ); ?>[id_number]">
		</p>
		<p>
			<label><?php esc_html_e( 'Passport Number (if not an SA citizen)', 'bizupkeep-astra-child' ); ?></label>
			<input type="text" name="<?php echo esc_attr( $base ); ?>[passport_number]">
		</p>
		<p>
			<label><?php esc_html_e( 'Phone', 'bizupkeep-astra-child' ); ?></label>
			<input type="text" name="<?php echo esc_attr( $base ); ?>[phone]">
		</p>
		<p>
			<label><?php esc_html_e( 'Email', 'bizupkeep-astra-child' ); ?></label>
			<input type="email" name="<?php echo esc_attr( $base ); ?>[email]">
		</p>
		<?php bizupkeep_child_render_address_fields( $base . '[address]' ); ?>
		<button type="button" class="bizupkeep-btn bizupkeep-repeater-remove"><?php esc_html_e( 'Remove', 'bizupkeep-astra-child' ); ?></button>
	</div>
	<?php
}

/**
 * Render one Annual Return filing repeater block: financial year +
 * turnover under "{$prefix}[{$index}][...]", plus a Remove button.
 * Turnover is asked for because CIPC's Annual Return filing fee is
 * turnover-banded - staff need it to work out the right quote once
 * they've checked CIPC (see bizupkeep_child_render_director_fields()'s
 * docblock for the $index convention this mirrors).
 *
 * @param int|string $index
 */
function bizupkeep_child_render_filing_fields( string $prefix, $index ): void {
	$base = sprintf( '%s[%s]', $prefix, $index );
	?>
	<div class="bizupkeep-filing-block">
		<p>
			<label><?php esc_html_e( 'Financial Year', 'bizupkeep-astra-child' ); ?></label>
			<input type="number" name="<?php echo esc_attr( $base ); ?>[financial_year]" min="2000" max="2100">
		</p>
		<p>
			<label><?php esc_html_e( 'Annual Turnover for that Year (ZAR)', 'bizupkeep-astra-child' ); ?></label>
			<input type="number" name="<?php echo esc_attr( $base ); ?>[turnover]" min="0" step="0.01">
		</p>
		<button type="button" class="bizupkeep-btn bizupkeep-repeater-remove"><?php esc_html_e( 'Remove', 'bizupkeep-astra-child' ); ?></button>
	</div>
	<?php
}

/**
 * Document collection (Company Registration workflow's PendingDocuments
 * step).
 *
 * BizHub's Documents module has no upload route anywhere (REST only
 * exposes view/delete - see Documents/Controllers/DocumentController.php's
 * own docblock, which says file-upload handling is left to the caller)
 * and BizHub's Storage module is an unbuilt stub - the real file storage
 * lives in Documents\Services\DocumentStorageService, called via
 * DocumentService::uploadDocument(). This is the first place in the
 * codebase that actually handles a raw $_FILES upload into it.
 *
 * "Verifying" documents is purely a boolean the caller passes to
 * CompanyRegistrationService::performAction() - the workflow engine
 * has no awareness of real Document rows (see
 * CompanyRegistrationGuard::guard(), which only checks
 * $context['documents_verified'] === true). This wires the two
 * together: once both required categories (ID document, signed Power
 * of Attorney) are uploaded for a company, the workflow is advanced
 * automatically.
 *
 * Signed POA replaced Proof of Address as the second required
 * category in theme 1.13.0, matching the workflow spec's actual
 * document requirement ("ID certified not older than 3 months, POA
 * signed by all directors") - the generated POA itself is what the
 * client is expected to print, get signed, and upload back here. See
 * bizupkeep_child_render_poa_document().
 */

const BIZUPKEEP_REQUIRED_DOCUMENT_CATEGORIES = array(
	DocumentCategory::ID_DOCUMENT,
	DocumentCategory::SIGNED_POA,
);

/**
 * Every status a Company Registration/Amendment can be in while
 * document upload should still be offered on My Applications -
 * everything before a final outcome, now that payment no longer
 * waits on documents being in first (see
 * bizupkeep_child_advance_to_awaiting_payment()). A client can submit
 * documents before paying, after paying, or while staff are already
 * processing/reviewing the application - whichever order suits them.
 * Deliberately excludes Completed/Archived/Cancelled/Rejected: once
 * an application has a final outcome, there's nothing left to upload
 * documents against.
 */
const BIZUPKEEP_DOCUMENT_UPLOAD_STATUSES = array(
	WorkflowStatus::PendingDocuments,
	WorkflowStatus::DocumentsVerified,
	WorkflowStatus::AwaitingPayment,
	WorkflowStatus::Processing,
	WorkflowStatus::QualityReview,
	WorkflowStatus::NamesRejected,
);

/**
 * Company Amendment additionally requires a signed Resolution Letter
 * and Minutes of Meeting (see bizupkeep_child_render_resolution_document()/
 * bizupkeep_child_render_minutes_document()) - a board resolution
 * recording the directors' decision to make the specific change(s)
 * being applied for, which Company Registration has no equivalent of
 * (there's no existing board to resolve anything - the company doesn't
 * exist yet).
 */
const BIZUPKEEP_AMENDMENT_ADDITIONAL_REQUIRED_DOCUMENT_CATEGORIES = array(
	DocumentCategory::SIGNED_RESOLUTION,
	DocumentCategory::SIGNED_MINUTES,
);

const BIZUPKEEP_MAX_DOCUMENT_UPLOAD_BYTES = 5 * 1024 * 1024; // 5MB.

/**
 * The document categories a workflow instance must have on file before
 * bizupkeep_child_maybe_verify_documents() will advance it out of
 * PendingDocuments - see BIZUPKEEP_REQUIRED_DOCUMENT_CATEGORIES/
 * BIZUPKEEP_AMENDMENT_ADDITIONAL_REQUIRED_DOCUMENT_CATEGORIES for why
 * Company Amendment's set is larger than Company Registration's.
 *
 * @return DocumentCategory[]
 */
function bizupkeep_child_required_document_categories( string $workflow_type ): array {
	return CompanyAmendmentDefinition::TYPE === $workflow_type
		? array_merge( BIZUPKEEP_REQUIRED_DOCUMENT_CATEGORIES, BIZUPKEEP_AMENDMENT_ADDITIONAL_REQUIRED_DOCUMENT_CATEGORIES )
		: BIZUPKEEP_REQUIRED_DOCUMENT_CATEGORIES;
}

add_action( 'template_redirect', 'bizupkeep_child_handle_document_upload' );

/**
 * Handle the document upload form POST, now surfaced on My
 * Applications rather than a separate My Documents page. Still
 * accepted from the old My Documents page URL too (which now just
 * redirects into My Applications on a plain GET, see
 * template-documents.php) so a stale open tab or bookmark still
 * works. Runs on template_redirect (before the page template renders)
 * so it can redirect on success or failure.
 */
function bizupkeep_child_handle_document_upload(): void {
	if ( ! isset( $_POST['bizupkeep_upload_nonce'] ) ) {
		return;
	}

	$client_portal_id = bizupkeep_child_find_page( 'client-portal', 0 );
	$accepted_page_ids = array(
		bizupkeep_child_find_page( 'client-portal-applications', $client_portal_id ),
		bizupkeep_child_find_page( 'client-portal-documents', $client_portal_id ),
	);

	if ( ! is_page() || ! in_array( get_queried_object_id(), $accepted_page_ids, true ) ) {
		return;
	}

	$applications_url = home_url( '/client-portal/client-portal-applications/' );

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( $applications_url ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_upload_document', 'bizupkeep_upload_nonce' );

	$wp_user_id    = get_current_user_id();
	$workflow_uuid = isset( $_POST['workflow_uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['workflow_uuid'] ) ) : '';
	$category_raw  = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';

	$result = bizupkeep_child_process_document_upload( $wp_user_id, $workflow_uuid, $category_raw );

	wp_safe_redirect( add_query_arg( $result ? 'uploaded' : 'upload_error', '1', $applications_url ) );
	exit;
}

/**
 * Validate and store an uploaded document against a specific
 * application (workflow instance), then advance that same workflow if
 * the upload completes its required document set. Returns false
 * (rather than throwing) on any failure - ownership checks, file
 * validation, and BizHub service calls are all treated as "show a
 * generic error", not something that should ever surface as a fatal
 * error page from a public-facing form submission.
 *
 * Takes a workflow UUID, not a company UUID: a company can have
 * several applications in flight at once (e.g. a completed
 * registration and a fresh amendment), so "which application is this
 * upload for" has to be explicit, not inferred by guessing "the
 * company's most recent workflow" - see
 * bizupkeep_child_get_owned_workflow_instance()'s docblock for the bug
 * this replaced.
 */
function bizupkeep_child_process_document_upload( int $wp_user_id, string $workflow_uuid, string $category_raw ): bool {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return false;
	}

	// Only these categories are collected on this form - reject
	// anything else rather than trusting the posted value blindly.
	// Signed Resolution/Minutes only apply to Company Amendment (see
	// BIZUPKEEP_AMENDMENT_ADDITIONAL_REQUIRED_DOCUMENT_CATEGORIES), but
	// are allowed here regardless of workflow type - harmless if a
	// Registration application never ends up needing them, since
	// bizupkeep_child_required_document_categories() is what actually
	// decides whether a workflow can advance.
	$allowed_categories = array(
		'id_document' => DocumentCategory::ID_DOCUMENT,
		'signed_poa' => DocumentCategory::SIGNED_POA,
		'signed_resolution' => DocumentCategory::SIGNED_RESOLUTION,
		'signed_minutes' => DocumentCategory::SIGNED_MINUTES,
	);

	if ( ! isset( $allowed_categories[ $category_raw ] ) ) {
		return false;
	}

	$category = $allowed_categories[ $category_raw ];

	$workflow = bizupkeep_child_get_owned_workflow_instance( $wp_user_id, $workflow_uuid );

	if ( null === $workflow ) {
		return false;
	}

	// Only accept uploads while this specific application is still
	// somewhere between "documents requested" and a final outcome -
	// not before (just created, nothing requested yet) and not after
	// (Completed/Archived/Cancelled/Rejected). See
	// BIZUPKEEP_DOCUMENT_UPLOAD_STATUSES's docblock for why this is no
	// longer just PendingDocuments now that payment doesn't wait on
	// documents being in first.
	if ( ! in_array( $workflow->getStatus(), BIZUPKEEP_DOCUMENT_UPLOAD_STATUSES, true ) ) {
		return false;
	}

	$file = bizupkeep_child_validate_uploaded_file( 'document' );

	if ( null === $file ) {
		return false;
	}

	try {
		$documents = bizhub()->container()->get( DocumentService::class );

		$documents->uploadDocument(
			'company',
			$workflow->getSubjectUuid(),
			$file['name'],
			$category,
			$file['tmp_name'],
			$file['name'],
			$wp_user_id
		);
	} catch ( \Throwable $e ) {
		return false;
	}

	bizupkeep_child_maybe_verify_documents( $workflow, $wp_user_id );

	return true;
}

/**
 * Read, validate, and return the uploaded file's PHP $_FILES entry, or
 * null if it's missing, failed, too large, or not an allowed type.
 * Deliberately checks is_uploaded_file() as a defense against a
 * crafted tmp_name path, even though PHP's own upload handling makes
 * that hard to spoof - DocumentStorageService::store() uses copy()
 * rather than move_uploaded_file(), which is what would normally
 * enforce this.
 *
 * @return array{name:string,tmp_name:string}|null
 */
function bizupkeep_child_validate_uploaded_file( string $field ): ?array {
	if ( empty( $_FILES[ $field ] ) || ! is_array( $_FILES[ $field ] ) ) {
		return null;
	}

	$file = $_FILES[ $field ];

	if ( ! isset( $file['error'], $file['tmp_name'], $file['name'], $file['size'] ) ) {
		return null;
	}

	if ( UPLOAD_ERR_OK !== $file['error'] ) {
		return null;
	}

	if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
		return null;
	}

	if ( $file['size'] <= 0 || $file['size'] > BIZUPKEEP_MAX_DOCUMENT_UPLOAD_BYTES ) {
		return null;
	}

	$extension = strtolower( (string) pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );

	$allowed_extensions_to_mime = array(
		'pdf'  => 'application/pdf',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
	);

	if ( ! isset( $allowed_extensions_to_mime[ $extension ] ) ) {
		return null;
	}

	// Don't trust the client-supplied $file['type'] header - detect
	// the real MIME type from the file's actual bytes, same as
	// DocumentStorageService::store() does after storing it, just
	// checked here before accepting the upload at all.
	$detected_mime = function_exists( 'mime_content_type' ) ? mime_content_type( $file['tmp_name'] ) : false;

	if ( false === $detected_mime || $detected_mime !== $allowed_extensions_to_mime[ $extension ] ) {
		return null;
	}

	return array(
		'name'     => sanitize_file_name( (string) $file['name'] ),
		'tmp_name' => (string) $file['tmp_name'],
	);
}

/**
 * Look up a company by UUID and confirm it belongs to the given
 * WordPress user's client record. Returns null if the client doesn't
 * exist, the company doesn't exist, or the company belongs to someone
 * else - the caller should treat all three identically (generic
 * failure), never revealing which case it was.
 */
function bizupkeep_child_get_owned_company( int $wp_user_id, string $company_uuid ): ?Company {
	if ( '' === $company_uuid ) {
		return null;
	}

	$clients = bizhub()->container()->get( ClientServiceInterface::class );

	try {
		$client = $clients->getClientByWpUserId( $wp_user_id );
	} catch ( ClientNotFoundException $e ) {
		return null;
	}

	$client_id = $client->getId();

	if ( null === $client_id ) {
		return null;
	}

	$companies = bizhub()->container()->get( CompanyServiceInterface::class );

	try {
		$company = $companies->getCompany( $company_uuid );
	} catch ( \Throwable $e ) {
		return null;
	}

	return $company->getClientId() === $client_id ? $company : null;
}

/**
 * Look up a workflow instance by UUID and confirm it belongs to a
 * company owned by the given WordPress user's client record. Returns
 * null if the workflow doesn't exist, its company doesn't exist, or
 * the company belongs to someone else - the caller should treat all
 * three identically (generic failure), the same ownership-
 * re-verification pattern bizupkeep_child_get_owned_company() already
 * uses.
 *
 * This is the fix for a real bug: every caller of this function used
 * to instead call a "find the company's workflow" helper that ignored
 * workflow type entirely and just took whichever instance was created
 * most recently - fine when Company Registration was the only
 * workflow type a company could have, wrong now that a company can
 * simultaneously have (for example) a completed registration and a
 * brand new amendment. Callers must now say which specific
 * application (workflow UUID) they mean, the same way the URL/form
 * already identifies a specific application everywhere else (Quality
 * Review, the apply form's company pickers).
 */
function bizupkeep_child_get_owned_workflow_instance( int $wp_user_id, string $workflow_uuid ): ?WorkflowInstance {
	if ( '' === $workflow_uuid ) {
		return null;
	}

	$workflows = bizhub()->container()->get( WorkflowRepositoryInterface::class );
	$workflow  = $workflows->find( $workflow_uuid );

	if ( null === $workflow || 'company' !== $workflow->getSubjectType() ) {
		return null;
	}

	$company = bizupkeep_child_get_owned_company( $wp_user_id, $workflow->getSubjectUuid() );

	return null === $company ? null : $workflow;
}

/**
 * Resolve a workflow type string to its own Service class - the only
 * path that may touch the workflow engine for that type. Mirrors
 * BizHub\Workflow\Admin\QualityReviewPage::serviceFor() in the
 * bizupkeep-workflow plugin, which resolves the same
 * WorkflowTypeServiceInterface-implementing classes for the same
 * reason (code that operates on a workflow instance without knowing
 * its concrete type in advance).
 */
function bizupkeep_child_workflow_type_service( string $workflow_type ): WorkflowTypeServiceInterface {
	return match ( $workflow_type ) {
		CompanyAmendmentDefinition::TYPE => bizhub()->container()->get( CompanyAmendmentService::class ),
		AnnualReturnDefinition::TYPE => bizhub()->container()->get( AnnualReturnService::class ),
		default => bizhub()->container()->get( CompanyRegistrationService::class ),
	};
}

/**
 * Human-readable label for a workflow type. Mirrors
 * QualityReviewPage::typeLabel()/WorkflowAdminMenu::typeLabel() in the
 * bizupkeep-workflow plugin.
 */
function bizupkeep_child_workflow_type_label( string $workflow_type ): string {
	return match ( $workflow_type ) {
		CompanyAmendmentDefinition::TYPE => __( 'Company Amendment', 'bizupkeep-astra-child' ),
		AnnualReturnDefinition::TYPE => __( 'Annual Return', 'bizupkeep-astra-child' ),
		default => __( 'Company Registration', 'bizupkeep-astra-child' ),
	};
}

/**
 * If a specific application now has every required document category
 * uploaded (see bizupkeep_child_required_document_categories() - a
 * larger set for Company Amendment than Company Registration), advance
 * it from PendingDocuments to DocumentsVerified (and on to
 * AwaitingPayment, since request_payment has no guard for either
 * Company Registration or Company Amendment - see
 * CompanyRegistrationGuard/CompanyAmendmentGuard, which only have
 * explicit cases for verify_documents/confirm_payment/approve). Safe
 * to call after every upload - re-checks the current status first, so
 * it's a no-op once already verified (or if the required set still
 * isn't complete).
 *
 * Only ever reached for Company Registration/Amendment (the two types
 * with a PendingDocuments stage) - see
 * bizupkeep_child_applications_sections(), which never offers an
 * upload form for an Annual Return application in the first place.
 */
function bizupkeep_child_maybe_verify_documents( WorkflowInstance $workflow, int $wp_user_id ): void {
	if ( WorkflowStatus::PendingDocuments !== $workflow->getStatus() ) {
		return;
	}

	$documents = bizhub()->container()->get( DocumentService::class );
	$uploaded  = $documents->getDocumentsForOwner( 'company', $workflow->getSubjectUuid() );

	$categories = array_map(
		static function ( $document ) {
			return $document->getCategory();
		},
		$uploaded
	);

	foreach ( bizupkeep_child_required_document_categories( $workflow->getWorkflowType() ) as $required ) {
		if ( ! in_array( $required, $categories, true ) ) {
			return;
		}
	}

	try {
		$service = bizupkeep_child_workflow_type_service( $workflow->getWorkflowType() );

		// 'verify_documents'/'request_payment' are shared action-name
		// literals across CompanyRegistrationDefinition and
		// CompanyAmendmentDefinition (both mirror the same 9-action
		// lifecycle shape) - see ROADMAP.md in bizupkeep-workflow for
		// why Company Amendment was modelled that way.
		$service->performAction(
			$workflow->getUuid(),
			'verify_documents',
			$wp_user_id,
			__( 'Required documents uploaded by client.', 'bizupkeep-astra-child' ),
			array( 'documents_verified' => true )
		);

		$service->performAction(
			$workflow->getUuid(),
			'request_payment',
			$wp_user_id,
			__( 'Payment requested automatically after document verification.', 'bizupkeep-astra-child' )
		);
	} catch ( \Throwable $e ) {
		// Leave the workflow wherever it got to - the client can still
		// see their uploaded documents either way, and this can be
		// resolved manually or retried on the next upload.
	}
}

/**
 * Fetch every workflow instance (across all three types) belonging to
 * companies owned by this WordPress user's client record - the shared
 * data source for both My Documents and My Applications, each of
 * which used to instead iterate "one row per company" and silently
 * guess at a company's "current" workflow. Sorted most-recently-
 * updated first.
 *
 * @return array<int,array{instance:WorkflowInstance,company:Company}>
 */
function bizupkeep_child_client_workflow_instances( int $wp_user_id ): array {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return array();
	}

	$clients = bizhub()->container()->get( ClientServiceInterface::class );

	try {
		$client = $clients->getClientByWpUserId( $wp_user_id );
	} catch ( ClientNotFoundException $e ) {
		return array();
	}

	$client_id = $client->getId();

	if ( null === $client_id ) {
		return array();
	}

	$companies = bizhub()->container()->get( CompanyServiceInterface::class );
	$workflows = bizhub()->container()->get( WorkflowRepositoryInterface::class );

	$rows = array();

	foreach ( $companies->getCompaniesForClient( $client_id ) as $company ) {
		foreach ( $workflows->findForSubject( 'company', $company->getUuid() ) as $instance ) {
			$rows[] = array(
				'instance' => $instance,
				'company'  => $company,
			);
		}
	}

	usort(
		$rows,
		static function ( array $a, array $b ): int {
			$aTime = $a['instance']->getUpdatedAt() ?? $a['instance']->getCreatedAt();
			$bTime = $b['instance']->getUpdatedAt() ?? $b['instance']->getCreatedAt();

			return $bTime <=> $aTime;
		}
	);

	return $rows;
}

/**
 * The name and CIPC customer code of the person clients grant power of
 * attorney to - A2Z Business Administrators' registered practitioner.
 * Kept as named constants rather than buried in the POA markup itself,
 * since these are real identifying details that could conceivably
 * change (a new practitioner, a new customer code) without anything
 * else about the POA needing to.
 */
const BIZUPKEEP_POA_ATTORNEY_NAME = 'ANZELLE KIDSON';
const BIZUPKEEP_POA_ATTORNEY_CUSTOMER_CODE = 'A01607';

/**
 * The URL that streams a workflow's generated Power of Attorney - see
 * bizupkeep_child_handle_poa_request(), which intercepts this on
 * template_redirect and never lets the linked-to page actually render.
 */
function bizupkeep_child_poa_url( string $workflow_uuid ): string {
	return add_query_arg( 'bizupkeep_poa', $workflow_uuid, home_url( '/client-portal/client-portal-applications/' ) );
}

add_action( 'template_redirect', 'bizupkeep_child_handle_poa_request' );

/**
 * Stream a Company Registration or Company Amendment application's
 * generated Power of Attorney as a standalone, print-styled HTML
 * document - deliberately not wrapped in get_header()/get_footer(), so
 * printing it doesn't also print the site's nav/footer. Runs on
 * template_redirect (before any page template renders) and exits, the
 * same "intercept early, bypass the theme" approach
 * QualityReviewPage::streamDocument() uses on the admin side for the
 * signed copy the client uploads back.
 */
function bizupkeep_child_handle_poa_request(): void {
	if ( ! isset( $_GET['bizupkeep_poa'] ) ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( home_url( '/client-portal/client-portal-applications/' ) ) );
		exit;
	}

	$workflow_uuid = sanitize_text_field( wp_unslash( $_GET['bizupkeep_poa'] ) );
	$result        = bizupkeep_child_resolve_generated_document_request(
		$workflow_uuid,
		array( CompanyRegistrationDefinition::TYPE, CompanyAmendmentDefinition::TYPE )
	);

	if ( null === $result ) {
		wp_die( esc_html__( 'That document could not be found.', 'bizupkeep-astra-child' ) );
	}

	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );

	echo bizupkeep_child_render_poa_document( $result['workflow'], $result['company'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the function returns a complete, self-escaping HTML document.

	exit;
}

/**
 * Build the full standalone, print-styled HTML Power of Attorney
 * document for a Company Registration or Company Amendment
 * application, pre-populated from the application's own data - the
 * company name (its first proposed name for a still-pending
 * Registration, since the real CIPC name isn't final until approved;
 * its current registered name plus registration number for an
 * Amendment, since that identifies an existing, already-registered
 * company) and the full list of directors the client entered, each
 * with a blank Signature cell for physical, ink signing after
 * printing. The client uploads the signed, scanned copy back under
 * the Signed Power of Attorney document category (see
 * BIZUPKEEP_REQUIRED_DOCUMENT_CATEGORIES).
 */
function bizupkeep_child_render_poa_document( WorkflowInstance $workflow, Company $company ): string {
	$is_amendment = CompanyAmendmentDefinition::TYPE === $workflow->getWorkflowType();

	if ( $is_amendment ) {
		$company_name = $company->getCompanyName();
		$company_identifier = sprintf(
			/* translators: 1: company name, 2: CIPC registration number */
			__( '%1$s (registration number %2$s)', 'bizupkeep-astra-child' ),
			$company_name,
			$company->getRegistrationNumber()
		);
		$action_verb = __( 'Amend', 'bizupkeep-astra-child' );
	} else {
		$metadata       = $workflow->getMetadata();
		$proposed_names = isset( $metadata['proposed_names'] ) && is_array( $metadata['proposed_names'] )
			? $metadata['proposed_names']
			: array();
		$company_identifier = $proposed_names[0] ?? $company->getCompanyName();
		$action_verb         = __( 'Register', 'bizupkeep-astra-child' );
	}

	$removed_director_uuids = $is_amendment ? bizupkeep_child_amendment_removed_director_uuids( $workflow ) : array();
	$directors_rows          = '';

	foreach ( $company->getDirectors() as $index => $director ) {
		$is_resigning = in_array( $director->getUuid(), $removed_director_uuids, true );

		$directors_rows .= sprintf(
			'<tr><td>%1$d</td><td>%2$s</td><td>%3$s %4$s</td><td>%5$s</td><td>%6$s</td><td class="bizupkeep-poa-signature-cell"></td></tr>',
			$index + 1,
			esc_html( $director->getLastName() ),
			esc_html( $director->getFirstName() ),
			esc_html( $director->getLastName() ),
			esc_html( $director->getIdNumber() ?? $director->getPassportNumber() ?? '' ),
			$is_resigning ? '<strong>' . esc_html__( 'Resigning', 'bizupkeep-astra-child' ) . '</strong>' : ''
		);
	}

	if ( '' === $directors_rows ) {
		$directors_rows = '<tr><td colspan="6">' . esc_html__( 'No directors on file.', 'bizupkeep-astra-child' ) . '</td></tr>';
	}

	ob_start();
	?>
<!DOCTYPE html>
<html lang="en-ZA">
<head>
	<meta charset="utf-8">
	<title><?php esc_html_e( 'Limited Power of Attorney', 'bizupkeep-astra-child' ); ?></title>
	<style>
		html { background: #ffffff; color-scheme: light; }
		body { font-family: Georgia, 'Times New Roman', serif; max-width: 800px; margin: 2rem auto; padding: 0 1.5rem; line-height: 1.6; color: #1a1a1a; background: #ffffff; }
		h1 { text-align: center; font-size: 1.4rem; text-transform: uppercase; letter-spacing: 0.05em; }
		table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; }
		th, td { border: 1px solid #333; padding: 0.5rem 0.75rem; text-align: left; font-size: 0.95rem; }
		.bizupkeep-poa-signature-cell { min-width: 120px; }
		.bizupkeep-poa-print-bar { text-align: center; margin-bottom: 2rem; }
		.bizupkeep-poa-print-bar button { font-size: 1rem; padding: 0.5rem 1.25rem; cursor: pointer; }
		.bizupkeep-poa-date-line { margin-top: 3rem; }
		@media print {
			.bizupkeep-poa-print-bar { display: none; }
			body { margin: 0; max-width: none; }
		}
	</style>
</head>
<body>
	<div class="bizupkeep-poa-print-bar">
		<button type="button" onclick="window.print();"><?php esc_html_e( 'Print this document', 'bizupkeep-astra-child' ); ?></button>
	</div>

	<h1><?php esc_html_e( 'Limited Power of Attorney', 'bizupkeep-astra-child' ); ?></h1>

	<p>
		<?php
		printf(
			/* translators: 1: attorney's full name, 2: CIPC customer code, 3: "Register" or "Amend", 4: company name (and registration number for an amendment) */
			esc_html__(
				'I/We the undersigned hereby nominate, constitute and appoint %1$s, with customer code %2$s, with power of substitution, to be my/our lawful representative in my/our name, place and stead to %3$s on my/our behalf a Company with the name %4$s.',
				'bizupkeep-astra-child'
			),
			esc_html( BIZUPKEEP_POA_ATTORNEY_NAME ),
			esc_html( BIZUPKEEP_POA_ATTORNEY_CUSTOMER_CODE ),
			esc_html( $action_verb ),
			esc_html( $company_identifier )
		);
		?>
	</p>

	<table>
		<thead>
			<tr>
				<th><?php esc_html_e( 'Director', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'Surname', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'Full Names', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'ID Number', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'Status', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'Signature', 'bizupkeep-astra-child' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php echo $directors_rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built entirely from esc_html() calls above. ?>
		</tbody>
	</table>

	<p>
		<?php
		esc_html_e(
			'I/We the undersigned members also confirm, with my/our signature(s) hereto, that I/we qualify to be (a) member(s) of the Private Company, and that my/our directorship is not in conflict with the Companies Act of 2008.',
			'bizupkeep-astra-child'
		);
		?>
	</p>

	<p class="bizupkeep-poa-date-line"><?php esc_html_e( 'Date:', 'bizupkeep-astra-child' ); ?> _______________________</p>
</body>
</html>
	<?php
	return (string) ob_get_clean();
}

/**
 * Format a posted "new_address"/"new_company[address]" metadata array
 * (the AddressData::toArray() shape) into the same comma-joined
 * single-line format RegisteredAddress::getFormattedAddress() uses for
 * a company's *current* address, so old and new addresses read
 * consistently side by side in generated documents.
 *
 * @param array<string,mixed> $address
 */
function bizupkeep_child_format_address_array( array $address ): string {
	$parts = array_filter(
		array(
			(string) ( $address['address_line_1'] ?? '' ),
			(string) ( $address['address_line_2'] ?? '' ),
			(string) ( $address['suburb'] ?? '' ),
			(string) ( $address['city'] ?? '' ),
			(string) ( $address['province'] ?? '' ),
			(string) ( $address['postal_code'] ?? '' ),
			'South Africa',
		)
	);

	return implode( ', ', $parts );
}

/**
 * Build a plain-text (not yet escaped - callers esc_html() each line)
 * description of every change a Company Amendment application is
 * making, one line per change, for the Resolution Letter and Minutes
 * of Meeting documents - both need exactly the same content, just
 * wrapped in a different document shell. Name/address changes read
 * "from X to Y"; director changes deliberately list only full name and
 * ID/passport number (nothing else), per how the client asked for
 * these documents to read.
 *
 * A "remove" director_changes entry only carries a name and UUID (see
 * bizupkeep_child_parse_director_changes()) - its ID/passport number
 * is looked up fresh here from the still-current Director record,
 * since the removal hasn't taken effect yet.
 *
 * @return string[]
 */
function bizupkeep_child_amendment_change_lines( WorkflowInstance $workflow, Company $company ): array {
	$metadata         = $workflow->getMetadata();
	$amendment_types  = isset( $metadata['amendment_types'] ) && is_array( $metadata['amendment_types'] ) ? $metadata['amendment_types'] : array();
	$lines            = array();

	if ( in_array( CompanyAmendmentDefinition::AMENDMENT_TYPE_NAME, $amendment_types, true ) ) {
		$proposed_names = isset( $metadata['proposed_names'] ) && is_array( $metadata['proposed_names'] ) ? $metadata['proposed_names'] : array();
		$new_name       = $proposed_names[0] ?? '';

		$lines[] = sprintf(
			/* translators: 1: current company name, 2: proposed new name */
			__( 'The name of the Company be changed from "%1$s" to "%2$s".', 'bizupkeep-astra-child' ),
			$company->getCompanyName(),
			$new_name
		);

		$alternates = array_slice( $proposed_names, 1 );

		if ( array() !== $alternates ) {
			$lines[] = sprintf(
				/* translators: %s: comma-separated list of alternative proposed names */
				__( 'Should the above name not be available, the following alternative(s) are proposed, in order of preference: %s.', 'bizupkeep-astra-child' ),
				implode( ', ', $alternates )
			);
		}
	}

	if ( in_array( CompanyAmendmentDefinition::AMENDMENT_TYPE_ADDRESS, $amendment_types, true ) ) {
		$new_address = isset( $metadata['new_address'] ) && is_array( $metadata['new_address'] ) ? $metadata['new_address'] : array();

		$lines[] = sprintf(
			/* translators: 1: current registered address, 2: new registered address */
			__( 'The registered address of the Company be changed from %1$s to %2$s.', 'bizupkeep-astra-child' ),
			$company->getRegisteredAddress()->getFormattedAddress(),
			bizupkeep_child_format_address_array( $new_address )
		);
	}

	if ( in_array( CompanyAmendmentDefinition::AMENDMENT_TYPE_DIRECTOR, $amendment_types, true ) ) {
		$director_changes = isset( $metadata['director_changes'] ) && is_array( $metadata['director_changes'] ) ? $metadata['director_changes'] : array();
		$appointed        = array();
		$resigning        = array();
		$director_repo    = null;

		foreach ( $director_changes as $change ) {
			if ( ! is_array( $change ) ) {
				continue;
			}

			if ( 'add' === ( $change['action'] ?? '' ) ) {
				$id_number = (string) ( $change['id_number'] ?? $change['passport_number'] ?? '' );
				$full_name = trim( ( $change['first_name'] ?? '' ) . ' ' . ( $change['last_name'] ?? '' ) );
				$appointed[] = '' !== $id_number
					? sprintf( '%1$s (ID: %2$s)', $full_name, $id_number )
					: $full_name;
			} elseif ( 'remove' === ( $change['action'] ?? '' ) ) {
				if ( null === $director_repo && function_exists( 'bizhub' ) && null !== bizhub() ) {
					$director_repo = bizhub()->container()->get( DirectorRepositoryInterface::class );
				}

				$id_number = '';

				if ( null !== $director_repo && isset( $change['uuid'] ) ) {
					$director  = $director_repo->findByUuid( (string) $change['uuid'] );
					$id_number = null !== $director ? (string) ( $director->getIdNumber() ?? $director->getPassportNumber() ?? '' ) : '';
				}

				$full_name   = (string) ( $change['name'] ?? '' );
				$resigning[] = '' !== $id_number
					? sprintf( '%1$s (ID: %2$s)', $full_name, $id_number )
					: $full_name;
			}
		}

		if ( array() !== $appointed ) {
			$lines[] = sprintf(
				/* translators: %s: semicolon-separated list of "Full Name (ID: ...)" */
				__( 'The following director(s) be appointed: %s.', 'bizupkeep-astra-child' ),
				implode( '; ', $appointed )
			);
		}

		if ( array() !== $resigning ) {
			$lines[] = sprintf(
				/* translators: %s: semicolon-separated list of "Full Name (ID: ...)" */
				__( 'The following director(s) resign as director(s) of the Company: %s.', 'bizupkeep-astra-child' ),
				implode( '; ', $resigning )
			);
		}
	}

	return $lines;
}

/**
 * Resolve and ownership-verify a generated-document request's workflow
 * UUID into its WorkflowInstance + owning Company - the shared check
 * bizupkeep_child_handle_poa_request()/bizupkeep_child_handle_resolution_request()/
 * bizupkeep_child_handle_minutes_request() each otherwise repeat.
 * Returns null if the workflow doesn't exist, isn't owned by the
 * current user, isn't one of $allowed_types, or BizHub isn't
 * available - any of which the caller treats as wp_die().
 *
 * @param array<int,string> $allowed_types
 * @return array{workflow:WorkflowInstance,company:Company}|null
 */
function bizupkeep_child_resolve_generated_document_request( string $workflow_uuid, array $allowed_types ): ?array {
	$workflow = bizupkeep_child_get_owned_workflow_instance( get_current_user_id(), $workflow_uuid );

	if ( null === $workflow || ! in_array( $workflow->getWorkflowType(), $allowed_types, true ) ) {
		return null;
	}

	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return null;
	}

	try {
		$company = bizhub()->container()->get( CompanyServiceInterface::class )->getCompany( $workflow->getSubjectUuid() );
	} catch ( \Throwable $e ) {
		return null;
	}

	return array(
		'workflow' => $workflow,
		'company'  => $company,
	);
}

/**
 * The URL that streams a Company Amendment's generated Resolution
 * Letter - see bizupkeep_child_handle_resolution_request().
 */
function bizupkeep_child_resolution_url( string $workflow_uuid ): string {
	return add_query_arg( 'bizupkeep_resolution', $workflow_uuid, home_url( '/client-portal/client-portal-applications/' ) );
}

/**
 * The URL that streams a Company Amendment's generated Minutes of
 * Meeting - see bizupkeep_child_handle_minutes_request().
 */
function bizupkeep_child_minutes_url( string $workflow_uuid ): string {
	return add_query_arg( 'bizupkeep_minutes', $workflow_uuid, home_url( '/client-portal/client-portal-applications/' ) );
}

add_action( 'template_redirect', 'bizupkeep_child_handle_resolution_request' );

/**
 * Stream a Company Amendment's generated Resolution Letter as a
 * standalone, print-styled HTML document - same "intercept early on
 * template_redirect, bypass the theme" approach as
 * bizupkeep_child_handle_poa_request().
 */
function bizupkeep_child_handle_resolution_request(): void {
	if ( ! isset( $_GET['bizupkeep_resolution'] ) ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( home_url( '/client-portal/client-portal-applications/' ) ) );
		exit;
	}

	$workflow_uuid = sanitize_text_field( wp_unslash( $_GET['bizupkeep_resolution'] ) );
	$result        = bizupkeep_child_resolve_generated_document_request( $workflow_uuid, array( CompanyAmendmentDefinition::TYPE ) );

	if ( null === $result ) {
		wp_die( esc_html__( 'That document could not be found.', 'bizupkeep-astra-child' ) );
	}

	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );

	echo bizupkeep_child_render_resolution_document( $result['workflow'], $result['company'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the function returns a complete, self-escaping HTML document.

	exit;
}

add_action( 'template_redirect', 'bizupkeep_child_handle_minutes_request' );

/**
 * Stream a Company Amendment's generated Minutes of Meeting as a
 * standalone, print-styled HTML document - same "intercept early on
 * template_redirect, bypass the theme" approach as
 * bizupkeep_child_handle_poa_request().
 */
function bizupkeep_child_handle_minutes_request(): void {
	if ( ! isset( $_GET['bizupkeep_minutes'] ) ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( home_url( '/client-portal/client-portal-applications/' ) ) );
		exit;
	}

	$workflow_uuid = sanitize_text_field( wp_unslash( $_GET['bizupkeep_minutes'] ) );
	$result        = bizupkeep_child_resolve_generated_document_request( $workflow_uuid, array( CompanyAmendmentDefinition::TYPE ) );

	if ( null === $result ) {
		wp_die( esc_html__( 'That document could not be found.', 'bizupkeep-astra-child' ) );
	}

	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );

	echo bizupkeep_child_render_minutes_document( $result['workflow'], $result['company'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the function returns a complete, self-escaping HTML document.

	exit;
}

/**
 * Build the full standalone, print-styled HTML Resolution Letter for a
 * Company Amendment application - the directors' formal decision to
 * make the specific change(s) applied for, pre-populated with the
 * company's current details and each change (see
 * bizupkeep_child_amendment_change_lines()), with a signature block
 * for every director currently on file (the people who can actually
 * resolve something - not any newly-proposed director from a
 * director-change amendment, who has no standing to sign this yet).
 * The client prints, signs, and uploads it back under the Signed
 * Resolution Letter document category (see
 * BIZUPKEEP_AMENDMENT_ADDITIONAL_REQUIRED_DOCUMENT_CATEGORIES).
 */
function bizupkeep_child_render_resolution_document( WorkflowInstance $workflow, Company $company ): string {
	$change_lines = bizupkeep_child_amendment_change_lines( $workflow, $company );

	$change_items = '';

	foreach ( $change_lines as $line ) {
		$change_items .= '<li>' . esc_html( $line ) . '</li>';
	}

	if ( '' === $change_items ) {
		$change_items = '<li>' . esc_html__( 'No changes on file for this application.', 'bizupkeep-astra-child' ) . '</li>';
	}

	$directors_rows = bizupkeep_child_render_director_signature_rows( $company, bizupkeep_child_amendment_removed_director_uuids( $workflow ) );

	ob_start();
	?>
<!DOCTYPE html>
<html lang="en-ZA">
<head>
	<meta charset="utf-8">
	<title><?php esc_html_e( 'Board Resolution', 'bizupkeep-astra-child' ); ?></title>
	<style>
		html { background: #ffffff; color-scheme: light; }
		body { font-family: Georgia, 'Times New Roman', serif; max-width: 800px; margin: 2rem auto; padding: 0 1.5rem; line-height: 1.6; color: #1a1a1a; background: #ffffff; }
		h1 { text-align: center; font-size: 1.4rem; text-transform: uppercase; letter-spacing: 0.05em; }
		ul.bizupkeep-resolution-items { margin: 1.25rem 0; padding-left: 1.5rem; }
		ul.bizupkeep-resolution-items li { margin-bottom: 0.75rem; }
		table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; }
		th, td { border: 1px solid #333; padding: 0.5rem 0.75rem; text-align: left; font-size: 0.95rem; }
		.bizupkeep-signature-cell { min-width: 120px; }
		.bizupkeep-print-bar { text-align: center; margin-bottom: 2rem; }
		.bizupkeep-print-bar button { font-size: 1rem; padding: 0.5rem 1.25rem; cursor: pointer; }
		.bizupkeep-date-line { margin-top: 3rem; }
		@media print {
			.bizupkeep-print-bar { display: none; }
			body { margin: 0; max-width: none; }
		}
	</style>
</head>
<body>
	<div class="bizupkeep-print-bar">
		<button type="button" onclick="window.print();"><?php esc_html_e( 'Print this document', 'bizupkeep-astra-child' ); ?></button>
	</div>

	<h1><?php esc_html_e( 'Board Resolution', 'bizupkeep-astra-child' ); ?></h1>

	<p>
		<?php
		printf(
			/* translators: 1: company name, 2: CIPC registration number */
			esc_html__(
				'We, the undersigned, being all of the directors of %1$s (registration number %2$s), do hereby resolve as follows:',
				'bizupkeep-astra-child'
			),
			esc_html( $company->getCompanyName() ),
			esc_html( $company->getRegistrationNumber() )
		);
		?>
	</p>

	<ul class="bizupkeep-resolution-items">
		<?php echo $change_items; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built entirely from esc_html() calls above. ?>
	</ul>

	<p>
		<?php
		printf(
			/* translators: %s: the practitioner's full name */
			esc_html__(
				'FURTHER RESOLVED that %s be and is hereby authorised, on behalf of the Company, to sign all documentation and take all steps necessary to give effect to the above with the Companies and Intellectual Property Commission (CIPC).',
				'bizupkeep-astra-child'
			),
			esc_html( BIZUPKEEP_POA_ATTORNEY_NAME )
		);
		?>
	</p>

	<table>
		<thead>
			<tr>
				<th><?php esc_html_e( 'Director', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'Full Names', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'ID Number', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'Status', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'Signature', 'bizupkeep-astra-child' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php echo $directors_rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built entirely from esc_html() calls in bizupkeep_child_render_director_signature_rows(). ?>
		</tbody>
	</table>

	<p class="bizupkeep-date-line"><?php esc_html_e( 'Date:', 'bizupkeep-astra-child' ); ?> _______________________</p>
</body>
</html>
	<?php
	return (string) ob_get_clean();
}

/**
 * Build the full standalone, print-styled HTML Minutes of Meeting for
 * a Company Amendment application - the meeting record backing the
 * Resolution Letter, with the same change list (see
 * bizupkeep_child_amendment_change_lines()) and director signature
 * block. The "Present" list is pre-populated from the company's
 * current directors rather than left blank, since that's already
 * known; the date is left for the client to fill in by hand, since the
 * meeting itself happens after this document is generated and printed.
 */
function bizupkeep_child_render_minutes_document( WorkflowInstance $workflow, Company $company ): string {
	$change_lines = bizupkeep_child_amendment_change_lines( $workflow, $company );

	$change_items = '';

	foreach ( $change_lines as $line ) {
		$change_items .= '<li>' . esc_html( $line ) . '</li>';
	}

	if ( '' === $change_items ) {
		$change_items = '<li>' . esc_html__( 'No changes on file for this application.', 'bizupkeep-astra-child' ) . '</li>';
	}

	$present_names = array_map(
		static function ( $director ) {
			return $director->getFullName();
		},
		$company->getDirectors()
	);

	$present = array() !== $present_names
		? implode( ', ', $present_names )
		: __( 'No directors on file.', 'bizupkeep-astra-child' );

	$directors_rows = bizupkeep_child_render_director_signature_rows( $company, bizupkeep_child_amendment_removed_director_uuids( $workflow ) );

	ob_start();
	?>
<!DOCTYPE html>
<html lang="en-ZA">
<head>
	<meta charset="utf-8">
	<title><?php esc_html_e( 'Minutes of Meeting', 'bizupkeep-astra-child' ); ?></title>
	<style>
		html { background: #ffffff; color-scheme: light; }
		body { font-family: Georgia, 'Times New Roman', serif; max-width: 800px; margin: 2rem auto; padding: 0 1.5rem; line-height: 1.6; color: #1a1a1a; background: #ffffff; }
		h1 { text-align: center; font-size: 1.4rem; text-transform: uppercase; letter-spacing: 0.05em; }
		.bizupkeep-minutes-meta p { margin: 0.35rem 0; }
		ul.bizupkeep-resolution-items { margin: 1.25rem 0; padding-left: 1.5rem; }
		ul.bizupkeep-resolution-items li { margin-bottom: 0.75rem; }
		table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; }
		th, td { border: 1px solid #333; padding: 0.5rem 0.75rem; text-align: left; font-size: 0.95rem; }
		.bizupkeep-signature-cell { min-width: 120px; }
		.bizupkeep-print-bar { text-align: center; margin-bottom: 2rem; }
		.bizupkeep-print-bar button { font-size: 1rem; padding: 0.5rem 1.25rem; cursor: pointer; }
		.bizupkeep-date-line { margin-top: 3rem; }
		@media print {
			.bizupkeep-print-bar { display: none; }
			body { margin: 0; max-width: none; }
		}
	</style>
</head>
<body>
	<div class="bizupkeep-print-bar">
		<button type="button" onclick="window.print();"><?php esc_html_e( 'Print this document', 'bizupkeep-astra-child' ); ?></button>
	</div>

	<h1><?php esc_html_e( 'Minutes of Meeting', 'bizupkeep-astra-child' ); ?></h1>

	<div class="bizupkeep-minutes-meta">
		<p>
			<?php
			printf(
				/* translators: 1: company name, 2: CIPC registration number */
				esc_html__( 'Minutes of a meeting of the directors of %1$s (registration number %2$s).', 'bizupkeep-astra-child' ),
				esc_html( $company->getCompanyName() ),
				esc_html( $company->getRegistrationNumber() )
			);
			?>
		</p>
		<p><strong><?php esc_html_e( 'Date:', 'bizupkeep-astra-child' ); ?></strong> _______________________</p>
		<p><strong><?php esc_html_e( 'Present:', 'bizupkeep-astra-child' ); ?></strong> <?php echo esc_html( $present ); ?></p>
	</div>

	<p><?php esc_html_e( 'The following resolutions were passed:', 'bizupkeep-astra-child' ); ?></p>

	<ul class="bizupkeep-resolution-items">
		<?php echo $change_items; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built entirely from esc_html() calls above. ?>
	</ul>

	<table>
		<thead>
			<tr>
				<th><?php esc_html_e( 'Director', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'Full Names', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'ID Number', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'Status', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'Signature', 'bizupkeep-astra-child' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php echo $directors_rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built entirely from esc_html() calls in bizupkeep_child_render_director_signature_rows(). ?>
		</tbody>
	</table>

	<p class="bizupkeep-date-line"><?php esc_html_e( 'Meeting closed at:', 'bizupkeep-astra-child' ); ?> _______________________</p>
</body>
</html>
	<?php
	return (string) ob_get_clean();
}

/**
 * The UUIDs of every director a Company Amendment's director_changes
 * metadata marks for removal - so bizupkeep_child_render_director_signature_rows()
 * can flag them in the Resolution Letter/Minutes of Meeting signature
 * table, not just in bizupkeep_child_amendment_change_lines()' prose
 * ("The following director(s) resign..."). A resigning director still
 * needs their own row to sign (their resignation, like everyone
 * else's assent to the other changes, needs their signature) - this
 * only affects how that row reads, not whether it appears.
 *
 * @return string[]
 */
function bizupkeep_child_amendment_removed_director_uuids( WorkflowInstance $workflow ): array {
	$metadata         = $workflow->getMetadata();
	$director_changes = isset( $metadata['director_changes'] ) && is_array( $metadata['director_changes'] ) ? $metadata['director_changes'] : array();
	$removed          = array();

	foreach ( $director_changes as $change ) {
		if ( is_array( $change ) && 'remove' === ( $change['action'] ?? '' ) && isset( $change['uuid'] ) ) {
			$removed[] = (string) $change['uuid'];
		}
	}

	return $removed;
}

/**
 * Build the director rows shared by the Resolution Letter and Minutes
 * of Meeting's signature tables - identical shape to the rows
 * bizupkeep_child_render_poa_document() builds inline, factored out
 * since three documents now need it. $removedUuids (see
 * bizupkeep_child_amendment_removed_director_uuids()) marks any
 * matching row "Resigning" in its own Status column, so it's clear at
 * a glance which signatory is the one stepping down.
 *
 * @param string[] $removedUuids
 */
function bizupkeep_child_render_director_signature_rows( Company $company, array $removedUuids = array() ): string {
	$rows = '';

	foreach ( $company->getDirectors() as $index => $director ) {
		$is_resigning = in_array( $director->getUuid(), $removedUuids, true );

		$rows .= sprintf(
			'<tr><td>%1$d</td><td>%2$s %3$s</td><td>%4$s</td><td>%5$s</td><td class="bizupkeep-signature-cell"></td></tr>',
			$index + 1,
			esc_html( $director->getFirstName() ),
			esc_html( $director->getLastName() ),
			esc_html( $director->getIdNumber() ?? $director->getPassportNumber() ?? '' ),
			$is_resigning ? '<strong>' . esc_html__( 'Resigning', 'bizupkeep-astra-child' ) . '</strong>' : ''
		);
	}

	if ( '' === $rows ) {
		$rows = '<tr><td colspan="5">' . esc_html__( 'No directors on file.', 'bizupkeep-astra-child' ) . '</td></tr>';
	}

	return $rows;
}

/**
 * Human-readable label for a WorkflowStatus - mirrors
 * CompanyRegistrationDefinition's status names since the enum itself
 * has no label() method (unlike DocumentCategory/ApplicationStatus,
 * which do).
 *
 * $workflow_type is optional but lets a handful of (type, status)
 * combinations override the generic label with something more
 * accurate for that type - currently only Annual Return's Created,
 * which for every OTHER type means "just submitted, about to move on
 * immediately" but for Annual Return means "submitted, and will sit
 * here until staff check CIPC and send a quote" (see
 * bizupkeep_child_submit_annual_return()).
 */
function bizupkeep_child_workflow_status_label( WorkflowStatus $status, string $workflow_type = '' ): string {
	if ( AnnualReturnDefinition::TYPE === $workflow_type && WorkflowStatus::Created === $status ) {
		return __( 'Awaiting Staff Review', 'bizupkeep-astra-child' );
	}

	return match ( $status ) {
		WorkflowStatus::Created => __( 'Created', 'bizupkeep-astra-child' ),
		WorkflowStatus::PendingDocuments => __( 'Awaiting Your Documents', 'bizupkeep-astra-child' ),
		WorkflowStatus::DocumentsVerified => __( 'Documents Verified', 'bizupkeep-astra-child' ),
		WorkflowStatus::AwaitingPayment => __( 'Awaiting Payment', 'bizupkeep-astra-child' ),
		WorkflowStatus::Processing => __( 'Processing', 'bizupkeep-astra-child' ),
		WorkflowStatus::QualityReview => __( 'In Review', 'bizupkeep-astra-child' ),
		WorkflowStatus::Completed => __( 'Completed', 'bizupkeep-astra-child' ),
		WorkflowStatus::Archived => __( 'Archived', 'bizupkeep-astra-child' ),
		WorkflowStatus::Cancelled => __( 'Cancelled', 'bizupkeep-astra-child' ),
		WorkflowStatus::Rejected => __( 'Rejected', 'bizupkeep-astra-child' ),
		WorkflowStatus::NamesRejected => __( 'Name Not Approved - Please Resubmit', 'bizupkeep-astra-child' ),
	};
}

/**
 * Payment (the AwaitingPayment step, shared by all three workflow
 * types), via WooCommerce.
 *
 * BizHub already has a WooCommerce integration
 * (includes/Integrations/WooCommerce/: ApplicationCreator,
 * OrderListener, ProductMapper, CustomerSynchronizer), but it's a
 * separate, unrelated system: it creates a brand-new Application for
 * any order containing a product mapped via the `_bizhub_application_type`
 * product meta key, with no link to an existing Company or workflow at
 * all. Reusing it here would mean fighting its shape rather than
 * building on it, so this is new, separate wiring - it never touches
 * ApplicationCreator/OrderListener/ProductMapper, and doesn't create
 * any bizhub_applications rows itself.
 *
 * The core problem this solves: nothing ties a WooCommerce order to a
 * *specific* in-progress application. The flow:
 *   1. "Pay Now" on My Applications routes straight to the ONE product
 *      matching that application (bizupkeep_child_registration_payment_url()/
 *      bizupkeep_child_amendment_payment_url()/
 *      bizupkeep_child_annual_return_payment_url() - a fixed real
 *      product for Registration, one of 7 real products for Amendment
 *      depending on its exact amendment_types, a dynamically-priced
 *      product for Annual Return since that's quoted, not fixed),
 *      clearing the cart and adding that one product before sending
 *      the client straight to checkout - never an open category browse
 *      a client could pick anything from, unrelated to what they
 *      actually applied for.
 *   2. Each of those three handlers stores the application's UUID (its
 *      own, not the company's, since a company can have more than one
 *      application in flight) in the WooCommerce session before
 *      redirecting to checkout - not order meta yet, since no order
 *      exists.
 *   3. bizupkeep_child_attach_workflow_to_order() copies the session
 *      value onto the new order as post meta at checkout, re-verifying
 *      ownership again (a session value could otherwise be replayed
 *      across tabs/accounts).
 *   4. When that order's status changes to processing/completed,
 *      bizupkeep_child_handle_order_payment() reads the application
 *      UUID back off the order and confirms payment on it (dispatched
 *      to whichever workflow type it actually is via
 *      bizupkeep_child_workflow_type_service()), using the order ID as
 *      the guard's required context['payment_reference'].
 */

const BIZUPKEEP_PAYMENT_SESSION_KEY = 'bizupkeep_workflow_uuid';

/**
 * An Annual Return's price isn't a fixed WooCommerce product - it's
 * whatever staff quoted after checking CIPC (see AnnualReturnGuard::
 * guardRequestPayment()), which can differ per company/filing. This
 * session key carries that specific amount from
 * bizupkeep_child_handle_annual_return_payment_intent() through to
 * bizupkeep_child_apply_annual_return_quote_price(), the same way
 * BIZUPKEEP_PAYMENT_SESSION_KEY already carries the workflow UUID -
 * cleared in bizupkeep_child_attach_workflow_to_order() once the order
 * exists and no longer needs it.
 */
const BIZUPKEEP_ANNUAL_RETURN_AMOUNT_SESSION_KEY = 'bizupkeep_annual_return_quote_amount';

/**
 * SKU of the hidden, zero-priced "Annual Return Filing Fee" product
 * used purely as a cart line item to collect a quoted amount at
 * checkout - see bizupkeep_child_get_or_create_annual_return_fee_product().
 */
const BIZUPKEEP_ANNUAL_RETURN_FEE_PRODUCT_SKU = 'bizupkeep-annual-return-fee';

/**
 * Carries the company UUID being paid for through the "Bookkeeping
 * Monthly Access" checkout flow - same session->order-meta pattern as
 * BIZUPKEEP_PAYMENT_SESSION_KEY above, just company-scoped instead of
 * workflow-scoped (a bookkeeping subscription isn't tied to any one
 * workflow application). See bizupkeep_child_handle_bookkeeping_subscription_payment_intent()
 * / bizupkeep_child_attach_bookkeeping_subscription_to_order().
 */
const BIZUPKEEP_BOOKKEEPING_SUBSCRIPTION_SESSION_KEY = 'bizupkeep_bookkeeping_subscription_company_uuid';

/**
 * SKU of the "Bookkeeping Monthly Access" product - lazily created at
 * a R0 PLACEHOLDER price (see
 * bizupkeep_child_get_or_create_bookkeeping_subscription_product()).
 * Unlike the Annual Return fee product, nothing overrides this price
 * dynamically - staff must set the real monthly price in WooCommerce
 * Products before this goes live.
 */
const BIZUPKEEP_BOOKKEEPING_SUBSCRIPTION_PRODUCT_SKU = 'bizupkeep-bookkeeping-monthly';

/**
 * Slug of the real, staff-managed "New Company Registration" product -
 * https://bizupkeep.co.za/product/new-company-registration/. Resolved
 * by slug rather than SKU (see bizupkeep_child_get_product_id_by_slug())
 * since the product's URL is the one stable identifier actually known
 * here; nothing requires staff to also set a matching SKU.
 * bizupkeep_child_handle_registration_payment_intent() adds this one
 * product to a cleared cart rather than sending the client to browse a
 * category, matching how bizupkeep_child_resolve_amendment_product_id()
 * routes each Amendment combination to its own real product.
 */
const BIZUPKEEP_REGISTRATION_PRODUCT_SLUG = 'new-company-registration';

/**
 * Slugs of the seven real, staff-managed Company Amendment products -
 * one per non-empty subset of {director, name, address} - keyed by
 * the same sorted, hyphen-joined combination
 * bizupkeep_child_resolve_amendment_product_id() builds from a
 * workflow's amendment_types, so the same combination always maps to
 * the same product regardless of the order the client happened to
 * submit the types in.
 *
 * https://bizupkeep.co.za/product/director-change/
 * https://bizupkeep.co.za/product/name-change/
 * https://bizupkeep.co.za/product/address-change/
 * https://bizupkeep.co.za/product/director-name-change/
 * https://bizupkeep.co.za/product/director-address-change/
 * https://bizupkeep.co.za/product/name-address-change/
 * https://bizupkeep.co.za/product/all-in-one-director-name-address/
 */
const BIZUPKEEP_AMENDMENT_PRODUCT_SLUGS = array(
	'director'              => 'director-change',
	'name'                  => 'name-change',
	'address'               => 'address-change',
	'director-name'         => 'director-name-change',
	'address-director'      => 'director-address-change',
	'address-name'          => 'name-address-change',
	'address-director-name' => 'all-in-one-director-name-address',
);

/**
 * Resolve a WooCommerce product's post ID from its slug (the `product`
 * post type's post_name) - the one identifier this theme actually has
 * for the real, staff-managed products behind Registration/Amendment
 * payment (see BIZUPKEEP_REGISTRATION_PRODUCT_SLUG/
 * BIZUPKEEP_AMENDMENT_PRODUCT_SLUGS), since nothing here requires
 * staff to also set a matching SKU. Returns 0 if no such product
 * exists, the same "nothing to charge for" signal
 * bizupkeep_child_handle_registration_payment_intent()/
 * bizupkeep_child_handle_amendment_payment_intent() already treat as
 * a reason to bail out to the fallback URL rather than proceed.
 */
function bizupkeep_child_get_product_id_by_slug( string $slug ): int {
	$products = get_posts(
		array(
			'post_type'   => 'product',
			'name'        => $slug,
			'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
			'numberposts' => 1,
			'fields'      => 'ids',
		)
	);

	return empty( $products ) ? 0 : (int) $products[0];
}

/**
 * Resolve the ONE real WooCommerce product matching a Company
 * Amendment's exact amendment_types, via
 * BIZUPKEEP_AMENDMENT_PRODUCT_SLUGS - so a bundled director+name+address
 * change can never be paid for at a single-change-type price (or vice
 * versa). Unrecognised type strings are dropped rather than trusted
 * verbatim; returns 0 if the (cleaned) type set is empty or doesn't
 * match one of the seven known combinations.
 *
 * @param array<int,string> $amendmentTypes
 */
function bizupkeep_child_resolve_amendment_product_id( array $amendmentTypes ): int {
	$types = array_values( array_intersect( $amendmentTypes, CompanyAmendmentDefinition::ALL_AMENDMENT_TYPES ) );

	if ( array() === $types ) {
		return 0;
	}

	sort( $types );
	$key = implode( '-', $types );

	if ( ! isset( BIZUPKEEP_AMENDMENT_PRODUCT_SLUGS[ $key ] ) ) {
		return 0;
	}

	return bizupkeep_child_get_product_id_by_slug( BIZUPKEEP_AMENDMENT_PRODUCT_SLUGS[ $key ] );
}

/**
 * Resolve a Company Amendment application's exact amendment_types to a
 * Service catalog service_key, mirroring
 * bizupkeep_child_resolve_amendment_product_id()'s sort logic exactly
 * (same sorted type set) but joined with "_" and prefixed with
 * "amendment_" to match the catalog's naming convention instead of a
 * WooCommerce product slug.
 *
 * @param array<int,string> $amendmentTypes
 */
function bizupkeep_child_resolve_amendment_service_key( array $amendmentTypes ): ?string {
	$types = array_values( array_intersect( $amendmentTypes, CompanyAmendmentDefinition::ALL_AMENDMENT_TYPES ) );

	if ( array() === $types ) {
		return null;
	}

	sort( $types );

	return 'amendment_' . implode( '_', $types );
}

/**
 * Post A2Z's own revenue for a confirmed WooCommerce order into its
 * Internal Books company (see InternalBooksPage), using the Service
 * catalog (round 2 of the WooCommerce service-catalog plan) to decide
 * VAT treatment - deliberately best-effort: called from inside its
 * own try/catch by every caller, since a failure here must never be
 * allowed to affect the actual payment/subscription confirmation that
 * already succeeded by the time this runs.
 */
function bizupkeep_child_post_a2z_revenue( string $service_key, WC_Order $order ): void {
	$internal_company_uuid = get_option( 'bizupkeep_bookkeeping_internal_company_uuid' );

	if ( ! is_string( $internal_company_uuid ) || '' === $internal_company_uuid ) {
		return;
	}

	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return;
	}

	$container = bizhub()->container();

	$service = $container->get( ServiceRepositoryInterface::class )->findByKey( $service_key );

	if ( null === $service ) {
		return;
	}

	$settings     = $container->get( CompanySettingsRepositoryInterface::class )->findByCompanyUuid( $internal_company_uuid );
	$includes_vat = ServiceVatTreatment::Inclusive === $service->vatTreatment
		&& null !== $settings
		&& $settings->isVatRegistered;

	$account = $container->get( BookkeepingAccountServiceInterface::class )->getByCode(
		$internal_company_uuid,
		BookkeepingChartOfAccountsTemplate::CODE_SALES_REVENUE
	);

	$data = new CaptureTransactionData(
		date: new \DateTimeImmutable(),
		amount: BookkeepingMoney::fromRands( (float) $order->get_total() ),
		categoryAccountUuid: $account->uuid,
		paymentMethod: BookkeepingPaymentMethod::Bank,
		description: sprintf( '%s - Order #%d', $service->name, $order->get_id() ),
		includesVat: $includes_vat
	);

	$container->get( TransactionCaptureServiceInterface::class )->captureIncome(
		$internal_company_uuid,
		$data,
		(int) $order->get_customer_id()
	);
}

if ( class_exists( 'WooCommerce' ) ) {
	add_action( 'template_redirect', 'bizupkeep_child_handle_registration_payment_intent' );
	add_action( 'template_redirect', 'bizupkeep_child_handle_annual_return_payment_intent' );
	add_action( 'template_redirect', 'bizupkeep_child_handle_amendment_payment_intent' );
	add_action( 'woocommerce_checkout_create_order', 'bizupkeep_child_attach_workflow_to_order', 10, 2 );
	add_action( 'woocommerce_order_status_changed', 'bizupkeep_child_handle_order_payment', 10, 4 );
	add_action( 'woocommerce_before_calculate_totals', 'bizupkeep_child_apply_annual_return_quote_price' );

	add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_subscription_payment_intent' );
	add_action( 'woocommerce_checkout_create_order', 'bizupkeep_child_attach_bookkeeping_subscription_to_order', 10, 2 );
	add_action( 'woocommerce_order_status_changed', 'bizupkeep_child_handle_bookkeeping_subscription_order_payment', 10, 4 );
}

/**
 * Build the "Pay Now" URL for a Company Registration application
 * specifically - routes straight to
 * bizupkeep_child_handle_registration_payment_intent(), which adds the
 * fixed New Company Registration product to a cleared cart and
 * redirects straight to checkout. Mirrors
 * bizupkeep_child_amendment_payment_url()'s pattern; unlike Amendment
 * there's only ever the one product, since Registration has no
 * sub-types to combine.
 */
function bizupkeep_child_registration_payment_url( string $workflow_uuid ): string {
	return add_query_arg(
		'bizupkeep_pay_registration',
		$workflow_uuid,
		home_url( '/client-portal/client-portal-applications/' )
	);
}

/**
 * Handle ?bizupkeep_pay_registration={workflow_uuid}: verify the
 * application belongs to the logged-in client and is actually a
 * Company Registration sitting in AwaitingPayment, then add the fixed
 * New Company Registration product
 * (BIZUPKEEP_REGISTRATION_PRODUCT_SLUG) to a cleared cart and send the
 * client straight to checkout - the same "clear the cart first"
 * precaution bizupkeep_child_handle_amendment_payment_intent() takes,
 * so a Registration payment is never accidentally combined with an
 * unrelated item.
 */
function bizupkeep_child_handle_registration_payment_intent(): void {
	if ( ! isset( $_GET['bizupkeep_pay_registration'] ) || ! is_user_logged_in() ) {
		return;
	}

	if ( null === WC()->cart || null === WC()->session ) {
		return;
	}

	$fallback_url  = home_url( '/client-portal/client-portal-applications/' );
	$workflow_uuid = sanitize_text_field( wp_unslash( $_GET['bizupkeep_pay_registration'] ) );
	$workflow      = bizupkeep_child_get_owned_workflow_instance( get_current_user_id(), $workflow_uuid );

	if ( null === $workflow
		|| CompanyRegistrationDefinition::TYPE !== $workflow->getWorkflowType()
		|| WorkflowStatus::AwaitingPayment !== $workflow->getStatus()
	) {
		wp_safe_redirect( $fallback_url );
		exit;
	}

	$product_id = bizupkeep_child_get_product_id_by_slug( BIZUPKEEP_REGISTRATION_PRODUCT_SLUG );

	if ( 0 === $product_id ) {
		wp_safe_redirect( $fallback_url );
		exit;
	}

	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( $product_id, 1 );
	WC()->session->set( BIZUPKEEP_PAYMENT_SESSION_KEY, $workflow_uuid );

	wp_safe_redirect( wc_get_checkout_url() );
	exit;
}

/**
 * Build the "Pay Now" URL for an Annual Return application
 * specifically - unlike bizupkeep_child_registration_payment_url()/
 * bizupkeep_child_amendment_payment_url() (fixed real WooCommerce
 * products), this doesn't send the client anywhere to pick a product:
 * it goes straight to bizupkeep_child_handle_annual_return_payment_intent(),
 * which adds the hidden fee product to a cleared cart at the exact
 * quoted amount and redirects straight to checkout.
 */
function bizupkeep_child_annual_return_payment_url( string $workflow_uuid ): string {
	return add_query_arg(
		'bizupkeep_pay_annual_return',
		$workflow_uuid,
		home_url( '/client-portal/client-portal-applications/' )
	);
}

/**
 * Handle ?bizupkeep_pay_annual_return={workflow_uuid}: verify the
 * application belongs to the logged-in client, is actually an Annual
 * Return sitting in AwaitingPayment, and has a positive quote_amount
 * on file (all three should already be true by the time a "Pay Now"
 * link exists at all, but this is the actual trust boundary, not the
 * link) - then clear the cart, add the hidden fee product, stash both
 * the workflow UUID and the quoted amount in the WooCommerce session,
 * and send the client straight to checkout. The cart is deliberately
 * emptied first so an Annual Return payment is never accidentally
 * combined with an unrelated Company Registration package sitting in
 * the same cart.
 */
function bizupkeep_child_handle_annual_return_payment_intent(): void {
	if ( ! isset( $_GET['bizupkeep_pay_annual_return'] ) || ! is_user_logged_in() ) {
		return;
	}

	if ( null === WC()->cart || null === WC()->session ) {
		return;
	}

	$fallback_url  = home_url( '/client-portal/client-portal-applications/' );
	$workflow_uuid = sanitize_text_field( wp_unslash( $_GET['bizupkeep_pay_annual_return'] ) );
	$workflow      = bizupkeep_child_get_owned_workflow_instance( get_current_user_id(), $workflow_uuid );

	if ( null === $workflow
		|| AnnualReturnDefinition::TYPE !== $workflow->getWorkflowType()
		|| WorkflowStatus::AwaitingPayment !== $workflow->getStatus()
	) {
		wp_safe_redirect( $fallback_url );
		exit;
	}

	$quote_amount = $workflow->getMetadata()['quote_amount'] ?? null;

	if ( ! is_numeric( $quote_amount ) || (float) $quote_amount <= 0 ) {
		wp_safe_redirect( $fallback_url );
		exit;
	}

	$product_id = bizupkeep_child_get_or_create_annual_return_fee_product();

	if ( 0 === $product_id ) {
		wp_safe_redirect( $fallback_url );
		exit;
	}

	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( $product_id, 1 );
	WC()->session->set( BIZUPKEEP_PAYMENT_SESSION_KEY, $workflow_uuid );
	WC()->session->set( BIZUPKEEP_ANNUAL_RETURN_AMOUNT_SESSION_KEY, (float) $quote_amount );

	wp_safe_redirect( wc_get_checkout_url() );
	exit;
}

/**
 * Override the hidden fee product's cart-item price to whatever
 * amount was stashed in session when the client clicked "Pay Now" -
 * the standard WooCommerce pattern for a variable/custom-amount
 * product (the product's own listed price is just a 0 placeholder,
 * since it's never meant to be added to a cart any way other than via
 * bizupkeep_child_handle_annual_return_payment_intent()).
 */
function bizupkeep_child_apply_annual_return_quote_price( WC_Cart $cart ): void {
	if ( null === WC()->session ) {
		return;
	}

	$amount = WC()->session->get( BIZUPKEEP_ANNUAL_RETURN_AMOUNT_SESSION_KEY );

	if ( ! is_numeric( $amount ) || (float) $amount <= 0 ) {
		return;
	}

	$product_id = wc_get_product_id_by_sku( BIZUPKEEP_ANNUAL_RETURN_FEE_PRODUCT_SKU );

	if ( ! $product_id ) {
		return;
	}

	foreach ( $cart->get_cart() as $cart_item ) {
		if ( (int) $cart_item['product_id'] === (int) $product_id ) {
			$cart_item['data']->set_price( (float) $amount );
		}
	}
}

/**
 * Idempotently find (or create) the hidden "Annual Return Filing Fee"
 * product used to collect a staff-quoted amount at checkout - hidden
 * from the shop/search catalog since it's never meant to be browsed,
 * only added to cart programmatically.
 */
function bizupkeep_child_get_or_create_annual_return_fee_product(): int {
	$existing = wc_get_product_id_by_sku( BIZUPKEEP_ANNUAL_RETURN_FEE_PRODUCT_SKU );

	if ( $existing ) {
		return (int) $existing;
	}

	if ( ! class_exists( 'WC_Product_Simple' ) ) {
		return 0;
	}

	$product = new WC_Product_Simple();
	$product->set_name( __( 'Annual Return Filing Fee', 'bizupkeep-astra-child' ) );
	$product->set_sku( BIZUPKEEP_ANNUAL_RETURN_FEE_PRODUCT_SKU );
	$product->set_regular_price( '0' );
	$product->set_price( '0' );
	$product->set_virtual( true );
	$product->set_catalog_visibility( 'hidden' );
	$product->set_status( 'publish' );

	return (int) $product->save();
}


/**
 * Build the "Pay Now" URL for a Company Amendment application
 * specifically - unlike bizupkeep_child_registration_payment_url()
 * (always the one fixed Registration product, since Registration has
 * no sub-types to combine), this routes straight to
 * bizupkeep_child_handle_amendment_payment_intent(), which adds the ONE
 * product matching this application's exact amendment_types to a
 * cleared cart and redirects straight to checkout.
 */
function bizupkeep_child_amendment_payment_url( string $workflow_uuid ): string {
	return add_query_arg(
		'bizupkeep_pay_amendment',
		$workflow_uuid,
		home_url( '/client-portal/client-portal-applications/' )
	);
}

/**
 * Handle ?bizupkeep_pay_amendment={workflow_uuid}: verify the
 * application belongs to the logged-in client and is actually a
 * Company Amendment sitting in AwaitingPayment (both should already be
 * true by the time a "Pay Now" link exists at all, but this is the
 * actual trust boundary, not the link) - then resolve the ONE real
 * product matching its amendment_types, clear the cart, add that
 * product, and send the client straight to checkout. The cart is
 * deliberately emptied first so an Amendment payment is never
 * accidentally combined with an unrelated item.
 */
function bizupkeep_child_handle_amendment_payment_intent(): void {
	if ( ! isset( $_GET['bizupkeep_pay_amendment'] ) || ! is_user_logged_in() ) {
		return;
	}

	if ( null === WC()->cart || null === WC()->session ) {
		return;
	}

	$fallback_url  = home_url( '/client-portal/client-portal-applications/' );
	$workflow_uuid = sanitize_text_field( wp_unslash( $_GET['bizupkeep_pay_amendment'] ) );
	$workflow      = bizupkeep_child_get_owned_workflow_instance( get_current_user_id(), $workflow_uuid );

	if ( null === $workflow
		|| CompanyAmendmentDefinition::TYPE !== $workflow->getWorkflowType()
		|| WorkflowStatus::AwaitingPayment !== $workflow->getStatus()
	) {
		wp_safe_redirect( $fallback_url );
		exit;
	}

	$amendment_types = $workflow->getMetadata()['amendment_types'] ?? array();
	$product_id      = is_array( $amendment_types )
		? bizupkeep_child_resolve_amendment_product_id( $amendment_types )
		: 0;

	if ( 0 === $product_id ) {
		wp_safe_redirect( $fallback_url );
		exit;
	}

	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( $product_id, 1 );
	WC()->session->set( BIZUPKEEP_PAYMENT_SESSION_KEY, $workflow_uuid );

	wp_safe_redirect( wc_get_checkout_url() );
	exit;
}

/**
 * At checkout, copy the pending application's workflow UUID from the
 * WooCommerce session onto the new order as post meta, so it survives
 * past the session into something permanently queryable against the
 * order. Re-verifies ownership again here rather than trusting the
 * session value blindly - it could otherwise be replayed by switching
 * accounts in another tab before completing checkout.
 */
function bizupkeep_child_attach_workflow_to_order( WC_Order $order, array $data ): void {
	if ( null === WC()->session ) {
		return;
	}

	$workflow_uuid = WC()->session->get( BIZUPKEEP_PAYMENT_SESSION_KEY );

	if ( ! is_string( $workflow_uuid ) || '' === $workflow_uuid ) {
		return;
	}

	WC()->session->set( BIZUPKEEP_PAYMENT_SESSION_KEY, null );
	WC()->session->set( BIZUPKEEP_ANNUAL_RETURN_AMOUNT_SESSION_KEY, null );

	$wp_user_id = get_current_user_id();

	if ( 0 === $wp_user_id || null === bizupkeep_child_get_owned_workflow_instance( $wp_user_id, $workflow_uuid ) ) {
		return;
	}

	$order->update_meta_data( '_bizupkeep_workflow_uuid', $workflow_uuid );
}

/**
 * When an order's status changes to processing or completed, confirm
 * payment on the application it was attached to (if any) - moving
 * AwaitingPayment to Processing, dispatched to whichever workflow type
 * that application actually is. Guarded so this only ever fires once
 * per order (mirrors the idempotency pattern
 * Integrations/WooCommerce/OrderListener.php already uses for its own,
 * unrelated purpose) and only while the application is genuinely still
 * waiting on payment.
 */
function bizupkeep_child_handle_order_payment( int $order_id, string $old_status, string $new_status, WC_Order $order ): void {
	if ( ! in_array( $new_status, array( 'processing', 'completed' ), true ) ) {
		return;
	}

	if ( '1' === $order->get_meta( '_bizupkeep_payment_confirmed' ) ) {
		return;
	}

	$workflow_uuid = $order->get_meta( '_bizupkeep_workflow_uuid' );

	if ( ! is_string( $workflow_uuid ) || '' === $workflow_uuid ) {
		return;
	}

	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return;
	}

	$workflows = bizhub()->container()->get( WorkflowRepositoryInterface::class );
	$instance  = $workflows->find( $workflow_uuid );

	if ( null === $instance || WorkflowStatus::AwaitingPayment !== $instance->getStatus() ) {
		return;
	}

	try {
		$service = bizupkeep_child_workflow_type_service( $instance->getWorkflowType() );

		// 'confirm_payment' is a shared action-name literal across all
		// three workflow type Definitions.
		$service->performAction(
			$instance->getUuid(),
			'confirm_payment',
			(int) $order->get_customer_id(),
			sprintf(
				/* translators: %d: WooCommerce order ID. */
				__( 'Payment confirmed via order #%d.', 'bizupkeep-astra-child' ),
				$order_id
			),
			array( 'payment_reference' => (string) $order_id )
		);

		$order->update_meta_data( '_bizupkeep_payment_confirmed', '1' );

		// Best-effort A2Z revenue posting - deliberately its own inner
		// try/catch, separate from the outer one: a failure here must
		// never be mistaken for confirm_payment() itself having failed,
		// which would incorrectly leave the workflow at AwaitingPayment
		// when the customer's payment actually succeeded.
		try {
			$service_key = match ( $instance->getWorkflowType() ) {
				CompanyRegistrationDefinition::TYPE => 'registration',
				AnnualReturnDefinition::TYPE => 'annual_return_fee',
				CompanyAmendmentDefinition::TYPE => bizupkeep_child_resolve_amendment_service_key(
					$instance->getMetadata()['amendment_types'] ?? array()
				),
				default => null,
			};

			if ( null !== $service_key ) {
				bizupkeep_child_post_a2z_revenue( $service_key, $order );
				$order->update_meta_data( '_bizupkeep_a2z_revenue_posted', '1' );
			}
		} catch ( \Throwable $e ) {
			// Best-effort only - staff can capture the missed revenue
			// manually via Internal Books.
		}

		$order->save();
	} catch ( \Throwable $e ) {
		// Leave the workflow at AwaitingPayment - resolvable manually,
		// or automatically on the order's next status change.
	}
}

/**
 * List the financial year(s) an Annual Return workflow's metadata
 * covers, tolerating the old single-`financial_year` shape (from
 * before one application could cover multiple years) as well as the
 * current `filings` list - mirrors
 * AnnualReturnService::filingsFromMetadata()/QualityReviewPage::filingsFromMetadata()
 * in bizupkeep-workflow, kept separate since this is purely a
 * client-facing display concern.
 *
 * @param array<string,mixed> $metadata
 * @return array<int,int>
 */
function bizupkeep_child_filing_years( array $metadata ): array {
	if ( isset( $metadata['filings'] ) && is_array( $metadata['filings'] ) ) {
		return array_map(
			static function ( $filing ) {
				return (int) ( is_array( $filing ) ? ( $filing['financial_year'] ?? 0 ) : 0 );
			},
			$metadata['filings']
		);
	}

	if ( isset( $metadata['financial_year'] ) ) {
		return array( (int) $metadata['financial_year'] );
	}

	return array();
}

/**
 * Build the data the My Applications template needs: one row per
 * application (workflow instance) belonging to the logged-in user's
 * client record, across all three workflow types, with its own status
 * and (only while that specific application is AwaitingPayment) a
 * "Pay Now" link, or (only while it's a Company Registration sitting
 * in NamesRejected) the staff reviewer's rejection notes and a flag
 * telling the template to show the "resubmit names" form, or (only
 * while it's an Annual Return that's actually been quoted) the quoted
 * amount/notes to display alongside its own "Pay Now" link.
 *
 * An Annual Return's "Pay Now" link is NOT a fixed WooCommerce product
 * the way Registration's/Amendment's are - it routes to
 * bizupkeep_child_annual_return_payment_url() instead, which charges
 * the exact staff-quoted amount rather than a fixed product price.
 *
 * Also carries each application's document data (already-uploaded
 * documents, whether it's currently accepting an upload, and its POA
 * download link where relevant) so this one page can cover what used
 * to be split across My Applications and My Documents - see
 * bizupkeep_child_process_document_upload() for where an upload
 * submitted from this page's form actually gets handled. Annual
 * Return applications get an empty documents list and can_upload =
 * false: that workflow type has no PendingDocuments stage or document
 * requirement at all (see AnnualReturnDefinition).
 *
 * @return array<int,array{workflow_uuid:string,workflow_type:string,workflow_type_label:string,company_name:string,status_label:string,pay_url:?string,needs_resubmission:bool,rejection_reason:string,quote_amount:?float,quote_notes:string,filing_years:string,can_upload:bool,documents:array<int,array{category_label:string,name:string,uploaded_at:string}>,poa_url:?string,resolution_url:?string,minutes_url:?string}>
 */
function bizupkeep_child_applications_sections( int $wp_user_id ): array {
	$sections  = array();
	$documents = ( function_exists( 'bizhub' ) && null !== bizhub() ) ? bizhub()->container()->get( DocumentService::class ) : null;

	foreach ( bizupkeep_child_client_workflow_instances( $wp_user_id ) as $row ) {
		$instance = $row['instance'];
		$is_annual_return = AnnualReturnDefinition::TYPE === $instance->getWorkflowType();
		$is_amendment      = CompanyAmendmentDefinition::TYPE === $instance->getWorkflowType();

		$uploaded_documents = array();
		$can_upload         = false;
		$poa_url             = null;
		$resolution_url       = null;
		$minutes_url          = null;

		if ( ! $is_annual_return && null !== $documents ) {
			$uploaded_documents = array_map(
				static function ( $document ) {
					$version = $document->getCurrentVersion();

					return array(
						'category_label' => $document->getCategory()->label(),
						'name'            => $document->getName(),
						'uploaded_at'     => $version ? $version->uploadedAt->format( 'j M Y' ) : '',
					);
				},
				$documents->getDocumentsForOwner( 'company', $row['company']->getUuid() )
			);

			$can_upload = in_array( $instance->getStatus(), BIZUPKEEP_DOCUMENT_UPLOAD_STATUSES, true );

			if ( in_array( $instance->getWorkflowType(), array( CompanyRegistrationDefinition::TYPE, CompanyAmendmentDefinition::TYPE ), true ) ) {
				$poa_url = bizupkeep_child_poa_url( $instance->getUuid() );
			}

			if ( $is_amendment ) {
				$resolution_url = bizupkeep_child_resolution_url( $instance->getUuid() );
				$minutes_url    = bizupkeep_child_minutes_url( $instance->getUuid() );
			}
		}

		$needs_resubmission = in_array( $instance->getWorkflowType(), array( CompanyRegistrationDefinition::TYPE, CompanyAmendmentDefinition::TYPE ), true )
			&& WorkflowStatus::NamesRejected === $instance->getStatus();

		$pay_url = null;

		if ( WorkflowStatus::AwaitingPayment === $instance->getStatus() && class_exists( 'WooCommerce' ) ) {
			$pay_url = match ( $instance->getWorkflowType() ) {
				AnnualReturnDefinition::TYPE => bizupkeep_child_annual_return_payment_url( $instance->getUuid() ),
				CompanyAmendmentDefinition::TYPE => bizupkeep_child_amendment_payment_url( $instance->getUuid() ),
				default => bizupkeep_child_registration_payment_url( $instance->getUuid() ),
			};
		}

		$metadata     = $instance->getMetadata();
		$quote_amount = null;
		$quote_notes  = '';
		$filing_years = '';

		if ( $is_annual_return && isset( $metadata['quote_amount'] ) && is_numeric( $metadata['quote_amount'] ) && (float) $metadata['quote_amount'] > 0 ) {
			$quote_amount = (float) $metadata['quote_amount'];
			$quote_notes  = is_string( $metadata['quote_notes'] ?? null ) ? $metadata['quote_notes'] : '';
		}

		if ( $is_annual_return ) {
			$filing_years = implode( ', ', bizupkeep_child_filing_years( $metadata ) );
		}

		$sections[] = array(
			'workflow_uuid'        => $instance->getUuid(),
			'workflow_type'        => $instance->getWorkflowType(),
			'workflow_type_label' => bizupkeep_child_workflow_type_label( $instance->getWorkflowType() ),
			'company_name'         => $row['company']->getCompanyName(),
			'status_label'         => bizupkeep_child_workflow_status_label( $instance->getStatus(), $instance->getWorkflowType() ),
			'pay_url'              => $pay_url,
			'needs_resubmission'   => $needs_resubmission,
			'rejection_reason'     => $needs_resubmission ? bizupkeep_child_latest_rejection_reason( $instance ) : '',
			'quote_amount'         => $quote_amount,
			'quote_notes'          => $quote_notes,
			'filing_years'         => $filing_years,
			'can_upload'           => $can_upload,
			'documents'            => $uploaded_documents,
			'poa_url'              => $poa_url,
			'resolution_url'       => $resolution_url,
			'minutes_url'          => $minutes_url,
		);
	}

	return $sections;
}

/**
 * The staff reviewer's notes from the most recent time this workflow
 * was sent to NamesRejected (see QualityReviewPage::ACTION_REJECT_NAME
 * in bizupkeep-workflow), so the client can see *why* their proposed
 * names weren't approved before submitting new ones. Empty if the
 * workflow has never been through that action, or its history can't
 * be read for any reason.
 */
function bizupkeep_child_latest_rejection_reason( WorkflowInstance $instance ): string {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return '';
	}

	try {
		$history = bizupkeep_child_workflow_type_service( $instance->getWorkflowType() )
			->historyFor( $instance->getUuid() );
	} catch ( \Throwable $e ) {
		return '';
	}

	$reject_name_actions = array(
		CompanyRegistrationDefinition::ACTION_REJECT_NAME,
		CompanyAmendmentDefinition::ACTION_REJECT_NAME,
	);

	for ( $i = count( $history ) - 1; $i >= 0; $i-- ) {
		if ( in_array( $history[ $i ]->action, $reject_name_actions, true ) ) {
			return $history[ $i ]->reason;
		}
	}

	return '';
}

add_action( 'template_redirect', 'bizupkeep_child_handle_resubmit_names' );

/**
 * Handle the My Applications page's "resubmit names" form POST - the
 * client-facing other half of QualityReviewPage's "Reject - Name Not
 * Approved" action in bizupkeep-workflow. Runs on template_redirect
 * (before the page template renders) so it can redirect on
 * success/failure.
 */
function bizupkeep_child_handle_resubmit_names(): void {
	if ( ! isset( $_POST['bizupkeep_resubmit_nonce'] ) ) {
		return;
	}

	if ( ! is_page() || bizupkeep_child_find_page( 'client-portal-applications', bizupkeep_child_find_page( 'client-portal', 0 ) ) !== get_queried_object_id() ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_resubmit_names', 'bizupkeep_resubmit_nonce' );

	$wp_user_id    = get_current_user_id();
	$workflow_uuid = isset( $_POST['resubmit_workflow_uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['resubmit_workflow_uuid'] ) ) : '';

	$result = bizupkeep_child_process_resubmit_names( $wp_user_id, $workflow_uuid );

	wp_safe_redirect( add_query_arg( $result ? 'names_resubmitted' : 'resubmit_error', '1', get_permalink() ) );
	exit;
}

/**
 * Re-verify ownership and status, then resubmit new proposed names on
 * a Company Registration or Company Amendment (name-change) workflow
 * currently sitting in NamesRejected - moving it back to QualityReview
 * for another look. Returns false (rather than throwing) on any
 * failure, since this runs from a public-facing form submission.
 */
function bizupkeep_child_process_resubmit_names( int $wp_user_id, string $workflow_uuid ): bool {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return false;
	}

	$workflow = bizupkeep_child_get_owned_workflow_instance( $wp_user_id, $workflow_uuid );

	$resubmittable_types = array( CompanyRegistrationDefinition::TYPE, CompanyAmendmentDefinition::TYPE );

	if ( null === $workflow
		|| ! in_array( $workflow->getWorkflowType(), $resubmittable_types, true )
		|| WorkflowStatus::NamesRejected !== $workflow->getStatus()
	) {
		return false;
	}

	$proposed_names = isset( $_POST['resubmit_proposed_name'] ) && is_array( $_POST['resubmit_proposed_name'] )
		? bizupkeep_child_parse_proposed_names( $_POST['resubmit_proposed_name'] )
		: array();

	if ( array() === $proposed_names ) {
		return false;
	}

	$resubmit_action = CompanyAmendmentDefinition::TYPE === $workflow->getWorkflowType()
		? CompanyAmendmentDefinition::ACTION_RESUBMIT_NAMES
		: CompanyRegistrationDefinition::ACTION_RESUBMIT_NAMES;

	try {
		bizupkeep_child_workflow_type_service( $workflow->getWorkflowType() )->performAction(
			$workflow_uuid,
			$resubmit_action,
			$wp_user_id,
			__( 'Client resubmitted new proposed company names.', 'bizupkeep-astra-child' ),
			array( 'proposed_names' => $proposed_names )
		);
	} catch ( \Throwable $e ) {
		return false;
	}

	return true;
}

/**
 * Build the data the My Profile template needs: the logged-in user's
 * editable BizHub profile fields (first/last name, phone - see
 * BizHub\ClientPortal\Entities\Profile) alongside their WordPress
 * account's email and username, which this page shows read-only
 * rather than lets the client edit here - email changes and password
 * changes go through WordPress' own account tools
 * (bizupkeep_child_password_change_url()), not this form. Returns
 * null if the client record can't be resolved (shouldn't normally
 * happen - bizupkeep_child_guard_client_portal() provisions one for
 * every logged-in visitor to any client-portal-* page before this
 * ever runs).
 *
 * @return array{first_name:string,last_name:string,phone:string,email:string,username:string}|null
 */
function bizupkeep_child_profile_data( int $wp_user_id ): ?array {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return null;
	}

	try {
		$client = bizhub()->container()->get( ClientServiceInterface::class )->getClientByWpUserId( $wp_user_id );
	} catch ( ClientNotFoundException $e ) {
		return null;
	}

	$wp_user = get_userdata( $wp_user_id );

	if ( false === $wp_user ) {
		return null;
	}

	$profile = $client->getProfile();

	return array(
		'first_name' => $profile->getFirstName(),
		'last_name'  => $profile->getLastName(),
		'phone'      => $profile->getPhone(),
		'email'      => $wp_user->user_email,
		'username'   => $wp_user->user_login,
	);
}

/**
 * The URL to WordPress' own "reset your password" flow - deliberately
 * not reimplemented on the My Profile page, since password changes
 * are a WordPress account/security concern, not a BizHub client
 * profile field.
 */
function bizupkeep_child_password_change_url(): string {
	return wp_lostpassword_url( home_url( '/client-portal/client-portal-profile/' ) );
}

add_action( 'template_redirect', 'bizupkeep_child_handle_profile_update' );

/**
 * Handle the My Profile page's update form POST. Runs on
 * template_redirect (before the page template renders) so it can
 * redirect on success or failure.
 */
function bizupkeep_child_handle_profile_update(): void {
	if ( ! isset( $_POST['bizupkeep_profile_nonce'] ) ) {
		return;
	}

	if ( ! is_page() || bizupkeep_child_find_page( 'client-portal-profile', bizupkeep_child_find_page( 'client-portal', 0 ) ) !== get_queried_object_id() ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_update_profile', 'bizupkeep_profile_nonce' );

	$result = bizupkeep_child_process_profile_update( get_current_user_id() );

	wp_safe_redirect( add_query_arg( $result ? 'profile_updated' : 'profile_error', '1', get_permalink() ) );
	exit;
}

/**
 * Validate and persist the posted first name/last name/phone against
 * the logged-in user's BizHub client profile. Returns false (rather
 * than throwing) on any failure - both Profile's own validation
 * (first/last name can't be blank) and any BizHub service failure are
 * treated as "show a generic error", the same approach every other
 * public-facing form handler in this file takes.
 */
function bizupkeep_child_process_profile_update( int $wp_user_id ): bool {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return false;
	}

	$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
	$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
	$phone      = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

	try {
		$client = bizhub()->container()->get( ClientServiceInterface::class )->getClientByWpUserId( $wp_user_id );

		bizhub()->container()->get( ProfileService::class )->updateProfile(
			$client->getUuid(),
			new ProfileData( $first_name, $last_name, $phone, $client->getProfile()->getAvatarUrl() )
		);
	} catch ( \Throwable $e ) {
		return false;
	}

	return true;
}

/*
|--------------------------------------------------------------------------
| My Bookkeeping (BizUpKeep Bookkeeping plugin integration)
|--------------------------------------------------------------------------
|
| Follows the exact same pattern as every other Client Portal page in
| this file: guard -> bizhub()->container() chain -> ownership-checked
| forms handled on template_redirect, never inside the template itself.
| The bookkeeping plugin's own services do all the double-entry/
| validation work; this file only scopes access to the logged-in
| client's own company and translates form input <-> service calls.
*/

/**
 * Idempotently seed a company's default chart of accounts. Safe to
 * call on every "My Bookkeeping" page load - AccountService::ensureSeeded()
 * is itself a no-op once a company already has any accounts.
 */
function bizupkeep_child_bookkeeping_ensure_seeded( string $company_uuid ): void {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return;
	}

	bizhub()->container()->get( BookkeepingAccountServiceInterface::class )->ensureSeeded( $company_uuid );
}

/**
 * @return array<int,object>
 */
function bizupkeep_child_bookkeeping_income_accounts( string $company_uuid ): array {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return array();
	}

	return bizhub()->container()->get( BookkeepingAccountServiceInterface::class )->listIncomeAccounts( $company_uuid );
}

/**
 * @return array<int,object>
 */
function bizupkeep_child_bookkeeping_expense_accounts( string $company_uuid ): array {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return array();
	}

	return bizhub()->container()->get( BookkeepingAccountServiceInterface::class )->listExpenseAccounts( $company_uuid );
}

/**
 * The full chart of accounts, for the read-only "Accounts" tab.
 *
 * @return array<int,object>
 */
function bizupkeep_child_bookkeeping_all_accounts( string $company_uuid ): array {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return array();
	}

	return bizhub()->container()->get( BookkeepingAccountServiceInterface::class )->listAccounts( $company_uuid );
}

/**
 * Parse the "Statements" and "Export" tabs' ?from=/?to= (or ?as_of= for
 * the Balance Sheet) query params into a DateRange, defaulting to
 * month-to-date when absent or unparseable - never trusts raw input
 * into a DateTimeImmutable without a try/catch, since a malformed date
 * string throws rather than silently producing a wrong date.
 */
function bizupkeep_child_bookkeeping_parse_range(): BookkeepingDateRange {
	$from_raw = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
	$to_raw   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';

	try {
		$to = '' !== $to_raw ? new \DateTimeImmutable( $to_raw ) : new \DateTimeImmutable();
	} catch ( \Exception $e ) {
		$to = new \DateTimeImmutable();
	}

	if ( '' === $from_raw ) {
		return BookkeepingDateRange::monthToDate( $to );
	}

	try {
		return BookkeepingDateRange::between( new \DateTimeImmutable( $from_raw ), $to );
	} catch ( \Exception $e ) {
		return BookkeepingDateRange::monthToDate( $to );
	}
}

/**
 * Parse the "Balance Sheet" tab's ?as_of= query param, defaulting to
 * today.
 */
function bizupkeep_child_bookkeeping_parse_as_of(): \DateTimeImmutable {
	$raw = isset( $_GET['as_of'] ) ? sanitize_text_field( wp_unslash( $_GET['as_of'] ) ) : '';

	if ( '' === $raw ) {
		return new \DateTimeImmutable();
	}

	try {
		return new \DateTimeImmutable( $raw );
	} catch ( \Exception $e ) {
		return new \DateTimeImmutable();
	}
}

/**
 * Resolve a CSV export platform key ("quickbooks"/"xero"/"sage") to its
 * exporter. Each concrete exporter is autowired by BizHub's shared
 * container with no explicit binding needed (see
 * bizupkeep-bookkeeping's Container/definitions.php) - there's no
 * ambiguity to resolve since each concrete class has exactly one
 * possible set of constructor dependencies.
 */
function bizupkeep_child_bookkeeping_exporter( string $platform_key ): ?LedgerExporterInterface {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return null;
	}

	$class = match ( $platform_key ) {
		'quickbooks' => QuickBooksOnlineExporter::class,
		'xero'       => XeroExporter::class,
		'sage'       => SageExporter::class,
		default      => null,
	};

	if ( null === $class ) {
		return null;
	}

	return bizhub()->container()->get( $class );
}

/**
 * Determine whether the current request is for the "My Bookkeeping"
 * page, the same is_page()+parent-match pattern
 * bizupkeep_child_handle_profile_update() uses.
 */
function bizupkeep_child_is_bookkeeping_page(): bool {
	if ( ! is_page() ) {
		return false;
	}

	$portal = bizupkeep_child_find_page( 'client-portal', 0 );

	return bizupkeep_child_find_page( 'client-portal-bookkeeping', $portal ) === get_queried_object_id();
}

/*
|--------------------------------------------------------------------------
| Bookkeeping monthly subscription (WooCommerce checkout)
|--------------------------------------------------------------------------
|
| Mirrors bizupkeep_child_handle_amendment_payment_intent()'s exact
| pattern (see the doc comment above BIZUPKEEP_PAYMENT_SESSION_KEY for
| the full session -> order-meta -> order-status-changed flow this
| copies), scoped to a company UUID instead of a workflow UUID, since a
| bookkeeping subscription isn't tied to any one application. A
| successful payment calls SubscriptionServiceInterface::extend() - the
| same call the bizupkeep-bookkeeping plugin's staff-facing "Extend 30
| Days" admin button makes (ManualJournalEntryPage), so a client
| payment and a staff manual extension behave identically from the
| ledger's point of view.
*/

/**
 * Whether a company currently has an active bookkeeping subscription -
 * the gate the Capture/Export tabs check before showing their forms.
 */
function bizupkeep_child_bookkeeping_subscription_active( string $company_uuid ): bool {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return false;
	}

	return bizhub()->container()->get( SubscriptionServiceInterface::class )->isActive( $company_uuid );
}

/**
 * Whether staff have flagged this company as VAT registered - the sole
 * gate on whether the Capture tab shows VAT fields and whether the
 * Statements tab shows the VAT Summary section. Set only via the
 * staff-facing ManualJournalEntryPage; never client-editable.
 */
function bizupkeep_child_bookkeeping_is_vat_registered( string $company_uuid ): bool {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return false;
	}

	$settings = bizhub()->container()->get( CompanySettingsRepositoryInterface::class )->findByCompanyUuid( $company_uuid );

	return null !== $settings && $settings->isVatRegistered;
}

/**
 * The company's current subscription record (for displaying
 * paid_until on the Dashboard tab), lazily creating one if this
 * company has never been touched before - mirrors
 * bizupkeep_child_bookkeeping_ensure_seeded()'s lazy pattern for the
 * chart of accounts.
 */
function bizupkeep_child_bookkeeping_subscription_status( string $company_uuid ): ?object {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return null;
	}

	return bizhub()->container()->get( SubscriptionServiceInterface::class )->getOrCreate( $company_uuid );
}

/**
 * Find an existing "Bookkeeping Monthly Access" product by SKU, or
 * create one - mirrors bizupkeep_child_get_or_create_annual_return_fee_product()
 * exactly, except this product's R0 price is a genuine placeholder
 * (nothing overrides it dynamically the way the Annual Return fee's
 * quote-price hook does) - staff must set the real monthly price in
 * WooCommerce Products before this goes live.
 */
function bizupkeep_child_get_or_create_bookkeeping_subscription_product(): int {
	$existing = wc_get_product_id_by_sku( BIZUPKEEP_BOOKKEEPING_SUBSCRIPTION_PRODUCT_SKU );

	if ( $existing ) {
		return (int) $existing;
	}

	if ( ! class_exists( 'WC_Product_Simple' ) ) {
		return 0;
	}

	$product = new WC_Product_Simple();
	$product->set_name( __( 'Bookkeeping Monthly Access', 'bizupkeep-astra-child' ) );
	$product->set_sku( BIZUPKEEP_BOOKKEEPING_SUBSCRIPTION_PRODUCT_SKU );
	$product->set_regular_price( '0' );
	$product->set_price( '0' );
	$product->set_virtual( true );
	$product->set_catalog_visibility( 'hidden' );
	$product->set_status( 'publish' );

	return (int) $product->save();
}

/**
 * Build the "Subscribe"/"Renew" URL for a company's bookkeeping
 * subscription, routing to bizupkeep_child_handle_bookkeeping_subscription_payment_intent().
 */
function bizupkeep_child_bookkeeping_subscription_payment_url( string $company_uuid ): string {
	return add_query_arg(
		array(
			'bizupkeep_pay_bookkeeping_subscription' => $company_uuid,
			'tab' => 'capture',
		),
		get_permalink( bizupkeep_child_find_page( 'client-portal-bookkeeping', bizupkeep_child_find_page( 'client-portal', 0 ) ) )
	);
}

/**
 * Handle ?bizupkeep_pay_bookkeeping_subscription={company_uuid}: verify
 * the company belongs to the logged-in client, add the (real,
 * staff-priced) "Bookkeeping Monthly Access" product to a cleared
 * cart, stash the company UUID in session, and send the client to
 * checkout - exactly bizupkeep_child_handle_amendment_payment_intent()'s
 * pattern, scoped to a company instead of a workflow.
 */
function bizupkeep_child_handle_bookkeeping_subscription_payment_intent(): void {
	if ( ! isset( $_GET['bizupkeep_pay_bookkeeping_subscription'] ) || ! is_user_logged_in() ) {
		return;
	}

	if ( null === WC()->cart || null === WC()->session ) {
		return;
	}

	$fallback_url = home_url( '/client-portal/client-portal-bookkeeping/' );
	$company_uuid = sanitize_text_field( wp_unslash( $_GET['bizupkeep_pay_bookkeeping_subscription'] ) );
	$company      = bizupkeep_child_get_owned_company( get_current_user_id(), $company_uuid );

	if ( null === $company ) {
		wp_safe_redirect( $fallback_url );
		exit;
	}

	$product_id = bizupkeep_child_get_or_create_bookkeeping_subscription_product();

	if ( 0 === $product_id ) {
		wp_safe_redirect( $fallback_url );
		exit;
	}

	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( $product_id, 1 );
	WC()->session->set( BIZUPKEEP_BOOKKEEPING_SUBSCRIPTION_SESSION_KEY, $company_uuid );

	wp_safe_redirect( wc_get_checkout_url() );
	exit;
}

/**
 * At checkout, copy the company UUID from the WooCommerce session onto
 * the new order as post meta - mirrors
 * bizupkeep_child_attach_workflow_to_order() exactly, re-verifying
 * ownership again here rather than trusting the session value blindly.
 */
function bizupkeep_child_attach_bookkeeping_subscription_to_order( WC_Order $order, array $data ): void {
	if ( null === WC()->session ) {
		return;
	}

	$company_uuid = WC()->session->get( BIZUPKEEP_BOOKKEEPING_SUBSCRIPTION_SESSION_KEY );

	if ( ! is_string( $company_uuid ) || '' === $company_uuid ) {
		return;
	}

	WC()->session->set( BIZUPKEEP_BOOKKEEPING_SUBSCRIPTION_SESSION_KEY, null );

	$wp_user_id = get_current_user_id();

	if ( 0 === $wp_user_id || null === bizupkeep_child_get_owned_company( $wp_user_id, $company_uuid ) ) {
		return;
	}

	$order->update_meta_data( '_bizupkeep_bookkeeping_subscription_company_uuid', $company_uuid );
}

/**
 * When an order's status changes to processing or completed, extend
 * the paid-for company's bookkeeping subscription by 30 days - mirrors
 * bizupkeep_child_handle_order_payment()'s idempotency-guarded pattern
 * exactly, reading exclusively from order meta (never session, which
 * may not be available in this hook's context - see the doc comment
 * above BIZUPKEEP_PAYMENT_SESSION_KEY).
 */
function bizupkeep_child_handle_bookkeeping_subscription_order_payment( int $order_id, string $old_status, string $new_status, WC_Order $order ): void {
	if ( ! in_array( $new_status, array( 'processing', 'completed' ), true ) ) {
		return;
	}

	if ( '1' === $order->get_meta( '_bizupkeep_bookkeeping_subscription_confirmed' ) ) {
		return;
	}

	$company_uuid = $order->get_meta( '_bizupkeep_bookkeeping_subscription_company_uuid' );

	if ( ! is_string( $company_uuid ) || '' === $company_uuid ) {
		return;
	}

	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return;
	}

	try {
		bizhub()->container()->get( SubscriptionServiceInterface::class )->extend( $company_uuid, 30 );

		$order->update_meta_data( '_bizupkeep_bookkeeping_subscription_confirmed', '1' );

		// Best-effort A2Z revenue posting - its own inner try/catch, same
		// reasoning as bizupkeep_child_handle_order_payment(): must never
		// be mistaken for the subscription extension itself having
		// failed.
		try {
			bizupkeep_child_post_a2z_revenue( 'bookkeeping_monthly', $order );
			$order->update_meta_data( '_bizupkeep_a2z_revenue_posted', '1' );
		} catch ( \Throwable $e ) {
			// Best-effort only - staff can capture the missed revenue
			// manually via Internal Books.
		}

		$order->save();
	} catch ( \Throwable $e ) {
		// Left unconfirmed - a subsequent status change (or manual staff
		// extension) can still resolve this; nothing here should ever
		// fatal a WooCommerce order-status transition.
	}
}

add_action( 'template_redirect', 'bizupkeep_child_handle_capture_transaction_submission' );

/**
 * Handle the "Capture" tab's income/expense form POST. Runs on
 * template_redirect so it can redirect on success/failure before any
 * HTML is sent, matching every other portal form handler in this file.
 */
function bizupkeep_child_handle_capture_transaction_submission(): void {
	if ( ! isset( $_POST['bizupkeep_bookkeeping_capture_nonce'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_bookkeeping_capture', 'bizupkeep_bookkeeping_capture_nonce' );

	$result = bizupkeep_child_process_capture_transaction( get_current_user_id() );

	$redirect_args = $result
		? array( 'tab' => 'capture', 'captured' => '1' )
		: array( 'tab' => 'capture', 'capture_error' => '1' );

	wp_safe_redirect( add_query_arg( $redirect_args, get_permalink() ) );
	exit;
}

/**
 * Validate and post the capture form against the logged-in client's own
 * company - the posted company UUID is always re-verified via
 * bizupkeep_child_get_owned_company() before anything is written,
 * never trusted outright. Returns false (rather than throwing) on any
 * failure, matching every other public-facing form handler's approach.
 */
function bizupkeep_child_process_capture_transaction( int $wp_user_id ): bool {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return false;
	}

	$company_uuid = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$company      = bizupkeep_child_get_owned_company( $wp_user_id, $company_uuid );

	if ( null === $company ) {
		return false;
	}

	$type_raw     = isset( $_POST['transaction_type'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_type'] ) ) : '';
	$date_raw     = isset( $_POST['transaction_date'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_date'] ) ) : '';
	$amount_raw   = isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : '0';
	$category     = isset( $_POST['category_account'] ) ? sanitize_text_field( wp_unslash( $_POST['category_account'] ) ) : '';
	$payment_raw  = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : '';
	$description  = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';

	// Nonce already verified by the caller; a checkbox's mere presence is the only signal needed here.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$includes_vat = isset( $_POST['includes_vat'] ) && bizupkeep_child_bookkeeping_is_vat_registered( $company->getUuid() );

	$payment_method = 'cash' === $payment_raw ? BookkeepingPaymentMethod::Cash : BookkeepingPaymentMethod::Bank;

	try {
		$date = '' !== $date_raw ? new \DateTimeImmutable( $date_raw ) : new \DateTimeImmutable();
	} catch ( \Exception $e ) {
		return false;
	}

	$data = new CaptureTransactionData(
		$date,
		\BizHub\Bookkeeping\Support\Money::fromRands( (float) $amount_raw ),
		$category,
		$payment_method,
		$description,
		$includes_vat
	);

	$capture = bizhub()->container()->get( TransactionCaptureServiceInterface::class );

	try {
		if ( 'income' === $type_raw ) {
			$capture->captureIncome( $company->getUuid(), $data, $wp_user_id );
		} else {
			$capture->captureExpense( $company->getUuid(), $data, $wp_user_id );
		}
	} catch ( BookkeepingException $e ) {
		return false;
	}

	return true;
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_export_request' );

/**
 * Stream a CSV export download. Runs on template_redirect (before any
 * HTML output) since it sends its own Content-Type/Content-Disposition
 * headers and exits - the same constraint every other file-download
 * handler in this codebase (e.g. Quality Review's streamDocument())
 * respects.
 */
function bizupkeep_child_handle_bookkeeping_export_request(): void {
	if ( ! isset( $_GET['bizupkeep_bookkeeping_export'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( get_permalink() );
		exit;
	}

	$company_uuid = isset( $_GET['company'] ) ? sanitize_text_field( wp_unslash( $_GET['company'] ) ) : '';
	$company      = bizupkeep_child_get_owned_company( get_current_user_id(), $company_uuid );

	if ( null === $company ) {
		wp_safe_redirect( add_query_arg( array( 'tab' => 'export', 'export_error' => '1' ), get_permalink() ) );
		exit;
	}

	// Export has no dedicated per-client service of its own to gate (the
	// exporters are generic reporting classes, also usable by staff) - the
	// subscription check lives here, the only entry point client-facing
	// export ever goes through. This also closes the direct-URL bypass a
	// client could otherwise use even with the Export tab's UI hidden.
	if ( ! bizupkeep_child_bookkeeping_subscription_active( $company->getUuid() ) ) {
		wp_safe_redirect( add_query_arg( array( 'tab' => 'export', 'export_error' => '1' ), get_permalink() ) );
		exit;
	}

	$platform_key = sanitize_text_field( wp_unslash( $_GET['bizupkeep_bookkeeping_export'] ) );
	$exporter     = bizupkeep_child_bookkeeping_exporter( $platform_key );

	if ( null === $exporter ) {
		wp_safe_redirect( add_query_arg( array( 'tab' => 'export', 'export_error' => '1' ), get_permalink() ) );
		exit;
	}

	$range = bizupkeep_child_bookkeeping_parse_range();
	$csv   = $exporter->exportJournalEntries( $company->getUuid(), $range );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $platform_key . '-export-' . gmdate( 'Y-m-d' ) . '.csv' ) . '"' );
	header( 'Content-Length: ' . strlen( $csv ) );

	echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw CSV file content, not HTML.
	exit;
}

/**
 * Render the "Dashboard" tab: current-month income/expense totals plus
 * a short recent-activity list. Bails out to a generic message on any
 * BookkeepingException (e.g. LedgerIntegrityException) rather than
 * fataling the whole portal page.
 */
function bizupkeep_child_render_bookkeeping_dashboard_tab( Company $company ): void {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return;
	}

	try {
		$statements = bizhub()->container()->get( FinancialStatementsServiceInterface::class );
		$income     = $statements->incomeStatement( $company->getUuid(), BookkeepingDateRange::monthToDate() );

		$journal = bizhub()->container()->get( BookkeepingJournalRepositoryInterface::class );
		$recent  = $journal->findEntriesForCompany( $company->getUuid(), BookkeepingDateRange::sinceInception( new \DateTimeImmutable() ), 10 );
	} catch ( \Throwable $e ) {
		echo '<p>' . esc_html__( 'Your bookkeeping summary could not be loaded right now.', 'bizupkeep-astra-child' ) . '</p>';
		return;
	}
	$subscription_active = bizupkeep_child_bookkeeping_subscription_active( $company->getUuid() );
	$subscription        = bizupkeep_child_bookkeeping_subscription_status( $company->getUuid() );
	?>
	<div class="bizupkeep-bookkeeping-summary">
		<p class="bizupkeep-status-pill">
			<?php if ( $subscription_active && null !== $subscription && null !== $subscription->paidUntil ) : ?>
				<?php
				printf(
					/* translators: %s: the date the current subscription period paid for runs until. */
					esc_html__( 'Bookkeeping subscription: Active until %s', 'bizupkeep-astra-child' ),
					esc_html( $subscription->paidUntil->format( 'd/m/Y' ) )
				);
				?>
			<?php else : ?>
				<?php esc_html_e( 'Bookkeeping subscription: Inactive - ', 'bizupkeep-astra-child' ); ?>
				<a href="<?php echo esc_url( bizupkeep_child_bookkeeping_subscription_payment_url( $company->getUuid() ) ); ?>">
					<?php esc_html_e( 'Subscribe Now', 'bizupkeep-astra-child' ); ?>
				</a>
			<?php endif; ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'This month - Income:', 'bizupkeep-astra-child' ); ?></strong>
			<?php echo esc_html( $income->totalIncome->format() ); ?>
			&nbsp;&nbsp;
			<strong><?php esc_html_e( 'Expenses:', 'bizupkeep-astra-child' ); ?></strong>
			<?php echo esc_html( $income->totalExpenses->format() ); ?>
			&nbsp;&nbsp;
			<strong><?php esc_html_e( 'Net:', 'bizupkeep-astra-child' ); ?></strong>
			<?php echo esc_html( $income->netIncome->format() ); ?>
		</p>
	</div>

	<h2><?php esc_html_e( 'Recent Activity', 'bizupkeep-astra-child' ); ?></h2>

	<?php if ( empty( $recent ) ) : ?>
		<p><?php esc_html_e( 'No transactions captured yet.', 'bizupkeep-astra-child' ); ?></p>
	<?php else : ?>
		<table class="bizupkeep-bookkeeping-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'bizupkeep-astra-child' ); ?></th>
					<th><?php esc_html_e( 'Description', 'bizupkeep-astra-child' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'bizupkeep-astra-child' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $recent as $entry ) : ?>
					<?php
					$total = \BizHub\Bookkeeping\Support\Money::zero();
					foreach ( $entry->lines as $line ) {
						if ( $line->isDebit() ) {
							$total = $total->add( $line->debit );
						}
					}
					?>
					<tr>
						<td><?php echo esc_html( $entry->entryDate->format( 'Y-m-d' ) ); ?></td>
						<td><?php echo esc_html( $entry->description ); ?></td>
						<td><?php echo esc_html( $total->format() ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<?php
}

/**
 * Shared "this action needs an active subscription" notice, shown by
 * both the Capture and Export tabs in place of their normal content
 * when bizupkeep_child_bookkeeping_subscription_active() is false.
 * Dashboard/Accounts/Statements are deliberately NOT gated - a lapsed
 * client can still see their own historical books, only the paid
 * actions (capture new transactions, export) are locked.
 */
function bizupkeep_child_render_bookkeeping_subscription_locked_notice( Company $company ): void {
	?>
	<div class="bizupkeep-bookkeeping-summary">
		<p class="bizupkeep-status-pill">
			<?php esc_html_e( 'This feature requires an active bookkeeping subscription.', 'bizupkeep-astra-child' ); ?>
		</p>
		<p>
			<a href="<?php echo esc_url( bizupkeep_child_bookkeeping_subscription_payment_url( $company->getUuid() ) ); ?>" class="bizupkeep-btn bizupkeep-btn-primary">
				<?php esc_html_e( 'Subscribe Now', 'bizupkeep-astra-child' ); ?>
			</a>
		</p>
	</div>
	<?php
}

/**
 * Render the "Capture" tab: the simplified income/expense form.
 * Deliberately shows no debit/credit vocabulary - see
 * bizupkeep_child_process_capture_transaction() and
 * TransactionCaptureService for where that translation happens.
 */
function bizupkeep_child_render_bookkeeping_capture_tab( Company $company ): void {
	if ( ! bizupkeep_child_bookkeeping_subscription_active( $company->getUuid() ) ) {
		bizupkeep_child_render_bookkeeping_subscription_locked_notice( $company );
		return;
	}

	$income_accounts  = bizupkeep_child_bookkeeping_income_accounts( $company->getUuid() );
	$expense_accounts = bizupkeep_child_bookkeeping_expense_accounts( $company->getUuid() );
	$vat_registered   = bizupkeep_child_bookkeeping_is_vat_registered( $company->getUuid() );
	?>
	<form method="post" class="bizupkeep-upload-form bizupkeep-bookkeeping-capture-form">
		<?php wp_nonce_field( 'bizupkeep_bookkeeping_capture', 'bizupkeep_bookkeeping_capture_nonce' ); ?>
		<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">

		<p>
			<label>
				<input type="radio" name="transaction_type" value="income" checked
					onclick="document.getElementById('bizupkeep-bk-income-category').style.display='block';document.getElementById('bizupkeep-bk-expense-category').style.display='none';">
				<?php esc_html_e( 'Income', 'bizupkeep-astra-child' ); ?>
			</label>
			&nbsp;&nbsp;
			<label>
				<input type="radio" name="transaction_type" value="expense"
					onclick="document.getElementById('bizupkeep-bk-income-category').style.display='none';document.getElementById('bizupkeep-bk-expense-category').style.display='block';">
				<?php esc_html_e( 'Expense', 'bizupkeep-astra-child' ); ?>
			</label>
		</p>

		<label for="bizupkeep-bk-date"><?php esc_html_e( 'Date', 'bizupkeep-astra-child' ); ?></label>
		<input type="date" id="bizupkeep-bk-date" name="transaction_date" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" required>

		<label for="bizupkeep-bk-amount"><?php esc_html_e( 'Amount (R)', 'bizupkeep-astra-child' ); ?></label>
		<input type="number" step="0.01" min="0.01" id="bizupkeep-bk-amount" name="amount" required>

		<div id="bizupkeep-bk-income-category">
			<label for="bizupkeep-bk-income-select"><?php esc_html_e( 'Category', 'bizupkeep-astra-child' ); ?></label>
			<select id="bizupkeep-bk-income-select" name="category_account">
				<?php foreach ( $income_accounts as $account ) : ?>
					<option value="<?php echo esc_attr( $account->uuid ); ?>"><?php echo esc_html( $account->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div id="bizupkeep-bk-expense-category" style="display:none;">
			<label for="bizupkeep-bk-expense-select"><?php esc_html_e( 'Category', 'bizupkeep-astra-child' ); ?></label>
			<select id="bizupkeep-bk-expense-select" name="category_account">
				<?php foreach ( $expense_accounts as $account ) : ?>
					<option value="<?php echo esc_attr( $account->uuid ); ?>"><?php echo esc_html( $account->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<script>
		// Only the visible category <select>'s value is submitted (a hidden
		// <select> still posts its own value otherwise) - toggle the
		// "name" attribute along with visibility so only one category ever
		// reaches the server, matching whichever transaction_type is checked.
		(function () {
			var incomeSelect = document.getElementById('bizupkeep-bk-income-select');
			var expenseSelect = document.getElementById('bizupkeep-bk-expense-select');
			function sync() {
				var isIncome = document.querySelector('input[name="transaction_type"]:checked').value === 'income';
				incomeSelect.name = isIncome ? 'category_account' : '';
				expenseSelect.name = isIncome ? '' : 'category_account';
			}
			document.querySelectorAll('input[name="transaction_type"]').forEach(function (el) {
				el.addEventListener('change', sync);
			});
			sync();
		})();
		</script>

		<label for="bizupkeep-bk-payment"><?php esc_html_e( 'Payment Method', 'bizupkeep-astra-child' ); ?></label>
		<select id="bizupkeep-bk-payment" name="payment_method">
			<option value="bank"><?php esc_html_e( 'Bank', 'bizupkeep-astra-child' ); ?></option>
			<option value="cash"><?php esc_html_e( 'Cash', 'bizupkeep-astra-child' ); ?></option>
		</select>

		<?php if ( $vat_registered ) : ?>
			<p>
				<label>
					<input type="checkbox" name="includes_vat" value="1">
					<?php esc_html_e( 'This amount includes VAT (15%)', 'bizupkeep-astra-child' ); ?>
				</label>
			</p>
		<?php endif; ?>

		<label for="bizupkeep-bk-description"><?php esc_html_e( 'Description', 'bizupkeep-astra-child' ); ?></label>
		<input type="text" id="bizupkeep-bk-description" name="description" maxlength="500">

		<p>
			<button type="submit" class="bizupkeep-btn bizupkeep-btn-primary">
				<?php esc_html_e( 'Capture Transaction', 'bizupkeep-astra-child' ); ?>
			</button>
		</p>
	</form>
	<?php
}

/**
 * Render the read-only "Chart of Accounts" tab.
 */
function bizupkeep_child_render_bookkeeping_accounts_tab( Company $company ): void {
	$accounts = bizupkeep_child_bookkeeping_all_accounts( $company->getUuid() );
	?>
	<table class="bizupkeep-bookkeeping-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Code', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'Name', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'Type', 'bizupkeep-astra-child' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $accounts as $account ) : ?>
				<tr>
					<td><?php echo esc_html( $account->code ); ?></td>
					<td><?php echo esc_html( $account->name ); ?></td>
					<td><?php echo esc_html( $account->type->label() ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Render the "Statements" tab: Trial Balance, Income Statement and
 * Balance Sheet for the requested date range/as-of date, each in its
 * own table. Any LedgerIntegrityException (or other BookkeepingException)
 * is shown as a generic message rather than fataling the page - a real
 * occurrence would indicate a data problem worth a client contacting
 * support about, not something this page can resolve itself.
 */
function bizupkeep_child_render_bookkeeping_statements_tab( Company $company ): void {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return;
	}

	$range = bizupkeep_child_bookkeeping_parse_range();
	$as_of = bizupkeep_child_bookkeeping_parse_as_of();
	?>
	<form method="get" class="bizupkeep-bookkeeping-range-form">
		<input type="hidden" name="tab" value="statements">
		<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">
		<label><?php esc_html_e( 'From', 'bizupkeep-astra-child' ); ?>
			<input type="date" name="from" value="<?php echo esc_attr( null !== $range->from ? $range->from->format( 'Y-m-d' ) : '' ); ?>">
		</label>
		<label><?php esc_html_e( 'To', 'bizupkeep-astra-child' ); ?>
			<input type="date" name="to" value="<?php echo esc_attr( $range->to->format( 'Y-m-d' ) ); ?>">
		</label>
		<label><?php esc_html_e( 'Balance Sheet as of', 'bizupkeep-astra-child' ); ?>
			<input type="date" name="as_of" value="<?php echo esc_attr( $as_of->format( 'Y-m-d' ) ); ?>">
		</label>
		<button type="submit" class="bizupkeep-btn bizupkeep-btn-secondary"><?php esc_html_e( 'Update', 'bizupkeep-astra-child' ); ?></button>
	</form>

	<?php
	try {
		$statements    = bizhub()->container()->get( FinancialStatementsServiceInterface::class );
		$trial_balance = $statements->trialBalance( $company->getUuid(), $range );
		$income        = $statements->incomeStatement( $company->getUuid(), $range );
		$balance_sheet = $statements->balanceSheet( $company->getUuid(), $as_of );
	} catch ( \Throwable $e ) {
		echo '<p>' . esc_html__( 'Statements could not be generated for this range - please try again.', 'bizupkeep-astra-child' ) . '</p>';
		return;
	}
	?>

	<h2><?php esc_html_e( 'Trial Balance', 'bizupkeep-astra-child' ); ?></h2>
	<table class="bizupkeep-bookkeeping-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Account', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'Debit', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'Credit', 'bizupkeep-astra-child' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $trial_balance->rows as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row->code . ' - ' . $row->name ); ?></td>
					<td><?php echo esc_html( $row->debit->isZero() ? '' : $row->debit->format() ); ?></td>
					<td><?php echo esc_html( $row->credit->isZero() ? '' : $row->credit->format() ); ?></td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<td><strong><?php esc_html_e( 'Total', 'bizupkeep-astra-child' ); ?></strong></td>
				<td><strong><?php echo esc_html( $trial_balance->totalDebit->format() ); ?></strong></td>
				<td><strong><?php echo esc_html( $trial_balance->totalCredit->format() ); ?></strong></td>
			</tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Income Statement', 'bizupkeep-astra-child' ); ?></h2>
	<table class="bizupkeep-bookkeeping-table">
		<tbody>
			<?php foreach ( $income->incomeRows as $row ) : ?>
				<tr><td><?php echo esc_html( $row->name ); ?></td><td><?php echo esc_html( $row->net->format() ); ?></td></tr>
			<?php endforeach; ?>
			<tr><td><strong><?php esc_html_e( 'Total Income', 'bizupkeep-astra-child' ); ?></strong></td><td><strong><?php echo esc_html( $income->totalIncome->format() ); ?></strong></td></tr>
			<?php foreach ( $income->expenseRows as $row ) : ?>
				<tr><td><?php echo esc_html( $row->name ); ?></td><td><?php echo esc_html( $row->net->format() ); ?></td></tr>
			<?php endforeach; ?>
			<tr><td><strong><?php esc_html_e( 'Total Expenses', 'bizupkeep-astra-child' ); ?></strong></td><td><strong><?php echo esc_html( $income->totalExpenses->format() ); ?></strong></td></tr>
			<tr><td><strong><?php esc_html_e( 'Net Income', 'bizupkeep-astra-child' ); ?></strong></td><td><strong><?php echo esc_html( $income->netIncome->format() ); ?></strong></td></tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Balance Sheet', 'bizupkeep-astra-child' ); ?></h2>
	<table class="bizupkeep-bookkeeping-table">
		<tbody>
			<tr><td colspan="2"><strong><?php esc_html_e( 'Assets', 'bizupkeep-astra-child' ); ?></strong></td></tr>
			<?php foreach ( $balance_sheet->assetRows as $row ) : ?>
				<tr><td><?php echo esc_html( $row->name ); ?></td><td><?php echo esc_html( $row->net->format() ); ?></td></tr>
			<?php endforeach; ?>
			<tr><td><strong><?php esc_html_e( 'Total Assets', 'bizupkeep-astra-child' ); ?></strong></td><td><strong><?php echo esc_html( $balance_sheet->totalAssets->format() ); ?></strong></td></tr>

			<tr><td colspan="2"><strong><?php esc_html_e( 'Liabilities', 'bizupkeep-astra-child' ); ?></strong></td></tr>
			<?php foreach ( $balance_sheet->liabilityRows as $row ) : ?>
				<tr><td><?php echo esc_html( $row->name ); ?></td><td><?php echo esc_html( $row->net->format() ); ?></td></tr>
			<?php endforeach; ?>
			<tr><td><strong><?php esc_html_e( 'Total Liabilities', 'bizupkeep-astra-child' ); ?></strong></td><td><strong><?php echo esc_html( $balance_sheet->totalLiabilities->format() ); ?></strong></td></tr>

			<tr><td colspan="2"><strong><?php esc_html_e( 'Equity', 'bizupkeep-astra-child' ); ?></strong></td></tr>
			<?php foreach ( $balance_sheet->equityRows as $row ) : ?>
				<tr><td><?php echo esc_html( $row->name ); ?></td><td><?php echo esc_html( $row->net->format() ); ?></td></tr>
			<?php endforeach; ?>
			<tr><td><?php esc_html_e( 'Retained Earnings (prior years)', 'bizupkeep-astra-child' ); ?></td><td><?php echo esc_html( $balance_sheet->priorYearsEarnings->format() ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Current Year Earnings', 'bizupkeep-astra-child' ); ?></td><td><?php echo esc_html( $balance_sheet->currentYearEarnings->format() ); ?></td></tr>
			<tr><td><strong><?php esc_html_e( 'Total Equity', 'bizupkeep-astra-child' ); ?></strong></td><td><strong><?php echo esc_html( $balance_sheet->totalEquity->format() ); ?></strong></td></tr>
		</tbody>
	</table>

	<?php if ( bizupkeep_child_bookkeeping_is_vat_registered( $company->getUuid() ) ) : ?>
		<?php $vat_summary = $statements->vatSummary( $company->getUuid(), $range ); ?>
		<h2><?php esc_html_e( 'VAT Summary', 'bizupkeep-astra-child' ); ?></h2>
		<table class="bizupkeep-bookkeeping-table">
			<tbody>
				<tr><td><?php esc_html_e( 'Output VAT (on sales)', 'bizupkeep-astra-child' ); ?></td><td><?php echo esc_html( $vat_summary->outputVat->format() ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Input VAT (on purchases)', 'bizupkeep-astra-child' ); ?></td><td><?php echo esc_html( $vat_summary->inputVat->format() ); ?></td></tr>
				<tr>
					<td><strong>
						<?php
						echo esc_html(
							$vat_summary->netVatPayable->isNegative()
								? __( 'Net VAT Refundable', 'bizupkeep-astra-child' )
								: __( 'Net VAT Payable', 'bizupkeep-astra-child' )
						);
						?>
					</strong></td>
					<td><strong><?php echo esc_html( $vat_summary->netVatPayable->format() ); ?></strong></td>
				</tr>
			</tbody>
		</table>
	<?php endif; ?>
	<?php
}

/**
 * Render the "Export" tab: a date range plus one download link per
 * supported platform. Each link is a GET request handled by
 * bizupkeep_child_handle_bookkeeping_export_request() on
 * template_redirect - no form/POST needed since exporting has no side
 * effects.
 */
function bizupkeep_child_render_bookkeeping_export_tab( Company $company ): void {
	if ( ! bizupkeep_child_bookkeeping_subscription_active( $company->getUuid() ) ) {
		bizupkeep_child_render_bookkeeping_subscription_locked_notice( $company );
		return;
	}

	$range = bizupkeep_child_bookkeeping_parse_range();
	?>
	<form method="get" class="bizupkeep-bookkeeping-range-form">
		<input type="hidden" name="tab" value="export">
		<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">
		<label><?php esc_html_e( 'From', 'bizupkeep-astra-child' ); ?>
			<input type="date" name="from" value="<?php echo esc_attr( null !== $range->from ? $range->from->format( 'Y-m-d' ) : '' ); ?>">
		</label>
		<label><?php esc_html_e( 'To', 'bizupkeep-astra-child' ); ?>
			<input type="date" name="to" value="<?php echo esc_attr( $range->to->format( 'Y-m-d' ) ); ?>">
		</label>
		<button type="submit" class="bizupkeep-btn bizupkeep-btn-secondary"><?php esc_html_e( 'Update Range', 'bizupkeep-astra-child' ); ?></button>
	</form>

	<p>
		<?php
		$platforms = array(
			'quickbooks' => __( 'QuickBooks Online', 'bizupkeep-astra-child' ),
			'xero'       => __( 'Xero', 'bizupkeep-astra-child' ),
			'sage'       => __( 'Sage', 'bizupkeep-astra-child' ),
		);

		foreach ( $platforms as $key => $label ) :
			$url = add_query_arg(
				array(
					'tab' => 'export',
					'company' => $company->getUuid(),
					'from' => null !== $range->from ? $range->from->format( 'Y-m-d' ) : '',
					'to' => $range->to->format( 'Y-m-d' ),
					'bizupkeep_bookkeeping_export' => $key,
				),
				get_permalink()
			);
			?>
			<a href="<?php echo esc_url( $url ); ?>" class="bizupkeep-btn bizupkeep-btn-primary">
				<?php
				/* translators: %s: accounting platform name, e.g. "Xero". */
				printf( esc_html__( 'Download for %s', 'bizupkeep-astra-child' ), esc_html( $label ) );
				?>
			</a>
		<?php endforeach; ?>
	</p>
	<?php
}

/*
|--------------------------------------------------------------------------
| Bank statement CSV import
|--------------------------------------------------------------------------
|
| Upload -> map columns (a live GET-parameterized preview, no JS round
| trip needed) -> review/categorize. Categorizing always goes through
| BankImportServiceInterface::categorize()/bulkCategorize(), which
| themselves only ever call TransactionCaptureServiceInterface - this
| tab never posts to the ledger directly, and inherits the same
| subscription gate every other paid action already has (bulk capture
| is still capture).
*/

const BIZUPKEEP_BOOKKEEPING_IMPORT_TRANSIENT_PREFIX = 'bizupkeep_bk_import_';

/**
 * Short-lived storage for an uploaded CSV between the "upload" and
 * "map" steps - this plugin has never written a file to disk and
 * shouldn't start now for one temporary upload.
 */
function bizupkeep_child_bookkeeping_import_transient_key( int $wp_user_id, string $company_uuid ): string {
	return BIZUPKEEP_BOOKKEEPING_IMPORT_TRANSIENT_PREFIX . md5( $wp_user_id . '|' . $company_uuid );
}

/**
 * The two accounts a statement can be imported against - Bank Account
 * and Cash on Hand, the only accounts BankImportService's
 * resolvePaymentMethod() knows how to map back from.
 *
 * @return array<int,object>
 */
function bizupkeep_child_bookkeeping_import_source_accounts( string $company_uuid ): array {
	return array_values( array_filter(
		bizupkeep_child_bookkeeping_all_accounts( $company_uuid ),
		static function ( $account ): bool {
			return in_array(
				$account->code,
				array(
					BookkeepingChartOfAccountsTemplate::CODE_BANK_ACCOUNT,
					BookkeepingChartOfAccountsTemplate::CODE_CASH_ON_HAND,
				),
				true
			);
		}
	) );
}

/**
 * Parse+validate the column-mapping fields shared by the map step's
 * GET-parameterized preview and its POST confirm submission.
 *
 * @param array<string,mixed> $source $_GET or $_POST.
 *
 * @return array{date_column:string,description_column:string,amount_style:ImportAmountStyle,amount_column:?string,debit_column:?string,credit_column:?string,date_format:string}|null
 */
function bizupkeep_child_bookkeeping_parse_mapping_fields( array $source ): ?array {
	$date_column        = isset( $source['date_column'] ) ? sanitize_text_field( wp_unslash( $source['date_column'] ) ) : '';
	$description_column = isset( $source['description_column'] ) ? sanitize_text_field( wp_unslash( $source['description_column'] ) ) : '';
	$date_format        = isset( $source['date_format'] ) ? sanitize_text_field( wp_unslash( $source['date_format'] ) ) : 'd/m/Y';
	$amount_style_raw   = isset( $source['amount_style'] ) ? sanitize_text_field( wp_unslash( $source['amount_style'] ) ) : 'signed';

	if ( '' === $date_column || '' === $description_column ) {
		return null;
	}

	$amount_style = 'debit_credit' === $amount_style_raw ? ImportAmountStyle::DebitCredit : ImportAmountStyle::Signed;

	$amount_column = isset( $source['amount_column'] ) ? sanitize_text_field( wp_unslash( $source['amount_column'] ) ) : '';
	$debit_column  = isset( $source['debit_column'] ) ? sanitize_text_field( wp_unslash( $source['debit_column'] ) ) : '';
	$credit_column = isset( $source['credit_column'] ) ? sanitize_text_field( wp_unslash( $source['credit_column'] ) ) : '';

	if ( ImportAmountStyle::Signed === $amount_style && '' === $amount_column ) {
		return null;
	}

	if ( ImportAmountStyle::DebitCredit === $amount_style && ( '' === $debit_column || '' === $credit_column ) ) {
		return null;
	}

	return array(
		'date_column' => $date_column,
		'description_column' => $description_column,
		'amount_style' => $amount_style,
		'amount_column' => '' !== $amount_column ? $amount_column : null,
		'debit_column' => '' !== $debit_column ? $debit_column : null,
		'credit_column' => '' !== $credit_column ? $credit_column : null,
		'date_format' => $date_format,
	);
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_import_upload' );

/**
 * Step 1: handle the statement upload. Stashes the raw CSV content in
 * a transient and redirects to the mapping step - nothing is staged
 * until the client confirms a column mapping.
 */
function bizupkeep_child_handle_bookkeeping_import_upload(): void {
	if ( ! isset( $_POST['bizupkeep_bookkeeping_import_upload_nonce'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	$wp_user_id = get_current_user_id();

	if ( 0 === $wp_user_id ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_bookkeeping_import_upload', 'bizupkeep_bookkeeping_import_upload_nonce' );

	$company_uuid = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$company      = bizupkeep_child_get_owned_company( $wp_user_id, $company_uuid );

	if ( null === $company ) {
		wp_safe_redirect( add_query_arg( array( 'tab' => 'import', 'import_error' => '1' ), get_permalink() ) );
		exit;
	}

	$redirect_base = add_query_arg( array( 'tab' => 'import', 'company' => $company->getUuid() ), get_permalink() );

	$source_account_uuid = isset( $_POST['source_account'] ) ? sanitize_text_field( wp_unslash( $_POST['source_account'] ) ) : '';

	if ( '' === $source_account_uuid
		|| empty( $_FILES['statement']['tmp_name'] )
		|| ! is_uploaded_file( $_FILES['statement']['tmp_name'] )
	) {
		wp_safe_redirect( add_query_arg( 'import_error', '1', $redirect_base ) );
		exit;
	}

	$csv_content = file_get_contents( $_FILES['statement']['tmp_name'] );

	if ( false === $csv_content || '' === trim( $csv_content ) ) {
		wp_safe_redirect( add_query_arg( 'import_error', '1', $redirect_base ) );
		exit;
	}

	set_transient(
		bizupkeep_child_bookkeeping_import_transient_key( $wp_user_id, $company->getUuid() ),
		array(
			'csv' => $csv_content,
			'source_account' => $source_account_uuid,
		),
		15 * MINUTE_IN_SECONDS
	);

	wp_safe_redirect( add_query_arg( 'step', 'map', $redirect_base ) );
	exit;
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_import_confirm' );

/**
 * Step 2: handle the "Confirm & Import" submission - saves the chosen
 * column mapping (reused next month) and stages every parseable row,
 * deduped against previously-staged rows for this company.
 */
function bizupkeep_child_handle_bookkeeping_import_confirm(): void {
	if ( ! isset( $_POST['bizupkeep_bookkeeping_import_confirm_nonce'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	$wp_user_id = get_current_user_id();

	if ( 0 === $wp_user_id ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_bookkeeping_import_confirm', 'bizupkeep_bookkeeping_import_confirm_nonce' );

	$company_uuid = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$company      = bizupkeep_child_get_owned_company( $wp_user_id, $company_uuid );

	if ( null === $company || ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( add_query_arg( array( 'tab' => 'import', 'import_error' => '1' ), get_permalink() ) );
		exit;
	}

	$redirect_base = add_query_arg( array( 'tab' => 'import', 'company' => $company->getUuid() ), get_permalink() );

	$transient_key = bizupkeep_child_bookkeeping_import_transient_key( $wp_user_id, $company->getUuid() );
	$stashed       = get_transient( $transient_key );

	if ( ! is_array( $stashed ) || empty( $stashed['csv'] ) || empty( $stashed['source_account'] ) ) {
		wp_safe_redirect( add_query_arg( array( 'step' => 'upload', 'import_error' => '1' ), $redirect_base ) );
		exit;
	}

	$mapping_fields = bizupkeep_child_bookkeeping_parse_mapping_fields( $_POST );

	if ( null === $mapping_fields ) {
		wp_safe_redirect( add_query_arg( array( 'step' => 'map', 'import_error' => '1' ), $redirect_base ) );
		exit;
	}

	$bank_import = bizhub()->container()->get( BankImportServiceInterface::class );

	$saved_mapping = $bank_import->saveMapping(
		$company->getUuid(),
		$mapping_fields['date_column'],
		$mapping_fields['description_column'],
		$mapping_fields['amount_style'],
		$mapping_fields['amount_column'],
		$mapping_fields['debit_column'],
		$mapping_fields['credit_column'],
		$mapping_fields['date_format']
	);

	$result = $bank_import->import( $company->getUuid(), $stashed['source_account'], $stashed['csv'], $saved_mapping );

	delete_transient( $transient_key );

	wp_safe_redirect( add_query_arg(
		array(
			'step' => 'upload',
			'imported' => $result->imported,
			'duplicates' => $result->duplicates,
			'unparseable' => $result->unparseable,
		),
		$redirect_base
	) );
	exit;
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_categorize' );

/**
 * Categorize a single staged transaction into a real journal entry.
 */
function bizupkeep_child_handle_bookkeeping_categorize(): void {
	if ( ! isset( $_POST['bizupkeep_bookkeeping_categorize_nonce'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	$wp_user_id = get_current_user_id();

	if ( 0 === $wp_user_id ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_bookkeeping_categorize', 'bizupkeep_bookkeeping_categorize_nonce' );

	$company_uuid   = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$company        = bizupkeep_child_get_owned_company( $wp_user_id, $company_uuid );
	$redirect_base  = add_query_arg( array( 'tab' => 'import', 'step' => 'review', 'company' => $company_uuid ), get_permalink() );

	if ( null === $company || ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( add_query_arg( 'import_error', '1', $redirect_base ) );
		exit;
	}

	$staged_uuid      = isset( $_POST['staged'] ) ? sanitize_text_field( wp_unslash( $_POST['staged'] ) ) : '';
	$category_account = isset( $_POST['category_account'] ) ? sanitize_text_field( wp_unslash( $_POST['category_account'] ) ) : '';

	try {
		bizhub()->container()->get( BankImportServiceInterface::class )
			->categorize( $company->getUuid(), $staged_uuid, $category_account, $wp_user_id );

		wp_safe_redirect( add_query_arg( 'categorized', '1', $redirect_base ) );
	} catch ( BookkeepingException $e ) {
		wp_safe_redirect( add_query_arg( 'import_error', '1', $redirect_base ) );
	}

	exit;
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_bulk_categorize' );

/**
 * Categorize many staged transactions at once with one shared category.
 */
function bizupkeep_child_handle_bookkeeping_bulk_categorize(): void {
	if ( ! isset( $_POST['bizupkeep_bookkeeping_bulk_categorize_nonce'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	$wp_user_id = get_current_user_id();

	if ( 0 === $wp_user_id ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_bookkeeping_bulk_categorize', 'bizupkeep_bookkeeping_bulk_categorize_nonce' );

	$company_uuid  = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$company       = bizupkeep_child_get_owned_company( $wp_user_id, $company_uuid );
	$redirect_base = add_query_arg( array( 'tab' => 'import', 'step' => 'review', 'company' => $company_uuid ), get_permalink() );

	if ( null === $company || ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( add_query_arg( 'import_error', '1', $redirect_base ) );
		exit;
	}

	$staged_uuids     = isset( $_POST['staged'] ) && is_array( $_POST['staged'] )
		? array_map( 'sanitize_text_field', wp_unslash( $_POST['staged'] ) )
		: array();
	$category_account = isset( $_POST['category_account'] ) ? sanitize_text_field( wp_unslash( $_POST['category_account'] ) ) : '';

	if ( array() === $staged_uuids || '' === $category_account ) {
		wp_safe_redirect( add_query_arg( 'import_error', '1', $redirect_base ) );
		exit;
	}

	bizhub()->container()->get( BankImportServiceInterface::class )
		->bulkCategorize( $company->getUuid(), $staged_uuids, $category_account, $wp_user_id );

	wp_safe_redirect( add_query_arg( 'categorized', '1', $redirect_base ) );
	exit;
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_ignore' );

/**
 * Mark a staged transaction ignored without ever posting it.
 */
function bizupkeep_child_handle_bookkeeping_ignore(): void {
	if ( ! isset( $_POST['bizupkeep_bookkeeping_ignore_nonce'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	$wp_user_id = get_current_user_id();

	if ( 0 === $wp_user_id ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_bookkeeping_ignore', 'bizupkeep_bookkeeping_ignore_nonce' );

	$company_uuid  = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$company       = bizupkeep_child_get_owned_company( $wp_user_id, $company_uuid );
	$redirect_base = add_query_arg( array( 'tab' => 'import', 'step' => 'review', 'company' => $company_uuid ), get_permalink() );

	if ( null === $company || ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( add_query_arg( 'import_error', '1', $redirect_base ) );
		exit;
	}

	$staged_uuid = isset( $_POST['staged'] ) ? sanitize_text_field( wp_unslash( $_POST['staged'] ) ) : '';

	try {
		bizhub()->container()->get( BankImportServiceInterface::class )->ignore( $company->getUuid(), $staged_uuid );
		wp_safe_redirect( add_query_arg( 'ignored', '1', $redirect_base ) );
	} catch ( BookkeepingException $e ) {
		wp_safe_redirect( add_query_arg( 'import_error', '1', $redirect_base ) );
	}

	exit;
}

/**
 * Render the "Import" tab: gated by subscription like Capture/Export
 * (bulk capture is still capture), dispatches on ?step= to the
 * upload/map/review sub-views.
 */
function bizupkeep_child_render_bookkeeping_import_tab( Company $company ): void {
	if ( ! bizupkeep_child_bookkeeping_subscription_active( $company->getUuid() ) ) {
		bizupkeep_child_render_bookkeeping_subscription_locked_notice( $company );
		return;
	}

	$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : 'upload';

	if ( ! in_array( $step, array( 'upload', 'map', 'review' ), true ) ) {
		$step = 'upload';
	}

	if ( 'map' === $step ) {
		bizupkeep_child_render_bookkeeping_import_map_step( $company );
	} elseif ( 'review' === $step ) {
		bizupkeep_child_render_bookkeeping_import_review_step( $company );
	} else {
		bizupkeep_child_render_bookkeeping_import_upload_step( $company );
	}
}

function bizupkeep_child_render_bookkeeping_import_upload_step( Company $company ): void {
	$source_accounts = bizupkeep_child_bookkeeping_import_source_accounts( $company->getUuid() );

	if ( isset( $_GET['imported'] ) ) {
		printf(
			'<p class="bizupkeep-status-pill">%s</p>',
			esc_html( sprintf(
				/* translators: 1: imported count, 2: duplicate count, 3: unparseable count. */
				__( 'Imported %1$d transaction(s) - %2$d duplicate(s) skipped, %3$d row(s) could not be read.', 'bizupkeep-astra-child' ),
				(int) $_GET['imported'],
				isset( $_GET['duplicates'] ) ? (int) $_GET['duplicates'] : 0,
				isset( $_GET['unparseable'] ) ? (int) $_GET['unparseable'] : 0
			) )
		);
	}
	?>
	<form method="post" enctype="multipart/form-data" class="bizupkeep-upload-form">
		<?php wp_nonce_field( 'bizupkeep_bookkeeping_import_upload', 'bizupkeep_bookkeeping_import_upload_nonce' ); ?>
		<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">

		<label for="bizupkeep-bk-source-account"><?php esc_html_e( 'Account', 'bizupkeep-astra-child' ); ?></label>
		<select id="bizupkeep-bk-source-account" name="source_account">
			<?php foreach ( $source_accounts as $account ) : ?>
				<option value="<?php echo esc_attr( $account->uuid ); ?>"><?php echo esc_html( $account->name ); ?></option>
			<?php endforeach; ?>
		</select>

		<label for="bizupkeep-bk-statement"><?php esc_html_e( 'Bank Statement (CSV)', 'bizupkeep-astra-child' ); ?></label>
		<input type="file" id="bizupkeep-bk-statement" name="statement" accept=".csv,text/csv" required>

		<p>
			<button type="submit" class="bizupkeep-btn bizupkeep-btn-primary">
				<?php esc_html_e( 'Upload Statement', 'bizupkeep-astra-child' ); ?>
			</button>
		</p>
	</form>

	<?php bizupkeep_child_render_bookkeeping_import_review_link( $company ); ?>
	<?php
}

function bizupkeep_child_render_bookkeeping_import_review_link( Company $company ): void {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return;
	}

	$pending = bizhub()->container()->get( BankImportServiceInterface::class )->listPending( $company->getUuid() );

	if ( array() === $pending ) {
		return;
	}

	$url = add_query_arg( array( 'tab' => 'import', 'step' => 'review', 'company' => $company->getUuid() ), get_permalink() );
	printf(
		'<p><a href="%1$s" class="bizupkeep-btn bizupkeep-btn-secondary">%2$s</a></p>',
		esc_url( $url ),
		esc_html( sprintf(
			/* translators: %d: number of transactions still awaiting review. */
			__( 'Review %d transaction(s) awaiting categorization', 'bizupkeep-astra-child' ),
			count( $pending )
		) )
	);
}

/**
 * @param array<int,string> $headers
 * @param array{date_column:string,description_column:string,amount_style:ImportAmountStyle,amount_column:?string,debit_column:?string,credit_column:?string,date_format:string}|null $fields
 */
function bizupkeep_child_render_bookkeeping_import_column_selects( array $headers, ?array $fields ): void {
	$is_signed = null === $fields || ImportAmountStyle::Signed === $fields['amount_style'];
	?>
	<p>
		<label for="bizupkeep-bk-date-column"><?php esc_html_e( 'Date column', 'bizupkeep-astra-child' ); ?></label>
		<select id="bizupkeep-bk-date-column" name="date_column">
			<?php foreach ( $headers as $header ) : ?>
				<option value="<?php echo esc_attr( $header ); ?>" <?php selected( $fields['date_column'] ?? '', $header ); ?>>
					<?php echo esc_html( $header ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="bizupkeep-bk-date-format"><?php esc_html_e( 'Date format', 'bizupkeep-astra-child' ); ?></label>
		<select id="bizupkeep-bk-date-format" name="date_format">
			<?php foreach ( array( 'd/m/Y', 'Y/m/d', 'm/d/Y' ) as $format ) : ?>
				<option value="<?php echo esc_attr( $format ); ?>" <?php selected( $fields['date_format'] ?? 'd/m/Y', $format ); ?>>
					<?php echo esc_html( $format ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="bizupkeep-bk-description-column"><?php esc_html_e( 'Description column', 'bizupkeep-astra-child' ); ?></label>
		<select id="bizupkeep-bk-description-column" name="description_column">
			<?php foreach ( $headers as $header ) : ?>
				<option value="<?php echo esc_attr( $header ); ?>" <?php selected( $fields['description_column'] ?? '', $header ); ?>>
					<?php echo esc_html( $header ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label>
			<input type="radio" name="amount_style" value="signed" <?php checked( $is_signed ); ?>>
			<?php esc_html_e( 'Single signed amount column', 'bizupkeep-astra-child' ); ?>
		</label>
		<br>
		<label>
			<input type="radio" name="amount_style" value="debit_credit" <?php checked( ! $is_signed ); ?>>
			<?php esc_html_e( 'Separate debit/credit columns', 'bizupkeep-astra-child' ); ?>
		</label>
	</p>
	<p>
		<label for="bizupkeep-bk-amount-column"><?php esc_html_e( 'Amount column (if signed)', 'bizupkeep-astra-child' ); ?></label>
		<select id="bizupkeep-bk-amount-column" name="amount_column">
			<option value="">-</option>
			<?php foreach ( $headers as $header ) : ?>
				<option value="<?php echo esc_attr( $header ); ?>" <?php selected( $fields['amount_column'] ?? '', $header ); ?>>
					<?php echo esc_html( $header ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="bizupkeep-bk-debit-column"><?php esc_html_e( 'Debit column (if separate)', 'bizupkeep-astra-child' ); ?></label>
		<select id="bizupkeep-bk-debit-column" name="debit_column">
			<option value="">-</option>
			<?php foreach ( $headers as $header ) : ?>
				<option value="<?php echo esc_attr( $header ); ?>" <?php selected( $fields['debit_column'] ?? '', $header ); ?>>
					<?php echo esc_html( $header ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="bizupkeep-bk-credit-column"><?php esc_html_e( 'Credit column (if separate)', 'bizupkeep-astra-child' ); ?></label>
		<select id="bizupkeep-bk-credit-column" name="credit_column">
			<option value="">-</option>
			<?php foreach ( $headers as $header ) : ?>
				<option value="<?php echo esc_attr( $header ); ?>" <?php selected( $fields['credit_column'] ?? '', $header ); ?>>
					<?php echo esc_html( $header ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<?php
}

/**
 * @param array{date_column:string,description_column:string,amount_style:ImportAmountStyle,amount_column:?string,debit_column:?string,credit_column:?string,date_format:string}|null $fields
 */
function bizupkeep_child_render_bookkeeping_import_hidden_mapping_fields( ?array $fields ): void {
	if ( null === $fields ) {
		return;
	}

	echo '<input type="hidden" name="date_column" value="' . esc_attr( $fields['date_column'] ) . '">';
	echo '<input type="hidden" name="description_column" value="' . esc_attr( $fields['description_column'] ) . '">';
	echo '<input type="hidden" name="amount_style" value="' . esc_attr( $fields['amount_style']->value ) . '">';
	echo '<input type="hidden" name="amount_column" value="' . esc_attr( $fields['amount_column'] ?? '' ) . '">';
	echo '<input type="hidden" name="debit_column" value="' . esc_attr( $fields['debit_column'] ?? '' ) . '">';
	echo '<input type="hidden" name="credit_column" value="' . esc_attr( $fields['credit_column'] ?? '' ) . '">';
	echo '<input type="hidden" name="date_format" value="' . esc_attr( $fields['date_format'] ) . '">';
}

function bizupkeep_child_render_bookkeeping_import_map_step( Company $company ): void {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return;
	}

	$wp_user_id = get_current_user_id();
	$stashed    = get_transient( bizupkeep_child_bookkeeping_import_transient_key( $wp_user_id, $company->getUuid() ) );

	if ( ! is_array( $stashed ) || empty( $stashed['csv'] ) ) {
		echo '<p>' . esc_html__( 'Your upload has expired - please upload the statement again.', 'bizupkeep-astra-child' ) . '</p>';

		return;
	}

	$bank_import   = bizhub()->container()->get( BankImportServiceInterface::class );
	$csv_reader    = new BookkeepingCsvReader();
	$headers       = $csv_reader->readHeaders( $stashed['csv'] );
	$saved_mapping = $bank_import->getMapping( $company->getUuid() );

	$fields = bizupkeep_child_bookkeeping_parse_mapping_fields( $_GET );

	if ( null === $fields && null !== $saved_mapping && in_array( $saved_mapping->dateColumn, $headers, true ) ) {
		$fields = array(
			'date_column' => $saved_mapping->dateColumn,
			'description_column' => $saved_mapping->descriptionColumn,
			'amount_style' => $saved_mapping->amountStyle,
			'amount_column' => $saved_mapping->amountColumn,
			'debit_column' => $saved_mapping->debitColumn,
			'credit_column' => $saved_mapping->creditColumn,
			'date_format' => $saved_mapping->dateFormat,
		);
	}

	$preview = array();

	if ( null !== $fields ) {
		try {
			$mapping = new ImportMapping(
				uuid: 'preview',
				companyUuid: $company->getUuid(),
				dateColumn: $fields['date_column'],
				descriptionColumn: $fields['description_column'],
				amountStyle: $fields['amount_style'],
				amountColumn: $fields['amount_column'],
				debitColumn: $fields['debit_column'],
				creditColumn: $fields['credit_column'],
				dateFormat: $fields['date_format'],
				createdAt: new DateTimeImmutable(),
			);
			$preview = $bank_import->previewRows( $stashed['csv'], $mapping );
		} catch ( \Throwable $e ) {
			$preview = array();
		}
	}
	?>
	<h2><?php esc_html_e( 'Map Columns', 'bizupkeep-astra-child' ); ?></h2>

	<form method="get" class="bizupkeep-bookkeeping-import-map-form">
		<input type="hidden" name="tab" value="import">
		<input type="hidden" name="step" value="map">
		<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">

		<?php bizupkeep_child_render_bookkeeping_import_column_selects( $headers, $fields ); ?>

		<p>
			<button type="submit" class="bizupkeep-btn bizupkeep-btn-secondary">
				<?php esc_html_e( 'Preview', 'bizupkeep-astra-child' ); ?>
			</button>
		</p>
	</form>

	<?php if ( array() !== $preview ) : ?>
		<h3><?php esc_html_e( 'Preview (first rows)', 'bizupkeep-astra-child' ); ?></h3>
		<table class="bizupkeep-bookkeeping-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'bizupkeep-astra-child' ); ?></th>
					<th><?php esc_html_e( 'Description', 'bizupkeep-astra-child' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'bizupkeep-astra-child' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $preview as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['date'] ); ?></td>
						<td><?php echo esc_html( $row['description'] ); ?></td>
						<td><?php echo esc_html( $row['amount'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<form method="post">
			<?php wp_nonce_field( 'bizupkeep_bookkeeping_import_confirm', 'bizupkeep_bookkeeping_import_confirm_nonce' ); ?>
			<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">
			<?php bizupkeep_child_render_bookkeeping_import_hidden_mapping_fields( $fields ); ?>
			<button type="submit" class="bizupkeep-btn bizupkeep-btn-primary">
				<?php esc_html_e( 'Confirm & Import', 'bizupkeep-astra-child' ); ?>
			</button>
		</form>
	<?php elseif ( null !== $fields ) : ?>
		<p><?php esc_html_e( 'No rows could be parsed with this mapping - please check your column selections.', 'bizupkeep-astra-child' ); ?></p>
	<?php endif; ?>
	<?php
}

/**
 * Review step: NOT wrapped in a <form> itself (a <table> containing
 * per-row mini-forms nested inside an outer form would be invalid
 * HTML) - the bulk-categorize form renders separately, and each
 * checkbox references it via the form="..." attribute instead of DOM
 * nesting.
 */
function bizupkeep_child_render_bookkeeping_import_review_step( Company $company ): void {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return;
	}

	$bank_import = bizhub()->container()->get( BankImportServiceInterface::class );
	$pending     = $bank_import->listPending( $company->getUuid() );

	$upload_url = add_query_arg( array( 'tab' => 'import', 'step' => 'upload', 'company' => $company->getUuid() ), get_permalink() );
	printf(
		'<p><a href="%1$s" class="bizupkeep-btn bizupkeep-btn-secondary">%2$s</a></p>',
		esc_url( $upload_url ),
		esc_html__( 'Upload Another Statement', 'bizupkeep-astra-child' )
	);

	if ( array() === $pending ) {
		echo '<p>' . esc_html__( 'No transactions awaiting review.', 'bizupkeep-astra-child' ) . '</p>';

		return;
	}

	$income_accounts  = bizupkeep_child_bookkeeping_income_accounts( $company->getUuid() );
	$expense_accounts = bizupkeep_child_bookkeeping_expense_accounts( $company->getUuid() );
	$all_accounts     = array_merge( $income_accounts, $expense_accounts );
	$bulk_form_id     = 'bizupkeep-bk-bulk-categorize-form';
	?>
	<h2><?php esc_html_e( 'Review Imported Transactions', 'bizupkeep-astra-child' ); ?></h2>

	<table class="bizupkeep-bookkeeping-table">
		<thead>
			<tr>
				<th></th>
				<th><?php esc_html_e( 'Date', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'Description', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'Amount', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'Category', 'bizupkeep-astra-child' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $pending as $staged ) : ?>
				<?php $candidates = $staged->isIncomeShaped() ? $income_accounts : $expense_accounts; ?>
				<tr>
					<td>
						<input type="checkbox" name="staged[]" value="<?php echo esc_attr( $staged->uuid ); ?>" form="<?php echo esc_attr( $bulk_form_id ); ?>">
					</td>
					<td><?php echo esc_html( $staged->transactionDate->format( 'Y-m-d' ) ); ?></td>
					<td><?php echo esc_html( $staged->description ); ?></td>
					<td><?php echo esc_html( $staged->amount->format() ); ?></td>
					<td>
						<form method="post" style="display:inline;">
							<?php wp_nonce_field( 'bizupkeep_bookkeeping_categorize', 'bizupkeep_bookkeeping_categorize_nonce' ); ?>
							<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">
							<input type="hidden" name="staged" value="<?php echo esc_attr( $staged->uuid ); ?>">
							<select name="category_account">
								<?php foreach ( $candidates as $account ) : ?>
									<option value="<?php echo esc_attr( $account->uuid ); ?>"><?php echo esc_html( $account->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="submit" class="bizupkeep-btn bizupkeep-btn-secondary">
								<?php esc_html_e( 'Categorize', 'bizupkeep-astra-child' ); ?>
							</button>
						</form>
					</td>
					<td>
						<form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Ignore this transaction?', 'bizupkeep-astra-child' ) ); ?>');">
							<?php wp_nonce_field( 'bizupkeep_bookkeeping_ignore', 'bizupkeep_bookkeeping_ignore_nonce' ); ?>
							<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">
							<input type="hidden" name="staged" value="<?php echo esc_attr( $staged->uuid ); ?>">
							<button type="submit" class="bizupkeep-btn bizupkeep-btn-secondary">
								<?php esc_html_e( 'Ignore', 'bizupkeep-astra-child' ); ?>
							</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<form method="post" id="<?php echo esc_attr( $bulk_form_id ); ?>">
		<?php wp_nonce_field( 'bizupkeep_bookkeeping_bulk_categorize', 'bizupkeep_bookkeeping_bulk_categorize_nonce' ); ?>
		<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">
		<p>
			<?php esc_html_e( 'Bulk categorize selected rows as:', 'bizupkeep-astra-child' ); ?>
			<select name="category_account">
				<?php foreach ( $all_accounts as $account ) : ?>
					<option value="<?php echo esc_attr( $account->uuid ); ?>">
						<?php echo esc_html( $account->code . ' - ' . $account->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="bizupkeep-btn bizupkeep-btn-primary">
				<?php esc_html_e( 'Categorize Selected', 'bizupkeep-astra-child' ); ?>
			</button>
		</p>
	</form>
	<?php
}

/*
|--------------------------------------------------------------------------
| Bookkeeping: Recurring Transactions
|--------------------------------------------------------------------------
|
| A client-defined template (e.g. "R2,500 rent, monthly by bank") that
| WP-Cron turns into a pending occurrence each time it comes due
| (RecurringTransactionServiceProvider, bizupkeep-bookkeeping plugin).
| Occurrences never post themselves - a client must confirm (optionally
| editing the amount/date) or skip each one, mirroring the Import tab's
| review-queue pattern exactly. Confirming still delegates to
| TransactionCaptureServiceInterface, the same call Capture/Import
| already make.
*/

/**
 * Render the "Recurring" tab: gated by subscription like Capture/Import
 * (a recurring template is just automated capture), Pending Review
 * shown first since that is the thing needing attention, followed by
 * the list of templates and the new-template form.
 */
function bizupkeep_child_render_bookkeeping_recurring_tab( Company $company ): void {
	if ( ! bizupkeep_child_bookkeeping_subscription_active( $company->getUuid() ) ) {
		bizupkeep_child_render_bookkeeping_subscription_locked_notice( $company );
		return;
	}

	bizupkeep_child_render_bookkeeping_recurring_pending_section( $company );
	bizupkeep_child_render_bookkeeping_recurring_templates_section( $company );
}

function bizupkeep_child_render_bookkeeping_recurring_pending_section( Company $company ): void {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return;
	}

	$recurring = bizhub()->container()->get( RecurringTransactionServiceInterface::class );
	$pending   = $recurring->listPendingOccurrences( $company->getUuid() );

	echo '<h2>' . esc_html__( 'Pending Review', 'bizupkeep-astra-child' ) . '</h2>';

	if ( array() === $pending ) {
		echo '<p>' . esc_html__( 'No recurring transactions awaiting review.', 'bizupkeep-astra-child' ) . '</p>';
		return;
	}

	$templates_by_uuid = array();
	foreach ( $recurring->listTemplates( $company->getUuid() ) as $template ) {
		$templates_by_uuid[ $template->uuid ] = $template;
	}

	$bulk_form_id = 'bizupkeep-bk-recurring-confirm-all-form';
	?>
	<table class="bizupkeep-bookkeeping-table">
		<thead>
			<tr>
				<th></th>
				<th><?php esc_html_e( 'Due Date', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'Description', 'bizupkeep-astra-child' ); ?></th>
				<th><?php esc_html_e( 'Amount', 'bizupkeep-astra-child' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $pending as $occurrence ) : ?>
				<?php $template = $templates_by_uuid[ $occurrence->templateUuid ] ?? null; ?>
				<?php if ( null === $template ) : ?>
					<?php continue; ?>
				<?php endif; ?>
				<tr>
					<td>
						<input type="checkbox" name="occurrence[]" value="<?php echo esc_attr( $occurrence->uuid ); ?>" form="<?php echo esc_attr( $bulk_form_id ); ?>">
					</td>
					<td><?php echo esc_html( $template->description ); ?></td>
					<td>
						<form method="post" style="display:inline;">
							<?php wp_nonce_field( 'bizupkeep_bookkeeping_recurring_confirm', 'bizupkeep_bookkeeping_recurring_confirm_nonce' ); ?>
							<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">
							<input type="hidden" name="occurrence" value="<?php echo esc_attr( $occurrence->uuid ); ?>">
							<input type="date" name="due_date" value="<?php echo esc_attr( $occurrence->dueDate->format( 'Y-m-d' ) ); ?>">
					</td>
					<td>
							<input type="number" step="0.01" min="0.01" name="amount" value="<?php echo esc_attr( number_format( $template->amount->toRands(), 2, '.', '' ) ); ?>">
					</td>
					<td>
							<button type="submit" class="bizupkeep-btn bizupkeep-btn-secondary">
								<?php esc_html_e( 'Confirm & Post', 'bizupkeep-astra-child' ); ?>
							</button>
						</form>
						<form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Skip this occurrence?', 'bizupkeep-astra-child' ) ); ?>');">
							<?php wp_nonce_field( 'bizupkeep_bookkeeping_recurring_skip', 'bizupkeep_bookkeeping_recurring_skip_nonce' ); ?>
							<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">
							<input type="hidden" name="occurrence" value="<?php echo esc_attr( $occurrence->uuid ); ?>">
							<button type="submit" class="bizupkeep-btn bizupkeep-btn-secondary">
								<?php esc_html_e( 'Skip', 'bizupkeep-astra-child' ); ?>
							</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<form method="post" id="<?php echo esc_attr( $bulk_form_id ); ?>">
		<?php wp_nonce_field( 'bizupkeep_bookkeeping_recurring_confirm_all', 'bizupkeep_bookkeeping_recurring_confirm_all_nonce' ); ?>
		<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">
		<p>
			<button type="submit" class="bizupkeep-btn bizupkeep-btn-primary">
				<?php esc_html_e( 'Confirm All Selected As-Is', 'bizupkeep-astra-child' ); ?>
			</button>
		</p>
	</form>
	<?php
}

function bizupkeep_child_render_bookkeeping_recurring_templates_section( Company $company ): void {
	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return;
	}

	$recurring = bizhub()->container()->get( RecurringTransactionServiceInterface::class );
	$templates = $recurring->listTemplates( $company->getUuid() );

	echo '<h2>' . esc_html__( 'Recurring Templates', 'bizupkeep-astra-child' ) . '</h2>';

	if ( array() !== $templates ) :
		?>
		<table class="bizupkeep-bookkeeping-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Type', 'bizupkeep-astra-child' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'bizupkeep-astra-child' ); ?></th>
					<th><?php esc_html_e( 'Description', 'bizupkeep-astra-child' ); ?></th>
					<th><?php esc_html_e( 'Frequency', 'bizupkeep-astra-child' ); ?></th>
					<th><?php esc_html_e( 'Next Due', 'bizupkeep-astra-child' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bizupkeep-astra-child' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $templates as $template ) : ?>
					<tr>
						<td>
							<?php
							echo esc_html(
								BookkeepingTransactionType::Income === $template->transactionType
									? __( 'Income', 'bizupkeep-astra-child' )
									: __( 'Expense', 'bizupkeep-astra-child' )
							);
							?>
						</td>
						<td><?php echo esc_html( $template->amount->format() ); ?></td>
						<td><?php echo esc_html( $template->description ); ?></td>
						<td><?php echo esc_html( $template->frequency->label() ); ?></td>
						<td><?php echo esc_html( $template->nextDueDate->format( 'Y-m-d' ) ); ?></td>
						<td>
							<?php
							echo esc_html(
								$template->isActive
									? __( 'Active', 'bizupkeep-astra-child' )
									: __( 'Paused', 'bizupkeep-astra-child' )
							);
							?>
						</td>
						<td>
							<form method="post" style="display:inline;">
								<?php wp_nonce_field( 'bizupkeep_bookkeeping_recurring_toggle', 'bizupkeep_bookkeeping_recurring_toggle_nonce' ); ?>
								<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">
								<input type="hidden" name="template" value="<?php echo esc_attr( $template->uuid ); ?>">
								<input type="hidden" name="active" value="<?php echo esc_attr( $template->isActive ? '0' : '1' ); ?>">
								<button type="submit" class="bizupkeep-btn bizupkeep-btn-secondary">
									<?php echo esc_html( $template->isActive ? __( 'Pause', 'bizupkeep-astra-child' ) : __( 'Resume', 'bizupkeep-astra-child' ) ); ?>
								</button>
							</form>
							<form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this recurring template? This cannot be undone.', 'bizupkeep-astra-child' ) ); ?>');">
								<?php wp_nonce_field( 'bizupkeep_bookkeeping_recurring_delete', 'bizupkeep_bookkeeping_recurring_delete_nonce' ); ?>
								<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">
								<input type="hidden" name="template" value="<?php echo esc_attr( $template->uuid ); ?>">
								<button type="submit" class="bizupkeep-btn bizupkeep-btn-secondary">
									<?php esc_html_e( 'Delete', 'bizupkeep-astra-child' ); ?>
								</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	endif;

	bizupkeep_child_render_bookkeeping_recurring_new_template_form( $company );
}

function bizupkeep_child_render_bookkeeping_recurring_new_template_form( Company $company ): void {
	$income_accounts  = bizupkeep_child_bookkeeping_income_accounts( $company->getUuid() );
	$expense_accounts = bizupkeep_child_bookkeeping_expense_accounts( $company->getUuid() );
	$vat_registered   = bizupkeep_child_bookkeeping_is_vat_registered( $company->getUuid() );
	?>
	<h3><?php esc_html_e( 'New Recurring Transaction', 'bizupkeep-astra-child' ); ?></h3>
	<form method="post" class="bizupkeep-upload-form bizupkeep-bookkeeping-capture-form">
		<?php wp_nonce_field( 'bizupkeep_bookkeeping_recurring_create', 'bizupkeep_bookkeeping_recurring_create_nonce' ); ?>
		<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">

		<p>
			<label>
				<input type="radio" name="transaction_type" value="income" checked
					onclick="document.getElementById('bizupkeep-bk-rec-income-category').style.display='block';document.getElementById('bizupkeep-bk-rec-expense-category').style.display='none';">
				<?php esc_html_e( 'Income', 'bizupkeep-astra-child' ); ?>
			</label>
			&nbsp;&nbsp;
			<label>
				<input type="radio" name="transaction_type" value="expense"
					onclick="document.getElementById('bizupkeep-bk-rec-income-category').style.display='none';document.getElementById('bizupkeep-bk-rec-expense-category').style.display='block';">
				<?php esc_html_e( 'Expense', 'bizupkeep-astra-child' ); ?>
			</label>
		</p>

		<label for="bizupkeep-bk-rec-amount"><?php esc_html_e( 'Amount (R)', 'bizupkeep-astra-child' ); ?></label>
		<input type="number" step="0.01" min="0.01" id="bizupkeep-bk-rec-amount" name="amount" required>

		<div id="bizupkeep-bk-rec-income-category">
			<label for="bizupkeep-bk-rec-income-select"><?php esc_html_e( 'Category', 'bizupkeep-astra-child' ); ?></label>
			<select id="bizupkeep-bk-rec-income-select" name="category_account">
				<?php foreach ( $income_accounts as $account ) : ?>
					<option value="<?php echo esc_attr( $account->uuid ); ?>"><?php echo esc_html( $account->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div id="bizupkeep-bk-rec-expense-category" style="display:none;">
			<label for="bizupkeep-bk-rec-expense-select"><?php esc_html_e( 'Category', 'bizupkeep-astra-child' ); ?></label>
			<select id="bizupkeep-bk-rec-expense-select" name="category_account">
				<?php foreach ( $expense_accounts as $account ) : ?>
					<option value="<?php echo esc_attr( $account->uuid ); ?>"><?php echo esc_html( $account->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<script>
		// Same visible-select-only-submits pattern the Capture tab already uses.
		(function () {
			var incomeSelect = document.getElementById('bizupkeep-bk-rec-income-select');
			var expenseSelect = document.getElementById('bizupkeep-bk-rec-expense-select');
			function sync() {
				var isIncome = document.querySelector('input[name="transaction_type"]:checked').value === 'income';
				incomeSelect.name = isIncome ? 'category_account' : '';
				expenseSelect.name = isIncome ? '' : 'category_account';
			}
			document.querySelectorAll('input[name="transaction_type"]').forEach(function (el) {
				el.addEventListener('change', sync);
			});
			sync();
		})();
		</script>

		<label for="bizupkeep-bk-rec-payment"><?php esc_html_e( 'Payment Method', 'bizupkeep-astra-child' ); ?></label>
		<select id="bizupkeep-bk-rec-payment" name="payment_method">
			<option value="bank"><?php esc_html_e( 'Bank', 'bizupkeep-astra-child' ); ?></option>
			<option value="cash"><?php esc_html_e( 'Cash', 'bizupkeep-astra-child' ); ?></option>
		</select>

		<label for="bizupkeep-bk-rec-description"><?php esc_html_e( 'Description', 'bizupkeep-astra-child' ); ?></label>
		<input type="text" id="bizupkeep-bk-rec-description" name="description" maxlength="500" required>

		<?php if ( $vat_registered ) : ?>
			<p>
				<label>
					<input type="checkbox" name="includes_vat" value="1">
					<?php esc_html_e( 'This amount includes VAT (15%)', 'bizupkeep-astra-child' ); ?>
				</label>
			</p>
		<?php endif; ?>

		<label for="bizupkeep-bk-rec-frequency"><?php esc_html_e( 'Frequency', 'bizupkeep-astra-child' ); ?></label>
		<select id="bizupkeep-bk-rec-frequency" name="frequency">
			<?php foreach ( RecurringFrequency::cases() as $frequency ) : ?>
				<option value="<?php echo esc_attr( $frequency->value ); ?>" <?php selected( RecurringFrequency::Monthly->value, $frequency->value ); ?>>
					<?php echo esc_html( $frequency->label() ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label for="bizupkeep-bk-rec-start-date"><?php esc_html_e( 'Start Date', 'bizupkeep-astra-child' ); ?></label>
		<input type="date" id="bizupkeep-bk-rec-start-date" name="start_date" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" required>

		<p>
			<button type="submit" class="bizupkeep-btn bizupkeep-btn-primary">
				<?php esc_html_e( 'Create Recurring Transaction', 'bizupkeep-astra-child' ); ?>
			</button>
		</p>
	</form>
	<?php
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_recurring_create' );

/**
 * Handle the "New Recurring Transaction" form POST.
 */
function bizupkeep_child_handle_bookkeeping_recurring_create(): void {
	if ( ! isset( $_POST['bizupkeep_bookkeeping_recurring_create_nonce'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	$wp_user_id = get_current_user_id();

	if ( 0 === $wp_user_id ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_bookkeeping_recurring_create', 'bizupkeep_bookkeeping_recurring_create_nonce' );

	$company_uuid  = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$company       = bizupkeep_child_get_owned_company( $wp_user_id, $company_uuid );
	$redirect_base = add_query_arg( array( 'tab' => 'recurring', 'company' => $company_uuid ), get_permalink() );

	if ( null === $company || ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( add_query_arg( 'recurring_error', '1', $redirect_base ) );
		exit;
	}

	$type_raw       = isset( $_POST['transaction_type'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_type'] ) ) : '';
	$amount_raw     = isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : '0';
	$category       = isset( $_POST['category_account'] ) ? sanitize_text_field( wp_unslash( $_POST['category_account'] ) ) : '';
	$payment_raw    = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : '';
	$description    = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
	$frequency_raw  = isset( $_POST['frequency'] ) ? sanitize_text_field( wp_unslash( $_POST['frequency'] ) ) : 'monthly';
	$start_date_raw = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';

	// Nonce already verified above; a checkbox's mere presence is the only signal needed here.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$includes_vat = isset( $_POST['includes_vat'] ) && bizupkeep_child_bookkeeping_is_vat_registered( $company->getUuid() );

	$payment_method    = 'cash' === $payment_raw ? BookkeepingPaymentMethod::Cash : BookkeepingPaymentMethod::Bank;
	$transaction_type  = 'income' === $type_raw ? BookkeepingTransactionType::Income : BookkeepingTransactionType::Expense;

	try {
		$frequency  = RecurringFrequency::from( $frequency_raw );
		$start_date = '' !== $start_date_raw ? new \DateTimeImmutable( $start_date_raw ) : new \DateTimeImmutable();
	} catch ( \Throwable $e ) {
		wp_safe_redirect( add_query_arg( 'recurring_error', '1', $redirect_base ) );
		exit;
	}

	try {
		bizhub()->container()->get( RecurringTransactionServiceInterface::class )->createTemplate(
			$company->getUuid(),
			$transaction_type,
			\BizHub\Bookkeeping\Support\Money::fromRands( (float) $amount_raw ),
			$category,
			$payment_method,
			$description,
			$includes_vat,
			$frequency,
			$start_date
		);

		wp_safe_redirect( add_query_arg( 'recurring_created', '1', $redirect_base ) );
	} catch ( BookkeepingException $e ) {
		wp_safe_redirect( add_query_arg( 'recurring_error', '1', $redirect_base ) );
	}

	exit;
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_recurring_confirm' );

/**
 * Confirm a single pending occurrence, applying any amount/date edits
 * the client made in the Pending Review row.
 */
function bizupkeep_child_handle_bookkeeping_recurring_confirm(): void {
	if ( ! isset( $_POST['bizupkeep_bookkeeping_recurring_confirm_nonce'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	$wp_user_id = get_current_user_id();

	if ( 0 === $wp_user_id ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_bookkeeping_recurring_confirm', 'bizupkeep_bookkeeping_recurring_confirm_nonce' );

	$company_uuid  = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$company       = bizupkeep_child_get_owned_company( $wp_user_id, $company_uuid );
	$redirect_base = add_query_arg( array( 'tab' => 'recurring', 'company' => $company_uuid ), get_permalink() );

	if ( null === $company || ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( add_query_arg( 'recurring_error', '1', $redirect_base ) );
		exit;
	}

	$occurrence_uuid = isset( $_POST['occurrence'] ) ? sanitize_text_field( wp_unslash( $_POST['occurrence'] ) ) : '';
	$amount_raw      = isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : '';
	$due_date_raw    = isset( $_POST['due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['due_date'] ) ) : '';

	$override_amount = '' !== $amount_raw ? \BizHub\Bookkeeping\Support\Money::fromRands( (float) $amount_raw ) : null;

	try {
		$override_date = '' !== $due_date_raw ? new \DateTimeImmutable( $due_date_raw ) : null;
	} catch ( \Throwable $e ) {
		$override_date = null;
	}

	try {
		bizhub()->container()->get( RecurringTransactionServiceInterface::class )
			->confirmOccurrence( $company->getUuid(), $occurrence_uuid, $wp_user_id, $override_amount, $override_date );

		wp_safe_redirect( add_query_arg( 'recurring_confirmed', '1', $redirect_base ) );
	} catch ( BookkeepingException $e ) {
		wp_safe_redirect( add_query_arg( 'recurring_error', '1', $redirect_base ) );
	}

	exit;
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_recurring_confirm_all' );

/**
 * Confirm every checked pending occurrence as-is (no amount/date
 * overrides) - one bad row must never lose the rest of the batch,
 * matching bulk_categorize()'s per-item resilience.
 */
function bizupkeep_child_handle_bookkeeping_recurring_confirm_all(): void {
	if ( ! isset( $_POST['bizupkeep_bookkeeping_recurring_confirm_all_nonce'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	$wp_user_id = get_current_user_id();

	if ( 0 === $wp_user_id ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_bookkeeping_recurring_confirm_all', 'bizupkeep_bookkeeping_recurring_confirm_all_nonce' );

	$company_uuid  = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$company       = bizupkeep_child_get_owned_company( $wp_user_id, $company_uuid );
	$redirect_base = add_query_arg( array( 'tab' => 'recurring', 'company' => $company_uuid ), get_permalink() );

	if ( null === $company || ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( add_query_arg( 'recurring_error', '1', $redirect_base ) );
		exit;
	}

	// Nonce verified above; the array is only ever read as sanitized string UUIDs below.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$occurrence_uuids = isset( $_POST['occurrence'] ) && is_array( $_POST['occurrence'] )
		? array_map( 'sanitize_text_field', wp_unslash( $_POST['occurrence'] ) )
		: array();

	if ( array() === $occurrence_uuids ) {
		wp_safe_redirect( add_query_arg( 'recurring_error', '1', $redirect_base ) );
		exit;
	}

	$recurring = bizhub()->container()->get( RecurringTransactionServiceInterface::class );

	foreach ( $occurrence_uuids as $occurrence_uuid ) {
		try {
			$recurring->confirmOccurrence( $company->getUuid(), $occurrence_uuid, $wp_user_id );
		} catch ( BookkeepingException $e ) {
			continue;
		}
	}

	wp_safe_redirect( add_query_arg( 'recurring_confirmed', '1', $redirect_base ) );
	exit;
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_recurring_skip' );

/**
 * Mark a pending occurrence skipped without ever posting it.
 */
function bizupkeep_child_handle_bookkeeping_recurring_skip(): void {
	if ( ! isset( $_POST['bizupkeep_bookkeeping_recurring_skip_nonce'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	$wp_user_id = get_current_user_id();

	if ( 0 === $wp_user_id ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_bookkeeping_recurring_skip', 'bizupkeep_bookkeeping_recurring_skip_nonce' );

	$company_uuid  = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$company       = bizupkeep_child_get_owned_company( $wp_user_id, $company_uuid );
	$redirect_base = add_query_arg( array( 'tab' => 'recurring', 'company' => $company_uuid ), get_permalink() );

	if ( null === $company || ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( add_query_arg( 'recurring_error', '1', $redirect_base ) );
		exit;
	}

	$occurrence_uuid = isset( $_POST['occurrence'] ) ? sanitize_text_field( wp_unslash( $_POST['occurrence'] ) ) : '';

	try {
		bizhub()->container()->get( RecurringTransactionServiceInterface::class )
			->skipOccurrence( $company->getUuid(), $occurrence_uuid );
		wp_safe_redirect( add_query_arg( 'recurring_skipped', '1', $redirect_base ) );
	} catch ( BookkeepingException $e ) {
		wp_safe_redirect( add_query_arg( 'recurring_error', '1', $redirect_base ) );
	}

	exit;
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_recurring_toggle' );

/**
 * Pause or resume a template - one handler for both, driven by the
 * hidden "active" field the template row's own form sets to the
 * opposite of its current state.
 */
function bizupkeep_child_handle_bookkeeping_recurring_toggle(): void {
	if ( ! isset( $_POST['bizupkeep_bookkeeping_recurring_toggle_nonce'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	$wp_user_id = get_current_user_id();

	if ( 0 === $wp_user_id ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_bookkeeping_recurring_toggle', 'bizupkeep_bookkeeping_recurring_toggle_nonce' );

	$company_uuid  = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$company       = bizupkeep_child_get_owned_company( $wp_user_id, $company_uuid );
	$redirect_base = add_query_arg( array( 'tab' => 'recurring', 'company' => $company_uuid ), get_permalink() );

	if ( null === $company || ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( add_query_arg( 'recurring_error', '1', $redirect_base ) );
		exit;
	}

	$template_uuid = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '';
	$make_active   = isset( $_POST['active'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['active'] ) );

	try {
		$recurring = bizhub()->container()->get( RecurringTransactionServiceInterface::class );

		if ( $make_active ) {
			$recurring->resumeTemplate( $company->getUuid(), $template_uuid );
		} else {
			$recurring->pauseTemplate( $company->getUuid(), $template_uuid );
		}

		wp_safe_redirect( add_query_arg( 'recurring_template_updated', '1', $redirect_base ) );
	} catch ( BookkeepingException $e ) {
		wp_safe_redirect( add_query_arg( 'recurring_error', '1', $redirect_base ) );
	}

	exit;
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_recurring_delete' );

/**
 * Delete a recurring template outright - unlike a journal entry, a
 * template is a scheduling convenience, not an audit record.
 */
function bizupkeep_child_handle_bookkeeping_recurring_delete(): void {
	if ( ! isset( $_POST['bizupkeep_bookkeeping_recurring_delete_nonce'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	$wp_user_id = get_current_user_id();

	if ( 0 === $wp_user_id ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_bookkeeping_recurring_delete', 'bizupkeep_bookkeeping_recurring_delete_nonce' );

	$company_uuid  = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$company       = bizupkeep_child_get_owned_company( $wp_user_id, $company_uuid );
	$redirect_base = add_query_arg( array( 'tab' => 'recurring', 'company' => $company_uuid ), get_permalink() );

	if ( null === $company || ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( add_query_arg( 'recurring_error', '1', $redirect_base ) );
		exit;
	}

	$template_uuid = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '';

	try {
		bizhub()->container()->get( RecurringTransactionServiceInterface::class )
			->deleteTemplate( $company->getUuid(), $template_uuid );

		wp_safe_redirect( add_query_arg( 'recurring_template_updated', '1', $redirect_base ) );
	} catch ( BookkeepingException $e ) {
		wp_safe_redirect( add_query_arg( 'recurring_error', '1', $redirect_base ) );
	}

	exit;
}

/*
|--------------------------------------------------------------------------
| Bookkeeping: Customers
|--------------------------------------------------------------------------
|
| A client's own customer - the party they invoice - entirely distinct
| from BizHub's own Client/Company entities. Never a WP user, never
| logs into any portal.
*/

/**
 * Render the "Customers" tab: a list of the company's own customers
 * plus a create/edit form. Gated by subscription like Capture/Recurring
 * - invoicing is still paid capture functionality.
 */
function bizupkeep_child_render_bookkeeping_customers_tab( Company $company ): void {
	if ( ! bizupkeep_child_bookkeeping_subscription_active( $company->getUuid() ) ) {
		bizupkeep_child_render_bookkeeping_subscription_locked_notice( $company );
		return;
	}

	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return;
	}

	$invoicing = bizhub()->container()->get( InvoiceServiceInterface::class );
	$customers = $invoicing->listCustomers( $company->getUuid() );

	$edit_uuid = isset( $_GET['edit_customer'] ) ? sanitize_text_field( wp_unslash( $_GET['edit_customer'] ) ) : '';
	$editing   = null;

	if ( '' !== $edit_uuid ) {
		foreach ( $customers as $customer ) {
			if ( $customer->uuid === $edit_uuid ) {
				$editing = $customer;
			}
		}
	}

	if ( array() !== $customers ) {
		?>
		<h2><?php esc_html_e( 'Customers', 'bizupkeep-astra-child' ); ?></h2>
		<table class="bizupkeep-bookkeeping-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'bizupkeep-astra-child' ); ?></th>
					<th><?php esc_html_e( 'Email', 'bizupkeep-astra-child' ); ?></th>
					<th><?php esc_html_e( 'Phone', 'bizupkeep-astra-child' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $customers as $customer ) : ?>
					<tr>
						<td><?php echo esc_html( $customer->name ); ?></td>
						<td><?php echo esc_html( $customer->email ); ?></td>
						<td><?php echo esc_html( $customer->phone ); ?></td>
						<td>
							<a class="bizupkeep-btn bizupkeep-btn-secondary" href="<?php echo esc_url( add_query_arg( array( 'tab' => 'customers', 'company' => $company->getUuid(), 'edit_customer' => $customer->uuid ), get_permalink() ) ); ?>">
								<?php esc_html_e( 'Edit', 'bizupkeep-astra-child' ); ?>
							</a>
							<a class="bizupkeep-btn bizupkeep-btn-secondary" href="<?php echo esc_url( add_query_arg( array( 'tab' => 'customers', 'company' => $company->getUuid(), 'bizupkeep_bookkeeping_statement_customer' => $customer->uuid ), get_permalink() ) ); ?>">
								<?php esc_html_e( 'Statement', 'bizupkeep-astra-child' ); ?>
							</a>
							<form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this customer?', 'bizupkeep-astra-child' ) ); ?>');">
								<?php wp_nonce_field( 'bizupkeep_bookkeeping_customer_delete', 'bizupkeep_bookkeeping_customer_delete_nonce' ); ?>
								<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">
								<input type="hidden" name="customer" value="<?php echo esc_attr( $customer->uuid ); ?>">
								<button type="submit" class="bizupkeep-btn bizupkeep-btn-secondary"><?php esc_html_e( 'Delete', 'bizupkeep-astra-child' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
	?>

	<h3><?php echo esc_html( null !== $editing ? __( 'Edit Customer', 'bizupkeep-astra-child' ) : __( 'New Customer', 'bizupkeep-astra-child' ) ); ?></h3>
	<form method="post" class="bizupkeep-upload-form">
		<?php wp_nonce_field( 'bizupkeep_bookkeeping_customer_save', 'bizupkeep_bookkeeping_customer_save_nonce' ); ?>
		<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">
		<?php if ( null !== $editing ) : ?>
			<input type="hidden" name="customer" value="<?php echo esc_attr( $editing->uuid ); ?>">
		<?php endif; ?>

		<label for="bizupkeep-bk-cust-name"><?php esc_html_e( 'Name', 'bizupkeep-astra-child' ); ?></label>
		<input type="text" id="bizupkeep-bk-cust-name" name="customer_name" value="<?php echo esc_attr( $editing->name ?? '' ); ?>" required>

		<label for="bizupkeep-bk-cust-email"><?php esc_html_e( 'Email', 'bizupkeep-astra-child' ); ?></label>
		<input type="email" id="bizupkeep-bk-cust-email" name="email" value="<?php echo esc_attr( $editing->email ?? '' ); ?>">

		<label for="bizupkeep-bk-cust-phone"><?php esc_html_e( 'Phone', 'bizupkeep-astra-child' ); ?></label>
		<input type="text" id="bizupkeep-bk-cust-phone" name="phone" value="<?php echo esc_attr( $editing->phone ?? '' ); ?>">

		<label for="bizupkeep-bk-cust-address1"><?php esc_html_e( 'Address Line 1', 'bizupkeep-astra-child' ); ?></label>
		<input type="text" id="bizupkeep-bk-cust-address1" name="address_line_1" value="<?php echo esc_attr( $editing->addressLine1 ?? '' ); ?>">

		<label for="bizupkeep-bk-cust-address2"><?php esc_html_e( 'Address Line 2', 'bizupkeep-astra-child' ); ?></label>
		<input type="text" id="bizupkeep-bk-cust-address2" name="address_line_2" value="<?php echo esc_attr( $editing->addressLine2 ?? '' ); ?>">

		<label for="bizupkeep-bk-cust-suburb"><?php esc_html_e( 'Suburb', 'bizupkeep-astra-child' ); ?></label>
		<input type="text" id="bizupkeep-bk-cust-suburb" name="suburb" value="<?php echo esc_attr( $editing->suburb ?? '' ); ?>">

		<label for="bizupkeep-bk-cust-city"><?php esc_html_e( 'City', 'bizupkeep-astra-child' ); ?></label>
		<input type="text" id="bizupkeep-bk-cust-city" name="city" value="<?php echo esc_attr( $editing->city ?? '' ); ?>">

		<label for="bizupkeep-bk-cust-province"><?php esc_html_e( 'Province', 'bizupkeep-astra-child' ); ?></label>
		<input type="text" id="bizupkeep-bk-cust-province" name="province" value="<?php echo esc_attr( $editing->province ?? '' ); ?>">

		<label for="bizupkeep-bk-cust-postal"><?php esc_html_e( 'Postal Code', 'bizupkeep-astra-child' ); ?></label>
		<input type="text" id="bizupkeep-bk-cust-postal" name="postal_code" value="<?php echo esc_attr( $editing->postalCode ?? '' ); ?>">

		<p>
			<button type="submit" class="bizupkeep-btn bizupkeep-btn-primary">
				<?php echo esc_html( null !== $editing ? __( 'Save Changes', 'bizupkeep-astra-child' ) : __( 'Create Customer', 'bizupkeep-astra-child' ) ); ?>
			</button>
		</p>
	</form>
	<?php
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_customer_save' );

/**
 * Handle the "New/Edit Customer" form POST - creates or updates
 * depending on whether a "customer" UUID was submitted.
 */
function bizupkeep_child_handle_bookkeeping_customer_save(): void {
	if ( ! isset( $_POST['bizupkeep_bookkeeping_customer_save_nonce'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_bookkeeping_customer_save', 'bizupkeep_bookkeeping_customer_save_nonce' );

	$company_uuid  = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$company       = bizupkeep_child_get_owned_company( get_current_user_id(), $company_uuid );
	$redirect_base = add_query_arg( array( 'tab' => 'customers', 'company' => $company_uuid ), get_permalink() );

	if ( null === $company || ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( add_query_arg( 'customer_error', '1', $redirect_base ) );
		exit;
	}

	$customer_uuid = isset( $_POST['customer'] ) ? sanitize_text_field( wp_unslash( $_POST['customer'] ) ) : '';
	// 'name' is a reserved WordPress public query var (WP::parse_request() reads it from $_POST too,
	// for ?name=post-slug permalink lookups) - a POST field literally called "name" causes WordPress's
	// own routing to 404 the request before it ever reaches this handler. Field is "customer_name" instead.
	$name          = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
	$email         = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$phone         = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$address_1     = isset( $_POST['address_line_1'] ) ? sanitize_text_field( wp_unslash( $_POST['address_line_1'] ) ) : '';
	$address_2     = isset( $_POST['address_line_2'] ) ? sanitize_text_field( wp_unslash( $_POST['address_line_2'] ) ) : '';
	$suburb        = isset( $_POST['suburb'] ) ? sanitize_text_field( wp_unslash( $_POST['suburb'] ) ) : '';
	$city          = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
	$province      = isset( $_POST['province'] ) ? sanitize_text_field( wp_unslash( $_POST['province'] ) ) : '';
	$postal_code   = isset( $_POST['postal_code'] ) ? sanitize_text_field( wp_unslash( $_POST['postal_code'] ) ) : '';

	$invoicing = bizhub()->container()->get( InvoiceServiceInterface::class );

	try {
		if ( '' !== $customer_uuid ) {
			$invoicing->updateCustomer( $company->getUuid(), $customer_uuid, $name, $email, $phone, $address_1, $address_2, $suburb, $city, $province, $postal_code );
		} else {
			$invoicing->createCustomer( $company->getUuid(), $name, $email, $phone, $address_1, $address_2, $suburb, $city, $province, $postal_code );
		}

		wp_safe_redirect( add_query_arg( 'customer_saved', '1', $redirect_base ) );
	} catch ( BookkeepingException $e ) {
		wp_safe_redirect( add_query_arg( 'customer_error', '1', $redirect_base ) );
	}

	exit;
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_customer_delete' );

/**
 * Handle deleting a customer.
 */
function bizupkeep_child_handle_bookkeeping_customer_delete(): void {
	if ( ! isset( $_POST['bizupkeep_bookkeeping_customer_delete_nonce'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_bookkeeping_customer_delete', 'bizupkeep_bookkeeping_customer_delete_nonce' );

	$company_uuid  = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$company       = bizupkeep_child_get_owned_company( get_current_user_id(), $company_uuid );
	$redirect_base = add_query_arg( array( 'tab' => 'customers', 'company' => $company_uuid ), get_permalink() );

	if ( null === $company || ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( add_query_arg( 'customer_error', '1', $redirect_base ) );
		exit;
	}

	$customer_uuid = isset( $_POST['customer'] ) ? sanitize_text_field( wp_unslash( $_POST['customer'] ) ) : '';

	try {
		bizhub()->container()->get( InvoiceServiceInterface::class )->deleteCustomer( $company->getUuid(), $customer_uuid );
		wp_safe_redirect( add_query_arg( 'customer_deleted', '1', $redirect_base ) );
	} catch ( BookkeepingException $e ) {
		wp_safe_redirect( add_query_arg( 'customer_error', '1', $redirect_base ) );
	}

	exit;
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_statement_download' );

/**
 * Stream a customer's statement of account as a PDF - GET request, no
 * side effects, same streaming pattern as
 * bizupkeep_child_handle_bookkeeping_export_request().
 */
function bizupkeep_child_handle_bookkeeping_statement_download(): void {
	if ( ! isset( $_GET['bizupkeep_bookkeeping_statement_customer'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( get_permalink() );
		exit;
	}

	$company_uuid = isset( $_GET['company'] ) ? sanitize_text_field( wp_unslash( $_GET['company'] ) ) : '';
	$company      = bizupkeep_child_get_owned_company( get_current_user_id(), $company_uuid );

	if ( null === $company || ! bizupkeep_child_bookkeeping_subscription_active( $company->getUuid() ) ) {
		wp_safe_redirect( add_query_arg( array( 'tab' => 'customers', 'customer_error' => '1' ), get_permalink() ) );
		exit;
	}

	$customer_uuid = sanitize_text_field( wp_unslash( $_GET['bizupkeep_bookkeeping_statement_customer'] ) );
	$range         = bizupkeep_child_bookkeeping_parse_range();

	try {
		$pdf = bizhub()->container()->get( InvoiceServiceInterface::class )
			->generateStatementPdf( $company->getUuid(), $customer_uuid, $range );
	} catch ( BookkeepingException $e ) {
		wp_safe_redirect( add_query_arg( array( 'tab' => 'customers', 'customer_error' => '1' ), get_permalink() ) );
		exit;
	}

	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="statement-' . sanitize_file_name( gmdate( 'Y-m-d' ) ) . '.pdf"' );
	header( 'Content-Length: ' . strlen( $pdf ) );

	echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw PDF file content, not HTML.
	exit;
}

/*
|--------------------------------------------------------------------------
| Bookkeeping: Invoices
|--------------------------------------------------------------------------
|
| Invoicing a client's own customer. Sending an invoice or recording a
| payment always delegates to InvoiceServiceInterface, which posts via
| LedgerServiceInterface directly (a genuinely different economic event
| shape - AR-based recognition - than the simplified "capture" the
| Capture/Recurring tabs use), never through TransactionCaptureService.
*/

/**
 * Fixed number of line-item rows the "New Invoice" form renders -
 * mirrors ManualJournalEntryPage::MAX_LINES's exact reasoning: a small
 * number of monthly line items doesn't justify a dynamic JS add/remove-
 * row widget, unused rows are simply left blank and ignored on submit.
 */
const BIZUPKEEP_BOOKKEEPING_INVOICE_MAX_LINES = 8;

/**
 * Render the "Invoices" tab: a list of invoices with per-row actions,
 * followed by the "New Invoice" form. Gated by subscription like
 * Capture/Recurring.
 */
function bizupkeep_child_render_bookkeeping_invoices_tab( Company $company ): void {
	if ( ! bizupkeep_child_bookkeeping_subscription_active( $company->getUuid() ) ) {
		bizupkeep_child_render_bookkeeping_subscription_locked_notice( $company );
		return;
	}

	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		return;
	}

	$invoicing = bizhub()->container()->get( InvoiceServiceInterface::class );
	$invoices  = $invoicing->listInvoices( $company->getUuid() );

	$customers_by_uuid = array();
	foreach ( $invoicing->listCustomers( $company->getUuid() ) as $customer ) {
		$customers_by_uuid[ $customer->uuid ] = $customer;
	}

	if ( array() !== $invoices ) {
		?>
		<h2><?php esc_html_e( 'Invoices', 'bizupkeep-astra-child' ); ?></h2>
		<table class="bizupkeep-bookkeeping-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Number', 'bizupkeep-astra-child' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'bizupkeep-astra-child' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bizupkeep-astra-child' ); ?></th>
					<th><?php esc_html_e( 'Total', 'bizupkeep-astra-child' ); ?></th>
					<th><?php esc_html_e( 'Due', 'bizupkeep-astra-child' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $invoices as $invoice ) : ?>
					<?php $customer = $customers_by_uuid[ $invoice->customerUuid ] ?? null; ?>
					<tr>
						<td><?php echo esc_html( $invoice->invoiceNumber ); ?></td>
						<td><?php echo esc_html( null !== $customer ? $customer->name : '' ); ?></td>
						<td><?php echo esc_html( $invoice->status->label() ); ?></td>
						<td><?php echo esc_html( $invoice->total->format() ); ?></td>
						<td><?php echo esc_html( $invoice->dueDate->format( 'Y-m-d' ) ); ?></td>
						<td>
							<a class="bizupkeep-btn bizupkeep-btn-secondary" href="<?php echo esc_url( add_query_arg( array( 'tab' => 'invoices', 'company' => $company->getUuid(), 'bizupkeep_bookkeeping_invoice_pdf' => $invoice->uuid ), get_permalink() ) ); ?>">
								<?php esc_html_e( 'PDF', 'bizupkeep-astra-child' ); ?>
							</a>
							<?php if ( 'draft' === $invoice->status->value ) : ?>
								<form method="post" style="display:inline;">
									<?php wp_nonce_field( 'bizupkeep_bookkeeping_invoice_send', 'bizupkeep_bookkeeping_invoice_send_nonce' ); ?>
									<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">
									<input type="hidden" name="invoice" value="<?php echo esc_attr( $invoice->uuid ); ?>">
									<button type="submit" class="bizupkeep-btn bizupkeep-btn-secondary"><?php esc_html_e( 'Send', 'bizupkeep-astra-child' ); ?></button>
								</form>
								<form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Void this draft invoice?', 'bizupkeep-astra-child' ) ); ?>');">
									<?php wp_nonce_field( 'bizupkeep_bookkeeping_invoice_void', 'bizupkeep_bookkeeping_invoice_void_nonce' ); ?>
									<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">
									<input type="hidden" name="invoice" value="<?php echo esc_attr( $invoice->uuid ); ?>">
									<button type="submit" class="bizupkeep-btn bizupkeep-btn-secondary"><?php esc_html_e( 'Void', 'bizupkeep-astra-child' ); ?></button>
								</form>
							<?php elseif ( 'sent' === $invoice->status->value ) : ?>
								<form method="post" style="display:inline;">
									<?php wp_nonce_field( 'bizupkeep_bookkeeping_invoice_resend', 'bizupkeep_bookkeeping_invoice_resend_nonce' ); ?>
									<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">
									<input type="hidden" name="invoice" value="<?php echo esc_attr( $invoice->uuid ); ?>">
									<button type="submit" class="bizupkeep-btn bizupkeep-btn-secondary"><?php esc_html_e( 'Resend', 'bizupkeep-astra-child' ); ?></button>
								</form>
								<form method="post" style="display:inline;">
									<?php wp_nonce_field( 'bizupkeep_bookkeeping_invoice_pay', 'bizupkeep_bookkeeping_invoice_pay_nonce' ); ?>
									<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">
									<input type="hidden" name="invoice" value="<?php echo esc_attr( $invoice->uuid ); ?>">
									<select name="payment_method">
										<option value="bank"><?php esc_html_e( 'Bank', 'bizupkeep-astra-child' ); ?></option>
										<option value="cash"><?php esc_html_e( 'Cash', 'bizupkeep-astra-child' ); ?></option>
									</select>
									<button type="submit" class="bizupkeep-btn bizupkeep-btn-secondary"><?php esc_html_e( 'Record Payment', 'bizupkeep-astra-child' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	bizupkeep_child_render_bookkeeping_invoice_create_form( $company, $invoicing );
}

function bizupkeep_child_render_bookkeeping_invoice_create_form( Company $company, InvoiceServiceInterface $invoicing ): void {
	$customers = $invoicing->listCustomers( $company->getUuid() );

	if ( array() === $customers ) {
		echo '<h3>' . esc_html__( 'New Invoice', 'bizupkeep-astra-child' ) . '</h3>';
		echo '<p>' . esc_html__( 'Add a customer first before creating an invoice.', 'bizupkeep-astra-child' ) . '</p>';
		return;
	}

	$income_accounts = bizupkeep_child_bookkeeping_income_accounts( $company->getUuid() );
	$vat_registered  = bizupkeep_child_bookkeeping_is_vat_registered( $company->getUuid() );
	?>
	<h3><?php esc_html_e( 'New Invoice', 'bizupkeep-astra-child' ); ?></h3>
	<form method="post" class="bizupkeep-upload-form">
		<?php wp_nonce_field( 'bizupkeep_bookkeeping_invoice_create', 'bizupkeep_bookkeeping_invoice_create_nonce' ); ?>
		<input type="hidden" name="company" value="<?php echo esc_attr( $company->getUuid() ); ?>">

		<label for="bizupkeep-bk-inv-customer"><?php esc_html_e( 'Customer', 'bizupkeep-astra-child' ); ?></label>
		<select id="bizupkeep-bk-inv-customer" name="customer">
			<?php foreach ( $customers as $customer ) : ?>
				<option value="<?php echo esc_attr( $customer->uuid ); ?>"><?php echo esc_html( $customer->name ); ?></option>
			<?php endforeach; ?>
		</select>

		<label for="bizupkeep-bk-inv-category"><?php esc_html_e( 'Revenue Category', 'bizupkeep-astra-child' ); ?></label>
		<select id="bizupkeep-bk-inv-category" name="category_account">
			<?php foreach ( $income_accounts as $account ) : ?>
				<option value="<?php echo esc_attr( $account->uuid ); ?>"><?php echo esc_html( $account->name ); ?></option>
			<?php endforeach; ?>
		</select>

		<label for="bizupkeep-bk-inv-date"><?php esc_html_e( 'Invoice Date', 'bizupkeep-astra-child' ); ?></label>
		<input type="date" id="bizupkeep-bk-inv-date" name="invoice_date" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" required>

		<label for="bizupkeep-bk-inv-due"><?php esc_html_e( 'Due Date', 'bizupkeep-astra-child' ); ?></label>
		<input type="date" id="bizupkeep-bk-inv-due" name="due_date" value="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+14 days' ) ) ); ?>" required>

		<?php if ( $vat_registered ) : ?>
			<p>
				<label>
					<input type="checkbox" name="includes_vat" value="1">
					<?php esc_html_e( 'Line prices include VAT (15%)', 'bizupkeep-astra-child' ); ?>
				</label>
			</p>
		<?php endif; ?>

		<label for="bizupkeep-bk-inv-notes"><?php esc_html_e( 'Notes', 'bizupkeep-astra-child' ); ?></label>
		<input type="text" id="bizupkeep-bk-inv-notes" name="notes" maxlength="500">

		<table class="bizupkeep-bookkeeping-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Description', 'bizupkeep-astra-child' ); ?></th>
					<th><?php esc_html_e( 'Qty', 'bizupkeep-astra-child' ); ?></th>
					<th><?php esc_html_e( 'Unit Price (R)', 'bizupkeep-astra-child' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php for ( $i = 0; $i < BIZUPKEEP_BOOKKEEPING_INVOICE_MAX_LINES; $i++ ) : ?>
					<tr>
						<td><input type="text" name="lines[<?php echo esc_attr( (string) $i ); ?>][description]" size="30"></td>
						<td><input type="number" min="1" step="1" name="lines[<?php echo esc_attr( (string) $i ); ?>][quantity]" value="1" size="4"></td>
						<td><input type="number" min="0" step="0.01" name="lines[<?php echo esc_attr( (string) $i ); ?>][unit_price]" size="10"></td>
					</tr>
				<?php endfor; ?>
			</tbody>
		</table>

		<p>
			<button type="submit" class="bizupkeep-btn bizupkeep-btn-primary">
				<?php esc_html_e( 'Create Draft Invoice', 'bizupkeep-astra-child' ); ?>
			</button>
		</p>
	</form>
	<?php
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_invoice_create' );

/**
 * Handle the "New Invoice" form POST - builds InvoiceLineInput[] from
 * the fixed set of line rows, skipping any left blank.
 */
function bizupkeep_child_handle_bookkeeping_invoice_create(): void {
	if ( ! isset( $_POST['bizupkeep_bookkeeping_invoice_create_nonce'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_bookkeeping_invoice_create', 'bizupkeep_bookkeeping_invoice_create_nonce' );

	$company_uuid  = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$company       = bizupkeep_child_get_owned_company( get_current_user_id(), $company_uuid );
	$redirect_base = add_query_arg( array( 'tab' => 'invoices', 'company' => $company_uuid ), get_permalink() );

	if ( null === $company || ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( add_query_arg( 'invoice_error', '1', $redirect_base ) );
		exit;
	}

	$customer_uuid = isset( $_POST['customer'] ) ? sanitize_text_field( wp_unslash( $_POST['customer'] ) ) : '';
	$category      = isset( $_POST['category_account'] ) ? sanitize_text_field( wp_unslash( $_POST['category_account'] ) ) : '';
	$notes         = isset( $_POST['notes'] ) ? sanitize_text_field( wp_unslash( $_POST['notes'] ) ) : '';
	$vat_registered = bizupkeep_child_bookkeeping_is_vat_registered( $company_uuid );

	// Nonce already verified above; a checkbox's mere presence is the only signal needed here.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$includes_vat = isset( $_POST['includes_vat'] ) && $vat_registered;

	try {
		$invoice_date = new \DateTimeImmutable( isset( $_POST['invoice_date'] ) ? sanitize_text_field( wp_unslash( $_POST['invoice_date'] ) ) : 'now' );
		$due_date     = new \DateTimeImmutable( isset( $_POST['due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['due_date'] ) ) : 'now' );
	} catch ( \Exception $e ) {
		wp_safe_redirect( add_query_arg( 'invoice_error', '1', $redirect_base ) );
		exit;
	}

	// Nonce already verified above; every field read from this array is individually sanitized below.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$raw_lines = isset( $_POST['lines'] ) && is_array( $_POST['lines'] ) ? wp_unslash( $_POST['lines'] ) : array();

	$lines = array();
	foreach ( $raw_lines as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$description = isset( $row['description'] ) ? sanitize_text_field( (string) $row['description'] ) : '';
		$quantity    = isset( $row['quantity'] ) ? (int) $row['quantity'] : 0;
		$unit_price  = isset( $row['unit_price'] ) ? (float) sanitize_text_field( (string) $row['unit_price'] ) : 0.0;

		if ( '' === $description || $quantity < 1 || $unit_price <= 0 ) {
			continue;
		}

		$lines[] = new InvoiceLineInput( $description, $quantity, BookkeepingMoney::fromRands( $unit_price ) );
	}

	try {
		bizhub()->container()->get( InvoiceServiceInterface::class )->createInvoice(
			$company->getUuid(),
			$customer_uuid,
			$category,
			$includes_vat,
			$invoice_date,
			$due_date,
			$notes,
			$lines
		);

		wp_safe_redirect( add_query_arg( 'invoice_created', '1', $redirect_base ) );
	} catch ( BookkeepingException $e ) {
		wp_safe_redirect( add_query_arg( 'invoice_error', '1', $redirect_base ) );
	}

	exit;
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_invoice_send' );

/**
 * Send a draft invoice: posts the AR/revenue/VAT entry and emails the
 * PDF to the customer.
 */
function bizupkeep_child_handle_bookkeeping_invoice_send(): void {
	if ( ! isset( $_POST['bizupkeep_bookkeeping_invoice_send_nonce'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	$wp_user_id = get_current_user_id();

	if ( 0 === $wp_user_id ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_bookkeeping_invoice_send', 'bizupkeep_bookkeeping_invoice_send_nonce' );

	$company_uuid  = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$company       = bizupkeep_child_get_owned_company( $wp_user_id, $company_uuid );
	$redirect_base = add_query_arg( array( 'tab' => 'invoices', 'company' => $company_uuid ), get_permalink() );

	if ( null === $company || ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( add_query_arg( 'invoice_error', '1', $redirect_base ) );
		exit;
	}

	$invoice_uuid = isset( $_POST['invoice'] ) ? sanitize_text_field( wp_unslash( $_POST['invoice'] ) ) : '';

	try {
		bizhub()->container()->get( InvoiceServiceInterface::class )->sendInvoice( $company->getUuid(), $invoice_uuid, $wp_user_id );
		wp_safe_redirect( add_query_arg( 'invoice_sent', '1', $redirect_base ) );
	} catch ( BookkeepingException $e ) {
		wp_safe_redirect( add_query_arg( 'invoice_error', '1', $redirect_base ) );
	}

	exit;
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_invoice_resend' );

/**
 * Re-email an already-sent invoice's PDF without re-posting.
 */
function bizupkeep_child_handle_bookkeeping_invoice_resend(): void {
	if ( ! isset( $_POST['bizupkeep_bookkeeping_invoice_resend_nonce'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_bookkeeping_invoice_resend', 'bizupkeep_bookkeeping_invoice_resend_nonce' );

	$company_uuid  = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$company       = bizupkeep_child_get_owned_company( get_current_user_id(), $company_uuid );
	$redirect_base = add_query_arg( array( 'tab' => 'invoices', 'company' => $company_uuid ), get_permalink() );

	if ( null === $company || ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( add_query_arg( 'invoice_error', '1', $redirect_base ) );
		exit;
	}

	$invoice_uuid = isset( $_POST['invoice'] ) ? sanitize_text_field( wp_unslash( $_POST['invoice'] ) ) : '';

	try {
		bizhub()->container()->get( InvoiceServiceInterface::class )->resendInvoice( $company->getUuid(), $invoice_uuid );
		wp_safe_redirect( add_query_arg( 'invoice_sent', '1', $redirect_base ) );
	} catch ( BookkeepingException $e ) {
		wp_safe_redirect( add_query_arg( 'invoice_error', '1', $redirect_base ) );
	}

	exit;
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_invoice_pay' );

/**
 * Record a payment against a sent invoice.
 */
function bizupkeep_child_handle_bookkeeping_invoice_pay(): void {
	if ( ! isset( $_POST['bizupkeep_bookkeeping_invoice_pay_nonce'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	$wp_user_id = get_current_user_id();

	if ( 0 === $wp_user_id ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_bookkeeping_invoice_pay', 'bizupkeep_bookkeeping_invoice_pay_nonce' );

	$company_uuid  = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$company       = bizupkeep_child_get_owned_company( $wp_user_id, $company_uuid );
	$redirect_base = add_query_arg( array( 'tab' => 'invoices', 'company' => $company_uuid ), get_permalink() );

	if ( null === $company || ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( add_query_arg( 'invoice_error', '1', $redirect_base ) );
		exit;
	}

	$invoice_uuid   = isset( $_POST['invoice'] ) ? sanitize_text_field( wp_unslash( $_POST['invoice'] ) ) : '';
	$payment_raw    = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : '';
	$payment_method = 'cash' === $payment_raw ? BookkeepingPaymentMethod::Cash : BookkeepingPaymentMethod::Bank;

	try {
		bizhub()->container()->get( InvoiceServiceInterface::class )
			->recordPayment( $company->getUuid(), $invoice_uuid, $payment_method, $wp_user_id );
		wp_safe_redirect( add_query_arg( 'invoice_paid', '1', $redirect_base ) );
	} catch ( BookkeepingException $e ) {
		wp_safe_redirect( add_query_arg( 'invoice_error', '1', $redirect_base ) );
	}

	exit;
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_invoice_void' );

/**
 * Void a draft invoice - never posted, so nothing to reverse.
 */
function bizupkeep_child_handle_bookkeeping_invoice_void(): void {
	if ( ! isset( $_POST['bizupkeep_bookkeeping_invoice_void_nonce'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_bookkeeping_invoice_void', 'bizupkeep_bookkeeping_invoice_void_nonce' );

	$company_uuid  = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$company       = bizupkeep_child_get_owned_company( get_current_user_id(), $company_uuid );
	$redirect_base = add_query_arg( array( 'tab' => 'invoices', 'company' => $company_uuid ), get_permalink() );

	if ( null === $company || ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( add_query_arg( 'invoice_error', '1', $redirect_base ) );
		exit;
	}

	$invoice_uuid = isset( $_POST['invoice'] ) ? sanitize_text_field( wp_unslash( $_POST['invoice'] ) ) : '';

	try {
		bizhub()->container()->get( InvoiceServiceInterface::class )->voidInvoice( $company->getUuid(), $invoice_uuid );
		wp_safe_redirect( add_query_arg( 'invoice_voided', '1', $redirect_base ) );
	} catch ( BookkeepingException $e ) {
		wp_safe_redirect( add_query_arg( 'invoice_error', '1', $redirect_base ) );
	}

	exit;
}

add_action( 'template_redirect', 'bizupkeep_child_handle_bookkeeping_invoice_pdf_download' );

/**
 * Stream a single invoice as a PDF - GET request, no side effects.
 */
function bizupkeep_child_handle_bookkeeping_invoice_pdf_download(): void {
	if ( ! isset( $_GET['bizupkeep_bookkeeping_invoice_pdf'] ) || ! bizupkeep_child_is_bookkeeping_page() ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	if ( ! function_exists( 'bizhub' ) || null === bizhub() ) {
		wp_safe_redirect( get_permalink() );
		exit;
	}

	$company_uuid = isset( $_GET['company'] ) ? sanitize_text_field( wp_unslash( $_GET['company'] ) ) : '';
	$company      = bizupkeep_child_get_owned_company( get_current_user_id(), $company_uuid );

	if ( null === $company || ! bizupkeep_child_bookkeeping_subscription_active( $company->getUuid() ) ) {
		wp_safe_redirect( add_query_arg( array( 'tab' => 'invoices', 'invoice_error' => '1' ), get_permalink() ) );
		exit;
	}

	$invoice_uuid = sanitize_text_field( wp_unslash( $_GET['bizupkeep_bookkeeping_invoice_pdf'] ) );

	try {
		$pdf = bizhub()->container()->get( InvoiceServiceInterface::class )
			->generateInvoicePdf( $company->getUuid(), $invoice_uuid );
	} catch ( BookkeepingException $e ) {
		wp_safe_redirect( add_query_arg( array( 'tab' => 'invoices', 'invoice_error' => '1' ), get_permalink() ) );
		exit;
	}

	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $invoice_uuid ) . '.pdf"' );
	header( 'Content-Length: ' . strlen( $pdf ) );

	echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw PDF file content, not HTML.
	exit;
}
