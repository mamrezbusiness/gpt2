(function () {
    'use strict';

    function fetchStatus() {
        if (!window.wohStatus) {
            return Promise.reject('Missing configuration');
        }

        const params = new URLSearchParams({ action: 'woh_status' });
        if (window.wohStatus.nonce) {
            params.append('nonce', window.wohStatus.nonce);
        }

        return fetch(window.wohStatus.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Cache-Control': 'no-store',
            },
            body: params.toString(),
            credentials: 'same-origin',
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Request failed');
            }
            return response.json();
        }).then(function (payload) {
            if (!payload || !payload.success) {
                throw new Error('Invalid response');
            }
            return payload.data;
        });
    }

    function renderBanner(container, data) {
        container.innerHTML = '';
        if (!data) {
            return;
        }

        const status = data.status || {};
        const messages = data.messages || window.wohStatus.messages || {};
        const toggles = data.toggles || window.wohStatus.toggles || {};

        if (!toggles.show_global_banner_when_closed || status.is_open) {
            container.classList.remove('woh-banner-active');
            return;
        }

        container.classList.add('woh-banner-active');

        const banner = document.createElement('div');
        banner.className = 'woh-banner';

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'woh-banner-close';
        closeButton.setAttribute('aria-label', window.wohStatus.i18n.close || 'Close');
        closeButton.textContent = '×';

        const message = document.createElement('p');
        message.className = 'woh-banner-text';

        const notice = messages.closed_notice || window.wohStatus.messages.closed_notice || '';
        const reopenPrefix = messages.reopen_prefix || window.wohStatus.messages.reopen_prefix || '';

        let messageText = notice;
        if (status.human_next_opening) {
            messageText += ' ' + reopenPrefix + ' ' + status.human_next_opening;
        }
        message.textContent = messageText;

        banner.appendChild(closeButton);
        banner.appendChild(message);

        if (toggles.show_countdown_to_next_open && status.next_opening_iso) {
            const countdown = document.createElement('div');
            countdown.className = 'woh-countdown';
            banner.appendChild(countdown);

            const target = new Date(status.next_opening_iso);
            if (!isNaN(target.getTime())) {
                let intervalId;
                const update = function () {
                    const now = new Date();
                    const distance = target.getTime() - now.getTime();
                    if (distance <= 0) {
                        countdown.textContent = window.wohStatus.i18n.refresh || '';
                        if (intervalId) {
                            clearInterval(intervalId);
                        }
                        setTimeout(function () {
                            fetchStatus().then(function (fresh) {
                                renderBanner(container, fresh);
                            });
                        }, 5000);
                        return;
                    }

                    const totalSeconds = Math.floor(distance / 1000);
                    const hours = Math.floor(totalSeconds / 3600);
                    const minutes = Math.floor((totalSeconds % 3600) / 60);
                    const seconds = totalSeconds % 60;
                    countdown.textContent = [hours, minutes, seconds]
                        .map(function (unit) { return String(unit).padStart(2, '0'); })
                        .join(':');
                };

                update();
                intervalId = setInterval(update, 1000);
            }
        }

        closeButton.addEventListener('click', function () {
            container.innerHTML = '';
            container.classList.remove('woh-banner-active');
        });

        container.appendChild(banner);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const container = document.getElementById('woh-status-banner');
        if (!container || !window.wohStatus) {
            return;
        }

        fetchStatus()
            .then(function (data) {
                renderBanner(container, data);
            })
            .catch(function () {
                // Fail silently.
            });
    });
})();
