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

        // --- INICIO MODIFICACIÓN: Enviar datos como JSON ---
        const loginData = {
            email: email,
            password: password
        };
        // --- FIN MODIFICACIÓN ---

        try {
            const response = await fetch('api/login', { // Llama al endpoint de login
                method: 'POST',
                // CRÍTICO: Informar al servidor que enviamos JSON
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                // CRÍTICO: Enviar el objeto de datos como una cadena JSON
                body: JSON.stringify(loginData) 
            });

            // Si el error es 500, la respuesta no es JSON válido (aquí fallará el .json())
            if (!response.ok) {
                // Leer el texto del error 500 para depurar
                const errorText = await response.text();
                console.error("Server Error Response (500):", errorText);
                showError('Error interno del servidor (500). Consulta la consola para depuración.');
                return; 
            }

            const data = await response.json();

            if (data.success) {
                // ¡Éxito! Redirigir según el rol
                if (data.role === 'admin') {
                    window.location.href = 'admin-dashboard.html';
                } else if (data.role === 'technician') {
                    window.location.href = 'tech-dashboard.html';
                } else {
                    showError('User role is invalid.');
                }
            } else {
                // Muestra el mensaje de error del backend (e.g., "Invalid Credentials")
                showError(data.message);
            }
        } catch (error) {
            console.error('Request Error:', error);
            showError('Could not connect to the server. Please try again.');
        }
    });
});