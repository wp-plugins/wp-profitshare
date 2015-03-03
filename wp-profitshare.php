<?php
/**
 * Plugin Name: WP Profitshare
 * Plugin URI: http://www.profitshare.ro
 * Description: Converts all your direct links into affiliate links in order for you to earn commissions through Profitshare.
 * Version: 1.2
 * Author: Conversion
 * Author URI: http://www.conversion.ro
 * License: GPL2
 */

defined( 'ABSPATH' ) || exit;
define( 'PS_VERSION', '1.2' );

require_once( 'includes/functions.php' );
require_once( 'includes/class-conversions.php' );
require_once( 'includes/class-history-links.php' );
require_once( 'includes/class-keywords-list.php' );

register_activation_hook( __FILE__, 'ps_init_settings' );
register_deactivation_hook( __FILE__, 'ps_remove_settings' );

add_action('admin_init', 'ps_check_update');
add_action( 'admin_enqueue_scripts', 'ps_enqueue_admin_assets' );
add_action( 'wp_enqueue_scripts', 'ps_enqueue_assets' );
add_action( 'wp_footer', 'ps_footer_js', 1);
add_action( 'admin_menu', 'ps_add_menus' );
add_action( 'save_post', 'ps_auto_convert_posts' );
add_action( 'comment_post', 'ps_auto_convert_comments' );
add_filter( 'the_content', 'ps_filter_links' );
add_filter( 'comment_text', 'ps_filter_links' );

function ps_check_update() {
	$version = get_option('ps_installed_version', '0');
	if (!version_compare($version, PS_VERSION, '=')) {
		ps_init_settings();
	}
}

function ps_enqueue_admin_assets() {
    $screen = get_current_screen();
    wp_enqueue_style('profitshare-admin-style', plugins_url('css/admin.css', __FILE__), array());

    // add assets on certain page
    if(!empty($screen->id) && $screen->id == 'profitshare_page_ps_keywords_settings') {
		wp_enqueue_media();
		wp_enqueue_script('profitshare-admin-script', plugins_url('js/admin.js', __FILE__), array('jquery'));
    }
}

function ps_enqueue_assets() {
    wp_enqueue_style('profitshare-style', plugins_url('css/public.css', __FILE__), array());
    wp_enqueue_script('profitshare-script', plugins_url('js/public.js', __FILE__), array('jquery'));
}

function ps_footer_js() {
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
                $newText .= $link->tip_description."<div class='ttlast'>WP Profitshare 1.2</div></span></a>";
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
	 *	Creating Profitshare menu in Dashboard
	 *	With: Plugin Settings, Keyword Settings, Conversions, Link history, Istoric linkuri, Help
	 */
	add_menu_page( 'Profitshare', 'Profitshare', 'edit_others_posts', 'ps_account_settings', 'ps_account_settings', 'dashicons-chart-pie', 21 );
	add_submenu_page( 'ps_account_settings', 'Plugin Settings', 'Plugin Settings', 'manage_options', 'ps_account_settings', 'ps_account_settings' );
	$current_user = wp_get_current_user();
	if ( get_user_meta( $current_user->ID, 'ps_is_api_connected', true ) ) {
		add_submenu_page( 'ps_account_settings', 'Keyword Settings', 'Keyword Settings', 'manage_options', 'ps_keywords_settings', 'ps_keywords_settings' );
		add_submenu_page( 'ps_account_settings', 'Conversions', 'Conversions', 'manage_options', 'ps_conversions', 'ps_conversions' );
		add_submenu_page( 'ps_account_settings', 'Link history', 'Link history', 'manage_options', 'ps_history_links', 'ps_history_links' );
		add_submenu_page( 'ps_account_settings', 'Help', 'Help', 'manage_options', 'ps_useful_info', 'ps_useful_info' );
	}
}

