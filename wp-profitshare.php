<?php
/**
 * Plugin Name: WP Profitshare
 * Plugin URI: http://www.profitshare.ro
 * Description: Plugin-ul converteste toate link-urile directe catre advertiseri existenti in Profitshare in link-uri care au paramentru de tracking pentru inregistrarea conversiilor aferente promovarii acestora. 
 * Version: 1.0
 * Author: Conversion
 * Author URI: http://www.conversion.ro
 * License: GPL2
 */

defined( 'ABSPATH' ) || exit;

define( 'PS_URL', 'http://api.profitshare.ro' );

require_once( 'includes/functions.php' );
require_once( 'includes/class-conversions.php' );
require_once( 'includes/class-history-links.php' );

add_action( 'admin_head', 'css_profitshare_admin_head' );
add_action( 'admin_menu', 'add_profitshare_menus' );
register_activation_hook( __FILE__, 'init_profitshare_settings' );
register_deactivation_hook( __FILE__, 'remove_profitshare_settings' );

/**
 *	@since: 1.0.0
 *	Adaugam CSS-ul in header
 */
function css_profitshare_admin_head() {
	?>
	<style type="text/css">
	#profitshare {
		background-image: url(<?php echo plugin_dir_url( __FILE__ ) . '/images/button.png'; ?>);
		background-repeat: none;
		width: 90px;
		height: 30px;
		border: 0;
		background-color: none;
		cursor: pointer;
		outline: 0;
		color: #FFFFFF;
	}
	</style>
	<?php
}

	/**
	 *	@since: 1.0.0
	 *	Creează meniul Profitshare în Dashboard
	 *	cu submeniurile: Setări plugin, Conversii, Generare în articole, Istoric linkuri
	 */
function add_profitshare_menus() {
	add_menu_page( 'Profitshare', 'Profitshare', 'manage_options', 'account_settings', 'account_settings', 'dashicons-chart-pie', 21 );
	add_submenu_page( 'account_settings', 'Setări plugin', 'Setări plugin', 'manage_options', 'account_settings', 'account_settings' );
	
	$current_user = wp_get_current_user();
	if ( get_user_meta( $current_user->ID, 'is_curl_profitshare_connected', true ) ) {
		add_submenu_page( 'account_settings', 'Conversii', 'Conversii', 'manage_options', 'conversions', 'conversions' );
		add_submenu_page( 'account_settings', 'Istoric linkuri', 'Istoric linkuri', 'manage_options', 'history_links', 'history_links' );
		add_submenu_page( 'account_settings', 'Informatii utile', 'Informatii utile', 'manage_options', 'informatii_utile', 'informatii_utile' );
	}
	
	/**
	 *	Se face cache la interval de:
	 *	24 de ore pentru advertiseri
	 *	6 ore pentru conversii
	 */
	update_advertisers_db();
	update_conversions();
}

	/**
	 *	Pagina Setări API
	 *	Se setează datele de conectare la API şi se efectuează conectarea
	 */
