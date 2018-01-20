<?php

/*
 * StarChapter to iCalendar Feed
 *
 * Parse the "Future Events" page on a StarChapter website and output an iCalendar feed
 * The "Created" date is set to the first time script sees an event
 * The "Modified" date is set to the last time script has seen an event change.
 * Track hash of each event in MySQL to determine when an event has changed.
 *
 * (c) 2018 Jason Klein FWD/SGF
 */

require_once('./php-simple-html-dom-parser/Src/Sunra/PhpSimple/HtmlDomParser.php');
use Sunra\PhpSimple\HtmlDomParser;

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

// Validate Domain Name
function is_valid_domain_name($domain_name)
{
    return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain_name) //valid chars check
            && preg_match("/^.{1,253}$/", $domain_name) //overall length check
            && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain_name)   ); //length of each label
}

// Parse Request URL
function parse_request_url($request_url) {
    // Require user specified URL. Show example if URL not specified.
    if (empty($request_url)) {
		// https://fwdsgf.com/ical/starchapter.php?url=http://pmiswmo.org/meetinginfo.php?p_or_f=f
		// https://fwdsgf.com/ical/starchapter.php?url=http://aitpozarks.org/meetinginfo.php?p_or_f=f
        $url = $_SERVER['SCRIPT_URI'] . "?url=http://aitpozarks.org/meetinginfo.php?p_or_f=f";
        echo "Request requires a URL parameter. For example: <br/>\n<br/>\n ";
        echo "<a href=\"$url\">$url</a>";
        exit();
    }
    else {
        $url = $_REQUEST['url'];
    
    	// Validate URL parts and fail if scheme or host are invalid
        $parts = parse_url($url);
        if ($parts['scheme'] != 'http' && $parts['scheme'] != 'https') {
            die('Invalid URL scheme');
        }
        if (!is_valid_domain_name($parts['host'])) {
            die('Invalid URL host');
        }
    }

    return $url;
}

// Parse StarChapter Start Date/Time and End Date/Time
function parse_start_end_date_time($raw) {
    // PARSE BEGIN/END DATE/TIME
    // February 26, 2018 8:00 AM to March 01, 2018 5:00 PM
    // March 01, 2018 \n 5:30 PM to 8:00 PM

    $raw = trim($raw);
    $raw = str_replace("\n", ' ', $raw);
    $raw = str_replace("\r", ' ', $raw);
    $raw = str_replace('  ', ' ', $raw);
    $raw = str_replace('  ', ' ', $raw);

    $rawParts = explode(' to ', $raw);

    // Start Date/Time
    $rawStart = $rawParts[0];
    $ps = explode(' ', $rawStart);
    switch(count($ps)) {
        case 5:
            // February 26, 2018 8:00 AM
            $rs = $rawStart;
            break;
        default:
            die('Unable to parse start DateTime');
            break;
    }
    $ds = DateTime::createFromFormat('F d, Y g:i A', $rs);
    $us = $ds->format('U');
    $dts = date('Ymd', $us) . 'T' . date('His', $us);

    // End Date/Time
    $rawEnd = $rawParts[1];
    $pe = explode(' ', $rawEnd);
    switch(count($pe)) {
        case 0:
            // If End Date/Time empty, use Start Date/Time
            $re = $rs;
            break;
        case 2:
            // If End Date empty, use Start Date
            // 8:00 PM
            $re = implode(' ', [$ps[0], $ps[1], $ps[2]] ) . ' ' . $rawEnd;
            break;
        case 3:
            // If End Time empty, use Start Time
            // February 26, 2018
            $re = $rawEnd . ' ' . implode(' ', [$ps[3], $ps[4]] );
            break;
        case 5:
            // February 26, 2018 8:00 AM
            $re = $rawEnd;
            break;
        default:
            die('Unable to parse end DateTime');
            break;
    }
    $de = DateTime::createFromFormat('F d, Y g:i A', $re);
    $ue = $de->format('U');
    $dte = date('Ymd', $ue) . 'T' . date('His', $ue);


    $tz = 'America/Chicago';

    $out = [];
    $out['dtStart'] = $dts;
    $out['tzStart'] = $tz;
    $out['dtEnd'] = $dte;
    $out['tzEnd'] = $tz;

    return $out;
}

// Get Created/Modified Dates for Host/Event Pair
function get_host_event_created_modified_dates($host, $event) {

    // Sort array before generating hash for more consistent results
    ksort($event);

    // Generate hash of event data so we can detect changes
    $hash = md5(serialize($event));

    // Lookup created/modified dates based on host/event/hash
    $dates = db_query_host_event_hash_dates($host, $event, $hash);

    // Return created/modified dates
    $dt = [];
    $dt['created'] = $dates['created'];
    $dt['modified'] = $dates['modified'];

    return $dt;
}

