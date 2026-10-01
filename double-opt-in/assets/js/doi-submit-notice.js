/**
 * Double Opt-In: confirmation hint under the Contact Form 7 message (5.8.0).
 *
 * CF7 writes its message with innerText, so the hint is a separate block.
 * The server puts everything, already translated, into `apiResponse.doi`
 * (CF7Integration::filterFeedbackResponse). Built with textContent only.
 */
(function () {
	'use strict';

	function removeNotice(form) {
		var old = form.querySelector('.f12-doi-notice');
		if (old) {
			old.parentNode.removeChild(old);
		}
	}

	/*
	 * Actions added by add-ons (filter f12_doi_submit_notice_data, 5.8.0).
	 * A link opens in a new tab; a button only announces the click
	 * (`f12-doi-notice-action`) — the add-on's own script does the work.
	 */
	function renderAction(form, action) {
		if (!action || typeof action.id !== 'string' || !action.label) {
			return null;
		}

		if (action.type === 'link') {
			if (typeof action.url !== 'string' || action.url.indexOf('https://') !== 0) {
				return null;
			}
			var link = document.createElement('a');
			link.className = 'f12-doi-action f12-doi-action-' + action.id;
			link.href = action.url;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			link.textContent = String(action.label);
			return link;
		}

		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'f12-doi-action f12-doi-action-' + action.id;
		button.textContent = String(action.label);

		// A button that waits (the reminder's "send it again": the first mail
		// gets a minute) counts down in its label. Greyed out with no reason
		// given, it read as broken.
		var wait = parseInt(action.wait, 10) || 0;
		if (wait > 0) {
			var label = String(action.label);
			var left = wait;
			button.disabled = true;
			button.textContent = label + ' (' + left + ' s)';
			var timer = setInterval(function () {
				left -= 1;
				if (left > 0) {
					button.textContent = label + ' (' + left + ' s)';
					return;
				}
				clearInterval(timer);
				button.disabled = false;
				button.textContent = label;
			}, 1000);
		}

		button.addEventListener('click', function () {
			form.dispatchEvent(new CustomEvent('f12-doi-notice-action', {
				bubbles: true,
				detail: { id: action.id, data: action.data || {}, button: button, form: form }
			}));
		});

		return button;
	}

	document.addEventListener('wpcf7beforesubmit', function (event) {
		removeNotice(event.target);
	});

	document.addEventListener('wpcf7mailsent', function (event) {
		var form = event.target;
		var detail = event.detail || {};
		var doi = detail.apiResponse && detail.apiResponse.doi;
		if (!doi || !form || !form.querySelector) {
			return;
		}

		removeNotice(form);

		var box = document.createElement('div');
		box.className = 'f12-doi-notice';

		(doi.lines || []).forEach(function (line) {
			var p = document.createElement('p');
			p.textContent = String(line);
			box.appendChild(p);
		});

		if (doi.inbox && typeof doi.inbox.url === 'string' && doi.inbox.url.indexOf('https://') === 0) {
			var link = document.createElement('a');
			link.className = 'f12-doi-inbox';
			link.href = doi.inbox.url;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			link.textContent = String(doi.inbox.label || doi.inbox.name || '');
			var p = document.createElement('p');
			p.appendChild(link);
			box.appendChild(p);
		}

		(doi.actions || []).forEach(function (action) {
			var el = renderAction(form, action);
			if (el) {
				var row = document.createElement('p');
				row.appendChild(el);
				box.appendChild(row);
			}
		});

		var output = form.querySelector('.wpcf7-response-output');
		if (output && output.parentNode) {
			output.parentNode.insertBefore(box, output.nextSibling);
		} else {
			form.appendChild(box);
		}
	});
})();
