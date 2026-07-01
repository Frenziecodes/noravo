<?php
/**
 * Integration contract.
 *
 * @package Noravo
 */

declare(strict_types=1);

namespace Noravo\Integrations;

interface IntegrationInterface {
	/** Unique integration identifier. */
	public function id(): string;

	/** Human-readable integration name. */
	public function label(): string;

	/** Short description shown in admin settings. */
	public function description(): string;

	/** Icon URL shown in admin integration lists. */
	public function icon_url(): string;

	/** Whether required plugins or services are present. */
	public function is_available(): bool;

	/** Whether this integration should be highlighted in onboarding. */
	public function is_recommended(): bool;
}
