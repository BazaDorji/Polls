<?php
/*
Plugin Name: Simple Polls Plugin
Description: Adds a polling feature to your WordPress site, allowing visitors to vote on various topics and see the results.
Version: 1.0
Author: [Dorji Norbu]
License: GPL2
*/

// Register Custom Post Type for Polls
function spp_create_poll_post_type() {
    register_post_type('spp_poll',
        array(
            'labels'      => array(
                'name'          => __('Polls', 'textdomain'),
                'singular_name' => __('Poll', 'textdomain'),
            ),
            'public'      => true,
            'has_archive' => true,
            'supports'    => array('title', 'editor'),
            'menu_icon'   => 'dashicons-chart-bar',
        )
    );
}
add_action('init', 'spp_create_poll_post_type');

// Add Meta Boxes for Poll Options
function spp_add_poll_meta_boxes() {
    add_meta_box(
        'spp_poll_options',
        __('Poll Options', 'textdomain'),
        'spp_poll_options_callback',
        'spp_poll',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'spp_add_poll_meta_boxes');

// Callback Function to Display Poll Options Meta Box
function spp_poll_options_callback($post) {
    wp_nonce_field('spp_save_poll_options', 'spp_poll_options_nonce');
    $options = get_post_meta($post->ID, '_spp_poll_options', true);
    echo '<label for="spp_poll_options">Enter Poll Options (comma separated):</label>';
    echo '<textarea id="spp_poll_options" name="spp_poll_options" rows="5" style="width:100%;">' . esc_textarea($options) . '</textarea>';
}

// Save Poll Options Meta Box Data
function spp_save_poll_options($post_id) {
    if (!isset($_POST['spp_poll_options_nonce']) || !wp_verify_nonce($_POST['spp_poll_options_nonce'], 'spp_save_poll_options')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $options = sanitize_text_field($_POST['spp_poll_options']);
    update_post_meta($post_id, '_spp_poll_options', $options);
}
add_action('save_post', 'spp_save_poll_options');

// Enqueue the custom CSS for the polls
function spp_enqueue_styles() {
    wp_enqueue_style('simple-polls-style', plugin_dir_url(__FILE__) . 'css/simple-polls.css');
}
add_action('wp_enqueue_scripts', 'spp_enqueue_styles');

// Shortcode to Display Poll and Handle Voting
function spp_poll_shortcode($atts) {
    $atts = shortcode_atts(array('id' => null), $atts);
    $poll_id = $atts['id'];

    if (!$poll_id) return 'No poll ID provided.';

    $poll = get_post($poll_id);
    if (!$poll || $poll->post_type != 'spp_poll') return 'Invalid poll ID.';

    $options = explode(',', get_post_meta($poll_id, '_spp_poll_options', true));

    if ($_POST && isset($_POST['spp_poll_vote'])) {
        $vote = sanitize_text_field($_POST['spp_poll_vote']);
        $votes = get_post_meta($poll_id, '_spp_poll_votes', true);
        $votes = $votes ? json_decode($votes, true) : array();

        if (array_key_exists($vote, $votes)) {
            $votes[$vote]++;
        } else {
            $votes[$vote] = 1;
        }

        update_post_meta($poll_id, '_spp_poll_votes', json_encode($votes));
    }

    $output = '<div class="spp-poll-container">';
    $output .= '<h3 class="spp-poll-title">' . esc_html($poll->post_title) . '</h3>';
    $output .= '<form method="POST">';
    foreach ($options as $option) {
        $output .= '<div class="spp-poll-option">';
        $output .= '<label>';
        $output .= '<input type="radio" name="spp_poll_vote" value="' . esc_attr(trim($option)) . '"> ' . esc_html(trim($option));
        $output .= '</label>';
        $output .= '</div>';
    }
    $output .= '<input type="submit" class="spp-poll-submit" value="Vote">';
    $output .= '</form>';

    $votes = get_post_meta($poll_id, '_spp_poll_votes', true);
    $votes = $votes ? json_decode($votes, true) : array();

    $output .= '<div class="spp-poll-results">';
    $output .= '<h4>Results:</h4>';
    foreach ($votes as $option => $count) {
        $percentage = ($count / array_sum($votes)) * 100;
        $output .= '<div class="spp-poll-result-item">';
        $output .= '<div class="spp-poll-result-bar" style="width: ' . $percentage . '%;"></div>';
        $output .= '<span class="spp-poll-result-label">' . esc_html($option) . ': ' . esc_html($count) . ' votes (' . round($percentage) . '%)</span>';
        $output .= '</div>';
    }
    $output .= '</div>';
    $output .= '</div>';

    return $output;
}
add_shortcode('spp_poll', 'spp_poll_shortcode');

// ===========================
// Admin Menu and Settings Page Code
// ===========================

// Add a new menu item under "Settings" for the plugin's configuration page
function spp_add_admin_menu() {
    add_options_page(
        'Simple Polls Settings', 
        'Simple Polls', 
        'manage_options', 
        'spp_settings', 
        'spp_settings_page'
    );
}
add_action('admin_menu', 'spp_add_admin_menu');

// Create the settings page content
function spp_settings_page() {
    ?>
    <div class="wrap">
        <h1>Simple Polls Settings</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('spp_settings_group');
            do_settings_sections('spp_settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings, sections, and fields
function spp_register_settings() {
    register_setting('spp_settings_group', 'spp_default_option');
    add_settings_section('spp_main_section', 'Main Settings', null, 'spp_settings');
    add_settings_field('spp_default_option_field', 'Default Option', 'spp_default_option_callback', 'spp_settings', 'spp_main_section');
}
add_action('admin_init', 'spp_register_settings');

// Callback function to display the field for the default option
function spp_default_option_callback() {
    $default_option = get_option('spp_default_option');
    echo '<input type="text" name="spp_default_option" value="' . esc_attr($default_option) . '">';
}
