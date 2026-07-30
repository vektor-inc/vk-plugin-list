<?php
/**
 * GitHub Updater
 *
 * @package VK_Plugin_List
 */

if ( ! class_exists( 'VK_Plugin_List_Updater' ) ) {
	/**
	 * VK Plugin List Updater Class
	 */
	class VK_Plugin_List_Updater {
		/**
		 * プラグインのスラッグ
		 *
		 * @var string
		 */
		private $plugin_slug;

		/**
		 * プラグインのデータ
		 *
		 * @var array
		 */
		private $plugin_data;

		/**
		 * GitHubのユーザー名
		 *
		 * @var string
		 */
		private $username;

		/**
		 * GitHubのリポジトリ名
		 *
		 * @var string
		 */
		private $repo;

		/**
		 * プラグインファイルのパス
		 *
		 * @var string
		 */
		private $plugin_file;

		/**
		 * GitHub APIの結果
		 *
		 * @var object
		 */
		private $github_api_result;

		/**
		 * アクセストークン
		 *
		 * @var string
		 */
		private $access_token;

		/**
		 * コンストラクタ
		 *
		 * @param string $plugin_file プラグインファイルのパス
		 */
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
		 * リリースアセットから有効なzipファイルのダウンロードURLを取得する
		 *
		 * GitHubリリースに複数のアセットが添付されている場合でも、
		 * ファイル名が .zip（大文字小文字は問わない）で終わるアセットのみを対象とし、
		 * そのダウンロードURLを返す。zipアセットが無い、または assets が空・未定義の場合は空文字を返す。
		 *
		 * @return string 有効なzipアセットのダウンロードURL。該当が無い場合は空文字。
		 */
		private function get_package_url() {
			// APIの取得結果やアセットが空の場合は安全に空文字を返す。
			if ( empty( $this->github_api_result ) || empty( $this->github_api_result->assets ) ) {
				return '';
			}

			foreach ( $this->github_api_result->assets as $asset ) {
				// ファイル名・ダウンロードURLのいずれかが欠けているアセットはスキップする。
				if ( empty( $asset->name ) || empty( $asset->browser_download_url ) ) {
					continue;
				}

				// ファイル名が .zip（大文字小文字問わず）で終わるアセットのダウンロードURLを返す。
				if ( '.zip' === strtolower( substr( $asset->name, -4 ) ) ) {
					return $asset->browser_download_url;
				}
			}

			return '';
		}

		/**
		 * Push in plugin version information to get the update notification
		 *
		 * @param object $transient プラグイン更新情報
		 * @return object 更新されたプラグイン更新情報
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

			// 第3引数に '>' を指定し、GitHubの最新リリースがインストール済みバージョンより新しい場合のみ更新対象とする（ダウングレード防止）。
			$do_update = version_compare( $this->github_api_result->tag_name, $this->plugin_data['Version'], '>' );

			if ( $do_update ) {
				// リリースアセットから有効なzipのダウンロードURLを取得する。有効なzipが無ければ更新情報を出さない。
				$package = $this->get_package_url();
				if ( '' === $package ) {
					return $transient;
				}

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
		 *
		 * @param object|bool $false プラグイン情報
		 * @param string      $action アクション
		 * @param object      $response レスポンス
		 * @return object|bool 更新されたプラグイン情報
		 */
		public function set_plugin_info( $false, $action, $response ) {
			// plugins_api フィルターは全プラグインの「詳細を表示」で発火するため、まずはローカル情報のみでスラッグを用意する。
			// init_plugin_data() は plugin_basename() と get_plugin_data() だけを使い GitHub 通信を伴わないため、スラッグ照合の前に呼んでも問題ない。
			$this->init_plugin_data();

			// 本プラグイン宛でないリクエストは GitHub API へ通信する前にここで打ち切る。
			// スラッグ照合を通信より前に置くことで、無関係なプラグインの詳細を開くたびに不要な外部通信が発生するのを防ぐ。
			if ( empty( $response->slug ) || $response->slug !== $this->plugin_slug ) {
				return $false;
			}

			// 本プラグイン宛と確定した後にのみ GitHub API へ問い合わせる。
			$this->get_repository_info();

			// GitHub APIの取得に失敗している場合、$this->github_api_result のプロパティ参照でPHP8の警告が発生するため早期returnする。
			if ( empty( $this->github_api_result ) ) {
				return $false;
			}

			// リリースアセットから有効なzipのダウンロードURLを取得する。有効なzipが無ければ更新情報を出さない。
			$package = $this->get_package_url();
			if ( '' === $package ) {
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

			$response->download_link = $package;

			return $response;
		}

		/**
		 * Perform additional actions to successfully install our plugin
		 *
		 * @param bool  $true インストール結果
		 * @param array $hook_extra フックの追加情報
		 * @param array $result インストール結果
		 * @return array 更新されたインストール結果
		 */
		public function post_install( $true, $hook_extra, $result ) {
			global $wp_filesystem;

			// Avoid deprecated dirname(null) error and only process if plugin_slug is set
			if ( ! empty( $this->plugin_slug ) ) {
				$plugin_folder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname( $this->plugin_slug );
				$wp_filesystem->move( $result['destination'], $plugin_folder );
				$result['destination'] = $plugin_folder;

				if ( is_plugin_active( $this->plugin_slug ) ) {
					activate_plugin( $this->plugin_slug );
				}
			}

			return $result;
		}
	}
}