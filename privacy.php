<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\Guard;

$pageTitle = 'Privacy policy';
$pageDesc  = 'How WISDOM Blended Classes collects, uses and protects personal data.';

$current = Guard::user();
$home = $current === null ? 'landing.php' : ($current->isStaff() ? 'admin.php' : 'dashboard.php');
$updated = '30 September 2026';
?>
<!doctype html>
<html lang="en">
<head><?php require __DIR__ . '/partials/head.php'; ?></head>
<body>
<nav class="landing-nav"><div class="landing-inner"><a class="landing-brand" href="landing.php"><span class="logo-mark" aria-hidden="true">W</span><span class="wordmark">WISDOM<small>BLENDED CLASSES</small></span></a><div class="landing-links"><a href="<?= e($home) ?>">Back to app</a><a href="terms.php">Terms</a><a href="contact.php">Contact</a></div></div></nav>
<main class="shell__main legal" style="max-width:840px;margin-inline:auto">
    <div class="eyebrow">Legal</div>
    <h1>Privacy policy</h1>
    <p class="text-muted">Last updated <?= e($updated) ?>.</p>

    <h2>1. Who we are</h2>
    <p>WISDOM Blended Classes ("we", "us") provides blended professional training and examinations. This policy explains what we collect when you use this platform and why.</p>

    <h2>2. What we collect</h2>
    <ul>
        <li><strong>Account details:</strong> your name, email address, sex and chosen programme and subjects.</li>
        <li><strong>Learning records:</strong> examination results entered by our staff.</li>
        <li><strong>Payment evidence:</strong> the deposit slip or receipt image you upload, the amount, and the payment category.</li>
        <li><strong>Profile picture</strong>, if you choose to upload one.</li>
        <li><strong>Technical data:</strong> IP address and sign-in attempts, used only for security and fraud prevention.</li>
    </ul>

    <h2>3. Why we collect it</h2>
    <ul>
        <li>To register you and confirm your programme enrolment.</li>
        <li>To verify fee payments before granting access to results.</li>
        <li>To publish examination results to your account.</li>
        <li>To contact you about notices, sessions and account issues.</li>
        <li>To keep the platform secure and prevent abuse.</li>
    </ul>

    <h2>4. What we never do</h2>
    <p>We do not sell your personal data. We do not show your payment proof, examination record or contact details to other students. Payment proof images are only accessible to your own account and to authorised administrators.</p>

    <h2>5. How long we keep it</h2>
    <p>Account and academic records are kept while your account is active and for as long as required for academic and financial record-keeping. Security logs are kept for a limited period and then discarded.</p>

    <h2>6. Your rights</h2>
    <p>You may ask us to correct inaccurate details, replace your profile picture, or delete your account. Write to us through the contact page and we will respond within a reasonable period, subject to records we must legally retain.</p>

    <h2>7. Security</h2>
    <p>Passwords are stored only as salted bcrypt hashes and are never recoverable in plain text. Uploaded documents are stored outside the public web directory and served only after an access check. All traffic should always be served over HTTPS in production.</p>

    <h2>8. Cookies and sessions</h2>
    <p>We use a single, strictly necessary session cookie to keep you signed in. It is not used for advertising or cross-site tracking.</p>

    <h2>9. Changes</h2>
    <p>If this policy changes we will update the date above and, where the change is significant, show a notice on your dashboard.</p>

    <h2>10. Contact</h2>
    <p>Questions about this policy? Use the <a href="contact.php">contact form</a>.</p>
</main>
<footer class="footer"><p class="text-muted"><a href="landing.php">Home</a> · <a href="terms.php">Terms of service</a> · <a href="contact.php">Contact support</a></p></footer>
</body>
</html>
