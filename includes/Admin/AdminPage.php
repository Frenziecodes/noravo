<?php
/**
 * Admin settings and onboarding page.
 *
 * @package Noravo
 */

declare(strict_types=1);

namespace Noravo\Admin;

use Noravo\Automation\AutomationRuleRepository;
use Noravo\Assets\AssetManager;
use Noravo\Integrations\IntegrationRegistry;
use Noravo\Notifications\NotificationHistoryRepository;
use Noravo\Settings\SettingsRepository;

/**
 * Renders the settings screen and handles admin form submissions.
 */
final class AdminPage {
	private SettingsRepository $settings;

	private IntegrationRegistry $integrations;

	private AssetManager $assets;

	private AutomationRuleRepository $automation_rules;
	private NotificationHistoryRepository $notification_history;

	/**
	 * @param SettingsRepository  $settings     Settings store.
	 * @param IntegrationRegistry $integrations Registered integrations.
	 * @param AssetManager                  $assets               Admin asset loader.
	 * @param AutomationRuleRepository      $automation_rules     Automation rule storage.
	 * @param NotificationHistoryRepository $notification_history Displayed notification history.
	 */
	public function __construct(SettingsRepository $settings, IntegrationRegistry $integrations, AssetManager $assets, AutomationRuleRepository $automation_rules, NotificationHistoryRepository $notification_history) {
		$this->settings             = $settings;
		$this->integrations         = $integrations;
		$this->assets               = $assets;
		$this->automation_rules     = $automation_rules;
		$this->notification_history = $notification_history;
	}