function account_settings() {
	$current_user 					= wp_get_current_user();
	$profitshare_api_user 			= get_user_meta( $current_user->ID, 'profitshare_api_user', true );
	$profitshare_api_key 			= get_user_meta( $current_user->ID, 'profitshare_api_key', true );
	$is_curl_profitshare_connected 	= get_user_meta( $current_user->ID, 'is_curl_profitshare_connected', true );
	if ( isset( $_POST['submit'] ) ) {
		$api_user		= esc_sql( $_POST['api_user'] );
		$api_key		= esc_sql( $_POST['api_key'] );
		$post_convert 	= isset( $_POST['post_convert']) ? 1 : 0;
		update_user_meta( $current_user->ID, 'profitshare_post_convert', $post_convert );
		if ( connection_check( $api_user, $api_key ) ) {
			echo '<meta http-equiv="refresh" content="0; url='. admin_url( 'admin.php?page=account_settings&ps_status=true' ) .'">';
		} else {
			echo '<meta http-equiv="refresh" content="0; url='. admin_url( 'admin.php?page=account_settings&ps_status=false' ) .'">';
		}
	}
	
	if ( get_user_meta( $current_user->ID, 'is_curl_profitshare_connected', true ) ) {
		$background_color	= '#006D3E';
		$title_div			= 'Conectat';
	} else {
		$background_color	= '#c0392b';
		$title_div			= 'Neconectat';
	}

	if ( get_user_meta( $current_user->ID, 'is_curl_profitshare_connected', true ) ) {
		echo '<div id="message" class="updated"><p>Datele API sunt corecte, iar conexiunea s-a realizat cu succes.</p></div>';
	} else if( get_user_meta( $current_user->ID, 'is_curl_profitshare_connected', false ) ) {
		echo '<div id="message" class="error"><p>Datele API sunt incorecte sau au apărut erori de conectare.</p></div>';
	}
	
	$profitshare_api_user		= get_user_meta( $current_user->ID, 'profitshare_api_user', true );
	$profitshare_api_key		= get_user_meta( $current_user->ID, 'profitshare_api_key', true );
	$profitshare_post_convert	= get_user_meta( $current_user->ID, 'profitshare_post_convert', true );
	$check_box = ( 1 == (int)$profitshare_post_convert ) ? 'checked="checked"' : '';
	?>
	<div class="wrap">
		<a href="http://profitshare.ro"><img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/profitshare-conversion.png" alt="Profitshare" /></a><br/>
		<hr style="border-top: 1px dashed #7f8c8d;">
		<form method="post" action="">
			<table class="form-table">
				<tr>
					<th><label for="api_user">API user</label></th>
					<td><input id="api_user" class="regular-text" type="text" name="api_user" value="<?php echo $profitshare_api_user; ?>" /></td>
				</tr>
				<tr>
					<th><label for="api_key">API key</label></th>
					<td><input id="api_key" class="regular-text" type="text" name="api_key" value="<?php echo $profitshare_api_key; ?>" /></td>
				</tr>
				<tr>
					<th><label for="post_convert">Converteste automat link-urile in viitoarele articole publicate</label></th>
					<td><input id="post_convert" type="checkbox" name="post_convert" <?php echo $check_box; ?>/></td>
				</tr>
				<tr>
					<th>Status conexiune</th>
					<td><div style="background-color: <?php echo $background_color; ?>; border-radius: 7px; width: 20px; height: 20px;" title="<?php echo $title_div; ?>"></div></td>
				</tr>
				<tr>
					<th><input type="submit" name="submit" id="profitshare" value="Salvare" /></th>
				</tr>
			</table>
		</form>
		<?php
		if ( $is_curl_profitshare_connected ) {
			if ( isset( $_POST['generate'] ) ) {
				$shorted_links = replace_links_posts();
				?>
				<div id="message" class="updated"><p>Au fost scurtate şi înlocuite <?php echo $shorted_links; ?> linkuri în toate articolele.<br/>
				<a href="<?php echo admin_url( 'admin.php?page=history_links' ); ?>">Vezi lista</a> cu linkuri modificate.</p></div>
				<?php
			}
			?>
			<h3>Generare automată de link-uri profitshare în toate articolele *</h3>
			<p>* Aceasta este o acţiune ce durează cel puţin câteva secunde. Fiecare link din reţeaua Profitshare ce se găseşte în articolele tale, va fi înlocuit cu un link profitshare, de forma http://profitshare.ro/l/123456. <strong>Pentru a evita eventualele neplăceri, vă recomandăm să faceţi un back-up la baza de date înainte de a rula această acţiune.</strong></p>
			<form action="" method="post">			
				<input type="submit" name="generate" id="profitshare" value="Rulează!" />
			</form>
			<?php
		}
		?>
	</div>
	<?php
}

	/**
	 *	Pagina Conversii
	 *	Afişează ultimele conversii preluate prin API
	 *	Se poate scurta un link de la unul dintre advertiserii din baza de date
	 */
function conversions() {
	?>
	<div class="wrap">
		<a href="http://profitshare.ro"><img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/profitshare-conversion.png" alt="Profitshare" /></a><br/>
		<hr style="border-top: 1px dashed #7f8c8d;">
		<h2>Conversii</h2>
		<h3>Câştigul tău curent este în valoare de: <font style="color: #006D3E;"><?php echo get_account_balance();?> RON</font></h3>
		<form method="post" action="">
			<table class="form-table">
				<tr>
					<td>
						<input type="text" name="link" placeholder="http://www.flanco.ro/" id="link" class="regular-text" />
						<input type="submit" name="submit_link" id="profitshare" value="Obţine link" /><br />
						<span class="description">Exemplu de link generat: http://profitshare.ro/l/123456</span>
					</td>
				</tr>
				<tr>
					<?php
					if ( isset( $_POST['submit_link'] ) ) {
						$link = esc_sql( $_POST['link'] );
						$shorten_link = shorten_link( 'WP Profitshare', $link );
						if ( ! $shorten_link ) {
							?>
							<div id="message" class="error"><p>Linkul nu face parte din reţeaua Profitshare şi nu poate fi generat.</p></div>
							<?php
						} else {
							?>
							<td><font style="color: #006D3E; font-weight: bold;">Link generat:</font><br/><input id="shorten_link" onClick="this.setSelectionRange(0, this.value.length)" class="regular-text" type="text" value="<?php echo $shorten_link; ?>" /></td>
							<?php
						}
					}
					?>
				</tr>
			</table>
		</form>
		<?php
	$conversions = new Conversions();
	$conversions->prepare_items();
	$conversions->display();
		?>
	</div>
	<?php
}

/**
 *	Generam link-uri din link-urile din articol
 */
