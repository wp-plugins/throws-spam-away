<?php
/**
 *
 * <p>ThrowsSpamAway</p> Class
 * WordPress's Plugin
 * @author Takeshi Satoh@GTI Inc. 2013
 *
 */
class ThrowsSpamAway {

	// version
	var $version = '2.4';
	var $table_name = "";

	public function __construct($flg = FALSE) {
		global $default_spam_data_save;
		if ($flg == FALSE) {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		}
		global $wpdb;
		// 接頭辞（wp_）を付けてテーブル名を設定
		$this->table_name = $wpdb->prefix . 'tsa_spam';
	}

	/**
	 * スパム投稿テーブル作成
	 * $flg がTRUEなら強制的にテーブル作成
	 */
	function tsa_createTbl() {
		global $wpdb;
		global $tsa_db_version;

		// テーブル作成要フラグ
		$flg = FALSE;
		if($wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") != $this->table_name) {
			// テーブルが存在しないため作成する
			$flg = TRUE;
		}

		//DBのバージョン
		//$tsa_db_version
		//現在のDBバージョン取得
		$installed_ver = get_option( 'tsa_meta_version', 0 );
		// DBバージョンが低い　または　テーブルが存在しない場合は作成
		if( $installed_ver < $tsa_db_version || $flg == TRUE) {
			// DBバージョンは 2.3未満が存在しないためSQLはCREATE文のみ
			$sql = "CREATE TABLE " . $this->table_name . " (
              meta_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
              post_id bigint(20) UNSIGNED DEFAULT '0' NOT NULL,
              ip_address text,
              post_date timestamp,
              UNIQUE KEY meta_id (meta_id)
            )
            CHARACTER SET 'utf8';";
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
			//オプションにDBバージョン保存
			update_option( 'tsa_meta_version', $tsa_db_version );
		}
	}

	/**
	 * スパム投稿の記録
	 * @param string $post_id
	 * @param string $ip_address
	 */
	function save_post_meta( $post_id, $ip_address ) {
		if ( get_option('tsa_spam_data_save', $default_spam_data_save) != "1" )  return;

		global $wpdb;

		//保存するために配列にする
		$set_arr = array(
				'post_id' => $post_id,
				'ip_address' => $ip_address
		);

		//レコード新規追加
		$wpdb->insert( $this->table_name, $set_arr );
		$wpdb->show_errors();
		return;
	}

	function comment_form() {
		global $default_caution_msg;
		// 注意文言表示
		$caution_msg = get_option( 'tsa_caution_message', $default_caution_msg );
		echo '<div id="throwsSpamAway">'.$caution_msg.'</div>';
		return TRUE;
	}

	function comment_post( $id ) {
		global $newThrowsSpamAway;
		global $user_ID;
		global $default_back_second;
		global $default_error_msg;
		global $default_ng_key_error_msg;
		global $default_must_key_error_msg;
		global $default_block_ip_address_error_msg;
		global $default_url_count_over_error_msg;
		global $error_type;

		if( $user_ID ) {
			return $id;
		}
		// コメント（comment）及び名前（author）の中も検査
		$author = $_POST["author"];
		$comment = $_POST["comment"];
		// IP系の検査
		$ip = $_SERVER['REMOTE_ADDR'];
		if ( !$newThrowsSpamAway->ip_check( $ip ) ) {
			// アウト！
		} else
			// コメント検査
			if ( $newThrowsSpamAway->validation( $comment, $author ) ) {
			return $id;
		}
		$error_msg = "";
		switch ( $error_type ) {
			case "must_word" :
				$error_msg = get_option( 'tsa_must_key_error_message', $default_must_key_error_msg );
				break;
			case "ng_word" :
				$error_msg = get_option( 'tsa_ng_key_error_message', $default_ng_key_error_msg );
				break;
			case "block_ip" :
				$error_msg = get_option( 'tsa_block_ip_address_error_message', $default_block_ip_address_error_msg );
				break;
			case "url_count_over" :
				$error_msg = get_option( 'tsa_url_count_over_error_message', $default_url_count_over_error_msg );
				break;
			default :
				$error_msg = get_option( 'tsa_error_message', $default_error_msg );
		}
		// 記録する場合はDB記録
		if ( get_option( 'tsa_spam_data_save', $default_spam_data_save ) == "1" ) $this->save_post_meta( $id, $ip );
		// 元画面へ戻るタイム計算
		$back_time = ( (int) get_option( 'tsa_back_second', $default_back_second ) ) * 1000;
		// タイム値が０なら元画面へそのままリダイレクト
		if ( $back_time == 0 ) {
			header( "Location:".$_SERVER['HTTP_REFERER'] );
			die;
		} else {
			wp_die( __($error_msg."<script type=\"text/javascript\">window.setTimeout(location.href='".$_SERVER['HTTP_REFERER']."', ".$back_time.");</script>", 'throws-spam-away'));
		}
	}

	/**
	 * IPアドレスのチェックメソッド
	 * @param string $target_ip
	 */
	function ip_check( $target_ip ) {
		global $wpdb; // WordPress DBアクセス
		global $newThrowsSpamAway;
		global $error_type;
		// IP制御 WordPressのスパムチェックにてスパム扱いしている投稿のIPをブロックするか
		$ip_block_from_spam_chk_flg = get_option( 'tsa_ip_block_from_spam_chk_flg' );

		if ($ip_block_from_spam_chk_flg === "1") {
			// wp_commentsの　comment_approved　カラムが「spam」のIP_ADDRESSからの投稿は無視する
			$results = $wpdb->get_results( "SELECT DISTINCT comment_author_IP FROM  $wpdb->comments WHERE comment_approved =  'spam' ORDER BY comment_author_IP ASC " );
			foreach ( $results as $item ) {
				if ( trim( $item->comment_author_IP ) == trim( $target_ip ) ) {
					// ブロックしたいIP
					$error_type = "block_ip";
					return FALSE;
				}
			}
		}
		// IP制御 任意のIPアドレスをあればブロックする
		$block_ip_addresses = get_option( 'tsa_block_ip_addresses', "" );
		if ( $block_ip_addresses != NULL && $block_ip_addresses != "" ) {
			// 改行区切りの場合はカンマ区切りに文字列置換後リスト化
			$block_ip_addresses = str_replace("\n", ",", $block_ip_addresses);
			$ip_list = mb_split(  ",", $block_ip_addresses );
			foreach ( $ip_list as $ip ) {
				// 指定IPが範囲指定の場合 例：192.168.1.0/24
				if ( strpos( $ip, "/" ) != FALSE ) {
					if ( $this->inCIDR( $target_ip, $ip ) ) {
						// ブロックしたいIP
						$error_type = "block_ip";
						return FALSE;
					}
				} elseif ( trim( $ip ) == trim( $target_ip ) ) {
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
			$ip_net = ip2long($ip) >> $host << $host;   // 11000000101010000000000000000000
			return $net === $ip_net;
		} else {
			// 形式が不正ならば無視するためFALSE
			return FALSE;
		}
	}

	/**
	 * 日本語が含まれているかチェックメソッド
	 * @param string $comment
	 * @param string $author
	 */
	function validation( $comment, $author ) {
		global $newThrowsSpamAway;
		global $error_type;
		global $default_url_count_check_flg;    // URL数を制御するか初期設定値
		global $default_ok_url_count;   // 制限する場合のURL数初期設定値
		global $default_japanese_string_min_count; // 日本語文字最小含有数
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
				$flg = (intval(get_option('tsa_japanese_string_min_count', $default_japanese_string_min_count)) < $count_flg);
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
					if (preg_match('/'.trim($key)."/u", $author.$comment)) {
						$error_type = "ng_word";
						return FALSE;
					}
				}
			}
			// キーワードチェック（ブラックリスト）を抜けたら必須キーワードチェックを行う
			// キーワード文字列群　※ブラックリストと重複するものはブラックリストのほうが優先です。
			$must_keywords = get_option('tsa_must_keywords', "");
			if ($must_keywords != NULL && $must_keywords != "") {
				$keyword_list = mb_split(",", $must_keywords);
				foreach ($keyword_list as $key) {
					if (preg_match('/'.trim($key)."/u", $author.$comment)) {
						// OK
					} else {
						// 必須ワードがなかったためエラー
						$error_type = "must_word";
						return FALSE;
					}
				}
			}
			// URL数チェック
			$url_count_check = get_option('tsa_url_count_on_flg', $default_url_count_check_flg);
			// 許容URL数設定値
			$ok_url_count = intval(get_option('tsa_ok_url_count', $default_ok_url_count)); // デフォルト値３（３つまで許容）
			if ( $url_count_check != "2" ) {
				if ( substr_count( strtolower( $author.$comment ), 'http') > $ok_url_count) {
					// URL文字列（httpの数）が多いエラー
					$error_type = "url_count_over";
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
		$mincap="level_8";
		add_menu_page(__( 'Throws SPAM Away', $this->domain ), __( 'Throws SPAM Away', $this->domain ), $mincap, __FILE__, array( $this, 'options_page' ) );

		// 従来通りスパムデータ保存しない場合はスルーする
		if ( get_option( 'tsa_spam_data_save' ) != 1 ) {
			// N/A
		} else {
			// プラグインアップデート時もチェックするため常に・・・
			$this->tsa_createTbl(TRUE);
		}

	}

	/**
	 * Admin options page
	 */
	function options_page() {
		global $wpdb; // WordPress DBアクセス
		global $default_japanese_string_min_count;
		global $default_caution_msg;
		global $default_back_second;
		global $default_error_msg;
		global $default_ng_key_error_msg;
		global $default_must_key_error_msg;
		global $default_block_ip_address_error_msg;
		global $default_url_count_over_error_msg;
		global $default_ok_url_count;
		global $default_spam_data_save;
		?>
<style>
table.form-table { }
table.form-table th {
    width : 200px;
}
</style>
<script type="text/Javascript">
// 配列重複チェック
var isDuplicate = function(ary, str) {
 for (i = 0; i < ary.length; i++) {
        if(str == ary[i]) {
            return true;
        }
    }
    return false;
};
function addIpAddresses(newAddressStr) {
	// チェック用配列
	var test_newAddress_list = newAddressStr.split(",");
    var str = document.getElementById('tsa_block_ip_addresses').value;
	// 現在の配列（テスト用）
    str = str.replace(",", "\n");
	var test_oldAddress_list = str.split("\n");

    if (str.length > 0) { str += "\n"; }
    if (newAddressStr.length > 0) {
	    newAddressStr = newAddressStr.replace(",", "\n");
    }
    str += newAddressStr;
    str = str.replace(",", "\n");

    var ary = str.split("\n");
    var newAry = new Array;
    var ret = "";

    upd_flg = false;
    upd_ip_str = "";
    for ( var i=0; i < test_newAddress_list.length; i++) {
        if (!isDuplicate(test_oldAddress_list, test_newAddress_list[i])) {
            upd_flg = true;
            upd_ip_str = upd_ip_str + "・"+test_newAddress_list[i]+"\n";
        }
    }
    if (upd_flg == true) {

	    for( var i=0 ; i < ary.length ; i++ ) {
	        if( !isDuplicate(newAry, ary[i]) ){
	            newAry.push(ary[i]);
	        }
	    }
	    document.getElementById('tsa_block_ip_addresses').value = newAry.join('\n');
        alert('新たにIPアドレスを追加しました。\n'+upd_ip_str);
    } else {
        alert('指定されたIPアドレスは\nすでに追加されています。');
    }
	return false;
}
</script>
<div class="wrap">
    <h2>Throws SPAM Away設定</h2>
    <form method="post" action="options.php">
    <h3>スパム対策機能 設定</h3>
    <?php wp_nonce_field('update-options'); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">日本語が存在しない場合、無視対象とする<br />（日本語文字列が存在しない場合無視対象となります。）</th>
                <td><?php
                $chk_1 = "";
                $chk_2 = "";
                if (get_option('tsa_on_flg', "1") == "2") {
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
                    value="<?php echo get_option('tsa_japanese_string_min_count', $default_japanese_string_min_count); ?>" />
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">元の記事に戻ってくる時間<br />（秒）※0の場合エラー画面表示しません。</th>
                <td><input type="text" name="tsa_back_second"
                    value="<?php echo get_option('tsa_back_second', $default_back_second);?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">コメント欄の下に表示される注意文言</th>
                <td><input type="text" name="tsa_caution_message" size="80"
                    value="<?php echo get_option('tsa_caution_message', $default_caution_msg);?>" /><br />（初期設定:<?php echo $default_caution_msg;?>）</td>
            </tr>
            <tr valign="top">
                <th scope="row">日本語文字列規定値未満エラー時に表示される文言<br />（元の記事に戻ってくる時間の間のみ表示）</th>
                <td><input type="text" name="tsa_error_message" size="80"
                    value="<?php echo get_option('tsa_error_message', $default_error_msg);?>" /><br />（初期設定:<?php echo $default_error_msg;?>）</td>
            </tr>
        </table>
    <h3>URL文字列除外 設定</h3>
        <table class="form-table">
            <tr valign="top">
            <th scope="row">URLらしき文字列が混入している場合エラーとするか</th>
                <td><?php
                $chk_1 = "";
                $chk_2 = "";
                if (get_option('tsa_url_count_on_flg', "1") == "2") {
                    $chk_2 = " checked=\"checked\"";
                } else {
                    $chk_1 = " checked=\"checked\"";
                }
                 ?>
                 <label><input type="radio" name="tsa_url_count_on_flg" value="1"<?php echo $chk_1;?>/>&nbsp;する</label>&nbsp;
                 <label><input type="radio" name="tsa_url_count_on_flg" value="2"<?php echo $chk_2;?>/>&nbsp;しない</label><br />
                 する場合の制限数（入力数値まで許容）：<input type="text" name="tsa_ok_url_count" size="2"
                    value="<?php echo get_option('tsa_ok_url_count', $default_ok_url_count);?>" />
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">URLらしき文字列混入数オーバーエラー時に表示される文言
（元の記事に戻ってくる時間の間のみ表示）</th>
                <td><input type="text" name="tsa_url_count_over_error_message" size="80"
                    value="<?php echo get_option('tsa_url_count_over_error_message', $default_url_count_over_error_msg);?>" /><br />（初期設定:<?php echo $default_url_count_over_error_msg;?>）</td>
            </tr>
        </table>
    <h3>NGキーワード / 必須キーワード 制御設定</h3>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">その他NGキーワード<br />（日本語でも英語（その他）でもNGとしたいキーワードを半角カンマ区切りで複数設定できます。<br />挙動は同じです。NGキーワードだけでも使用できます。）</th>
                <td><input type="text" name="tsa_ng_keywords" size="80"
                    value="<?php echo get_option('tsa_ng_keywords', "");?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">NGキーワードエラー時に表示される文言<br />（元の記事に戻ってくる時間の間のみ表示）</th>
                <td><input type="text" name="tsa_ng_key_error_message" size="80"
                    value="<?php echo get_option('tsa_ng_key_error_message', $default_ng_key_error_msg);?>" /><br />（初期設定:<?php echo $default_ng_key_error_msg;?>）</td>
            </tr>
            <tr valign="top">
                <th scope="row">その上での必須キーワード<br />（日本語でも英語（その他）でも必須としたいキーワードを半角カンマ区切りで複数設定できます。<br />指定文字列を含まない場合はエラーとなります。※複数の方が厳しくなります。<br />必須キーワードだけでも使用できます。）</th>
                <td><input type="text" name="tsa_must_keywords" size="80"
                    value="<?php echo get_option('tsa_must_keywords', "");?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">必須キーワードエラー時に表示される文言<br />（元の記事に戻ってくる時間の間のみ表示）</th>
                <td><input type="text" name="tsa_must_key_error_message" size="80"
                    value="<?php echo get_option('tsa_must_key_error_message', $default_must_key_error_msg);?>" /><br />（初期設定:<?php echo $default_must_key_error_msg;?>）</td>
            </tr>
        </table>
    <h3>トラックバックへの対応設定</h3>
        <table class="form-table">
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
        </table>
    <h3>投稿IPアドレスによる制御設定</h3>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">WordPressのコメントで「スパム」にしたIPからの投稿にも採用する</th>
                <td><?php
                $chk = "";
                if (get_option('tsa_ip_block_from_spam_chk_flg', "") == "1") {
                    $chk = "checked=\"checked\"";
                } else {
                    $chk = "";
                }
                ?>
                <label><input type="checkbox" name="tsa_ip_block_from_spam_chk_flg" value="1"<?php echo $chk; ?>/>&nbsp;スパム投稿設定したIPアドレスからの投稿も無視する</label><br />
<?php
            // wp_commentsの　comment_approved　カラムが「spam」のIP_ADDRESSからの投稿は無視する
            $results = $wpdb->get_results("SELECT DISTINCT comment_author_IP FROM  $wpdb->comments WHERE comment_approved =  'spam' ORDER BY comment_author_IP ASC ");
?>現在「spam」フラグが付いているIPアドレス：<br /><blockquote><?php
            $add_ip_addresses = "";
            foreach ($results as $item) {
                $spam_ip = $item->comment_author_IP;
                // ブロックしたいIP
                if ( strlen( $add_ip_addresses ) > 0 ) {
                    $add_ip_addresses .= ",";
                }
                $add_ip_addresses .= $spam_ip;
?><b><?php echo $spam_ip; ?></b><br /><?php
            }
?>&nbsp;<input type="button" onclick="javascript:addIpAddresses('<?php echo $add_ip_addresses; ?>');" value="これらのIPアドレスを任意のブロック対象IPアドレスにコピーする" /><br />
            </blockquote>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">任意のIPアドレスからの投稿も無視したい場合、対象となるIPアドレスを記述してください。<br />改行区切りで複数設定できます。（半角数字とスラッシュ、ドットのみ）<br />※カンマは自動的に改行に変換されます</th>
                <td><textarea name="tsa_block_ip_addresses" id="tsa_block_ip_addresses" cols="80" rows="10"><?php echo get_option('tsa_block_ip_addresses', "");?></textarea></td>
            </tr>
            <tr valign="top">
            <th scope="row">ブロック対象のIPアドレスからの投稿時に表示される文言<br />（元の記事に戻ってくる時間の間のみ表示）</th>
            <td><input type="text" name="tsa_block_ip_address_error_message" size="80"
                    value="<?php echo get_option('tsa_block_ip_address_error_message', $default_block_ip_address_error_msg);?>" /><br />（初期設定：<?php echo $default_block_ip_address_error_msg; ?>）</td>
            </tr>
        </table>
    <h3>スパムデータベース</h3>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">スパムコメント投稿情報を保存しますか？</th>
                <td><?php
                $chk = "";
                if (get_option('tsa_spam_data_save', "") == "1") {
                    $chk = "checked=\"checked\"";
                } else {
                    $chk = "";
                }
                ?>
                <label><input type="checkbox" name="tsa_spam_data_save" value="1"<?php echo $chk; ?>/>&nbsp;スパムコメント情報を保存する</label><br />※Throws SPAM Away設定画面表示時に時間がかかることがあります。<br />※「保存する」を解除した場合でもテーブルは残りますので３０日以内の取得データは表示されます。
                </td>
            </tr>
        </table>

        <input type="hidden" name="action" value="update" /> <input
            type="hidden" name="page_options"
            value="tsa_on_flg,tsa_japanese_string_min_count,tsa_back_second,tsa_caution_message,tsa_error_message,tsa_ng_keywords,tsa_ng_key_error_message,tsa_must_keywords,tsa_must_key_error_message,tsa_tb_on_flg,tsa_tb_url_flg,tsa_block_ip_addresses,tsa_ip_block_from_spam_chk_flg,tsa_block_ip_address_error_message,tsa_url_count_on_flg,tsa_ok_url_count,tsa_url_count_over_error_message,tsa_spam_data_save" />
        <p class="submit">
            <input type="submit" class="button-primary"
                value="<?php _e('Save Changes') ?>" />
        </p>

<?php
if ( get_option( 'tsa_spam_data_save' ) == "1" ) {
// 日数
$gdays = 30;
// 表カラー
$unique_color="#114477";
$web_color="#3377B6";
?>
<h3>スパム投稿３０日間の推移</h3>
    <div class="clear"></div>

    <div class="clear" style="background-color:#efefef;">
<table style="width:100%;border:none;"><tr>
<?php
$total_qry = "
        SELECT count(ppd) as pageview, ppd
        FROM (select ip_address, SUBSTRING(post_date,1,10) as ppd from $this->table_name) as A
        GROUP BY ppd HAVING ppd >= '".gmdate('Y-m-d', current_time('timestamp')-86400*$gdays)."'
        ORDER BY pageview DESC
        LIMIT 1
    ";
$qry = $wpdb->get_row($total_qry);
$maxxday=$qry->pageview;

$total_vis = "
SELECT count(distinct ip_address) as vis, ppd
FROM (select ip_address, SUBSTRING(post_date,1,10) as ppd from $this->table_name) as B
GROUP BY ppd HAVING ppd >= '".gmdate('Y-m-d', current_time('timestamp')-86400*$gdays)."'
ORDER BY vis DESC
LIMIT 1
    ";
$qry_vis = $wpdb->get_row($total_vis);
$maxxday += $qry_vis->vis;

if($maxxday == 0) { $maxxday = 1; }

// Y
$gd=(100/$gdays).'%';
for($gg=$gdays-1;$gg>=0;$gg--)
{
// TOTAL SPAM COUNT
$visitor_qry = "
        SELECT count(DISTINCT ip_address) AS total
        FROM (select ip_address, SUBSTRING(post_date,1,10) as ppd from $this->table_name) as B
        WHERE ppd = '".gmdate('Y-m-d', current_time('timestamp')-86400*$gg)."'
        ";
$qry_visitors = $wpdb->get_row($visitor_qry);
        $px_visitors = round($qry_visitors->total*100/$maxxday);
        // TOTAL
$pageview_qry = "
SELECT count(ppd) as total
FROM (select ip_address, SUBSTRING(post_date,1,10) as ppd from $this->table_name) as C
WHERE ppd = '".gmdate('Y-m-d', current_time('timestamp')-86400*$gg)."'
        ";
$qry_pageviews = $wpdb->get_row($pageview_qry);
        $px_pageviews = round($qry_pageviews->total*100/$maxxday);
        $px_white = 100 - $px_pageviews - $px_visitors;
        if ($px_white < 0) { $px_white = 0; }

        print '<td width="'.$gd.'" valign="bottom"';
        if($start_of_week == gmdate('w',current_time('timestamp')-86400*$gg)) { print ' style="border-left:2px dotted gray;"'; }  # week-cut
        print "><div style='float:left;width:100%;font-family:Helvetica;font-size:7pt;text-align:center;border-right:1px solid white;color:black;'>
        <div style='background:#ffffff;width:100%;height:".$px_white."px;'></div>
        <div style='background:$unique_color;width:100%;height:".$px_visitors."px;' title='".$qry_visitors->total." ip_addresses'></div>
        <div style='background:$web_color;width:100%;height:".$px_pageviews."px;' title='".$qry_pageviews->total." spam comments'></div>
        <div style='background:gray;width:100%;height:1px;'></div>
        <br />".gmdate('d', current_time('timestamp')-86400*$gg) . ' ' . gmdate('M', current_time('timestamp')-86400*$gg) ."
		<div style='background:$ffffff;width:100%;height:2.2em;'>".$qry_visitors->total."<br />".$qry_pageviews->total."</div>
		<br clear=\"all\" /></div>
		</td>\n";
}
?>
    </tr></table>
    </div>
&nbsp;※&nbsp;数値は &lt;上段&gt;がSPAM投稿したユニークIPアドレス数、&nbsp;&lt;下段&gt;が破棄したスパム投稿数<br />
<div class="clear">
<?php
            // wp_tsa_spam の ip_address カラムに存在するIP_ADDRESS投稿は無視するか
            $results = $wpdb->get_results(
"SELECT count(ip_address) as cnt,ip_address FROM (select ip_address, SUBSTRING(post_date,1,10) as ppd from $this->table_name) as D
WHERE ppd >= '".gmdate('Y-m-d', current_time('timestamp')-86400*$gdays)."'
GROUP BY ip_address
ORDER BY cnt DESC"
);
?><h4>過去３０日間に無視投稿されたIPアドレス</h4>
<p>※「このIPアドレスを任意のブロック対象IPアドレスにコピーする」ボタンを押した場合は上の<b>「変更を保存」</b>をクリックし内容を保存してください。</p>
<p>※IPアドレスをクリックすると特定のホストが存在するか確認し存在する場合は表示されます。</p>
<?php if ( count( $results ) > 0 ) { ?>
<div style="height: 500px; overflow:auto;"><blockquote>
<table style="width:100%;border:1px #cccccc solid;border-collapse: collapse;"><?php
            foreach ($results as $item) {
                $spam_ip = $item->ip_address;
                $spam_cnt = $item->cnt;
                $p_url = WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__));
?><tr style="border:1px #cccccc solid;border-collapse: collapse;"><td><b><a href="javascript:void(0);" onclick="window.open('<?php echo $p_url; ?>hostbyip.php?ip=<?php echo $spam_ip; ?>', 'hostbyip', 'width=350,height=250,scrollbars=no,location=no,menubar=no,toolbar=no,directories=no,status=no');"><?php echo $spam_ip; ?></a></b></td><td><?php echo $spam_cnt; ?>回</td><td>&nbsp;<input type="button" onclick="javascript:addIpAddresses('<?php echo $spam_ip; ?>');" value="このIPアドレスを任意のブロック対象IPアドレスにコピーする" /></td></tr><?php
            }
?></table></blockquote></div>
<?php } ?>
</div>
<?php } ?>
</form>
<p>スパム投稿IPアドレスを参考にアクセス禁止対策を行なってください。</p>
<div class="clear"></div>

</div>

    <?php
    }

    function trackback_spam_away($tb) {
        global $newThrowsSpamAway;

        $tsa_tb_on_flg = get_option( 'tsa_tb_on_flg' );
        $tsa_tb_url_flg = get_option( 'tsa_tb_url_flg' );
        $siteurl = get_option('siteurl');
        // トラックバック OR ピンバック時にフィルタ発動
        if ( $tsa_tb_on_flg == "2" || ( $tb['comment_type'] != 'trackback' && $tb['comment_type'] != 'pingback' ) ) return $tb;

        // SPAMかどうかフラグ
        $tb_val['is_spam'] = FALSE;

        // コメント判定
        $author = $tb["comment_author"];
        $comment = $tb["comment_content"];
        // IP系の検査
        $ip = $_SERVER['REMOTE_ADDR'];
        if ( !$newThrowsSpamAway->ip_check( $ip ) ) {
            $tb_val['is_spam'] = TRUE;
        } else
        // 検査します！
        if ( !$newThrowsSpamAway->validation( $comment, $author ) ) {
            $tb_val['is_spam'] = TRUE;
        } else
        // URL検索する場合、URL包含検査 （このブログのURLを含んでない場合エラー
        if ( $tsa_tb_url_flg == "1" && stripos( $comment, $siteurl ) == FALSE ) {
            $tb_val['is_spam'] = TRUE;  // スパム扱い
        }
        // トラックバックスパムがなければ返却・あったら捨てちゃう
        if ( !$tb_val['is_spam'] ) {
            // トラックバック内に日本語存在（または禁止語句混入なし）
            return $tb;
        } else {
            die( 'Your Trackback Throws Away.' );
        }
    }

    /**
     * 当該IPアドレスからの最終投稿日時取得
     * @param string ip_address
     * @return 最終投稿日時 Y-m-d H:i:s
     */
    function get_last_spam_comment($ip_address = NULL) {
		global $wpdb;
		// IPアドレスがなければNULL返却
		if ( $ip_address == NULL ) {
			return NULL;
		}
		// 最終コメント情報取得
		$qry_str = "SELECT post_date, post_id FROM  $this->table_name WHERE ip_address = '".htmlspecialchars($ip_address)."' ORDER BY post_date DESC LIMIT 1 ";
		$results = $wpdb->get_results($qry_str);
		if ( count( $results ) > 0 ) {
			return $results[0];
		}
		return NULL;
	}
}
