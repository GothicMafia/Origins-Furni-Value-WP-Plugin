<?php
/**
 * Plugin Name: Habbo Furni Updater
 * Description: Updates individual Habbo furniture items using the TraderClub API and displays them categorized with custom styling.
 * Version: 1.5
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Add a menu item in the WordPress admin
function habbo_furni_menu() {
    add_menu_page(
        'Habbo Furni Updater',
        'Habbo Furni Updater',
        'manage_options',
        'habbo-furni-updater',
        'habbo_furni_updater_page'
    );
}
add_action( 'admin_menu', 'habbo_furni_menu' );

// Display the plugin settings page
function habbo_furni_updater_page() {
    ?>
    <div class="wrap">
        <h1>Habbo Furni Updater</h1>
        <button id="update-furni-data">Update Furni Data</button>
        <div id="furni-updater-result"></div>
    </div>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#update-furni-data').on('click', function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'update_furni_data',
                    },
                    success: function(response) {
                        $('#furni-updater-result').html(response);
                    }
                });
            });
        });
    </script>
    <?php
}

// Handle the AJAX request to update furni data
function habbo_update_furni_data() {
    $api_url = 'https://tc-api.serversia.com/items/';
    
    // Fetch the API data
    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        error_log('Failed to fetch furni data from API');
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $furni_items = json_decode($body, true);

    // Log the fetched data
    error_log('Fetched furni data: ' . print_r($furni_items, true));

    if (!empty($furni_items)) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'habbo_furni';

        foreach ($furni_items as $item) {
            $wpdb->replace(
                $table_name,
                array(
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'coin_val' => $item['coin_val'],
                    'hc_val' => $item['hc_val'],
                    'cola_val' => $item['cola_val'],
                    'slug' => $item['slug'],
                    'image' => $item['image'],
                    'trend' => $item['trend'],
                    'category_id' => $item['category_id']
                ),
                array(
                    '%d', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%d'
                )
            );
        }

        error_log('Furni data updated successfully via manual update or cron job.');
    } else {
        error_log('No furni data found in the API response.');
    }
}
add_action('wp_ajax_update_furni_data', 'habbo_update_furni_data');

// Create database table on plugin activation
function habbo_furni_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'habbo_furni';
    
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL,
        name text NOT NULL,
        description text NOT NULL,
        coin_val float DEFAULT 0 NOT NULL,
        hc_val float DEFAULT 0 NOT NULL,
        cola_val float DEFAULT 0 NOT NULL,
        slug varchar(100) NOT NULL,
        image varchar(255) NOT NULL,
        trend varchar(50),
        category_id mediumint(9) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Schedule the cron job upon plugin activation
    habbo_furni_schedule_event();
}
register_activation_hook(__FILE__, 'habbo_furni_install');

// Schedule the cron event on plugin activation
function habbo_furni_schedule_event() {
    if ( ! wp_next_scheduled( 'habbo_furni_update_event' ) ) {
        wp_schedule_event( time(), 'daily', 'habbo_furni_update_event' );
    }
}

// Clear the cron event on plugin deactivation
function habbo_furni_clear_scheduled_event() {
    $timestamp = wp_next_scheduled( 'habbo_furni_update_event' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'habbo_furni_update_event' );
    }
}
register_deactivation_hook( __FILE__, 'habbo_furni_clear_scheduled_event' );

// Hook the furni update function to the scheduled event
add_action( 'habbo_furni_update_event', 'habbo_update_furni_data' );

// Enqueue CSS styles
function habbo_furni_enqueue_styles() {
    wp_enqueue_style( 'habbo-furni-styles', plugin_dir_url( __FILE__ ) . 'css/habbo-furni-styles.css' );
}
add_action( 'wp_enqueue_scripts', 'habbo_furni_enqueue_styles' );

// Shortcode functions for each category
function display_furni_by_category($category_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'habbo_furni';
    $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE category_id = %d", $category_id));

    // Log the items retrieved from the database
    error_log('Items retrieved for category ' . $category_id . ': ' . print_r($items, true));

    if (!empty($items)) {
        $output = '<div class="habbo-furni-list">';

        foreach ($items as $item) {
            $output .= render_furni_item($item);
        }

        $output .= '</div>';

        return $output;
    } else {
        return 'No furniture items found.';
    }
}

function render_furni_item($item) {
    $output = '<div class="furni-item">';
    $output .= '<div class="furni-image"><img src="' . esc_url($item->image) . '" alt="' . esc_attr($item->name) . '" /></div>';
    $output .= '<div class="furni-details">';
    $output .= '<h3 class="furni-name">' . esc_html($item->name) . '</h3>';
    $output .= '<p class="furni-description">' . esc_html($item->description) . '</p>';
    $output .= '<div class="furni-value">' . esc_html($item->hc_val) . ' HC</div>';
    $output .= '</div>'; // .furni-details
    $output .= '</div>'; // .furni-item
    return $output;
}

// Shortcodes for individual categories
function hc_rares_furni_shortcode() {
    return display_furni_by_category(1); // Use appropriate category ID for HC Rares
}
add_shortcode('hc_rares_furni', 'hc_rares_furni_shortcode');

function super_rares_furni_shortcode() {
    return display_furni_by_category(2); // Use appropriate category ID for Super Rares
}
add_shortcode('super_rares_furni', 'super_rares_furni_shortcode');

function funky_friday_furni_shortcode() {
    return display_furni_by_category(3); // Use appropriate category ID for Funky Friday
}
add_shortcode('funky_friday_furni', 'funky_friday_furni_shortcode');

function rares_furni_shortcode() {
    return display_furni_by_category(4); // Use appropriate category ID for Rares
}
add_shortcode('rares_furni', 'rares_furni_shortcode');
