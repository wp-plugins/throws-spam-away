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
	var $version = '2.5';
	var $table_name = "";

	public function __construct($flg = FALSE) {
		global $default_spam_data_save;
		global $wpdb;
		// 接頭辞（wp_）を付けてテーブル名を設定
		$this->table_name = $wpdb->prefix . 'tsa_spam';
		if ($flg == FALSE) {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			global $default_spam_keep_day_count;
			global $lower_spam_keep_day_count;

			// 保存期間終了したデータ削除
			$skdc = intval( get_option('tsa_spam_keep_day_count', $default_spam_keep_day_count) );
			if ( $skdc < $lower_spam_keep_day_count ) { $skdc = $lower_spam_keep_day_count; }
			if ( get_option('tsa_spam_data_delete_flg', "") == "1" ) {
				// 期間 get_option('tsa_spam_keep_day_count') 日
				$wpdb->query(
						"DELETE FROM ".$this->table_name." WHERE post_date < '".gmdate('Y-m-d 23:59:59', current_time('timestamp')-86400 * $skdc)."'"
				);
			}
		}
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
		global $default_spam_data_save;

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
		global $default_spam_limit_over_interval_error_msg;
		global $error_type;

		// ログインしている場合は通過させます。
		if( $user_ID ) {
			return $id;
		}
		// コメント（comment）及び名前（author）の中も検査
		$author = $_POST["author"];
		$comment = $_POST["comment"];

		// チェック対象IPアドレス
		$ip = $_SERVER['REMOTE_ADDR'];

		// ホワイトリスト優先通過
		// IP制御 任意のIPアドレスをあればブロックする
		$white_ip_addresses = get_option( 'tsa_white_ip_addresses', "" );
		if ( $white_ip_addresses != NULL && $white_ip_addresses != "" ) {
			// 改行区切りの場合はカンマ区切りに文字列置換後リスト化
			$white_ip_addresses = str_replace("\n", ",", $white_ip_addresses);
			$ip_list = mb_split( ",", $white_ip_addresses );
			foreach ( $ip_list as $_ip ) {
				// 指定IPが範囲指定の場合 例：192.168.1.0/24
				if ( strpos( $_ip, "/" ) != FALSE ) {
					if ( $this->inCIDR( $ip, $_ip ) ) {
						// 通過対象
						return $id;
					}
				} elseif ( trim( $_ip ) == trim( $ip ) ) {
					// 通過対象
					return $id;
				}
			}
		}
		// IP系の検査
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
			case "spam_limit_over" :
				$error_msg = get_option( 'tsa_spam_limit_over_interval_error_message', $default_spam_limit_over_interval_error_msg );
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
		// スパムフィルター利用あれば始めに通す
		// １．スパムちゃんぷるー
		$spam_filter_spam_champuru_flg = get_option( 'tsa_spam_champuru_flg' );
		if ( get_option( 'tsa_spam_champuru_flg', "" ) == "1" ) {
			return $this->rejectSpamIP( $target_ip );
		}
		// ２．以降あれば追加

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
	 * スパムちゃんぷるー利用ブロック
	 */
	function rejectSpamIP( $ip ) {
		global $spam_champuru_host;

		$spam_IP  = '127.0.0.2';
		$host     = $spam_champuru_host;
		$pattern  = '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/';
		$check_IP = trim(preg_match($pattern, $ip) ? $ip : $_SERVER['REMOTE_ADDR']);
		$spam     = false;
		if (preg_match($pattern, $check_IP)) {
			$host = implode('.',array_reverse(split('\.',$check_IP))) . '.' . $host;
			if (function_exists('dns_get_record')) {
				$check_recs = dns_get_record($host, DNS_A);
				if (isset($check_recs[0]['ip'])) $spam = ($check_recs[0]['ip'] === $spam_IP);
				unset($check_recs);
			} elseif (function_exists('gethostbyname')) {
				$checked = (gethostbyname($host) === $spam_IP);
			} elseif (class_exists('Net_DNS_Resolver')) {
				$resolver = new Net_DNS_Resolver();
				$response = $resolver->query($host, 'A');
				if ($response) {
					foreach ($response->answer as $rr) {
						if ($rr->type === 'A') {
							$spam = ($rr->address === $spam_IP);
							break;
						}
					}
				}
				unset($response);
				unset($resolver);
			} elseif (function_exists('checkdnsrr')) {
				$spam = (checkdnsrr($host, "A") === true);
			}
		}
		if ($spam) {
			$error_type = "block_ip";
			return FALSE;
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

		// Throws SPAM Away 起動フラグ "1":起動 "2":オフ
		$tsa_on_flg = get_option( 'tsa_on_flg', "" );

		// 一定時間制限チェック
		// 一定時間内スパム認定機能<br />○分以内に○回スパムとなったら○分間、当該IPからのコメントはスパム扱いする設定+スパム情報保存

		// ○分以内に○回スパムとなったら○分間そのIPからのコメントははじくかの設定
		//$default_spam_limit_flg = 2;	// 1:する 2:しない ※スパム情報保存がデフォルトではないのでこちらも基本はしない方向です。
		// ※スパム情報保存していないと機能しません。
		//$default_spam_limit_minutes = 60;		// ６０分（１時間）以内に・・・
		//$default_spam_limit_count = 2;			// ２回までは許そうか。
		//$default_spam_limit_over_interval = 60;	// だがそれを超えたら（デフォルト３回目以降）60分はOKコメントでもスパム扱いするんでよろしく！
		// tsa_spam_limit_flg,tsa_spam_limit_minutes,tsa_spam_limit_count,tsa_spam_limit_over_interval,tsa_spam_limit_over_interval_error_message

		// スパム情報保存フラグ
		$tsa_spam_data_save = get_option( 'tsa_spam_data_save' );
		// 一定時間制限チェック
		$tsa_spam_limit_flg = get_option( 'tsa_spam_limit_flg', "" );
		if ( $tsa_spam_data_save == "1" && $tsa_spam_limit_flg == "1" ) {
			global $default_spam_limit_minutes;
			global $default_spam_limit_over_interval;
			global $default_spam_limit_count;
			global $wpdb;
			$tsa_spam_limit_minutes = intval(get_option( 'tsa_spam_limit_minutes', $default_spam_limit_minutes ) );
			$tsa_spam_limit_over_interval = intval(get_option( 'tsa_spam_limit_over_interval', $default_spam_limit_over_interval ) );
			// ○分以内（インターバルの方が長い場合はインターバル値を利用する）の同一IPからのスパム投稿回数を調べる
			$interval_minutes = ($tsa_spam_limit_minutes >= $tsa_spam_limit_over_interval ? $tsa_spam_limit_minutes : $tsa_spam_limit_over_interval );

			// 上記が○回を超えているかチェック
			$ip = htmlspecialchars($_SERVER['REMOTE_ADDR']);
			$this_ip_spam_cnt = "
			SELECT ip_address, count(ppd) as spam_count, max(post_date)
			FROM (select ip_address, post_date as ppd, post_date from $this->table_name) as A
			WHERE A.ip_address = '".$ip."' AND
					 ppd >= '".gmdate('Y-m-d H:i:s', current_time('timestamp')-60 * $interval_minutes)."'
			GROUP BY ip_address LIMIT 1";
			$query = $wpdb->get_row( $this_ip_spam_cnt );
			$spam_count = intval($query->spam_count);


			// 最後のスパム投稿から○分超えていなければ　アウト！！
			$tsa_spam_limit_count = intval( get_option( 'tsa_spam_limit_count', $default_spam_limit_count ) );
			if ( $spam_count > $tsa_spam_limit_count ) {
				// アウト！
				$error_type = "spam_limit_over";
				return FALSE;
			}
		}
		// まずはシングルバイトだけならエラー
		if ($tsa_on_flg != "2" && strlen( bin2hex( $comment ) ) / 2 == mb_strlen( $comment ) ) {
			return FALSE;
		} else {
			// 日本語文字列必須含有数
			$tsa_japanese_string_min_count = intval( get_option( 'tsa_japanese_string_min_count', $default_japanese_string_min_count ) );
			// NGキーワード文字列群
			$tsa_ng_keywords = get_option( 'tsa_ng_keywords', "" );
			// キーワード文字列群　※ブラックリストと重複するものはブラックリストのほうが優先です。
			$tsa_must_keywords = get_option( 'tsa_must_keywords', "" );
			// URL数チェック
			$tsa_url_count_check = get_option( 'tsa_url_count_on_flg', $default_url_count_check_flg );
			// 許容URL数設定値
			$tsa_ok_url_count = intval( get_option( 'tsa_ok_url_count', $default_ok_url_count ) ); // デフォルト値３（３つまで許容）

			// OKフラグ
			$flg = FALSE;
			// マルチバイト文字が含まれている場合は日本語が含まれていればOK
			if ($tsa_on_flg != "2") {
				$count_flg = 0;
				mb_regex_encoding('UTF-8');
				$com_split = $newThrowsSpamAway->mb_str_split($comment);
				foreach ($com_split as $it) {
					if (preg_match('/[一-龠]+/u', $it)){
						$count_flg += 1;
					}
					if (preg_match('/[ァ-ヶー]+/u', $it)){
						$count_flg += 1;
					}
					if (preg_match('/[ぁ-ん]+/u', $it)){
						$count_flg += 1;
					}
				}
				$flg = ($tsa_japanese_string_min_count < $count_flg);
				if ($flg == FALSE) {
					return FALSE;
				}
			}
			// 日本語文字列チェック抜けたらキーワードチェックを行う
			if ( $tsa_ng_keywords != "" ) {
				$keyword_list = mb_split( ",", $tsa_ng_keywords );
				foreach ( $keyword_list as $key ) {
					if ( preg_match('/'.trim($key)."/u", $author.$comment) ) {
						$error_type = "ng_word";
						return FALSE;
					}
				}
			}
			// キーワードチェック（ブラックリスト）を抜けたら必須キーワードチェックを行う
			if ( $tsa_must_keywords != "" ) {
				$keyword_list = mb_split( ",", $tsa_must_keywords );
				foreach ( $keyword_list as $key ) {
					if ( preg_match( '/'.trim($key)."/u", $author.$comment ) ) {
						// OK
					} else {
						// 必須ワードがなかったためエラー
						$error_type = "must_word";
						return FALSE;
					}
				}
			}
			// 含有URL数チェック
			if ( $tsa_url_count_check != "2" ) {
				if ( substr_count( strtolower( $author.$comment ), 'http') > $tsa_ok_url_count) {
					// URL文字列（httpの数）が多いエラー
					$error_type = "url_count_over";
					return FALSE;
				}
			}

			return TRUE;
		}
	}

	function mb_str_split( $string ) {
		return preg_split( '/(?<!^)(?!$)/u', $string );
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

		global $default_spam_limit_minutes;
		global $default_spam_limit_count;
		global $default_spam_limit_over_interval;
		global $default_spam_limit_over_interval_error_msg;

		global $default_spam_display_day_count;

		global $default_spam_keep_day_count;

		// 設定完了の場合はメッセージ表示
		$_saved = FALSE;
		// スパム情報から 特定IPアドレス削除
		if ( $_POST['act'] != NULL && $_POST['act'] == "remove_ip" ) {
			$remove_ip_address = @htmlspecialchars($_POST['ip_address']);
			if ( !isset($remove_ip_address) || strlen($remove_ip_address) == 0 ) {
				// N/A
			} else {
				// スパムデータベースから特定IP情報削除
				$wpdb->query(
					"DELETE FROM ".$this->table_name." WHERE ip_address = '".$remove_ip_address."' "
				);
				$_saved = TRUE;
			}
		}
		if ( $_GET['settings-updated'] == "true" ) {
			$_saved = TRUE;
		}
		?>
<style>
table.form-table {

}

table.form-table th {
	width: 200px;
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
    str = str.replace(/\,/g, "\n");
	var test_oldAddress_list = str.split("\n");

    if (str.length > 0) { str += "\n"; }
    if (newAddressStr.length > 0) {
	    newAddressStr = newAddressStr.replace(/\,/g, "\n");
    }
    str += newAddressStr;
    str = str.replace(/\,/g, "\n");

    var ary = str.split("\n");
    var newAry = new Array;
    var ret = "";

    upd_flg = false;
    upd_ip_str = "";
    for ( var i=0; i < test_newAddress_list.length; i++) {
        if (!isDuplicate(test_oldAddress_list, test_newAddress_list[i]) && test_newAddress_list[i] != "") {
            upd_flg = true;
            upd_ip_str = upd_ip_str + "・"+test_newAddress_list[i]+"\n";
        }
    }
    if (upd_flg == true) {

	    for( var i=0 ; i < ary.length ; i++ ) {
	        if( !isDuplicate(newAry, ary[i]) && ary[i] != ""){
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
function removeIpAddressOnData(ipAddressStr) {
	if (confirm('['+ipAddressStr+'] をスパムデータベースから削除します。よろしいですか？この操作は取り消せません')) {
		jQuery('#remove_ip_address').val(ipAddressStr);
		jQuery('#remove').submit();
	} else {
		return false;
	}
}
</script>
<div class="wrap">
	<h2>Throws SPAM Away設定</h2>
	<?php if ($_saved) { ?>
	<div class="updated" style="padding: 10px; width: 50%;" id="message">設定の更新が完了しました。</div>
	<?php } ?>
	<form method="post" action="options.php">
		<h3>スパム対策機能 設定</h3>
		<?php wp_nonce_field('update-options'); ?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">日本語が存在しない場合、無視対象とする<br />（日本語文字列が存在しない場合無視対象となります。）
				</th>
				<td><?php
				$chk_1 = "";
				$chk_2 = "";
				if (get_option('tsa_on_flg', "1") == "2") {
                    $chk_2 = " checked=\"checked\"";
                } else {
                    $chk_1 = " checked=\"checked\"";
                }
                ?> <label><input type="radio" name="tsa_on_flg"
						value="1" <?php echo $chk_1;?> />&nbsp;する</label>&nbsp; <label><input
						type="radio" name="tsa_on_flg" value="2" <?php echo $chk_2;?> />&nbsp;しない</label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">日本語文字列含有数<br />（この文字列に達していない場合無視対象となります。）
				</th>
				<td><input type="text" name="tsa_japanese_string_min_count"
					value="<?php echo get_option('tsa_japanese_string_min_count', $default_japanese_string_min_count); ?>" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">元の記事に戻ってくる時間<br />（秒）※0の場合エラー画面表示しません。
				</th>
				<td><input type="text" name="tsa_back_second"
					value="<?php echo get_option('tsa_back_second', $default_back_second);?>" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">コメント欄の下に表示される注意文言</th>
				<td><input type="text" name="tsa_caution_message" size="80"
					value="<?php echo get_option('tsa_caution_message', $default_caution_msg);?>" /><br />（初期設定:<?php echo $default_caution_msg;?>）</td>
			</tr>
			<tr valign="top">
				<th scope="row">日本語文字列規定値未満エラー時に表示される文言<br />（元の記事に戻ってくる時間の間のみ表示）
				</th>
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
                ?> <label><input type="radio"
						name="tsa_url_count_on_flg" value="1" <?php echo $chk_1;?> />&nbsp;する</label>&nbsp;
					<label><input type="radio" name="tsa_url_count_on_flg" value="2"
					<?php echo $chk_2;?> />&nbsp;しない</label><br /> する場合の制限数（入力数値まで許容）：<input
					type="text" name="tsa_ok_url_count" size="2"
					value="<?php echo get_option('tsa_ok_url_count', $default_ok_url_count);?>" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">URLらしき文字列混入数オーバーエラー時に表示される文言 （元の記事に戻ってくる時間の間のみ表示）</th>
				<td><input type="text" name="tsa_url_count_over_error_message"
					size="80"
					value="<?php echo get_option('tsa_url_count_over_error_message', $default_url_count_over_error_msg);?>" /><br />（初期設定:<?php echo $default_url_count_over_error_msg;?>）</td>
			</tr>
		</table>
		<h3>NGキーワード / 必須キーワード 制御設定</h3>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">その他NGキーワード<br />（日本語でも英語（その他）でもNGとしたいキーワードを半角カンマ区切りで複数設定できます。<br />挙動は同じです。NGキーワードだけでも使用できます。）
				</th>
				<td><input type="text" name="tsa_ng_keywords" size="80"
					value="<?php echo get_option('tsa_ng_keywords', "");?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">NGキーワードエラー時に表示される文言<br />（元の記事に戻ってくる時間の間のみ表示）
				</th>
				<td><input type="text" name="tsa_ng_key_error_message" size="80"
					value="<?php echo get_option('tsa_ng_key_error_message', $default_ng_key_error_msg);?>" /><br />（初期設定:<?php echo $default_ng_key_error_msg;?>）</td>
			</tr>
			<tr valign="top">
				<th scope="row">その上での必須キーワード<br />（日本語でも英語（その他）でも必須としたいキーワードを半角カンマ区切りで複数設定できます。<br />指定文字列を含まない場合はエラーとなります。※複数の方が厳しくなります。<br />必須キーワードだけでも使用できます。）
				</th>
				<td><input type="text" name="tsa_must_keywords" size="80"
					value="<?php echo get_option('tsa_must_keywords', "");?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">必須キーワードエラー時に表示される文言<br />（元の記事に戻ってくる時間の間のみ表示）
				</th>
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
                ?> <label><input type="radio" name="tsa_tb_on_flg"
						value="1" <?php echo $chk_1;?> />&nbsp;する</label>&nbsp; <label><input
						type="radio" name="tsa_tb_on_flg" value="2" <?php echo $chk_2;?> />&nbsp;しない</label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">トラックバック記事にも採用する場合、ついでにこちらのURLが含まれているか判断する<br />（初期設定:「しない」）
				</th>
				<td><?php
				$chk_1 = "";
				$chk_2 = "";
				if (get_option('tsa_tb_url_flg', "2") == "2") {
                    $chk_2 = " checked=\"checked\"";
                } else {
                    $chk_1 = " checked=\"checked\"";
                }
                ?> <label><input type="radio" name="tsa_tb_url_flg"
						value="1" <?php echo $chk_1;?> />&nbsp;する</label>&nbsp; <label><input
						type="radio" name="tsa_tb_url_flg" value="2" <?php echo $chk_2;?> />&nbsp;しない</label>
				</td>
			</tr>
		</table>
		<h3>投稿IPアドレスによる制御設定</h3>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">SPAMブラックリスト利用</th>
				<td><?php
				$chk = "";
				if (get_option('tsa_spam_champuru_flg', "") == "1" ) {
					$chk = "checked=\"checked\"";
				}
				?>
					<label><input type="checkbox" name="tsa_spam_champuru_flg" value="1" <?php echo $chk; ?> /><a href="http://spam-champuru.livedoor.com/dnsbl/">スパムちゃんぷるーDNSBL</a>に登録されているIPアドレスからのコメントを拒否する</label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">WordPressのコメントで「スパム」にしたIPからの投稿にも採用する</th>
				<td><?php
				$chk = "";
				if (get_option('tsa_ip_block_from_spam_chk_flg', "") == "1") {
                    $chk = "checked=\"checked\"";
                }
                ?> <label><input type="checkbox"
						name="tsa_ip_block_from_spam_chk_flg" value="1"
						<?php echo $chk; ?> />&nbsp;スパム投稿設定したIPアドレスからの投稿も無視する</label>&nbsp;※Akismet等で自動的にスパムマークされたものも含む<br />
					<?php
					// wp_commentsの　comment_approved　カラムが「spam」のIP_ADDRESSからの投稿は無視する
					$results = $wpdb->get_results("SELECT DISTINCT comment_author_IP FROM  $wpdb->comments WHERE comment_approved =  'spam' ORDER BY comment_author_IP ASC ");
					?>現在「spam」フラグが付いているIPアドレス：<br />
					<blockquote>
						<?php
						$add_ip_addresses = "";
						foreach ($results as $item) {
                $spam_ip = $item->comment_author_IP;
                // ブロックしたいIP
                if ( strlen( $add_ip_addresses ) > 0 ) {
                    $add_ip_addresses .= ",";
                }
                $add_ip_addresses .= $spam_ip;
                ?>
						<b><?php echo $spam_ip; ?> </b><br />
						<?php
            }
            ?>
						&nbsp;<input type="button"
							onclick="javascript:addIpAddresses('<?php echo $add_ip_addresses; ?>');"
							value="これらのIPアドレスを任意のブロック対象IPアドレスにコピーする" /><br />
					</blockquote>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">任意のIPアドレスからの投稿も無視したい場合、対象となるIPアドレスを記述してください。<br />改行区切りで複数設定できます。（半角数字とスラッシュ、ドットのみ）<br />※カンマは自動的に改行に変換されます
				</th>
				<td><textarea name="tsa_block_ip_addresses"
						id="tsa_block_ip_addresses" cols="80" rows="10"><?php echo get_option('tsa_block_ip_addresses', "");?></textarea></td>
			</tr>
			<tr valign="top">
				<th scope="row">ブロック対象のIPアドレスからの投稿時に表示される文言<br />（元の記事に戻ってくる時間の間のみ表示）
				</th>
				<td><input type="text" name="tsa_block_ip_address_error_message"
					size="80"
					value="<?php echo get_option('tsa_block_ip_address_error_message', $default_block_ip_address_error_msg);?>" /><br />（初期設定：<?php echo $default_block_ip_address_error_msg; ?>）</td>
			</tr>

			<tr style="background-color: #efefef;"><td colspan="2"><hr />※上記のスパムチェックから除外するIPアドレスがあれば下記に設定してください。優先的に通過させます。<br />※トラックバックは優先通過ではありません。</td></tr>
			<tr style="background-color: #efefef;" valign="top">
				<th scope="row"><strong>IP制御免除<br />ホワイトリスト</strong><br />※ここに登録したIPアドレスはスパムフィルタを掛けず優先的に通します。<br />※日本語以外の言語でご利用になられるお客様のIPアドレスを登録するなどご利用ください。<br />改行区切りで複数設定できます。範囲指定も可能です。（半角数字とスラッシュ、ドットのみ）</th>
				<td>
					<textarea name="tsa_white_ip_addresses"
						id="tsa_white_ip_addresses" cols="80" rows="10"><?php echo get_option('tsa_white_ip_addresses', "");?></textarea>
				</td>
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
                }
                ?> <label><input type="checkbox"
						name="tsa_spam_data_save" value="1" <?php echo $chk; ?> />&nbsp;スパムコメント情報を保存する</label><br />※Throws
					SPAM Away設定画面表示時に時間がかかることがあります。<br />※「保存する」を解除した場合でもテーブルは残りますので<?php echo get_option('tsa_spam_keep_day_count', $default_spam_keep_day_count); ?>日以内の取得データは表示されます。
				</td>
			</tr>
			<tr>
				<th scope="row">スパムデータを表示する期間</th>
				<td>
					<input
					type="text" name="tsa_spam_keep_day_count" size="3"
					value="<?php echo get_option('tsa_spam_keep_day_count', $default_spam_keep_day_count); ?>" />日分（最低７日）&nbsp;
					<?php
						$chk = "";
						if (get_option('tsa_spam_data_delete_flg', "") == "1") {
							$chk = "checked=\"checked\"";
						}
					?>
					<label><input type="checkbox" name="tsa_spam_data_delete_flg" value="1"
				<?php echo $chk; ?> />&nbsp;期間が過ぎたデータを削除する</label><br />
				※一度消したデータは復活出来ませんのでご注意ください。また最低７日分は保存されます。
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" colspan="2">一定時間内スパム認定機能<br />○分以内に○回スパムとなったら○分間、当該IPからのコメントはスパム扱いする設定<br />
					<b>※一定時間以内にスパム投稿された回数を測定していますので「スパムコメント情報を保存する」機能がオフの場合は機能しません。</b>
				</th>
			</tr>
			<tr>
				<th scope="row">機能設定</th>
				<td><?php
				$chk = "";
				if (get_option('tsa_spam_limit_flg', "") == "1") {
					$chk = "checked=\"checked\"";
				}
				?> <label><input type="checkbox" name="tsa_spam_limit_flg" value="1"
				<?php echo $chk; ?> />&nbsp;機能させる</label><br /> 一定時間:<input
					type="text" name="tsa_spam_limit_minutes" size="3"
					value="<?php echo get_option('tsa_spam_limit_minutes', $default_spam_limit_minutes); ?>" />分以内に
					一定回数:<input type="text" name="tsa_spam_limit_count" size="3"
					value="<?php echo get_option('tsa_spam_limit_count', $default_spam_limit_count); ?>" />回スパムとなったら<b>次から</b>
					一定時間:<input type="text" name="tsa_spam_limit_over_interval" size="3"
					value="<?php echo get_option('tsa_spam_limit_over_interval', $default_spam_limit_over_interval); ?>" />分間<br />
					当該IPアドレスからのコメントを強制スパム扱いします。<br /> エラーメッセージは：<input type="text"
					name="tsa_spam_limit_over_interval_error_message" size="80"
					value="<?php echo get_option('tsa_spam_limit_over_interval_error_message', $default_spam_limit_over_interval_error_msg); ?>" /><br />
					（初期設定：<?php echo $default_spam_limit_over_interval_error_msg; ?>）
				</td>
			</tr>
		</table>

		<input type="hidden" name="action" value="update" /> <input
			type="hidden" name="page_options"
			value="tsa_on_flg,tsa_japanese_string_min_count,tsa_back_second,tsa_caution_message,tsa_error_message,tsa_ng_keywords,tsa_ng_key_error_message,tsa_must_keywords,tsa_must_key_error_message,tsa_tb_on_flg,tsa_tb_url_flg,tsa_block_ip_addresses,tsa_ip_block_from_spam_chk_flg,tsa_block_ip_address_error_message,tsa_url_count_on_flg,tsa_ok_url_count,tsa_url_count_over_error_message,tsa_spam_data_save,tsa_spam_limit_flg,tsa_spam_limit_minutes,tsa_spam_limit_count,tsa_spam_limit_over_interval,tsa_spam_limit_over_interval_error_message,tsa_spam_champuru_flg,tsa_spam_keep_day_count,tsa_spam_data_delete_flg,tsa_white_ip_addresses" />
		<p class="submit">
			<input type="submit" class="button-primary"
				value="<?php _e('Save Changes') ?>" />
		</p>

		<?php
		if ( get_option( 'tsa_spam_data_save' ) == "1" ) {
// 日数
$gdays = get_option( 'tsa_spam_keep_day_count', $default_spam_keep_day_count);
if ( $gdays < $lower_spam_keep_day_count ) { $gdays = $lower_spam_keep_day_count; }
// 表カラー
$unique_color="#114477";
$web_color="#3377B6";
?>
		<h3>スパム投稿<?php echo $gdays; ?>日間の推移</h3>
		<div class="clear"></div>

		<div class="clear" style="background-color: #efefef;">
			<table style="width: 100%; border: none;">
				<tr>
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
					GROUP BY ppd HAVING ppd >= '" . gmdate( 'Y-m-d', current_time( 'timestamp' ) - 86400 * $gdays ) . "'
				ORDER BY vis DESC
				LIMIT 1
				";
					$qry_vis = $wpdb->get_row($total_vis);
					$maxxday += $qry_vis->vis;

					if ( $maxxday == 0 ) {
$maxxday = 1;
}

// Y
$gd = ( 100 / $gdays ).'%';
for ( $gg = $gdays - 1; $gg >= 0; $gg-- ) {
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
	if ($px_white < 0) {
$px_white = 0;
}

print '<td width="'.$gd.'" valign="bottom"';
if($start_of_week == gmdate('w',current_time('timestamp')-86400*$gg)) {
print ' style="border-left:2px dotted gray;"';
}  # week-cut
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
				</tr>
			</table>
		</div>
		&nbsp;※&nbsp;数値は
		&lt;上段&gt;がSPAM投稿したユニークIPアドレス数、&nbsp;&lt;下段&gt;が破棄したスパム投稿数<br />
		<div class="clear">
<?php
			// wp_tsa_spam の ip_address カラムに存在するIP_ADDRESS投稿は無視するか
			$results = $wpdb->get_results(
"SELECT count(ip_address) as cnt,ip_address,max(ppd) as post_date FROM (select ip_address, post_date as ppd from $this->table_name) as D
WHERE ppd >= '". gmdate( 'Y-m-d', current_time( 'timestamp' ) - 86400 * $gdays )."'
GROUP BY ip_address
ORDER BY cnt DESC"
);
?>
			<h4>
				過去
				<?php echo $gdays; ?>
				日間に無視投稿されたIPアドレス
			</h4>
			<p>
				※「このIPアドレスを任意のブロック対象IPアドレスにコピーする」ボタンを押した場合は上の<b>「変更を保存」</b>をクリックし内容を保存してください。
			</p>
			<p>※IPアドレスをクリックすると特定のホストが存在するか確認し存在する場合は表示されます。</p>
			<p>「スパムデータから削除する」ボタンを押しますと該当IPアドレスのスパム投稿データが削除されます。テストしたあとの削除などに使用してください。</p>
			<?php if ( count( $results ) > 0 ) {
				$p_url = WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__));
				wp_enqueue_script("jquery.tablesorter", $p_url."js/jquery.tablesorter.min.js", array('jquery'), FALSE);
				wp_enqueue_style("jquery.tablesorter", $p_url."images/style.css");
				?>
<style type="text/css">
<!--
/** ------ lightbox風 ----- */
#kotak-dialog {
  position:absolute;
  top:20%;
  left:50%;
  margin:0px 0px 0px -200px;
  width:400px;
  height:auto;
  background-color:#fff;
  -webkit-box-shadow:0px 1px 2px rgba(0,0,0,0.4);
  -moz-box-shadow:0px 1px 2px rgba(0,0,0,0.4);
  box-shadow:0px 1px 2px rgba(0,0,0,0.4);
  z-index:1000;
  display:none;
}

#kotak-dialog *:focus {
  outline:none;
}

#kotak-dialog h3.title {
  background-color:#3B5998;
  padding:10px 15px;
  color:#fff;
  font:normal 16px Arial,Sans-Serif;
  margin:0px 0px 0px 0px;
  position:relative;
}

#kotak-dialog h3.title a {
  position:absolute;
  top:10px;
  right:15px;
  color:#fff;
  text-decoration:none;
  cursor:pointer;
}

#kotak-dialog .isi-dialog {
  margin:15px;
  font:normal 12px Arial,Sans-Serif;
}

#kotak-dialog .button-wrapper {
  padding:10px 15px 0px;
  border-top:1px solid #ddd;
  margin-top:15px;
}

#kotak-dialog .button-wrapper button {
  background-color:#FF0C39;
  border:none;
  font:bold 12px Arial,Sans-Serif;
  color:#fff;
  padding:5px 10px;
  -webkit-border-radius:3px;
  -moz-border-radius:3px;
  border-radius:3px;
  cursor:pointer;
}

