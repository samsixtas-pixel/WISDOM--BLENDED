<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';
use Wisdom\Core\Guard;
if (Guard::user() !== null) redirect('dashboard.php');
$pageTitle = 'Learn, think, grow';
$pageDesc = 'WISDOM Blended Classes — professional learning in procurement, supply chain, and logistics.';
?>
<!doctype html>
<html lang="en">
<head><?php require __DIR__ . '/partials/head.php'; ?></head>
<body>
<nav class="lp-nav"><div class="lp-nav__inner"><a class="lp-nav__brand" href="landing.php" aria-label="WISDOM home"><span class="rail__logo" aria-hidden="true">W</span><span><span class="wordmark">WISDOM</span><span class="wordmark-sub">Blended Classes</span></span></a><div class="lp-nav__links"><a href="#features">The platform</a><a href="#begin">Get started</a><a href="login.php">Sign in</a><a href="register.php" class="btn btn--gold btn--sm">Create an account</a></div></div></nav>
<main><section class="lp-hero page-fade"><div class="lp-hero__copy"><div class="eyebrow">Learn · Think · Grow</div><h1>An academic home for the <em>professionals</em> shaping supply chains.</h1><p class="lead">WISDOM Blended Classes brings structured curricula, live instruction, examination records, and fee handling into one learning workspace.</p><div class="lp-hero__cta"><a href="register.php" class="btn btn--gold">Create your account</a><a href="login.php" class="btn btn--ghost">I already have an account</a></div><div class="lp-hero__meta"><div><strong>4</strong>Programmes</div><div><strong>22</strong>Subjects</div><div><strong>Verified</strong>Results &amp; payments</div></div></div><div class="lp-motif" aria-hidden="true"><div class="lp-motif__ring"></div><div class="lp-motif__mark">W</div><div class="lp-motif__chip lp-motif__chip--gold">Live classes</div><div class="lp-motif__chip lp-motif__chip--teal">Exams &amp; results</div><div class="lp-motif__chip lp-motif__chip--navy">Fees &amp; records</div></div></section>
<section id="features" class="lp-features" aria-label="Platform features"><article class="lp-feature lp-feature--a"><span class="clay clay--gold clay--md" aria-hidden="true">▤</span><h3>Structured learning</h3><p>Study across CPSP I and II and PD I and II, with subjects managed through the learner workspace.</p></article><article class="lp-feature lp-feature--b"><span class="clay clay--teal clay--md" aria-hidden="true">✓</span><h3>Results in one place</h3><p>Review examination records entered and published by administration.</p></article><article class="lp-feature lp-feature--c"><span class="clay clay--gold clay--md" aria-hidden="true">$</span><h3>Clear fee submissions</h3><p>Submit payment proof and follow its review status.</p></article><article class="lp-feature lp-feature--d"><span class="clay clay--navy clay--md" aria-hidden="true">◇</span><h3>Security built in</h3><p>Session protections, CSRF checks, prepared queries, and role-based permissions safeguard key actions.</p></article></section>
<section class="lp-signature" id="begin"><div class="lp-signature__inner"><div><div class="eyebrow" style="color:var(--gold-400)">Learn · Think · Grow</div><h2>Begin with a single account.</h2></div><div><p>Create a learner account, select your programme subjects, and use the workspace to manage fees, classes, and results.</p><a href="register.php" class="btn btn--gold">Create an account</a></div></div></section></main>
<footer class="lp-foot"><p class="mb-0">Learn · Think · Grow &nbsp; | &nbsp; © <?= date('Y') ?> WISDOM Blended Classes</p></footer>
</body></html>
