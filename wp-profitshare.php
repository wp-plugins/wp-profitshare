<?php
/**
 * Plugin Name: WP Profitshare
 * Plugin URI: http://www.profitshare.ro
 * Description: Plugin-ul converteste toate link-urile directe catre advertiseri existenti in Profitshare in link-uri care au paramentru de tracking pentru inregistrarea conversiilor aferente promovarii acestora. 
 * Version: 1.1
 * Author: Conversion
 * Author URI: http://www.conversion.ro
 * License: GPL2
 */

defined( 'ABSPATH' ) || exit;

define( 'PS_URL', 'http://api.profitshare.ro' );


require_once( 'includes/functions.php' );
require_once( 'includes/class-conversions.php' );
require_once( 'includes/class-history-links.php' );
require_once( 'includes/class-keywords-list.php' );


register_activation_hook( __FILE__, 'ps_init_settings' );
register_deactivation_hook( __FILE__, 'ps_remove_settings' );


add_action( 'admin_enqueue_scripts', 'ps_enqueue_admin_assets' );
add_action( 'wp_enqueue_scripts', 'ps_enqueue_assets' );
add_action( 'wp_footer', 'ps_footer_js', 1);
add_action( 'admin_menu', 'ps_add_menus' );
add_action( 'save_post', 'ps_auto_convert_posts' );
add_action( 'comment_post', 'ps_auto_convert_comments' );
add_filter( 'the_content', 'ps_filter_links' );
add_filter( 'comment_text', 'ps_filter_links' );


function ps_enqueue_admin_assets(){

    $screen = get_current_screen();
    wp_enqueue_style('profitshare-admin-style', plugins_url('css/admin.css', __FILE__), array());

    // add assets on certain page

    if(!empty($screen->id) && $screen->id == 'profitshare_page_ps_keywords_settings') {
        wp_enqueue_media();
	wp_enqueue_script('profitshare-admin-script', plugins_url('js/admin.js', __FILE__), array('jquery'));
    }
}

function ps_enqueue_assets(){

    wp_enqueue_style('profitshare-style', plugins_url('css/public.css', __FILE__), array());

    wp_enqueue_script('profitshare-script', plugins_url('js/public.js', __FILE__), array('jquery'));

}

function ps_footer_js(){

    global $wpdb;

    $table_name = $wpdb->prefix . "ps_keywords";

    $links = $wpdb->get_results("SELECT * FROM $table_name");

    if(!empty($links)) {

    ?>

        <script type='text/javascript'>

        jQuery().ready(function(e) {

        <?php 

        foreach($links as $link) {

            $openin = $link->openin == 'new' ? '_blank' : '_self';



            if($link->tip_display=='y') {
                $newText = $link->tip_style=='light' ? "<a class='pslinks ttlight'" : "<a class='pslinks ttdark'";

                $newText .= " href='".$link->link."' target='".$openin."'>".$link->keyword."<span><div class='ttfirst'></div><strong>".$link->tip_title."</strong><br />";

                if($link->tip_image!='') {
                    $newText .= "<img alt='CSS tooltip image' style='float:right; width:90px; margin:0 0 10px 10px;' src='".$link->tip_image."'>";
                }
                $newText .= $link->tip_description."<div class='ttlast'>WP Profitshare 1.1</div></span></a>";
            } else {
                $newText = "<a class='pslinks' href='".$link->link."' title='".$link->title."' target='".$openin."'>".$link->keyword."</a>";
            }

            ?>

            jQuery('p').each(function() {
                //  var strNewString = jQuery(this).html().replace(/(?!<a[^>]*>)(<?php echo $link->keyword; ?>)(?![^<]*<\/a>)/g,"<?php echo $newText; ?>");
                    var strNewString = jQuery(this).html().replace(/(<?php echo $link->keyword; ?>)(?![^<]*>|[^<>]*<\/)/gm,"<?php echo $newText; ?>");
                    jQuery(this).html(strNewString);
            });
        <?php }	?>
        });
        </script>
    <?php }	
}

