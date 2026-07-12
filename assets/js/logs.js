const API_BASE = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1) + 'backend/index.php';

document.addEventListener('DOMContentLoaded', async () => {
    // 1. Seed dummy data if needed (for demonstration)
    try {
        await fetch(API_BASE + '/api/logs/seed', { method: 'POST' });
    } catch (e) {}

    // 2. Load Real Data
    loadLogs();
    loadNotifications();
});

async function loadLogs() {
    const filter = document.getElementById('log-module-filter').value;
    const tbody = document.getElementById('activity-logs-tbody');
    
    try {
        const r = await fetch(API_BASE + `/api/logs?module=${encodeURIComponent(filter)}`);
        const res = await r.json();
        
        if (res.success && res.data && res.data.logs && res.data.logs.length > 0) {
            tbody.innerHTML = '';
            res.data.logs.forEach(log => {
                let badgeClass = 'bg-surface-container-high text-on-surface-variant'; // default
                
                if (log.module === 'Allocations') badgeClass = 'bg-secondary-container/20 text-secondary-container';
                if (log.module === 'Maintenance') badgeClass = 'bg-error/10 text-error';
                if (log.module === 'Bookings') badgeClass = 'bg-tertiary-fixed-dim/30 text-tertiary-container';
                if (log.module === 'Audit') badgeClass = 'bg-primary-fixed text-on-primary-fixed';

                tbody.innerHTML += `
                    <tr class="hover:bg-surface-container-low transition-colors">
                        <td class="px-space-md py-3 font-mono-md text-on-surface-variant">${log.created_at}</td>
                        <td class="px-space-md py-3 font-medium">${log.user_name || 'System'}</td>
                        <td class="px-space-md py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full ${badgeClass} font-label-sm">${log.module}</span>
                        </td>
                        <td class="px-space-md py-3">${log.action}</td>
                        <td class="px-space-md py-3 text-right">
                            <button class="text-on-surface-variant hover:text-primary"><span class="material-symbols-outlined text-sm">open_in_new</span></button>
                        </td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="5" class="px-space-md py-8 text-center text-on-surface-variant">No activity logs found for this filter.</td></tr>`;
        }
    } catch (e) {
        console.error("Failed to load logs", e);
    }
}

async function loadNotifications() {
    const feed = document.getElementById('notifications-feed');
    try {
        const r = await fetch(API_BASE + '/api/notifications');
        const res = await r.json();
        
        if (res.success && res.data && res.data.notifications && res.data.notifications.length > 0) {
            feed.innerHTML = '';
            res.data.notifications.forEach(n => {
                const unreadClass = n.is_read == 0 ? 'border-error/20 bg-error/5' : 'border-outline-variant/30 bg-surface-container-lowest';
                const dot = n.is_read == 0 ? `<div class="absolute top-4 right-3 w-2 h-2 rounded-full bg-error"></div>` : '';
                
                let iconColor = 'text-outline';
                if (n.type === 'warning' || n.type === 'error') iconColor = 'text-error';
                else if (n.type === 'assignment_returned' || n.type === 'assignment_turned_in') iconColor = 'text-secondary-container';
                else if (n.type === 'event') iconColor = 'text-tertiary-container';
                else if (n.type === 'fact_check') iconColor = 'text-primary';

                // We split message at colon for title/body purely for demo presentation since we packed it into `message`
                let parts = n.message.split(': ');
                let title = parts[0];
                let body = parts.length > 1 ? parts.slice(1).join(': ') : '';

                feed.innerHTML += `
                    <div class="p-space-md border ${unreadClass} rounded-DEFAULT relative pl-10 cursor-pointer hover:bg-surface-container-low transition-colors block no-underline mb-2">
                        <div class="absolute left-3 top-4 ${iconColor}">
                            <span class="material-symbols-outlined" style="${n.is_read == 0 ? "font-variation-settings: 'FILL' 1;" : ""}">${n.type}</span>
                        </div>
                        ${dot}
                        <h4 class="font-label-md text-label-md text-on-surface">${title}</h4>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">${body}</p>
                        <span class="font-mono-md text-xs text-on-surface-variant/70 mt-2 block">${n.created_at}</span>
                    </div>
                `;
            });
        } else {
            feed.innerHTML = `<div class="p-4 text-center text-on-surface-variant text-sm">No recent alerts.</div>`;
        }
    } catch (e) {
        console.error("Failed to load notifications", e);
    }
}

async function markAllRead() {
    try {
        await fetch(API_BASE + '/api/notifications/mark-read', { method: 'POST' });
        loadNotifications();
    } catch (e) {
        console.error("Failed to mark read", e);
    }
}

async function clearAlerts() {
    try {
        await fetch(API_BASE + '/api/notifications/clear', { method: 'POST' });
        loadNotifications();
    } catch (e) {
        console.error("Failed to clear alerts", e);
    }
}
