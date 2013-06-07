<?php
/*
 Plugin Name: Throws SPAM Away
 Plugin URI: http://gti.jp/tsa/
 Description: コメント内に日本語の記述が存在しない場合はあたかも受け付けたように振る舞いながらも捨ててしまうプラグイン
 Author: 株式会社ジーティーアイ　さとう　たけし
 Version: 2.4
 Author URI: http://gti.jp/
 */
require_once 'throws_spam_away.class.php';

// スパムデータベースバージョン
$tsa_db_version = 2.3;

/** 初期設定 */
// エラー種別
$error_type = "";
// 日本語文字最小含有数
$default_japanese_string_min_count = 3;
// コメント欄下に表示される注意文言（初期設定）
$default_caution_msg = '日本語が含まれない投稿は無視されますのでご注意ください。（スパム対策）';
// エラー時に表示されるエラー文言（初期設定）
$default_error_msg = '日本語を規定文字数以上含まない記事は投稿できませんよ。';
// 元画面に戻る時間
$default_back_second = 0;
// キーワードNGエラー時に表示されるエラー文言（初期設定）
$default_ng_key_error_msg = 'NGキーワードが含まれているため投稿できません。';
// 必須キーワードが含まれないエラー文言（初期設定）
$default_must_key_error_msg = "必須キーワードが含まれていないため投稿できません。";
// ブロックIPアドレスからの投稿の場合に表示されるエラー文言（初期設定）
$default_block_ip_address_error_msg = "";
// URL数制限値オーバーのエラー文言（初期設定）
$default_url_count_over_error_msg = "";
// URL数の制限をするか
$default_url_count_check_flg = "1"; // 1:する
// URL数の制限数
$default_ok_url_count = 3;  // ３つまで許容
/** オプションキー */
// 日本語が存在しない時エラーとするかフラグ         [tsa_on_flg] 1:する 2:しない
// 日本語文字列含有数 （入力値以下ならエラー）  [tsa_japanese_string_min_count] 数値型
// 元の記事に戻ってくる時間（秒）                               [tsa_back_second] 数値型
// コメント欄の下に表示される注意文言                       [tsa_caution_message] 文字列型
// 日本語文字列規定値未満エラー時に表示される文言（元の記事に戻ってくる時間の間のみ表示）
//                                                                                          [tsa_error_message] 文字列型
// その他NGキーワード（日本語でも英語（その他）でもNGとしたいキーワードを半角カンマ区切りで複数設定できます。挙動は同じです。NGキーワードだけでも使用できます。）
//                                                                                          [tsa_ng_keywords] 文字列型（半角カンマ区切り文字列）
// NGキーワードエラー時に表示される文言（元の記事に戻ってくる時間の間のみ表示）
//                                                                                          [tsa_ng_key_error_message] 文字列型
// 必須キーワード（日本語でも英語（その他）でも必須としたいキーワードを半角カンマ区切りで複数設定できます。指定文字列を含まない場合はエラーとなります。※複数の方が厳しくなります。必須キーワードだけでも使用できます。）
//                                                                                          [tsa_must_keywords] 文字列型（半角カンマ区切り文字列）
// 必須キーワードエラー時に表示される文言（元の記事に戻ってくる時間の間のみ表示）
//                                                                                          [tsa_must_key_error_message] 文字列型
// この設定をトラックバック記事にも採用するか       [tsa_tb_on_flg] 1:する 2:しない
// トラックバック記事にも採用する場合、ついでにこちらのURLが含まれているか判断するか
//                                                                                          [tsa_tb_url_flg] 1:する 2:しない
// WordPressのcommentsテーブルで「spam」判定されたことがあるIPアドレスからの投稿を無視するか
//                                                                                          [tsa_ip_block_from_spam_chk_flg] 1:する その他：しない
// ブロックしたいIPアドレスを任意で入力（半角カンマ区切りで複数設定できます。）
//                                                                                          [tsa_block_ip_addresses] 文字列型
// ブロック対象IPアドレスからの投稿時に表示される文言（元の記事に戻ってくる時間の間のみ表示）
//                                                                                          [tsa_block_ip_address_error_message] 文字列型
// URL（単純に'http'文字列のチェックのみ）文字列数を制限するか                              [tsa_url_count_on_flg] 1:する その他：しない
// URL（単純に'http'文字列のチェックのみ）文字列の許容数                                    [tsa_ok_url_count] 数値型
// URL（単純に'http'文字列のチェックのみ）文字列許容数オーバー時に表示される文言（元の記事に戻ってくる時間の間のみ表示）
//                                                                                          [tsa_url_count_over_error_message] 文字列型

/** プロセス */
$newThrowsSpamAway = new ThrowsSpamAway;
// トラックバックチェックフィルター
add_filter('preprocess_comment', array(&$newThrowsSpamAway, 'trackback_spam_away'), 1, 1);
// コメントフォーム表示
add_action('comment_form_after', array(&$newThrowsSpamAway, "comment_form"), 9999); // Ver.2.1.1 comment_form → comment_form_after
add_action('pre_comment_on_post', array(&$newThrowsSpamAway, "comment_post"), 1);
