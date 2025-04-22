=== VK Plugin List ===
Contributors: vektor-inc
Tags: plugin list, shortcode, plugins
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 0.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

インストールされたプラグインのリストを表示する WordPress プラグイン。ショートコード [vk-plugin-list] で表示できます。

== Description ==

このプラグインは、WordPressサイトにインストールされているプラグインのリストを表示するためのシンプルなツールです。

= 主な機能 =

* ショートコード [vk-plugin-list] でプラグインリストを表示
* プラグイン名、説明、バージョン、作者情報を表示
* プラグインの説明文を各プラグインのテキストドメインで翻訳
* 特定のプラグインをリストから除外可能
* フィルターフックで表示内容をカスタマイズ可能

= 除外されるプラグイン =

デフォルトで以下のプラグインはリストから除外されます：

* VK Plugin List（このプラグイン自体）
* VK FullSite Exporter

除外するプラグインは `vk_plugin_list_excluded` フィルターで変更可能です。

== Installation ==

1. プラグインをインストールし、有効化します
2. 投稿やページに `[vk-plugin-list]` ショートコードを追加します

== Frequently Asked Questions ==

= ショートコードの使い方を教えてください =

投稿やページに `[vk-plugin-list]` と記述するだけで、プラグインリストが表示されます。

= 特定のプラグインをリストから除外するにはどうすればよいですか？ =

以下のようなコードをテーマの `functions.php` に追加することで、除外するプラグインを変更できます：

```php
add_filter( 'vk_plugin_list_excluded', function( $excluded_plugins ) {
    $excluded_plugins[] = 'プラグイン名';
    return $excluded_plugins;
} );
```

= プラグインリストの表示をカスタマイズするにはどうすればよいですか？ =

`vk_plugin_list_array` フィルターを使用して、プラグインリストの内容を変更できます：

```php
add_filter( 'vk_plugin_list_array', function( $plugins ) {
    // プラグインリストをカスタマイズ
    return $plugins;
} );
```

== Screenshots ==

1. プラグインリストの表示例

== Changelog ==

= 0.1.3 =
* 有効化されているプラグインのみを表示

= 0.1.2 =
* プラグインの説明文に含まれるリンクが正しく表示されるように修正
* WordPressのコーディング規約に沿ったコードの修正
  * インデントの修正
  * PHPDocコメントの追加
  * セキュリティ強化（esc_html__の使用）

= 0.1.1 =
* バージョン番号の更新（テスト用）

= 0.1.0 =
* 初期リリース
* プラグインリストの表示機能
* ショートコード実装
* 除外プラグインの設定
* フィルターフックの追加
* GitHubリリースからの自動更新機能 