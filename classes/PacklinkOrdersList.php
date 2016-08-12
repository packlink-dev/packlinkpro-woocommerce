<?php

if (!defined('ABSPATH')) { 
    exit; 
}

if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class PacklinkOrdersList extends WP_List_Table {
  
    function __construct($data) {
        parent::__construct();
        $this->items = $data;
    }

    function get_columns() {
        $columns = array(
            'order_id'                      => 'ID',
            'order_title'                   => 'Order',
            'order_status'                  => 'Status',
            'packlink_draft_reference'      => 'packlink_draft_reference',
            'packlink_zip_code_id_to'       => 'packlink_zip_code_id_to',
            'packlink_postal_zone_id_to'    => 'packlink_postal_zone_id_to',
            'actions'                       => 'Actions',
        );
        return $columns;
    }


    function prepare_items() {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();
        $this->_column_headers = array($columns, $hidden, $sortable);

    }


    function column_default( $item, $column_name ) {

        switch( $column_name ) { 
            case 'order_id':
            case 'order_status':
            case 'packlink_draft_reference':
            case 'packlink_zip_code_id_to':
            case 'packlink_postal_zone_id_to':
                return $item[ $column_name ];
            case 'actions':
                $orderid = $item['order_id'];
                $base_query_string = '?page=packlinkDEBUG&orderid='.$orderid;
                $actions[] = '<a class="button tips view" href="'.$base_query_string.'&action=export" data-tip="View">EXPORT</a>';
                return implode('',$actions);
            default:
                return print_r( $item, true ) ; 
        }
    }


}




