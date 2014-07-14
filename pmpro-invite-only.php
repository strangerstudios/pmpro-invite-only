<?php
/*
Plugin Name: PMPro Invite Only
Plugin URI: http://www.paidmembershipspro.com/add-ons/pmpro-invite-only/
Description: Users must have an invite code to sign up for certain levels. Users are given an invite code to share.
Version: .1
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/

/*
	Set an array with the level ids of the levels which should require invite codes and generate them.
	
	e.g.
	global $pmproio_invite_levels;
	$pmproio_invite_levels = array(1,2,3);

    Set the number of invite codes to be created.
    define('PMPROIO_CODES', 10);
*/

//check if a level id requires an invite code or should generate one
function pmproio_isInviteLevel($level_id)
{
	global $pmproio_invite_levels;
	return in_array($level_id, $pmproio_invite_levels);
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

    //return unsorted codes unless specified otherwise
    if(!$sort_codes)
        return $codes;

    //sort codes
    $unused_codes = array();
    $used_codes = array();

    foreach($codes as $code)
    {
        $user_id = $wpdb->get_var("SELECT user_id FROM " . $wpdb->usermeta . " WHERE meta_key LIKE 'pmpro_invite_code_at_signup' AND meta_value LIKE '" . $code . "'");
        if(empty($user_id))
            $unused_codes[] = $code;
        else
            $used_codes[$code] = $user_id;
    }

    //add used codes to array
    $codes = array('unused' => $unused_codes, 'used' => $used_codes);

    return $codes;

}

//save invite codes
function pmproio_saveInviteCodes($new_codes, $user_id = null)
{
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
function pmproio_createInviteCodes($user_id = null)
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
    if(defined('PMPROIO_CODES'))
        $quantity = PMPROIO_CODES;
    else
        $quantity = 1;

    for($i=0; $i<$quantity; $i++) {

        //user_id part of code for easy searching later
        $id_part = str_pad(dechex($user_id), 4, '0', STR_PAD_LEFT);

        //random scramble part
        $scramble = md5(AUTH_KEY . $user_id . time() . SECURE_AUTH_KEY);

        $code = substr($id_part . $scramble, 0, 14);

        //if code is a duplicate or number, try again
        if(in_array($code, $old_codes) || in_array($code, $new_codes) || is_numeric($code)) {
            $i--;
            continue;
        }

        //code is ok, add it to array
        $new_codes[] = $code;
    }

    return $new_codes;
}

//check if an invite code is valid
function pmproio_checkInviteCode($invite_code)
{
    global $wpdb;

    //default to false
    $valid = false;

    $user_id = pmproio_getUserFromInviteCode($invite_code);

    //only valid if user still has membership, but allow filter
    if(pmpro_hasMembershipLevel(0, $user_id) && apply_filters('pmproio_check_user', true))
        $valid = false;

    //search for code
    $user_codes = pmproio_getInviteCodes($user_id);

    if(!in_array($invite_code, $user_codes))
        $valid = false;

    //has code already been used?
    $used = $wpdb->get_var("SELECT user_id FROM " . $wpdb->usermeta . " WHERE meta_key LIKE 'pmpro_invite_code_at_signup' AND meta_value LIKE '" . $invite_code . "'");

    if(empty($used))
        $valid = true;

    return $valid;
}

//get user id from invite code
function pmproio_getUserFromInviteCode($invite_code)
{
    $user_id = hexdec(substr($invite_code, 0, 4));
    return $user_id;
}

/*
	Add an invite code field to checkout
*/
function pmproio_pmpro_checkout_boxes()
{
	global $pmpro_level, $current_user;
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
	?>
	<table class="pmpro_checkout top1em" width="100%" cellpadding="0" cellspacing="0" border="0">
		<thead>
			<tr>
				<th><?php _e('Invite Code', 'pmpro');?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label for="invite_code"><?php _e('Invite Code', 'pmpro');?></label>
					<input id="invite_code" name="invite_code" type="text" class="input <?php echo pmpro_getClassForField("invite_code");?>" size="20" value="<?php echo esc_attr($invite_code);?>" />
					<span class="pmpro_asterisk"> *</span>
				</td>
			</tr>
		</tbody>
	</table>
	<?php
	}
}
add_action('pmpro_checkout_boxes', 'pmproio_pmpro_checkout_boxes');

