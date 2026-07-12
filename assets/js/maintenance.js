const API_BASE = '/Enterprise%20Asset%20&%20Resource%20Management%20System/backend/index.php';

document.addEventListener('DOMContentLoaded', () => {
    loadAssets();
    loadMaintenanceData();
});

async function loadAssets() {
    try {
        const r = await fetch(API_BASE + '/api/assets');
        const res = await r.json();
        
        if (res.success) {
            const select = document.getElementById('maint-asset');
            select.innerHTML = '<option value="">Select an asset...</option>';
            res.data.forEach(a => {
                select.innerHTML += `<option value="${a.id}">${a.asset_tag} - ${a.name}</option>`;
            });
        }
    } catch(err) { console.error(err); }
}

async function loadMaintenanceData() {
    try {
        const r = await fetch(API_BASE + '/api/maintenance');
        const res = await r.json();
        
        if (res.success) {
            const active = res.data.filter(m => !['Resolved', 'Rejected'].includes(m.status));
            const history = res.data.filter(m => ['Resolved', 'Rejected'].includes(m.status));
            
            renderActiveRequests(active);
            renderHistory(history);
        }
    } catch(err) { console.error(err); }
}

function renderActiveRequests(active) {
    const container = document.getElementById('active-requests-wrapper');
    container.innerHTML = '';
    
    if (active.length === 0) {
        container.innerHTML = '<div class="text-center p-8 text-on-surface-variant">No active maintenance requests.</div>';
        return;
    }
    
    active.forEach(req => {
        // Build Stepper HTML
        const steps = ['Pending', 'Approved', 'Technician Assigned', 'In Progress', 'Resolved'];
        const currentIdx = steps.indexOf(req.status) !== -1 ? steps.indexOf(req.status) : 0;
        
        let stepperHTML = `
            <div class="mt-auto pt-space-lg">
                <div class="flex items-center justify-between relative">
                    <div class="absolute top-1/2 left-0 w-full h-0.5 bg-outline-variant -z-10 -translate-y-1/2"></div>
                    <div class="absolute top-1/2 left-0 h-0.5 bg-secondary -z-10 -translate-y-1/2" style="width: ${(currentIdx / 4) * 100}%"></div>
        `;
        
        steps.forEach((step, idx) => {
            if (idx < currentIdx) {
                // Completed
                stepperHTML += `
                    <div class="flex flex-col items-center gap-2 bg-white px-2">
                        <div class="w-6 h-6 rounded-full bg-secondary text-white flex items-center justify-center">
                            <span class="material-symbols-outlined text-[14px]">check</span>
                        </div>
                        <span class="font-label-sm text-label-sm text-on-surface">${step}</span>
                    </div>
                `;
            } else if (idx === currentIdx) {
                // Current
                stepperHTML += `
                    <div class="flex flex-col items-center gap-2 bg-white px-2">
                        <div class="w-6 h-6 rounded-full border-2 border-secondary bg-white text-secondary flex items-center justify-center cursor-pointer" onclick="advanceWorkflow(${req.id}, '${step}')" title="Click to advance status">
                            <div class="w-2 h-2 rounded-full bg-secondary"></div>
                        </div>
                        <span class="font-label-sm text-label-sm text-primary font-bold">${step}</span>
                    </div>
                `;
            } else {
                // Future
                stepperHTML += `
                    <div class="flex flex-col items-center gap-2 bg-white px-2">
                        <div class="w-6 h-6 rounded-full border-2 border-outline-variant bg-white flex items-center justify-center cursor-pointer" onclick="advanceWorkflow(${req.id}, '${step}')" title="Click to manually advance status here">
                        </div>
                        <span class="font-label-sm text-label-sm text-on-surface-variant">${step}</span>
                    </div>
                `;
            }
        });
        
        stepperHTML += `</div></div>`;

        // priority color
        let pColor = 'text-on-surface';
        if (req.priority === 'High') pColor = 'text-[#FF9800]';
        if (req.priority === 'Critical') pColor = 'text-error';

        container.innerHTML += `
            <div class="mb-8 border-b border-outline-variant pb-8 last:border-0 last:mb-0 last:pb-0">
                <div class="flex justify-between items-center mb-space-lg">
                    <h3 class="font-title-md text-title-md text-on-surface">Request: #${req.id}</h3>
                    <span class="bg-surface-container-highest text-on-surface px-2 py-1 rounded font-label-sm text-label-sm flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-secondary"></span>
                        ${req.status}
                    </span>
                </div>
                
                <div class="flex gap-space-lg mb-space-xl">
                    <div class="w-24 h-24 rounded border border-outline-variant overflow-hidden flex-shrink-0 bg-surface-container-low flex items-center justify-center">
                        <span class="material-symbols-outlined text-4xl text-on-surface-variant">build</span>
                    </div>
                    <div>
                        <a href="assets.html" class="font-title-md text-title-md text-on-surface hover:text-secondary transition-colors">${req.asset_name}</a>
                        <p class="font-mono-md text-mono-md text-on-surface-variant mb-2">Asset Tag: <a href="assets.html" class="hover:text-secondary transition-colors">${req.asset_tag}</a></p>
                        <p class="font-body-sm text-body-sm text-on-surface"><strong class="${pColor}">[${req.priority} Priority]</strong> ${req.issue_description}</p>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mt-2">Raised by ${req.raised_by_name} on ${new Date(req.raised_at).toLocaleDateString()}</p>
                    </div>
                </div>
                ${stepperHTML}
            </div>
        `;
    });
}

