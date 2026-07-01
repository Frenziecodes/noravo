<?php
/**
 * Automation rule storage.
 *
 * @package Noravo
 */

declare(strict_types=1);

namespace Noravo\Automation;

/**
 * Stores automation rules in a single WordPress option.
 */
final class AutomationRuleRepository {
	public const OPTION = 'noravo_automation_rules';

	/** Seeds rule storage on activation. */
	public static function install_defaults(): void {
		if (false === get_option(self::OPTION, false)) {
			add_option(self::OPTION, array(), '', false);
		}
	}

	/** @return array<int, array<string, mixed>> */
	public function all(): array {
		$rules = get_option(self::OPTION, array());

		if (! is_array($rules)) {
			return array();
		}

		return array_values(array_map(array($this, 'sanitize_rule'), $rules));
	}

	/** Saves a new rule and returns it. */
	public function create(string $name, string $trigger, string $action, string $status, string $source): array {
		$rules = $this->all();
		$now   = current_time('mysql');
		$rule  = $this->sanitize_rule(
			array(
				'id'         => uniqid('rule_', true),
				'name'       => $name,
				'trigger'    => $trigger,
				'action'     => $action,
				'status'     => $status,
				'status_note' => '',
				'source'     => $source,
				'times_run'  => 0,
				'created_at' => $now,
				'updated_at' => $now,
			)
		);

		$rules[] = $rule;
		update_option(self::OPTION, $rules, false);

		return $rule;
	}

	/** Finds a rule by ID. */
	public function find(string $id): ?array {
		foreach ($this->all() as $rule) {
			if ($rule['id'] === $id) {
				return $rule;
			}
		}

		return null;
	}

	/** Updates a rule by ID. */
	public function update_rule(string $id, string $name, string $trigger, string $action, string $status, string $source): void {
		$rules = $this->all();
		$now   = current_time('mysql');

		foreach ($rules as $index => $rule) {
			if ($rule['id'] !== $id) {
				continue;
			}

			$rules[$index] = $this->sanitize_rule(
				array_merge(
					$rule,
					array(
						'name'       => $name,
						'trigger'    => $trigger,
						'action'     => $action,
						'status'     => $status,
						'status_note' => 'active' === $status ? '' : (string) ($rule['status_note'] ?? ''),
						'source'     => $source,
						'updated_at' => $now,
					)
				)
			);

			update_option(self::OPTION, $rules, false);
			return;
		}
	}

	/** Toggles a rule status. */
	public function update_status(string $id, string $status, string $status_note = ''): void {
		$rules = $this->all();
		$now   = current_time('mysql');

		foreach ($rules as $index => $rule) {
			if ($rule['id'] === $id) {
				$rules[$index]['status']     = 'active' === $status ? 'active' : 'draft';
				$rules[$index]['status_note'] = 'active' === $status ? '' : $status_note;
				$rules[$index]['updated_at'] = $now;
				break;
			}
		}

		update_option(self::OPTION, array_values(array_map(array($this, 'sanitize_rule'), $rules)), false);
	}

	/** Pauses every active rule that depends on a source. */
	public function pause_active_by_source(string $source, string $status_note): int {
		$source  = sanitize_key($source);
		$rules   = $this->all();
		$changed = 0;
		$now     = current_time('mysql');

		foreach ($rules as $index => $rule) {
			if ($source !== $rule['source'] || 'active' !== $rule['status']) {
				continue;
			}

			$rules[$index]['status']      = 'draft';
			$rules[$index]['status_note'] = sanitize_text_field($status_note);
			$rules[$index]['updated_at']  = $now;
			$changed++;
		}

		if (0 < $changed) {
			update_option(self::OPTION, array_values(array_map(array($this, 'sanitize_rule'), $rules)), false);
		}

		return $changed;
	}

	/** Deletes a rule. */
	public function delete(string $id): void {
		$rules = array_values(
			array_filter(
				$this->all(),
				static fn (array $rule): bool => $rule['id'] !== $id
			)
		);

		update_option(self::OPTION, $rules, false);
	}

	/** Deletes several rules by ID. */
	public function delete_many(array $ids): void {
		$ids = array_values(array_filter(array_map('sanitize_key', $ids)));

		if (empty($ids)) {
			return;
		}

		$rules = array_values(
			array_filter(
				$this->all(),
				static fn (array $rule): bool => ! in_array($rule['id'], $ids, true)
			)
		);

		update_option(self::OPTION, $rules, false);
	}

	/** @return array<int, string> */
	public function active_sources(): array {
		$sources = array();

		foreach ($this->all() as $rule) {
			if ('active' === $rule['status'] && '' !== $rule['source']) {
				$sources[] = $rule['source'];
			}
		}

		return array_values(array_unique($sources));
	}

	/** Increments active rule run counts by source. */
	public function record_runs_for_sources(array $source_counts): void {
		$rules   = $this->all();
		$changed = false;
		$now     = current_time('mysql');

		foreach ($rules as $index => $rule) {
			$source = (string) $rule['source'];

			if ('active' !== $rule['status'] || ! isset($source_counts[$source])) {
				continue;
			}

			$rules[$index]['times_run']  = absint($rule['times_run']) + absint($source_counts[$source]);
			$rules[$index]['updated_at'] = $now;
			$changed = true;
		}

		if ($changed) {
			update_option(self::OPTION, array_values(array_map(array($this, 'sanitize_rule'), $rules)), false);
		}
	}

	/** @return array<string, mixed> */
	private function sanitize_rule(array $rule): array {
		$status = sanitize_key((string) ($rule['status'] ?? 'draft'));

		return array(
			'id'         => sanitize_key((string) ($rule['id'] ?? '')),
			'name'       => sanitize_text_field((string) ($rule['name'] ?? 'Untitled rule')),
			'trigger'    => sanitize_key((string) ($rule['trigger'] ?? '')),
			'action'     => sanitize_key((string) ($rule['action'] ?? '')),
			'status'     => in_array($status, array('active', 'draft'), true) ? $status : 'draft',
			'status_note' => sanitize_text_field((string) ($rule['status_note'] ?? '')),
			'source'     => sanitize_key((string) ($rule['source'] ?? '')),
			'times_run'  => absint($rule['times_run'] ?? 0),
			'created_at' => sanitize_text_field((string) ($rule['created_at'] ?? '')),
			'updated_at' => sanitize_text_field((string) ($rule['updated_at'] ?? '')),
		);
	}
}
