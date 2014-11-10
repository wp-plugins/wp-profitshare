<?php
/**	Functions
 *	@ package: wp-profitshare
 *	@ since: 1.0
 */
function ps_init_settings() {
	/**
	 *	@since: 1.0
	 *	Sunt create tabelele plugin-ului și sunt configurate anumite valori
	 */
	global $wpdb;
	$query = array();
	$query[] = "CREATE TABLE " . $wpdb->prefix . "ps_advertisers (
		ID smallint(5) unsigned NOT NULL auto_increment,
		advertiser_id mediumint(5) unsigned NOT NULL,
		name char(250) NOT NULL,
		link char(50) NOT NULL,
		UNIQUE KEY (advertiser_id),
		UNIQUE KEY (name),
		PRIMARY KEY (ID)
		);";
	$query[] = "CREATE TABLE " . $wpdb->prefix . "ps_conversions (
		ID smallint(5) unsigned NOT NULL auto_increment,
		order_date char(20) NOT NULL,
		items_commision char(255) NOT NULL,
		order_status char(8) NOT NULL,
		advertiser_id mediumint(5) unsigned NOT NULL,		
		PRIMARY KEY (ID)
		);";
        $query[] = "CREATE TABLE " . $wpdb->prefix . "ps_keywords (
                ID mediumint(9) NOT NULL auto_increment,
                keyword varchar(255) NOT NULL default '',
                title varchar(255) NOT NULL default '',
                link varchar(255) NOT NULL default '',
                openin varchar(55) NOT NULL default '',
                tip_display enum('y','n') DEFAULT NULL,
                tip_style varchar(55) default NULL,
                tip_description text,
                tip_title varchar(255) default NULL,
                tip_image varchar(255) default NULL,
                UNIQUE KEY id (id)
                );";
	$query[] = "CREATE TABLE " . $wpdb->prefix . "ps_shorted_links (
		ID smallint(5) unsigned NOT NULL auto_increment,
		source char(100) NOT NULL,
		link text NOT NULL,
		shorted char(50) NOT NULL,
		date int(10) NOT NULL,
		PRIMARY KEY (ID)
		);";
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $query );
}

function ps_remove_settings() {
	/**
	 *	@since: 1.0
	 *	Se curăță urmele plugin-ului, după dezactivare
	 *	Tabelul *_ps_shorted_links rămâne intact pentru istoric și o eventuală reactivare
	 *	Tabelul *_ps_keywords rămâne intact pentru istoric și o eventuală reactivare
	 */
	global $wpdb;
	$current_user = wp_get_current_user();
	delete_user_meta( $current_user->ID, 'ps_api_user' ); 
	delete_user_meta( $current_user->ID, 'ps_api_key' );
	delete_user_meta( $current_user->ID, 'ps_is_api_connected' );
	delete_option( 'ps_last_advertisers_update' );
	delete_option( 'ps_last_conversions_update' );
	delete_option( 'ps_account_balance' );
	delete_option( 'ps_last_check_account_balance' );
	delete_option( 'auto_convert_posts' );
	delete_option( 'auto_convert_comments' );
	$wpdb->query( "DROP TABLE " . $wpdb->prefix . "ps_advertisers" );
	$wpdb->query( "DROP TABLE " . $wpdb->prefix . "ps_conversions" );
}

function ps_connection_check( $api_user, $api_key ) {
	/**
	 *	@since: 1.0
	 *	Se verifică dacă se poate face conexiunea la api.profitshare.ro prin cURL
	 *	@param:		(string)	$api_user
	 *				(string)	$api_key
	 *	@return:	(bool)
	 */
	$current_user = wp_get_current_user();
	update_user_meta( $current_user->ID, 'ps_api_user', $api_user );
	update_user_meta( $current_user->ID, 'ps_api_key', $api_key );
	$json = ps_api_connect( 'affiliate-campaigns', 'GET', array(), 'page=1' );
	if ( false !== $json ) {
		update_user_meta( $current_user->ID, 'ps_is_api_connected', 1 );
		ps_update_advertisers_db();
		return true;
	} else {
		delete_user_meta( $current_user->ID, 'ps_api_user' );
		delete_user_meta( $current_user->ID, 'ps_api_key' );
		return false;
	}
}

