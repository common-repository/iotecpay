<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Test environment global variable
$test_env = "";

/*
 * Add ioTec menu to the Admin Control Panel (Reserved for future use)
 */
// Hook the 'admin_menu' action hook, run the function named 'iotec_Add_My_Admin_Link()'
// add_action('admin_menu', 'iotec_Add_Admin_Link');

// Add a new top level menu link to the Admin Control Panel
function iotec_Add_Admin_Link()
{
    add_menu_page(
        'Settings',
        // Title of the page
        'IOTEC',
        // Text to show on the menu link
        'manage_options',
        // Capability requirement to see the link
        plugin_dir_path(__FILE__) . '/iotec-first-acp-page.html' // The 'slug' - file to display when clicking the link

    );
}

// Test to see if WooCommerce is active (including network activation).
$plugin_path = trailingslashit(WP_PLUGIN_DIR) . 'woocommerce/woocommerce.php';

if (
    in_array($plugin_path, wp_get_active_and_valid_plugins())
    || in_array($plugin_path, wp_get_active_network_plugins())
) {
    // Custom code here. WooCommerce is active, however it has not 
    // necessarily initialized (when that is important, consider
    // using the `woocommerce_init` action).

    // Initialize payment gateway
    add_action('plugins_loaded', 'init_iotec');
}

