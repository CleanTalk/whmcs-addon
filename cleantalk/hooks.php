<?php
/**
 * CleanTalk Anti-Spam hook file
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
   
require_once(dirname(__FILE__)."/JSON.php");
require_once(dirname(__FILE__)."/cleantalk.class.php");
define("CLEANTALK_LOG", true);

function cleantalk_addlog($message)
{
	if(CLEANTALK_LOG)
	{
		$command = "logactivity";
		$values["description"] = $message;						
		$results = localAPI($command,$values,cleantalk_getadmin());
	}
}

function cleantalk_getadmin()
{
	$query=full_query("SELECT username from tbladmins limit 1;");
	$row=mysql_fetch_array($query);
	return($row['username']);
}
    
function send_request($url,$data,$isJSON)
{
	$result=null;
	if(!$isJSON)
	{
		$data=http_build_query($data);
	}
	else
	{
		$data= json_encode($data);
	}
	if (function_exists('curl_init') && function_exists('json_decode'))
	{
	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		
		// receive server response ...
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		// resolve 'Expect: 100-continue' issue
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
		
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		
		$result = curl_exec($ch);
		curl_close($ch);
	}
	else
	{
		$opts = array(
		    'http'=>array(
		        'method'=>"POST",
		        'content'=>$data)
		);
		$context = stream_context_create($opts);
		$result = @file_get_contents($url, 0, $context);
	}
	return $result;
}



function cleantalk_hook_order($vars)
{
	$ct_dates=Array();
	$ct_dates['Monthly']=1;
	$ct_dates['Quarterly']=3;
	$ct_dates['Semi-Annually']=6;
	$ct_dates['Annually']=12;
	$ct_dates['Biennially']=24;
	$ct_dates['Triennially']=36;
	cleantalk_addlog("Hooked order activation");
	
	$command = "getorders";
	$values=Array();
	$values["id"] = $vars['orderid'];
	$values["responsetype"] = "json";
	$result = localAPI($command,$values, cleantalk_getadmin());
	cleantalk_addlog("Called GetOrders");
	cleantalk_addlog("Result:".print_r($result,true));
	
	if($result['result']=='success')
	{
		cleantalk_addlog("API call success");
		$userid=$result['orders']['order'][0]['userid'];
		$items=$result['orders']['order'][0]['lineitems']['lineitem'];
		$is_cleantalk=false;
		$domain='';
		$cycle='';
		$renew=12;
		for($i=0;$i<sizeof($items);$i++)
		{
			if($items[$i]['type']=='product'&&@trim($items[$i]['domain'])!='')
			{
				$domain=$items[$i]['domain'];
				$cycle=$items[$i]['billingcycle'];
			}
			if($items[$i]['type']=='addon'&&@strpos($items[$i]['product'],'CleanTalk')!==false)//&&$items[$i]['status']=='Active'
			{
				$is_cleantalk=true;
			}
		}
		if(isset($ct_dates[$cycle]))
		{
			$renew=$ct_dates[$cycle];
		}
		if($is_cleantalk)
		{
			cleantalk_addlog("CleanTalk added to order");
		}
		else
		{
			cleantalk_addlog("CleanTalk not added to order");
		}
		if($domain=='')
		{
			cleantalk_addlog("Domain not added!");
		}
		else
		{
			cleantalk_addlog("Domain added!");
		}
		
		//if($domain!=''&&$is_cleantalk)
		if($is_cleantalk)
		{
			$command = "getclientsdetails";
			$values["clientid"] = $userid;
			$values["stats"] = true;
			$values["responsetype"] = "json";
			$uresult = localAPI($command,$values,cleantalk_getadmin());
			//addlog("uresult:\n".print_r($uresult,true)."\n\n");
			cleantalk_addlog("Called GetClientsDetails");
			cleantalk_addlog("Result:".print_r($uresult,true));
			if($uresult['result']=='success')
			{
				cleantalk_addlog("API call success");
				$email=$uresult['client']['email'];
				cleantalk_addlog("User email: ".$email);
				
				$cfg=full_query("SELECT value from tbladdonmodules where module='cleantalk' and setting='partner_api_key'");
				$cfg=mysql_fetch_array($cfg);
				
				if(@trim($cfg['value'])!='')
				{
					$url = 'https://api.cleantalk.org';
					$data = array();
					$data['method_name'] = 'get_api_key'; 
					$data['email'] = $email;
					$data['website'] = $domain;
					$data['platform'] = 'whmcs';
					$data['hoster_api_key'] = $cfg['value'];
					$data['locale'] = $_LANG['isocode']."_".strtoupper($_LANG['isocode']);
					$auth=send_request($url,$data,false);
					cleantalk_addlog("Sending request to CleanTalk servers");
					if($auth!==null)
					{
						$auth=json_decode($auth);
						if(isset($auth->data)&&isset($auth->data->auth_key))
						{
							$command = "logactivity";
							$values["description"] = "CleanTalk account $email succesfully created";						
							$results = localAPI($command,$values,cleantalk_getadmin());
							
							$url = 'https://api.cleantalk.org';
							$data = array();
							$data['method_name'] = 'extend_license'; 
							$data['email'] = $email;
							$data['platform'] = 'whmcs';
							$data['billing_cycle'] = 'month';
							$data['period'] = $renew;
							$data['hoster_api_key'] = $cfg['value'];
							$auth=send_request($url,$data,false);
							if($auth!==null)
							{
								$auth=json_decode($auth);
								if(isset($auth->data)&&isset($auth->data->extended))
								{
									cleantalk_addlog("CleanTalk account $email extended till ".$auth->data->extended);
								}
								else if(isset($auth->error_no))
								{
									cleantalk_addlog("Failed to extend CleanTalk account: ".$auth->error_message);
								}
							}
							else
							{
								cleantalk_addlog("Failed to extend CleanTalk account!");
							}
						}
						else if(isset($auth->error_no))
						{
							$command = "logactivity";
							$values["description"] = "Failed to create CleanTalk account: ".$auth->error_message;						
							$results = localAPI($command,$values,cleantalk_getadmin());
						}
					}
				}
				else
				{
					$command = "logactivity";
					$values["description"] = "Failed to create CleanTalk account: please enter Hoster API key";						
					$results = localAPI($command,$values,cleantalk_getadmin());
				}
			}
		}
	}
	
	if($result['result']=='success')
	{
		$email=$result['client']['email'];
	}
}

function cleantalk_hook_invoice_paid($invoiceid)
{
	/*cleantalk_addlog("Hooked invoice paid! Invoice info: ".print_r($invoiceid,true));
	$command = "getinvoice";

	$values=Array();
	$values["responsetype"] = "json";
	$values["invoiceid"] = $invoiceid['invoiceid'];
	
	$invoice_results = localAPI($command,$values,cleantalk_getadmin());
	cleantalk_addlog("Called GetInvoice");
	cleantalk_addlog("Result:".print_r($invoice_results,true));
	
	$userid=$invoice_results['userid'];
	
	$values=Array();
	$command = "getclientsdetails";
	$values["clientid"] = $userid;
	$values["stats"] = true;
	$values["responsetype"] = "json";
	$uresult = localAPI($command,$values,cleantalk_getadmin());
	cleantalk_addlog("Called GetClientsDetails");
	cleantalk_addlog("Result:".print_r($uresult,true));

	$email=$uresult['client']['email'];
	
	
	$url = 'https://api.cleantalk.org';
	$data = array();
	$data['method_name'] = 'extend_license'; 
	$data['email'] = $email;
	$data['platform'] = 'whmcs';
	
	if($invoice_results['recurcycle'] == 'Months')
	{
		$data['billing_cycle'] = 'months';
	}
	else if($invoice_results['recurcycle'] == 'Years')
	{
		$data['billing_cycle'] = 'years';
	}
	
	$data['period'] = $invoice_results['recurfor'];
	
	$cfg=full_query("SELECT value from tbladdonmodules where module='cleantalk' and setting='partner_api_key'");
	$cfg=mysql_fetch_array($cfg);

	$data['hoster_api_key'] = $cfg['value'];

	$auth=send_request($url,$data,false);
	if($auth!==null)
	{
		$auth=json_decode($auth);
		if(isset($auth->data)&&isset($auth->data->extended))
		{
			$command = "logactivity";
			$values["description"] = "CleanTalk account $email succesfully extended till ".$auth->data->extended;						
			$results = localAPI($command,$values,cleantalk_getadmin());
		}
		else if(isset($auth->error_no))
		{
			$command = "logactivity";
			$values["description"] = "Failed to extend CleanTalk account: ".$auth->error_message;						
			$results = localAPI($command,$values,cleantalk_getadmin());
		}
	}*/
	
}

add_hook('ShoppingCartCheckoutCompletePage', 1, 'cleantalk_hook_order');
add_hook('AcceptOrder', 1, 'cleantalk_hook_order');
add_hook('InvoicePaid', 1, 'cleantalk_hook_invoice_paid');