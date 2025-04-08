<?php
/**
 * GitHub Updater
 *
 * @package VK_Plugin_List
 */

if ( ! class_exists( 'VK_Plugin_List_Updater' ) ) {
    class VK_Plugin_List_Updater {
        private $plugin_slug;
        private $plugin_data;
        private $username;
        private $repo;
        private $plugin_file;
        private $github_api_result;
        private $access_token;

        public function __construct( $plugin_file ) {
            add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'set_transient' ) );
            add_filter( 'plugins_api', array( $this, 'set_plugin_info' ), 10, 3 );
            add_filter( 'upgrader_post_install', array( $this, 'post_install' ), 10, 3 );

            $this->plugin_file = $plugin_file;
            $this->username    = 'vektor-inc';
            $this->repo       = 'vk-plugin-list';
        }

        /**
         * Get information regarding our plugin from WordPress
         */
        private function init_plugin_data() {
            $this->plugin_slug = plugin_basename( $this->plugin_file );
            $this->plugin_data = get_plugin_data( $this->plugin_file );
        }

        /**
         * Get information regarding our plugin from GitHub
         */
        private function get_repository_info() {
            if ( ! empty( $this->github_api_result ) ) {
                return;
            }

            $url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases";

            $args = array(
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                ),
            );

            $response = wp_remote_get( $url, $args );

            if ( is_wp_error( $response ) ) {
                return;
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            if ( 200 !== $response_code ) {
                return;
            }

            $response_body = wp_remote_retrieve_body( $response );
            $releases      = json_decode( $response_body );

            if ( ! is_array( $releases ) || empty( $releases ) ) {
                return;
            }

            $this->github_api_result = $releases[0];
        }

        /**
         * Push in plugin version information to get the update notification
         */
        public function set_transient( $transient ) {
            if ( empty( $transient->checked ) ) {
                return $transient;
            }

            $this->init_plugin_data();
            $this->get_repository_info();

            if ( empty( $this->github_api_result ) ) {
                return $transient;
            }

            $do_update = version_compare( $this->github_api_result->tag_name, $this->plugin_data['Version'] );

            if ( $do_update ) {
                $package = $this->github_api_result->assets[0]->browser_download_url;

                $obj              = new stdClass();
                $obj->slug        = $this->plugin_slug;
                $obj->new_version = $this->github_api_result->tag_name;
                $obj->url         = $this->plugin_data['PluginURI'];
                $obj->package     = $package;

                $transient->response[ $this->plugin_slug ] = $obj;
            }

            return $transient;
        }

        /**
         * Push in plugin version information to display in the details lightbox
         */
        public function set_plugin_info( $false, $action, $response ) {
            $this->init_plugin_data();
            $this->get_repository_info();

            if ( empty( $response->slug ) || $response->slug !== $this->plugin_slug ) {
                return $false;
            }

            $response->last_updated = $this->github_api_result->published_at;
            $response->slug        = $this->plugin_slug;
            $response->plugin_name = $this->plugin_data['Name'];
            $response->version     = $this->github_api_result->tag_name;
            $response->author      = $this->plugin_data['Author'];
            $response->homepage    = $this->plugin_data['PluginURI'];

            $response->sections = array(
                'description' => $this->plugin_data['Description'],
            );

            $response->download_link = $this->github_api_result->assets[0]->browser_download_url;

            return $response;
        }

        /**
         * Perform additional actions to successfully install our plugin
         */
        public function post_install( $true, $hook_extra, $result ) {
            global $wp_filesystem;

            $plugin_folder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname( $this->plugin_slug );
            $wp_filesystem->move( $result['destination'], $plugin_folder );
            $result['destination'] = $plugin_folder;

            if ( is_plugin_active( $this->plugin_slug ) ) {
                activate_plugin( $this->plugin_slug );
            }

            return $result;
        }
    }
} 