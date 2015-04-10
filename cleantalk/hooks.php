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
    
if(!function_exists('addlog'))    
{
	function addlog($s)
	{
		$f=fopen(dirname(__FILE__)."/log.txt","a");
		fwrite($f,$s."\n");
		fclose($f);
	}
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
	//addlog("activate!");
	
	$command = "getorders";
	$values=Array();
	$values["id"] = $vars['orderid'];
	$values["responsetype"] = "json";
	$result = localAPI($command,$values);
	//addlog(print_r($result,true));
	
	if($result['result']=='success')
	{
		$userid=$result['orders']['order'][0]['userid'];
		$items=$result['orders']['order'][0]['lineitems']['lineitem'];
		$is_cleantalk=false;
		$domain='';
		for($i=0;$i<sizeof($items);$i++)
		{
			if($items[$i]['type']=='product'&&@trim($items[$i]['domain'])!='')
			{
				$domain=$items[$i]['domain'];
			}
			if($items[$i]['type']=='addon'&&@strpos($items[$i]['product'],'CleanTalk')!==false&&$items[$i]['status']=='Active')
			{
				$is_cleantalk=true;
			}
		}
		if($domain!=''&&$is_cleantalk)
		{
			$command = "getclientsdetails";
			$values["clientid"] = $userid;
			$values["stats"] = true;
			$values["responsetype"] = "json";
			$uresult = localAPI($command,$values);
			if($uresult['result']=='success')
			{
				$email=$uresult['client']['email'];
				
				$cfg=full_query("SELECT value from tbladdonmodules where module='cleantalk' and setting='partner_api_key'");
				$cfg=mysql_fetch_array($cfg);
				
				$url = 'https://api.cleantalk.org';
				$data = array();
				$data['method_name'] = 'get_api_key'; 
				$data['email'] = $email;
				$data['website'] = $_SERVER['HTTP_HOST'];
				$data['platform'] = 'whmcs';
				$data['partner_api_key'] = $cfg['value'];
				$auth=send_request($url,$data,false);
				if($auth!==null)
				{
					$auth=json_decode($auth);
					if(isset($auth->data)&&isset($auth->data->auth_key))
					{
						$command = "logactivity";
						$adminuser = "admin";
						$values["description"] = "CleanTalk account $email succesfully created";						
						$results = localAPI($command,$values,$adminuser);
					}
					else if(isset($auth->error_no))
					{
						$command = "logactivity";
						$adminuser = "admin";
						$values["description"] = "Failed to create CleanTalk account: ".$auth->error_message;						
						$results = localAPI($command,$values,$adminuser);
					}
				}
			}
		}
	}
	
	if($result['result']=='success')
	{
		$email=$result['client']['email'];
	}
}

add_hook('ShoppingCartCheckoutCompletePage', 1, 'cleantalk_hook_order');