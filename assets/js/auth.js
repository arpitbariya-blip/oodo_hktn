// assets/js/auth.js
// Handles authentication checks and UI updates across all protected pages.

document.addEventListener('DOMContentLoaded', function() {
    const basePath = '/Enterprise%20Asset%20&%20Resource%20Management%20System/backend/index.php';
    const currentPath = window.location.pathname;

    // Check session
    fetch(basePath + '/api/auth/me')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                // User is authenticated
                const user = data.data;
                
                // Update UI Profile in Sidebar (if it exists on this page)
                const profileName = document.getElementById('sidebar-profile-name');
                const profileRole = document.getElementById('sidebar-profile-role');
                
                if (profileName) profileName.textContent = user.name;
                if (profileRole) profileRole.textContent = user.role;

                // --- RBAC: Access Control Matrix ---
                const accessMatrix = {
                    'dashboard.html': ['Employee', 'Asset Manager', 'Department Head', 'Admin'],
                    'assets.html': ['Employee', 'Asset Manager', 'Department Head', 'Admin'],
                    'allocation.html': ['Employee', 'Asset Manager', 'Department Head', 'Admin'],
                    'bookings.html': ['Employee', 'Asset Manager', 'Department Head', 'Admin'],
                    'maintenance.html': ['Asset Manager', 'Admin'],
                    'audits.html': ['Asset Manager', 'Admin'],
                    'reports.html': ['Department Head', 'Asset Manager', 'Admin'],
                    'logs.html': ['Admin'],
                    'org-setup.html': ['Admin']
                };

                // Get current page filename
                let pageName = currentPath.substring(currentPath.lastIndexOf('/') + 1);
                if (!pageName || pageName === '') pageName = 'index.html'; // Or whatever root maps to

                // 1. Guard Page Access
                // Only enforce if the page is in our matrix
                if (accessMatrix[pageName]) {
                    const allowedRoles = accessMatrix[pageName];
                    if (!allowedRoles.includes(user.role)) {
                        alert("You do not have permission to access this page.");
                        window.location.href = 'dashboard.html';
                        return; // Stop further rendering
                    }
                }

                // 2. Hide restricted Sidebar Links dynamically
                const sidebarLinks = document.querySelectorAll('nav a');
                sidebarLinks.forEach(link => {
                    const href = link.getAttribute('href');
                    if (href && accessMatrix[href]) {
                        if (!accessMatrix[href].includes(user.role)) {
                            link.style.display = 'none'; // Completely hide the link
                        }
                    }
                });

                // Show the page content (hiding trick prevents flash)
                document.body.style.visibility = 'visible';
            } else {
                // Not authenticated
                window.location.href = 'login.html';
            }
        })
        .catch(err => {
            console.error("Auth check failed:", err);
            window.location.href = 'login.html';
        });

    // Handle Logout
    const logoutBtn = document.getElementById('sidebar-logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            fetch(basePath + '/api/auth/logout', { method: 'POST' })
                .then(() => {
                    window.location.href = 'login.html';
                });
        });
    }
});
