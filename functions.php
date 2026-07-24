<?php
/**
 * BizUpKeep Astra Child theme functions.
 *
 * @package BizUpKeep_Astra_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

define( 'BIZUPKEEP_CHILD_VERSION', '1.21.0' );
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
			'bizupkeep-primary'       => __( 'Primary Menu', 'bizupkeep-astra-child' ),
			'bizupkeep-footer'        => __( 'Footer Menu', 'bizupkeep-astra-child' ),
			'bizupkeep-client-portal' => __( 'Client Portal Menu', 'bizupkeep-astra-child' ),
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
 * menu linking them. The dashboard entry reuses the existing
 * "Client Portal" page (already published and linked in the primary
 * menu) rather than creating a competing one; the other four pages
 * nest underneath it. Swap each page's content for a real template
 * (or a shortcode backed by those REST endpoints, the same pattern
 * [bizupkeep_packages] already uses on the homepage) as the portal
 * front-end gets built - the menu links to the pages by ID, not
 * hardcoded markup, so nothing here needs to change to support that.
 */

add_action( 'after_switch_theme', 'bizupkeep_child_setup_client_portal' );

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
 * Create (or reuse) the "Client Portal" nav menu with a single
 * top-level "Client Portal" item, and nest Dashboard/My Companies/
 * My Documents/My Applications/My Profile underneath it as a dropdown
 * - rather than each getting its own top-level slot, which is how
 * this used to render (see bizupkeep_child_render_portal_nav()'s
 * depth argument for the other half of this change). Assigns the
 * menu to the client portal menu location.
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
	$menu_name = __( 'Client Portal', 'bizupkeep-astra-child' );
	$menu      = wp_get_nav_menu_object( $menu_name );

	if ( ! $menu ) {
		$menu_id = wp_create_nav_menu( $menu_name );

		if ( is_wp_error( $menu_id ) ) {
			return;
		}
	} else {
		$menu_id = $menu->term_id;
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
		$parent_menu_item_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => __( 'Client Portal', 'bizupkeep-astra-child' ),
				'menu-item-url'       => $dashboard_url,
				'menu-item-type'      => 'custom',
				'menu-item-status'    => 'publish',
				'menu-item-parent-id' => 0,
				'menu-item-position'  => 1,
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

	$locations = get_theme_mod( 'nav_menu_locations', array() );

	if ( empty( $locations['bizupkeep-client-portal'] ) ) {
		$locations['bizupkeep-client-portal'] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}
}

/**
 * Render the client portal menu as a slim utility bar above the main
 * site header. depth => 2 (one level of children) lets the "Client
 * Portal" parent item's five children render as a dropdown - see
 * bizupkeep_child_sync_client_portal_menu() for how that structure is
 * built. Dropdown open/close is handled by .bizupkeep-portal-menu's
 * CSS (hover/focus) plus the tap-to-toggle JS in assets/js/custom.js,
 * since :hover alone doesn't work on touch devices.
 */
function bizupkeep_child_render_portal_nav(): void {
	if ( ! has_nav_menu( 'bizupkeep-client-portal' ) ) {
		return;
	}

	wp_nav_menu(
		array(
			'theme_location'  => 'bizupkeep-client-portal',
			'container'       => 'div',
			'container_class' => 'bizupkeep-portal-bar',
			'menu_class'      => 'bizupkeep-portal-menu',
			'fallback_cb'     => false,
			'depth'           => 2,
		)
	);
}
add_action( 'wp_body_open', 'bizupkeep_child_render_portal_nav' );

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
 * idempotent, and this does nothing if BizHub isn't active.
 */
