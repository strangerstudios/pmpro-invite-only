<?php
/**
 * Plugin Name: Paid Memberships Pro - Invite Only Membership Add On
 * Plugin URI: https://www.paidmembershipspro.com/add-ons/pmpro-invite-only-membership/
 * Description: Require an invite code to sign up for the specified Membership Levels (works for free or paid levels).
 * Version: 0.4
 * Author: Paid Memberships Pro
 * Author URI: https://www.paidmembershipspro.com
 * Text Domain: pmpro-invite-only
 * Domain Path: /languages
 * License: GPL-3.0
 */

/*
	Set an array with the level ids which should require invite codes and generate them.

	e.g.
	global $pmproio_invite_required_levels;
	$pmproio_invite_required_levels = array(1,2,3);

	The above is the only required global setting to get pmpro-invite-only working. Below are some optional settings.

	Should only specific levels be given invite codes to share?

	global $pmproio_invite_given_levels;
	$pmproio_invite_given_levels = array(1);	//defaults to $pmproio_invite_required_levels
	
	Set the number of invite codes to be created at checkout (and maximum a user can get without admin intervention)
	define('PMPROIO_CODES', 10);	//defaults to 1

	Set the number of times each code can be used.
	define('PMPROIO_CODES_USES', 1);	//defaults to unlimited
*/

//Set default values.
function pmproio_init_defaults()
{
	global $pmproio_invite_levels, $pmproio_invite_required_levels, $pmproio_invite_given_levels;

	//which levels require invites?
	if(isset($pmproio_invite_levels) && !isset($pmproio_invite_required_levels))
		$pmproio_invite_required_levels = $pmproio_invite_levels;

	//which levels are given invite codes?
	if(!isset($pmproio_invite_given_levels))
		$pmproio_invite_given_levels = $pmproio_invite_required_levels;

	//how many codes to give?
	if(!defined('PMPROIO_CODES'))
		define('PMPROIO_CODES', 1);

	//how many times can a code be used
	if(!defined('PMPROIO_CODES_USES'))
		define('PMPROIO_CODES_USES', false);
}
add_action('init', 'pmproio_init_defaults', 99);

/**
 * Load plugin textdomain.
 */
function pmproio_load_plugin_text_domain() {
	load_plugin_textdomain( 'pmpro-invite-only', false, basename( dirname( __FILE__ ) ) . '/languages' ); 
}
add_action( 'plugins_loaded', 'pmproio_load_plugin_text_domain');

//check if a level id requires an invite code or should generate one
function pmproio_isInviteLevel($level_id)
{
	global $pmproio_invite_required_levels;
    if(empty($pmproio_invite_required_levels))
        $pmproio_invite_required_levels = array();
	return in_array($level_id, $pmproio_invite_required_levels);
}

//check if a level id requires an invite code or should generate one
function pmproio_isInviteGivenLevel($level_id)
{
	global $pmproio_invite_given_levels;
    if(empty($pmproio_invite_given_levels))
        $pmproio_invite_given_levels = array();
	return in_array($level_id, $pmproio_invite_given_levels);
}

//get invite codes
function pmproio_getInviteCodes($user_id = null, $sort_codes = false)
{
    global $current_user, $wpdb;

    if(empty($user_id))
        $user_id = $current_user->id;

    //return if we still don't have a user id
    if(empty($user_id))
        return false;

    $codes = get_user_meta($user_id, 'pmpro_invite_code', true);

    //no codes
	if(empty($codes))
		return false;

    //return unsorted codes unless specified otherwise
    if(!$sort_codes)
        return $codes;

    //sort codes
    $unused_codes = array();
    $used_codes = array();
	$code_count = array();

	//figure out used codes
	foreach($codes as $code)
    {
        $user_ids = $wpdb->get_col("SELECT user_id FROM " . $wpdb->usermeta . " WHERE meta_key LIKE 'pmpro_invite_code_at_signup' AND meta_value LIKE '" . esc_sql( $code ) . "'");
	    if(!empty($user_ids)) {

		    $used_codes[$code] = $user_ids;

		    //add to code count
		    $code_count[$code] = count($user_ids);
	    }
    }

	//figure out unused codes
	$unused_codes = $codes;
	if(PMPROIO_CODES_USES != false)
	{
		foreach($unused_codes as $key => $code)
		{
			if(!empty($code_count[$code]) && $code_count[$code] >= PMPROIO_CODES_USES)
				unset($unused_codes[$key]);
		}
	}

    //add used codes to array
    $codes = array('unused' => $unused_codes, 'used' => $used_codes);

    return $codes;
}

