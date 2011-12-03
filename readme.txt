=== Plugin Name ===
Contributors: tsato
Donate link: http://iscw.jp/
Tags: comments, spam
Requires at least: 3.1
Tested up to: 3.3b
Stable tag: 1.4.1

コメント内に日本語文字列が一つも存在しない場合あたかも受け付けたように振る舞いながらも無視

== Description ==
海外からのコメントスパムに対抗（？）する手段として開発したプラグインです。

コメント欄に日本語文字列が含まれていないと投稿出来ない・・・
といってもエラーにするのではなく「無視」して何事もなかったようにもとの記事に戻ります。

ダブルバイトをカウントするのではなく正規表現により日本語を検出しているため、ダブルバイトの他言語も侵入してきません。

また、NGキーワードを複数設定することが出来ます。（カンマ区切りにて設定）
日本語の文章であっても設定された「NGキーワード」を含む投稿の場合は、同様に無視します。
もちろん他の言語のキーワードでもOKです。

日本語が一切入っていないコメントでも「NGキーワード」だけ設定したい場合を想定し
日本語が入っていないと許可しない設定を「オン・オフ」出来るようになっています。

制作：TAMAN <a href="http://iscw.jp/" target="_blank">ホームページ制作 池袋</a> 株式会社アイ・エス・シー所属

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `throws-spam-away` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= A question that someone might have =

= What about foo bar? =


== Screenshots ==

== Changelog ==
= 1.4.1 =
バグ修正

= 1.4 =
機能のオン・オフをつけました。
NGキーワードの設定が出来るようになりました。
日本語でも他の言語でもNGキーワードが入っていれば同様に処理するようにしました。
NGキーワードに引っかかってしまった場合のエラー文言は別途設定できます。

= 1.3 =
コメント欄下の注意文言及びエラー文言を設定可能にしました。

= 1.2.1 =
バージョン表記修正。機能は変更ありません。

= 1.2 =
設定画面を設けました。こちらで、日本語文字が何文字以上入っていないとNGかのしきい値とちらっと現れるエラー文言画面から元の記事に戻ってくる時間（ミリ秒）を設定することが出来ます。

= 1.1 =
マルチバイト文字が存在していても日本語文字列を含まないとNGとするよう
正規表現を入れて精査するように修正

= 1.0 =
新規作成

== Upgrade Notice ==
= 1.4 =
機能のオン・オフ設定追加
NGキーワード（日本語及びその他言語）の設定による無視機能追加

= 1.3 =
コメント欄下の注意文言及びエラー文言を設定可能にしました。

= 1.2.1 =
バージョン表記修正

= 1.2 =
日本語が1文字でも含まれると通ってしまうのを何文字以上含まれていないといけないのかを設定出来るようにしました。

= 1.1 =
正規表現を使いロシア語や他のマルチバイト文字列を使用する言語からの攻撃に対応

= 1.0 =
とりあえず作りました！
== Arbitrary section ==

== A brief Markdown Example ==
