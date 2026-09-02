<?php
/**
 * @package PrivateRepoUpdater
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'pru_sources' );
delete_site_transient( 'update_plugins' );
delete_site_transient( 'update_themes' );
