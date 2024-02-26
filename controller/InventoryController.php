<?php

// Instantiate the class
$inventoryController = new InventoryController();

// Call the method to handle all incoming AJAX requests
$inventoryController->handleAjaxRequest();

// Release the DB connection after page execution
$inventoryController->closeDBConnection();

// Controller class
class InventoryController {
    private $conn;
    private $itemTypeEnum = [
        1 => 'Office Supply',
        2 => 'Equipment',
        3 => 'Furniture'
    ];

    public function __construct() {
        // Initialize the database connection
        $db = new DbConnection();
        $this->conn = $db->getConnection();
    }

    /**
     * Handle incoming AJAX requests
     */
    public function handleAjaxRequest() {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            // The request was made via Ajax
            // Process your Ajax request here
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->processPostRequest();
            } 
            else {
                // Grab and sanitize the search term from the 'Search' field
                $searchValue = htmlspecialchars(filter_var(isset($_GET['search']['value']) ? $_GET['search']['value'] : '', FILTER_SANITIZE_STRING), ENT_QUOTES, 'UTF-8');
                
                // Lost the requests, apply the $searchValue filter, if any
                $data = $this->getRequests($searchValue);
                echo json_encode(array('data' => $data));
            }
        }
    }

    /**
     * Handle the POST AJAX requests 
     */
    private function processPostRequest() {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'EditRequest') {
            // Load the existing request so we can populate the form
            $request = $this->getRequestById(intval($_POST['req_id']));

            if (is_array($request)) {
                //Success
                $response = array('status' => 'success', 'message' => '', 'data' => json_encode($request));
            }
            else {
                // Error
                $response = array('status' => 'error', 'message' => $request);
            }
        }
        else if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'SaveRequest') {
            // Save the request
            if (!isset($_POST['user_name']) || strlen($_POST['user_name']) < 1) {
                $errorMessage = "Missing name";
            }
            else {
                $user = htmlspecialchars(filter_var($_POST['user_name'], FILTER_SANITIZE_STRING), ENT_QUOTES, 'UTF-8');
                $items = $_POST['requested_items'];
                $reqId = isset($_POST['req_id']) ? intval($_POST['req_id']) : "";
                $errorMessage = $this->addOrUpdateRequest($user, $items, $reqId);
            }

            if (strlen($errorMessage)) {
                // Error
                $response = array('status' => 'error', 'message' => $errorMessage);
            } else {
                // Success
                $response = array('status' => 'success', 'message' => '');
            }
        }
        else {
            $response = array('status' => 'error', 'message' => 'Invalid request');
        }

        // Send the JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    /**
     * Fetch all the items from the inventory
     * 
     * @return items the list of all items
     */
    public function getInventory() {
        $items = [];

        // Select all the records from the items table to display in the request form dropdown
        $sql = "SELECT * FROM `items`";
        $rslt = $this->conn->query($sql);

        while (($row = $rslt->fetch_array()) !== null) {
            $items[] = $row;
        }

        return $items;
    }

    /**
     * Fetch all the request records for display with optional filter
     * 
     * @param searchValue optional search term to filter by
     * @return requestRows the list of request rows formatted for display in the UI table
     */
    public function getRequests($searchValue = '') {
        $requestRows = [];     

        // Select all the request to populate the table
        $sql = "SELECT * FROM `requests`";
        $rslt = $this->conn->query($sql);

        while (($row = $rslt->fetch_array()) !== null) {
            $itemList = "";
            $type = "";

            // Convert the list of item ids into a list of item names for display
            $items = explode(', ', $row['items']);
            foreach ($items as $itemId) {
                // For every item in the request, load the data from the item table that includes the item name and type
                if (strlen($itemId) && $itemId != null) {
                    $item = $this->getItemById($itemId);
                    $type = $this->itemTypeEnum[intval($item['item_type'])];
                    $itemList .= $item['Item'] . ", ";
                }
            }

            // Format the row for display in the DataTables table
            $requestRows[] = ['user' => $row['requested_by'], 'items' => substr($itemList, 0, strlen($itemList) - 2), 'type' => $type, 'action' => "<button class=\"add-request-button\" onclick=\"openPopup(".$row['req_id'].")\">Edit Request</button>"];
        }

        // If a search term is provided, filter the results
        if (strlen($searchValue)) {
            $requestRows = filterResults($searchValue, $requestRows);
        }

        return $requestRows;
    }

    /**
     * Fetch an individual request by it's req_id to be used to populate the 'Edit Request' form
     * 
     * @param reqId the id of the request we need
     * @return row the request record if found or an error message if it can't be found
     */
    private function getRequestById($reqId) {
        $sql = "SELECT * FROM `requests` WHERE `req_id` = ".intval($reqId);
        $rslt = $this->conn->query($sql);

        if (($row = $rslt->fetch_array()) !== null) {
            return $row;
        }

        return "Request not found";
    }

    /**
     * Fetch an individual item by it's Id to display it's name and type
     * 
     * @param itemId the Id of the item we need
     * @return row the item record if found or an error message if it can't be found
     */
    private function getItemById($itemId) {
        $sql = "SELECT `Item`, `item_type` FROM `items` WHERE `Id` = ".intval($itemId);
        $rslt = $this->conn->query($sql);

        if (($row = $rslt->fetch_array()) !== null) {
            return $row;
        }

        return "Item not found";
    }

    /**
     * Create or update a request record depending on if this is a 'Create Request' or an 'Edit Request'
     * 
     * @param user name of user making the request
     * @param items list of item Ids they are requesting
     * @return true if the request is successfully saved and summary record is updated or an error message if not
     */
    private function addOrUpdateRequest($user, $items, $reqId = "") {
        // Remove any items where nothing was selected from the dropdown
        foreach ($items as $i => $item) {
            if (strlen($item) < 1) {
                unset($items[$i]);
            }
        }

        // If there are no items left, return an error message
        if (count($items) < 1) {
            return "Request contains no items";
        }

        // Get the item_type for each item and group by the item_type
        $sql = "SELECT `item_type` FROM `items` WHERE `Id` IN (" . implode(', ', $items) . ") GROUP BY `item_type`";
        $rslt = $this->conn->query($sql);

        // Check if the query was successful
        if ($rslt) {
            // If there are more than one rows returned, then that is too many types for the same request and we should return an error
            if ($rslt->num_rows > 1) {
                return "Items in request must be of the same type";
            }
        }

        // Perform a REPLACE INTO requests, that way we can update an existing request or create a brand new one
        $insert = "REPLACE INTO `requests`(`req_id`, `requested_by`, `requested_on`, `items`) VALUES (" . $this->escape($reqId) . ", " . $this->escape($user) . ", '" . date('Y-m-d') . "', '" . implode(", ", $items) . "')";

        // If the REPLACE INTO was sucessful, update the summary record
        if ($this->conn->query($insert)) {
            return $this->updateSummary($user);
        }

        return "Failed to insert request";
    }

    /**
     * Updates the summary record to include all of the requests made by a given user so far
     * 
     * @param user the requested_by person whose summary we are updating
     * @return true if the summary record is updated or an error message if not
     */
    private function updateSummary($user) {
        $reqId = "";
        $userRows = $this->getUserRequests($user);

        // Attempt to find the existing summary record to be updated using the requested_by value since there should be only one summary row per user
        $sql = "SELECT `req_id` FROM `summary` WHERE `requested_by` = " . $this->escape($user);
        $rslt = $this->conn->query($sql);
        if (($row = $rslt->fetch_array()) != null) {
            $reqId = $row['req_id'];
        }

        // Perform a REPLACE INTO the summary table, that way if an existing req_id is found, we update that row, otherwise we create a new one
        $insert = "REPLACE INTO `summary`(`req_id`, `requested_by`, `items`) VALUES (" . $this->escape($reqId) . ", " . $this->escape($user) . ", " . $this->escape($userRows) . ")";

        if (!$this->conn->query($insert)) {
            return "Failed to update summary.";
        }

        return '';
    }

    /**
     * Fetches all the requests for a given user to be rolled up in the summary table
     * 
     * @param user the requested_by person whose records we need
     * @return userRequests a JSON encoded array of the person's complete request history
     */
    private function getUserRequests($user) {
        $sql = "SELECT * FROM `requests` WHERE `requested_by` = " . $this->escape($user);
        $rslt = $this->conn->query($sql);
    
        // Build an array of the requests with the req_id as the key and the list of item Ids as the value
        while (($row = $rslt->fetch_array()) !== null) {
            $userRequests[$row['req_id']] = $row['items'];
        }
    
        return json_encode($userRequests);
    }
    
    /**
     * Filters the rows of requests looking for string matches in the user name, item names and type
     * 
     * @param needle the search term provided in the UI
     * @param haystack the list of request rows already formatted for the table
     * @return haystack the list with all the needle matches filtered out
     */
    private function filterResults($needle, $haystack) {
        foreach ($haystack as $i => $hay) {
            if (!strpos($hay['user'], $needle) && !strpos($hay['items'], $needle) && !strpos($hay['type'], $needle)) {
                unset($haystack[$i]);
            }
        }

        return $haystack;
    }
    
    /** 
     * Escape DB input to prevent SQL injection
     * 
     * @param input the value that needs to be escaped
     * @return input after it's been escaped
     */
    private function escape($input) {
        if (is_int($input)) {
            return intval($input);
        }
        else {
            return $input !== '' ? "'" . $this->conn->real_escape_string(trim($input)) . "'" : 'NULL';
        }
    }

    public function closeDBConnection() {
        $this->conn->close();
    }
}

// Class for managing the database connection
class DbConnection {
    private $host = 'localhost';
    private $username = 'root';
    private $password = 'mysql';
    private $dbname = 'inventory';
    private $conn;

    /**
     * Create the DB connection
     */
    public function __construct() {
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->dbname);
        if ($this->conn->connect_error) {
            die('Connection failed: ' . $this->conn->connect_error);
        }
    }

    /**
     * Accessor for the DB connection object
     * 
     * @return $conn the mysqli connection object
     */
    public function getConnection() {
        return $this->conn;
    }

    /**
     * Close the DB connection
     */
    public function closeConnection() {
        $this->conn->close();
    }
}

