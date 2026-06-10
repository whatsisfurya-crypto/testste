document.addEventListener('DOMContentLoaded', function() {
    // Поиск по администраторам
    const searchInput = document.getElementById('searchAdmin');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('.admin-card').forEach(card => {
                const name = card.querySelector('h3').textContent.toLowerCase();
                card.style.display = name.includes(searchTerm) ? 'block' : 'none';
            });
        });
    }
    
    // Анимация карточек
    document.querySelectorAll('.admin-card').forEach((card, index) => {
        card.style.opacity = '0';
        card.style.animation = `fadeInUp 0.5s ease-out ${index * 0.1}s forwards`;
    });
});