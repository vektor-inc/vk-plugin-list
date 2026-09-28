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
		 * WordPress のプラグイン一覧は、対象プラグインが $transient->response（更新あり）
		 * または $transient->no_update（更新なし）のどちらかに登録されていないと
		 * update-supported を false と判定し、「自動更新」欄自体を表示しない
		 * （wp-admin/includes/class-wp-plugins-list-table.php 参照）。
		 * そのため、更新が無い場合も no_update 側へ明示的に登録する。
		 *
		 * @param object $transient プラグイン更新情報
		 * @return object 更新されたプラグイン更新情報
		 */
		public function set_transient( $transient ) {
			// $transient がオブジェクトでない場合はプロパティを触れないためそのまま返す。
			if ( ! is_object( $transient ) ) {
				return $transient;
			}

			if ( empty( $transient->checked ) ) {
				return $transient;
			}

			// response / no_update が未定義・配列以外でも安全に添字代入できるようガードする。
			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = array();
			}
			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
				$transient->no_update = array();
			}

			$this->init_plugin_data();
			$this->get_repository_info();

			$current_version = isset( $this->plugin_data['Version'] ) ? (string) $this->plugin_data['Version'] : '';

			// GitHub API の取得に失敗した場合も、自動更新欄が消えないよう no_update に登録する。
			// 更新の有無が確認できないだけで、プラグイン自体は正常に動作しているため。
			if ( empty( $this->github_api_result ) ) {
				$this->register_no_update( $transient, $current_version );
				return $transient;
			}

			// 第3引数に '>' を指定し、GitHubの最新リリースがインストール済みバージョンより新しい場合のみ更新対象とする（ダウングレード防止）。
			$do_update = version_compare( $this->github_api_result->tag_name, $current_version, '>' );

			if ( $do_update ) {
				// リリースアセットから有効なzipのダウンロードURLを取得する。
				$package = $this->get_package_url();

				if ( '' !== $package ) {
					$obj              = new stdClass();
					$obj->slug        = $this->plugin_slug;
					$obj->plugin      = $this->plugin_slug; // WP_Automatic_Updater が自動更新の可否判定（auto_update_plugins との照合）に使うため必須。
					$obj->new_version = $this->github_api_result->tag_name;
					$obj->url         = isset( $this->plugin_data['PluginURI'] ) ? $this->plugin_data['PluginURI'] : '';
					$obj->package     = $package;

					// response と no_update の両方に同時登録されないことをコード上で保証するための防御。
					// 現状の分岐は排他的で両方に載る手順は確認できていないが、
					// 今後の分岐追加で崩れても片方には確実に載る状態を保つ。
					unset( $transient->no_update[ $this->plugin_slug ] );
					$transient->response[ $this->plugin_slug ] = $obj;

					return $transient;
				}
			}

			// 更新が無い場合に加え、「更新はあるが配布zipが見つからない」場合も
			// 自動更新欄が消えないよう no_update に登録する。
			$this->register_no_update( $transient, $current_version );

			return $transient;
		}

		/**
		 * 「更新なし」として $transient->no_update へ登録する
		 *
		 * response と no_update の両方に同時登録されないことをコード上で保証するため、
		 * 登録前に response 側を必ず削除する。現状の呼び出し箇所は排他的で
		 * 両方に載る手順は確認できていないが、今後の分岐追加で崩れても
		 * 片方には確実に載る状態を保つための防御。
		 *
		 * @param object $transient       プラグイン更新情報（オブジェクトのため呼び出し元にも反映される）
		 * @param string $current_version 現在のプラグインバージョン
		 * @return void
		 */
		private function register_no_update( $transient, $current_version ) {
			unset( $transient->response[ $this->plugin_slug ] );
			$transient->no_update[ $this->plugin_slug ] = $this->build_no_update_item( $current_version );
		}

		/**
		 * no_update に登録するプラグイン情報オブジェクトを組み立てる
		 *
		 * plugin-update-checker の getNoUpdateItemFields() が生成する項目に倣い、
		 * WordPress が期待するフィールドを埋める。
		 * package を空文字にすることで「更新パッケージが無い＝最新」を表す。
		 *
		 * @param string $current_version 現在のプラグインバージョン
		 * @return object no_update に登録するオブジェクト
		 */
		private function build_no_update_item( $current_version ) {
			$obj                = new stdClass();
			$obj->id            = $this->plugin_slug;
			$obj->slug          = $this->plugin_slug;
			$obj->plugin        = $this->plugin_slug;
			$obj->new_version   = $current_version;
			$obj->url           = isset( $this->plugin_data['PluginURI'] ) ? $this->plugin_data['PluginURI'] : '';
			$obj->package       = '';
			$obj->icons         = array();
			$obj->banners       = array();
			$obj->banners_rtl   = array();
			$obj->tested        = '';
			$obj->requires_php  = '';
			$obj->compatibility = new stdClass();

			return $obj;
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
		 * upgrader_post_install は本プラグイン専用のフックではなく、サイト上の
		 * すべてのプラグイン・テーマのインストール／更新で発火する WordPress コア共通フックのため、
		 * $hook_extra（今回インストール・更新された対象を示す情報。プラグインなら
		 * $hook_extra['plugin']、テーマなら $hook_extra['theme'] にスラッグが入る）を見て、
		 * 今回の対象が本プラグイン自身かどうかを判定してから処理する。
		 * 対象が本プラグイン以外（他のプラグイン・テーマの更新）の場合は、
		 * ファイル移動・再有効化を一切行わず $result をそのまま返し、他の更新処理に干渉しない。
		 *
		 * @param bool  $true インストール結果
		 * @param array $hook_extra フックの追加情報
		 * @param array $result インストール結果
		 * @return array 更新されたインストール結果
		 */
		public function post_install( $true, $hook_extra, $result ) {
			global $wp_filesystem;

			// plugin_slug は init_plugin_data() を呼ぶまで未セットのため、
			// $hook_extra との比較前に必ず呼び出してセットしておく
			// （set_transient() 等が同一リクエスト内で先に呼ばれているとは限らないため）。
			$this->init_plugin_data();

			// $hook_extra はプラグインなら 'plugin' キー、テーマなら 'theme' キーにスラッグが入る。
			// いずれのキーも無い場合は空文字とし、本プラグインのスラッグとは一致しない値にする。
			$target_slug = isset( $hook_extra['plugin'] ) ? $hook_extra['plugin'] : ( isset( $hook_extra['theme'] ) ? $hook_extra['theme'] : '' );

			// 今回の対象が本プラグイン自身でなければ、ここで処理を打ち切って $result をそのまま返す。
			// plugin_slug が未取得（空）の場合も、誤って他の対象を移動しないよう同様に打ち切る。
			if ( empty( $this->plugin_slug ) || $target_slug !== $this->plugin_slug ) {
				return $result;
			}

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