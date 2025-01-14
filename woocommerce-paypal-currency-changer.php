<?php
/*
Plugin Name: WooCommerce PayPal Currency Changer
Plugin URI: https://www.mcwebdesign.ro/2018/01/17/woocommerce-paypal-unsupported-currency-wordpress-plugin/
Description: Change (unsupported) PayPal currency to 'EUR', 'GBP', 'JPY' or 'USD', after checking out with PayPal in WooCommerce store. After activation, you can find the plugin under admin WooCommerce menu "PayPal Currency". 
Version: 1.0
Author: MC Web Design
Author URI: https://www.mcwebdesign.ro/
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.txt
*/


// check if WooCommerce is active
if ( ! WooPaypalCurrency::is_woocommerce_active() )
	return;
$GLOBALS['WooPaypalCurrency'] = new WooPaypalCurrency();


class WooPaypalCurrency {
	//define valid PayPal Currencies
	public $acc_currencies=array( 'EUR', 'GBP', 'JPY', 'USD' );

    protected $option_name = 'wupc-options';
	
	//default settings
    protected $data = array(
        'target_currency' => 'EUR',
        'conversion_rate' => '1.0',
		'auto_update' => 'on',
		'api_selection' => 'coinbase',
    );

    public function __construct() {

        add_action('init', array($this, 'init'));

        // admin sub-menu
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'wupc_add_options_pages'));

        // check if plugin is activated
        register_activation_hook(__FILE__, array($this, 'activate'));

    }

    public function activate() {
		global $woocommerce;
		$options = get_option('wupc-options');
		$exchange_data = wupc_get_exchangerate($options['target_currency'],get_woocommerce_currency());
		$options['conversion_rate'] = $exchange_data;
		$options['results_count'] = $options['results_count'] + 1;
		update_option( 'wupc-options', $options );
    }	
	
    public function init() {

		// add current WooCommerce currency to Paypal and convert
		$options = get_option('wupc-options');

		add_filter('woocommerce_paypal_args', 'change_currency', 11);  
		function change_currency($paypal_args){ 
			global $woocommerce;
			$options = get_option('wupc-options');

			if ( $paypal_args['currency_code'] == get_woocommerce_currency()){  
				$convert_rate = $options['conversion_rate']; //set the conversion rate  
				$paypal_args['currency_code'] = $options['target_currency']; 
				$i = 1;  
				while (isset($paypal_args['amount_' . $i])) {  
					$paypal_args['amount_' . $i] = round( $paypal_args['amount_' . $i] / $convert_rate, 2);
					++$i;  
				}  
				if ( $paypal_args['shipping_1'] > 0 ) {
					$paypal_args['shipping_1'] = round( $paypal_args['shipping_1'] / $convert_rate, 2);
				}	
				if ( $paypal_args['discount_amount_cart'] > 0 ) {
					$paypal_args['discount_amount_cart'] = round( $paypal_args['discount_amount_cart'] / $convert_rate, 2);
				}
				if ( $paypal_args['tax_cart'] > 0 ) {
					$paypal_args['tax_cart'] = round( $paypal_args['tax_cart'] / $convert_rate, 2);
				}
			}
			return $paypal_args;  
		}  
	}

    // white list our plugin options 
    public function admin_init() {
        register_setting('wupc_options', $this->option_name, array($this, 'validate'));
    }

    // add entry in the WooCommerce settings menu
    public function wupc_add_options_pages() {
		add_submenu_page( 'woocommerce', 'PayPal Currency',  'PayPal Currency' , 'manage_options', 'wupc_options', array($this, 'wupc_options_do_page') );
    }

    // print the menu page itself
    public function wupc_options_do_page() {
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		global $woocommerce;

        $options = get_option($this->option_name);

		$fromto = get_woocommerce_currency().$options['target_currency'];
		if ($_GET['page']=='wupc_options'){
			$exchange_data = wupc_get_exchangerate($options['target_currency'],get_woocommerce_currency());
			wp_register_script( 'wupc_script', plugins_url( '/js/wupc_script.js', __FILE__ ),'woocommerce.min.js', '1.0', true);//pass variables to javascript
			wp_register_script( 'woocommerce_admin', $woocommerce->plugin_url() . '/assets/js/admin/woocommerce_admin.min.js', array( 'jquery', 'jquery-tiptip'), $woocommerce->version );
			wp_enqueue_style( 'woocommerce_admin_styles', $woocommerce->plugin_url() . '/assets/css/admin.css' );
			$data = array(	
							'source_currency' => $options['target_currency'],
							'target_currency' => get_woocommerce_currency(),
							'amount'=>$exchange_data,
							);			
			wp_localize_script('wupc_script', 'php_data', $data);
			wp_enqueue_script('wupc_script');
			wp_enqueue_script( 'woocommerce_admin' );
		}

	$currency_selector='<select id="target_curr" name="'.$this->option_name.'[target_currency]">';
		
		foreach($this->acc_currencies as $key => $value)
				{
					if ($options['target_currency']==$value){
						$currency_selector.= '<option value="'.$value.'" selected="selected">'.$value.'</option>';
						}else{
						$currency_selector.= '<option value="'.$value.'">'.$value.'</option>';
						}
				};
		$currency_selector.='</select>
		<label for="wupc_target_curr"> (convert to currency)</label>';

        
        echo '<div class="wrap">
            <h2>'. __('WooCommerce PayPal Currency Changer Settings').'</h2>
			<p>You can support the author for further development by donating a small amount. It helps a lot!</p>
			<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
				<input type="hidden" name="cmd" value="_s-xclick">
				<input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----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-----END PKCS7-----">
				<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
				<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
			</form>
			<p>Visit the <a href="https://www.mcwebdesign.ro/2018/01/17/woocommerce-paypal-unsupported-currency-wordpress-plugin/">plugin homepage</a> for more details.</p>	
            <form method="post" action="options.php">';
        settings_fields('wupc_options');

		echo '<div class="error settings-error" visibility="hidden"><p>Please check your current <strong>Conversion Rate</strong> setting!</p></div>';

		echo'   <table class="form-table">
 				<tbody>
                   <tr valign="top">
						<th class="titledesc" scope="row">
							<label >'.__('Current Currency').': </label>
							<img class="help_tip" data-tip="'.__('Current shop currency as setted in general WooCommerce settings.').'" src="'.plugins_url().'/woocommerce/assets/images/help.png" height="16" width="16" />
						</th>
                        <td class="forminp"><input type="text" size="3" value="'.get_woocommerce_currency().'"  disabled/><label for="wupc_source_curr"> (convert from currency &raquo; this is your WooCommerce Shop Currency)</label></td>
                    </tr>
                    <tr valign="top">
					<th class="titledesc" scope="row">
							<label >'.__('Target Currency').': </label>
							<img class="help_tip" data-tip="'.__('Desired target currency, what you expect to be billed in PayPal.').'" src="'.plugins_url().'/woocommerce/assets/images/help.png" height="16" width="16" />
						</th>
                        <td class="forminp">'. $currency_selector .'</td>
                    </tr>
                    <tr valign="top">
						<th class="titledesc" scope="row">
							<label >'. __('Conversion Rate').': </label>
							<img class="help_tip" data-tip="'. __('Accept suggested Coinbase rate or set your own conversion rate.').'" src="'.plugins_url().'/woocommerce/assets/images/help.png" height="16" width="16" />
						</th>
						<td class="forminp" ><input type="text" id="cr" size="7" name="'. $this->option_name.'[conversion_rate]" value="'.$options['conversion_rate'].'" /><img class="help_tip" data-tip="'. __('Input will be red when custom currency is not equal to suggested currency.').'" src="'.plugins_url().'/woocommerce/assets/images/help.png" height="16" width="16" />
							'. __('Coinbase exchange rate:').'&nbsp; <input type="button" id="selected_currency" value="'.$exchange_data.'"/>
						</td>
                    </tr>
				</tbody>
				</table>
				<tbody>
					<tr valign="middle">
						<th scope="row" class="titledesc">
							<input type="submit" class="button-primary" value="'. __('Save Changes') .'" />
						</th>
					</tr>					
				</tbody>
				</table>
	         </form>
		</div>';
				
    }


	 // check if WooCommerce is active
	public static function is_woocommerce_active() {

		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() )
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );

		return in_array( 'woocommerce/woocommerce.php', $active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins );
	}	

	public function validate($input) {

		$valid = array();
		$valid['target_currency'] = $input['target_currency'];
		$valid['conversion_rate'] = sanitize_text_field($input['conversion_rate']);
		$valid['api_selection'] = $input['api_selection'];
		return $valid;
	}


	public function wupc_logging($msg) {
			global $woocommerce;
			$this->log = $woocommerce->logger();
			$this->log->add( 'wupc', $msg);
	}

}

	// add current currency to WooCommerce PayPal accepted currencies
	add_filter( 'woocommerce_paypal_supported_currencies', 'wupc_add_paypal_valid_currency' );
	function wupc_add_paypal_valid_currency( $currencies ) {    
		array_push ( $currencies , get_woocommerce_currency() );  
		return $currencies;    
	}
	
	// get currency exchange rates url response
	function wupc_request_data( $url ) {
		$response = '';
		// first, we try to use wp_remote_get
		$response = wp_remote_get( $url, array( 'timeout' => 120, 'httpversion' => '1.1', 'user-agent'  => 'Mozilla/5.0 (compatible; Googlebot/2.1)' ) );
		// if the response is an array, we want to capture the body index for json_decode
		if( is_array( $response ) ) {
			$response = $response['body'];
		} 
		if( (is_wp_error( $response )) || ($response == null) ) {
			// if that doesn't work, then we'll try file_get_contents
			if ( ini_get( 'allow_url_fopen' ) ) {
				$response = file_get_contents( $url );
			}
		} 
		return ( '' != $response ? json_decode( $response ) : false );
	}
		
	//retrieve exchange data from Coinbase API
	function wupc_get_exchangerate( $currency_from, $currency_to ) {
		$response = wupc_request_data( "https://api.coinbase.com/v2/exchange-rates?currency=$currency_from" );
		return ( isset( $response->data->rates->{$currency_to} ) ? $response->data->rates->{$currency_to} : 0 );		
	}

?>