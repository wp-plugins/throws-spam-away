<?php
/*
 Plugin Name: Throws SPAM Away
 Plugin URI: http://gti.jp/tsa/
 Description: コメント内に日本語の記述が存在しない場合はあたかも受け付けたように振る舞いながらも捨ててしまうプラグイン
 Author: 株式会社ジーティーアイ　さとう　たけし
 Version: 2.1
 Author URI: http://gti.jp/
 */

/** 初期設定 */
// エラー種別
$error_type = "";
// コメント欄下に表示される注意文言（初期設定）
$default_caution_msg = '日本語が含まれない投稿は無視されますのでご注意ください。（スパム対策）';
// エラー時に表示されるエラー文言（初期設定）
$default_error_msg = '日本語を規定文字数以上含まない記事は投稿できませんよ。';
// キーワードNGエラー時に表示されるエラー文言（初期設定）
$default_ng_key_error_msg = 'NGキーワードが含まれているため投稿できません。';
// 必須キーワードが含まれないエラー文言（初期設定）
$default_must_key_error_msg = "必須キーワードが含まれていないため投稿できません。";
// ブロックIPアドレスからの投稿の場合に表示されるエラー文言（初期設定）
$default_block_ip_address_error_msg = "";
/** オプションキー */
// 日本語が存在しない時エラーとするかフラグ			[tsa_on_flg] 1:する 2:しない
// 日本語文字列含有数 （入力値以下ならエラー）	[tsa_japanese_string_min_count] 数値型
// 元の記事に戻ってくる時間（秒）								[tsa_back_second] 数値型
// コメント欄の下に表示される注意文言						[tsa_caution_message] 文字列型
// 日本語文字列規定値未満エラー時に表示される文言（元の記事に戻ってくる時間の間のみ表示）
//																							[tsa_error_message] 文字列型
// その他NGキーワード（日本語でも英語（その他）でもNGとしたいキーワードを半角カンマ区切りで複数設定できます。挙動は同じです。NGキーワードだけでも使用できます。）
// 																							[tsa_ng_keywords] 文字列型（半角カンマ区切り文字列）
// NGキーワードエラー時に表示される文言（元の記事に戻ってくる時間の間のみ表示）
// 																							[tsa_ng_key_error_message] 文字列型
// 必須キーワード（日本語でも英語（その他）でも必須としたいキーワードを半角カンマ区切りで複数設定できます。指定文字列を含まない場合はエラーとなります。※複数の方が厳しくなります。必須キーワードだけでも使用できます。）
//																							[tsa_must_keywords] 文字列型（半角カンマ区切り文字列）
// 必須キーワードエラー時に表示される文言（元の記事に戻ってくる時間の間のみ表示）
// 																							[tsa_must_key_error_message] 文字列型
// この設定をトラックバック記事にも採用するか		[tsa_tb_on_flg] 1:する 2:しない
// トラックバック記事にも採用する場合、ついでにこちらのURLが含まれているか判断するか
//																							[tsa_tb_url_flg] 1:する 2:しない
// WordPressのcommentsテーブルで「spam」判定されたことがあるIPアドレスからの投稿を無視するか
// 																							[tsa_ip_block_from_spam_chk_flg] 1:する その他：しない
// ブロックしたいIPアドレスを任意で入力（半角カンマ区切りで複数設定できます。）
//																							[tsa_block_ip_addresses] 文字列型
// ブロック対象IPアドレスからの投稿時に表示される文言（元の記事に戻ってくる時間の間のみ表示）
// 																							[tsa_block_ip_address_error_message] 文字列型

/** プロセス */
$newThrowsSpamAway = new ThrowsSpamAway;
// トラックバックチェックフィルター
add_filter('preprocess_comment', array(&$newThrowsSpamAway, 'trackback_spam_away'), 1, 1);
// コメントフォーム表示
add_action('comment_form', array(&$newThrowsSpamAway, "comment_form"), 9999);
add_action('pre_comment_on_post', array(&$newThrowsSpamAway, "comment_post"), 1);

/**
 *
 * <p>ThrowsSpamAway</p>
 * WordPress's Plugin
 * @author TAMAN
 *
 */
