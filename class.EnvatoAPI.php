<?php


/**
 * Exception handling class.
 */
class EnvatoException extends Exception {
}


class EnvatoAPI {

	private static $instance = null;

	public static function getInstance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private $_api_url = 'https://api.envato.com/';

	private $_client_id = false;
	private $_client_secret = false;
	private $_personal_token = false;
	private $_redirect_url = false;
	private $_cookie = false;
	private $token = false; // token returned from oauth
	private $ch = false; // curl
	private $_mode = 'oauth'; // personal or oauth

	public function set_client_id( $token ) {
		$this->_client_id = $token;
	}

	public function set_client_secret( $token ) {
		$this->_client_secret = $token;
	}

	public function set_personal_token( $token ) {
		$this->_personal_token = $token;
	}

	public function set_redirect_url( $token ) {
		$this->_redirect_url = $token;
	}

	public function set_cookie( $cookie ) {
		$this->_cookie = $cookie;
	}

	public function set_mode( $mode ){
		$this->_mode = $mode;
	}

	public function api( $endpoint, $params = array(), $personal = true ) {
		$headers = array();
		if ( $this->_mode == 'personal' && ! empty( $this->_personal_token ) ) {
			// personal request with personal token
			$headers[] = 'Authorization: Bearer ' . $this->_personal_token;
		} else if ( ! empty( $this->token['access_token'] ) ) {
			// oauth request with customer oauth token
			$headers[] = 'Authorization: Bearer ' . $this->token['access_token'];
		}
		$response = $this->get_url($this->_api_url . $endpoint,false,$headers);
		if ( $response ) {
			$body   = @json_decode( $response, true );
			if ( ! $body ) {
				echo 'Error';
			}
			return $body;
		} else{
			echo 'API Error';
		}

		return false;
	}


	public function curl_init() {
		if ( ! function_exists( 'curl_init' ) ) {
			echo 'Please contact hosting provider and enable CURL for PHP';

			return false;
		}
		$this->ch = curl_init();
		curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, true );
		@curl_setopt( $this->ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $this->ch, CURLOPT_CONNECTTIMEOUT, 10 );
		curl_setopt( $this->ch, CURLOPT_TIMEOUT, 20 );
		curl_setopt( $this->ch, CURLOPT_HEADER, false );
		curl_setopt( $this->ch, CURLOPT_USERAGENT, "Envato Simple PHP Class dtbaker" );
	}

	public function get_url( $url, $post = false, $extra_headers = array() ) {

		if ( $this->ch ) {
			curl_close( $this->ch );
		}
		$this->curl_init();
		curl_setopt( $this->ch, CURLOPT_URL, $url );
		if ( $extra_headers ) {
			curl_setopt( $this->ch, CURLOPT_HTTPHEADER, $extra_headers );
		}
		if ( is_string( $post ) && strlen( $post ) ) {
			curl_setopt( $this->ch, CURLOPT_POST, true );
			curl_setopt( $this->ch, CURLOPT_POSTFIELDS, $post );
		} else if ( is_array( $post ) && count( $post ) ) {
			curl_setopt( $this->ch, CURLOPT_POST, true );
			curl_setopt( $this->ch, CURLOPT_POSTFIELDS, $post );
		} else {
			curl_setopt( $this->ch, CURLOPT_POST, 0 );
		}

		return curl_exec( $this->ch );
	}

	/**
	 * OAUTH STUFF
	 */

	public function get_authorization_url() {
		return 'https://api.envato.com/authorization?response_type=code&client_id=' . $this->_client_id . "&redirect_uri=" . urlencode( $this->_redirect_url );
	}

	public function get_token_url() {
		return 'https://api.envato.com/token';
	}

	public function get_authentication( $code ) {
		$url                         = $this->get_token_url();
		$parameters                  = array();
		$parameters['grant_type']    = "authorization_code";
		$parameters['code']          = $code;
		$parameters['redirect_uri']  = $this->_redirect_url;
		$parameters['client_id']     = $this->_client_id;
		$parameters['client_secret'] = $this->_client_secret;
		$fields_string               = '';
		foreach ( $parameters as $key => $value ) {
			$fields_string .= $key . '=' . urlencode( $value ) . '&';
		}
		try {
			$response = $this->get_url( $url, $fields_string );
		} catch ( EnvatoException $e ) {
			echo 'OAuth API Fail: ' . $e->__toString();

			return false;
		}
		$this->token = json_decode( $response, true );

		return $this->token;
	}

	public function set_manual_token( $token ) {
		$this->token = $token;
	}

	public function refresh_token() {
		$url = $this->get_token_url();

		$parameters               = array();
		$parameters['grant_type'] = "refresh_token";

		$parameters['refresh_token'] = $this->token['refresh_token'];
		$parameters['redirect_uri']  = $this->_redirect_url;
		$parameters['client_id']     = $this->_client_id;
		$parameters['client_secret'] = $this->_client_secret;

		$fields_string = '';
		foreach ( $parameters as $key => $value ) {
			$fields_string .= $key . '=' . urlencode( $value ) . '&';
		}
		try {
			$response = $this->get_url( $url, $fields_string );
		} catch ( EnvatoException $e ) {
			echo 'OAuth API Fail: ' . $e->__toString();

			return false;
		}
		$new_token                   = json_decode( $response, true );
		$this->token['access_token'] = $new_token['access_token'];

		return $this->token['access_token'];
	}


}