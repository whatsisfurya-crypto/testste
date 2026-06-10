document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.tab');
    const forms = document.querySelectorAll('.auth-form');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabName = this.dataset.tab;
            
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            forms.forEach(form => {
                form.style.display = 'none';
                if (form.id === tabName + 'Form') {
                    form.style.display = 'block';
                }
            });
        });
    });
    
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch('php/auth.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                window.location.href = data.redirect;
            } else {
                alert(data.message);
            }
        } catch(error) {
            alert('Ошибка соединения с сервером');
        }
    });
    
    document.getElementById('registerForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch('php/auth.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                window.location.href = data.redirect;
            } else {
                alert(data.message);
            }
        } catch(error) {
            alert('Ошибка соединения с сервером');
        }
    });
});