<?php
/**
 * Main plugin composition root.
 *
 * @package Noravo
 */

declare( strict_types=1 );

namespace Noravo;

use Noravo\Admin\AdminPage;
use Noravo\Assets\AssetManager;
use Noravo\Frontend\Frontend;
use Noravo\Integrations\IntegrationRegistry;
use Noravo\Integrations\WooCommerce\WooCommerceIntegration;
use Noravo\Notifications\DemoNotificationProvider;
use Noravo\Notifications\NotificationProviderRegistry;
use Noravo\Rest\NotificationsController;
use Noravo\Settings\SettingsRepository;

/**
 * Bootstraps services and wires plugin hooks.
 */
final class Plugin {
	private static ?self $instance = null;

	private SettingsRepository $settings;

	private NotificationProviderRegistry $providers;

	private IntegrationRegistry $integrations;

	/** Returns the singleton plugin instance. */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/** Runs first-time setup on plugin activation. */
	public static function activate(): void {
		SettingsRepository::install_defaults();
		add_option( 'noravo_onboarding_complete', 'no', '', false );
	}

	/** Initializes registries, assets, and frontend/admin hooks. */
	public function boot(): void {
		$this->settings     = new SettingsRepository();
		$this->providers    = new NotificationProviderRegistry();
		$this->integrations = new IntegrationRegistry();

		$this->register_providers();
		$this->register_integrations();
		$this->maybe_disable_demo_for_real_orders();

		$assets = new AssetManager( $this->settings );

		( new Frontend( $this->settings, $assets ) )->register();
		( new NotificationsController( $this->settings, $this->providers ) )->register();

		if ( is_admin() ) {
			( new AdminPage( $this->settings, $this->integrations, $assets ) )->register();
		}
	}

	/** Registers built-in and third-party notification providers. */
	private function register_providers(): void {
		$this->providers->register( new DemoNotificationProvider() );

		/**
		 * Register custom notification providers.
		 *
		 * @param NotificationProviderRegistry $providers Provider registry.
		 */
		do_action( 'noravo_register_notification_providers', $this->providers );
	}

	/** Registers integrations and their notification providers when available. */
	private function register_integrations(): void {
		$woocommerce = new WooCommerceIntegration( $this->settings );
		$this->integrations->register( $woocommerce );

		if ( $woocommerce->is_available() ) {
			$this->providers->register( $woocommerce );
		}

		/**
		 * Register custom integrations.
		 *
		 * @param IntegrationRegistry $integrations Integration registry.
		 */
		do_action( 'noravo_register_integrations', $this->integrations );
	}

	/** Disables demo mode once WooCommerce has real order data. */
	private function maybe_disable_demo_for_real_orders(): void {
		$settings = $this->settings->all();

		if ( empty( $settings['demo_mode'] ) ) {
			return;
		}

		$woocommerce = new WooCommerceIntegration( $this->settings );

		if ( $woocommerce->has_real_data() ) {
			$sources   = $settings['enabled_sources'];
			$sources[] = 'woocommerce';

			$this->settings->update(
				array(
					'demo_mode'       => false,
					'enabled_sources' => array_values(array_unique( $sources ) ),
				)
			);
		}
	}
}
