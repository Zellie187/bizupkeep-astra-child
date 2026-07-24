<?php
/**
 * Template Name: BizUpKeep My Documents
 * Description:   Retired standalone page - My Documents was merged
 *                 into My Applications so clients can upload documents
 *                 directly from their application list instead of
 *                 hopping between two pages. This template now only
 *                 redirects here. Left in place (rather than deleted)
 *                 so the page object, its menu entry, and any
 *                 bookmarked/cached link keep working. See
 *                 bizupkeep_child_applications_sections() and
 *                 template-applications.php for where the merged
 *                 content actually lives, and
 *                 bizupkeep_child_handle_document_upload() for why a
 *                 POST submitted to this old URL still works too.
 *
 * @package BizUpKeep_Astra_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_safe_redirect( home_url( '/client-portal/client-portal-applications/' ) );
exit;
