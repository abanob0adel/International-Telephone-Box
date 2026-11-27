<?php
/**
 * Uninstall: remove all stored data.
 *
 * @package International_Telephone_Box
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Delete the `intelebox_settings` option on the current site.
 */
function intelebox_uninstall_delete_option() {
    delete_option( 'intelebox_settings' );
}

if ( is_multisite() ) {
    // Delete on every site in the network.
    $intelebox_site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );

    foreach ( $intelebox_site_ids as $intelebox_site_id ) {
        switch_to_blog( (int) $intelebox_site_id );
        intelebox_uninstall_delete_option();
        restore_current_blog();
    }

    delete_site_option( 'intelebox_settings' );
} else {
    intelebox_uninstall_delete_option();
}
