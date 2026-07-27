<!DOCTYPE html>
<html lang="en" data-theme-mode="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Retail Tracking & Billing System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="public/assets/css/landing.css?v=<?= time(); ?>">
  <script>
    (function() {
      var t = localStorage.getItem('theme');
      if (t) document.documentElement.setAttribute('data-theme-mode', t);
    })();
  </script>
</head>
<body class="landing-page">
  <?php require_once 'views/landing/components/navbar.php'; ?>
  <?php require_once 'views/landing/components/hero.php'; ?>
  <?php require_once 'views/landing/components/features.php'; ?>
  <?php require_once 'views/landing/components/how-it-works.php'; ?>
  <?php require_once 'views/landing/components/screenshots.php'; ?>
  <?php require_once 'views/landing/components/pricing.php'; ?>
  <?php require_once 'views/landing/components/faq.php'; ?>
  <?php require_once 'views/landing/components/cta.php'; ?>
  <?php require_once 'views/landing/components/footer.php'; ?>
  <script src="public/assets/js/landing.js?v=<?= time(); ?>"></script>
</body>
</html>
