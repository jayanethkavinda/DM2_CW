// ==========================================================================
// LifeLineConnect - System Admin Dashboard Script (Pure Vanilla JS)
// ==========================================================================

document.addEventListener('DOMContentLoaded', () => {
    // Sidebar active navigation on click
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            navLinks.forEach(l => l.classList.remove('active'));
            this.classList.add('active');
        });
    });
});

function closeAlert() {
    const alertBox = document.getElementById('alertBox');
    if (alertBox) {
        alertBox.style.display = 'none';
    }
}