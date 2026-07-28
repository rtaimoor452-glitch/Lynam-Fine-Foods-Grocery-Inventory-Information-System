/**
 * Lynam Fine Foods Inventory System - Frontend JavaScript
 * 
 * This file handles all frontend functionality using vanilla JavaScript:
 * 
 * 1. CRUD Operations - Uses the Fetch API to communicate with the PHP backend
 * 2. DOM Manipulation - Updates the HTML page without full-page refreshes
 * 3. Form Handling   - Manages the add/edit/cancel form workflow
 * 4. Search & Filter - Filters and sorts products on the client side
 * 5. Summary Stats   - Calculates and displays inventory statistics
 * 6. Validation      - Validates input before sending to the API
 * 7. Messages        - Shows success and error messages to the user
 * 
 * Architecture:
 *   User interacts with HTML form/buttons
 *   → JavaScript captures the event
 *   → Fetch API sends HTTP request to PHP API
 *   → PHP processes request and queries SQLite
 *   → API returns JSON response
 *   → JavaScript updates the DOM with the response data
 */

// ============================================================
// CONFIGURATION
// ============================================================

// The URL of the PHP API endpoint
// All CRUD operations go through this single endpoint
var API_URL = 'api/products.php';

// Products with quantity below this number trigger a low-stock warning
var LOW_STOCK_THRESHOLD = 5;

// Maximum allowed values for validation
var MAX_PRICE = 99999;
var MAX_QUANTITY = 99999;
var MAX_NAME_LENGTH = 100;
var MAX_SUPPLIER_LENGTH = 100;

// Store all products loaded from the API
// This array is used for client-side search, filter and sort
var allProducts = [];

// Track whether the form is in "edit" mode or "add" mode
var isEditing = false;

// Track whether an API request is currently in progress
// This prevents duplicate submissions if the user clicks quickly
var isRequestInProgress = false;

// ============================================================
// INITIALISATION
// ============================================================

/**
 * When the page finishes loading, set up all event listeners
 * and load the products from the API
 * 
 * DOMContentLoaded fires when the HTML has been fully parsed,
 * so all elements are available for JavaScript to reference
 */
document.addEventListener('DOMContentLoaded', function () {

    // Load all products from the API when the page opens
    loadProducts();

    // Listen for form submission (both add and edit)
    document.getElementById('product-form').addEventListener('submit', handleFormSubmit);

    // Listen for the Cancel Edit button click
    document.getElementById('cancel-btn').addEventListener('click', cancelEdit);

    // Listen for changes in search, filter and sort controls
    // 'input' fires on every keystroke, 'change' fires when a dropdown selection changes
    document.getElementById('search-input').addEventListener('input', applyFilters);
    document.getElementById('filter-category').addEventListener('change', applyFilters);
    document.getElementById('sort-by').addEventListener('change', applyFilters);
});

// ============================================================
// API FUNCTIONS (Fetch API)
// ============================================================

/**
 * Show the loading indicator and disable form buttons
 * 
 * This provides visual feedback to the user that an API
 * request is in progress and prevents duplicate submissions.
 */
function showLoading() {
    isRequestInProgress = true;
    var loadingIndicator = document.getElementById('loading-indicator');
    if (loadingIndicator) {
        loadingIndicator.style.display = 'flex';
    }
    setFormButtonsDisabled(true);
}

/**
 * Hide the loading indicator and re-enable form buttons
 * 
 * Called after an API request completes (success or failure).
 */
function hideLoading() {
    isRequestInProgress = false;
    var loadingIndicator = document.getElementById('loading-indicator');
    if (loadingIndicator) {
        loadingIndicator.style.display = 'none';
    }
    setFormButtonsDisabled(false);
}

/**
 * Enable or disable the form submit and cancel buttons
 * 
 * Disabling buttons during API requests prevents the user
 * from submitting the same data twice.
 * 
 * @param {boolean} disabled - true to disable, false to enable
 */
