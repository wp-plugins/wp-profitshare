<?php
/**	Functions
 *	@ package: wp-profitshare
 *	@ since: 1.0
 */
$ps_api_config = array(
			'RO'	=>	array(	'NAME'		=>	'Romania',
								'API_URL'	=>	'http://api.profitshare.ro',
								'PS_HOME'	=>	'http://profitshare.ro',
								'CURRENCY'	=>	'RON',
							),
			'BG'	=>	array(	'NAME'		=>	'Bulgaria',
								'API_URL'	=>	'http://api.profitshare.bg',
								'PS_HOME'	=>	'http://profitshare.bg',
								'CURRENCY'	=>	'LEV',
							)
		);
function config( $param ) {
	/**
	 *	@since: 1.2
	 *	Get values of config matrix from the user country
	 */
	global $ps_api_config;
	$current_user = wp_get_current_user();
	$country = get_user_meta( $current_user->ID, 'ps_api_country', true );
	return $ps_api_config[ $country ][ $param ];
}
function ps_init_settings() {
	/**
	 *	@since: 1.0
	 *	Creating tables
	 */
	global $wpdb;
	$queries = array();
	$queries[] = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "ps_advertisers (
		`ID` smallint(5) unsigned NOT NULL auto_increment,
		`advertiser_id` mediumint(5) unsigned NOT NULL,
		`name` char(250) NOT NULL,
		`link` char(50) NOT NULL,
		UNIQUE KEY (`advertiser_id`),
		UNIQUE KEY (`name`),
		PRIMARY KEY (`ID`)
		)CHARSET=utf8;";
	$queries[] = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "ps_conversions (
		`ID` smallint(5) unsigned NOT NULL auto_increment,
		`order_date` char(20) NOT NULL,
		`items_commision` char(255) NOT NULL,
		`order_status` char(8) NOT NULL,
		`advertiser_id` mediumint(5) unsigned NOT NULL,		
		PRIMARY KEY (`ID`)
		)CHARSET=utf8;";
   $queries[] = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "ps_keywords (
                `ID` mediumint(9) NOT NULL auto_increment,
                `keyword` varchar(255) NOT NULL default '',
                `title` varchar(255) NOT NULL default '',
                `link` varchar(255) NOT NULL default '',
                `openin` varchar(55) NOT NULL default '',
                `tip_display` enum('y','n') DEFAULT NULL,
                `tip_style` varchar(55) default NULL,
                `tip_description` text,
                `tip_title` varchar(255) default NULL,
                `tip_image` varchar(255) default NULL,
                PRIMARY KEY (`ID`)
  )CHARSET=utf8;";
	$queries[] = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "ps_shorted_links (
		`ID` smallint(5) unsigned NOT NULL auto_increment,
		`source` char(100) NOT NULL,
		`link` text NOT NULL,
		`shorted` char(50) NOT NULL,
		`date` int(10) NOT NULL,
		PRIMARY KEY (`ID`)
		)CHARSET=utf8;";
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
  foreach($queries as $query){
     dbDelta($query);
  }
  
 // Set PS Version
	update_option('ps_installed_version', PS_VERSION);
}

function ps_remove_settings() {
	/**
	 *	@since: 1.0
	 *	Cleaning DB after uninstall
	 *	Table *_ps_shorted_links remains for future installs
	 *	Table *_ps_keywords remains for future installs
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

function ps_connection_check( $api_user, $api_key, $api_country ) {
	/**
	 *	@since: 1.0
	 *	Check API connexion through cURL
	 *	@param:		(string)	$api_user
	 *				(string)	$api_key
	 *				(string)	$api_country
	 *	@return:	(bool)
	 */
	$current_user = wp_get_current_user();
	update_user_meta( $current_user->ID, 'ps_api_user', $api_user );
	update_user_meta( $current_user->ID, 'ps_api_key', $api_key );
	update_user_meta( $current_user->ID, 'ps_api_country', $api_country );
	$json = ps_api_connect( 'affiliate-campaigns', 'GET', array(), 'page=1' );
	if ( false !== $json ) {
		update_user_meta( $current_user->ID, 'ps_is_api_connected', 1 );
		ps_update_advertisers_db();
		return true;
	} else {
		delete_user_meta( $current_user->ID, 'ps_api_user' );
		delete_user_meta( $current_user->ID, 'ps_api_key' );
		delete_user_meta( $current_user->ID, 'ps_api_country' );
		return false;
	}
}

function ps_account_balance() {
	/**
	 *	@since: 1.0
	 *	Get current ballance
	 *	Update every 60 minutes
	 */
	if ( get_option( 'ps_last_check_account_balance' ) + 60 * 60 < time() ) {
		$json	= ps_api_connect( 'affiliate-info', 'GET', array() );
		$total	= number_format( $json['result']['current_affiliate_earnings'], 2 );
		update_option( 'ps_account_balance', $total );
		update_option( 'ps_last_check_account_balance', time() + 60 * 60 );
		return $total;
	} else {
		global $ps_api_config;
		$current_user = wp_get_current_user();
		$country = get_user_meta( $current_user->ID, 'ps_api_country', true );
		return get_option( 'ps_account_balance' ) . ' ' . config( 'CURRENCY' );
	}
}

