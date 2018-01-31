<?php
/*
Plugin Name: Paid Memberships Pro - Invite Only Add On
Plugin URI: http://www.paidmembershipspro.com/add-ons/pmpro-invite-only/
Description: Users must have an invite code to sign up for certain levels. Users are given an invite code to share.
Version: .4
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/

/*
	Retired as of v.4: Set an array with the level ids which should require invite codes and generate them.

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
function pmproio_init_defaults() {
	
	global $pmproio_invite_levels, $pmproio_invite_required_levels, $pmproio_invite_given_levels;
	
	$save_required     = array();
	$invite_code_level = array();
	$give_level_invite = array();
	$save_given_levels = array();
	
	// Grab all settings
	$settings = pmproio_getSettings( null );
	
	// Remove the default settings entry
	unset( $settings['default'] );
	
	// Load & merge the list(s) of invite levels
	if ( ! isset( $pmproio_invite_levels ) ) {
		$pmproio_invite_levels = array();
	}
	
	// Add all membership level(s) where the invite code is required
	foreach ( $settings as $level_id => $config ) {
		
		if ( ! empty( $config['code-required'] ) ) {
			$invite_code_level[] = $level_id;
		}
		
		if ( ! empty( $config['give-to-member'] ) ) {
			$give_level_invite[] = $level_id;
		}
	}
	
	$pmproio_invite_levels = array_merge( $pmproio_invite_levels, $invite_code_level );
	
	if ( ! empty( $pmproio_invite_required_levels ) ) {
		$save_required = $pmproio_invite_required_levels;
	}
	
	$pmproio_invite_required_levels = array_merge( $save_required, $invite_code_level );
	
	//which levels are given invite codes?
	if ( ! empty( $pmproio_invite_given_levels ) ) {
		$save_given_levels = $pmproio_invite_given_levels;
	}
	
	$pmproio_invite_given_levels = array_merge( $save_given_levels, $pmproio_invite_required_levels, $give_level_invite );
	
	
	//how many codes to give?
	/**
	 * Handled by the new settings UI & functionality
	 *
	 * if ( ! defined( 'PMPROIO_CODES' ) ) {
	 * define( 'PMPROIO_CODES', 1 );
	 * }
	 *
	 * if ( ! defined( 'PMPROIO_CODES_USES' ) ) {
	 * define( 'PMPROIO_CODES_USES', false );
	 * }
	 */
}

add_action( 'init', 'pmproio_init_defaults', 99 );

//check if a level id requires an invite code or should generate one
function pmproio_isInviteLevel( $level_id ) {
	global $pmproio_invite_required_levels;
	if ( empty( $pmproio_invite_required_levels ) ) {
		$pmproio_invite_required_levels = array();
	}
	
	return in_array( $level_id, $pmproio_invite_required_levels );
}

//check if a level id requires an invite code or should generate one
function pmproio_isInviteGivenLevel( $level_id ) {
	global $pmproio_invite_given_levels;
	if ( empty( $pmproio_invite_given_levels ) ) {
		$pmproio_invite_given_levels = array();
	}
	
	return in_array( $level_id, $pmproio_invite_given_levels );
}

