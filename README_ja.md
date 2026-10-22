```
  _____  _____     ___  _  _   _____ 
 / ____|  __ \   |__ \| || | |___  |
| |  __| |__) |     ) | || |_   / / 
| | |_ |  ___/     / /|__   _| / /  
| |__| | |        / /_   | |  / /   
 \_____|_|       |____|  |_| /_/    
```

> 🌐 **言語:** [🇻🇳 Tiếng Việt](./README_vi.md) · [🇬🇧 English](./README.md) · 🇯🇵 日本語（現在）

企業向けの無料 EC サイトシステム

`composer require gp247/shop`

[![総ダウンロード数](https://poser.pugx.org/GP247/Shop/d/total.svg)](https://packagist.org/packages/GP247/Shop)
[![最新の安定版](https://poser.pugx.org/GP247/Shop/v/stable.svg)](https://packagist.org/packages/GP247/Shop)
[![ライセンス](https://poser.pugx.org/GP247/Shop/license.svg)](https://packagist.org/packages/GP247/Shop)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/gp247net/shop)


## 概要

GP247/Shop は GP247 エコシステムの主要パッケージの 1 つで、GP247 を企業向けの強力なオンラインストアに変えます。このパッケージは、GP247 エコシステムのすべての機能を継承しつつ、EC に必要な機能を一通り提供します。

## 主な機能

### EC 機能
- 商品管理
  - 商品カテゴリーと属性
  - 商品バリエーションとオプション
  - 在庫管理
  - 一括インポート／エクスポート
- 注文管理
  - 注文の処理と追跡
  - 複数の決済ゲートウェイ
  - 配送方法の統合
  - 注文ステータスの管理
- 顧客管理
  - 顧客プロフィールとアカウント
  - 住所の管理
  - 注文履歴
  - 顧客グループと割引
- マーケティングツール
  - プロモーションと割引
  - クーポンの管理
  - ニュースレターの統合
  - 商品レビューと評価
- ショッピング機能
  - カート
  - お気に入りリスト
  - 商品比較
  - 最近閲覧した商品
- マルチベンダー対応
  - 販売者ダッシュボード
  - 手数料の管理
  - 販売者の商品管理
  - 販売者の注文追跡

### GP247 エコシステムの機能
- ページコンテンツの管理
- 柔軟なテンプレートシステム
- 拡張可能なプラグインシステム
- ナビゲーションとリンクの管理
- お問い合わせフォームと購読フォームの統合
- 多言語対応
- SEO 最適化
- モバイル対応のレスポンシブデザイン
- セキュリティ機能
- バックアップと復元

## インストール

### 選択肢 1：GP247 CMS で新規インストール
1. gp247/cms をインストールします（Laravel、GP247/Core、GP247/Front を含みます）

>`composer create-project gp247/cms`

2. gp247/shop パッケージをインストールします

>`composer require gp247/shop`

3. `bootstrap/providers.php` にサービスプロバイダーを登録します（配列の末尾に追加）

```php
return [
    // ... 既存のプロバイダー
    GP247\Shop\ShopServiceProvider::class,
];
```


4. インストールしてサンプルデータを作成します

>`php artisan gp247:shop-install`

>`php artisan gp247:shop-sample`



### 選択肢 2：S-Cart のソースコードを使用する
S-Cart には必要なコンポーネントがすべて含まれています。詳細は [S-Cart の GitHub リンク](https://github.com/gp247net/s-cart) をご覧ください。

1. パッケージをインストールします

>`composer create-project gp247/s-cart`

2. プラットフォームをインストールします（core + front + shop を自動で検出）

>`php artisan gp247:install`

3. （任意）サンプルデータをインストールします

>`php artisan gp247:shop-sample`


## カスタマイズ

### 管理画面のビューをカスタマイズする
管理画面のビューをカスタマイズするには、次のコマンドを実行します：

>`php artisan vendor:publish --tag=gp247:shop-view-admin`

ビューは `resources/views/vendor/gp247-shop-admin` に保存されます

### フロントのビューをカスタマイズする
フロントのビューをカスタマイズ・更新するには、次のコマンドを実行します：

>`php artisan vendor:publish --tag=gp247:shop-view-front`

ビューは `app/GP247/Templates/GP247Front` に保存されます

`GP247Front` テンプレートを使用していない場合は、`vendor/gp247/shop/src/Views/front` から新しいビューへ手動でコピーしてください。

### コントローラーのオーバーライド（override）

>ステップ 1：オーバーライドしたいコントローラーファイルを `vendor/gp247/shop/src/Controllers`（または `src/Api/Controllers`）-> `app/GP247/Shop/Controllers`（または `app/GP247/Shop/Api/Controllers`）にコピーします

>ステップ 2：名前空間を `GP247\Shop\Controllers`（または `GP247\Shop\Api\Controllers`）から `App\GP247\Shop\Controllers`（または `App\GP247\Shop\Api\Controllers`）に変更します — 先頭に `App` を追加するだけで、残りはそのままにします。

## ドキュメント
- GP247 システムの完全なドキュメントは、[ドキュメント](https://gp247.net/en/docs) をご覧ください

## ライセンス
GP247/Shop は、[MIT ライセンス](https://opensource.org/licenses/MIT) のもとで提供されるオープンソースソフトウェアです。