function setFormButtonsDisabled(disabled) {
    var submitBtn = document.getElementById('submit-btn');
    var cancelBtn = document.getElementById('cancel-btn');

    if (submitBtn) {
        submitBtn.disabled = disabled;
        submitBtn.textContent = disabled ? 'Please Wait...' :
            (isEditing ? 'Update Product' : 'Add Product');
    }
    if (cancelBtn) {
        cancelBtn.disabled = disabled;
    }
}

/**
 * Load all products from the API
 * 
 * Makes a GET request to: api/products.php
 * The API returns a JSON array of all products
 * 
 * After loading, we store the products, apply any active
 * filters, and update the summary statistics
 */
function loadProducts() {
    showLoading();

    fetch(API_URL)
        .then(function (response) {
            // Check if the response was successful (status 200-299)
            if (!response.ok) {
                throw new Error('Failed to load products (HTTP ' + response.status + ')');
            }
            // Parse the JSON response body
            return response.json();
        })
        .then(function (products) {
            // Store all products for filtering and sorting
            allProducts = products;

            // Apply any active search/filter/sort and display the products
            applyFilters();

            // Update the summary statistics (totals, low stock count)
            updateSummary();
        })
        .catch(function (error) {
            // Show an error message if the request fails
            showMessage('Error loading products: ' + error.message, 'error');
        })
        .finally(function () {
            // Always hide the loading indicator when done
            hideLoading();
        });
}

/**
 * Create a new product
 * 
 * Makes a POST request to: api/products.php
 * Sends the product data as JSON in the request body
 * 
 * @param {Object} productData - The product data from the form
 */
function createProductAPI(productData) {
    // Prevent duplicate submissions
    if (isRequestInProgress) {
        return;
    }
    showLoading();

    fetch(API_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(productData)
    })
        .then(function (response) {
            // Parse the JSON response (we need it for both success and error)
            return response.json().then(function (data) {
                return { ok: response.ok, status: response.status, body: data };
            });
        })
        .then(function (result) {
            if (!result.ok) {
                // The API returned a validation error or other error
                if (result.body.details) {
                    throw new Error(result.body.details.join(', '));
                }
                throw new Error(result.body.error || 'Failed to create product');
            }

            // Success - show message, reset the form, and reload products
            showMessage('Product created successfully!', 'success');
            resetForm();
            loadProducts();

            // Move focus to the message area so screen readers announce success
            focusMessageArea();
        })
        .catch(function (error) {
            showMessage('Error: ' + error.message, 'error');
            focusMessageArea();
        })
        .finally(function () {
            hideLoading();
        });
}

/**
 * Update an existing product
 * 
 * Makes a PUT request to: api/products.php?id={id}
 * Sends the updated product data as JSON in the request body
 * 
 * @param {number} id - The product ID to update
 * @param {Object} productData - The updated product data from the form
 */
function updateProductAPI(id, productData) {
    // Prevent duplicate submissions
    if (isRequestInProgress) {
        return;
    }
    showLoading();

    fetch(API_URL + '?id=' + id, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(productData)
    })
        .then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, status: response.status, body: data };
            });
        })
        .then(function (result) {
            if (!result.ok) {
                if (result.body.details) {
                    throw new Error(result.body.details.join(', '));
                }
                throw new Error(result.body.error || 'Failed to update product');
            }

            showMessage('Product updated successfully!', 'success');
            resetForm();
            loadProducts();

            // Move focus to the message area so screen readers announce success
            focusMessageArea();
        })
        .catch(function (error) {
            showMessage('Error: ' + error.message, 'error');
            focusMessageArea();
        })
        .finally(function () {
            hideLoading();
        });
}

/**
 * Delete a product
 * 
 * Makes a DELETE request to: api/products.php?id={id}
 * No request body is needed for DELETE
 * 
 * @param {number} id - The product ID to delete
 */
