<?php
/*
Plugin Name: 		GoUrl Paid Memberships Pro - Bitcoin Payment Gateway Addon
Plugin URI: 		https://gourl.io/bitcoin-payments-paid-memberships-pro.html
Description: 		Provides a <a href="https://gourl.io">GoUrl.io</a> Bitcoin/Altcoin Payment Gateway for <a href="https://wordpress.org/plugins/paid-memberships-pro/">Paid Memberships Pro 1.8+</a>. Direct Integration on your website, no external payment pages opens (as other payment gateways offer). Accept Bitcoin, BitcoinCash, Litecoin, Dash, Dogecoin, Speedcoin, Reddcoin, Potcoin, Feathercoin, Vertcoin, Peercoin, MonetaryUnit payments online. You will see the bitcoin/altcoin payment statistics in one common table on your website. No Chargebacks, Global, Secure. All in automatic mode.
Version: 			1.1.7
Author: 			GoUrl.io
Author URI: 		https://gourl.io
License: 			GPLv2
License URI: 		http://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: 	https://github.com/cryptoapi/Bitcoin-Gateway-Paid-Memberships-Pro
*/


if (!defined( 'ABSPATH' )) exit; // Exit if accessed directly

if (!function_exists('gourl_pmp_gateway_load'))
{
	// localisation
	add_action( 'plugins_loaded', 'gourl_pmp_load_textdomain' );
		
	// gateway load
	add_action( 'plugins_loaded', 'gourl_pmp_gateway_load', 20);
	
	DEFINE('GOURLPMP', "gourl-paidmembershipspro");



			
		
	
	function gourl_pmp_load_textdomain()
	{
		load_plugin_textdomain( GOURLPMP, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	
	
	function gourl_pmp_gateway_load()
	{

		// paid memberships pro required
		if (!class_exists('PMProGateway')) return;

		// load classes init method
		add_action('init', array('PMProGateway_gourl', 'init'));
		
		// add cryptocurrencies
		add_filter('pmpro_currencies', array('PMProGateway_gourl', 'pmpro_currencies'), 10, 1);
		
		// order log
		add_action('pmpro_after_order_settings', array('PMProGateway_gourl', 'pmpro_after_order_settings'));
		
		// custom confirmation page
		add_filter('pmpro_pages_shortcode_confirmation', array('PMProGateway_gourl', 'pmpro_pages_shortcode_confirmation'), 20, 1);
		
		// custom invoice
		add_filter('pmpro_invoice_bullets_bottom', array('PMProGateway_gourl', 'pmpro_invoice_bullets_bottom'), 20, 1);
		
		// plugin links
		add_filter('plugin_action_links', array('PMProGateway_gourl', 'plugin_action_links'), 10, 2 );


		// multiple gateway options at checkout
		add_filter("pmpro_get_gateway", array('PMProGateway_gourl', 'select_gateway'), 10, 1);
		add_filter("pmpro_valid_gateways", array('PMProGateway_gourl', 'valid_gateway'), 10, 1);
		add_action('admin_notices', array('PMProGateway_gourl', 'admin_notice'));
		add_action("pmpro_checkout_boxes", array('PMProGateway_gourl', 'checkout_boxes'));
		
		
		
		
		
		/*
		 *  1.
		*/
		class PMProGateway_gourl extends PMProGateway
		{
		
			/**
			 * 1.1
			 */
			function __construct($gateway = NULL)
			{
				$this->gateway = $gateway;
				return $this->gateway;
			}
		
			/**
			 * 1.2 Run on WP init
			 */
			public static function init()
			{
				//make sure Pay by Bitcoin/Altcoin is a gateway option
				add_filter('pmpro_gateways', array('PMProGateway_gourl', 'pmpro_gateways'));
		
				//add fields to payment settings
				add_filter('pmpro_payment_options', array('PMProGateway_gourl', 'pmpro_payment_options'));
				add_filter('pmpro_payment_option_fields', array('PMProGateway_gourl', 'pmpro_payment_option_fields'), 10, 2);
					
				//code to add at checkout
				$gateway = pmpro_getGateway();
				if($gateway == "gourl")
				{
					add_filter('pmpro_include_billing_address_fields', '__return_false');
					add_filter('pmpro_include_payment_information_fields', '__return_false');
					add_filter('pmpro_required_billing_fields', array('PMProGateway_gourl', 'pmpro_required_billing_fields'));
					add_filter('pmpro_checkout_before_change_membership_level', array('PMProGateway_gourl', 'pmpro_checkout_before_change_membership_level'), 1, 2);
				}
			}
		
			
			/**
			 * 1.3
			*/
			public static function plugin_action_links($links, $file)
			{
				static $this_plugin;
			
				if (false === isset($this_plugin) || true === empty($this_plugin)) {
					$this_plugin = plugin_basename(__FILE__);
				}
			
				if ($file == $this_plugin) {
					$settings_link = '<a href="'.admin_url('admin.php?page=pmpro-paymentsettings').'">'.__( 'Settings', GOURLPMP ).'</a>';
					array_unshift($links, $settings_link);
				}
			
				return $links;
			}
			
				
			/**
			 * 1.4 Make sure Gourl is in the gateways list
			 */
			public static function pmpro_gateways($gateways)
			{
				if(empty($gateways['gourl']))
				$gateways = array_slice($gateways, 0, 1) + array("gourl" => __('GoUrl Bitcoin/Altcoins', GOURLPMP)) + array_slice($gateways, 1);
		
				return $gateways;
			}
		
			/**
			 * 1.5 Get a list of payment options that the gourl gateway needs/supports.
			 */
			public static function getGatewayOptions()
			{
				$options = array(
						'gourl_defcoin',
						'gourl_deflang',
						'gourl_emultiplier',
						'gourl_emailuser',
						'gourl_emailadmin',
						'gourl_iconwidth',
						'currency'
				);
		
				return $options;
			}
		
			/**
			 * 1.6 Set payment options for payment settings page.
			 */
			public static function pmpro_payment_options($options)
			{
				//get stripe options
				$gourl_options = self::getGatewayOptions();
		
				//merge with others.
				$options = array_merge($gourl_options, $options);
		
				return $options;
			}
		
			/**
			 * 1.7 Add cryptocurrencies
			 */
			public static function pmpro_currencies($currencies)
			{
				global $gourl;
					
				if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
				{
					$arr = $gourl->coin_names();
						
					foreach ($arr as $k => $v)
					{
					    if (in_array($v, array("bitcoin", "bitcoincash", "dash"))) 	 $decimals = 4; // 25.0001,25.0002+
					    elseif (in_array($v, array("litecoin"))) 			         $decimals = 3; // 25.001
					    elseif (in_array($v, array("vertcoin", "peercoin"))) 	     $decimals = 2; // 25.01
					    else 									                     $decimals = 0; // 25
					    $currencies[$k] = array('name' => __( "Cryptocurrency", GOURLPMP ) . " - " . __( ucfirst($v), GOURLPMP ), 'symbol' => "&#160;".$k, 'decimals' => $decimals);
					}
				}
				
				__( 'Bitcoin', GOURLPMP );  // use in translation
					
				return $currencies;
			}
		
			/**
			 * 1.8 Display fields for Gourl options.
			 */
			public static function pmpro_payment_option_fields($options, $gateway)
			{
				global $gourl;
					
				$payments 		= array();
				$coin_names 	= array();
				$languages 		= array();
				$mainplugin_url = admin_url("plugin-install.php?tab=search&type=term&s=GoUrl+Bitcoin+Payment+Gateway+Downloads");
		
				$description  	= "<a target='_blank' href='https://gourl.io/'><img border='0' style='float:left; margin-right:15px' src='https://gourl.io/images/gourlpayments.png'></a>";
				$description  .= "<a target='_blank' href='https://gourl.io/bitcoin-payments-paid-memberships-pro.html'>".__( 'Plugin Homepage', GOURLPMP )."</a> &#160;&amp;&#160; <a target='_blank' href='https://gourl.io/bitcoin-payments-paid-memberships-pro.html#screenshot'>".__( 'screenshots', GOURLPMP )." &#187;</a><br>";
				$description  .= "<a target='_blank' href='https://github.com/cryptoapi/Bitcoin-Gateway-Paid-Memberships-Pro'>".__( 'Plugin on Github - 100% Free Open Source', GOURLPMP )." &#187;</a><br><br>";
				
				if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
				{
					if (true === version_compare(GOURL_VERSION, '1.4.1', '<'))
					{
						$description .= '<div style="background:#fff;border:1px solid #f77676;padding:7px"><p><b>' .sprintf(__( "Your GoUrl Bitcoin Gateway <a href='%s'>Main Plugin</a> version is too old. Requires 1.4.1 or higher version. Please <a href='%s'>update</a> to latest version.", GOURLPMP ), GOURL_ADMIN.GOURL, $mainplugin_url)."</b> &#160; &#160; &#160; &#160; " .
										__( 'Information', GOURLPMP ) . ": &#160; <a href='https://gourl.io/bitcoin-wordpress-plugin.html'>".__( 'Main Plugin Homepage', GOURLPMP )."</a> &#160; &#160; &#160; " .
										"<a href='https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/'>".__( 'WordPress.org Plugin Page', GOURLPMP )."</a></p></div><br>";
					}
					elseif (true === version_compare(PMPRO_VERSION, '1.8.4', '<'))
					{
						$description .= '<div style="background:#fff;border:1px solid #f77676;padding:7px"><p><b>' .sprintf(__( "Your PaidMembershipsPro version is too old. The GoUrl payment plugin requires PaidMembershipsPro 1.8.4 or higher to function. Please update to <a href='%s'>latest version</a>.", GOURLPMP ), admin_url('plugin-install.php?tab=search&type=term&s=paidmembershipspro+affiliates')).'</b></p></div><br>';
					}
					else
					{
						$payments 			= $gourl->payments(); 		// Activated Payments
						$coin_names			= $gourl->coin_names(); 	// All Coins
						$languages			= $gourl->languages(); 		// All Languages
					}
						
					$coins 	= implode(", ", $payments);
					$url	= GOURL_ADMIN.GOURL."settings";
					$url2	= GOURL_ADMIN.GOURL."payments&s=pmpro";
					$url3	= GOURL_ADMIN.GOURL;
					$text 	= ($coins) ? $coins : __( '- Please setup -', GOURLPMP );
				}
				else
				{
					$coins 	= "";
					$url	= $mainplugin_url;
					$url2	= $url;
					$url3	= $url;
					$text 	= '<b>'.__( 'Please install GoUrl Bitcoin Gateway WP Plugin', GOURLPMP ).' &#187;</b>';
						
					$description .= '<div style="background:#fff;border:1px solid #f77676;padding:7px;color:#444"><p><b>' .
							sprintf(__( "You need to install GoUrl Bitcoin Gateway Main Plugin also. Go to - <a href='%s'>Automatic installation</a> or <a href='%s'>Manual</a>.", GOURLPMP ), $mainplugin_url, "https://gourl.io/bitcoin-wordpress-plugin.html") . "</b> &#160; &#160; &#160; &#160; " .
							__( 'Information', GOURLPMP ) . ": &#160; &#160;<a href='https://gourl.io/bitcoin-wordpress-plugin.html'>".__( 'Main Plugin Homepage', GOURLPMP )."</a> &#160; &#160; &#160; <a href='https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/'>" .
							__( 'WordPress.org Plugin Page', GOURLPMP ) . "</a></p></div><br>";
						
				}
		
				$description  .= "<b>" . __( "Secure payments with virtual currency. <a target='_blank' href='https://bitcoin.org/'>What is Bitcoin?</a>", GOURLPMP ) . '</b><br>';
				$description  .= sprintf(__( 'Accept %s payments online in PaidMembershipsPro.', GOURLPMP ), __( ucwords(implode(", ", $coin_names)), GOURLPMP )).'<br>';
				if (class_exists('gourlclass')) $description .= sprintf(__( "If you use multiple websites online, please create separate <a target='_blank' href='%s'>GoUrl Payment Box</a> (with unique payment box public/private keys) for each of your websites. Do not use the same GoUrl Payment Box with the same public/private keys on your different websites.", GOURLPMP ), "https://gourl.io/editrecord/coin_boxes/0") . '<br><br>';

				
				$tr = '<tr class="gateway gateway_gourl"'.($gateway!="gourl"?' style="display: none;"':'').'>';
					
				// a
				$tmp  = '<tr class="pmpro_settings_divider gateway gateway_gourl"'.($gateway!="gourl"?' style="display: none;"':'').'>';
				$tmp .= '<td colspan="2">'.__('Gourl Bitcoin/Altcoin Settings', GOURLPMP).'</td>';
				$tmp .= "</tr>";
					
					
				// b
				$tmp .= $tr;
				$tmp .= '<td colspan="2"><div style="font-size:13px;line-height:22px">' . $description . '</div></td>';
				$tmp .= "</tr>";
					
					
				// c
				$defcoin = $options["gourl_defcoin"];
				if (!in_array($defcoin, array_keys($payments))) $defcoin = current(array_keys($payments));
					
				$tmp .= $tr.'<th scope="row" valign="top"><label for="gourl_defcoin">'.__( 'PaymentBox Default Coin', GOURLPMP ).'</label></th>
					<td><select name="gourl_defcoin" id="gourl_defcoin">';
				foreach ($payments as $k => $v) $tmp .= "<option value='".$k."'".self::sel($k, $defcoin).">".$v."</option>";
				$tmp .= "</select>";
				$tmp .= '<p class="description">'.sprintf(__( "Default Coin in Crypto Payment Box. Activated Payments : <a href='%s'>%s</a>", GOURLPMP), $url, $text)."</p></td>";
				
				$tmp .= "</tr>";
					
					
				// d
				$deflang = $options["gourl_deflang"];
				if (!in_array($deflang, array_keys($languages))) $deflang = current(array_keys($languages));
		
				$tmp .= $tr.'<th scope="row" valign="top"><label for="gourl_deflang">'.__( 'PaymentBox Language', GOURLPMP ).'</label></th>
					<td><select name="gourl_deflang" id="gourl_deflang">';
				foreach ($languages as $k => $v) $tmp .= "<option value='".$k."'".self::sel($k, $deflang).">".$v."</option>";
				$tmp .= "</select>";
				$tmp .= '<p class="description">'.__("Default Crypto Payment Box Localisation", GOURLPMP)."</p></td>";
				$tmp .= "</tr>";
					
					
				// e
				$emultiplier = str_replace("%", "", $options["gourl_emultiplier"]);
				if (!$emultiplier || !is_numeric($emultiplier) || $emultiplier <= 0) $emultiplier = "1.00";
					
				$tmp .= $tr.'<th scope="row" valign="top"><label for="gourl_emultiplier">'.__( 'Exchange Rate Multiplier', GOURLPMP ).'</label></th>
					<td><input type="text" value="'.$emultiplier.'" name="gourl_emultiplier" id="gourl_emultiplier">';
				$tmp .= '<p class="description">'.__('The system uses the multiplier rate with today LIVE cryptocurrency exchange rates (which are updated every 30 minutes) when the transaction is calculating from a fiat currency (e.g. USD, EUR, etc) to cryptocurrency. <br> Example: <b>1.05</b> - will add an extra 5% to the total price in bitcoin/altcoins, <b>0.85</b> - will be a 15% discount for the price in bitcoin/altcoins. Default: 1.00', GOURLPMP )."</p></td>";
				$tmp .= "</tr>";
					
					
				// f
				$emailtouser = $options["gourl_emailuser"];
				if ($emailtouser != "No") $emailtouser = "Yes";
				
				$tmp .= $tr.'<th scope="row" valign="top"><label for="gourl_emailuser">'.__( 'Send Email to Member', GOURLPMP ).'</label></th>
					         <td><select name="gourl_emailuser" id="gourl_emailuser">';
				$tmp .= "<option value='Yes'".self::sel($emailtouser, "Yes").">Yes</option>";
				$tmp .= "<option value='No'".self::sel($emailtouser, "No").">No</option>";
				$tmp .= "</select>";
				$tmp .= '<p class="description">'.__("Send email to Member after payment has been received", GOURLPMP)."</p></td>";
				$tmp .= "</tr>";
				

				// g
				$emailtoadmin = $options["gourl_emailadmin"];
				if ($emailtoadmin != "No") $emailtoadmin = "Yes";
				
				$tmp .= $tr.'<th scope="row" valign="top"><label for="gourl_emailadmin">'.__( 'Send Email to Admin', GOURLPMP ).'</label></th>
					         <td><select name="gourl_emailadmin" id="gourl_emailadmin">';
				$tmp .= "<option value='Yes'".self::sel($emailtoadmin, "Yes").">Yes</option>";
				$tmp .= "<option value='No'".self::sel($emailtoadmin, "No").">No</option>";
				$tmp .= "</select>";
				$tmp .= '<p class="description">'.__("Send email to Admin after payment has been received from member", GOURLPMP)."</p></td>";
				$tmp .= "</tr>";
				
				
				// h
				$iconwidth = str_replace("px", "", $options["gourl_iconwidth"]);
				if (!$iconwidth || !is_numeric($iconwidth) || $iconwidth < 30 || $iconwidth > 250) $iconwidth = 60;
				$iconwidth = $iconwidth . "px";
					
				$tmp .= $tr.'<th scope="row" valign="top"><label for="gourl_iconwidth">'.__( 'Icons Size', GOURLPMP ).'</label></th>
					<td><input type="text" value="'.$iconwidth.'" name="gourl_iconwidth" id="gourl_iconwidth">';
				$tmp .= '<p class="description">'.__( "Cryptocoin icons size in 'Select Payment Method' that the customer will see on your checkout. Default 60px. Allowed: 30..250px", GOURLPMP )."</p></td>";
				$tmp .= "</tr>";
					
					
				// i
				$tmp .= $tr.'<th scope="row" valign="top"><label for="gourl_boxstyle">'.__( 'PaymentBox Style', GOURLPMP ).'</label></th>
					<td>'.sprintf(__( "Payment Box <a href='%s'>sizes</a> and border <a href='%s'>shadow</a> you can change <a href='%s'>here &#187;</a>", GOURLPMP ), "https://gourl.io/images/global/sizes.png", "https://gourl.io/images/global/styles.png", $url."#gourlmonetaryunitprivate_key")."</td>";
				$tmp .= "</tr>";
					
					
				// k
				$tmp .= $tr.'<th scope="row" valign="top"><label for="gourl_lang">'.__( 'Languages', GOURLPMP ).'</label></th>
					<td>'.sprintf(__( "If you want to use GoUrl PaidMembershipsPro Bitcoin Gateway plugin in a language other than English, see the page <a href='%s'>Languages and Translations</a>", GOURLPMP ), "https://gourl.io/languages.html")."</td>";
				$tmp .= "</tr>";
				
						
				echo $tmp;
					
				return;
			}
		
			
		
		
			/**
			 * 1.9 Remove required billing fields
			 */
			public static function pmpro_required_billing_fields($fields)
			{
				unset($fields['bfirstname']);
				unset($fields['blastname']);
				unset($fields['baddress1']);
				unset($fields['bcity']);
				unset($fields['bstate']);
				unset($fields['bzipcode']);
				unset($fields['bphone']);
				unset($fields['bemail']);
				unset($fields['bcountry']);
				unset($fields['CardType']);
				unset($fields['AccountNumber']);
				unset($fields['ExpirationMonth']);
				unset($fields['ExpirationYear']);
				unset($fields['CVV']);
					
				return $fields;
			}
		
			
		

			/**
			 * 1.10 Process checkout.
			 *
			 */
			function process(&$order)
			{
				return true;
			}
			
			
				
			
			/**
			 * 1.11 Redirect to bitcoin/altcoin payment page
			 */
			public static function pmpro_checkout_before_change_membership_level($user_id, $order)
			{
			    global $pmpro_currency, $discount_code_id, $wpdb;
				
				if(!session_id()) session_start();
			
				if (!$order || $order->gateway != "gourl") 
				{
				    unset($_SESSION['gourl_pmp_orderid']); 
				    unset($_SESSION['gourl_pmp_orderdt']);
				    return true;
				}
				
				
				$order->payment_type = __('GoUrl Bitcoin/Altcoin', GOURLPMP);
				$order->gateway 	 	= "gourl";
				$order->user_id 	 	= get_current_user_id();
				$order->timestamp       = current_time('timestamp');
				
				
				if(!empty($order->TotalBillingCycles)) $order->TotalBillingCycles++;
				
				if(is_numeric($order->membership_level->cycle_number) && $order->membership_level->cycle_number > 0 && $order->membership_level->cycle_period &&  
				    !($order->membership_level->expiration_number && $order->membership_level->expiration_period &&
				           strtotime("+ " . $order->membership_level->expiration_number." ".$order->membership_level->expiration_period) < strtotime("+ " . $order->membership_level->cycle_number." ".$order->membership_level->cycle_period)))
			    {				    
				    $order->membership_level->expiration_number = $order->membership_level->cycle_number; 
				    $order->membership_level->expiration_period = $order->membership_level->cycle_period;
			    }
			     
			    
			    // new membership settings
			    
			    $old_startdate = current_time('timestamp');
			    $old_enddate = current_time('timestamp');
			    $new_startdate = $old_enddate; 
			    
			    $active_levels = pmpro_getMembershipLevelsForUser($user_id);
			    if (is_array($active_levels))
			    foreach ($active_levels as $row)
			    {
			       if ($row->id == $order->membership_level->id && $row->enddate > current_time('timestamp')) 
			       {
			           if ($row->startdate > strtotime("2010-01-01")) $old_startdate = $row->startdate;
			           $old_enddate   = $row->enddate;
			           if ($old_enddate > $new_startdate) $new_startdate = $old_enddate; 
			       }
			    }

			    // subscription start/end
			    $startdate = "'" . date("Y-m-d H:i:s", $old_startdate) . "'";
			    $enddate = (!empty($order->membership_level->expiration_number)) ? "'" . date("Y-m-d H:i:s", strtotime("+ ".$order->membership_level->expiration_number." ".$order->membership_level->expiration_period, $old_enddate)) . "'" : "NULL";
			    
			    $order->subscription_transaction_id = ($enddate == "NULL") ? __('NO EXPIRY', GOURLPMP) : date("d M y", $new_startdate) . " - " . date("d M y", strtotime(trim($enddate, "'")));
			    
			    
			    $custom_level = array(
			        'user_id' 			=> $user_id,
			        'membership_id' 	=> $order->membership_level->id,
			        'code_id' 			=> '',
			        'initial_payment' 	=> $order->membership_level->initial_payment,
			        'billing_amount' 	=> $order->membership_level->billing_amount,
			        'cycle_number' 		=> $order->membership_level->cycle_number,
			        'cycle_period' 		=> $order->membership_level->cycle_period,
			        'billing_limit' 	=> $order->membership_level->billing_limit,
			        'trial_amount' 		=> $order->membership_level->trial_amount,
			        'trial_limit' 		=> $order->membership_level->trial_limit,
			        'startdate' 		=> $startdate,
			        'enddate' 			=> $enddate);
			     
			    
			    
				// is it initial payment ?
				if(!get_option(GOURL."PMPRO_INIT_".$user_id."_".$order->membership_level->id))
				{
					 
					if (floatval($order->subtotal) > 0)
					{
					    $order->total = $order->subtotal;
					}
					// Free Trial or Free Membership
					else
					{
						// Initial Free Payment
						update_option(GOURL."PMPRO_INIT_".$user_id."_".$order->membership_level->id, date("d F Y"));
						
						if (floatval($order->PaymentAmount) > 0) 
						{
							// Free Trial Payment
							$order->TrialBillingPeriod = $order->BillingPeriod;
							$order->TrialBillingFrequency = $order->BillingFrequency;
							$order->TrialBillingCycles++;
							$order->TrialAmount = 0;
							update_option(GOURL."PMPRO_FREE_".$user_id."_".$order->membership_level->id, date("d F Y"));
						}

					    $prevorder = new MemberOrder();
					    $prevorder->getLastMemberOrder($user_id, apply_filters("pmpro_confirmation_order_status", array("success")));
					    $prevorder->updateStatus("-success-");
						
					    pmpro_changeMembershipLevel($custom_level, $user_id, 'changed');
						
						$order->payment_transaction_id = (floatval($order->PaymentAmount) > 0) ? "#FREETRIAL" : "#FREE";
						$order->status = "success";
						$order->saveOrder();
						
						$user = (!$order->user_id) ? __('Guest', GOURLPMP) : "<a href='".admin_url("user-edit.php?user_id=".$order->user_id)."'>user".$order->user_id."</a>";
						self::add_order_note($order->id, sprintf(__("Order Created by %s <br>Membership - %s <br>%s (%s)", GOURLPMP ), $user, $order->membership_level->name, $order->payment_transaction_id, $order->subscription_transaction_id));
						
					}
				}
					
				// second, third, etc payments ....
				else
				{
				    // not allow duplicated trials
				    if (floatval($order->PaymentAmount) != floatval($order->subtotal))
				    {
				        if (floatval($order->PaymentAmount) > 0)
				        {
				            $order->subtotal = $order->PaymentAmount;
				            $order->total 	 = $order->PaymentAmount;
				        }
				        else $order->total 	 = $order->subtotal;
				    }
				    else  $order->total 	 = $order->subtotal;
				    
				    
				    if (floatval($order->subtotal) == 0)
					{
					    $prevorder = new MemberOrder();
					    $prevorder->getLastMemberOrder($user_id, apply_filters("pmpro_confirmation_order_status", array("success")));
					    $prevorder->updateStatus("-success-");
					    
					    pmpro_changeMembershipLevel($custom_level, $user_id, 'changed');
					    
					    $order->payment_transaction_id = "#FREE";
						$order->status = "success";
						$order->saveOrder();
						
						$user = (!$order->user_id) ? __('Guest', GOURLPMP) : "<a href='".admin_url("user-edit.php?user_id=".$order->user_id)."'>user".$order->user_id."</a>";
						self::add_order_note($order->id, sprintf(__("Order Created by %s <br>Membership - %s <br>%s (%s)", GOURLPMP ), $user, $order->membership_level->name, $order->payment_transaction_id, $order->subscription_transaction_id));
					}
				}

				
				// check for previous pending orders
				if ($order->total > 0)
				{
    				$morder = new MemberOrder();
    				$morder->getLastMemberOrder(get_current_user_id(), apply_filters("pmpro_confirmation_order_status", array("pending")), $order->membership_id, "gourl");
    
    				if ($morder->gateway != "gourl" || !isset($morder->total) || $morder->total != $order->total || $morder->membership_id != $order->membership_id || $morder->timestamp < (current_time('timestamp') - 24*60*60))
    				{
    					$order->status = "pending";
    					$order->saveOrder();
    					
    					$user = (!get_current_user_id()) ? __('Guest', GOURLPMP) : "<a href='".admin_url("user-edit.php?user_id=".get_current_user_id())."'>user".get_current_user_id()."</a>";
    					self::add_order_note($order->id, sprintf(__("Order Created by %s <br>Membership - %s <br>Awaiting Cryptocurrency Payment - %s <br>Invoice <a href='%s'>#%s</a>", GOURLPMP ), $user, $order->membership_level->name.($order->membership_level->expiration_number?", ".$order->membership_level->expiration_number." ".$order->membership_level->expiration_period:""), $order->total . " " . $pmpro_currency, pmpro_url("invoice", "?invoice=" . $order->code), $order->code));
    				}
    				else $order->id = $morder->id;  
				}
				
				$_SESSION['gourl_pmp_orderid'] = $order->id; 
				$_SESSION['gourl_pmp_orderdt'] = ($enddate == "NULL") ? __('NO EXPIRY', GOURLPMP) : date("d M Y", $new_startdate) . " - " . date("d M Y", strtotime(trim($enddate, "'")));

				//save discount code use
				if(!empty($discount_code_id))
				    $wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discount_code_id . "', '" . $user_id . "', '" . $order->id . "', now())");
				  
				    
				do_action( 'pmpro_before_send_to_gourl', $user_id, $order );
				
				
				wp_redirect(pmpro_url("confirmation"));
				die();
		
				return true;
			}
		
		
			
		
			/**
			 * 1.12 Custom confirmation page
			 *
			 */
			public static function pmpro_pages_shortcode_confirmation($content)
			{
				global $wpdb, $current_user, $pmpro_currency;
				
				if(!session_id()) session_start();
				
				if (!isset($_SESSION['gourl_pmp_orderid'])) return $content;

    			$order = new MemberOrder();
		        $order->getMemberOrderByID($_SESSION['gourl_pmp_orderid']);
				 
				if (!empty($order) && $order->gateway == "gourl" && isset($order->total) && $order->total > 0 && $order->user_id == get_current_user_id())
				{
					$content = self::pmpro_gourl_cryptocoin_payment($order, false);
				}
		
		
				return $content;
		
			}
		
		
			
			
			/**
			 *  1.13. GoUrl Payment Box
			 */
			public static function pmpro_gourl_cryptocoin_payment ($order, $invoice = false)
			{
				global $gourl, $pmpro_currency, $current_user, $wpdb;
		
				$tmp = "";
				
				
				if (!empty($order) && $order->gateway == "gourl" && isset($order->total) && $order->total > 0)
				{   
				
    				$levelName = $wpdb->get_var("SELECT name FROM $wpdb->pmpro_membership_levels WHERE id = '" . $order->membership_id . "' LIMIT 1");
    				
    				if ($invoice) $tmp .= '<h2>' . __( 'Details', GOURLPMP ) . '</h2>' . PHP_EOL;
    				
    				$tmp .= "<table>";
    				
    				if (!$invoice) $tmp .= "<tr><td><strong>".__('Account', GOURLPMP).":</strong></td><td>".$current_user->display_name." (".$current_user->user_email.")</td></tr>";
    				
    				$tmp .= "<tr><td><strong>".__('Order', GOURLPMP).":</strong></td><td>".$order->code."</td></tr>
						    <tr><td><strong>".__('Amount', GOURLPMP).":</strong></td><td>".$order->total." ".$pmpro_currency."</td></tr>
							<tr><td><strong>".__('Membership Level', GOURLPMP).":</strong></td><td>".$levelName."</td></tr>";
    				
    				if ($invoice && $order->subscription_transaction_id) $tmp .= "<tr><td><strong>".__('Membership Period', GOURLPMP).":</strong></td><td>".$order->subscription_transaction_id."</td></tr>";
    				if (!$invoice) $tmp .= "<tr><td><strong>".__('Membership Period', GOURLPMP).":</strong></td><td>".$_SESSION['gourl_pmp_orderdt']."</td></tr>";
					
    				$tmp .= "<tr><td><strong>".__('Invoice Status', GOURLPMP).":</strong></td><td>---INVOICE--STATUS----</td></tr>";
    				
					$tmp .= "</table>";
				}
				
				elseif (!$order)
				{
					$tmp .= '<h2>' . __( 'Information', GOURLPMP ) . '</h2>' . PHP_EOL;
					$tmp .= "<div class='pmpro_message pmpro_error'>".sprintf(__( 'The GoUrl payment plugin was called to process a payment but could not retrieve the order details for orderID %s. Cannot continue!', GOURLPMP ), $order_id)."</div>";
					
					return $tmp;
				}
				elseif ($order->gateway != "gourl") return false;
				
		
				// Initialize
				// ------------------------
				if (class_exists('gourlclass') && defined('GOURL') && is_object($gourl))
				{
					$payments 		= $gourl->payments(); 		// Activated Payments
					$coin_names		= $gourl->coin_names(); 	// All Coins
					$languages		= $gourl->languages(); 		// All Languages
				}
				else
				{
					$payments 		= array();
					$coin_names 	= array();
					$languages 		= array();
				}
		
				$defcoin = pmpro_getOption("gourl_defcoin");
				if (!in_array($defcoin, array_keys($payments))) $defcoin = current(array_keys($payments));
		
				$deflang = pmpro_getOption("gourl_deflang");
				if (!in_array($deflang, array_keys($languages))) $deflang = current(array_keys($languages));
		
				$emultiplier = str_replace("%", "", pmpro_getOption("gourl_emultiplier"));
				if (!$emultiplier || !is_numeric($emultiplier) || $emultiplier <= 0) $emultiplier = "1.00";
		
				$iconwidth = str_replace("px", "", pmpro_getOption("gourl_iconwidth"));
				if (!$iconwidth || !is_numeric($iconwidth) || $iconwidth < 30 || $iconwidth > 250) $iconwidth = 60;
				$iconwidth = $iconwidth . "px";
		
		
		
				// Current Order
				// -----------------
				$order_id 			= $order->id;
				$order_total		= $order->total;
				$order_currency		= $pmpro_currency;
				$order_user_id		= $order->user_id;
		
		
		
				// Security
				// -------------
				if (!$order_id)
				{
					$tmp .= '<h2>' . __( 'Information', GOURLPMP ) . '</h2>' . PHP_EOL;
					$tmp .= "<div class='pmpro_message pmpro_error'>".sprintf(__( 'The GoUrl payment plugin was called to process a payment but could not retrieve the order details for orderID %s. Cannot continue!', GOURLPMP ), $order_id)."</div>";
				}
				elseif ($order_user_id && $order_user_id != get_current_user_id() && !current_user_can('manage_options'))
				{ 
					return false;
				}
				elseif (!class_exists('gourlclass') || !defined('GOURL') || !is_object($gourl))
				{
					$tmp .= '<h2>' . __( 'Information', GOURLPMP ) . '</h2>' . PHP_EOL;
					$tmp .= "<div class='pmpro_message pmpro_error'>".sprintf(__( "Please try a different payment method. Admin need to install and activate wordpress plugin <a href='%s'>GoUrl Bitcoin Gateway for Wordpress</a> to accept Bitcoin/Altcoin Payments online.", GOURLPMP ), "https://gourl.io/bitcoin-wordpress-plugin.html")."</div>";
				}
				elseif (!$payments || !$defcoin || true === version_compare(PMPRO_VERSION, '1.8.4', '<') || true === version_compare(GOURL_VERSION, '1.4.14', '<'))
				{
					$tmp .= '<h2>' . __( 'Information', GOURLPMP ) . '</h2>' . PHP_EOL;
					$tmp .=  "<div class='pmpro_message pmpro_error'>".sprintf(__( 'Sorry, but there was an error processing your order. Please try a different payment method or contact us if you need assistance (GoUrl Bitcoin Plugin v1.4.1+ not configured / %s not activated)', GOURLPMP ),(!$payments || !$defcoin || !isset($coin_names[$order_currency])?__("Cryptocurrency", GOURLPMP):$coin_names[$order_currency]))."</div>";
				}
				else
				{
					$plugin			= "gourlpmpro";
					$amount 		= $order_total;
					$currency 		= $order_currency;
					$orderID		= "order" . $order_id;
					$userID			= $order_user_id;
					$period			= "NOEXPIRY";
					$language		= $deflang;
					$coin 			= $coin_names[$defcoin];
					$affiliate_key 	= "gourl";
					$crypto			= array_key_exists($currency, $coin_names);
		
					if (!$userID) $userID = "guest"; // allow guests to make payments
		
		
					if (!$userID)
					{
						$tmp .= '<h2>' . __( 'Information', GOURLPMP ) . '</h2>' . PHP_EOL;
						$tmp .= "<div align='center'><a href='".wp_login_url(get_permalink())."'>
					<img style='border:none;box-shadow:none;' title='".__('You need first to login or register on the website to make Bitcoin/Altcoin Payments', GOURLPMP )."' vspace='10'
					src='".$gourl->box_image()."' border='0'></a></div>";
					}
					elseif ($amount <= 0)
					{
						$tmp .= '<h2>' . __( 'Information', GOURLPMP ) . '</h2>' . PHP_EOL;
						$tmp .= "<div class='pmpro_message pmpro_error'>". sprintf(__( "This order's amount is '%s' - it cannot be paid for. Please contact us if you need assistance.", GOURLPMP ), $amount ." " . $currency)."</div>";
					}
					else
					{
		
						// Exchange (optional)
						// --------------------
						if ($currency != "USD" && !$crypto)
						{
							$amount = gourl_convert_currency($currency, "USD", $amount);
		
							if ($amount <= 0)
							{
								$tmp .= '<h2>' . __( 'Information', GOURLPMP ) . '</h2>' . PHP_EOL;
								$tmp .= "<div class='pmpro_message pmpro_error'>".sprintf(__( 'Sorry, but there was an error processing your order. Please try later or use a different payment method. Cannot receive exchange rates for %s/USD from Google Finance', GOURLPMP ), $currency)."</div>";
							}
							else $currency = "USD";
						}
		
						if (!$crypto) $amount = $amount * $emultiplier;
		
		
						// Payment Box
						// ------------------
						if ($amount > 0)
						{
							// crypto payment gateway
							$result = $gourl->cryptopayments ($plugin, $amount, $currency, $orderID, $period, $language, $coin, $affiliate_key, $userID, $iconwidth);

							if (!$result["is_paid"])
							{ 
							    if (in_array($order->status, array("pending", "cancelled"))) $tmp = str_replace("---INVOICE--STATUS----", "<span style='color:red'>".__('UNPAID', GOURLPMP), $tmp)."</span>";
							    else $tmp = str_replace("---INVOICE--STATUS----", "-", $tmp);
							    	
								// trial used before
								if ($userID != "guest" && get_option(GOURL."PMPRO_FREE_".$userID."_".$order->membership_id) && !$invoice && $wpdb->get_var("SELECT 	(billing_amount-initial_payment) as dif FROM $wpdb->pmpro_membership_levels WHERE id = '" . $order->membership_id . "' LIMIT 1") > 0) 
								{
								    $tmp .= "<div style='color:green'>" . sprintf(__('Notes - You have already used your trial on %s', GOURLPMP ), get_option(GOURL."PMPRO_FREE_".$userID."_".$order->membership_id)) . "</div><br>";
								}
								
								//$tmp .= '<br><h3>' . __( 'Pay Now -', GOURLPMP ) . '</h3>' . PHP_EOL;
								$tmp .= "<br><script>
									           jQuery(document).ready(function() {
								                   jQuery( '.entry-title' ).text('" . __( 'Pay Now -', GOURLPMP ) . "');
								               });
								         </script>";
								
							}
							else 
							{
							    $tmp = str_replace("---INVOICE--STATUS----", "<span style='color:green'>".__('FULLY PAID', GOURLPMP), $tmp)."</span>";
							    $tmp .= '<br><br>';
							}
							    
		
							if ($result["error"]) $tmp .= "<div class='pmpro_message pmpro_error'>".__( "Sorry, but there was an error processing your order. Please try a different payment method.", GOURLPMP )."<br/>".$result["error"]."</div>";
							else
							{
								// display payment box or successful payment result
								$tmp .= $result["html_payment_box"];
		
								if ($order_user_id == get_current_user_id())
								{	
									// payment received
									if ($result["is_paid"])
									{
									    
									    $tmp .= "<br><br>";
									    
										if ($invoice)
											$tmp .= "<div align='center'>" . __('Thank you for your membership.', GOURLPMP) . "</div>";
										else
											$tmp .= "<div align='center'>" . sprintf(__('<b>Thank you for your membership to %s.</b><br>Your %s membership is now active.', GOURLPMP), get_bloginfo("name"), $current_user->membership_level->name) . "</div>";
									}
									
									if (!$invoice) $tmp .= "<br><br><div align='center'><a href=".pmpro_url("account").">".__('View Your Membership Account', GOURLPMP)." &rarr;</a>";
								}
							}
						}
					}
				}
		
				$tmp .= "<br><br>";
		
				return $tmp;
			}
				
		
		
			
			/**
			 * 1.14 Custom invoice
			 *
			 */
			public static function pmpro_invoice_bullets_bottom($order)
			{
				if (empty($order) || $order->gateway != "gourl" || $order->total == 0) return true;
				
				
				echo self::pmpro_gourl_cryptocoin_payment($order, true);
				
				
				return true;
			}
			
		
		
			
			/**
			 * 1.15 Show payment log on order details page
			 */
			public static function pmpro_after_order_settings($order)
			{
				if (!empty($order) && $order->gateway == "gourl")
				{
					$data = self::display_order_notes();
		
					if ($data)
					{
						$tmp  = '<tr><th scope="row" valign="top"></th>';
						$tmp .= '<td>';
						$tmp .= $data;
						$tmp .= '</td>';
						$tmp .= '</tr>';
		
						echo $tmp;
					}
				}
		
				return true;
			}
		
			
		
			
			/**
			 * 1.16 Save payment log
			 */
			public static function add_order_note($order_id, $notes)
			{
				$id	= GOURLPMP."_".$order_id."_gourl_log";
				$dt = date("d M Y, H:i", current_time('timestamp'));
		
				$arr = get_option($id);
				if (!$arr) $arr = array();
				$arr[] = "<tr><th style='padding-top:15px' valign='top'>" . $dt . "</th><td>" . $notes . "</td></tr>";
				update_option($id, $arr);
		
				return true;
			}
		
			
			
		
			/**
			 * 1.17 Display payment log
			 */
			public static function display_order_notes()
			{
				$tmp = "";
				if (is_admin() && isset($_GET["order"]) && is_numeric($_GET["order"]) && isset($_GET["page"]) && $_GET["page"] == "pmpro-orders")
				{
					$order_id = $_GET["order"];
						
					$data = get_option(GOURLPMP."_".$order_id."_gourl_log");
		
					if ($data)
					{
						$tmp  = "<br><h3>". __("Payment Log", GOURLPMP)." -</h3>";
						$tmp .= "<table>" . implode("\n", $data) . "</table>";
					}
				}
		
				return $tmp;
			}
		
			
			
		
			/**
			 * 1.18
			 */
			public static function sel($val1, $val2)
			{
				$tmp = ((is_array($val1) && in_array($val2, $val1)) || strval($val1) == strval($val2)) ? ' selected="selected"' : '';
		
				return $tmp;
			}
			
			
			
			
			// multiple gateway options at checkout
			// ----------------------------------------
			
			
			/**
			 * 1.19 Use gateway which selected on checkout
			 */
			public static function select_gateway($gateway)
			{
			    if(!session_id()) session_start();
			
			    if (isset($_POST["gateway"]))
			    {
			        $gateway = $_SESSION['gourl_pmp_gateway'] = $_POST["gateway"];
			    }
			    else
			    {
			        if (isset($_SESSION['gourl_pmp_gateway']) && $_SESSION['gourl_pmp_gateway'] == "gourl") $gateway = $_SESSION['gourl_pmp_gateway'];
			    }
			
			    return $gateway;
			}
			
			
			
			/**
			 * 1.20 Add gourl to list valid gateways
			 */
			public static function valid_gateway($gateways)
			{
			    if (array_search('gourl', $gateways) === FALSE) $gateways[] = 'gourl';
			
			    return $gateways;
			}
			
			
			
			/**
			 * 1.21 Notice for admin
			 */
			public static function admin_notice()
			{
			    //make sure we're on the payment settings page
			    if( !empty( $_REQUEST['page'] ) && $_REQUEST['page'] == 'pmpro-paymentsettings' )
			    {
			        $tmp = '<div class="notice notice-info is-dismissible" style="margin:20px">';
			        $tmp .= '<img style="float:left" alt="New" src="' . plugins_url("/images/new.png", __FILE__) . '" border="0" vspace="12" hspace="10">';
			        $tmp .= '<p>' . sprintf(__( "<b>You can offer your customers multiple Gateway Options at PaidMembershipPro Checkout. <a href='%s'>Screenshot &#187;</a></b><br>To get this facility you need to setup Gourl 'Bitcoin/Altcoins' settings on this page, click 'Save Settings' button, and then switch to another gateway (for example, Paypal, or Stripe) and keep that other gateway as a primary gateway. The GoUrl settings will be remembered 'in the background' and the two gateways will be displayed on the checkout page. If you want to use Gourl Bitcoin/Altcoin on checkout page only you should keep the Gourl Gateway as your primary gateway. If you don't want to use the Bitcoin gateway, simply disable 'GoUrl Paid Memberships Pro' addon on your plugin page.<br>Also you can setup Optional Free or Reduced-price Trial Period in Paid Memberships Pro with Bitcoins (<a href='%s'>screenshot</a>). &#160; More info on <a target='_blank' href='%s'>www.gourl.io</a>", GOURLPMP ), plugins_url("/screenshot-7.png", __FILE__), plugins_url("/screenshot-6.png", __FILE__), "https://gourl.io") .'</p>';
			        $tmp .= '</div>';
			        echo $tmp;
			    }
			
			    return true;
			}
			
			
			
			/**
			 * 1.22 Add radio boxes on checkout page
			 */
			public static function checkout_boxes()
			{
			    global $pmpro_requirebilling, $gateway, $pmpro_review;
			     
			    //if already using gourl, ignore this
			    $setting_gateway = get_option("pmpro_gateway");
			    if($setting_gateway == "gourl")
			    {
			        echo '<h2>' . __('Payment method', GOURLPMP) . '</h2>';
			        echo __('Bitcoin/Altcoin', GOURLPMP) . '<img style="vertical-align:middle" src="' . plugins_url("/images/crypto.png", __FILE__) . '" border="0" vspace="10" hspace="10" height="43" width="143"><br><br>';
			        return true;
			    }
			
			    $arr = pmpro_gateways();
			    $setting_gateway_name = (isset($arr["$setting_gateway"]) && $arr["$setting_gateway"]) ? $arr["$setting_gateway"] : ucwords($setting_gateway);
			
			    $image = $setting_gateway;
			    if (in_array($image, array("paypalexpress", "paypal", "payflowpro", "paypalstandard"))) $image = "paypal";
			    if (!in_array($image, array("authorizenet", "braintree", "check", "cybersource", "gourl", "paypal", "stripe", "twocheckout"))) $image = "creditcards";
			
			    //only show this if we're not reviewing and the current gateway isn't a gourl gateway
			    if(empty($pmpro_review))
			    {
			        ?>
					<div id="pmpro_payment_method" class="pmpro_checkout" <?php if(!$pmpro_requirebilling) { ?>style="display: none;"<?php } ?>>
					<br><h2><?php _e('Choose your payment method', GOURLPMP) ?> -</h2>
					<div class="pmpro_checkout-fields">
					
						<span class="gateway_gourl">
						<input type="radio" name="gateway" value="gourl" <?php if($gateway == "gourl") { ?>checked="checked"<?php } ?> />
						<a href="javascript:void(0);" class="pmpro_radio" style="box-shadow:none"><?php _e('Bitcoin/Altcoin', GOURLPMP) ?></a>
						<img style="vertical-align:middle" src="<?php echo plugins_url("/images/crypto.png", __FILE__); ?>" border="0" vspace="10" hspace="10" height="43" width="143"> 
						</span>
						
						<br>
						<span class="gateway_<?php echo esc_attr($setting_gateway); ?>">
						<input type="radio" name="gateway" value="<?php echo esc_attr($setting_gateway);?>" <?php if(!$gateway || $gateway == $setting_gateway) { ?>checked="checked"<?php } ?> />
						<a href="javascript:void(0);" class="pmpro_radio" style="box-shadow:none"><?php _e($setting_gateway_name, GOURLPMP) ?></a>
						<img style="vertical-align:middle" src="<?php echo plugins_url("/images/".$image.".png", __FILE__); ?>" border="0" vspace="10" hspace="10" height="43"> 
						</span>
						<br><br><br>
						
					</div>
			</div> <!--end pmpro_payment_method -->
			
			
			<?php //here we draw the gourl Express button, which gets moved in place by JavaScript ?>
			<script>	
				var pmpro_require_billing = <?php if($pmpro_requirebilling) echo "true"; else echo "false";?>;
				
				//choosing payment method
				jQuery(document).ready(function() {		
					//move gourl express button into submit box
					jQuery('#pmpro_gourl_checkout').appendTo('div.pmpro_submit');
					
					function showLiteCheckout()
					{
						jQuery('#pmpro_billing_address_fields').hide();
						jQuery('#pmpro_payment_information_fields').hide();
						jQuery('#pmpro_paypalexpress_checkout, #pmpro_paypalstandard_checkout, #pmpro_payflowpro_checkout, #pmpro_paypal_checkout').hide();	
						jQuery('#pmpro_submit_span').show();		
						
						pmpro_require_billing = false;		
					}
					
					function showFullCheckout()
					{
						jQuery('#pmpro_billing_address_fields').show();
						jQuery('#pmpro_payment_information_fields').show();
						
						pmpro_require_billing = true;
					}
					
					
					//detect gateway change
					jQuery('input[name=gateway]').click(function() {		
						if(jQuery(this).val() != 'gourl')
						{
							showFullCheckout();
						}
						else
						{			
							showLiteCheckout();
						}
					});
					
					//update radio on page load
					if(jQuery('input[name=gateway]:checked').val() != 'gourl' && pmpro_require_billing == true)
					{
						showFullCheckout();
					}
					else
					{
						showLiteCheckout();
					}
					
					//select the radio button if the label is clicked on
					jQuery('a.pmpro_radio').click(function() {
						jQuery(this).prev().click();
					});
				});
			</script>
			<?php
			}
			else
			{
			?>
			<script>
				//choosing payment method
				jQuery(document).ready(function() {		
					jQuery('#pmpro_billing_address_fields').hide();
					jQuery('#pmpro_payment_information_fields').hide();			
				});		
			</script>
			<?php
			}	
		}			

	}
	// end class
		
		
		
		
		
		
		
		/*
		*  2. Instant Payment Notification Function - pluginname."_gourlcallback"    
		*
		*  This function will appear every time by GoUrl Bitcoin Gateway when a new payment from any user is received successfully.
		*  Function gets user_ID - user who made payment, current order_ID (the same value as you provided to bitcoin payment gateway),
		*  payment details as array and box status.
		*
		*  The function will automatically appear for each new payment usually two times :
		*  a) when a new payment is received, with values: $box_status = cryptobox_newrecord, $payment_details[is_confirmed] = 0
		*  b) and a second time when existing payment is confirmed (6+ confirmations) with values: $box_status = cryptobox_updated, $payment_details[is_confirmed] = 1.
		*
		*  But sometimes if the payment notification is delayed for 20-30min, the payment/transaction will already be confirmed and the function will
		*  appear once with values: $box_status = cryptobox_newrecord, $payment_details[is_confirmed] = 1
		*
		*  Payment_details example - https://gourl.io/images/plugin2.png
		*  Read more - https://gourl.io/affiliates.html#wordpress
		*/
		function gourlpmpro_gourlcallback ($user_id, $order_id, $payment_details, $box_status)
		{
			global $wpdb;
		
			if (!in_array($box_status, array("cryptobox_newrecord", "cryptobox_updated"))) return false;
		
			if (strpos($order_id, "order") === 0) $order_id = intval(substr($order_id, 5)); else return false;
		
			if (!$user_id || $payment_details["status"] != "payment_received") return false;
		
		
			// Initialize
			$coinName 	= ucfirst($payment_details["coinname"]);
			$amount		= $payment_details["amount"] . " " . $payment_details["coinlabel"] . "&#160; ( $" . $payment_details["amountusd"] . " )";
			$payID		= $payment_details["paymentID"];
			$trID		= $payment_details["tx"];
			$confirmed	= ($payment_details["is_confirmed"]) ? __('Yes', GOURLPMP) : __('No', GOURLPMP);
		

			$order = new MemberOrder();
			$order->getMemberOrderByID($order_id);
				
			
			// New Payment Received
			if ($box_status == "cryptobox_newrecord")
			{
				if (!empty($order)) update_option(GOURL."PMPRO_INIT_".$user_id."_".$order->membership_id, date("d F Y"));
				PMProGateway_gourl::add_order_note($order_id, sprintf(__("<b>%s</b> payment received <br>%s <br>Payment id <a href='%s'>#%s</a>. Awaiting network confirmation...", GOURLPMP), $coinName, $amount, GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID));
			}
		
		
			// Existing Payment confirmed (6+ confirmations)
			if ($payment_details["is_confirmed"])
			{
				PMProGateway_gourl::add_order_note($order_id, sprintf(__("%s Payment id <a href='%s'>#%s</a> Confirmed", GOURLPMP), $coinName, GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID));
			}
		
		
			// Update User Membership
			if (!empty($order) && $order->gateway == "gourl" && in_array($order->status, array("pending", "review", "token")))
			{
			    
			    
				$pmpro_level = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_membership_levels WHERE id = '" . (int)$order->membership_id . "' LIMIT 1");
					

				if(is_numeric($pmpro_level->cycle_number) && $pmpro_level->cycle_number > 0 && $pmpro_level->cycle_period &&
				    !($pmpro_level->expiration_number && $pmpro_level->expiration_period &&
				        strtotime("+ " . $pmpro_level->expiration_number." ".$pmpro_level->expiration_period) < strtotime("+ " . $pmpro_level->cycle_number." ".$pmpro_level->cycle_period)))
				{
				    $pmpro_level->expiration_number = $pmpro_level->cycle_number;
				    $pmpro_level->expiration_period = $pmpro_level->cycle_period;
				}
				 
				 
				$old_startdate = current_time('timestamp');
				$old_enddate = current_time('timestamp');
				 
				$active_levels = pmpro_getMembershipLevelsForUser($user_id);
				if (is_array($active_levels))
				    foreach ($active_levels as $row)
				    {
				        if ($row->id == $pmpro_level->id && $row->enddate > current_time('timestamp'))
				        {
				            $old_startdate = $row->startdate;
				            $old_enddate   = $row->enddate;
				        }
				    }

				// subscription start/end
				$startdate = "'" . date("Y-m-d H:i:s", $old_startdate) . "'";
				$enddate = (!empty($pmpro_level->expiration_number)) ? "'" . date("Y-m-d H:i:s", strtotime("+ ".$pmpro_level->expiration_number." ".$pmpro_level->expiration_period, $old_enddate)) . "'" : "NULL";
				
				$prevorder = new MemberOrder();
				$prevorder->getLastMemberOrder($user_id, apply_filters("pmpro_confirmation_order_status", array("success")));
				$prevorder->updateStatus("-success-");
				
				$custom_level = array(
						'user_id' 			=> $user_id,
						'membership_id' 	=> $pmpro_level->id,
						'code_id' 			=> '',
						'initial_payment' 	=> $pmpro_level->initial_payment,
						'billing_amount' 	=> $pmpro_level->billing_amount,
						'cycle_number' 		=> $pmpro_level->cycle_number,
						'cycle_period' 		=> $pmpro_level->cycle_period,
						'billing_limit' 	=> $pmpro_level->billing_limit,
						'trial_amount' 		=> $pmpro_level->trial_amount,
						'trial_limit' 		=> $pmpro_level->trial_limit,
						'startdate' 		=> $startdate,
						'enddate' 			=> $enddate);
					
				pmpro_changeMembershipLevel($custom_level, $user_id, 'changed');
				
				
				$order->membership_id 					= $pmpro_level->id;
				$order->payment_transaction_id 			= strtoupper($coinName) . " #" . $payID;
				$order->status 							= "success";
				$order->saveOrder();
				
				
				// New Payment Received
				if ($box_status == "cryptobox_newrecord")
				{  
				    //hook
				    do_action("pmpro_after_checkout", $order->user_id, $order);
				        
				    //setup some values for the emails
				    $invoice = new MemberOrder($order->id);

				    $user = get_userdata($order->user_id);
				    if(!empty($user))
				    {
				        $user->membership_level = new stdClass();
				        $user->membership_level   = $pmpro_level;
				        
				        $invoice->cardtype = $coinName . ", &#160; " . $trID . ", &#160; " . $amount;
				        $invoice->expirationmonth = "-";
				        $invoice->expirationyear = "-";
		
				        //send email to member
				        if (pmpro_getOption("gourl_emailuser") == "Yes")
				        {
    				        $pmproemail = new PMProEmail();
    				        $pmproemail->sendCheckoutEmail($user, $invoice);
				        }
		
				        //send email to admin
				        if (pmpro_getOption("gourl_emailadmin") == "Yes")
				        {
				            $pmproemail = new PMProEmail();
				            $pmproemail->sendCheckoutAdminEmail($user, $invoice);
				        }
				    }
			    
				}				
			}

			return true;         
		}

	}
}
  
  