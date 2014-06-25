<?php
/*	Functions
 *	@ package: profitshare-for-affiliates
 *	@ since: 1.0.0
 */

/**
 *	@since: 1.0.0
 *	Sunt create câmpuri şi tabele pentru informaţiile primite prin API
 */
function init_profitshare_settings() {
	global $wpdb;
	$current_user = wp_get_current_user();
	add_user_meta( $current_user->ID, 'profitshare_api_user', '', true );
	add_user_meta( $current_user->ID, 'profitshare_api_key', '', true );
	add_user_meta( $current_user->ID, 'profitshare_post_convert', 0, true );
	add_user_meta( $current_user->ID, 'is_curl_profitshare_connected', 0, true );
	add_option( 'last_advertisers_update', '0', '', 'no' );
	add_option( 'last_conversions_update', '0', '', 'no' );
	add_option( 'account_balance', '0', '', 'no' );
	add_option( 'last_check_account_balance', '0', '', 'no' );
	$query = array();
	$query[] = "
	CREATE TABLE " . $wpdb->prefix . "ps_advertisers (
		ID smallint(5) unsigned NOT NULL auto_increment,
		advertiser_id mediumint(5) unsigned NOT NULL,
		name char(250) NOT NULL,
		link char(50) NOT NULL,
		UNIQUE KEY advertiser_id (advertiser_id),
		UNIQUE KEY name (name),
		PRIMARY KEY advertisers_id (ID)
		);";
	$query[] = "
	CREATE TABLE " . $wpdb->prefix . "ps_conversions (
		ID smallint(5) unsigned NOT NULL auto_increment,
		order_date char(20) NOT NULL,
		items_commision char(255) NOT NULL,
		order_status char(8) NOT NULL,
		advertiser_id mediumint(5) unsigned NOT NULL,		
		PRIMARY KEY conversions_id (ID)
		);";
	$query[] = "
	CREATE TABLE " . $wpdb->prefix . "ps_shorted_links (
		ID smallint(5) unsigned NOT NULL auto_increment,
		source char(100) NOT NULL,
		link text NOT NULL,
		shorted char(50) NOT NULL,
		date int(10) NOT NULL,
		PRIMARY KEY shorted_links_id (ID)
		);";
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $query );
}

/**
 *	@since: 1.0.0
 *	Se şterg câmpurile şi tabelele odată cu dezactivarea plugin-ului.
 */
function remove_profitshare_settings() {
	global $wpdb;
	$current_user = wp_get_current_user();
	
	delete_user_meta( $current_user->ID, 'profitshare_api_user' ); 
	delete_user_meta( $current_user->ID, 'profitshare_api_key' );
	delete_user_meta( $current_user->ID, 'is_curl_profitshare_connected' );
	
	delete_option( 'last_advertisers_update' );
	delete_option( 'last_conversions_update' );
	delete_option( 'account_balance' );
	delete_option( 'last_check_account_balance' );
	
	$ps_advertisers = $wpdb->prefix . "ps_advertisers";
	$wpdb->query( "DROP TABLE " . $ps_advertisers );
	
	$ps_conversions = $wpdb->prefix . "ps_conversions";
	$wpdb->query( "DROP TABLE " . $ps_conversions );
	
	$ps_conversions = $wpdb->prefix . "ps_shorted_links";
	$wpdb->query( "DROP TABLE " . $ps_conversions );
}

/**
 *	@since: 1.0.0
 *	Se face conexiunea la api.profitshare.ro prin cURL
 */
function connection_check( $api_user, $api_key ) {
	$current_user = wp_get_current_user();
	update_user_meta( $current_user->ID, 'profitshare_api_user', $api_user );
	update_user_meta( $current_user->ID, 'profitshare_api_key', $api_key );
	
	$json = ps_connect( 'affiliate-campaigns', 'GET', array(), 'page=1' );
	if ( false !== $json ) {
		update_user_meta( $current_user->ID, 'is_curl_profitshare_connected', 1 );
		update_advertisers_db();
		return true;
	} else {
		update_user_meta( $current_user->ID, 'profitshare_api_user', '' );
		update_user_meta( $current_user->ID, 'profitshare_api_key', '' );
		update_user_meta( $current_user->ID, 'is_curl_profitshare_connected', 0 );
		return false;
	}
}

