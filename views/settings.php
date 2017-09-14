<?php
/**
 * Settings form in admin menu
 *
 * @package envato-form
 */

?>

<div id="envascout-wrap" class="wrap about-wrap">
	<h1>EnvaScout Form &nbsp;<?php echo esc_html( ENVASCOUT_FORM_VER ); ?></h1>
	<p class="about-text">Connects between Envato API, Helpscout, and caldera forms.</p>

	<h2 class="nav-tab-wrapper wp-clearfix">
		<a href="#" v-on:click="showTab('envato', $event)" class="nav-tab" :class="{'nav-tab-active': displayTab === 'envato'}">Envato Settings</a>
		<a href="#" v-on:click="showTab('helpscout', $event)" class="nav-tab" :class="{'nav-tab-active': displayTab === 'helpscout'}">Helpscout Settings</a>
	</h2>

	<form method="post" action="admin.php?page=envascout-form-setting">
		<?php settings_fields( 'envascout-form' ); ?>
		<?php do_settings_sections( 'envascout-form' ); ?>

		<div class="content-wrapper">
			<div class="tab-content tab-envato" style="display:none" v-show="displayTab === 'envato'">
				<br />
				<p class="description">Add <code>[envascout-form]</code> shortcode to displaying OAuth Button and Form in your page.</p>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="envato_client_secret">Client Secret</label></th>
						<td>
							<div>
							<input type="text" name="envascout_options[envato_client_secret]" id="envato_client_secret" value="<?php echo esc_attr( $options['envato_client_secret'] ); ?>" class="regular-text">
							<button type="button" class="button wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="Toggle Secret Client" v-on:click="toogleEnvatoClientSecret()">
								<span class="dashicons" :class="{'dashicons-visibility': !envato.displayClientSecret, 'dashicons-hidden': envato.displayClientSecret}"></span>
							</button>
							</div>
							<p class="description">You can find Client Secret just after <a href="https://build.envato.com/register/" target="_blank">Register an Envato App</a>.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="envato_client_id">Client ID</label></th>
						<td>
							<input name="envascout_options[envato_client_id]" type="text" id="envato_client_id" value="<?php echo esc_attr( $options['envato_client_id'] ); ?>" class="regular-text">
							<p class="description">You can find Client ID in <a href="https://build.envato.com/my-apps/" target="_blank">My Envato Apps</a> page.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="oauth_button_label">OAuth Button Label</label></th>
						<td>
							<input name="envascout_options[oauth_button_label]" type="text" id="oauth_button_label" value="<?php echo esc_attr( $options['oauth_button_label'] ); ?>" class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="caldera_form">Caldera Form</label></th>
						<td>
							<?php $caldera_forms = Caldera_Forms_Forms::get_forms( true ); ?>
							<?php
							if ( count( $caldera_forms ) > 0 ) {
								$caldera_forms_selector[] = '<select name="envascout_options[caldera_form]" id="caldera_form">';
								foreach ( $caldera_forms as $form ) {
									$selected = $options['caldera_form'] === $form['ID'] ? ' selected="selected"' : '';
									$caldera_forms_selector[] = sprintf( '<option value="%s"%s>%s</option>', $form['ID'], $selected, $form['name'] );
								}
								$caldera_forms_selector[] = '</select>';

								echo wp_kses(
									implode( '', $caldera_forms_selector ),
									array(
										'select' => array(
											'id' => array(),
											'name' => array(),
										),
										'option' => array(
											'selected' => array(),
											'value' => array(),
										),
									)
								);
							} else {
								/* translators: caldera admin page link. */
								printf( esc_html__( 'Please create forms at %1$s page.', 'envascout-form' ), '<a href="admin.php?page=caldera-forms">Caldera Forms</a>' );
							}
							?>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="session_prefix">Session Prefix</label></th>
						<td>
							<input name="envascout_options[session_prefix]" type="text" id="session_prefix" value="<?php echo esc_attr( $options['session_prefix'] ); ?>" class="regular-text">
							<p class="description">Prefix used by <code>$_SESSION</code> to store oauth <code>access_token</code>.</p>
						</td>
					</tr>
				</table>
			</div>
			<div class="tab-content tab-helpscout" style="display:none" v-show="displayTab === 'helpscout'">
				<table class="form-table">
					<tr>
						<th scope="row"><label for="helpscout_api_key">API Key</label></th>
						<td>
							<div>
							<input name="envascout_options[helpscout_api_key]" id="helpscout_api_key" value="<?php echo esc_attr( $options['helpscout_api_key'] ); ?>" class="regular-text">
							<button type="button" class="button wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="Toggle Secret Client" v-on:click="toogleHelpscoutApiKey()">
								<span class="dashicons" :class="{'dashicons-visibility': !helpscout.displayApiKey, 'dashicons-hidden': helpscout.displayApiKey}"></span>
							</button>
							</div>
							<p class="description">To generate new API Key, go to <strong>Profile</strong> &gt; <strong>Authentication</strong> &gt; <strong>API Keys</strong>.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="helpscout_mailbox">Select Mailbox</label></th>
						<td>
							<?php if ( count( $mailboxes ) > 0 ) : ?>
							<select name="envascout_options[helpscout_mailbox]">
								<option value="0"<?php echo intval( $options['helpscout_mailbox'] ) === 0 ? ' selected="selected"' : ''; ?>>Please Select Mailbox</p>
								<?php
								foreach ( $mailboxes as $mailbox ) {
									$selected = intval( $options['helpscout_mailbox'] ) === intval( $mailbox['id'] ) ? ' selected="selected"' : '';
									printf( '<option value="%s"%s>%s</option>', esc_attr( $mailbox['id'] ), esc_attr( $selected ), esc_html( $mailbox['name'] ) );
								}
								?>
							</select>
							<button type="button" class="button hide-if-no-js" data-toggle="0" aria-label="Refresh Mailbox" v-on:click="refreshHelpscout()">
								<span class="dashicons dashicons-update dashicon-margin-top"></span>
							</button>
							<?php else : ?>
							<p class="description">Please set API Key Correctly or <a href="https://secure.helpscout.net/settings/mailboxes/" target="_blank">Create Mailbox</a>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="caldera_item_id">Item ID Field</label></th>
						<td>
							<input name="envascout_options[caldera_item_id]" type="text" id="caldera_item_id" value="<?php echo esc_attr( $options['caldera_item_id'] ); ?>" class="regular-text">
							<p class="description">Item ID Field Slug in Caldera Forms.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="caldera_attachment_id">Attachment Field</label></th>
						<td>
							<input name="envascout_options[caldera_attachment_id]" type="text" id="caldera_attachment_id" value="<?php echo esc_attr( $options['caldera_attachment_id'] ); ?>" class="regular-text">
							<p class="description">Attachment Field Slug in Caldera Forms.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="helpscout_subject">Subject Template</label></th>
						<td>
							<input name="envascout_options[helpscout_subject]" type="text" id="helpscout_subject" value="<?php echo esc_attr( $options['helpscout_subject'] ); ?>" class="regular-text">
							<p class="description"><strong>%fieldslug%</strong> will be replaced with caldera form slug, e.g %subject%.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="helpscout_content">Content Template</label></th>
						<td>
							<?php wp_editor( stripslashes_deep( $options['helpscout_content'] ), 'helpscout_content', wp_parse_args( array(
								'textarea_name' => 'envascout_options[helpscout_content]'
							), $template_editor_settings ) ); ?>
							<p class="description"><strong>%fieldslug%</strong> will be replaced with caldera form slug, e.g %content%.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="helpscout_dynamic_app">Dynamic App Template</label></th>
						<td>
							<?php wp_editor( stripslashes_deep( $options['helpscout_dynamic_app'] ), 'helpscout_dynamic_app', wp_parse_args( array(
								'textarea_name' => 'envascout_options[helpscout_dynamic_app]'
							), $template_editor_settings ) ); ?>
							<p class="description">
								Dynamic App URL: <?php echo site_url( '?envascout_action=helpscout_app' ); ?>
							</p>
							<br />
							<p class="description">
								<strong>%username%</strong> will be replaced with envato username.<br />
								<strong>%firstname%</strong> will be replaced with envato firstname.<br />
								<strong>%lastname%</strong> will be replaced with envato lastname.<br />
								<strong>%image_url%</strong> will be replaced with avatar's url.<br />
								<strong>%country%</strong> will be replaced with user's country.<br />
								<strong>%item_info%</strong> will be replaced with item information.<br />
								<strong>%purchase_info%</strong> will be replaced with purchase information, such as purchase code.<br />
								<strong>%fieldslug%</strong> will be replaced with caldera form slug, e.g %content%.
							</p>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<?php submit_button(); ?>
	</form>
</div>
