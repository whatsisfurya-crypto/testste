document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.buy-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const productName = this.closest('.product-card').querySelector('h3').textContent;
            alert('Товар "' + productName + '" добавлен в корзину! (демо)');
        });
    });
});