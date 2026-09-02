<?php
/**
 * Runnable checks for Private Repo Updater (no WordPress install required).
 *
 * php tests/self-check.php
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'PRU_VERSION', '1.0.0' );

class WP_Error {
	public $code;
	public $message;
	public $data;

	public function __construct( $code, $message, $data = array() ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function __( $text, $domain = '' ) {
	return $text;
}

function wp_salt( $scheme = 'auth' ) {
	return 'pru-test-salt-' . $scheme;
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' );
}

function trailingslashit( $value ) {
	return untrailingslashit( $value ) . '/';
}

function esc_url_raw( $url ) {
	$url = trim( (string) $url );
	if ( $url !== '' && ! preg_match( '#^https?://#i', $url ) ) {
		return '';
	}
	return $url;
}

function sanitize_key( $key ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
}

function sanitize_text_field( $str ) {
	return trim( strip_tags( (string) $str ) );
}

function get_bloginfo( $show = '' ) {
	return '6.4-test';
}

require_once __DIR__ . '/../includes/class-sources.php';
require_once __DIR__ . '/../includes/class-http.php';
require_once __DIR__ . '/../includes/class-providers.php';
require_once __DIR__ . '/../includes/class-updater.php';

$failed = 0;
$passed = 0;

function check( $name, $cond ) {
	global $failed, $passed;
	if ( $cond ) {
		echo "ok  - $name\n";
		$passed++;
	} else {
		echo "FAIL- $name\n";
		$failed++;
	}
}

check( 'normalize v-prefix', PRU_Providers::normalize_version( 'v1.2.3' ) === '1.2.3' );
check( 'normalize already plain', PRU_Providers::normalize_version( '1.2.3' ) === '1.2.3' );
check( 'normalize V prefix', PRU_Providers::normalize_version( 'V2.0.0-beta' ) === '2.0.0-beta' );

check(
	'highest semver among tags',
	PRU_Providers::highest_version( array( 'v1.0.0', 'v1.10.0', 'v1.9.9', 'nightly' ) ) === '1.10.0'
);

$release = array(
	'tag_name'    => 'v2.1.0',
	'html_url'    => 'https://github.com/acme/plug/releases/tag/v2.1.0',
	'body'        => 'Fixed the thing',
	'zipball_url' => 'https://api.github.com/repos/acme/plug/zipball/v2.1.0',
	'assets'      => array(
		array(
			'name' => 'notes.txt',
			'url'  => 'https://api.github.com/repos/acme/plug/releases/assets/1',
		),
		array(
			'name' => 'acme-plug.zip',
			'url'  => 'https://api.github.com/repos/acme/plug/releases/assets/99',
		),
	),
);

$parsed = PRU_Providers::parse_github_release( $release, true, 'https://api.github.com', 'acme/plug' );
check( 'github release version', ! is_wp_error( $parsed ) && $parsed['version'] === '2.1.0' );
check( 'github prefers zip asset', $parsed['download_url'] === 'https://api.github.com/repos/acme/plug/releases/assets/99' );
check( 'github changelog from body', $parsed['changelog'] === 'Fixed the thing' );

$no_asset = PRU_Providers::parse_github_release( $release, false, 'https://api.github.com', 'acme/plug' );
check( 'github zipball when assets off', $no_asset['download_url'] === 'https://api.github.com/repos/acme/plug/zipball/v2.1.0' );

$tags = PRU_Providers::parse_github_tags(
	array(
		array( 'name' => 'v0.9.0' ),
		array( 'name' => 'v1.2.0' ),
		array( 'name' => 'v1.1.5' ),
	),
	'https://api.github.com',
	'acme/plug'
);
check( 'github tags pick highest', $tags['version'] === '1.2.0' );
check(
	'github tags zipball uses original tag',
	$tags['download_url'] === 'https://api.github.com/repos/acme/plug/zipball/v1.2.0'
);

$gl = PRU_Providers::parse_gitlab_releases(
	array(
		array(
			'tag_name'    => 'v3.0.1',
			'description' => 'Ship it',
			'_links'      => array( 'self' => 'https://gitlab.com/g/p/-/releases/v3.0.1' ),
		),
	),
	'https://gitlab.com',
	rawurlencode( 'g/p' ),
	'g/p'
);
check( 'gitlab release version', $gl['version'] === '3.0.1' );
check( 'gitlab archive url', strpos( $gl['download_url'], '/repository/archive.zip?sha=v3.0.1' ) !== false );

$bb = PRU_Providers::parse_bitbucket_tags(
	array(
		'values' => array(
			array( 'name' => 'v0.1.0' ),
			array( 'name' => 'v2.0.0' ),
		),
	),
	'https://bitbucket.org',
	'team/pkg'
);
check( 'bitbucket highest tag', $bb['version'] === '2.0.0' );
check( 'bitbucket zip url', $bb['download_url'] === 'https://bitbucket.org/team/pkg/get/v2.0.0.zip' );

$gitea = PRU_Providers::parse_gitea_release(
	array(
		'tag_name' => '1.4.0',
		'body'     => 'gitea notes',
		'assets'   => array(
			array(
				'name'                 => 'pkg.zip',
				'browser_download_url' => 'https://git.example.com/x/y/releases/download/1.4.0/pkg.zip',
			),
		),
	),
	true,
	'https://git.example.com',
	'x/y'
);
check( 'gitea asset zip', $gitea['download_url'] === 'https://git.example.com/x/y/releases/download/1.4.0/pkg.zip' );

$feed = PRU_Providers::parse_json_feed(
	array(
		'version'      => 'v4.0.0',
		'download_url' => 'https://cdn.example.com/a.zip',
		'sections'     => array( 'changelog' => '<p>Hi</p>' ),
	)
);
check( 'json feed version strip v', $feed['version'] === '4.0.0' );
check( 'json feed changelog section', $feed['changelog'] === '<p>Hi</p>' );

$bad_feed = PRU_Providers::parse_json_feed( array( 'version' => '1.0' ) );
check( 'json feed requires download_url', is_wp_error( $bad_feed ) );

$header = "<?php\n/**\n * Plugin Name: Demo\n * Version: 8.1.0\n */\n";
check( 'plugin header version', PRU_Providers::version_from_header( $header, false ) === '8.1.0' );
check(
	'theme header version',
	PRU_Providers::version_from_header( "Theme Name: T\nVersion: v3.2\n", true ) === '3.2'
);

