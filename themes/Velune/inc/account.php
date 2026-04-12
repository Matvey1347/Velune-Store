<?php
/**
 * Account and profile customizations for WooCommerce account area.
 *
 * @package Velune
 */

function velune_add_account_avatar_form_encoding() {
	echo 'enctype="multipart/form-data"';
}
add_action( 'woocommerce_edit_account_form_tag', 'velune_add_account_avatar_form_encoding' );

/**
 * Render custom avatar editor in WooCommerce account details form.
 */
function velune_render_account_avatar_field() {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$current_user = wp_get_current_user();

	if ( ! ( $current_user instanceof WP_User ) || $current_user->ID <= 0 ) {
		return;
	}

	$avatar_url = velune_get_user_avatar_url( (int) $current_user->ID, 160 );
	$initials   = velune_get_user_initials( $current_user );
	?>
	<fieldset class="velune-account-avatar-fieldset">
		<legend><?php esc_html_e( 'Profile photo', 'velune' ); ?></legend>
		<label for="velune_avatar_file" class="velune-account-avatar-picker">
			<span class="velune-account-avatar-media" aria-hidden="true">
				<?php if ( $avatar_url ) : ?>
					<img src="<?php echo esc_url( $avatar_url ); ?>" alt="" width="96" height="96" loading="lazy" decoding="async">
				<?php else : ?>
					<span class="velune-account-avatar-initials"><?php echo esc_html( $initials ? $initials : 'U' ); ?></span>
				<?php endif; ?>
				<span class="velune-account-avatar-overlay"><?php esc_html_e( 'Change photo', 'velune' ); ?></span>
			</span>
			<span class="velune-account-avatar-meta">
				<span class="velune-account-avatar-title"><?php esc_html_e( 'Update your avatar', 'velune' ); ?></span>
				<span class="velune-account-avatar-help"><?php esc_html_e( 'Accepted: JPG, PNG, GIF, WebP, AVIF.', 'velune' ); ?></span>
			</span>
		</label>
		<input type="file" name="velune_avatar_file" id="velune_avatar_file" accept="image/*">
	</fieldset>
	<?php
}
add_action( 'woocommerce_edit_account_form_start', 'velune_render_account_avatar_field' );

/**
 * Handle avatar upload during WooCommerce account details save.
 *
 * @param int $user_id User ID.
 */
function velune_handle_account_avatar_upload( $user_id ) {
	$user_id = (int) $user_id;

	if ( $user_id <= 0 || ! is_user_logged_in() || (int) get_current_user_id() !== $user_id ) {
		return;
	}

	if ( empty( $_FILES['velune_avatar_file'] ) || ! is_array( $_FILES['velune_avatar_file'] ) ) {
		return;
	}

	$upload = $_FILES['velune_avatar_file'];

	if ( empty( $upload['name'] ) || ( isset( $upload['error'] ) && UPLOAD_ERR_NO_FILE === (int) $upload['error'] ) ) {
		return;
	}

	if ( ! empty( $upload['error'] ) ) {
		wc_add_notice( __( 'We could not upload your profile photo. Please try again.', 'velune' ), 'error' );
		return;
	}

	$tmp_name = isset( $upload['tmp_name'] ) ? (string) $upload['tmp_name'] : '';
	$file_name = isset( $upload['name'] ) ? (string) $upload['name'] : '';

	if ( '' === $tmp_name || '' === $file_name ) {
		wc_add_notice( __( 'Please choose a valid image file.', 'velune' ), 'error' );
		return;
	}

	$filetype = wp_check_filetype_and_ext( $tmp_name, $file_name );
	$mime     = isset( $filetype['type'] ) ? (string) $filetype['type'] : '';

	if ( '' === $mime || 0 !== strpos( $mime, 'image/' ) ) {
		wc_add_notice( __( 'Only image files can be used for profile photos.', 'velune' ), 'error' );
		return;
	}

	if ( ! function_exists( 'media_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$overrides     = array(
		'test_form' => false,
		'mimes'     => array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'gif'          => 'image/gif',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
			'avif'         => 'image/avif',
		),
	);
	$attachment_id = media_handle_upload( 'velune_avatar_file', 0, array(), $overrides );

	if ( is_wp_error( $attachment_id ) ) {
		wc_add_notice( $attachment_id->get_error_message(), 'error' );
		return;
	}

	update_user_meta( $user_id, 'velune_avatar_id', (int) $attachment_id );
	wc_add_notice( __( 'Profile photo updated.', 'velune' ), 'success' );
}
add_action( 'woocommerce_save_account_details', 'velune_handle_account_avatar_upload', 20 );

