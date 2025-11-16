$(document).ready(function() {

    const loggedInUserNameEl = document.getElementById('loggedInUserName');

    // --- Función Asíncrona para Comprobar la Sesión ---
    async function checkAuthStatus() {
        try {
            const response = await fetch('api/auth/status');

            if (!response.ok) {
                // Si el servidor devuelve 401 o 500, redirigir
                throw new Error('Not authenticated');
            }

            const result = await response.json();

            if (result.success && result.user) {
                const roleDisplay = result.user.role.charAt(0).toUpperCase() + result.user.role.slice(1);
                // CAMBIO DE IDIOMA: Mensaje de bienvenida
                loggedInUserNameEl.textContent = `Welcome, ${result.user.full_name} (${roleDisplay})`;
                
                initDataTables();
                
            } else {
                throw new Error('Invalid session data');
            }
        } catch (error) {
            console.error('Error checking auth status:', error.message);
            // Redirigir si la sesión falla
            window.location.href = 'login.html'; 
        }
    }

    // --- LÓGICA DE INICIALIZACIÓN DE DATATABLES (EXTRAÍDA) ---
    function initDataTables() {
        
        // --- Lógica del Administrador ---
        if ($('#adminOrderTable').length) {
            $('#adminOrderTable').DataTable({
                "ajax": {
                    "url": "api/workorders/all", 
                    "type": "GET",
                    "dataSrc": "data",
                    "error": function(xhr, error, thrown) {
                        if(xhr.status == 401 || xhr.status == 403) {
                            // CAMBIO DE IDIOMA: Alerta de error
                            alert("Session expired or unauthorized. Redirecting to login...");
                            window.location.href = "login.html";
                        }
                    }
                },
                "columns": [
                    { "data": "work_order_number" },
                    { "data": "customer_name" },
                    { "data": "category_name" },
                    { "data": "technician_name" },
                    { "data": "status" },
                    { 
                        "data": "work_order_id",
                        "render": function(data, type, row) {
                            // CAMBIO DE IDIOMA: Texto del botón
                            return `<a href="./index.html?id=${data}" class="btn btn-sm btn-outline-secondary">View/Edit</a>`;
                        },
                        "orderable": false
                    }
                ],
                "responsive": true
            });
        }

        // --- Lógica del Técnico ---
        if ($('#techOrderTable').length) {
            $('#techOrderTable').DataTable({
                "ajax": {
                    "url": "api/workorders/assigned", 
                    "type": "GET",
                    "dataSrc": "data",
                    "error": function(xhr, error, thrown) {
                        if(xhr.status == 401 || xhr.status == 403) {
                            // CAMBIO DE IDIOMA: Alerta de error
                            alert("Session expired. Redirecting to login...");
                            window.location.href = "login.html";
                        }
                    }
                },
                "columns": [
                    { "data": "work_order_number" },
                    { "data": "customer_name" },
                    { "data": "customer_city" },
                    { "data": "category_name" },
                    { "data": "status" },
                    { 
                        "data": "work_order_id",
                        "render": function(data, type, row) {
                            // CAMBIO DE IDIOMA: Texto del botón
                            return `<a href="./index.html?id=${data}" class="btn btn-sm btn-outline-secondary">Update</a>`;
                        },
                        "orderable": false
                    }
                ],
                "responsive": true
            });
        }
    }
    // --- FIN LÓGICA DE INICIALIZACIÓN DE DATATABLES ---


    // --- Lógica de Logout (común para ambos) ---
    $('#logoutButton').on('click', function() {
        // CAMBIO DE IDIOMA: Alerta de logout
        alert("Logging out...");
        
        fetch('api/logout', { method: 'POST' }) 
            .then(() => {
                window.location.href = "login.html";
            })
            .catch(() => {
                window.location.href = "login.html"; 
            });
    });
    
    // --- Punto de inicio: Comprobar la sesión primero ---
    checkAuthStatus();
});