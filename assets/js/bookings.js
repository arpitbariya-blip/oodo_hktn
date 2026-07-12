const API_BASE = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1) + 'backend/index.php';
let bookableAssets = [];
let currentBookings = [];
let today = new Date().toISOString().split('T')[0];

document.addEventListener('DOMContentLoaded', () => {
    // Set default date to today
    document.getElementById('booking-date').value = today;
    
    // Load data
    loadBookableResources().then(() => {
        loadCalendar(today);
    });
    loadUpcoming();

    // Scroll calendar to 8 AM
    const calendarBody = document.querySelector('.flex-1.overflow-y-auto');
    if (calendarBody) calendarBody.scrollTop = 450;
});

async function loadBookableResources() {
    try {
        const r = await fetch(API_BASE + '/api/assets/bookable');
        const res = await r.json();
        
        if (res.success) {
            bookableAssets = res.data;
            
            // 1. Populate Dropdown
            const select = document.getElementById('booking-asset');
            select.innerHTML = '<option value="">Select a resource...</option>';
            bookableAssets.forEach(a => {
                select.innerHTML += `<option value="${a.id}">${a.name} (${a.location_name || 'No Location'})</option>`;
            });

            // 2. Populate Calendar Header (Up to 4 columns for day view)
            const header = document.getElementById('calendar-header');
            const bgGrid = document.getElementById('calendar-bg-grid');
            
            // Limit to 4 resources for this specific UI grid layout
            const displayAssets = bookableAssets.slice(0, 4);
            
            let headerHTML = `
                <div class="grid-header p-2 flex items-center justify-center border-r border-outline-variant">
                    <span class="font-mono-md text-mono-md text-on-surface-variant">GMT-4</span>
                </div>
            `;
            let gridHTML = `<div class="border-r border-outline-variant bg-surface h-full"></div>`;
            
            displayAssets.forEach((a, i) => {
                headerHTML += `
                    <div class="grid-header p-space-sm text-center ${i < displayAssets.length - 1 ? 'border-r' : ''} border-outline-variant">
                        <h4 class="font-label-md text-label-md text-on-surface">${a.name}</h4>
                        <p class="font-label-sm text-label-sm text-on-surface-variant mt-1">${a.location_name || '-'}</p>
                    </div>
                `;
                gridHTML += `<div class="border-r border-outline-variant border-dashed h-full"></div>`;
            });
            
            header.innerHTML = headerHTML;
            bgGrid.innerHTML = gridHTML;
        }
    } catch(err) { console.error(err); }
}

async function loadCalendar(dateStr) {
    try {
        const r = await fetch(API_BASE + '/api/bookings/calendar?date=' + dateStr);
        const res = await r.json();
        
        if (res.success) {
            currentBookings = res.data;
            renderCalendarBody();
        }
    } catch (err) { console.error(err); }
}

function renderCalendarBody() {
    const container = document.getElementById('calendar-body');
    
    // 1. Render Time Labels
    let html = `<div class="absolute left-0 w-[80px] h-full flex flex-col font-mono-md text-mono-md text-on-surface-variant text-right pr-2 z-20">`;
    for(let hour = 0; hour < 24; hour++) {
        let top = hour * 60;
        html += `
            <div style="height: 60px; position: absolute; top: ${top}px; width: 100%; border-top: 1px solid theme('colors.outline-variant');">
                <span class="-mt-3 block bg-surface px-1">${hour.toString().padStart(2, '0')}:00</span>
            </div>
        `;
    }
    html += `</div>`;

    // 2. Render Events
    const displayAssets = bookableAssets.slice(0, 4);
    
    currentBookings.forEach(booking => {
        // Find column index (1 to 4)
        const colIdx = displayAssets.findIndex(a => a.id == booking.asset_id);
        if (colIdx === -1) return; // Resource not in the current 4 columns

        // Calculate Top (start_time) and Height (duration)
        const start = new Date(booking.start_time);
        const end = new Date(booking.end_time);
        
        const startMinutes = (start.getHours() * 60) + start.getMinutes();
        const endMinutes = (end.getHours() * 60) + end.getMinutes();
        
        const top = startMinutes; // 1px per minute
        const height = endMinutes - startMinutes;
        const leftPct = colIdx * 25; // 25% per column
        
        // Colors based on status
        let borderColor = 'border-l-primary';
        let badgeClass = 'bg-primary/10 text-primary';
        
        if (booking.status === 'Completed') {
            borderColor = 'border-l-secondary opacity-60';
            badgeClass = 'bg-surface-container text-secondary border border-secondary/20';
        }

        html += `
            <div class="absolute bg-surface-container ${borderColor} border border-outline-variant rounded-r-DEFAULT p-2 shadow-sm overflow-hidden z-10" 
                 style="left: calc(80px + ${leftPct}%); width: calc(25% - 20px); top: ${top}px; height: ${height}px;">
                <div class="flex justify-between items-start mb-1">
                    <span class="px-1.5 py-0.5 ${badgeClass} font-label-sm text-label-sm rounded-sm">${booking.status}</span>
                </div>
                <p class="font-label-md text-label-md text-on-surface truncate">${booking.title || 'Booking'}</p>
                <p class="font-body-sm text-body-sm text-on-surface-variant truncate">${booking.booked_by_name}</p>
            </div>
        `;
    });

    container.innerHTML = html;
}

