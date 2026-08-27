/*
 * Shared notification bell/inbox logic for the Admin panel and Hotel
 * Portal — both use the Firebase compat SDK (firebase.firestore()), unlike
 * the guest-facing bell in includes/user_top_navbar.php which uses the
 * modular SDK, so this is a separate implementation rather than a shared
 * function with that one.
 *
 * Usage (after firebase.initializeApp + firebase.firestore() are ready):
 *   window.travelixInitPortalNotifications({
 *       db: firebase.firestore(),
 *       filters: [['audience', '==', 'admin']],           // array of [field, op, value]
 *   });
 *
 * Expects the shared markup (see admin_sidebar.php / hotel_portal topbars)
 * with element ids matching the guest bell's ids, and
 * assets/css/travelix_notifications.css loaded for styling.
 */
window.travelixInitPortalNotifications = function (options) {
    const db = options.db;
    const filters = options.filters || [];
    const manageUrl = options.manageUrl || '';

    const notificationToggle = document.getElementById('travelixNotificationToggle');
    const notificationPanel = document.getElementById('travelixNotificationPanel');
    const notificationWrapper = document.getElementById('travelixNotificationWrapper');
    const notificationList = document.getElementById('travelixNotificationList');
    const notificationBadge = document.getElementById('travelixNotificationBadge');
    const notificationCountText = document.getElementById('travelixNotificationCountText');
    const readAllBtn = document.getElementById('travelixReadAllBtn');
    const refreshNotificationsBtn = document.getElementById('travelixRefreshNotificationsBtn');

    if (!notificationToggle || !db) return;

    // Every portal panel gets a real View All destination. Older markup did
    // not include one, which made the popup button merely reopen the bell.
    if (notificationPanel && manageUrl && !notificationPanel.querySelector('.travelix-notification-view-all')) {
        const footer = document.createElement('div');
        footer.style.cssText = 'padding:11px 14px;border-top:1px solid #e5e7eb;text-align:center;background:#f8fafc;';
        footer.innerHTML = `<a class="travelix-notification-view-all" href="${manageUrl}" style="color:#1484B4;font-weight:800;font-size:13px;text-decoration:none;">View All Notifications</a>`;
        notificationPanel.appendChild(footer);
    }

    let currentNotifications = [];
    let unsubscribe = null;

    const popupStorageKey = 'travelix_popup_shown_ids_' + (options.popupKey || JSON.stringify(filters));
    let shownPopupIds = new Set();
    try {
        const stored = sessionStorage.getItem(popupStorageKey);
        if (stored) shownPopupIds = new Set(JSON.parse(stored));
    } catch (e) { /* sessionStorage unavailable — popups just won't dedupe across page loads */ }

    // Tab-title blinking so the admin notices a new notification even when
    // this browser tab isn't the focused one — flips the tab title back and
    // forth until they actually switch to it.
    const originalTitle = document.title;
    let blinkIntervalId = null;
    let previousUnreadCount = null;

    function startTitleBlink() {
        if (blinkIntervalId) return;
        let showAlert = true;
        blinkIntervalId = setInterval(() => {
            document.title = showAlert ? '🔔 New Notification!' : originalTitle;
            showAlert = !showAlert;
        }, 1000);
    }

    function stopTitleBlink() {
        if (!blinkIntervalId) return;
        clearInterval(blinkIntervalId);
        blinkIntervalId = null;
        document.title = originalTitle;
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) stopTitleBlink();
    });
    window.addEventListener('focus', stopTitleBlink);

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function getIcon(type) {
        const icons = {
            refund_pending: 'fa-solid fa-hourglass-half',
            refund_sent: 'fa-solid fa-circle-check',
            payout_pending: 'fa-solid fa-money-bill-wave',
            payout_sent: 'fa-solid fa-circle-check',
            booking_cancelled: 'fa-solid fa-circle-xmark',
            hotel_booking_rejected: 'fa-solid fa-circle-xmark',
            general: 'fa-solid fa-bell'
        };
        return icons[type] || icons.general;
    }

    function getCreatedAtMs(createdAt) {
        if (!createdAt) return Date.now();
        if (createdAt.toDate) return createdAt.toDate().getTime();
        if (createdAt.seconds) return createdAt.seconds * 1000;
        const parsed = new Date(createdAt).getTime();
        return Number.isNaN(parsed) ? Date.now() : parsed;
    }

    function formatTime(createdAt) {
        if (!createdAt) return 'Just now';
        const seconds = Math.floor((Date.now() - getCreatedAtMs(createdAt)) / 1000);
        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + ' min ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + ' hour ago';
        if (seconds < 604800) return Math.floor(seconds / 86400) + ' day ago';
        return new Date(getCreatedAtMs(createdAt)).toLocaleDateString();
    }

    function sortNotifications() {
        currentNotifications.sort((a, b) => getCreatedAtMs(b.createdAt) - getCreatedAtMs(a.createdAt));
    }

    function updateNotificationCount() {
        const unreadCount = currentNotifications.filter((item) => item.isRead !== true).length;
        if (notificationBadge) {
            notificationBadge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            notificationBadge.style.display = unreadCount > 0 ? 'inline-flex' : 'none';
        }
        if (notificationCountText) {
            notificationCountText.textContent = unreadCount + ' new';
        }

        // Only blink for a genuinely NEW arrival (count went up), and only
        // while this tab isn't the one being looked at — otherwise every
        // render (e.g. after marking something read) would restart it.
        if (previousUnreadCount !== null && unreadCount > previousUnreadCount && document.hidden) {
            startTitleBlink();
        }
        if (unreadCount === 0) {
            stopTitleBlink();
        }
        previousUnreadCount = unreadCount;
    }

    function maybeShowLoginPopup() {
        if (!window.Swal) return;

        // Anything unread that hasn't been popped up yet this session — not
        // just the first batch at load, so a genuinely new arrival (via the
        // real-time listener below) gets its own popup immediately instead
        // of silently landing only in the passive bell badge.
        const items = currentNotifications.filter((item) => item.isRead !== true && !shownPopupIds.has(item.id));
        if (items.length === 0) return;

        items.forEach((item) => shownPopupIds.add(item.id));
        try { sessionStorage.setItem(popupStorageKey, JSON.stringify([...shownPopupIds])); } catch (e) { /* ignore */ }

        const listHtml = items.slice(0, 5).map((item) => `
            <div class="travelix-popup-notif-item" data-id="${escapeHtml(item.id)}" data-link="${escapeHtml(item.link || '')}"
                 style="text-align:left;border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;margin-bottom:8px;${item.link ? 'cursor:pointer;' : ''}">
                <div style="font-weight:600;color:#111827;">${escapeHtml(item.title || 'Notification')}</div>
                <div style="font-size:13px;color:#4b5563;margin-top:2px;">${escapeHtml(item.message || '')}</div>
                ${item.link ? '<div style="font-size:11.5px;color:#1484B4;font-weight:700;margin-top:4px;">Tap to open →</div>' : ''}
            </div>
        `).join('');

        // Exactly one actionable notification — skip the extra "View All"
        // click and go straight to the page it's about (e.g. the booking a
        // hotel needs to confirm), instead of making them open the panel
        // first and click again.
        const singleLink = items.length === 1 ? (items[0].link || '') : '';

        Swal.fire({
            icon: 'info',
            title: items.length === 1 ? 'New Notification' : `${items.length} New Notifications`,
            html: `<div style="max-height:280px;overflow-y:auto;">${listHtml}</div>`,
            confirmButtonText: singleLink ? 'Open' : 'View All',
            confirmButtonColor: '#1484B4',
            showCancelButton: true,
            cancelButtonText: 'Dismiss',
            didOpen: (popup) => {
                popup.querySelectorAll('.travelix-popup-notif-item[data-link]').forEach((el) => {
                    const link = el.dataset.link;
                    if (!link) return;
                    el.addEventListener('click', async () => {
                        const id = el.dataset.id;
                        try { await db.collection('notifications').doc(id).update({ isRead: true, readAt: firebase.firestore.FieldValue.serverTimestamp() }); } catch (e) { /* non-blocking */ }
                        window.location.href = link;
                    });
                });
            },
            // Deferred a tick, not run synchronously — the click that
            // confirms this SweetAlert also bubbles up to the document-level
            // "click outside closes the panel" listener below, which would
            // otherwise undo this in the very same event before the panel
            // ever became visible.
            preConfirm: () => {
                if (singleLink) {
                    window.location.href = singleLink;
                    return;
                }
                if (manageUrl) {
                    window.location.href = manageUrl;
                    return;
                }
                setTimeout(() => notificationPanel?.classList.add('show'), 0);
            }
        });
    }

    function renderNotifications() {
        sortNotifications();
        updateNotificationCount();
        maybeShowLoginPopup();
        if (!notificationList) return;

        if (currentNotifications.length === 0) {
            notificationList.innerHTML = '<div class="travelix-notification-empty">No notifications yet.</div>';
            return;
        }

        notificationList.innerHTML = currentNotifications.map((item) => {
            const isUnread = item.isRead !== true;
            const icon = item.icon || getIcon(item.type);

            return `
                <div class="travelix-notification-item ${isUnread ? 'unread' : ''}" data-id="${escapeHtml(item.id)}">
                    <div class="travelix-notification-icon">
                        <i class="${escapeHtml(icon)}"></i>
                    </div>
                    <div>
                        <div class="travelix-notification-title">${escapeHtml(item.title || 'Notification')}</div>
                        <div class="travelix-notification-message">${escapeHtml(item.message || '')}</div>
                        <div class="travelix-notification-time">${formatTime(item.createdAt)}</div>
                        <div class="travelix-notification-actions">
                            ${isUnread ? `
                                <button type="button" class="travelix-notification-action-btn travelix-mark-read-btn" data-action="read" data-id="${escapeHtml(item.id)}">Read</button>
                            ` : ''}
                            <button type="button" class="travelix-notification-action-btn travelix-delete-notification-btn" data-action="delete" data-id="${escapeHtml(item.id)}">Delete</button>
                        </div>
                    </div>
                    ${item.link ? '<i class="fa-solid fa-chevron-right travelix-notification-item-chevron"></i>' : ''}
                </div>
            `;
        }).join('');
    }

    function buildQuery() {
        let q = db.collection('notifications');
        filters.forEach(([field, op, value]) => {
            q = q.where(field, op, value);
        });
        return q;
    }

    async function refreshNotifications(showToast) {
        try {
            refreshNotificationsBtn?.classList.add('loading');
            const snap = await buildQuery().get();
            currentNotifications = [];
            snap.forEach((docSnap) => currentNotifications.push({ id: docSnap.id, ...docSnap.data() }));
            renderNotifications();

            if (showToast && window.Swal) {
                Swal.fire({ icon: 'success', title: 'Notifications refreshed', timer: 900, showConfirmButton: false });
            }
        } catch (error) {
            if (window.Swal) {
                Swal.fire({ icon: 'error', title: 'Refresh Failed', text: error.message, confirmButtonColor: '#1484B4' });
            }
        } finally {
            refreshNotificationsBtn?.classList.remove('loading');
        }
    }

    function listenForNotifications() {
        if (unsubscribe) unsubscribe();
        unsubscribe = buildQuery().onSnapshot(
            (snap) => {
                currentNotifications = [];
                snap.forEach((docSnap) => currentNotifications.push({ id: docSnap.id, ...docSnap.data() }));
                renderNotifications();
            },
            (error) => {
                console.warn('Notification load error:', error);
                currentNotifications = [];
                updateNotificationCount();
                if (notificationList) {
                    notificationList.innerHTML = '<div class="travelix-notification-empty">No notifications yet.</div>';
                }
            }
        );
    }

    notificationToggle.addEventListener('click', async function (e) {
        e.stopPropagation();
        notificationPanel?.classList.toggle('show');
        if (notificationPanel?.classList.contains('show')) {
            await refreshNotifications(false);
        }
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('.swal2-container')) return;
        if (notificationWrapper && !notificationWrapper.contains(e.target)) {
            notificationPanel?.classList.remove('show');
        }
    });

    notificationList?.addEventListener('click', async function (e) {
        const button = e.target.closest('button[data-action]');
        if (button) {
            const id = button.dataset.id;
            const action = button.dataset.action;
            if (!id) return;

            try {
                if (action === 'read') {
                    currentNotifications = currentNotifications.map((item) => item.id === id ? { ...item, isRead: true } : item);
                    renderNotifications();
                    await db.collection('notifications').doc(id).update({ isRead: true, readAt: firebase.firestore.FieldValue.serverTimestamp() });
                }

                if (action === 'delete') {
                    const result = window.Swal ? await Swal.fire({
                        icon: 'warning', title: 'Delete notification?', text: 'This notification will be removed.',
                        showCancelButton: true, confirmButtonText: 'Yes, delete', cancelButtonText: 'Cancel', confirmButtonColor: '#dc2626'
                    }) : { isConfirmed: true };
                    if (!result.isConfirmed) return;

                    currentNotifications = currentNotifications.filter((item) => item.id !== id);
                    renderNotifications();
                    await db.collection('notifications').doc(id).delete();
                }
            } catch (error) {
                console.error('[travelix-notif] action failed:', error);
                await refreshNotifications(false);
                if (window.Swal) {
                    Swal.fire({ icon: 'error', title: 'Action Failed', text: error.message, confirmButtonColor: '#1484B4' });
                }
            }
            return;
        }

        // Click anywhere else on an item (not its buttons) marks it read and
        // jumps straight to the linked page — e.g. a refund notification
        // opens the exact refund/booking list instead of a generic page.
        const itemEl = e.target.closest('.travelix-notification-item');
        if (!itemEl) return;

        const id = itemEl.dataset.id;
        const item = currentNotifications.find((n) => n.id === id);
        if (!item) return;

        if (item.isRead !== true) {
            try {
                await db.collection('notifications').doc(id).update({ isRead: true, readAt: firebase.firestore.FieldValue.serverTimestamp() });
            } catch (error) { /* non-blocking */ }
        }

        if (item.link) {
            window.location.href = item.link;
        }
    });

    readAllBtn?.addEventListener('click', async function () {
        const unread = currentNotifications.filter((item) => item.isRead !== true);
        if (unread.length === 0) {
            if (window.Swal) Swal.fire({ icon: 'info', title: 'No unread notifications', confirmButtonColor: '#1484B4' });
            return;
        }

        try {
            currentNotifications = currentNotifications.map((item) => ({ ...item, isRead: true }));
            renderNotifications();

            const batch = db.batch();
            unread.forEach((item) => {
                batch.update(db.collection('notifications').doc(item.id), { isRead: true, readAt: firebase.firestore.FieldValue.serverTimestamp() });
            });
            await batch.commit();
        } catch (error) {
            await refreshNotifications(false);
            if (window.Swal) Swal.fire({ icon: 'error', title: 'Failed', text: error.message, confirmButtonColor: '#1484B4' });
        }
    });

    refreshNotificationsBtn?.addEventListener('click', async function (e) {
        e.stopPropagation();
        await refreshNotifications(true);
    });

    listenForNotifications();
    refreshNotifications(false);
};
