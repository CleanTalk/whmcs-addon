<?php

/**
 * CleanTalk Anti-Spam
 *
 *
 * @package    CleanTalk Anti-Spam
 * @author     CleanTalk <development@whmcs.com>
 * @copyright  Copyright (c) CleanTalk 2015
 * @license    https://cleantalk.org/publicoffer
 * @version    $Id$
 * @link       https://cleantalk.org/
 */

if (!defined("WHMCS"))
    die("This file cannot be accessed directly");

function cleantalk_config() {
    $configarray = array(
    "name" => "CleanTalk anti-spam addon",
    "description" => 'Protect your website against spam bots. Anti-spam for comments, registrations, orders, bookings and contacts. <a href="http://cleantalk.org/publicoffer">License agreement</a>.',
    "version" => "1.0",
    "author" => "CleanTalk",
    "language" => "english",
    "fields" => array(
        "partner_api_key" => array ("FriendlyName" => "Hoster API key", "Type" => "text", "Size" => "32", "Description" => "API key<br /><a href='https://cleantalk.org/my/?cp_mode=hosting-antispam' target='_blank'>Hoster Dashboard</a>", "Default" => "" ),
    ));
    return $configarray;
}

function cleantalk_activate() {

	$ct_plans_query = full_query("SELECT * from tbladdons where lower(name) like '%leantalk%'");
	if (mysql_num_rows($ct_plans_query) == 0){
		$addon_type=array(
            "name" => 'Anti-spam protection by CleanTalk',
            "description" => 'Protect your website against spam bots. Anti-spam for comments, registrations, orders, bookings and contacts. <a href="http://cleantalk.org/publicoffer">License agreement</a>.',
            "billingcycle" => "Annually",
            "showorder" => "on",
            "welcomeemail" => 0,
            "weight" => 1,
			"autoactivate" => "on");
		
		$addon_pricing = array(
	        "type" => "addon",
	        "currency" => 1
	    );
		
		$addonid               = insert_query("tbladdons", $addon_type);
	    $plan_pricing          = $addon_pricing;
	    $plan_pricing['relid'] = $addonid;
	    $pricingid             = insert_query("tblpricing", $plan_pricing);
	}

    # Return Result
    return array('status'=>'success','description'=>'This is an demo module only. In a real module you might instruct a user how to get started with it here...');
    return array('status'=>'error','description'=>'You can use the error status return to indicate there was a problem activating the module');
    return array('status'=>'info','description'=>'You can use the info status return to display a message to the user');

}

function cleantalk_deactivate() {
    # Return Result
    return array('status'=>'success','description'=>'If successful, you can return a message to show the user here');
    return array('status'=>'error','description'=>'If an error occurs you can return an error message for display here');
    return array('status'=>'info','description'=>'If you want to give an info message to a user you can return it here');

}

function cleantalk_upgrade($vars) {

}

function cleantalk_output($vars) {

}

function cleantalk_sidebar($vars) {

    $modulelink = $vars['modulelink'];
    $version = $vars['version'];
    $LANG = $vars['_lang'];

    $sidebar = '<span class="header"><img src="images/icons/addonmodules.png" class="absmiddle" width="16" height="16" /> Example</span>
<ul class="menu">
        <li><a href="#">Demo Sidebar Content</a></li>
        <li><a href="#">Version: '.$version.'</a></li>
    </ul>';
    return $sidebar;
}
if(!function_exists('addlog'))    
{
	function addlog($s)
	{
		$f=fopen("F:/denwer_new/home/whmcs.example.com/www/modules/addons/cleantalk/log.txt","a");
		fwrite($f,$s."\n");
		fclose($f);
	}
}