(function ($) {
    'use strict';

    function dismissNotice($notice) {
        $notice.fadeOut(200, function () {
            $notice.remove();
            $('body').removeClass('has-restaurant-schedule-notice');
        });
        if (window.sessionStorage) {
            sessionStorage.setItem(RestaurantScheduleFrontend.dismissKey, '1');
        }
    }

    $(function () {
        var $notice = $('.restaurant-schedule-notice');
        if (!$notice.length) {
            return;
        }

        if (window.sessionStorage && sessionStorage.getItem(RestaurantScheduleFrontend.dismissKey)) {
            $notice.remove();
            return;
        }

        $('body').addClass('has-restaurant-schedule-notice');

        $notice.on('click', '.restaurant-schedule-notice__dismiss', function () {
            dismissNotice($notice);
        });
    });
})(jQuery);
