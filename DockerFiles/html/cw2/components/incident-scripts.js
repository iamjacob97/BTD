document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.incident-form');
    const plateInput = form.querySelector('[name="vehicle_plate"]');
    const licenceInput = form.querySelector('[name="licence_number"]');
    
    // Store original values for detecting changes
    const originalValues = {};
    form.querySelectorAll('input, select, textarea').forEach(field => {
        originalValues[field.name] = field.value;
    });

    // Handle uppercase conversion
    function handleUppercase(input) {
        input.value = input.value.toUpperCase();
    }

    // Set field state
    function setFieldState(field, isReadOnly) {
        field.readOnly = isReadOnly;
        field.style.backgroundColor = isReadOnly ? 'var(--hover-clr)' : 'var(--base-clr)';
        field.style.borderColor = 'var(--line-clr)';
        field.style.cursor = isReadOnly ? 'not-allowed' : 'text';
    }

    // Show error message
    function showError(input, message) {
        const existingError = input.parentNode.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = message;
        errorDiv.style.color = '#ff4444';
        errorDiv.style.fontSize = '0.85rem';
        errorDiv.style.marginTop = '0.25rem';
        input.parentNode.appendChild(errorDiv);
    }

    // Remove error message
    function removeError(input) {
        const existingError = input.parentNode.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }
    }

    // Check if fields are in editable state
    function isFieldSetEditable(fields, prefix) {
        const firstField = form.querySelector(`[name="${prefix}${fields[0]}"]`);
        return !firstField.readOnly;
    }

    // Handle vehicle fields
    function handleVehicleFields(makeEditable) {
        const fields = ['make', 'model', 'colour'];
        fields.forEach(fieldName => {
            const field = form.querySelector(`[name="vehicle_${fieldName}"]`);
            if (makeEditable) {
                field.value = '';
                setFieldState(field, false); // Make editable
            } else {
                setFieldState(field, true); // Make readonly
            }
        });
    }

    // Handle person fields
    function handlePersonFields(makeEditable) {
        const fields = ['name', 'address'];
        fields.forEach(fieldName => {
            const field = form.querySelector(`[name="person_${fieldName}"]`);
            if (makeEditable) {
                field.value = '';
                setFieldState(field, false); // Make editable
            } else {
                setFieldState(field, true); // Make readonly
            }
        });
    }

    // Remove any existing message
    function removeMessage(parentElement) {
        const existingMessage = parentElement.querySelector('.hint-text');
        if (existingMessage) {
            existingMessage.remove();
        }
    }

    // Track modified fields
    function trackModification(field) {
        if (field.value !== originalValues[field.name]) {
            field.classList.add('modified');
        } else {
            field.classList.remove('modified');
        }
    }

    // Validate vehicle details
    function validateVehicleDetails() {
        const fields = ['make', 'model', 'colour'];
        const vehicleFields = fields.map(field => form.querySelector(`[name="vehicle_${field}"]`));
        
        if (isFieldSetEditable(fields, 'vehicle_')) {
            // Check if any field is empty
            for (let i = 0; i < fields.length; i++) {
                if (!vehicleFields[i].value.trim()) {
                    showError(vehicleFields[i], `Vehicle ${fields[i]} is required for new vehicles`);
                    return false;
                }
                removeError(vehicleFields[i]);
            }
        }
        return true;
    }

    // Validate person details
    function validatePersonDetails() {
        const nameField = form.querySelector('[name="person_name"]');
        
        if (isFieldSetEditable(['name', 'address'], 'person_')) {
            if (!nameField.value.trim()) {
                showError(nameField, 'Person name is required for new people');
                return false;
            }
            removeError(nameField);
        }
        return true;
    }

    // Initialize field states on page load
    function initializeFieldStates() {
        if (plateInput.value.trim()) {
            handleVehicleFields(false);
        }
        if (licenceInput.value.trim()) {
            handlePersonFields(false);
        }
    }

    // Validate plate number format
    function validatePlate(plate) {
        return plate === '' || plate.length === 6;
    }

    // Validate licence number format
    function validateLicence(licence) {
        return licence === '' || licence.length <= 20;
    }

    // Event Listeners
    plateInput.addEventListener('input', function(e) {
        handleUppercase(e.target);
        trackModification(e.target);
        handleVehicleFields(true);
        removeMessage(this.parentNode);
    });
    
    licenceInput.addEventListener('input', function(e) {
        handleUppercase(e.target);
        trackModification(e.target);
        handlePersonFields(true);
        removeMessage(this.parentNode);
    });

    // Handle plate changes and lookups
    plateInput.addEventListener('blur', async function() {
        const plate = this.value.trim();
        removeError(this);
        
        if (plate === '') {
            handleVehicleFields(true);
            removeMessage(this.parentNode);
            return;
        }
        
        if (!validatePlate(plate)) {
            showError(this, 'Plate number must be exactly 6 characters');
            return;
        }

        removeMessage(this.parentNode);
        try {
            const response = await fetch(`?action=fetch_vehicle&plate=${encodeURIComponent(plate)}`);
            const data = await response.json();
            
            if (data.exists) {
                // Fill vehicle details
                form.querySelector('[name="vehicle_make"]').value = data.make;
                form.querySelector('[name="vehicle_model"]').value = data.model;
                form.querySelector('[name="vehicle_colour"]').value = data.colour;
                handleVehicleFields(false);

                if (data.currentOwner.licence) {
                    const message = document.createElement('div');
                    message.className = 'hint-text';
                    message.textContent = `Current owner: ${data.currentOwner.name} (${data.currentOwner.licence})`;
                    this.parentNode.appendChild(message);
                }
            } else {
                handleVehicleFields(true);
            }
        } catch (error) {
            console.error('Error:', error);
            showError(this, 'Error fetching vehicle details');
        }
    });

    // Handle licence changes and lookups
    licenceInput.addEventListener('blur', async function() {
        const licence = this.value.trim();
        removeError(this);
        
        if (licence === '') {
            handlePersonFields(true);
            removeMessage(this.parentNode);
            return;
        }
        
        if (!validateLicence(licence)) {
            showError(this, 'License number cannot exceed 20 characters');
            return;
        }

        removeMessage(this.parentNode);
        try {
            const response = await fetch(`?action=fetch_person&licence=${encodeURIComponent(licence)}`);
            const data = await response.json();
            
            if (data.exists) {
                form.querySelector('[name="person_name"]').value = data.name;
                form.querySelector('[name="person_address"]').value = data.address;
                handlePersonFields(false);
            } else {
                handlePersonFields(true);
            }
        } catch (error) {
            console.error('Error:', error);
            showError(this, 'Error fetching person details');
        }
    });

    // Form submission validation
    form.addEventListener('submit', function(e) {
        const plate = plateInput.value.trim();
        const licence = licenceInput.value.trim();
        let hasError = false;

        // Validate plate if provided
        if (plate) {
            if (!validatePlate(plate)) {
                showError(plateInput, 'Plate number must be exactly 6 characters');
                hasError = true;
            } else if (!validateVehicleDetails()) {
                hasError = true;
            }
        }

        // Validate licence if provided
        if (licence) {
            if (!validateLicence(licence)) {
                showError(licenceInput, 'License number cannot exceed 20 characters');
                hasError = true;
            } else if (!validatePersonDetails()) {
                hasError = true;
            }
        }

        if (hasError) {
            e.preventDefault();
            window.scrollTo(0, 0);
        }
    });

    // Initialize field states on page load
    initializeFieldStates();
});