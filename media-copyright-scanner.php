<?php

/**
 * Plugin Name: Media Copyright Scanner
 * Description: Scans the media library for filenames, title text, alt text, and description text matching common stock image patterns.
 * Plugin URI:  https://github.com/robertdevore/media-copyright-scanner/
 * Version:     1.0.0
 * Author:      Robert DeVore
 * Author URI:  https://robertdevore.com/
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: media-copyright-scanner
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

require 'includes/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/robertdevore/media-copyright-scanner/',
	__FILE__,
	'media-copyright-scanner'
);

// Set the branch that contains the stable release.
$myUpdateChecker->setBranch( 'main' );

// Plugin Version
define( 'MEDIA_COPYRIGHT_SCANNER_VERSION', '1.0.0' );

/**
 * Registers the settings page under the Media menu.
 * 
 * @since  1.0.0
 * @return void
 */
function mcs_register_settings_page() {
    add_media_page(
        esc_html__( 'Media Copyright Scanner', 'media-copyright-scanner' ),
        esc_html__( 'Copyright Scanner', 'media-copyright-scanner' ),
        'manage_options',
        'media-copyright-scanner',
        'mcs_render_settings_page'
    );
}
add_action( 'admin_menu', 'mcs_register_settings_page' );

/**
 * Renders the settings page for the Media Copyright Scanner plugin.
 * 
 * @since  1.0.0
 * @return void
 */
function mcs_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>
            <?php esc_html_e( 'Media Copyright Scanner', 'media-copyright-scanner' ); ?>
            <?php
            echo '<a id="mcs-support-btn" href="https://robertdevore.com/contact/" target="_blank" class="button button-alt" style="margin-left: 10px;">
                    <span class="dashicons dashicons-format-chat" style="vertical-align: middle;"></span> ' . esc_html__( 'Support', 'media-copyright-scanner' ) . '
                </a>
                <a id="mcs-docs-btn" href="https://robertdevore.com/articles/media-copyright-scanner/" target="_blank" class="button button-alt" style="margin-left: 5px;">
                    <span class="dashicons dashicons-media-document" style="vertical-align: middle;"></span> ' . esc_html__( 'Documentation', 'media-copyright-scanner' ) . '
                </a>';
            ?>
        </h1>
        <hr />
        <div id="mcs-progress-container">
            <div class="mcs-progress-wrapper">
                <progress id="mcs-progress-bar" value="0" max="100"></progress>
                <span id="mcs-progress-text">0%</span>
            </div>
            <div class="mcs-buttons">
                <button id="mcs-start-scan" class="button button-primary">
                    <?php esc_html_e( 'Start Scan', 'media-copyright-scanner' ); ?>
                </button>
                <button id="mcs-download-csv" class="button" style="display: none;">
                    <?php esc_html_e( 'Download CSV', 'media-copyright-scanner' ); ?>
                </button>
                <button id="mcs-show-safe" class="button">
                    <?php esc_html_e( 'Show Safe Images', 'media-copyright-scanner' ); ?>
                </button>
            </div>
        </div>
        <table id="mcs-results-table" class="widefat fixed striped" style="display: none; margin-top: 20px;">
            <thead>
                <tr>
                    <th style="width: 5%;"><input type="checkbox" id="mcs-select-all" /></th>
                    <th style="width: 10%;"><?php esc_html_e( 'Media ID', 'media-copyright-scanner' ); ?></th>
                    <th style="width: 20%;"><?php esc_html_e( 'Filename', 'media-copyright-scanner' ); ?></th>
                    <th style="width: 20%;"><?php esc_html_e( 'Title Text', 'media-copyright-scanner' ); ?></th>
                    <th style="width: 20%;"><?php esc_html_e( 'Alt Text', 'media-copyright-scanner' ); ?></th>
                    <th style="width: 20%;"><?php esc_html_e( 'Description', 'media-copyright-scanner' ); ?></th>
                    <th style="width: 10%;"><?php esc_html_e( 'Source', 'media-copyright-scanner' ); ?></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <div class="mcs-buttons">
            <button id="mcs-save-flags" class="button button-secondary" style="display: none;">
                <?php esc_html_e( 'Save Flags', 'media-copyright-scanner' ); ?>
            </button>
        </div>
    </div>
    <?php
}

/**
 * Enqueues necessary scripts and localizes variables for the plugin.
 *
 * @param string $hook The current admin page.
 * 
 * @since  1.0.0
 * @return void
 */
