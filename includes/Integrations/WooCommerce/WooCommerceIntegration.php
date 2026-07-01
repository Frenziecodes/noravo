<?php
/**
 * WooCommerce notification integration.
 *
 * @package Noravo
 */

declare(strict_types=1);

namespace Noravo\Integrations\WooCommerce;

use Noravo\Integrations\AbstractIntegration;
use Noravo\Notifications\NotificationProviderInterface;
use Noravo\Settings\SettingsRepository;

/**
 * Builds purchase notifications from recent WooCommerce orders.
 */
final class WooCommerceIntegration extends AbstractIntegration implements NotificationProviderInterface {
	private SettingsRepository $settings;

	/** @param SettingsRepository $settings Plugin settings store. */
	public function __construct(SettingsRepository $settings) {
		$this->settings = $settings;
	}

	public function id(): string {
		return 'woocommerce';
	}

	public function source(): string {
		return 'woocommerce';
	}

	public function label(): string {
		return __('WooCommerce', 'noravo');
	}

	public function description(): string {
		return __('Show recent purchase activity as elegant social proof notifications.', 'noravo');
	}

	/** Folder name for this integration. */
	protected function folder_name(): string {
		return 'WooCommerce';
	}

	/** Whether WooCommerce is active and usable. */
	public function is_available(): bool {
		return class_exists('WooCommerce') && function_exists('wc_get_orders');
	}

	public function is_recommended(): bool {
		return $this->is_available();
	}

	/** Whether the store has at least one qualifying order. */
	public function has_real_data(): bool {
		if (! $this->is_available()) {
			return false;
		}

		$count = get_transient('noravo_wc_order_count');

		if (false === $count) {
			$orders = wc_get_orders(
				array(
					'limit'  => 1,
					'status' => array('wc-processing', 'wc-completed'),
					'return' => 'ids',
				)
			);

			$count = is_array($orders) ? count($orders) : 0;
			set_transient('noravo_wc_order_count', $count, HOUR_IN_SECONDS);
		}

		return (int) $count > 0;
	}

	/**
	 * Builds purchase notifications from recent orders.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function notifications(int $limit): array {
		if (! $this->is_available() || ! $this->settings->is_source_enabled('woocommerce')) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'limit'   => $limit,
				'status'  => array('wc-processing', 'wc-completed'),
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);

		if (! is_array($orders)) {
			return array();
		}

		$settings         = $this->settings->all();
		$customer_display = (string) ($settings['customer_display'] ?? 'location');
		$notifications    = array();

		foreach ($orders as $order) {
			if (! is_a($order, 'WC_Order')) {
				continue;
			}

			$item_name = $this->first_item_name($order);
			$customer = $this->customer_label($order, $customer_display);

			$notifications[] = array(
				'type'      => 'purchase',
				'title'     => __('New purchase', 'noravo'),
				'message'   => sprintf(
					/* translators: 1: customer label, 2: product name. */
					__('%1$s purchased %2$s', 'noravo'),
					$customer,
					$item_name
				),
				'timestamp' => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time(),
				'cta_url'   => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/'),
				'image'     => $this->first_item_image($order),
				'source'    => 'woocommerce',
			);
		}

		return $notifications;
	}

	/** Returns the customer label based on the store owner's privacy setting. */
	private function customer_label(object $order, string $display): string {
		if ('full_name' === $display) {
			$name = $this->customer_name($order, false);

			if ('' !== $name) {
				return $name;
			}
		}

		if ('masked_name' === $display) {
			$name = $this->customer_name($order, true);

			if ('' !== $name) {
				return $name;
			}
		}

		return $this->customer_location($order);
	}

	/** Returns the customer's name, optionally masking every name after the first. */
	private function customer_name(object $order, bool $mask_last_names): string {
		$first_name = method_exists($order, 'get_billing_first_name')
			? sanitize_text_field((string) $order->get_billing_first_name())
			: '';
		$last_name  = method_exists($order, 'get_billing_last_name')
			? sanitize_text_field((string) $order->get_billing_last_name())
			: '';
		$parts      = preg_split('/\s+/', trim($first_name . ' ' . $last_name));

		if (! is_array($parts)) {
			return '';
		}

		$parts = array_values(array_filter($parts));

		if (empty($parts)) {
			return '';
		}

		if (! $mask_last_names || 1 === count($parts)) {
			return implode(' ', $parts);
		}

		foreach ($parts as $index => $part) {
			if (0 === $index) {
				continue;
			}

			$parts[$index] = $this->mask_name_part($part);
		}

		return implode(' ', $parts);
	}

	/** Returns the location-based anonymous customer label. */
	private function customer_location(object $order): string {
		$city = method_exists($order, 'get_billing_city')
			? sanitize_text_field((string) $order->get_billing_city())
			: '';

		if ('' === $city) {
			return __('A customer', 'noravo');
		}

		return sprintf(
			/* translators: %s is a city name. */
			__('Someone in %s', 'noravo'),
			$city
		);
	}

	/** Masks a name part while keeping the first letter visible. */
	private function mask_name_part(string $name): string {
		$first_letter = function_exists('mb_substr') ? mb_substr($name, 0, 1) : substr($name, 0, 1);

		return $first_letter . '***';
	}

	/** Returns the name of the first line item in an order. */
	private function first_item_name(object $order): string {
		$items = $order->get_items();

		foreach ($items as $item) {
			if (is_object($item) && method_exists($item, 'get_name')) {
				return sanitize_text_field((string) $item->get_name());
			}
		}

		return __('a product', 'noravo');
	}

	/** Returns the thumbnail URL of the first line item product. */
	private function first_item_image(object $order): string {
		$items = $order->get_items();

		foreach ($items as $item) {
			if (! is_object($item) || ! method_exists($item, 'get_product')) {
				continue;
			}

			$product = $item->get_product();

			if ($product && method_exists($product, 'get_image_id')) {
				$image = wp_get_attachment_image_url((int) $product->get_image_id(), 'thumbnail');

				return $image ? esc_url_raw($image) : '';
			}
		}

		return '';
	}
}