//get invite codes
function pmproio_getInviteCodes( $user_id = null, $sort_codes = false ) {
	global $current_user, $wpdb;
	
	if ( empty( $user_id ) ) {
		$user_id = $current_user->id;
	}
	
	//return if we still don't have a user id
	if ( empty( $user_id ) ) {
		return false;
	}
	
	$level = pmpro_getMembershipLevelForUser( $user_id );
	$codes = get_user_meta( $user_id, 'pmpro_invite_code', true );
	
	$settings = pmproio_getSettings( $level->id );
	//no codes
	if ( empty( $codes ) ) {
		return false;
	}
	
	//return unsorted codes unless specified otherwise
	if ( ! $sort_codes ) {
		return $codes;
	}
	
	//sort codes
	$unused_codes = array();
	$used_codes   = array();
	$code_count   = array();
	
	//figure out used codes
	foreach ( $codes as $code ) {
		$user_ids = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key LIKE 'pmpro_invite_code_at_signup' AND meta_value LIKE %s", $code ) );
		if ( ! empty( $user_ids ) ) {
			
			$used_codes[ $code ] = $user_ids;
			
			//add to code count
			$code_count[ $code ] = count( $user_ids );
		}
	}
	
	//figure out unused codes
	$unused_codes = $codes;
	if ( $settings['code-uses'] != false ) {
		foreach ( $unused_codes as $key => $code ) {
			if ( ! empty( $code_count[ $code ] ) && $code_count[ $code ] >= $settings['code-uses'] ) {
				unset( $unused_codes[ $key ] );
			}
		}
	}
	
	//add used codes to array
	$codes = array( 'unused' => $unused_codes, 'used' => $used_codes );
	
	return $codes;
}

//save invite codes
function pmproio_saveInviteCodes( $new_codes, $user_id = null ) {
	global $current_user;
	
	if ( empty( $user_id ) ) {
		$user_id = $current_user->ID;
	}
	
	//only continue if we have a user id and new codes
	if ( empty( $user_id ) || empty( $new_codes ) ) {
		return false;
	}
	
	$old_codes = pmproio_getInviteCodes( $user_id );
	if ( empty( $old_codes ) ) {
		$old_codes = array();
	}
	
	$codes = array_merge( $old_codes, $new_codes );
	
	//update user meta
	if ( update_user_meta( $user_id, 'pmpro_invite_code', $codes ) ) {
		return true;
	} else {
		return false;
	}
}

//create new invite codes
function pmproio_createInviteCodes( $user_id = null, $admin_override = false, $admin_quantity = 0 ) {
	global $current_user;
	
	if ( empty( $user_id ) ) {
		$user_id = $current_user->ID;
	}
	
	//only continue if we have a user id
	if ( empty( $user_id ) ) {
		return false;
	}
	
	//get old codes
	$old_codes = pmproio_getInviteCodes( $user_id );
	if ( empty( $old_codes ) ) {
		$old_codes = array();
	}
	$new_codes = array();
	
	$level    = pmpro_getMembershipLevelForUser( $user_id );
	$settings = pmproio_getSettings( $level->id );
	//use constant or default to 1 code if not set
	
	if ( $admin_override && current_user_can( "manage_options" ) ) {
		$quantity = $admin_quantity;
	} else if ( ! empty( $settings['code-uses'] ) ) {
		$quantity = $settings['code-uses'];
	} else {
		$quantity = 1;
	}
	
	//how many do we need to make?
	$quantity = $quantity - count( $old_codes );
	
	
	if ( $quantity > 0 ) {
		for ( $i = 0; $i < $quantity; $i ++ ) {
			//user_id part of code for easy searching later
			$id_part = str_pad( dechex( $user_id ), 4, '0', STR_PAD_LEFT );
			
			//random scramble part
			$scramble = strtoupper( md5( AUTH_KEY . $user_id . time() . SECURE_AUTH_KEY ) );
			
			$code = substr( $id_part . $scramble, 0, 14 );
			
			//if code is a duplicate or number, try again
			if ( in_array( $code, $old_codes ) || in_array( $code, $new_codes ) || is_numeric( $code ) ) {
				$i --;
				continue;
			}
			
			//code is ok, add it to array
			$new_codes[] = $code;
		}
	}
	
	return $new_codes;
}

