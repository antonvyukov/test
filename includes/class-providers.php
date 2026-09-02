<?php
/**
 * Version lookup against GitHub, GitLab, Gitea/Forgejo, Bitbucket, or a JSON file.
 *
 * @package PrivateRepoUpdater
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRU_Providers {

	/**
	 * @param array<string, mixed> $source
	 * @return array<string, mixed>|\WP_Error {
	 *     @type string $version
	 *     @type string $download_url
	 *     @type string $homepage
	 *     @type string $changelog
	 *     @type string $tested
	 *     @type string $requires
	 *     @type string $requires_php
	 * }
	 */
	public static function fetch( $source ) {
		$provider = $source['provider'] ?? '';
		switch ( $provider ) {
			case 'github':
				return self::github( $source );
			case 'gitlab':
				return self::gitlab( $source );
			case 'gitea':
				return self::gitea( $source );
			case 'bitbucket':
				return self::bitbucket( $source );
			case 'json':
				return self::json_file( $source );
			default:
				return new WP_Error( 'pru_provider', __( 'Unknown provider.', 'private-repo-updater' ) );
		}
	}

	/**
	 * Auth + extra headers for a package download URL belonging to this source.
	 *
	 * @param array<string, mixed> $source
	 * @param string $url
	 * @return array<string, string>
	 */
	public static function download_headers( $source, $url ) {
		$headers = self::auth_headers( $source );
		if ( ( $source['provider'] ?? '' ) === 'github' && strpos( $url, '/releases/assets/' ) !== false ) {
			$headers['Accept'] = 'application/octet-stream';
		}
		if ( ( $source['provider'] ?? '' ) === 'gitea' ) {
			$headers['Accept'] = 'application/octet-stream';
		}
		return $headers;
	}

	/**
	 * @param array<string, mixed> $source
	 * @return array<string, string>
	 */
	public static function auth_headers( $source ) {
		$token    = PRU_Sources::plain_token( $source );
		$provider = $source['provider'] ?? '';
		if ( $token === '' ) {
			return array();
		}

		if ( $provider === 'github' ) {
			return array(
				'Authorization'        => 'Bearer ' . $token,
				'X-GitHub-Api-Version' => '2022-11-28',
			);
		}
		if ( $provider === 'gitlab' ) {
			return array( 'PRIVATE-TOKEN' => $token );
		}
		if ( $provider === 'gitea' ) {
			return array( 'Authorization' => 'token ' . $token );
		}
		if ( $provider === 'bitbucket' ) {
			if ( strpos( $token, ':' ) !== false ) {
				return array( 'Authorization' => 'Basic ' . base64_encode( $token ) );
			}
			return array( 'Authorization' => 'Bearer ' . $token );
		}
		return array( 'Authorization' => 'Bearer ' . $token );
	}

	/**
	 * @param string $tag
	 * @return string
	 */
	public static function normalize_version( $tag ) {
		$tag = trim( (string) $tag );
		if ( $tag === '' ) {
			return '';
		}
		if ( $tag[0] === 'v' || $tag[0] === 'V' ) {
			$tag = substr( $tag, 1 );
		}
		return $tag;
	}

	/**
	 * Pick the highest semver-ish tag from a list.
	 *
	 * @param array<int, string> $tags
	 * @return string
	 */
	public static function highest_version( $tags ) {
		$best = '';
		foreach ( $tags as $tag ) {
			$ver = self::normalize_version( $tag );
			if ( $ver === '' || ! preg_match( '/^[0-9]/', $ver ) ) {
				continue;
			}
			if ( $best === '' || version_compare( $ver, $best, '>' ) ) {
				$best = $ver;
			}
		}
		return $best;
	}

	/**
	 * @param array<string, mixed> $source
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function github( $source ) {
		$api      = self::github_api_root( $source['host'] ?? '' );
		$repo     = $source['repository'];
		$headers  = self::auth_headers( $source );
		$channel  = $source['channel'] ?? 'release';

		if ( $channel === 'branch' ) {
			return self::github_branch( $source, $api, $headers );
		}

		if ( $channel === 'tag' ) {
			$tags = PRU_Http::get_decoded( $api . '/repos/' . $repo . '/tags?per_page=30', $headers );
			if ( is_wp_error( $tags ) ) {
				return $tags;
			}
			$parsed = self::parse_github_tags( $tags, $api, $repo );
			if ( is_wp_error( $parsed ) ) {
				return $parsed;
			}
			$parsed['homepage'] = self::github_web_root( $source['host'] ?? '' ) . '/' . $repo;
			return $parsed;
		}

		$release = PRU_Http::get_decoded( $api . '/repos/' . $repo . '/releases/latest', $headers );
		if ( is_wp_error( $release ) ) {
			return $release;
		}
		$parsed = self::parse_github_release( $release, ! empty( $source['prefer_asset'] ), $api, $repo );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}
		if ( empty( $parsed['homepage'] ) ) {
			$parsed['homepage'] = self::github_web_root( $source['host'] ?? '' ) . '/' . $repo;
		}
		return $parsed;
	}

	/**
	 * @param array<string, mixed> $release
	 * @param bool $prefer_asset
	 * @param string $api
	 * @param string $repo
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function parse_github_release( $release, $prefer_asset, $api = 'https://api.github.com', $repo = '' ) {
		$tag = $release['tag_name'] ?? '';
		$ver = self::normalize_version( $tag );
		if ( $ver === '' ) {
			return new WP_Error( 'pru_version', __( 'GitHub release has no tag.', 'private-repo-updater' ) );
		}

		$download = '';
		if ( $prefer_asset && ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				$name = (string) ( $asset['name'] ?? '' );
				if ( preg_match( '/\.zip$/i', $name ) && ! empty( $asset['url'] ) ) {
					$download = (string) $asset['url'];
					break;
				}
			}
		}
		if ( $download === '' ) {
			if ( ! empty( $release['zipball_url'] ) ) {
				$download = (string) $release['zipball_url'];
			} elseif ( $repo !== '' ) {
				$download = rtrim( $api, '/' ) . '/repos/' . $repo . '/zipball/' . rawurlencode( (string) $tag );
			}
		}
		if ( $download === '' ) {
			return new WP_Error( 'pru_zip', __( 'GitHub release has no downloadable zip.', 'private-repo-updater' ) );
		}

		return array(
			'version'      => $ver,
			'download_url' => $download,
			'homepage'     => (string) ( $release['html_url'] ?? '' ),
			'changelog'    => (string) ( $release['body'] ?? '' ),
			'tested'       => '',
			'requires'     => '',
			'requires_php' => '',
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $tags
	 * @param string $api
	 * @param string $repo
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function parse_github_tags( $tags, $api, $repo ) {
		$names = array();
		$map   = array();
		foreach ( $tags as $tag ) {
			if ( empty( $tag['name'] ) ) {
				continue;
			}
			$names[]                 = (string) $tag['name'];
			$map[ (string) $tag['name'] ] = $tag;
		}
		$best = self::highest_version( $names );
		if ( $best === '' ) {
			return new WP_Error( 'pru_tags', __( 'No version tags found in the repository.', 'private-repo-updater' ) );
		}
		$raw = $best;
		foreach ( $map as $name => $_tag ) {
			if ( self::normalize_version( $name ) === $best ) {
				$raw = $name;
				break;
			}
		}
		return array(
			'version'      => $best,
			'download_url' => rtrim( $api, '/' ) . '/repos/' . $repo . '/zipball/' . rawurlencode( $raw ),
			'homepage'     => '',
			'changelog'    => '',
			'tested'       => '',
			'requires'     => '',
			'requires_php' => '',
		);
	}

	/**
	 * @param array<string, mixed> $source
	 * @param string $api
	 * @param array<string, string> $headers
	 * @return array<string, mixed>|\WP_Error
	 */
	protected static function github_branch( $source, $api, $headers ) {
		$ref  = $source['ref'] ?: 'main';
		$path = ( $source['type'] ?? '' ) === 'theme' ? 'style.css' : basename( $source['slug'] );
		$url  = $api . '/repos/' . $source['repository'] . '/contents/' . implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) ) . '?ref=' . rawurlencode( $ref );

		$response = PRU_Http::get_json(
			$url,
			array_merge( $headers, array( 'Accept' => 'application/vnd.github.raw' ) )
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = wp_remote_retrieve_body( $response );
		$ver  = self::version_from_header( $body, ( $source['type'] ?? '' ) === 'theme' );
		if ( $ver === '' ) {
			return new WP_Error( 'pru_header', __( 'Could not read Version from the branch file.', 'private-repo-updater' ) );
		}
		return array(
			'version'      => $ver,
			'download_url' => $api . '/repos/' . $source['repository'] . '/zipball/' . rawurlencode( $ref ),
			'homepage'     => self::github_web_root( $source['host'] ?? '' ) . '/' . $source['repository'],
			'changelog'    => '',
			'tested'       => '',
			'requires'     => '',
			'requires_php' => '',
		);
	}

	/**
	 * @param array<string, mixed> $source
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function gitlab( $source ) {
		$root     = self::gitlab_root( $source['host'] ?? '' );
		$project  = rawurlencode( $source['repository'] );
		$headers  = self::auth_headers( $source );
		$channel  = $source['channel'] ?? 'release';

		if ( $channel === 'branch' ) {
			$ref  = $source['ref'] ?: 'main';
			$path = ( $source['type'] ?? '' ) === 'theme' ? 'style.css' : basename( $source['slug'] );
			$file = PRU_Http::get_json(
				$root . '/api/v4/projects/' . $project . '/repository/files/' . rawurlencode( $path ) . '/raw?ref=' . rawurlencode( $ref ),
				$headers
			);
			if ( is_wp_error( $file ) ) {
				return $file;
			}
			$ver = self::version_from_header( wp_remote_retrieve_body( $file ), ( $source['type'] ?? '' ) === 'theme' );
			if ( $ver === '' ) {
				return new WP_Error( 'pru_header', __( 'Could not read Version from the branch file.', 'private-repo-updater' ) );
			}
			return array(
				'version'      => $ver,
				'download_url' => $root . '/api/v4/projects/' . $project . '/repository/archive.zip?sha=' . rawurlencode( $ref ),
				'homepage'     => $root . '/' . $source['repository'],
				'changelog'    => '',
				'tested'       => '',
				'requires'     => '',
				'requires_php' => '',
			);
		}

		if ( $channel === 'tag' ) {
			$tags = PRU_Http::get_decoded( $root . '/api/v4/projects/' . $project . '/repository/tags?per_page=30', $headers );
			if ( is_wp_error( $tags ) ) {
				return $tags;
			}
			return self::parse_gitlab_tags( $tags, $root, $project, $source['repository'] );
		}

		$releases = PRU_Http::get_decoded( $root . '/api/v4/projects/' . $project . '/releases', $headers );
		if ( is_wp_error( $releases ) ) {
			return $releases;
		}
		return self::parse_gitlab_releases( $releases, $root, $project, $source['repository'] );
	}

	/**
	 * @param array<int, array<string, mixed>> $releases
	 * @param string $root
	 * @param string $project encoded
	 * @param string $repo
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function parse_gitlab_releases( $releases, $root, $project, $repo ) {
		if ( empty( $releases[0]['tag_name'] ) ) {
			return new WP_Error( 'pru_release', __( 'No GitLab releases found.', 'private-repo-updater' ) );
		}
		$rel = $releases[0];
		$ver = self::normalize_version( $rel['tag_name'] );
		return array(
			'version'      => $ver,
			'download_url' => rtrim( $root, '/' ) . '/api/v4/projects/' . $project . '/repository/archive.zip?sha=' . rawurlencode( (string) $rel['tag_name'] ),
			'homepage'     => (string) ( $rel['_links']['self'] ?? ( rtrim( $root, '/' ) . '/' . $repo ) ),
			'changelog'    => (string) ( $rel['description'] ?? '' ),
			'tested'       => '',
			'requires'     => '',
			'requires_php' => '',
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $tags
	 * @param string $root
	 * @param string $project
	 * @param string $repo
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function parse_gitlab_tags( $tags, $root, $project, $repo ) {
		$names = array();
		foreach ( $tags as $tag ) {
			if ( ! empty( $tag['name'] ) ) {
				$names[] = (string) $tag['name'];
			}
		}
		$best = self::highest_version( $names );
		if ( $best === '' ) {
			return new WP_Error( 'pru_tags', __( 'No version tags found in the repository.', 'private-repo-updater' ) );
		}
		$raw = $best;
		foreach ( $names as $name ) {
			if ( self::normalize_version( $name ) === $best ) {
				$raw = $name;
				break;
			}
		}
		return array(
			'version'      => $best,
			'download_url' => rtrim( $root, '/' ) . '/api/v4/projects/' . $project . '/repository/archive.zip?sha=' . rawurlencode( $raw ),
			'homepage'     => rtrim( $root, '/' ) . '/' . $repo,
			'changelog'    => '',
			'tested'       => '',
			'requires'     => '',
			'requires_php' => '',
		);
	}

	/**
	 * @param array<string, mixed> $source
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function gitea( $source ) {
		$host = $source['host'] ?? '';
		if ( $host === '' ) {
			return new WP_Error( 'pru_host', __( 'Gitea/Forgejo requires a host URL.', 'private-repo-updater' ) );
		}
		$root    = untrailingslashit( $host );
		$repo    = $source['repository'];
		$headers = self::auth_headers( $source );
		$channel = $source['channel'] ?? 'release';

		if ( $channel === 'branch' ) {
			$ref  = $source['ref'] ?: 'main';
			$path = ( $source['type'] ?? '' ) === 'theme' ? 'style.css' : basename( $source['slug'] );
			$file = PRU_Http::get_json(
				$root . '/api/v1/repos/' . $repo . '/raw/' . implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) ) . '?ref=' . rawurlencode( $ref ),
				$headers
			);
			if ( is_wp_error( $file ) ) {
				return $file;
			}
			$ver = self::version_from_header( wp_remote_retrieve_body( $file ), ( $source['type'] ?? '' ) === 'theme' );
			if ( $ver === '' ) {
				return new WP_Error( 'pru_header', __( 'Could not read Version from the branch file.', 'private-repo-updater' ) );
			}
			return array(
				'version'      => $ver,
				'download_url' => $root . '/api/v1/repos/' . $repo . '/archive/' . rawurlencode( $ref ) . '.zip',
				'homepage'     => $root . '/' . $repo,
				'changelog'    => '',
				'tested'       => '',
				'requires'     => '',
				'requires_php' => '',
			);
		}

		if ( $channel === 'tag' ) {
			$tags = PRU_Http::get_decoded( $root . '/api/v1/repos/' . $repo . '/tags?limit=30', $headers );
			if ( is_wp_error( $tags ) ) {
				return $tags;
			}
			return self::parse_gitea_tags( $tags, $root, $repo );
		}

		$release = PRU_Http::get_decoded( $root . '/api/v1/repos/' . $repo . '/releases/latest', $headers );
		if ( is_wp_error( $release ) ) {
			return $release;
		}
		return self::parse_gitea_release( $release, ! empty( $source['prefer_asset'] ), $root, $repo );
	}

	/**
	 * @param array<string, mixed> $release
	 * @param bool $prefer_asset
	 * @param string $root
	 * @param string $repo
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function parse_gitea_release( $release, $prefer_asset, $root, $repo ) {
		$tag = $release['tag_name'] ?? '';
		$ver = self::normalize_version( $tag );
		if ( $ver === '' ) {
			return new WP_Error( 'pru_version', __( 'Gitea release has no tag.', 'private-repo-updater' ) );
		}
		$download = '';
		if ( $prefer_asset && ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				$name = (string) ( $asset['name'] ?? '' );
				if ( preg_match( '/\.zip$/i', $name ) ) {
					$download = (string) ( $asset['browser_download_url'] ?? $asset['url'] ?? '' );
					if ( $download !== '' ) {
						break;
					}
				}
			}
		}
		if ( $download === '' ) {
			$download = rtrim( $root, '/' ) . '/api/v1/repos/' . $repo . '/archive/' . rawurlencode( (string) $tag ) . '.zip';
		}
		return array(
			'version'      => $ver,
			'download_url' => $download,
			'homepage'     => (string) ( $release['html_url'] ?? ( rtrim( $root, '/' ) . '/' . $repo ) ),
			'changelog'    => (string) ( $release['body'] ?? '' ),
			'tested'       => '',
			'requires'     => '',
			'requires_php' => '',
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $tags
	 * @param string $root
	 * @param string $repo
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function parse_gitea_tags( $tags, $root, $repo ) {
		$names = array();
		foreach ( $tags as $tag ) {
			if ( ! empty( $tag['name'] ) ) {
				$names[] = (string) $tag['name'];
			}
		}
		$best = self::highest_version( $names );
		if ( $best === '' ) {
			return new WP_Error( 'pru_tags', __( 'No version tags found in the repository.', 'private-repo-updater' ) );
		}
		$raw = $best;
		foreach ( $names as $name ) {
			if ( self::normalize_version( $name ) === $best ) {
				$raw = $name;
				break;
			}
		}
		return array(
			'version'      => $best,
			'download_url' => rtrim( $root, '/' ) . '/api/v1/repos/' . $repo . '/archive/' . rawurlencode( $raw ) . '.zip',
			'homepage'     => rtrim( $root, '/' ) . '/' . $repo,
			'changelog'    => '',
			'tested'       => '',
			'requires'     => '',
			'requires_php' => '',
		);
	}

	/**
	 * @param array<string, mixed> $source
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function bitbucket( $source ) {
		$api     = 'https://api.bitbucket.org/2.0';
		$web     = 'https://bitbucket.org';
		$repo    = $source['repository'];
		$headers = self::auth_headers( $source );
		$channel = $source['channel'] ?? 'release';

		if ( $channel === 'branch' ) {
			$ref  = $source['ref'] ?: 'main';
			$path = ( $source['type'] ?? '' ) === 'theme' ? 'style.css' : basename( $source['slug'] );
			$file = PRU_Http::get_json(
				$api . '/repositories/' . $repo . '/src/' . rawurlencode( $ref ) . '/' . $path,
				$headers
			);
			if ( is_wp_error( $file ) ) {
				return $file;
			}
			$ver = self::version_from_header( wp_remote_retrieve_body( $file ), ( $source['type'] ?? '' ) === 'theme' );
			if ( $ver === '' ) {
				return new WP_Error( 'pru_header', __( 'Could not read Version from the branch file.', 'private-repo-updater' ) );
			}
			return array(
				'version'      => $ver,
				'download_url' => $web . '/' . $repo . '/get/' . rawurlencode( $ref ) . '.zip',
				'homepage'     => $web . '/' . $repo,
				'changelog'    => '',
				'tested'       => '',
				'requires'     => '',
				'requires_php' => '',
			);
		}

		$tags = PRU_Http::get_decoded( $api . '/repositories/' . $repo . '/refs/tags?pagelen=30&sort=-target.date', $headers );
		if ( is_wp_error( $tags ) ) {
			return $tags;
		}
		return self::parse_bitbucket_tags( $tags, $web, $repo );
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param string $web
	 * @param string $repo
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function parse_bitbucket_tags( $payload, $web, $repo ) {
		$values = $payload['values'] ?? $payload;
		if ( ! is_array( $values ) ) {
			return new WP_Error( 'pru_tags', __( 'No version tags found in the repository.', 'private-repo-updater' ) );
		}
		$names = array();
		foreach ( $values as $tag ) {
			if ( ! empty( $tag['name'] ) ) {
				$names[] = (string) $tag['name'];
			}
		}
		$best = self::highest_version( $names );
		if ( $best === '' ) {
			return new WP_Error( 'pru_tags', __( 'No version tags found in the repository.', 'private-repo-updater' ) );
		}
		$raw = $best;
		foreach ( $names as $name ) {
			if ( self::normalize_version( $name ) === $best ) {
				$raw = $name;
				break;
			}
		}
		return array(
			'version'      => $best,
			'download_url' => rtrim( $web, '/' ) . '/' . $repo . '/get/' . rawurlencode( $raw ) . '.zip',
			'homepage'     => rtrim( $web, '/' ) . '/' . $repo,
			'changelog'    => '',
			'tested'       => '',
			'requires'     => '',
			'requires_php' => '',
		);
	}

	/**
	 * Generic JSON update feed (Plugin Update Checker compatible).
	 *
	 * @param array<string, mixed> $source
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function json_file( $source ) {
		$decoded = PRU_Http::get_decoded( $source['json_url'], self::auth_headers( $source ) );
		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}
		return self::parse_json_feed( $decoded );
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function parse_json_feed( $data ) {
		$ver = self::normalize_version( $data['version'] ?? $data['new_version'] ?? '' );
		$zip = $data['download_url'] ?? $data['download_link'] ?? $data['package'] ?? '';
		if ( $ver === '' || $zip === '' ) {
			return new WP_Error(
				'pru_json',
				__( 'JSON feed must include version and download_url.', 'private-repo-updater' )
			);
		}
		$changelog = '';
		if ( ! empty( $data['sections']['changelog'] ) ) {
			$changelog = (string) $data['sections']['changelog'];
		} elseif ( ! empty( $data['changelog'] ) ) {
			$changelog = (string) $data['changelog'];
		}
		return array(
			'version'      => $ver,
			'download_url' => esc_url_raw( (string) $zip ),
			'homepage'     => esc_url_raw( (string) ( $data['url'] ?? $data['homepage'] ?? $data['details_url'] ?? '' ) ),
			'changelog'    => $changelog,
			'tested'       => (string) ( $data['tested'] ?? '' ),
			'requires'     => (string) ( $data['requires'] ?? '' ),
			'requires_php' => (string) ( $data['requires_php'] ?? '' ),
		);
	}

	/**
	 * @param string $contents
	 * @param bool $is_theme
	 * @return string
	 */
	public static function version_from_header( $contents, $is_theme = false ) {
		if ( $is_theme ) {
			if ( preg_match( '/^Version:\s*(.+)$/mi', $contents, $m ) ) {
				return self::normalize_version( trim( $m[1] ) );
			}
			return '';
		}
		if ( preg_match( '/^\s*\*\s*Version:\s*(.+)$/mi', $contents, $m ) ) {
			return self::normalize_version( trim( $m[1] ) );
		}
		if ( preg_match( '/^Version:\s*(.+)$/mi', $contents, $m ) ) {
			return self::normalize_version( trim( $m[1] ) );
		}
		return '';
	}

	/**
	 * @param string $host
	 * @return string
	 */
	public static function github_api_root( $host ) {
		$host = untrailingslashit( trim( (string) $host ) );
		if ( $host === '' || preg_match( '#^https?://(www\.)?github\.com$#i', $host ) ) {
			return 'https://api.github.com';
		}
		return $host . '/api/v3';
	}

	/**
	 * @param string $host
	 * @return string
	 */
	public static function github_web_root( $host ) {
		$host = untrailingslashit( trim( (string) $host ) );
		if ( $host === '' ) {
			return 'https://github.com';
		}
		return $host;
	}

	/**
	 * @param string $host
	 * @return string
	 */
	public static function gitlab_root( $host ) {
		$host = untrailingslashit( trim( (string) $host ) );
		return $host === '' ? 'https://gitlab.com' : $host;
	}

	/**
	 * Does this package URL belong to the source? Used to attach the right token.
	 *
	 * @param array<string, mixed> $source
	 * @param string $url
	 * @return bool
	 */
	public static function package_belongs( $source, $url ) {
		$provider = $source['provider'] ?? '';
		if ( $provider === 'json' ) {
			$feed = PRU_Http::redact_url( $source['json_url'] ?? '' );
			return $url === ( $source['json_url'] ?? '' ) || strpos( $url, (string) ( $source['json_url'] ?? '---' ) ) === 0 || self::url_mentions_repo( $url, $source );
		}
		$repo = (string) ( $source['repository'] ?? '' );
		if ( $repo === '' ) {
			return false;
		}
		return self::url_mentions_repo( $url, $source );
	}

	/**
	 * @param string $url
	 * @param array<string, mixed> $source
	 * @return bool
	 */
	public static function url_mentions_repo( $url, $source ) {
		$repo = (string) ( $source['repository'] ?? '' );
		if ( $repo === '' ) {
			return false;
		}
		$enc  = rawurlencode( $repo );
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$path = is_string( $path ) ? $path : $url;
		return strpos( $url, $repo ) !== false
			|| strpos( $url, $enc ) !== false
			|| strpos( $path, $repo ) !== false;
	}
}
