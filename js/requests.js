$(document).ready(function() {
    // Initialize DataTable with server-side processing
    var dataTable = $('#requestsTable').DataTable({
        "ajax": {
            "url": "controller/InventoryController.php",
            "type": "GET"
        },
        "columns": [
            { "data": "user" },
            { "data": "items" },
            { "data": "type" },
            { "data": "action" }
        ]
    });

    // Add a listener for the search event to trigger a redraw
    $('#requestsTable_filter input').on('keyup', function() {
        dataTable.search(this.value).draw();
    });
});

// Function to refresh the table whever a request is added or edited
function refreshData() {
    var dataTable = $('#requestsTable').DataTable();
    dataTable.ajax.reload(null, false);
}

// Function to open the popup form
function openPopup(reqId) {
    resetForm(reqId);
    $("#requestForm").show();
}

// Function to close the popup form
function closePopup() {
    $("#requestForm").hide();
    resetForm();
}

// Function to set the form to edit an existing request or reseting it to the defaults for adding a request
function resetForm(reqId) {
    $('#errorMessage').text('').hide();

    if (reqId != undefined) {
        // If we are setting the form to update an existing request, make an AJAX call to get that request using the reqId
        $.ajax({
            type: 'POST',
            url: 'controller/InventoryController.php',
            data: {'req_id': reqId},
            dataType: "json",
            headers: {'X-Requested-With': 'EditRequest'},
            success: function(response) {
                if (response.status === 'success') {
                    // Handle success            
                    var responseData = JSON.parse(response.data);
                    // After parsing the response data, populate the userName field and set it to readonly
                    // (allowing the userName to be changed would mess up the summary record and force creation of a new one)
                    var userNameField = $('#userName');
                    userNameField.val(responseData['requested_by']);
                    userNameField.prop('readonly', true);
                    // Explode the list of items and create dropdowns for each, with the respective items pre-selected
                    var itemsArray = responseData['items'].split(', ');
                    $('#requestedItem').val(itemsArray[0]);
                    for (var i = 1; i < itemsArray.length; i++) {
                        addItemRow(itemsArray[i]);
                    }

                    // Create a hidden input element with your additional value
                    var reqIdInput = $('<input>')
                        .attr('type', 'hidden')
                        .attr('id', 'req_id')
                        .attr('name', 'req_id')  // replace 'extraKey' with your desired name
                        .val(reqId);       // replace 'extraValue' with your desired value

                    // Append the hidden input to the form (this is so we know what request record to update)
                    $('#request').append(reqIdInput);

                    // Update the title in the h2 element
                    $('#requestForm h2').text('Edit Request');

                    // Update the onclick action for the Submit button
                    $('#submitRequest').attr('onclick', 'submitForm(' + responseData['req_id'] + ')');
                }
                else {
                    // Handle error
                    console.error(response.message);
                    $('#errorMessage').text(response.message).show();
                }
            },
            error: function(xhr, textStatus, errorThrown) {
                // Handle AJAX request failure (e.g., network error)
                console.error('Error:', textStatus, errorThrown);

                // Log the actual response received from the server
                console.log('Response:', xhr.responseText);
            }
        });
    }
    else {
        // Reset the form to it's default by first clearing the name field
        $('#userName').val('');
        // Reset the first item dropdown to the default (blank) option
        $('#requestedItem').val($('#requestedItem option:first').val());
        // Remove all additional item dropdowns
        var itemsArray = $('[name="requested_items[]"]');
        for (var i = 1; i <= itemsArray.length; i++) {
            $('#itemRow' + i).remove();
        }

        // Update the title in the h2 element
        $('#requestForm h2').text('Add Request');

        // Update the onclick action for the Submit button
        $('#submitRequest').attr('onclick', 'submitForm()');

        // Remove the hidden req_id input from the form and the DOM
        $('#requestForm').find('#req_id').remove();

        // Enable the input field
        $('#userName').prop('readonly', false);
    }
}

// Function to add a new item row when the 'Add more' button is pressed
function addItemRow(itemId) {
    // Select the container for the new row
    var container = $("#itemRows");

    // Clone the template row
    var newRow = $("#itemRow").clone();

    // Update the ID and name attributes for the new select element
    var i = $('[name^="requested_items"]').length;
    newRow[0].id = "itemRow" + i;
    newRow.find('label').attr({
        for: "requestedItem" + i
    });
    newRow.find('select').attr({
        id: "requestedItem" + i,
        name: "requested_items[]"
    });
    newRow.find('button').attr({
        id: "addItem" + i
    });

    // Append the new row to the container
    container.append(newRow);

    if (itemId != undefined) {
        $('#requestedItem' + i).val(itemId);
    }
}

// Function to submit the Add Request or Edit Request form
function submitForm(reqId) {
    // Serialize the form data
    var formData = $('#request').serialize();

    // Submit the form using AJAX
    $.ajax({
        type: 'POST',
        url: 'controller/InventoryController.php',
        data: formData,
        dataType: "json",
        headers: {'X-Requested-With': 'SaveRequest'},
        success: function(response) {
            if (response.status === 'success') {
                // Handle success by closing the popup and refreshing the table
                closePopup();
                refreshData();
            } else {
                // Handle error
                console.error(response.message);
                $('#errorMessage').text(response.message).show();
            }
        },
        error: function(xhr, textStatus, errorThrown) {
            // Handle AJAX request failure
            console.error("Ajax request failed: " + textStatus);

            // Log the actual response received from the server
            console.log('Response:', xhr.responseText);
        }
    });
}