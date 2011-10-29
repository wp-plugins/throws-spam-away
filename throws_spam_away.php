<?php
/*
 Plugin Name: Throws SPAM Away
 Plugin URI: http://iscw.jp/wp/
 Description: コメント内に日本語の記述が一つも存在しない場合はあたかも受け付けたように振る舞いながらも捨ててしまうプラグイン
 Author: 株式会社アイ・エス・シー　さとう　たけし
 Version: 1.4
 Author URI: http://iscw.jp/
 */

class ThrowsSpamAway {
	// version
	var $version = '1.4';
	
	function ThrowsSpamAway() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	function comment_form() {
		global $default_caution_msg;
		// 注意文言表示
		$caution_msg = get_option('tsa_caution_message');
		echo '<div id="throwsSpamAway">';
		echo ($caution_msg != null? $caution_msg : $default_caution_msg);
		echo '</div>';
		return TRUE;
	}

	function comment_post($id) {
		global $newThrowsSpamAway;
		global $user_ID;
		global $default_error_msg;
		global $default_ng_key_error_msg;
		global $error_type;

		if( $user_ID ) {
			return $id;
		}

		$comment = $_POST["comment"];
		if ($newThrowsSpamAway->validation($comment)) {
			return $id;
		}
		$error_msg = (
			$error_type != "ng_word" ? (
				get_option('tsa_error_message') != null ? 
					get_option('tsa_error_message') : $default_error_msg) : 
				(get_option('tsa_ng_key_error_message') != null ? 
					get_option('tsa_ng_key_error_message') : $default_ng_key_error_msg));
		wp_die( __(($error_msg != null? $error_msg : $default_error_msg).'<script type="text/javascript">window.setTimeout(location.href = "'.$_SERVER['HTTP_REFERER'].'", '.(get_option('tsa_back_content_second')!=null?get_option('tsa_back_content_second'):10).');</script>', 'throws-spam-away'));
	}

	/**
	 * 日本語が含まれているかチェックメソッド
	 * @param string $comment
	 */
	function validation($comment) {
		global $newThrowsSpamAway;
		global $error_type;
		// まずはシングルバイトだけならエラー
		if (get_option('tsa_on_flg') != "2" && strlen(bin2hex($comment)) / 2 == mb_strlen($comment)) {
			return FALSE;
		} else {
			// OKフラグ
			$flg = FALSE;
			// マルチバイト文字が含まれている場合は日本語が含まれていればOK
			if (get_option('tsa_on_flg') != "2") {
				$count_flg = 0;
				mb_regex_encoding('UTF-8');
				$com_split = $newThrowsSpamAway->mb_str_split($comment);
				foreach ($com_split as $it) {
					if (preg_match('/[一-龠]+/u', $it)){ $count_flg += 1; }
					if (preg_match('/[ァ-ヶー]+/u', $it)){ $count_flg += 1; }
					if (preg_match('/[ぁ-ん]+/u', $it)){ $count_flg += 1; }
				}
				$flg = ((get_option('tsa_japanese_string_min_count')!= null?
					intval(get_option('tsa_japanese_string_min_count')):0) < $count_flg);
				if ($flg == FALSE) {
					return FALSE;
				}
			}
			// 日本語文字列チェック抜けたらキーワードチェックを行う
			// キーワード文字列群
			$keyword_list = mb_split(",", get_option('tsa_ng_keywords'));
			foreach ($keyword_list as $key) {
				if (preg_match('/'.trim($key)."/u", $comment)) {
					$error_type = "ng_word";
					return FALSE;
				}
			}
			return TRUE;
		}
	}

	function mb_str_split( $string ) {
		return preg_split('/(?<!^)(?!$)/u', $string );
	}

	/**
	 * Callback admin_menu
	 */
	function admin_menu() {
		if ( function_exists( 'add_options_page' ) AND current_user_can( 'manage_options' ) ) {
			// add options page
			$page = add_options_page( __( 'Throws SPAM Away', $this->domain ), __( 'Throws SPAM Away', $this->domain ),
								'manage_options', __FILE__, array( $this, 'options_page' ) );
		}
	}

