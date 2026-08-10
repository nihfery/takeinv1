(function () {
    const root = document.querySelector('[data-support-chat]');

    if (!root) {
        return;
    }

    const configNode = document.getElementById('supportChatConfig');
    const messagesNode = root.querySelector('[data-chat-messages]');
    const form = root.querySelector('[data-chat-form]');
    const input = root.querySelector('[data-chat-input]');
    const threadList = root.querySelector('[data-thread-list]');
    const searchInputs = root.querySelectorAll('[data-chat-search]');
    const refreshButtons = root.querySelectorAll('[data-chat-refresh]');
    const focusButtons = root.querySelectorAll('[data-chat-focus]');
    const imageInput = root.querySelector('[data-chat-image]');
    const imageTrigger = root.querySelector('[data-chat-image-trigger]');
    const filePreview = root.querySelector('[data-chat-file-preview]');
    const fileName = root.querySelector('[data-chat-file-name]');
    const fileClear = root.querySelector('[data-chat-file-clear]');
    const emojiToggle = root.querySelector('[data-emoji-toggle]');
    const emojiPanel = root.querySelector('[data-emoji-panel]');
    const emojiButtons = root.querySelectorAll('[data-emoji]');
    const endChatForms = root.querySelectorAll('[data-chat-end-form]');
    const endChatDialog = root.querySelector('[data-chat-end-dialog]');
    const endChatConfirm = root.querySelector('[data-chat-end-confirm]');
    const endChatCancelButtons = root.querySelectorAll('[data-chat-end-cancel]');

    if (!configNode) {
        return;
    }

    const config = JSON.parse(configNode.textContent || '{}');
    const currentUserId = Number(config.currentUserId || 0);
    const activeThreadId = Number(config.activeThreadId || 0);
    const subscribedThreadIds = new Set();
    let realtimeClient = null;
    let latestMessageId = 0;
    let isPolling = false;
    let currentReadReceipts = config.readReceipts || {};
    let pendingEndChatForm = null;
    let isRedirectingClosedThread = false;
    let latestChatNotificationId = Number(config.latestChatNotificationId || 0);
    let isPollingChatNotifications = false;
    const previewedMessageIds = new Set();

    const scrollToBottom = () => {
        if (messagesNode) {
            messagesNode.scrollTop = messagesNode.scrollHeight;
        }
    };

    const removeEmptyState = () => {
        const empty = root.querySelector('[data-chat-empty]');

        if (empty) {
            empty.remove();
        }
    };

    const addSystemNotice = (message) => {
        if (!messagesNode || !message) {
            return;
        }

        removeEmptyState();

        const notice = document.createElement('div');
        notice.className = 'support-chat-system';
        notice.textContent = message;
        messagesNode.appendChild(notice);
        scrollToBottom();
    };

    const closeComposer = (message) => {
        if (input) {
            input.value = '';
            input.disabled = true;
            input.placeholder = 'Chat has ended';
        }

        if (imageInput) {
            imageInput.value = '';
            imageInput.disabled = true;
        }

        if (emojiPanel) {
            emojiPanel.hidden = true;
        }

        if (filePreview) {
            filePreview.classList.add('is-hidden');
        }

        if (form) {
            form.classList.add('is-closed');
            form.querySelectorAll('button').forEach((button) => {
                button.disabled = true;
            });
        }

        addSystemNotice(message || config.closedMessage || 'Chat has ended.');
    };

    const leaveClosedThread = () => {
        if (!config.closedRedirectUrl || isRedirectingClosedThread) {
            return false;
        }

        isRedirectingClosedThread = true;
        window.location.href = config.closedRedirectUrl;

        return true;
    };

    const messageExists = (id) => {
        return Boolean(messagesNode && messagesNode.querySelector(`[data-message-id="${id}"]`));
    };

    const syncLatestMessageId = () => {
        if (!messagesNode) {
            return;
        }

        messagesNode.querySelectorAll('[data-message-id]').forEach((node) => {
            latestMessageId = Math.max(latestMessageId, Number(node.dataset.messageId || 0));
        });
    };

    const makeMessageNode = (message) => {
        const isMine = Number(message.sender_id) === currentUserId;
        const wrapper = document.createElement('div');
        wrapper.className = `support-message ${isMine ? 'is-mine' : ''}`.trim();
        wrapper.dataset.messageId = message.id;
        wrapper.dataset.messageSenderId = message.sender_id || '';
        wrapper.dataset.messageSenderRole = message.sender_role || '';
        wrapper.dataset.messageCreatedAt = message.created_at || '';

        const bubble = document.createElement('div');
        bubble.className = 'support-bubble';

        const meta = document.createElement('div');
        meta.className = 'support-bubble-meta';

        const senderLine = document.createElement('span');
        senderLine.className = 'support-sender-line';

        const name = document.createElement('strong');
        name.textContent = message.sender_name || 'User';
        senderLine.appendChild(name);

        if (message.sender_role === 'admin') {
            const badge = document.createElement('span');
            badge.className = 'support-admin-badge';
            badge.textContent = 'Admin';
            senderLine.appendChild(badge);

            const check = document.createElement('span');
            check.className = 'support-verified-check support-message-check';
            check.title = 'Official admin account';
            check.setAttribute('aria-label', 'Official admin account');
            check.innerHTML = '<svg viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="9" fill="currentColor"/><path d="M6 10.2l2.5 2.5L14.2 7" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            senderLine.appendChild(check);
        }

        const time = document.createElement('span');
        time.className = 'support-bubble-time';
        time.textContent = message.sent_at || '';

        meta.append(senderLine, time);
        bubble.appendChild(meta);

        if (message.attachment && message.attachment.type === 'image' && message.attachment.url) {
            const imageLink = document.createElement('a');
            imageLink.className = 'support-message-image';
            imageLink.href = message.attachment.url;
            imageLink.target = '_blank';
            imageLink.rel = 'noopener';

            const image = document.createElement('img');
            image.src = message.attachment.url;
            image.alt = message.attachment.name || 'Chat image';

            imageLink.appendChild(image);
            bubble.appendChild(imageLink);
        }

        if (message.body) {
            const body = document.createElement('p');
            body.textContent = message.body;
            bubble.appendChild(body);
        }

        if (isMine) {
            const footer = document.createElement('div');
            footer.className = 'support-bubble-footer';

            const status = document.createElement('span');
            status.className = `support-message-status is-${message.delivery_status || 'sent'}`;
            status.dataset.messageStatus = '';
            status.dataset.status = message.delivery_status || 'sent';
            status.textContent = message.delivery_label || 'Sent';

            footer.appendChild(status);
            bubble.appendChild(footer);
        }

        wrapper.appendChild(bubble);

        return wrapper;
    };

    const recipientReadColumn = (senderRole, threadType) => {
        if (threadType === 'provider_branch') {
            return senderRole === 'provider_branch' ? 'last_provider_read_at' : 'last_branch_read_at';
        }

        return senderRole === 'admin' ? 'last_provider_read_at' : 'last_admin_read_at';
    };

    const setMessageStatus = (messageNode, status) => {
        const statusNode = messageNode.querySelector('[data-message-status]');

        if (!statusNode) {
            return;
        }

        statusNode.dataset.status = status;
        statusNode.textContent = status === 'read' ? 'Read' : 'Sent';
        statusNode.classList.remove('is-sent', 'is-read');
        statusNode.classList.add(`is-${status}`);
    };

    const updateDeliveryStatuses = (thread) => {
        if (!thread) {
            return;
        }

        currentReadReceipts = thread.read_receipts || currentReadReceipts || {};

        const threadType = thread.conversation_type || config.conversationType || 'provider_admin';

        root.querySelectorAll('.support-message.is-mine[data-message-created-at][data-message-sender-role]').forEach((messageNode) => {
            const createdAt = Date.parse(messageNode.dataset.messageCreatedAt || '');
            const readColumn = recipientReadColumn(messageNode.dataset.messageSenderRole || '', threadType);
            const readAt = Date.parse(currentReadReceipts[readColumn] || thread[readColumn] || '');

            if (!createdAt || !readAt) {
                setMessageStatus(messageNode, 'sent');
                return;
            }

            setMessageStatus(messageNode, readAt >= createdAt ? 'read' : 'sent');
        });
    };

    const updateThreadPreview = (message, incrementUnread) => {
        const item = threadList?.querySelector(`[data-thread-id="${message.thread_id}"]`);

        if (!item) {
            return;
        }

        const last = item.querySelector('[data-thread-last]');
        const time = item.querySelector('[data-thread-time]');
        const unread = item.querySelector('[data-thread-unread]');

        if (last) {
            const attachment = message.attachment || {};
            last.textContent = message.body || (attachment.type === 'image' ? 'Sending image' : '');
        }

        if (time) {
            time.textContent = message.sent_at || '';
        }

        if (unread && incrementUnread) {
            unread.textContent = String((Number(unread.textContent || 0) || 0) + 1);
            unread.classList.remove('is-hidden');
        }

        if (threadList.firstElementChild !== item) {
            threadList.prepend(item);
        }
    };

    const compactCount = (count) => {
        const value = Number(count || 0);

        return value > 99 ? '99+' : String(value);
    };

    const incrementSidebarChatBadge = () => {
        document.querySelectorAll('[data-sidebar-chat-badge]').forEach((badge) => {
            const current = Number(String(badge.textContent || '0').replace('+', '')) || 0;
            const next = current + 1;

            badge.textContent = compactCount(next);
            badge.classList.remove('is-hidden');
        });
    };

    const notificationTime = (notification) => {
        if (!notification.created_at) {
            return notification.time || '';
        }

        const date = new Date(notification.created_at);

        if (Number.isNaN(date.getTime())) {
            return notification.time || '';
        }

        return date.toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const updateThreadPreviewFromNotification = (notification, incrementSidebarTotal = false) => {
        if (notification.type !== 'chat.message') {
            return;
        }

        const threadId = Number(notification.data?.thread_id || 0);
        const messageId = Number(notification.data?.message_id || 0);

        if (!threadId || !messageId || previewedMessageIds.has(messageId)) {
            return;
        }

        previewedMessageIds.add(messageId);

        const isActive = threadId === activeThreadId;

        updateThreadPreview({
            id: messageId,
            thread_id: threadId,
            body: notification.body || '',
            sent_at: notificationTime(notification),
            attachment: {},
        }, !isActive);

        if (!isActive && incrementSidebarTotal) {
            incrementSidebarChatBadge();
        }

        if (isActive) {
            markRead();
        }
    };

    const updateThreadStatus = (thread) => {
        const item = threadList?.querySelector(`[data-thread-id="${thread.id}"]`);
        const statusNodes = root.querySelectorAll(`[data-thread-status="${thread.id}"]`);
        const label = thread.ticket_status === 'closed' ? 'Ended' : thread.ticket_status;

        statusNodes.forEach((node) => {
            node.textContent = label;
            node.classList.remove('pending', 'approved', 'rejected', 'closed', 'none');
            node.classList.add(thread.ticket_status || 'none');
        });

        if (item && thread.ticket_status === 'closed') {
            item.classList.add('is-closed');
        }
    };

    const handleThreadUpdated = (thread) => {
        if (!thread) {
            return;
        }

        updateThreadStatus(thread);
        updateDeliveryStatuses(thread);

        if (Number(thread.id) === activeThreadId && thread.ticket_status === 'closed' && !leaveClosedThread()) {
            closeComposer(thread.ticket_rejection_reason || config.closedMessage);
        }
    };

    const closedTicketNotificationTypes = new Set([
        'ticket.closed',
        'ticket.internal.closed',
    ]);

    window.addEventListener('app:notification-created', (event) => {
        const notification = event.detail?.notification || {};
        const notificationId = Number(notification.id || 0);

        if (notificationId && notification.type === 'chat.message') {
            latestChatNotificationId = Math.max(latestChatNotificationId, notificationId);
        }

        updateThreadPreviewFromNotification(notification);

        if (!closedTicketNotificationTypes.has(notification.type)) {
            return;
        }

        const threadId = Number(notification.data?.thread_id || 0);

        if (!threadId) {
            return;
        }

        handleThreadUpdated({
            id: threadId,
            conversation_type: config.conversationType || 'provider_admin',
            ticket_status: 'closed',
            status: 'closed',
            ticket_rejection_reason: notification.body || config.closedMessage,
            closed_at: notification.created_at || null,
            read_receipts: currentReadReceipts,
        });
    });

    const markRead = () => {
        if (!config.readUrl) {
            return;
        }

        fetch(config.readUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': config.csrfToken || '',
            },
        })
            .then((response) => response.ok ? response.json() : null)
            .then((payload) => {
                if (payload && payload.thread) {
                    handleThreadUpdated(payload.thread);
                }
            })
            .catch(() => {});
    };

    const appendMessage = (message, fromRealtime) => {
        if (!message) {
            return;
        }

        const isActive = Number(message.thread_id) === activeThreadId;
        const fromCurrentUser = Number(message.sender_id) === currentUserId;
        const messageId = Number(message.id || 0);
        const previewAlreadyApplied = messageId && previewedMessageIds.has(messageId);

        if (!previewAlreadyApplied) {
            if (messageId) {
                previewedMessageIds.add(messageId);
            }

            updateThreadPreview(message, fromRealtime && !isActive && !fromCurrentUser);
        }

        if (isActive) {
            latestMessageId = Math.max(latestMessageId, Number(message.id || 0));
        }

        if (!isActive || !messagesNode || messageExists(message.id)) {
            return;
        }

        removeEmptyState();
        messagesNode.appendChild(makeMessageNode(message));
        updateDeliveryStatuses({
            id: activeThreadId,
            conversation_type: config.conversationType || 'provider_admin',
            read_receipts: currentReadReceipts,
        });
        scrollToBottom();

        if (fromRealtime && isActive && !fromCurrentUser) {
            markRead();
        }
    };

    const pollChatNotifications = async () => {
        if (!config.chatNotificationPollUrl || isPollingChatNotifications) {
            return;
        }

        isPollingChatNotifications = true;

        try {
            const url = new URL(config.chatNotificationPollUrl, window.location.origin);
            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken || '',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const chatNotifications = (payload.notifications || [])
                .filter((notification) => notification.type === 'chat.message')
                .sort((first, second) => Number(first.id || 0) - Number(second.id || 0));

            chatNotifications.forEach((notification) => {
                const notificationId = Number(notification.id || 0);

                if (!notificationId || notificationId <= latestChatNotificationId) {
                    return;
                }

                latestChatNotificationId = Math.max(latestChatNotificationId, notificationId);
                updateThreadPreviewFromNotification(notification);
            });
        } catch (error) {
            // Realtime is primary; polling quietly covers local websocket gaps.
        } finally {
            isPollingChatNotifications = false;
        }
    };

    const pollMessages = async () => {
        if (!config.messagesUrl || !activeThreadId || !messagesNode || isPolling) {
            return;
        }

        isPolling = true;

        try {
            const url = new URL(config.messagesUrl, window.location.origin);
            url.searchParams.set('after_id', String(latestMessageId));

            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken || '',
                },
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();

            (payload.messages || []).forEach((message) => appendMessage(message, true));

            if (payload.thread) {
                handleThreadUpdated(payload.thread);
            }
        } catch (error) {
            // Polling is only a fallback; websocket state already tells the user if realtime is offline.
        } finally {
            isPolling = false;
        }
    };

    const autoresizeInput = () => {
        if (!input) {
            return;
        }

        const minHeight = 38;
        const maxHeight = 82;

        input.style.height = `${minHeight}px`;

        const nextHeight = Math.min(input.scrollHeight, maxHeight);
        input.style.height = `${Math.max(minHeight, nextHeight)}px`;
        input.style.overflowY = input.scrollHeight > maxHeight ? 'auto' : 'hidden';
    };

    const setEmojiPanelOpen = (isOpen) => {
        if (emojiPanel) {
            emojiPanel.hidden = !isOpen;
        }
    };

    const refreshFilePreview = () => {
        if (!imageInput || !filePreview || !fileName) {
            return;
        }

        const file = imageInput.files && imageInput.files[0];

        if (!file) {
            filePreview.classList.add('is-hidden');
            fileName.textContent = '';
            return;
        }

        fileName.textContent = file.name;
        filePreview.classList.remove('is-hidden');
    };

    const clearSelectedImage = () => {
        if (imageInput) {
            imageInput.value = '';
        }

        refreshFilePreview();
    };

    const setEndChatDialogOpen = (isOpen, endForm = null) => {
        if (!endChatDialog) {
            return;
        }

        pendingEndChatForm = isOpen ? endForm : null;
        endChatDialog.hidden = !isOpen;
        endChatDialog.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        root.classList.toggle('is-confirming-end-chat', isOpen);

        if (isOpen) {
            window.setTimeout(() => endChatConfirm?.focus(), 0);
        }
    };

    const insertEmoji = (emoji) => {
        if (!input || !emoji) {
            return;
        }

        const start = input.selectionStart ?? input.value.length;
        const end = input.selectionEnd ?? input.value.length;
        input.value = `${input.value.slice(0, start)}${emoji}${input.value.slice(end)}`;
        input.selectionStart = start + emoji.length;
        input.selectionEnd = start + emoji.length;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.focus();
    };

    if (input) {
        input.addEventListener('input', autoresizeInput);
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                form?.requestSubmit();
            }
        });
    }

    if (imageTrigger && imageInput) {
        imageTrigger.addEventListener('click', () => {
            imageInput.click();
        });
    }

    if (imageInput) {
        imageInput.addEventListener('change', refreshFilePreview);
    }

    if (fileClear) {
        fileClear.addEventListener('click', clearSelectedImage);
    }

    endChatForms.forEach((endForm) => {
        endForm.addEventListener('submit', (event) => {
            if (endForm.dataset.chatEndConfirmed === 'true') {
                return;
            }

            event.preventDefault();

            if (!endChatDialog) {
                if (window.confirm('Are you sure you want to end this chat?')) {
                    endForm.dataset.chatEndConfirmed = 'true';
                    endForm.requestSubmit();
                }

                return;
            }

            setEndChatDialogOpen(true, endForm);
        });
    });

    endChatCancelButtons.forEach((button) => {
        button.addEventListener('click', () => setEndChatDialogOpen(false));
    });

    if (endChatConfirm) {
        endChatConfirm.addEventListener('click', () => {
            if (!pendingEndChatForm) {
                setEndChatDialogOpen(false);
                return;
            }

            endChatConfirm.disabled = true;
            pendingEndChatForm.dataset.chatEndConfirmed = 'true';
            pendingEndChatForm.requestSubmit();
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && endChatDialog && !endChatDialog.hidden) {
            setEndChatDialogOpen(false);
        }
    });

    if (emojiToggle && emojiPanel) {
        emojiToggle.addEventListener('click', () => {
            setEmojiPanelOpen(emojiPanel.hidden);
        });
    }

    emojiButtons.forEach((button) => {
        button.addEventListener('click', () => {
            insertEmoji(button.dataset.emoji || button.textContent || '');
            setEmojiPanelOpen(false);
        });
    });

    document.addEventListener('click', (event) => {
        if (!emojiPanel || emojiPanel.hidden || !form) {
            return;
        }

        if (!form.contains(event.target)) {
            setEmojiPanelOpen(false);
        }
    });

    searchInputs.forEach((searchInput) => {
        searchInput.addEventListener('input', () => {
            const keyword = searchInput.value.trim().toLowerCase();

            root.querySelectorAll('[data-chat-row]').forEach((row) => {
                const label = (row.dataset.chatLabel || row.textContent || '').toLowerCase();
                row.classList.toggle('is-hidden', keyword !== '' && !label.includes(keyword));
            });
        });
    });

    refreshButtons.forEach((button) => {
        button.addEventListener('click', () => {
            pollMessages();
        });
    });

    focusButtons.forEach((button) => {
        button.addEventListener('click', () => {
            input?.focus();
        });
    });

    if (form && input && config.sendUrl) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const body = input.value.trim();
            const file = imageInput?.files?.[0] || null;

            if (!body && !file) {
                return;
            }

            if (file && file.size > 4 * 1024 * 1024) {
                addSystemNotice('Maximum image size is 4 MB.');
                return;
            }

            const button = form.querySelector('button[type="submit"]');

            if (button) {
                button.disabled = true;
            }

            try {
                const headers = {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken || '',
                };
                const socketId = realtimeClient?.connection?.socket_id;
                const formData = new FormData();

                formData.append('body', body);

                if (file) {
                    formData.append('image', file);
                }

                if (socketId) {
                    headers['X-Socket-ID'] = socketId;
                }

                const response = await fetch(config.sendUrl, {
                    method: 'POST',
                    headers,
                    body: formData,
                });

                if (!response.ok) {
                    throw new Error('Message failed');
                }

                const payload = await response.json();
                input.value = '';
                clearSelectedImage();
                setEmojiPanelOpen(false);
                autoresizeInput();
                appendMessage(payload.message, false);
            } catch (error) {
                addSystemNotice('Message failed to send. Please try again.');
            } finally {
                if (button) {
                    button.disabled = false;
                }

                input.focus();
            }
        });
    }

    const subscribeToThread = (pusher, threadId) => {
        if (!threadId || subscribedThreadIds.has(threadId)) {
            return;
        }

        subscribedThreadIds.add(threadId);

        const channel = pusher.subscribe(`private-chat.thread.${threadId}`);

        channel.bind('message.sent', (payload) => {
            if (payload && payload.message) {
                appendMessage(payload.message, true);
            }
        });

        channel.bind('thread.updated', (payload) => {
            if (payload && payload.thread) {
                handleThreadUpdated(payload.thread);
            }
        });
    };

    const bootRealtime = () => {
        const broadcast = config.broadcast || {};

        if (!window.Pusher || !broadcast.key) {
            return;
        }

        const forceTLS = broadcast.scheme === 'https';
        const authEndpoint = config.authEndpoint || '/broadcasting/auth';
        const authHeaders = {
            'X-CSRF-TOKEN': config.csrfToken || '',
        };
        const pusher = new window.Pusher(broadcast.key, {
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

        realtimeClient = pusher;

        (config.threadIds || []).forEach((threadId) => subscribeToThread(pusher, Number(threadId)));
    };

    syncLatestMessageId();
    scrollToBottom();
    autoresizeInput();
    updateDeliveryStatuses({
        id: activeThreadId,
        conversation_type: config.conversationType || 'provider_admin',
        read_receipts: currentReadReceipts,
    });
    bootRealtime();

    if (config.messagesUrl) {
        window.setTimeout(pollMessages, 900);
        window.setInterval(pollMessages, 2500);
    }

    if (config.chatNotificationPollUrl) {
        window.setTimeout(pollChatNotifications, 1200);
        window.setInterval(pollChatNotifications, 3000);
    }
})();
