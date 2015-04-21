<?php
/*
Plugin Name: WPFBLP - Facebook Login for Wordpress
Plugin URI: https://github.com/mucahityilmaz/wpfblp
Description: A Facebook Login plugin for Wordpress with FB PHP SDK v4.x
Version: 1.0
Author: mucahityilmaz
Author URI: http://www.mucahityilmaz.com.tr
*/

session_start();

require_once 'facebook/autoload.php';

use Facebook\FacebookSession;
use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookRequest;
use Facebook\FacebookResponse;
use Facebook\FacebookSDKException;
use Facebook\FacebookRequestException;
use Facebook\FacebookAuthorizationException;
use Facebook\GraphObject;
use Facebook\GraphUser;

class Wpfblp {

	function __construct() {
		add_action( 'admin_menu', array( $this, 'wpfblp_create_menu') );
		add_action( 'admin_init', array( $this, 'wpfblp_save_settings') );
		add_action( 'admin_notices', array( $this, 'wpfblp_notice_incomplete') );
		add_action( 'admin_notices', array( $this, 'wpfblp_notice_saved') );
		add_action( 'login_form', array( $this, 'wpfblp_fblogin') );
	}

	function wpfblp_create_menu () {
		add_options_page( 'WPFBLP Options', 'WPFBLP', 'manage_options', 'wpfblp', array( $this, 'wpfblp_options' ));
	}

	function wpfblp_options () {
		global $wpdb;
		$wpfblp_app_id = get_option('wpfblp_app_id');
		$wpfblp_app_secret = get_option('wpfblp_app_secret');
?>
		<div class="wrap">
			<h2>WPFBLP - Facebook Login for Wordpress</h2>
			<form name="wpfblp_form" method="post" action="" style="background-color:#fff;border:1px solid #e5e5e5;padding:15px;">
				<input type="hidden" name="wpfblp_save" value="wpfblp_save_settings" />
				<p>
					<label for="wpfblp_app_id">App ID:</label>
					<input type="text" name="wpfblp_app_id" value="<?php echo $wpfblp_app_id; ?>" />
				</p>
				<p>
					<label for="wpfblp_app_secret">App Secret:</label>
					<input type="text" name="wpfblp_app_secret" value="<?php echo $wpfblp_app_secret; ?>" />
				</p>
				<p>
					<input type="submit" name="wpfblp_submit" value="Save" />
				</p>
			</form>
		</div>
<?php
	}
	
	function wpfblp_save_settings(){
		if(isset($_POST['wpfblp_save']) && $_POST['wpfblp_save'] == "wpfblp_save_settings"){
			update_option( 'wpfblp_app_id', $_POST['wpfblp_app_id'] );
			update_option( 'wpfblp_app_secret', $_POST['wpfblp_app_secret'] );
		}
	}

	function wpfblp_notice_incomplete(){
		if(get_option('wpfblp_app_id')=='' || get_option('wpfblp_app_secret')==''){
?>
			<div class="error">
				<p>WPFBLP - At least one of App ID or App Secret is missing, please complete <a href="http://www.wpfblp.com/wp-admin/options-general.php?page=wpfblp">here</a>!</p>
			</div>
<?php
		}
	}

	function wpfblp_notice_saved(){
		if(isset($_POST['wpfblp_save']) && $_POST['wpfblp_save'] == "wpfblp_save_settings" && get_option('wpfblp_app_id')!='' && get_option('wpfblp_app_secret')!=''){
?>
			<div class="updated">
				<p>All saved. Have a nice day!</p>
			</div>
<?php
		}
	}

	function wpfblp_fblogin(){

		$is_redirected = ( isset( $_GET['from_fb'] ) && $_GET['from_fb']=='1' && isset( $_GET['code'] ) && $_GET['code']!='') ? true : false;

		FacebookSession::setDefaultApplication(get_option('wpfblp_app_id'), get_option('wpfblp_app_secret'));
		
		if($is_redirected){

			$helper = new FacebookRedirectLoginHelper(site_url().'/wp-login.php?from_fb=1');

			if(isset($_SESSION['fb_at'])) {
				$access_token = $_SESSION['fb_at'];
				$session = new FacebookSession($access_token);
			} else {
			    unset($_SESSION['fb_at']);
			    try {
			        $session = $helper->getSessionFromRedirect();
			        if($session){
			            $_SESSION['fb_at'] = $session->getToken();
			        }
			    } catch(FacebookRequestException $e) {
			        echo $e->xdebug_message;
			    } catch(Exception $e) {
			        echo $e->xdebug_message;
			    }
			}

			try {
				$request = new FacebookRequest($session, 'GET', '/me');
				$response = $request->execute();
				$graphObject = $response->getGraphObject(GraphUser::className());

				$email = $graphObject->getEmail();

				if( email_exists( $email )) {
					// logging an existing user in
					$user = get_user_by('email', $email );
					$user_id = $user->ID;
					wp_set_auth_cookie( $user_id, true );
				} else {
					// creating a new user
					$password = wp_generate_password();
					$user_id = wp_create_user( str_replace('@', '-', $email), $password, $email );
					wp_set_auth_cookie( $user_id, true );
				}

				wp_redirect( admin_url() );
				exit;

			} catch (FacebookRequestException $e) {
				echo $e->xdebug_message;
			} catch (\Exception $e) {
				echo $e->xdebug_message;
			}

		} else {

			$helper = new FacebookRedirectLoginHelper(site_url().'/wp-login.php?from_fb=1');

			$loginUrl = $helper->getLoginUrl(array('scope' => 'public_profile,email'));
			echo '<p style="font-size:18px;margin-bottom:5px;">or</p><p style="line-height:35px;"><a href="'.$loginUrl.'" style="background-color:blue;color:white;padding:3px 8px;margin:10px 0;font-weight:bold;font-size:20px;text-decoration:none;">Connect with Facebook</a></p><p>&nbsp;</p>';
		}

	}
}

new Wpfblp;