	/**
	 * Admin options page
	 */
	function options_page() {
		global $default_caution_msg;
		global $default_error_msg;
		global $default_ng_key_error_msg;
		?>
<div class="wrap">
	<h2>Throws SPAM Away. Setting</h2>
	<form method="post" action="options.php">
	<?php wp_nonce_field('update-options'); ?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">日本語が存在しない場合、無視対象とする<br />（日本語文字列が存在しない場合無視対象となります。）</th>
				<td><?php 
				$chk_1 = "";
				$chk_2 = "";
				if (get_option('tsa_on_flg') == "2") {
					$chk_2 = " checked=\"checked\"";
				} else {
					$chk_1 = " checked=\"checked\"";
				}
				 ?>
				 <label><input type="radio" name="tsa_on_flg"	value="1"<?php echo $chk_1;?>/>&nbsp;する</label>&nbsp;
				 <label><input type="radio" name="tsa_on_flg" value="2"<?php echo $chk_2;?>/>&nbsp;しない</label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">日本語文字列含有数<br />（この文字列に達していない場合無視対象となります。）</th>
				<td><input type="text" name="tsa_japanese_string_min_count"
					value="<?php echo get_option('tsa_japanese_string_min_count'); ?>" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">元の記事に戻ってくる時間<br />（ミリ秒）</th>
				<td><input type="text" name="tsa_back_content_second"
					value="<?php echo get_option('tsa_back_content_second');?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">コメント欄の下に表示される注意文言</th>
				<td><input type="text" name="tsa_caution_message" size="100"
					value="<?php echo get_option('tsa_caution_message');?>" /><br />（初期設定:<?php echo $default_caution_msg;?>）</td>
			</tr>
			<tr valign="top">
				<th scope="row">日本語文字列規定値未満エラー時に表示される文言<br />（元の記事に戻ってくる時間の間のみ表示）</th>
				<td><input type="text" name="tsa_error_message" size="100"
					value="<?php echo get_option('tsa_error_message');?>" /><br />（初期設定:<?php echo $default_error_msg;?>）</td>
			</tr>
			<tr valign="top">
				<th scope="row">その他NGキーワード<br />（日本語でも英語（その他）でもNGとしたいキーワードを半角カンマ区切りで複数設定できます。<br />挙動は同じです。NGキーワードだけでも使用できます。）</th>
				<td><input type="text" name="tsa_ng_keywords" size="100"
					value="<?php echo get_option('tsa_ng_keywords');?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">NGキーワードエラー時に表示される文言<br />（元の記事に戻ってくる時間の間のみ表示）</th>
				<td><input type="text" name="tsa_ng_key_error_message" size="100"
					value="<?php echo get_option('tsa_ng_key_error_message');?>" /><br />（初期設定:<?php echo $default_ng_key_error_msg;?>）</td>
			</tr>
		</table>
		<input type="hidden" name="action" value="update" /> <input
			type="hidden" name="page_options"
			value="tsa_on_flg,tsa_japanese_string_min_count,tsa_back_content_second,tsa_caution_message,tsa_error_message,tsa_ng_keywords,tsa_ng_key_error_message" />
		<p class="submit">
			<input type="submit" class="button-primary"
				value="<?php _e('Save Changes') ?>" />
		</p>
	</form>
	<div class="clear"></div>
</div>
	<?php
	}
}
// エラー種別
$error_type = "";
// コメント欄下に表示される注意文言（初期設定）
$default_caution_msg = '日本語が含まれない投稿は無視されますのでご注意ください。（スパム対策）';
// エラー時に表示されるエラー文言（初期設定）
$default_error_msg = '日本語を規定文字数以上含まない記事は投稿できませんよ。';
// キーワードNGエラー時に表示されるエラー文言（初期設定）
$default_ng_key_error_msg = 'NGキーワードが含まれているため投稿できません。';

$newThrowsSpamAway = new ThrowsSpamAway;
add_action('comment_form', array(&$newThrowsSpamAway, "comment_form"), 9999);
add_action('pre_comment_on_post', array(&$newThrowsSpamAway, "comment_post"), 1);
?>