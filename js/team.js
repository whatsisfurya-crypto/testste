document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.team-card').forEach((card, index) => {
        card.style.opacity = '0';
        card.style.animation = `fadeInUp 0.5s ease-out ${index * 0.2}s forwards`;
    });
});