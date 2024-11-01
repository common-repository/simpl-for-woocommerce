<?php
/*
 * Plugin Name: Simpl for WooCommerce
 * Plugin URI: https://getsimpl.com
 * Description: Simpl Plugin for WooCommerce
 * Version: 1.0.0
 * Author: Get Simpl Technologies Private Limited
 * Author URI: https://getsimpl.com
*/

if ( ! defined( 'ABSPATH' ) )
{
    exit; // Exit if accessed directly
}

add_action('plugins_loaded', 'woocommerce_getsimpl_init', 0);

function woocommerce_getsimpl_init()
{
    if (!class_exists('WC_Payment_Gateway'))
    {
        return;
    }

    class WC_GetSimpl extends WC_Payment_Gateway
    {

      public $supports = array(
        'products',
        'refunds'
      );
      public function __construct() {
        $this->id = 'getsimpl';
        $this->icon = apply_filters( 'woocommerce_cheque_icon', '' );
        $this->has_fields = false;
        $this->method_title = 'Simpl';
        $this->method_description = 'Simpl - Buy Now, Pay Later';
        $this->init_form_fields();
        $this->init_settings();
        #add_action('init', array(&$this, 'process_getsimpl_payment'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'checkout_form'));
        // add_action('woocommerce_api_'. $this->id, array($this, 'process_getsimpl_payment'));
        $cb = array($this, 'process_admin_options');
        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>='))
        {
                add_action("woocommerce_update_options_payment_gateways_{$this->id}", $cb);
        }
        else
        {
                add_action('woocommerce_update_options_payment_gateways', $cb);
        }
        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];
        $this->enabled = $this->get_option('enabled');
        $this->client_id = $this->get_option('clientId');
        $this->client_secret = $this->get_option('clientSecret');
        $this->sandbox = $this->get_option('sandbox');
        if ($this->sandbox == 'yes') {
          $this->client_secret = $this->get_option('sandboxclientSecret');
          $this->endpoint = 'https://sandbox-api.getsimpl.com';
        } else {
          $this->endpoint = 'https://api.getsimpl.com';
        }
      }

      public function init_form_fields() {
        $this->form_fields = array(
          'enabled' => array(
            'title' => __( 'Enable/Disable', 'woocommerce' ),
            'type' => 'checkbox',
            'label' => __( 'Enable Getsimpl Payment', 'woocommerce' ),
            'default' => 'yes'
          ),
          'clientId' => array(
            'title' =>  'clientId',
            'type' => 'text',
            'description' => 'client id',
            'default' => ''
          ),
          'clientSecret' => array(
            'title' =>  'clientSecret',
            'type' => 'text',
            'description' => 'client Secret',
            'default' => ''
          ),
          'sandboxclientSecret' => array(
            'title' =>  'sandboxclientSecret',
            'type' => 'text',
            'description' => 'sandbox client Secret',
            'default' => ''
          ),
          'title' => array(
            'title' => __( 'Title', 'woocommerce' ),
            'type' => 'text',
            'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
            'default' => __( 'GetSimpl', 'woocommerce' ),
            'desc_tip'      => true,
          ),
          'description' => array(
            'title' => __( 'Customer Message', 'woocommerce' ),
            'type' => 'textarea',
            'default' => ''
          ),
          'sandbox' => array(
            'title' => 'Sandbox',
            'type' => 'checkbox',
            'label' => __( 'Enable Sandbox', 'woocommerce' ),
            'default' => 'no'
          )
        );
      }
      function admin_options() {
        ?>
        <h2>GetSimpl</h2>
        <a href="https://getsimpl.com/merchants"> Merchant Signup</a>
        <table class="form-table">
          <?php $this->generate_settings_html(); ?>
        </table> <?php
      }

      public function checkout_form($order_id)
      {
        $order = new WC_Order($order_id);
        $data = array(
          "email" => $order->email,
          "phone" => $order->get_billing_phone(),
          "total" => $order->get_total()
        );
        if (version_compare(WOOCOMMERCE_VERSION, '2.7.0', '>='))
        {
          $data = array(
            "email" => $order->get_billing_email(),
            "phone" => $order->get_billing_phone(),
            "total" => $order->get_total()
          );
        }
        else
        {
          $data = array(
            "email" => $order->billing_email,
            "phone" => $order->get_billing_phone,
            "total" => $order->get_total()
          );
        }
        $data['client_id'] = $this->client_id;
        if ($this->sandbox == 'yes') {
          $data['sandbox'] = true;
        } else {
          $data['sandbox'] = false;
        }

        $data['redirect'] = $this->get_redirect_url($order_id);
        $this->generate_payment_form($data);
      }

      public function generate_payment_form($data) {
        echo '<script id="getsimpl"';
        if ($data['sandbox'] == true) {
          echo 'data-env="sandbox"';
        } else {
          echo 'data-env="production"';
        }
        echo 'data-merchant-id="'.$data['client_id'].'"';
        echo <<<EOT
        src="https://cdn.getsimpl.com/simpl-custom-v1.min.js">
        </script>
        <h1>Don't click stop button</h1>
        <h1 id="simpl-msg"></h1>
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
          window.Simpl && window.Simpl.setTransactionAmount(
EOT;
echo          "window.Simpl.convertRupeeToPaise(".$data['total'].")";
echo <<<EOT
          );

          window.Simpl && window.Simpl.setApprovalConfig({
EOT;

          echo  'email: "'.$data['email'].'",';
          echo  'phone_number: "'.$data['phone'].'"';
          echo <<<EOT
          });
          window.Simpl && window.Simpl.on('approval', function yep() {
            // show the Simpl payment option
            document.getElementById('simpl-button').style.display = 'block';
          }, function nope() {
            // hide the Simpl payment option
            document.getElementById('simpl-button').style.display = 'none';
            document.getElementById("simpl-msg").inneHTML = "You don't have GetSimpl account";
          });
          //document.getElementById('simpl-button').addEventListener('click',function() {
          window.Simpl && window.Simpl.setTransactionAmount(
EOT;
      echo     "window.Simpl.convertRupeeToPaise(".$data['total'].")";
echo <<<EOT
          );
          window.Simpl && window.Simpl.authorizeTransaction();
          window.Simpl && window.Simpl.on('success', function(response) {
            // response from Simpl will include the following keys:
            // { status: 'success', transaction_token: 'xyz' }
            placeOrder(response);
          });
          window.Simpl && window.Simpl.on('error', function(response) {
            // response from Simpl will include the following keys:
            // { status: 'error', message: 'Something went wrong.' }
            document.getElementById("simpl-msg").inneHTML = "Something went wrong";
          });
        //}, false);

        function placeOrder(response) {
          console.log(response);
EOT;
          echo 'window.location.href = "'.$data['redirect'].'"+response.transaction_token';
          echo <<<EOT
        }
}, false);
        </script>