function ps_update_conversions() {
	/**
	 *	@since: 1.0
	 *	Get PS conversions and chaching into DB
	 *	Update every 6 hours
	 */
	global $wpdb;
	$current_user = wp_get_current_user();
	if ( get_option( 'ps_last_conversions_update' ) + 60 * 60 * 6 < time() && get_user_meta( $current_user->ID, 'ps_is_api_connected', true )) {
		update_option( 'ps_last_conversions_update', time() + 60 * 60 * 6 );
		$json = ps_api_connect( 'affiliate-commissions', 'GET', array(), 'page=1' );
		$data = $json['result']['commissions'];
		$wpdb->query( "TRUNCATE " . $wpdb->prefix . "ps_conversions" );	// Cleaning table 
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
	 *	Get advertisers list and caching
	 *	Update every 24 hours
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
	 *	Replace all advertisers links from blog
	 *	Returns (with functions: replace_links_post() and shorten_link()) how many links have been replaced
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
	 *	Replace all links from a post and returns how many links have been replaced
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
	 *	Replace all comments links from a post and returns how many links have been replaced
	 *	@param:		(Object)	$post
	 *	@return:	(int)		$total_links
	 */
	 
	global $wpdb;
	$content	=	get_comment_text( $comment_id );
	$title		=	'Comment #' . $comment_id;
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
	 *	Extracting all links from post content and placing them into a vector
	 *	Function returns the vector with links
	 *	The function have been upgraded starting with version 1.1 by using DOMDocument() class
	 *	@param:		(string)	$text;
	 *	@return:	(array)		$links
	 */
	global $wpdb;
	$DOMDoc = new DOMDocument();
	@$DOMDoc->loadHTML( $content );
	$links = array();
    foreach( $DOMDoc->getElementsByTagName('a') as $link ) {
    	$check_advertiser = $wpdb->get_results( "SELECT COUNT(*) as count FROM " . $wpdb->prefix . "ps_advertisers WHERE link='" . ps_clear_url( $link->getAttribute('href') ) . "'", OBJECT );
		if ( $check_advertiser[0]->count || strpos( $link->getAttribute('href'), 'm.' ) )
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
	if ( empty( $link ) || strpos( $link, config( 'PS_HOME' ) . '/l/' ) )
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
	 *	Cleaning URL trough function parameters C
	 *	utility: Secure comparing for links between the post content and advertisers links.
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
	 *	Changing long links from post content with the short ones. 
	 *	The function is referring to the filter for 'the_content' and 'comment_text'
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
					$doc->loadHTML('<?xml encoding="UTF-8">' . $anchors[0][$j]);
					$link = $doc->getElementsByTagName('a');
					$new_content = '<a href="' . $shorted[0]->shorted . '" target="_blank" rel="nofollow">' . $link->item(0)->nodeValue . '</a>';
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
	 *	Translate months of the year in english
	 *	The function returns english months
	 *	@param:		(int)		$month
	 *	@return:	(string)
	 */
	switch ( $month ) {
		case 1:		return 'January'; break;
		case 2:		return 'February'; break;
		case 3:		return 'March'; break;
		case 4:		return 'April'; break;
		case 5:		return 'May'; break;
		case 6:		return 'June'; break;
		case 7:		return 'July'; break;
		case 8:		return 'August'; break;
		case 9:		return 'September'; break;
		case 10:	return 'October'; break;
		case 11:	return 'November'; break;
		default:	return 'December';	
	}
}

function ps_api_connect( $path, $method = 'GET', $post_data = array(), $query_string = "") {
	/**
	 *	@since: 1.0
	 *	Connexion trough cURL for Profitshare API
	 *	@param:		(string)		$path
	 *				(string)		$method
	 *				(array)			$post_data
	 *				(string)		$query_string
	 *	@return:	(bool|string)	FALSE|$content
	 */
	if ( is_callable( 'curl_init' ) ) {
		$current_user	= wp_get_current_user();
		$ps_api_user	= get_user_meta( $current_user->ID, 'ps_api_user', true );
		$ps_api_key		= get_user_meta( $current_user->ID, 'ps_api_key', true );
		$ps_api_country = get_user_meta( $current_user->ID, 'ps_api_country', true );
		global $ps_api_config;
		$api_url = $ps_api_config[ $ps_api_country ]['API_URL'];
		$curl_init = curl_init();
		curl_setopt( $curl_init, CURLOPT_HEADER, false );
		curl_setopt( $curl_init, CURLOPT_URL, $api_url . "/" . $path . "/?" . $query_string );
		curl_setopt( $curl_init, CURLOPT_CONNECTTIMEOUT, 60 );
		curl_setopt( $curl_init, CURLOPT_TIMEOUT, 30 );
		curl_setopt( $curl_init, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl_init, CURLOPT_HTTPAUTH, CURLAUTH_ANY );
		curl_setopt( $curl_init, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT'] );
		if ( 'POST' == $method ) {
			curl_setopt( $curl_init, CURLOPT_POST, true );
			curl_setopt( $curl_init, CURLOPT_POSTFIELDS, http_build_query( $post_data ) );
		}
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