function mcs_enqueue_scripts( $hook ) {
    if ( 'media_page_media-copyright-scanner' !== $hook ) {
        return;
    }

    wp_enqueue_style(
        'mcs-scan-styles',
        plugin_dir_url( __FILE__ ) . 'assets/css/styles.css',
        [],
        MEDIA_COPYRIGHT_SCANNER_VERSION,
        'all'
    );

    wp_enqueue_script(
        'mcs-scan-script',
        plugin_dir_url( __FILE__ ) . 'assets/js/scan-script.js',
        [ 'jquery' ],
        MEDIA_COPYRIGHT_SCANNER_VERSION,
        true
    );

    wp_localize_script( 'mcs-scan-script', 'mcsVars', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'mcs_scan_nonce' ),
        'domain'  => esc_js( parse_url( site_url(), PHP_URL_HOST ) ),
    ] );
}
add_action( 'admin_enqueue_scripts', 'mcs_enqueue_scripts' );

/**
 * Retrieves the default patterns with their respective sources.
 *
 * @since  1.0.0
 * @return array The default patterns organized by source.
 */
function mcs_get_default_patterns() {
    return [
        'Getty Images'           => [ '/gettyimages/i', '/gi-\d+/i' ],
        'Shutterstock'           => [ '/shutterstock_\d+/i', '/shutterstock-\d+/i' ],
        'iStockPhoto'            => [ '/istockphoto/i', '/iStock_\d+/i' ],
        'Adobe Stock'            => [ '/adobestock_\d+/i', '/AdobeStock_\d+/i' ],
        'Dreamstime'             => [ '/dreamstime/i', '/dreamstime_xl_\d+/i', '/dreamstimefree_\d+/i' ],
        'Pexels'                 => [ '/pexels/i', '/pexels-photo-\d+/i' ],
        'Unsplash'               => [ '/unsplash/i', '/photo-\d+-[a-f0-9]+/i' ],
        'Depositphotos'          => [ '/depositphotos/i' ],
        '123RF'                  => [ '/123rf/i' ],
        'Alamy'                  => [ '/alamy/i' ],
        'Bigstock'               => [ '/bigstock/i' ],
        'Freepik'                => [ '/freepik/i' ],
        'VectorStock'            => [ '/vectorstock/i' ],
        'Pixabay'                => [ '/pixabay/i' ],
        'RawPixel'               => [ '/rawpixel/i' ],
        'StockVault'             => [ '/stockvault/i' ],
        'Burst'                  => [ '/burst/i' ],
        'Kaboompics'             => [ '/kaboompics/i' ],
        'Reshot'                 => [ '/reshot/i' ],
        'Envato Elements'        => [ '/envato/i', '/envato-elements/i' ],
        'Canva'                  => [ '/canva/i', '/canva-photo-editor/i' ],
        'Picfair'                => [ '/picfair/i' ],
        'Pond5'                  => [ '/pond5/i' ],
        'EyeEm'                  => [ '/eyeem/i' ],
        'Stocksy'                => [ '/stocksy/i', '/stocksy_tx\d+/i' ],
        'Fotolia'                => [ '/fotolia/i', '/fotolia_\d+/i' ],
        'Twenty20'               => [ '/twenty20/i' ],
        'Pixnio'                 => [ '/pixnio/i' ],
        'Flickr'                 => [ '/flickr/i', '/flickr_\d+/i' ],
        'ISO Republic'           => [ '/isorepublic/i' ],
        'Public Domain Pictures' => [ '/publicdomainpictures/i' ],
        'Picjumbo'               => [ '/picjumbo/i' ],
        'Life of Pix'            => [ '/lifeofpix/i' ],
        'Gratisography'          => [ '/gratisography/i' ],
        'Magdeleine'             => [ '/magdeleine/i' ],
        'Negative Space'         => [ '/negativespace/i' ],
        'Styled Stock'           => [ '/styledstock/i' ],
        'Death to Stock'         => [ '/deathtostock/i' ],
        'SplitShire'             => [ '/splitshire/i' ],
        'ShotStash'              => [ '/shotstash/i' ],
        'FancyCrave'             => [ '/fancycrave/i' ],
        'Skitterphoto'           => [ '/skitterphoto/i' ],
        'LibreShot'              => [ '/libreshot/i' ],
        'PikWizard'              => [ '/pikwizard/i' ],
        'AllTheFreeStock'        => [ '/allthefreestock/i' ],
        'FoodiesFeed'            => [ '/foodiesfeed/i' ],
        'Travel Coffee Book'     => [ '/travelcoffeebook/i' ],
        'Moose Photos'           => [ '/moosephotos/i', '/moose_\d+/i' ],
        'Freestocks'             => [ '/freestocks/i' ],
        'Good Stock Photos'      => [ '/goodstockphotos/i' ],
    ];
}

