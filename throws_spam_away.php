<?php
/*
 Plugin Name: Throws SPAM Away
 Plugin URI: http://iscw.jp/wp/
 Description: コメント内に日本語の記述が一つも存在しない場合はあたかも受け付けたように振る舞いながらも捨ててしまうプラグイン
 Author: 株式会社アイ・エス・シー　さとう　たけし
 Version: 1.2.1
 Author URI: http://iscw.jp/
 */

class ThrowsSpamAway {
	var $version = '1.2.1';
	function ThrowsSpamAway() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	function comment_form() {
		// 注意文言表示
		echo '<div id="throwsSpamAway">';
		echo '日本語が含まれない投稿は無視されますのでご注意ください。（スパム対策）';
		echo '</div>';
		return true;
	}

	function comment_post($id) {
		global $newThrowsSpamAway;
		global $user_ID;

		if( $user_ID ) {
			return $id;
		}

		$comment = $_POST["comment"];
		if ($newThrowsSpamAway->validation($comment)) {
			return $id;
		}
		wp_die( __('日本語を規定文字数以上含まない記事は投稿できませんよ。<script type="text/javascript">window.setTimeout(location.href = "'.$_SERVER['HTTP_REFERER'].'", '.(get_option('back_content_second')!=null?get_option('back_content_second'):10).');</script>', 'throws-spam-away'));
	}

	/**
	 * 日本語が含まれているかチェックメソッド
	 * @param string $comment
	 */
	function validation($comment) {
		global $newThrowsSpamAway;
		// まずはシングルバイトだけならエラー
		if (strlen(bin2hex($comment)) / 2 == mb_strlen($comment)) {
			return false;
		} else {
			// マルチバイト文字が含まれている場合は日本語が含まれていればOK
			$count_flg = 0;
			mb_regex_encoding('UTF-8');
			$com_split = $newThrowsSpamAway->mb_str_split($comment);
			foreach ($com_split as $it) {
				if (preg_match('/[一-龠]+/u', $it)){ $count_flg += 1; }
				if (preg_match('/[ァ-ヶー]+/u', $it)){ $count_flg += 1; }
				if (preg_match('/[ぁ-ん]+/u', $it)){ $count_flg += 1; }
			}
			return ((get_option('tsa_japanese_string_min_count')!= null?
					intval(get_option('tsa_japanese_string_min_count')):0) < $count_flg);
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
?>
<div class="wrap">
	<h2>Throws SPAM Away. Setting</h2>
<form method="post" action="options.php">
<?php wp_nonce_field('update-options'); ?>
<table class="form-table">
	<tr valign="top">
		<th scope="row">日本語文字列含有数<br />（この文字列に達していない場合無視対象となります。）</th>
		<td><input type="text" name="tsa_japanese_string_min_count" value="<?php echo get_option('tsa_japanese_string_min_count'); ?>" /></td>
	</tr>
	<tr valign="top">
		<th scope="row">元の記事に戻ってくる時間<br />（ミリ秒）</th>
		<td><input type="text" name="tsa_back_content_second" value="<?php echo get_option('tsa_back_content_second');?>" /></td>
	</tr>	
</table>
<input type="hidden" name="action" value="update" />
<input type="hidden" name="page_options" value="tsa_japanese_string_min_count,tsa_back_content_second" />
<p class="submit">
		<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
</p>
</form>
		<div class="clear"></div>
	</div>
<?php
	}
}

$newThrowsSpamAway = new ThrowsSpamAway;
add_action('comment_form', array(&$newThrowsSpamAway, "comment_form"), 9999);
add_action('pre_comment_on_post', array(&$newThrowsSpamAway, "comment_post"), 9999);
?>