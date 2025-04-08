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
        private $excluded_plugins;

        public function __construct() {
            // 除外プラグインの初期値を設定
            $this->excluded_plugins = array(
                'VK Plugin List',
                'VK FullSite Exporter',
            );
            // フィルターフックで除外プラグインを改変可能
            $this->excluded_plugins = apply_filters( 'vk_plugin_list_excluded', $this->excluded_plugins );

            add_shortcode( 'vk-plugin-list', array( $this, 'render_plugin_list' ) );
        }

        /**
         * プラグインリストを取得してフィルタリングする
         *
         * @return array フィルタリングされたプラグインリスト
         */
        private function get_filtered_plugins() {
            // すべてのプラグインを取得
            $all_plugins = get_plugins();
            $filtered_plugins = array();

            foreach ( $all_plugins as $plugin_file => $plugin_data ) {
                // 除外プラグインをスキップ
                if ( in_array( $plugin_data['Name'], $this->excluded_plugins, true ) ) {
                    continue;
                }

                $filtered_plugins[$plugin_file] = $plugin_data;
            }

            // フィルターフックでプラグインリストを改変可能
            return apply_filters( 'vk_plugin_list_array', $filtered_plugins );
        }

        /**
         * プラグインリストをHTMLとして出力する
         *
         * @return string HTML出力
         */
        private function render_plugin_list_html( $plugins ) {
            $output = '<table class="vkpl_table vk-table--mobile-block">';
            $output .= '<thead><tr><th>Plugin Name</th><th>Description</th></tr></thead>';
            $output .= '<tbody>';

            foreach ( $plugins as $plugin_file => $plugin_data ) {
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

        /**
         * ショートコードのコールバック関数
         */
        public function render_plugin_list() {
            $plugins = $this->get_filtered_plugins();
            return $this->render_plugin_list_html( $plugins );
        }
    }

    // クラスを初期化
    new VK_Plugin_List();
}