class ThrowsSpamAway {
	// version
	var $version = '2.1';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	function comment_form() {
		global $default_caution_msg;
		// 注意文言表示
		$caution_msg = get_option('tsa_caution_message');
		echo '<div id="throwsSpamAway">';
		echo ($caution_msg != NULL? $caution_msg : $default_caution_msg);
		echo '</div>';
		return TRUE;
	}

	function comment_post($id) {
		global $newThrowsSpamAway;
		global $user_ID;
		global $default_error_msg;
		global $default_ng_key_error_msg;
		global $default_must_key_error_msg;
		global $error_type;

		if( $user_ID ) {
			return $id;
		}

		$comment = $_POST["comment"];
		// IP系の検査
		$ip = $_SERVER['REMOTE_ADDR'];
		if (!$newThrowsSpamAway->ip_check($ip)) {
			// アウト！
		} else
		// コメント検査
		if ($newThrowsSpamAway->validation($comment)) {
			return $id;
		}
		$error_msg = "";
		switch ($error_type) {
			case "must_word" :
				$error_msg = (get_option('tsa_must_key_error_message') != NULL ?
							get_option('tsa_must_key_error_message') : $default_must_key_error_msg);
				break;
			case "ng_word" :
				$error_msg = (get_option('tsa_ng_key_error_message') != NULL ?
							get_option('tsa_ng_key_error_message') : $default_ng_key_error_msg);
				break;
			case "block_ip" :
				$error_msg = (get_option('tsa_block_ip_address_error_message') != NULL ?
							get_option('tsa_block_ip_address_error_message') : $default_block_ip_address_error_msg);
				break;
			default :
				$error_msg = (get_option('tsa_error_message') != NULL ?
							get_option('tsa_error_message') : $default_error_msg);
		}
		// 元画面へ戻るタイム計算
		$back_time = get_option('tsa_back_second')!=NULL?(((int)get_option('tsa_back_second')) * 1000):0;
		// タイム値が０なら元画面へそのままリダイレクト
		if ($back_time == 0) {
			header("Location:".$_SERVER['HTTP_REFERER']);
			die;
		} else {
			wp_die( __(($error_msg != NULL? $error_msg : "")."<script type=\"text/javascript\">window.setTimeout(location.href='".$_SERVER['HTTP_REFERER']."', ".$back_time.");</script>", 'throws-spam-away'));
		}
	}

	/**
	 * IPアドレスのチェックメソッド
	 * @param string $target_ip
	 */
	function ip_check($target_ip) {
		global $wpdb; // WordPress DBアクセス
		global $newThrowsSpamAway;
		global $error_type;
		// IP制御 WordPressのスパムチェックにてスパム扱いしている投稿のIPをブロックするか
		$ip_block_from_spam_chk_flg = get_option('tsa_ip_block_from_spam_chk_flg');

		if ($ip_block_from_spam_chk_flg === "1") {
			// wp_commentsの　comment_approved　カラムが「spam」のIP_ADDRESSからの投稿は無視する
			$results = $wpdb->get_results("SELECT DISTINCT comment_author_IP FROM  $wpdb->comments WHERE comment_approved =  'spam' ORDER BY comment_author_IP ASC ");
			foreach ($results as $item) {
				if (trim($item->comment_author_IP) == trim($target_ip)) {
					// ブロックしたいIP
					$error_type = "block_ip";
					return FALSE;
				}
			}
		}
		// IP制御 任意のIPアドレスをあればブロックする
		$block_ip_addresses = get_option('tsa_block_ip_addresses');
		if ($block_ip_addresses != NULL && $block_ip_addresses != "") {
			$ip_list = mb_split(",", $block_ip_addresses);
			foreach ($ip_list as $ip) {
				// 指定IPが範囲指定の場合 例：192.168.1.0/24
				if ( strpos( $ip, "/" ) != FALSE ) {
					if ( $this->inCIDR( $target_ip, $ip ) ) {
						// ブロックしたいIP
						$error_type = "block_ip";
						return FALSE;
					}
				} elseif (trim($ip) == trim($target_ip)) {
					// ブロックしたいIP
					$error_type = "block_ip";
					return FALSE;
				} else {
					// セーフIP
				}
			}
		}
		return TRUE;
	}

