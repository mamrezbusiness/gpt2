(function () {
    'use strict';

    const timePattern = /^(?:[01]\d|2[0-3]):[0-5]\d$/;

    function parseJSON(value, fallback) {
        try {
            const parsed = JSON.parse(value);
            return parsed && typeof parsed === 'object' ? parsed : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function createIntervalRow(dayKey, interval, onChange) {
        const row = document.createElement('div');
        row.className = 'woh-interval';

        const startInput = document.createElement('input');
        startInput.type = 'text';
        startInput.className = 'woh-time woh-time-start';
        startInput.value = interval.start || '';
        startInput.placeholder = '08:00';
        startInput.setAttribute('aria-label', (wohAdmin.i18n.start || 'Start time') + ' ' + dayKey);

        const endInput = document.createElement('input');
        endInput.type = 'text';
        endInput.className = 'woh-time woh-time-end';
        endInput.value = interval.end || '';
        endInput.placeholder = '17:00';
        endInput.setAttribute('aria-label', (wohAdmin.i18n.end || 'End time') + ' ' + dayKey);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'button-link woh-remove-interval';
        remove.textContent = wohAdmin.i18n.remove || 'Remove';

        function updateValue() {
            if (timePattern.test(startInput.value) && timePattern.test(endInput.value)) {
                startInput.classList.remove('woh-invalid');
                endInput.classList.remove('woh-invalid');
                onChange({ start: startInput.value, end: endInput.value });
            } else {
                if (!timePattern.test(startInput.value)) {
                    startInput.classList.add('woh-invalid');
                } else {
                    startInput.classList.remove('woh-invalid');
                }
                if (!timePattern.test(endInput.value)) {
                    endInput.classList.add('woh-invalid');
                } else {
                    endInput.classList.remove('woh-invalid');
                }
            }
        }

        startInput.addEventListener('blur', updateValue);
        endInput.addEventListener('blur', updateValue);
        remove.addEventListener('click', function () {
            row.dispatchEvent(new CustomEvent('woh-remove', { bubbles: true }));
        });

        row.appendChild(startInput);
        row.appendChild(document.createTextNode(' – '));
        row.appendChild(endInput);
        row.appendChild(remove);

        return row;
    }

    function initWeekly() {
        const wrapper = document.querySelector('.woh-weekly');
        const input = document.querySelector('.woh-weekly-input');
        if (!wrapper || !input) {
            return;
        }

        const data = parseJSON(wrapper.getAttribute('data-weekly') || '{}', {});
        const state = Object.assign({}, data);

        function sync() {
            input.value = JSON.stringify(state);
        }

        Array.prototype.forEach.call(wrapper.querySelectorAll('.woh-day'), function (day) {
            const dayKey = day.getAttribute('data-day');
            if (!state[dayKey]) {
                state[dayKey] = [];
            }
            const list = day.querySelector('.woh-interval-list');
            const addBtn = day.querySelector('.woh-add-interval');

            function render() {
                list.innerHTML = '';
                state[dayKey].forEach(function (interval, index) {
                    const row = createIntervalRow(dayKey, interval, function (updated) {
                        state[dayKey][index] = updated;
                        sync();
                    });
                    row.addEventListener('woh-remove', function () {
                        state[dayKey].splice(index, 1);
                        render();
                        sync();
                    });
                    list.appendChild(row);
                });
            }

            addBtn.addEventListener('click', function () {
                state[dayKey].push({ start: '08:00', end: '17:00' });
                render();
                sync();
            });

            render();
        });

        sync();
    }

    function initHolidays() {
        const wrapper = document.querySelector('.woh-holidays');
        const input = document.querySelector('.woh-holidays-input');
        if (!wrapper || !input) {
            return;
        }

        const list = wrapper.querySelector('.woh-holiday-list');
        const addBtn = wrapper.querySelector('.woh-add-holiday');
        let holidays = parseJSON(wrapper.getAttribute('data-holidays') || '[]', []);

        function sync() {
            input.value = JSON.stringify(holidays);
        }

        function render() {
            list.innerHTML = '';
            holidays.forEach(function (value, index) {
                const row = document.createElement('div');
                row.className = 'woh-holiday-row';

                const inputDate = document.createElement('input');
                inputDate.type = 'date';
                inputDate.value = value;
                inputDate.setAttribute('aria-label', wohAdmin.i18n.holiday_date || 'Holiday date');

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'button-link woh-remove-holiday';
                remove.textContent = wohAdmin.i18n.remove || 'Remove';

                inputDate.addEventListener('change', function () {
                    holidays[index] = inputDate.value;
                    sync();
                });

                remove.addEventListener('click', function () {
                    holidays.splice(index, 1);
                    render();
                    sync();
                });

                row.appendChild(inputDate);
                row.appendChild(remove);
                list.appendChild(row);
            });
        }

        addBtn.addEventListener('click', function () {
            holidays.push('');
            render();
            sync();
        });

        render();
        sync();
    }

    function initSpecialHours() {
        const wrapper = document.querySelector('.woh-special');
        const input = document.querySelector('.woh-special-input');
        if (!wrapper || !input) {
            return;
        }

        const list = wrapper.querySelector('.woh-special-list');
        const addBtn = wrapper.querySelector('.woh-add-special');
        let specials = parseJSON(wrapper.getAttribute('data-special') || '[]', []);

        function sync() {
            input.value = JSON.stringify(specials);
        }

        function render() {
            list.innerHTML = '';
            specials.forEach(function (entry, index) {
                const box = document.createElement('div');
                box.className = 'woh-special-entry';

                const header = document.createElement('div');
                header.className = 'woh-special-header';

                const dateInput = document.createElement('input');
                dateInput.type = 'date';
                dateInput.value = entry.date || '';
                dateInput.setAttribute('aria-label', wohAdmin.i18n.special_date || 'Date');

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'button-link woh-remove-special';
                remove.textContent = wohAdmin.i18n.remove || 'Remove';

                header.appendChild(dateInput);
                header.appendChild(remove);

                const intervalList = document.createElement('div');
                intervalList.className = 'woh-special-intervals';

                const addInterval = document.createElement('button');
                addInterval.type = 'button';
                addInterval.className = 'button woh-add-special-interval';
                addInterval.textContent = wohAdmin.i18n.add_interval || 'Add interval';

                if (!Array.isArray(entry.intervals)) {
                    entry.intervals = [];
                }

                function renderIntervals() {
                    intervalList.innerHTML = '';
                    entry.intervals.forEach(function (interval, intervalIndex) {
                        const row = createIntervalRow(entry.date || 'special', interval, function (updated) {
                            entry.intervals[intervalIndex] = updated;
                            specials[index] = entry;
                            sync();
                        });
                        row.addEventListener('woh-remove', function () {
                            entry.intervals.splice(intervalIndex, 1);
                            renderIntervals();
                            specials[index] = entry;
                            sync();
                        });
                        intervalList.appendChild(row);
                    });
                }

                dateInput.addEventListener('change', function () {
                    entry.date = dateInput.value;
                    specials[index] = entry;
                    sync();
                });

                remove.addEventListener('click', function () {
                    specials.splice(index, 1);
                    render();
                    sync();
                });

                addInterval.addEventListener('click', function () {
                    entry.intervals.push({ start: '08:00', end: '17:00' });
                    renderIntervals();
                    specials[index] = entry;
                    sync();
                });

                renderIntervals();

                box.appendChild(header);
                const heading = document.createElement('p');
                heading.className = 'woh-special-heading';
                heading.textContent = wohAdmin.i18n.special_heading || 'Intervals';
                box.appendChild(heading);
                box.appendChild(intervalList);
                box.appendChild(addInterval);

                list.appendChild(box);
            });
        }

        addBtn.addEventListener('click', function () {
            specials.push({ date: '', intervals: [] });
            render();
            sync();
        });

        render();
        sync();
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof wohAdmin === 'undefined') {
            return;
        }
        initWeekly();
        initHolidays();
        initSpecialHours();
    });
})();
