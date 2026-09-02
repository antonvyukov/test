<?php
/**
 * Authenticated HTTP helpers. GitHub zipballs 302 to a signed URL on
 * another host — Authorization must not follow that redirect.
 *
 * @package PrivateRepoUpdater
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRU_Http {

	/**
	 * @param string $url
	 * @param array<string, string> $headers
	 * @param array<string, mixed> $args extra wp_remote_get args
	 * @return array|\WP_Error WP HTTP response
	 */
	public static function get_json( $url, $headers = array(), $args = array() ) {
		$defaults = array(
			'timeout'     => 20,
			'redirection' => 3,
			'sslverify'   => true,
			'headers'     => array_merge(
				array(
					'Accept'     => 'application/json',
					'User-Agent' => self::user_agent(),
				),
				$headers
			),
		);
		$response = wp_remote_get( $url, array_replace_recursive( $defaults, $args ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$body = wp_remote_retrieve_body( $response );
			$hint = '';
			if ( $code === 401 || $code === 403 ) {
				$hint = ' ' . __( 'Check the access token and repository permissions.', 'private-repo-updater' );
			} elseif ( $code === 404 ) {
				$hint = ' ' . __( 'Repository, release, or URL was not found.', 'private-repo-updater' );
			}
			return new WP_Error(
				'pru_http',
				sprintf(
					/* translators: 1: HTTP status, 2: URL */
					__( 'Request failed (HTTP %1$d) for %2$s.', 'private-repo-updater' ),
					$code,
					self::redact_url( $url )
				) . $hint,
				array(
					'status' => $code,
					'body'   => substr( (string) $body, 0, 500 ),
				)
			);
		}
		return $response;
	}

	/**
	 * @param string $url
	 * @param array<string, string> $headers
	 * @return array<string, mixed>|\WP_Error decoded JSON object as array
	 */
	public static function get_decoded( $url, $headers = array() ) {
		$response = self::get_json( $url, $headers );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'pru_json', __( 'Remote response was not valid JSON.', 'private-repo-updater' ) );
		}
		return $decoded;
	}

	/**
	 * Download a package, sending auth only while staying on the original origin.
	 *
	 * @param string $url
	 * @param array<string, string> $headers
	 * @param int $timeout
	 * @return string|\WP_Error path to temp file
	 */
	public static function download( $url, $headers = array(), $timeout = 300 ) {
		$tmp = wp_tempnam( $url );
		if ( ! $tmp ) {
			return new WP_Error( 'pru_tmp', __( 'Could not create a temporary file.', 'private-repo-updater' ) );
		}

		$current  = $url;
		$use_auth = true;
		$max      = 5;

		for ( $i = 0; $i < $max; $i++ ) {
			$request_headers = array_merge(
				array( 'User-Agent' => self::user_agent() ),
				$use_auth ? $headers : array()
			);

			$response = wp_remote_get(
				$current,
				array(
					'timeout'     => $timeout,
					'redirection' => 0,
					'sslverify'   => true,
					'headers'     => $request_headers,
					'stream'      => true,
					'filename'    => $tmp,
				)
			);

			if ( is_wp_error( $response ) ) {
				self::unlink( $tmp );
				return $response;
			}

			$code     = (int) wp_remote_retrieve_response_code( $response );
			$location = (string) wp_remote_retrieve_header( $response, 'location' );
			$step     = self::next_download_step( $current, $code, $location );

			if ( isset( $step['error'] ) ) {
				self::unlink( $tmp );
				return new WP_Error(
					'pru_download',
					sprintf(
						/* translators: 1: HTTP status, 2: URL */
						__( 'Download failed (HTTP %1$d) for %2$s.', 'private-repo-updater' ),
						$code,
						self::redact_url( $current )
					)
				);
			}

			if ( ! empty( $step['done'] ) ) {
				if ( ! file_exists( $tmp ) || filesize( $tmp ) < 22 ) {
					self::unlink( $tmp );
					return new WP_Error( 'pru_empty', __( 'Downloaded package was empty.', 'private-repo-updater' ) );
				}
				return $tmp;
			}

			$current  = $step['url'];
			$use_auth = ! empty( $step['use_auth'] );
		}

		self::unlink( $tmp );
		return new WP_Error( 'pru_redirects', __( 'Too many redirects while downloading the package.', 'private-repo-updater' ) );
	}

	/**
	 * Pure redirect/auth policy used by download() — unit-tested.
	 *
	 * @param string $current_url
	 * @param int $status_code
	 * @param string $location
	 * @return array<string, mixed>
	 */
	public static function next_download_step( $current_url, $status_code, $location ) {
		if ( $status_code >= 300 && $status_code < 400 ) {
			if ( $location === '' ) {
				return array( 'error' => 'redirect-without-location' );
			}
			if ( strpos( $location, 'http' ) !== 0 ) {
				$parts    = wp_parse_url( $current_url );
				$scheme   = $parts['scheme'] ?? 'https';
				$host     = $parts['host'] ?? '';
				$port     = empty( $parts['port'] ) ? '' : ':' . $parts['port'];
				$base     = $scheme . '://' . $host . $port;
				$location = ( isset( $location[0] ) && $location[0] === '/' ) ? $base . $location : $base . '/' . $location;
			}
			return array(
				'url'      => $location,
				'use_auth' => self::same_origin( $current_url, $location ),
			);
		}
		if ( $status_code === 200 ) {
			return array( 'done' => true );
		}
		return array( 'error' => 'http-' . $status_code );
	}

	/**
	 * @param string $a
	 * @param string $b
	 * @return bool
	 */
	public static function same_origin( $a, $b ) {
		$pa = wp_parse_url( $a );
		$pb = wp_parse_url( $b );
		if ( empty( $pa['host'] ) || empty( $pb['host'] ) ) {
			return false;
		}
		$sa = strtolower( (string) ( $pa['scheme'] ?? 'https' ) );
		$sb = strtolower( (string) ( $pb['scheme'] ?? 'https' ) );
		$ha = strtolower( (string) $pa['host'] );
		$hb = strtolower( (string) $pb['host'] );
		$oa = isset( $pa['port'] ) ? (int) $pa['port'] : ( $sa === 'https' ? 443 : 80 );
		$ob = isset( $pb['port'] ) ? (int) $pb['port'] : ( $sb === 'https' ? 443 : 80 );
		return $sa === $sb && $ha === $hb && $oa === $ob;
	}

	/**
	 * @return string
	 */
	public static function user_agent() {
		$ver = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '0';
		return 'WordPress/' . $ver . '; Private-Repo-Updater/' . PRU_VERSION;
	}

	/**
	 * Strip query tokens from URLs shown in errors.
	 *
	 * @param string $url
	 * @return string
	 */
	public static function redact_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '[url]';
		}
		$scheme = $parts['scheme'] ?? 'https';
		$path   = $parts['path'] ?? '/';
		return $scheme . '://' . $parts['host'] . $path;
	}

	/**
	 * @param string $path
	 */
	public static function unlink( $path ) {
		if ( $path && file_exists( $path ) ) {
			@unlink( $path );
		}
	}
}
