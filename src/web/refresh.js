// Refresh functions for MagellanWars
function refresh_init() {
    // Initialize refresh functionality
    console.log("MagellanWars initialized");
}

function refresh_page() {
    location.reload();
}

// Placeholder for missing urchinTracker
if (typeof urchinTracker === 'undefined') {
    window.urchinTracker = function() {
        // Google Analytics legacy tracker - disabled
    };
}