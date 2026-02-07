<?php
  // 他サイトでiframe引用を禁止
  header('X-FRAME-OPTIONS: SAMEORIGIN');

  // セッションの開始
  session_cache_expire(0);
  session_cache_limiter('private_no_expire');
  session_start();
  $title = empty($title) ? 'ページタイトル' : $title;
  $description = empty($description) ? 'ページディスクリプション' : $description;
  $url = empty($url) ? ( empty( $_SERVER["HTTPS"] ) ? "http://" : "https://" ) . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"] : $url;
  $content = empty($content) ? '' : $content;
  $root = empty($root) ? './' : $root;
?>
<!DOCTYPE html>
<html lang="ja" class="<?php echo $size; ?>">
<head>
    <?php include($root.'_inc/head.php'); ?>
</head>
<body id="top">
  <div class="overlay"></div>
  <?php include($root.'_inc/svgsprite.php'); ?>
  <?php include($root.'_inc/header.php'); ?>
  <main id="contents">
    <?php echo $content; ?>
  </main>
  <?php include($root.'_inc/footer.php'); ?>
  <?php include($root.'_inc/foot.php'); ?>
</body>
</html>