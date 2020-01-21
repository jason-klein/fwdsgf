<?php

// Personal Facebook User Event URL
$facebook_user_event_url = 'https://www.facebook.com/events/ical/upcoming/?uid=999999999&key=ZZZZZZZZZZZZZZZZ';

// Short name or abbreviation for your calendar. 3-5 characters recommended.
$x_wr_calname = 'ACME';

// Customize User Agent Sent to Facebook (e.g. MacOS Chrome 2020-01)
$user_agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36';

// Local Timezone Name
$local_timezone = 'America/Chicago';

// Configuration
$config = [
    // limit number of events returned (or false for unlimited)
    //'max_event_count' => 10,
    'max_event_count' => false,

    // only include events that contain one or more of these keywords
    'keywords_include' => [
        'aitp',
        'artificial intelligence',
        'aws',
        'cybersecurity',
        'cyber security',
        'developer',
        'efactory',
        'google',
        'ibm',
        'infosec',
        'isaca',
        'machine learning',
        'maker',
        'microsoft',
        'sgforum', // The Network annual YP event
        'software',
        'technology',
        'webdev',
        'web dev',
    ],
    // always exclude events that contain one or more of these keywords
    'keywords_exclude' => [
        //'springbike',
    ],
    // search/replace pairs
    'search_replace' => [
        'noreply@facebookmail.com' => 'events@example.com',
        'facebookuser@example.org' => 'events@example.com',
    ],
];

