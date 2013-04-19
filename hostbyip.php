<?php
$spam_ip = htmlspecialchars($_GET['ip']);
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
<?php
$spam_host = gethostbyaddr(htmlspecialchars($spam_ip));
if ($spam_host != $spam_ip) {
?>
特定のホスト情報が見つかりました。<br />
↓↓↓<br />

<h4><?php echo $spam_host; ?></h4>
Whois: <a href="http://whois.arin.net/rest/ip/<?php echo $spam_ip; ?>" target="_blank"><?php echo $spam_ip; ?></a>
<?php
} else {
?>
特定のホスト情報は見つかりませんでした。
<?php
}
?>

<a href="javascript:void(0);" onclick="window.close();">閉じる</a>
</div>
</body>
</html>