function ps_add_menus() {

	/**
	 *	@since: 1.0
	 *	Creează meniul Profitshare în Dashboard
	 *	cu submeniurile: Setări plugin, Conversii, Generare în articole, Istoric linkuri
	 */

	add_menu_page( 'Profitshare', 'Profitshare', 'edit_others_posts', 'ps_account_settings', 'ps_account_settings', 'dashicons-chart-pie', 21 );
	add_submenu_page( 'ps_account_settings', 'Setări plugin', 'Setări plugin', 'manage_options', 'ps_account_settings', 'ps_account_settings' );

	$current_user = wp_get_current_user();

	if ( get_user_meta( $current_user->ID, 'ps_is_api_connected', true ) ) {
		add_submenu_page( 'ps_account_settings', 'Setări cuvinte cheie', 'Setări cuvinte cheie', 'manage_options', 'ps_keywords_settings', 'ps_keywords_settings' );
		add_submenu_page( 'ps_account_settings', 'Conversii afiliat', 'Conversii afiliat', 'manage_options', 'ps_conversions', 'ps_conversions' );
		add_submenu_page( 'ps_account_settings', 'Istoric linkuri', 'Istoric linkuri', 'manage_options', 'ps_history_links', 'ps_history_links' );
		add_submenu_page( 'ps_account_settings', 'Informații utile', 'Informații utile', 'manage_options', 'ps_useful_info', 'ps_useful_info' );
	}
}