function renderHistory(history) {
    const container = document.getElementById('maintenance-history-container');
    container.innerHTML = '';
    
    if (history.length === 0) {
        container.innerHTML = '<div class="text-on-surface-variant text-sm">No resolved maintenance requests found.</div>';
        return;
    }

    history.forEach(req => {
        const dateStr = req.resolved_at ? new Date(req.resolved_at).toLocaleDateString() : new Date(req.raised_at).toLocaleDateString();
        
        let iconColor = req.status === 'Rejected' ? 'border-error' : 'border-secondary';
        let textColor = req.status === 'Rejected' ? 'text-error' : 'text-primary';
        
        container.innerHTML += `
            <div class="relative">
                <div class="absolute w-3 h-3 bg-white border-2 ${iconColor} rounded-full -left-[1.65rem] top-1"></div>
                <div class="flex items-start justify-between">
                    <div>
                        <h4 class="font-label-md text-label-md ${textColor}">${dateStr} - ${req.status}</h4>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">${req.issue_description}</p>
                        <p class="font-body-xs text-on-surface-variant/70 mt-1">Request #${req.id} • Raised by ${req.raised_by_name}</p>
                    </div>
                    <span class="bg-surface-container text-on-surface px-2 py-1 rounded font-label-sm text-label-sm text-xs border border-outline-variant">${req.asset_tag}</span>
                </div>
            </div>
        `;
    });
}

async function submitRequest() {
    const assetId = document.getElementById('maint-asset').value;
    const priority = document.getElementById('maint-priority').value;
    const desc = document.getElementById('maint-desc').value;

    if (!assetId || !desc.trim()) {
        alert("Please select an asset and describe the issue.");
        return;
    }

    const btn = document.getElementById('maint-submit-btn');
    btn.disabled = true;
    btn.textContent = 'Submitting...';

    try {
        const r = await fetch(API_BASE + '/api/maintenance', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                asset_id: assetId,
                priority: priority,
                issue_description: desc
            })
        });
        const res = await r.json();

        if (res.success) {
            document.getElementById('maint-desc').value = '';
            document.getElementById('maint-priority').value = 'Medium';
            document.getElementById('maint-asset').value = '';
            loadMaintenanceData(); // Refresh UI
        } else {
            alert("Error: " + res.error);
        }
    } catch(err) {
        console.error(err);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Submit Request';
    }
}

async function advanceWorkflow(id, clickedStep) {
    // For demo purposes, we'll allow clicking on the step dots to advance status
    if (!confirm(`Are you sure you want to update request #${id} to ${clickedStep}?`)) {
        return;
    }

    try {
        const r = await fetch(API_BASE + '/api/maintenance/status', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                request_id: id,
                status: clickedStep
            })
        });
        const res = await r.json();

        if (res.success) {
            loadMaintenanceData(); // Refresh UI
        } else {
            alert("Error: " + res.error);
        }
    } catch(err) {
        console.error(err);
    }
}