check( 'github.com uses public API', PRU_Providers::github_api_root( '' ) === 'https://api.github.com' );
check( 'github.com host still public API', PRU_Providers::github_api_root( 'https://github.com' ) === 'https://api.github.com' );
check( 'GHE api root', PRU_Providers::github_api_root( 'https://github.acme.test' ) === 'https://github.acme.test/api/v3' );
check( 'gitlab default root', PRU_Providers::gitlab_root( '' ) === 'https://gitlab.com' );
check( 'gitlab custom root', PRU_Providers::gitlab_root( 'https://gitlab.acme.test/' ) === 'https://gitlab.acme.test' );

$gh_headers = PRU_Providers::auth_headers(
	array(
		'provider' => 'github',
		'token'    => 'ghp_secret',
	)
);
check(
	'github bearer token',
	( $gh_headers['Authorization'] ?? '' ) === 'Bearer ghp_secret'
);

$gl_headers = PRU_Providers::auth_headers(
	array(
		'provider' => 'gitlab',
		'token'    => 'glpat-x',
	)
);
check( 'gitlab private-token header', ( $gl_headers['PRIVATE-TOKEN'] ?? '' ) === 'glpat-x' );

$bb_basic = PRU_Providers::auth_headers(
	array(
		'provider' => 'bitbucket',
		'token'    => 'user:app-password',
	)
);
check( 'bitbucket basic when token has colon', strpos( $bb_basic['Authorization'], 'Basic ' ) === 0 );

$asset_h = PRU_Providers::download_headers(
	array(
		'provider' => 'github',
		'token'    => 't',
	),
	'https://api.github.com/repos/a/b/releases/assets/12'
);
check( 'github asset accept octet-stream', ( $asset_h['Accept'] ?? '' ) === 'application/octet-stream' );

$step_cross = PRU_Http::next_download_step(
	'https://api.github.com/repos/a/b/zipball/v1',
	302,
	'https://codeload.github.com/a/b/legacy.zip/refs/tags/v1'
);
check( 'codeload redirect drops auth', empty( $step_cross['use_auth'] ) && ! empty( $step_cross['url'] ) );

$step_same = PRU_Http::next_download_step(
	'https://api.github.com/repos/a/b/zipball/v1',
	302,
	'https://api.github.com/download?blob=1'
);
check( 'same-origin redirect keeps auth', ! empty( $step_same['use_auth'] ) );

$step_ok = PRU_Http::next_download_step( 'https://api.github.com/x', 200, '' );
check( 'http 200 finishes download', ! empty( $step_ok['done'] ) );

$step_fail = PRU_Http::next_download_step( 'https://api.github.com/x', 404, '' );
check( 'http 404 is an error', isset( $step_fail['error'] ) );

check(
	'same origin ignores default ports',
	PRU_Http::same_origin( 'https://api.github.com/a', 'https://api.github.com:443/b' )
);
check(
	'different host not same origin',
	! PRU_Http::same_origin( 'https://api.github.com/a', 'https://codeload.github.com/a' )
);

$rel = PRU_Http::redact_url( 'https://api.github.com/repos/a/b?token=SECRET' );
check( 'redact_url strips query', $rel === 'https://api.github.com/repos/a/b' );
check(
	'redact_url keeps non-default port',
	PRU_Http::redact_url( 'http://127.0.0.1:8091/api/v3/repos/a/b?token=SECRET' ) === 'http://127.0.0.1:8091/api/v3/repos/a/b'
);