	/** Registers admin menu, assets, and save handler hooks. */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this->assets, 'enqueue_admin' ) );
		add_action( 'admin_post_noravo_save_settings', array( $this, 'save' ) );
		add_action( 'admin_post_noravo_save_automation_rule', array( $this, 'save_automation_rule' ) );
		add_action( 'admin_post_noravo_toggle_automation_rule', array( $this, 'toggle_automation_rule' ) );
		add_action( 'admin_post_noravo_delete_automation_rule', array( $this, 'delete_automation_rule' ) );
		add_action( 'admin_post_noravo_delete_automation_rules', array( $this, 'delete_automation_rules' ) );
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
			25
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
			__( 'Automation Rules', 'noravo' ),
			__( 'Automation Rules', 'noravo' ),
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

	/** Saves an automation rule from the simple setup page. */
	public function save_automation_rule(): void {
		if (! current_user_can( 'manage_options' ) ) {
			wp_die(esc_html__( 'You do not have permission to manage Noravo automation rules.', 'noravo' ) );
		}

		check_admin_referer( 'noravo_save_automation_rule' );

		$trigger = isset( $_POST['trigger']) ? sanitize_key(wp_unslash( $_POST['trigger']) ) : '';
		$action  = isset( $_POST['rule_action']) ? sanitize_key(wp_unslash( $_POST['rule_action']) ) : '';
		$status  = isset( $_POST['rule_status']) ? sanitize_key(wp_unslash( $_POST['rule_status']) ) : 'draft';
		$name    = isset( $_POST['rule_name']) ? sanitize_text_field(wp_unslash( $_POST['rule_name']) ) : '';
		$rule_id = isset( $_POST['rule_id']) ? sanitize_key(wp_unslash( $_POST['rule_id']) ) : '';

		if ('' === $name) {
			$name = __('Store purchase automation', 'noravo');
		}

		$action_definition = $this->automation_action_definition($action);
		$source            = $action_definition['source'] ?? '';

		if ('' === $trigger || '' === $action || '' === $source) {
			wp_safe_redirect(add_query_arg('page', 'noravo-campaigns', admin_url('admin.php')));
			exit;
		}

		$status = 'active' === $status ? 'active' : 'draft';
		$status_note = '';

		if ('active' === $status && ! $this->integration_source_can_run($source)) {
			$status      = 'draft';
			$status_note = $this->integration_disabled_note($source);
		}

		if ('' !== $rule_id && null !== $this->automation_rules->find($rule_id)) {
			$this->automation_rules->update_rule($rule_id, $name, $trigger, $action, $status, $source);
			if ('' !== $status_note) {
				$this->automation_rules->update_status($rule_id, 'draft', $status_note);
			}
		} else {
			$rule = $this->automation_rules->create($name, $trigger, $action, $status, $source);
			if ('' !== $status_note) {
				$this->automation_rules->update_status((string) $rule['id'], 'draft', $status_note);
			}
		}

		$redirect_args = array('updated' => 'true');

		if ('' !== $status_note) {
			$redirect_args['notice'] = 'integration_required';
		}

		wp_safe_redirect(
			wp_nonce_url(
				add_query_arg($redirect_args, admin_url('admin.php?page=noravo-campaigns')),
				'noravo_settings_updated'
			)
		);
		exit;
	}

	/** Activates or deactivates an automation rule from the rules table. */
	public function toggle_automation_rule(): void {
		if (! current_user_can( 'manage_options' ) ) {
			wp_die(esc_html__( 'You do not have permission to manage Noravo automation rules.', 'noravo' ) );
		}

		$rule_id = isset( $_GET['rule_id']) ? sanitize_key(wp_unslash( $_GET['rule_id']) ) : '';
		$state   = isset( $_GET['state']) ? sanitize_key(wp_unslash( $_GET['state']) ) : '';

		check_admin_referer( 'noravo_toggle_automation_rule_' . $rule_id );

		$notice = '';
		$rule   = '' !== $rule_id ? $this->automation_rules->find($rule_id) : null;

		if (null !== $rule) {
			if ('active' === $state && ! $this->integration_source_can_run((string) $rule['source'])) {
				$this->automation_rules->update_status($rule_id, 'draft', $this->integration_disabled_note((string) $rule['source']));
				$notice = 'integration_required';
			} else {
				$this->automation_rules->update_status($rule_id, 'active' === $state ? 'active' : 'draft');
			}
		}

		$redirect_args = array('updated' => 'true');

		if ('' !== $notice) {
			$redirect_args['notice'] = $notice;
		}

		wp_safe_redirect(
			wp_nonce_url(
				add_query_arg($redirect_args, admin_url('admin.php?page=noravo-campaigns')),
				'noravo_settings_updated'
			)
		);
		exit;
	}

	/** Deletes an automation rule from the rules table. */
	public function delete_automation_rule(): void {
		if (! current_user_can( 'manage_options' ) ) {
			wp_die(esc_html__( 'You do not have permission to manage Noravo automation rules.', 'noravo' ) );
		}

		$rule_id = isset( $_GET['rule_id']) ? sanitize_key(wp_unslash( $_GET['rule_id']) ) : '';

		check_admin_referer( 'noravo_delete_automation_rule_' . $rule_id );

		if ('' !== $rule_id) {
			$this->automation_rules->delete($rule_id);
		}

		wp_safe_redirect(
			wp_nonce_url(
				add_query_arg('updated', 'true', admin_url('admin.php?page=noravo-campaigns')),
				'noravo_settings_updated'
			)
		);
		exit;
	}

	/** Deletes checked automation rules from the rules table. */
	public function delete_automation_rules(): void {
		if (! current_user_can( 'manage_options' ) ) {
			wp_die(esc_html__( 'You do not have permission to manage Noravo automation rules.', 'noravo' ) );
		}

		check_admin_referer( 'noravo_delete_automation_rules' );

		$rule_ids = isset( $_POST['rule_ids']) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['rule_ids']) ) : array();
		$this->automation_rules->delete_many($rule_ids);

		wp_safe_redirect(
			wp_nonce_url(
				add_query_arg('updated', 'true', admin_url('admin.php?page=noravo-campaigns')),
				'noravo_settings_updated'
			)
		);
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
		$sources  = array_values(array_unique((array) $settings['enabled_sources']) );

		if ( 'activate' === $state && $this->integration_is_available( $integration) ) {
			$sources[] = $integration;
		}

		if ( 'deactivate' === $state ) {
			$sources = array_values(array_diff( $sources, array( $integration) ) );
			$this->automation_rules->pause_active_by_source($integration, $this->integration_disabled_note($integration));
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
		$rules          = $this->automation_rules->all();
		$active_rules   = array_filter($rules, static fn (array $rule): bool => 'active' === $rule['status']);
		$enabled_count  = count(
			array_filter(
				$this->integrations->all(),
				static fn ($integration): bool => in_array($integration->id(), (array) $settings['enabled_sources'], true)
			)
		);
		$recent_items   = $this->notification_history->latest(5);
		$trigger_groups = $this->automation_trigger_groups();
		$action_groups  = $this->automation_action_groups();
		?>
		<div class="wrap noravo-admin">
			<div class="noravo-shell noravo-dashboard-shell">
				<?php $this->updated_notice(); ?>
				<section class="noravo-dashboard-card">
					<header class="noravo-dashboard-card-header">
						<span class="noravo-dashboard-step">1</span>
						<h1><?php esc_html_e( 'Dashboard', 'noravo' ); ?></h1>
					</header>
					<div class="noravo-dashboard-card-body">
						<h2><?php esc_html_e( 'Noravo Dashboard', 'noravo' ); ?></h2>
						<div class="noravo-dashboard-summary">
							<div class="is-blue">
								<small><?php esc_html_e( 'Status', 'noravo' ); ?></small>
								<strong><?php echo $settings['enabled'] ? esc_html__( 'Live', 'noravo' ) : esc_html__( 'Paused', 'noravo' ); ?></strong>
								<span><?php esc_html_e( 'Notifications', 'noravo' ); ?></span>
							</div>
							<div class="is-green">
								<small><?php esc_html_e( 'Active', 'noravo' ); ?></small>
								<strong><?php echo esc_html((string) count($active_rules)); ?></strong>
								<span><?php esc_html_e( 'Automation rules', 'noravo' ); ?></span>
							</div>
							<div class="is-purple">
								<small><?php esc_html_e( 'Enabled', 'noravo' ); ?></small>
								<strong><?php echo esc_html((string) $enabled_count); ?></strong>
								<span><?php esc_html_e( 'Integrations', 'noravo' ); ?></span>
							</div>
							<div class="is-orange">
								<small><?php esc_html_e( 'Display', 'noravo' ); ?></small>
								<strong><?php echo esc_html(ucwords(str_replace( '-', ' ', (string) $settings['position']) ) ); ?></strong>
								<span><?php esc_html_e( 'Position', 'noravo' ); ?></span>
							</div>
						</div>
						<div class="noravo-dashboard-grid">
							<section class="noravo-dashboard-panel">
								<h3><?php esc_html_e( 'Recent Notifications', 'noravo' ); ?></h3>
								<ul class="noravo-dashboard-notifications">
									<?php if (empty($recent_items)) : ?>
										<li class="noravo-dashboard-empty"><?php esc_html_e( 'No recent notifications.', 'noravo' ); ?></li>
									<?php endif; ?>
									<?php foreach ( $recent_items as $item ) : ?>
										<li>
											<span class="dashicons <?php echo esc_attr($this->dashboard_notification_icon((string) $item['icon'])); ?>" aria-hidden="true"></span>
											<p><?php echo esc_html($item['message']); ?></p>
										</li>
									<?php endforeach; ?>
								</ul>
							</section>
							<section class="noravo-dashboard-panel">
								<h3><?php esc_html_e( 'Quick Actions', 'noravo' ); ?></h3>
								<div class="noravo-dashboard-actions">
									<button type="button" data-noravo-open-rule-modal>
										<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
										<?php esc_html_e( 'Create New Rule', 'noravo' ); ?>
									</button>
									<a href="<?php echo esc_url(admin_url('admin.php?page=noravo-integrations')); ?>">
										<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
										<?php esc_html_e( 'Configure Integrations', 'noravo' ); ?>
									</a>
									<a href="<?php echo esc_url(admin_url('admin.php?page=noravo-appearance')); ?>">
										<span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span>
										<?php esc_html_e( 'Configure Appearance', 'noravo' ); ?>
									</a>
									<a href="<?php echo esc_url(admin_url('admin.php?page=noravo-settings')); ?>">
										<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
										<?php esc_html_e( 'Settings', 'noravo' ); ?>
									</a>
								</div>
							</section>
						</div>
					</div>
				</section>
				<?php $this->render_rule_modal($trigger_groups, $action_groups); ?>
			</div>
		</div>
		<?php
	}

	/** Outputs the automation rules admin page. */
	public function render_campaigns(): void {
		if (! current_user_can( 'manage_options' ) ) {
			return;
		}

		$trigger_groups = $this->automation_trigger_groups();
		$action_groups  = $this->automation_action_groups();
		$rules          = $this->automation_rules->all();
		$view           = isset( $_GET['view']) ? sanitize_key(wp_unslash( $_GET['view']) ) : '';

		if ('builder' === $view) {
			$this->render_automation_builder();
			return;
		}

		$active_rules   = array_filter($rules, static fn (array $rule): bool => 'active' === $rule['status']);
		$inactive_rules = array_filter($rules, static fn (array $rule): bool => 'active' !== $rule['status']);
		$per_page       = isset( $_GET['per_page']) ? absint( wp_unslash( $_GET['per_page']) ) : 25;
		$per_page       = in_array( $per_page, array( 10, 25, 50, 100 ), true ) ? $per_page : 25;
		$sort           = isset( $_GET['sort']) ? sanitize_key(wp_unslash( $_GET['sort']) ) : 'created';
		$order          = isset( $_GET['order']) ? sanitize_key(wp_unslash( $_GET['order']) ) : 'desc';
		$sort           = in_array( $sort, array( 'rule', 'times_run', 'status', 'updated', 'created' ), true ) ? $sort : 'created';
		$order          = 'asc' === $order ? 'asc' : 'desc';
		$visible_fields = isset( $_GET['fields'])
			? array_values(array_filter(array_map('sanitize_key', explode(',', (string) wp_unslash( $_GET['fields']) ) ) ) )
			: array( 'times_run', 'status', 'updated', 'created' );
		$visible_fields = array_values(array_intersect($visible_fields, array( 'times_run', 'status', 'updated', 'created' )));
		$field_labels   = array(
			'times_run' => __( 'Times Run', 'noravo' ),
			'status'    => __( 'Status', 'noravo' ),
			'updated'   => __( 'Updated', 'noravo' ),
			'created'   => __( 'Created', 'noravo' ),
		);
		$sort_labels    = array(
			'rule'      => __( 'Rule', 'noravo' ),
			'times_run' => __( 'Times Run', 'noravo' ),
			'status'    => __( 'Status', 'noravo' ),
			'updated'   => __( 'Updated', 'noravo' ),
			'created'   => __( 'Created', 'noravo' ),
		);
		usort(
			$rules,
			static function (array $a, array $b) use ($sort, $order): int {
				$left  = match ($sort) {
					'rule'      => strtolower((string) $a['name']),
					'times_run' => absint($a['times_run']),
					'status'    => (string) $a['status'],
					'updated'   => strtotime((string) $a['updated_at']) ?: 0,
					default     => strtotime((string) $a['created_at']) ?: 0,
				};
				$right = match ($sort) {
					'rule'      => strtolower((string) $b['name']),
					'times_run' => absint($b['times_run']),
					'status'    => (string) $b['status'],
					'updated'   => strtotime((string) $b['updated_at']) ?: 0,
					default     => strtotime((string) $b['created_at']) ?: 0,
				};
				$result = $left <=> $right;

				return 'asc' === $order ? $result : -$result;
			}
		);
		$total_items    = count( $rules );
		$total_pages    = max( 1, (int) ceil( $total_items / $per_page ) );
		$current_page   = isset( $_GET['rule_page']) ? absint( wp_unslash( $_GET['rule_page']) ) : 1;
		$current_page   = max( 1, min( $current_page, $total_pages ) );
		$paged_rules    = array_slice( $rules, ( $current_page - 1 ) * $per_page, $per_page );
		$url_state      = array(
			'fields'   => implode(',', $visible_fields),
			'per_page' => $per_page,
			'sort'     => $sort,
			'order'    => $order,
		);
		$prev_page_url  = $this->automation_rules_url(array_merge($url_state, array( 'rule_page' => max( 1, $current_page - 1 ) ) ) );
		$next_page_url  = $this->automation_rules_url(array_merge($url_state, array( 'rule_page' => min( $total_pages, $current_page + 1 ) ) ) );
		$column_count   = 4 + count($visible_fields);
		?>
		<div class="wrap noravo-admin">
			<div class="noravo-shell noravo-automation-shell">
				<div class="noravo-automation-header">
					<div>
						<h1><?php esc_html_e( 'Automation Rules', 'noravo' ); ?></h1>
						<p><?php esc_html_e( 'Create rules that automatically run actions when certain events occur.', 'noravo' ); ?></p>
					</div>
					<button type="button" class="button button-primary noravo-automation-create" data-noravo-open-rule-modal><?php esc_html_e( 'Create New Rule', 'noravo' ); ?></button>
				</div>
				<div class="noravo-rule-stats">
					<div class="noravo-rule-stat">
						<span class="noravo-rule-stat-icon is-total"><span class="dashicons dashicons-networking"></span></span>
						<strong><?php echo esc_html((string) count($rules)); ?></strong>
						<small><?php esc_html_e( 'Total Rules', 'noravo' ); ?></small>
					</div>
					<div class="noravo-rule-stat">
						<span class="noravo-rule-stat-icon is-active"><span class="dashicons dashicons-yes-alt"></span></span>
						<strong><?php echo esc_html((string) count($active_rules)); ?></strong>
						<small><?php esc_html_e( 'Active', 'noravo' ); ?></small>
					</div>
					<div class="noravo-rule-stat">
						<span class="noravo-rule-stat-icon is-inactive"><span class="dashicons dashicons-dismiss"></span></span>
						<strong><?php echo esc_html((string) count($inactive_rules)); ?></strong>
						<small><?php esc_html_e( 'Inactive', 'noravo' ); ?></small>
					</div>
				</div>
				<div class="noravo-rules-table-shell">
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="noravo-rules-form">
						<input type="hidden" name="action" value="noravo_delete_automation_rules">
						<?php wp_nonce_field('noravo_delete_automation_rules'); ?>
						<div class="noravo-rules-toolbar">
							<label class="noravo-rules-search">
								<span class="dashicons dashicons-search" aria-hidden="true"></span>
								<input type="search" placeholder="<?php esc_attr_e( 'Search', 'noravo' ); ?>">
							</label>
							<div class="noravo-rules-tools">
								<button type="submit" class="noravo-rules-delete-all"><?php esc_html_e( 'Delete all', 'noravo' ); ?></button>
								<div class="noravo-rules-config">
									<button type="button" class="noravo-rules-config-toggle" aria-expanded="false" aria-haspopup="true" data-noravo-rules-config>
										<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
										<span class="screen-reader-text"><?php esc_html_e( 'Configure automation list', 'noravo' ); ?></span>
									</button>
									<div class="noravo-rules-config-menu" hidden data-noravo-rules-config-menu>
										<details>
											<summary>
												<span><?php esc_html_e( 'Sort by', 'noravo' ); ?></span>
												<em><?php echo esc_html($sort_labels[$sort]); ?></em>
												<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
											</summary>
											<div class="noravo-rules-config-options">
												<?php foreach ( $sort_labels as $sort_key => $sort_label ) : ?>
													<?php foreach ( array( 'desc' => __( 'Descending', 'noravo' ), 'asc' => __( 'Ascending', 'noravo' ) ) as $order_key => $order_label ) : ?>
														<a href="<?php echo esc_url($this->automation_rules_url(array_merge($url_state, array( 'sort' => $sort_key, 'order' => $order_key, 'rule_page' => 1 ) ) ) ); ?>">
															<span><?php echo esc_html($sort_label); ?></span>
															<em><?php echo esc_html($order_label); ?></em>
															<?php if ($sort === $sort_key && $order === $order_key) : ?>
																<span class="dashicons dashicons-yes" aria-hidden="true"></span>
															<?php endif; ?>
														</a>
													<?php endforeach; ?>
												<?php endforeach; ?>
											</div>
										</details>
										<details>
											<summary>
												<span><?php esc_html_e( 'Fields', 'noravo' ); ?></span>
												<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
											</summary>
											<div class="noravo-rules-config-options">
												<?php foreach ( $field_labels as $field_key => $field_label ) : ?>
													<?php
													$field_is_visible = in_array($field_key, $visible_fields, true);
													$next_fields      = $field_is_visible ? array_values(array_diff($visible_fields, array( $field_key ))) : array_values(array_unique(array_merge($visible_fields, array( $field_key ))));
													?>
													<a href="<?php echo esc_url($this->automation_rules_url(array_merge($url_state, array( 'fields' => implode(',', $next_fields), 'rule_page' => 1 ) ) ) ); ?>">
														<span><?php echo esc_html($field_label); ?></span>
														<?php if ($field_is_visible) : ?>
															<span class="dashicons dashicons-yes" aria-hidden="true"></span>
														<?php endif; ?>
													</a>
												<?php endforeach; ?>
											</div>
										</details>
										<details>
											<summary>
												<span><?php esc_html_e( 'Items per page', 'noravo' ); ?></span>
												<em><?php echo esc_html((string) $per_page); ?></em>
												<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
											</summary>
											<div class="noravo-rules-config-options">
												<?php foreach ( array( 10, 25, 50, 100 ) as $option ) : ?>
													<a href="<?php echo esc_url($this->automation_rules_url(array_merge($url_state, array( 'per_page' => $option, 'rule_page' => 1 ) ) ) ); ?>">
														<span><?php echo esc_html(sprintf(__('%d per page', 'noravo'), $option)); ?></span>
														<?php if ($per_page === $option) : ?>
															<span class="dashicons dashicons-yes" aria-hidden="true"></span>
														<?php endif; ?>
													</a>
												<?php endforeach; ?>
											</div>
										</details>
									</div>
								</div>
							</div>
						</div>
						<table class="widefat noravo-rules-table">
							<colgroup>
								<col class="noravo-rules-check-col">
								<col class="noravo-rules-rule-col">
								<col class="noravo-rules-steps-col">
								<?php if (in_array('times_run', $visible_fields, true)) : ?><col class="noravo-rules-runs-col"><?php endif; ?>
								<?php if (in_array('status', $visible_fields, true)) : ?><col class="noravo-rules-status-col"><?php endif; ?>
								<?php if (in_array('updated', $visible_fields, true)) : ?><col class="noravo-rules-date-col"><?php endif; ?>
								<?php if (in_array('created', $visible_fields, true)) : ?><col class="noravo-rules-date-col"><?php endif; ?>
								<col class="noravo-rules-actions-col">
							</colgroup>
							<thead>
								<tr>
									<th class="noravo-rules-check-cell">
										<label class="noravo-rules-checkbox">
											<input type="checkbox" data-noravo-select-all-rules>
											<span></span>
											<em class="screen-reader-text"><?php esc_html_e( 'Select all automation rules', 'noravo' ); ?></em>
										</label>
									</th>
									<th><?php esc_html_e( 'Rule', 'noravo' ); ?></th>
									<th><?php esc_html_e( 'Steps', 'noravo' ); ?></th>
									<?php if (in_array('times_run', $visible_fields, true)) : ?><th><?php esc_html_e( 'Times Run', 'noravo' ); ?></th><?php endif; ?>
									<?php if (in_array('status', $visible_fields, true)) : ?><th><?php esc_html_e( 'Status', 'noravo' ); ?></th><?php endif; ?>
									<?php if (in_array('updated', $visible_fields, true)) : ?><th><?php esc_html_e( 'Updated', 'noravo' ); ?></th><?php endif; ?>
									<?php if (in_array('created', $visible_fields, true)) : ?><th><?php esc_html_e( 'Created', 'noravo' ); ?> <span aria-hidden="true">&darr;</span></th><?php endif; ?>
									<th><?php esc_html_e( 'Actions', 'noravo' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if (empty($rules)) : ?>
									<tr>
										<td colspan="<?php echo esc_attr((string) $column_count); ?>" class="noravo-rules-empty"><?php esc_html_e( 'No results', 'noravo' ); ?></td>
									</tr>
								<?php endif; ?>
								<?php foreach ($paged_rules as $rule) : ?>
								<?php
								$is_active     = 'active' === $rule['status'];
								$next_status   = $is_active ? 'draft' : 'active';
								$toggle_url    = wp_nonce_url(
									add_query_arg(
										array(
											'action'  => 'noravo_toggle_automation_rule',
											'rule_id' => $rule['id'],
											'state'   => $next_status,
										),
										admin_url('admin-post.php')
									),
									'noravo_toggle_automation_rule_' . $rule['id']
								);
								$edit_url      = add_query_arg(
									array(
										'page'    => 'noravo-campaigns',
										'view'    => 'builder',
										'rule_id' => $rule['id'],
									),
									admin_url('admin.php')
								);
								$delete_url    = wp_nonce_url(
									add_query_arg(
										array(
											'action'  => 'noravo_delete_automation_rule',
											'rule_id' => $rule['id'],
										),
										admin_url('admin-post.php')
									),
									'noravo_delete_automation_rule_' . $rule['id']
								);
								$trigger_title = $this->automation_trigger_title($rule['trigger']);
								$action_title  = $this->automation_action_title($rule['action']);
								?>
								<tr>
									<td class="noravo-rules-check-cell">
										<label class="noravo-rules-checkbox">
											<input type="checkbox" name="rule_ids[]" value="<?php echo esc_attr($rule['id']); ?>" data-noravo-rule-checkbox>
											<span></span>
											<em class="screen-reader-text"><?php echo esc_html(sprintf(__('Select %s', 'noravo'), $rule['name'])); ?></em>
										</label>
									</td>
									<td>
										<div class="noravo-rule-title">
											<strong><?php echo esc_html($rule['name']); ?></strong>
											<small><?php echo esc_html($this->automation_trigger_description($rule['trigger'])); ?></small>
											<?php if (! empty($rule['status_note'])) : ?>
												<em><?php echo esc_html((string) $rule['status_note']); ?></em>
											<?php endif; ?>
										</div>
									</td>
									<td>
										<div class="noravo-rule-steps">
											<span class="noravo-rule-step-icon" title="<?php echo esc_attr($trigger_title); ?>" aria-label="<?php echo esc_attr($trigger_title); ?>"><span class="dashicons dashicons-money-alt"></span></span>
											<span aria-hidden="true">&rsaquo;</span>
											<span class="noravo-rule-step-icon" title="<?php echo esc_attr($action_title); ?>" aria-label="<?php echo esc_attr($action_title); ?>"><span class="dashicons dashicons-megaphone"></span></span>
										</div>
									</td>
									<?php if (in_array('times_run', $visible_fields, true)) : ?>
									<td><?php echo esc_html((string) $rule['times_run']); ?></td>
									<?php endif; ?>
									<?php if (in_array('status', $visible_fields, true)) : ?>
									<td>
										<a class="noravo-rule-toggle <?php echo $is_active ? 'is-active' : 'is-draft'; ?>" href="<?php echo esc_url($toggle_url); ?>" role="switch" aria-checked="<?php echo $is_active ? 'true' : 'false'; ?>">
											<span></span>
											<em class="screen-reader-text"><?php echo $is_active ? esc_html__('Deactivate rule', 'noravo') : esc_html__('Activate rule', 'noravo'); ?></em>
										</a>
									</td>
									<?php endif; ?>
									<?php if (in_array('updated', $visible_fields, true)) : ?>
									<td><?php echo esc_html($this->automation_rule_time_label((string) $rule['updated_at'])); ?></td>
									<?php endif; ?>
									<?php if (in_array('created', $visible_fields, true)) : ?>
									<td><?php echo esc_html($this->automation_rule_time_label((string) $rule['created_at'])); ?></td>
									<?php endif; ?>
									<td>
										<div class="noravo-rule-actions">
											<a href="<?php echo esc_url($edit_url); ?>" aria-label="<?php esc_attr_e('Edit rule', 'noravo'); ?>">
												<span class="dashicons dashicons-edit"></span>
											</a>
											<a class="is-delete" href="<?php echo esc_url($delete_url); ?>" aria-label="<?php esc_attr_e('Delete rule', 'noravo'); ?>">
												<span class="dashicons dashicons-trash"></span>
											</a>
										</div>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</form>
					<div class="noravo-rules-pagination">
						<form method="get" class="noravo-rules-per-page">
							<input type="hidden" name="page" value="noravo-campaigns">
							<input type="hidden" name="fields" value="<?php echo esc_attr(implode(',', $visible_fields)); ?>">
							<input type="hidden" name="sort" value="<?php echo esc_attr($sort); ?>">
							<input type="hidden" name="order" value="<?php echo esc_attr($order); ?>">
							<select name="per_page" onchange="this.form.submit()">
								<?php foreach ( array( 10, 25, 50, 100 ) as $option ) : ?>
									<option value="<?php echo esc_attr((string) $option); ?>" <?php selected($per_page, $option); ?>><?php echo esc_html(sprintf(__('%d per page', 'noravo'), $option)); ?></option>
								<?php endforeach; ?>
							</select>
						</form>
						<div class="noravo-pagination-links">
							<span><?php echo esc_html(sprintf(__('Page %1$d of %2$d', 'noravo'), $current_page, $total_pages)); ?></span>
							<?php if (1 < $current_page) : ?>
								<a href="<?php echo esc_url($prev_page_url); ?>" aria-label="<?php esc_attr_e('Previous page', 'noravo'); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span></a>
							<?php else : ?>
								<span class="is-disabled"><span class="dashicons dashicons-arrow-left-alt2"></span></span>
							<?php endif; ?>
							<?php if ($current_page < $total_pages) : ?>
								<a href="<?php echo esc_url($next_page_url); ?>" aria-label="<?php esc_attr_e('Next page', 'noravo'); ?>"><span class="dashicons dashicons-arrow-right-alt2"></span></a>
							<?php else : ?>
								<span class="is-disabled"><span class="dashicons dashicons-arrow-right-alt2"></span></span>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<div class="noravo-rule-modal" id="noravo-rule-modal" aria-hidden="true">
					<div class="noravo-rule-modal-panel" role="dialog" aria-modal="true" aria-labelledby="noravo-rule-modal-title">
						<header class="noravo-rule-modal-header">
							<h2 id="noravo-rule-modal-title" data-noravo-modal-title><?php esc_html_e( 'Select a trigger for your automation rule', 'noravo' ); ?></h2>
							<div class="noravo-rule-modal-actions">
								<button type="button" aria-label="<?php esc_attr_e( 'Go back', 'noravo' ); ?>" data-noravo-rule-back hidden>
									<span class="dashicons dashicons-arrow-left-alt2"></span>
								</button>
								<button type="button" aria-label="<?php esc_attr_e( 'Close', 'noravo' ); ?>" data-noravo-close-rule-modal>
									<span class="dashicons dashicons-no-alt"></span>
								</button>
							</div>
						</header>
						<div class="noravo-rule-modal-step is-active" data-noravo-rule-step="trigger">
							<div class="noravo-rule-modal-body">
								<nav class="noravo-trigger-categories" aria-label="<?php esc_attr_e( 'Trigger categories', 'noravo' ); ?>">
									<?php foreach ( $trigger_groups as $group_key => $group ) : ?>
										<button type="button" class="<?php echo 'orders' === $group_key ? 'is-active' : ''; ?>" data-noravo-trigger-group="<?php echo esc_attr( $group_key); ?>">
											<?php echo esc_html( $group['label']); ?>
										</button>
									<?php endforeach; ?>
								</nav>
								<div class="noravo-trigger-groups">
									<?php foreach ( $trigger_groups as $group_key => $group ) : ?>
										<section class="noravo-trigger-group <?php echo 'orders' === $group_key ? 'is-active' : ''; ?>" data-noravo-trigger-panel="<?php echo esc_attr( $group_key); ?>">
											<h3><?php echo esc_html( $group['label']); ?></h3>
											<div class="noravo-trigger-cards">
												<?php if (empty( $group['triggers']) ) : ?>
													<p class="noravo-trigger-empty"><?php esc_html_e( 'No triggers are available in this section yet.', 'noravo' ); ?></p>
												<?php endif; ?>
												<?php foreach ( $group['triggers'] as $trigger ) : ?>
													<article class="noravo-trigger-card">
														<header>
															<h4><?php echo esc_html( $trigger['title']); ?></h4>
															<span class="dashicons dashicons-money-alt" aria-hidden="true"></span>
														</header>
														<p><?php echo esc_html( $trigger['description']); ?></p>
														<button type="button" class="button button-primary" data-noravo-select-trigger="<?php echo esc_attr( $trigger['id']); ?>">
															<?php esc_html_e( 'Use trigger', 'noravo' ); ?>
															<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
														</button>
													</article>
												<?php endforeach; ?>
											</div>
										</section>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
						<div class="noravo-rule-modal-step" data-noravo-rule-step="action">
							<div class="noravo-rule-modal-body">
								<nav class="noravo-trigger-categories" aria-label="<?php esc_attr_e( 'Action categories', 'noravo' ); ?>">
									<?php foreach ( $action_groups as $group_key => $group ) : ?>
										<button type="button" class="<?php echo 'campaigns' === $group_key ? 'is-active' : ''; ?>" data-noravo-action-group="<?php echo esc_attr( $group_key); ?>">
											<?php echo esc_html( $group['label']); ?>
										</button>
									<?php endforeach; ?>
								</nav>
								<div class="noravo-trigger-groups">
									<?php foreach ( $action_groups as $group_key => $group ) : ?>
										<section class="noravo-trigger-group <?php echo 'campaigns' === $group_key ? 'is-active' : ''; ?>" data-noravo-action-panel="<?php echo esc_attr( $group_key); ?>">
											<h3><?php echo esc_html( $group['label']); ?></h3>
											<div class="noravo-trigger-cards">
												<?php foreach ( $group['actions'] as $action ) : ?>
													<article class="noravo-trigger-card">
														<header>
															<h4><?php echo esc_html( $action['title']); ?></h4>
															<span class="dashicons dashicons-megaphone" aria-hidden="true"></span>
														</header>
														<p><?php echo esc_html( $action['description']); ?></p>
														<button type="button" class="button button-primary" data-noravo-select-action="<?php echo esc_attr( $action['id']); ?>">
															<?php esc_html_e( 'Set up', 'noravo' ); ?>
															<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
														</button>
													</article>
												<?php endforeach; ?>
											</div>
										</section>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/** Renders the simple automation rule setup page. */
	private function render_automation_builder(): void {
		$rule_id = isset( $_GET['rule_id']) ? sanitize_key(wp_unslash( $_GET['rule_id']) ) : '';
		$rule    = '' !== $rule_id ? $this->automation_rules->find($rule_id) : null;
		$trigger = isset( $_GET['trigger']) ? sanitize_key(wp_unslash( $_GET['trigger']) ) : '';
		$action  = isset( $_GET['rule_action']) ? sanitize_key(wp_unslash( $_GET['rule_action']) ) : '';

		if (null !== $rule) {
			$trigger = (string) $rule['trigger'];
			$action  = (string) $rule['action'];
		}

		if ('' === $trigger || '' === $action) {
			wp_safe_redirect(admin_url('admin.php?page=noravo-campaigns'));
			exit;
		}

		$trigger_title = $this->automation_trigger_title($trigger);
		$action_title  = $this->automation_action_title($action);
		$rule_name     = null !== $rule ? (string) $rule['name'] : sprintf('%s -> %s', $trigger_title, $action_title);
		?>
		<div class="wrap noravo-admin">
			<div class="noravo-shell noravo-rule-builder-shell">
				<a class="noravo-builder-back" href="<?php echo esc_url(admin_url('admin.php?page=noravo-campaigns')); ?>">
					<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
					<?php esc_html_e('Automation Rules', 'noravo'); ?>
				</a>
				<div class="noravo-builder-header">
					<h1><?php echo null !== $rule ? esc_html__('Edit automation rule', 'noravo') : esc_html__('Set up automation rule', 'noravo'); ?></h1>
					<p><?php esc_html_e('Review the selected trigger and action, then activate the rule or save it as a draft.', 'noravo'); ?></p>
				</div>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="noravo-rule-builder-card">
					<input type="hidden" name="action" value="noravo_save_automation_rule">
					<input type="hidden" name="rule_id" value="<?php echo esc_attr($rule_id); ?>">
					<input type="hidden" name="trigger" value="<?php echo esc_attr($trigger); ?>">
					<input type="hidden" name="rule_action" value="<?php echo esc_attr($action); ?>">
					<?php wp_nonce_field('noravo_save_automation_rule'); ?>
					<div class="noravo-field">
						<label for="noravo-rule-name"><?php esc_html_e('Rule name', 'noravo'); ?></label>
						<input id="noravo-rule-name" type="text" name="rule_name" value="<?php echo esc_attr($rule_name); ?>">
					</div>
					<div class="noravo-builder-steps">
						<div>
							<span><?php esc_html_e('When', 'noravo'); ?></span>
							<strong><?php echo esc_html($trigger_title); ?></strong>
						</div>
						<div>
							<span><?php esc_html_e('Then', 'noravo'); ?></span>
							<strong><?php echo esc_html($action_title); ?></strong>
						</div>
					</div>
					<div class="noravo-builder-actions">
						<button type="submit" name="rule_status" value="draft" class="button button-secondary"><?php esc_html_e('Save as draft', 'noravo'); ?></button>
						<button type="submit" name="rule_status" value="active" class="button button-primary"><?php esc_html_e('Activate rule', 'noravo'); ?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/** Renders the create-rule trigger/action modal. */
	private function render_rule_modal(array $trigger_groups, array $action_groups): void {
		?>
		<div class="noravo-rule-modal" id="noravo-rule-modal" aria-hidden="true">
			<div class="noravo-rule-modal-panel" role="dialog" aria-modal="true" aria-labelledby="noravo-rule-modal-title">
				<header class="noravo-rule-modal-header">
					<h2 id="noravo-rule-modal-title" data-noravo-modal-title><?php esc_html_e( 'Select a trigger for your automation rule', 'noravo' ); ?></h2>
					<div class="noravo-rule-modal-actions">
						<button type="button" aria-label="<?php esc_attr_e( 'Go back', 'noravo' ); ?>" data-noravo-rule-back hidden>
							<span class="dashicons dashicons-arrow-left-alt2"></span>
						</button>
						<button type="button" aria-label="<?php esc_attr_e( 'Close', 'noravo' ); ?>" data-noravo-close-rule-modal>
							<span class="dashicons dashicons-no-alt"></span>
						</button>
					</div>
				</header>
				<div class="noravo-rule-modal-step is-active" data-noravo-rule-step="trigger">
					<div class="noravo-rule-modal-body">
						<nav class="noravo-trigger-categories" aria-label="<?php esc_attr_e( 'Trigger categories', 'noravo' ); ?>">
							<?php foreach ( $trigger_groups as $group_key => $group ) : ?>
								<button type="button" class="<?php echo 'orders' === $group_key ? 'is-active' : ''; ?>" data-noravo-trigger-group="<?php echo esc_attr( $group_key); ?>">
									<?php echo esc_html( $group['label']); ?>
								</button>
							<?php endforeach; ?>
						</nav>
						<div class="noravo-trigger-groups">
							<?php foreach ( $trigger_groups as $group_key => $group ) : ?>
								<section class="noravo-trigger-group <?php echo 'orders' === $group_key ? 'is-active' : ''; ?>" data-noravo-trigger-panel="<?php echo esc_attr( $group_key); ?>">
									<h3><?php echo esc_html( $group['label']); ?></h3>
									<div class="noravo-trigger-cards">
										<?php foreach ( $group['triggers'] as $trigger ) : ?>
											<article class="noravo-trigger-card">
												<header>
													<h4><?php echo esc_html( $trigger['title']); ?></h4>
													<span class="dashicons dashicons-money-alt" aria-hidden="true"></span>
												</header>
												<p><?php echo esc_html( $trigger['description']); ?></p>
												<button type="button" class="button button-primary" data-noravo-select-trigger="<?php echo esc_attr( $trigger['id']); ?>">
													<?php esc_html_e( 'Use trigger', 'noravo' ); ?>
													<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
												</button>
											</article>
										<?php endforeach; ?>
									</div>
								</section>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<div class="noravo-rule-modal-step" data-noravo-rule-step="action">
					<div class="noravo-rule-modal-body">
						<nav class="noravo-trigger-categories" aria-label="<?php esc_attr_e( 'Action categories', 'noravo' ); ?>">
							<?php foreach ( $action_groups as $group_key => $group ) : ?>
								<button type="button" class="<?php echo 'campaigns' === $group_key ? 'is-active' : ''; ?>" data-noravo-action-group="<?php echo esc_attr( $group_key); ?>">
									<?php echo esc_html( $group['label']); ?>
								</button>
							<?php endforeach; ?>
						</nav>
						<div class="noravo-trigger-groups">
							<?php foreach ( $action_groups as $group_key => $group ) : ?>
								<section class="noravo-trigger-group <?php echo 'campaigns' === $group_key ? 'is-active' : ''; ?>" data-noravo-action-panel="<?php echo esc_attr( $group_key); ?>">
									<h3><?php echo esc_html( $group['label']); ?></h3>
									<div class="noravo-trigger-cards">
										<?php foreach ( $group['actions'] as $action ) : ?>
											<article class="noravo-trigger-card">
												<header>
													<h4><?php echo esc_html( $action['title']); ?></h4>
													<span class="dashicons dashicons-megaphone" aria-hidden="true"></span>
												</header>
												<p><?php echo esc_html( $action['description']); ?></p>
												<button type="button" class="button button-primary" data-noravo-select-action="<?php echo esc_attr( $action['id']); ?>">
													<?php esc_html_e( 'Set up', 'noravo' ); ?>
													<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
												</button>
											</article>
										<?php endforeach; ?>
									</div>
								</section>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/** Maps frontend icon keys to dashboard dashicons. */
	private function dashboard_notification_icon(string $icon): string {
		return match (sanitize_key($icon)) {
			'bag'   => 'dashicons-cart',
			'spark' => 'dashicons-star-filled',
			'star'  => 'dashicons-star-filled',
			default => 'dashicons-marker',
		};
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

		$notice = isset( $_GET['notice']) ? sanitize_key(wp_unslash( $_GET['notice']) ) : '';

		if ('integration_required' === $notice) {
			?>
			<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Enable the required integration before activating this automation rule.', 'noravo' ); ?></p></div>
			<?php
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

	/** Whether an integration source is enabled and available. */
	private function integration_source_can_run(string $source): bool {
		$source   = sanitize_key($source);
		$settings = $this->settings->all();

		return in_array($source, (array) $settings['enabled_sources'], true) && $this->integration_is_available($source);
	}

	/** Returns an integration label by source key. */
	private function integration_label(string $integration_id): string {
		foreach ( $this->integrations->all() as $integration ) {
			if ( $integration->id() === $integration_id ) {
				return $integration->label();
			}
		}

		return ucwords(str_replace('_', ' ', sanitize_key($integration_id)));
	}

	/** Returns the note shown when a rule is paused by integration state. */
	private function integration_disabled_note(string $source): string {
		return sprintf(
			/* translators: %s: Integration name. */
			__('%s disabled', 'noravo'),
			$this->integration_label($source)
		);
	}

	/**
	 * Returns automation trigger groups shown in the rule picker.
	 *
	 * @return array<string, array{label: string, triggers: array<int, array<string, string>>}>
	 */
	private function automation_trigger_groups(): array {
		$order_triggers = array(
			array(
				'id'          => 'woocommerce_order_processing',
				'title'       => __( 'Order > Processing', 'noravo' ),
				'description' => __( 'When a WooCommerce order is marked as processing.', 'noravo' ),
				'integration' => 'woocommerce',
			),
			array(
				'id'          => 'woocommerce_order_completed',
				'title'       => __( 'Order > Completed', 'noravo' ),
				'description' => __( 'When a WooCommerce order is completed.', 'noravo' ),
				'integration' => 'woocommerce',
			),
		);

		return array(
			'featured' => array(
				'label'    => __( 'Featured', 'noravo' ),
				'triggers' => $order_triggers,
			),
			'orders'   => array(
				'label'    => __( 'Orders', 'noravo' ),
				'triggers' => $order_triggers,
			),
		);
	}

	/**
	 * Returns automation action groups shown after a trigger is selected.
	 *
	 * @return array<string, array{label: string, actions: array<int, array<string, string>>}>
	 */
	private function automation_action_groups(): array {
		$campaign_actions = array(
			array(
				'id'          => 'display_store_purchases',
				'title'       => __( 'Display Store Purchases', 'noravo' ),
				'description' => __( 'Show the WooCommerce purchase notification campaign on the frontend.', 'noravo' ),
				'campaign'    => 'woocommerce',
				'source'      => 'woocommerce',
			),
		);

		return array(
			'featured'  => array(
				'label'   => __( 'Featured', 'noravo' ),
				'actions' => $campaign_actions,
			),
			'campaigns' => array(
				'label'   => __( 'Campaigns', 'noravo' ),
				'actions' => $campaign_actions,
			),
		);
	}

	/** Returns a trigger title by ID. */
	private function automation_trigger_title(string $trigger_id): string {
		$trigger = $this->automation_trigger_definition($trigger_id);

		return $trigger['title'] ?? __('Unknown trigger', 'noravo');
	}

	/** Returns a trigger description by ID. */
	private function automation_trigger_description(string $trigger_id): string {
		$trigger = $this->automation_trigger_definition($trigger_id);

		return $trigger['description'] ?? '';
	}

	/** @return array<string, string> */
	private function automation_trigger_definition(string $trigger_id): array {
		foreach ($this->automation_trigger_groups() as $group) {
			foreach ($group['triggers'] as $trigger) {
				if ($trigger['id'] === $trigger_id) {
					return $trigger;
				}
			}
		}

		return array();
	}

	/** Returns an action title by ID. */
	private function automation_action_title(string $action_id): string {
		$action = $this->automation_action_definition($action_id);

		return $action['title'] ?? __('Unknown action', 'noravo');
	}

	/** @return array<string, string> */
	private function automation_action_definition(string $action_id): array {
		foreach ($this->automation_action_groups() as $group) {
			foreach ($group['actions'] as $action) {
				if ($action['id'] === $action_id) {
					return $action;
				}
			}
		}

		return array();
	}

	/** Formats a stored rule timestamp for the automation table. */
	private function automation_rule_time_label(string $mysql): string {
		if ('' === $mysql) {
			return '—';
		}

		$timestamp = strtotime($mysql);

		if (false === $timestamp) {
			return $mysql;
		}

		return sprintf(
			/* translators: %s: Human-readable elapsed time. */
			__('%s ago', 'noravo'),
			human_time_diff($timestamp, current_time('timestamp'))
		);
	}

	/** Builds an automation rules list URL while preserving known table state. */
	private function automation_rules_url(array $args = array()): string {
		return add_query_arg(
			array_merge(
				array(
					'page' => 'noravo-campaigns',
				),
				$args
			),
			admin_url('admin.php')
		);
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