//save invite codes
function pmproio_saveInviteCodes($new_codes, $user_id = null)
{
	global $current_user;

    if(empty($user_id))
        $user_id = $current_user->ID;

    //only continue if we have a user id and new codes
    if(empty($user_id) || empty($new_codes))
        return false;

    $old_codes = pmproio_getInviteCodes($user_id);
    if(empty($old_codes))
        $old_codes = array();

    $codes = array_merge($old_codes, $new_codes);

    //update user meta
    if(update_user_meta($user_id, 'pmpro_invite_code', $codes))
        return true;
    else
        return false;
}

//create new invite codes
function pmproio_createInviteCodes($user_id = null, $admin_override = false, $admin_quantity = 0)
{
    global $current_user;

    if(empty($user_id))
        $user_id = $current_user->ID;

    //only continue if we have a user id
    if(empty($user_id))
        return false;

    //get old codes
    $old_codes = pmproio_getInviteCodes($user_id);
    if(empty($old_codes))
        $old_codes = array();
    $new_codes = array();

    //use constant or default to 1 code if not set

	if($admin_override && current_user_can("manage_options"))
	{
		$quantity = $admin_quantity;
	}

	else	if(defined('PMPROIO_CODES'))
		$quantity = PMPROIO_CODES;
	else
		$quantity = 1;

	//how many do we need to make?
	$quantity = $quantity - count($old_codes);


	if($quantity > 0)
	{
		for($i=0; $i<$quantity; $i++)
		{
			//user_id part of code for easy searching later
			$id_part = str_pad(dechex($user_id), 4, '0', STR_PAD_LEFT);

			//random scramble part
			$scramble = strtoupper(md5(AUTH_KEY . $user_id . time() . SECURE_AUTH_KEY));

			$code = substr($id_part . $scramble, 0, 14);

			//if code is a duplicate or number, try again
			if(in_array($code, $old_codes) || in_array($code, $new_codes) || is_numeric($code)) {
				$i--;
				continue;
			}

			//code is ok, add it to array
			$new_codes[] = $code;
		}
	}

	return $new_codes;
}

//check if an invite code is valid
function pmproio_checkInviteCode($invite_code)
{
    global $wpdb;

	//check for user from code
    $user_id = pmproio_getUserFromInviteCode($invite_code);

	//no user? not a real code
	if(empty($user_id))
		return false;

    //only valid if user still has membership, but allow filter
    if(pmpro_hasMembershipLevel(0, $user_id) && apply_filters('pmproio_check_user', true))
        return false;

    //search for code
    $user_codes = pmproio_getInviteCodes($user_id);
    if(!in_array($invite_code, $user_codes))
        return false;

    //has code already been used?
    if(PMPROIO_CODES_USES)
	{
		$used = $wpdb->get_var("SELECT COUNT(user_id) FROM " . $wpdb->usermeta . " WHERE meta_key LIKE 'pmpro_invite_code_at_signup' AND meta_value LIKE '" . esc_sql($invite_code) . "'");

		//valid if we didn't hit use limit yet
		if(!empty($used) && $used >= PMPROIO_CODES_USES)
			return false;
	}

	//got here, must be valid
    return true;
}

//get user id from invite code
function pmproio_getUserFromInviteCode($invite_code)
{
    $user_id = hexdec(substr($invite_code, 0, 4));
    return $user_id;
}