/**
 * Allows patterns to be filterable by merging with default patterns.
 *
 * @param array $patterns Existing patterns.
 * 
 * @since  1.0.0
 * @return array Merged patterns.
 */
function mcs_filter_patterns( $patterns ) {
    return array_merge( $patterns, mcs_get_default_patterns() );
}
add_filter( 'mcs_patterns', 'mcs_filter_patterns' );

/**
 * Handles AJAX requests for scanning media in batches.
 * 
 * @since  1.0.0
 * @return void
 */
function mcs_scan_media() {
    check_ajax_referer( 'mcs_scan_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => esc_html__( 'Unauthorized user', 'media-copyright-scanner' ) ] );
    }

    $offset     = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
    $batch_size = apply_filters( 'mcs_scan_media_batch_size', 20 );

    $args = [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => $batch_size,
        'offset'         => $offset,
        'meta_query'     => [
            [
                'key'     => '_mcs_safe_flag',
                'compare' => 'NOT EXISTS',
            ],
        ],
    ];

    $query   = new WP_Query( $args );
    $results = [];

    // Fetch filterable patterns.
    $patterns = apply_filters( 'mcs_patterns', [] );

    foreach ( $query->posts as $attachment ) {
        $data = array(
            'id'          => $attachment->ID,
            'filename'    => basename( get_attached_file( $attachment->ID ) ),
            'title'       => get_the_title( $attachment->ID ),
            'alt'         => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
            'description' => $attachment->post_content,
            'source'      => '',
            'media_url'   => wp_get_attachment_url( $attachment->ID ),
        );

        foreach ( $patterns as $source => $regex_list ) {
            foreach ( $regex_list as $pattern ) {
                if (
                    preg_match( $pattern, $data['filename'] ) ||
                    preg_match( $pattern, $data['title'] ) ||
                    preg_match( $pattern, $data['alt'] ) ||
                    preg_match( $pattern, $data['description'] )
                ) {
                    $data['source'] = sanitize_text_field( $source );
                    $results[]      = $data;
                    break 2;
                }
            }
        }
    }

    wp_send_json( [
        'results' => $results,
        'hasMore' => $query->found_posts > ( $offset + $batch_size ),
    ] );
}
add_action( 'wp_ajax_mcs_scan_media', 'mcs_scan_media' );

/**
 * Handles AJAX requests to flag images as safe.
 * 
 * @since  1.0.0
 * @return void
 */
function mcs_flag_safe() {
    check_ajax_referer( 'mcs_scan_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized user', 'media-copyright-scanner' ) ) );
    }

    $safe_ids = isset( $_POST['safe_ids'] ) ? array_map( 'intval', (array) $_POST['safe_ids'] ) : array();

    if ( empty( $safe_ids ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'No images selected.', 'media-copyright-scanner' ) ) );
    }

    foreach ( $safe_ids as $id ) {
        update_post_meta( $id, '_mcs_safe_flag', 1 );
    }

    wp_send_json_success( array( 'message' => esc_html__( 'Images flagged as safe.', 'media-copyright-scanner' ) ) );
}
add_action( 'wp_ajax_mcs_flag_safe', 'mcs_flag_safe' );

/**
 * Handles AJAX requests to fetch safe images.
 * 
 * @since  1.0.0
 * @return void
 */
function mcs_get_safe_images() {
    check_ajax_referer( 'mcs_scan_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized user', 'media-copyright-scanner' ) ) );
    }

    $query = new WP_Query( [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'     => '_mcs_safe_flag',
                'compare' => 'EXISTS',
            ],
        ],
    ] );

    $results = [];

    foreach ( $query->posts as $attachment ) {
        $results[] = [
            'id'          => $attachment->ID,
            'filename'    => basename( get_attached_file( $attachment->ID ) ),
            'title'       => get_the_title( $attachment->ID ),
            'alt'         => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
            'description' => $attachment->post_content,
            'media_url'   => wp_get_attachment_url( $attachment->ID ),
        ];
    }

    wp_send_json( [ 'results' => $results ] );
}
add_action( 'wp_ajax_mcs_get_safe_images', 'mcs_get_safe_images' );
