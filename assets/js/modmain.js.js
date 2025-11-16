document.addEventListener('DOMContentLoaded', () => {

    // --- GLOBAL STATE ---
    let currentUserRole = '';
    let currentUserFullName = ''; // <<< NUEVA VARIABLE PARA EL NOMBRE DEL DESPACHADOR (USUARIO LOGUEADO)
    let techSignatureTempBase64 = null;
    let managerSignatureTempBase64 = null;

    // --- 0. LÓGICA DE AUTENTICACIÓN Y LOGOUT ---
    const logoutButtonForm = document.getElementById('logoutButtonForm');
    const loggedInUserNameEl = document.getElementById('loggedInUserName');

    // --- CREDENCIALES EMAILJS (REEMPLAZAR CON TUS VALORES REALES) ---
    const EMAILJS_SERVICE_ID = 'service_tewkhg2'; // REEMPLAZAR
    const EMAILJS_TEMPLATE_ID = 'template_m073kpg'; // REEMPLAZAR
    const EMAILJS_STATUS_TEMPLATE_ID = 'template_status_update';
    const EMAILJS_PUBLIC_KEY = 'BNmYFBN0xpJf4jzrH'; // REEMPLAZAR


    // Registrar Plugins de FilePond
    if (typeof FilePondPluginImagePreview !== 'undefined') {
        FilePond.registerPlugin(FilePondPluginImagePreview);
    }

    // assets/js/main.js

    // --- FUNCIÓN EMAILJS DE NOTIFICACIÓN DE ESTADO ---
    async function sendStatusNotification(orderData, adminEmail, adminName, technicianName) {

        if (!adminEmail) {
            console.error("Error EmailJS: Email del administrador no encontrado para notificación de estado.");
            return false;
        }

        const templateParams = {
            // Esto asume que tienes una plantilla separada para notificaciones de estado (EJEMPLO: template_status_update)
            title: `ACTUALIZACIÓN DE ESTADO: WO ${orderData.workOrderNumber}`,

            to_name: adminName, // El destinatario es el administrador
            to_email: adminEmail, // Correo del administrador (creador)

            wo_number: orderData.workOrderNumber,
            customer_name: orderData.customerName,
            service_date: orderData.serviceDate,
            technician_name: technicianName, // Nombre del técnico que reporta
            new_status: orderData.workStage, // Nuevo estado
            work_description: orderData.workDescription || 'No description provided.',
        };

        // --- ADVERTENCIA: DEBES CREAR Y USAR UN TEMPLATE_ID DISTINTO PARA EL STATUS ---
        const EMAILJS_STATUS_TEMPLATE_ID = 'template_status_update'; // REEMPLAZAR CON TU ID REAL
        // -----------------------------------------------------------------------------

        try {
            const response = await emailjs.send(
                EMAILJS_SERVICE_ID,
                EMAILJS_STATUS_TEMPLATE_ID, // <<< Usamos el nuevo ID de plantilla
                templateParams
            );
            console.log('EmailJS Status SUCCESS!', response.status, response.text);
            return true;
        } catch (error) {
            console.error('EmailJS Status FAILED...', error);
            return false;
        }
    }


    function resizeCanvas(canvas, signaturePadInstance, tempStorageVarName) {
        if (!canvas || !signaturePadInstance) return;

        // 1. Guardar firma actual
        if (!signaturePadInstance.isEmpty()) {
            // Usa toDataURL (como en tu código original)
            window[tempStorageVarName] = signaturePadInstance.toDataURL('image/png');
        }

        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        if (canvas.offsetWidth === 0) return; // Evita borrar cuando el panel está oculto

        // 2. Redimensionar
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);

        // 3. Restaurar la firma si existe
        if (window[tempStorageVarName]) {
            signaturePadInstance.fromDataURL(window[tempStorageVarName]);
        }
    }


    async function checkAuthStatus() {
        try {
            const response = await fetch('api/auth/status');

            if (!response.ok) {
                throw new Error('Not authenticated');
            }

            const result = await response.json();

            if (result.success && result.user) {
                currentUserRole = result.user.role;
                currentUserFullName = result.user.full_name; // <<< CAPTURA DEL NOMBRE DEL DESPACHADOR
                const roleDisplay = currentUserRole.charAt(0).toUpperCase() + currentUserRole.slice(1);
                loggedInUserNameEl.textContent = `Welcome, ${result.user.full_name} (${roleDisplay})`;
            } else {
                throw new Error('Invalid session data');
            }
        } catch (error) {
            console.error('Error checking auth status:', error.message);
            alert("Session expired or invalid. Redirecting to login.");
            window.location.href = 'login.html';
        }
    }

    if (logoutButtonForm) {
        logoutButtonForm.addEventListener('click', async () => {
            try {
                await fetch('api/logout', { method: 'POST' });
                alert("Logging out...");
                window.location.href = 'login.html';
            } catch (error) {
                console.error('Error logging out:', error);
                window.location.href = 'login.html';
            }
        });
    }

    // Inicializar EmailJS al inicio
    if (typeof emailjs !== 'undefined') {
        emailjs.init(EMAILJS_PUBLIC_KEY);
    }

    // --- 1. GLOBAL VARIABLES & CONFIGS ---
    const workOrderForm = document.getElementById('workOrderForm');
    const categorySelect = document.getElementById('category');
    const subcategorySelect = document.getElementById('subcategory');
    const assignToEmailSelect = document.getElementById('assignToEmail');
    const assignedTechniciansSelect = document.getElementById('assignedTechnicians');

    const downloadPdfButton = document.getElementById('downloadPdfButton');
    const goToDashboardButton = document.getElementById('goToDashboardButton');

    const collapseAdminEl = document.getElementById('collapseAdmin');
    const collapseTechEl = document.getElementById('collapseTech');
    const collapseSignaturesEl = document.getElementById('collapseSignatures');
    const collapseAdmin = new bootstrap.Collapse(collapseAdminEl, { toggle: false });
    const collapseTech = new bootstrap.Collapse(collapseTechEl, { toggle: false });
    const collapseSignatures = new bootstrap.Collapse(collapseSignaturesEl, { toggle: false });

    const adminButton = document.querySelector('button[data-bs-target="#collapseAdmin"]');
    const techButton = document.querySelector('button[data-bs-target="#collapseTech"]');
    const signaturesButton = document.querySelector('button[data-bs-target="#collapseSignatures"]');

    let categoriesData = {};
    let techniciansData = [];
    let techSignaturePad, managerSignaturePad;

    // CONFIGURACIÓN DE FILEPOND CON SERVER
    FilePond.setOptions({
        labelIdle: 'Drag & Drop your images or <span class="filepond--label-action">Browse</span>',
        credits: false, allowMultiple: true, maxFiles: 5, acceptedFileTypes: ['image/*'],

        server: {
            process: {
                url: 'api/file-upload/process',
                fieldName: 'file', // CRÍTICO: Nombre del campo esperado por PHP

                // NUEVO: INTERCEPTAR LA PETICIÓN ANTES DE ENVIAR
                ondata: (formData) => {
                    console.groupCollapsed('--- FILEPOND UPLOAD PAYLOAD ---');

                    // console.log para ver todas las claves y sus tipos
                    for (var pair of formData.entries()) {
                        if (pair[1] instanceof File) {
                            console.log(`Key: ${pair[0]}, Value: [File Object: ${pair[1].name}, ${pair[1].size} bytes]`);
                        } else {
                            console.log(`Key: ${pair[0]}, Value: ${pair[1]}`);
                        }
                    }
                    console.groupEnd();
                    return formData;
                }
            },
            revert: {
                url: 'api/file-upload/revert',
                method: 'DELETE',
            },
            // Load handler
            load: (source, load, error, progress, abort, headers) => {
                const projectRoot = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
                // Concatena el origen (http://localhost), la raíz del proyecto, y la ruta de la BD (source)
                const fullUrl = window.location.origin + projectRoot + '/' + source;

                const request = new Request(fullUrl);
                fetch(request)
                    .then(res => res.blob())
                    .then(load)
                    .catch(error);
            },
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            }
        },
    });

    // Inicializar FilePond (añadimos name para recolección)
    const pondBefore = FilePond.create(document.getElementById('photosBefore'));
    const pondAfter = FilePond.create(document.getElementById('photosAfter'));

    // Flatpickr Config (con retraso para móviles)
    setTimeout(() => {
        flatpickr("#serviceDate", {
            dateFormat: "Y-m-d",
            disableMobile: true
        });
        flatpickr("#startDate", {
            dateFormat: "Y-m-d H:i",
            enableTime: true,
            disableMobile: true
        });
        flatpickr("#endDate", {
            dateFormat: "Y-m-d H:i",
            enableTime: true,
            disableMobile: true
        });
    }, 100); // 100ms de retraso

    // --- 2. INITIALIZATION FUNCTIONS ---
    function initSelect2() {
        $('#category').select2({ theme: "bootstrap-5", placeholder: "Select a Category" });
        $('#subcategory').select2({ theme: "bootstrap-5", placeholder: "Select a Subcategory" });
        $('#assignToEmail').select2({
            theme: "bootstrap-5",
            placeholder: "Select Responsible Technician"
        });
        $('#assignedTechnicians').select2({
            theme: "bootstrap-5",
            placeholder: "Select team members (tags)",
        });
    }

    function initSignaturePads() {
        const techCanvas = document.getElementById('techSignatureCanvas');
        const managerCanvas = document.getElementById('managerSignatureCanvas');
        const signatureAccordion = document.getElementById('collapseSignatures');

        // Solo inicializar si los elementos Canvas existen
        if (!techCanvas || !managerCanvas) return;

        // --- INICIALIZACIÓN DE LOS PADS ---
        techSignaturePad = new SignaturePad(techCanvas, { penColor: "rgb(0, 0, 0)" });
        managerSignaturePad = new SignaturePad(managerCanvas, { penColor: "rgb(0, 0, 0)" });
        techSignaturePad.clear();
        managerSignaturePad.clear();


        // --- LÓGICA DE EVENTOS DE REDIMENSIONAMIENTO ---

        // 1. Redimensionar cuando el acordeón se ABRE (CRÍTICO: con setTimeout)
        if (signatureAccordion) {
            signatureAccordion.addEventListener('shown.bs.collapse', () => {
                console.log("Acordeón de firmas abierto. Intentando restaurar dibujo.");

                // Usamos setTimeout para asegurar que el DOM ha terminado su animación 
                // y el navegador ha calculado el ancho real (evita offsetWidth=0).
                setTimeout(() => {
                    // LLAMAMOS A LA FUNCIÓN GLOBAL CON LAS INSTANCIAS
                    resizeCanvas(techCanvas, techSignaturePad, 'techSignatureTempBase64');
                    resizeCanvas(managerCanvas, managerSignaturePad, 'managerSignatureTempBase64');

                }, 50);
            });
        }

        // 2. Redimensionar si la ventana cambia de tamaño (rotación móvil)
        window.addEventListener("resize", () => {
            if (techSignaturePad) {
                // LLAMAMOS A LA FUNCIÓN GLOBAL CON LAS INSTANCIAS
                resizeCanvas(techCanvas, techSignaturePad, 'techSignatureTempBase64');
                resizeCanvas(managerCanvas, managerSignaturePad, 'managerSignatureTempBase64');

            }
        });

        // 3. Botones de limpiar (se mantienen igual)
        document.getElementById('clearTechSignature').addEventListener('click', () => {
            techSignaturePad.clear();
        });
        document.getElementById('clearManagerSignature').addEventListener('click', () => {
            managerSignaturePad.clear();
        });
    }

    // --- FUNCIÓN EMAILJS DE NOTIFICACIÓN ---
    async function sendNotificationEmail(formData) {
        // 1. Buscar los detalles del técnico asignado (usando techniciansData)
        const assignedTech = techniciansData.find(tech => tech.email === formData.assignToEmail);

        if (!assignedTech) {
            console.error("Error EmailJS: Detalles del técnico asignado no encontrados.");
            return false;
        }

        const templateParams = {
            // Marcadores de la Plantilla de EmailJS
            title: `NUEVA ORDEN DE TRABAJO ASIGNADA: ${formData.workOrderNumber}`,
            to_name: assignedTech.full_name,
            to_email: assignedTech.email, // Destinatario CRÍTICO
            wo_number: formData.workOrderNumber,
            customer_name: formData.customerName,
            service_date: formData.serviceDate,
            activity_description: formData.activityDescription || 'Sin descripción de actividad.',
            dispatcher_name: currentUserFullName || 'Administrador Despachador', // Nombre del usuario logueado
        };

        try {
            const response = await emailjs.send(
                EMAILJS_SERVICE_ID,
                EMAILJS_TEMPLATE_ID,
                templateParams
            );
            console.log('EmailJS SUCCESS! Status:', response.status, response.text);
            return true;
        } catch (error) {
            console.error('EmailJS FAILED...', error);
            return false;
        }
    }


    // --- 3. DATA POPULATION & FORM LOGIC ---

    async function populateSelects() {
        try {
            const response = await fetch('api/data/form-init');
            const data = await response.json();

            if (data.success) {
                categoriesData = data.categories;
                techniciansData = data.technicians;

                categorySelect.innerHTML = '<option value=""></option>';
                for (const category in categoriesData) {
                    const option = new Option(category, category);
                    categorySelect.appendChild(option);
                }

                assignToEmailSelect.innerHTML = '<option value=""></option>';
                assignedTechniciansSelect.innerHTML = '';

                techniciansData.forEach(tech => {
                    const responsibleOption = new Option(tech.full_name, tech.email);
                    assignToEmailSelect.appendChild(responsibleOption);
                    const involvedOption = new Option(tech.full_name, tech.user_id);
                    assignedTechniciansSelect.appendChild(involvedOption);
                });

                $(assignToEmailSelect).trigger('change');
                $(assignedTechniciansSelect).trigger('change');
                $(categorySelect).trigger('change');

            } else { alert('Error loading form data.'); }
        } catch (error) { console.error('Error fetching init data:', error); }
    }

    $('#category').on('change', function () {
        const selectedCategory = $(this).val();
        subcategorySelect.innerHTML = '<option value=""></option>';
        let hasSubcategories = false;

        if (selectedCategory && categoriesData[selectedCategory] && categoriesData[selectedCategory].length > 0) {
            categoriesData[selectedCategory].forEach(subcategory => {
                const option = new Option(subcategory, subcategory);
                subcategorySelect.appendChild(option);
            });
            hasSubcategories = true;
        }

        $(subcategorySelect).prop('disabled', !hasSubcategories);
        subcategorySelect.required = hasSubcategories;
        $(subcategorySelect).trigger('change');
    });

    // --- 4. FORM MODES (CREATE vs UPDATE) ---
    function getWorkOrderIdFromUrl() {
        const params = new URLSearchParams(window.location.search);
        return params.get('id');
    }

    async function initCreateForm() {
        console.log("Mode: CREATE (Admin)");
        await populateSelects();
        initSelect2();
        initSignaturePads();
        disableTechFields();

        const today = new Date();
        const yyyy = today.getFullYear();
        // getMonth() es base 0, por eso sumamos 1
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');

        // Formato YYYY-MM-DD
        document.getElementById('serviceDate').value = `${yyyy}-${mm}-${dd}`;

        collapseAdmin.show();
        collapseTech.hide();
        collapseSignatures.hide();

        // --- OCULTAR PDF EN MODO CREATE ---
        if (downloadPdfButton) {
            downloadPdfButton.classList.add('d-none');
        }
        // --- FIN OCULTAR PDF ---

        try {
            const response = await fetch('api/workorders/next-number');
            const data = await response.json();
            document.getElementById('workOrderNumber').value = data.success ? data.next_number : "Error-Gen";
        } catch (error) { console.error('Error fetching next WO number:', error); }
    }

    async function initUpdateForm(id) {
        console.log("Mode: UPDATE (Role-based permissions applied after load)", id);
        await populateSelects();
        initSelect2();
        initSignaturePads();

        /* collapseAdmin.show();
         collapseTech.show();
         collapseSignatures.show(); */

        // --- MOSTRAR Y CONFIGURAR PDF EN MODO UPDATE ---
        if (downloadPdfButton) {
            downloadPdfButton.classList.remove('d-none');
            downloadPdfButton.href = `api/workorders/pdf/${id}`;
        }
        // --- FIN MOSTRAR Y CONFIGURAR PDF ---

        try {
            const response = await fetch(`api/workorders/details/${id}`);
            const result = await response.json();

            if (result.success) {
                populateFormData(result.data);

                // --- NUEVO CÓDIGO CRÍTICO: CARGA DE FIRMAS SIN DELAY ---
                if (typeof techSignaturePad !== 'undefined') {
                    // Cargar Base64 inmediatamente. Esto lo hace 'invisible' si el canvas está mal dimensionado.
                    if (result.data.tech_signature_base64) {
                        techSignaturePad.fromDataURL(result.data.tech_signature_base64);
                        console.log(result.data.tech_signature_base64)
                    }
                    if (result.data.manager_signature_base64) {
                        managerSignaturePad.fromDataURL(result.data.manager_signature_base64);
                    }
                }
                // --- FIN DE CARGA DE FIRMAS ---

                // CRÍTICO: Disparar el redimensionamiento después de cargar los datos
                // (Esto es redundante si el acordeón está cerrado, pero esencial si está abierto por defecto)
                const techCanvas = document.getElementById('techSignatureCanvas');
                const managerCanvas = document.getElementById('managerSignatureCanvas');
                resizeCanvas(techCanvas, techSignaturePad, 'techSignatureTempBase64');
                resizeCanvas(managerCanvas, managerSignaturePad, 'managerSignatureTempBase64');



            } else {
                alert(result.message);
                window.location.href = 'tech-dashboard.html';
            }
        } catch (error) { console.error('Error fetching order details:', error); }
    }

    // --- 5. FIELD CONTROL & POPULATION ---

    // DESHABILITA CAMPOS DEL ADMINISTRADOR (Sección 1)
    function disableAdminFields() {
        // [CRÍTICO] MANTENER EL BOTÓN DEL ACORDEÓN HABILITADO
        adminButton.disabled = false;
        adminButton.classList.remove('disabled');

        // DESHABILITAR TODOS LOS INPUTS DENTRO DE LA SECCIÓN
        document.getElementById('customerName').disabled = true;
        document.getElementById('city').disabled = true;
        document.getElementById('phoneNumber').disabled = true;
        document.getElementById('customerType').disabled = true;
        document.getElementById('activityDescription').disabled = true;
        document.getElementById('category').disabled = true;
        document.getElementById('subcategory').disabled = true;
        document.getElementById('totalCost').disabled = true;
        document.getElementById('assignToEmail').disabled = true;
        document.getElementById('serviceDate').disabled = true;
        $(categorySelect).prop('disabled', true).trigger('change');
        $(subcategorySelect).prop('disabled', true).trigger('change');
        $(assignToEmailSelect).prop('disabled', true).trigger('change');
    }

    // DESHABILITA CAMPOS DEL TÉCNICO (Sección 2 y 3)
    function disableTechFields() {
        document.querySelectorAll('input[name="workStage"]').forEach(el => el.disabled = false);
        document.getElementById('materials').disabled = false;
        document.getElementById('workDescription').disabled = false;
        pondBefore.disabled = false;
        pondAfter.disabled = false;
        document.getElementById('startDate').disabled = false;
        document.getElementById('endDate').disabled = false;
        document.getElementById('totalHours').disabled = false;
        document.querySelectorAll('input[name="estimatedDuration"]').forEach(el => el.disabled = false);
        document.getElementById('workAfter5PM').disabled = false;
        document.getElementById('workWeekend').disabled = false;
        document.getElementById('isEmergency').disabled = false;
        document.getElementById('assignedTechnicians').disabled = true;
        document.getElementById('techSignatureName').disabled = true;
        document.getElementById('managerSignatureName').disabled = true;

        if (techSignaturePad) techSignaturePad.off();
        if (managerSignaturePad) managerSignaturePad.off();

        $(assignedTechniciansSelect).prop('disabled', true).trigger('change');
    }

    function populateFormData(data) {
        document.getElementById('workOrderNumber').value = data.work_order_number;
        document.getElementById('serviceDate').value = data.service_date;
        document.getElementById('customerName').value = data.customer_name;
        document.getElementById('city').value = data.customer_city;
        document.getElementById('phoneNumber').value = data.customer_phone;
        document.getElementById('customerType').value = data.customer_type;
        document.getElementById('activityDescription').value = data.activity_description || '';
        document.getElementById('totalCost').value = data.total_cost;
        $('#category').val(data.category_name).trigger('change');
        $('#assignToEmail').val(data.assign_to_email).trigger('change');

        setTimeout(() => {
            $('#subcategory').val(data.subcategory_name).trigger('change');
        }, 500);

        if (data.status) {
            document.querySelector(`input[name="workStage"][value="${data.status}"]`).checked = true;
        }
        document.getElementById('materials').value = data.materials_used || '';
        document.getElementById('workDescription').value = data.work_description || '';
        document.getElementById('startDate').value = data.start_datetime || '';
        document.getElementById('endDate').value = data.end_datetime || '';
        document.getElementById('totalHours').value = data.total_hours || '';
        if (data.estimated_duration) {
            document.querySelector(`input[name="estimatedDuration"][value="${data.estimated_duration}"]`).checked = true;
        }
        document.getElementById('workAfter5PM').checked = !!data.work_after_5pm;
        document.getElementById('workWeekend').checked = !!data.work_weekend;
        document.getElementById('isEmergency').checked = !!data.is_emergency;
        document.getElementById('techSignatureName').value = data.tech_signature_name_print || '';
        document.getElementById('managerSignatureName').value = data.manager_signature_name_print || '';

        if (data.involved_technicians) {
            $('#assignedTechnicians').val(data.involved_technicians).trigger('change');
        }

        // CARGA DE FOTOS
        if (data.photos && data.photos.length > 0) {
            const filesBefore = data.photos
                .filter(p => p.photo_type === 'before')
                .map(p => ({
                    source: p.file_path,
                    options: { type: 'local' },
                }));

            const filesAfter = data.photos
                .filter(p => p.photo_type === 'after')
                .map(p => ({
                    source: p.file_path,
                    options: { type: 'local' },
                }));

            pondBefore.setOptions({ files: filesBefore });
            pondAfter.setOptions({ files: filesAfter });
        }


        if (data.tech_signature_base64 && techSignaturePad) {
            try {
                // La firma se carga, aunque esté invisible/mal dimensionada al inicio.
                techSignaturePad.fromDataURL(data.tech_signature_base64);
            } catch (e) { console.error("Error loading tech signature:", e); }
        }
        if (data.manager_signature_base64 && managerSignaturePad) {
            try {
                managerSignaturePad.fromDataURL(data.manager_signature_base64);
            } catch (e) { console.error("Error loading manager signature:", e); }
        }
    }

    // --- 6. FORM SUBMISSION (CON LÓGICA EMAILJS) ---
    workOrderForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        let isFormValid = true;
        const categoryVal = $('#category').val();
        const assignToEmailVal = $('#assignToEmail').val();
        $('.select2-container').removeClass('is-invalid');

        if (!workOrderForm.checkValidity()) {
            isFormValid = false;
            workOrderForm.classList.add('was-validated');
        }
        if (!categoryVal) {
            isFormValid = false;
            $('#category').next('.select2-container').addClass('is-invalid');
        }
        const workOrderId = getWorkOrderIdFromUrl();
        if (!workOrderId && !assignToEmailVal) {
            isFormValid = false;
            $('#assignToEmail').next('.select2-container').addClass('is-invalid');
        }

        if (!isFormValid) {
            e.stopPropagation();
            return;
        }

        const formData = new FormData(workOrderForm);
        const data = Object.fromEntries(formData.entries());

        data.workStage = document.querySelector('input[name="workStage"]:checked')?.value;
        data.estimatedDuration = document.querySelector('input[name="estimatedDuration"]:checked')?.value;
        data.workAfter5PM = document.getElementById('workAfter5PM').checked;
        data.workWeekend = document.getElementById('workWeekend').checked;
        data.isEmergency = document.getElementById('isEmergency').checked;
        data.category = categoryVal;
        data.assignToEmail = assignToEmailVal;
        data.subcategory = $('#subcategory').val();
        data.assignedTechnicians = $('#assignedTechnicians').val();

        // Recolección de identificadores de FilePond
        data.photosBefore = pondBefore.getFiles().map(file => file.serverId || file.source);
        data.photosAfter = pondAfter.getFiles().map(file => file.serverId || file.source);

        // Firmas
        data.tech_signature_base64 = techSignaturePad.isEmpty() ? null : techSignaturePad.toDataURL('image/png');
        data.manager_signature_base64 = managerSignaturePad.isEmpty() ? null : managerSignaturePad.toDataURL('image/png');

        if (workOrderId) {
            data.customerName = document.getElementById('customerName').value;
            data.city = document.getElementById('city').value;
            data.phoneNumber = document.getElementById('phoneNumber').value;
            data.customerType = document.getElementById('customerType').value;
            data.activityDescription = document.getElementById('activityDescription').value;
            data.totalCost = document.getElementById('totalCost').value;
            data.serviceDate = document.getElementById('serviceDate').value;
            data.workOrderNumber = document.getElementById('workOrderNumber').value;
        }

        let url = workOrderId ? `api/workorders/update/${workOrderId}` : 'api/workorders/create';

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();

            if (result.success) {
                let message = result.message;
                let mailSent = true;

                // --- LÓGICA DE ENVÍO DE CORREO SOLO PARA CREATE (NUEVO) ---
                if (!workOrderId) { // Si NO existe workOrderId, es una creación.
                    const techEmail = techniciansData.find(tech => tech.email === data.assignToEmail)?.email;

                    if (techEmail) {
                        mailSent = await sendNotificationEmail(data); // data tiene todos los datos necesarios
                        if (!mailSent) {
                            message += " (ADVERTENCIA: Falló el envío de notificación por email.)";
                        }
                    } else {
                        message += " (ADVERTENCIA: No se encontró el email del técnico para notificar.)";
                    }
                }
                // --- FIN LÓGICA DE ENVÍO DE CORREO ---


                alert(message);

                let redirectUrl = 'admin-dashboard.html';

                if (workOrderId) {
                    if (currentUserRole.toLowerCase() === 'admin') {
                        redirectUrl = 'admin-dashboard.html';
                    } else if (currentUserRole.toLowerCase() === 'technician') {
                        redirectUrl = 'tech-dashboard.html';
                    }
                }

                window.location.href = redirectUrl;

            } else { alert(`Error: ${result.message}`); }

        } catch (error) {
            console.error('Error submitting form:', error);
            alert('A network error occurred.');
        }
    });

    // --- 7. INITIALIZATION (LÓGICA DE PERMISOS BASADA EN ROL) ---
    async function initializePage() {
        await checkAuthStatus();

        const workOrderId = getWorkOrderIdFromUrl();

        if (!workOrderId) {
            await initCreateForm();
        } else {
            await initUpdateForm(workOrderId);

            const role = currentUserRole.toLowerCase();

            if (role === 'admin') {
                console.log("Applying permissions: Admin Mode (Update). Disabling Tech fields.");
                disableTechFields();
            } else if (role === 'technician') {
                console.log("Applying permissions: Technician Mode (Update). Disabling Admin fields.");
                disableAdminFields();

            } else {
                console.log("Applying permissions: Unknown Role. Disabling ALL fields.");
                disableAdminFields();
                disableTechFields();
            }
        }

        if (goToDashboardButton) {
            goToDashboardButton.addEventListener('click', () => {
                let redirectUrl = 'login.html'; // Default seguro

                if (currentUserRole.toLowerCase() === 'admin') {
                    redirectUrl = 'admin-dashboard.html';
                } else if (currentUserRole.toLowerCase() === 'technician') {
                    redirectUrl = 'tech-dashboard.html';
                }

                window.location.href = redirectUrl;
            });
        }
    }

    initializePage();

});