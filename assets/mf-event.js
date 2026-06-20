/* MF Event — front-end modal for event details / poster / related links.
   No dependencies. Content is cloned from each card's inert <template>. */
(function () {
	'use strict';

	var i18n = window.MFE_I18N || { close: 'Close', related: 'Related links' };
	var modal, dialog, titleEl, dateEl, bodyEl, lastFocus;

	function build() {
		modal = document.createElement('div');
		modal.className = 'mfe-modal';
		modal.setAttribute('hidden', '');
		modal.innerHTML =
			'<div class="mfe-modal__overlay" data-close></div>' +
			'<div class="mfe-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mfe-modal-title" tabindex="-1">' +
				'<button type="button" class="mfe-modal__close" data-close aria-label="' + escapeAttr(i18n.close) + '">&times;</button>' +
				'<h2 class="mfe-modal__title" id="mfe-modal-title"></h2>' +
				'<div class="mfe-modal__date"></div>' +
				'<div class="mfe-modal__body"></div>' +
			'</div>';
		document.body.appendChild(modal);

		dialog = modal.querySelector('.mfe-modal__dialog');
		titleEl = modal.querySelector('.mfe-modal__title');
		dateEl = modal.querySelector('.mfe-modal__date');
		bodyEl = modal.querySelector('.mfe-modal__body');

		modal.addEventListener('click', function (e) {
			if (e.target && e.target.hasAttribute('data-close')) {
				close();
			}
		});
		dialog.addEventListener('keydown', trapTab);
	}

	function escapeAttr(s) {
		return String(s).replace(/"/g, '&quot;');
	}

	function open(card) {
		if (!modal) {
			build();
		}
		lastFocus = document.activeElement;

		var titleNode = card.querySelector('.mfe-title');
		var whenNode = card.querySelector('.mfe-when');
		var tpl = card.querySelector('.mfe-detail-data');

		titleEl.textContent = titleNode ? titleNode.textContent : '';
		dateEl.textContent = whenNode ? whenNode.textContent : '';
		bodyEl.innerHTML = '';

		if (tpl) {
			if (tpl.content) {
				bodyEl.appendChild(tpl.content.cloneNode(true));
			} else {
				// Very old browsers without <template> support: fall back to innerHTML.
				bodyEl.innerHTML = tpl.innerHTML;
			}
		}

		modal.removeAttribute('hidden');
		document.body.classList.add('mfe-modal-open');
		dialog.focus();
	}

	function close() {
		if (!modal || modal.hasAttribute('hidden')) {
			return;
		}
		modal.setAttribute('hidden', '');
		document.body.classList.remove('mfe-modal-open');
		if (lastFocus && typeof lastFocus.focus === 'function') {
			lastFocus.focus();
		}
	}

	function trapTab(e) {
		if (e.key !== 'Tab') {
			return;
		}
		var f = dialog.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])');
		if (!f.length) {
			return;
		}
		var first = f[0];
		var last = f[f.length - 1];
		if (e.shiftKey && document.activeElement === first) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && document.activeElement === last) {
			e.preventDefault();
			first.focus();
		}
	}

	function activate(e) {
		var card = e.target.closest ? e.target.closest('.mfe-card.has-detail') : null;
		if (!card) {
			return;
		}
		// Let genuine links/buttons inside the card behave normally.
		if (e.target.closest('a, button')) {
			return;
		}
		if (e.type === 'keydown') {
			if (e.key !== 'Enter' && e.key !== ' ' && e.key !== 'Spacebar') {
				return;
			}
			e.preventDefault();
		}
		open(card);
	}

	document.addEventListener('click', activate);
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			close();
			return;
		}
		activate(e);
	});
})();