function bizupkeep_child_ensure_client_record( int $wp_user_id ): void {
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
				new ProfileData( $first_name, $last_name, '', get_avatar_url( $wp_user_id ) )
			)
		);
	} catch ( InvalidArgumentException $e ) {
		// Another request created it first (race), or invalid data - either way, nothing more to do here.
	}
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
 * Dispatches to one of three handlers based on the posted
 * "application_type" (New Registration / Company Amendment / Annual
 * Return - see template-apply.php's radio buttons), each of which
 * starts the matching bizupkeep-workflow workflow type directly via
 * the shared container, the same pattern already used for Company
 * Registration.
 */
function bizupkeep_child_handle_apply_submission(): void {
	if ( ! isset( $_POST['bizupkeep_apply_nonce'] ) ) {
		return;
	}

	if ( ! is_page() || bizupkeep_child_find_page( 'apply', 0 ) !== get_queried_object_id() ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	check_admin_referer( 'bizupkeep_apply', 'bizupkeep_apply_nonce' );

	$wp_user_id        = get_current_user_id();
	$application_type  = isset( $_POST['application_type'] ) ? sanitize_text_field( wp_unslash( $_POST['application_type'] ) ) : '';
	$notes             = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

	bizupkeep_child_ensure_client_record( $wp_user_id );

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

		// Move Created -> PendingDocuments immediately: the moment an
		// application is submitted, the client IS being asked for
		// documents (that's what the My Documents page does next) - so
		// there's no separate "someone decides to request documents"
		// step to wait for here.
		$registration->performAction(
			$instance->getUuid(),
			CompanyRegistrationDefinition::ACTION_REQUEST_DOCUMENTS,
			$wp_user_id,
			__( 'Documents requested automatically at application submission.', 'bizupkeep-astra-child' )
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

		// Same immediate-document-request pattern as Company
		// Registration: submitting the application is itself the
		// trigger to start collecting supporting documents.
		$amendments->performAction(
			$instance->getUuid(),
			CompanyAmendmentDefinition::ACTION_REQUEST_DOCUMENTS,
			$wp_user_id,
			$notes
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
			<p class="bizupkeep-field-hint"><?php esc_html_e( "We need this now, not just for a Director Amendment - these are the people who'll need to sign the Power of Attorney, whatever you're changing.", 'bizupkeep-astra-child' ); ?></p>

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

	// Only accept uploads while this specific application is actually
	// waiting on documents - not before (just created, nothing
	// requested yet) and not after (already verified/moved on).
	if ( WorkflowStatus::PendingDocuments !== $workflow->getStatus() ) {
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

	$directors_rows = '';

	foreach ( $company->getDirectors() as $index => $director ) {
		$directors_rows .= sprintf(
			'<tr><td>%1$d</td><td>%2$s</td><td>%3$s %4$s</td><td>%5$s</td><td class="bizupkeep-poa-signature-cell"></td></tr>',
			$index + 1,
			esc_html( $director->getLastName() ),
			esc_html( $director->getFirstName() ),
			esc_html( $director->getLastName() ),
			esc_html( $director->getIdNumber() ?? $director->getPassportNumber() ?? '' )
		);
	}

	if ( '' === $directors_rows ) {
		$directors_rows = '<tr><td colspan="5">' . esc_html__( 'No directors on file.', 'bizupkeep-astra-child' ) . '</td></tr>';
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

	$directors_rows = bizupkeep_child_render_director_signature_rows( $company );

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

	$directors_rows = bizupkeep_child_render_director_signature_rows( $company );

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
 * Build the director rows shared by the Resolution Letter and Minutes
 * of Meeting's signature tables - identical shape to the rows
 * bizupkeep_child_render_poa_document() builds inline, factored out
 * since three documents now need it.
 */
function bizupkeep_child_render_director_signature_rows( Company $company ): string {
	$rows = '';

	foreach ( $company->getDirectors() as $index => $director ) {
		$rows .= sprintf(
			'<tr><td>%1$d</td><td>%2$s %3$s</td><td>%4$s</td><td class="bizupkeep-signature-cell"></td></tr>',
			$index + 1,
			esc_html( $director->getFirstName() ),
			esc_html( $director->getLastName() ),
			esc_html( $director->getIdNumber() ?? $director->getPassportNumber() ?? '' )
		);
	}

	if ( '' === $rows ) {
		$rows = '<tr><td colspan="4">' . esc_html__( 'No directors on file.', 'bizupkeep-astra-child' ) . '</td></tr>';
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
 *   1. "Pay Now" on My Applications links to the packages category
 *      archive with ?bizupkeep_pay_for={workflow_uuid} appended - the
 *      *application's* UUID, not the company's, since a company can
 *      have more than one application and only one of them is the one
 *      actually being paid for.
 *   2. On that (or any) page load, bizupkeep_child_capture_payment_intent()
 *      verifies that application belongs to the logged-in client and
 *      is actually AwaitingPayment, then stores its UUID in the
 *      WooCommerce session - not order meta yet, since no order exists.
 *   3. Whichever package the client actually buys,
 *      bizupkeep_child_attach_workflow_to_order() copies the session
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
const BIZUPKEEP_PACKAGES_CATEGORY_SLUG = 'company-registration-packages';

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

if ( class_exists( 'WooCommerce' ) ) {
	add_action( 'template_redirect', 'bizupkeep_child_capture_payment_intent' );
	add_action( 'template_redirect', 'bizupkeep_child_handle_annual_return_payment_intent' );
	add_action( 'template_redirect', 'bizupkeep_child_handle_amendment_payment_intent' );
	add_action( 'woocommerce_checkout_create_order', 'bizupkeep_child_attach_workflow_to_order', 10, 2 );
	add_action( 'woocommerce_order_status_changed', 'bizupkeep_child_handle_order_payment', 10, 4 );
	add_action( 'woocommerce_before_calculate_totals', 'bizupkeep_child_apply_annual_return_quote_price' );
}

/**
 * Build the "Pay Now" URL for a specific application: the packages
 * category archive (matching the same category the homepage's pricing
 * cards link into), with the application's workflow UUID attached as
 * a query arg for bizupkeep_child_capture_payment_intent() to pick up.
 * Falls back to the general shop page if that category doesn't exist,
 * rather than linking somewhere broken.
 */
function bizupkeep_child_payment_url( string $workflow_uuid ): string {
	$term = get_term_by( 'slug', BIZUPKEEP_PACKAGES_CATEGORY_SLUG, 'product_cat' );
	$base = $term instanceof WP_Term ? get_term_link( $term ) : wc_get_page_permalink( 'shop' );

	if ( is_wp_error( $base ) || false === $base ) {
		$base = home_url( '/' );
	}

	return add_query_arg( 'bizupkeep_pay_for', $workflow_uuid, $base );
}

/**
 * If the current request carries ?bizupkeep_pay_for={workflow_uuid},
 * verify that application belongs to the logged-in client and is
 * actually AwaitingPayment, then remember it in the WooCommerce
 * session for whichever order results from checkout. Runs on every
 * page load (not just a dedicated page) since the client lands
 * directly on a product/category page, not a theme-owned one.
 */
function bizupkeep_child_capture_payment_intent(): void {
	if ( ! isset( $_GET['bizupkeep_pay_for'] ) || ! is_user_logged_in() ) {
		return;
	}

	if ( null === WC()->session ) {
		return;
	}

	$workflow_uuid = sanitize_text_field( wp_unslash( $_GET['bizupkeep_pay_for'] ) );
	$workflow      = bizupkeep_child_get_owned_workflow_instance( get_current_user_id(), $workflow_uuid );

	if ( null === $workflow || WorkflowStatus::AwaitingPayment !== $workflow->getStatus() ) {
		return;
	}

	WC()->session->set( BIZUPKEEP_PAYMENT_SESSION_KEY, $workflow_uuid );
}

/**
 * Build the "Pay Now" URL for an Annual Return application
 * specifically - unlike bizupkeep_child_payment_url() (a fixed-price
 * WooCommerce product category, used by Company Registration/
 * Amendment), this doesn't send the client anywhere to pick a product:
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
 * SKU prefix for the lazily-created, hidden Company Amendment products -
 * one per non-empty subset of {director, name, address} (7 in total).
 * Unlike Registration's open packages category,
 * bizupkeep_child_amendment_payment_url() routes the client straight to
 * checkout with the ONE product matching exactly what they submitted,
 * so a bundled director+name+address change can never be paid for at a
 * single-change-type price (or vice versa) - see
 * bizupkeep_child_amendment_sku().
 */
const BIZUPKEEP_AMENDMENT_SKU_PREFIX = 'bizupkeep-amendment-';

/**
 * Build the canonical SKU for a set of amendment types - sorted
 * alphabetically so the same combination always maps to the same
 * product regardless of the order the client happened to submit them
 * in. Unrecognised type strings are dropped rather than trusted
 * verbatim into a SKU.
 *
 * @param array<int,string> $amendmentTypes
 */
function bizupkeep_child_amendment_sku( array $amendmentTypes ): string {
	$types = array_values( array_intersect( $amendmentTypes, CompanyAmendmentDefinition::ALL_AMENDMENT_TYPES ) );
	sort( $types );

	return BIZUPKEEP_AMENDMENT_SKU_PREFIX . implode( '-', $types );
}

/**
 * Human-readable label for a single amendment type, used only to name
 * the auto-created products below - mirrors
 * QualityReviewPage::amendmentTypeLabel() in bizupkeep-workflow, kept
 * separate since that's an admin-side display concern.
 */
function bizupkeep_child_amendment_type_label( string $type ): string {
	return match ( $type ) {
		CompanyAmendmentDefinition::AMENDMENT_TYPE_DIRECTOR => __( 'Director Change', 'bizupkeep-astra-child' ),
		CompanyAmendmentDefinition::AMENDMENT_TYPE_NAME => __( 'Name Change', 'bizupkeep-astra-child' ),
		CompanyAmendmentDefinition::AMENDMENT_TYPE_ADDRESS => __( 'Address Change', 'bizupkeep-astra-child' ),
		default => ucfirst( $type ),
	};
}

/**
 * Idempotently find (or create) the hidden product for a specific
 * amendment-type combination. Created at a 0 placeholder price -
 * staff must set the real price for each combination in WooCommerce
 * (Products screen, searchable by its bizupkeep-amendment- SKU) before
 * clients relying on that exact combination can be charged correctly.
 * Hidden from the shop/search catalog since it's never meant to be
 * browsed, only added to cart programmatically, same as the Annual
 * Return fee product.
 *
 * @param array<int,string> $amendmentTypes
 */
function bizupkeep_child_get_or_create_amendment_product( array $amendmentTypes ): int {
	$types = array_values( array_intersect( $amendmentTypes, CompanyAmendmentDefinition::ALL_AMENDMENT_TYPES ) );

	if ( array() === $types ) {
		return 0;
	}

	$sku      = bizupkeep_child_amendment_sku( $types );
	$existing = wc_get_product_id_by_sku( $sku );

	if ( $existing ) {
		return (int) $existing;
	}

	if ( ! class_exists( 'WC_Product_Simple' ) ) {
		return 0;
	}

	sort( $types );
	$name = sprintf(
		/* translators: %s: "&"-joined list of change types, e.g. "Director Change & Name Change" */
		__( 'Company Amendment - %s', 'bizupkeep-astra-child' ),
		implode( ' & ', array_map( 'bizupkeep_child_amendment_type_label', $types ) )
	);

	$product = new WC_Product_Simple();
	$product->set_name( $name );
	$product->set_sku( $sku );
	$product->set_regular_price( '0' );
	$product->set_price( '0' );
	$product->set_virtual( true );
	$product->set_catalog_visibility( 'hidden' );
	$product->set_status( 'publish' );

	return (int) $product->save();
}

/**
 * Build the "Pay Now" URL for a Company Amendment application
 * specifically - unlike bizupkeep_child_payment_url() (Registration's
 * open packages category, still used there since a Registration has
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
 * actual trust boundary, not the link) - then resolve (or lazily
 * create) the ONE product matching its amendment_types, clear the
 * cart, add that product, and send the client straight to checkout.
 * The cart is deliberately emptied first so an Amendment payment is
 * never accidentally combined with an unrelated item.
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
		? bizupkeep_child_get_or_create_amendment_product( $amendment_types )
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
 * An Annual Return's "Pay Now" link is NOT the same
 * bizupkeep_child_payment_url() the other two types use (a fixed-price
 * WooCommerce product category) - it routes to
 * bizupkeep_child_annual_return_payment_url() instead, which charges
 * the exact staff-quoted amount rather than whatever product the
 * client happens to pick.
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

			$can_upload = WorkflowStatus::PendingDocuments === $instance->getStatus();

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
				default => bizupkeep_child_payment_url( $instance->getUuid() ),
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