function ps_account_settings() {


	/**
	 *	@since: 1.0
	 *	Pagina Setări API
	 *	Se setează datele de conectare la API şi se efectuează conectarea
	 */

	$current_user = wp_get_current_user();

	if ( isset( $_POST['disconnect'] ) ) {
		delete_user_meta( $current_user->ID, 'ps_api_user' ); 
		delete_user_meta( $current_user->ID, 'ps_api_key' );
		delete_user_meta( $current_user->ID, 'ps_is_api_connected' );
		delete_option( 'ps_last_advertisers_update' );
		delete_option( 'ps_last_conversions_update' );
		delete_option( 'ps_account_balance' );
		delete_option( 'ps_last_check_account_balance' );
		delete_option( 'auto_convert_posts' );
		delete_option( 'auto_convert_comments' );

	} else if ( isset( $_POST['connect'] ) ) {

		$api_user	=	esc_sql( $_POST['api_user'] );
		$api_key	=	esc_sql( $_POST['api_key'] );

		if ( ps_connection_check( $api_user, $api_key ) ) {


			/**
			 *	Se face cache la interval de:
			 *	24 de ore pentru advertiseri
			 *	6 ore pentru conversii
			 */

			ps_update_advertisers_db();
			ps_update_conversions();


			echo '<meta http-equiv="refresh" content="0; url='. admin_url( 'admin.php?page=ps_account_settings&ps_status=true' ) .'">';
		} else {
			 echo '<meta http-equiv="refresh" content="0; url='. admin_url( 'admin.php?page=ps_account_settings&ps_status=false' ) .'">';
		}
	}


	$ps_api_user 		= get_user_meta( $current_user->ID, 'ps_api_user', true );
	$ps_api_key 		= get_user_meta( $current_user->ID, 'ps_api_key', true );
	$is_api_connected	= get_user_meta( $current_user->ID, 'ps_is_api_connected', true );	


	if ( $is_api_connected ) {
		$button = '<input type="submit" name="disconnect" id="ps-red" value="Deconectare" />';
		$disabled = 'disabled="disabled"';
	} else {
		$button = '<input type="submit" name="connect" id="ps-green" value="Conectare" />';
		$disabled = '';
	}


	if ( $is_api_connected ) {
		echo '<div id="message" class="updated"><p>Conectat: Datele API sunt corecte, iar conexiunea este realizată cu succes.</p></div>';
	} else if( ! $is_api_connected ) {
		echo '<div id="message" class="error"><p>Deconectat: Datele API sunt incorecte sau au apărut erori de conectare.</p></div>';
	}
	?>


	<div class="wrap">
		<a href="http://profitshare.ro"><img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/profitshare-conversion.png" alt="Profitshare" /></a><br/>
		<hr style="border-top: 1px dashed #7f8c8d;">
		<form method="post" action="">
			<table class="form-table">
				<tr>
					<th><label for="api_user">API user</label></th>
					<td><input id="api_user" class="regular-text" type="text" name="api_user" value="<?php echo $ps_api_user . '"'; echo $disabled; ?> /></td>
				</tr>
				<tr>
					<th><label for="api_key">API key</label></th>
					<td><input id="api_key" class="regular-text" type="text" name="api_key" value="<?php echo $ps_api_key . '"'; echo $disabled; ?> /></td>
				</tr>
				<tr>
					<th><?php echo $button; ?></th>
				</tr>
			</table>
		</form>


		<?php

		if ( $is_api_connected ) {
			if ( isset( $_POST['generate_in_posts'] ) ) {
				$param = 'posts';
				$where = 'articolele';
			} else {
				$param = 'comments';
				$where = 'comentariile';
			}
			if ( isset( $_POST['generate_in_posts'] ) || isset( $_POST['generate_in_comments'] ) ) {
				$shorted_links = ps_replace_links( $param );
		?>

				<div id="message" class="updated"><p>Au fost scurtate şi convertite <?php echo $shorted_links; ?> linkuri în <strong>toate <?php echo $where; ?></strong> (<a href="<?php echo admin_url( 'admin.php?page=ps_history_links' ); ?>">Vezi lista</a>).</div>

		<?php
			}
			$auto_convert_posts	= get_option( 'auto_convert_posts' );
			$auto_convert_comm	= get_option( 'auto_convert_comments' );

			if ( isset( $_POST['links_in_posts'] ) ) {
				$auto_convert_posts ? $val = 0 : $val = 1;
				update_option( 'auto_convert_posts', $val );
			}
			if ( isset ( $_POST['links_in_comments'] ) ) {
				$auto_convert_comm ? $val = 0 : $val = 1;
				update_option( 'auto_convert_comments', $val );
			}
			$auto_convert_posts	= get_option( 'auto_convert_posts' );
			$auto_convert_comm	= get_option( 'auto_convert_comments' );
			if ( $auto_convert_posts )
				$form_post = array(
					'css_id'		=>	'ps-red',
					'input_value'	=>	'Dezactivează!',
				);
			else
				$form_post = array(
					'css_id'		=>	'ps-green',
					'input_value'	=>	'Activează!',
				);

			if ( $auto_convert_comm )
				$form_comment = array(
					'css_id'		=>	'ps-red',
					'input_value'	=>	'Dezactivează!',
				);
			else
				$form_comment = array(
					'css_id'		=>	'ps-green',
					'input_value'	=>	'Activează!',
				);
		?>

			<h3>Link-uri profitshare în articole *</h3>
			<form action="" method="post" style="display: inline;">
				<input type="submit" name="generate_in_posts" id="ps-green" value="Generează!" />
			</form>
			<form action="" method="post" style="display: inline;">
				<input type="submit" name="links_in_posts" id="<?php echo $form_post['css_id']; ?>" value="<?php echo $form_post['input_value']; ?>" />
			</form>
			

			<h3>Link-uri profitshare în comentarii *</h3>
			<form action="" method="post" style="display: inline;">
				<input type="submit" name="generate_in_comments" id="ps-green" value="Generează!" />
			</form>
			<form action="" method="post" style="display: inline;">
				<input type="submit" name="links_in_comments" id="<?php echo $form_comment['css_id']; ?>" value="<?php echo $form_comment['input_value']; ?>" />
			</form><br/><br/>			

			<span class="description">* Prin click pe "Generează!", în toate articolele/comentariile existente până în acest moment pe blog, toate link-urile din reţeaua Profitshare vor fi înlocuit cu un link, de forma http://profitshare.ro/l/123456.<br/>
			Prin click pe "Activează!", în toate articolele/comentariile ce vor fi postate de acum înainte, link-urile din rețeaua Profitshare vor fi înlocuite automat ca mai sus.<br/>
			<strong>Pentru a evita eventualele neplăceri, vă recomandăm să faceţi un back-up la baza de date înainte de a rula această acţiune.</strong></span>
			<?php
		}
		?>
	</div>

	<?php
}

