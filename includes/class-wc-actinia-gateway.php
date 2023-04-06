<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!defined('ACTINIA_WOOCOMMERCE_VERSION')) {
    define('ACTINIA_WOOCOMMERCE_VERSION', '2.6.10');
}

/**
 * Gateway class
 */
class WC_actinia extends WC_Payment_Gateway
{

    const ORDER_APPROVED = 'PAID';
    const ORDER_PROCESSING = 'PENDING';
    const ORDER_EXPIRED = 'EXPIRED';
    const ORDER_CANCELED = 'CANCELED';

    const SIGNATURE_SEPARATOR = '|';
    const ORDER_SEPARATOR = "_";

    public $private_key;
    public $merchant_id;
    public $client_code_name;
    public $client_account_id;
    public $currency;
    public $language;
    public $is_test;

    public $redirect_page_id;
    public $page_mode;
    public $page_mode_instant;
    public $default_order_status;
    public $expired_order_status;
    public $declined_order_status;
    public $actinia_unique;
    public $msg = [];

    /**
     * WC_actinia constructor.
     */
    public function __construct()
    {
        $this->id = 'actinia';
        $this->method_title = 'ACTINIA';
        $this->method_description = __('Payment gateway', 'actinia-woocommerce-payment-gateway');
        $this->has_fields = false;
        $this->init_form_fields();
        $this->init_settings();

        $this->getOptions();

    }

    /**
     * Actinia Logo
     * @return string
     */
    public function get_icon()
    {
        $icon =
            '<img 
                    style="width: 100%;max-width:170px;min-width: 120px;float: right;" 
                    src="'  . ACTINIA_BASE_PATH . 'assets/img/logo.png' . '" 
                    alt="Actinia Logo" />';
        if ($this->get_option('showlogo') == "yes") {
            return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
        } else {
            return false;
        }
    }

    /**
     * Process checkout func
     */
    function generate_ajax_order_actinia_info()
    {
        check_ajax_referer('actinia-submit-nonce', 'nonce_code');
        wc_maybe_define_constant('WOOCOMMERCE_CHECKOUT', true);
        WC()->checkout()->process_checkout();
        wp_die(0);
    }

    /**
     * Custom button order
     * @param $button
     * @return string
     */
    function custom_order_button_html($button)
    {
        $order_button_text = __('Place order', 'actinia-woocommerce-payment-gateway');
        $js_event = "actinia_submit_order(event);";
        $button = '<button type="submit" onClick="' . esc_attr($js_event) . '" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr($order_button_text) . '" data-value="' . esc_attr($order_button_text) . '" >' . esc_attr($order_button_text) . '</button>';

        return $button;
    }

    /**
     * Enqueue checkout page scripts
     */
    public function checkoutScriptsActinia()
    {
        if (is_checkout()) {
            wp_enqueue_style('actinia-checkout', ACTINIA_BASE_PATH . 'assets/css/actinia_styles.css');
//            if (isset($this->on_checkout_page) and $this->on_checkout_page == 'yes') {
                wp_enqueue_script('actinia_pay_v2', 'https://unpkg.com/ipsp-js-sdk@latest/dist/checkout.min.js', ['jquery'], null, true);
                wp_enqueue_script('actinia_pay_v2_woocom', ACTINIA_BASE_PATH . 'assets/js/actinia.js', ['actinia_pay_v2'], '2.4.9', true);
                wp_enqueue_script('actinia_pay_v2_card', ACTINIA_BASE_PATH . 'assets/js/payform.min.js', ['actinia_pay_v2_woocom'], '2.4.9', true);
                if (isset($this->force_lang) and $this->force_lang == 'yes') {
                    $endpoint = new WC_AJAX();
                    $endpoint = $endpoint::get_endpoint('checkout');
                } else {
                    $endpoint = admin_url('admin-ajax.php');
                }
                wp_localize_script('actinia_pay_v2_woocom', 'actinia_info', [
                        'url' => $endpoint,
                        'nonce' => wp_create_nonce('actinia-submit-nonce')
                    ]
                );
//            } else {
//                wp_enqueue_script('actinia_pay', '//api.actinia.eu/static_common/v1/checkout/ipsp.js', [], null, false);
//            }
        }
    }

