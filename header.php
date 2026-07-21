<?php
/**
 * BizUpKeep custom site header.
 *
 * @package BizUpKeep_Astra_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header id="bizupkeep-header" class="bizupkeep-header">
	<div class="bizupkeep-header-inner">
		<div class="bizupkeep-branding">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="bizupkeep-site-title">
					<?php bloginfo( 'name' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<nav id="bizupkeep-primary-nav" class="bizupkeep-primary-nav" aria-label="<?php esc_attr_e( 'Primary Menu', 'bizupkeep-astra-child' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'bizupkeep-primary',
					'container'      => false,
					'menu_class'     => 'bizupkeep-menu',
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>

		<div class="bizupkeep-header-cta">
			<a href="<?php echo esc_url( home_url( '/apply/' ) ); ?>" class="bizupkeep-btn bizupkeep-btn-primary">
				<?php esc_html_e( 'Start Application', 'bizupkeep-astra-child' ); ?>
			</a>
		</div>

		<button id="bizupkeep-menu-toggle" class="bizupkeep-menu-toggle" aria-expanded="false" aria-controls="bizupkeep-primary-nav">
			<span class="screen-reader-text"><?php esc_html_e( 'Toggle Menu', 'bizupkeep-astra-child' ); ?></span>
			<span class="bizupkeep-menu-toggle-bar"></span>
			<span class="bizupkeep-menu-toggle-bar"></span>
			<span class="bizupkeep-menu-toggle-bar"></span>
		</button>
	</div>
</header>
