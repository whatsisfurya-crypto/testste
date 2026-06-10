document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('inactivityModal');
    const createBtn = document.getElementById('createInactivityBtn');
    const closeBtn = document.querySelector('.close-modal');
    
    if (createBtn) {
        createBtn.addEventListener('click', () => modal.classList.add('active'));
    }
    
    if (closeBtn) {
        closeBtn.addEventListener('click', () => modal.classList.remove('active'));
    }
    
    window.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.remove('active');
    });
});