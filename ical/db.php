<?php

// Database Connect
function db_connect() {
    // Import WP SQL settings
    require_once('../wp-config.php');
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    
    if ($mysqli->connect_errno) {
        printf("Connect failed: %s\n", $mysqli->connect_error);
        exit();
    }

    return $mysqli;
}

// Database Select Query
function db_select($mysqli, $query) {
    $rows = [];
    if ($result = $mysqli->query($query)) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

// Database Insert/Update/Delete Query
function db_update($mysqli, $query) {
    if ($result = $mysqli->query($query)) {
        return $mysqli->affected_rows;
    }

    return false;
}

// Fetch Created/Modified Dates for Host/Event/Hash Triplet from Database
function db_query_host_event_hash_dates($host, $event, $hash) {
    $db = db_connect();

    $id = $event['id'];

    $sql = "INSERT INTO ical_starchapter (host, event, hash) VALUES ('$host', '$id', '$hash') ON DUPLICATE KEY UPDATE hash='$hash'";
    //echo "SQL:$sql\n";
    $count = db_update($db, $sql);

    $sql = "SELECT UNIX_TIMESTAMP(created) created, UNIX_TIMESTAMP(modified) modified FROM ical_starchapter WHERE host='$host' AND event='$id'";
    //echo "SQL:$sql\n";
    $rows = db_select($db, $sql);
    $row = $rows[0];

    $dates = [];
    $dates['created'] = $row['created'];
    $dates['modified'] = $row['modified'];

    return $dates;
}