function deleteProductAPI(id) {
    // Prevent duplicate submissions
    if (isRequestInProgress) {
        return;
    }
    showLoading();

    fetch(API_URL + '?id=' + id, {
        method: 'DELETE'
    })
        .then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, status: response.status, body: data };
            });
        })
        .then(function (result) {
            if (!result.ok) {
                throw new Error(result.body.error || 'Failed to delete product');
            }

            showMessage('Product deleted successfully!', 'success');
            loadProducts();

            // Move focus to the message area so screen readers announce success
            focusMessageArea();
        })
        .catch(function (error) {
            showMessage('Error: ' + error.message, 'error');
            focusMessageArea();
        })
        .finally(function () {
            hideLoading();
        });
}

// ============================================================
// FORM HANDLING
// ============================================================

/**
 * Handle form submission
 * 
 * This function runs when the user clicks "Add Product" or "Update Product".
 * It prevents the default form submission (which would cause a page refresh),
 * collects the form data, validates it, and calls the appropriate API function.
 * 
 * @param {Event} event - The form submit event
 */
function handleFormSubmit(event) {
    // IMPORTANT: Prevent the default form submission
    // Without this, the browser would refresh the page
    event.preventDefault();

    // Prevent submission if a request is already in progress
    if (isRequestInProgress) {
        showMessage('Please wait, your request is being processed...', 'error');
        return;
    }

    // Collect data from the form fields
    // trim() removes leading and trailing whitespace
    var productData = {
        product_name: document.getElementById('product-name').value.trim(),
        category: document.getElementById('category').value.trim(),
        price: document.getElementById('price').value,
        quantity: document.getElementById('quantity').value,
        supplier: document.getElementById('supplier').value.trim(),
        expiry_date: document.getElementById('expiry-date').value
    };

    // Validate on the frontend first for quick user feedback
    // The backend will also validate (never trust frontend validation alone)
    var errors = validateProductFrontend(productData);
    if (errors.length > 0) {
        showMessage('Please fix the following errors: ' + errors.join(', '), 'error');
        focusMessageArea();
        return;
    }

    // Check if we are editing an existing product or adding a new one
    var productId = document.getElementById('product-id').value;

    if (isEditing && productId) {
        // We are in edit mode - update the existing product
        updateProductAPI(productId, productData);
    } else {
        // We are in add mode - create a new product
        createProductAPI(productData);
    }
}

/**
 * Populate the form with a product's data for editing
 * 
 * When the user clicks the "Edit" button on a product row,
 * this function fills in the form fields with that product's
 * current values and switches the form to edit mode.
 * 
 * @param {number} id - The product ID to edit
 */
function editProduct(id) {
    // Find the product in our stored data array
    var product = null;
    for (var i = 0; i < allProducts.length; i++) {
        if (allProducts[i].id == id) {
            product = allProducts[i];
            break;
        }
    }

    if (!product) {
        showMessage('Product not found', 'error');
        return;
    }

    // Switch the form to edit mode
    isEditing = true;

    // Fill in all form fields with the product's current values
    document.getElementById('product-id').value = product.id;
    document.getElementById('product-name').value = product.product_name;
    document.getElementById('category').value = product.category;
    document.getElementById('price').value = product.price;
    document.getElementById('quantity').value = product.quantity;
    document.getElementById('supplier').value = product.supplier || '';
    document.getElementById('expiry-date').value = product.expiry_date || '';

    // Change the form title and button text to show we are editing
    document.getElementById('form-title').textContent = 'Edit Product (ID: ' + product.id + ')';
    document.getElementById('submit-btn').textContent = 'Update Product';

    // Show the Cancel Edit button
    document.getElementById('cancel-btn').style.display = 'inline-block';

    // Scroll up to the form so the user can see it
    document.getElementById('form-section').scrollIntoView({ behavior: 'smooth' });
}

/**
 * Cancel editing and reset the form to add mode
 * Shows a message to confirm the edit was cancelled
 */
function cancelEdit() {
    resetForm();
    showMessage('Edit cancelled', 'success');
}

/**
 * Reset the form to its default state (add mode)
 * 
 * Clears all form fields, resets the title and button text,
 * and hides the Cancel Edit button
 */
