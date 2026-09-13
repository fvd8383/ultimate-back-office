<?php
/** $reviewFailure is the existing content-free, allowlisted workflow failure. */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Website review submission not accepted</title>
    <link rel="stylesheet" href="../assets/css/design-system.css">
</head>
<body class="theme-247sp">
    <main class="page-shell">
        <section class="business-switcher site-customer-review">
            <h1>Submission not accepted</h1>
            <p class="site-customer-receipt" role="alert" tabindex="-1" autofocus><?= htmlspecialchars($reviewFailure, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <p><a href="website-manager.php">Reload Website Manager</a></p>
        </section>
    </main>
</body>
</html>
