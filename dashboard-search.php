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

function tbds_post_types()
{
    $results = [];

    // get post types
    $post_types = get_post_types([
        'public' => true,
        'show_ui' => true,
    ], 'objects');

    // get admin url
    $admin_url = get_admin_url();

    // save only the data we need
    foreach ( $post_types as $post_type )
    {
        $results[ $post_type->name ] = [
            'name'       => $post_type->name,
            'label'      => $post_type->label,
            'action_url' => $admin_url.'edit.php?post_type='.$post_type->name,
        ];
    }

    // add Gravity Forms
    if ( is_plugin_active( 'gravityforms/gravityforms.php' ) || is_plugin_active_for_network( 'gravityforms/gravityforms.php' ) )
    {
        $results['gform'] = [
            'name'       => false,
            'label'      => __( 'Forms' ),
            'action_url' => $admin_url.'admin.php',
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

    // add Users
    $results['user'] = [
        'name'       => 'user',
        'label'      => __( 'Users' ),
        'action_url' => $admin_url.'users.php',
    ];

    // add Network Users
    if ( is_multisite() )
    {
        $results['network_user'] = [
            'name'       => 'network_user',
            'label'      => __( 'Network Users' ),
            'action_url' => network_admin_url().'users.php',
        ];
    }

    return $results;
}

add_action( 'wp_dashboard_setup', function () {
    // create widget
    wp_add_dashboard_widget( 'dashboard-search-boxes', 'Search', function ( $post, $callback_args ) {
        // styles
?>
<style>
#dashboard-search-boxes.postbox .inside form + form { margin-top: 5px; }
#dashboard-search-boxes.postbox .inside input { width: 100%; }
#dashboard-search-boxes.postbox .inside input[type="submit"] { display: none; }
</style>
<?php
        // get and loop post types
        foreach ( tbds_post_types() as $post_type )
        {
?>
<form action="<?=$post_type['action_url'];?>" method="get" role="form">
    <div class="form-group">
<?php if ( $post_type['name'] ): ?>
        <input type="hidden" name="post_type" value="<?=$post_type['name'];?>">
<?php endif; // $post_type['name'] ?>
<?php if ( isset( $post_type['other_gets'] ) ): ?>
<?php foreach ( $post_type['other_gets'] as $k => $v ): ?>
        <input type="hidden" name="<?=$k;?>" value="<?=$v;?>">
<?php endforeach; // $post_type['other_gets'] ?>
<?php endif; // $post_type['other_gets'] ?>
        <input type="search" name="s" value="" placeholder="<?=$post_type['label'];?>">
        <input type="submit" value="search">
    </div>
</form>
<?php
        }
    });
});

