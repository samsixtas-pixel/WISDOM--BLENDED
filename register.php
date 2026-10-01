<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Wisdom\Core\App;
use Wisdom\Core\AppException;
use Wisdom\Core\Csrf;
use Wisdom\Core\Guard;
use Wisdom\Core\Request;
use Wisdom\Models\User;
use Wisdom\Repositories\SubjectRepository;
use Wisdom\Services\RegistrationService;

if (Request::isPost()) {
    Guard::throttle('register', 5, 300);
}

if (Guard::user() !== null) {
    redirect('dashboard.php');
}

$registration = App::get(RegistrationService::class);
$subjectRepo = App::get(SubjectRepository::class);
$catalogue = $subjectRepo->catalogue();
$pageTitle = 'Create account';

$error = '';
$name = '';
$email = '';
$sex = '';
$level = '';
$selectedSubjects = [];

if (Request::isPost()) {
    $name = Request::post('name');
    $email = Request::post('email');
    $sex = Request::post('sex');
    $level = Request::post('level');
    $selectedSubjects = array_values(array_intersect($catalogue[$level] ?? [], Request::postList('subjects')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!Csrf::verifyRequest()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $validator = $registration->validator([
            'name' => $name, 'email' => $email, 'sex' => $sex, 'level' => $level,
            'password' => $password, 'password_confirmation' => $confirmation,
        ]);

        if (!$validator->passes()) {
            $fieldErrors = $validator->errors();
            $error = (string) reset($fieldErrors);
        } else {
            try {
                $registration->register($name, $email, $sex, $level, $selectedSubjects, $password);
                redirect('login.php?registered=1');
            } catch (AppException $exception) {
                $error = $exception->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="en"><head><?php require __DIR__ . '/partials/head.php'; ?>
<style>
/*:root{--ink:#172033;--muted:#667085;--primary:#315efb;--line:#d5dce8}*{box-sizing:border-box}body{min-height:100vh;margin:0;display:grid;place-items:center;padding:24px;color:var(--ink);background:linear-gradient(135deg,#eef3ff,#f8fbff);font:16px/1.5 system-ui,sans-serif}main{width:min(100%,500px);padding:36px;border:1px solid #e3e8ef;border-radius:18px;background:#fff;box-shadow:0 18px 50px rgb(24 34 48 / 10%)}.brand{margin:0 0 28px;color:var(--primary);font-size:1rem;letter-spacing:.18em}h1{margin:0 0 8px;font-size:1.9rem}p{margin:0 0 26px;color:var(--muted)}label{display:block;margin:15px 0 7px;font-weight:650}input,select{width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:9px;font:inherit;background:#fff}input:focus,select:focus{outline:3px solid rgb(49 94 251 / 18%);border-color:var(--primary)}button{width:100%;margin-top:24px;padding:13px;border:0;border-radius:9px;color:#fff;background:var(--primary);font:inherit;font-weight:700;cursor:pointer}.error{margin:0 0 18px;padding:11px 13px;border-radius:8px;color:#9d1c2b;background:#fff0f1}.link{margin-top:22px;text-align:center}a{color:var(--primary);font-weight:650;text-decoration:none}*/
/*.subjects-fieldset{margin:18px 0 0;padding:12px 14px;border:1px solid var(--line);border-radius:9px}
.subjects-fieldset legend{padding:0 6px;font-weight:650}
.subjects-list{display:grid;gap:4px;max-height:220px;overflow-y:auto;margin-top:6px}
.subject-option{display:flex;align-items:center;gap:9px;margin:0;padding:6px 8px;border-radius:6px;font-weight:400;cursor:pointer}
.subject-option:hover{background:#f4f7fb}
.subject-option input{width:18px;height:18px;padding:0;margin:0;flex:0 0 auto;accent-color:var(--primary)}

*/</style>
</head><body class="auth-page"><main class="auth-card"><div class="wordmark">WISDOM</div><h1>Create your account</h1><p>Set up your learner profile to get started.</p>
<?php if ($error !== ''): ?><div class="alert alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="register.php" novalidate>
<?= csrf_field() ?>

<label for="name">Full name</label>
<input id="name" name="name" value="<?= e($name) ?>" autocomplete="name" required>

<label for="email">Email</label>
<input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" required>

<label for="sex">Sex</label>
<select id="sex" name="sex" required>
    <option value="">Select sex</option><?php foreach (User::SEXES as $option): ?>
        <option value="<?= e($option) ?>" <?= $sex === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option>
        <?php endforeach; ?>
    </select>
<label for="level">Programme</label>
<select id="level" name="level" required>
    <option value="">Select programme</option>
    <?php foreach (User::LEVELS as $option): ?>
        <option value="<?= e($option) ?>" <?= $level === $option ? 'selected' : '' ?>><?= e($option) ?></option>
        <?php endforeach; ?>
    </select>
<!--<label for="subjects">Subjects</label><select id="subjects" name="subjects[]" multiple required size="5" aria-describedby="subjects-help"></select><small id="subjects-help">Choose one or more subjects for your programme.</small>-->
<fieldset class="subjects-fieldset">
    <legend>Subjects</legend>
        <div id="subjects" class="subjects-list" role="group" aria-describedby="subjects-help"></div>
            <small id="subjects-help">Tick one or more subjects for your programme.</small>
</fieldset>

<label for="password">Password</label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
<label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
<button type="submit">Create account</button>
</form>
<div class="auth-links">Already registered? <a href="login.php">Sign in</a></div>
<script nonce="<?= e(nonce()) ?>">
const subjects = <?= json_encode($catalogue, JSON_THROW_ON_ERROR) ?>;
const programme = document.querySelector('#level');
const subjectList = document.querySelector('#subjects');
const selectedSubjects = <?= json_encode($selectedSubjects, JSON_THROW_ON_ERROR) ?>;
function renderSubjects() {
    subjectList.replaceChildren();
    (subjects[programme.value] || []).forEach((subject, index) => {
        const id = 'subject-' + index;

        const label = document.createElement('label');
        label.className = 'subject-option';
        label.htmlFor = id;

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.name = 'subjects[]';
        checkbox.id = id;
        checkbox.value = subject;
        checkbox.checked = selectedSubjects.includes(subject);

        label.append(checkbox, document.createTextNode('' + subject));
        subjectList.append(label);

    });
}
programme.addEventListener('change', renderSubjects);
renderSubjects();
</script>
</main>
</body>
</html>