//display used/unused invite codes
function pmproio_displayInviteCodes($user_id = null, $unused = true, $used = false)
{
    global $current_user;

    if(empty($user_id))
        $user_id = $current_user->ID;

    $codes = pmproio_getInviteCodes($user_id, true);

    if(empty($codes))
        return false;

    //start output buffering
    ob_start();

    if(!empty($unused))
    {
		if(empty($codes['unused']))
			esc_html__('All codes have been used.','pmpro-invite-only');
		else
		{
			?>
			<textarea class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input pmpro_form_input-textarea pmproio_unused_codes', 'pmproio_unused_codes' ) ); ?>" rows="3" readonly><?php
				echo esc_html( implode( ', ', $codes['unused'] ) );
			?></textarea>
			<?php
		}
    }

    if(!empty($used))
    {
		//figure out if codes have been used
		if(!empty($codes['used']))
		{
			foreach($codes['used'] as $code => $user_ids)
				if(!empty($user_ids))
				{
					$codes_used = true;
					break;
				}
		}

		if(empty($codes_used))
		{
			echo '<p>' . esc_html__('None of your codes have been used.','pmpro-invite-only') . '</p>';
		}
		else
		{
			?>
            <table class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_table used_codes' ) ); ?>">
                <thead>
					<tr>
						<th><?php esc_html_e('Member','pmpro-invite-only'); ?></th>
						<th><?php esc_html_e('Invite Code','pmpro-invite-only'); ?></th>
					</tr>
                </thead>
                <tbody>
                <?php
					foreach($codes['used'] as $code => $user_ids)
					{
						foreach($user_ids as $user_id)
						{
							$display_name = get_userdata($user_id)->display_name;
							if(empty($display_name))
							{
								$display_name = esc_html__( 'N/A (Deleted or abandoned.', 'pmpro-invite-only' );
							}
							else
							{
								if(current_user_can('manage_options'))
									$userlink = "<a href=" . esc_url( add_query_arg('user_id', $user_id, admin_url('user-edit.php') ) ) . ">" . esc_html( $display_name ) . "</a>";
							}
							?>
							<tr>
								<th scope="row">
									<?php
									if(!empty($userlink))
										echo $userlink;
									else
										echo $display_name;
									?>
								</th>
								<td><?php echo $code; ?></td>
							</tr><?php
						}
					}
				?>
                </tbody>
            </table><?php
		}
    }

    //save html
    $html = ob_get_contents();
    ob_end_clean();

    return $html;
}

/*
	Add an invite code field to checkout
*/
function pmproio_pmpro_checkout_boxes()
{
	global $pmpro_level, $current_user, $pmpro_review;
	if(pmproio_isInviteLevel($pmpro_level->id))
	{
		if(!empty($_REQUEST['invite_code']))
			$invite_code = $_REQUEST['invite_code'];
		elseif(!empty($_SESSION['invite_code']))
			$invite_code = $_SESSION['invite_code'];
		elseif(is_user_logged_in())
			$invite_code = $current_user->pmpro_invite_code_at_signup;
		else
			$invite_code = "";

		if( empty( $pmpro_review ) ) {
		?>
		<fieldset id="pmpro_invite_only_fields" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_fieldset', 'pmpro_invite_only_fields' ) ); ?>">
			<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card' ) ); ?>">
				<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ); ?>">
					<legend class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_legend' ) ); ?>">
						<h2 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_heading pmpro_font-large' ) ); ?>">
							<?php esc_html_e( 'Invite Code','pmpro-invite-only' ); ?>
						</h2>
					</legend>
					<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_fields' ) ); ?>">
						<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field pmpro_form_field-text pmpro_form_field-required' ) ); ?>">
							<label for="invite_code" class=<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label' ) ); ?>><?php esc_html_e( 'Invite Code', 'pmpro-invite-only' ); ?></label>
							<input id="invite_code" name="invite_code" type="text" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input pmpro_form_input-text pmpro_form_input-required' ) ); ?>" value="<?php echo esc_attr( $invite_code ); ?>" />
						</div> <!-- end pmpro_form_field -->
					</div> <!-- end pmpro_form_fields -->
				</div> <!-- end pmpro_card_content -->
			</div> <!-- end pmpro_card -->
		</fieldset> <!-- end pmpro_invite_only_fields -->
		<?php
		}
	}
}
add_action('pmpro_checkout_boxes', 'pmproio_pmpro_checkout_boxes');