/**
 * Remove legacy endpoints from My Account navigation.
 *
 * @param array<string, string> $items Account menu items.
 * @return array<string, string>
 */
function velune_filter_my_account_menu_items( $items ) {
	if ( ! is_array( $items ) || empty( $items ) ) {
		return $items;
	}

	$preferred_keys = array(
		'dashboard',
		'orders',
		'wp-sp-subscriptions',
		'subscriptions',
		'edit-account',
		'customer-logout',
	);
	$filtered_items = array();

	foreach ( $preferred_keys as $menu_key ) {
		if ( ! isset( $items[ $menu_key ] ) ) {
			continue;
		}

		if ( 'subscriptions' === $menu_key && isset( $filtered_items['wp-sp-subscriptions'] ) ) {
			continue;
		}

		$filtered_items[ $menu_key ] = $items[ $menu_key ];
	}

	return ! empty( $filtered_items ) ? $filtered_items : $items;
}
add_filter( 'woocommerce_account_menu_items', 'velune_filter_my_account_menu_items', 20 );

/**
 * Redirect legacy edit-address endpoint to edit-account to keep one primary settings screen.
 */
function velune_redirect_edit_address_endpoint() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ! is_user_logged_in() ) {
		return;
	}

	if ( ! function_exists( 'is_account_page' ) || ! function_exists( 'is_wc_endpoint_url' ) ) {
		return;
	}

	if ( ! is_account_page() || ! is_wc_endpoint_url( 'edit-address' ) ) {
		return;
	}

	$target = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'edit-account' ) : '';

	if ( '' !== $target ) {
		wp_safe_redirect( $target );
		exit;
	}
}
add_action( 'template_redirect', 'velune_redirect_edit_address_endpoint', 15 );

/**
 * Get ordered account address fields for rendering and validation.
 *
 * @param string $address_type Address type.
 * @param int    $user_id      User ID.
 * @return array<string, array<string, mixed>>
 */
function velune_get_account_address_fields( $address_type, $user_id ) {
	if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
		return array();
	}

	$user_id      = (int) $user_id;
	$address_type = 'shipping' === $address_type ? 'shipping' : 'billing';
	$prefix       = $address_type . '_';
	$country_key  = $prefix . 'country';
	$country      = get_user_meta( $user_id, $country_key, true );

	if ( '' === $country ) {
		$base_location = wc_get_base_location();
		$country       = isset( $base_location['country'] ) ? (string) $base_location['country'] : '';
	}

	$fields = WC()->countries->get_address_fields( $country, $prefix );

	if ( ! is_array( $fields ) ) {
		return array();
	}

	$ordered_keys = 'billing' === $address_type
		? array(
			'billing_first_name',
			'billing_last_name',
			'billing_company',
			'billing_country',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_phone',
		)
		: array(
			'shipping_first_name',
			'shipping_last_name',
			'shipping_company',
			'shipping_country',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_city',
			'shipping_state',
			'shipping_postcode',
		);

	$ordered_fields = array();

	foreach ( $ordered_keys as $field_key ) {
		if ( isset( $fields[ $field_key ] ) ) {
			$ordered_fields[ $field_key ] = $fields[ $field_key ];
		}
	}

	foreach ( $fields as $field_key => $field_args ) {
		if ( isset( $ordered_fields[ $field_key ] ) ) {
			continue;
		}

		if ( 'billing_email' === $field_key ) {
			continue;
		}

		$ordered_fields[ $field_key ] = $field_args;
	}

	return $ordered_fields;
}

/**
 * Determine if shipping address should mirror billing for account details UX.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function velune_is_shipping_same_as_billing( $user_id ) {
	$user_id    = (int) $user_id;
	$stored_preference = get_user_meta( $user_id, 'velune_shipping_same_as_billing', true );

	if ( 'yes' === $stored_preference ) {
		return true;
	}

	if ( 'no' === $stored_preference ) {
		return false;
	}

	$shared_parts = array(
		'first_name',
		'last_name',
		'company',
		'country',
		'address_1',
		'address_2',
		'city',
		'state',
		'postcode',
	);

	foreach ( $shared_parts as $part ) {
		$billing_value  = trim( (string) get_user_meta( $user_id, 'billing_' . $part, true ) );
		$shipping_value = trim( (string) get_user_meta( $user_id, 'shipping_' . $part, true ) );

		if ( $billing_value !== $shipping_value ) {
			return false;
		}
	}

	return true;
}

/**
 * Get a sanitized posted value for account details fields.
 *
 * @param string $field_key Field key.
 * @return string
 */
