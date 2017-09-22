<?php
/**
 * Main Class to initegrate with caldera forms
 *
 * @package Envascout_Form
 * @since 1.0.0
 */

/**
 * Envascout Form Main Class.
 */
class Envascout_Form {
	/**
	 * Check if has been initialized or not yet.
	 *
	 * @var boolean
	 */
	private static $initiated = false;

	/**
	 * Options storage.
	 *
	 * @var array
	 */
	private static $options = array();

	/**
	 * Access Envato API.
	 *
	 * @var object
	 */
	public static $envato_api = null;

	/**
	 * Access HelpScout API.
	 *
	 * @var object
	 */
	public static $helpscout = null;

	/**
	 * Initiaalization.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! self::$initiated ) {
			self::setup_all();
		}
	}

	/**
	 * Initializes All WordPress hooks.
	 *
	 * @static
	 */
	private static function setup_all() {
		self::$initiated = true;

		// Admin init.
		add_action( 'init', array( 'Envascout_Form', 'admin_init' ) );

		// Only works when caldera forms already activated.
		if ( is_plugin_active( 'caldera-forms/caldera-core.php' ) ) {
			if ( ! is_admin() ) {
				// Cache options.
				self::$options = self::get_options();

				// Call Envato API.
				self::$envato_api = new WP_Envato_API( self::$options['envato_client_id'], self::$options['envato_client_secret'], self::$options['session_prefix'] );
				self::$helpscout = new WP_Helpscout_Api( self::$options['helpscout_api_key'], self::$options['helpscout_mailbox'] );
				self::manage_flows();
			}

			// Add caldera forms types: envato purchase item list.
			add_filter( 'caldera_forms_get_field_types', array( 'Envascout_Form', 'add_caldera_form_types' ) );

			// Caldera core init.
			remove_action( 'caldera_forms_submit_complete', array( 'Caldera_Forms_Files', 'cleanup' ) );

			// Submit caldera forms to helpscout.
			add_action( 'caldera_forms_submit_complete', array( 'Envascout_Form', 'caldera_submit_forms' ), 99 );

			// Register Stylesheet.
			add_action( 'wp_enqueue_scripts', array( 'Envascout_Form', 'wp_enqueue_scripts' ) );

			// Add [envascout_form] shortcode.
			add_shortcode( 'envascout-form', array( 'Envascout_Form', 'shortcode' ) );
		}
	}

	public static function admin_init() {
		if ( is_admin() ) {
			// Plugin Updater.
			$config = array(
				'slug' => 'envascout-form/envascout-form.php' ,
				'proper_folder_name' => 'envascout-form',
				'api_url' => 'https://api.github.com/repos/djavaweb/envascout-form',
				'raw_url' => 'https://raw.github.com/djavaweb/envascout-form/master',
				'github_url' => 'https://github.com/djavaweb/envascout-form',
				'zip_url' => 'https://github.com/djavaweb/envascout-form/archive/master.zip',
				'sslverify' => true,
				'requires' => '4.2',
				'tested' => '4.2',
				'readme' => 'README.md',
				'access_token' => '',
			);

			new WP_GitHub_Updater( $config );
		}
	}

	/**
	 * Get options from database.
	 *
	 * @return array
	 */
	public static function get_options() {
		$options = json_decode( get_option( 'envascout_options' ), true );
		return $options;
	}

	/**
	 * Register envato user's purchases of the app creator's items list
	 *
	 * @static
	 * @param array $fields Caldera form fields.
	 * @return array
	 */
	public static function add_caldera_form_types( $fields ) {
		$fields['envascout_purchase_list'] = array(
			'field' => __( 'Envascout: Envato Purchase List', 'caldera-forms' ),
			'description' => __( 'Get purchase items from buyer\'s', 'caldera-forms' ),
			'category' => __( 'Special', 'caldera-forms' ),
			'file' => ENVASCOUT_FORM_PLUGIN_DIR . 'caldera-fields/purchase-list/render-template.php',
			'setup' => array(
				'template' => ENVASCOUT_FORM_PLUGIN_DIR . 'caldera-fields/purchase-list/edit-template.php',
				'preview' => ENVASCOUT_FORM_PLUGIN_DIR . 'caldera-fields/purchase-list/preview-template.php',
			),
		);

		$fields['envascout_text_editor'] = array(
			'field' => __( 'Envascout: WP Editor', 'caldera-forms' ),
			'description' => __( 'WordPress TinyMCE Editor', 'caldera-forms' ),
			'category' => __( 'Special', 'caldera-forms' ),
			'file' => ENVASCOUT_FORM_PLUGIN_DIR . 'caldera-fields/wp-editor/render-template.php',
			'setup' => array(
				'preview' => ENVASCOUT_FORM_PLUGIN_DIR . 'caldera-fields/wp-editor/preview-template.php',
			),
		);

		return $fields;
	}

