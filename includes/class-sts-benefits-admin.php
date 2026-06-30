<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class STS_Benefits_Admin {

	public function __construct() {
		add_action( 'admin_menu',                                    array( $this, 'register_menus' ) );
		add_action( 'admin_post_sts_benefits_save_settings',         array( $this, 'save_settings' ) );
		add_action( 'admin_enqueue_scripts',                         array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_order_status_changed',              array( $this, 'clear_stats_cache' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'sts-benefits' ) ) {
			return;
		}

		wp_enqueue_style(
			'sts-benefits-admin',
			STS_BENEFITS_PLUGIN_URL . 'assets/css/benefits-admin.css',
			array(),
			STS_BENEFITS_VERSION
		);

		// wc-enhanced-select for product/category exclusion fields
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'select2' );

		wp_enqueue_script(
			'sts-benefits-admin',
			STS_BENEFITS_PLUGIN_URL . 'assets/js/benefits-admin.js',
			array( 'jquery', 'wc-enhanced-select' ),
			STS_BENEFITS_VERSION,
			true
		);

		wp_localize_script( 'sts-benefits-admin', 'stsBenefitsAdmin', array(
			'ajaxUrl' => esc_url( admin_url( 'admin-ajax.php' ) ),
			'nonce'   => wp_create_nonce( 'search-products' ),
		) );
	}

	// -------------------------------------------------------------------------
	// Menus
	// -------------------------------------------------------------------------

	public function register_menus() {
		add_submenu_page(
			'woocommerce',
			'הטבות – לוח בקרה',
			'הטבות',
			'manage_woocommerce',
			'sts-benefits',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'woocommerce',
			'הטבות – הגדרות',
			'הטבות – הגדרות',
			'manage_woocommerce',
			'sts-benefits-settings',
			array( $this, 'render_settings' )
		);
	}

	// -------------------------------------------------------------------------
	// Dashboard
	// -------------------------------------------------------------------------

	public function render_dashboard() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'אין לך הרשאה לצפות בדף זה.' );
		}

		$stats = $this->get_stats();
		?>
		<div class="wrap stsb-admin-wrap" dir="rtl">
			<h1 class="stsb-page-title">הטבות – לוח בקרה</h1>

			<div class="stsb-stats-row">
				<div class="stsb-stat-card">
					<div class="stsb-stat-value"><?php echo esc_html( number_format( $stats['count'] ) ); ?></div>
					<div class="stsb-stat-label">הטבות שניתנו</div>
				</div>
				<div class="stsb-stat-card">
					<div class="stsb-stat-value" style="color:#d63638;"><?php echo wp_kses_post( wc_price( $stats['total_discount'] ) ); ?></div>
					<div class="stsb-stat-label">סך ההנחות שניתנו</div>
				</div>
				<div class="stsb-stat-card">
					<div class="stsb-stat-value" style="color:#00a32a;"><?php echo wp_kses_post( wc_price( $stats['total_revenue'] ) ); ?></div>
					<div class="stsb-stat-label">הכנסה מהזמנות ראשונות</div>
				</div>
			</div>

			<?php if ( 0 === $stats['count'] ) : ?>
				<div class="notice notice-info inline">
					<p>טרם ניתנו הטבות. ברגע שלקוח ירכוש בפעם הראשונה תוצג כאן הסטטיסטיקה.</p>
				</div>
			<?php else : ?>
				<div class="stsb-orders-table-wrap">
					<h2>הזמנות עם הטבה</h2>
					<table class="wp-list-table widefat fixed striped stsb-orders-table">
						<thead>
							<tr>
								<th>מס' הזמנה</th>
								<th>לקוח</th>
								<th>תאריך</th>
								<th>סכום הנחה</th>
								<th>סה"כ הזמנה</th>
								<th>סטטוס</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $stats['orders'] as $order ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_id() ); ?></a></td>
									<td><?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?></td>
									<td><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
									<td><?php echo wp_kses_post( wc_price( $order->get_meta( '_sts_benefits_discount_amount' ) ) ); ?></td>
									<td><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></td>
									<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<p class="stsb-footer-link">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sts-benefits-settings' ) ); ?>">← לדף ההגדרות</a>
			</p>
		</div>
		<?php
	}

	private function get_stats() {
		$cached = get_transient( 'sts_benefits_stats' );

		if ( false !== $cached ) {
			// Transient stores only scalars + IDs — re-fetch order objects fresh to avoid
			// HPOS deserialization failures from storing WC_Order objects in options.
			$cached['orders'] = empty( $cached['order_ids'] )
				? array()
				: wc_get_orders( array( 'include' => $cached['order_ids'], 'limit' => count( $cached['order_ids'] ) ) );
			return $cached;
		}

		// Fetch IDs only first — lightweight, avoids hydrating full objects for aggregation
		$order_ids = wc_get_orders( array(
			'status'     => array( 'processing', 'completed' ),
			'meta_key'   => '_sts_benefits_first_order',
			'meta_value' => 'yes',
			'limit'      => -1,
			'return'     => 'ids',
		) );

		$count          = count( $order_ids );
		$total_discount = 0.0;
		$total_revenue  = 0.0;
		$order_objects  = array();

		if ( ! empty( $order_ids ) ) {
			$order_objects = wc_get_orders( array( 'include' => $order_ids, 'limit' => $count ) );
			foreach ( $order_objects as $order ) {
				$total_discount += (float) $order->get_meta( '_sts_benefits_discount_amount' );
				$total_revenue  += (float) $order->get_total();
			}
		}

		// Cache only serializable scalars + IDs — never WC_Order objects
		set_transient( 'sts_benefits_stats', array(
			'count'          => $count,
			'total_discount' => $total_discount,
			'total_revenue'  => $total_revenue,
			'order_ids'      => $order_ids,
		), HOUR_IN_SECONDS );

		return array(
			'count'          => $count,
			'total_discount' => $total_discount,
			'total_revenue'  => $total_revenue,
			'orders'         => $order_objects,
		);
	}

	public function clear_stats_cache() {
		delete_transient( 'sts_benefits_stats' );
	}

	// -------------------------------------------------------------------------
	// Settings
	// -------------------------------------------------------------------------

	public function render_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'אין לך הרשאה לצפות בדף זה.' );
		}

		$saved   = isset( $_GET['saved'] );
		$engine  = STS_Benefits::get_instance();
		$pct     = $engine->get_percentage();
		$enabled = $engine->is_enabled();
		$popup   = $engine->is_popup_enabled();
		$mode    = $engine->get_eligibility_mode();
		$ex_prod = $engine->get_excluded_products();
		$ex_cats = $engine->get_excluded_categories();

		// Bulk-fetch selected product names in a single query
		$selected_products = array();
		if ( ! empty( $ex_prod ) ) {
			$products = wc_get_products( array(
				'include' => $ex_prod,
				'limit'   => count( $ex_prod ),
				'return'  => 'objects',
			) );
			foreach ( $products as $p ) {
				$selected_products[ $p->get_id() ] = $p->get_name();
			}
		}

		// Build category terms for display
		$all_cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		if ( is_wp_error( $all_cats ) ) {
			$all_cats = array();
		}
		?>
		<div class="wrap stsb-admin-wrap" dir="rtl">
			<h1 class="stsb-page-title">הטבות – הגדרות</h1>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p>ההגדרות נשמרו בהצלחה.</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'sts_benefits_settings' ); ?>
				<input type="hidden" name="action" value="sts_benefits_save_settings">

				<table class="form-table stsb-settings-table">
					<tr>
						<th scope="row"><label>הפעלת הנחת הזמנה ראשונה</label></th>
						<td>
							<label>
								<input type="checkbox" name="sts_benefits_enabled" value="1" <?php checked( $enabled ); ?>>
								הפעל הנחה אוטומטית על ההזמנה הראשונה
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sts_benefits_percentage">אחוז הנחה</label></th>
						<td>
							<input type="number" id="sts_benefits_percentage" name="sts_benefits_percentage"
								min="1" max="100" value="<?php echo esc_attr( $pct ); ?>" class="small-text">
							<span>%</span>
							<p class="description">ברירת מחדל: 10%</p>
						</td>
					</tr>
					<tr>
						<th scope="row">תנאי זכאות</th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="sts_benefits_eligibility_mode" value="unused_benefit" <?php checked( $mode, 'unused_benefit' ); ?>>
									כל לקוח שטרם ניצל את ההטבה (כולל לקוחות קיימים שלא השתמשו בהנחה)
								</label>
								<br>
								<label>
									<input type="radio" name="sts_benefits_eligibility_mode" value="new_customers" <?php checked( $mode, 'new_customers' ); ?>>
									לקוחות חדשים בלבד (ללא הזמנות קודמות כלל)
								</label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label>הצגת חלון קופץ</label></th>
						<td>
							<label>
								<input type="checkbox" name="sts_benefits_popup_enabled" value="1" <?php checked( $popup ); ?>>
								הצג חלון קופץ המעודד הרשמה / רכישה ראשונה
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sts_benefits_excluded_products">מוצרים מוחרגים</label></th>
						<td>
							<select id="sts_benefits_excluded_products" name="sts_benefits_excluded_products[]"
								class="wc-product-search" multiple="multiple" style="width:50%;"
								data-placeholder="חפש מוצרים..."
								data-action="woocommerce_json_search_products_and_variations">
								<?php foreach ( $selected_products as $pid => $name ) : ?>
									<option value="<?php echo esc_attr( $pid ); ?>" selected="selected"><?php echo esc_html( $name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">מוצרים שיוחרגו מבסיס חישוב ההנחה.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sts_benefits_excluded_categories">קטגוריות מוחרגות</label></th>
						<td>
							<select id="sts_benefits_excluded_categories" name="sts_benefits_excluded_categories[]"
								multiple="multiple" style="width:50%;" class="wc-enhanced-select">
								<?php foreach ( $all_cats as $cat ) : ?>
									<option value="<?php echo esc_attr( $cat->term_id ); ?>"
										<?php echo in_array( (int) $cat->term_id, $ex_cats, true ) ? 'selected' : ''; ?>>
										<?php echo esc_html( $cat->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">מוצרים מקטגוריות אלו לא ייכללו בחישוב ההנחה.</p>
						</td>
					</tr>
				</table>

				<?php submit_button( 'שמירת הגדרות' ); ?>
			</form>

			<p class="stsb-footer-link">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sts-benefits' ) ); ?>">← חזרה ללוח הבקרה</a>
			</p>
		</div>
		<?php
	}

	public function save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'אין לך הרשאה לבצע פעולה זו.' );
		}

		check_admin_referer( 'sts_benefits_settings' );

		// Enabled toggle
		update_option( 'sts_benefits_enabled', ! empty( $_POST['sts_benefits_enabled'] ) ? 1 : 0 );

		// Percentage
		$pct = isset( $_POST['sts_benefits_percentage'] ) ? absint( $_POST['sts_benefits_percentage'] ) : 10;
		$pct = max( 1, min( 100, $pct ) );
		update_option( 'sts_benefits_percentage', $pct );

		// Eligibility mode
		$allowed_modes = array( 'unused_benefit', 'new_customers' );
		$mode          = isset( $_POST['sts_benefits_eligibility_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['sts_benefits_eligibility_mode'] ) ) : 'unused_benefit';
		if ( ! in_array( $mode, $allowed_modes, true ) ) {
			$mode = 'unused_benefit';
		}
		update_option( 'sts_benefits_eligibility_mode', $mode );

		// Popup toggle
		update_option( 'sts_benefits_popup_enabled', ! empty( $_POST['sts_benefits_popup_enabled'] ) ? 1 : 0 );

		// Excluded products
		$ex_prod = isset( $_POST['sts_benefits_excluded_products'] ) ? array_map( 'absint', (array) $_POST['sts_benefits_excluded_products'] ) : array();
		update_option( 'sts_benefits_excluded_products', array_filter( $ex_prod ) );

		// Excluded categories
		$ex_cats = isset( $_POST['sts_benefits_excluded_categories'] ) ? array_map( 'absint', (array) $_POST['sts_benefits_excluded_categories'] ) : array();
		update_option( 'sts_benefits_excluded_categories', array_filter( $ex_cats ) );

		// Clear analytics cache
		delete_transient( 'sts_benefits_stats' );

		wp_redirect( admin_url( 'admin.php?page=sts-benefits-settings&saved=1' ) );
		exit;
	}
}
