document.addEventListener('DOMContentLoaded', () => {
    // --- FILEPOND CONFIGURATION ---
    // Register the Image Preview plugin if needed (for more advanced previews)
    // FilePond.registerPlugin(FilePondPluginImagePreview); 

    // Set FilePond options (global settings for a cleaner look)
    FilePond.setOptions({
        labelIdle: 'Drag & Drop your images or <span class="filepond--label-action">Browse</span>',
        labelInvalidField: 'Field contains invalid files',
        labelFileWaitingForSize: 'Waiting for size',
        labelFileSizeNotAvailable: 'Size not available',
        labelTapToCancel: 'tap to cancel',
        labelTapToRetry: 'tap to retry',
        labelTapToUndo: 'tap to undo',
        labelButtonRemoveItem: 'Remove',
        labelButtonAbortItemLoad: 'Abort',
        labelButtonRetryItemLoad: 'Retry',
        labelButtonUndoItemLoad: 'Undo',
        labelButtonAbortItemProcessing: 'Cancel upload',
        labelButtonUndoItemProcessing: 'Undo upload',
        labelButtonRetryItemProcessing: 'Retry upload',
        labelButtonProcessItem: 'Upload',
        credits: false, // Opcional: Remueve el 'Powered by FilePond'
        allowMultiple: true,
        maxFiles: 5, // Límite de 5 imágenes por sección
        acceptedFileTypes: ['image/*'],
    });

    // Initialize FilePond instances
    const pondBefore = FilePond.create(document.getElementById('photosBefore'));
    const pondAfter = FilePond.create(document.getElementById('photosAfter'));
    // --- 1. INITIALIZE LIBRARIES (Flatpickr) ---
    const defaultDate = new Date();
    const futureDate = new Date(defaultDate.getTime() + 24 * 60 * 60 * 1000); // 24 hours later

    flatpickr("#serviceDate", {
        dateFormat: "Y-m-d",
        defaultDate: defaultDate
    });
    flatpickr("#startDate", {
        dateFormat: "Y-m-d H:i",
        enableTime: true,
        defaultDate: defaultDate
    });
    flatpickr("#endDate", {
        dateFormat: "Y-m-d H:i",
        enableTime: true,
        defaultDate: futureDate
    });
    flatpickr("#signatureDate", {
        dateFormat: "Y-m-d",
        defaultDate: defaultDate
    });
    flatpickr("#techSignatureTime", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        defaultDate: defaultDate
    });

    // --- 2. DATA STRUCTURE BASED ON CSV ---
    const categoriesData = {
        "Roofing": [],
        "Siding": [],
        "Windows": [],
        "Flooring": [],
        "Plumbing": ["Drains Cleaning", "Water pipes", "Sewer Repair", "Water heaer"],
        "Electrical": ["Exterior Lighting", "Interior Lighting"],
        "HVAC and Heating": [],
        "Kitchen equipment": [],
        "Refrigeration": [],
        "Special Services": ["Emergency", "Emergency clean up"],
        "Painting": ["Exterior", "Interior", "Canopy"],
        "Line Stripes": [],
        "Paving": [],
        "Concret": [],
        "Escavation": [],
        "Demolition": [],
        "Desposal": [],
        "Presure Washing": [],
        "Floor Cleaning": [],
        "Floor Repair": [],
        "Tree Removals": [],
        "Landscaping": [],
        "Building Glass": [],
        "Locksmith": [],
        "Septic System Pum out": [],
        "Spill Groves": [],
        "Maintenance and Repairs": [],
        "Sump": [],
        "Inspections": ["Exterior", "Interior"],
        "Gathers": ["Cleaning", "Instalation", "Repair"],
        "Fire Suppression System": ["Maintenance", "Emergency Clean up"],
        "Fencing": ["Instalation", "Repair", "Wooding", "Vinyl", "Chain Link Fence"],
        "Dumpster and Closure": ["Vynil", "wooding", "Chain Link"],
        "Snow Removal": ["Outside Removal", "Emergency sidewalk opening", "De icing", "Emergency snow removal from roof", "Canopy equipment"],
        "Mold Removal": [],
        "Pest Control": [],
        "Equipment pick up and Relocation": []
    };

    const techniciansData = [
        { id: "tech1", name: "Juan Perez" },
        { id: "tech2", name: "Martin Diaz" },
        { id: "tech3", name: "Carlos Lopez" },
        { id: "tech4", name: "Ana Garcia" },
        { id: "tech5", name: "Luis Ramirez" }
    ];

    const categorySelect = document.getElementById('category');
    const subcategorySelect = document.getElementById('subcategory');
    const assignedTechniciansSelect = document.getElementById('assignedTechnicians');
    const workOrderForm = document.getElementById('workOrderForm');

    // --- 3. DYNAMIC SELECT POPULATION ---

    // Load Categories
    for (const category in categoriesData) {
        const option = document.createElement('option');
        option.value = category;
        option.textContent = category;
        categorySelect.appendChild(option);
    }

    // Select a default category for the example data
    categorySelect.value = "Plumbing";

    // Load Subcategories based on selected Category
    categorySelect.addEventListener('change', () => {
        const selectedCategory = categorySelect.value;
        subcategorySelect.innerHTML = '<option value="">Select a Subcategory</option>';
        subcategorySelect.disabled = true;
        subcategorySelect.removeAttribute('required'); // Remove required if no subcategories exist

        if (selectedCategory && categoriesData[selectedCategory]) {
            const subcategories = categoriesData[selectedCategory];

            if (subcategories.length > 0) {
                subcategories.forEach(subcategory => {
                    const option = document.createElement('option');
                    option.value = subcategory;
                    option.textContent = subcategory;
                    subcategorySelect.appendChild(option);
                });
                subcategorySelect.disabled = false;
                subcategorySelect.setAttribute('required', 'required'); // Add required back
                // Select a default subcategory for the example
                if (selectedCategory === "Plumbing") {
                    subcategorySelect.value = "Water pipes";
                }
            }
        }
    });

    // Load Technicians
    techniciansData.forEach(tech => {
        const option = document.createElement('option');
        option.value = tech.id;
        option.textContent = tech.name;
        assignedTechniciansSelect.appendChild(option);
    });
    // Select default technicians for the example
    ['tech1', 'tech3'].forEach(techId => {
        const option = assignedTechniciansSelect.querySelector(`option[value="${techId}"]`);
        if (option) {
            option.selected = true;
        }
    });

    // Trigger change for initial subcategory load
    categorySelect.dispatchEvent(new Event('change'));

    // --- 4. FORM SUBMISSION AND RESET ---

    workOrderForm.addEventListener('submit', (e) => {
        e.preventDefault();

        // Manual validation check (in case novalidate is used or for extra logic)
        if (!workOrderForm.checkValidity()) {
            e.stopPropagation();
            workOrderForm.classList.add('was-validated');
            return;
        }

        const formData = new FormData(workOrderForm);
        const data = {};
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }

        // Get non-standard form data
        data.workStage = document.querySelector('input[name="workStage"]:checked').value;
        data.estimatedDuration = document.querySelector('input[name="estimatedDuration"]:checked').value;
        data.workAfter5PM = document.getElementById('workAfter5PM').checked;
        data.workWeekend = document.getElementById('workWeekend').checked;
        data.assignedTechnicians = Array.from(assignedTechniciansSelect.selectedOptions).map(option => option.textContent);

        // Handle file inputs (just logging filenames for simplicity)
        data.photosBefore = pondBefore.getFiles().map(fileItem => fileItem.file.name);
        data.photosAfter = pondAfter.getFiles().map(fileItem => fileItem.file.name);


        console.log("Work Order Data:", data);
        alert("Work Order generated successfully. Check the console for data.");
    });

    document.getElementById('resetFormButton').addEventListener('click', () => {
        workOrderForm.reset();
        workOrderForm.classList.remove('was-validated');

        // Re-set initial values for better UX (dates, selects, radios)
        flatpickr("#serviceDate").setDate(defaultDate);
        flatpickr("#startDate").setDate(defaultDate);
        flatpickr("#endDate").setDate(futureDate);
        flatpickr("#signatureDate").setDate(defaultDate);
        flatpickr("#techSignatureTime").setDate(defaultDate);

        // Re-set select and trigger change
        categorySelect.value = "Plumbing";
        categorySelect.dispatchEvent(new Event('change'));

        // Re-set technician selection
        Array.from(assignedTechniciansSelect.options).forEach(option => option.selected = false);
        ['tech1', 'tech3'].forEach(techId => {
            const option = assignedTechniciansSelect.querySelector(`option[value="${techId}"]`);
            if (option) option.selected = true;
        });

        // Re-set radios and switches
        document.getElementById('stageInProgress').checked = true;
        document.getElementById('duration24').checked = true;
        document.getElementById('workAfter5PM').checked = true;
        document.getElementById('workWeekend').checked = false;

        // Re-set text data
        document.getElementById('workOrderNumber').value = "WO-2024-001";
        document.getElementById('customerName').value = "7-Eleven store#";
        document.getElementById('city').value = "Boxford";
        document.getElementById('phoneNumber').value = "781-555-1234";
        document.getElementById('customerType').value = "Gas Station";
        document.getElementById('materials').value = "Electric Pipes & PVC pipes, elbows, tees, couplings";
        document.getElementById('workDescription').value = "Installation and review of electrical and PVC piping system at the service station.";
        document.getElementById('totalHours').value = "24";
        document.getElementById('totalCost').value = "1500.00";
        document.getElementById('managerSignature').value = "John Doe";
        document.getElementById('techSignature').value = "Juan Perez";
        document.getElementById('assignToEmail').value = "alanavidsilva@yahoo.com.mx";
    });
});