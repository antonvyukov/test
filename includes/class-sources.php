<?php
/**
 * Stored update sources (plugin/theme + VCS connection).
 *
 * @package PrivateRepoUpdater
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRU_Sources {

	const OPTION = 'pru_sources';

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return apply_filters( 'pru_sources', $stored );
	}

	/**
	 * Sources saved in the database (not filter-injected).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function stored() {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * @param string $id
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		foreach ( self::all() as $source ) {
			if ( isset( $source['id'] ) && (string) $source['id'] === (string) $id ) {
				return $source;
			}
		}
		return null;
	}

	/**
	 * @param string $type plugin|theme
	 * @param string $slug
	 * @return array<string, mixed>|null
	 */
	public static function find_by_slug( $type, $slug ) {
		foreach ( self::all() as $source ) {
			if ( ( $source['type'] ?? '' ) === $type && ( $source['slug'] ?? '' ) === $slug ) {
				return $source;
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $source
	 * @return string
	 */
	public static function plain_token( $source ) {
		if ( ! empty( $source['token_encrypted'] ) && ! empty( $source['token'] ) ) {
			$plain = self::decrypt( (string) $source['token'] );
			if ( $plain !== '' ) {
				return $plain;
			}
		} elseif ( ! empty( $source['token'] ) && empty( $source['token_encrypted'] ) ) {
			return (string) $source['token'];
		}

		if ( defined( 'PRU_ACCESS_TOKEN' ) && PRU_ACCESS_TOKEN ) {
			return (string) PRU_ACCESS_TOKEN;
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed>|null $existing
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function sanitize( $input, $existing = null ) {
		$provider = sanitize_key( $input['provider'] ?? 'github' );
		$allowed  = array( 'github', 'gitlab', 'gitea', 'bitbucket', 'json' );
		if ( ! in_array( $provider, $allowed, true ) ) {
			return new WP_Error( 'pru_provider', __( 'Unknown provider.', 'private-repo-updater' ) );
		}

		$type = ( $input['type'] ?? 'plugin' ) === 'theme' ? 'theme' : 'plugin';
		$slug = trim( (string) ( $input['slug'] ?? '' ) );
		$slug = str_replace( '\\', '/', $slug );
		$slug = ltrim( $slug, '/' );

		if ( $type === 'plugin' ) {
			if ( ! preg_match( '#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+\.php$#', $slug ) ) {
				return new WP_Error(
					'pru_slug',
					__( 'Plugin slug must look like folder/plugin-file.php.', 'private-repo-updater' )
				);
			}
		} else {
			if ( ! preg_match( '#^[A-Za-z0-9._-]+$#', $slug ) ) {
				return new WP_Error(
					'pru_slug',
					__( 'Theme slug must be the theme directory name.', 'private-repo-updater' )
				);
			}
		}

		$channel = sanitize_key( $input['channel'] ?? 'release' );
		if ( ! in_array( $channel, array( 'release', 'tag', 'branch' ), true ) ) {
			$channel = 'release';
		}

		$repository = trim( (string) ( $input['repository'] ?? '' ) );
		$json_url   = esc_url_raw( (string) ( $input['json_url'] ?? '' ) );

		if ( $provider === 'json' ) {
			if ( $json_url === '' || ! preg_match( '#^https?://#i', $json_url ) ) {
				return new WP_Error( 'pru_json', __( 'A version JSON URL is required.', 'private-repo-updater' ) );
			}
			$repository = '';
		} else {
			if ( ! preg_match( '#^[A-Za-z0-9_.-]+(/[A-Za-z0-9_.-]+)+$#', $repository ) ) {
				return new WP_Error(
					'pru_repo',
					__( 'Repository must look like owner/repo (groups/subgroups allowed).', 'private-repo-updater' )
				);
			}
		}

		$host = trim( (string) ( $input['host'] ?? '' ) );
		if ( $host !== '' ) {
			$host = esc_url_raw( $host );
			if ( ! preg_match( '#^https?://#i', $host ) ) {
				return new WP_Error( 'pru_host', __( 'Host must be an http(s) URL.', 'private-repo-updater' ) );
			}
			$host = untrailingslashit( $host );
		}

		$ref = sanitize_text_field( (string) ( $input['ref'] ?? '' ) );
		if ( $channel === 'branch' && $ref === '' ) {
			$ref = 'main';
		}

		$id = $existing['id'] ?? self::new_id();

		$token_encrypted = $existing['token'] ?? '';
		$new_token       = (string) ( $input['token'] ?? '' );
		if ( $new_token !== '' ) {
			$token_encrypted = self::encrypt( $new_token );
		}
		if ( ! empty( $input['clear_token'] ) ) {
			$token_encrypted = '';
		}

		return array(
			'id'               => $id,
			'label'            => sanitize_text_field( (string) ( $input['label'] ?? '' ) ),
			'type'             => $type,
			'slug'             => $slug,
			'provider'         => $provider,
			'repository'       => $repository,
			'host'             => $host,
			'token'            => $token_encrypted,
			'token_encrypted'  => true,
			'channel'          => $channel,
			'ref'              => $ref,
			'json_url'         => $json_url,
			'prefer_asset'     => ! empty( $input['prefer_asset'] ),
			'last_check'       => $existing['last_check'] ?? 0,
			'last_version'     => $existing['last_version'] ?? '',
			'last_error'       => $existing['last_error'] ?? '',
		);
	}

	/**
	 * @param array<string, mixed> $source
	 */
	public static function save( $source ) {
		$items = self::stored();
		$found = false;
		foreach ( $items as $i => $item ) {
			if ( ( $item['id'] ?? '' ) === $source['id'] ) {
				$items[ $i ] = $source;
				$found       = true;
				break;
			}
		}
		if ( ! $found ) {
			$items[] = $source;
		}
		update_option( self::OPTION, array_values( $items ), false );
	}

	/**
	 * @param string $id
	 */
	public static function delete( $id ) {
		$items = array_values(
			array_filter(
				self::stored(),
				static function ( $item ) use ( $id ) {
					return ( $item['id'] ?? '' ) !== $id;
				}
			)
		);
		update_option( self::OPTION, $items, false );
	}

	/**
	 * @param string $id
	 * @param array<string, mixed> $fields
	 */
	public static function patch( $id, $fields ) {
		$items = self::stored();
		foreach ( $items as $i => $item ) {
			if ( ( $item['id'] ?? '' ) === $id ) {
				$items[ $i ] = array_merge( $item, $fields );
				update_option( self::OPTION, $items, false );
				return;
			}
		}
	}

	/**
	 * @param string $plain
	 * @return string
	 */
	public static function encrypt( $plain ) {
		if ( $plain === '' ) {
			return '';
		}
		$key    = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv     = random_bytes( 16 );
		$cipher = openssl_encrypt( $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		if ( $cipher === false ) {
			return '';
		}
		return base64_encode( $iv . $cipher );
	}

	/**
	 * @param string $stored
	 * @return string
	 */
	public static function decrypt( $stored ) {
		if ( $stored === '' ) {
			return '';
		}
		$raw = base64_decode( $stored, true );
		if ( $raw === false || strlen( $raw ) < 17 ) {
			return '';
		}
		$key    = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv     = substr( $raw, 0, 16 );
		$plain  = openssl_decrypt( substr( $raw, 16 ), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		return $plain === false ? '' : $plain;
	}

	/**
	 * @return string
	 */
	public static function new_id() {
		return substr( bin2hex( random_bytes( 8 ) ), 0, 12 );
	}

	/**
	 * Directory name WordPress should install into.
	 *
	 * @param array<string, mixed> $source
	 * @return string
	 */
	public static function install_dirname( $source ) {
		$slug = (string) ( $source['slug'] ?? '' );
		if ( ( $source['type'] ?? '' ) === 'theme' ) {
			return $slug;
		}
		$dir = dirname( $slug );
		return ( $dir === '.' || $dir === '' ) ? $slug : $dir;
	}
}
