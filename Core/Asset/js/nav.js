// Simple navigation: show/hide sections by ID
function navigateTo(sectionId) {
    // Hide all sections
    document.querySelectorAll('.view-section').forEach(section => {
        section.classList.remove('active');
    });
    // Show selected section
    const activeSection = document.getElementById(sectionId);
    if (activeSection) activeSection.classList.add('active');
    
    // Update active class on sidebar links
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        if (item.getAttribute('onclick')?.includes(sectionId)) {
            item.classList.add('active');
        }
    });
}

// Make navigateTo globally available
window.navigateTo = navigateTo;

// Handle sidebar clicks
document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', (e) => {
        const onclickAttr = item.getAttribute('onclick');
        if (onclickAttr) {
            // Extract section id from onclick string (e.g., "switchTab('productmaster')")
            const match = onclickAttr.match(/'(.*?)'/);
            if (match && match[1]) {
                navigateTo(match[1]);
            }
        }
    });
});

// Show product master by default (or whatever is active)
document.addEventListener('DOMContentLoaded', () => {
    // Check which section has 'active' class already, else show productmaster
    const activeAlready = document.querySelector('.view-section.active');
    if (!activeAlready) {
        navigateTo('productmaster');
    }
});