#kotak-dialog .button-wrapper button:hover {
  background-color:#aaa;
}

#dialog-overlay {
  position:fixed !important;
  position:absolute;
  z-index:999;
  top:0px;
  right:0px;
  bottom:0px;
  left:0px;
  background-color:#000;
  display:none;
}


/* Iframe */
#iframeContainer iframe {
  width:100%;
  height:300px;
  border:none;
  background-color:#ccc;
  overflow:auto;
}
/** ------ /lightbox風 ----- */

/** スクロール対象テーブルCSS */
#spam_list {
	background-color: #ffffff;
 	border-collapse:;
 	font-size: 1em !important;
}
/** 全体container */
#spam_list_container {
	position: relative;
	padding-top: 26px;
	width: 826px; /* 列幅合計＋セル間の幅(2px)の合計＋20px */
	border: 1px solid #3377b6;
	background-color: #ffffff;
}
/** tbody スクロール対象 */
#spam_list_div {
	overflow: auto;
	height: 500px;
}

#spam_list thead tr {
	position: absolute;
	top: 0;
	left: 0;
	background-color: #ffffff;
}
#spam_list thead tr th {
	background-color: #3377b6;
	color: #fff;
	padding: 3px 0px;
}
#spam_list tbody tr td {
	background-color: #efefef;
	color: black;
	padding: 3px 6px;
}
#spam_list tbody tr.odd td {
	background-color:#F0F0F6;
}

