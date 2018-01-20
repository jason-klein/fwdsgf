<?php

/*
* Plugin Name: FWD/SGF
* Plugin URI: https://fwdsgf.com
* Description: FWD/SGF Customizations
* Version: 1.0.0
* Author: FWD/SGF
* Author URI: https://fwdsgf.com
* Text Domain: fwdsgf
* Domain Path: /languages
* License: MIT
*/

// Custom HTML TITLE prefix "FWD/SGF"
function fwdsgf_title_prefix($parts) {
    //print_r($parts);
    //$title = $parts['site'] . ' | ' . $parts['title']; // FWD/SGF | TITLE
    //$parts['title'] = $title;

    // Events for January 2018 - Technology Event Calendar for Springfield Missouri - FWD/SGF
    if (!empty($parts['tagline'])) {
        $parts['tagline'] .= ' - FWD/SGF';
    }

    return $parts;
}
add_filter('document_title_parts', 'fwdsgf_title_prefix', 10, 2);

?>