function ps_keywords_settings() {

    global $wpdb;

	/**
	 *	@since: 1.1
	 *	Pagina Setări cuvinte cheie
	 */
	 ?>

	<div class="wrap">
		<a href="http://profitshare.ro"><img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/profitshare-conversion.png" alt="Profitshare" /></a><br/>
		<hr style="border-top: 1px dashed #7f8c8d;">
		<h2>
                    Setări cuvinte cheie 
                    <a href="<?php echo admin_url('admin.php?page=ps_keywords_settings&do=add'); ?>" style="background: #FFF; font-size: 12px; padding: 4px; text-decoration: none; border-radius: 3px;">Adauga cuvant cheie</a>
                    <?php if(!empty($_REQUEST['do']) && $_REQUEST['do'] != 'delete') { ?><a href="<?php echo admin_url('admin.php?page=ps_keywords_settings'); ?>" style="background: #FFF; font-size: 12px; padding: 4px; text-decoration: none; border-radius: 3px;">Afiseaza lista cuvinte cheie</a><?php } ?>
                </h2>
		<?php
                $show_table = true;
                $errors = array();
                $success = array();
               

                // ACTIONS

                if(!empty($_REQUEST['do'])) {
                    $table_name = $wpdb->prefix.'ps_keywords';                 

                    switch($_REQUEST['do']) {
                        case 'delete':
                            if(!empty($_REQUEST['keyword_id'])) {
                                $do_query = $wpdb->delete( $table_name, array( 'ID' => $_REQUEST['keyword_id'] ), array( '%d' ) );                               

                                if($do_query){
                                    $success[] = 'Cuvantul cheie a fost sters.';
                                }else{
                                    $errors[] = 'Cuvantul cheie nu a putut fi sters.';
                                }
                            }
                            break;                        
						case 'edit':
                            if(!empty($_REQUEST['keyword_id']) && !empty($_POST)) {
                                    if($_POST['keyword']=="") {
                                            $errors[] = "<strong>Cuvantul cheie</strong> este obligatoriu.";
                                    }
                                    if($_POST['title']=="") {
                                            $errors[] = "<strong>Titlul</strong> este obligatoriu.";
                                    }
                                    if($_POST['link']=="") {
                                            $errors[] = "<strong>Link-ul</strong> este obligatoriu.";
                                    }
                                    if($_POST['link'] != "" && !preg_match('#^https?://profitshare\.ro/l/#i', $_POST['link'])){
                                            $errors[] = "<strong>Link-ul</strong> trebuie sa inceapa cu profitshare.ro/l/";
                                    }
                                    if($_POST['openin']=="") {
                                            $errors[] = "<strong>Deschide link in...</strong> este obligatoriu.";
                                    }
                                    if($_POST['tip_display']=="1") {
                                            if($_POST['tip_title']=="") {
                                                    $errors[] = "<strong>Titlu tooltip</strong> este obligatoriu.";
                                            }
                                            if($_POST['tip_description']=="") {
                                                    $errors[] = "<strong>Descriere tooltip</strong> este obligatoriu.";
                                            }
                                    }

                                    if(empty($errors)) {
                                            if( !preg_match('#^https?://#', $_POST['link'])) {
                                                $_POST['link'] = 'https://' . $_POST['link'];
                                            }

                                            $wpdb->update( 
                                                    $table_name, 
                                                    array( 
                                                        'keyword' => $_POST['keyword'], 
                                                        'title' => $_POST['title'],
                                                        'link' => $_POST['link'],
                                                        'openin' => $_POST['openin'],
                                                        'tip_display' => $_POST['tip_display'],
                                                        'tip_style' => $_POST['tip_style'],
                                                        'tip_title' => $_POST['tip_title'],
                                                        'tip_description' => $_POST['tip_description'],
                                                        'tip_image' => $_POST['tip_image']
                                                    ), 

                                                    array( 'ID' => $_POST['ID'] ),
                                                    array( 
                                                        '%s', 
                                                        '%s',
                                                        '%s',
                                                        '%s',
                                                        '%s',
                                                        '%s',
                                                        '%s',
                                                        '%s',
                                                        '%s'
                                                    ),

                                                    array(
                                                        '%d'
                                                    )
                                            );

                                            $success[] = 'Cuvantul cheie a fost salvat.';
                                    }
                            }
                            $show_table = false;
                            break;
                        case 'add': 
                            if(!empty($_POST)){
                                if($_POST['keyword']=="") {
                                        $errors[] = "<strong>Cuvantul cheie</strong> este obligatoriu.";
                                }
                                if($_POST['title']=="") {
                                        $errors[] = "<strong>Titlul</strong> este obligatoriu.";
                                }
                                if($_POST['link']=="") {
                                        $errors[] = "<strong>Link-ul</strong> este obligatoriu.";
                                }
                                if($_POST['link'] != "" && !preg_match('#^https?://profitshare\.ro/l/#i', $_POST['link'])){
                                        $errors[] = "<strong>Link-ul</strong> trebuie sa inceapa cu profitshare.ro/l/";
                                }
                                if($_POST['openin']=="") {
                                        $errors[] = "<strong>Deschide link in...</strong> este obligatoriu.";
                                }
                                if($_POST['tip_display']=="1") {
                                        if($_POST['tip_title']=="") {
                                                $errors[] = "<strong>Titlu tooltip</strong> este obligatoriu.";
                                        }
                                        if($_POST['tip_description']=="") {
                                                $errors[] = "<strong>Descriere tooltip</strong> este obligatoriu.";
                                        }
                                }

                                if(empty($errors)) {
                                        if( !preg_match('#^https?://#', $_POST['link'])) {
                                            $_POST['link'] = 'https://' . $_POST['link'];
                                        }
                                    
                                        $wpdb->insert( 
                                                $table_name, 
                                                array( 
                                                    'keyword' => $_POST['keyword'], 
                                                    'title' => $_POST['title'],
                                                    'link' => $_POST['link'],
                                                    'openin' => $_POST['openin'],
                                                    'tip_display' => $_POST['tip_display'],
                                                    'tip_style' => $_POST['tip_style'],
                                                    'tip_title' => $_POST['tip_title'],
                                                    'tip_description' => $_POST['tip_description'],
                                                    'tip_image' => $_POST['tip_image']
                                                ), 

                                                array( 
                                                    '%s', 
                                                    '%s',
                                                    '%s',
                                                    '%s',
                                                    '%s',
                                                    '%s',
                                                    '%s',
                                                    '%s',
                                                    '%s'
                                                ) 
                                        );

                                        $success[] = 'Cuvantul cheie a fost salvat.';
                                }
                            }                        

                            $show_table = false;
                            break;
                    }
                }
           

                if(!empty($success) && is_array($success)){
                    ?>
                        <div id="message" class="updated fade">
                            <?php foreach($success as $msg) { ?>
                                <p><?php echo $msg; ?></p>
                            <?php } ?>
                        </div>
                    <?php
                }              

                if(!empty($errors) && is_array($errors)){
                    ?>
                        <div id="message" class="error fade">
                            <?php foreach($errors as $msg) { ?>
                                <p><?php echo $msg; ?></p>
                            <?php } ?>
                        </div>
                    <?php
                }
              

                // VIEWS
                if(!empty($_REQUEST['do'])) {
                    switch($_REQUEST['do']) {
                        case 'edit':
                            if(!empty($_REQUEST['keyword_id'])) {
                                $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE ID = %d", $_REQUEST['keyword_id']), ARRAY_A);
                            ?>
                                <form method="post">
                                    <table class="form-table">
                                        <tbody>
                                            <tr valign="top">
                                                <th scope="row"><label for="keyword">Cuvant cheie</label></th>
                                                <td>
                                                    <input class="large-text" type="text" name="keyword" value="<?php echo !empty($_POST['keyword']) ? $_POST['keyword'] : $row['keyword']; ?>" />
                                               </td>
                                            </tr>
                                            <tr valign="top">
                                                <th scope="row"><label for="title">Titlu</label></th>
                                                <td>
                                                    <input class="large-text" type="text" name="title" value="<?php echo !empty($_POST['title']) ? $_POST['title'] : $row['title']; ?>" />
                                               </td>
                                            </tr>
                                            <tr valign="top">
                                                <th scope="row"><label for="link">Link</label></th>
                                                <td>
                                                    <input class="large-text" type="text" name="link" value="<?php echo !empty($_POST['link']) ? $_POST['link'] : $row['link']; ?>" />
                                               </td>
                                            </tr>  
                                            <tr valign="top">
                                                <th scope="row"><label for="openin">Deschide link in</label></th>
                                                <td>
                                                    <select name="openin">
                                                        <option value="new"<?php echo (!empty($_POST['openin']) && $_POST['openin'] == 'new') || $row['openin'] == 'new' ? ' SELECTED' : ''; ?>>Fereastra noua</option>
                                                        <option value="current"<?php echo (!empty($_POST['openin']) && $_POST['openin'] == 'current') || $row['openin'] == 'current' ? ' SELECTED' : ''; ?>>Fereastra actuala</option>
                                                    </select>
                                               </td>
                                            </tr>  
                                            <tr valign="top">
                                                <th scope="row"><label for="tip_display">Tooltip</label></th>
                                                <td>
                                                    <label><input name="tip_display" id="tip_display" type="radio" value="y" onclick="toggleTips(1);"<?php echo (!empty($_POST['tip_display']) && $_POST['tip_display'] == 'y') || $row['tip_display'] == 'y' ? ' checked="checked"' : ''; ?>>Da</label>
                                                    <label><input name="tip_display" id="tip_display" type="radio" value="n" onclick="toggleTips(0);"<?php echo (!empty($_POST['tip_display']) && $_POST['tip_display'] == 'n') || $row['tip_display'] == 'n' ? ' checked="checked"' : ''; ?>>Nu</label>
                                               </td>
                                            </tr>    
                                            <tr valign="top" class="tip_display_1 hide_display">
                                                <th scope="row"><label for="tip_style">Design tooltip</label></th>
                                                <td>
                                                    <label><input name="tip_style" type="radio" value="light"<?php echo (!empty($_POST['tip_style']) && $_POST['tip_style'] == 'light') || $row['tip_style'] == 'light' ? ' checked="checked"' : ''; ?>>Light</label>
                                                    <label><input name="tip_style" type="radio" value="dark"<?php echo (!empty($_POST['tip_style']) && $_POST['tip_style'] == 'dark') || $row['tip_style'] == 'dark' ? ' checked="checked"' : ''; ?>>Dark</label>
                                               </td>
                                            </tr>   
                                            <tr valign="top" class="tip_display_1 hide_display">
                                                <th scope="row"><label for="tip_title">Titlu tooltip</label></th>
                                                <td>
                                                    <input class="large-text" type="text" name="tip_title" value="<?php echo !empty($_POST['tip_title']) ? $_POST['tip_title'] : $row['tip_title']; ?>" />
                                               </td>
                                            </tr>      
                                            <tr valign="top" class="tip_display_1 hide_display">
                                                <th scope="row"><label for="tip_description">Descriere tooltip</label></th>
                                                <td>
                                                    <input class="large-text" type="text" name="tip_description" value="<?php echo !empty($_POST['tip_description']) ? $_POST['tip_description'] : $row['tip_description']; ?>" />
                                               </td>
                                            </tr>  
                                            <tr valign="top" class="tip_display_1 hide_display">
                                                <th scope="row"><label for="tip_image">Imagine tooltip</label></th>
                                                <td>
                                                    <input class="upload_image_input" type="text" name="tip_image" value="<?php echo !empty($_POST['tip_image']) ? $_POST['tip_image'] : $row['tip_image']; ?>" id="upload_image_1" />
                                                    <input class="button-primary upload_image_button" data-id="1" type="button" value="Selecteaza Imaginea" />
                                               </td>
                                            </tr>                                          
                                            <tr valign="top">
                                                <th scope="row">
                                                    <input type="hidden" value="<?php echo $row['ID']; ?>" name="ID">
                                                    <?php submit_button(); ?>
                                                </th>
                                                <td></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </form>

								<?php 
                            }
                            break;
                        case 'add':
                            ?>
                            <form method="post">
                                <table class="form-table">
                                    <tbody>
                                        <tr valign="top">
                                            <th scope="row"><label for="keyword">Cuvant cheie</label></th>
                                            <td>
                                                <input class="large-text" type="text" name="keyword" value="<?php echo !empty($_POST['keyword']) ? $_POST['keyword'] : ''; ?>" />
                                           </td>
                                        </tr>
                                        <tr valign="top">
                                            <th scope="row"><label for="title">Titlu</label></th>
                                            <td>
                                                <input class="large-text" type="text" name="title" value="<?php echo !empty($_POST['title']) ? $_POST['title'] : ''; ?>" />
                                           </td>
                                        </tr>
                                        <tr valign="top">
                                            <th scope="row"><label for="link">Link</label></th>
                                            <td>
                                                <input class="large-text" type="text" name="link" value="<?php echo !empty($_POST['link']) ? $_POST['link'] : ''; ?>" />
                                           </td>
                                        </tr>  
                                        <tr valign="top">
                                            <th scope="row"><label for="openin">Deschide link in</label></th>
                                            <td>
                                                <select name="openin">
                                                    <option value="new"<?php echo !empty($_POST['openin']) && $_POST['openin'] == 'new' ? ' SELECTED' : ''; ?>>Fereastra noua</option>
                                                    <option value="current"<?php echo !empty($_POST['openin']) && $_POST['openin'] == 'current' ? ' SELECTED' : ''; ?>>Fereastra actuala</option>
                                                </select>
                                           </td>
                                        </tr>  
                                        <tr valign="top">
                                            <th scope="row"><label for="tip_display">Tooltip</label></th>
                                            <td>
                                                <label><input name="tip_display" id="tip_display" type="radio" value="y" onclick="toggleTips(1);"<?php echo !empty($_POST['tip_display']) && $_POST['tip_display'] == 'y' ? ' checked="checked"' : ''; ?>>Da</label>
                                                <label><input name="tip_display" id="tip_display" type="radio" value="n" onclick="toggleTips(0);"<?php echo !empty($_POST['tip_display']) && $_POST['tip_display'] == 'n' ? ' checked="checked"' : ''; ?><?php echo empty($_POST) ? ' checked="checked"' : ''; ?>>Nu</label>
                                           </td>
                                        </tr>    
                                        <tr valign="top" class="tip_display_1 hide_display">
                                            <th scope="row"><label for="tip_style">Design tooltip</label></th>
                                            <td>
                                                <label><input name="tip_style" type="radio" value="light"<?php echo !empty($_POST['tip_style']) && $_POST['tip_style'] == 'light' ? ' checked="checked"' : ''; ?>>Light</label>
                                                <label><input name="tip_style" type="radio" value="dark"<?php echo !empty($_POST['tip_style']) && $_POST['tip_style'] == 'dark' ? ' checked="checked"' : ''; ?><?php echo empty($_POST) ? ' checked="checked"' : ''; ?>>Dark</label>
                                           </td>
                                        </tr>   
                                        <tr valign="top" class="tip_display_1 hide_display">
                                            <th scope="row"><label for="tip_title">Titlu tooltip</label></th>
                                            <td>
                                                <input class="large-text" type="text" name="tip_title" value="<?php echo !empty($_POST['tip_title']) ? $_POST['tip_title'] : ''; ?>" />
                                           </td>
                                        </tr>      
                                        <tr valign="top" class="tip_display_1 hide_display">
                                            <th scope="row"><label for="tip_description">Descriere tooltip</label></th>
                                            <td>
                                                <input class="large-text" type="text" name="tip_description" value="<?php echo !empty($_POST['tip_description']) ? $_POST['tip_description'] : ''; ?>" />
                                           </td>
                                        </tr>  
                                        <tr valign="top" class="tip_display_1 hide_display">
                                            <th scope="row"><label for="tip_image">Imagine tooltip</label></th>
                                            <td>
                                                <input class="upload_image_input" type="text" name="tip_image" value="<?php echo !empty($_POST['tip_image']) ? $_POST['tip_image'] : ''; ?>" id="upload_image_1" />
                                                <input class="button-primary upload_image_button" data-id="1" type="button" value="Selecteaza Imaginea" />
                                           </td>
                                        </tr>                                          
                                        <tr valign="top">
                                            <th scope="row"><?php submit_button(); ?></th>
                                            <td></td>
                                        </tr>                                     
                                    </tbody>
                                </table>
                            </form>

                            <?php
                            break;
                    }
                }
				

                /**
                 * Arata tabel doar atunci cand e nevoie
                 */

                if(!empty($show_table)) {
                    $keywords = new Keywords_List();
                    $keywords->prepare_items();
                    $keywords->display();
                }
		?>		
	 </div>

	 <?php
}

