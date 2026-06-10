document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('formModal');
    const closeBtn = document.querySelector('.close-modal');
    
    document.querySelectorAll('.form-type-card').forEach(card => {
        card.addEventListener('click', function() {
            document.getElementById('formType').value = this.dataset.type;
            modal.classList.add('active');
        });
    });
    
    if (closeBtn) {
        closeBtn.addEventListener('click', () => modal.classList.remove('active'));
    }
    
    window.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.remove('active');
    });
});