function resetForm() {
    // Clear all form fields using the built-in reset method
    document.getElementById('product-form').reset();

    // Clear the hidden product ID field
    document.getElementById('product-id').value = '';

    // Switch back to add mode
    isEditing = false;

    // Reset the form title and button text
    document.getElementById('form-title').textContent = 'Add New Product';
    document.getElementById('submit-btn').textContent = 'Add Product';

    // Hide the Cancel Edit button
    document.getElementById('cancel-btn').style.display = 'none';
}

// ============================================================
// DISPLAY FUNCTIONS
// ============================================================

/**
 * Display products in the HTML table
 * 
 * This function clears the current table body and creates a new
 * row for each product. Each row includes Edit and Delete buttons.
 * Products with low stock are highlighted with a yellow background.
 * 
 * @param {Array} products - Array of product objects to display
 */
function displayProducts(products) {
    var tbody = document.getElementById('products-tbody');
    var noProductsMsg = document.getElementById('no-products-message');

    // Clear the current table contents
    tbody.innerHTML = '';

    // If there are no products, show a message
    if (products.length === 0) {
        noProductsMsg.style.display = 'block';
        return;
    }

    // Hide the "no products" message
    noProductsMsg.style.display = 'none';

    // Create a table row for each product
    for (var i = 0; i < products.length; i++) {
        var product = products[i];
        var row = document.createElement('tr');

        // Add the low-stock CSS class if quantity is below the threshold
        if (parseInt(product.quantity) < LOW_STOCK_THRESHOLD) {
            row.classList.add('low-stock');
        }

        // Format the price with 2 decimal places and euro symbol
        var formattedPrice = '\u20AC' + parseFloat(product.price).toFixed(2);

        // Show supplier or dash if empty
        var supplier = product.supplier ? escapeHtml(product.supplier) : '-';

        // Show expiry date or dash if empty
        var expiryDate = product.expiry_date ? escapeHtml(product.expiry_date) : '-';

        // Show low stock badge if quantity is below threshold
        var quantityDisplay = '';
        if (parseInt(product.quantity) < LOW_STOCK_THRESHOLD) {
            quantityDisplay = product.quantity +
                ' <span class="low-stock-badge">(Low Stock)</span>';
        } else {
            quantityDisplay = String(product.quantity);
        }

        // Escape the product name for use in the onclick attribute
        var escapedName = escapeHtml(product.product_name).replace(/'/g, "\\'");

        // Build the row HTML
        row.innerHTML =
            '<td>' + product.id + '</td>' +
            '<td>' + escapeHtml(product.product_name) + '</td>' +
            '<td>' + escapeHtml(product.category) + '</td>' +
            '<td>' + formattedPrice + '</td>' +
            '<td>' + quantityDisplay + '</td>' +
            '<td>' + supplier + '</td>' +
            '<td>' + expiryDate + '</td>' +
            '<td>' +
            '<button class="btn-edit" onclick="editProduct(' + product.id + ')"' +
            ' aria-label="Edit ' + escapedName + '">Edit</button>' +
            '<button class="btn-delete" onclick="confirmDelete(' + product.id +
            ', \'' + escapedName + '\')"' +
            ' aria-label="Delete ' + escapedName + '">Delete</button>' +
            '</td>';

        // Add the row to the table body
        tbody.appendChild(row);
    }
}

/**
 * Show a success or error message to the user
 * 
 * Creates a styled message div and adds it to the message area.
 * The message automatically disappears after 5 seconds.
 * 
 * @param {string} text - The message text to display
 * @param {string} type - 'success' (green) or 'error' (red)
 */
function showMessage(text, type) {
    var messageArea = document.getElementById('message-area');

    // Create the message element
    var messageDiv = document.createElement('div');
    messageDiv.className = 'message message-' + type;
    messageDiv.textContent = text;

    // Clear any previous messages
    messageArea.innerHTML = '';

    // Add the new message
    messageArea.appendChild(messageDiv);

    // Automatically remove the message after 5 seconds
    setTimeout(function () {
        if (messageDiv.parentNode) {
            messageDiv.remove();
        }
    }, 5000);
}

/**
 * Confirm before deleting a product
 * 
 * Shows a browser confirmation dialog. If the user confirms,
 * calls the delete API function.
 * 
 * @param {number} id - The product ID to delete
 * @param {string} name - The product name (shown in the confirmation dialog)
 */
function confirmDelete(id, name) {
    // Show a confirmation dialog
    var confirmed = confirm('Are you sure you want to delete "' + name + '"?\n\nThis action cannot be undone.');

    if (confirmed) {
        deleteProductAPI(id);
    }
}

// ============================================================
// SEARCH, FILTER AND SORT
// ============================================================

/**
 * Apply search, filter and sort to the products list
 * 
 * This function reads the current values from the search input,
 * category filter dropdown, and sort dropdown. It then filters
 * and sorts the allProducts array and calls displayProducts
 * with the result.
 * 
 * This runs on the client side using the data already loaded
 * from the API, so it does not make additional API calls.
 */
function applyFilters() {
    // Start with a copy of all products
    var filtered = allProducts.slice();

    // Get the current values from the control elements
    var searchTerm = document.getElementById('search-input').value.trim().toLowerCase();
    var categoryFilter = document.getElementById('filter-category').value;
    var sortBy = document.getElementById('sort-by').value;

    // SEARCH: Filter by product name (case-insensitive)
    if (searchTerm !== '') {
        filtered = filtered.filter(function (product) {
            return product.product_name.toLowerCase().indexOf(searchTerm) !== -1;
        });
    }

    // FILTER: Filter by category (exact match)
    if (categoryFilter !== '') {
        filtered = filtered.filter(function (product) {
            return product.category === categoryFilter;
        });
    }

    // SORT: Sort by the selected field
    if (sortBy !== '') {
        filtered.sort(function (a, b) {
            if (sortBy === 'product_name') {
                // Alphabetical sort by product name
                return a.product_name.localeCompare(b.product_name);
            } else if (sortBy === 'price') {
                // Numerical sort by price (low to high)
                return parseFloat(a.price) - parseFloat(b.price);
            } else if (sortBy === 'quantity') {
                // Numerical sort by quantity (low to high)
                return parseInt(a.quantity) - parseInt(b.quantity);
            }
            return 0;
        });
    }

    // Display the filtered and sorted products in the table
    displayProducts(filtered);
}

// ============================================================
// SUMMARY STATISTICS
// ============================================================

/**
 * Update the summary statistics section
 * 
 * Calculates three values from the allProducts array:
 * 1. Total number of products
 * 2. Total stock quantity (sum of all quantities)
 * 3. Number of low-stock items (quantity below threshold)
 * 
 * Updates the DOM elements with the calculated values.
 * If there are low-stock items, the count is shown in red.
 */
function updateSummary() {
    // Total number of products is simply the array length
    var totalProducts = allProducts.length;

    // Calculate total stock by adding up all quantities
    var totalStock = 0;
    for (var i = 0; i < allProducts.length; i++) {
        totalStock += parseInt(allProducts[i].quantity);
    }

    // Count products with quantity below the low stock threshold
    var lowStockCount = 0;
    for (var j = 0; j < allProducts.length; j++) {
        if (parseInt(allProducts[j].quantity) < LOW_STOCK_THRESHOLD) {
            lowStockCount++;
        }
    }

    // Update the DOM elements with the calculated values
    document.getElementById('total-products').textContent = totalProducts;
    document.getElementById('total-stock').textContent = totalStock;

    var lowStockElement = document.getElementById('low-stock-count');
    lowStockElement.textContent = lowStockCount;

    // Add or remove the red warning colour based on low stock count
    if (lowStockCount > 0) {
        lowStockElement.classList.add('stat-warning');
    } else {
        lowStockElement.classList.remove('stat-warning');
    }
}

// ============================================================
// FRONTEND VALIDATION
// ============================================================

/**
 * Validate product data on the frontend
 * 
 * This provides quick feedback to the user before sending data
 * to the API. The backend (PHP) will also validate independently.
 * 
 * IMPORTANT: Never trust frontend validation alone. A user could
 * bypass JavaScript and send requests directly to the API.
 * 
 * @param {Object} data - The product data from the form
 * @returns {Array} Array of error messages (empty if valid)
 */
function validateProductFrontend(data) {
    var errors = [];

    // Product name is required
    if (!data.product_name || data.product_name.trim() === '') {
        errors.push('Product name is required');
    } else if (data.product_name.length > MAX_NAME_LENGTH) {
        errors.push('Product name must be ' + MAX_NAME_LENGTH + ' characters or less');
    }

    // Category is required
    if (!data.category || data.category.trim() === '') {
        errors.push('Category is required');
    }

    // Price must be a valid number and not negative
    if (data.price === '' || data.price === null || isNaN(data.price)) {
        errors.push('Price must be a valid number');
    } else if (parseFloat(data.price) < 0) {
        errors.push('Price cannot be negative');
    } else if (parseFloat(data.price) > MAX_PRICE) {
        errors.push('Price cannot exceed ' + MAX_PRICE);
    }

    // Quantity must be a whole number and not negative
    if (data.quantity === '' || data.quantity === null || isNaN(data.quantity)) {
        errors.push('Quantity must be a valid number');
    } else if (parseFloat(data.quantity) % 1 !== 0) {
        errors.push('Quantity must be a whole number');
    } else if (parseInt(data.quantity) < 0) {
        errors.push('Quantity cannot be negative');
    } else if (parseInt(data.quantity) > MAX_QUANTITY) {
        errors.push('Quantity cannot exceed ' + MAX_QUANTITY);
    }

    // Supplier is optional but has a maximum length
    if (data.supplier && data.supplier.length > MAX_SUPPLIER_LENGTH) {
        errors.push('Supplier name must be ' + MAX_SUPPLIER_LENGTH + ' characters or less');
    }

    // Expiry date must be a valid format if provided
    if (data.expiry_date && data.expiry_date.trim() !== '') {
        var dateRegex = /^\d{4}-\d{2}-\d{2}$/;
        if (!dateRegex.test(data.expiry_date)) {
            errors.push('Expiry date must be in YYYY-MM-DD format');
        } else {
            // Additional check: verify it is a real calendar date
            var dateParts = data.expiry_date.split('-');
            var testDate = new Date(
                parseInt(dateParts[0]),
                parseInt(dateParts[1]) - 1,
                parseInt(dateParts[2])
            );
            if (isNaN(testDate.getTime())) {
                errors.push('Expiry date is not a valid calendar date');
            }
        }
    }

    return errors;
}

// ============================================================
// UTILITY FUNCTIONS
// ============================================================

/**
 * Escape HTML special characters to prevent XSS attacks
 * 
 * When displaying user-entered data in the DOM, we must escape
 * special characters like < > & " to prevent them from being
 * interpreted as HTML. This is a security best practice.
 * 
 * For example, if a product name contains "<script>", this
 * function ensures it is displayed as text, not executed as code.
 * 
 * @param {string} text - The text to escape
 * @returns {string} The escaped text safe for HTML display
 */
function escapeHtml(text) {
    if (!text) {
        return '';
    }
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Move keyboard focus to the message area
 * 
 * This is an accessibility improvement. When a success or error
 * message is displayed, moving focus to the message area ensures
 * that screen reader users hear the message immediately without
 * having to navigate to it manually.
 * 
 * The message area has aria-live="polite" which announces changes,
 * but setting focus provides an additional guarantee.
 */
function focusMessageArea() {
    var messageArea = document.getElementById('message-area');
    if (messageArea) {
        // Make the area focusable if it is not already
        messageArea.setAttribute('tabindex', '-1');
        messageArea.focus();
    }
}