/*
	Require the invite code
*/
function pmproio_pmpro_registration_checks($okay)
{
	global $pmpro_level, $pmproio_invite_required_levels;
	if(pmproio_isInviteLevel($pmpro_level->id))
	{
		global $pmpro_msg, $pmpro_msgt, $pmpro_error_fields, $wpdb;

		//get invite code
		$invite_code = $_REQUEST['invite_code'];

        $real = pmproio_checkInviteCode($invite_code);

		if(empty($invite_code) || empty($real))
		{
			pmpro_setMessage( esc_html__( 'An invite code is required for this level. Please enter a valid invite code.', 'pmpro-invite-only' ), 'pmpro_error' );
			$pmpro_error_fields[] = "invite_code";
		}
	}

	return $okay;
}
add_filter("pmpro_registration_checks", "pmproio_pmpro_registration_checks");

/*
	Generate invite codes for new users.
*/
//on level change
function pmproio_pmpro_after_change_membership_level($level_id, $user_id)
{
	//does this level give out invite codes?
	if(pmproio_isInviteGivenLevel($level_id))
        $new_codes = pmproio_createInviteCodes($user_id);

    if(!empty($new_codes))
        pmproio_saveInviteCodes($new_codes, $user_id);
}
add_action("pmpro_after_change_membership_level", "pmproio_pmpro_after_change_membership_level", 10, 2);

//at checkout
function pmproio_pmpro_after_checkout( $user_id )
{
	//get level
	$level = pmpro_getLevelAtCheckout();

	if ( ! empty( $level ) && pmproio_isInviteLevel( $level->id ) ) {
		//look for code
		if(!empty($_REQUEST['invite_code']))
			$invite_code = $_REQUEST['invite_code'];
		elseif(!empty($_SESSION['invite_code']))
			$invite_code = $_SESSION['invite_code'];
		else
			$invite_code = false;

		//update code used
		if(!empty($invite_code))
			update_user_meta($user_id, "pmpro_invite_code_at_signup", $invite_code);
	}

	//delete any session var
	if(!empty($_SESSION['invite_code']))
		unset($_SESSION['invite_code']);
}
add_action("pmpro_after_checkout", "pmproio_pmpro_after_checkout", 10, 1);

/*
	Save invite code while at PayPal
*/
function pmproio_pmpro_paypalexpress_session_vars()
{
	if(!empty($_REQUEST['invite_code']))
		$_SESSION['invite_code'] = $_REQUEST['invite_code'];
}
add_action("pmpro_paypalexpress_session_vars", "pmproio_pmpro_paypalexpress_session_vars");

/*
	Save invite code used when a user is created for PayPal and other offsite gateways.
	We are abusing the pmpro_wp_new_user_notification filter which runs after the user is created.
*/
function pmproio_pmpro_wp_new_user_notification($notify, $user_id)
{
    if(!empty($_REQUEST['invite_code']))
    {
        update_user_meta($user_id, "pmpro_invite_code_at_signup", $_REQUEST['invite_code']);
    }

	return $notify;
}
add_filter('pmpro_wp_new_user_notification', 'pmproio_pmpro_wp_new_user_notification', 10, 2);

/**
 *  Display invite codes on the confirmation page.
 *
 * @param string $message  The confirmation message.
 * @return string $message filtered with invite codes added.
 * @since TBD
 */