	/**
	 * Register styles.
	 *
	 * @return void
	 */
	public static function wp_enqueue_scripts() {
		wp_register_style( 'envascout', plugins_url( '/assets/css/styles.css', __FILE__ ), array(), null );
		wp_register_script( 'envascout', plugins_url( '/assets/js/scripts.js', __FILE__ ), array( 'jquery' ), null, true );
	}

	/**
	 * Register [envascout_form] shortcode
	 *
	 * @return void
	 */
	public static function shortcode() {
		global $wp;

		// Set current page as request page.
		$current_url = home_url( add_query_arg( array(), $wp->request ) );
		self::$envato_api->session_set( 'request_page', $current_url );

		// Get error message after logged in.
		$error_message = self::$envato_api->session_get( 'error_message' );

		// Add required styles and javascripts.
		wp_enqueue_style( 'envascout' );
		wp_enqueue_script( 'envascout' );

		$html = '';

		if ( ! self::$envato_api->is_authenticated() ) {
			$html .= '<div class="envascout-button-wrapper">' .
			sprintf( '<a href="%s" class="envascout-button">%s</a>', esc_url( site_url( '?envascout_action=request' ) ), esc_html( self::$options['oauth_button_label'] ) );

			if ( ! empty( $error_message ) ) {
				$html .= '<br />' .
				sprintf( '<div class="envascout-error">%s</div>', esc_html( $error_message ) );
			}

			$html .= '</div>';
		} else {
			if ( '' !== self::$options['caldera_form'] ) {
				$caldera_form = sprintf( '[caldera_form id="%s"]', self::$options['caldera_form'] );
				$html = do_shortcode( $caldera_form );
			}
		}

		return $html;
	}

