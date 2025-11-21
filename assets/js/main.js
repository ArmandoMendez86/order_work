document.addEventListener('DOMContentLoaded', () => {

    // --- GLOBAL STATE ---
    let currentUserRole = '';
    let currentUserFullName = '';
    let techSignatureTempBase64 = null;
    let managerSignatureTempBase64 = null;

    // --- DEFINICIÓN DE ELEMENTOS CRÍTICOS ---
    const logoutButtonForm = document.getElementById('logoutButtonForm');
    const loggedInUserNameEl = document.getElementById('loggedInUserName');
    // ----------------------------------------------------------------------


    // --- CREDENCIALES EMAILJS ---
    const EMAILJS_SERVICE_ID = 'service_tewkhg2';
    const EMAILJS_CREATE_TEMPLATE_ID = 'template_m073kpg';
    const EMAILJS_STATUS_TEMPLATE_ID = 'template_2j1b8vs';
    const EMAILJS_PUBLIC_KEY = 'BNmYFBN0xpJf4jzrH';


    // Registrar Plugins de FilePond
    if (typeof FilePondPluginImagePreview !== 'undefined') {
        FilePond.registerPlugin(FilePondPluginImagePreview);
    }


    function resizeCanvas(canvas, signaturePadInstance, tempStorageVarName) {
        if (!canvas || !signaturePadInstance) return;

        if (!signaturePadInstance.isEmpty()) {
            window[tempStorageVarName] = signaturePadInstance.toDataURL('image/png');
        }

        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        if (canvas.offsetWidth === 0) return; 

        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);

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
                currentUserFullName = result.user.full_name; 
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

    function toggleSubmissionState(isLoading) {
        if (!submitButton || !submitSpinner || !submitButtonText) return;

        submitButton.disabled = isLoading;

        if (isLoading) {
            submitSpinner.classList.remove('d-none');
            submitButtonText.innerHTML = 'Processing...';
        } else {
            submitSpinner.classList.add('d-none');
            submitButtonText.innerHTML = '<i class="bi bi-send-fill"></i> Submit Work Order';
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

    if (typeof emailjs !== 'undefined') {
        emailjs.init(EMAILJS_PUBLIC_KEY);
    }

    // --- 1. GLOBAL VARIABLES & CONFIGS ---
    const workOrderForm = document.getElementById('workOrderForm');

    // === INICIO DE MODIFICACIÓN: Definir campos de cliente ===
    const customerSelect = document.getElementById('customerName'); // <-- Es un select
    const cityInput = document.getElementById('city');
    const phoneInput = document.getElementById('phoneNumber');
    const customerTypeInput = document.getElementById('customerType');
    // === FIN DE MODIFICACIÓN ===

    const categorySelect = document.getElementById('category');
    const subcategorySelect = document.getElementById('subcategory');
    const assignToEmailSelect = document.getElementById('assignToEmail');
    const assignedTechniciansSelect = document.getElementById('assignedTechnicians');

    const downloadPdfButton = document.getElementById('downloadPdfButton');
    const goToDashboardButton = document.getElementById('goToDashboardButton');

    const submitButton = document.getElementById('submitButton');
    const submitSpinner = document.getElementById('submitSpinner');
    const submitButtonText = document.getElementById('submitButtonText');

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
    let customersData = []; // <<< AÑADIDO: Para clientes

    let techSignaturePad, managerSignaturePad;

    // CONFIGURACIÓN DE FILEPOND
    FilePond.setOptions({
        labelIdle: 'Drag & Drop your images or <span class="filepond--label-action">Browse</span>',
        credits: false, allowMultiple: true, maxFiles: 5, acceptedFileTypes: ['image/*'],
        server: {
            process: {
                url: 'api/file-upload/process',
                fieldName: 'file', 
                ondata: (formData) => {
                    return formData;
                }
            },
            revert: {
                url: 'api/file-upload/revert',
                method: 'DELETE',
            },
            load: (source, load, error, progress, abort, headers) => {
                const projectRoot = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
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

    const pondBefore = FilePond.create(document.getElementById('photosBefore'));
    const pondAfter = FilePond.create(document.getElementById('photosAfter'));

    // Flatpickr Config
    setTimeout(() => {
        flatpickr("#serviceDate", { dateFormat: "Y-m-d", disableMobile: true });
        flatpickr("#startDate", { dateFormat: "Y-m-d H:i", enableTime: true, disableMobile: true });
        flatpickr("#endDate", { dateFormat: "Y-m-d H:i", enableTime: true, disableMobile: true });
    }, 100);

    // --- 2. INITIALIZATION FUNCTIONS ---
    function initSelect2() {
        // === INICIO DE MODIFICACIÓN: Inicializar Select2 para Clientes ===
        $('#customerName').select2({
            theme: "bootstrap-5",
            placeholder: "Select or type customer name...",
            tags: true // Permite añadir nuevos clientes
        });
        // === FIN DE MODIFICACIÓN ===

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

        if (!techCanvas || !managerCanvas) return;

        techSignaturePad = new SignaturePad(techCanvas, { penColor: "rgb(0, 0, 0)" });
        managerSignaturePad = new SignaturePad(managerCanvas, { penColor: "rgb(0, 0, 0)" });
        techSignaturePad.clear();
        managerSignaturePad.clear();

        if (signatureAccordion) {
            signatureAccordion.addEventListener('shown.bs.collapse', () => {
                setTimeout(() => {
                    resizeCanvas(techCanvas, techSignaturePad, 'techSignatureTempBase64');
                    resizeCanvas(managerCanvas, managerSignaturePad, 'managerSignatureTempBase64');
                }, 50);
            });
        }

        window.addEventListener("resize", () => {
            if (techSignaturePad) {
                resizeCanvas(techCanvas, techSignaturePad, 'techSignatureTempBase64');
                resizeCanvas(managerCanvas, managerSignaturePad, 'managerSignatureTempBase64');
            }
        });

        document.getElementById('clearTechSignature').addEventListener('click', () => {
            techSignaturePad.clear();
        });
        document.getElementById('clearManagerSignature').addEventListener('click', () => {
            managerSignaturePad.clear();
        });
    }

    // --- FUNCIÓN EMAILJS DE NOTIFICACIÓN DE CREACIÓN (Al Técnico) ---
    async function sendCreationNotification(formData) {
        const assignedTech = techniciansData.find(tech => tech.email === formData.assignToEmail);
        if (!assignedTech) {
            console.error("Error EmailJS: Detalles del técnico asignado no encontrados.");
            return false;
        }

        // === INICIO DE MODIFICACIÓN: Obtener texto del cliente ===
        const customerNameText = $('#customerName option:selected').text();
        // === FIN DE MODIFICACIÓN ===

        const templateParams = {
            title: `NEW WORK ORDER ASSIGNED: ${formData.workOrderNumber}`,
            to_name: assignedTech.full_name,
            to_email: assignedTech.email,
            wo_number: formData.workOrderNumber,
            customer_name: customerNameText, // <-- MODIFICADO
            service_date: formData.serviceDate,
            activity_description: formData.activityDescription || 'N/A',
            dispatcher_name: currentUserFullName || 'Admin',
        };

        try {
            const response = await emailjs.send(
                EMAILJS_SERVICE_ID,
                EMAILJS_CREATE_TEMPLATE_ID,
                templateParams
            );
            console.log('EmailJS SUCCESS (Creation)!', response.status, response.text);
            return true;
        } catch (error) {
            console.error('EmailJS FAILED (Creation)...', error);
            return false;
        }
    }

    // --- FUNCIÓN EMAILJS DE NOTIFICACIÓN DE ESTADO (Al Admin Creador) ---
    async function sendStatusNotification(orderData, adminEmail, adminName, technicianName) {

        if (!adminEmail) {
            console.error("Error EmailJS: Email del administrador no encontrado.");
            return false;
        }
        
        // === INICIO DE MODIFICACIÓN: Obtener texto del cliente ===
        const customerNameText = $('#customerName option:selected').text();
        // === FIN DE MODIFICACIÓN ===

        const templateParams = {
            title: `STATUS UPDATE: WO ${orderData.workOrderNumber}`,
            to_name: adminName,
            to_email: adminEmail,
            wo_number: orderData.workOrderNumber,
            customer_name: customerNameText, // <-- MODIFICADO
            service_date: orderData.serviceDate,
            technician_name: technicianName,
            new_status: orderData.workStage,
            work_description: orderData.workDescription || 'N/A',
        };

        try {
            const response = await emailjs.send(
                EMAILJS_SERVICE_ID,
                EMAILJS_STATUS_TEMPLATE_ID,
                templateParams
            );
            console.log('EmailJS Status SUCCESS!', response.status, response.text);
            return true;
        } catch (error) {
            console.error('EmailJS Status FAILED...', error);
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
                customersData = data.customers; // <<< AÑADIDO

                // Poblar Categorías
                categorySelect.innerHTML = '<option value=""></option>';
                for (const category in categoriesData) {
                    const option = new Option(category, category);
                    categorySelect.appendChild(option);
                }

                // === INICIO DE MODIFICACIÓN: Poblar Clientes ===
                customerSelect.innerHTML = '<option value=""></option>';
                if (customersData && customersData.length > 0) {
                    customersData.forEach(cust => {
                        const option = new Option(
                            cust.customer_name,  // Texto
                            cust.customer_id     // Valor (ID)
                        );
                        // Guardamos datos para autocompletar
                        option.setAttribute('data-city', cust.customer_city || '');
                        option.setAttribute('data-phone', cust.customer_phone || '');
                        option.setAttribute('data-type', cust.customer_type || '');
                        customerSelect.appendChild(option);
                    });
                }
                // === FIN DE MODIFICACIÓN ===

                // Poblar Técnicos
                assignToEmailSelect.innerHTML = '<option value=""></option>';
                assignedTechniciansSelect.innerHTML = '';
                techniciansData.forEach(tech => {
                    const responsibleOption = new Option(tech.full_name, tech.email);
                    assignToEmailSelect.appendChild(responsibleOption);
                    const involvedOption = new Option(tech.full_name, tech.user_id);
                    assignedTechniciansSelect.appendChild(involvedOption);
                });

                // Disparar 'change' en todos los Select2
                $(assignToEmailSelect).trigger('change');
                $(assignedTechniciansSelect).trigger('change');
                $(categorySelect).trigger('change');
                $(customerSelect).trigger('change'); // <<< AÑADIDO

            } else { alert('Error loading form data.'); }
        } catch (error) { console.error('Error fetching init data:', error); }
    }

    // Listener de Categoría
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

    // === INICIO DE MODIFICACIÓN: Listener de Cliente para Autocompletar ===
    $('#customerName').on('change', function (e) {
        // Si se está creando una 'tag' (cliente nuevo), limpiar campos
        if (e.params && e.params.data && e.params.data.created) {
            cityInput.value = '';
            phoneInput.value = '';
            customerTypeInput.value = '';
            return;
        }

        const selectedOption = $(this).find('option:selected');
        const city = selectedOption.data('city') || '';
        const phone = selectedOption.data('phone') || '';
        const type = selectedOption.data('type') || '';

        // Autocompletar campos
        cityInput.value = city;
        phoneInput.value = phone;
        customerTypeInput.value = type;
    });
    // === FIN DE MODIFICACIÓN ===


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
        //disableTechFields(); 

        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        document.getElementById('serviceDate').value = `${yyyy}-${mm}-${dd}`;

        collapseAdmin.show();
        collapseTech.hide();
        collapseSignatures.hide();

        if (downloadPdfButton) {
            downloadPdfButton.classList.add('d-none');
        }

        try {
            const response = await fetch('api/workorders/next-number');
            const data = await response.json();
            document.getElementById('workOrderNumber').value = data.success ? data.next_number : "Error-Gen";
        } catch (error) { console.error('Error fetching next WO number:', error); }
    }

    async function initUpdateForm(id) {
        console.log("Mode: UPDATE", id);
        await populateSelects(); // Poblar selects PRIMERO
        initSelect2();
        initSignaturePads();

        if (downloadPdfButton) {
            downloadPdfButton.classList.remove('d-none');
            downloadPdfButton.href = `api/workorders/pdf/${id}`;
        }

        try {
            const response = await fetch(`api/workorders/details/${id}`);
            const result = await response.json();

            if (result.success) {
                const data = result.data;
                
                // Cargar datos (esta función AHORA maneja el select de cliente)
                populateFormData(data);

                const techSignatureNameInput = document.getElementById('techSignatureName');
                const assignedTech = techniciansData.find(tech => tech.email === data.assign_to_email);

                if (currentUserRole.toLowerCase() === 'technician' && assignedTech &&
                    (techSignatureNameInput.value === '' || techSignatureNameInput.value === 'N/A')) {
                    techSignatureNameInput.value = assignedTech.full_name;
                }

                // Asegurar datos de solo lectura para notificación
                document.getElementById('workOrderNumber').value = data.work_order_number;
                document.getElementById('serviceDate').value = data.service_date;

                // Carga de firmas
                if (typeof techSignaturePad !== 'undefined') {
                    if (data.tech_signature_base64) {
                        techSignaturePad.fromDataURL(data.tech_signature_base64);
                    }
                    if (data.manager_signature_base64) {
                        managerSignaturePad.fromDataURL(data.manager_signature_base64);
                    }
                }
                
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
        adminButton.disabled = false;
        adminButton.classList.remove('disabled');

        // === INICIO DE MODIFICACIÓN: Deshabilitar campos de cliente ===
        $('#customerName').prop('disabled', true).trigger('change');
        cityInput.disabled = true;
        phoneInput.disabled = true;
        customerTypeInput.disabled = true;
        // === FIN DE MODIFICACIÓN ===

        document.getElementById('activityDescription').disabled = true;
        document.getElementById('totalCost').disabled = true;
        document.getElementById('serviceDate').disabled = true;

        $(categorySelect).prop('disabled', true).trigger('change');
        $(subcategorySelect).prop('disabled', true).trigger('change');
        $(assignToEmailSelect).prop('disabled', true).trigger('change');
    }

    // DESHABILITA CAMPOS DEL TÉCNICO (Sección 2 y 3)
    function disableTechFields() {
        // === INICIO DE MODIFICACIÓN: Habilitar campos de Admin ===
        $('#customerName').prop('disabled', false).trigger('change');
        cityInput.disabled = false;
        phoneInput.disabled = false;
        customerTypeInput.disabled = false;
        document.getElementById('activityDescription').disabled = false;
        document.getElementById('totalCost').disabled = false;
        document.getElementById('serviceDate').disabled = false;
        $(categorySelect).prop('disabled', false).trigger('change');
        $(assignToEmailSelect).prop('disabled', false).trigger('change');
        // (Subcategoría se maneja por su listener)
        // === FIN DE MODIFICACIÓN ===
        
        // Deshabilitar campos de Técnico
        document.querySelectorAll('input[name="workStage"]').forEach(el => el.disabled = true);
        document.getElementById('materials').disabled = true;
        document.getElementById('workDescription').disabled = true;
        pondBefore.disabled = true;
        pondAfter.disabled = true;
        document.getElementById('startDate').disabled = true;
        document.getElementById('endDate').disabled = true;
        document.getElementById('totalHours').disabled = true;
        document.querySelectorAll('input[name="estimatedDuration"]').forEach(el => el.disabled = true);
        document.getElementById('workAfter5PM').disabled = true;
        document.getElementById('workWeekend').disabled = true;
        document.getElementById('isEmergency').disabled = true;
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

        // === INICIO DE MODIFICACIÓN: Poblar Select2 de Cliente ===
        const customerNameSelect = $('#customerName');
        const customerId = data.customer_id;
        const customerName = data.customer_name;
        
        if (customerId && customerName) {
            // Comprobamos si el cliente (por ID) ya existe en la lista
            if (customerNameSelect.find(`option[value="${customerId}"]`).length) {
                // Si existe, lo seleccionamos
                customerNameSelect.val(customerId).trigger('change');
            } else {
                // Si no existe, lo creamos y seleccionamos
                const newOption = new Option(customerName, customerId, true, true);
                newOption.setAttribute('data-city', data.customer_city || '');
                newOption.setAttribute('data-phone', data.customer_phone || '');
                newOption.setAttribute('data-type', data.customer_type || '');
                customerNameSelect.append(newOption).trigger('change');
            }
        }
        
        // Poblamos los campos de autocompletado
        cityInput.value = data.customer_city;
        phoneInput.value = data.customer_phone;
        customerTypeInput.value = data.customer_type;
        // === FIN DE MODIFICACIÓN ===

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
                .map(p => ({ source: p.file_path, options: { type: 'local' } }));

            const filesAfter = data.photos
                .filter(p => p.photo_type === 'after')
                .map(p => ({ source: p.file_path, options: { type: 'local' } }));

            pondBefore.setOptions({ files: filesBefore });
            pondAfter.setOptions({ files: filesAfter });
        }

        // Carga de firmas
        if (data.tech_signature_base64 && techSignaturePad) {
            try {
                techSignaturePad.fromDataURL(data.tech_signature_base64);
            } catch (e) { console.error("Error loading tech signature:", e); }
        }
        if (data.manager_signature_base64 && managerSignaturePad) {
            try {
                managerSignaturePad.fromDataURL(data.manager_signature_base64);
            } catch (e) { console.error("Error loading manager signature:", e); }
        }
    }


    // --- 6. FORM SUBMISSION ---
    workOrderForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        let isFormValid = true;
        const categoryVal = $('#category').val();
        const assignToEmailVal = $('#assignToEmail');
        
        // === INICIO DE MODIFICACIÓN: Validar Select2 de Cliente ===
        const customerVal = $('#customerName').val();
        $('.select2-container').removeClass('is-invalid');
        // === FIN DE MODIFICACIÓN ===

        if (!workOrderForm.checkValidity()) {
            isFormValid = false;
            workOrderForm.classList.add('was-validated');
        }
        if (!categoryVal) {
            isFormValid = false;
            $('#category').next('.select2-container').addClass('is-invalid');
        }
        
        // === INICIO DE MODIFICACIÓN: Validar Select2 de Cliente ===
        if (!customerVal) {
            isFormValid = false;
            $('#customerName').next('.select2-container').addClass('is-invalid');
        }
        // === FIN DE MODIFICACIÓN ===
        
        const workOrderId = getWorkOrderIdFromUrl();
        if (!workOrderId && !assignToEmailVal.val()) {
            isFormValid = false;
            assignToEmailVal.next('.select2-container').addClass('is-invalid');
        }

        if (!isFormValid) {
            e.stopPropagation();
            return;
        }

        toggleSubmissionState(true);

        const formData = new FormData(workOrderForm);
        const data = Object.fromEntries(formData.entries());

        // === INICIO DE MODIFICACIÓN: Recolección de Datos de Cliente ===
        const customerSelectValue = $('#customerName').val();
        
        if (isNaN(customerSelectValue)) {
            // Es una 'tag' nueva (texto)
            data.customer_id = null; 
            data.customer_name_new = customerSelectValue; // El valor es el nombre
        } else {
            // Es un cliente existente (ID)
            data.customer_id = customerSelectValue;
            data.customer_name_new = null;
        }
        
        // Enviamos los campos de autocompletado también
        data.customer_city = cityInput.value;
        data.customer_phone = phoneInput.value;
        data.customer_type = customerTypeInput.value;
        // (El campo 'customerName' del <select> se sobreescribe, por eso usamos customer_id y customer_name_new)
        // === FIN DE MODIFICACIÓN ===

        data.workStage = document.querySelector('input[name="workStage"]:checked')?.value;
        data.estimatedDuration = document.querySelector('input[name="estimatedDuration"]:checked')?.value;
        data.workAfter5PM = document.getElementById('workAfter5PM').checked;
        data.workWeekend = document.getElementById('workWeekend').checked;
        data.isEmergency = document.getElementById('isEmergency').checked;
        data.category = categoryVal;
        data.assignToEmail = assignToEmailVal.val();
        data.subcategory = $('#subcategory').val();
        data.assignedTechnicians = $('#assignedTechnicians').val();

        data.photosBefore = pondBefore.getFiles().map(file => file.serverId || file.source);
        data.photosAfter = pondAfter.getFiles().map(file => file.serverId || file.source);

        data.tech_signature_base64 = techSignaturePad.isEmpty() ? null : techSignaturePad.toDataURL('image/png');
        data.manager_signature_base64 = managerSignaturePad.isEmpty() ? null : managerSignaturePad.toDataURL('image/png');

        // === INICIO DE MODIFICACIÓN: Recolectar solo campos de admin NO-cliente en modo update ===
        if (workOrderId) {
            // (Los campos de cliente ya se recolectaron arriba)
            data.activityDescription = document.getElementById('activityDescription').value;
            data.totalCost = document.getElementById('totalCost').value;
            data.serviceDate = document.getElementById('serviceDate').value;
            data.workOrderNumber = document.getElementById('workOrderNumber').value;
        }
        // === FIN DE MODIFICACIÓN ===

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

                // Lógica de Email (sin cambios, ya se corrigió arriba)
                if (!workOrderId) {
                    const techEmail = techniciansData.find(tech => tech.email === data.assignToEmail)?.email;
                    if (techEmail) {
                        mailSent = await sendCreationNotification(data);
                        if (!mailSent) message += " (WARNING: Failed to send email notification to technician.)";
                    } else {
                        message += " (WARNING: Technician email not found for notification.)";
                    }
                } else if (workOrderId && currentUserRole.toLowerCase() === 'technician') {
                    const adminEmail = result.adminEmail;
                    const adminName = result.adminName;
                    const technicianName = currentUserFullName;
                    if (adminEmail && data.workStage) {
                        mailSent = await sendStatusNotification(data, adminEmail, adminName, technicianName);
                        if (!mailSent) message += " (WARNING: Failed to send status notification to Admin.)";
                    }
                }
                
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

            } else {
                alert(`Error: ${result.message}`);
            }

        } catch (error) {
            console.error('Error submitting form:', error);
            alert('A network error occurred.');
        } finally {
            toggleSubmissionState(false);
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
                // === INICIO DE MODIFICACIÓN: Esta función ahora está correcta ===
                disableTechFields(); 
                // === FIN DE MODIFICACIÓN ===
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
                let redirectUrl = 'login.html';
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