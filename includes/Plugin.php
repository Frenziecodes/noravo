<?php
/**
 * Main plugin composition root.
 *
 * @package Fomozo
 */

declare(strict_types=1);

namespace Fomozo;

use Fomozo\Admin\AdminPage;
use Fomozo\Assets\AssetManager;
use Fomozo\Frontend\Frontend;
use Fomozo\Integrations\IntegrationRegistry;
use Fomozo\Integrations\WooCommerceIntegration;
use Fomozo\Notifications\DemoNotificationProvider;
use Fomozo\Notifications\NotificationProviderRegistry;
use Fomozo\Rest\NotificationsController;
use Fomozo\Settings\SettingsRepository;

final class Plugin {
	private static ?self $instance = null;

	private SettingsRepository $settings;

	private NotificationProviderRegistry $providers;

	private IntegrationRegistry $integrations;

	public static function instance(): self {
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate(): void {
		SettingsRepository::install_defaults();
		add_option('fomozo_onboarding_complete', 'no', '', false);
	}

	public function boot(): void {
		$this->settings     = new SettingsRepository();
		$this->providers    = new NotificationProviderRegistry();
		$this->integrations = new IntegrationRegistry();

		$this->register_providers();
		$this->register_integrations();
		$this->maybe_disable_demo_for_real_orders();

		$assets = new AssetManager($this->settings);

		(new Frontend($this->settings, $assets))->register();
		(new NotificationsController($this->settings, $this->providers))->register();

		if (is_admin()) {
			(new AdminPage($this->settings, $this->integrations, $assets))->register();
		}
	}

	private function register_providers(): void {
		$this->providers->register(new DemoNotificationProvider());

		/**
		 * Register custom notification providers.
		 *
		 * @param NotificationProviderRegistry $providers Provider registry.
		 */
		do_action('fomozo_register_notification_providers', $this->providers);
	}

	private function register_integrations(): void {
		$woocommerce = new WooCommerceIntegration($this->settings);
		$this->integrations->register($woocommerce);

		if ($woocommerce->is_available()) {
			$this->providers->register($woocommerce);
		}

		/**
		 * Register custom integrations.
		 *
		 * @param IntegrationRegistry $integrations Integration registry.
		 */
		do_action('fomozo_register_integrations', $this->integrations);
	}

	private function maybe_disable_demo_for_real_orders(): void {
		$settings = $this->settings->all();

		if (empty($settings['demo_mode'])) {
			return;
		}

		$woocommerce = new WooCommerceIntegration($this->settings);

		if ($woocommerce->has_real_data()) {
			$sources   = $settings['enabled_sources'];
			$sources[] = 'woocommerce';

			$this->settings->update(
				array(
					'demo_mode'       => false,
					'enabled_sources' => array_values(array_unique($sources)),
				)
			);
		}
	}
}
