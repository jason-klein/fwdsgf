<?php

require('facebook-ics-config.php');
require('facebook-ics-urllinker.php');

/**
 * Download Facebook iCalendar (ICS) file that lists all of a person's events.
 * Filter events to include or exclude certain keywords.
 * Perform search/replace on events
 * Rewrite time zone so events import correctly.
 *
 * (c) 2019-2020 Jason Klein
 *
 * Instructions for Modern Tribe Events Calendar Aggregator for Wordpress
 * 1. New Import
 * 2. Import Origin = iCalendar
 * 3. Import Type = Scheduled Import, Daily (based on your preferences)
 * 4. URL = https://fwdsgf.com/scripts/facebook-ics.php
 * 5. Refine = (optional, based on your preferences)
 * 6. Preview
 * 7. Review and set default Status and default Category.
 * 8. Save Scheduled Import
 */

// Retrieve ICS from Facebook
$ics_data = getContents($facebook_user_event_url);

// Filter ICS events by include/exclude keywords
$ics_new = filterICS($ics_data, $config);

if (!isset($_REQUEST['debug'])) {
    header('Content-type: text/calendar');
}

// Return new ICS file
echo $ics_new;


function checkValueExclude($val,$config) {
    foreach($config['keywords_exclude'] as $keyword) {
        if (stripos($val,$keyword) !== false) {
            return true; // keyword found
        }
    }
    return false; // keyword NOT found
}

function checkValueInclude($val,$config) {
    foreach($config['keywords_include'] as $keyword) {
        if (stripos($val,$keyword) !== false) {
            return true; // keyword found
        }
    }
    return false; // keyword NOT found
}

function getContents($facebook_user_event_url) {
    global $user_agent;

    // Create a stream
    $opts = [
        'http' => [
            // Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36
            'header' => 'User-Agent: ' . $user_agent . "\r\n",
        ]
    ];

    $context = stream_context_create($opts);

    $data = file_get_contents($facebook_user_event_url, false, $context);
    return $data;
}

function searchReplace($val,$config) {
    foreach($config['search_replace'] as $search => $replace) {
        $val = str_replace($search,$replace,$val);
    }
    return $val;
}

/**
 * Convert iCalendar Zulu Time to Local Time
 *
 * BEFORE
 * DTSTART:20191215T000000Z
 * DTEND:20191215T030000Z
 *
 * AFTER
 * DTSTART;TZID=America/Chicago:20191215T000000
 * DTEND;TZID=America/Chicago:20191215T030000
 *
 * (c) 2019-2020 Jason Klein
 */
function updateTimezone($key,$val) {
    global $local_timezone;
    //$local_timezone = 'America/Chicago';

    if ($key === 'DTSTART' || $key === 'DTEND') {
        if (substr($val,-1) === 'Z') {
            $key = $key . ';TZID=' . $local_timezone;
            $val = substr($val,0,-1);

            // Convert Date/Time from UTC to Local Time
            date_default_timezone_set('UTC');
            $datetime = new DateTime($val);
            $local_time = new DateTimeZone($local_timezone);
            $datetime->setTimezone($local_time);
            $val = $datetime->format('Y-m-d H:i:s');
        }
    }
    return [ $key, $val ];
}

function filterICS($icalfeed, $config) {
    global $x_wr_calname;

    $max_event_count = $config['max_event_count'];

    // https://icalendar.org/php-library.html
    // https://github.com/zcontent/icalendar
    require_once("icalendar/zapcallib.php");
    $icalobj = new ZCiCal($icalfeed); // parse ical file

    // Extract header from original iCal feed
    $ical_header_end = strpos($icalfeed,'BEGIN:VEVENT');
    $ical_header = substr($icalfeed,0,$ical_header_end);
    $ical_footer = 'END:VCALENDAR';
    $ical_template = $ical_header . $ical_footer;
    $ical_template = str_replace('X-WR-CALNAME:','X-WR-CALNAME:' . $x_wr_calname . ' ',$ical_template);

    // Create new iCal feed using original headers
    $icalnew = new ZCiCal($ical_template);

    //echo "Number of events found: " . $icalobj->countEvents() . "\n";
    $ecount = 0;
    $return_event_count = 0;

    // read back icalendar data that was just parsed
    if(isset($icalobj->tree->child))
    {
        foreach($icalobj->tree->child as $node)
        {
            $match_keyword_include = false; // does this event match any include keywords?
            $match_keyword_exclude = false; // does this event match any exclude keywords?

            if($node->getName() == "VEVENT")
            {
                $event_summary = '';
                $ecount++;
                //echo "Event $ecount:\n";
                foreach($node->data as $key => $value)
                {
                    if ($key === 'SUMMARY' && !is_array($value)) {
                        $event_summary = $value->getValues();
                    }
                    if(is_array($value))
                    {
                        for($i = 0; $i < count($value); $i++)
                        {
                            $p = $value[$i]->getParameters();
                            //echo "  $key: " . $value[$i]->getValues() . "\n";
                            $val = $value[$i]->getValues();
                            if (checkValueInclude($val,$config)) {
                                $match_keyword_include = true;
                            }
                            if (checkValueExclude($val,$config)) {
                                $match_keyword_exclude = true;
                            }
                        }
                    }
                    else
                    {
                        //echo "  $key: " . $value->getValues() . "\n";
                        $val = $value->getValues();
                        if (checkValueInclude($val,$config)) {
                            $match_keyword_include = true;
                        }
                        if (checkValueExclude($val,$config)) {
                            $match_keyword_exclude = true;
                        }
                    }
                }
            }

            if ($match_keyword_include && !$match_keyword_exclude) {
                // add event to new ical file
                
                // create the event within the ical object
                $eventobj = new ZCiCalNode("VEVENT", $icalnew->curnode);

                $ecount++;
                //echo "Event $ecount:\n";
                foreach($node->data as $key => $value)
                {
                    if(is_array($value))
                    {
                        for($i = 0; $i < count($value); $i++)
                        {
                            $p = $value[$i]->getParameters();
                            //echo "  $key: " . $value[$i]->getValues() . "\n";
                            $val = $value[$i]->getValues();
                            $val = searchReplace($val,$config);
                            list($key, $val) = updateTimezone($key,$val);
                            $eventobj->addNode(new ZCiCalDataNode($key . ':' . $val));
                        }
                    }
                    else
                    {
                            //echo "  $key: " . $value->getValues() . "\n";
                            $val = $value->getValues();
                            $val = searchReplace($val,$config);
                            if ($key === 'DESCRIPTION') {
                                $val = nl2br(htmlEscapeAndLinkUrls($val));
                            }
                            if ($key === 'DESCRIPTION' && !empty($event_summary)) {
                                $val = '<h1>' . $event_summary . '</h1>\n\n' . $val;
                            }
                            list($key, $val) = updateTimezone($key,$val);
                            $eventobj->addNode(new ZCiCalDataNode($key . ':' . $val));
                    }
                }

                $return_event_count++;
                if ($max_event_count !== false && $return_event_count >= $max_event_count) {
                    break;
                }

            }
        }
    }
    //echo "Number of events found: " . $icalobj->countEvents() . "\n";
    //echo "Number of events found: " . $icalnew->countEvents() . "\n";

    return $icalnew->export();
}