function pmproio_pmpro_confirmation_message( $message ) {
	global $current_user;
	$codes = pmproio_getInviteCodes( $current_user->ID );
	$level = pmpro_getLevelAtCheckout();
	//Bail if no codes or level
	if( empty( $codes ) || empty( $level ) ) {
		return $message;
	}
	$title = 'Your Invite Code';
	$text = 'Give this code to your invited member to use at checkout';
	if ( count( $codes ) > 1 ) {
		$title = 'Your Invite Codes';
		$text = 'Give these codes to your invited members to use at checkout';
	}
	//Add pmpro_card div
	$message .= '<div class="' . esc_attr( pmpro_get_element_class( 'pmpro_card' ) ) . '">';
	$message .= '<h2 class="' . esc_attr( pmpro_get_element_class( 'pmpro_card_title pmpro_font-large' ) ) . '">' . esc_html( sprintf( __( '%s', 'pmpro-invite-only' ), $title ) ) . '</h2>';
	$message .= '<div class="' . esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ) . '">';
	$message .= '<div class="' . esc_attr( pmpro_get_element_class( 'pmpro_form_fields' ) ) . '">';
	$message .= '<p class="' . esc_attr( pmpro_get_element_class( 'pmpro_form_fields-description' ) ) . '">' . esc_html( sprintf( __( '%s', 'pmpro-invite-only' ), $text ) ) . '</p>';
	$message .= pmproio_displayInviteCodes(  $current_user->ID);
	$message .= '</div>';
	$message .= '</div>';
	$message .= '</div>';
	return $message;
}


add_filter( "pmpro_confirmation_message", "pmproio_pmpro_confirmation_message", 10, 1 );

/*
	Show invite code fields on edit profile page for admins.
*/
function pmproio_show_extra_profile_fields($user)
{
	if(current_user_can('manage_options'))
	{
		?>
		<hr />
		<h2><?php esc_html_e('Invite Codes', 'pmpro-invite-only');?></h2>
		<p><strong><?php esc_html_e('Available Invite Codes', 'pmpro-invite-only');?></strong></p>
		<?php echo pmproio_displayInviteCodes($user->ID);?>
		<p><?php esc_html_e('Increase total available invites to', 'pmpro-invite-only'); ?> <input type="text" class="input" size="4" name="pmpro_add_invites" id="pmpro_add_invites" value="" /></p>
		<p><strong><?php esc_html_e('Used Invite Codes', 'pmpro-invite-only');?></strong></p>
		<?php echo pmproio_displayInviteCodes($user->ID, false, true); ?>
		<hr />
		<table class="form-table">
			<tr>
				<th><?php esc_html_e('Invite Code Used at Signup', 'pmpro-invite-only');?></th>
				<td>
					<?php
						$invite_code_used = $user->pmpro_invite_code_at_signup;
						if(empty($invite_code_used)) {
							echo "N/A";
						}
						else {
							$user_id = pmproio_getUserFromInviteCode($invite_code_used);
							$user_info = get_userdata($user_id);

							if (false !== $user_info) {
								echo esc_html( sprintf("%s (%s)", $invite_code_used, $user_info->display_name) );
							}
							else {
								echo esc_html( $invite_code_used );
							}
						}
					?>
				</td>
			</tr>
		</table>
		<?php
	}
}

add_action( 'show_user_profile', 'pmproio_show_extra_profile_fields' );
add_action( 'edit_user_profile', 'pmproio_show_extra_profile_fields' );

//save them
function pmproio_save_extra_profile_fields( $user_id )
{
	if ( !current_user_can( 'edit_user', $user_id ) )
		return false;

	if(!empty($_POST['invite_code']))
		update_user_meta($user_id, "pmpro_invite_code", $_POST['invite_code']);

	$invites_to_add = intval($_POST['pmpro_add_invites'], 10);

	if(!empty($_POST['pmpro_add_invites']) && $invites_to_add > 0 && current_user_can("manage_options"))
	{
		$codes = pmproio_createInviteCodes($user_id, true, $invites_to_add);
		pmproio_saveInviteCodes($codes, $user_id);
	}
}
add_action( 'personal_options_update', 'pmproio_save_extra_profile_fields' );
add_action( 'edit_user_profile_update', 'pmproio_save_extra_profile_fields' );

