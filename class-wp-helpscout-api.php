<?php
/**
 * Helpscout API Class in WordPress.
 *
 * @package WordPress Helpscout API Class
 * @since 1.0.0
 */

/**
 * WP_Helpscout_Api Class.
 */
class WP_Helpscout_Api {
	/**
	 * Helpscout Endpoint URL.
	 *
	 * @var string
	 */
	private $endpoint_url = 'https://api.helpscout.net/';

	/**
	 * Helpscout API Version.
	 *
	 * @var string
	 */
	private $api_version = 'v1';

	/**
	 * API Key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Mailbox ID
	 *
	 * @var string
	 */
	private $mailbox_id;

	/**
	 * Transient prefix used for save wp trasient data.
	 *
	 * @var string
	 */
	private $transient_prefix = 'helpscout_api_';

	/**
	 * Constructor
	 *
	 * @param string $api_key API Key.
	 * @param string $mailbox_id Mailbox ID.
	 * @throws Exception If client ID or client Secret is not given in arguments.
	 */
	public function __construct( $api_key = '', $mailbox_id = 0 ) {
		if ( '' === $api_key ) {
			return new WP_Error( 'Please set API KEY correctly.' );
		}

		$this->api_key = $api_key;
		$this->mailbox_id = $mailbox_id;
	}

	/**
	 * Get API URL.
	 *
	 * @param string $path JSON Path.
	 * @return string
	 */
	public function api_url( $path ) {
		return $this->endpoint_url . $this->api_version . '/' . $path . '.json';
	}

	/**
	 * Clear transient.
	 *
	 * @return void
	 */
	public function clear_session() {
		global $wpdb;

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
	 * Get mailbox list
	 *
	 * @return array
	 */
	public function get_mailbox_list() {
		$endpoint_url = $this->api_url( 'mailboxes' );
		$transient_key = $this->transient_prefix . sanitize_title_with_dashes( $endpoint_url );
		$mailbox_list = get_transient( $transient_key );

		if ( empty( $mailbox_list ) ) {
			// New request.
			$request = wp_remote_request(
				$endpoint_url, array(
					'method' => 'GET',
					'timeout' => 45,
					'redirection' => 5,
					'headers' => array(
						'Accept' => 'application/json',
						'Content-Type' => 'application/json',
						'Authorization' => 'Basic ' . base64_encode( $this->api_key . ':X' ),
					),
				)
			);

			// Save cache.
			$mailbox_list = wp_remote_retrieve_body( $request );

			// Valid for one hour only.
			set_transient( $transient_key, $mailbox_list, 60 * 60 );
		}

		return json_decode( $mailbox_list, true );
	}

	/**
	 * Create attachment, upload to helpscout.
	 *
	 * @param string $item File URL uploaded from wp-content.
	 * @return array
	 */
	public function create_attachment( $item ) {
		$filename = str_replace( content_url(), '', $item );
		$filepath = WP_CONTENT_DIR . $filename;

		if ( file_exists( $filepath ) ) {
			$mime = wp_check_filetype( $filepath );
			$data = base64_encode( file_get_contents( $filepath ) );
			$fields = array(
				'fileName' => basename( $filepath ),
				'mimeType' => $mime['type'],
				'data' => $data,
			);

			$request = wp_remote_post( $this->api_url( 'attachments' ), array(
				'method' => 'POST',
				'timeout' => 45,
				'redirection' => 5,
				'headers' => array(
					'Accept' => 'application/json',
					'Content-Type' => 'application/json',
					'Authorization' => 'Basic ' . base64_encode( $this->api_key . ':X' ),
				),
				'body' => wp_json_encode( $fields ),
			) );

			$response = wp_remote_retrieve_body( $request );

			if ( $response ) {
				$response = json_decode( $response, true );

				if ( isset( $response['item'] ) ) {
					return $response['item'];
				}
			}
		}
	}

	/**
	 * New ticket request
	 *
	 * @param array $customer Customer data contains first, last name and email.
	 * @param array $thread Thread contains subject and message.
	 * @param array $tags Tags.
	 * @param array $attachments Optional Attachments.
	 * @return array
	 */
	public function compose_message( $customer = array(), $thread = array(), $tags = array(), $attachments = array() ) {
		$customer['type'] = 'customer';
		$utc_time = gmdate( 'Y-m-d\TH:i:s\Z' );

		$attachments = array_map( array( $this, 'create_attachment' ), $attachments );
		$attachments = array_filter( $attachments );

		$fields = array(
			'type' => 'email',
			'status' => 'active',
			'customer' => $customer,
			'mailbox' => array(
				'id' => $this->mailbox_id,
			),
			'tags' => $tags,
			'createdAt' => $utc_time,
			'subject' => $thread['subject'],
			'threads' => array(
				array(
					'type' => 'customer',
					'status' => 'active',
					'body' => $thread['message'],
					'createdBy' => $customer,
					'createdAt' => $utc_time,
					'attachments' => $attachments,
				),
			),
		);

		$fields = wp_json_encode( $fields );

		$request = wp_safe_remote_post(
			$this->api_url( 'conversations' ), array(
				'method' => 'POST',
				'timeout' => 45,
				'redirection' => 5,
				'sslverify' => false,
				'cookies' => array(),
				'headers' => array(
					'Accept' => 'application/json',
					'Content-Type' => 'application/json',
					'Content-Length' => strlen( $fields ),
					'Authorization' => 'Basic ' . base64_encode( $this->api_key . ':X' ),
				),
				'body' => $fields,
			)
		);

		return $request;
	}
}
