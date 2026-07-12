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