//check if an invite code is valid
function pmproio_checkInviteCode( $invite_code ) {
	global $wpdb;
	
	//check for user from code
	$user_id = pmproio_getUserFromInviteCode( $invite_code );
	
	//no user? not a real code
	if ( empty( $user_id ) ) {
		return false;
	}
	
	//only valid if user still has membership, but allow filter
	if ( pmpro_hasMembershipLevel( 0, $user_id ) && apply_filters( 'pmproio_check_user', true ) ) {
		return false;
	}
	
	$level    = pmpro_getMembershipLevelForUser( $user_id );
	$settings = pmproio_getSettings( $level->id );
	
	//search for code
	$user_codes = pmproio_getInviteCodes( $user_id );
	if ( ! in_array( $invite_code, $user_codes ) ) {
		return false;
	}
	
	//has code already been used?
	if ( ! empty( $settings['code-uses'] ) ) {
		$used = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(user_id) FROM {$wpdb->usermeta} WHERE meta_key LIKE 'pmpro_invite_code_at_signup' AND meta_value LIKE %s", $invite_code )
		);
		
		//valid if we didn't hit use limit yet
		if ( ! empty( $used ) && $used >= $settings['code-uses'] ) {
			return false;
		}
	}
	
	//got here, must be valid
	return true;
}

//get user id from invite code
function pmproio_getUserFromInviteCode( $invite_code ) {
	$user_id = hexdec( substr( $invite_code, 0, 4 ) );
	
	return $user_id;
}

