<?php
/**
 * Plugin Name: הטבות
 * Plugin URI: https://shaytechsolutions.com
 * Description: מעניק הנחה אוטומטית של 10% על ההזמנה הראשונה של לקוחות רשומים, כולל חלון קופץ פרסומי ולוח ניהול עם אנליטיקס.
 * Version: 1.0.1
 * Author: Shay Tech Solutions
 * Author URI: https://shaytechsolutions.com
 * Text Domain: sts-benefits
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

define( 'STS_BENEFITS_VERSION',    '1.0.1' );
define( 'STS_BENEFITS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STS_BENEFITS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'STS_BENEFITS_FILE',       __FILE__ );

require_once STS_BENEFITS_PLUGIN_DIR . 'includes/class-sts-benefits.php';
require_once STS_BENEFITS_PLUGIN_DIR . 'includes/class-sts-benefits-popup.php';
require_once STS_BENEFITS_PLUGIN_DIR . 'includes/class-sts-benefits-admin.php';

STS_Benefits::get_instance();

if ( is_admin() ) {
	new STS_Benefits_Admin();
} else {
	new STS_Benefits_Popup();
}
