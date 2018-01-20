<?php

/*
 * Custom database maintenance jobs
 *
 */

define('LOG', 'cron.log');
$msg = date('Y-m-d H:i:s') . ' cron.php';
if (!empty($_SERVER['REMOTE_ADDR'])) {
    $msg .= ' from ' . $_SERVER['REMOTE_ADDR'];
}
if (!empty($_SERVER['USER'])) {
    $msg .= ' by ' . $_SERVER['USER'];
}
if (!empty($_SERVER['SUDO_USER'])) {
    $msg .= ' (' . $_SERVER['SUDO_USER'] . ')';
}
file_put_contents(LOG, $msg . PHP_EOL, FILE_APPEND);

$mysqli = false;

function getConnection() {
    global $mysqli;

    // Import WP SQL settings
    require_once('wp-config.php');
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    
    if ($mysqli->connect_errno) {
        printf("Connect failed: %s\n", $mysqli->connect_error);
        exit();
    }
}

// Search for all Events where Timezone is not America/Chicago
function getEvents() {
    global $mysqli;
    $rows = [];

    $query = '
    SELECT p.id, 
            pmsd.meta_value start_date,
            pmtz.meta_value timezone,
            pmtza.meta_value timezone_abbr,
            p.post_title
    FROM wpsgf_posts p
    INNER JOIN wpsgf_postmeta pmsd ON (p.id = pmsd.post_id AND pmsd.meta_key = "_EventStartDate")
    INNER JOIN wpsgf_postmeta pmtz ON (p.id = pmtz.post_id AND pmtz.meta_key = "_EventTimezone")
    INNER JOIN wpsgf_postmeta pmtza ON (p.id = pmtza.post_id AND pmtza.meta_key = "_EventTimezoneAbbr")
    WHERE (pmtz.meta_value != "America/Chicago")
    ORDER BY pmsd.meta_value
    ';
    
    if ($result = $mysqli->query($query)) {
        foreach($result as $row) {
            $rows[] = $row;
        }
        $result->close();
    }

    return $rows;
}

// Get Timezone Abbreviation (CST or CDT) for given DateTime and Timezone Name (America/Chicago)
function getTimezoneAbbr($date, $timezone_name) {
    $timezone = new DateTimeZone($timezone_name);

    // Transitions for given unix timestamp
    $transitions = $timezone->getTransitions($date->format('U'));
    //print_r(array_slice($transitions, 0, 3));

    // First transition is correct abbr for given date
    $transition = $transitions[0];
    // Array
    // (
    //     [ts] => 1521657000
    //     [time] => 2018-03-21T18:30:00+0000
    //     [offset] => -18000
    //     [isdst] => 1
    //     [abbr] => CDT
    // )

    $timezone_abbr = $transition['abbr']; // CST or CDT

    if ($timezone_abbr == 'CST' || $timezone_abbr == 'CDT') {
        return $timezone_abbr;
    }
    else {
        return false;
    }
}

// Change Timezone to America/Chicago and CST/DST for specified event ID
function fixEvent($id, $timezone, $timezone_abbr) {
    global $mysqli;

    $mysqli->autocommit(FALSE);

    $query = 'UPDATE wpsgf_postmeta SET meta_value="' . $timezone . '" WHERE post_id="' . $id . '" AND meta_key="_EventTimezone"';
    file_put_contents(LOG, $query, FILE_APPEND);
    $mysqli->query($query);

    $query = 'UPDATE wpsgf_postmeta SET meta_value="' . $timezone_abbr . '" WHERE post_id="' . $id . '" AND meta_key="_EventTimezoneAbbr"';
    file_put_contents(LOG, $query, FILE_APPEND);
    $mysqli->query($query);

    $mysqli->commit();
    $mysqli->autocommit(TRUE);
}

// Change Timezone to America/Chicago and CST/DST for each event returned
function fixEvents($events) {
    foreach($events as $event) {
        //print_r($event);

        $date = DateTime::createFromFormat('Y-m-d H:i:s', $event['start_date']);
        $timezone = 'America/Chicago';
        if (!$timezone_abbr = getTimezoneAbbr($date, $timezone)) {
            continue; // value is not CST or CDT. Do not update the event.
        }
        //echo "timezone:$timezone timezone_abbr:$timezone_abbr \n";
        
        fixEvent($event['id'],$timezone,$timezone_abbr);
    }
}

// MySQL connection
getConnection();

// Timezone maintenance
$events = getEvents();
$result = fixEvents($events);


// Close connection
$mysqli->close();