	/**
	 * Submit caldera forms.
	 *
	 * @param array $form Caldera Fields.
	 * @return mixed
	 */
	public static function caldera_submit_forms( $form ) {
		global $table_prefix, $wpdb;

		// Only submit process when caldera form id is matches with settings.
		if ( $form['ID'] !== self::$options['caldera_form'] ) {
			Caldera_Forms_Files::cleanup( $form );
			return true;
		}

		// Templating loader.
		$templates = array(
			'subject' => self::$options['helpscout_subject'],
			'content' => self::$options['helpscout_content'],
			'tag' => self::$options['helpscout_tag'],
		);
		$twig = new Twig_Environment( new Twig_Loader_Array( $templates ) );

		// Get submission data.
		$data = array();
		foreach ( $form['fields'] as $field_id => $field ) {
			$data[ $field['slug'] ] = Caldera_Forms::get_field_data( $field_id, $form );
		}

		// Get purchase details by items.
		$purchase_detail = self::$envato_api->get_all_purchase_from_buyer();
		$purchase_info = array();
		$purchase_id = '';
		$item_info = array();
		$item_id = 0;

		if ( isset( $purchase_detail['purchases'] ) && ! empty( $purchase_detail['purchases'] ) ) {
			$purchase_items = $purchase_detail['purchases'];

			foreach ( $purchase_detail['purchases'] as $detail ) {
				if ( strval( $detail['item']['id'] ) === $data[ self::$options['caldera_item_id'] ] ) {
					$purchase_info[] = $detail;
					$purchase_id = $detail['code'];
					$wpdb->query( $wpdb->prepare( 'INSERT INTO `' . $table_prefix . 'envascout_purchases` (`ID`, `purchase_id`, `data`, `time`) VALUES (NULL, %s, %s, %s) ON DUPLICATE KEY UPDATE `purchase_id` = `purchase_id`;',
						$purchase_id,
						wp_json_encode( $detail ),
						time()
					) );

					$item_info = $detail['item'];
					$item_id = $detail['item']['id'];
					$wpdb->query( $wpdb->prepare( 'INSERT INTO `' . $table_prefix . 'envascout_items` (`ID`, `item_id`, `data`, `time`) VALUES (NULL, %s, %s, %s) ON DUPLICATE KEY UPDATE `item_id` = `item_id`;',
						$item_id,
						wp_json_encode( $item_info ),
						time()
					) );
				}
			}
		} else {
			$data['not_a_buyer'] = true;
		}

		// Get user full detail.
		$user_info = self::$envato_api->get_user_full_info();

		// Get Attachment.
		$attachment = array();
		if ( isset( $data[ self::$options['caldera_attachment_id'] ] ) ) {
			$attachment[] = $data[ self::$options['caldera_attachment_id'] ];
		}

		// Parsing tags.
		$tags = array();
		if ( ! empty( self::$options['helpscout_tag'] ) ) {
			$compiled_tags = $twig->render('tag', array(
				'caldera' => $data
			));

			if ( isset( $purchase_info ) ) {
				$compiled_tags = $compiled_tags = $twig->render('tag', array(
					'purchase' => $purchase_info,
				));
			}

			if ( isset( $item_info ) ) {
				$compiled_tags = $compiled_tags = $twig->render('tag', array(
					'item' => $item_info,
				));
			}

			$compiled_tags = explode( ',', $compiled_tags );
			$compiled_tags = array_map( 'trim', $compiled_tags );
			$compiled_tags = array_map( 'sanitize_title_with_dashes', $compiled_tags );

			$tags = $compiled_tags;
		}

		// Compile syntax from data.
		if ( $user_info ) {
			$subject = $twig->render( 'subject', array(
				'caldera' => $data,
			) );

			$content = $twig->render( 'content', array(
				'caldera' => $data,
			) );

			$request = self::$helpscout->compose_message(
				// Customer Info.
				array(
					'firstName' => $user_info['firstname'],
					'lastName' => $user_info['lastname'],
					'email' => $user_info['email'],
				),
				// Container.
				array(
					'subject' => $subject,
					'message' => wpautop( $content ),
				),

				// Tags.
				$tags,

				// Attachments.
				$attachment
			);

			if ( 201 === wp_remote_retrieve_response_code( $request ) ) {
				$location = wp_remote_retrieve_header( $request, 'location' );
				preg_match_all( '/https:\/\/api.helpscout.net\/v1\/conversations\/(.*)\.json/', $location, $matches, PREG_SET_ORDER, 0 );

				// Insert conversation to database.
				if ( isset( $matches[0] ) && ! empty( $matches[0][1] ) ) {
					$conversation_id = $matches[0][1];

					$wpdb->insert(
						$table_prefix . 'envascout_tickets',
						array(
							'email' => self::$envato_api->session_get( 'email' ),
							'ticket_id' => $conversation_id,
							'purchase_id' => $purchase_id,
							'item_id' => $item_id,
							'data' => wp_json_encode( $data ),
							'time' => time(),
						),
						array(
							'%s',
							'%d',
							'%s',
							'%d',
							'%s',
							'%d',
						)
					);
				}
			}
		}

		// Cleanup attachments.
		Caldera_Forms_Files::cleanup( $form );
	}

