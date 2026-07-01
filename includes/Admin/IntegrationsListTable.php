<?php
/**
 * Integrations list table.
 *
 * @package Noravo
 */

declare(strict_types=1);

namespace Noravo\Admin;

use Noravo\Integrations\IntegrationInterface;

/**
 * Displays integrations using the native WordPress list table pattern.
 */
final class IntegrationsListTable extends \WP_List_Table {
	/** @var array<int, IntegrationInterface> */
	private array $integrations;

	/** @var array<int, string> */
	private array $enabled_sources;

	/**
	 * @param array<int|string, IntegrationInterface> $integrations    Registered integrations.
	 * @param array<int, string>                     $enabled_sources Enabled source IDs.
	 */
	public function __construct(array $integrations, array $enabled_sources) {
		parent::__construct(
			array(
				'singular' => 'integration',
				'plural'   => 'integrations',
				'ajax'     => false,
			)
		);

		$this->integrations    = array_values($integrations);
		$this->enabled_sources = $enabled_sources;
	}

	/** Prepares integration rows. */
	public function prepare_items(): void {
		$this->_column_headers = array($this->get_columns(), array(), array(), 'integration');
		$this->items           = $this->integrations;

		$this->set_pagination_args(
			array(
				'total_items' => count($this->items),
				'per_page'    => max(1, count($this->items)),
				'total_pages' => 1,
			)
		);
	}

	/** @return array<string, string> */
	public function get_columns(): array {
		return array(
			'icon'        => __('Icon', 'noravo'),
			'integration' => __('Integration', 'noravo'),
			'description' => __('Description', 'noravo'),
			'status'      => __('Status', 'noravo'),
		);
	}

	/** @return array<int, string> */
	protected function get_table_classes(): array {
		return array('widefat', 'fixed', 'striped', 'plugins', 'noravo-integrations-list-table');
	}

	/**
	 * Renders the icon column.
	 *
	 * @param IntegrationInterface $item Integration row.
	 */
	public function column_icon($item): string {
		return sprintf(
			'<img class="noravo-integration-icon" src="%1$s" alt="%2$s" width="24" height="24" loading="lazy" style="display:inline-block;width:24px;height:24px;max-width:24px;max-height:24px;object-fit:contain;vertical-align:middle;border-radius:4px;">',
			esc_url($item->icon_url()),
			esc_attr($item->label())
		);
	}

	/**
	 * Renders the integration column.
	 *
	 * @param IntegrationInterface $item Integration row.
	 */
	public function column_integration($item): string {
		$actions = array();

		if ($item->is_available()) {
			$is_enabled = $this->is_enabled($item);
			$state      = $is_enabled ? 'deactivate' : 'activate';
			$label      = $is_enabled ? __('Deactivate', 'noravo') : __('Activate', 'noravo');
			$action     = $is_enabled ? 'deactivate' : 'activate';

			$actions[$action] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url($this->action_url($item, $state)),
				esc_html($label)
			);
		}

		return sprintf(
			'<strong>%1$s</strong>%2$s',
			esc_html($item->label()),
			$this->row_actions($actions)
		);
	}

	/**
	 * Renders the description column.
	 *
	 * @param IntegrationInterface $item Integration row.
	 */
	public function column_description($item): string {
		if (! $item->is_available()) {
			return sprintf(
				'<span class="description">%s</span>',
				esc_html__('Install and activate the required plugin to use this integration.', 'noravo')
			);
		}

		return sprintf(
			'<span class="description">%s</span>',
			esc_html($item->description())
		);
	}

	/**
	 * Renders the status column.
	 *
	 * @param IntegrationInterface $item Integration row.
	 */
	public function column_status($item): string {
		$is_enabled = $this->is_enabled($item);
		$class      = $is_enabled ? 'is-active' : 'is-inactive';
		$label      = $is_enabled ? __('Active', 'noravo') : __('Inactive', 'noravo');

		return sprintf(
			'<span class="noravo-status-badge %1$s">%2$s</span>',
			esc_attr($class),
			esc_html($label)
		);
	}

	/** Builds the activation/deactivation URL. */
	private function action_url(IntegrationInterface $item, string $state): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'      => 'noravo_toggle_integration',
					'integration' => $item->id(),
					'state'       => $state,
				),
				admin_url('admin-post.php')
			),
			'noravo_toggle_integration_' . $item->id()
		);
	}

	/**
	 * Fallback column renderer.
	 *
	 * @param IntegrationInterface $item        Integration row.
	 * @param string               $column_name Column name.
	 */
	public function column_default($item, $column_name): string {
		return '';
	}

	/** No integrations message. */
	public function no_items(): void {
		esc_html_e('No integrations are available yet.', 'noravo');
	}

	/** Whether an integration is enabled. */
	private function is_enabled(IntegrationInterface $integration): bool {
		return $integration->is_available() && in_array($integration->id(), $this->enabled_sources, true);
	}

}
