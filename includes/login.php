<?php
/**
 * Redirect to Membership Account page for user login.
 *
 * @since 2.3
 *
 */
function pmpro_login_redirect( $redirect_to, $request = NULL, $user = NULL ) {
	global $wpdb;

	// Is a user logging in?
	if ( ! empty( $user ) && ! empty( $user->ID ) ) {
		// Logging in, let's figure out where to send them.
		if ( strpos( $redirect_to, "checkout" ) !== false ) {
			// If the redirect url includes the word checkout, leave it alone.
		} elseif ( $wpdb->get_var("SELECT membership_id FROM $wpdb->pmpro_memberships_users WHERE status = 'active' AND user_id = '" . $user->ID . "' LIMIT 1" ) ) {
			// If logged in and a member, send to wherever they were going.
		} else {
			// Not a member, send to subscription page.
			$redirect_to = pmpro_url( 'levels' );
		}
	}
	else {
		// Not logging in (login form) so return what was given.
	}

	return apply_filters( 'pmpro_login_redirect_url', $redirect_to, $request, $user );
}
add_filter( 'login_redirect','pmpro_login_redirect', 10, 3 );

//Where is the sign page? Levels page or default multisite page.
function pmpro_wp_signup_location($location)
{
	if(is_multisite() && pmpro_getOption("redirecttosubscription"))
	{
		return pmpro_url("levels");
	}
	else
		return $location;
}
add_filter('wp_signup_location', 'pmpro_wp_signup_location');

//redirect from default login pages to PMPro
function pmpro_login_head()
{
	global $pagenow;

	$login_redirect = apply_filters("pmpro_login_redirect", true);
	
	if((pmpro_is_login_page() || is_page("login") ||
		class_exists("Theme_My_Login") && method_exists('Theme_My_Login', 'is_tml_page') && (Theme_My_Login::is_tml_page("register") || Theme_My_Login::is_tml_page("login")) ||
		function_exists( 'tml_is_action' ) && ( tml_is_action( 'register' ) || tml_is_action( 'login' ) )
		)
		&& $login_redirect
	)
	{
		//redirect registration page to levels page
		if( isset($_REQUEST['action']) && $_REQUEST['action'] == "register" || 
			isset($_REQUEST['registration']) && $_REQUEST['registration'] == "disabled"	||
			!is_admin() && class_exists("Theme_My_Login") && method_exists('Theme_My_Login', 'is_tml_page') && Theme_My_Login::is_tml_page("register") ||
			function_exists( 'tml_is_action' ) && tml_is_action( 'register' )
		)
		{
			//redirect to levels page unless filter is set.
			$link = apply_filters("pmpro_register_redirect", pmpro_url("levels"));						
			if(!empty($link))
			{
				wp_redirect($link);
				exit;
			}
			else
				return;	//don't redirect if pmpro_register_redirect filter returns false or a blank URL
		}

		//if theme my login is installed, redirect all logins to the login page
		if(pmpro_is_plugin_active("theme-my-login/theme-my-login.php"))
		{
			//check for the login page id and redirect there if we're not there already
			global $post;
						
			if(!empty($GLOBALS['theme_my_login']) && is_array($GLOBALS['theme_my_login']->options))
			{
				//an older version of TML stores it this way
				if($GLOBALS['theme_my_login']->options['page_id'] !== $post->ID)
				{
					//redirect to the real login page
					$link = get_permalink($GLOBALS['theme_my_login']->options['page_id']);
					if($_SERVER['QUERY_STRING'])
						$link .= "?" . $_SERVER['QUERY_STRING'];
					wp_redirect($link);
					exit;
				}
			}
			elseif(!empty($GLOBALS['theme_my_login']->options))
			{
				//another older version of TML stores it this way
				if($GLOBALS['theme_my_login']->options->options['page_id'] !== $post->ID)
				{
					//redirect to the real login page
					$link = get_permalink($GLOBALS['theme_my_login']->options->options['page_id']);
					if($_SERVER['QUERY_STRING'])
						$link .= "?" . $_SERVER['QUERY_STRING'];
					wp_redirect($link);
					exit;
				}
			}
			elseif(class_exists("Theme_My_Login") && method_exists('Theme_My_Login', 'get_page_link') && method_exists('Theme_My_Login', 'is_tml_page'))
			{
				//TML > 6.3
				$link = Theme_My_Login::get_page_link("login");
				if(!empty($link))
				{
					//redirect if !is_page(), i.e. we're on wp-login.php
					if(!Theme_My_Login::is_tml_page())
					{
						wp_redirect($link);
						exit;
					}
				}				
			}
			elseif ( function_exists( 'tml_is_action' ) && function_exists( 'tml_get_action_url' ) && function_exists( 'tml_action_exists' ) )
			{
				$action = ! empty( $_REQUEST['action'] ) ? $_REQUEST['action'] : 'login';
				if ( tml_action_exists( $action ) ) {
					if ( 'wp-login.php' == $pagenow ) {
						$link = tml_get_action_url( $action );
						wp_redirect( $link );
						exit;
					}
				}
			}

			//make sure users are only getting to the profile when logged in
			global $current_user;
			if(!empty($_REQUEST['action']) && $_REQUEST['action'] == "profile" && !$current_user->ID)
			{
				$link = get_permalink($GLOBALS['theme_my_login']->options->options['page_id']);								
				wp_redirect($link);
				exit;
			}
		}
	}
}
add_action('wp', 'pmpro_login_head');
add_action('login_init', 'pmpro_login_head');

