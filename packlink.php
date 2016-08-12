<?php
/*
Plugin Name: Packlink
Plugin URI: https://en.wordpress.org/plugins/packlink-pro-shipping/
Description: Save up to 70% on your shipping costs. No fixed fees, no minimum shipping volume required. Manage all your shipments in a single platform.
Version: 1.0.0
Author: 202 ecommerce
Author URI: http://www.202-ecommerce.com/
Text Domain: packlink
*/

if (!defined('ABSPATH')) { 
    exit; 
}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {


if ( !class_exists( 'PacklinkPlugin' ) ) :

require_once 'api/PacklinkApiCaller.php';
require_once 'api/PacklinkSDK.php';
require_once 'classes/PacklinkTab.php';


register_activation_hook( __FILE__, array('PacklinkPlugin','onActivate' ));
register_deactivation_hook( __FILE__, array('PacklinkPlugin','onDeactivate'));


class PacklinkPlugin {

    private $debug = false; 

    public function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    public function init() {

        // Add payment_complete trigger
        add_action('woocommerce_order_status_processing', array($this, 'hook_woocommerce_order_status_processing'),99);

        // languages
        load_plugin_textdomain( 'packlink', false, dirname(plugin_basename(__FILE__)).'/languages/' );

        // common properties
        $this->setting_packlink_api_key = get_option('wc_settings_tab_packlink_api_key'); 
        $this->setting_dimension_unit = get_option('woocommerce_dimension_unit');
        $this->setting_weight_unit = get_option('woocommerce_weight_unit');

        $this->apiConnector = new PacklinkSDK($this->setting_packlink_api_key, $this);

        if ($this->debug) {
            $this->logger =  new WC_Logger();
            add_action('admin_menu', array($this, 'register_packlink_debug_page'),99);
        }


    }


    public static function onActivate() {
        // postAnalitics require key
    }

    public static function onDeactivate() {
        // postAnalitics require key
    }



    public function log($message) {
        if ($this->debug) {
            $this->logger->add('packlink',$message);
        }
    }




    public function register_packlink_debug_page() {
        add_submenu_page( 'woocommerce', 'packlinkDEBUG', 'packlinkDEBUG', 'manage_options', 'packlinkDEBUG', array($this,'packlinkDEBUG_page_callback' )); 
    }


    public function hook_woocommerce_order_status_processing($order_id) { 
        try {
            $this->exportOrder($order_id);
        } catch (Exception $e) {
            // exportOrder error to log !
        }
    }


    public function packlink_shipping_method_init() {
        if ( ! class_exists( 'PacklinkShippingMethod' ) ) {
            require_once 'classes/PacklinkShippingMethod.php';
        }
    }

    public function add_packlink_shipping_method( $methods ) {
        $methods[] = 'PacklinkShippingMethod';
        return $methods;
    }





    public function packlinkDEBUG_page_callback() {

        require_once 'classes/PacklinkOrdersList.php';

        $order = new WC_Order(39);
        echo dev( $this->getShipmentsDatas($order) );
        //$this->exportOrder(118);
        die('ok');



        $action =  @$_GET['action'];
        $action_orderid = @$_GET['orderid'];
        if ($action && $action_orderid) {
            switch ($action) {
                case 'export':
                    $result = $this->exportOrder($action_orderid); 
                    break;
            }
        }

        $data = array();
        $args = array(
            'post_type'     => 'shop_order', 
            'posts_per_page' => 20 ,
        );

        $loop = new WP_Query( $args );
        while ( $loop->have_posts() ) : $loop->the_post();

            $order_id = get_the_ID();
            $order_title = (string)get_the_title(); 

            $order = new WC_Order($order_id);

            $order_status = $order->post_status;   
            
            $listitem = array(
                'order_id'                      => $order_id,
                'order_title'                   => $order_title,
                'order_status'                  => $order_status,
                'packlink_draft_reference'      => get_post_meta($order->id,'_packlink_draft_reference',true),
                'packlink_zip_code_id_to'       => get_post_meta($order->id,'_packlink_zip_code_id_to',true),
                'packlink_postal_zone_id_to'    => get_post_meta($order->id,'_packlink_postal_zone_id_to',true),
            );
            $data[] = $listitem;
        endwhile;


        // rendering
        echo '<h1>Packlink</h1>';
        if ($action) {
            echo '<div style="border:solid 1px #ccc">';
            echo '<h3>EXECUTE '.$action.' sur order #'.$action_orderid.'</h3>';
            echo @$action_result;
            echo '</div>';

        }
        echo '<div class="wrap">'; 
        $ordersList = new PacklinkOrdersList($data);
        $ordersList->prepare_items(); 
        $ordersList->display(); 
        echo '</div>'; 
        
    }





    private function exportOrder($order_id) {

        global $woocommerce;

        $order = new WC_Order($order_id);
        
        $exportable = ($order->post_status=='wc-processing');
        $already_exported = get_post_meta($order_id,'_packlink_draft_reference',true);

        if ($exportable && !$already_exported) {
           
            //todo : ajout filtre status
            $api_key = $this->setting_packlink_api_key;
           
            // recuperation commande woocommerce
            $order = new WC_Order($order_id);

            // recuperation des $shipments_datas a partir de cette commande
            $shipments_datas = $this->getShipmentsDatas($order);

            // creation du draft
            $pl_reference = $this->apiConnector->createDraft($shipments_datas);

            // mise a jour des info packlink pour cette commande
            $packlink_draft_reference = $pl_reference ? $pl_reference->reference : null;
            $packlink_zip_code_id_to = $shipments_datas['additional_data']['zip_code_id_to'];
            $packlink_postal_zone_id_to = $shipments_datas['additional_data']['postal_zone_id_to'];

            update_post_meta($order->id,'_packlink_draft_reference',$packlink_draft_reference);
            update_post_meta($order->id,'_packlink_zip_code_id_to', $packlink_zip_code_id_to);
            update_post_meta($order->id,'_packlink_postal_zone_id_to',$packlink_postal_zone_id_to);
        }

    }

    private function convertSize($size)
    {
        // packlink unit = cm
        $conversions_rules = array(
            'cm'    =>  1,
            'm'     =>  100,
            'mm'    =>  0.1,
            'in'    =>  2.54,
            'yd'    =>  91.44
        );
        $unit = $this->setting_dimension_unit;
        if (array_key_exists($unit,$conversions_rules)) {
            $ratio = $conversions_rules[$unit];
            $size = $size * $ratio;
            return $size;
        } else {
            return '';
        }

    }

    private function convertWeight($weight)
    {
        // packlink unit = cm
        $conversions_rules = array(
            'kg'    =>  1,
            'g'     =>  0.001,
            'lbs'   =>  0.45,
            'oz'    =>  0.028,

        );
        $unit = $this->setting_weight_unit;
        if (array_key_exists($unit,$conversions_rules)) {
            $ratio = $conversions_rules[$unit];
            $weight = $weight * $ratio;
            return $weight;
        } else {
            return '';
        }

    }

    private function getShipmentsDatas($order) {

        // mapping

        $name = $order->shipping_first_name;
        $surname = $order->shipping_last_name;
        $company = $order->shipping_company;
        $street1 = $order->shipping_address_1;
        $street2 = $order->shipping_address_2;
        $zip_code = $order->shipping_postcode;
        $city = $order->shipping_city;
        $country = $order->shipping_country;
        $state = $order->shipping_state;
        $phone = $order->billing_phone; 
        $email = $order->billing_email; 

        /*
        // contentvalue => order total (with tax but without shipping cost)
        // this method is deprecated (pb voucher,etc..) so we calculate contentvalue (with tax) by adding each items
        $price_total = $order->get_total();
        $price_shipping = $order->order_shipping;
        $price_shipping_tax = $order->order_shipping_tax;
        $contentvalue = $price_total - $price_shipping - $price_shipping_tax; 
        */
        

        // get extra data via sdk : $postal_zone_id_to + $zip_code_id_to

        $datas_client = $this->apiConnector->getCitiesByPostCode($zip_code, $country);
        $postal_zone_id_to = $datas_client[0]->postalZone->id;
        $zip_code_id_to = $datas_client[0]->id;

        if (count($datas_client) > 1) {
            foreach ($datas_client as $key => $value) {
                $city = strtolower($city);
                $arr = array("-", "/", ",", "_");
                $city_formated = str_replace($arr, " ", $city);
                $city_formated_pl = strtolower(str_replace($arr, " ", $value->city->name));
                if ($city_formated_pl == $city_formated) {
                    $postal_zone_id_to = $value->postalZone->id;
                    $zip_code_id_to = $value->id;
                }
            }
        }

        // build Shipments Datas
        
        $shipments_datas = array(
            'to' => array(
                 'name' => $name,
                 'surname' => $surname,
                 'company' => $company,
                 'street1' => $street1,
                 'street2' => $street2,
                 'zip_code' => $zip_code,
                 'city' => $city,
                 'country' => $country,
                 'state' => $state,
                 'phone' => $phone, 
                 'email' => $email,
            ),
            'additional_data' => array(
                 'postal_zone_id_to' => $postal_zone_id_to,
                 'zip_code_id_to' => $zip_code_id_to,
            ),
            'contentvalue' =>  0, 
            'source' => 'module_woocommerce',
            'content' => '',
        );

        // packages
        $force_details = true; // if true details are send even if there is only one product
        $num_items = $order->get_item_count();
        if ($force_details || $num_items>1) {    
            if ($num_items>1) {                    
                $shipments_datas['packages'][] = array(
                 'weight' => 0,
                 'length' => 0,
                 'width' => 0,
                 'height' => 0
                );
            }
            $cmpt = 0;

            foreach ($order->get_items() as $key => $lineItem) {

                $product_id = $lineItem['product_id'];
                $product_variation_id = $lineItem['variation_id'];
                $quantity = $lineItem['qty'];

                $product = new WC_Product($product_id);

                if (!$product_variation_id) {
                    $item_id = $product_id; 
                    $item_url = get_permalink($product_id);
                    $price = $product->get_price_including_tax();  
                    $title = $product->get_title();
                } else {
                    $productVariation = new WC_Product_Variation($product_variation_id);
                    $item_id = $product_variation_id; 
                    $item_url = get_permalink($product_variation_id);
                    $price = $productVariation->get_price_including_tax();  

                    $attributes = $productVariation->get_attributes();
                    $title = $product->get_title();
        
                    // gestion v2.2 -> v2.6
                    if (method_exists($productVariation,'get_formatted_variation_attributes')) {
                        $extratitle = $productVariation->get_formatted_variation_attributes(true);
                    } else {
                        $variation_data = $productVariation->get_variation_attributes(); 
                        $extratitle = woocommerce_get_formatted_variation($variation_data, true);  
                    }
                    
                    $title = $title.' - '.$extratitle;
                    if (strlen($title) > 250)
                       $title = substr($title,0,250).'...';
                }

                $weight = $this->convertWeight($product->weight);
                $width = $this->convertSize($product->width);
                $height = $this->convertSize($product->height);
                $length = $this->convertSize($product->length);

                // category
                $product_categories = wp_get_post_terms( $product->id, 'product_cat' );
                $category_name = $product_categories ? $product_categories[0]->name : '';

                // image
                $id_image = $product->get_image_id();    
                $picture_url = $id_image ? wp_get_attachment_url( $id_image ) : '';

                $items =  array(
                    'quantity' => $quantity,
                    'category_name'  => $category_name,
                    'picture_url' => $picture_url,
                    'item_id' => $item_id,
                    'price' => $price,
                    'item_url' => $item_url,
                    'title' => $title
                );

                $shipments_datas['contentvalue'] += $price*$quantity;

                $shipments_datas['additional_data']['items'][] = $items;
                $shipments_datas['content'] .= $quantity.' '.$title.'; ';

                for ($i = 1; $i <= $quantity; $i++) {
                    $package = array(
                         'weight' => $weight,
                         'length' => $length,
                         'width' => $width,
                         'height' => $height
                    );
                    $shipments_datas['additional_data']['items'][$cmpt]['package'][] = $package;

                    // ajout des info si 1 seul produit
                    if ($num_items==1) {                    
                        $shipments_datas['packages'][] = $package;
                    }
                }
                $cmpt++;
            }
        } else {
        
            foreach ($order->get_items() as $key => $lineItem) {
                $product = new WC_Product($lineItem['product_id']); 
                $package = array(
                     'weight'   => $this->convertWeight($product->weight),
                     'width'    => $this->convertSize($product->width),
                     'height'   => $this->convertSize($product->height),
                     'length'   => $this->convertSize($product->length),
                );
                $shipments_datas['packages'][] = $package;
            }
        }

        return $shipments_datas;
    }



}

$packlink = new packlinkPlugin( __FILE__ );

endif;

}
