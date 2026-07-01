<?php
/**
 * Notifications REST endpoint.
 *
 * @package Noravo
 */

declare(strict_types=1);

namespace Noravo\Rest;

use Noravo\Automation\AutomationRuleRepository;
use Noravo\Notifications\NotificationHistoryRepository;
use Noravo\Notifications\NotificationProviderRegistry;
use Noravo\Settings\SettingsRepository;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exposes notification data via the REST API.
 */
final class NotificationsController {
	private SettingsRepository $settings;

	private NotificationProviderRegistry $providers;

	private AutomationRuleRepository $automation_rules;
	private NotificationHistoryRepository $notification_history;

	/**
	 * @param SettingsRepository           $settings  Plugin settings store.
	 * @param NotificationProviderRegistry $providers Notification source registry.
	 */
	public function __construct(SettingsRepository $settings, NotificationProviderRegistry $providers, AutomationRuleRepository $automation_rules, NotificationHistoryRepository $notification_history) {
		$this->settings             = $settings;
		$this->providers            = $providers;
		$this->automation_rules     = $automation_rules;
		$this->notification_history = $notification_history;
	}

	/** Registers REST route hooks. */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Registers the notifications collection route. */
	public function register_routes(): void {
		register_rest_route(
			'noravo/v1',
			'/notifications',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'index' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'limit' => array(
						'type'              => 'integer',
						'default'           => $this->settings->all()['max_per_page'],
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'noravo/v1',
			'/notifications/displayed',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'record_displayed' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Returns notifications from enabled sources.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response
	 */
	public function index(WP_REST_Request $request): WP_REST_Response {
		if ( ! $this->settings->is_enabled() ) {
			return new WP_REST_Response( array( 'notifications' => array() ), 200 );
		}

		$settings            = $this->settings->all();
		$enabled_sources     = (array) $settings['enabled_sources'];
		$active_rule_sources = array_values(
			array_filter(
				$this->automation_rules->active_sources(),
				static fn (string $source): bool => in_array($source, $enabled_sources, true)
			)
		);
		$sources             = $settings['enabled_sources'];
		$sources             = array_values(array_unique(array_merge($sources, $active_rule_sources)));

		$limit         = max( 1, min( (int) $request->get_param( 'limit' ), (int) $settings['max_per_page'] ) );
		$notifications = $this->providers->collect( $sources, $limit );
		$source_counts = array();

		foreach ( $notifications as $notification ) {
			$source = sanitize_key((string) ($notification['source'] ?? ''));

			if (in_array($source, $active_rule_sources, true)) {
				$source_counts[$source] = ($source_counts[$source] ?? 0) + 1;
			}
		}

		if (! empty($source_counts)) {
			$this->automation_rules->record_runs_for_sources($source_counts);
		}

		return new WP_REST_Response(
			array(
				'notifications' => $notifications,
			),
			200
		);
	}

	/** Records a notification after it is displayed on the frontend. */
	public function record_displayed(WP_REST_Request $request): WP_REST_Response {
		$notification = $request->get_json_params();

		if (! is_array($notification)) {
			$notification = array();
		}

		$this->notification_history->record($notification);

		return new WP_REST_Response(array( 'recorded' => true ), 200);
	}
}
