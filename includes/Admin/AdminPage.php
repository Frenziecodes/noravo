<?php
/**
 * Admin settings and onboarding page.
 *
 * @package Noravo
 */

declare(strict_types=1);

namespace Noravo\Admin;

use Noravo\Assets\AssetManager;
use Noravo\Integrations\IntegrationRegistry;
use Noravo\Settings\SettingsRepository;

/**
 * Renders the settings screen and handles admin form submissions.
 */
final class AdminPage {
	private SettingsRepository $settings;

	private IntegrationRegistry $integrations;

	private AssetManager $assets;

	/**
	 * @param SettingsRepository  $settings     Settings store.
	 * @param IntegrationRegistry $integrations Registered integrations.
	 * @param AssetManager        $assets       Admin asset loader.
	 */
	public function __construct(SettingsRepository $settings, IntegrationRegistry $integrations, AssetManager $assets) {
		$this->settings     = $settings;
		$this->integrations = $integrations;
		$this->assets       = $assets;
	}

	/** Registers admin menu, assets, and save handler hooks. */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this->assets, 'enqueue_admin' ) );
		add_action( 'admin_post_noravo_save_settings', array( $this, 'save' ) );
		add_action( 'admin_post_noravo_toggle_integration', array( $this, 'toggle_integration' ) );
		add_filter( 'plugin_action_links_' . NORAVO_BASENAME, array( $this, 'action_links' ) );
	}

	/** Adds the top-level Noravo admin menu item. */
	public function add_menu(): void {
		add_menu_page(
			__( 'Dashboard', 'noravo' ),
			__( 'Noravo', 'noravo' ),
			'manage_options',
			'noravo',
			array( $this, 'render_dashboard' ),
			'dashicons-megaphone',
			58
		);

		add_submenu_page(
			'noravo',
			__( 'Dashboard', 'noravo' ),
			__( 'Dashboard', 'noravo' ),
			'manage_options',
			'noravo',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'noravo',
			__( 'Campaigns', 'noravo' ),
			__( 'Campaigns', 'noravo' ),
			'manage_options',
			'noravo-campaigns',
			array( $this, 'render_campaigns' )
		);

		add_submenu_page(
			'noravo',
			__( 'Integrations', 'noravo' ),
			__( 'Integrations', 'noravo' ),
			'manage_options',
			'noravo-integrations',
			array( $this, 'render_integrations' )
		);

		add_submenu_page(
			'noravo',
			__( 'Appearance', 'noravo' ),
			__( 'Appearance', 'noravo' ),
			'manage_options',
			'noravo-appearance',
			array( $this, 'render_appearance' )
		);

		add_submenu_page(
			'noravo',
			__( 'Settings', 'noravo' ),
			__( 'Settings', 'noravo' ),
			'manage_options',
			'noravo-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Prepends a Settings link on the plugins list screen.
	 *
	 * @param array<int, string> $links Existing plugin action links.
	 * @return array<int, string>
	 */
	public function action_links(array $links): array {
		$settings = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url(admin_url( 'admin.php?page=noravo-settings' ) ),
			esc_html__( 'Settings', 'noravo' )
		);

		array_unshift( $links, $settings );

		return $links;
	}

	/** Validates and persists settings submitted from the admin form. */
	public function save(): void {
		if (! current_user_can( 'manage_options' ) ) {
			wp_die(esc_html__( 'You do not have permission to manage Noravo settings.', 'noravo' ) );
		}

		check_admin_referer( 'noravo_save_settings' );

		$form    = isset( $_POST['noravo_form']) ? sanitize_key(wp_unslash( $_POST['noravo_form']) ) : '';
		$updates = array();

		if ( 'campaigns' === $form ) {
			$updates['enabled']   = isset( $_POST['enabled']);
			$updates['demo_mode'] = isset( $_POST['demo_mode']);
		}

		if ( 'integrations' === $form ) {
			$updates['enabled_sources'] = isset( $_POST['enabled_sources'])
				? array_map( 'sanitize_key', (array) wp_unslash( $_POST['enabled_sources']) )
				: array();
		}

		if ( 'appearance' === $form ) {
			if (isset( $_POST['position']) ) {
				$updates['position'] = sanitize_text_field(wp_unslash( $_POST['position']) );
			}

			if (isset( $_POST['animation']) ) {
				$updates['animation'] = sanitize_text_field(wp_unslash( $_POST['animation']) );
			}
		}

		if ( 'settings' === $form ) {
			if (isset( $_POST['time_format']) ) {
				$updates['time_format'] = sanitize_text_field(wp_unslash( $_POST['time_format']) );
			}

			if (isset( $_POST['customer_display']) ) {
				$updates['customer_display'] = sanitize_text_field(wp_unslash( $_POST['customer_display']) );
			}

			if (isset( $_POST['initial_delay']) ) {
				$updates['initial_delay'] = absint(wp_unslash( $_POST['initial_delay']) );
			}

			if (isset( $_POST['interval']) ) {
				$updates['interval'] = absint(wp_unslash( $_POST['interval']) );
			}

			if (isset( $_POST['max_per_page']) ) {
				$updates['max_per_page'] = absint(wp_unslash( $_POST['max_per_page']) );
			}
		}

		if ( ! empty( $updates) ) {
			$this->settings->update( $updates);
		}

		update_option( 'noravo_onboarding_complete', 'yes', false);

		$redirect = isset( $_POST['redirect_to']) ? esc_url_raw(wp_unslash( $_POST['redirect_to']) ) : admin_url( 'admin.php?page=noravo' );

		wp_safe_redirect(wp_nonce_url(add_query_arg( 'updated', 'true', $redirect), 'noravo_settings_updated' ) );
		exit;
	}

	/** Activates or deactivates a single integration source. */
	public function toggle_integration(): void {
		if (! current_user_can( 'manage_options' ) ) {
			wp_die(esc_html__( 'You do not have permission to manage Noravo integrations.', 'noravo' ) );
		}

		$integration = isset( $_GET['integration']) ? sanitize_key(wp_unslash( $_GET['integration']) ) : '';
		$state       = isset( $_GET['state']) ? sanitize_key(wp_unslash( $_GET['state']) ) : '';

		check_admin_referer( 'noravo_toggle_integration_' . $integration );

		$settings = $this->settings->all();
		$sources  = array_values(array_unique(array_merge(array( 'demo' ), (array) $settings['enabled_sources']) ) );

		if ( 'activate' === $state && $this->integration_is_available( $integration) ) {
			$sources[] = $integration;
		}

		if ( 'deactivate' === $state ) {
			$sources = array_values(array_diff( $sources, array( $integration) ) );
		}

		$this->settings->update(
			array(
				'enabled_sources' => array_values(array_unique( $sources) ),
			)
		);

		wp_safe_redirect(
			wp_nonce_url(
				add_query_arg( 'updated', 'true', admin_url( 'admin.php?page=noravo-integrations' ) ),
				'noravo_settings_updated'
			)
		);
		exit;
	}

	/** Outputs the dashboard admin page. */
	public function render_dashboard(): void {
		if (! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings       = $this->settings->all();
		$is_onboarding  = 'yes' !== get_option( 'noravo_onboarding_complete', 'no' );
		$integrations   = $this->integrations->all();
		$enabled_count  = count( $settings['enabled_sources']);
		?>
		<div class="wrap noravo-admin">
			<div class="noravo-shell">
				<?php $this->header( __( 'Dashboard', 'noravo' ), __( 'Overview of notifications and plugin status.', 'noravo' ), $settings); ?>
				<?php $this->updated_notice(); ?>

				<?php if ( $is_onboarding) : ?>
					<section class="noravo-panel noravo-onboarding">
						<div>
							<h2><?php esc_html_e( 'Start with a confident preview', 'noravo' ); ?></h2>
							<p><?php esc_html_e( 'Demo mode is enabled by default so you can see Noravo working immediately. Connect WooCommerce when you are ready to use real purchase activity.', 'noravo' ); ?></p>
						</div>
						<ul>
							<?php foreach ( $integrations as $integration) : ?>
								<li>
									<strong><?php echo esc_html( $integration->label() ); ?></strong>
									<span><?php echo $integration->is_available() ? esc_html__( 'Detected', 'noravo' ) : esc_html__( 'Not installed', 'noravo' ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<div class="noravo-grid">
					<section class="noravo-panel">
						<h2><?php esc_html_e( 'Summary', 'noravo' ); ?></h2>
						<div class="noravo-summary">
							<div>
								<strong><?php echo $settings['enabled'] ? esc_html__( 'Live', 'noravo' ) : esc_html__( 'Paused', 'noravo' ); ?></strong>
								<span><?php esc_html_e( 'Notification status', 'noravo' ); ?></span>
							</div>
							<div>
								<strong><?php echo $settings['demo_mode'] ? esc_html__( 'On', 'noravo' ) : esc_html__( 'Off', 'noravo' ); ?></strong>
								<span><?php esc_html_e( 'Demo mode', 'noravo' ); ?></span>
							</div>
							<div>
								<strong><?php echo esc_html((string) $enabled_count); ?></strong>
								<span><?php esc_html_e( 'Enabled sources', 'noravo' ); ?></span>
							</div>
							<div>
								<strong><?php echo esc_html(ucwords(str_replace( '-', ' ', (string) $settings['position']) ) ); ?></strong>
								<span><?php esc_html_e( 'Position', 'noravo' ); ?></span>
							</div>
						</div>
					</section>

					<section class="noravo-panel">
						<h2><?php esc_html_e( 'Integrations', 'noravo' ); ?></h2>
						<?php foreach ( $integrations as $integration) : ?>
							<?php $available = $integration->is_available(); ?>
							<label class="noravo-check">
								<input type="checkbox" <?php checked($available); ?> disabled>
								<span>
									<strong>
										<?php echo esc_html( $integration->label() ); ?>
										<?php $this->help( $integration->description() ); ?>
									</strong>
								</span>
								<em><?php echo $available ? esc_html__( 'Detected', 'noravo' ) : esc_html__( 'Not installed', 'noravo' ); ?></em>
							</label>
						<?php endforeach; ?>
					</section>
				</div>
			</div>
		</div>
		<?php
	}

	/** Outputs the campaigns admin page. */
	public function render_campaigns(): void {
		if (! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->settings->all();
		?>
		<div class="wrap noravo-admin">
			<div class="noravo-shell">
				<?php $this->header( __( 'Campaigns', 'noravo' ), __( 'Control whether notification campaigns run on the frontend.', 'noravo' ), $settings); ?>
				<?php $this->updated_notice(); ?>
				<form method="post" action="<?php echo esc_url(admin_url( 'admin-post.php' ) ); ?>" class="noravo-grid">
					<?php $this->form_fields( 'campaigns'); ?>
					<section class="noravo-panel">
						<h2><?php esc_html_e( 'Campaign Status', 'noravo' ); ?></h2>
						<?php $this->toggle( 'enabled', __( 'Enable notifications', 'noravo' ), __( 'Show Noravo notifications on the frontend.', 'noravo' ), $settings['enabled']); ?>
						<?php $this->toggle( 'demo_mode', __( 'Demo mode', 'noravo' ), __( 'Use sample notifications for instant previews.', 'noravo' ), $settings['demo_mode']); ?>
					</section>
					<?php $this->save_actions(); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/** Outputs the integrations admin page. */
	public function render_integrations(): void {
		if (! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->settings->all();

		if ( ! class_exists( '\WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		$table = new IntegrationsListTable( $this->integrations->all(), $settings['enabled_sources']);
		$table->prepare_items();
		?>
		<div class="wrap noravo-admin">
			<div class="noravo-shell">
				<?php $this->updated_notice(); ?>
				<div class="noravo-list-table-wrap">
					<?php $table->display(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/** Outputs the appearance admin page. */
	public function render_appearance(): void {
		if (! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->settings->all();
		$tabs     = array(
			'templates'  => __( 'Templates', 'noravo' ),
			'layout'     => __( 'Layout', 'noravo' ),
			'colors'     => __( 'Colors', 'noravo' ),
			'typography' => __( 'Typography', 'noravo' ),
			'avatar'     => __( 'Avatar / Icon', 'noravo' ),
			'position'   => __( 'Position', 'noravo' ),
			'animations' => __( 'Animations', 'noravo' ),
		);
		$active_tab = isset( $_GET['tab']) ? sanitize_key(wp_unslash( $_GET['tab']) ) : 'templates';

		if ( ! isset( $tabs[$active_tab]) ) {
			$active_tab = 'templates';
		}
		?>
		<div class="wrap noravo-admin">
			<div class="noravo-shell noravo-appearance-shell">
				<?php $this->updated_notice(); ?>
				<nav class="noravo-settings-tabbar noravo-appearance-tabbar" aria-label="<?php esc_attr_e( 'Appearance sections', 'noravo' ); ?>">
					<?php foreach ( $tabs as $tab => $label ) : ?>
						<a
							class="noravo-settings-tab <?php echo $active_tab === $tab ? 'is-active' : ''; ?>"
							href="<?php echo esc_url(add_query_arg(array( 'page' => 'noravo-appearance', 'tab' => $tab), admin_url( 'admin.php' ) ) ); ?>"
						>
							<?php echo esc_html( $label); ?>
						</a>
					<?php endforeach; ?>
				</nav>
				<form method="post" action="<?php echo esc_url(admin_url( 'admin-post.php' ) ); ?>" class="noravo-settings-form noravo-appearance-form">
					<?php $this->form_fields( 'appearance'); ?>
					<div class="noravo-appearance-layout">
						<section class="noravo-panel noravo-settings-panel noravo-appearance-panel">
							<h2><?php echo esc_html( $tabs[$active_tab]); ?></h2>
							<?php if ( 'templates' === $active_tab ) : ?>
								<div class="noravo-template-options" aria-label="<?php esc_attr_e( 'Notification templates', 'noravo' ); ?>">
									<button type="button" class="is-active"><?php esc_html_e( 'Default', 'noravo' ); ?></button>
									<button type="button"><?php esc_html_e( 'Minimal', 'noravo' ); ?></button>
									<button type="button"><?php esc_html_e( 'Modern', 'noravo' ); ?></button>
									<button type="button"><?php esc_html_e( 'Glass', 'noravo' ); ?></button>
									<button type="button"><?php esc_html_e( 'Compact', 'noravo' ); ?></button>
									<button type="button"><?php esc_html_e( 'Dark', 'noravo' ); ?></button>
									<button type="button"><?php esc_html_e( 'Rounded', 'noravo' ); ?></button>
								</div>
							<?php endif; ?>
							<?php if ( 'position' === $active_tab ) : ?>
								<div class="noravo-field">
									<label for="noravo-position">
										<?php esc_html_e( 'Position', 'noravo' ); ?>
										<?php $this->help(__( 'Where notifications appear on the visitor-facing site.', 'noravo' ) ); ?>
									</label>
									<select id="noravo-position" name="position">
										<?php foreach (array( 'bottom-left', 'bottom-right', 'top-left', 'top-right' ) as $position) : ?>
											<option value="<?php echo esc_attr( $position); ?>" <?php selected( $settings['position'], $position); ?>><?php echo esc_html(ucwords(str_replace( '-', ' ', $position) )); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
							<?php endif; ?>
							<?php if ( 'animations' === $active_tab ) : ?>
								<div class="noravo-field">
									<label for="noravo-animation">
										<?php esc_html_e( 'Animation', 'noravo' ); ?>
										<?php $this->help(__( 'The entrance style used when each notification appears.', 'noravo' ) ); ?>
									</label>
									<select id="noravo-animation" name="animation">
										<option value="slide" <?php selected( $settings['animation'], 'slide' ); ?>><?php esc_html_e( 'Slide', 'noravo' ); ?></option>
										<option value="fade" <?php selected( $settings['animation'], 'fade' ); ?>><?php esc_html_e( 'Fade', 'noravo' ); ?></option>
									</select>
								</div>
							<?php endif; ?>
							<?php if (in_array( $active_tab, array( 'layout', 'colors', 'typography', 'avatar' ), true) ) : ?>
								<div class="noravo-appearance-placeholder">
									<?php esc_html_e( 'Controls for this section will be added here.', 'noravo' ); ?>
								</div>
							<?php endif; ?>
						</section>
						<div class="noravo-live-preview" aria-label="<?php esc_attr_e( 'Live preview', 'noravo' ); ?>">
							<div class="noravo-live-preview-stage">
								<div class="noravo-preview-toast">
									<span class="noravo-preview-avatar"></span>
									<span>
										<strong><?php esc_html_e( 'John Doe', 'noravo' ); ?></strong>
										<em><?php esc_html_e( 'purchased Hoodie', 'noravo' ); ?></em>
										<small><?php esc_html_e( '2 mins ago  •  Nairobi, KE', 'noravo' ); ?></small>
									</span>
								</div>
							</div>
							<div class="noravo-live-preview-note">
								<strong><?php esc_html_e( 'Live Preview', 'noravo' ); ?></strong>
								<span><?php esc_html_e( 'Preview controls will be connected later.', 'noravo' ); ?></span>
							</div>
						</div>
					</div>
					<?php $this->save_actions(); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/** Outputs the settings admin page. */
	public function render_settings(): void {
		if (! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->settings->all();
		$tabs     = array(
			'general'  => __( 'General', 'noravo' ),
			'timing'   => __( 'Timing', 'noravo' ),
			'behavior' => __( 'Behavior', 'noravo' ),
		);
		$active_tab = isset( $_GET['tab']) ? sanitize_key(wp_unslash( $_GET['tab']) ) : 'general';

		if ( ! isset( $tabs[$active_tab]) ) {
			$active_tab = 'general';
		}
		?>
		<div class="wrap noravo-admin">
			<div class="noravo-shell noravo-settings-shell">
				<?php $this->updated_notice(); ?>
				<nav class="noravo-settings-tabbar" aria-label="<?php esc_attr_e( 'Settings sections', 'noravo' ); ?>">
					<?php foreach ( $tabs as $tab => $label ) : ?>
						<a
							class="noravo-settings-tab <?php echo $active_tab === $tab ? 'is-active' : ''; ?>"
							href="<?php echo esc_url(add_query_arg(array( 'page' => 'noravo-settings', 'tab' => $tab), admin_url( 'admin.php' ) ) ); ?>"
						>
							<?php echo esc_html( $label); ?>
						</a>
					<?php endforeach; ?>
				</nav>
				<form method="post" action="<?php echo esc_url(admin_url( 'admin-post.php' ) ); ?>" class="noravo-settings-form">
					<?php $this->form_fields( 'settings'); ?>
					<?php if ( 'general' === $active_tab ) : ?>
						<section class="noravo-panel noravo-settings-panel">
							<h2><?php esc_html_e( 'General', 'noravo' ); ?></h2>
							<div class="noravo-field">
								<label for="noravo-time-format">
									<?php esc_html_e( 'Time display', 'noravo' ); ?>
									<?php $this->help(__( 'Choose how much detail to show in notification timestamps after the first day.', 'noravo' ) ); ?>
								</label>
								<select id="noravo-time-format" name="time_format">
									<option value="rounded" <?php selected( $settings['time_format'], 'rounded' ); ?>><?php esc_html_e( '3 days ago', 'noravo' ); ?></option>
									<option value="days_hours" <?php selected( $settings['time_format'], 'days_hours' ); ?>><?php esc_html_e( '3 days 5 hours ago', 'noravo' ); ?></option>
									<option value="full" <?php selected( $settings['time_format'], 'full' ); ?>><?php esc_html_e( '3 days 5 hours 40 minutes ago', 'noravo' ); ?></option>
								</select>
							</div>
							<div class="noravo-field">
								<label for="noravo-customer-display">
									<?php esc_html_e( 'Customer display', 'noravo' ); ?>
									<?php $this->help(__( 'Choose how customer names appear in purchase notifications.', 'noravo' ) ); ?>
								</label>
								<select id="noravo-customer-display" name="customer_display">
									<option value="location" <?php selected( $settings['customer_display'], 'location' ); ?>><?php esc_html_e( 'Hide name, show location', 'noravo' ); ?></option>
									<option value="full_name" <?php selected( $settings['customer_display'], 'full_name' ); ?>><?php esc_html_e( 'Show full name', 'noravo' ); ?></option>
									<option value="masked_name" <?php selected( $settings['customer_display'], 'masked_name' ); ?>><?php esc_html_e( 'Show first name and masked last name', 'noravo' ); ?></option>
								</select>
							</div>
						</section>
					<?php endif; ?>
					<?php if ( 'timing' === $active_tab ) : ?>
						<section class="noravo-panel noravo-settings-panel">
							<h2><?php esc_html_e( 'Timing', 'noravo' ); ?></h2>
							<div class="noravo-settings-fields">
								<?php $this->number( 'initial_delay', __( 'Initial delay', 'noravo' ), __( 'How long Noravo waits before showing the first notification, in milliseconds.', 'noravo' ), $settings['initial_delay']); ?>
								<?php $this->number( 'interval', __( 'Interval', 'noravo' ), __( 'How long Noravo waits between notifications, in milliseconds.', 'noravo' ), $settings['interval']); ?>
							</div>
						</section>
					<?php endif; ?>
					<?php if ( 'behavior' === $active_tab ) : ?>
						<section class="noravo-panel noravo-settings-panel">
							<h2><?php esc_html_e( 'Behavior', 'noravo' ); ?></h2>
							<div class="noravo-settings-fields">
								<?php $this->number( 'max_per_page', __( 'Maximum per page', 'noravo' ), __( 'The most notifications a visitor can see during a single page visit.', 'noravo' ), $settings['max_per_page']); ?>
							</div>
						</section>
					<?php endif; ?>
					<?php $this->save_actions(); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/** Renders the shared page header. */
	private function header(string $title, string $description, array $settings): void {
		?>
		<header class="noravo-hero">
			<div>
				<p class="noravo-kicker"><?php esc_html_e( 'Modern social proof and trust signals for WordPress', 'noravo' ); ?></p>
				<h1><?php echo esc_html( $title); ?></h1>
				<p><?php echo esc_html( $description); ?></p>
			</div>
			<div class="noravo-status">
				<span><?php echo $settings['enabled'] ? esc_html__( 'Live', 'noravo' ) : esc_html__( 'Paused', 'noravo' ); ?></span>
			</div>
		</header>
		<?php
	}

	/** Renders the saved settings notice. */
	private function updated_notice(): void {
		$show_updated = isset( $_GET['updated'], $_GET['_wpnonce'])
			&& wp_verify_nonce(sanitize_text_field(wp_unslash( $_GET['_wpnonce']) ), 'noravo_settings_updated' )
			&& 'true' === sanitize_text_field(wp_unslash( $_GET['updated']) );

		if ( ! $show_updated) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Noravo settings saved.', 'noravo' ); ?></p></div>
		<?php
	}

	/** Renders shared form hidden fields. */
	private function form_fields(string $form): void {
		$page         = sanitize_key( wp_unslash( $_GET['page'] ?? 'noravo' ) );
		$redirect_url = admin_url( 'admin.php?page=' . $page );

		if (in_array( $page, array( 'noravo-settings', 'noravo-appearance' ), true) && isset( $_GET['tab']) ) {
			$tab = sanitize_key(wp_unslash( $_GET['tab']) );
			$allowed_tabs = 'noravo-appearance' === $page
				? array( 'templates', 'layout', 'colors', 'typography', 'avatar', 'position', 'animations' )
				: array( 'general', 'timing', 'behavior' );

			if (in_array( $tab, $allowed_tabs, true) ) {
				$redirect_url = add_query_arg( 'tab', $tab, $redirect_url);
			}
		}
		?>
		<input type="hidden" name="action" value="noravo_save_settings">
		<input type="hidden" name="noravo_form" value="<?php echo esc_attr( $form); ?>">
		<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_url ); ?>">
		<?php wp_nonce_field( 'noravo_save_settings' ); ?>
		<?php
	}

	/** Renders shared form actions. */
	private function save_actions(): void {
		?>
		<div class="noravo-actions">
			<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save settings', 'noravo' ); ?></button>
		</div>
		<?php
	}

	/** Whether a registered integration is currently available. */
	private function integration_is_available(string $integration_id): bool {
		foreach ( $this->integrations->all() as $integration ) {
			if ( $integration->id() === $integration_id ) {
				return $integration->is_available();
			}
		}

		return false;
	}

	/** Renders a styled checkbox toggle field. */
	private function toggle(string $name, string $label, string $description, bool $checked): void {
		?>
		<label class="noravo-toggle">
			<input type="checkbox" name="<?php echo esc_attr( $name); ?>" <?php checked( $checked); ?>>
			<span></span>
			<div>
				<strong>
					<?php echo esc_html( $label); ?>
					<?php $this->help( $description); ?>
				</strong>
			</div>
		</label>
		<?php
	}

	/** Renders a numeric settings field. */
	private function number(string $name, string $label, string $description, int $value): void {
		?>
		<div class="noravo-field">
			<label for="noravo-<?php echo esc_attr( $name); ?>">
				<?php echo esc_html( $label); ?>
				<?php $this->help( $description); ?>
			</label>
			<input id="noravo-<?php echo esc_attr( $name); ?>" type="number" name="<?php echo esc_attr( $name); ?>" value="<?php echo esc_attr((string) $value); ?>" min="0" step="1">
		</div>
		<?php
	}

	/** Renders an inline help tooltip trigger. */
	private function help(string $description): void {
		?>
		<span class="noravo-help" tabindex="0" aria-label="<?php echo esc_attr( $description); ?>">
			<span aria-hidden="true">?</span>
			<small role="tooltip"><?php echo esc_html( $description); ?></small>
		</span>
		<?php
	}
}