function ps_conversions() {

	/**
	 *	@since: 1.0
	 *	Pagina Conversii
	 *	Afişează ultimele conversii preluate prin API
	 *	Se poate scurta un link de la unul dintre advertiserii din baza de date
	 */

	?>

	<div class="wrap">
		<a href="http://profitshare.ro"><img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/profitshare-conversion.png" alt="Profitshare" /></a><br/>
		<hr style="border-top: 1px dashed #7f8c8d;">
		<h2>Conversii</h2>
		<h3>Câştigul tău curent este în valoare de: <font style="color: #006D3E;"><?php echo ps_account_balance();?> RON</font></h3>
		<form method="post" action="">
			<table class="form-table">
				<tr>
					<td>
						<input type="text" name="link" placeholder="http://www.flanco.ro/" id="link" class="regular-text" />
						<input type="submit" name="submit_link" id="ps-green" value="Obţine link" /><br />
						<span class="description">Exemplu de link generat: http://profitshare.ro/l/123456</span>
					</td>
				</tr>
				<tr>
					<?php
					if ( isset( $_POST['submit_link'] ) ) {
						$link = esc_sql( $_POST['link'] );
						$ps_shorten_link = ps_shorten_link( 'WP Profitshare', $link );
						if ( ! $ps_shorten_link['result'] ) {
							?>
							<div id="message" class="error"><p>A apărut o eroare sau linkul nu face parte din reţeaua Profitshare şi nu poate fi generat.</p></div>
							<?php
						} else {
							?>
							<td><font style="color: #006D3E; font-weight: bold;">Link generat:</font><br/><input id="shorten_link" onClick="this.setSelectionRange(0, this.value.length)" class="regular-text" type="text" value="<?php echo $ps_shorten_link['shorted']; ?>" /></td>
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

function ps_history_links() {

	/**
	 *	@since: 1.0
	 *	Pagina Istoric linkuri
	 *	Afişează ultimele linkuri scurtat (manual sau automat)
	 */

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

function ps_useful_info() {


	/**
	 *	@since: 1.0
	 *	Pagina Informații utile
	 *	Conține rubrica F.A.Q.
	 *	Conține feed cu ultimele 2 articole din blog.profitshare.ro având excerpt 500 de caractere
	 */

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
		for( $i = 0; $i < $limit; $i++ ) {
			$title = str_replace( ' & ', ' &amp; ', $feed[ $i ]['title'] );
			$link = $feed[ $i ]['link'];
			$description = $feed[ $i ]['desc'];
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
			<li>Pluginul <strong>Profitshare pentru afiliați</strong> este instrumentul prin intermediul căruia poți crește rata conversiilor și câștiga bani. <strong>Profitshare pentru afiliați</strong> îți pune la dispoziție istoricul conversiilor tale, posibilitatea de a genera linkuri profitshare manual sau automat atunci când publici un articol, poți urmări câștigul tău curent direct din interfața Wordpress sau poți chiar înlocui în articolele deja existente pe site-ul tău, toate linkurile advertiserilor cu unele generatoare de venituri, profitshare.</li>
		</ul>
	</div>
	
	<?php
}

?>