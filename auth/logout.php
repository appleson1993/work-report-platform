<?php
session_start();

// 清除所有Session變數
session_unset();

// 銷毀Session
session_destroy();

// 重導向到登入頁面
header('Location: ../index.php');
exit;
?>