	/**
	 * Manage routers.
	 *
	 * @return void
	 */
	public static function manage_flows() {
		global $table_prefix, $wpdb;

		if ( ! is_admin() && isset( $_GET['envascout_action'] ) ) { // WPCS: CSRF ok.
			// Get access_token expiration time.
			$expires_in = self::$envato_api->session_get( 'expires_in' );

			// Clear when expired.
			if ( isset( $expires_in ) && self::$envato_api->session_get( 'expires_in' ) < time() ) {
				self::$envato_api->session_clear();
			}

			switch ( $_GET['envascout_action'] ) { // WPCS: CSRF ok.
				case 'debug':
					print_r( $_SESSION );
					die();

				case 'request':
					$oauth_url = self::$envato_api->oauth_url( site_url( '?envascout_action=confirm' ) );
					header( 'Location: ' . $oauth_url );
					die();

				case 'clear_session':
					self::$envato_api->session_clear();
					self::$helpscout->clear_session();
					break;

				case 'confirm':
					$error_message = '';
					$redirect_to = '';

					if ( self::$envato_api->is_authenticated() ) {
						$redirect_to = self::$envato_api->session_get( 'request_page' );
					}

					// Callback to get token.
					if ( isset( $_GET['code'] ) ) { // WPCS: CSRF ok.
						// Get access token.
						$token = self::$envato_api->request_token( $_GET['code'] ); // WPCS: CSRF ok.

						// If succeed redirect again to new-ticket page.
						if ( self::$envato_api->is_token_valid( $token ) ) {
							// Save user to database.
							$user_info = self::$envato_api->get_user_full_info();

							// Usually it give erros when envato server down, show it's safe to check it first.
							if ( isset( $user_info ) && isset( $user_info['email'] ) && isset( $user_info['username'] ) ) {
								$redirect_to = self::$envato_api->session_get( 'request_page' );
								self::$envato_api->session_set( 'email', $user_info['email'] );

								$wpdb->query( $wpdb->prepare( 'INSERT INTO `' . $table_prefix . 'envascout_users` (`ID`,`username`, `email`, `firstname`, `lastname`, `image_url`, `country`) VALUES (NULL, %s, %s, %s, %s, %s, %s) ON DUPLICATE KEY UPDATE `username` = `username`;',
									$user_info['username'],
									$user_info['email'],
									$user_info['firstname'],
									$user_info['lastname'],
									$user_info['image'],
									$user_info['country']
								) );
							}
						} else {
							$error_message = 'Invalid Authentication';
						}
					}

					if ( isset( $_GET['error_description'] ) ) { // WPCS: CSRF ok.
						$error_message = $_GET['error_description']; // WPCS: CSRF ok.
					}

					if ( ! empty( $error_message ) ) {
						self::$envato_api->session_set( 'error_message', $error_message );
						$redirect_to = self::$envato_api->session_get( 'request_page' );
					}

					if ( empty( $error_message ) && empty( $redirect_to ) ) {
						$redirect_to = site_url();
					}

					if ( ! empty( $redirect_to ) ) {
						header( 'Location: ' . esc_url( $redirect_to ) );
						die();
					}
					break;

				case 'helpscout_app':
					header( 'Content-type: application/json' );

					// Data Request from helpscout.
					$php_input = json_decode( file_get_contents('php://input'), true );

					// Templating loader.
					$html = '';
					$templates = array(
						'dynamic_app' => self::$options['helpscout_dynamic_app'],
					);

					$twig = new Twig_Environment( new Twig_Loader_Array( $templates ), array(
						'debug' => true,
					) );
					$twig->addExtension(new Twig_Extension_Debug());

					// Get email from db.
					if ( isset( $php_input['ticket' ] ) ) {
						$ticket_id = $php_input['ticket']['id'];

						// Get ticket and users data.
						$ticket = $wpdb->get_row( $wpdb->prepare('SELECT * FROM `' . $table_prefix . 'envascout_tickets` WHERE `ticket_id` = %d', $ticket_id) );

						if ( $ticket ) {
							$data = json_decode( $ticket->data );
							$purchase_id = $ticket->purchase_id;
							$item_id = $ticket->item_id;
							$email = $ticket->email;

							// Get users data.
							$user_info = $wpdb->get_row( $wpdb->prepare('SELECT * FROM `' . $table_prefix . 'envascout_users` WHERE `email` = %s', $email) );

							// Get purchase data.
							$purchases = $wpdb->get_results( $wpdb->prepare('SELECT * FROM `' . $table_prefix . 'envascout_purchases` WHERE `purchase_id` = %s', $purchase_id), ARRAY_A );
							$purchase_info = [];
							$support_expired = false;
							if ( $purchases ) {
								foreach( $purchases as $index => $info ) {
									$_purchase_info = json_decode( $info['data'], true );
									$purchase_info[$index + 1] = $_purchase_info;

									if ( time() > strtotime($_purchase_info['supported_until'] ) ) {
										$support_expired = true;
									}
								}
							}

							// // Get item data.
							$item_info = $wpdb->get_row( $wpdb->prepare('SELECT * FROM `' . $table_prefix . 'envascout_items` WHERE `item_id` = %s', $item_id) );
							if ( $item_info ) {
								$item_info = json_decode( $item_info->data, true );
							}

							$html = $twig->render( 'dynamic_app', array(
								'user' => $user_info,
								'item' => $item_info,
								'purchase' => $purchase_info,
								'not_buyer' => ( isset( $data->not_a_buyer ) ) ? $data->not_a_buyer : false,
								'support_expired' => $support_expired,
							) );
						}

						echo wp_json_encode( array(
							'html' => wpautop( stripslashes_deep( $html ) ),
						) );
					}
					die();
			}
		}
	}

