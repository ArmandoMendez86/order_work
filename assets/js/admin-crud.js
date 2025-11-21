// assets/js/admin-crud.js

$(document).ready(function () {

    // Helper para validación de Bootstrap
    const bootstrapValidate = (form) => {
        if (!form[0].checkValidity()) {
            form.addClass('was-validated');
            return false;
        }
        form.removeClass('was-validated');
        return true;
    };

    // Helper para resetear formularios
    const resetForm = (form) => {
        form[0].reset();
        form.removeClass('was-validated');
    };

    // =========================================================================
    // LÓGICA DEL CRUD DE CLIENTES (CLIENT)
    // =========================================================================

    const clientCrudModal = $('#clientCrudModal');
    const clientForm = $('#clientForm');
    const clientFormTitle = $('#clientFormTitle');
    const clientFormReset = $('#clientFormReset');
    let clientTable; // Variable global para la DataTable

    // Inicializar o Recargar la DataTable de Clientes
    function initClientTable() {
        if ($.fn.DataTable.isDataTable('#clientTable')) {
            clientTable.ajax.reload(null, false); // Recargar datos sin resetear la paginación
        } else {
            clientTable = $('#clientTable').DataTable({
                "ajax": {
                    "url": "api/customers",
                    "type": "GET",
                    "dataSrc": "data",
                    "error": function (xhr, error, thrown) {
                        if (xhr.status == 401 || xhr.status == 403) {
                            alert("Session expired or unauthorized. Please login again.");
                            window.location.href = "login.html";
                        } else {
                            console.error("Error loading client data:", error);
                        }
                    }
                },
                "columns": [
                    { "data": "customer_id", "width": "5%" },
                    { "data": "customer_name" },
                    { "data": "customer_city" },
                    { "data": "customer_phone" },
                    { "data": "customer_email" },
                    { "data": "customer_type" },
                    {
                        "data": null,
                        "render": function (data, type, row) {
                            // Usamos JSON.stringify para pasar el objeto completo de forma segura
                            const rowData = JSON.stringify(row).replace(/'/g, '&apos;');
                            return `
                                <button class='btn btn-warning btn-sm edit-client' data-data='${rowData}' title='Edit'>
                                    <i class='bi bi-pencil'></i>
                                </button>
                                <button class='btn btn-danger btn-sm delete-client' data-id='${row.customer_id}' title='Delete'>
                                    <i class='bi bi-trash'></i>
                                </button>
                            `;
                        },
                        "orderable": false,
                        "width": "10%"
                    }
                ],
                // === CAMBIO SOLICITADO: 5 registros por defecto ===
                "pageLength": 5,
                // =================================================
                "responsive": true,
                "order": [[1, 'asc']] // Ordenar por nombre por defecto
            });
        }
    }

    // --- EVENTOS DEL MODAL DE CLIENTES ---

    // 1. Cuando el modal se abre, cargar/recargar la tabla
    clientCrudModal.on('show.bs.modal', function () {
        initClientTable();
        clientFormReset.trigger('click');
    });

    // 2. Manejar el envío del formulario (Crear/Actualizar)
    clientForm.on('submit', function (e) {
        e.preventDefault();
        if (!bootstrapValidate(clientForm)) return;

        const customerId = $('#customerId').val();
        let url = customerId ? `api/customers/update/${customerId}` : 'api/customers/create';
        let method = 'POST';

        const formData = {
            customer_name: $('#customerName').val(),
            customer_city: $('#customerCity').val(),
            customer_phone: $('#customerPhone').val(),
            customer_email: $('#customerEmail').val(),
            customer_type: $('#customerType').val()
        };

        fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert(result.message);
                    clientFormReset.trigger('click'); // Resetear el formulario
                    clientTable.ajax.reload(); // Recargar la tabla
                } else {
                    alert('Error: ' + result.message);
                }
            })
            .catch(error => {
                console.error('Submission error:', error);
                alert('A network error occurred.');
            });
    });

    // 3. Cargar datos en el formulario para EDICIÓN
    $('#clientTable tbody').on('click', '.edit-client', function () {
        const rowData = $(this).data('data');

        clientFormTitle.text(`Edit Client: ${rowData.customer_name}`);
        $('#customerId').val(rowData.customer_id);
        $('#customerName').val(rowData.customer_name);
        $('#customerCity').val(rowData.customer_city);
        $('#customerPhone').val(rowData.customer_phone);
        $('#customerEmail').val(rowData.customer_email);
        $('#customerType').val(rowData.customer_type);

        clientForm.removeClass('was-validated');
        clientForm[0].scrollIntoView({ behavior: 'smooth' });
    });

    // 4. Manejar ELIMINACIÓN
    $('#clientTable tbody').on('click', '.delete-client', function () {
        const customerId = $(this).data('id');
        if (confirm(`Are you sure you want to delete client ID ${customerId}? This cannot be undone.`)) {

            fetch(`api/customers/delete/${customerId}`, {
                method: 'DELETE'
            })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert(result.message);
                        clientTable.ajax.reload();
                    } else {
                        alert('Error deleting client: ' + result.message);
                    }
                })
                .catch(error => {
                    console.error('Delete error:', error);
                    alert('A network error occurred.');
                });
        }
    });

    // 5. Botón de Resetear/Cancelar formulario
    clientFormReset.on('click', function () {
        resetForm(clientForm);
        $('#customerId').val('');
        clientFormTitle.text('Add New Client');
    });

    // =========================================================================
    // LÓGICA DEL CRUD DE CATEGORÍAS
    // =========================================================================

    const categoryModal = $('#categoryCrudModal');
    if (categoryModal.length) {
        const categoryForm = $('#categoryForm');
        const categoryNameInput = $('#categoryName');
        const categoryTableBody = $('#categoryTableBody');

        const subcategoryForm = $('#subcategoryForm');
        const subcategoryNameInput = $('#subcategoryName');
        const categorySelectForSub = $('#categorySelectForSub');

        let allCategoriesData = []; // Caché para los datos

        // --- Cargar todas las categorías y subcategorías (VISTA UNIFICADA) ---
        const loadCategories = async () => {
            try {
                const response = await fetch('api/categories');
                const result = await response.json();

                if (!result.success) throw new Error(result.message);

                allCategoriesData = result.data || [];

                // 1. Limpiar vistas
                categoryTableBody.empty();
                categorySelectForSub.empty().append('<option value="" selected disabled>Select parent category...</option>');

                if (allCategoriesData.length === 0) {
                    categoryTableBody.html('<tr><td colspan="2" class="text-center text-muted">No categories found.</td></tr>');
                }

                // 2. Poblar tabla con estructura colapsable
                allCategoriesData.forEach(cat => {
                    const hasSubs = cat.subcategories && cat.subcategories.length > 0;
                    const collapseId = `subs-${cat.category_id}`;

                    // Fila de Categoría (PADRE)
                    let catHtml = `
                        <tr class="category-row bg-light" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}">
                            <td class="fw-bold">${hasSubs ? '<i class="bi bi-caret-right-fill me-2 expand-icon"></i>' : ''}${cat.category_name}</td>
                            <td class="text-end">
                                <button class="btn btn-warning btn-sm btn-edit-cat" data-id="${cat.category_id}" data-name="${cat.category_name}" title="Edit">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <button class="btn btn-danger btn-sm btn-delete-cat" data-id="${cat.category_id}" title="Delete">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    categoryTableBody.append(catHtml);

                    // Fila de Subcategorías (HIJO, colapsable)
                    if (hasSubs) {
                        let subTableBody = '';
                        cat.subcategories.forEach(sub => {
                            subTableBody += `
                                <tr>
                                    <td class="ps-5">${sub.subcategory_name}</td>
                                    <td class="text-end">
                                        <button class="btn btn-warning btn-sm btn-edit-sub" data-id="${sub.subcategory_id}" data-name="${sub.subcategory_name}" title="Edit">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm btn-delete-sub" data-id="${sub.subcategory_id}" title="Delete">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
                        });

                        let subHtml = `
                            <tr class="collapse" id="${collapseId}">
                                <td colspan="2" class="p-0 border-0">
                                    <table class="table table-sm table-striped mb-0">
                                        <tbody class="border-top-0">
                                            ${subTableBody}
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        `;
                        categoryTableBody.append(subHtml);
                    }

                    // 3. Poblar select de subcategorías para el formulario de adición
                    categorySelectForSub.append(`
                        <option value="${cat.category_id}">${cat.category_name}</option>
                    `);
                });

            } catch (error) {
                console.error('Error loading categories:', error);
                alert('Could not load categories: ' + error.message);
            }
        };

        // Evento: Rotar icono de expansión al hacer clic en la fila de categoría
        categoryTableBody.on('click', '.category-row', function () {
            // Encuentra el icono dentro de la fila y alterna la clase de rotación
            $(this).find('.expand-icon').toggleClass('bi-caret-right-fill bi-caret-down-fill');
        });


        // Evento: Crear Categoría
        categoryForm.on('submit', async (e) => {
            e.preventDefault();
            if (!bootstrapValidate(categoryForm)) return;

            const name = categoryNameInput.val();
            try {
                const response = await fetch('api/categories/create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: name, parent_id: null })
                });
                const result = await response.json();
                alert(result.message);
                if (result.success) {
                    resetForm(categoryForm);
                    await loadCategories(); // Recargar todo
                }
            } catch (error) {
                console.error('Error creating category:', error);
                alert('A network error occurred.');
            }
        });

        // Evento: Crear Subcategoría
        subcategoryForm.on('submit', async (e) => {
            e.preventDefault();
            if (!bootstrapValidate(subcategoryForm)) return;

            const name = subcategoryNameInput.val();
            const parentId = categorySelectForSub.val();

            if (!parentId) {
                alert('Please select a parent category.');
                return;
            }

            try {
                const response = await fetch('api/categories/create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: name, parent_id: parentId })
                });
                const result = await response.json();
                alert(result.message);
                if (result.success) {
                    resetForm(subcategoryForm);
                    // Recargar y mantener la categoría padre seleccionada y abierta
                    await loadCategories();
                    categorySelectForSub.val(parentId);
                    $(`#subs-${parentId}`).collapse('show'); // Abrir la sección
                }
            } catch (error) {
                console.error('Error creating subcategory:', error);
                alert('A network error occurred.');
            }
        });

        // --- Eventos de Edición/Eliminación ---
        categoryTableBody.on('click', '.btn-edit-cat, .btn-delete-cat, .btn-edit-sub, .btn-delete-sub', async (e) => {
            const btn = e.target.closest('button');
            const id = btn.dataset.id;

            // Si es subcategoría, encontramos la fila colapsable para obtener el ID padre
            const isSubEditOrDelete = btn.classList.contains('btn-edit-sub') || btn.classList.contains('btn-delete-sub');
            let parentId = null;
            if (isSubEditOrDelete) {
                const subRow = btn.closest('tr'); // Fila de subcategoría
                const tableBody = subRow.closest('tbody'); // Cuerpo de tabla de subcategorías
                const collapseRow = tableBody.closest('tr.collapse'); // Fila colapsable
                if (collapseRow) {
                    parentId = $(collapseRow).attr('id').replace('subs-', '');
                }
            }

            const isCategory = btn.classList.contains('btn-edit-cat') || btn.classList.contains('btn-delete-cat');
            const type = isCategory ? 'category' : 'subcategory';

            // Edición
            if (btn.classList.contains('btn-edit-cat') || btn.classList.contains('btn-edit-sub')) {
                const currentName = btn.dataset.name;
                const newName = prompt(`Enter new ${type} name:`, currentName);

                if (newName && newName.trim() !== currentName) {
                    try {
                        const response = await fetch(`api/categories/update/${type}/${id}`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ name: newName })
                        });
                        const result = await response.json();
                        alert(result.message);
                        if (result.success) {
                            await loadCategories();
                            if (!isCategory && parentId) {
                                // Mantener expandido si es subcategoría
                                $(`#subs-${parentId}`).collapse('show');
                                $('.category-row[data-bs-target="#subs-' + parentId + '"]').find('.expand-icon').toggleClass('bi-caret-right-fill bi-caret-down-fill', true);
                            }
                        }
                    } catch (error) { console.error(`Error updating ${type}:`, error); }
                }

                // Eliminación
            } else if (btn.classList.contains('btn-delete-cat') || btn.classList.contains('btn-delete-sub')) {
                let confirmationText = isCategory
                    ? `Are you sure you want to delete this category? ALL its subcategories and any Work Orders linked to it might be affected.`
                    : `Are you sure you want to delete this subcategory?`;

                if (!confirm(confirmationText)) {
                    return;
                }

                try {
                    const response = await fetch(`api/categories/delete/${type}/${id}`, {
                        method: 'DELETE'
                    });
                    const result = await response.json();
                    alert(result.message);
                    if (result.success) {
                        await loadCategories();
                        if (!isCategory && parentId) {
                            // Mantener expandido si es subcategoría
                            $(`#subs-${parentId}`).collapse('show');
                            $('.category-row[data-bs-target="#subs-' + parentId + '"]').find('.expand-icon').toggleClass('bi-caret-right-fill bi-caret-down-fill', true);
                        }
                    }
                } catch (error) { console.error(`Error deleting ${type}:`, error); }
            }
        });

        // Carga inicial al abrir el modal
        categoryModal.on('shown.bs.modal', loadCategories);
    }

    // =========================================================================
    // LÓGICA DEL CRUD DE USUARIOS (USER) - ¡NUEVO!
    // =========================================================================

    const userCrudModal = $('#userCrudModal');
    const userForm = $('#userForm');
    const userFormTitle = $('#userFormTitle');
    const userFormReset = $('#userFormReset');
    const userPasswordInput = $('#userPassword');
    const passwordValidationEl = $('#passwordValidation');
    let userTable; // Variable global para la DataTable

    // Inicializar o Recargar la DataTable de Usuarios
    function initUserTable() {
        if ($.fn.DataTable.isDataTable('#userTable')) {
            userTable.ajax.reload(null, false);
        } else {
            userTable = $('#userTable').DataTable({
                "ajax": {
                    "url": "api/users",
                    "type": "GET",
                    "dataSrc": "data",
                    "error": function (xhr, error, thrown) {
                        if (xhr.status == 401 || xhr.status == 403) {
                            alert("Session expired or unauthorized. Please login again.");
                            window.location.href = "login.html";
                        } else {
                            console.error("Error loading user data:", error);
                        }
                    }
                },
                "columns": [
                    { "data": "user_id", "width": "5%" },
                    { "data": "full_name" },
                    { "data": "email" },
                    {
                        "data": "role",
                        "render": function (data) {
                            return data.charAt(0).toUpperCase() + data.slice(1); // Capitalizar
                        }
                    },
                    {
                        "data": null,
                        "render": function (data, type, row) {
                            const rowData = JSON.stringify(row).replace(/'/g, '&apos;');
                            return `
                                <button class='btn btn-sm btn-warning edit-user' data-data='${rowData}' title='Edit'>
                                    <i class='bi bi-pencil'></i>
                                </button>
                                <button class='btn btn-sm btn-danger delete-user' data-id='${row.user_id}' title='Delete'>
                                    <i class='bi bi-trash'></i>
                                </button>
                            `;
                        },
                        "orderable": false,
                        "width": "10%"
                    }
                ],
                "pageLength": 5, // <<< APLICADO: 5 registros por defecto
                "responsive": true,
                "order": [[0, 'asc']]
            });
        }
    }

    // --- EVENTOS DEL MODAL DE USUARIOS ---

    // 1. Cuando el modal se abre, cargar/recargar la tabla
    userCrudModal.on('show.bs.modal', function () {
        initUserTable();
        userFormReset.click(); // Asegurar formulario limpio
    });

    // 2. Manejar el envío del formulario (Crear/Actualizar)
    userForm.on('submit', function (e) {
        e.preventDefault();

        const userId = $('#userId').val();
        const isCreating = !userId;

        // --- VALIDACIÓN DE CONTRASEÑA ESPECÍFICA PARA CREACIÓN ---
        // Hacemos que la contraseña sea requerida si es un nuevo usuario y el campo está vacío
        if (isCreating && userPasswordInput.val() === '') {
            userPasswordInput.prop('required', true);
            passwordValidationEl.text('Password is required for new users.');
        } else {
            // Quitamos el requisito de HTML5 para que pase el checkValidity si estamos actualizando
            userPasswordInput.prop('required', false);
        }

        // Si la validación de Bootstrap falla, salir
        if (!this.checkValidity()) {
            this.classList.add('was-validated');
            return;
        }

        let url = isCreating ? 'api/users/create' : `api/users/update/${userId}`;
        let method = 'POST';

        const formData = {
            full_name: $('#userName').val(),
            email: $('#userEmail').val(),
            role: $('#userRole').val(),
            // La contraseña solo se envía si no está vacía
            password: userPasswordInput.val()
        };

        fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
            .then(response => {
                if (response.status === 409) { // Conflict (Email duplicado)
                    return response.json().then(data => { throw new Error(data.message); });
                }
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    alert(result.message);
                    userFormReset.click(); // Resetear el formulario
                    userTable.ajax.reload(); // Recargar la tabla
                } else {
                    alert('Error: ' + result.message);
                }
            })
            .catch(error => {
                console.error('Submission error:', error);
                // Mensaje de error más específico para el usuario
                alert('A network or server error occurred: ' + (error.message.includes("Email already exists") ? "Error: Email is already in use." : error.message));
            });
    });

    // 3. Cargar datos en el formulario para EDICIÓN
    $('#userTable tbody').on('click', '.edit-user', function () {
        const rowData = $(this).data('data');

        userFormTitle.text(`Edit User: ${rowData.full_name}`);
        $('#userId').val(rowData.user_id);
        $('#userName').val(rowData.full_name);
        $('#userEmail').val(rowData.email);
        $('#userRole').val(rowData.role);

        // Resetear campo de contraseña para UPDATE y hacer que no sea requerido
        userPasswordInput.val('');
        userPasswordInput.prop('required', false);
        userPasswordInput.attr('placeholder', 'Leave empty to keep current password');
        passwordValidationEl.text('Leave empty to keep current password.');

        // Quitar la validación por si el formulario se mostró inválido
        userForm.removeClass('was-validated');
    });

    // 4. Manejar ELIMINACIÓN
    $('#userTable tbody').on('click', '.delete-user', function () {
        const userId = $(this).data('id');
        if (confirm(`Are you sure you want to delete user ID ${userId}? This cannot be undone and will fail if the user has assigned work orders.`)) {

            fetch(`api/users/delete/${userId}`, {
                method: 'DELETE'
            })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert(result.message);
                        userTable.ajax.reload();
                    } else {
                        alert('Error deleting user: ' + result.message);
                    }
                })
                .catch(error => {
                    console.error('Delete error:', error);
                    alert('A network error occurred.');
                });
        }
    });

    // 5. Botón de Resetear/Cancelar formulario
    userFormReset.on('click', function () {
        userForm[0].reset();
        $('#userId').val('');
        userFormTitle.text('Add New User');
        userForm.removeClass('was-validated');

        // Restaurar estado inicial del campo password (requerido para CREATE)
        userPasswordInput.prop('required', true);
        userPasswordInput.attr('placeholder', 'Leave empty to keep current password');
        passwordValidationEl.text('Password is required for new users.');
    });

});