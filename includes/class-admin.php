<?php
/**
 * Settings screen: register private plugin/theme update sources.
 *
 * @package PrivateRepoUpdater
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRU_Admin {

	const PAGE = 'private-repo-updater';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_post' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PRU_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu() {
		add_options_page(
			__( 'Private Repo Updates', 'private-repo-updater' ),
			__( 'Private Repo Updates', 'private-repo-updater' ),
			'update_plugins',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * @param array<int, string> $links
	 * @return array<int, string>
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'private-repo-updater' ) . '</a>' );
		return $links;
	}

	/**
	 * @param string $hook
	 */
	public static function assets( $hook ) {
		if ( $hook !== 'settings_page_' . self::PAGE ) {
			return;
		}
		wp_add_inline_style(
			'wp-admin',
			'.pru-ok{color:#00a32a}.pru-update{color:#996800;font-weight:600}.pru-err{color:#d63638}.pru-muted{color:#646970}.pru-actions form{display:inline}'
		);
		wp_register_script( 'pru-admin', false, array(), PRU_VERSION, true );
		wp_enqueue_script( 'pru-admin' );
		wp_add_inline_script(
			'pru-admin',
			"document.addEventListener('DOMContentLoaded',function(){
				var p=document.getElementById('pru-provider'), c=document.getElementById('pru-channel');
				function sync(){
					if(!p) return;
					var v=p.value, ch=c?c.value:'release';
					document.querySelectorAll('[data-pru]').forEach(function(el){
						var need=el.getAttribute('data-pru').split(/\\s+/);
						var ok=need.some(function(n){
							if(n==='json') return v==='json';
							if(n==='vcs') return v!=='json';
							if(n==='host-gitea') return v==='gitea';
							if(n==='asset') return v==='github'||v==='gitea';
							if(n==='branch') return v!=='json' && ch==='branch';
							return n===v;
						});
						el.style.display=ok?'':'none';
					});
				}
				if(p) p.addEventListener('change',sync);
				if(c) c.addEventListener('change',sync);
				sync();
			});"
		);
	}

	public static function handle_post() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		if ( empty( $_POST['pru_action'] ) ) {
			return;
		}
		check_admin_referer( 'pru_admin' );

		$action = sanitize_key( wp_unslash( $_POST['pru_action'] ) );

		if ( $action === 'save' ) {
			$id       = sanitize_text_field( wp_unslash( $_POST['pru_id'] ?? '' ) );
			$existing = $id !== '' ? PRU_Sources::get( $id ) : null;
			if ( $existing && ! empty( $existing['readonly'] ) ) {
				self::redirect( array( 'error' => rawurlencode( __( 'This source is registered in code and cannot be edited here.', 'private-repo-updater' ) ) ) );
			}
			$source = PRU_Sources::sanitize( wp_unslash( $_POST['pru'] ?? array() ), $existing );
			if ( is_wp_error( $source ) ) {
				self::redirect( array( 'error' => rawurlencode( $source->get_error_message() ) ) );
			}
			if ( $id !== '' ) {
				$source['id'] = $id;
			}
			PRU_Sources::save( $source );
			self::redirect( array( 'updated' => '1' ) );
		}

		if ( $action === 'delete' ) {
			$id = sanitize_text_field( wp_unslash( $_POST['pru_id'] ?? '' ) );
			PRU_Sources::delete( $id );
			self::redirect( array( 'updated' => '1' ) );
		}

		if ( $action === 'check' ) {
			PRU_Updater::force_check();
			self::redirect( array( 'checked' => '1' ) );
		}
	}

	/**
	 * @param array<string, string> $args
	 */
	protected static function redirect( $args ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php?page=' . self::PAGE ) ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		$edit_id = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : '';
		$editing = $edit_id !== '' ? PRU_Sources::get( $edit_id ) : null;
		$sources = PRU_Sources::all();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Private Repo Updates', 'private-repo-updater' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Register plugins and themes hosted in private Git repositories. WordPress will then show updates the usual way.', 'private-repo-updater' ) . '</p>';

		if ( ! empty( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'private-repo-updater' ) . '</p></div>';
		}
		if ( ! empty( $_GET['checked'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Checked for updates. Open Plugins or Themes if a new version was found.', 'private-repo-updater' ) . '</p></div>';
		}
		if ( ! empty( $_GET['error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( rawurldecode( wp_unslash( $_GET['error'] ) ) ) . '</p></div>';
		}

		self::render_table( $sources );
		self::render_form( $editing );
		echo '</div>';
	}

	/**
	 * @param array<int, array<string, mixed>> $sources
	 */
	protected static function render_table( $sources ) {
		echo '<h2>' . esc_html__( 'Sources', 'private-repo-updater' ) . '</h2>';
		echo '<form method="post">';
		wp_nonce_field( 'pru_admin' );
		echo '<input type="hidden" name="pru_action" value="check" />';
		submit_button( __( 'Check for updates now', 'private-repo-updater' ), 'secondary', 'submit', false );
		echo '</form>';

		if ( ! $sources ) {
			echo '<p class="pru-muted">' . esc_html__( 'No sources yet. Add one below.', 'private-repo-updater' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped" style="margin-top:12px">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Package', 'private-repo-updater' ) . '</th>';
		echo '<th>' . esc_html__( 'Type', 'private-repo-updater' ) . '</th>';
		echo '<th>' . esc_html__( 'Repository', 'private-repo-updater' ) . '</th>';
		echo '<th>' . esc_html__( 'Installed', 'private-repo-updater' ) . '</th>';
		echo '<th>' . esc_html__( 'Remote', 'private-repo-updater' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'private-repo-updater' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'private-repo-updater' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $sources as $source ) {
			$installed = PRU_Updater::installed_version( $source );
			$remote    = (string) ( $source['last_version'] ?? '' );
			$error     = (string) ( $source['last_error'] ?? '' );
			$label     = $source['label'] ? $source['label'] : $source['slug'];
			$repo      = ( $source['provider'] === 'json' ) ? ( $source['json_url'] ?? '' ) : ( $source['repository'] ?? '' );
			$readonly  = ! empty( $source['readonly'] );

			if ( $error !== '' ) {
				$status = '<span class="pru-err">' . esc_html( $error ) . '</span>';
			} elseif ( $installed === '' ) {
				$status = '<span class="pru-err">' . esc_html__( 'Not installed', 'private-repo-updater' ) . '</span>';
			} elseif ( $remote !== '' && version_compare( $remote, $installed, '>' ) ) {
				$status = '<span class="pru-update">' . esc_html__( 'Update available', 'private-repo-updater' ) . '</span>';
			} elseif ( $remote !== '' ) {
				$status = '<span class="pru-ok">' . esc_html__( 'Up to date', 'private-repo-updater' ) . '</span>';
			} else {
				$status = '<span class="pru-muted">' . esc_html__( 'Not checked yet', 'private-repo-updater' ) . '</span>';
			}

			echo '<tr>';
			echo '<td><strong>' . esc_html( $label ) . '</strong><br><code>' . esc_html( $source['slug'] ) . '</code></td>';
			echo '<td>' . esc_html( $source['type'] ) . '</td>';
			echo '<td>' . esc_html( $source['provider'] ) . '<br><code>' . esc_html( $repo ) . '</code></td>';
			echo '<td>' . esc_html( $installed !== '' ? $installed : '—' ) . '</td>';
			echo '<td>' . esc_html( $remote !== '' ? $remote : '—' ) . '</td>';
			echo '<td>' . $status . '</td>';
			echo '<td class="pru-actions">';
			if ( ! $readonly ) {
				$edit = admin_url( 'options-general.php?page=' . self::PAGE . '&edit=' . rawurlencode( $source['id'] ) );
				echo '<a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'private-repo-updater' ) . '</a> ';
				echo '<form method="post" onsubmit="return confirm(\'' . esc_js( __( 'Delete this source?', 'private-repo-updater' ) ) . '\');">';
				wp_nonce_field( 'pru_admin' );
				echo '<input type="hidden" name="pru_action" value="delete" />';
				echo '<input type="hidden" name="pru_id" value="' . esc_attr( $source['id'] ) . '" />';
				echo '<button type="submit" class="button-link delete">' . esc_html__( 'Delete', 'private-repo-updater' ) . '</button>';
				echo '</form>';
			} else {
				echo '<span class="pru-muted">' . esc_html__( 'Code-defined', 'private-repo-updater' ) . '</span>';
			}
			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * @param array<string, mixed>|null $editing
	 */
	protected static function render_form( $editing ) {
		$is_edit = is_array( $editing );
		$v       = wp_parse_args(
			$editing ?: array(),
			array(
				'id'           => '',
				'label'        => '',
				'type'         => 'plugin',
				'slug'         => '',
				'provider'     => 'github',
				'repository'   => '',
				'host'         => '',
				'channel'      => 'release',
				'ref'          => 'main',
				'json_url'     => '',
				'prefer_asset' => true,
			)
		);

		echo '<h2>' . esc_html( $is_edit ? __( 'Edit source', 'private-repo-updater' ) : __( 'Add source', 'private-repo-updater' ) ) . '</h2>';
		echo '<form method="post" class="pru-form" autocomplete="off">';
		wp_nonce_field( 'pru_admin' );
		echo '<input type="hidden" name="pru_action" value="save" />';
		echo '<input type="hidden" name="pru_id" value="' . esc_attr( $v['id'] ) . '" />';
		echo '<input type="hidden" name="pru[prefer_asset]" value="0" />';

		echo '<table class="form-table" role="presentation">';

		self::row(
			__( 'Label', 'private-repo-updater' ),
			'<input type="text" class="regular-text" name="pru[label]" value="' . esc_attr( $v['label'] ) . '" />',
			__( 'Optional. Shown only on this screen.', 'private-repo-updater' )
		);

		self::row(
			__( 'Type', 'private-repo-updater' ),
			'<label><input type="radio" name="pru[type]" value="plugin"' . checked( $v['type'], 'plugin', false ) . '> ' . esc_html__( 'Plugin', 'private-repo-updater' ) . '</label> '
			. '<label><input type="radio" name="pru[type]" value="theme"' . checked( $v['type'], 'theme', false ) . '> ' . esc_html__( 'Theme', 'private-repo-updater' ) . '</label>'
		);

		self::row(
			__( 'Installed slug', 'private-repo-updater' ),
			'<input type="text" class="regular-text" name="pru[slug]" value="' . esc_attr( $v['slug'] ) . '" required placeholder="my-plugin/my-plugin.php" />',
			__( 'Plugin: folder/main-file.php. Theme: directory name. The repository root must be that folder.', 'private-repo-updater' )
		);

		$providers = array(
			'github'    => 'GitHub',
			'gitlab'    => 'GitLab',
			'gitea'     => 'Gitea / Forgejo',
			'bitbucket' => 'Bitbucket',
			'json'      => __( 'JSON update feed', 'private-repo-updater' ),
		);
		$select = '<select name="pru[provider]" id="pru-provider">';
		foreach ( $providers as $key => $label ) {
			$select .= '<option value="' . esc_attr( $key ) . '"' . selected( $v['provider'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$select .= '</select>';
		self::row( __( 'Provider', 'private-repo-updater' ), $select );

		echo '<tr data-pru="vcs"><th scope="row">' . esc_html__( 'Repository', 'private-repo-updater' ) . '</th><td>';
		echo '<input type="text" class="regular-text" name="pru[repository]" value="' . esc_attr( $v['repository'] ) . '" placeholder="owner/repo" />';
		echo '<p class="description">' . esc_html__( 'owner/repo. GitLab subgroups work: group/sub/repo.', 'private-repo-updater' ) . '</p></td></tr>';

		echo '<tr data-pru="vcs"><th scope="row">' . esc_html__( 'Host', 'private-repo-updater' ) . '</th><td>';
		echo '<input type="url" class="regular-text" name="pru[host]" value="' . esc_attr( $v['host'] ) . '" placeholder="https://git.example.com" />';
		echo '<p class="description">' . esc_html__( 'Leave empty for github.com / gitlab.com. Required for Gitea, Forgejo, GitHub Enterprise, and self-hosted GitLab.', 'private-repo-updater' ) . '</p></td></tr>';

		echo '<tr data-pru="json"><th scope="row">' . esc_html__( 'JSON URL', 'private-repo-updater' ) . '</th><td>';
		echo '<input type="url" class="regular-text" name="pru[json_url]" value="' . esc_attr( $v['json_url'] ) . '" placeholder="https://example.com/plugin.json" />';
		echo '<p class="description">' . esc_html__( 'Must return JSON with version and download_url. Optional Bearer token below.', 'private-repo-updater' ) . '</p></td></tr>';

		$token_help = $is_edit && ! empty( $editing['token'] )
			? __( 'A token is saved. Leave blank to keep it, or enter a new one to replace it.', 'private-repo-updater' )
			: __( 'Personal access token for private repositories. Fine-grained GitHub tokens need Contents: Read. Or define PRU_ACCESS_TOKEN in wp-config.php.', 'private-repo-updater' );
		$token_field = '<input type="password" class="regular-text" name="pru[token]" value="" autocomplete="new-password" />';
		if ( $is_edit && ! empty( $editing['token'] ) ) {
			$token_field .= '<p><label><input type="checkbox" name="pru[clear_token]" value="1" /> ' . esc_html__( 'Remove saved token', 'private-repo-updater' ) . '</label></p>';
		}
		self::row( __( 'Access token', 'private-repo-updater' ), $token_field, $token_help );

		$channels = array(
			'release' => __( 'Latest release', 'private-repo-updater' ),
			'tag'     => __( 'Latest version tag', 'private-repo-updater' ),
			'branch'  => __( 'Branch (read Version header)', 'private-repo-updater' ),
		);
		$ch = '<select name="pru[channel]" id="pru-channel">';
		foreach ( $channels as $key => $label ) {
			$ch .= '<option value="' . esc_attr( $key ) . '"' . selected( $v['channel'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$ch .= '</select>';
		echo '<tr data-pru="vcs"><th scope="row">' . esc_html__( 'Version source', 'private-repo-updater' ) . '</th><td>' . $ch . '</td></tr>';

		echo '<tr data-pru="branch"><th scope="row">' . esc_html__( 'Branch', 'private-repo-updater' ) . '</th><td>';
		echo '<input type="text" class="regular-text" name="pru[ref]" value="' . esc_attr( $v['ref'] ) . '" /></td></tr>';

		echo '<tr data-pru="asset"><th scope="row">' . esc_html__( 'Release zip', 'private-repo-updater' ) . '</th><td>';
		echo '<label><input type="checkbox" name="pru[prefer_asset]" value="1"' . checked( ! empty( $v['prefer_asset'] ), true, false ) . '> ';
		echo esc_html__( 'Prefer a .zip asset attached to the release (otherwise download the source zipball).', 'private-repo-updater' );
		echo '</label></td></tr>';

		echo '</table>';
		submit_button( $is_edit ? __( 'Update source', 'private-repo-updater' ) : __( 'Add source', 'private-repo-updater' ) );
		if ( $is_edit ) {
			echo '<p><a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Cancel', 'private-repo-updater' ) . '</a></p>';
		}
		echo '</form>';
	}

	/**
	 * @param string $label
	 * @param string $field html
	 * @param string $help
	 */
	protected static function row( $label, $field, $help = '' ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . $field;
		if ( $help !== '' ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}
}
