<?php
/**
* Plugin Name: FP Table Comments
* Description: Adds comments column to plugins table
* Version: 1.2.3
* Author: FunkPd
* Author URI: https://funkpd.com
* License: GPL-2.0+
* Text Domain: fp-table-comments
* Requires PHP: 7.2
* Tested up to: 6.2
*/

defined('ABSPATH') || exit;

// WHY: Global constants are easier to maintain than class properties
define('FPTC_POST_TYPE', 'plugin_comment');
define('FPTC_PLUGIN_FILE', __FILE__);

// Hook declarations at top for clarity
add_action('init', 'fp_register_post_type');
add_filter('manage_plugins_columns', 'fp_add_comments_column');
add_action('manage_plugins_custom_column', 'fp_display_comments_column', 10, 3);
add_action('admin_enqueue_scripts', 'fp_enqueue_scripts');
add_action('wp_ajax_fptc_save_comments', 'fp_save_comments');
register_activation_hook(FPTC_PLUGIN_FILE, 'fp_activate');
register_deactivation_hook(FPTC_PLUGIN_FILE, 'fp_deactivate');

// WHY: Combined activation functions to reduce cognitive load
function fp_activate() {
    fp_register_post_type();
    flush_rewrite_rules();
}

function fp_deactivate() {
    unregister_post_type(FPTC_POST_TYPE);
    flush_rewrite_rules();
}

function fp_register_post_type() {
    register_post_type(FPTC_POST_TYPE, [
        'labels' => [
            'name' => 'Plugin Comments',
            'singular_name' => 'Plugin Comment'
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'supports' => ['title', 'editor']
    ]);
}

function fp_add_comments_column($columns) {
    $columns['comments'] = 'Comments';
    return $columns;
}

// WHY: This function gets a post by its plugin comment title - used in two places
function fp_get_plugin_comment_post($plugin_file) {
    $post_title = 'Plugin Comment: ' . $plugin_file;
    
    // WHY: Keep the original working query approach
    $query = new WP_Query([
        'post_type' => FPTC_POST_TYPE,
        'title' => $post_title,
        'posts_per_page' => 1
    ]);
    
    return $query->have_posts() ? $query->posts[0] : null;
}

function fp_display_comments_column($column_name, $plugin_file) {
    if ($column_name !== 'comments') {
        return;
    }

    $post = fp_get_plugin_comment_post($plugin_file);
    
    printf(
        '<div class="fp-comment-wrap" style="position:relative">
            <textarea class="fp-plugin-comments" data-plugin-file="%s" style="min-height:40px;width:100%%">%s</textarea>
            <a href="%s" class="fp-edit-link" title="Edit in full editor" style="position:absolute;top:0;right:0;padding:5px">&#9432;</a>
        </div>',
        esc_attr($plugin_file),
        esc_textarea($post ? $post->post_content : ''),
        esc_url($post ? get_edit_post_link($post->ID) : '#')
    );
}

function fp_enqueue_scripts($hook) {
    if ($hook !== 'plugins.php') {
        return;
    }

    wp_enqueue_script(
        'fp-table-comments', 
        plugin_dir_url(FPTC_PLUGIN_FILE) . 'table-comments.js', 
        [], 
        '1.0', 
        true
    );

    wp_localize_script('fp-table-comments', 'fpAjax', [
        'url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('fp_save_comments')
    ]);
}

function fp_save_comments() {
    if (!check_ajax_referer('fp_save_comments', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
    }

    // WHY: Security check to ensure user has appropriate permissions
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permission denied');
    }

    if (empty($_POST['plugin'])) {
        wp_send_json_error('Missing plugin data');
    }
    
    if (!isset($_POST['comments'])) {
        wp_send_json_error('Missing comments data');
    }

    $plugin = sanitize_text_field($_POST['plugin']);
    $comments = sanitize_textarea_field($_POST['comments']);
    
    $post_title = 'Plugin Comment: ' . $plugin;
    $post = fp_get_plugin_comment_post($plugin);
    
    $post_data = [
        'post_title' => $post_title,
        'post_content' => $comments,
        'post_type' => FPTC_POST_TYPE,
        'post_status' => 'publish'
    ];

    if ($post) {
        $post_data['ID'] = $post->ID;
        wp_update_post($post_data);
    } else {
        wp_insert_post($post_data);
    }

    wp_send_json_success();
}
