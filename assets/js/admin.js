(function () {
	'use strict';

	var modal = document.getElementById('noravo-rule-modal');

	if (!modal) {
		return;
	}

	var openButtons = document.querySelectorAll('[data-noravo-open-rule-modal]');
	var closeButtons = modal.querySelectorAll('[data-noravo-close-rule-modal]');
	var categoryButtons = modal.querySelectorAll('[data-noravo-trigger-group]');
	var panels = modal.querySelectorAll('[data-noravo-trigger-panel]');

	function openModal() {
		modal.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('noravo-rule-modal-open');
	}

	function closeModal() {
		modal.classList.remove('is-open');
		modal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('noravo-rule-modal-open');
	}

	function activateGroup(group) {
		categoryButtons.forEach(function (button) {
			button.classList.toggle('is-active', button.getAttribute('data-noravo-trigger-group') === group);
		});

		panels.forEach(function (panel) {
			panel.classList.toggle('is-active', panel.getAttribute('data-noravo-trigger-panel') === group);
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

	modal.addEventListener('click', function (event) {
		if (event.target === modal) {
			closeModal();
		}
	});

	document.addEventListener('keydown', function (event) {
		if ('Escape' === event.key && modal.classList.contains('is-open')) {
			closeModal();
		}
	});
}());
