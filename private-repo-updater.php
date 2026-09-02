<?php
/**
 * Plugin Name: Private Repo Updater
 * Plugin URI: https://github.com/antonvyukov/test
 * Description: Update plugins and themes from private GitHub, GitLab, Gitea/Forgejo, or Bitbucket repositories — and from any server that serves a version JSON file.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Private Repo Updater
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: private-repo-updater
 *
 * @package PrivateRepoUpdater
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PRU_VERSION', '1.0.0' );
define( 'PRU_FILE', __FILE__ );
define( 'PRU_DIR', plugin_dir_path( __FILE__ ) );
define( 'PRU_URL', plugin_dir_url( __FILE__ ) );

require_once PRU_DIR . 'includes/class-sources.php';
require_once PRU_DIR . 'includes/class-http.php';
require_once PRU_DIR . 'includes/class-providers.php';
require_once PRU_DIR . 'includes/class-updater.php';
require_once PRU_DIR . 'includes/class-admin.php';

add_action(
	'plugins_loaded',
	static function () {
		PRU_Updater::init();
		if ( is_admin() ) {
			PRU_Admin::init();
		}
	}
);
