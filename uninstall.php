<?php
/*
Throws SPAM Awayプラグインアンインストール
*/

if(!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN')) { exit(); }

delete_option('tsa_back_content_second');	// ver.1.6以下の方用
delete_option('tsa_on_flg');
delete_option('tsa_japanese_string_min_count');
delete_option('tsa_back_second');
delete_option('tsa_caution_message');
delete_option('tsa_error_message');
delete_option('tsa_ng_keywords');
delete_option('tsa_ng_key_error_message');
delete_option('tsa_must_keywords');
delete_option('tsa_must_key_error_message');
delete_option('tsa_tb_on_flg');
delete_option('tsa_tb_url_flg');
?>