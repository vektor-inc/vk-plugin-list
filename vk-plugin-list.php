<?php
/**
 * Plugin Name: VK Plugin List
 * Description: インストールされたプラグインのリストを表示する WordPress プラグイン。
 * Plugin URI: https://github.com/vektor-inc/vk-plugin-list
 * Version: 1.0
 * Author: Vektor,Inc.
 * Author URI: https://www.vektor-inc.co.jp/
 * Text Domain: vk-plugin-list
 * License: GPLv2
 */

if ( ! class_exists( 'VK_Plugin_List' ) ) {
    class VK_Plugin_List {

        // 除外するプラグイン
        private $excluded_plugins = array(
            'VK Plugin List',
            'VK FullSite Exporter',
        );

        public function __construct() {
            add_shortcode( 'vk-plugin-list', array( $this, 'render_plugin_list' ) );
        }

        /**
         * プラグインリストを取得して出力する
         */
        public function render_plugin_list() {
            // すべてのプラグインを取得
            $all_plugins = get_plugins();
            $output = '<table class="vkpl_table vk-table--mobile-block">';
            $output .= '<thead><tr><th>Plugin Name</th><th>Description</th></tr></thead>';
            $output .= '<tbody>';

            foreach ( $all_plugins as $plugin_file => $plugin_data ) {
                // 除外プラグインをスキップ
                if ( in_array( $plugin_data['Name'], $this->excluded_plugins, true ) ) {
                    continue;
                }

                $plugin_name = esc_html( $plugin_data['Name'] );
                $plugin_description = esc_html( $plugin_data['Description'] );
                $plugin_author = esc_html( $plugin_data['Author'] );

                // Plugin URI
                if ( ! empty( $plugin_data['PluginURI'] ) ) {
                    $plugin_name = '<a href="' . esc_url( $plugin_data['PluginURI'] ) . '" target="_blank" rel="noopener noreferrer">' . $plugin_name . '</a>';
                }

                // Author URI
                if ( ! empty( $plugin_data['AuthorURI'] ) ) {
                    $plugin_author = '<a href="' . esc_url( $plugin_data['AuthorURI'] ) . '" target="_blank" rel="noopener noreferrer">' . $plugin_author . '</a>';
                }

                $output .= '<tr>';
                $output .= '<td>' . $plugin_name . '</td>';
                $output .= '<td><div class="vkpl_description">' . $plugin_description . '</div><div class="vkpl_author">' . $plugin_author . '</div></td>';
                $output .= '</tr>';
            }

            $output .= '</tbody>';
            $output .= '</table>';

            return $output;
        }
    }

    // クラスを初期化
    new VK_Plugin_List();
}