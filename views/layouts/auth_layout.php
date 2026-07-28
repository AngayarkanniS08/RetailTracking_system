<?php
// Auth Layout Wrapper (No Sidebar / Topbar)
require_once __DIR__ . '/header.php';
echo $pageContent ?? '';
require_once __DIR__ . '/footer.php';
?>