	/**
	 * Attached to activate_{ plugin_basename( __FILES__ ) } by register_activation_hook().
	 *
	 * @static
	 */
	public static function plugin_activation() {
		global $table_prefix, $wpdb;

		if ( ! is_plugin_active( 'caldera-forms/caldera-core.php' ) ) {
			load_plugin_textdomain( 'envascout-form' );
			$message = '<strong>' . __( 'Envscout Form requires Caldera Forms to be Activated.' , 'envascout-form' ) . '</strong> ';
			self::raw_html( $message );
		}

		// Add default options.
		$default_options = array(
			'envato_client_secret' => '',
			'envato_client_id' => '',
			'oauth_button_label' => 'Login with Envato to Open New Ticket',
			'caldera_form' => '',
			'caldera_item_id' => '',
			'caldera_attachment_id' => '',
			'session_prefix' => 'session',
			'helpscout_api_key' => '',
			'helpscout_mailbox' => 0,
			'helpscout_subject' => '{{caldera.subject}}',
			'helpscout_content' => '{{caldera.content}}',
			'helpscout_tag' => '{{item.name}}',
			'helpscout_dynamic_app' => "<img src=\"%image_url%\" />\r\n \r\n <div class=\"toggleGroup open\">\r\n <h4><a href=\"#\" class=\"toggleBtn\"><i class=\"icon-person\"></i>Profile</a></h4>\r\n <div class=\"toggle indent\">\r\n <ul class=\"unstyled\">\r\n <li><strong>Username</strong><br />%username%</li>\r\n <li><strong>Name</strong><br />%firstname% %lastname%</li>\r\n <li><strong>Country</strong><br />%country%</li>\r\n </ul>\r\n </div>\r\n </div>\r\n \r\n <div class=\"toggleGroup\">\r\n <h4><a href=\"#\" class=\"toggleBtn\"><i class=\"icon-tag\"></i>Item Info</a></h4>\r\n <div class=\"toggle indent\">\r\n %item_info%\r\n </div>\r\n </div>\r\n \r\n<div class=\"toggleGroup\">\r\n <h4><a href=\"#\" class=\"toggleBtn\"><i class=\"icon-cart\"></i>Purchase Info</a></h4>\r\n <div class=\"toggle indent\">\r\n %purchase_info%\r\n </div>\r\n </div>\r\n",
		);
		add_option( 'envascout_options', wp_json_encode( $default_options ), '', false );
		update_option( 'envascout_version',  ENVASCOUT_FORM_VER );

		// Add database structure.
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		}

		// Create db structures.
		$tables = array();

		// User structures.
		$user_table = $table_prefix . 'envascout_users';
		if ( $user_table !== $wpdb->get_var( 'SHOW TABLES LIKE \'' . $user_table . '\'' ) ) {
			$sql = 'CREATE TABLE `' . $user_table . '` (' .
				'`ID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,' .
				'`username` varchar(60) NOT NULL,' .
				'`email` varchar(255) NOT NULL,' .
				'`firstname` varchar(60) NOT NULL,' .
				'`lastname` varchar(60) NOT NULL,' .
				'`image_url` varchar(255) CHARACTER SET utf16 NOT NULL,' .
				'`country` varchar(60) NOT NULL,' .
				'PRIMARY KEY (`ID`),' .
				'UNIQUE(`username`),' .
				'KEY `email` (`email`)' .
			') ENGINE=InnoDB DEFAULT CHARSET=utf8;';
			dbDelta( $sql );
		}