/*
	If a redirect_to value is passed into /login/ and you are logged in already, just redirect there
	
	@since 1.7.14
*/
function pmpro_redirect_to_logged_in()
{	
	if((pmpro_is_login_page() || is_page("login")) && !empty($_REQUEST['redirect_to']) && is_user_logged_in() && (empty($_REQUEST['action']) || $_REQUEST['action'] == 'login') && empty($_REQUEST['reauth']))
	{
		wp_safe_redirect($_REQUEST['redirect_to']);
		exit;
	}
}
add_action("template_redirect", "pmpro_redirect_to_logged_in", 5);
add_action("login_init", "pmpro_redirect_to_logged_in", 5);

/**
 * Redirect to the Membership Account page for member login.
 *
 * @since 2.3
 */
function pmpro_login_url( $login_url='', $redirect='' ) {
	$account_page_id = pmpro_getOption( 'account_page_id' );
    if ( ! empty ( $account_page_id ) ) {
        $login_url = get_permalink( $account_page_id );

        if ( ! empty( $redirect ) )
            $login_url = add_query_arg( 'redirect_to', urlencode( $redirect ), $login_url ) ;
    }
    return $login_url;
}
add_filter( 'login_url', 'pmpro_login_url', 50, 2 );

/**
 * Show a member login form or logged in member widget.
 *
 * @since 2.3
 *
 */
function pmpro_login_form( ) {

	// Set the message return string.
	$message = '';
	if ( ! empty( $_GET['action'] ) ) {
        if ( 'failed' == $_GET['action'] ) {
            $message = 'There was a problem with your username or password.';
        } elseif ( 'loggedout' == $_GET['action'] ) {
            $message = 'You are now logged out.';
        } elseif ( 'recovered' == $_GET['action'] ) {
            $message = 'Check your e-mail for the confirmation link.';
        }
	}
	
	// Get Errors from password reset.
	if ( ! empty( $_GET['errors'] ) ) {

		switch ( $_GET['errors'] ) {
			case 'invalidcombo':
				$message = __( 'There is no account with that username or email address.', 'paid-memberships-pro' );
				break;
			case 'empty_username':
				$message = __( 'Please enter a valid username.', 'paid-memberships-pro' );
				break;
			case 'invalid_email':
				$message = __( "You've entered an invalid email address.", 'paid-memberships-pro' );
				break;
		}
	}

	// Password reset email confirmation.
	if ( ! empty( $_GET['checkemail'] ) ) {
		if ( 'confirm' == $_GET['checkemail']) {
			$message = 'Check your email for a link to reset your password.';
		}
	}
	
	// Password errors
	if ( ! empty( $_GET['login'] ) ) {
		switch ($_GET['login']) {
			case 'invalidkey':
				$message = 'Invalid key';
				break;
			case 'expiredkey':
				$message = 'Expired Key';
				break;
		}
	}

	if ( ! empty( $_GET['password'] ) ) {
		if ( $_GET['password'] == 'changed' ) {
			$message = 'Your password has successfully been updated.';
		}
	}


    if ( $message ) {
		echo '<div class="pmpro_message pmpro_alert">'. $message .'</div>';
    }

    // Show the login form.
    if ( ! is_user_logged_in( ) && $_GET['action'] !== 'reset_pass' ) {
		if ( empty( $_GET['login'] ) || empty( $_GET['key'] ) ) {
			wp_login_form( );
			echo '<p><a href="' . add_query_arg( 'action', urlencode( 'reset_pass' ), $login_url )  . '"title="Recover Lost Password">Lost Password?</a>';
		} 
	}

	if ( ! is_user_logged_in() && $_GET['action'] === 'reset_pass' ) {
		pmpro_lost_password_form();
	}

	if ( is_user_logged_in() &&  isset( $_REQUEST['login'] ) && isset( $_REQUEST['key'] ) ) {
		_e( 'You are already signed in.', 'paid-memberships-pro' );
	}

	if ( ! is_user_logged_in() && isset( $_REQUEST['action'] ) == 'rp' ) {
		pmpro_reset_password_form();
	}

}

