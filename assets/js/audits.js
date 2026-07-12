const API_BASE = '/Enterprise%20Asset%20&%20Resource%20Management%20System/backend/index.php';
let activeCycle = null;

document.addEventListener('DOMContentLoaded', () => {
    loadDepartments();
    loadActiveAudit();
});

function openCreateAuditModal() {
    const modal = document.getElementById('create-audit-modal');
    modal.classList.remove('hidden');
    // slight delay to allow display:block before adding scale class for animation
    setTimeout(() => {
        modal.firstElementChild.classList.remove('scale-95');
        modal.firstElementChild.classList.add('scale-100');
    }, 10);
}

function closeCreateAuditModal() {
    const modal = document.getElementById('create-audit-modal');
    modal.firstElementChild.classList.remove('scale-100');
    modal.firstElementChild.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 200);
}

async function loadDepartments() {
    try {
        // Simple fetch of departments to populate scope dropdown
        const r = await fetch(API_BASE + '/api/departments'); // assuming this exists, if not we ignore or use static
        if (r.ok) {
            const res = await r.json();
            const select = document.getElementById('audit-department');
            if (res.success && res.data) {
                res.data.forEach(d => {
                    select.innerHTML += `<option value="${d.id}">${d.name}</option>`;
                });
            }
        }
    } catch (e) { console.log('Could not load departments', e); }
}

async function submitAuditCycle() {
    const name = document.getElementById('audit-name').value;
    const deptId = document.getElementById('audit-department').value;
    
    try {
        const r = await fetch(API_BASE + '/api/audits', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: name, department_id: deptId || null })
        });
        const res = await r.json();
        
        if (res.success) {
            closeCreateAuditModal();
            loadActiveAudit();
        } else {
            alert(res.error || 'Failed to create audit cycle');
        }
    } catch(err) {
        console.error(err);
    }
}

async function loadActiveAudit() {
    try {
        const r = await fetch(API_BASE + '/api/audits/active');
        const res = await r.json();
        
        const header = document.getElementById('active-cycle-header');
        const tbody = document.getElementById('audit-items-tbody');
        const closeBtn = document.getElementById('close-audit-btn');
        
        if (!res.data || !res.data.cycle) {
            activeCycle = null;
            header.innerHTML = `
                <h2 class="font-headline-lg text-headline-lg text-on-surface mb-space-xs">No Active Audit Cycle</h2>
                <p class="text-on-surface-variant font-body-sm text-body-sm">Start a new audit cycle to begin verifying assets.</p>
            `;
            tbody.innerHTML = `<tr><td colspan="5" class="py-8 text-center text-on-surface-variant">No active audit items.</td></tr>`;
            updateProgressCounters({total:0, verified:0, missing:0, damaged:0, pending:0});
            closeBtn.classList.add('hidden');
            return;
        }

        activeCycle = res.data.cycle;
        
        header.innerHTML = `
            <h2 class="font-headline-lg text-headline-lg text-on-surface mb-space-xs">${activeCycle.name}</h2>
            <div class="flex items-center gap-space-md text-on-surface-variant font-body-sm text-body-sm">
                <span>Cycle ID: #AUD-${activeCycle.id}</span>
                <span class="w-1 h-1 bg-outline-variant rounded-full"></span>
                <span>Status: ${activeCycle.status}</span>
            </div>
        `;
        
        closeBtn.classList.remove('hidden');

        // Render Items
        tbody.innerHTML = '';
        res.data.items.forEach(item => {
            let rowClass = "hover:bg-surface-container-lowest transition-colors group";
            let actionOpacity = "opacity-0 group-hover:opacity-100";
            let statusBadge = "";
            let actionUI = "";

            if (item.result === 'Missing') {
                rowClass = "bg-error-container/20 hover:bg-error-container/30 transition-colors border-l-2 border-l-error";
                actionOpacity = "opacity-100";
                statusBadge = `
                    <span class="inline-flex items-center gap-1 text-error font-label-sm text-label-sm mr-2">
                        <span class="material-symbols-outlined text-[14px]">warning</span> Missing Flagged
                    </span>
                    <button onclick="updateAuditItem(${item.id}, 'Pending')" class="text-on-surface-variant hover:text-on-surface underline text-label-sm font-label-sm">Undo</button>
                `;
            } else if (item.result === 'Damaged') {
                rowClass = "bg-[#f57f17]/10 hover:bg-[#f57f17]/20 transition-colors border-l-2 border-l-[#f57f17]";
                actionOpacity = "opacity-100";
                statusBadge = `
                    <span class="inline-flex items-center gap-1 text-[#f57f17] font-label-sm text-label-sm mr-2">
                        <span class="material-symbols-outlined text-[14px]">broken_image</span> Damaged Flagged
                    </span>
                    <button onclick="updateAuditItem(${item.id}, 'Pending')" class="text-on-surface-variant hover:text-on-surface underline text-label-sm font-label-sm">Undo</button>
                `;
            } else if (item.result === 'Verified') {
                rowClass = "bg-[#1b5e20]/10 hover:bg-[#1b5e20]/20 transition-colors border-l-2 border-l-[#1b5e20]";
                actionOpacity = "opacity-100";
                statusBadge = `
                    <span class="inline-flex items-center gap-1 text-[#1b5e20] font-label-sm text-label-sm mr-2">
                        <span class="material-symbols-outlined text-[14px]">check_circle</span> Verified
                    </span>
                    <button onclick="updateAuditItem(${item.id}, 'Pending')" class="text-on-surface-variant hover:text-on-surface underline text-label-sm font-label-sm">Undo</button>
                `;
            } else {
                // Pending Action Buttons
                statusBadge = `
                    <div class="flex justify-end gap-2 ${actionOpacity} transition-opacity">
                        <button onclick="updateAuditItem(${item.id}, 'Verified')" class="w-8 h-8 rounded bg-surface border border-outline-variant flex items-center justify-center text-on-surface hover:border-[#1b5e20] hover:text-[#1b5e20] transition-colors" title="Mark Verified">
                            <span class="material-symbols-outlined text-[18px]">check</span>
                        </button>
                        <button onclick="updateAuditItem(${item.id}, 'Missing')" class="w-8 h-8 rounded bg-surface border border-outline-variant flex items-center justify-center text-on-surface hover:border-error hover:text-error transition-colors" title="Mark Missing">
                            <span class="material-symbols-outlined text-[18px]">visibility_off</span>
                        </button>
                        <button onclick="updateAuditItem(${item.id}, 'Damaged')" class="w-8 h-8 rounded bg-surface border border-outline-variant flex items-center justify-center text-on-surface hover:border-[#f57f17] hover:text-[#f57f17] transition-colors" title="Mark Damaged">
                            <span class="material-symbols-outlined text-[18px]">broken_image</span>
                        </button>
                    </div>
                `;
            }

            tbody.innerHTML += `
                <tr class="${rowClass}">
                    <td class="py-3 px-space-md">${item.asset_tag}</td>
                    <td class="py-3 px-space-md font-body-sm text-body-sm truncate max-w-[200px]">${item.asset_name}</td>
                    <td class="py-3 px-space-md font-body-sm text-body-sm truncate max-w-[150px]">${item.location || '-'}</td>
                    <td class="py-3 px-space-md">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-surface-container-highest text-on-surface text-[11px] font-medium border border-outline-variant/30">
                            <div class="w-1.5 h-1.5 rounded-full bg-secondary"></div> ${item.lifecycle_status}
                        </span>
                    </td>
                    <td class="py-3 px-space-md text-right">
                        ${statusBadge}
                    </td>
                </tr>
            `;
        });

        updateProgressCounters(res.data.counts);

    } catch(err) { console.error(err); }
}

