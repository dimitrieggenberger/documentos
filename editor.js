// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A small toolbar over the text of a template or a draft.
 *
 * The text stays the plugin's markup (templates.php): bold, italic, a centred
 * paragraph, a bulleted list and the data placeholders. No colours, sizes or
 * fonts: the brand letter decides those, so whatever is typed here prints the
 * same on every document. Every button only writes markup into the textarea,
 * which keeps working without JavaScript.
 *
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    /**
     * Wrap the selection in a mark, or put the mark around a placeholder word.
     *
     * @param {HTMLTextAreaElement} area
     * @param {string} before
     * @param {string} after
     * @param {string} sample
     */
    var wrap = function(area, before, after, sample) {
        var start = area.selectionStart;
        var end = area.selectionEnd;
        var text = area.value.slice(start, end) || sample;
        area.setRangeText(before + text + after, start, end, 'end');
        area.setSelectionRange(start + before.length, start + before.length + text.length);
    };

    /**
     * Put a prefix at the start of every selected line (or of the current one).
     *
     * @param {HTMLTextAreaElement} area
     * @param {string} prefix
     */
    var prefixLines = function(area, prefix) {
        var value = area.value;
        var start = value.lastIndexOf('\n', area.selectionStart - 1) + 1;
        var end = value.indexOf('\n', area.selectionEnd);
        end = end === -1 ? value.length : end;
        var lines = value.slice(start, end).split('\n').map(function(line) {
            return line.indexOf(prefix) === 0 ? line.slice(prefix.length) : prefix + line;
        });
        area.setRangeText(lines.join('\n'), start, end, 'select');
    };

    var build = function(area) {
        var labels = JSON.parse(area.getAttribute('data-labels') || '{}');
        var placeholders = JSON.parse(area.getAttribute('data-placeholders') || '{}');
        var bar = document.createElement('div');
        bar.className = 'local-documents-toolbar';
        bar.setAttribute('role', 'toolbar');
        bar.setAttribute('aria-label', labels.toolbar || '');
        var button = function(label, title, action) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn btn-sm btn-outline-secondary';
            b.innerHTML = label;
            b.title = title;
            b.setAttribute('aria-label', title);
            b.addEventListener('click', function() {
                action();
                area.focus();
                area.dispatchEvent(new Event('input', {bubbles: true}));
            });
            bar.appendChild(b);
        };
        button('<b>B</b>', labels.bold, function() {
            wrap(area, '**', '**', labels.bold);
        });
        button('<i>I</i>', labels.italic, function() {
            wrap(area, '*', '*', labels.italic);
        });
        button('&#8801;', labels.center, function() {
            prefixLines(area, '= ');
        });
        button('&#8226;', labels.list, function() {
            prefixLines(area, '- ');
        });
        if (!Object.keys(placeholders).length) {
            area.parentNode.insertBefore(bar, area);
            return;
        }
        var select = document.createElement('select');
        select.className = 'form-select form-select-sm custom-select custom-select-sm';
        select.setAttribute('aria-label', labels.insert);
        var first = document.createElement('option');
        first.value = '';
        first.textContent = labels.insert;
        select.appendChild(first);
        Object.keys(placeholders).forEach(function(key) {
            var option = document.createElement('option');
            option.value = key;
            option.textContent = placeholders[key] + ' · {{' + key + '}}';
            select.appendChild(option);
        });
        select.addEventListener('change', function() {
            if (select.value) {
                area.setRangeText('{{' + select.value + '}}', area.selectionStart, area.selectionEnd, 'end');
                select.value = '';
                area.focus();
            }
        });
        bar.appendChild(select);
        area.parentNode.insertBefore(bar, area);
    };

    var init = function() {
        document.querySelectorAll('textarea[data-placeholders]').forEach(build);
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
