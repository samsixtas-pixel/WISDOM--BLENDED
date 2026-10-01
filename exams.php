<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\Guard;
use Wisdom\Repositories\PaymentRepository;
use Wisdom\Repositories\UserRepository;
use Wisdom\Services\ExamService;

$user = Guard::requireLogin();
Guard::requirePasswordResetHandled();
$userRepo = App::get(UserRepository::class);
try { $userRepo->setResultsAck($user->getId()); } catch (\Throwable) {}
$active = 'exams';
$examService = App::get(ExamService::class);
$paymentRepo = App::get(PaymentRepository::class);

$examinationFeesPaid = $examService->canViewResults($user, $paymentRepo);
$exams = $examinationFeesPaid ? array_map(fn ($exam) => $exam->toStudentArray(), $examService->forUser($user)) : [];
$pageTitle = 'Examination results';
?>
<!doctype html>
   <html lang="en">
    <head>
        <?php require __DIR__ . '/partials/head.php'; ?>
        <style>
            /*body{margin:0;padding:40px;color:#172033;background:#f4f7fb;font:16px/1.5 system-ui,sans-serif}main{max-width:1100px;margin:auto;padding:32px;background:#fff;border-radius:16px;box-shadow:0 8px 25px #1c264b10}a{color:#315efb;text-decoration:none}.notice{padding:14px;border-radius:8px;background:#fff7df}table{width:100%;border-collapse:collapse;display:block;overflow-x:auto}th,td{padding:12px;text-align:left;white-space:nowrap;border-bottom:1px solid #e5e7eb}th{background:#f4f7fb}.muted{color:#718096}@media(max-width:600px){body{padding:18px}}*/
        </style>
    </head>
    <body>
    <div class="shell">
        <?php require __DIR__ . '/partials/nav.php'; ?>
        <main class="shell__main page-fade">
            <?php require __DIR__ . '/partials/notice.php'; ?>
            <?php require __DIR__ . '/partials/admin_flash.php'; ?>
            <p><a href="dashboard.php">&larr; Dashboard</a></p>
            <div class="row row--between" style="align-items:flex-end;gap:var(--s-4);flex-wrap:wrap"><h1 style="margin:0">Examination results</h1><?php if ($examinationFeesPaid && $exams !== []): ?><a href="results_pdf.php" class="btn btn--gold" target="_blank" rel="noopener">Download PDF</a><?php endif; ?></div>
            <?php if (!$examinationFeesPaid): ?>
                <p class="notice">Your examination results will appear after an administrator approves your examination-fee payment. <a href="fees.php">Submit examination fees</a>.</p>
            <?php elseif ($exams === []): ?>
                <p class="muted">Your examination records have not been added yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Code</th>
                            <th>Exam number</th>
                            <th>Subject name</th>
                            <th>Weight</th>
                            <th>Results</th>
                            <th>Grade</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exams as $number => $exam): ?>
                            <tr>
                                <td><?= $number + 1 ?></td>
                                <td><?= e($exam['subject_code']) ?></td>
                                <td><?= e($exam['exam_number']) ?></td>
                                <td><?= e($exam['subject_name']) ?></td>
                                <td><?= e((string) $exam['weight']) ?></td>
                                <td><?= $exam['result'] === null ? 'Pending' : e((string) $exam['result']) ?></td>
                                <td><?= $exam['grade'] === null ? 'Pending' : e((string) $exam['grade']) ?></td>
                                <td><?= e(ucfirst($exam['status'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </main>
    </div>
    <script src="assets/js/wisdom-ui.js" defer></script>
    </body>
</html>

