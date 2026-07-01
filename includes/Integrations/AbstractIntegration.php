<?php
/**
 * Shared integration behavior.
 *
 * @package Noravo
 */

declare(strict_types=1);

namespace Noravo\Integrations;

/**
 * Provides common metadata helpers for built-in integrations.
 */
abstract class AbstractIntegration implements IntegrationInterface {
	/** Icon URL shown in admin integration lists. */
	public function icon_url(): string {
		$icon_path = $this->icon_path();

		if (is_readable($icon_path)) {
			return NORAVO_URL . 'includes/Integrations/' . $this->folder_name() . '/assets/' . $this->id() . '.svg';
		}

		return NORAVO_URL . 'assets/images/integration-default.svg';
	}

	/** Folder name for this integration. */
	protected function folder_name(): string {
		return $this->id();
	}

	/** Absolute filesystem path for this integration's icon. */
	private function icon_path(): string {
		return NORAVO_PATH . 'includes/Integrations/' . $this->folder_name() . '/assets/' . $this->id() . '.svg';
	}
}
