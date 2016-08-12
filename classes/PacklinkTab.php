<?php
/**
 * Integration Packlink.
 *
 * @package  WC_Integration_Packlink
 * @category Integration
 * @author   WooThemes
 */

if (!defined('ABSPATH')) { 
    exit; 
}


class WC_Settings_Tab_packlink {

    public static function init() {
        add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50 );
        add_action( 'woocommerce_settings_tabs_settings_tab_packlink', __CLASS__ . '::settings_tab' );
        add_action( 'woocommerce_update_options_settings_tab_packlink', __CLASS__ . '::update_settings' );
    }
    

    public static function add_settings_tab( $settings_tabs ) {
        $settings_tabs['settings_tab_packlink'] = __( 'Packlink', 'woocommerce-settings-tab-packlink' );
        return $settings_tabs;
    }

    public static function settings_tab() {
        woocommerce_admin_fields( self::get_settings() );
    }

    public static function update_settings() {

        $settings = self::get_settings();
        woocommerce_update_options( $settings );

        try {
            $api_key = get_option('wc_settings_tab_packlink_api_key'); 
            $analitics_posted = get_option('wc_settings_tab_packlink_analitics_posted'); 
            if ($api_key && !$analitics_posted) {
                global $woocommerce;
                $body = array(
                    'ecommerce' => 'woocommerce',
                    'ecommerce_version' => $woocommerce->version,
                    'event' => 'setup'
                );
                $connector = new PacklinkSDK($api_key,null);
                $error = (array)$connector->postAnalitics($body);
                if(!$error) {
                    // first correct key -> postAnalitics
                    update_option('wc_settings_tab_packlink_analitics_posted',true); 
                }
            }

        } catch (Exception $e) {
            // postAnalitics pb or api key error
        }
    }




    public static function get_settings() {

        $base_links = array(
            'FR'=> 'https://pro.packlink.fr/',
            'ES'=> 'https://pro.packlink.es/',
            'IT'=> 'https://pro.packlink.it/',
            'DE'=> 'https://pro.packlink.de/',
        );

        $country_info = explode(':',get_option('woocommerce_default_country'));
        $country_id = $country_info[0];
        $fallback_country_id = 'ES';

        $base_link = array_key_exists($country_id,$base_links) ? $base_links[$country_id] : $base_links[$fallback_country_id];

        $register_link = $base_link.'woocommerce?utm_source=woocommerce&utm_medium=partnerships&utm_campaign=extensions';
        $generate_link = $base_link.'private/settings/integrations/woocommerce_module';
        $base_url = plugin_dir_url( realpath(__DIR__.'/../packlink.php') );
        $logo = $base_url.'/assets/images/logo.png';

        $desc = '<img src="'.$logo.'" width="200">';
        $desc .= '<p>';
        $desc .= __("Ship your paid orders easily at the best prices with Packlink PRO. No account yet? It only takes few seconds to",'packlink');
        $desc .= ' <a href="'.$register_link.'" target="_blank">'.__("register online",'packlink').'</a>';
        $desc .= __(".",'packlink');
        $desc .= '</p>'; 

        $desc .= '<h3>'.__("Packlink Pro Connection",'packlink').'</h3>'; 
       
        $desc .= '<p>';
        $desc .= __('An API key associated with your Packlink PRO account must be indicated in the field below in order to import your paid orders automatically from Woocommerce.','packlink');
        $desc .= ' <a href="'.$generate_link.'" target="_blank">'.__("Generate API key now.",'packlink').'</a>';
        $desc .= '</p>'; 

        $settings = array(
            'section_title' => array(
                'type'     => 'title',
                'desc'     => $desc,
                'id'       => 'wc_settings_tab_packlink_section_title'
            ),
            'packlink_api_key' => array(
                'id'        => 'wc_settings_tab_packlink_api_key',
                'title'     => __('Packlink API Key', 'packlink'),
                'type'      => 'text',
                'desc'      => '',
                'default'   => ''
            ),
           
            'section_end' => array(
                 'type' => 'sectionend',
                 'id' => 'wc_settings_tab_packlink_section_end'
            )
        );
        return apply_filters( 'wc_settings_tab_packlink_settings', $settings );
    }
}
WC_Settings_Tab_packlink::init();














