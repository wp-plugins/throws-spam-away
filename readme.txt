=== Plugin Name ===
Contributors: tsato
Donate link: http://iscw.jp/
Tags: comments, spam
Requires at least: 3.1
Tested up to: 3.2.1
Stable tag: 1.3

コメント内に日本語文字列が一つも存在しない場合あたかも受け付けたように振る舞いながらも無視

== Description ==
コメント欄に日本語文字列が含まれていないと投稿出来ない・・・といってもエラーにするのではなく「無視」して何事もなかったようにもとの記事に戻る。

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