/*
	Require the invite code
*/
function pmproio_pmpro_registration_checks($okay)
{
	global $pmpro_level, $pmproio_invite_levels;
	if(pmproio_isInviteLevel($pmpro_level->id))
	{
		global $pmpro_msg, $pmpro_msgt, $pmpro_error_fields, $wpdb;

		//get invite code
		$invite_code = $_REQUEST['invite_code'];

        $real = pmproio_checkInviteCode($invite_code);

		if(empty($invite_code) || empty($real))
		{
			pmpro_setMessage(__("An invite code is required for this level. Please enter a valid invite code.", "pmpro"), "pmpro_error");
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
	if(pmproio_isInviteLevel($level_id))
        $new_codes = pmproio_createInviteCodes($user_id);

    if(!empty($new_codes))
        pmproio_saveInviteCodes($new_codes, $user_id);
}
add_action("pmpro_after_change_membership_level", "pmproio_pmpro_after_change_membership_level", 10, 2);

//at checkout
function pmproio_pmpro_after_checkout($user_id)
{
	//get level
	$level_id = intval($_REQUEST['level']);

	if(pmproio_isInviteLevel($level_id))
	{
		//generate a code/etc
		pmproio_pmpro_after_change_membership_level($level_id, $user_id);

        //update code used
        if(!empty($_REQUEST['invite_code']))
            update_user_meta($user_id, "pmpro_invite_code_at_signup", $_REQUEST['invite_code']);
    }

	//delete any session var
	if(isset($_SESSION['invite_code']))
		unset($_SESSION['invite_code']);
}
add_action("pmpro_after_checkout", "pmproio_pmpro_after_checkout", 10, 2);

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

/*
	Show invite codes on confirmation and account pages
*/
function pmproio_pmpro_confirmation_message($message)
{
	global $current_user;

    $codes = pmproio_getInviteCodes($current_user->ID, true);

	if(!empty($codes))
	{
		$textarea = "<textarea rows=10 cols=20>";
        foreach($codes['unused'] as $code)
            $textarea .= $code . "\n";
        $textarea .= "</textarea>";

        $message .= "<div class=\"pmpro_content_message\"><p>Give these invite codes to others to use at checkout:</p>" . $textarea . "</div>";
	}
	return $message;
}
add_filter("pmpro_confirmation_message", "pmproio_pmpro_confirmation_message");

/*
	Show invite code fields on edit profile page for admins.
*/
function pmproio_show_extra_profile_fields($user)
{	
?>
	<h3><?php _e('Invite Codes', 'pmpro');?></h3>
 
	<table class="form-table">
 
		<tr>
			<th><?php _e('Invite Code', 'pmpro');?></th>			
			<td>
				<input type="text" name="invite_code" value="<?php echo esc_attr($user->pmpro_invite_code);?>" />
			</td>
		</tr>
		
		<tr>
			<th><?php _e('Invite Code Used at Signup', 'pmpro');?></th>
			<td>
				<?php 
					$invite_code_used = $user->pmpro_invite_code_at_signup;
					if(empty($invite_code_used))
						echo "N/A";
					else
						echo $invite_code_used;
				?>
			</td>
		</tr>
		
	</table>
<?php
}
add_action( 'show_user_profile', 'pmproio_show_extra_profile_fields' );
add_action( 'edit_user_profile', 'pmproio_show_extra_profile_fields' );

function pmproio_save_extra_profile_fields( $user_id ) 
{
	if ( !current_user_can( 'edit_user', $user_id ) )
		return false;
 
	if(!empty($_POST['invite_code']))
		update_user_meta($user_id, "pmpro_invite_code", $_POST['invite_code']);
}
add_action( 'personal_options_update', 'pmproio_save_extra_profile_fields' );
add_action( 'edit_user_profile_update', 'pmproio_save_extra_profile_fields' );