// Initialize the ioTec plugin
function init_iotec()
{
    class WC_Gateway_Iotec extends WC_Payment_Gateway
    {
        public function __construct()
        {
            // Constructor variables
            $this->id = "iotec";
            $this->icon = plugin_dir_url(dirname(__FILE__)) . 'assets/images/icon-128x128.png';
            $this->has_fields = true;
            $this->method_title = __("ioTecPay", 'iotec');
            $this->method_description = __("Accept Airtel Money and MTN MoMo payments in Uganda on your WordPress site.", 'iotec');

            // ioTec settings form fields in admin panel (WooCommerce -> payments -> iotec -> manage)
            $this->form_fields = array(
                // Title (text input field is hidden and disabled)
                'title' => array(
                    'title' => 'ioTecPay',
                    'type' => 'text',
                    'description' => 'This is the title of the payment option the user sees during checkout.',
                    'default' => 'ioTecPay',
                    'desc_tip' => true,
                    'disabled' => true,
                    'css' => 'visibility:hidden;',
                ),
                // Plugin enabled or disabled
                'enabled' => array(
                    'title' => __('Enable / Disable', 'iotec'),
                    'label' => __('Enable this payment gateway', 'iotec'),
                    'type' => 'checkbox',
                    'default' => 'no',
                ),
                // ioTec wallet ID
                'iotec_wallet' => array(
                    'title' => __('Wallet ID', 'iotec'),
                    'type' => 'text',
                    'desc_tip' => __('Enter the Wallet ID provided to you by ioTec.', 'iotec'),
                ),
                // ioTec client ID
                'iotec_client_id' => array(
                    'title' => __('Client ID', 'iotec'),
                    'type' => 'text',
                    'desc_tip' => __('Enter the Client ID provided to you by ioTec.', 'iotec'),
                ),
                // iotec client secret
                'iotec_client_secret' => array(
                    'title' => __('Client Secret', 'iotec'),
                    'type' => 'text',
                    'desc_tip' => __('Enter the Client Secret provided to you by ioTec.', 'iotec'),
                ),
                // Test environment enabled or disabled. This affects whether ssl verification is enabled or disabled
                'environment' => array(
                    'title' => __('Test Mode', 'iotec'),
                    'label' => __('Enable Test Mode', 'iotec'),
                    'type' => 'checkbox',
                    'description' => __('Place the payment gateway in test mode.', 'iotec'),
                    'default' => 'no',
                )
            );

            // Initialize form fields
            $this->init_form_fields();
            // Initialize plugin settings
            $this->init_settings();

            // Turn these settings into variables we can use
            foreach ($this->settings as $setting_key => $value) {
                $this->$setting_key = $value;
            }

            // Set the title for the payment option at checkout to be saved in the database
            $this->title = 'ioTecPay';

            // Check for SSL on checkout page and alert user if it's not a test environment
            if ($this->environment == "no")
                add_action('admin_notices', array($this, 'do_ssl_check'));

            // Save settings if the the current user is ADMIN
            if (is_admin()) {

                // Versions over 2.0
                // Save our administration options. Since we are not going to be doing anything special
                // we have not defined 'process_admin_options' in this class so the method in the parent
                // class will be used instead
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            }
        }

        // Validate payment form fields
        public function validate_fields()
        {
            $phone_no = sanitize_text_field(wp_unslash($_POST['iotec_mm_no']));

            // Make sure the mobile number has been entered
            if (empty(esc_attr($phone_no))) {
                // Alert the user the mobile money number hasn't been entered
                wc_add_notice('Mobile money phone number is required!', 'error');
                return false;
            }
            // TO DO
            // Better phone number validation
            if (preg_match('^(0)[0-9]{9}$', esc_attr($phone_no)) && strlen(esc_attr($phone_no)) == 10) {
                return true;
            }
            return true;
        }

        // Contains the ioTec moile number input form at checkout
        function payment_fields()
        {
            // Display some description before the payment form
            $this->description = 'Please enter an Airtel or MTN mobile money number. You will receive a prompt to enter your PIN to process the payment.';
            $this->description = trim($this->description);
            // display the description with <p> tags etc.
            echo wpautop(wp_kses_post($this->description));

            // Add this action hook if you want your custom gateway to support it
            do_action('woocommerce_iotec_form_start', $this->id);

            // Mobile money number input form
            // Use inique IDs, because other gateways could already use them
            echo '<br/>
            <div class="form-row form-row-wide">
            <label>Mobile money phone number <span class="required">*</span></label>
		<input id="iotec_mm_no" type="text" required name="iotec_mm_no">
		</div>
		<div class="clear"></div>';

            do_action('woocommerce_iotec_form_end', $this->id);

            echo '<div class="clear"></div></fieldset>';
        }

        // Check for SSL at checkout and alert user
        public function do_ssl_check()
        {
            if ($this->enabled == "yes") {
                if (get_option('woocommerce_force_ssl_checkout') == "no") {
                    echo "<div class=\"error\"><p>" . sprintf(__("<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>"), $this->method_title, admin_url('admin.php?options-general.php')) . "</p></div>";
                }
            }
        }

        // Process a payment
        public function process_payment($order_id)
        {
            global $woocommerce;
            global $test_env;

            // Get this Order's information so that we know
            // who to charge and how much
            $customer_order = new WC_Order($order_id);

            // Get the Order number
            $customer_order_no = $customer_order->get_order_number();

            // Are we testing right now or is it a real transaction
            $test_environment = ($this->environment == "yes") ? 'TRUE' : 'FALSE';
            // Update the global test_env variable
            $test_env = $test_environment;

            // ioTec Pay API url for collections
            $environment_url = 'https://pay.iotec.io/api/collections/collect';

            // Escape client id and secret
            $iotec_client_id = sanitize_text_field(wp_unslash($this->iotec_client_id));
            $iotec_client_secret = sanitize_text_field(wp_unslash($this->iotec_client_secret));

            // Get ioTec API token
            $token_payload = array(
                'grant_type' => 'client_credentials',
                'client_id' => esc_attr($iotec_client_id),
                'client_secret' => esc_attr($iotec_client_secret)
            );

            $token_url = 'https://id.iotec.io/connect/token';
            $token_response = wp_remote_post(
                esc_url($token_url),
                array(
                    'body' => http_build_query($token_payload),
                    'timeout' => 90,
                    'sslverify' => ("TRUE" == $test_environment)
                    ? false : true,
                    'headers' => array(
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'Accept' => 'application/x-www-form-urlencoded',
                    ),
                )
            );
            $token_body = wp_remote_retrieve_body($token_response);
            $json_token_body = json_decode($token_body);
            $token = $json_token_body->access_token;

            // Wallet ID
            $iotec_wallet = sanitize_text_field(wp_unslash($this->iotec_wallet));

            // Mobile money number
            $mobile_money_number = sanitize_text_field(wp_unslash($_POST['iotec_mm_no']));

            // Current website name
            $site_name = get_bloginfo('name');

            // Remove special characters from website name
            $site_name = preg_replace('/[^a-zA-Z ]/', '', $site_name);

            $tx_payload = array(
                "category" => "MobileMoney",
                "currency" => "UGX",
                "walletId" => esc_attr($iotec_wallet),
                "externalId" => "",
                "payer" => esc_attr($mobile_money_number),
                "amount" => $customer_order->order_total,
                "payerNote" => "order number " . $customer_order_no . " for " . $site_name,
                "payeeNote" => "order number " . $customer_order_no . " for " . $site_name,
                "channel" => "WOOCOMMERCE"
            );

            // Encode the PHP array as a JSON object (important! API call fails without this)
            $tx_payload = wp_json_encode($tx_payload);

            // Send this payload to ioTec for processing
            $tx_response = wp_remote_post(
                esc_url($environment_url),
                array(
                    'body' => $tx_payload,
                    'timeout' => 90,
                    'sslverify' => ("TRUE" == $test_environment)
                    ? false : true,
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ),
                )
            );

            // Alert the user if there is trouble connecting to ioTec API
            if (is_wp_error($tx_response))
                throw new Exception(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'iotec'));

            // Alert user if there is no response from API
            if (empty($tx_response['body']))
                throw new Exception(__('No response from payment gateway.', 'iotec'));

            // Retrieve the response body if no errors found
            $tx_response_body = wp_remote_retrieve_body($tx_response);
            $json_tx_response_body = json_decode($tx_response_body);

            // Get payment status. User has to input PIN for the transaction to go through
            $payment_status = check_iotec_payment_status($json_tx_response_body->id, $token);

            // Keep checking payment status until it changes
            while ($payment_status["status"] == "Pending" || $payment_status["status"] == "SentToVendor") {

                // Wait 5 seconds before checking status again
                sleep(5);
                $payment_status = check_iotec_payment_status($json_tx_response_body->id, $token);
            }

            $status = $payment_status["status"];
            $status_message = $payment_status["statusMessage"];

            // Check if the transaction went through or not
            if ($status == 'Success') {

                // Payment has been successful. Add a note to the order
                $customer_order->add_order_note(__('Payment completed.', 'iotec'));

                // Mark order as Paid
                $customer_order->payment_complete();
                // Update product stock
                $customer_order->reduce_order_stock();

                // Empty the cart (Very important step)
                $woocommerce->cart->empty_cart();

                // Redirect to thank you page
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($customer_order),
                );

            } else {

                // Transaction was not succesful
                // Add notice to the cart to alert the user the transaction failed
                wc_add_notice(__('Payment error. ', 'iotec') . $status_message, 'error');
                // Add note to the order for your reference
                $customer_order->add_order_note('Payment failed. ' . $status_message);
                return;
            }
        }
    }
}

// Check payment status
function check_iotec_payment_status($transaction_id, $token)
{
    global $test_env;

    $url = 'https://pay.iotec.io/api/collections/status/' . $transaction_id;

    $response = wp_remote_get(
        esc_url($url),
        array(
            'timeout' => 30,
            'sslverify' => ("TRUE" == $test_env)
            ? false : true,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
        )
    )['body'];

    $response_body = json_decode($response, true);
    return $response_body;
}

// Register ioTec payment gateway with WooCommerce
function add_iotec_pay($methods)
{
    $methods[] = 'WC_Gateway_Iotec';
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_iotec_pay');