.cols0 { width: 200px; }
.cols1 { width: 100px; }
.cols2 { width: 100px; }
.cols3 { width: 400px; }

-->
</style>
<script type="text/JavaScript">
<!--
jQuery(function() {
	jQuery('#spam_list').tablesorter({
		  widgets: ['zebra'],
		  headers: {
			0: { id: "ipAddress" },
			1: { sorter: "digit" },
			2: { sorter: "shortDate" },
			3: { sorter: false }
			}
		});

	});
-->
</script>
	<div id="spam_list_container">
		<div id="spam_list_div">
			<table id="spam_list" class="tablesorter">
				<colgroup class="cols0"></colgroup>
				<colgroup class="cols1"></colgroup>
				<colgroup class="cols2"></colgroup>
				<colgroup class="cols3"></colgroup>
				<thead>
					<tr>
						<th class="cols0">IPアドレス</th>
						<th class="cols1">投稿数</th>
						<th class="cols2">最終投稿日時</th>
						<th class="cols3">スパムIP登録</th>
					</tr>
				</thead>
				<tbody>
<?php
	foreach ($results as $item) {
		$spam_ip = $item->ip_address;
		$spam_cnt = $item->cnt;
		$last_post_date = $item->post_date;
?>
					<tr>
						<td>
							<b><a href="javascript:void(0);"
									onclick="window.open('<?php echo $p_url; ?>hostbyip.php?ip=<?php echo $spam_ip; ?>', 'hostbyip', 'width=350,height=300,scrollbars=no,location=no,menubar=no,toolbar=no,directories=no,status=no');"><?php echo $spam_ip; ?>
							</a></b><br clear="all" />
							<input type="button"
							onclick="javascript:removeIpAddressOnData('<?php echo $spam_ip; ?>');"
							value="スパムデータから削除する" />
						</td>
						<td><?php echo $spam_cnt; ?>回</td>
						<td><?php echo $last_post_date; ?></td>
						<td><input type="button"
							onclick="javascript:addIpAddresses('<?php echo $spam_ip; ?>');"
							value="ブロック対象IPアドレスにコピー[<?php echo $spam_ip; ?>]" /></td>
					</tr>
<?php
	}
?>
				</tbody>
			</table>
<?php } ?>
		</div>
	</div>
</div>
<?php } ?>
	</form>
	<form method="post" id="remove">
		<input type="hidden" name="ip_address" id="remove_ip_address" value="" />
		<input type="hidden" name="act" value="remove_ip" />
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
