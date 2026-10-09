<?php

class PMProio_Member_Edit_Panel extends PMPro_Member_Edit_Panel {
	/**
	 * Set up the panel.
	 */
	public function __construct() {
		$this->slug        = 'invite-codes';
		$this->title       = __( 'Invite Codes', 'pmpro-invite-only' );
		$this->submit_text = __( 'Update Invite Codes', 'pmpro-invite-only' );
	}

	/**
	 * Only show this panel to admins.
	 */
	public function should_show() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Display the panel contents.
	 */
	protected function display_panel_contents() {
		$user = self::get_user();
		?>
		<p><strong><?php esc_html_e( 'Available Invite Codes', 'pmpro-invite-only' ); ?></strong></p>
		<?php echo pmproio_displayInviteCodes( $user->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Function returns escaped HTML. ?>
		<p><?php esc_html_e( 'Increase total available invites to', 'pmpro-invite-only' ); ?> <input type="text" class="input" size="4" name="pmpro_add_invites" id="pmpro_add_invites" value="" /></p>
		<p><strong><?php esc_html_e( 'Used Invite Codes', 'pmpro-invite-only' ); ?></strong></p>
		<?php echo pmproio_displayInviteCodes( $user->ID, false, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Function returns escaped HTML. ?>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Invite Code Used at Signup', 'pmpro-invite-only' ); ?></th>
				<td>
					<?php
						$invite_code_used = $user->pmpro_invite_code_at_signup;
						if ( empty( $invite_code_used ) ) {
							echo 'N/A';
						} else {
							$user_id   = pmproio_getUserFromInviteCode( $invite_code_used );
							$user_info = get_userdata( $user_id );

							if ( false !== $user_info ) {
								echo esc_html( sprintf( '%s (%s)', $invite_code_used, $user_info->display_name ) );
							} else {
								echo esc_html( $invite_code_used );
							}
						}
					?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save the panel.
	 */
	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$user_id        = self::get_user()->ID;
		$invites_to_add = isset( $_POST['pmpro_add_invites'] ) ? intval( $_POST['pmpro_add_invites'], 10 ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by PMPro core before save() is called.

		if ( ! empty( $user_id ) && $invites_to_add > 0 ) {
			$codes = pmproio_createInviteCodes( $user_id, true, $invites_to_add );
			pmproio_saveInviteCodes( $codes, $user_id );
		}
	}
}