/**
 *	@since: 1.0.0
 *	Se obţine castigul curent Profitshare cu update la o ora
 */
function get_account_balance() {
	
	if (get_option( 'last_check_account_balance' ) + 60 * 60 * 1 < time()) {
		$json	= ps_connect( 'affiliate-info', 'GET', array());
		$total	= number_format($json["result"]["current_affiliate_earnings"], 2);
		
		update_option( 'account_balance', $total);
		update_option( 'last_check_account_balance', time() + 60 * 60 * 1 );
		
		return $total;
	} else {
		return get_option( 'account_balance' );
	}
}

/**
 *	@since: 1.0.0
 *	Se obţin conversiile de pe Profitshare şi se face cache în baza de date
 *	Acelaşi tabel se updatează odată la 6 ore
 */
function update_conversions() {
	global $wpdb;	
	$current_user = wp_get_current_user();
	if ( get_option( 'last_conversions_update' ) + 60 * 60 * 6 < time() && get_user_meta( $current_user->ID, 'is_curl_profitshare_connected', true )) {	
		update_option( 'last_conversions_update', time() + 60 * 60 * 6 );
		
		$json = ps_connect( 'affiliate-commissions', 'GET', array(), 'page=1' );

		
		$data = $json['result']['commissions'];
		$wpdb->query( "TRUNCATE " . $wpdb->prefix . "ps_conversions" );	// Se goleşte tabelul cu ultimele conversii
		for ( $i = 0; $i < 25; $i++ ) {
			$insert_data = array(
				'order_date'		=> $data[ $i ]['order_date'],
				'items_commision'	=> $data[ $i ]['items_commision'],
				'order_status'		=> $data[ $i ]['order_status'],
				'advertiser_id'		=> $data[ $i ]['advertiser_id']
				);
			$format_data = array( '%s', '%s', '%s', '%u' );
			$wpdb->insert( $wpdb->prefix . "ps_conversions", $insert_data, $format_data );	// Se introduc noile valori ale conversiilor
		}
	}
}

/**
 *	@since: 1.0.0
 *	Se obţine lista de advertiseri din reţeaua Profitshare şi se face cache în baza de date
 *	Acelaşi tabel se updatează odată la 24 ore
 *	În funcţie de parametrul $update_db, funcţia returnează numele advertiserului în funcţie de ID (preluat din baza de date)
 */
function update_advertisers_db() {
	global $wpdb;
	$current_user = wp_get_current_user();
	
	if ( get_option( 'last_advertisers_update' ) + 60 * 60 * 24 < time() && get_user_meta( $current_user->ID, 'is_curl_profitshare_connected', true ) ) {
		update_option( 'last_advertisers_update', time() + 60 * 60 * 24 );

		$json = ps_connect( 'affiliate-advertisers', 'GET' );
		foreach($json["result"] as $res) {
			$adv_id	= $res['id'];
			$check	= $wpdb->get_results( "SELECT name FROM " . $wpdb->prefix . "ps_advertisers WHERE advertiser_id='" . esc_sql($adv_id) . "'", OBJECT );
			if ( empty( $check[0]->name ) ) {
				$insert_data = array(
						'advertiser_id'			=> $res['id'],
						'name'					=> $res['name'],
						'link'					=> clear_url( $res['url'] )
				);
				$format_data = array( '%u', '%s', '%s', '%s' );
				$wpdb->insert( $wpdb->prefix . "ps_advertisers", $insert_data, $format_data );
			}
		}
	}
}

