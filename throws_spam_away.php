<?php
/*
 Plugin Name: Throws SPAM Away
 Plugin URI: http://iscw.jp/wp/
 Description: コメント内に日本語の記述が一つも存在しない場合はあたかも受け付けたように振る舞いながらも捨ててしまうプラグイン
 Author: 株式会社アイ・エス・シー　さとう　たけし
 Version: 1.0
 Author URI: http://iscw.jp/
 */

class ThrowsSpamAway {
	var $version = '1.0';
	function ThrowsSpamAway() {
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
		wp_die( __('日本語を含まない記事は投稿できませんよ。<script type="text/javascript">window.setTimeout(location.href = "'.$_SERVER['HTTP_REFERER'].'", 100);</script>', 'throws-spam-away'));
	}

	/**
	 * 日本語が含まれているかチェックメソッド
	 * @param string $comment
	 */
	function validation($comment) {
		// まずはシングルバイトだけならエラー
		if (strlen(bin2hex($comment)) / 2 == mb_strlen($comment)) {
			return false;
		} else {
			// マルチバイト文字が含まれている場合は日本語が含まれていればOK
			$flg = false;
			mb_regex_encoding('UTF-8');
			if (preg_match('/[一-龠]+/u', $comment)){ $flg = true; }
			if (preg_match('/[ァ-ヶー]+/u', $comment)){ $flg = true; }
			if (preg_match('/[ぁ-ん]+/u', $comment)){ $flg = true; }
			return $flg;
		}
	}
}

$newThrowsSpamAway = new ThrowsSpamAway;
add_action('comment_form', array(&$newThrowsSpamAway, "comment_form"), 9999);
add_action('pre_comment_on_post', array(&$newThrowsSpamAway, "comment_post"), 9999);
?>