function ps_account_balance() {
	/**
	 *	@since: 1.0
	 *	Se obţine câștigul curent Profitshare prin API
	 *	Valoarea se reactualizează o dată la 60 de minute
	 */
	if ( get_option( 'ps_last_check_account_balance' ) + 60 * 60 < time() ) {
		$json	= ps_api_connect( 'affiliate-info', 'GET', array() );
		$total	= number_format( $json['result']['current_affiliate_earnings'], 2 );
		update_option( 'ps_account_balance', $total );
		update_option( 'ps_last_check_account_balance', time() + 60 * 60 );
		return $total;
	} else {
		return get_option( 'ps_account_balance' );
	}
}

function ps_update_conversions() {
	/**
	 *	@since: 1.0
	 *	Se obţin conversiile de pe Profitshare şi se face cache în baza de date
	 *	Informațiile se actualizează o dată la 6 ore
	 */
	global $wpdb;
	$current_user = wp_get_current_user();
	if ( get_option( 'ps_last_conversions_update' ) + 60 * 60 * 6 < time() && get_user_meta( $current_user->ID, 'ps_is_api_connected', true )) {
		update_option( 'ps_last_conversions_update', time() + 60 * 60 * 6 );
		$json = ps_api_connect( 'affiliate-commissions', 'GET', array(), 'page=1' );
		$data = $json['result']['commissions'];
		$wpdb->query( "TRUNCATE " . $wpdb->prefix . "ps_conversions" );	// Se goleşte tabelul cu ultimele conversii
		if ( ! empty( $data ) )
			for ( $i = 0; $i < 25; $i++ ) {
				$insert_data = array(
					'order_date'		=>	$data[ $i ]['order_date'],
					'items_commision'	=>	$data[ $i ]['items_commision'],
					'order_status'		=>	$data[ $i ]['order_status'],
					'advertiser_id'		=>	$data[ $i ]['advertiser_id']
					);
				$format_data = array( '%s', '%s', '%s', '%u' );
				$wpdb->insert( $wpdb->prefix . "ps_conversions", $insert_data, $format_data );	// Se introduc noile valori ale conversiilor
			}
	}
}

function ps_update_advertisers_db() {
	/**
	 *	@since: 1.0
	 *	Se obţine lista de advertiseri din reţeaua Profitshare şi se face cache în baza de date
	 *	Informațiile se actualizează o dată la 24 ore
	 */
	global $wpdb;
	$current_user = wp_get_current_user();
	if ( get_option( 'ps_last_advertisers_update' ) + 60 * 60 * 24 < time() && get_user_meta( $current_user->ID, 'ps_is_api_connected', true ) ) {
		update_option( 'ps_last_advertisers_update', time() + 60 * 60 * 24 );
		$json = ps_api_connect( 'affiliate-advertisers', 'GET' );
		foreach( $json['result'] as $res ) {
			$adv_id	= (int)$res['id'];
			$check	= $wpdb->get_results( "SELECT * FROM " . $wpdb->prefix . "ps_advertisers WHERE advertiser_id='" . $adv_id . "'", OBJECT );
			if ( ! $check ) {
				$insert_data = array(
						'advertiser_id'	=>	$res['id'],
						'name'			=>	$res['name'],
						'link'			=>	ps_clear_url( $res['url'] )
				);
				$wpdb->insert( $wpdb->prefix . "ps_advertisers", $insert_data, array( '%u', '%s', '%s', '%s' ) );
			}
		}
	}
}

function ps_replace_links( $where='posts' ) {
	/**
	 *	@since: 1.0
	 *	Înlocuieşte toate linkurile advertiserilor din toate articolele de pe blog, cu linkuri scurte ale acestora
	 *	Funcţia returnează (cu ajutorul funcției replace_links_post() și shorten_link()) numărul de linkuri scurtate din toate articolele
	 *	@return:	(int)	$shorted_links
	 */
	global $wpdb;
	if ( 'posts' == $where )
		$item_ids = $wpdb->get_results( "SELECT ID FROM " . $wpdb->prefix . "posts WHERE `post_status`='publish' AND `post_type`='post' ORDER BY ID DESC", OBJECT );
	else
		$item_ids = $wpdb->get_results( "SELECT comment_ID FROM " . $wpdb->prefix . "comments ORDER BY comment_ID DESC", OBJECT );
	$shorted_links = 0;
	foreach ( $item_ids as $item )
		if ( 'posts' == $where ) {
			if ( false !== ($total = ps_replace_links_post( $item->ID ) ) )
				$shorted_links += $total;
		} else {
			if ( false !== ($total = ps_replace_links_comment( $item->comment_ID ) ) )
				$shorted_links += $total;
		}
	return $shorted_links;
}

