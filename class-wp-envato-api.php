<?php
/**
 * Envato API Class in WordPress.
 *
 * @package WordPress Envato API Class
 * @since 1.0.0
 */

/**
 * WP_Envato_API Class.
 */
class WP_Envato_API {
	/**
	 * Envato Endpoint URL.
	 *
	 * @var string
	 */
	private $endpoint_url = 'https://api.envato.com/';

	/**
	 * Envato API Version.
	 *
	 * @var string
	 */
	private $api_version = 'v3';

	/**
	 * Client ID.
	 *
	 * @var string
	 */
	private $client_id;

	/**
	 * Client Secret.
	 *
	 * @var string
	 */
	private $client_secret;

	/**
	 * Session Prefix.
	 *
	 * @var string
	 */
	private $session_prefix;

	/**
	 * Transient prefix used for save wp trasient data.
	 *
	 * @var string
	 */
	private $transient_prefix = 'envato_api_';

	/**
	 * Constructor
	 *
	 * @param string $client_id Client ID.
	 * @param string $client_secret Client Secret.
	 * @param string $session_prefix $_SESSION prefix.
	 * @throws Exception If client ID or client Secret is not given in arguments.
	 */
	public function __construct( $client_id = '', $client_secret = '', $session_prefix = '' ) {
		if ( '' === $client_id || '' === $client_secret ) {
			throw new Exception( 'Please set CLIENT ID and CLIENT SECRET correctly.' );
		}

		$this->client_id = $client_id;
		$this->client_secret = $client_secret;
		$this->session_prefix = $session_prefix;

		// Register session.
		$this->session_handshake();
	}

	/**
	 * Check is session already started yet?
	 * If not started, then started it
	 *
	 * @return void
	 */
	public function session_handshake() {
		if ( version_compare( phpversion(), '5.4.0', '<' ) ) {
			if ( ! session_id() ) {
				session_start();
			}
		} else {
			if ( PHP_SESSION_NONE === session_status() ) {
				session_start();
			}
		}
	}

	/**
	 * Get authentication session.
	 *
	 * @param string $key Session Key.
	 * @return string
	 */
	public function session_get( $key = '' ) {
		$session_key = $this->session_prefix . '.' . $key;

		if ( isset( $_SESSION[ $session_key ] ) ) {
			return $_SESSION[ $session_key ];
		}
	}

	/**
	 * Set session with prefix
	 *
	 * @param string $key Session Key.
	 * @param string $value Session Value.
	 * @return void
	 */
	public function session_set( $key = '', $value = '' ) {
		$_SESSION[ $this->session_prefix . '.' . $key ] = $value;
	}

	/**
	 * Clear all $_SESSION.
	 *
	 * @return void
	 */
	public function session_clear() {
		global $wpdb;
		if ( ! isset( $_SESSION ) ) {
			return;
		}

		foreach ( $_SESSION as $key => $value ) {
			if ( false !== strpos( $key, $this->session_prefix ) ) {
				unset( $_SESSION[ $key ] );
			}
		}

		$result = $wpdb->get_results( $wpdb->prepare( 'SELECT `option_name` AS `name`, `option_value` AS `value` FROM ' . $wpdb->options . ' WHERE `option_name` LIKE %s ORDER BY `option_name`', '%' . $this->transient_prefix . '%' ), ARRAY_A );

		if ( $result ) {
			foreach ( $result as $item ) {
				if ( strpos( $item['name'], $this->transient_prefix ) ) {
					$transient_key = str_replace( '_transient_', '', $item['name'] );
					delete_transient( $transient_key );
				}
			}
		}
	}

	/**
	 * Get API URL.
	 *
	 * @param string  $api_endpoint Page.
	 * @param boolean $is_path Is Page or using API.
	 * @param string  $version API Version.
	 * @return string
	 */
	public function api_url( $api_endpoint, $is_path = false, $version = '' ) {
		$api_version = ( isset( $version ) && ! empty( $version ) ) ? $version : $this->api_version;
		$final_url = $is_path ? $api_endpoint : $api_version . '/' . $api_endpoint;
		return $this->endpoint_url . $final_url;
	}

	/**
	 * Get oAuth URL.
	 *
	 * @param string $redirect_uri Confirmation URL After granted to oAuth.
	 * @return string
	 */
	public function oauth_url( $redirect_uri = '' ) {
		return 'https://api.envato.com/authorization?response_type=code&client_id=' . $this->client_id . '&redirect_uri=' . $redirect_uri;
	}