//display used/unused invite codes
function pmproio_displayInviteCodes( $user_id = null, $unused = true, $used = false ) {
	global $current_user;
	
	if ( empty( $user_id ) ) {
		$user_id = $current_user->ID;
	}
	
	$codes = pmproio_getInviteCodes( $user_id, true );
	
	if ( empty( $codes ) ) {
		return false;
	}
	
	//start output buffering
	ob_start();
	
	if ( ! empty( $unused ) ) {
		if ( empty( $codes['unused'] ) ) {
			__( 'All codes have been used.', 'pmpro_invite_only' );
		} else {
			?>
            <textarea class="pmproio_unused_codes" rows="3" style="width: 100%;" readonly><?php
				echo implode( ', ', $codes['unused'] );
				?></textarea>
			<?php
		}
	}
	
	if ( ! empty( $used ) ) {
		//figure out if codes have been used
		if ( ! empty( $codes['used'] ) ) {
			foreach ( $codes['used'] as $code => $user_ids ) {
				if ( ! empty( $user_ids ) ) {
					$codes_used = true;
					break;
				}
			}
		}
		
		if ( empty( $codes_used ) ) {
			_e( 'None of your codes have been used.', 'pmpro_invite_only' );
		} else {
			?>
            <table class="used_codes widefat striped">
            <thead>
            <tr>
                <th><?php _e( 'Member', 'pmpro_invite_only' ); ?></th>
                <th><?php _e( 'Invite Code', 'pmpro_invite_only' ); ?></th>
            </tr>
            </thead>
            <tbody>
			<?php
			foreach ( $codes['used'] as $code => $user_ids ) {
				foreach ( $user_ids as $user_id ) {
					$display_name = get_userdata( $user_id )->display_name;
					if ( empty( $display_name ) ) {
						$display_name = __( 'N/A (Deleted or abandoned.' );
					} else {
						if ( current_user_can( 'manage_options' ) ) {
							$userlink = "<a href=" . add_query_arg( 'user_id', $user_id, admin_url( 'user-edit.php' ) ) . ">" . $display_name . "</a>";
						}
					}
					?>
                    <tr>
                    <td>
						<?php
						if ( ! empty( $userlink ) ) {
							echo $userlink;
						} else {
							echo $display_name;
						}
						?>
                    </td>
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

/**
 * Load required JavaScript on the Member Levels page
 */
function pmproio_enqueueJS( $hook ) {
	
	if ( $hook != 'admin.php' && $_GET['page'] != 'pmpro-membershiplevels' ) {
		return;
	}
	
	wp_enqueue_script( 'pmproio', plugins_url( 'js/pmpro-invite-only.js', __FILE__ ), array( 'jquery' ), '.4', true );
}

add_action( 'admin_enqueue_scripts', 'pmproio_enqueueJS' );
/**
 * Show Invite Code settings for the level
 *
 * @param $level_id
 */
function pmproio_displayLevelSettings() {
	
	$level_id = isset( $_GET['edit'] ) ? intval( $_GET['edit'] ) : - 1;
	
	if ( $level_id === - 1 ) {
		$level_id = 'default';
	}
	
	if ( 'default' === $level_id ) {
		$settings = pmproio_getSettings( $level_id );
		$settings = $settings['default'];
	} else {
		$settings = pmproio_getSettings( $level_id );
	}
	
	$code_required   = isset( $settings['code-required'] ) ? (bool) $settings['code-required'] : false;
	$give_to_member  = ( isset( $settings['give-to-member'] ) && true === (bool) $settings['give-to-member'] ) ? true : false;
	$code_uses       = ( ! isset( $settings['code-uses'] ) || empty( $settings['code-uses'] ) ? '' : intval( $settings['code-uses'] ) );
	$code_count      = isset( $settings['code-count'] ) ? intval( $settings['code-uses'] ) : 1;
	$show_code_count = ( true === $give_to_member && $code_uses > 0 ); ?>
    <hr/>
    <h3 class="pmproio-header"><?php _e( "Invite Only Settings", 'pmpro-invite-only' ); ?></h3>
    <table class="pmproio-settings form-table">
        <tbody class="pmproio-settings-body">
        <tr scope="row" valign="top" class="pmproio-settings-row">
            <th scope="row" valign="top" class="pmproio-settings-cell">
                <label><?php _e( 'Require Invite Code', 'pmpro-invite-only' ); ?></label>
            </th>
            <td class="pmproio-settings-cell">
                <input type="checkbox" name="pmproio-setting_code-required" class="pmproio-setting_code-required"
                       value="1" <?php checked( true, $code_required ); ?> />
                <label for="pmproio_code-required"><?php _e( "Require an invite code to checkout for this level", 'pmpro-invite-only' ); ?></label>
            </td>
        </tr>
        <tr scope="row" valign="top" class="pmproio-settings-row">
            <th class="pmproio-settings-cell">
                <label><?php _e( 'Member Codes', 'pmpro-invite-only' ); ?></label>
            </th>
            <td class="pmproio-settings-cell">
                <input type="checkbox" name="pmproio-setting_give-to-member" class="pmproio-setting_give-to-member"
                       value="1" <?php checked( true, $give_to_member ); ?> />
                <label for="pmproio-setting_give-to-member"><?php _e( "Give invite codes to members of this level", 'pmpro-invite-only' ); ?></label>
            </td>
        </tr>
        <tr scope="row" valign="top"
            class="pmproio-settings-row" <?php echo( $give_to_member ? null : 'style="display:none;"' ); ?>>
            <td class="pmproio-settings-cell">
                <label for="pmproio-setting_code-uses"><?php _e( "How many times can the codes be used?", 'pmpro-invite-only' ); ?></label>
            </td>
            <td class="pmproio-settings-cell">
                <input type="number" name="pmproio-setting_code-uses" class="pmproio-setting_code-uses"
                       value="<?php esc_attr_e( $code_uses ); ?>"/>
            </td>
        </tr>
        <tr scope="row" valign="top"
            class="pmproio-settings-row" <?php echo( $show_code_count ? null : 'style="display:none;"' ); ?>>
            <td class="pmproio-settings-cell">
                <label for="pmproio-setting_code-count"><?php _e( "How many:", 'pmpro-invite-only' ); ?></label>
            </td>
            <td class="pmproio-settings-cell">
                <input type="number" name="pmproio-setting_code-count" class="pmproio-setting_code-count"
                       value="<?php esc_attr_e( $code_count ); ?>"/>
            </td>
        </tr>
        </tbody>
    </table>
	<?php
}

add_action( 'pmpro_membership_level_after_other_settings', 'pmproio_displayLevelSettings', 10, 1 );

/**
 * Return the settings for the specified level ID
 *
 * @param string|int|null $level_id
 *
 * @return array
 */
function pmproio_getSettings( $level_id ) {
	
	$settings = get_option( 'pmproio_level_settings' );
	
	// Requested all saved settings to be returned
	if ( empty( $level_id ) && ! empty( $settings ) ) {
		return $settings;
	}
	
	if ( empty( $level_id ) ) {
		$level_id = 'default';
	}
	
	// No settings saved (yet)
	if ( ! isset( $settings[ $level_id ] ) ) {
		$settings[ $level_id ] = pmproio_defaultSettings( $level_id );
	}
	
	return $settings[ $level_id ];
}

/**
 * Save the level specific settings for
 *
 * @param int $level_id
 */
function pmproio_saveLevelSettings( $level_id ) {
	
	$defaults     = pmproio_defaultSettings( $level_id );
	$all_settings = pmproio_getSettings( null );
	
	foreach ( $defaults['default'] as $key => $value ) {
		
		// Update any saved/updated values
		if ( isset( $_REQUEST["pmproio-setting_{$key}"] ) ) {
			
			$value = sanitize_text_field( $_REQUEST["pmproio-setting_{$key}"] );
		}
		
		$all_settings[ $level_id ][ $key ] = $value;
		
		error_log( "Setting {$key} to {$value} for level {$level_id}" );
	}
	
	update_option( 'pmproio_level_settings', $all_settings, 'no' );
}

add_action( 'pmpro_save_membership_level', 'pmproio_saveLevelSettings', 10, 1 );

/**
 * Return the default settings
 *
 * @param int $level_id The Level ID to use in order to load default (new) settings when not saved
 *
 * @return array
 */
function pmproio_defaultSettings( $level_id ) {
	
	global $pmproio_invite_required_levels;
	global $pmproio_invite_given_levels;
	
	// Default values for settings
	$is_required = false;
	$to_members  = false;
	$code_uses   = ( defined( 'PMPROIO_CODES_USES' ) && false !== PMPROIO_CODES_USES ? PMPROIO_CODES_USES : false );
	$code_count  = ( defined( 'PMPROIO_CODES' ) ? PMPROIO_CODES : 1 );
	
	// Change the $is_required flag if old config contains this level ID
	if ( ! empty( $pmproio_invite_required_levels ) && in_array( $level_id, $pmproio_invite_required_levels ) ) {
		
		$is_required = false;
	}
	/*
	// Change the $to_members flag if old config contains this level ID
	if ( ! empty( $pmproio_invite_given_levels ) && in_array( $level_id, $pmproio_invite_given_levels ) ||
	     ! empty( $pmproio_invite_required_levels ) && in_array( $level_id, $pmproio_invite_required_levels ) ) {
		
	    error_log( print_r( $pmproio_invite_required_levels, true ) );
	    error_log( print_r( $pmproio_invite_given_levels, true ) );
	    
		$to_members = true;
	}
	*/
	
	// Let defaults reflect the old setting configuration
	return array(
		'default' => array(
			'code-required'  => $is_required,
			'give-to-member' => $to_members,
			'code-uses'      => $code_uses,
			'code-count'     => $code_count,
		),
	);
}

/**
 * Delete settings for the specified level ID
 *
 * @param int $level_id
 */
function pmproio_deleteLevelSettings( $level_id ) {
	
	$settings = pmproio_getSettings( null );
	
	// Remove the settings for the specified level (when they exist)
	if ( ! empty( $settings[ $level_id ] ) ) {
		
		unset( $settings[ $level_id ] );
		update_option( 'pmproio_level_settings', $settings, 'no' );
	}
}

add_action( 'pmpro_delete_membership_level', 'pmproio_deleteLevelSettings', 10, 1 );


/*
	Add an invite code field to checkout
*/
function pmproio_pmpro_checkout_boxes() {
	global $pmpro_level, $current_user, $pmpro_review;
	if ( pmproio_isInviteLevel( $pmpro_level->id ) ) {
		if ( ! empty( $_REQUEST['invite_code'] ) ) {
			$invite_code = $_REQUEST['invite_code'];
		} else if ( ! empty( $_SESSION['invite_code'] ) ) {
			$invite_code = $_SESSION['invite_code'];
		} else if ( is_user_logged_in() ) {
			$invite_code = $current_user->pmpro_invite_code_at_signup;
		} else {
			$invite_code = "";
		}
		
		if ( empty( $pmpro_review ) ) {
			?>
            <table class="pmpro_checkout top1em" width="100%" cellpadding="0" cellspacing="0" border="0">
                <thead>
                <tr>
                    <th><?php _e( 'Invite Code', 'pmpro_invite_only' ); ?></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>
                        <label for="invite_code"><?php _e( 'Invite Code', 'pmpro_invite_only' ); ?></label>
                        <input id="invite_code" name="invite_code" type="text"
                               class="input <?php echo pmpro_getClassForField( "invite_code" ); ?>" size="20"
                               value="<?php echo esc_attr( $invite_code ); ?>"/>
                        <span class="pmpro_asterisk"> *</span>
                    </td>
                </tr>
                </tbody>
            </table>
			<?php
		}
	}
}

add_action( 'pmpro_checkout_boxes', 'pmproio_pmpro_checkout_boxes' );

/*
	Require the invite code
*/
function pmproio_pmpro_registration_checks( $okay ) {
	global $pmpro_level, $pmproio_invite_required_levels;
	if ( pmproio_isInviteLevel( $pmpro_level->id ) ) {
		global $pmpro_msg, $pmpro_msgt, $pmpro_error_fields, $wpdb;
		
		//get invite code
		$invite_code = $_REQUEST['invite_code'];
		
		$real = pmproio_checkInviteCode( $invite_code );
		
		if ( empty( $invite_code ) || empty( $real ) ) {
			pmpro_setMessage( __( "An invite code is required for this level. Please enter a valid invite code.", "pmpro" ), "pmpro_error" );
			$pmpro_error_fields[] = "invite_code";
		}
	}
	
	return $okay;
}

add_filter( "pmpro_registration_checks", "pmproio_pmpro_registration_checks" );

/*
	Generate invite codes for new users.
*/
//on level change
function pmproio_pmpro_after_change_membership_level( $level_id, $user_id ) {
	//does this level give out invite codes?
	if ( pmproio_isInviteGivenLevel( $level_id ) ) {
		$new_codes = pmproio_createInviteCodes( $user_id );
	}
	
	if ( ! empty( $new_codes ) ) {
		pmproio_saveInviteCodes( $new_codes, $user_id );
	}
}

add_action( "pmpro_after_change_membership_level", "pmproio_pmpro_after_change_membership_level", 10, 2 );

//at checkout
function pmproio_pmpro_after_checkout( $user_id ) {
	//get level
	$level_id = intval( $_REQUEST['level'] );
	
	if ( pmproio_isInviteLevel( $level_id ) ) {
		//look for code
		if ( ! empty( $_REQUEST['invite_code'] ) ) {
			$invite_code = $_REQUEST['invite_code'];
		} else if ( ! empty( $_SESSION['invite_code'] ) ) {
			$invite_code = $_SESSION['invite_code'];
		} else {
			$invite_code = false;
		}
		
		//update code used
		if ( ! empty( $invite_code ) ) {
			update_user_meta( $user_id, "pmpro_invite_code_at_signup", $invite_code );
		}
	}
	
	//delete any session var
	if ( ! empty( $_SESSION['invite_code'] ) ) {
		unset( $_SESSION['invite_code'] );
	}
}

add_action( "pmpro_after_checkout", "pmproio_pmpro_after_checkout", 10, 2 );

/*
	Save invite code while at PayPal
*/
function pmproio_pmpro_paypalexpress_session_vars() {
	if ( ! empty( $_REQUEST['invite_code'] ) ) {
		$_SESSION['invite_code'] = $_REQUEST['invite_code'];
	}
}

add_action( "pmpro_paypalexpress_session_vars", "pmproio_pmpro_paypalexpress_session_vars" );

/*
	Save invite code used when a user is created for PayPal and other offsite gateways.
	We are abusing the pmpro_wp_new_user_notification filter which runs after the user is created.
*/
function pmproio_pmpro_wp_new_user_notification( $notify, $user_id ) {
	if ( ! empty( $_REQUEST['invite_code'] ) ) {
		update_user_meta( $user_id, "pmpro_invite_code_at_signup", $_REQUEST['invite_code'] );
	}
	
	return $notify;
}

add_filter( 'pmpro_wp_new_user_notification', 'pmproio_pmpro_wp_new_user_notification', 10, 2 );

/*
	Show invite codes on confirmation and account pages
*/
function pmproio_pmpro_confirmation_message( $message ) {
	global $current_user;
	
	$codes = pmproio_getInviteCodes( $current_user->ID );
	
	if ( ! empty( $codes ) && pmproio_isInviteGivenLevel( $current_user->membership_level->id ) ) {
		if ( count( $codes ) == 1 ) {
			$message .= "<div class=\"pmpro_message pmpro_alert\"><h3>" . __( 'Your Invite Code', 'pmpro_invite_only' ) . "</h3>";
			$message .= "<p>" . __( 'Give this code to your invited member to use at checkout', 'pmpro_invite_only' ) . "</p>";
		} else {
			$message .= "<div class=\"pmpro_message pmpro_alert\"><h3>" . __( 'Your Invite Codes', 'pmpro_invite_only' ) . "</h3>";
			$message .= "<p>" . __( 'Give these codes to your invited members to use at checkout', 'pmpro_invite_only' ) . "</p>";
		}
		$message .= pmproio_displayInviteCodes( $current_user->ID );
		$message .= "</div>";
	}
	
	return $message;
}

add_filter( "pmpro_confirmation_message", "pmproio_pmpro_confirmation_message" );

/*
	Show invite code fields on edit profile page for admins.
*/
function pmproio_show_extra_profile_fields( $user ) {
	if ( current_user_can( 'manage_options' ) ) {
		?>
        <hr/>
        <h2><?php _e( 'Invite Codes', 'pmpro_invite_only' ); ?></h2>
        <h4><?php _e( 'Available Invite Codes', 'pmpro_invite_only' ); ?></h4>
		<?php echo pmproio_displayInviteCodes( $user->ID ); ?>
        <p><?php _e( 'Increase total available invites to', 'pmpro_invite_only' ); ?> <input type="text" size="4"
                                                                                             name="pmpro_add_invites"
                                                                                             id="pmpro_add_invites"
                                                                                             value=""/></p>
        <hr/>
        <h4><?php _e( 'Used Invite Codes', 'pmpro_invite_only' ); ?></h4>
		<?php echo pmproio_displayInviteCodes( $user->ID, false, true ); ?>
        <hr/>
        <table class="form-table">
            <tr>
                <th><?php _e( 'Invite Code Used at Signup', 'pmpro_invite_only' ); ?></th>
                <td>
					<?php
					$invite_code_used = $user->pmpro_invite_code_at_signup;
					if ( empty( $invite_code_used ) ) {
						echo "N/A";
					} else {
						$user_id   = pmproio_getUserFromInviteCode( $invite_code_used );
						$user_info = get_userdata( $user_id );
						
						if ( false !== $user_info ) {
							echo sprintf( "%s (%s)", $invite_code_used, $user_info->display_name );
						} else {
							echo $invite_code_used;
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
function pmproio_save_extra_profile_fields( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return false;
	}
	
	if ( ! empty( $_POST['invite_code'] ) ) {
		update_user_meta( $user_id, "pmpro_invite_code", $_POST['invite_code'] );
	}
	
	$invites_to_add = intval( $_POST['pmpro_add_invites'], 10 );
	
	if ( ! empty( $_POST['pmpro_add_invites'] ) && $invites_to_add > 0 && current_user_can( "manage_options" ) ) {
		$codes = pmproio_createInviteCodes( $user_id, true, $invites_to_add );
		pmproio_saveInviteCodes( $codes, $user_id );
	}
}

add_action( 'personal_options_update', 'pmproio_save_extra_profile_fields' );
add_action( 'edit_user_profile_update', 'pmproio_save_extra_profile_fields' );

/*
	Show an invite code on the account page.
*/
function pmproio_the_content_account_page( $content ) {
	global $current_user, $pmpro_pages, $post;
	
	if ( ! empty( $current_user->ID ) && $post->ID == $pmpro_pages['account'] ) {
		//make sure they have codes
		$codes = pmproio_getInviteCodes( $current_user->ID );
		if ( empty( $codes ) ) {
			return $content;
		}
		
		ob_start();
		?>
        <div id="pmproio_codes" class="pmpro_box clear">
			<?php if ( count( $codes ) == 1 ) { ?>
                <h2><?php _e( 'Invite Code', 'pmpro_invite_only' ); ?></h2>
                <p><?php _e( 'Give this code to your invited member to use at checkout', 'pmpro_invite_only' ); ?></p>
			<?php } else { ?>
                <h2><?php _e( 'Invite Codes', 'pmpro_invite_only' ); ?></h2>
                <p><?php _e( 'Give these codes to your invited members to use at checkout', 'pmpro_invite_only' ); ?></p>
			<?php } ?>
			<?php echo pmproio_displayInviteCodes(); ?>
            <h4><?php _e( 'Used Invite Codes', 'pmpro_invite_only' ); ?></h4>
			<?php echo pmproio_displayInviteCodes( $current_user->ID, false, true ); ?>
        </div>
		<?php
		$temp_content = ob_get_contents();
		ob_end_clean();
		$content = str_replace( '<!-- end pmpro_account-profile -->', '<!-- end pmpro_account-profile -->' . $temp_content, $content );
	}
	
	return $content;
}

add_filter( 'the_content', 'pmproio_the_content_account_page', 20, 1 );

/*
	Add invite code to confirmation emails.
*/
function pmproio_pmpro_email_body( $body, $email ) {
	if ( strpos( $email->template, "checkout" ) !== false && strpos( $email->template, "debug" ) === false ) {
		$user  = get_user_by( "login", $email->data['user_login'] );
		$codes = get_user_meta( $user->ID, "pmpro_invite_code", true );
		if ( ! empty( $codes ) ) {
			$list = "";
			foreach ( $codes as $code ) {
				$list .= "{$code}<br>";
			}
			$body = str_replace( "<p>Account:", "<p>Give these invite codes to others to use at checkout:<br><strong>{$list}</strong></p><p>Account:", $body );
		}
	}
	
	return $body;
}

add_filter( "pmpro_email_body", "pmproio_pmpro_email_body", 10, 2 );

/*
Function to add links to the plugin row meta
*/
function pmproio_plugin_row_meta( $links, $file ) {
	if ( strpos( $file, 'pmpro-invite-only.php' ) !== false ) {
		$new_links = array(
			'<a href="' . esc_url( 'http://www.paidmembershipspro.com/add-ons/plus-add-ons/pmpro-invite-only/' ) . '" title="' . esc_attr( __( 'View Documentation', 'pmpro' ) ) . '">' . __( 'Docs', 'pmpro' ) . '</a>',
			'<a href="' . esc_url( 'http://paidmembershipspro.com/support/' ) . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro' ) ) . '">' . __( 'Support', 'pmpro' ) . '</a>',
		);
		$links     = array_merge( $links, $new_links );
	}
	
	return $links;
}

add_filter( 'plugin_row_meta', 'pmproio_plugin_row_meta', 10, 2 );