// Parse Events from StarChapter Future Events HTML
function parse_events_from_html($html, $url) {

    $parse_url = parse_url($url);

    // Parse DOM on Future Events page
    if (!$html = HtmlDomParser::str_get_html($html)) {
    	die('Unable to parse HTML');
    }

    // Loop through each Event on Future Events page
    foreach($html->find('div.meeting-list') as $meetingList) {

        // Event Summary
        $meetingSummary = $meetingList->find('h3',0)->plaintext;
        $event['summary'] = $meetingSummary;

        // Event Start and End Date/Time
        $meetingDateTime = $meetingList->find('p',0)->plaintext;
        $dates = parse_start_end_date_time($meetingDateTime);
        $event['dtStart'] = $dates['dtStart'];
        $event['tzStart'] = $dates['tzStart'];
        $event['dtEnd'] = $dates['dtEnd'];
        $event['tzEnd'] = $dates['tzEnd'];

        // Event Location
        $meetingLocation = $meetingList->find('p',1)->plaintext;
        $mlps = explode("\n",$meetingLocation);
        $ml = '';
        foreach($mlps as $mlp) {
            $mlp = trim($mlp);
            if (substr($mlp,0,4) == 'http') {
                continue;
            }
            $ml .= $mlp . ', ';
        }
        $ml = substr($ml,0,-2);
        $event['location'] = $ml;

        // Event Description
        $meetingDescription = $meetingList->find('p',2)->plaintext;
        $event['description'] = $meetingDescription;

        // Event Icon/Photo
        $meetingIcon = $meetingList->find('img.meeting-icon',0)->src;
        if (!empty($meetingIcon)) {
            if (substr($meetingIcon,0,4) != 'http') {
                // images/meeting/aitp_logo_simple.jpg -> http://aitpozarks.org/images/meeting/aitp_logo_simple.jpg
                $meetingIcon = $parse_url['scheme'] . '://' . $parse_url['host'] . '/' . $meetingIcon;
            }
            $event['iconLink'] = $meetingIcon;
            $pathinfo = pathinfo($meetingIcon);
            $event['iconType'] = 'image/' . $pathinfo['extension']; // png,jpg,gif
        }

        // Event URL
        $meetingLink = $meetingList->find('a.prime-btn',0)->href;
        if (substr($meetingLink,-14,4) == '&ts=') {
            // Strip timestamp variable from URL
            // http://aitpozarks.org/meetinginfo.php?id=260&ts=1509644888 -> http://aitpozarks.org/meetinginfo.php?id=260
            $meetingLink = substr($meetingLink,0,-14);
        }
        $event['url'] = $meetingLink;

        // Event UID
        // http://aitpozarks.org/meetinginfo.php?id=260 -> 260
        $meetingID = explode("=",$meetingLink)[1];
        $event['id'] = $meetingID;

        // Event Created/Modified Dates
        $host = $parse_url['host'];
        $dtRecord = get_host_event_created_modified_dates($host, $event);
		$dtc = $dtRecord['created'];
		$dtm = $dtRecord['modified'];
        $event['dtCreated'] = date('Ymd',$dtc) . 'T' . date('His',$dtc) . 'Z';
        $event['dtModified'] = date('Ymd',$dtm) . 'T' . date('His',$dtm) . 'Z';

        $events[] = $event;
    }
    
    return $events;
}

// Format Events as iCalendar Data
function format_events_as_ical($events, $url) {

    $parse_url = parse_url($url);
    $host = $parse_url['host'];

    $output = 'BEGIN:VCALENDAR' . PHP_EOL;
    $output .= 'VERSION:2.0' . PHP_EOL;
    $output .= 'PRODID:-//FWD/SGF - StarChapter to iCal//NONSGML v1.0//EN' . PHP_EOL;
    $output .= 'CALSCALE:GREGORIAN' . PHP_EOL;
    $output .= 'METHOD:PUBLISH' . PHP_EOL;
    $output .= 'X-WR-CALNAME:' . $host . PHP_EOL;
    $output .= 'X-ORIGINAL-URL:' . $url . PHP_EOL;
    $output .= 'X-WR-CALDESC:Events from StarChapter on ' . $host . PHP_EOL;

    foreach($events as $ev) {
        $output .= 'BEGIN:VEVENT' . PHP_EOL;
        $output .= 'DTSTART;TZID=' . $ev['tzStart'] . ':' . $ev['dtStart'] . PHP_EOL;
        $output .= 'DTEND;TZID=' . $ev['tzEnd'] . ':' . $ev['dtEnd'] . PHP_EOL;
        $output .= 'DTSTAMP:' . date('Ymd') . 'T' . date('His') . PHP_EOL;
        $output .= 'CREATED:' . $ev['dtCreated'] . PHP_EOL;
        $output .= 'LAST-MODIFIED:' . $ev['dtModified'] . PHP_EOL;
        $output .= 'UID:' . $ev['id'] . '@' . $host . PHP_EOL;
        $output .= 'SUMMARY:' . $ev['summary'] . PHP_EOL;
        $output .= 'DESCRIPTION:' . $ev['description'] . PHP_EOL;
        $output .= 'URL:' . $ev['url'] . PHP_EOL;
        if (!empty($ev['location'])) {
            $output .= 'LOCATION:' . str_replace(',', '\,', $ev['location']) . PHP_EOL;
        }
        if (!empty($ev['categories'])) {
            $output .= 'CATEGORIES:' . implode(',', $ev['categories']) . PHP_EOL;
        }
        if (!empty($ev['iconType']) && !empty($ev['iconLink']) ) {
            $output .= 'ATTACH;FMTTYPE=' . $ev['iconType'] . ':' . $ev['iconLink'] . PHP_EOL;
        }
        $output .= 'END:VEVENT' . PHP_EOL;
    }

    $output .= 'END:VCALENDAR' . PHP_EOL;

    return $output;
}


// Parse Request URL
$url = parse_request_url($_REQUEST['url']);

// Fetch StarChapter Future Events HTML
if (!$html = file_get_contents($url)) {
	die('Unable to get HTML from URL');
}

// Parse Events from StarChapter Future Events HTML
$events = parse_events_from_html($html, $url);

// Format Events as iCalendar Data
$output = format_events_as_ical($events, $url);

// Display iCalendar Data
echo $output;

