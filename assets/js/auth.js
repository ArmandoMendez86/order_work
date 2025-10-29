document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const errorMessage = document.getElementById('errorMessage');

    function showError(message) {
        errorMessage.textContent = message;
        errorMessage.classList.remove('d-none');
    }

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault(); // Evita que el formulario se envíe de forma tradicional

        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;

        // Oculta errores anteriores
        errorMessage.classList.add('d-none');

        // --- MODIFICACIÓN CLAVE: Preparar datos como JSON ---
        const loginData = {
            email: email,
            password: password
        };
        // --- FIN MODIFICACIÓN ---

        try {
            // Llama a la API de PHP
            const response = await fetch('api/login', {
                method: 'POST',
                // CRÍTICO: Informar al servidor que enviamos JSON
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                // CRÍTICO: Enviar el objeto de datos como una cadena JSON
                body: JSON.stringify(loginData) 
            });

            // Usamos .json() para capturar la respuesta
            const data = await response.json();
            
            if (data.success) {
                // Éxito! Redirigir según el rol
                if (data.role === 'admin') {
                    window.location.href = 'admin-dashboard.html';
                } else if (data.role === 'technician') {
                    window.location.href = 'tech-dashboard.html';
                } else {
                    showError('User role is invalid.');
                }
            } else {
                // Muestra el mensaje de error del backend
                showError(data.message);
            }
        } catch (error) {
            console.error('Error en la solicitud:', error);
            showError('No se pudo conectar al servidor. Inténtalo de nuevo.');
        }
    });

    function showError(message) {
        errorMessage.textContent = message;
        errorMessage.classList.remove('d-none');
    }
});