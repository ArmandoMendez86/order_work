document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const errorMessage = document.getElementById('errorMessage');

    loginForm.addEventListener('submit', (e) => {
        e.preventDefault(); // Evita que el formulario se envíe de forma tradicional

        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;

        // Oculta errores anteriores
        errorMessage.classList.add('d-none');

        // Prepara los datos para enviar al backend
        const formData = new URLSearchParams();
        formData.append('email', email);
        formData.append('password', password);

        // Llama a la API de PHP
        fetch('./api/login', { // Llama al endpoint que crearemos
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // ¡Éxito! Redirigir según el rol
                if (data.role === 'admin') {
                    window.location.href = 'admin-dashboard.html';
                } else if (data.role === 'technician') {
                    window.location.href = 'tech-dashboard.html';
                } else {
                    // Rol no reconocido (por si acaso)
                    showError('Rol de usuario no válido.');
                }
            } else {
                // Muestra el mensaje de error del backend
                showError(data.message);
            }
        })
        .catch(error => {
            console.error('Error en la solicitud:', error);
            showError('No se pudo conectar al servidor. Inténtalo de nuevo.');
        });
    });

    function showError(message) {
        errorMessage.textContent = message;
        errorMessage.classList.remove('d-none');
    }
});