function ps_account_settings() {
	/**
	 *	@since: 1.0
	 *	API Settings Page
	 *	Setting API connexion
	 */
	$current_user = wp_get_current_user();
	if ( isset( $_POST['disconnect'] ) ) {
		delete_user_meta( $current_user->ID, 'ps_api_user' ); 
		delete_user_meta( $current_user->ID, 'ps_api_key' );
		delete_user_meta( $current_user->ID, 'ps_api_country' );
		delete_user_meta( $current_user->ID, 'ps_is_api_connected' );
		delete_option( 'ps_last_advertisers_update' );
		delete_option( 'ps_last_conversions_update' );
		delete_option( 'ps_account_balance' );
		delete_option( 'ps_last_check_account_balance' );
		delete_option( 'auto_convert_posts' );
		delete_option( 'auto_convert_comments' );
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'ps_advertisers' );
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'ps_conversions' );
	} else if ( isset( $_POST['connect'] ) ) {
		$api_user	=	esc_sql( $_POST['api_user'] );
		$api_key	=	esc_sql( $_POST['api_key'] );
		$api_country=	esc_sql( $_POST['api_country'] );
		if ( ps_connection_check( $api_user, $api_key, $api_country ) ) {
			/**
			 *	Caching every:
			 *	24 h for advetisers
			 *	6 h for conversions
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
	$ps_api_country		= get_user_meta( $current_user->ID, 'ps_api_country', true );
	$is_api_connected	= get_user_meta( $current_user->ID, 'ps_is_api_connected', true );	
	if ( $is_api_connected ) {
		$button = '<input type="submit" name="disconnect" id="ps-red" value="Disconnect" />';
		$disabled = 'disabled="disabled"';
		$country = get_user_meta( $current_user->ID, 'ps_api_country', true );
	} else {
		$button = '<input type="submit" name="connect" id="ps-green" value="Connect" />';
		$disabled = '';
	}
	if ( $is_api_connected ) {
		echo '<div id="message" class="updated"><p>Connected: API data is correct and the connection has been successful established.</p></div>';
	} else if( ! $is_api_connected ) {
		echo '<div id="message" class="error"><p>Disconnected: API data is incorrect or connection error occurred.</p></div>';
	}
	?>
	<div class="wrap">
		<a href="<?php echo config( 'PS_HOME' ); ?>" target="_blank"><img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/profitshare-conversion.png" alt="Profitshare" /></a><br/>
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
					<th><label for="api_country">Country</label></th>
					<td><select id="api_country" name="api_country" <?php echo $disabled; ?>>
						<?php
						global $ps_api_config;
						foreach ( $ps_api_config AS $code => $array ) {
							if ( config( 'NAME' ) == $array['NAME'] ) {
								echo '<option value="' . $code . '" selected="selected">' . $array['NAME'] . '</option>';
							} else {
								echo '<option value="' . $code . '">' . $array['NAME'] . '</option>';
							}
						}
						?>
						</select>
						</td>
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
				$where = 'posts';
			} else {
				$param = 'comments';
				$where = 'comments';
			}
			if ( isset( $_POST['generate_in_posts'] ) || isset( $_POST['generate_in_comments'] ) ) {
				$shorted_links = ps_replace_links( $param );
		?>
				<div id="message" class="updated"><p>Have been shortened and converted <?php echo $shorted_links; ?> links in <strong>all <?php echo $where; ?></strong> (<a href="<?php echo admin_url( 'admin.php?page=ps_history_links' ); ?>">See list</a>).</div>
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
					'input_value'	=>	'Disable!',
				);
			else
				$form_post = array(
					'css_id'		=>	'ps-green',
					'input_value'	=>	'Enable!',
				);

			if ( $auto_convert_comm )
				$form_comment = array(
					'css_id'		=>	'ps-red',
					'input_value'	=>	'Disable!',
				);
			else
				$form_comment = array(
					'css_id'		=>	'ps-green',
					'input_value'	=>	'Enable!',
				);
		?>
			<h3>Profitshare links in posts *</h3>
			<form action="" method="post" style="display: inline;">
				<input type="submit" name="generate_in_posts" id="ps-green" value="Generate!" />
			</form>
			<form action="" method="post" style="display: inline;">
				<input type="submit" name="links_in_posts" id="<?php echo $form_post['css_id']; ?>" value="<?php echo $form_post['input_value']; ?>" />
			</form>
			<h3>Profitshare links in comments *</h3>
			<form action="" method="post" style="display: inline;">
				<input type="submit" name="generate_in_comments" id="ps-green" value="Generate!" />
			</form>
			<form action="" method="post" style="display: inline;">
				<input type="submit" name="links_in_comments" id="<?php echo $form_comment['css_id']; ?>" value="<?php echo $form_comment['input_value']; ?>" />
			</form><br/><br/>			
			<span class="description">* By clicking on "Generate!" all your direct links from all your posts/ comments published so far will be converted into affiliate links of this form  <?php echo config( 'PS_HOME' ); ?>/l/123456.<br/>
			By clicking on "Enable!" all the links from your future posts/ comments will be automatically converted into Profitshare affiliate links.<br/>
			<strong>We recommend making a backup of you database before running this functionality.</strong></span>
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
	 *	Keyword settings
	 */
	 ?>
	<div class="wrap">
		<a href="<?php echo config( 'PS_HOME' ); ?>"><img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/profitshare-conversion.png" alt="Profitshare" /></a><br/>
		<hr style="border-top: 1px dashed #7f8c8d;">
		<h2>Keyword Settings 
		<a href="<?php echo admin_url('admin.php?page=ps_keywords_settings&do=add'); ?>" style="background: #FFF; font-size: 12px; padding: 4px; text-decoration: none; border-radius: 3px;">Add keyword</a>
		<?php if(!empty($_REQUEST['do']) && $_REQUEST['do'] != 'delete') { ?><a href="<?php echo admin_url('admin.php?page=ps_keywords_settings'); ?>" style="background: #FFF; font-size: 12px; padding: 4px; text-decoration: none; border-radius: 3px;">Show keywords list</a><?php } ?>
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
                                    $success[] = 'Keyword deleted.';
                                }else{
                                    $errors[] = 'Keyword delete error.';
                                }
                            }
                            break;                        
						case 'edit':
                            if(!empty($_REQUEST['keyword_id']) && !empty($_POST)) {
                                    if($_POST['keyword']=="") {
                                            $errors[] = "<strong>Keyword</strong> required.";
                                    }
                                    if($_POST['title']=="") {
                                            $errors[] = "<strong>Title</strong> required.";
                                    }
                                    if($_POST['link']=="") {
                                            $errors[] = "<strong>Link </strong> required.";
                                    }
                                    if($_POST['link'] != "" && !preg_match('#^https?://profitshare\.ro/l/#i', $_POST['link']) && config( 'PS_HOME' ) == 'http://profitshare.ro'){
                                            $errors[] = "<strong>Your link</strong> must start with " . config( 'PS_HOME' ) . "/l/";
                                    }
									if($_POST['link'] != "" && !preg_match('#^https?://profitshare\.bg/l/#i', $_POST['link']) && config( 'PS_HOME' ) == 'http://profitshare.bg'){
                                            $errors[] = "<strong>Your link</strong> must start with " . config( 'PS_HOME' ) . "/l/";
                                    }
                                    if($_POST['openin']=="") {
                                            $errors[] = "<strong>Open link in...</strong> required.";
                                    }
                                    if($_POST['tip_display']=="1") {
                                            if($_POST['tip_title']=="") {
                                                    $errors[] = "<strong>Tooltip title</strong> required.";
                                            }
                                            if($_POST['tip_description']=="") {
                                                    $errors[] = "<strong>Tooltip description</strong> required.";
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
                                            $success[] = 'Keyword saved.';
                                    }
                            }
                            $show_table = false;
                            break;
                        case 'add': 
                            if(!empty($_POST)){
                                if($_POST['keyword']=="") {
                                        $errors[] = "<strong>Keyword</strong> required.";
                                }
                                if($_POST['title']=="") {
                                        $errors[] = "<strong>Title</strong> required.";
                                }
                                if($_POST['link']=="") {
                                        $errors[] = "<strong>Link</strong> required.";
                                }
								if($_POST['link'] != "" && !preg_match('#^https?://profitshare\.ro/l/#i', $_POST['link']) && config( 'PS_HOME' ) == 'http://profitshare.ro'){
                                    $errors[] = "<strong>Your link</strong> must start with " . config( 'PS_HOME' ) . "/l/";
                                }
								if($_POST['link'] != "" && !preg_match('#^https?://profitshare\.bg/l/#i', $_POST['link']) && config( 'PS_HOME' ) == 'http://profitshare.bg'){
                                    $errors[] = "<strong>Your link</strong> must start with " . config( 'PS_HOME' ) . "/l/";
                                }
                                if($_POST['openin']=="") {
                                        $errors[] = "<strong>Open link in...</strong> required.";
                                }
                                if($_POST['tip_display']=="1") {
                                        if($_POST['tip_title']=="") {
                                                $errors[] = "<strong>Tooltip title</strong> required.";
                                        }
                                        if($_POST['tip_description']=="") {
                                                $errors[] = "<strong>Tooltip description</strong> required.";
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
                                        $success[] = 'Keyword saved.';
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
                                                <th scope="row"><label for="keyword">Keyword</label></th>
                                                <td>
                                                    <input class="large-text" type="text" name="keyword" value="<?php echo !empty($_POST['keyword']) ? $_POST['keyword'] : $row['keyword']; ?>" />
                                               </td>
                                            </tr>
                                            <tr valign="top">
                                                <th scope="row"><label for="title">Title</label></th>
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
                                                <th scope="row"><label for="openin">Open link in</label></th>
                                                <td>
                                                    <select name="openin">
                                                        <option value="new"<?php echo (!empty($_POST['openin']) && $_POST['openin'] == 'new') || $row['openin'] == 'new' ? ' SELECTED' : ''; ?>>New window or tab (_blank) </option>
                                                        <option value="current"<?php echo (!empty($_POST['openin']) && $_POST['openin'] == 'current') || $row['openin'] == 'current' ? ' SELECTED' : ''; ?>>Same window or tab (_none)</option>
                                                    </select>
                                               </td>
                                            </tr>  
                                            <tr valign="top">
                                                <th scope="row"><label for="tip_display">Tooltip</label></th>
                                                <td>
                                                    <label><input name="tip_display" id="tip_display" type="radio" value="y" onclick="toggleTips(1);"<?php echo (!empty($_POST['tip_display']) && $_POST['tip_display'] == 'y') || $row['tip_display'] == 'y' ? ' checked="checked"' : ''; ?>>Yes</label>
                                                    <label><input name="tip_display" id="tip_display" type="radio" value="n" onclick="toggleTips(0);"<?php echo (!empty($_POST['tip_display']) && $_POST['tip_display'] == 'n') || $row['tip_display'] == 'n' ? ' checked="checked"' : ''; ?>>No</label>
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
                                                <th scope="row"><label for="tip_title">Tooltip title</label></th>
                                                <td>
                                                    <input class="large-text" type="text" name="tip_title" value="<?php echo !empty($_POST['tip_title']) ? $_POST['tip_title'] : $row['tip_title']; ?>" />
                                               </td>
                                            </tr>      
                                            <tr valign="top" class="tip_display_1 hide_display">
                                                <th scope="row"><label for="tip_description">Tooltip description</label></th>
                                                <td>
                                                    <input class="large-text" type="text" name="tip_description" value="<?php echo !empty($_POST['tip_description']) ? $_POST['tip_description'] : $row['tip_description']; ?>" />
                                               </td>
                                            </tr>  
                                            <tr valign="top" class="tip_display_1 hide_display">
                                                <th scope="row"><label for="tip_image">Tooltip image</label></th>
                                                <td>
                                                    <input class="upload_image_input" type="text" name="tip_image" value="<?php echo !empty($_POST['tip_image']) ? $_POST['tip_image'] : $row['tip_image']; ?>" id="upload_image_1" />
                                                    <input class="button-primary upload_image_button" data-id="1" type="button" value="Select Image" />
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
                                            <th scope="row"><label for="keyword">Keyword</label></th>
                                            <td>
                                                <input class="large-text" type="text" name="keyword" value="<?php echo !empty($_POST['keyword']) ? $_POST['keyword'] : ''; ?>" />
                                           </td>
                                        </tr>
                                        <tr valign="top">
                                            <th scope="row"><label for="title">Title</label></th>
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
                                            <th scope="row"><label for="openin">Open link in</label></th>
                                            <td>
                                                <select name="openin">
                                                    <option value="new"<?php echo !empty($_POST['openin']) && $_POST['openin'] == 'new' ? ' SELECTED' : ''; ?>>New window or tab (_blank)</option>
                                                    <option value="current"<?php echo !empty($_POST['openin']) && $_POST['openin'] == 'current' ? ' SELECTED' : ''; ?>>Same window or tab (_none)</option>
                                                </select>
                                           </td>
                                        </tr>  
                                        <tr valign="top">
                                            <th scope="row"><label for="tip_display">Tooltip</label></th>
                                            <td>
                                                <label><input name="tip_display" id="tip_display" type="radio" value="y" onclick="toggleTips(1);"<?php echo !empty($_POST['tip_display']) && $_POST['tip_display'] == 'y' ? ' checked="checked"' : ''; ?>>Yes</label>
                                                <label><input name="tip_display" id="tip_display" type="radio" value="n" onclick="toggleTips(0);"<?php echo !empty($_POST['tip_display']) && $_POST['tip_display'] == 'n' ? ' checked="checked"' : ''; ?><?php echo empty($_POST) ? ' checked="checked"' : ''; ?>>No</label>
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
                                            <th scope="row"><label for="tip_title">Tooltip title</label></th>
                                            <td>
                                                <input class="large-text" type="text" name="tip_title" value="<?php echo !empty($_POST['tip_title']) ? $_POST['tip_title'] : ''; ?>" />
                                           </td>
                                        </tr>      
                                        <tr valign="top" class="tip_display_1 hide_display">
                                            <th scope="row"><label for="tip_description">Tooltip description</label></th>
                                            <td>
                                                <input class="large-text" type="text" name="tip_description" value="<?php echo !empty($_POST['tip_description']) ? $_POST['tip_description'] : ''; ?>" />
                                           </td>
                                        </tr>  
                                        <tr valign="top" class="tip_display_1 hide_display">
                                            <th scope="row"><label for="tip_image">Tooltip image</label></th>
                                            <td>
                                                <input class="upload_image_input" type="text" name="tip_image" value="<?php echo !empty($_POST['tip_image']) ? $_POST['tip_image'] : ''; ?>" id="upload_image_1" />
                                                <input class="button-primary upload_image_button" data-id="1" type="button" value="Select Image" />
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
                 * Show table only when needed
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
		<a href="<?php echo config( 'PS_HOME' ); ?>"><img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/profitshare-conversion.png" alt="Profitshare" /></a><br/>
		<hr style="border-top: 1px dashed #7f8c8d;">
		<h2>Conversions</h2>
		<h3>Your Current earnings are: <font style="color: #006D3E;"><?php echo ps_account_balance();?></font></h3>
		<form method="post" action="">
			<table class="form-table">
				<tr>
					<td>
						<input type="text" name="link" placeholder="http://" id="link" class="regular-text" />
						<input type="submit" name="submit_link" id="ps-green" value="Get link" /><br />
						<span class="description">Example of generated link: <?php echo config( 'PS_HOME' ); ?>/l/123456</span>
					</td>
				</tr>
				<tr>
					<?php
					if ( isset( $_POST['submit_link'] ) ) {
						$link = esc_sql( $_POST['link'] );
						$ps_shorten_link = ps_shorten_link( 'WP Profitshare', $link );
						if ( ! $ps_shorten_link['result'] ) {
							?>
							<div id="message" class="error"><p>An error occurred or link is not part of Profitshare and can not be generated .</p></div>
							<?php
						} else {
							?>
							<td><font style="color: #006D3E; font-weight: bold;">Generted Link:</font><br/><input id="shorten_link" onClick="this.setSelectionRange(0, this.value.length)" class="regular-text" type="text" value="<?php echo $ps_shorten_link['shorted']; ?>" /></td>
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
	 *	Link history Page
	 *	Show latest generated links
	 */
	$history_links = new History_Links();
    $history_links->prepare_items();
	?>
	<div class="wrap">
		<a href="<?php echo config( 'PS_HOME' ); ?>"><img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/profitshare-conversion.png" alt="Profitshare" /></a><br/>
		<hr style="border-top: 1px dashed #7f8c8d;">
		<h2>Link history</h2>
		<?php $history_links->display(); ?>
	</div>
	<?php
}

function ps_useful_info() {
	/**
	 *	@since: 1.0
	 *	Help page
	 *	Contains F.A.Q.
	 */
	?>
	<div class="wrap">
		<a href="<?php echo config( 'PS_HOME' ); ?>"><img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/profitshare-conversion.png" alt="Profitshare" /></a><br/>
		
		<hr style="border-top: 1px dashed #7f8c8d;">
		<h2>Frequently Asked Questions</h2><br />
		<strong>What is Profitshare?</strong>
		<ul>
			<li>Profitshare is an affiliate marketing network, that is, a performance driven marketing tool.</li>
		</ul>
		<strong>What is an advertiser?</strong>
		<ul>
			<li>The advertiser is the one that wants to have its products or services advertised on the internet in order to obtain clients and increase its revenue.</li>
		</ul>
		<strong>What is an affiliate?</strong>
		<ul>
			<li>The affiliate is the one that advertises products and services offered by advertisers, through agreed methods, and earns a percentage of each sale that is made as a result of this. </li>
		</ul>
		<strong>Who is the client?</strong>
		<ul>
			<li>The client is the one that reaches advertisers' websites thanks to affiliate promotion. This client may then perform a pre-established action on the advertiser's website like: making a purchase, subscribing to a newsletter, signing up for an account etc.</li>
			<li>As <strong>an affiliate</strong> you gain access to a wide range of brands and varied products and services that can satisfy any kind of advertising projects, using efficient affiliate marketing tools. As <strong>an advertiser</strong>, the biggest advantage is acquiring an extensive community of ambassadors for your products or services from the affiliate marketing network</li>
		</ul>
		<strong>What is Profitshare for affiliates?</strong>
		<ul>
			<li>The <strong>Profitshare affiliate plugin</strong> is a tool that helps you grow your conversion rate and gain money online. Profitshare for affiliates lets you see your conversion history, facilitates generating affiliate links automatically or manually whenever you publish a new post. You can also watch your current earnings directly from your WordPress interface and you can replace al your existing links with affiliate links so that you can earn money with all your previous posts.</li>
		</ul>
	</div>
	<?php
}
?>