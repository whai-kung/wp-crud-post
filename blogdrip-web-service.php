<?php

/*
Plugin Name: BlogDrip Web Service
Plugin URI: https://bremic.co.th
Description: WordPress Web Service is used to access WordPress resources via WSDL and SOAP. After installation simply open http://yoursite.com/blog/index.php/sbws to test your plugin.
Version: 1.0
Author: BREMIC Digital Services
Author URI: https://bremic.co.th
*/

/*  Copyright 2021 BREMIC Digital Services (email: servicedesk@bremic.co.th)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 3, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/* Change Log
Version: 0.0.2
Add feature `featureImage`

Version: 0.0.3
Add feature `schedule publish`

Version: 0.0.4
make its compatible with version 0.0.1

Version: 0.0.5
Add feature to put yoast seo detail

version: 1.0
Add feature auto update plugin
*/

/**
 * Set auto update
 */
require_once(dirname(__FILE__) . "/plugin-update-checker/plugin-update-checker.php");
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://my.blogdrip.com/wordpress/update-plugin.json',
	__FILE__, //Full path to the main plugin file or functions.php.
	'unique-plugin-or-theme-slug'
);

require_once(dirname(__FILE__) . "/includes/sbws-access.php");

/*
 * quick and dirty debug
 *
require_once(WPWS_SOAP_SERVER_FILE);
$x = new wp_WebService();
$x->getGallery(123);
exit;
/**/

/**
 * Catches index.php/wpws requests, stops further execution by WordPress
 * and handles the request depending on the request type.
 *
 * The are 3 types of treatment:
 * 1) User hasn't requested a WSDL file nor a SOAP operation => HTML output of general information
 * 2) User has requested requested WSDL file => deliveration by SoapServer instance
 * 3) User has submited a SOAP operation request => treatment by SoapServer instance
 *
 * Because the caller needs to know where he can access the service
 * the correct blog address needs to be specified in the service port of the WSDL.
 * For that reason only a template WSDL file exists. Should the script detect that
 * a customized WSDL with the correct address doesn't exist it creates it
 * by making a copy of the template WSDL and by replacing the address placeholder.
 * Should you ever need to reallocate the Blog simply delete the wpws.wsdl but provoke it's recreation.
 */
function sbws_handle_request($wp) {	
	// Look for the magic /wpws string in the $_SERVER variable
	$wpws_found = false;
	$wsdl_requested = false;
	foreach($_SERVER as $val) {
		if(is_string($val) && strlen($val) >= 5 && substr($val, 0, 5) == "/sbws") {
			$wpws_found = true;
			if(isset($_SERVER["QUERY_STRING"]) && strpos($_SERVER["QUERY_STRING"], "?wsdl") !== false) $wsdl_requested = true;
			break;
		}
	}
	
	if($wpws_found) {
		// make sure the QUERY_STRING is correctly set to ?wsdl so the SoapServer instance delivers the wsdl file
		if($wsdl_requested) {
			header("Content-type: text/xml");
			echo sbws_getWSDLfromTemplate();
			exit;
		} else if(!isset($_SERVER["HTTP_SOAPACTION"])) {
			// client hasn't requested a SOAP operation
			// return HTML page
			include(SBWS_INDEX_FILE);
		} else {
			// Create a customized WSDL file on disk
			// so the SoapServer can take this copy to load from.
			sbws_createWSDL();
			 
			// SoapServer handles both: deliveration of the requested WSDL file
			// and execution of SOAP operations
			header("Content-type: text/xml");
			require_once(SBWS_SOAP_SERVER_FILE);
			
			ini_set("soap.wsdl_cache_enabled", "0");
			$server = new SoapServer(SBWS_WSDL, array("cache_wsdl" => WSDL_CACHE_NONE));
			$server->setClass(SBWS_SOAP_SERVER_CLASS);
			$server->handle();
		}
		exit;
	}
	// no sbws-request, go on with WordPress execution
}

// creates a customized WSDL on plugin activation
register_activation_hook(__FILE__, 'sbws_createWSDL');

// checks whether the request should be handled by WPWS
add_action("parse_request", "sbws_handle_request");
?>