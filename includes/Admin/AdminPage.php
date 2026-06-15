<?php
/**
 * Admin settings and onboarding page.
 *
 * @package Fomozo
 */

declare(strict_types=1);

namespace Fomozo\Admin;

use Fomozo\Assets\AssetManager;
use Fomozo\Integrations\IntegrationRegistry;
use Fomozo\Settings\SettingsRepository;

final class AdminPage {
	private SettingsRepository $settings;

	private IntegrationRegistry $integrations;

	private AssetManager $assets;

	public function __construct(SettingsRepository $settings, IntegrationRegistry $integrations, AssetManager $assets) {
		$this->settings     = $settings;
		$this->integrations = $integrations;
		$this->assets       = $assets;
	}

	public function register(): void {
		add_action('admin_menu', array($this, 'add_menu'));
		add_action('admin_enqueue_scripts', array($this->assets, 'enqueue_admin'));
		add_action('admin_post_fomozo_save_settings', array($this, 'save'));
		add_filter('plugin_action_links_' . FOMOZO_BASENAME, array($this, 'action_links'));
	}

	public function add_menu(): void {
		add_menu_page(
			__('Fomozo', 'fomozo'),
			__('Fomozo', 'fomozo'),
			'manage_options',
			'fomozo',
			array($this, 'render'),
			'dashicons-megaphone',
			58
		);
	}

	public function action_links(array $links): array {
		$settings = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url(admin_url('admin.php?page=fomozo')),
			esc_html__('Settings', 'fomozo')
		);

		array_unshift($links, $settings);