function velune_get_posted_account_field_value( $field_key ) {
	if ( ! isset( $_POST[ $field_key ] ) ) {
		return '';
	}

	$raw_value = wp_unslash( $_POST[ $field_key ] );

	if ( is_array( $raw_value ) ) {
		return '';
	}

	$value = wc_clean( $raw_value );

	if ( 'billing_phone' === $field_key || 'shipping_phone' === $field_key ) {
		$value = wc_sanitize_phone_number( $value );
	}

	if ( false !== strpos( $field_key, '_email' ) ) {
		$value = sanitize_email( $value );
	}

	return $value;
}

/**
 * Resolve active account-details subsection submitted from tabbed UI.
 *
 * @return string
 */
function velune_get_posted_account_details_section() {
	if ( ! isset( $_POST['velune_account_section'] ) ) {
		return '';
	}

	$section = sanitize_key( wp_unslash( $_POST['velune_account_section'] ) );

	if ( in_array( $section, array( 'profile', 'password', 'addresses' ), true ) ) {
		return $section;
	}

	return '';
}

/**
 * Read save-account-details nonce from both legacy/custom keys.
 *
 * @return string
 */
function velune_get_account_details_nonce() {
	if ( isset( $_POST['save-account-details-nonce'] ) ) {
		return (string) wp_unslash( $_POST['save-account-details-nonce'] );
	}

	if ( isset( $_POST['woocommerce-save-account-details-nonce'] ) ) {
		return (string) wp_unslash( $_POST['woocommerce-save-account-details-nonce'] );
	}

	return '';
}

/**
 * Render merged billing + shipping fields inside edit account form.
 */
function velune_render_account_address_fields() {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$user_id         = (int) get_current_user_id();
	$billing_fields  = velune_get_account_address_fields( 'billing', $user_id );
	$shipping_fields = velune_get_account_address_fields( 'shipping', $user_id );

	if ( empty( $billing_fields ) && empty( $shipping_fields ) ) {
		return;
	}

	$shipping_same_as_billing = velune_is_shipping_same_as_billing( $user_id );

	if ( isset( $_POST['velune_shipping_same_as_billing'] ) ) {
		$shipping_same_as_billing = '1' === (string) wp_unslash( $_POST['velune_shipping_same_as_billing'] );
	}
	?>
	<div class="velune-account-address-sections" data-velune-account-address-sections>
		<section class="velune-account-address-subsection">
			<h3><?php esc_html_e( 'Billing address', 'velune' ); ?></h3>
			<div class="velune-account-address-grid">
				<?php
				foreach ( $billing_fields as $field_key => $field_args ) {
					$value = isset( $_POST[ $field_key ] ) ? velune_get_posted_account_field_value( $field_key ) : (string) get_user_meta( $user_id, $field_key, true );

					woocommerce_form_field( $field_key, $field_args, $value );
				}
				?>
			</div>
		</section>

		<section class="velune-account-address-toggle velune-shipping-toggle">
			<label class="velune-account-checkbox">
				<input type="checkbox" id="velune_shipping_same_as_billing" name="velune_shipping_same_as_billing" value="1" <?php checked( $shipping_same_as_billing ); ?>>
				<span><?php esc_html_e( 'Shipping address is the same as billing', 'velune' ); ?></span>
			</label>
		</section>

		<section class="velune-account-address-subsection<?php echo $shipping_same_as_billing ? ' is-hidden' : ''; ?>" data-velune-shipping-fields>
			<h3><?php esc_html_e( 'Shipping address', 'velune' ); ?></h3>
			<div class="velune-account-address-grid">
				<?php
				foreach ( $shipping_fields as $field_key => $field_args ) {
					$value = isset( $_POST[ $field_key ] ) ? velune_get_posted_account_field_value( $field_key ) : (string) get_user_meta( $user_id, $field_key, true );

					woocommerce_form_field( $field_key, $field_args, $value );
				}
				?>
			</div>
		</section>
	</div>
	<script>
		(function () {
			var root = document.querySelector('[data-velune-account-address-sections]');

			if (!root) {
				return;
			}

			var toggle = root.querySelector('#velune_shipping_same_as_billing');
			var shippingSection = root.querySelector('[data-velune-shipping-fields]');

			if (!toggle || !shippingSection) {
				return;
			}

			var shippingInputs = shippingSection.querySelectorAll('input, select, textarea');

			var syncShippingVisibility = function () {
				var hideShipping = !!toggle.checked;

				shippingSection.classList.toggle('is-hidden', hideShipping);

				shippingInputs.forEach(function (input) {
					input.disabled = hideShipping;
				});
			};

			toggle.addEventListener('change', syncShippingVisibility);
			syncShippingVisibility();
		}());
	</script>
	<?php
}
add_action( 'woocommerce_edit_account_form', 'velune_render_account_address_fields', 15 );

