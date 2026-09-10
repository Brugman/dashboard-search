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

if ( !defined( 'ABSPATH' ) )
    exit;

function tbds_search_targets()
{
    $search_targets = [];

    $admin_url = get_admin_url();

    // group taxonomies by post type so they can be shown right after it
    $taxonomies_by_post_type = [];

    $taxonomies = get_taxonomies([
        'show_ui' => true,
    ], 'objects' );

    foreach ( $taxonomies as $taxonomy )
    {
        // respect taxonomies that were deliberately hidden from the admin menu
        if ( $taxonomy->show_in_menu === false )
            continue;

        foreach ( $taxonomy->object_type as $attached_post_type )
            $taxonomies_by_post_type[ $attached_post_type ][] = $taxonomy;
    }

    // add Post Types
    $post_types = get_post_types([
        'show_ui' => true,
    ], 'objects' );

    // sort alphabetically by label
    uasort( $post_types, function ( $a, $b ) {
        return strcasecmp( $a->labels->name, $b->labels->name );
    });

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

        // add a search bar for each taxonomy attached to this post type
        if ( isset( $taxonomies_by_post_type[ $post_type->name ] ) )
        {
            foreach ( $taxonomies_by_post_type[ $post_type->name ] as $taxonomy )
            {
                $target_key = 'taxonomy_'.$taxonomy->name.'_'.$post_type->name;

                if ( isset( $search_targets[ $target_key ] ) )
                    continue;

                $search_targets[ $target_key ] = [
                    'post_type'    => false,
                    // translators: 1: post type singular name, 2: taxonomy name.
                    'label'        => sprintf( __( '%1$s %2$s', 'tbds' ), $post_type->labels->singular_name, $taxonomy->label ),
                    'action_url'   => $admin_url.'edit-tags.php',
                    'cap'          => $taxonomy->cap->manage_terms,
                    'extra_params' => [
                        'taxonomy'  => $taxonomy->name,
                        'post_type' => $post_type->name,
                    ],
                ];
            }
        }
    }

    // move attachment to the end of the content cluster
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

    if ( ( $comments_enabled || $has_real_comments ) && !isset( $search_targets['comments'] ) )
    {
        $search_targets['comments'] = [
            'post_type'  => false,
            'label'      => __( 'Comments', 'tbds' ),
            'action_url' => $admin_url.'edit-comments.php',
            'cap'        => 'edit_posts',
        ];
    }

    // add Gravity Forms
    if ( class_exists( 'GFCommon' ) && !isset( $search_targets['gform'] ) )
    {
        $search_targets['gform'] = [
            'post_type'    => false,
            'label'        => __( 'Forms', 'tbds' ),
            'action_url'   => $admin_url.'admin.php',
            'cap'          => 'gravityforms_edit_forms',
            'extra_params' => [
                'page' => 'gf_edit_forms',
            ]
        ];
    }

    // add WooCommerce
    if ( class_exists( 'WooCommerce' ) )
    {
        // use our own labels instead of the generic post type boxes,
        // which would otherwise be scattered across the alphabetical cluster
        unset( $search_targets['product'], $search_targets['shop_order'], $search_targets['shop_coupon'] );

        $search_targets['wc_product'] = [
            'post_type'  => 'product',
            'label'      => __( 'Products', 'tbds' ),
            'action_url' => $admin_url.'edit.php',
            'cap'        => 'edit_products',
        ];

        // orders live on a custom admin screen when HPOS is enabled
        $hpos_enabled = class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' )
            && call_user_func( [ '\\Automattic\\WooCommerce\\Utilities\\OrderUtil', 'custom_orders_table_usage_is_enabled' ] );

        if ( $hpos_enabled )
        {
            $search_targets['wc_order'] = [
                'post_type'    => false,
                'label'        => __( 'Orders', 'tbds' ),
                'action_url'   => $admin_url.'admin.php',
                'cap'          => 'edit_shop_orders',
                'extra_params' => [
                    'page' => 'wc-orders',
                ],
            ];
        }
        else
        {
            $search_targets['wc_order'] = [
                'post_type'  => 'shop_order',
                'label'      => __( 'Orders', 'tbds' ),
                'action_url' => $admin_url.'edit.php',
                'cap'        => 'edit_shop_orders',
            ];
        }

        // only show coupons if the store actually uses them
        $coupon_counts = wp_count_posts( 'shop_coupon' );
        $has_coupons = $coupon_counts && array_sum( (array) $coupon_counts ) > 0;

        if ( $has_coupons )
        {
            $search_targets['wc_coupon'] = [
                'post_type'  => 'shop_coupon',
                'label'      => __( 'Coupons', 'tbds' ),
                'action_url' => $admin_url.'edit.php',
                'cap'        => 'edit_shop_coupons',
            ];
        }

        // place product taxonomies (categories, tags, ...) directly after products
        $product_taxonomies = [];

        foreach ( $search_targets as $target_key => $target )
        {
            if ( strpos( $target_key, 'taxonomy_' ) === 0 && $target['extra_params']['post_type'] === 'product' )
            {
                $product_taxonomies[ $target_key ] = $target;
                unset( $search_targets[ $target_key ] );
            }
        }

        if ( $product_taxonomies )
        {
            $wc_product_pos = array_search( 'wc_product', array_keys( $search_targets ), true ) + 1;

            $search_targets = array_merge(
                array_slice( $search_targets, 0, $wc_product_pos, true ),
                $product_taxonomies,
                array_slice( $search_targets, $wc_product_pos, null, true )
            );
        }
    }

    // add Plugins
    if ( !isset( $search_targets['plugins'] ) )
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
    if ( is_multisite() && !isset( $search_targets['network_sites'] ) )
    {
        $search_targets['network_sites'] = [
            'post_type'  => false,
            'label'      => __( 'Network Sites', 'tbds' ),
            'action_url' => network_admin_url().'sites.php',
            'cap'        => 'manage_sites',
        ];
    }

    // add Network Users
    if ( is_multisite() && !isset( $search_targets['network_user'] ) )
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

        foreach ( tbds_search_targets() as $target_key => $target )
        {
            if ( !current_user_can( $target['cap'] ) )
                continue;

            $hidden_params = [];

            if ( $target['post_type'] )
                $hidden_params['post_type'] = $target['post_type'];

            foreach ( $target['extra_params'] ?? [] as $param => $value )
                $hidden_params[ $param ] = $value;
?>
<form action="<?=esc_url( $target['action_url'] );?>" method="get">
    <div>
        <label class="screen-reader-text" for="tbds-<?=esc_attr( $target_key );?>">
            <?=esc_html( sprintf( __( 'Search %s', 'tbds' ), $target['label'] ) );?>
        </label>
<?php foreach ( $hidden_params as $param => $value ): ?>
        <input type="hidden" name="<?=esc_attr( $param );?>" value="<?=esc_attr( $value );?>">
<?php endforeach; ?>
        <input type="search" id="tbds-<?=esc_attr( $target_key );?>" name="s" value="" placeholder="<?=esc_attr( $target['label'] );?>">
        <input type="submit" value="search">
    </div>
</form>
<?php
        }
    });
});

