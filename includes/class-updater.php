<?php
/**
 * Wire sources into WordPress plugin/theme updates.
 *
 * @package PrivateRepoUpdater
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRU_Updater {

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'plugins_transient' ) );
		add_filter( 'pre_set_site_transient_update_themes', array( __CLASS__, 'themes_transient' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugins_api' ), 10, 3 );
		add_filter( 'themes_api', array( __CLASS__, 'themes_api' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'pre_download' ), 10, 4 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'rename_source' ), 10, 4 );
		add_filter( 'http_request_args', array( __CLASS__, 'block_wp_org_check' ), 10, 2 );
	}

	/**
	 * @param object $transient
	 * @return object
	 */
	public static function plugins_transient( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}
		foreach ( PRU_Sources::all() as $source ) {
			if ( ( $source['type'] ?? '' ) !== 'plugin' ) {
				continue;
			}
			self::inject( $transient, $source );
		}
		return $transient;
	}

	/**
	 * @param object $transient
	 * @return object
	 */
	public static function themes_transient( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}
		foreach ( PRU_Sources::all() as $source ) {
			if ( ( $source['type'] ?? '' ) !== 'theme' ) {
				continue;
			}
			self::inject( $transient, $source );
		}
		return $transient;
	}

	/**
	 * @param object $transient
	 * @param array<string, mixed> $source
	 */
	protected static function inject( $transient, $source ) {
		$slug = $source['slug'];
		$installed = self::installed_version( $source );
		if ( $installed === '' ) {
			self::remember( $source, '', __( 'Package is not installed on this site.', 'private-repo-updater' ) );
			return;
		}

		$remote = PRU_Providers::fetch( $source );
		if ( is_wp_error( $remote ) ) {
			self::remember( $source, '', $remote->get_error_message() );
			return;
		}

		self::remember( $source, $remote['version'], '' );

		if ( ! version_compare( $remote['version'], $installed, '>' ) ) {
			if ( isset( $transient->response[ $slug ] ) ) {
				unset( $transient->response[ $slug ] );
			}
			$transient->no_update[ $slug ] = self::payload( $source, $remote );
			return;
		}

		$transient->response[ $slug ] = self::payload( $source, $remote );
	}

	/**
	 * @param array<string, mixed> $source
	 * @param array<string, mixed> $remote
	 * @return object|array
	 */
	protected static function payload( $source, $remote ) {
		if ( $source['type'] === 'plugin' ) {
			$item = (object) array(
				'slug'           => PRU_Sources::install_dirname( $source ),
				'plugin'         => $source['slug'],
				'new_version'    => $remote['version'],
				'url'            => $remote['homepage'],
				'package'        => $remote['download_url'],
				'tested'         => $remote['tested'],
				'requires'       => $remote['requires'],
				'requires_php'   => $remote['requires_php'],
				'icons'          => array(),
			);
			return $item;
		}

		return array(
			'theme'       => $source['slug'],
			'new_version' => $remote['version'],
			'url'         => $remote['homepage'],
			'package'     => $remote['download_url'],
			'requires'    => $remote['requires'],
			'requires_php'=> $remote['requires_php'],
		);
	}

	/**
	 * @param mixed $result
	 * @param string $action
	 * @param object $args
	 * @return mixed
	 */
	public static function plugins_api( $result, $action, $args ) {
		if ( $action !== 'plugin_information' || empty( $args->slug ) ) {
			return $result;
		}
		$source = self::source_by_dir( 'plugin', (string) $args->slug );
		if ( ! $source ) {
			return $result;
		}
		$remote = PRU_Providers::fetch( $source );
		if ( is_wp_error( $remote ) ) {
			return $remote;
		}
		$installed = self::installed_version( $source );
		$name      = $source['label'] ?: $args->slug;
		if ( $installed !== '' && function_exists( 'get_plugin_data' ) ) {
			$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $source['slug'], false, false );
			if ( ! empty( $data['Name'] ) ) {
				$name = $data['Name'];
			}
		}
		return (object) array(
			'name'           => $name,
			'slug'           => $args->slug,
			'version'        => $remote['version'],
			'author'         => '',
			'homepage'       => $remote['homepage'],
			'requires'       => $remote['requires'],
			'requires_php'   => $remote['requires_php'],
			'tested'         => $remote['tested'],
			'download_link'  => $remote['download_url'],
			'sections'       => array(
				'description' => $source['label'] ? esc_html( $source['label'] ) : '',
				'changelog'   => $remote['changelog'] !== '' ? wp_kses_post( nl2br( $remote['changelog'] ) ) : '',
			),
		);
	}

	/**
	 * @param mixed $result
	 * @param string $action
	 * @param object $args
	 * @return mixed
	 */
	public static function themes_api( $result, $action, $args ) {
		if ( $action !== 'theme_information' || empty( $args->slug ) ) {
			return $result;
		}
		$source = PRU_Sources::find_by_slug( 'theme', (string) $args->slug );
		if ( ! $source ) {
			return $result;
		}
		$remote = PRU_Providers::fetch( $source );
		if ( is_wp_error( $remote ) ) {
			return $remote;
		}
		$theme = wp_get_theme( $source['slug'] );
		return (object) array(
			'name'          => $theme->exists() ? $theme->get( 'Name' ) : ( $source['label'] ?: $args->slug ),
			'slug'          => $args->slug,
			'version'       => $remote['version'],
			'homepage'      => $remote['homepage'],
			'download_link' => $remote['download_url'],
			'sections'      => array(
				'description' => $theme->exists() ? $theme->get( 'Description' ) : '',
				'changelog'   => $remote['changelog'] !== '' ? wp_kses_post( nl2br( $remote['changelog'] ) ) : '',
			),
		);
	}

	/**
	 * @param false|string|\WP_Error $reply
	 * @param string $package
	 * @param \WP_Upgrader $upgrader
	 * @param array<string, mixed> $hook_extra
	 * @return false|string|\WP_Error
	 */
	public static function pre_download( $reply, $package, $upgrader, $hook_extra = array() ) {
		if ( $reply !== false ) {
			return $reply;
		}
		$source = self::source_for_package( $package, $hook_extra );
		if ( ! $source ) {
			return $reply;
		}
		$headers = PRU_Providers::download_headers( $source, $package );
		$file    = PRU_Http::download( $package, $headers );
		return $file;
	}

	/**
	 * GitHub/GitLab zips unpack as owner-repo-hash/. Rename to the WP slug.
	 *
	 * @param string $source
	 * @param string $remote_source
	 * @param \WP_Upgrader $upgrader
	 * @param array<string, mixed> $hook_extra
	 * @return string|\WP_Error
	 */
	public static function rename_source( $source, $remote_source, $upgrader, $hook_extra ) {
		$row = self::source_from_hook( $hook_extra );
		if ( ! $row ) {
			return $source;
		}
		$desired = PRU_Sources::install_dirname( $row );
		$renamed = self::rename_folder( $source, $desired );
		if ( is_wp_error( $renamed ) ) {
			return $renamed;
		}
		return $renamed;
	}

	/**
	 * @param string $source_path trailing folder from the zip
	 * @param string $desired
	 * @return string|\WP_Error
	 */
	public static function rename_folder( $source_path, $desired ) {
		$source_path = trailingslashit( $source_path );
		$current     = basename( untrailingslashit( $source_path ) );
		if ( $desired === '' || $current === $desired ) {
			return $source_path;
		}

		$parent = dirname( untrailingslashit( $source_path ) );
		$target = trailingslashit( $parent ) . $desired;

		global $wp_filesystem;
		if ( $wp_filesystem instanceof WP_Filesystem_Base ) {
			if ( $wp_filesystem->exists( $target ) ) {
				$wp_filesystem->delete( $target, true );
			}
			if ( ! $wp_filesystem->move( $source_path, $target ) ) {
				return new WP_Error( 'pru_rename', __( 'Could not rename the downloaded folder to the plugin/theme slug.', 'private-repo-updater' ) );
			}
			return trailingslashit( $target );
		}

		if ( is_dir( $target ) ) {
			self::rrmdir( $target );
		}
		if ( ! @rename( $source_path, $target ) ) {
			return new WP_Error( 'pru_rename', __( 'Could not rename the downloaded folder to the plugin/theme slug.', 'private-repo-updater' ) );
		}
		return trailingslashit( $target );
	}

	/**
	 * Keep wordpress.org from 404ing custom plugins (Update URI / slug collisions).
	 *
	 * @param array<string, mixed> $args
	 * @param string $url
	 * @return array<string, mixed>
	 */
	public static function block_wp_org_check( $args, $url ) {
		if ( strpos( $url, 'api.wordpress.org/plugins/update-check' ) === false
			&& strpos( $url, 'api.wordpress.org/themes/update-check' ) === false ) {
			return $args;
		}
		if ( empty( $args['body']['plugins'] ) && empty( $args['body']['themes'] ) ) {
			return $args;
		}

		$is_plugins = strpos( $url, 'plugins/update-check' ) !== false;
		$slugs      = array();
		foreach ( PRU_Sources::all() as $source ) {
			if ( $is_plugins && ( $source['type'] ?? '' ) === 'plugin' ) {
				$slugs[] = $source['slug'];
			}
			if ( ! $is_plugins && ( $source['type'] ?? '' ) === 'theme' ) {
				$slugs[] = $source['slug'];
			}
		}
		if ( ! $slugs ) {
			return $args;
		}

		$key  = $is_plugins ? 'plugins' : 'themes';
		$data = json_decode( $args['body'][ $key ], true );
		if ( ! is_array( $data ) ) {
			return $args;
		}
		if ( $is_plugins && ! empty( $data['plugins'] ) ) {
			foreach ( $slugs as $slug ) {
				unset( $data['plugins'][ $slug ] );
			}
		}
		if ( ! $is_plugins && ! empty( $data['themes'] ) ) {
			foreach ( $slugs as $slug ) {
				unset( $data['themes'][ $slug ] );
			}
		}
		$args['body'][ $key ] = wp_json_encode( $data );
		return $args;
	}

	/**
	 * @param array<string, mixed> $source
	 * @return string
	 */
	public static function installed_version( $source ) {
		if ( ( $source['type'] ?? '' ) === 'theme' ) {
			$theme = wp_get_theme( $source['slug'] );
			return $theme->exists() ? (string) $theme->get( 'Version' ) : '';
		}
		$file = WP_PLUGIN_DIR . '/' . $source['slug'];
		if ( ! is_readable( $file ) ) {
			return '';
		}
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$data = get_plugin_data( $file, false, false );
		return (string) ( $data['Version'] ?? '' );
	}

	/**
	 * Force WordPress to rebuild update transients.
	 */
	public static function force_check() {
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
		wp_clean_plugins_cache( false );
		wp_clean_themes_cache( false );
		if ( ! function_exists( 'wp_update_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		wp_update_plugins();
		wp_update_themes();
	}

	/**
	 * @param array<string, mixed> $source
	 * @param string $version
	 * @param string $error
	 */
	protected static function remember( $source, $version, $error ) {
		if ( empty( $source['id'] ) || ! empty( $source['readonly'] ) ) {
			return;
		}
		PRU_Sources::patch(
			$source['id'],
			array(
				'last_check'   => time(),
				'last_version' => $version,
				'last_error'   => $error,
			)
		);
	}

	/**
	 * @param string $type
	 * @param string $dir
	 * @return array<string, mixed>|null
	 */
	protected static function source_by_dir( $type, $dir ) {
		foreach ( PRU_Sources::all() as $source ) {
			if ( ( $source['type'] ?? '' ) === $type && PRU_Sources::install_dirname( $source ) === $dir ) {
				return $source;
			}
		}
		return null;
	}

	/**
	 * @param string $package
	 * @param array<string, mixed> $hook_extra
	 * @return array<string, mixed>|null
	 */
	protected static function source_for_package( $package, $hook_extra ) {
		$from_hook = self::source_from_hook( $hook_extra );
		if ( $from_hook ) {
			return $from_hook;
		}
		foreach ( PRU_Sources::all() as $source ) {
			if ( PRU_Providers::package_belongs( $source, $package ) ) {
				return $source;
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $hook_extra
	 * @return array<string, mixed>|null
	 */
	protected static function source_from_hook( $hook_extra ) {
		if ( ! empty( $hook_extra['plugin'] ) ) {
			return PRU_Sources::find_by_slug( 'plugin', (string) $hook_extra['plugin'] );
		}
		if ( ! empty( $hook_extra['theme'] ) ) {
			return PRU_Sources::find_by_slug( 'theme', (string) $hook_extra['theme'] );
		}
		return null;
	}

	/**
	 * @param string $dir
	 */
	protected static function rrmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		if ( ! $items ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( $item === '.' || $item === '..' ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				self::rrmdir( $path );
			} else {
				@unlink( $path );
			}
		}
		@rmdir( $dir );
	}
}
