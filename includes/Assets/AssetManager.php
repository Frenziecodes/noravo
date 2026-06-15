<?php
/**
 * Asset registration.
 *
 * @package Fomozo
 */

declare(strict_types=1);

namespace Fomozo\Assets;

use Fomozo\Settings\SettingsRepository;

final class AssetManager {
	private SettingsRepository $settings;

	public function __construct(SettingsRepository $settings) {
		$this->settings = $settings;
	}

	public function register_frontend(): void {
		wp_register_style(
			'fomozo-frontend',
			FOMOZO_URL . 'assets/css/frontend.css',
			array(),
			FOMOZO_VERSION
		);

		wp_register_script(
			'fomozo-frontend',
			FOMOZO_URL . 'assets/js/frontend.js',
			array(),
			FOMOZO_VERSION,
			true
		);

		$settings = $this->settings->all();

		wp_localize_script(
			'fomozo-frontend',
			'fomozoConfig',
			array(
				'restUrl'      => esc_url_raw(rest_url('fomozo/v1/notifications')),
				'position'     => $settings['position'],
				'animation'    => $settings['animation'],
				'initialDelay' => $settings['initial_delay'],
				'interval'     => $settings['interval'],
				'maxPerPage'   => $settings['max_per_page'],
				'i18n'         => array(
					'justNow'    => __('Just now', 'fomozo'),
					'minuteAgo'  => __('1 minute ago', 'fomozo'),
					/* translators: %d is the number of minutes since the notification event. */
					'minutesAgo' => __('%d minutes ago', 'fomozo'),
					'hourAgo'    => __('1 hour ago', 'fomozo'),
					/* translators: %d is the number of hours since the notification event. */
					'hoursAgo'   => __('%d hours ago', 'fomozo'),
				),
			)
		);
	}

	public function enqueue_frontend(): void {
		$this->register_frontend();
		wp_enqueue_style('fomozo-frontend');
		wp_enqueue_script('fomozo-frontend');
	}

	public function enqueue_admin(string $hook): void {
		if ('toplevel_page_fomozo' !== $hook) {
			return;
		}

		wp_enqueue_style(
			'fomozo-admin',
			FOMOZO_URL . 'assets/css/admin.css',
			array(),
			FOMOZO_VERSION
		);
	}
}