    /**
     *
     */
    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'actinia-woocommerce-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Actinia Payment Module.', 'actinia-woocommerce-payment-gateway'),
                'default' => 'no',
                'description' => __('Show in the Payment List as a payment option', 'actinia-woocommerce-payment-gateway')
            ],
            'is_test' => [
                'title' => __('Test mode', 'actinia-woocommerce-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable test mode', 'actinia-woocommerce-payment-gateway'),
                'default' => 'no',
                'description' => __('Enable only for unit testing', 'actinia-woocommerce-payment-gateway')
            ],
            'title' => [
                'title' => __('Title:', 'actinia-woocommerce-payment-gateway'),
                'type' => 'text',
                'default' => __('Actinia Online Payments', 'actinia-woocommerce-payment-gateway'),
                'description' => __('This controls the title which the user sees during checkout.', 'actinia-woocommerce-payment-gateway'),
                'desc_tip' => true
            ],
            'description' => [
                'title' => __('Description:', 'actinia-woocommerce-payment-gateway'),
                'type' => 'textarea',
                'default' => __('Pay securely by Credit or Debit Card or Internet Banking through actinia service.', 'actinia-woocommerce-payment-gateway'),
                'description' => __('This controls the description which the user sees during checkout.', 'actinia-woocommerce-payment-gateway'),
                'desc_tip' => true
            ],
            'private_key' => [
                'title' => __('PrivateKey:', 'actinia-woocommerce-payment-gateway'),
                'type' => 'textarea',
                'default' => '',
                'rows' => 10,
                'description' => __('private_key.', 'actinia-woocommerce-payment-gateway'),
                'desc_tip' => true
            ],
            'merchant_id' => [
                'title' => __('Merchant ID', 'actinia-woocommerce-payment-gateway'),
                'type' => 'text',
                'description' => __('Given to Merchant by actinia'),
                'desc_tip' => true
            ],
            'client_code_name' => [
                'title' => __('ClientCodeName', 'actinia-woocommerce-payment-gateway'),
                'type' => 'text',
                'description' => __('client_code_name', 'actinia-woocommerce-payment-gateway'),
                'desc_tip' => true
            ],
            'client_account_id' => [
                'title' => __('ClientAccountId', 'actinia-woocommerce-payment-gateway'),
                'type' => 'text',
                'description' => __('client_account_id', 'actinia-woocommerce-payment-gateway'),
                'desc_tip' => true
            ],
            'showlogo' => [
                'title' => __('Show logo', 'actinia-woocommerce-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Show the logo in the payment method section for the user', 'actinia-woocommerce-payment-gateway'),
                'default' => 'yes',
                'description' => __('Tick to show "actinia" logo', 'actinia-woocommerce-payment-gateway'),
                'desc_tip' => true
            ],
            'redirect_page_id' => [
                'title' => __('Return Page', 'actinia-woocommerce-payment-gateway'),
                'type' => 'select',
                'options' => $this->getPagesActinia(__('Default order page', 'actinia-woocommerce-payment-gateway')),
                'description' => __('URL of success page', 'actinia-woocommerce-payment-gateway'),
                'desc_tip' => true
            ],
            'default_order_status' => [
                'title' => __('Payment completed order status', 'actinia-woocommerce-payment-gateway'),
                'type' => 'select',
                'options' => $this->getPaymentOrderStatuses(),
                'default' => 'none',
                'description' => __('The default order status after successful payment.', 'actinia-woocommerce-payment-gateway')
            ],
            'expired_order_status' => [
                'title' => __('Payment expired order status', 'actinia-woocommerce-payment-gateway'),
                'type' => 'select',
                'options' => $this->getPaymentOrderStatuses(),
                'default' => 'none',
                'description' => __('Order status when payment was expired.', 'actinia-woocommerce-payment-gateway')
            ],
            'declined_order_status' => [
                'title' => __('Payment declined order status', 'actinia-woocommerce-payment-gateway'),
                'type' => 'select',
                'options' => $this->getPaymentOrderStatuses(),
                'default' => 'none',
                'description' => __('Order status when payment was declined.', 'actinia-woocommerce-payment-gateway')
            ],
        ];
    }

    /**
     * Getting all available woocommerce order statuses
     */
    protected function getPaymentOrderStatuses()
    {
        $order_statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [];
        $statuses = [
            'default' => __('Default status', 'actinia-woocommerce-payment-gateway')
        ];
        if ($order_statuses) {
            foreach ($order_statuses as $k => $v) {
                $statuses[str_replace('wc-', '', $k)] = $v;
            }
        }
        return $statuses;
    }

    /**
     * Admin Panel Options
     **/
    public function admin_options()
    {
        echo '<h3>' . __('Actinia', 'actinia-woocommerce-payment-gateway') . '</h3>';
        echo '<p>' . __('Payment gateway', 'actinia-woocommerce-payment-gateway') . '</p>';
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

    /**
     * @param $order_id
     * @return string
     */
    protected function getUniqueId($order_id)
    {
        return $order_id . self::ORDER_SEPARATOR . $this->actinia_unique;
    }

    /**
     * @param $order_id
     * @return string
     */
    private function getProductInfo($order_id)
    {
        return __('Order: ', 'actinia-woocommerce-payment-gateway') . $order_id;
    }

    /**
     * @return string
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * @param int $order_id
     * @param false $must_be_logged_in
     * @return array
     */
    function process_payment($order_id, $must_be_logged_in = false)
    {
        global $woocommerce;
        if ($must_be_logged_in && get_current_user_id() === 0) {
            wc_add_notice(__('You must be logged in.', 'actinia-woocommerce-payment-gateway'), 'error');
            return [
                'result' => 'fail',
                'redirect' => $woocommerce->cart->get_checkout_url()
            ];
        }

        $order = new WC_Order($order_id);

        if (version_compare(WOOCOMMERCE_VERSION, '2.1.0', '>=')) {
            /* 2.1.0 */
            $checkout_payment_url = $order->get_checkout_payment_url(true);
        } else {
            /* 2.0.0 */
            $checkout_payment_url = get_permalink(get_option('woocommerce_pay_page_id'));
        }

        if (!$this->is_subscription($order_id)) {
            $redirect = add_query_arg('order_pay', $order_id, $checkout_payment_url);
        } else {
            $redirect = add_query_arg([
                'order_pay' => $order_id,
                'is_subscription' => true
            ], $checkout_payment_url);
        }

//        if ($this->on_checkout_page == 'yes') {


        // ------------------------------------------------------------
        $order_data = $order->get_data();
        $amount = round($order->get_total() * 100);
        $actiniaApi = new WC_Actinia_Api($this->is_test);

        $externalID = $this->getUniqueId($order_id);
        $paymentData = [
            'merchantId' => esc_attr($this->merchant_id),

            'clientName' => esc_attr(($order_data['billing']['first_name'] ?? '') . ' ' . ($order_data['billing']['last_name'] ?? '')),
            'clientEmail' => esc_attr($this->getEmail($order)),
            'description' => $this->getProductInfo($order_id),
            'amount' => str_replace(',', '.', (string)round($order->get_total(), 2)),
            'currency' => esc_attr(get_woocommerce_currency()),
//            'clientAccountId'   => esc_attr($this->client_account_id),
            'returnUrl' => $this->getCallbackUrl() . '&order_id=' . $externalID,
            'externalId' => $externalID,
            'locale' => strtoupper(esc_attr($this->getLanguage())),
            'expiresInMinutes' => "45",
//            'expireType'        => "minutes",
//            'time'              => "45",
            'feeCalculationType' => "INNER",
            'withQR' => "YES",
            'cb' => [
                'serviceName' => 'InvoiceService',
                'serviceAction' => 'invoiceGet',
                'serviceParams' => [
                    'callbackUrl' => $this->getCallbackUrl() . '&is_callback=true',
                ]
            ]
        ];

        if(!empty($order_data['billing']['phone']))
            $paymentData['clientPhone'] = esc_attr($actiniaApi->preparePhone($order_data['billing']['phone']));

        try {
            $resData = $actiniaApi
                ->setClientCodeName($this->client_code_name)
                ->setPrivateKey($this->private_key)
                ->chkPublicKey()
                ->invoiceCreate($paymentData)
                ->isSuccessException()
                ->getData();

        } catch (Exception $e) {
            return ['result' => 'failure', 'messages' => $e->getMessage()];
        }

        // ------------------------------------------------------------

        if ($this->checkPreOrders($order_id)) {
            $actinia_args['preauth'] = 'Y';
        }

        if ($resData) {
            return [
                'result' => 'success',
                'redirect' => $resData['link']
            ];
        } else {
            wp_send_json([
                'result' => 'fail',
                'redirect' => $woocommerce->cart->get_checkout_url()
            ]);
        }

    }

    /**
     * @return string
     */
    private function getCallbackUrl()
    {
        if (isset($this->force_lang) and $this->force_lang == 'yes') {
            $site_url = get_home_url();
        } else {
            $site_url = get_site_url() . "/";
        }

        $redirect_url = ($this->redirect_page_id == "" || $this->redirect_page_id == 0) ? $site_url : get_permalink($this->redirect_page_id);

        //For wooCoomerce 2.0
        return add_query_arg('wc-api', get_class($this), $redirect_url);
    }

    /**
     * Site lang cropped
     * @return string
     */
    private function getLanguage()
    {
        return substr(get_bloginfo('language'), 0, 2);
    }

    /**
     * Order Email
     * @param $order
     * @return string
     */
    private function getEmail($order)
    {
        $current_user = wp_get_current_user();
        $email = $current_user->user_email;

        if (empty($email)) {
            $order_data = $order->get_data();
            $email = $order_data['billing']['email'];
        }

        return $email;
    }

    /**
     * @param $orderId
     * @param $total
     * @param $cur
     */
    function clear_actinia_cache($orderId, $total, $cur)
    {
        WC()->session->__unset('session_token_' . $this->merchant_id . '_' . $orderId);
        WC()->session->__unset('session_token_' . md5($this->merchant_id . '_' . $orderId . '_' . $total . '_' . $cur));
    }

    /**
     * Response Handler
     */
    function check_actinia_response()
    {
        try {
            if (isset($_REQUEST['is_callback'])) {
                $callback = json_decode(file_get_contents("php://input"), true);
                $this->callback_process($callback);

            } else {
                list($orderId,) = explode(self::ORDER_SEPARATOR, $_GET['order_id']);
                $order = new WC_Order($orderId);
                if ($order && !$order->is_paid()) {
                    $this->msg['message'] = __("Thank you for shopping with us. Your account has been charged and your transaction is successful.", 'actinia-woocommerce-payment-gateway');
                    $this->msg['class'] = 'woocommerce-message';

                } elseif (!$order->is_paid()) {
                    $this->msg['class'] = 'error';
                    $this->msg['message'] = 'error';
                    $order->add_order_note("ERROR: " . 'error');
                }
            }

            if ($this->redirect_page_id == "" || $this->redirect_page_id == 0) {
                $redirect_url = $order->get_checkout_order_received_url();
            } else {
                $redirect_url = get_permalink($this->redirect_page_id);
                if ($this->msg['class'] == 'woocommerce-error' or $this->msg['class'] == 'error') {
                    wc_add_notice($this->msg['message'], 'error');
                } else {
                    wc_add_notice($this->msg['message']);
                }
            }
            wp_redirect($redirect_url);
            exit;

        } catch (Exception $e){
            wp_die($e->getMessage());
        }
    }

    /**
     * @param $data
     */
    protected function callback_process($data){
        try{
            $actiniaApi = new WC_Actinia_Api($this->is_test);
            $data = $actiniaApi->decodeJsonObjToArr($data, true);

            $payment = $actiniaApi
                ->setClientCodeName($this->client_code_name)
                ->setPrivateKey($this->private_key)
                ->chkPublicKey()
                ->isPaymentValid($data);

//             if($payment['merchantId'] !== $this->merchant_id){
//                 throw new Exception('not valid merchantId (|' .$payment['merchantId']. ' | '. $this->merchant_id .' |)');
//             }

            list($orderId,) = explode(self::ORDER_SEPARATOR, $payment['externalId']);
            $order = new WC_Order($orderId);

            if(!$order)
                throw new Exception('Order not found: ' . $orderId);

            $order->add_order_note(__('Actinia status: ' . esc_sql($payment['status']) . ', Payment id: ' . esc_sql($payment['invoiceNumber']), 'actinia-woocommerce-payment-gateway'));

            switch($payment['status']){
                case self::ORDER_APPROVED:
                    $order->update_status($this->default_order_status);
                    break;
                case self::ORDER_EXPIRED:
                    $order->update_status($this->expired_order_status);
                    break;
                case self::ORDER_CANCELED:
                    $order->update_status($this->declined_order_status);
                    break;
            }

            die('Order Reversed');


        } catch (Exception $e){
            wp_die($e->getMessage());
        }
    }

    /**
     * @param $order_id
     * @return bool
     * Checking if subsciption order
     */
    protected function is_subscription($order_id)
    {
        return (function_exists('wcs_order_contains_subscription') && (wcs_order_contains_subscription($order_id) || wcs_is_subscription($order_id) || wcs_order_contains_renewal($order_id)));
    }

    /**
     * @param bool $title
     * @param bool $indent
     * @return array
     */
    protected function getPagesActinia($title = false, $indent = true)
    {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = [];
        if ($title) {
            $page_list[] = $title;
        }
        foreach ($wp_pages as $page) {
            $prefix = '';
            if ($indent) {
                $has_parent = $page->post_parent;
                while ($has_parent) {
                    $prefix .= ' - ';
                    $next_page = get_post($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }
            $page_list[$page->ID] = $prefix . $page->post_title;
        }

        return $page_list;
    }

    /**
     * Send capture request
     * @param $args
     * @return array
     * */
    protected function get_capture($args)
    {
        $conf = [
            'redirection' => 2,
            'user-agent' => 'CMS Woocommerce',
            'headers' => ["Content-type" => "application/json;charset=UTF-8"],
            'body' => json_encode(['request' => $args])
        ];
        $response = wp_remote_post('https://api.actinia.eu/api/capture/order_id', $conf);
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code != 200) {
            $error = "Return code is {$response_code}";
            return ['result' => 'failture', 'messages' => $error];
        }
        $result = json_decode($response['body'], true);
        return ['data' => $result['response']];
    }

    /**
     * Check pre order class and order status
     * @param $order_id
     * @param bool $withoutToken
     * @return boolean
     */
    public function checkPreOrders($order_id, $withoutToken = false)
    {
        if (class_exists('WC_Pre_Orders_Order')
            && WC_Pre_Orders_Order::order_contains_pre_order($order_id)) {
            if ($withoutToken) {
                return true;
            } else {
                if (WC_Pre_Orders_Order::order_requires_payment_tokenization($order_id)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     *
     */
    protected function getOptions(){

        $this->title = $this->get_option('title');
        $this->is_test = $this->get_option('is_test');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->client_code_name = $this->get_option('client_code_name');
        $this->client_account_id = $this->get_option('client_account_id');
        $this->private_key = $this->get_option('private_key');
        $this->currency = $this->get_option('currency');
        $this->language = $this->get_option('language');

        $this->redirect_page_id = $this->get_option('redirect_page_id');
        $this->description = $this->get_option('description');
        $this->page_mode = $this->get_option('page_mode');
        $this->page_mode_instant = $this->get_option('page_mode_instant');
//        $this->on_checkout_page = $this->get_option('on_checkout_page') ? $this->get_option('on_checkout_page') : false;
        $this->default_order_status = $this->get_option('default_order_status') ? $this->get_option('default_order_status') : false;
        $this->expired_order_status = $this->get_option('expired_order_status') ? $this->get_option('expired_order_status') : false;
        $this->declined_order_status = $this->get_option('declined_order_status') ? $this->get_option('declined_order_status') : false;
        $this->msg['message'] = "";
        $this->msg['class'] = "";

        $this->page_mode = ($this->get_option('payment_type') == 'page_mode') ? 'yes' : 'no';
//        $this->on_checkout_page = ($this->get_option('payment_type') == 'on_checkout_page') ? 'yes' : 'no';
        $this->page_mode_instant = ($this->get_option('payment_type') == 'page_mode_instant') ? 'yes' : 'no';

        $this->supports = [
            'products',
            'refunds',
            'pre-orders',
            'subscriptions',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_suspension'
        ];



        if (ACTINIA_WOOCOMMERCE_VERSION !== get_option('actinia_woocommerce_version')) {
            update_option('actinia_woocommerce_version', ACTINIA_WOOCOMMERCE_VERSION);
            $settings = maybe_unserialize(get_option('woocommerce_actinia_settings'));
            if (!isset($settings['payment_type'])) {
                if ($settings['page_mode'] == 'yes') {
                    $settings['payment_type'] = 'page_mode';
                } elseif ($settings['on_checkout_page'] == 'yes') {
                    $settings['payment_type'] = 'on_checkout_page';
                } elseif ($settings['page_mode_instant'] == 'yes') {
                    $settings['payment_type'] = 'page_mode_instant';
                } else {
                    $settings['payment_type'] = 'page_mode';
                }
            }
            update_option('woocommerce_actinia_settings', $settings);
        }
        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            /* 2.0.0 */
            add_action('woocommerce_api_' . strtolower(get_class($this)), [
                $this,
                'check_actinia_response'
            ]);
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [
                $this,
                'process_admin_options'
            ]);
        } else {
            /* 1.6.6 */
            add_action('init', [&$this, 'check_actinia_response']);
            add_action('woocommerce_update_options_payment_gateways', [&$this, 'process_admin_options']);
        }
        if (isset($this->on_checkout_page) and $this->on_checkout_page == 'yes') {
            add_filter('woocommerce_order_button_html', [&$this, 'custom_order_button_html']);
        }

        if ($this->actinia_unique = get_option('actinia_unique', true)) {
            add_option('actinia_unique', time());
        }

        add_action('wp_enqueue_scripts', [$this, 'checkoutScriptsActinia']);

    }
}