	/**
	 * Request token after signed in
	 *
	 * @param string $code Code Callback From Envato.
	 * @throws Exception If code id is not give.
	 * @return array
	 */
	public function request_token( $code = '' ) {
		if ( '' === $code ) {
			throw new Exception( 'No Code ID.' );
		}

		$request = wp_safe_remote_post(
			$this->api_url( 'token', true ), array(
				'method' => 'POST',
				'timeout' => 45,
				'redirection' => 5,
				'blocking' => true,
				'cookies' => array(),
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body' => build_query(
					array(
						'grant_type' => 'authorization_code',
						'code' => $code,
						'client_id' => $this->client_id,
						'client_secret' => $this->client_secret,
					)
				),
			)
		);

		if ( is_wp_error( $request ) ) {
			return array(
				'message' => $request->get_error_message(),
			);
		}

		$response = wp_remote_retrieve_body( $request );

		if ( $response ) {
			return json_decode( $response, true );
		}
	}

	/**
	 * Request API
	 *
	 * @param string $path API Path.
	 * @param string $api_version API Version.
	 * @param int    $transient_duration Cache Duration in Minutes.
	 * @return array
	 */
	public function request_api( $path, $api_version = '', $transient_duration = 15 ) {
		$endpoint_url = $this->api_url( $path, false, $api_version );
		$transient_key = sanitize_title_with_dashes( $endpoint_url );
		$data = get_transient( $this->transient_prefix . $transient_key );

		if ( false === $data ) {
			$request = wp_remote_get($endpoint_url, array(
				'timeout' => 45,
				'redirection' => 5,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->session_get( 'access_token' ),
				),
			) );

			if ( is_wp_error( $request ) ) {
				return array(
					'message' => $request->get_error_message(),
				);
			}

			$response = wp_remote_retrieve_body( $request );
			$status = wp_remote_retrieve_response_code( $request );

			if ( 502 === $status ) {
				echo $endpoint_url;
				print_r( $response );
				die();
			}

			// Return only if response is valid.
			if ( isset( $response ) ) {
				set_transient( $this->transient_prefix . $transient_key, $response, 60 * $transient_duration );
				$data = $response;
			}
		}

		if ( $data ) {
			return json_decode( $data, true );
		}
	}

	/**
	 * Check if request token whether valid or invalid.
	 *
	 * @param array $data HTTP Request Callback.
	 * @return boolean
	 */
	public function is_token_valid( $data = array() ) {
		if ( empty( $data ) || ! isset( $data['access_token'] ) ) {
			return false;
		}

		// OK.
		// Push session with detheme prefix. e.g 'detheme.access_token'.
		// Except 'expires_in', we will add timestamp later.
		foreach ( $data as $key => $value ) {
			if ( 'expires_in' !== $key ) {
				$this->session_set( $key, $value );
			}
		}

		// Set expires_in as timestamp.
		$this->session_set( 'expires_in', time() + $data['expires_in'] );
		return true;
	}

	/**
	 * Check whether session has an access token.
	 *
	 * @return boolean
	 */
	public function is_authenticated() {
		$access_token = $this->session_get( 'access_token' );
		return isset( $access_token ) && ! empty( $access_token );
	}

	/**
	 * Get all purchase from author's app.
	 *
	 * @return array
	 */
	public function get_all_purchase_from_buyer() {
		return $this->request_api( 'market/buyer/purchases' );
	}

	/**
	 * Get product item details.
	 *
	 * @param int|string $item_id Envato item ID.
	 * @return string
	 */
	public function get_item( $item_id = 0 ) {
		$response = $this->request_api( 'market/catalog/item?id=' . $item_id, 'v3', 1000 );
		if ( $response && ! isset( $response['message'] ) ) {
			return $response;
		}
	}

	/**
	 * Get username.
	 *
	 * @return string
	 */
	public function get_username() {
		$response = $this->request_api( 'market/private/user/username.json', 'v1' );
		if ( $response && ! isset( $response['message'] ) && isset( $response['username'] ) ) {
			return $response['username'];
		}
	}

	/**
	 * Get user information.
	 *
	 * @return string
	 */
	public function get_user_info() {
		return $this->request_api( 'market/private/user/account.json', 'v1' );
	}

	/**
	 * Get user email.
	 *
	 * @return string
	 */
	public function get_user_email() {
		$response = $this->request_api( 'market/private/user/email.json', 'v1' );
		if ( $response && ! isset( $response['message'] ) && isset( $response['email'] ) ) {
			return $response['email'];
		}
	}

	/**
	 * Get user full information, includes email and username.
	 *
	 * @return array
	 */
	public function get_user_full_info() {
		$response = $this->get_user_info();

		if ( $response && isset( $response['account'] ) ) {
			$user_info = $response['account'];
			$user_email = $this->get_user_email();
			$username = $this->get_username();

			if ( isset( $user_email ) ) {
				$user_info['email'] = $user_email;
			}

			if ( isset( $username ) ) {
				$user_info['username'] = $username;
			}

			// Change surname as last name.
			$user_info['lastname'] = $user_info['surname'];
			unset( $user_info['surname'] );

			return $user_info;
		}
	}
}