function post_update( $new_status, $old_status, $post ) {
	$current_user 				= wp_get_current_user();
	$profitshare_post_convert 	= get_user_meta( $current_user->ID, 'profitshare_post_convert', true );

	if ( $new_status == 'publish' && get_post_type( $post ) == 'post' && $profitshare_post_convert == true ) {
		replace_links_post( $post );
	}
}
add_action( 'transition_post_status', 'post_update', 10, 3 );

	/**
	 *	Pagina Istoric linkuri
	 *	Afişează ultimele linkuri scurtate: manual sau automat
	 */
function history_links() {
	$history_links = new History_Links();
    $history_links->prepare_items();
	?>
	<div class="wrap">
		<a href="http://profitshare.ro"><img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/profitshare-conversion.png" alt="Profitshare" /></a><br/>
		<hr style="border-top: 1px dashed #7f8c8d;">
		<h2>Istoric linkuri</h2>
		<?php $history_links->display(); ?>
	</div>
	<?php
}

	/**
	 *	Pagina Informatii utile
	 *	Contine rubrica F.A.Q.
	 *	Contine feed cu ultimele 2 articole din blog.profitshare.ro avand excerpt 500 caractere.
	 */
function informatii_utile() {
	?>
	<div class="wrap">
		<a href="http://profitshare.ro"><img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/profitshare-conversion.png" alt="Profitshare" /></a><br/>
		<hr style="border-top: 1px dashed #7f8c8d;">
		<h2>Ultimele informaţii de pe blogul Profitshare</h2><br />
		<?php
		$rss = new DOMDocument();
		$rss->load( 'http://blog.profitshare.ro/index.php/feed/' );
		$feed = array();
		foreach ( $rss->getElementsByTagName( 'item' ) as $node ) {
			$item = array ( 
				'title'	=> $node->getElementsByTagName( 'title' )->item(0)->nodeValue,
				'desc'	=> $node->getElementsByTagName( 'description' )->item(0)->nodeValue,
				'link'	=> $node->getElementsByTagName( 'link' )->item(0)->nodeValue,
			);
			array_push( $feed, $item );
		}
		$limit = 5;
		for( $x = 0; $x < $limit; $x++ ) {
			$title = str_replace( ' & ', ' &amp; ', $feed[ $x ]['title'] );
			$link = $feed[ $x ]['link'];
			$description = $feed[ $x ]['desc'];
			preg_match_all( "/<p>(.*?)<\/p>/s", $description, $result );
			echo '<p><strong><a href="' . $link . '" title="' . $title . '">' . $title . '</a></strong><br />';
			if ( isset( $result[0][0] ) )
				echo '<p>' . $result[0][0] . '</p><p></p>';
		}
		?>
		<hr style="border-top: 1px dashed #7f8c8d;">
		<h2>Întrebări frecvente</h2><br />
		<strong>Ce este Profitshare?</strong>
		<ul>
			<li>Simplu! Este o platformă de marketing afiliat, adică un instrument de marketing bazat pe performanță.</li>
		</ul>
		<strong>Ce este Advertiserul?</strong>
		<ul>
			<li>Advertiserul este cel care solicită promovarea produselor sau serviciilor sale.</li>
		</ul>
		<strong>Ce este Afiliatul?</strong>
		<ul>
			<li>Afiliatul este cel care face promovarea produselor Advertiserilor prin metodele de promovare agreate (de exemplu în website-urile sale). Prin promovare, afiliatul obține un comision din vânzările generate.</li>
		</ul>
		<strong>Cine este clientul?</strong>
		<ul>
			<li>Clientul este cel care ajunge pe website-ul Advertiserului prin intermediul promovării făcute de Afiliat și care îndeplinește o anumită acțiune. Acțiunea poate să fie o comandă, înscriere, abonare etc.</li>
			<li>Ca <strong>afiliat</strong> ești expus la o plajă mare de branduri, putând să alegi să promovezi dintre cele mai diverse servicii și produse. Pentru <strong>advertiseri</strong> cea mai mare oportunitate o reprezintă deschiderea către un sistem extins de ambasadori ai brandurilor, produselor și serviciilor din ofertă.</li>
		</ul>
		<strong>Ce este Profitshare pentru afiliați?</strong>
		<ul>
			<li>Pluginul <strong>Profitshare pentru afiliați</strong> este instrumentul prin intermediul căruia poți crește rata conversiilor și câștiga bani. <strong>Profitshare pentru afiliati</strong> îți pune la dispoziție istoricul conversiilor tale, posibilitatea de a genera linkuri profitshare manual sau automat atunci când publici un articol, poți urmări câștigul tău curent direct din interfața Wordpress sau poți chiar înlocui în articolele deja existente pe site-ul tău, toate linkurile advertiserilor cu unele generatoare de venituri, profitshare.</li>
		</ul>
	</div>
	<?php
}
?>