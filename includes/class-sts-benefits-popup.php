<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class STS_Benefits_Popup {

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		if ( ! STS_Benefits::get_instance()->is_popup_enabled() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer',          array( $this, 'render_popup' ) );
	}

	public function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		$variant = $this->get_variant();
		if ( null === $variant ) {
			return;
		}

		wp_enqueue_style(
			'sts-benefits-popup',
			STS_BENEFITS_PLUGIN_URL . 'assets/css/benefits-popup.css',
			array(),
			STS_BENEFITS_VERSION
		);

		wp_enqueue_script(
			'sts-benefits-popup',
			STS_BENEFITS_PLUGIN_URL . 'assets/js/benefits-popup.js',
			array(),
			STS_BENEFITS_VERSION,
			true
		);
	}

	private function get_variant() {
		// Never show when the user is already in the purchase funnel or account area
		if ( is_cart() || is_checkout() || is_account_page() || is_wc_endpoint_url( 'order-received' ) ) {
			return null;
		}

		$engine = STS_Benefits::get_instance();

		if ( ! is_user_logged_in() ) {
			return 'guest';
		}

		if ( $engine->is_user_eligible() ) {
			return 'eligible';
		}

		return null; // already used — show nothing
	}

	public function render_popup() {
		$variant = $this->get_variant();
		if ( null === $variant ) {
			return;
		}

		$engine = STS_Benefits::get_instance();
		$pct    = $engine->get_percentage();

		if ( 'guest' === $variant ) {
			$title  = 'מתנת הצטרפות';
			$body   = 'הירשמו עכשיו וקבלו ' . esc_html( $pct ) . '% הנחה על ההזמנה הראשונה שלכם!';
			$button = 'להרשמה';
			$url    = esc_url( wc_get_page_permalink( 'myaccount' ) );
		} else {
			$title  = esc_html( $pct ) . '% הנחה ממתינים לך!';
			$body   = 'ההזמנה הראשונה שלך מזכה אותך ב‑' . esc_html( $pct ) . '% הנחה אוטומטית בקופה.';
			$button = 'התחילו לקנות';
			$url    = esc_url( get_permalink( wc_get_page_id( 'shop' ) ) );
		}
		?>
		<div class="stsb-popup-overlay" id="stsb-popup-overlay" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $title ); ?>" dir="rtl">
			<div class="stsb-popup">
				<button class="stsb-popup-close" id="stsb-popup-close" aria-label="סגירה">&times;</button>
				<div class="stsb-popup-icon">🎁</div>
				<h2 class="stsb-popup-title"><?php echo esc_html( $title ); ?></h2>
				<p class="stsb-popup-body"><?php echo esc_html( $body ); ?></p>
				<a href="<?php echo esc_url( $url ); ?>" class="stsb-popup-btn"><?php echo esc_html( $button ); ?></a>
			</div>
		</div>
		<?php
	}
}
