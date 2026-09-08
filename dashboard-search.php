<?php

/**
 * Plugin Name: Dashboard Search
 * Description: Dashboard Search.
 * Version: 1.0.0
 * Plugin URI: https://github.com/Brugman/dashboard-search
 * Author: Tim Brugman
 * Author URI: https://timbr.dev/
 * Text Domain: tbds
 */

if ( ! defined( 'ABSPATH' ) )
    exit;

function tbds_search_targets()
{
    $search_targets = [];

    $admin_url = get_admin_url();

    // add Post Types
    $post_types = get_post_types([
        'show_ui' => true,
    ], 'objects' );

    foreach ( $post_types as $post_type )
    {
        // respect types that were deliberately hidden from the admin menu
        if ( $post_type->show_in_menu === false )
            continue;

        $search_targets[ $post_type->name ] = [
            'post_type'  => $post_type->name,
            'label'      => $post_type->label,
            'action_url' => $admin_url.'edit.php?post_type='.$post_type->name,
            'cap'        => $post_type->cap->edit_posts,
        ];
    }

    // add Taxonomies
    $taxonomies = get_taxonomies([
        'show_ui' => true,
    ], 'objects' );

    foreach ( $taxonomies as $taxonomy )
    {
        // respect taxonomies that were deliberately hidden from the admin menu
        if ( $taxonomy->show_in_menu === false )
            continue;

        // add a search bar for every visible post type this taxonomy is attached to
        foreach ( $taxonomy->object_type as $attached_post_type )
        {
            if ( ! isset( $search_targets[ $attached_post_type ] ) )
                continue;

            $target_key = 'taxonomy_'.$taxonomy->name.'_'.$attached_post_type;

            if ( isset( $search_targets[ $target_key ] ) )
                continue;

            $post_type_object = get_post_type_object( $attached_post_type );

            $search_targets[ $target_key ] = [
                'post_type'  => false,
                // translators: 1: post type singular name, 2: taxonomy name.
                'label'      => sprintf( __( '%1$s %2$s', 'tbds' ), $post_type_object->labels->singular_name, $taxonomy->label ),
                'action_url' => $admin_url.'edit-tags.php',
                'cap'        => $taxonomy->cap->manage_terms,
                'extra_params' => [
                    'taxonomy' => $taxonomy->name,
                    'post_type' => $attached_post_type,
                ],
            ];
        }
    }

    // add Gravity Forms
    if ( class_exists( 'GFCommon' ) && ! isset( $search_targets['gform'] ) )
    {
        $search_targets['gform'] = [
            'post_type'  => false,
            'label'      => __( 'Forms', 'tbds' ),
            'action_url' => $admin_url.'admin.php',
            'cap'        => 'gravityforms_edit_forms',
            'extra_params' => [
                'page' => 'gf_edit_forms',
            ]
        ];
    }

    // move attachment to the end
    if ( isset( $search_targets['attachment'] ) )
    {
        $attachment = $search_targets['attachment'];
        unset( $search_targets['attachment'] );
        $search_targets['attachment'] = $attachment;
    }

    // add Comments
    $comment_counts = wp_count_comments();
    $comments_enabled = get_option( 'default_comment_status' ) === 'open';
    $has_real_comments = $comment_counts->approved + $comment_counts->moderated > 1;

    if ( ( $comments_enabled || $has_real_comments ) && ! isset( $search_targets['comments'] ) )
    {
        $search_targets['comments'] = [
            'post_type'  => false,
            'label'      => __( 'Comments', 'tbds' ),
            'action_url' => $admin_url.'edit-comments.php',
            'cap'        => 'edit_posts',
        ];
    }

    // add Plugins
    if ( ! isset( $search_targets['plugins'] ) )
    {
        $search_targets['plugins'] = [
            'post_type'  => false,
            'label'      => __( 'Plugins', 'tbds' ),
            'action_url' => $admin_url.'plugins.php',
            'cap'        => 'activate_plugins',
        ];
    }

    // add Users
    $search_targets['user'] = [
        'post_type'  => false,
        'label'      => __( 'Users', 'tbds' ),
        'action_url' => $admin_url.'users.php',
        'cap'        => 'list_users',
    ];

    // add Network Sites
    if ( is_multisite() && ! isset( $search_targets['network_sites'] ) )
    {
        $search_targets['network_sites'] = [
            'post_type'  => false,
            'label'      => __( 'Network Sites', 'tbds' ),
            'action_url' => network_admin_url().'sites.php',
            'cap'        => 'manage_sites',
        ];
    }

    // add Network Users
    if ( is_multisite() && ! isset( $search_targets['network_user'] ) )
    {
        $search_targets['network_user'] = [
            'post_type'  => 'network_user',
            'label'      => __( 'Network Users', 'tbds' ),
            'action_url' => network_admin_url().'users.php',
            'cap'        => 'manage_network_users',
        ];
    }

    return $search_targets;
}

add_action( 'init', function () {
    load_plugin_textdomain( 'tbds' );
});

add_action( 'wp_dashboard_setup', function () {
    wp_add_dashboard_widget( 'dashboard-search-boxes', esc_html__( 'Search', 'tbds' ), function () {
?>
<style>
#dashboard-search-boxes.postbox .inside form + form { margin-top: 5px; }
#dashboard-search-boxes.postbox .inside input { width: 100%; }
#dashboard-search-boxes.postbox .inside input[type="submit"] { display: none; }
</style>
<?php

        foreach ( tbds_search_targets() as $target )
        {
            if ( ! current_user_can( $target['cap'] ) )
                continue;
?>
<form action="<?=esc_url( $target['action_url'] );?>" method="get">
    <div>
<?php if ( $target['post_type'] ): ?>
        <input type="hidden" name="post_type" value="<?=esc_attr( $target['post_type'] );?>">
<?php endif; ?>
<?php if ( isset( $target['extra_params'] ) ): ?>
<?php foreach ( $target['extra_params'] as $param => $value ): ?>
        <input type="hidden" name="<?=esc_attr( $param );?>" value="<?=esc_attr( $value );?>">
<?php endforeach; ?>
<?php endif; ?>
        <input type="search" name="s" value="" placeholder="<?=esc_attr( $target['label'] );?>">
        <input type="submit" value="search">
    </div>
</form>
<?php
        }
    });
});

