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

function tbds_post_types()
{
    $results = [];

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

        $results[ $post_type->name ] = [
            'name'       => $post_type->name,
            'label'      => $post_type->label,
            'action_url' => $admin_url.'edit.php?post_type='.$post_type->name,
            'cap'        => $post_type->cap->edit_posts,
        ];
    }

    // add Gravity Forms
    if ( class_exists( 'GFCommon' ) && ! isset( $results['gform'] ) )
    {
        $results['gform'] = [
            'name'       => false,
            'label'      => __( 'Forms', 'tbds' ),
            'action_url' => $admin_url.'admin.php',
            'cap'        => 'gravityforms_edit_forms',
            'other_gets' => [
                'page' => 'gf_edit_forms',
            ]
        ];
    }

    // move attachment to the end
    if ( isset( $results['attachment'] ) )
    {
        $attachment = $results['attachment'];
        unset( $results['attachment'] );
        $results['attachment'] = $attachment;
    }

    // add Comments
    $comment_counts = wp_count_comments();
    $comments_enabled = get_option( 'default_comment_status' ) === 'open';
    $has_real_comments = $comment_counts->approved + $comment_counts->moderated > 1;

    if ( ( $comments_enabled || $has_real_comments ) && ! isset( $results['comments'] ) )
    {
        $results['comments'] = [
            'name'       => false,
            'label'      => __( 'Comments', 'tbds' ),
            'action_url' => $admin_url.'edit-comments.php',
            'cap'        => 'edit_posts',
        ];
    }

    // add Plugins
    if ( ! isset( $results['plugins'] ) )
    {
        $results['plugins'] = [
            'name'       => false,
            'label'      => __( 'Plugins', 'tbds' ),
            'action_url' => $admin_url.'plugins.php',
            'cap'        => 'activate_plugins',
        ];
    }

    // add Users
    $results['user'] = [
        'name'       => 'user',
        'label'      => __( 'Users', 'tbds' ),
        'action_url' => $admin_url.'users.php',
        'cap'        => 'list_users',
    ];

    // add Network Sites
    if ( is_multisite() && ! isset( $results['network_sites'] ) )
    {
        $results['network_sites'] = [
            'name'       => false,
            'label'      => __( 'Network Sites', 'tbds' ),
            'action_url' => network_admin_url().'sites.php',
            'cap'        => 'manage_sites',
        ];
    }

    // add Network Users
    if ( is_multisite() && ! isset( $results['network_user'] ) )
    {
        $results['network_user'] = [
            'name'       => 'network_user',
            'label'      => __( 'Network Users', 'tbds' ),
            'action_url' => network_admin_url().'users.php',
            'cap'        => 'manage_network_users',
        ];
    }

    return $results;
}

add_action( 'init', function () {
    load_plugin_textdomain( 'tbds' );
});

add_action( 'wp_dashboard_setup', function () {
    wp_add_dashboard_widget( 'dashboard-search-boxes', esc_html__( 'Search', 'tbds' ), function ( $post, $callback_args ) {
?>
<style>
#dashboard-search-boxes.postbox .inside form + form { margin-top: 5px; }
#dashboard-search-boxes.postbox .inside input { width: 100%; }
#dashboard-search-boxes.postbox .inside input[type="submit"] { display: none; }
</style>
<?php

        foreach ( tbds_post_types() as $post_type )
        {
            if ( ! current_user_can( $post_type['cap'] ) )
                continue;
?>
<form action="<?=esc_url( $post_type['action_url'] );?>" method="get">
    <div>
<?php if ( $post_type['name'] ): ?>
        <input type="hidden" name="post_type" value="<?=esc_attr( $post_type['name'] );?>">
<?php endif; ?>
<?php if ( isset( $post_type['other_gets'] ) ): ?>
<?php foreach ( $post_type['other_gets'] as $k => $v ): ?>
        <input type="hidden" name="<?=esc_attr( $k );?>" value="<?=esc_attr( $v );?>">
<?php endforeach; ?>
<?php endif; ?>
        <input type="search" name="s" value="" placeholder="<?=esc_attr( $post_type['label'] );?>">
        <input type="submit" value="search">
    </div>
</form>
<?php
        }
    });
});