$cipher = PRU_Sources::encrypt( 'super-secret-token' );
check( 'encrypt is not plaintext', $cipher !== '' && $cipher !== 'super-secret-token' );
check( 'decrypt roundtrip', PRU_Sources::decrypt( $cipher ) === 'super-secret-token' );
check( 'encrypt empty', PRU_Sources::encrypt( '' ) === '' );

$ok_plugin = PRU_Sources::sanitize(
	array(
		'type'       => 'plugin',
		'slug'       => 'demo-plugin/demo-plugin.php',
		'provider'   => 'github',
		'repository' => 'acme/demo-plugin',
		'token'      => 'ghp_abc',
		'channel'    => 'release',
		'prefer_asset' => '1',
	)
);
check( 'sanitize plugin source', ! is_wp_error( $ok_plugin ) && $ok_plugin['slug'] === 'demo-plugin/demo-plugin.php' );
check( 'sanitize encrypts token', $ok_plugin['token'] !== 'ghp_abc' && $ok_plugin['token_encrypted'] === true );
check( 'plain_token decrypts', PRU_Sources::plain_token( $ok_plugin ) === 'ghp_abc' );
check( 'install dirname plugin', PRU_Sources::install_dirname( $ok_plugin ) === 'demo-plugin' );

$bad_slug = PRU_Sources::sanitize(
	array(
		'type'       => 'plugin',
		'slug'       => 'hello.php',
		'provider'   => 'github',
		'repository' => 'acme/hello',
	)
);
check( 'reject single-file plugin slug', is_wp_error( $bad_slug ) );

$ok_theme = PRU_Sources::sanitize(
	array(
		'type'       => 'theme',
		'slug'       => 'acme-theme',
		'provider'   => 'gitlab',
		'repository' => 'group/sub/acme-theme',
		'host'       => 'https://gitlab.example.com/',
		'channel'    => 'tag',
	)
);
check( 'sanitize gitlab subgroup + host', ! is_wp_error( $ok_theme ) && $ok_theme['repository'] === 'group/sub/acme-theme' );
check( 'host loses trailing slash', $ok_theme['host'] === 'https://gitlab.example.com' );
check( 'install dirname theme', PRU_Sources::install_dirname( $ok_theme ) === 'acme-theme' );

$json_src = PRU_Sources::sanitize(
	array(
		'type'     => 'plugin',
		'slug'     => 'x/x.php',
		'provider' => 'json',
		'json_url' => 'https://updates.example.com/x.json',
	)
);
check( 'sanitize json source', ! is_wp_error( $json_src ) && $json_src['json_url'] !== '' );

$bad_json = PRU_Sources::sanitize(
	array(
		'type'     => 'plugin',
		'slug'     => 'x/x.php',
		'provider' => 'json',
	)
);
check( 'json source requires url', is_wp_error( $bad_json ) );

$gitea_needs_host_at_fetch = PRU_Providers::gitea(
	array(
		'provider'   => 'gitea',
		'repository' => 'a/b',
		'host'       => '',
		'channel'    => 'release',
	)
);
check( 'gitea without host errors', is_wp_error( $gitea_needs_host_at_fetch ) );

$tmp = sys_get_temp_dir() . '/pru-rename-' . bin2hex( random_bytes( 4 ) );
mkdir( $tmp );
mkdir( $tmp . '/owner-repo-abc1234' );
file_put_contents( $tmp . '/owner-repo-abc1234/plugin.php', 'ok' );
$renamed = PRU_Updater::rename_folder( $tmp . '/owner-repo-abc1234/', 'demo-plugin' );
check( 'rename github folder to slug', ! is_wp_error( $renamed ) && is_dir( $tmp . '/demo-plugin' ) );
check( 'rename keeps files', is_file( $tmp . '/demo-plugin/plugin.php' ) );
$same = PRU_Updater::rename_folder( $tmp . '/demo-plugin/', 'demo-plugin' );
check( 'rename is no-op when already correct', ! is_wp_error( $same ) && is_dir( $tmp . '/demo-plugin' ) );
PRU_Http::unlink( $tmp . '/demo-plugin/plugin.php' );
@rmdir( $tmp . '/demo-plugin' );
@rmdir( $tmp );

check(
	'package url mentions repo',
	PRU_Providers::package_belongs(
		array(
			'provider'   => 'github',
			'repository' => 'acme/plug',
		),
		'https://api.github.com/repos/acme/plug/zipball/v1'
	)
);
check(
	'foreign package ignored',
	! PRU_Providers::package_belongs(
		array(
			'provider'   => 'github',
			'repository' => 'acme/plug',
		),
		'https://downloads.wordpress.org/plugin/hello.zip'
	)
);

echo "\n$passed passed, $failed failed\n";
exit( $failed > 0 ? 1 : 0 );