/**
 * Validate account address fields submitted through account details form.
 *
 * @param WP_Error $errors Validation errors.
 * @param WP_User  $user   User object.
 */
function velune_validate_account_address_fields( $errors, $user ) {
	if ( ! ( $errors instanceof WP_Error ) || ! ( $user instanceof WP_User ) ) {
		return;
	}

	if ( ! is_user_logged_in() || (int) get_current_user_id() !== (int) $user->ID ) {
		return;
	}

	$nonce = velune_get_account_details_nonce();

	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'save_account_details' ) ) {
		return;
	}

	$section = velune_get_posted_account_details_section();

	if ( '' !== $section && 'addresses' !== $section ) {
		return;
	}

	$shipping_same_as_billing = isset( $_POST['velune_shipping_same_as_billing'] ) && '1' === (string) wp_unslash( $_POST['velune_shipping_same_as_billing'] );
	$billing_fields           = velune_get_account_address_fields( 'billing', (int) $user->ID );
	$shipping_fields          = velune_get_account_address_fields( 'shipping', (int) $user->ID );

	foreach ( $billing_fields as $field_key => $field_args ) {
		$is_required = ! empty( $field_args['required'] );

		if ( ! $is_required ) {
			continue;
		}

		if ( '' !== velune_get_posted_account_field_value( $field_key ) ) {
			continue;
		}

		$label = isset( $field_args['label'] ) ? wp_strip_all_tags( (string) $field_args['label'] ) : __( 'Billing field', 'velune' );
		$errors->add( $field_key, sprintf( __( '%s is a required field.', 'woocommerce' ), $label ) );
	}

	if ( $shipping_same_as_billing ) {
		return;
	}

	foreach ( $shipping_fields as $field_key => $field_args ) {
		$is_required = ! empty( $field_args['required'] );

		if ( ! $is_required ) {
			continue;
		}

		if ( '' !== velune_get_posted_account_field_value( $field_key ) ) {
			continue;
		}

		$label = isset( $field_args['label'] ) ? wp_strip_all_tags( (string) $field_args['label'] ) : __( 'Shipping field', 'velune' );
		$errors->add( $field_key, sprintf( __( '%s is a required field.', 'woocommerce' ), $label ) );
	}
}
add_action( 'woocommerce_save_account_details_errors', 'velune_validate_account_address_fields', 20, 2 );

/**
 * Save billing + shipping account address fields from account details form.
 *
 * @param int $user_id User ID.
 */
function velune_save_account_address_fields( $user_id ) {
	$user_id = (int) $user_id;

	if ( $user_id <= 0 || ! is_user_logged_in() || (int) get_current_user_id() !== $user_id ) {
		return;
	}

	$nonce = velune_get_account_details_nonce();

	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'save_account_details' ) ) {
		return;
	}

	$section = velune_get_posted_account_details_section();

	if ( '' !== $section && 'addresses' !== $section ) {
		return;
	}

	$billing_fields           = velune_get_account_address_fields( 'billing', $user_id );
	$shipping_fields          = velune_get_account_address_fields( 'shipping', $user_id );
	$shipping_same_as_billing = isset( $_POST['velune_shipping_same_as_billing'] ) && '1' === (string) wp_unslash( $_POST['velune_shipping_same_as_billing'] );

	update_user_meta( $user_id, 'velune_shipping_same_as_billing', $shipping_same_as_billing ? 'yes' : 'no' );

	$billing_values = array();

	foreach ( $billing_fields as $field_key => $field_args ) {
		$value = velune_get_posted_account_field_value( $field_key );
		$billing_values[ $field_key ] = $value;
		update_user_meta( $user_id, $field_key, $value );
	}

	if ( $shipping_same_as_billing ) {
		foreach ( $shipping_fields as $field_key => $field_args ) {
			$billing_key = 'billing_' . substr( $field_key, strlen( 'shipping_' ) );
			$value       = isset( $billing_values[ $billing_key ] ) ? $billing_values[ $billing_key ] : '';

			update_user_meta( $user_id, $field_key, $value );
		}

		return;
	}

	foreach ( $shipping_fields as $field_key => $field_args ) {
		$value = velune_get_posted_account_field_value( $field_key );
		update_user_meta( $user_id, $field_key, $value );
	}
}
add_action( 'woocommerce_save_account_details', 'velune_save_account_address_fields', 30 );

/**
 * Get login page URL.
 *
 * @return string
 */