async function loadUpcoming() {
    try {
        const r = await fetch(API_BASE + '/api/bookings/upcoming');
        const res = await r.json();
        
        if (res.success) {
            const container = document.getElementById('upcoming-bookings-container');
            container.innerHTML = '';
            
            if (res.data.length === 0) {
                container.innerHTML = '<div class="p-4 text-center text-on-surface-variant text-sm">No upcoming bookings.</div>';
                return;
            }
            
            res.data.forEach(b => {
                // Formatting date
                const start = new Date(b.start_time);
                const isToday = start.toDateString() === new Date().toDateString();
                const timeStr = start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                const dateStr = isToday ? `Today, ${timeStr}` : `${start.toLocaleDateString()}, ${timeStr}`;
                
                // Reminders Logic: "Starting Soon" if within 1 hour
                const now = new Date();
                const diffMs = start - now;
                const isSoon = diffMs > 0 && diffMs < 3600000; // < 1 hour
                
                const badge = isSoon 
                    ? `<span class="px-2 py-1 bg-tertiary-container text-on-tertiary-container font-label-sm text-label-sm rounded-DEFAULT flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">timer</span> Soon</span>`
                    : `<span class="px-2 py-1 bg-surface-container text-secondary font-label-sm text-label-sm rounded-DEFAULT border border-secondary/20">${b.status}</span>`;

                container.innerHTML += `
                    <div class="p-space-sm flex justify-between items-center hover:bg-surface-container-lowest/50 transition-colors">
                        <div>
                            <p class="font-label-md text-label-md text-on-surface">${b.asset_name}</p>
                            <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">${dateStr}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            ${badge}
                            <button onclick="cancelBooking(${b.id})" class="text-error hover:text-error/80 transition-colors" title="Cancel Booking">
                                <span class="material-symbols-outlined text-sm">cancel</span>
                            </button>
                        </div>
                    </div>
                `;
            });
        }
    } catch(err) { console.error(err); }
}

function resetBookingForm() {
    document.getElementById('booking-asset').value = '';
    document.getElementById('booking-start').value = '';
    document.getElementById('booking-end').value = '';
    document.getElementById('booking-purpose').value = '';
    document.getElementById('booking-conflict-alert').classList.add('hidden');
}

async function submitBooking() {
    const assetId = document.getElementById('booking-asset').value;
    const date = document.getElementById('booking-date').value;
    const start = document.getElementById('booking-start').value;
    const end = document.getElementById('booking-end').value;
    const purpose = document.getElementById('booking-purpose').value;
    const alertBox = document.getElementById('booking-conflict-alert');
    const alertMsg = document.getElementById('booking-conflict-msg');
    
    alertBox.classList.add('hidden');

    if (!assetId || !date || !start || !end) {
        alert("Please fill out all required fields.");
        return;
    }
    
    // Combine date and time
    const startTime = `${date} ${start}:00`;
    const endTime = `${date} ${end}:00`;

    if (new Date(startTime) >= new Date(endTime)) {
        alert("End time must be after start time.");
        return;
    }

    const btn = document.getElementById('book-btn');
    btn.disabled = true;
    btn.textContent = 'Booking...';

    try {
        const r = await fetch(API_BASE + '/api/bookings', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                asset_id: assetId,
                title: 'Reservation',
                purpose: purpose,
                start_time: startTime,
                end_time: endTime
            })
        });
        const res = await r.json();

        if (res.success) {
            resetBookingForm();
            loadCalendar(date);
            loadUpcoming();
        } else if (res.code === 409) {
            // Overlap conflict
            alertMsg.textContent = `The selected resource is already booked during this time by ${res.conflict_data.user}.`;
            alertBox.classList.remove('hidden');
        } else {
            alert("Error: " + res.error);
        }
    } catch(err) {
        console.error(err);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Book Resource';
    }
}

async function cancelBooking(id) {
    if(!confirm("Are you sure you want to cancel this booking?")) return;

    try {
        const r = await fetch(API_BASE + '/api/bookings/cancel', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ booking_id: id })
        });
        const res = await r.json();
        if (res.success) {
            const date = document.getElementById('booking-date').value;
            loadCalendar(date);
            loadUpcoming();
        } else {
            alert("Error: " + res.error);
        }
    } catch(err) { console.error(err); }
}
