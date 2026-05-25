# Taro Sitemap 開発ガイド

## ディレクトリ構造

```
taro-sitemap/
├── .github/                  # GitHub関連設定
│   ├── release-drafter.yml   # リリースドラフト設定
│   └── workflows/            # GitHub Actions
│       ├── release-drafter.yml  # リリースドラフト作成
│       ├── wordpress.yml        # WordPress.orgへのデプロイ
│       └── test.yml             # テスト実行
├── assets/                   # ソースアセット（ビルド前）
│   └── scss/                 # SCSSファイル
├── bin/                      # ビルドスクリプト
│   └── build.sh              # プラグインビルド用スクリプト
├── dist/                     # ビルド済みアセット（生成される）
│   └── css/                  # コンパイル済みCSS
├── languages/                # 翻訳ファイル
│   ├── tsmap.pot             # POTテンプレート
│   └── tsmap-ja.po           # 日本語翻訳
├── src/                      # PHPソースコード
│   └── Tarosky/Sitemap/
│       ├── Pattern/          # 共通パターン・トレイト
│       ├── Provider/         # サイトマッププロバイダー実装
│       ├── Seo/              # SEO機能
│       │   └── Features/     # 個別SEO機能
│       ├── Styles/           # サイトマップXSLTスタイル
│       ├── Utility/          # ユーティリティクラス
│       ├── Registry.php      # プロバイダー登録
│       └── Setting.php       # 設定画面
├── vendor/                   # Composer依存関係（生成される）
├── taro-sitemap.php          # プラグインメインファイル
├── uninstall.php             # アンインストール処理
├── composer.json             # PHP依存関係定義
├── package.json              # Node.js依存関係定義
├── phpcs.ruleset.xml         # PHPコーディング規約
└── wp-dependencies.json      # wp-env設定
```

### 主要ディレクトリの説明

- **src/Tarosky/Sitemap/Pattern/**: 各機能で共通利用される抽象クラスやトレイト
- **src/Tarosky/Sitemap/Provider/**: 投稿、タクソノミー、添付ファイル、ニュースサイトマップの各プロバイダー実装
- **src/Tarosky/Sitemap/Seo/**: SEO関連機能（メタタグ、noindex設定、OGP、構造化データなど）
- **src/Tarosky/Sitemap/Styles/**: サイトマップXML用のXSLTスタイルシート（ブラウザ表示用）
- **src/Tarosky/Sitemap/Utility/**: ヘルパー関数やユーティリティクラス

## コア・コンセプト

### 解決する課題

このプラグインは、**投稿が10万件以上あるサイトでサイトマップアクセス時のパフォーマンス問題**を解決するために開発されました。

### 従来の問題点

一般的なサイトマッププラグインでは、`WP_Query`で日時順にソートし、`offset`を使ってページングします。

```php
// 一般的な実装（遅い）
$query = new WP_Query([
    'orderby' => 'date',
    'order' => 'DESC',
    'posts_per_page' => 1000,
    'offset' => 50000,  // 5万件目から取得
]);
```

この場合、10万件の投稿があるサイトで5万件目を取得しようとすると、MySQLは**5万件をスキャン**してからスキップする必要があり、非常に遅くなります。

### このプラグインの解決策

**年月別でインデックスを出力し、各サイトマップではその月の範囲内だけでクエリを実行**します。

```php
// このプラグインの実装（速い）
// 1. インデックス生成時：年月別に集計（QueryArgsHelper::post_type_indices()）
SELECT EXTRACT(YEAR_MONTH from post_date) as date, COUNT(ID) AS total
FROM wp_posts
WHERE post_type IN ('post') AND post_status = 'publish'
GROUP BY EXTRACT(YEAR_MONTH from post_date)
// → sitemap_post_202401_1.xml, sitemap_post_202401_2.xml... を生成

// 2. 個別サイトマップ生成時：その月の範囲内だけを検索（PostSitemapProvider::get_urls()）
SELECT * FROM wp_posts
WHERE post_type IN ('post')
  AND post_status = 'publish'
  AND post_date BETWEEN '2024-01-01 00:00:00' AND '2024-01-31 23:59:59'
ORDER BY post_date DESC
LIMIT 0, 1000  // オフセットは月内でのページングのみ
```

これにより、スキャン対象が**1/100以下**に削減されます。

### トレードオフ

この設計には以下のトレードオフがあります：

**犠牲にしているもの:**
- **理想的なSEO構造**: 投稿日の年月で固定的に分類されるため、例えば2020年1月の投稿を2025年に大幅更新しても、2020年1月のサイトマップに残ります
- **動的な優先順位付け**: 重要度や更新頻度に基づく並び替えはできません

**獲得しているもの:**
- **劇的なパフォーマンス改善**: 大規模サイトでも高速にサイトマップを生成
- **スケーラビリティ**: 投稿数が増えても、各クエリの範囲は月単位で限定される

つまり、「理想的なSEO構造」よりも「大規模サイトでの実用性」を優先した設計です。

### 開発時の重要な注意事項

⚠️ **以下のコードを修正する際は、パフォーマンスへの影響を十分に検討してください:**

- **`QueryArgsHelper::post_type_indices()`**: 年月別インデックス生成のクエリ
- **`PostSitemapProvider::get_urls()`**: 年月範囲を限定したクエリ
- **`Registry::add_rewrite_rules()`**: 年月を含むURL構造

**よくある誤った修正例:**
```php
// ❌ 悪い例：全体を日時順でソートしてオフセット
$query = new WP_Query([
    'orderby' => 'modified',  // 更新日順にしたい
    'order' => 'DESC',
    'posts_per_page' => 1000,
    'offset' => $offset,  // 全体からのオフセット → 遅い！
]);
```

このような修正は、このプラグインの根本的な設計思想に反し、パフォーマンスを大幅に悪化させます。

**新機能を追加する場合:**
- 必ず「年月別」または「カテゴリ別」など、範囲を限定する軸を設ける
- `BETWEEN`句や`IN`句で範囲を絞る
- 全体をスキャンするような`offset`の使用は避ける

## 環境設定

いずれもpackage.jsonおよびcomposer.jsonでバージョンを管理。

- Node.js（Voltaでバージョン管理）
- PHP
- Docker Desktop（wp-envを使用）

Dockerの設定では、以下が可能です。

- 開発に関係するプラグイン・テーマ（検証用、依存関係など）の指定。
- PHPのバージョン指定。

### 依存関係のインストール

```bash
npm install
composer install
```

### 開発環境の起動

```bash
npm run start
```

初回起動後や、WordPress/プラグインのバージョンを更新したい場合：

```bash
npm run update
```

環境の停止：

```bash
npm run stop
```

開発環境でWP-CLIコマンドを実行：

```bash
npm run cli -- <コマンド>
```

例：

```bash
npm run cli -- plugin list
```

テスト環境でWP-CLIコマンドを実行：

```bash
npm run cli:test -- <コマンド>
```

## 開発手順

ファイルのコンパイル・トランスパイルなどは以下の手順で行なって下さい。

### ビルド

```bash
npm run build
```

### ファイル監視

開発中にファイル変更を自動でビルド：

```bash
npm run watch
```

### コードスタイルチェック

```bash
# CSS
npm run lint:css
# PHP
composer lint
```

### コードの自動修正

```bash
# CSS
npm run fix:css
# PHP
composer fix
```

## リリース

1. プルリクエストをmainブランチにマージ
2. GitHubのReleasesページで自動作成されたリリースドラフトを確認
3. 必要に応じてタグ名やリリースノートを編集
4. "Publish release"をクリック
5. GitHubのreleaseにzipが添付される。