/**
 *	@since: 1.0.0
 *	Scurtează linkul trimis şi îl returnează
 *	În acelaşi timp introduce informaţiile în baza de date în tabelul cu linkuri (acelaşi tabel ce menţine istoria linkurilor scurtate)
 *	În cazul în care linkul nu face parte din reţeaua de advertiseri de la Profitshare, funcţia returnează false
 */
function shorten_link( $source, $link ) {
	if ( empty( $link ) || strpos( $link, 'profitshare.ro/l/' ) ) return false;
	$json = ps_connect( 'affiliate-links', 'POST', array(
		'name'	=> $source,
		'url'	=> $link
	));
	if ( ! empty( $json ) && isset( $json['result'][0]['ps_url'] ) ) {
		$result = $json['result'][0]['ps_url'];
		if ( isset( $result ) ) {
			global $wpdb;
			$insert_data = array(
				'source'	=> $source,
				'link'		=> $link,
				'shorted'	=> $result,
				'date'		=> time()
			);
			$wpdb->insert( $wpdb->prefix . "ps_shorted_links", $insert_data, array( '%s', '%s', '%s', '%u' ) );
			return $result;
		} else return false;
	} else return false;
}

/**
 *	@since: 1.0.0
 *	Curăţă adresa url primită prin parametrul $url
 *	Funcţia este folosită la Generarea linkurilor în articole
 *	Funcţia este utilă pentru o comparaţie sigură a linkurilor din articole cu cele ale advertiserilor
 */
function clear_url( $url ) {
	$url = parse_url( $url );
	$url = str_replace( 'www.', '', $url['host'] );
    return $url;
}

/**
 *	@since: 1.0.0
 *	Înlocuieşte toate linkurile advertiserilor din toate articolele de pe blog, cu linkuri scurte ale acestora
 *	Pe viitor: îmbunătăţire prin micşorarea complexităţii algoritmului
 *	Funcţia returnează numărul de linkuri scurtate din toate articolele
 */
function replace_links_posts() {
	global $wpdb;
	$post_ids = $wpdb->get_results( "SELECT ID FROM " . $wpdb->prefix . "posts WHERE `post_status`='publish' AND `post_type`='post' ORDER BY ID DESC", OBJECT );
	$shorted_links = 0;
	foreach ( $post_ids as $p ) {
		$post = get_post( $p->ID );
		// Replace link
		if ( false !== ($total = replace_links_post($post)) )
			$shorted_links += $total;
	}
	return $shorted_links;
}

/**
 *	@since: 1.0.0
 *	Inlocuim toate link-urile dintr-un post cu link-uri ProfitShare
 *	@param Object $post
 */
function replace_links_post($post) {
	global $wpdb;
	@set_time_limit( 90 );
	@ini_set( 'memory_limit', '512M' );
	$total_links = 0;
	if ( ! empty( $post->post_content ) && ! empty( $post->post_title ) ) {
		$content 	= $post->post_content;
		$post_title = $post->post_title;
		$links 		= get_html_links( $content );
		$links 		= array_unique( $links );
		$count_links = count( $links );
		for ( $j = 0; $j < $count_links; $j++  ) {
			$check_advertiser = $wpdb->get_results( "SELECT advertiser_id FROM " . $wpdb->prefix . "ps_advertisers WHERE link='" . clear_url( $links [ $j ] ) . "'", OBJECT );
			if ( isset( $check_advertiser[0] ) && 0 != (int)$check_advertiser[0]->advertiser_id ) {
				$shorten_link = shorten_link( $post_title, $links[ $j ] );
				if ( false != $shorten_link ) {
					// Escape url
					$url	= $links[ $j ];
					$escape = array( '.', '/', ':', '-', '_', '?', '#' );
					foreach ( $escape as $char ) {
						$url = str_replace( $char, "\\$char",  $url );
					}
					// Replace Advertisers URLS
					$content = preg_replace( '/href="' . $url . '"/is', 'href="' . $shorten_link . '"', $content );
					$wpdb->get_results( "UPDATE " . $wpdb->prefix . "posts SET `post_content`='" . $content . "' WHERE ID='" . $post->ID . "'" );
					$total_links++;
				}
			}
		}
	}
	return $total_links;
}