function updateProgressCounters(counts) {
    document.getElementById('count-verified').textContent = counts.verified;
    document.getElementById('count-missing').textContent = counts.missing;
    document.getElementById('count-damaged').textContent = counts.damaged;

    const total = counts.total;
    const completed = counts.verified + counts.missing + counts.damaged;
    const pct = total === 0 ? 0 : Math.round((completed / total) * 100);

    document.getElementById('progress-verified').textContent = completed;
    document.getElementById('progress-total').textContent = `/ ${total} Expected Assets`;
    document.getElementById('progress-bar').style.width = `${pct}%`;
    document.getElementById('progress-pct').textContent = `${pct}% Complete`;
}

async function updateAuditItem(itemId, result) {
    try {
        const r = await fetch(API_BASE + '/api/audits/items', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: itemId, result: result })
        });
        const res = await r.json();
        
        if (res.success) {
            // Re-fetch to update the UI safely
            loadActiveAudit();
        } else {
            alert(res.error || 'Failed to update item');
        }
    } catch(err) {
        console.error(err);
    }
}

async function closeAudit() {
    if (!activeCycle) return;
    
    // Check if there are pending items
    const countVerified = parseInt(document.getElementById('count-verified').textContent);
    const countMissing = parseInt(document.getElementById('count-missing').textContent);
    const countDamaged = parseInt(document.getElementById('count-damaged').textContent);
    const completed = countVerified + countMissing + countDamaged;
    
    const totalText = document.getElementById('progress-total').textContent; // "/ X Expected"
    const totalMatch = totalText.match(/\d+/);
    const total = totalMatch ? parseInt(totalMatch[0]) : 0;
    
    if (completed < total) {
        if (!confirm(`You have ${total - completed} pending items that haven't been audited. Are you sure you want to close this audit cycle?`)) {
            return;
        }
    } else {
        if (!confirm("Are you sure you want to close this audit cycle? The system will automatically update the statuses of any Missing or Damaged assets.")) {
            return;
        }
    }

    try {
        const r = await fetch(API_BASE + '/api/audits/close', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cycle_id: activeCycle.id })
        });
        const res = await r.json();
        
        if (res.success) {
            alert(res.data.message);
            loadActiveAudit();
        } else {
            alert(res.error || 'Failed to close cycle');
        }
    } catch(err) {
        console.error(err);
    }
}
