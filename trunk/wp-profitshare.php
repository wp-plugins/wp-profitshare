<?php
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
                $newText .= " href='".$link->link."' target='".$openin."'>".$link->keyword."<span><div class='ttfirst'></div><strong>".$link->tip_title."</strong><br />";
                if($link->tip_image!='') {
            ?>
            jQuery('p').each(function() {
function ps_add_menus() {
	/**
	add_menu_page( 'Profitshare', 'Profitshare', 'edit_others_posts', 'ps_account_settings', 'ps_account_settings', 'dashicons-chart-pie', 21 );
	$current_user = wp_get_current_user();
	if ( get_user_meta( $current_user->ID, 'ps_is_api_connected', true ) ) {
function ps_account_settings() {

	/**
	$current_user = wp_get_current_user();
	if ( isset( $_POST['disconnect'] ) ) {
	} else if ( isset( $_POST['connect'] ) ) {
		$api_user	=	esc_sql( $_POST['api_user'] );
		if ( ps_connection_check( $api_user, $api_key ) ) {

			/**
			ps_update_advertisers_db();
			echo '<meta http-equiv="refresh" content="0; url='. admin_url( 'admin.php?page=ps_account_settings&ps_status=true' ) .'">';

	$ps_api_user 		= get_user_meta( $current_user->ID, 'ps_api_user', true );

	if ( $is_api_connected ) {

	if ( $is_api_connected ) {

	<div class="wrap">
		<?php
		if ( $is_api_connected ) {
				<div id="message" class="updated"><p>Au fost scurtate şi convertite <?php echo $shorted_links; ?> linkuri în <strong>toate <?php echo $where; ?></strong> (<a href="<?php echo admin_url( 'admin.php?page=ps_history_links' ); ?>">Vezi lista</a>).</div>
		<?php
			if ( isset( $_POST['links_in_posts'] ) ) {
			if ( $auto_convert_comm )
			<h3>Link-uri profitshare în articole *</h3>
			<h3>Link-uri profitshare în comentarii *</h3>
	<?php
    global $wpdb;
	/**
	<div class="wrap">
                // ACTIONS
                if(!empty($_REQUEST['do'])) {
                    switch($_REQUEST['do']) {
                                if($do_query){
                                            $wpdb->update( 
                                                    array( 'ID' => $_POST['ID'] ),
                                                    array(
                                            $success[] = 'Cuvantul cheie a fost salvat.';
                                if(empty($errors)) {
                                                array( 
                                        $success[] = 'Cuvantul cheie a fost salvat.';
                            $show_table = false;
                            break;
                    }
                }
                if(!empty($success) && is_array($success)){
                if(!empty($errors) && is_array($errors)){
                // VIEWS
                            <?php
                /**
                if(!empty($show_table)) {
	 <?php
function ps_conversions() {
	?>
	<div class="wrap">
	$history_links = new History_Links();
	?>
	<div class="wrap">
	<?php
}
function ps_useful_info() {
	/**
	?>
	<div class="wrap">
		$rss = new DOMDocument();
		?>
		<hr style="border-top: 1px dashed #7f8c8d;">
		<h2>Întrebări frecvente</h2><br />
?>