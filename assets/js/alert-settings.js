/**
 * Ops Health Dashboard - Alert Settings
 *
 * Toggles conditional fields: when a channel's "Enabled" checkbox
 * is unchecked, the remaining configuration fields in that section
 * are disabled and visually dimmed.
 *
 * ES5 for maximum browser compatibility.
 *
 * @package OpsHealthDashboard
 */
(function () {
	'use strict';

	function toggleFields(section) {
		var checkbox = section.querySelector('input[type="checkbox"][name$="_enabled"]');
		if (!checkbox) {
			return;
		}

		var rows = section.querySelectorAll('tr');
		var i, row, inputs, j;

		for (i = 0; i < rows.length; i++) {
			row = rows[i];
			// Skip the row containing the checkbox itself.
			if (row.contains(checkbox)) {
				continue;
			}
			row.style.opacity = checkbox.checked ? '1' : '0.5';
			inputs = row.querySelectorAll('input, select, textarea');
			for (j = 0; j < inputs.length; j++) {
				inputs[j].disabled = !checkbox.checked;
			}
		}
	}

	function init() {
		var sections = document.querySelectorAll('.ops-health-alert-section');
		var i;

		for (i = 0; i < sections.length; i++) {
			toggleFields(sections[i]);
			(function (section) {
				var cb = section.querySelector('input[type="checkbox"][name$="_enabled"]');
				if (cb) {
					cb.addEventListener('change', function () {
						toggleFields(section);
					});
				}
			})(sections[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
