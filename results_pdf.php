<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';
use Wisdom\Core\App; use Wisdom\Core\AppException; use Wisdom\Core\Guard; use Wisdom\Repositories\PaymentRepository; use Wisdom\Services\ExamService; use Wisdom\Services\ResultsPdfService;
$user=Guard::requireLogin(); Guard::requirePasswordResetHandled(); if($user->isStaff()) render_error_page(403,'Not available','This page is only for students.'); if(!App::get(ExamService::class)->canViewResults($user,App::get(PaymentRepository::class))) render_error_page(403,'Results locked','Your examination-fee payment has not yet been approved.'); try{App::get(ResultsPdfService::class)->stream($user,true);}catch(AppException $e){render_error_page(500,'PDF unavailable',$e->getMessage());}catch(Throwable $e){error_log('results_pdf failed: '.$e->getMessage());render_error_page(500,'PDF unavailable','We could not generate your results PDF right now.');}