/**
 * Generate a lost password form for front-end login.
 * @since 2.3
 */
function pmpro_lost_password_form() {
	?>
	<h2><?php _e( 'Password Reset', 'paid-memberships-pro' ); ?></h2>
	 <p>
        <?php
            _e( "Enter your email address/username and we'll send you a link you can use to pick a new password.",        	'paid-memberships-pro' );
        ?>
    </p>
	 <form id="lostpasswordform" action="<?php echo wp_lostpassword_url(); ?>" method="post">
        <p class="form-row">
            <label for="user_login"><?php _e( 'Your email address or username', 'personalize-login' ); ?>
            <input type="text" name="user_login" id="user_login">
        </p>
 
        <p class="lostpassword-submit">
            <input type="submit" name="submit" class="lostpassword-button"
                   value="<?php _e( 'Reset Password', 'personalize-login' ); ?>"/>
        </p>
    </form>
	<?php
}

/**
 * Handle the password reset functionality. Redirect back to login form and show message.
 * @since 2.3
 */
function pmpro_lost_password_redirect() {
	if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
		$account_page = pmpro_getOption( 'account_page_id' );
		$redirect_url = $account_page ? get_permalink( $account_page ): '';

		$errors = retrieve_password();
		if ( is_wp_error( $errors ) ) {	
            $redirect_url = add_query_arg( array( 'errors' => join( ',', $errors->get_error_codes() ), 'action' => 'reset_pass' ), $redirect_url );
		} else {
			$redirect_url = add_query_arg( array( 'checkemail' => 'confirm' ), $redirect_url );
		}

		wp_redirect( $redirect_url );
		exit;
	}
}
add_action( 'login_form_lostpassword', 'pmpro_lost_password_redirect' );

/**
 * Handle the reset password too.
 * @since 2.3
 */
function pmpro_reset_password_redirect() {
	if ( 'GET' == $_SERVER['REQUEST_METHOD'] ) {
		$account_page = pmpro_getOption( 'account_page_id' );
		$redirect_url = $account_page ? get_permalink( $account_page ): '';
		$user = check_password_reset_key( $_REQUEST['key'], $_REQUEST['login'] );
        if ( ! $user || is_wp_error( $user ) ) {
            if ( $user && $user->get_error_code() === 'expired_key' ) {
				wp_redirect( add_query_arg( 'login', 'expiredkey', $redirect_url ) );
            } else {
                wp_redirect( add_query_arg( 'login', 'invalidkey', $redirect_url ));
            }
            exit;
        }
 
        $redirect_url = add_query_arg( array( 'login' => esc_attr( $_REQUEST['login'] ), 'action' => 'rp' ), $redirect_url );
        $redirect_url = add_query_arg( array( 'key' => esc_attr( $_REQUEST['key'] ), 'action' => 'rp' ), $redirect_url );
 
        wp_redirect( $redirect_url );
        exit;
	}
}
add_action( 'login_form_rp', 'pmpro_reset_password_redirect' );
add_action( 'login_form_resetpass', 'pmpro_reset_password_redirect' );

function pmpro_reset_password_form() {
	if ( isset( $_REQUEST['login'] ) && isset( $_REQUEST['key'] ) ) {

		// Error messages
		$errors = array();
		if ( isset( $_REQUEST['error'] ) ) {
			$error_codes = explode( ',', $_REQUEST['error'] );
		}
		
		?>
		<form name="resetpassform" id="resetpassform" action="<?php echo site_url( 'wp-login.php?action=resetpass' ); ?>" method="post" autocomplete="off">
       	 	<input type="hidden" id="user_login" name="rp_login" value="<?php echo esc_attr( $_REQUEST['login'] ); ?>" autocomplete="off" />
        	<input type="hidden" name="rp_key" value="<?php echo esc_attr( $_REQUEST['key'] ); ?>" />
 
        <p>
            <label for="pass1"><?php _e( 'New password', 'personalize-login' ) ?></label>
            <input type="password" name="pass1" id="pass1" class="input" size="20" value="" autocomplete="off" />
        </p>
        <p>
            <label for="pass2"><?php _e( 'Repeat new password', 'personalize-login' ) ?></label>
            <input type="password" name="pass2" id="pass2" class="input" size="20" value="" autocomplete="off" />
        </p>
         
        <p class="description"><?php echo wp_get_password_hint(); ?></p>
         
        <p class="resetpass-submit">
            <input type="submit" name="submit" id="resetpass-button"
                   class="button" value="<?php _e( 'Reset Password', 'personalize-login' ); ?>" />
        </p>
    </form>
	<?php
	}	
}