		// Ticket structures.
		$ticket_table = $table_prefix . 'envascout_tickets';
		if ( $ticket_table !== $wpdb->get_var( 'SHOW TABLES LIKE \'' . $ticket_table . '\'' ) ) {
			$sql = 'CREATE TABLE `' . $ticket_table . '` (' .
				'`ID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,' .
				'`ticket_id` varchar(60) NOT NULL,' .
				'`purchase_id` varchar(255) NOT NULL,' .
				'`item_id` varchar(60) NOT NULL,' .
				'`email` varchar(60) NOT NULL,' .
				'`data` text NOT NULL,' .
				'`time` varchar(60) NOT NULL,' .
				'PRIMARY KEY (`ID`),' .
				'KEY `ticket_id` (`ticket_id`)' .
			') ENGINE=InnoDB DEFAULT CHARSET=utf8;';
			dbDelta( $sql );
		}

		// Item structures.
		$item_table = $table_prefix . 'envascout_items';
		if ( $item_table !== $wpdb->get_var( 'SHOW TABLES LIKE \'' . $item_table . '\'' ) ) {
			$sql = 'CREATE TABLE `' . $item_table . '` (' .
				'`ID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,' .
				'`item_id` varchar(60) NOT NULL,' .
				'`data` text NOT NULL,' .
				'`time` varchar(60) NOT NULL,' .
				'PRIMARY KEY (`ID`),' .
				'UNIQUE(`item_id`)' .
			') ENGINE=InnoDB DEFAULT CHARSET=utf8;';
			dbDelta( $sql );
		}

		// Purchase structures
		$purchase_table = $table_prefix . 'envascout_purchases';
		if ( $purchase_table !== $wpdb->get_var( 'SHOW TABLES LIKE \'' . $purchase_table . '\'' ) ) {
			$sql = 'CREATE TABLE `' . $purchase_table . '` (' .
				'`ID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,' .
				'`purchase_id` varchar(60) NOT NULL,' .
				'`data` text NOT NULL,' .
				'`time` varchar(60) NOT NULL,' .
				'PRIMARY KEY (`ID`),' .
				'UNIQUE(`purchase_id`)' .
			') ENGINE=InnoDB DEFAULT CHARSET=utf8;';
			dbDelta( $sql );
		}
	}

	/**
	 * Removes all connection options.
	 *
	 * @static
	 */
	public static function plugin_deactivation() {
		// Nothing.
	}

	/**
	 * Display raw html on activate or deactivation.
	 *
	 * @param string  $message Message.
	 * @param boolean $deactivate Is deactivation.
	 * @return void
	 */
	private static function raw_html( $message, $deactivate = true ) {
	?>
<!doctype html>
<html>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<style>
* {
	text-align: center;
	margin: 0;
	padding: 0;
	font-family: "Lucida Grande",Verdana,Arial,"Bitstream Vera Sans",sans-serif;
}
p {
	margin-top: 1em;
	font-size: 18px;
}
</style>
<body>
<p><?php echo wp_kses( $message, array( 'strong' ) ); ?></p>
</body>
</html>
	<?php
	if ( $deactivate ) {
		$plugins = get_option( 'active_plugins' );
		$akismet = plugin_basename( ENVASCOUT_FORM_PLUGIN_DIR . 'envascout-form.php' );
		$update  = false;
		foreach ( $plugins as $i => $plugin ) {
			if ( $plugin === $akismet ) {
				$plugins[ $i ] = false;
				$update = true;
			}
		}

		if ( $update ) {
			update_option( 'active_plugins', array_filter( $plugins ) );
		}
	}
		exit;
	}

	/**
	 * Viewer in MVC.
	 *
	 * @param string $name Filename.
	 * @param array  $args Additional Arguments.
	 * @return void
	 */
	public static function view( $name, array $args = array() ) {
		$args = apply_filters( 'envascout_form_view_arguments', $args, $name );

		foreach ( $args as $key => $val ) {
			$$key = $val;
		}

		load_plugin_textdomain( 'envascout-form' );
		$file = ENVASCOUT_FORM_PLUGIN_DIR . 'views/' . $name . '.php';
		include( $file );
	}
}
