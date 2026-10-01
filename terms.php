<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\Guard;

$pageTitle = 'Terms of service';
$pageDesc  = 'The terms that govern use of the WISDOM Blended Classes learning platform.';

$current = Guard::user();
$home = $current === null ? 'landing.php' : ($current->isStaff() ? 'admin.php' : 'dashboard.php');
$updated = '30 September 2026';
?>
<!doctype html>
<html lang="en">
<head><?php require __DIR__ . '/partials/head.php'; ?></head>
<body>
<nav class="landing-nav"><div class="landing-inner"><a class="landing-brand" href="landing.php"><span class="logo-mark" aria-hidden="true">W</span><span class="wordmark">WISDOM<small>BLENDED CLASSES</small></span></a><div class="landing-links"><a href="<?= e($home) ?>">Back to app</a><a href="privacy.php">Privacy</a><a href="contact.php">Contact</a></div></div></nav>
<main class="shell__main legal" style="max-width:840px;margin-inline:auto">
    <div class="eyebrow">Legal</div>
    <h1>Terms of service</h1>
    <p class="text-muted">Last updated <?= e($updated) ?>. By creating an account you accept these terms.</p>

    <h2>1. Your account</h2>
    <p>You must provide accurate registration details and keep your password confidential. You are responsible for activity carried out under your account. One account per learner.</p>

    <h2>2. Fees and approval</h2>
    <ul>
        <li>Programme fees are calculated from the subjects you select.</li>
        <li>Your account is marked <strong>approved</strong> once a programme-fee payment has been verified by an administrator.</li>
        <li>Examination results become visible only after an <strong>examination fee</strong> payment has been approved.</li>
        <li>Fees are non-refundable once a payment has been verified, except where required by law.</li>
    </ul>

    <h2>3. Payment proof</h2>
    <p>You must upload a genuine deposit slip or receipt matching the amount you declare. Submitting altered, duplicated or third-party documents may result in suspension of your account and forfeiture of the payment.</p>

    <h2>4. Acceptable use</h2>
    <p>Do not attempt to access another learner's records, probe or overload the platform, upload malicious files, or misuse examination records. We may suspend accounts that breach these rules.</p>

    <h2>5. Academic records</h2>
    <p>Examination records are entered by authorised staff. If you believe a record is wrong, contact support; do not attempt to change it yourself. Verified records are the authoritative version.</p>

    <h2>6. Availability</h2>
    <p>We aim to keep the platform available at all times but do not guarantee uninterrupted service. Maintenance, network failures and live-class provider outages may interrupt access.</p>

    <h2>7. Intellectual property</h2>
    <p>Course materials, recordings and platform content remain the property of WISDOM Blended Classes or its licensors and are provided for your personal study only.</p>

    <h2>8. Limitation of liability</h2>
    <p>To the extent permitted by law, we are not liable for indirect or consequential loss arising from use of the platform. Nothing in these terms limits rights that cannot be limited by law.</p>

    <h2>9. Changes and termination</h2>
    <p>We may update these terms and will publish the new version here. You may close your account at any time; we may suspend accounts that breach these terms.</p>

    <h2>10. Governing law</h2>
    <p>These terms are governed by the laws of the United Republic of Tanzania.</p>

    <h2>11. Contact</h2>
    <p>Questions about these terms? Use the <a href="contact.php">contact form</a>.</p>
</main>
<footer class="footer"><p class="text-muted"><a href="landing.php">Home</a> · <a href="privacy.php">Privacy policy</a> · <a href="contact.php">Contact support</a></p></footer>
</body>
</html>