/**
 * 
 */
function pmpro_do_password_reset() {
    if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
        $rp_key = $_REQUEST['rp_key'];
		$rp_login = $_REQUEST['rp_login'];
		
		$account_page = pmpro_getOption( 'account_page_id' );
		$redirect_url = $account_page ? get_permalink( $account_page ): '';
		$user = check_password_reset_key( $rp_key, $rp_login );
 
        if ( ! $user || is_wp_error( $user ) ) {
            if ( $user && $user->get_error_code() === 'expired_key' ) {
				wp_redirect( add_query_arg( array( 'login' => 'expiredkey', 'action' => 'rp' ), $redirect_url ) );
            } else {
                wp_redirect( add_query_arg( array( 'login' => 'invalidkey', 'action' => 'rp' ), $redirect_url ) );
            }
            exit;
        }
 
        if ( isset( $_POST['pass1'] ) ) {
            if ( $_POST['pass1'] != $_POST['pass2'] ) {
                // Passwords don't match
                $redirect_url = add_query_arg( 'key', $rp_key, $redirect_url );
                $redirect_url = add_query_arg( 'login', $rp_login, $redirect_url );
                $redirect_url = add_query_arg( 'error', 'password_reset_mismatch', $redirect_url );
 
                wp_redirect( $redirect_url );
                exit;
            }
 
            if ( empty( $_POST['pass1'] ) ) {
                // Password is empty 
                $redirect_url = add_query_arg( 'key', $rp_key, $redirect_url );
                $redirect_url = add_query_arg( 'login', $rp_login, $redirect_url );
                $redirect_url = add_query_arg( 'error', 'password_reset_empty', $redirect_url );
 
                wp_redirect( $redirect_url );
                exit;
            }
 
            // Parameter checks OK, reset password
            reset_password( $user, $_POST['pass1'] );
            wp_redirect( add_query_arg( 'password', 'changed', $redirect_url ) );
        } else {
            _e( 'Invalid Request', 'paid-memberships-pro' );
        }
 
        exit;
    }
}
add_action( 'login_form_rp', 'pmpro_do_password_reset' );
add_action( 'login_form_resetpass', 'pmpro_do_password_reset' );


function replace_retrieve_password_message( $message, $key, $user_login, $user_data ) {
    // Create new message
    $msg  = __( 'Hello!', 'personalize-login' ) . "\r\n\r\n";
    $msg .= sprintf( __( 'You asked us to reset your password for your account using the email address %s.', 'personalize-login' ), $user_login ) . "\r\n\r\n";
    $msg .= __( "If this was a mistake, or you didn't ask for a password reset, just ignore this email and nothing will happen.", 'personalize-login' ) . "\r\n\r\n";
    $msg .= __( 'To reset your password, visit the following address:', 'personalize-login' ) . "\r\n\r\n";
    $msg .= site_url( "membership-account?action=rp&key=$key&login=" . rawurlencode( $user_login ), 'login' ) . "\r\n\r\n";
    $msg .= __( 'Thanks!', 'personalize-login' ) . "\r\n";
 
    return $msg;
}
// add_filter( 'retrieve_password_message', 'replace_retrieve_password_message', 10, 4 );
/**
 * Authenticate the frontend user login.
 *
 * @since 2.3
 *
 */
function pmpro_authenticate_username_password( $user, $username, $password ) {
	if ( is_a( $user, 'WP_User' ) ) {
		return $user;
	}

	if ( empty( $username ) || empty( $password ) ) {
		$error = new WP_Error();
		$user  = new WP_Error( 'authentication_failed', __('<strong>ERROR</strong>: Invalid username or incorrect password.' ) );

		return $error;
	}
}
add_filter( 'authenticate', 'pmpro_authenticate_username_password', 30, 3);

/**
 * Redirect failed login to referrer for frontend user login.
 *
 * @since 2.3
 *
 */
function pmpro_login_failed( $username ) {
	$referrer = wp_get_referer();

	if ( $referrer && ! strstr( $referrer, 'wp-login' ) && ! strstr( $referrer, 'wp-admin' ) ) {
		if ( empty( $_GET['loggedout'] ) ) {
			wp_redirect( add_query_arg( 'action', 'failed', pmpro_login_url() ) );
		} else {
			wp_redirect( add_query_arg('action', 'loggedout', pmpro_login_url()) );
		}
		exit;
	}
}
add_action( 'wp_login_failed', 'pmpro_login_failed', 10, 2 );