	/**
	 * CIDRチェック
	 * @param string $ip
	 * @param string $cidr
	 * @return boolean
	 */
	function inCIDR($ip, $cidr) {
		list($network, $mask_bit_len) = explode('/', $cidr);
		if ( !is_nan($mask_bit_len) && $mask_bit_len <= 32) {
			$host = 32 - $mask_bit_len;
			$net = ip2long($network) >> $host << $host; // 11000000101010000000000000000000
			$ip_net = ip2long($ip) >> $host << $host; 	// 11000000101010000000000000000000
			return $net === $ip_net;
		} else {
			// 形式が不正ならば無視するためFALSE
			return FALSE;
		}
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
				$flg = ((get_option('tsa_japanese_string_min_count')!= NULL?
					intval(get_option('tsa_japanese_string_min_count')):0) < $count_flg);
				if ($flg == FALSE) {
					return FALSE;
				}
			}
			// 日本語文字列チェック抜けたらキーワードチェックを行う
			// キーワード文字列群
			$ng_keywords = get_option('tsa_ng_keywords');
			if ($ng_keywords != NULL && $ng_keywords != "") {
				$keyword_list = mb_split(",", $ng_keywords);
				foreach ($keyword_list as $key) {
					if (preg_match('/'.trim($key)."/u", $comment)) {
						$error_type = "ng_word";
						return FALSE;
					}
				}
			}
			// キーワードチェック（ブラックリスト）を抜けたら必須キーワードチェックを行う
			// キーワード文字列群　※ブラックリストと重複するものはブラックリストのほうが優先です。
			$must_keywords = get_option('tsa_must_keywords');
			if ($must_keywords != NULL && $must_keywords != "") {
				$keyword_list = mb_split(",", $must_keywords);
				foreach ($keyword_list as $key) {
					if (preg_match('/'.trim($key)."/u", $comment)) {
						// OK
					} else {
						// 必須ワードがなかったためエラー
						$error_type = "must_word";
						return FALSE;
					}
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
		global $wpdb; // WordPress DBアクセス
		global $default_caution_msg;
		global $default_error_msg;
		global $default_ng_key_error_msg;
		global $default_must_key_error_msg;
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
				 <label><input type="radio" name="tsa_on_flg" value="1"<?php echo $chk_1;?>/>&nbsp;する</label>&nbsp;
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
				<th scope="row">元の記事に戻ってくる時間<br />（秒）※0の場合エラー画面表示しません。</th>
				<td><input type="text" name="tsa_back_second"
					value="<?php echo get_option('tsa_back_second');?>" /></td>
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
			<tr valign="top">
				<th scope="row">その上での必須キーワード<br />（日本語でも英語（その他）でも必須としたいキーワードを半角カンマ区切りで複数設定できます。<br />指定文字列を含まない場合はエラーとなります。※複数の方が厳しくなります。<br />必須キーワードだけでも使用できます。）</th>
				<td><input type="text" name="tsa_must_keywords" size="100"
					value="<?php echo get_option('tsa_must_keywords');?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">必須キーワードエラー時に表示される文言<br />（元の記事に戻ってくる時間の間のみ表示）</th>
				<td><input type="text" name="tsa_must_key_error_message" size="100"
					value="<?php echo get_option('tsa_must_key_error_message');?>" /><br />（初期設定:<?php echo $default_must_key_error_msg;?>）</td>
			</tr>
			<tr valign="top">
				<th scope="row">上記設定をトラックバック記事にも採用する</th>
				<td><?php
				$chk_1 = "";
				$chk_2 = "";
				if (get_option('tsa_tb_on_flg', "2") == "2") {
					$chk_2 = " checked=\"checked\"";
				} else {
					$chk_1 = " checked=\"checked\"";
				}
				 ?>
				 <label><input type="radio" name="tsa_tb_on_flg" value="1"<?php echo $chk_1;?>/>&nbsp;する</label>&nbsp;
				 <label><input type="radio" name="tsa_tb_on_flg" value="2"<?php echo $chk_2;?>/>&nbsp;しない</label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">トラックバック記事にも採用する場合、ついでにこちらのURLが含まれているか判断する<br />（初期設定:「しない」）</th>
				<td><?php
				$chk_1 = "";
				$chk_2 = "";
				if (get_option('tsa_tb_url_flg', "2") == "2") {
					$chk_2 = " checked=\"checked\"";
				} else {
					$chk_1 = " checked=\"checked\"";
				}
				 ?>
				 <label><input type="radio" name="tsa_tb_url_flg" value="1"<?php echo $chk_1;?>/>&nbsp;する</label>&nbsp;
				 <label><input type="radio" name="tsa_tb_url_flg" value="2"<?php echo $chk_2;?>/>&nbsp;しない</label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">WordPressのコメントで「スパム」にしたIPからの投稿にも採用する</th>
				<td><?php
				$chk = "";
				if (get_option('tsa_ip_block_from_spam_chk_flg') == "1") {
					$chk = "checked=\"checked\"";
				} else {
					$chk = "";
				}
				?>
				<label><input type="checkbox" name="tsa_ip_block_from_spam_chk_flg" value="1"<?php echo $chk; ?>/>&nbsp;スパム投稿設定したIPアドレスからの投稿も無視する</label><br />
<?php
			// wp_commentsの　comment_approved　カラムが「spam」のIP_ADDRESSからの投稿は無視する
			$results = $wpdb->get_results("SELECT DISTINCT comment_author_IP FROM  $wpdb->comments WHERE comment_approved =  'spam' ORDER BY comment_author_IP ASC ");
?>現在「spam」フラグが付いているIPアドレス：<?php
			foreach ($results as $item) {
				// ブロックしたいIP
?><b><?php echo $item->comment_author_IP; ?></b>&nbsp;<?php
			}
?>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">任意のIPアドレスからの投稿も無視したい場合、対象となるIPアドレスを記述してください。<br />カンマ区切りで複数設定できます。（半角数字とドットのみ）</th>
				<td><input type="text" name="tsa_block_ip_addresses" size="100"
					value="<?php echo get_option('tsa_block_ip_addresses');?>" /></td>
			</tr>
			<tr valign="top">
			<th scope="row">ブロック対象のIPアドレスからの投稿時に表示される文言<br />（元の記事に戻ってくる時間の間のみ表示）</th>
			<td><input type="text" name="tsa_block_ip_address_error_message" size="100"
					value="<?php echo get_option('tsa_block_ip_address_error_message');?>" /><br />（初期設定：<?php echo $default_block_ip_address_error_msg; ?>）</td>
		</table>
		<input type="hidden" name="action" value="update" /> <input
			type="hidden" name="page_options"
			value="tsa_on_flg,tsa_japanese_string_min_count,tsa_back_second,tsa_caution_message,tsa_error_message,tsa_ng_keywords,tsa_ng_key_error_message,tsa_must_keywords,tsa_must_key_error_message,tsa_tb_on_flg,tsa_tb_url_flg,tsa_block_ip_addresses,tsa_ip_block_from_spam_chk_flg,tsa_block_ip_address_error_message" />
		<p class="submit">
			<input type="submit" class="button-primary"
				value="<?php _e('Save Changes') ?>" />
		</p>
	</form>
	<div class="clear"></div>
</div>
	<?php
	}

	function trackback_spam_away($tb) {
		global $newThrowsSpamAway;

		$tsa_tb_on_flg = get_option('tsa_tb_on_flg');
		$tsa_tb_url_flg = get_option('tsa_tb_url_flg');
		$siteurl = get_option('siteurl');
		// トラックバック OR ピンバック時にフィルタ発動
		if ($tsa_tb_on_flg == "2" || ($tb['comment_type'] != 'trackback' && $tb['comment_type'] != 'pingback')) return $tb;

		// SPAMかどうかフラグ
		$tb_val['is_spam'] = FALSE;

		// コメント判定
		$comment = $tb['comment_content'];

		// IP系の検査
		$ip = $_SERVER['REMOTE_ADDR'];
		if (!$newThrowsSpamAway->ip_check($ip)) {
			$tb_val['is_spam'] = TRUE;
		} else
		// 検査します！
		if (!$newThrowsSpamAway->validation($comment)) {
			$tb_val['is_spam'] = TRUE;
		} else
		// URL検索する場合、URL包含検査 （このブログのURLを含んでない場合エラー
		if ($tsa_tb_url_flg == "1" && stripos($comment, $siteurl) == FALSE) {
			$tb_val['is_spam'] = TRUE;	// スパム扱い
		}
		// トラックバックスパムがなければ返却・あったら捨てちゃう
		if (!$tb_val['is_spam']) {
			// トラックバック内に日本語存在（または禁止語句混入なし）
			return $tb;
		} else {
			die('Your Trackback Throws Away.');
		}
	}
}
