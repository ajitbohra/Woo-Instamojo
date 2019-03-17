<?php
/*
Plugin Name: WooCommerce - Instamojo
Plugin URI: http://www.instamojo.com
Description: Instamojo Payment Gateway for WooCommerce. Instamojo lets you collect payments instantly.
Version: 1.0.7
Author: instamojo
Email: support@instamojo.com
Author URI: http://www.instamojo.com/
License: MIT
License URI: https://opensource.org/licenses/MIT
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add settings link on plugin page
function your_plugin_settings_link($links) { 
  $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=instamojo">Settings</a>'; 
  array_unshift($links, $settings_link); 
  return $links; 
}
 
$plugin = plugin_basename(__FILE__); 
add_filter("plugin_action_links_$plugin", 'your_plugin_settings_link' );

function insta_log($message){
    $log = new WC_Logger();
    $log->add( 'instamojo', $message );
    
}

function truncate_secret($secret){
  // function for truncating client_id and client_secret
  return substr($secret, 0, 4) . str_repeat('x', 10);
}

# register our GET variables
function add_query_vars_filter( $vars ){
  $vars[] = "payment_id";
  $vars[] = "id";
  return $vars;
}
add_filter( 'query_vars', 'add_query_vars_filter' );

function instamojo_activation_hook(){
    
    $condition1 = !class_exists( 'WC_Payment_Gateway' );
    $condition2 = !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins')));

    if ($condition1 or $condition2) {
      $message = '<b>Instamojo</b> Plugin requires <a href="https://wordpress.org/plugins/woocommerce/">WooCommerce</a> to be installed and activated first!';
      echo $message;
      @trigger_error(__($message, 'ap'), E_USER_ERROR);
    }
    else if (!function_exists('curl_init')) {
        $message = '<b>Instamojo</b> <a href="https://www.digitalocean.com/community/questions/curl-is-not-installed-in-your-php-installation">Plugin requires <b>cURL</b> to be installed first</a>';
        echo $message;
        @trigger_error(__($message, 'ap'), E_USER_ERROR);
    }

}
register_activation_hook(__FILE__, 'instamojo_activation_hook');

# initialize your Gateway Class
add_action( 'plugins_loaded', 'init_instamojo_payment_gateway' );
function init_instamojo_payment_gateway()
{
    if (!class_exists('WC_Payment_Gateway')){
        return;   
    }
        
    Class WP_Gateway_Instamojo extends WC_Payment_Gateway{

      private $testmode;
      private $client_id;
      private $client_secret;
      
      
      public function __construct()
      {
        $this->id = "instamojo";
        $this->icon = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAEO0lEQVRIS6VVbWhbZRR+3nvT3ib9CFu7WdMPapZlzco2Vt3we+sQwYEWZSvsV203GIpD/ECZ3dqLDETQdpgh6K+haHHiGKJjwlp1iEjZZlsn7bqscZp2TT+Spflovu59PTcmpTe9YcgO3D/ve+77nPOc55zDUMAaGuQSsxk7AX6AMfYUuTXQZ9LcOedpgN3iHBc5Vz6PRiNXfb6+JaOnWP6hzSZbysqUB0VR6KZHWhiDWCiI7HlaVfklAj0RjYZ/ywfSAdjtb1uLiswdjHFZEJj1Lg/nXwcVhXVFo/wrn08O5C6XARwOucJkUl9nTDimj5pDAIdFTMMsEDNkCVVETClCGtrvuhhTlMn7nCdPjo+/t6D5Zm5ra18zWyzlnSYTO0lHGZ5zZmIKNpYE0Fp5EzvKZ2BiKn6P3IfvAnaMRtchyXXuVB8QiPpGOh381ONxJzIAdvvxHZIknqfIq/R5c9RLizhcPYJd1inQ/bKNRKrw8e1t+DO2jvLTl5JqMquq6tMTE+IfdCObXC6coZ+fzye1iKLfbf0bR+uGIAmq7lrhwCe3t+LsghNLatGqclEm/aoaO8waG49vId4vkxSL870qxASeW+vBS7ZRw3p/M+/AZ/4mzKctRvcxRUntIoDuHkEQZCMPi5DEM2u8eLXmKsQ8QVOE+GK2Ef1zLtxRSgwDoFp0M5dLvkT0PGHkwYjdbaWzeLPmMhpKFnU18CfNODW9HT+H6qCQzoyMghgggJ55oqfS0IMOy4mmPVSHZ9feRJ0UhsA4ppNlGLhTj/OkpAL0ZJ4jgCktg/TdulUD2WqZw0ZzkKjimIxbMxJdSJvz+0AXJ/VEjG3eLCfpdLUMjJPOnq6aMIUoClORe/6hsVBbiKJ7OacMPGzTpp7vRZHtvZeHCv1LDfctZSC/IghwGztxmItT2FAdxAPrA6gwJ0hJHOElCb4FKyamKxGOSwXroKo4whyOt2ppgg4bKUl7cM+WSex/ZARryuKQTNoaAJIpEYtLJfhh2IlzQy4EIoaN5k8k8HhmHBJNp4iml/OzuH99MdpbOQ48egWIjAGpoCY+Wjs0yUs34OLYTnzUL+HWlKYTvVH0H8bjOJYddrJTknCW5Nq00q3GZkXni9vR1mqjx0OAmvgPQCBaTOX48dcQ+txD8HqXx39O/6OpVPoFj+fEZFZvu2ngPdlCf365cqLW1Fhx8OBjaGt7KNM1OiPHwcFx9PUNEEBm9GeNz6ZSvPPGjbELwNfKsqCzO2EfrcreHIgOwEAFBgAzioKuQABn5ubkiPaLrmO0fVxaqrbQ4nmXit78fwAowSs03LqCwdgvfv8H0Vw8Bi25X3Q6ndWMiZ12e9Wh9vaH6zMUFcjA7f7pr+vXZ04nEsppr3fCp9Gy0rVQz5N8jxT39h5qam6uP2qzWfcZAYRCscFr16bf6ehwD9N61KSUVyjgX47gsK4dZzDsAAAAAElFTkSuQmCC";
        $this->has_fields = false;
        $this->method_title = "Instamojo";
        $this->method_description = "Online Payment Gateway";
      
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title          = $this->get_option( 'title' );
        $this->description    = $this->get_option( 'description' );
        $this->testmode       = 'yes' === $this->get_option( 'testmode', 'no' );
        $this->client_id      = $this->get_option( 'client_id' );
        $this->client_secret  = $this->get_option( 'client_secret' );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      }
      
      public function init_form_fields()
      {
        $this->form_fields = include("instamojo-settings.php");   
      }
      
      public function process_payment($orderId)
      {
        include_once "lib/Instamojo.php";
        $this->log("Creating Instamojo Order for order id: $orderId");
        $xclient_id      = $this->truncate_secret($this->client_id);
        $xclient_secret  = $this->truncate_secret($this->client_secret);
        $this->log("Client ID: $xclient_id | Client Secret: $xclient_secret | Testmode: $this->testmode ");       
        $order = new WC_Order( $orderId );
        try{
          
          $api = new Instamojo($this->client_id, $this->client_secret, $this->testmode);
          
          $api_data['name'] = substr(trim((html_entity_decode( $order->billing_first_name ." ".$order->billing_last_name, ENT_QUOTES, 'UTF-8'))), 0, 20);
          $api_data['email']      = substr($order->billing_email, 0, 75);
          $api_data['phone']      = substr(html_entity_decode($order->billing_phone, ENT_QUOTES, 'UTF-8'), 0, 20);
          $api_data['amount']     = $this->get_order_total();
          $api_data['currency']     = "INR";
          $api_data['redirect_url']   = get_site_url();
          $api_data['transaction_id'] = time()."-". $orderId;
          $this->log("Data sent for creating order ".print_r($api_data,true));
          
          $response = $api->createOrderPayment($api_data);
          $this->log("Response from server on creating order".print_r($response,true));
          if(isset($response->order))
          {
            $url = $response->payment_options->payment_url;
            WC()->session->set( 'payment_request_id',  $response->order->id); 
            // die( json_encode(array("result"=>"success", "redirect"=>$url)));
            return array(
                        'result' => 'success', 
                        'redirect' => $url
                    );
          }
        
        }catch(CurlException $e){
          $this->log("An error occurred on line " . $e->getLine() . " with message " .  $e->getMessage());
          $this->log("Traceback: " . (string)$e);
          $json = array(
            "result"=>"failure",
            "messages"=>"<ul class=\"woocommerce-error\">\n\t\t\t<li>" . $e->getMessage() . "</li>\n\t</ul>\n",
            "refresh"=>"false",
            "reload"=>"false"
            );
            
          die(json_encode($json));
        }catch(ValidationException $e){
          $this->log("Validation Exception Occured with response ".print_r($e->getResponse(), true));
          $errors_html = "<ul class=\"woocommerce-error\">\n\t\t\t";
          foreach( $e->getErrors() as $error)
          {
            $errors_html .="<li>".$error."</li>";
            
          }
          $errors_html .= "</ul>";
          $json = array(
            "result"=>"failure",
            "messages"=>$errors_html,
            "refresh"=>"false",
            "reload"=>"false"
            );
          die(json_encode($json));
        }
        catch(Exception $e){
          
          $this->log("An error occurred on line " . $e->getLine() . " with message " .  $e->getMessage());
          $this->log("Traceback: " . $e->getTraceAsString());
          $json = array(
            "result"=>"failure",
            "messages"=>"<ul class=\"woocommerce-error\">\n\t\t\t<li>".$e->getMessage()."</li>\n\t</ul>\n",
            "refresh"=>"false",
            "reload"=>"false"
            );
          die(json_encode($json));
          
        }
      }
      
      public static function log( $message ) 
      {
        insta_log($message);
      }
      
      public static function truncate_secret( $secret ) 
      {
        return truncate_secret($secret);
      }

    }

}

# look for redirect from instamojo.
add_action( 'template_redirect', 'init_instamojo_payment_gateway1' );
function init_instamojo_payment_gateway1(){
    if(get_query_var("payment_id") and get_query_var("id")){
        $payment_id = get_query_var("payment_id");
        $payment_request_id = get_query_var("id");
        include_once "payment_confirm.php";
    }
}

# add paymetnt method to payment gateway list
add_filter("woocommerce_payment_gateways","add_instamojo");
function add_instamojo($methods){
    $methods[] = 'WP_Gateway_Instamojo';
    return $methods;
}


?>
