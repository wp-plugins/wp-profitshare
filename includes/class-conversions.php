<?php
/*	Conversions
 *	@ package: wp-profitshare
 *	@ since: 1.0
 *	Clasă pentru generarea tabelului cu ultimele conversii
 */
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WP_List_Table' ) ) require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

class Conversions extends WP_List_Table {
	function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'order_date':
				$strtotime = strtotime( $item['order_date'] );
				return date( 'd', $strtotime ) . ' ' . ps_translate_month( date( 'n', $strtotime ) ) . date( ' Y, H:i', $strtotime );
			case 'items_commision':
				$conversions = explode("|", $item['items_commision']);
				return number_format(array_sum($conversions), 2) . ' ' . config( 'CURRENCY' );
            case 'order_status':
				return ( 'approved' == $item['order_status'] )?'Approved':(( 'pending' == $item['order_status'] )?'Pending':'Canceled');
			case 'advertiser_id':
				global $wpdb;
				$advertiser_name = $wpdb->get_results( "SELECT name FROM " . $wpdb->prefix . "ps_advertisers WHERE advertiser_id='" . $item['advertiser_id'] . "'", OBJECT );
				return $advertiser_name[0]->name;
			default:
				return print_r( $item );
		}
	}

	function column_title( $item ) {
		/**
		 * void()
		 */
		return;
	}

	function column_title_author( $item ) {
		/**
		 * void()
		 */
		return;
	}

	function column_cb( $item ) {
		/**
		 * void()
		 */
		return;
	}

	function get_columns() {
		$columns = array(
			'order_date'		=>	'Date, Hour',
			'items_commision'	=>	'Commission value',
			'order_status'		=>	'Status',
			'advertiser_id'		=>	'Advertiser',
		);
		return $columns;
	}

	function get_sortable_columns() {
		$sortable_columns = array(
			'order_date'		=>	array( 'order_date', true ),
			'items_commision'	=>	array( 'items_commision', true ),
			'order_status'		=>	array( 'order_status', true ),
			'advertiser_id'		=>	array( 'advertiser_id', true ),
		);
		return $sortable_columns;
	}

	function process_bulk_action() {
		/**
		 * void()
		 */
		return;
	}

	function prepare_items() {
		global $wpdb;
		$per_page = 25;
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->process_bulk_action();

		// Se obţin informaţiile din baza de date, pentru listare

		$query = "SELECT * FROM " . $wpdb->prefix . "ps_conversions";
		$data = $wpdb->get_results( $query, ARRAY_A );

		function usort_reorder( $a, $b ) {
			$orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : 'order_date';
			$order = ( ! empty( $_REQUEST['order'] ) ) ? $_REQUEST['order'] : 'desc';
			$result = strcmp( $a[ $orderby ], $b[ $orderby ] );
			return ( 'asc' === $order ) ? $result : -$result;
		}

		usort( $data, 'usort_reorder' );
		$current_page = $this->get_pagenum();
		$total_items = count( $data );
		$data = array_slice( $data, ( ($current_page-1) * $per_page ), $per_page );
		$this->items = $data;
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page )
		) );
	}
}
?>