function ps_replace_links_post( $post_id ) {
	/**
	 *	@since: 1.0
	 *	Înlocuiește toate link-urile dintr-un articol cu link-uri ProfitShare și returnează numărul de linkuri scurtate
	 *	@param:		(Object)	$post
	 *	@return:	(int)		$total_links
	 */
	global $wpdb;
	$post		=	get_post( $post_id );
	$content	=	$post->post_content;
	$title		=	$post->post_title;
	$total_links = 0;
	if ( ! empty( $content ) && ! empty( $title ) ) {
		$links			=	ps_get_html_links( $content );
		$count_links	=	count( $links );
		for ( $i = 0; $i < $count_links; $i++  ) {
			$shorten_link = ps_shorten_link( $title, $links[ $i ]['url'] );
			if ( $shorten_link['shorten'] )
				 $total_links++;
		}
	}
	return $total_links;
}

function ps_auto_convert_posts( $post_id ) {
	get_option( 'auto_convert_posts' ) ? ps_replace_links_post( $post_id ) : 0;
}


function ps_replace_links_comment( $comment_id ) {
	/**
	 *	@since: 1.1
	 *	Înlocuiește toate link-urile dintr-un articol cu link-uri ProfitShare și returnează numărul de linkuri scurtate
	 *	@param:		(Object)	$post
	 *	@return:	(int)		$total_links
	 */
	 
	global $wpdb;
	$content	=	get_comment_text( $comment_id );
	$title		=	'Comentariul #' . $comment_id;
	$total_links = 0;
	if ( ! empty( $content ) && ! empty( $title ) ) {
		$links			=	ps_get_html_links( make_clickable( $content ) );
		$count_links	=	count( $links );
		for ( $i = 0; $i < $count_links; $i++  ) {
			$shorten_link = ps_shorten_link( $title, $links[ $i ]['url'] );
			if ( $shorten_link['shorten'] )
				 $total_links++;
		}
	}
	return $total_links;
}

function ps_auto_convert_comments( $comment_id ) {
	get_option( 'auto_convert_comments' ) ? ps_replace_links_comment( $comment_id ) : 0;
}

function ps_get_html_links( $content ) {

	/**
	 *	@since: 1.1
	 *	Se extrag toate linkurile din conţinutul unui articol şi se pun într-un vector
	 *	Funcţia returnează vectorul cu linkuri
	 *	Funcția a fost îmbunătățită începând cu versiunea 1.1 folosind clasa DOMDocument()
	 *	@param:		(string)	$text;
	 *	@return:	(array)		$links
	 */
	 
	global $wpdb;
	$DOMDoc = new DOMDocument();
	@$DOMDoc->loadHTML( $content );
	$links = array();
    foreach( $DOMDoc->getElementsByTagName('a') as $link ) {
    	$check_advertiser = $wpdb->get_results( "SELECT COUNT(*) as count FROM " . $wpdb->prefix . "ps_advertisers WHERE link='" . ps_clear_url( $link->getAttribute('href') ) . "'", OBJECT );
		if ( $check_advertiser[0]->count )
			$links[] = array(
				'url'	=>	$link->getAttribute('href'),
				'text'	=>	$link->nodeValue
			);
	}
	return $links;
}

function ps_shorten_link( $source, $link ) {
	/**
	 *	@since: 1.0
	 *	Dacă link-ul primit prin parametrul $link, nu a fost scurtat, îl scurtează
	 *	Dacă link-ul primit prin parametrul $link a fost deja scurtat, funcția returnează link-ul scurtat respectiv
	 *	Funcția returnează un vector:
	 *		shorted	=>	link-ul scurtat
	 *		shorten	=>	1 dacă s-a scurtat link-ul primit prin parametrul $link, 0 altfel
	 *		result	=>	1 dacă acțiunea s-a efectuat cu succes, 0 altfel
	 *	Introduce informaţiile în baza de date în tabelul cu linkuri (acelaşi tabel ce menţine istoria linkurilor scurtate)
	 *	@param:		(string)	$source
	 *				(string)	$link
	 *	@return:	(array)		$result
	 */
	global $wpdb;
	$result = array(
		'shorted'	=>	'',
		'shorten'	=>	0,
		'result'	=>	0
	);
	$check_link = $wpdb->get_results( "SELECT shorted FROM " . $wpdb->prefix . "ps_shorted_links WHERE link='" . $link . "'", OBJECT );
	if ( empty( $link ) || strpos( $link, 'profitshare.ro/l/' ) )
		$result['result'] = 0;
	else if ( ! empty( $check_link[0]->shorted ) ) {
		$result['shorted']	=	$check_link[0]->shorted;
		$result['result']	=	1;
	} else {
		$json = ps_api_connect( 'affiliate-links', 'POST', array(
				'name'	=>	$source,
				'url'	=>	$link
			)
		);
		if ( ! empty( $json ) && isset( $json['result'][0]['ps_url'] ) ) {
			$shorted = $json['result'][0]['ps_url'];
			if ( isset( $shorted ) ) {
				$insert_data = array(
					'source'	=>	$source,
					'link'		=>	$link,
					'shorted'	=>	$shorted,
					'date'		=>	time()
				);
				$return = $wpdb->insert( $wpdb->prefix . "ps_shorted_links", $insert_data, array( '%s', '%s', '%s', '%u' ) );
				$result['shorted']	=	$shorted;
				$result['shorten']++;
				$result['result']	=	$return;
			}
		}
	}
	return $result;
}

