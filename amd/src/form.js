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
 * form.js
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["core/ajax", "core/notification"], function (Ajax, Notification) {

    /**
     * Returns the first element found for a list of selectors.
     *
     * @param {Array} selectors CSS selectors.
     * @returns {HTMLElement|null} Element.
     */
    const pick = (selectors) => {
        for (let i = 0; i < selectors.length; i++) {
            const el = document.querySelector(selectors[i]);
            if (el) {
                return el;
            }
        }
        return null;
    };

    /**
     * Gets selected values for a select (supports multiple).
     *
     * @param {HTMLSelectElement} select Select element.
     * @returns {Array} Selected values (strings).
     */
    const getSelectedValues = (select) => {
        if (!select) {
            return [];
        }
        return Array.from(select.options)
            .filter((o) => o.selected)
            .map((o) => o.value);
    };

    /**
     * Attempts to apply an initial selection stored in data-initial-value.
     * Supports JSON array, comma-separated list, or single value.
     *
     * @param {HTMLSelectElement} select Select element.
     * @returns {void}
     */
    const applyInitialSelection = (select) => {
        if (!select || !select.dataset || !select.dataset.initialValue) {
            return;
        }

        const raw = (select.dataset.initialValue || "").trim();
        if (!raw) {
            return;
        }

        let values = [];
        try {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) {
                values = parsed.map((v) => String(v));
            } else {
                values = [String(parsed)];
            }
        } catch (e) {
            // Fallback: comma-separated list.
            values = raw.split(",").map((v) => v.trim()).filter((v) => v !== "");
        }

        if (!values.length) {
            return;
        }

        const set = new Set(values);
        Array.from(select.options).forEach((o) => {
            o.selected = set.has(String(o.value));
        });
    };

    /**
     * Sets options in a select element while preserving selection when possible.
     *
     * @param {HTMLSelectElement} select Select element.
     * @param {Array} options Options array [{id, label|name}]
     * @param {Boolean} keepFirst Keep the first option (placeholder).
     * @returns {void}
     */
    const setSelectOptions = (select, options, keepFirst) => {
        if (!select) {
            return;
        }

        const wasMultiple = !!select.multiple;
        const previousSingle = select.value;
        const previousMulti = wasMultiple ? getSelectedValues(select) : [];

        let firstOption = null;
        if (keepFirst && select.options.length > 0) {
            firstOption = select.options[0];
        }

        select.innerHTML = "";
        if (firstOption) {
            select.appendChild(firstOption);
        }

        (options || []).forEach((o) => {
            const opt = document.createElement("option");
            opt.value = String(o.id);
            opt.textContent = o.label || o.name || "";
            select.appendChild(opt);
        });

        // Restore selection.
        if (wasMultiple) {
            if (previousMulti.length) {
                const set = new Set(previousMulti.map(String));
                Array.from(select.options).forEach((o) => {
                    o.selected = set.has(String(o.value));
                });
            } else {
                applyInitialSelection(select);
            }
        } else {
            if (previousSingle) {
                select.value = previousSingle;
            }
        }
    };

    /**
     * Loads child course details and updates dependent fields.
     *
     * @param {Number} parentCourseId Parent course id.
     * @param {Number} childCourseId Child course id.
     * @returns {void}
     */
    const loadDetails = (parentCourseId, childCourseId) => {
        if (!childCourseId || childCourseId <= 0) {
            return;
        }

        const requests = [{
            methodname: "mod_childcourse_get_course_details",
            args: {
                parentcourseid: parentCourseId,
                childcourseid: childCourseId
            }
        }];

        Ajax.call(requests)[0].then((result) => {
            const groupSelect = pick([
                "select[name='targetgroupid']",
                "#id_targetgroupid"
            ]);
            setSelectOptions(groupSelect, result.groups || [], true);

            const moduleOptions = (result.modules || []).map((m) => ({id: m.id, label: m.label}));

            const completionCmidSelect = pick([
                "select[name='completioncmid']",
                "#id_completioncmid"
            ]);
            setSelectOptions(completionCmidSelect, moduleOptions, true);

            return true;
        }).catch((e) => {
            Notification.exception(e);
        });
    };

    /**
     * Initializes the module form behavior.
     *
     * @returns {void}
     */
    const init = () => {
        const childSelect = pick([
            "select[name='childcourseid']",
            "#id_childcourseid"
        ]);

        const ruleSelect = pick([
            "select[name='completionrule']",
            "#id_completionrule"
        ]);

        if (!childSelect) {
            return;
        }

        const parentCourseInput = document.querySelector("input[name='course']");
        const parentCourseId = parentCourseInput ? (parseInt(parentCourseInput.value, 10) || 0) : 0;

        // If child course is frozen (editing), still keep toggles but skip Ajax refresh on change.
        if (!childSelect.disabled) {
            childSelect.addEventListener("change", () => {
                const childCourseId = parseInt(childSelect.value, 10) || 0;
                loadDetails(parentCourseId, childCourseId);
            });
        }

        const initialId = parseInt(childSelect.value, 10) || 0;
        if (initialId > 0) {
            loadDetails(parentCourseId, initialId);
        }
    };

    return {init: init};
});