/**
 *	@since: 1.0.0
 *	Se extrag toate linkurile din conţinutul unui articol şi se pun într-un vector
 *	Funcţia returnează vectorul cu linkuri
 */
function get_html_links( $content ) {
	preg_match_all( '/<a [^>]+>/i', $content, $links );
	$result = array();
	$html_links = array();
	foreach( $links[0] as $link_tag ) {
		preg_match_all( '/(href)=("[^"]*")/i', $link_tag, $result[ $link_tag ] );
		array_push( $html_links, str_replace( '"', '', $result[ $link_tag ][2][0] ) );
	}
	return $html_links;
}

/**
 *	@since: 1.0.0
 *	Traduce lunile anului în limba română
 *	Utilitate: în listarea informaţiilor
 *	Funcţia returnează numele lunii în limba română
 */
function translate_month( $month ) {
	switch ( $month ) {
		case 1:		return 'ianuarie'; break;
		case 2:		return 'februarie'; break;
		case 3:		return 'martie'; break;
		case 4:		return 'aprilie'; break;
		case 5:		return 'mai'; break;
		case 6:		return 'iunie'; break;
		case 7:		return 'iulie'; break;
		case 8:		return 'august'; break;
		case 9:		return 'septembrie'; break;
		case 10:	return 'octombrie'; break;
		case 11:	return 'noiembrie'; break;
		default:	return 'decembrie';	
	}
}

/**
 *	@since: 1.0.0
 *	Conexiune la Profitshare
 */
function ps_connect( $path, $method = 'GET', $post_data = array(), $query_string = "") {
	if ( is_callable( 'curl_init' ) ) {
		$curl_init = curl_init();
		curl_setopt( $curl_init, CURLOPT_HEADER, false );
		curl_setopt( $curl_init, CURLOPT_URL, PS_URL . "/" . $path . "/?" . $query_string );
		curl_setopt( $curl_init, CURLOPT_CONNECTTIMEOUT, 60 );
		curl_setopt( $curl_init, CURLOPT_TIMEOUT, 30 );
		curl_setopt( $curl_init, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl_init, CURLOPT_HTTPAUTH, CURLAUTH_ANY );
		curl_setopt( $curl_init, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT'] );
		if ( 'POST' == $method ) {
			curl_setopt( $curl_init, CURLOPT_POST, true );
			curl_setopt( $curl_init, CURLOPT_POSTFIELDS, http_build_query( $post_data ) );
		}
		$current_user 			= wp_get_current_user();
		$profitshare_api_user 	= get_user_meta( $current_user->ID, 'profitshare_api_user', true );
		$profitshare_api_key 	= get_user_meta( $current_user->ID, 'profitshare_api_key', true );
		$profitshare_login 		= array(
				'api_user'	=> $profitshare_api_user,
				'api_key'	=> $profitshare_api_key
		);
		$date 				= gmdate( 'D, d M Y H:i:s T', time() );
		$signature_string 	= $method . $path . '/?' . $query_string . '/' . $profitshare_login['api_user'] . $date;
		$auth 				= hash_hmac( 'sha1', $signature_string, $profitshare_login['api_key'] );
		$extra_headers = array(
				"Date: {$date}",
				"X-PS-Client: {$profitshare_login['api_user']}",
				"X-PS-Accept: json",
				"X-PS-Auth: {$auth}"
		);
		curl_setopt( $curl_init, CURLOPT_HTTPHEADER, $extra_headers );
		$content = curl_exec( $curl_init );
		// Check if exist connection error
		if ( ! curl_errno( $curl_init ) ) {
			$info = curl_getinfo( $curl_init );
		
			
			if ( $info['http_code'] != 200 ) {
				curl_close( $curl_init );
				return false;
			}
		} else {
			curl_close( $curl_init );
			return false;
		}
		return json_decode( $content, true );
	} else return false;
}
?>