function ps_clear_url( $url ) {
	/**
	 *	@since: 1.0
	 *	Curăţă adresa url primită prin parametrul funcției
	 *	Utilitate: o comparaţie sigură a linkurilor din articole cu cele ale advertiserilor
	 *	@param:		(string)	$url
	 *	@return:	(string)	$url
	 */
	$url = parse_url( $url );
	$url = str_replace( 'www.', '', $url['host'] );
    return $url;
}

function ps_filter_links( $content ) {
	/**
	 *	@since: 1.1
	 *	Se înlocuiesc linkurile lungi din articole cu link-urile scurte aferente fiecăruia
	 *	Funcția este referință la filtrul pentru 'the_content' și 'comment_text'
	 *	@param:		(string)	$content
	 *	@return:	(string)	$content
	 */
	global $wpdb;
	$links = ps_get_html_links( $content );
	$count_links = count( $links );
	for ( $i = 0; $i < $count_links; $i++ )
	{
		$shorted = $wpdb->get_results( "SELECT shorted FROM " . $wpdb->prefix . "ps_shorted_links WHERE link='" . $links[ $i ]['url'] . "'" );
		if ( isset( $shorted[0]->shorted ) ) {
			preg_match_all('~<a\s+.*?</a>~is', $content, $anchors);
			for ( $j = 0; $j < count( $anchors[0] ); $j++ )
				if ( strpos( $anchors[0][ $j ], $links[ $i ]['url'] ) ) {
					$doc = new DOMDocument();
					$doc->loadHTML( $anchors[0][ $j ] );
					$link = $doc->getElementsByTagName('a');
					$new_content = '<a href="' . $shorted[0]->shorted . '" target="_blank" rel="nofollow">' . $link->item(0)->nodeValue . ' <img src="' . plugins_url( '../images/hyperlink.png', __FILE__ ) . '" style="display: inline;" alt="Profitshare link" /></a>';
					$content = str_replace( $anchors[0][ $j ], $new_content, $content );
					$content = str_replace( $links[ $i ]['url'], $shorted[0]->shorted, $content );
				}
		}
	}
	return $content;
}

function ps_translate_month( $month ) {
	/**
	 *	@since: 1.0
	 *	Traduce lunile anului în limba română
	 *	Funcţia returnează numele lunii în limba română
	 *	@param:		(int)		$month
	 *	@return:	(string)
	 */
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

function ps_api_connect( $path, $method = 'GET', $post_data = array(), $query_string = "") {
	/**
	 *	@since: 1.0
	 *	Conexiune prin cURL la API-ul de la Profitshare
	 *	@param:		(string)		$path
	 *				(string)		$method
	 *				(array)			$post_data
	 *				(string)		$query_string
	 *	@return:	(bool|string)	FALSE|$content
	 */
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
		$current_user		= wp_get_current_user();
		$ps_api_user		= get_user_meta( $current_user->ID, 'ps_api_user', true );
		$ps_api_key			= get_user_meta( $current_user->ID, 'ps_api_key', true );
		$auth_data	= array(
			'api_user'	=>	$ps_api_user,
			'api_key'	=>	$ps_api_key
		);
		$date 				=	gmdate( 'D, d M Y H:i:s T', time() );
		$signature_string 	=	$method . $path . '/?' . $query_string . '/' . $auth_data['api_user'] . $date;
		$auth 				=	hash_hmac( 'sha1', $signature_string, $auth_data['api_key'] );
		$extra_headers = array(
			"Date: {$date}",
			"X-PS-Client: {$auth_data['api_user']}",
			"X-PS-Accept: json",
			"X-PS-Auth: {$auth}"
		);		
		curl_setopt( $curl_init, CURLOPT_HTTPHEADER, $extra_headers );
		$content = curl_exec( $curl_init );
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