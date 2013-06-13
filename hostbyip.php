<?php
/**
 * <p>ThrowsSpamAway</p> hostbyipページ
 * WordPress's Plugin
 * @author Takeshi Satoh@GTI Inc. 2013
 */
require_once 'throws_spam_away.class.php';
require_once dirname( __FILE__ ).'/../../../wp-load.php';
/**
 * ホスト検索
 */
$spam_ip = htmlspecialchars($_GET['ip']);
$newThrowsSpamAway = new ThrowsSpamAway(TRUE);
$last_spam_comment_result = $newThrowsSpamAway->get_last_spam_comment($spam_ip);
// 最終投稿日
$last_comment_date = $last_spam_comment_result->post_date;
$last_comment_post = get_permalink($last_spam_comment_result->post_id);
$last_comment_post_title = get_the_title(get_post($last_spam_comment_result->post_id));
?>
<!DOCTYPE html>
<!--[if IE 8]>
<html xmlns="http://www.w3.org/1999/xhtml" class="ie8 wp-toolbar"  lang="ja" prefix="og: http://ogp.me/ns#" >
<![endif]-->
<!--[if !(IE 8) ]><!-->
<html xmlns="http://www.w3.org/1999/xhtml" class="wp-toolbar"  lang="ja" >
<!--<![endif]-->
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Throws SPAM Away | Hostbyip[<?php echo htmlspecialchars($_GET['ip']); ?>]</title>
<script type="text/javascript">
window.onblur=function(){
	window.close();
}
</script>
</head>
<body>
<div style="textalign: center;">
<h2 style="background:#333; color:#fff;"><?php echo $spam_ip; ?></h2>
<?php
$spam_host = gethostbyaddr(htmlspecialchars($spam_ip));
if ($spam_host != $spam_ip) {
?>
<h3 style="background: #666;color: #fff;">特定のホスト情報が見つかりました。</h3>
↓↓↓<br />

<h4><?php echo $spam_host; ?></h4>
Whois: <a href="http://whois.arin.net/rest/ip/<?php echo $spam_ip; ?>" target="_blank"><?php echo $spam_ip; ?></a>
<?php
} else {
?>
<h3 style="background: #666;color: #fff;">このIPアドレスから特定のホスト情報は見つかりませんでした。</h3>
<?php
}
?>
<?php if ( $last_spam_comment_result != NULL ) { ?>
<div style="background: #999;color: #fff;margin:3px 0 0 0;">このIPからの最終投稿日時</div><?php echo $last_comment_date; ?><br />
<div style="background: #999;color: #fff;margin:3px 0 0 0;">このIPからスパム投稿対象となったページ</div><a href="<?php echo $last_comment_post; ?>" target="_blank"><?php echo $last_comment_post_title; ?></a><br />
<?php } ?>
<div style="text-align:right;"><a href="javascript:void(0);" onclick="window.close();">閉じる</a></div>
</div>
</body>
</html>