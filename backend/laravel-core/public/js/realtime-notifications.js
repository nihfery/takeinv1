(function () {
    const roots = Array.from(document.querySelectorAll('[data-notification-root]'));

    if (roots.length === 0) {
        return;
    }

    const jsonHeaders = (csrfToken) => ({
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken || '',
    });

    const parseConfig = (root) => {
        const node = root.querySelector('[data-notification-config]');

        if (!node) {
            return {};
        }

        try {
            return JSON.parse(node.textContent || '{}');
        } catch (error) {
            return {};
        }
    };

    const compactCount = (count) => {
        const value = Number(count || 0);

        return value > 99 ? '99+' : String(value);
    };

    const runWhenIdle = (callback, timeout = 1500) => {
        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(callback, { timeout });
            return;
        }

        window.setTimeout(callback, Math.min(timeout, 500));
    };

    const normalizeNotification = (notification) => ({
        id: Number(notification?.id || 0),
        type: notification?.type || 'general',
        title: notification?.title || 'Notification',
        body: notification?.body || '',
        url: notification?.url || '',
        data: notification?.data || {},
        is_read: Boolean(notification?.is_read),
        time: notification?.time || '',
        created_at: notification?.created_at || '',
    });

    const isChatNotification = (notification) => String(notification?.type || '').startsWith('chat.');

    const setupNotificationRoot = (root) => {
        const config = parseConfig(root);
        const toggle = root.querySelector('[data-notification-toggle]');
        const popover = root.querySelector('[data-notification-popover]');
        const list = root.querySelector('[data-notification-list]');
        const countNode = root.querySelector('[data-notification-count]');
        const subtitle = root.querySelector('[data-notification-subtitle]');
        const readAllButton = root.querySelector('[data-notification-read-all]');

        if (!toggle || !popover || !list || !config.userId || !config.indexUrl) {
            return;
        }

        let notifications = [];
        let unreadCount = 0;
        let realtimeClient = null;
        let isSyncing = false;
        let latestChatNotificationId = 0;
        let isSyncingChatNotifications = false;
        let hasLoadedNotifications = false;
        let notificationLoadPromise = null;

        const setOpen = (isOpen) => {
            root.classList.toggle('is-open', isOpen);
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        };

        const setBadge = (count) => {
            unreadCount = Math.max(0, Number(count || 0));

            if (!countNode) {
                return;
            }

            countNode.textContent = compactCount(unreadCount);
            countNode.classList.toggle('is-hidden', unreadCount === 0);
        };

        const setSubtitle = () => {
            if (!subtitle) {
                return;
            }

            subtitle.textContent = unreadCount > 0
                ? `${compactCount(unreadCount)} unread`
                : 'All caught up';
        };

        const setReadAllState = () => {
            if (readAllButton) {
                readAllButton.disabled = unreadCount === 0;
            }
        };

        const activeChatThreadId = () => {
            const node = document.getElementById('supportChatConfig');

            if (!node) {
                return 0;
            }

            try {
                return Number(JSON.parse(node.textContent || '{}')?.activeThreadId || 0);
            } catch (error) {
                return 0;
            }
        };

        const incrementSidebarChatBadge = (notification) => {
            const threadId = Number(notification?.data?.thread_id || 0);

            if (threadId && threadId === activeChatThreadId()) {
                return;
            }

            document.querySelectorAll('[data-sidebar-chat-badge]').forEach((badge) => {
                const current = Number(String(badge.textContent || '0').replace('+', '')) || 0;
                const next = current + 1;

                badge.textContent = compactCount(next);
                badge.classList.remove('is-hidden');
            });
        };

        const renderEmpty = (message) => {
            list.innerHTML = '';

            const empty = document.createElement('div');
            empty.className = 'notification-empty';
            empty.textContent = message || 'No notifications yet.';
            list.appendChild(empty);
        };

        const renderLoading = () => {
            list.innerHTML = '';

            const loading = document.createElement('div');
            loading.className = 'notification-loading';
            loading.textContent = 'Loading notifications...';
            list.appendChild(loading);
        };

        const markRead = async (notification) => {
            if (!notification?.id || notification.is_read || !config.readUrlTemplate) {
                return;
            }

            const url = String(config.readUrlTemplate).replace('__ID__', String(notification.id));
            const response = await fetch(url, {
                method: 'POST',
                headers: jsonHeaders(config.csrfToken),
            });

            if (!response.ok) {
                throw new Error('Cannot mark notification as read.');
            }

            const payload = await response.json();
            notification.is_read = true;
            setBadge(payload.unread_count);
            setSubtitle();
            setReadAllState();
        };

        const makeNotificationNode = (notification) => {
            const item = notification.url
                ? document.createElement('a')
                : document.createElement('button');

            item.className = `notification-item ${notification.is_read ? '' : 'is-unread'}`.trim();
            item.dataset.notificationId = String(notification.id);

            if (notification.url) {
                item.href = notification.url;
            } else {
                item.type = 'button';
            }

            const dot = document.createElement('span');
            dot.className = 'notification-item-dot';

            const copy = document.createElement('span');
            copy.className = 'notification-item-copy';

            const title = document.createElement('strong');
            title.textContent = notification.title;

            const body = document.createElement('span');
            body.textContent = notification.body || 'Click to view details.';

            const time = document.createElement('time');
            time.textContent = notification.time || '';

            copy.append(title, body, time);
            item.append(dot, copy);

            item.addEventListener('click', async (event) => {
                event.preventDefault();

                try {
                    await markRead(notification);
                    item.classList.remove('is-unread');
                } catch (error) {
                    // The notification is still useful even if marking it read fails.
                }

                if (notification.url) {
                    window.location.href = notification.url;
                } else {
                    render();
                }
            });

            return item;
        };

        const render = () => {
            if (notifications.length === 0) {
                renderEmpty('No notifications yet.');
                setSubtitle();
                setReadAllState();
                return;
            }

            list.innerHTML = '';
            notifications.slice(0, 12).forEach((notification) => {
                list.appendChild(makeNotificationNode(notification));
            });

            setSubtitle();
            setReadAllState();
        };

        const addNotification = (rawNotification, nextUnreadCount) => {
            const notification = normalizeNotification(rawNotification);

            window.dispatchEvent(new CustomEvent('app:notification-created', {
                detail: {
                    notification,
                    unread_count: nextUnreadCount,
                },
            }));

            if (isChatNotification(notification)) {
                latestChatNotificationId = Math.max(latestChatNotificationId, Number(notification.id || 0));
                incrementSidebarChatBadge(notification);
                setBadge(nextUnreadCount);
                setSubtitle();
                setReadAllState();
                return;
            }

            if (!notification.id) {
                return;
            }

            notifications = [
                notification,
                ...notifications.filter((item) => item.id !== notification.id),
            ].slice(0, 12);

            setBadge(nextUnreadCount);
            render();
        };

        const fetchNotificationPayload = async () => {
            const response = await fetch(config.indexUrl, {
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Cannot load notifications.');
            }

            return response.json();
        };

        const applyNotificationPayload = (payload, announceNew = false) => {
            const nextNotifications = (payload.notifications || []).map(normalizeNotification);

            if (announceNew) {
                const existingIds = new Set(notifications.map((notification) => notification.id));
                const newNotifications = nextNotifications
                    .filter((notification) => notification.id && !existingIds.has(notification.id))
                    .reverse();

                newNotifications.forEach((notification) => {
                    addNotification(notification, payload.unread_count);
                });

                if (newNotifications.length > 0) {
                    return;
                }
            }

            notifications = nextNotifications;
            setBadge(payload.unread_count);
            render();
        };

        const loadNotifications = async () => {
            hasLoadedNotifications = true;
            renderLoading();

            try {
                applyNotificationPayload(await fetchNotificationPayload());
            } catch (error) {
                setBadge(0);
                renderEmpty('Notifications could not be loaded yet.');
            }
        };

        const ensureNotificationsLoaded = () => {
            if (!notificationLoadPromise) {
                notificationLoadPromise = loadNotifications();
            }

            return notificationLoadPromise;
        };

        const syncNotifications = async () => {
            if (isSyncing || !hasLoadedNotifications) {
                return;
            }

            isSyncing = true;

            try {
                applyNotificationPayload(await fetchNotificationPayload(), true);
            } catch (error) {
                // Realtime websocket may be unavailable locally; polling will retry quietly.
            } finally {
                isSyncing = false;
            }
        };

        const chatNotificationsUrl = () => {
            const url = new URL(config.indexUrl, window.location.origin);
            url.searchParams.set('include_chat', '1');

            return url.toString();
        };

        const fetchChatNotificationPayload = async () => {
            const response = await fetch(chatNotificationsUrl(), {
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Cannot load chat notifications.');
            }

            return response.json();
        };

        const primeChatNotificationCursor = async () => {
            try {
                const payload = await fetchChatNotificationPayload();
                latestChatNotificationId = Math.max(
                    0,
                    ...(payload.notifications || []).map((notification) => Number(notification.id || 0))
                );
            } catch (error) {
                // The normal notification center still works if chat polling cannot start yet.
            }
        };

        const syncChatNotifications = async () => {
            if (isSyncingChatNotifications) {
                return;
            }

            isSyncingChatNotifications = true;

            try {
                const payload = await fetchChatNotificationPayload();
                const chatNotifications = (payload.notifications || [])
                    .map(normalizeNotification)
                    .filter((notification) => isChatNotification(notification))
                    .sort((first, second) => Number(first.id || 0) - Number(second.id || 0));

                chatNotifications.forEach((notification) => {
                    if (!notification.id || notification.id <= latestChatNotificationId) {
                        return;
                    }

                    addNotification(notification, payload.unread_count);
                });
            } catch (error) {
                // Realtime is primary; polling quietly covers local websocket gaps.
            } finally {
                isSyncingChatNotifications = false;
            }
        };

        const markAllRead = async () => {
            if (!config.readAllUrl || unreadCount === 0) {
                return;
            }

            if (readAllButton) {
                readAllButton.disabled = true;
            }

            try {
                const response = await fetch(config.readAllUrl, {
                    method: 'POST',
                    headers: jsonHeaders(config.csrfToken),
                });

                if (!response.ok) {
                    throw new Error('Cannot mark notifications as read.');
                }

                notifications = notifications.map((notification) => ({
                    ...notification,
                    is_read: true,
                }));
                setBadge(0);
                render();
            } catch (error) {
                setReadAllState();
            }
        };

        const bootRealtime = () => {
            const broadcast = config.broadcast || {};

            if (!window.Pusher || !broadcast.key || !config.userId) {
                return;
            }

            const forceTLS = broadcast.scheme === 'https';
            const authEndpoint = config.authEndpoint || '/broadcasting/auth';
            const authHeaders = {
                'X-CSRF-TOKEN': config.csrfToken || '',
            };

            realtimeClient = new window.Pusher(broadcast.key, {
                cluster: 'mt1',
                wsHost: broadcast.host || window.location.hostname,
                wsPort: Number(broadcast.port || 8080),
                wssPort: Number(broadcast.port || 443),
                forceTLS,
                encrypted: forceTLS,
                enabledTransports: forceTLS ? ['wss'] : ['ws'],
                disableStats: true,
                authEndpoint,
                auth: {
                    headers: authHeaders,
                },
                channelAuthorization: {
                    endpoint: authEndpoint,
                    transport: 'ajax',
                    headers: authHeaders,
                },
            });

            const channel = realtimeClient.subscribe(`private-notifications.user.${config.userId}`);

            channel.bind('notification.created', (payload) => {
                if (payload?.notification) {
                    addNotification(payload.notification, payload.unread_count);
                }
            });
        };

        toggle.addEventListener('click', () => {
            const willOpen = !root.classList.contains('is-open');
            setOpen(willOpen);

            if (willOpen) {
                ensureNotificationsLoaded();
            }
        });

        popover.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        if (readAllButton) {
            readAllButton.addEventListener('click', markAllRead);
        }

        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        });

        setBadge(Number(config.initialUnreadCount || 0));
        renderEmpty('Open notifications to load.');
        setSubtitle();
        setReadAllState();

        runWhenIdle(() => {
            bootRealtime();
            primeChatNotificationCursor();

            window.setInterval(() => {
                if (!document.hidden) {
                    syncNotifications();
                }
            }, Number(config.pollInterval || 10000));

            window.setInterval(() => {
                if (!document.hidden) {
                    syncChatNotifications();
                }
            }, Number(config.chatPollInterval || 10000));
        });
    };

    roots.forEach(setupNotificationRoot);
})();
