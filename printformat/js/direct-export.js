(function () {
	'use strict';

	var config = window.PrintFormatDirectExport || {};
	if (!config.endpoint || window.top !== window.self) return;

	function visible(element) {
		return !!(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
	}

	function cleanText(cell) {
		var clone = cell.cloneNode(true);
		clone.querySelectorAll('script,style,.pf-direct-export-bar,.dropdown-menu,.tooltip').forEach(function (node) { node.remove(); });
		clone.querySelectorAll('input,select,textarea,button').forEach(function (field) {
			var value = '';
			if (field.tagName === 'SELECT') value = field.options[field.selectedIndex] ? field.options[field.selectedIndex].text : '';
			else if ((field.type || '').toLowerCase() !== 'checkbox' && (field.type || '').toLowerCase() !== 'radio') value = field.value || '';
			field.replaceWith(document.createTextNode(value));
		});
		return (clone.innerText || clone.textContent || '').replace(/\u00a0/g, ' ').replace(/[ \t]+/g, ' ').replace(/\s*\n\s*/g, '\n').trim();
	}

	function tableMatrix(table) {
		var matrix = [];
		Array.prototype.forEach.call(table.rows, function (row) {
			if (!visible(row) || row.classList.contains('noexport') || row.classList.contains('liste_titre_filter')) return;
			var cells = Array.prototype.filter.call(row.cells, visible);
			if (!cells.length) return;
			var hasField = row.querySelector('input:not([type="hidden"]),select,textarea');
			var texts = cells.map(cleanText);
			if (hasField && texts.join('').trim() === '') return;
			var output = [];
			cells.forEach(function (cell, index) {
				var text = texts[index];
				var span = Math.max(1, parseInt(cell.getAttribute('colspan') || '1', 10));
				output.push(text);
				while (--span > 0) output.push('');
			});
			if (output.some(function (value) { return value !== ''; })) matrix.push(output);
		});
		var width = matrix.reduce(function (max, row) { return Math.max(max, row.length); }, 0);
		matrix.forEach(function (row) { while (row.length < width) row.push(''); });
		return matrix;
	}

	function filename() {
		var title = document.querySelector('h1, .titre, .refid, title');
		var value = title ? cleanText(title) : 'export';
		value = value.replace(/[\\/:*?"<>|]+/g, '-').replace(/\s+/g, '-').replace(/^-+|-+$/g, '').toLowerCase();
		return (value || 'export').slice(0, 100) + '.xlsx';
	}

	function download(table, button) {
		var rows = tableMatrix(table);
		if (rows.length < 2) {
			window.alert(config.emptyLabel || 'No data table to export.');
			return;
		}
		var oldLabel = button.innerHTML;
		button.disabled = true;
		button.textContent = config.workingLabel || 'Creating Excel...';
		fetch(config.endpoint + '?token=' + encodeURIComponent(config.token), {
			method: 'POST',
			credentials: 'same-origin',
			headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
			body: JSON.stringify({filename: filename(), title: document.title || 'Export', rows: rows})
		}).then(function (response) {
			if (!response.ok) return response.text().then(function (message) { throw new Error(message || config.errorLabel); });
			return response.blob();
		}).then(function (blob) {
			var url = URL.createObjectURL(blob);
			var link = document.createElement('a');
			link.href = url;
			link.download = filename();
			document.body.appendChild(link);
			link.click();
			link.remove();
			setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
		}).catch(function (error) {
			var label = config.errorLabel || 'Excel export failed.';
			var detail = (error && error.message) ? error.message.trim() : '';
			window.alert(detail && detail !== label ? label + '\n' + detail : label);
		}).finally(function () {
			button.disabled = false;
			button.innerHTML = oldLabel;
		});
	}

	function eligibleTables() {
		if (document.querySelector('a[href*="format=xlsx"],a[href*="format%3Dxlsx"]')) return [];
		return Array.prototype.filter.call(document.querySelectorAll('.div-table-responsive > table, table.liste, table.noborder'), function (table) {
			if (!visible(table) || table.dataset.directExportReady || table.closest('.tabsAction, #builddoc_form')) return false;
			if (table.classList.contains('tableforfield') || table.classList.contains('centpercent') && table.rows.length < 2) return false;
			if (table.parentElement && table.parentElement.closest('table')) return false;
			var matrix = tableMatrix(table);
			return matrix.length >= 2 && matrix[0].length >= 2;
		});
	}

	function initialize() {
		eligibleTables().forEach(function (table) {
			table.dataset.directExportReady = '1';
			var bar = document.createElement('div');
			bar.className = 'pf-direct-export-bar right';
			bar.style.margin = '8px 0';
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'button button-save';
			button.innerHTML = '<span class="fa fa-file-excel" aria-hidden="true"></span> ' + (config.label || 'Export Excel');
			button.addEventListener('click', function () { download(table, button); });
			bar.appendChild(button);
			table.parentNode.insertBefore(bar, table);
		});
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
	else initialize();
}());
