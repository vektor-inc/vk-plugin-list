# VK Plugin List

## What's this

インストールされたプラグインのリストを表示する WordPress プラグインです。
ショートコード `[vk-plugin-list]` でプラグインリストを出力します。

表示する項目は

| Plugin Name | Description |
| ---- | ---- |
| Plugin Name | <div class="vkpl_description">Description</div><div class="vkpl_author">Author</div> |

という感じでテーブル形式で出力します。
表には class="vkpl_table vk-table--mobile-block" が付与されます。

インストール済みのプラグインのファイルに
Plugin URI が記載されている場合はプラグイン名に別ウィンドウでリンクを指定されます。
Author URI が記載されている場合は Author に別ウィンドウでリンクを指定されます。

## 使い方

* npm run zip で dist ディレクトリ内に zip ファイルができますので、インストールして有効化します。
  （Releases から zip ファイルをダウンロードしても使えます）
* プラグインリストを表示したいページでショートコード `[vk-plugin-list]` を記載すると公開画面で表示されます。

## 実装について

* ライセンスは GPLv2です
* WordPressのコーディング規約に沿ってください。
* VK_Plugin_Linst クラスを作ってその中で完結して書いてください。
* プラグインリストから除外したいプラグインを配列で登録しておきたいです。
とりあえすこの VK Plugin List と VK FullSite Exporter は除外したいです。
