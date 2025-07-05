document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.ownership-form');
    const plateInput = form.querySelector('[name="plate_number"]');
    const licenceInput = form.querySelector('[name="licence_number"]');
    
    // Store original values for detecting changes
    const originalValues = {};
    form.querySelectorAll('input').forEach(field => {
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
        field.style.borderColor = isReadOnly ? 'var(--line-clr)' : '';
        field.style.cursor = isReadOnly ? 'not-allowed' : 'text';
    }

    // Show error message
    function showError(input, message) {
        removeError(input);
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

    // Remove any existing message
    function removeMessage(parentElement) {
        const existingMessage = parentElement.querySelector('.hint-text:not(:first-child)');
        if (existingMessage) {
            existingMessage.remove();
        }
    }

    // Handle vehicle fields
    function handleVehicleFields(makeEditable, clearValues = true) {
        const fields = ['make', 'model', 'colour'];
        fields.forEach(fieldName => {
            const field = form.querySelector(`[name="${fieldName}"]`);
            setFieldState(field, !makeEditable);
            if (clearValues) {
                field.value = '';
            }
        });
    }

    // Handle owner fields
    function handleOwnerFields(makeEditable, clearValues = true) {
        const fields = ['owner_name', 'owner_address'];
        fields.forEach(fieldName => {
            const field = form.querySelector(`[name="${fieldName}"]`);
            setFieldState(field, !makeEditable);
            if (clearValues) {
                field.value = '';
            }
        });
    }

    // Track modified fields
    function trackModification(field) {
        if (field.value !== originalValues[field.name]) {
            field.classList.add('modified');
        } else {
            field.classList.remove('modified');
        }
    }

    // Validate vehicle details for new vehicles
    function validateVehicleDetails() {
        const fields = ['make', 'model', 'colour'];
        const vehicleFields = fields.map(field => form.querySelector(`[name="${field}"]`));
        
        // Only validate if fields are editable (new vehicle)
        if (!vehicleFields[0].readOnly) {
            for (let i = 0; i < fields.length; i++) {
                if (!vehicleFields[i].value.trim()) {
                    showError(vehicleFields[i], `${fields[i].charAt(0).toUpperCase() + fields[i].slice(1)} is required for new vehicles`);
                    return false;
                }
                removeError(vehicleFields[i]);
            }
        }
        return true;
    }

    // Validate owner details for new owners
    function validateOwnerDetails() {
        const nameField = form.querySelector('[name="owner_name"]');
        
        // Only validate if fields are editable (new owner)
        if (!nameField.readOnly && !nameField.value.trim()) {
            showError(nameField, 'Name is required for new owners');
            return false;
        }
        removeError(nameField);
        return true;
    }

    // Event Listeners
    plateInput.addEventListener('input', function(e) {
        handleUppercase(e.target);
        trackModification(e.target);
        handleVehicleFields(true); // Make editable and clear
        removeMessage(this.parentNode);
        removeError(this);
    });
    
    licenceInput.addEventListener('input', function(e) {
        handleUppercase(e.target);
        trackModification(e.target);
        handleOwnerFields(true); // Make editable and clear
        removeMessage(this.parentNode);
        removeError(this);
    });

    // Handle plate changes and lookups
    plateInput.addEventListener('blur', async function() {
        const plate = this.value.trim();
        removeError(this);
        
        if (!plate) {
            handleVehicleFields(true);
            removeMessage(this.parentNode);
            return;
        }
        
        if (plate.length !== 6) {
            showError(this, 'Plate number must be exactly 6 characters');
            handleVehicleFields(true);
            return;
        }

        removeMessage(this.parentNode);
        try {
            const response = await fetch(`?action=fetch_vehicle&plate=${encodeURIComponent(plate)}`);
            const data = await response.json();
            
            if (data.exists) {
                // Fill vehicle details
                form.querySelector('[name="make"]').value = data.make;
                form.querySelector('[name="model"]').value = data.model;
                form.querySelector('[name="colour"]').value = data.colour;
                handleVehicleFields(false, false); // Make readonly, don't clear

                // Show current owner if exists
                if (data.currentOwner.licence) {
                    const message = document.createElement('div');
                    message.className = 'hint-text';
                    message.textContent = `Current owner: ${data.currentOwner.name} (${data.currentOwner.licence})`;
                    this.parentNode.appendChild(message);
                }
            } else {
                handleVehicleFields(true); // Make editable and clear
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
        
        if (!licence) {
            handleOwnerFields(true);
            return;
        }

        if (licence.length > 20) {
            showError(this, 'License number cannot exceed 20 characters');
            handleOwnerFields(true);
            return;
        }

        try {
            const response = await fetch(`?action=fetch_person&licence=${encodeURIComponent(licence)}`);
            const data = await response.json();
            
            if (data.exists) {
                // Fill owner details
                form.querySelector('[name="owner_name"]').value = data.name;
                form.querySelector('[name="owner_address"]').value = data.address;
                handleOwnerFields(false, false); // Make readonly, don't clear
            } else {
                handleOwnerFields(true); // Make editable and clear
            }
        } catch (error) {
            console.error('Error:', error);
            showError(this, 'Error fetching person details');
        }
    });

    // Form submission validation
    form.addEventListener('submit', function(e) {
        let hasError = false;
        const plate = plateInput.value.trim();
        const licence = licenceInput.value.trim();

        // Remove all existing errors first
        form.querySelectorAll('.error-message').forEach(error => error.remove());

        // Validate plate number
        if (!plate) {
            showError(plateInput, 'Plate number is required');
            hasError = true;
        } else if (plate.length !== 6) {
            showError(plateInput, 'Plate number must be exactly 6 characters');
            hasError = true;
        }

        // Validate licence number
        if (!licence) {
            showError(licenceInput, 'License number is required');
            hasError = true;
        } else if (licence.length > 20) {
            showError(licenceInput, 'License number cannot exceed 20 characters');
            hasError = true;
        }

        // Validate vehicle details if it's a new vehicle
        if (!hasError && !validateVehicleDetails()) {
            hasError = true;
        }

        // Validate owner details if it's a new owner
        if (!hasError && !validateOwnerDetails()) {
            hasError = true;
        }

        if (hasError) {
            e.preventDefault();
            window.scrollTo(0, 0);
        }
    });
});