/**
 * Show invite codes on the 'the_content' filter for the account page.
 * 
 * @param string $content The content of the page.
 * @return string $content The content of the page with invite codes added.
 * @since TBD
 */
function pmproio_the_content_account_page( $content ) {
    global $current_user, $pmpro_pages, $post;

	//bail if not on account page
	if( empty( $pmpro_pages['account'] ) || empty( $current_user->ID ) || empty( $post ) || $post->ID != $pmpro_pages['account'] ) {
		return $content;
	}

	//make sure they have codes
	$codes = pmproio_getInviteCodes( $current_user->ID );
	//bail if no codes
	if( empty( $codes ) ) {
		return $content;
	}

	$title = 'Your Invite Code';
	$text = 'Give this code to your invited member to use at checkout';
	if ( count( $codes ) > 1 ) {
		$title = 'Your Invite Codes';
		$text = 'Give these codes to your invited members to use at checkout';
	}

	ob_start();
        ?>
		<div id="pmproio_codes" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card', 'pmproio_codes' ) ) ?>">
			<h2 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_title pmpro_font-large' ) ) ?>"><?php echo esc_html( sprintf( __( '%s', 'pmpro-invite-only' ), $title ) ); ?></h2>
			<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ) ?>">
				<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_fields' ) ) ?>">
					<p class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_fields-description' ) ); ?>"><?php echo esc_html( sprintf( __( '%s', 'pmpro-invite-only' ), $text ) ); ?></p>
					<?php echo pmproio_displayInviteCodes( $current_user->ID ); ?>
				</div> <!-- end pmpro_form_fields -->
				<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_spacer' ) ); ?>"></div>
				<p><strong><?php esc_html_e( 'Used Invite Codes', 'pmpro-invite-only' ); ?></strong></p>
				<?php echo pmproio_displayInviteCodes($current_user->ID, false, true);?>
				</div> <!-- end pmpro_card_content -->
			</div> <!-- end pmpro_card -->
		<?php
	$temp_content = ob_get_contents();
	ob_end_clean();
	$content = str_replace('<!-- end pmpro_account-profile -->', '<!-- end pmpro_account-profile -->' . $temp_content, $content);
    return $content;
}
add_filter('the_content', 'pmproio_the_content_account_page', 20, 1);

/*
	Add invite code to confirmation emails.
*/
function pmproio_pmpro_email_body($body, $email)
{
	if(strpos($email->template, "checkout") !== false && strpos($email->template, "debug") === false)
	{
		$user = get_user_by("login", $email->data['user_login']);
		$codes = get_user_meta($user->ID, "pmpro_invite_code", true);
		if(!empty($codes))
		{
			$list = "";
			foreach($codes as $code)
			{
				$list .= "{$code}<br>";
			}
			$body = str_replace("<p>Account:", "<p>Give these invite codes to others to use at checkout:<br><strong>{$list}</strong></p><p>Account:", $body);
		}
	}

	return $body;
}
add_filter("pmpro_email_body", "pmproio_pmpro_email_body", 10, 2);

/*
Function to add links to the plugin row meta
*/
function pmproio_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-invite-only.php') !== false)
	{
		$new_links = array(
			'<a href="' . esc_url('https://www.paidmembershipspro.com/add-ons/pmpro-invite-only-membership/') . '" title="' . esc_attr( __( 'View Documentation', 'pmpro-invite-only' ) ) . '">' . esc_html__( 'Docs', 'pmpro-invite-only' ) . '</a>',
			'<a href="' . esc_url('https://www.paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro-invite-only' ) ) . '">' . esc_html__( 'Support', 'pmpro-invite-only' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmproio_plugin_row_meta', 10, 2);