		return $links;
	}

	public function save(): void {
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to manage Fomozo settings.', 'fomozo'));
		}

		check_admin_referer('fomozo_save_settings');

		$enabled_sources = isset($_POST['enabled_sources'])
			? array_map('sanitize_key', (array) wp_unslash($_POST['enabled_sources']))
			: array();

		$this->settings->update(
			array(
				'enabled'         => isset($_POST['enabled']),
				'demo_mode'       => isset($_POST['demo_mode']),
				'position'        => isset($_POST['position']) ? sanitize_text_field(wp_unslash($_POST['position'])) : '',
				'animation'       => isset($_POST['animation']) ? sanitize_text_field(wp_unslash($_POST['animation'])) : '',
				'initial_delay'   => isset($_POST['initial_delay']) ? absint(wp_unslash($_POST['initial_delay'])) : 0,
				'interval'        => isset($_POST['interval']) ? absint(wp_unslash($_POST['interval'])) : 0,
				'max_per_page'    => isset($_POST['max_per_page']) ? absint(wp_unslash($_POST['max_per_page'])) : 0,
				'enabled_sources' => $enabled_sources,
			)
		);

		update_option('fomozo_onboarding_complete', 'yes', false);

		wp_safe_redirect(wp_nonce_url(add_query_arg('updated', 'true', admin_url('admin.php?page=fomozo')), 'fomozo_settings_updated'));
		exit;
	}

	public function render(): void {
		if (! current_user_can('manage_options')) {
			return;
		}

		$settings       = $this->settings->all();
		$is_onboarding  = 'yes' !== get_option('fomozo_onboarding_complete', 'no');
		$integrations   = $this->integrations->all();
		$enabled_sources = $settings['enabled_sources'];
		$show_updated    = isset($_GET['updated'], $_GET['_wpnonce'])
			&& wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'fomozo_settings_updated')
			&& 'true' === sanitize_text_field(wp_unslash($_GET['updated']));
		?>
		<div class="wrap fomozo-admin">
			<div class="fomozo-shell">
				<header class="fomozo-hero">
					<div>
						<p class="fomozo-kicker"><?php esc_html_e('Modern social proof and trust signals for WordPress', 'fomozo'); ?></p>
						<h1><?php esc_html_e('Fomozo', 'fomozo'); ?></h1>
						<p><?php esc_html_e('Launch subtle, believable conversion notifications without adding bloat to your site.', 'fomozo'); ?></p>
					</div>
					<div class="fomozo-status">
						<span><?php echo $settings['enabled'] ? esc_html__('Live', 'fomozo') : esc_html__('Paused', 'fomozo'); ?></span>
					</div>
				</header>

				<?php if ($show_updated) : ?>
					<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Fomozo settings saved.', 'fomozo'); ?></p></div>
				<?php endif; ?>

				<?php if ($is_onboarding) : ?>
					<section class="fomozo-panel fomozo-onboarding">
						<div>
							<h2><?php esc_html_e('Start with a confident preview', 'fomozo'); ?></h2>
							<p><?php esc_html_e('Demo mode is enabled by default so you can see Fomozo working immediately. Connect WooCommerce when you are ready to use real purchase activity.', 'fomozo'); ?></p>
						</div>
						<ul>
							<?php foreach ($integrations as $integration) : ?>
								<li>
									<strong><?php echo esc_html($integration->label()); ?></strong>
									<span><?php echo $integration->is_available() ? esc_html__('Detected', 'fomozo') : esc_html__('Not installed', 'fomozo'); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="fomozo-grid">
					<input type="hidden" name="action" value="fomozo_save_settings">
					<?php wp_nonce_field('fomozo_save_settings'); ?>

					<section class="fomozo-panel">
						<h2><?php esc_html_e('General', 'fomozo'); ?></h2>
						<?php $this->toggle('enabled', __('Enable notifications', 'fomozo'), __('Show Fomozo notifications on the frontend.', 'fomozo'), $settings['enabled']); ?>
						<?php $this->toggle('demo_mode', __('Demo mode', 'fomozo'), __('Use sample notifications for instant previews.', 'fomozo'), $settings['demo_mode']); ?>
					</section>

					<section class="fomozo-panel">
						<h2><?php esc_html_e('Integrations', 'fomozo'); ?></h2>
						<input type="hidden" name="enabled_sources[]" value="demo">
						<?php foreach ($integrations as $integration) : ?>
							<label class="fomozo-check">
								<input type="checkbox" name="enabled_sources[]" value="<?php echo esc_attr($integration->id()); ?>" <?php checked(in_array($integration->id(), $enabled_sources, true)); ?> <?php disabled(! $integration->is_available()); ?>>
								<span>
									<strong>
										<?php echo esc_html($integration->label()); ?>
										<?php $this->help($integration->description()); ?>
									</strong>
								</span>
								<em><?php echo $integration->is_available() ? esc_html__('Available', 'fomozo') : esc_html__('Install plugin', 'fomozo'); ?></em>
							</label>
						<?php endforeach; ?>
					</section>

					<section class="fomozo-panel">
						<h2><?php esc_html_e('Display', 'fomozo'); ?></h2>
						<div class="fomozo-field">
							<label for="fomozo-position">
								<?php esc_html_e('Position', 'fomozo'); ?>
								<?php $this->help(__('Where notifications appear on the visitor-facing site.', 'fomozo')); ?>
							</label>
							<select id="fomozo-position" name="position">
								<?php foreach (array('bottom-left', 'bottom-right', 'top-left', 'top-right') as $position) : ?>
									<option value="<?php echo esc_attr($position); ?>" <?php selected($settings['position'], $position); ?>><?php echo esc_html(ucwords(str_replace('-', ' ', $position))); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="fomozo-field">
							<label for="fomozo-animation">
								<?php esc_html_e('Animation', 'fomozo'); ?>
								<?php $this->help(__('The entrance style used when each notification appears.', 'fomozo')); ?>
							</label>
							<select id="fomozo-animation" name="animation">
								<option value="slide" <?php selected($settings['animation'], 'slide'); ?>><?php esc_html_e('Slide', 'fomozo'); ?></option>
								<option value="fade" <?php selected($settings['animation'], 'fade'); ?>><?php esc_html_e('Fade', 'fomozo'); ?></option>
							</select>
						</div>
						<div class="fomozo-field-row">
							<?php $this->number('initial_delay', __('Initial delay', 'fomozo'), __('How long Fomozo waits before showing the first notification, in milliseconds.', 'fomozo'), $settings['initial_delay']); ?>
							<?php $this->number('interval', __('Interval', 'fomozo'), __('How long Fomozo waits between notifications, in milliseconds.', 'fomozo'), $settings['interval']); ?>
							<?php $this->number('max_per_page', __('Maximum per page', 'fomozo'), __('The most notifications a visitor can see during a single page visit.', 'fomozo'), $settings['max_per_page']); ?>
						</div>
					</section>

					<div class="fomozo-actions">
						<button type="submit" class="button button-primary button-hero"><?php esc_html_e('Save settings', 'fomozo'); ?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	private function toggle(string $name, string $label, string $description, bool $checked): void {
		?>
		<label class="fomozo-toggle">
			<input type="checkbox" name="<?php echo esc_attr($name); ?>" <?php checked($checked); ?>>
			<span></span>
			<div>
				<strong>
					<?php echo esc_html($label); ?>
					<?php $this->help($description); ?>
				</strong>
			</div>
		</label>
		<?php
	}

	private function number(string $name, string $label, string $description, int $value): void {
		?>
		<div class="fomozo-field">
			<label for="fomozo-<?php echo esc_attr($name); ?>">
				<?php echo esc_html($label); ?>
				<?php $this->help($description); ?>
			</label>
			<input id="fomozo-<?php echo esc_attr($name); ?>" type="number" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) $value); ?>" min="0" step="1">
		</div>
		<?php
	}

	private function help(string $description): void {
		?>
		<span class="fomozo-help" tabindex="0" aria-label="<?php echo esc_attr($description); ?>">
			<span aria-hidden="true">?</span>
			<small role="tooltip"><?php echo esc_html($description); ?></small>
		</span>
		<?php
	}
}