EOT;
      }
      public function process_payment($order_id) {
        $order = new WC_Order($order_id);
        if($_POST['payment_form'] == "yes") {
          return array(
            'result' => 'success',
            'redirect' =>  $this->get_redirect_url($order_id)
          );
        } else {
          return array(
          'result' => 'success',
          'redirect' => $order->get_checkout_payment_url(true)
          );
        }
      }
      private function get_redirect_url($order_id) {
        return home_url() . '?wc-api=' . $this->id . '&order_id=' . $order_id . '&transaction_token=';
      }



      public function process_refund($orderId, $amount = NULL, $reason = '') {
        $order = new WC_Order($orderId);
        if (! $order or ! $order->get_transaction_id())
        {
          return new WP_Error('error', __('Refund failed: No transaction ID', 'woocommerce'));
        }
        $data = array();
        $data['amount_in_paise'] = convert_ruppes_to_paise($amount);
        $data['reason'] = $reason;
        $data['transaction_id'] = $order->get_transaction_id();
        $args = array(
          'body' => json_encode($data),
          'headers' => array(
            'Authorization'=> $this->client_secret,
            'Content-Type'=> 'application/json'
          )
        );
        $response = wp_remote_post( esc_url_raw($this->endpoint."/api/v1.1/transactions/refund"), $args );

        $result = wp_remote_retrieve_body($response);
        $encode_result = json_decode($result);
        if ($encode_result->success == true) {
          $order->add_order_note(__( 'Refund Id: ' . $encode_result->data->refunded_transaction_id, 'woocommerce' ));
          return true;
        } else if($encode_result->success == false) {
          return new WP_Error('error', __($result, 'woocommerce'));
        }
      }

    }
    function process_getsimpl_payment() {
            $simpl = new WC_GetSimpl();
            $transaction_token  = $_GET['transaction_token'];
            $order_id = $_GET['order_id'];
            $order = new WC_Order($order_id);
            $req_body = array();
            $req_body['transaction_token'] = $transaction_token;
            $req_body['amount_in_paise'] = convert_ruppes_to_paise($order->get_total());
            $req_body['order_id'] = $order_id;
            $items = $order->get_items();
            $req_body['items'] = array();
            foreach ($items as $item) {
              $temp_item = array();
              $temp_item['display_name'] = $item->get_name();
              $temp_item['quantity'] = $item->get_quantity();
              $temp_item['unit_price_in_paise'] = convert_ruppes_to_paise($item->get_total());
              array_push($req_body['items'], $temp_item);
            }
            $billing_address['line1'] = $order->get_billing_address_1();
            $billing_address['line2'] = $order->get_billing_address_2();
            $billing_address['city'] = $order->get_billing_city();
            $billing_address['state'] = $order->get_billing_state();
            $billing_address['pincode'] = $order->get_billing_postcode();
            $req_body['billing_address'] = $billing_address;
            $shipping_address['line1'] = $order->get_shipping_address_1();
            $shipping_address['line2'] = $order->get_shipping_address_2();
            $shipping_address['city'] = $order->get_shipping_city();
            $shipping_address['state'] = $order->get_shipping_state();
            $shipping_address['pincode'] = $order->get_shipping_postcode();
            $req_body['shipping_address'] = $shipping_address;
            $req_body['shipping_amount_in_paise'] = convert_ruppes_to_paise($order->get_shipping_total());
            $args = array(
              'body' => json_encode($req_body),
              'headers' => array(
                'Authorization'=> $simpl->client_secret,
                'Content-Type'=> 'application/json'
              )
            );
            $response = wp_remote_post( esc_url_raw($simpl->endpoint."/api/v1.1/transactions"), $args );

            $result = wp_remote_retrieve_body( $response );
             $encode_result = json_decode($result);
            if ($encode_result->success == true) {
              $order->payment_complete($encode_result->data->transaction->transaction_id);
              wp_redirect($order->get_checkout_order_received_url().'&order_id='.$order_id);
            } else if($encode_result->success == false) {
              $order->update_status('failed');
              wp_redirect($order->get_checkout_payment_url());
            }
        }

    function woocommerce_add_getsimpl_gateway($methods)
    {
        $methods[] = 'WC_GetSimpl';
        return $methods;
    }
    function convert_ruppes_to_paise($ruppes) {
      $splited_ruppee = explode(".", $ruppes);
      if (count($splited_ruppee) == 2) {
        return ((intval($splited_ruppee[0]) * 100) + intval($splited_ruppee[1]));
      }
      return (intval($splited_ruppee[0]) * 100);
    }

    function getsimpl_account_checker() {
      $simpl = new WC_GetSimpl();
      if ($simpl->enabled == "yes") {
        echo '<script id="getsimpl"';
        if ($simpl->sandbox == 'yes') {
          echo ' data-env="sandbox"';
        } else {
          echo 'data-env="production"';
        }
        echo 'data-merchant-id="'.$simpl->client_id.'"';
        echo <<<EOT
        src="https://cdn.getsimpl.com/simpl-custom-v1.min.js">
        </script>
EOT;
        echo <<<EOT

        <script>
        function hideOriginalPlaceOrder () {
          if(userApproved && jQuery("input[name=payment_method]:checked").val() == 'getsimpl'){
            jQuery("#place_order").hide()
            if(jQuery('#simpl-pay').length == 0) {
              jQuery("#payment > div").append(`<button id='simpl-pay' class='button alt'>Buy Now, Pay Later</button>`)
            } else {
              jQuery("#simpl-pay").show()
            }
            jQuery("#simpl-pay").click(onClickSimplPay)
          } else {
            jQuery("#simpl-pay").hide()
            jQuery("#place_order").show()
          }
        }
        function onClickSimplPay(event) {
          event.preventDefault()
          jQuery.ajaxSetup({
            crossDomain: true,
            xhrFields: {
              withCredentials: true
            }
          });
          let formData = jQuery("form").serialize() + '&payment_form=yes'
          jQuery.post(window.location.origin+'/?wc-ajax=checkout', formData, (response) => {
            console.log(response)
            if(response.result == "failure") {
              if(jQuery(".woocommerce-NoticeGroup-checkout").length == 0) {
                console.log("prepending")
                jQuery("form.checkout.woocommerce-checkout").prepend(`<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"></div>`)
              }
              jQuery(".woocommerce-NoticeGroup-checkout").html(response.messages)
            } else {
              window.Simpl && window.Simpl.authorizeTransaction();
              window.Simpl && window.Simpl.on('success', function(res) {
                // response from Simpl will include the following keys:
                // { status: 'success', transaction_token: 'xyz' }
                let location = response.redirect + res.transaction_token
                window.location.href = location
              });
              window.Simpl && window.Simpl.on('error', function(response) {
                // response from Simpl will include the following keys:
                // { status: 'error', message: 'Something went wrong.' }
                console.log(response);
              });
            }
          })
        }
        let userApproved = false
        let checkGetSimpl = () => {
          let approvalData = {
            email: document.getElementById("billing_email").value,
            phone_number: document.getElementById("billing_phone").value
          }
          window.Simpl && window.Simpl.setTransactionAmount(
            window.Simpl.convertRupeeToPaise(jQuery(".order-total > td:nth-child(2) > strong:nth-child(1) > span:nth-child(1)").text().slice(1).split(".")[0].replace(",",""))
          );
          window.Simpl && window.Simpl.setApprovalConfig(approvalData);
          window.Simpl && window.Simpl.on('approval', function yep() {
            // show the Simpl payment option

            userApproved = true
            let elements = document.getElementsByClassName('payment_method_getsimpl')
            document.getElementsByClassName('payment_method_getsimpl')[0].style.display = "block"
            hideOriginalPlaceOrder()
          }, function nope() {
            // hide the Simpl payment option

            userApproved = false
            let elements = document.getElementsByClassName('payment_method_getsimpl')
            document.getElementsByClassName('payment_method_getsimpl')[0].style.display = "none"
            jQuery("input:radio[name=payment_method]:first").attr('checked', true)
            hideOriginalPlaceOrder()
          });
        }
        document.addEventListener('DOMContentLoaded', function() {
          checkGetSimpl()
          jQuery("form[name=checkout]").change(() => {
            hideOriginalPlaceOrder()
          })
          document.getElementById("billing_phone").addEventListener("keyup", checkGetSimpl);
          document.getElementById("billing_phone").addEventListener("change", checkGetSimpl);
          document.getElementById("billing_email").addEventListener("change", checkGetSimpl);
        })
        </script>
EOT;
      }
}
    add_action('woocommerce_after_order_notes', 'getsimpl_account_checker');
    add_action('woocommerce_api_getsimpl', 'process_getsimpl_payment');
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_getsimpl_gateway' );
}
