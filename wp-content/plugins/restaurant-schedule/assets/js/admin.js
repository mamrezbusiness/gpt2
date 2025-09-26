(function ($) {
    'use strict';

    function initDatepickers(context) {
        context.find('.restaurant-datepicker').each(function () {
            var $input = $(this);
            if ($input.hasClass('hasDatepicker')) {
                return;
            }
            $input.datepicker({
                dateFormat: RestaurantScheduleAdmin.dateFormat
            });
        });
    }

    function initTimepickers(context) {
        context.find('.restaurant-timepicker').each(function () {
            var $input = $(this);
            if ($input.attr('type') === 'time') {
                return;
            }
            $input.attr('type', 'time');
        });
    }

    function addRange(day) {
        var $dayContainer = $('.restaurant-schedule-day[data-day="' + day + '"]');
        var index = parseInt($dayContainer.attr('data-next-index'), 10);
        if (isNaN(index)) {
            index = $dayContainer.find('.restaurant-schedule-range').length;
        }
        var template = '<div class="restaurant-schedule-range">' +
            '<label><span class="screen-reader-text">' + RestaurantScheduleAdmin.i18n.startPlaceholder + '</span>' +
            '<input type="time" class="restaurant-timepicker" name="schedule[' + day + '][' + index + '][start]" /></label>' +
            '<span class="dashicons dashicons-arrow-right-alt"></span>' +
            '<label><span class="screen-reader-text">' + RestaurantScheduleAdmin.i18n.endPlaceholder + '</span>' +
            '<input type="time" class="restaurant-timepicker" name="schedule[' + day + '][' + index + '][end]" /></label>' +
            '<button type="button" class="button button-link-delete restaurant-remove-range">' + RestaurantScheduleAdmin.i18n.removeRange + '</button>' +
            '</div>';

        var $template = $(template);
        $dayContainer.find('.restaurant-schedule-ranges').append($template);
        initTimepickers($template);
        $dayContainer.attr('data-next-index', index + 1);
    }

    function addHoliday() {
        var $container = $('.restaurant-holidays-list');
        var index = parseInt($container.attr('data-next-index'), 10);
        if (isNaN(index)) {
            index = $container.find('.restaurant-holiday').length;
        }
        var template = '<div class="restaurant-holiday">' +
            '<input type="text" class="restaurant-datepicker" name="schedule[holidays][' + index + ']" placeholder="' + RestaurantScheduleAdmin.i18n.holidayPlaceholder + '" />' +
            '<button type="button" class="button button-link-delete restaurant-remove-holiday">' + RestaurantScheduleAdmin.i18n.removeHoliday + '</button>' +
            '</div>';

        var $template = $(template);
        $container.append($template);
        initDatepickers($template);
        $container.attr('data-next-index', index + 1);
    }

    $(document).ready(function () {
        initDatepickers($(document));
        initTimepickers($(document));

        $(document).on('click', '.restaurant-add-range', function (event) {
            event.preventDefault();
            addRange($(this).data('day'));
        });

        $(document).on('click', '.restaurant-remove-range', function (event) {
            event.preventDefault();
            var $range = $(this).closest('.restaurant-schedule-range');
            if ($range.siblings('.restaurant-schedule-range').length === 0) {
                $range.find('input').val('');
                return;
            }
            $range.remove();
        });

        $(document).on('click', '.restaurant-add-holiday', function (event) {
            event.preventDefault();
            addHoliday();
        });

        $(document).on('click', '.restaurant-remove-holiday', function (event) {
            event.preventDefault();
            var $holiday = $(this).closest('.restaurant-holiday');
            if ($holiday.siblings('.restaurant-holiday').length === 0) {
                $holiday.find('input').val('');
                return;
            }
            $holiday.remove();
        });
    });
})(jQuery);
