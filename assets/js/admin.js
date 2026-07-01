(function () {
	'use strict';

	if (document.querySelector('.noravo-settings-shell, .noravo-appearance-shell')) {
		document.body.classList.add('noravo-fixed-actions-page');
	}

	function setupAppearancePreview() {
		var preview = document.querySelector('[data-noravo-appearance-preview]');
		var root = preview ? preview.querySelector('.noravo-preview-root') : null;
		var positionField = document.getElementById('noravo-position');
		var animationField = document.getElementById('noravo-animation');
		var templateButtons = document.querySelectorAll('.noravo-template-options button');
		var positions = ['bottom-left', 'bottom-right', 'top-left', 'top-right'];
		var animations = ['slide', 'fade'];

		if (!preview || !root) {
			return;
		}

		function replaceClass(values, prefix, nextValue) {
			values.forEach(function (value) {
				root.classList.remove(prefix + value);
			});

			root.classList.add(prefix + nextValue);
		}

		function replayAnimation() {
			var toast = root.querySelector('.noravo-toast');

			if (!toast) {
				return;
			}

			toast.style.animation = 'none';
			toast.offsetHeight;
			toast.style.animation = '';
		}

		if (positionField) {
			positionField.addEventListener('change', function () {
				replaceClass(positions, 'noravo-', positionField.value);
			});
		}

		if (animationField) {
			animationField.addEventListener('change', function () {
				replaceClass(animations, 'noravo-animation-', animationField.value);
				replayAnimation();
			});
		}

		templateButtons.forEach(function (button) {
			button.addEventListener('click', function () {
				templateButtons.forEach(function (item) {
					item.classList.remove('is-active');
				});

				button.classList.add('is-active');
			});
		});
	}

	setupAppearancePreview();

	var modal = document.getElementById('noravo-rule-modal');

	if (!modal) {
		return;
	}

	var openButtons = document.querySelectorAll('[data-noravo-open-rule-modal]');
	var closeButtons = modal.querySelectorAll('[data-noravo-close-rule-modal]');
	var backButton = modal.querySelector('[data-noravo-rule-back]');
	var title = modal.querySelector('[data-noravo-modal-title]');
	var steps = modal.querySelectorAll('[data-noravo-rule-step]');
	var categoryButtons = modal.querySelectorAll('[data-noravo-trigger-group]');
	var panels = modal.querySelectorAll('[data-noravo-trigger-panel]');
	var actionCategoryButtons = modal.querySelectorAll('[data-noravo-action-group]');
	var actionPanels = modal.querySelectorAll('[data-noravo-action-panel]');
	var triggerButtons = modal.querySelectorAll('[data-noravo-select-trigger]');
	var actionButtons = modal.querySelectorAll('[data-noravo-select-action]');
	var rulesForm = document.querySelector('.noravo-rules-form');
	var selectAllRules = document.querySelector('[data-noravo-select-all-rules]');
	var ruleCheckboxes = document.querySelectorAll('[data-noravo-rule-checkbox]');
	var configButton = document.querySelector('[data-noravo-rules-config]');
	var configMenu = document.querySelector('[data-noravo-rules-config-menu]');
	var selectedTrigger = '';

	function updateRuleSelection() {
		var checkedCount = 0;

		ruleCheckboxes.forEach(function (checkbox) {
			var row = checkbox.closest('tr');

			if (checkbox.checked) {
				checkedCount += 1;
			}

			if (row) {
				row.classList.toggle('is-selected', checkbox.checked);
			}
		});

		if (selectAllRules) {
			selectAllRules.checked = ruleCheckboxes.length > 0 && checkedCount === ruleCheckboxes.length;
			selectAllRules.indeterminate = checkedCount > 0 && checkedCount < ruleCheckboxes.length;

			if (selectAllRules.closest('tr')) {
				selectAllRules.closest('tr').classList.toggle('is-selected', checkedCount > 0);
			}
		}
	}

	function closeConfigMenu() {
		if (!configButton || !configMenu) {
			return;
		}

		configMenu.hidden = true;
		configButton.setAttribute('aria-expanded', 'false');
	}

	function openModal() {
		showStep('trigger');
		modal.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('noravo-rule-modal-open');
	}

	function closeModal() {
		modal.classList.remove('is-open');
		modal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('noravo-rule-modal-open');
	}

	function showStep(stepName) {
		steps.forEach(function (step) {
			step.classList.toggle('is-active', step.getAttribute('data-noravo-rule-step') === stepName);
		});

		if (backButton) {
			backButton.hidden = stepName === 'trigger';
		}

		if (title) {
			title.textContent = stepName === 'action'
				? 'Select an action for your automation rule'
				: 'Select a trigger for your automation rule';
		}
	}

	function activateGroup(group) {
		categoryButtons.forEach(function (button) {
			button.classList.toggle('is-active', button.getAttribute('data-noravo-trigger-group') === group);
		});

		panels.forEach(function (panel) {
			panel.classList.toggle('is-active', panel.getAttribute('data-noravo-trigger-panel') === group);
		});
	}

	function activateActionGroup(group) {
		actionCategoryButtons.forEach(function (button) {
			button.classList.toggle('is-active', button.getAttribute('data-noravo-action-group') === group);
		});

		actionPanels.forEach(function (panel) {
			panel.classList.toggle('is-active', panel.getAttribute('data-noravo-action-panel') === group);
		});
	}

	openButtons.forEach(function (button) {
		button.addEventListener('click', openModal);
	});

	closeButtons.forEach(function (button) {
		button.addEventListener('click', closeModal);
	});

	categoryButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			activateGroup(button.getAttribute('data-noravo-trigger-group'));
		});
	});

	actionCategoryButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			activateActionGroup(button.getAttribute('data-noravo-action-group'));
		});
	});

	triggerButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			selectedTrigger = button.getAttribute('data-noravo-select-trigger') || '';
			showStep('action');
			activateActionGroup('campaigns');
		});
	});

	actionButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			var selectedAction = button.getAttribute('data-noravo-select-action') || '';
			var url = new URL(window.location.href);

			closeModal();
			url.searchParams.set('page', 'noravo-campaigns');
			url.searchParams.set('view', 'builder');
			url.searchParams.set('trigger', selectedTrigger);
			url.searchParams.set('rule_action', selectedAction);
			window.location.href = url.toString();
		});
	});

	if (selectAllRules) {
		selectAllRules.addEventListener('change', function () {
			ruleCheckboxes.forEach(function (checkbox) {
				checkbox.checked = selectAllRules.checked;
			});

			updateRuleSelection();
		});
	}

	ruleCheckboxes.forEach(function (checkbox) {
		checkbox.addEventListener('change', updateRuleSelection);
	});

	if (rulesForm) {
		rulesForm.addEventListener('submit', function (event) {
			var hasSelection = Array.prototype.some.call(ruleCheckboxes, function (checkbox) {
				return checkbox.checked;
			});

			if (!hasSelection) {
				event.preventDefault();
			}
		});
	}

	if (configButton && configMenu) {
		configButton.addEventListener('click', function (event) {
			event.stopPropagation();
			configMenu.hidden = !configMenu.hidden;
			configButton.setAttribute('aria-expanded', configMenu.hidden ? 'false' : 'true');
		});

		configMenu.addEventListener('click', function (event) {
			event.stopPropagation();
		});
	}

	if (backButton) {
		backButton.addEventListener('click', function () {
			showStep('trigger');
		});
	}

	modal.addEventListener('click', function (event) {
		if (event.target === modal) {
			closeModal();
		}
	});

	document.addEventListener('keydown', function (event) {
		if ('Escape' === event.key && modal.classList.contains('is-open')) {
			closeModal();
		}

		if ('Escape' === event.key) {
			closeConfigMenu();
		}
	});

	document.addEventListener('click', function () {
		closeConfigMenu();
	});

	updateRuleSelection();
}());
