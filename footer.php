<?php
/**
 * BizUpKeep custom site footer.
 *
 * @package BizUpKeep_Astra_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
	<footer id="bizupkeep-footer" class="bizupkeep-footer">
		<div class="bizupkeep-footer-inner">

			<div class="bizupkeep-footer-columns">
				<div class="bizupkeep-footer-column bizupkeep-footer-about">
					<h3 class="bizupkeep-footer-heading"><?php bloginfo( 'name' ); ?></h3>
					<p><?php esc_html_e( 'Company registration and compliance services, made simple.', 'bizupkeep-astra-child' ); ?></p>
				</div>

				<div class="bizupkeep-footer-column bizupkeep-footer-links">
					<h3 class="bizupkeep-footer-heading"><?php esc_html_e( 'Quick Links', 'bizupkeep-astra-child' ); ?></h3>
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'bizupkeep-footer',
							'container'      => false,
							'menu_class'     => 'bizupkeep-footer-menu',
							'fallback_cb'    => false,
						)
					);
					?>
				</div>

				<div class="bizupkeep-footer-column bizupkeep-footer-widgets">
					<?php if ( is_active_sidebar( 'bizupkeep-footer-widgets' ) ) : ?>
						<?php dynamic_sidebar( 'bizupkeep-footer-widgets' ); ?>
					<?php endif; ?>
				</div>
			</div>

			<div class="bizupkeep-footer-bottom">
				<p>
					&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'All rights reserved.', 'bizupkeep-astra-child' ); ?>
				</p>
			</div>

		</div>
	</footer>

<?php wp_footer(); ?>
</body>
</html>
