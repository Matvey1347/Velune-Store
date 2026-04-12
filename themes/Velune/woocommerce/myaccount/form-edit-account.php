<?php
/**
 * Custom Edit account form structure.
 *
 * @package Velune
 * @version 10.5.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hook - woocommerce_before_edit_account_form.
 *
 * @since 2.6.0
 */
do_action( 'woocommerce_before_edit_account_form' );
?>

<div class="velune-account-tabs" data-velune-account-tabs>
	<nav class="velune-account-tabs-nav" aria-label="<?php esc_attr_e( 'Account sections', 'velune' ); ?>">
		<button type="button" class="velune-account-tab-button is-active" data-velune-tab-trigger="profile" aria-controls="velune-account-panel-profile"><?php esc_html_e( 'Profile details', 'velune' ); ?></button>
		<button type="button" class="velune-account-tab-button" data-velune-tab-trigger="password" aria-controls="velune-account-panel-password"><?php esc_html_e( 'Change password', 'velune' ); ?></button>
		<button type="button" class="velune-account-tab-button" data-velune-tab-trigger="addresses" aria-controls="velune-account-panel-addresses"><?php esc_html_e( 'Billing & Shipping', 'velune' ); ?></button>
	</nav>

	<div class="velune-account-tab-panels">
		<section class="velune-account-tab-panel is-active" id="velune-account-panel-profile" data-velune-tab-panel="profile">
			<form class="woocommerce-EditAccountForm edit-account velune-edit-account-form" action="" method="post" <?php do_action( 'woocommerce_edit_account_form_tag' ); ?> >
				<section class="velune-account-section velune-account-section-profile">
					<header class="velune-account-section-header">
						<h2><?php esc_html_e( 'Profile details', 'velune' ); ?></h2>
					</header>

					<?php do_action( 'woocommerce_edit_account_form_start' ); ?>

					<p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
						<label for="account_first_name"><?php esc_html_e( 'First name', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
						<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_first_name" id="account_first_name" autocomplete="given-name" value="<?php echo esc_attr( $user->first_name ); ?>" aria-required="true" />
					</p>
					<p class="woocommerce-form-row woocommerce-form-row--last form-row form-row-last">
						<label for="account_last_name"><?php esc_html_e( 'Last name', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
						<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_last_name" id="account_last_name" autocomplete="family-name" value="<?php echo esc_attr( $user->last_name ); ?>" aria-required="true" />
					</p>
					<div class="clear"></div>

					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="account_display_name"><?php esc_html_e( 'Display name', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
						<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_display_name" id="account_display_name" aria-describedby="account_display_name_description" value="<?php echo esc_attr( $user->display_name ); ?>" aria-required="true" /> <span id="account_display_name_description"><em><?php esc_html_e( 'This will be how your name will be displayed in the account section and in reviews', 'woocommerce' ); ?></em></span>
					</p>
					<div class="clear"></div>

					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="account_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
						<input type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="account_email" id="account_email" autocomplete="email" value="<?php echo esc_attr( $user->user_email ); ?>" aria-required="true" />
					</p>

					<?php
					/**
					 * Hook where additional fields should be rendered.
					 *
					 * @since 8.7.0
					 */
					do_action( 'woocommerce_edit_account_form_fields' );
					?>
				</section>

				<p>
					<?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
					<input type="hidden" name="velune_account_section" value="profile" />
					<input type="hidden" name="action" value="save_account_details" />
					<button type="submit" class="woocommerce-Button button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="save_account_details" value="<?php esc_attr_e( 'Save profile details', 'velune' ); ?>"><?php esc_html_e( 'Save profile details', 'velune' ); ?></button>
				</p>

				<?php do_action( 'woocommerce_edit_account_form_end' ); ?>
			</form>
		</section>

		<section class="velune-account-tab-panel" id="velune-account-panel-password" data-velune-tab-panel="password" hidden>
			<form class="woocommerce-EditAccountForm edit-account velune-edit-account-form" action="" method="post">
				<section class="velune-account-section velune-account-section-password">
					<header class="velune-account-section-header">
						<h2><?php esc_html_e( 'Change password', 'velune' ); ?></h2>
					</header>

					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="password_current"><?php esc_html_e( 'Current password (leave blank to leave unchanged)', 'woocommerce' ); ?></label>
						<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_current" id="password_current" autocomplete="current-password" />
					</p>
					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="password_1"><?php esc_html_e( 'New password (leave blank to leave unchanged)', 'woocommerce' ); ?></label>
						<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_1" id="password_1" autocomplete="new-password" />
					</p>
					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="password_2"><?php esc_html_e( 'Confirm new password', 'woocommerce' ); ?></label>
						<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_2" id="password_2" autocomplete="new-password" />
					</p>
				</section>

				<input type="hidden" name="account_first_name" value="<?php echo esc_attr( $user->first_name ); ?>" />
				<input type="hidden" name="account_last_name" value="<?php echo esc_attr( $user->last_name ); ?>" />
				<input type="hidden" name="account_display_name" value="<?php echo esc_attr( $user->display_name ); ?>" />
				<input type="hidden" name="account_email" value="<?php echo esc_attr( $user->user_email ); ?>" />

				<p>
					<?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
					<input type="hidden" name="velune_account_section" value="password" />
					<input type="hidden" name="action" value="save_account_details" />
					<button type="submit" class="woocommerce-Button button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="save_account_details" value="<?php esc_attr_e( 'Save password', 'velune' ); ?>"><?php esc_html_e( 'Save password', 'velune' ); ?></button>
				</p>
			</form>
		</section>

		<section class="velune-account-tab-panel" id="velune-account-panel-addresses" data-velune-tab-panel="addresses" hidden>
			<form class="woocommerce-EditAccountForm edit-account velune-edit-account-form" action="" method="post">
				<section class="velune-account-section velune-account-section-addresses">
					<header class="velune-account-section-header">
						<h2><?php esc_html_e( 'Billing & Shipping', 'velune' ); ?></h2>
					</header>

					<?php
					/**
					 * My Account edit account form.
					 *
					 * @since 2.6.0
					 */
					do_action( 'woocommerce_edit_account_form' );
					?>
				</section>

				<input type="hidden" name="account_first_name" value="<?php echo esc_attr( $user->first_name ); ?>" />
				<input type="hidden" name="account_last_name" value="<?php echo esc_attr( $user->last_name ); ?>" />
				<input type="hidden" name="account_display_name" value="<?php echo esc_attr( $user->display_name ); ?>" />
				<input type="hidden" name="account_email" value="<?php echo esc_attr( $user->user_email ); ?>" />

				<p>
					<?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
					<input type="hidden" name="velune_account_section" value="addresses" />
					<input type="hidden" name="action" value="save_account_details" />
					<button type="submit" class="woocommerce-Button button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="save_account_details" value="<?php esc_attr_e( 'Save billing & shipping', 'velune' ); ?>"><?php esc_html_e( 'Save billing & shipping', 'velune' ); ?></button>
				</p>
			</form>
		</section>
	</div>
</div>

<script>
	(function () {
		var tabsRoot = document.querySelector('[data-velune-account-tabs]');

		if (!tabsRoot) {
			return;
		}

		var buttons = tabsRoot.querySelectorAll('[data-velune-tab-trigger]');
		var panels = tabsRoot.querySelectorAll('[data-velune-tab-panel]');

		if (!buttons.length || !panels.length) {
			return;
		}

		var activateTab = function (key) {
			buttons.forEach(function (button) {
				var isActive = button.getAttribute('data-velune-tab-trigger') === key;
				button.classList.toggle('is-active', isActive);
				button.setAttribute('aria-selected', isActive ? 'true' : 'false');
			});

			panels.forEach(function (panel) {
				var isActive = panel.getAttribute('data-velune-tab-panel') === key;
				panel.classList.toggle('is-active', isActive);
				panel.hidden = !isActive;
			});
		};

		buttons.forEach(function (button) {
			button.addEventListener('click', function () {
				activateTab(button.getAttribute('data-velune-tab-trigger'));
			});
		});

		var postedSection = <?php echo wp_json_encode( isset( $_POST['velune_account_section'] ) ? sanitize_key( wp_unslash( $_POST['velune_account_section'] ) ) : '' ); ?>;
		var hasErrors = document.body.classList.contains('woocommerce-invalid') || !!document.querySelector('.woocommerce-error, .woocommerce-NoticeGroup--error');
		var initialSection = 'profile';

		if (postedSection && (postedSection === 'profile' || postedSection === 'password' || postedSection === 'addresses')) {
			initialSection = postedSection;
		} else if (hasErrors) {
			initialSection = 'password';
		}

		activateTab(initialSection);
	}());
</script>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>
