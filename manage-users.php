<?php
require 'config.php';
require_role('admin');

$page_title = 'Patient & Staff Records';

$allowed_roles = ['patient', 'admin', 'dentist', 'receptionist'];
$creatable_roles = ['receptionist', 'admin'];
$flash = '';
$flash_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $user_id = (int) ($_POST['user_id'] ?? 0);

    if ($action === 'create') {
        $fullname = trim((string) ($_POST['fullname'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $new_role = $_POST['new_role'] ?? '';
        $password = (string) ($_POST['password'] ?? '');

        if (mb_strlen($fullname) < 2 || mb_strlen($fullname) > 100) {
            $flash_error = 'Enter the staff member\'s full name (2-100 characters).';
        } elseif (($email_err = email_length_error($email)) !== null) {
            $flash_error = $email_err;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $flash_error = 'Enter a valid email address.';
        } elseif (!in_array($new_role, $creatable_roles, true)) {
            $flash_error = 'Choose a valid staff role.';
        } elseif ($phone !== '' && (mb_strlen($phone) > 30 || !preg_match('/^[0-9+()\-\s.]+$/', $phone))) {
            $flash_error = 'Enter a valid phone number or leave it blank.';
        } elseif (($policy = password_policy_error($password)) !== null) {
            $flash_error = $policy;
        } else {
            $exists = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1');
            $exists->execute([$email]);

            if ($exists->fetch()) {
                $flash_error = 'An account with that email already exists.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (fullname, email, password, phone, role) VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $fullname,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                    $phone !== '' ? $phone : null,
                    $new_role,
                ]);
                $new_id = (int) $pdo->lastInsertId();
                log_activity('staff_account_created', ucfirst($new_role) . ' account #' . $new_id . ' (' . $email . ')');
                $flash = ucfirst($new_role) . ' account created for ' . $fullname . '. Share the initial password securely; they can change it under Account.';
            }
        }
    } elseif ($user_id <= 0) {
        $flash_error = 'Invalid user.';
    } elseif ($user_id === (int) $_SESSION['user_id']) {
        $flash_error = 'You cannot modify your own account here.';
    } elseif ($action === 'set_role') {
        $new_role = $_POST['role'] ?? '';
        if (!in_array($new_role, $allowed_roles, true)) {
            $flash_error = 'Invalid role.';
        } else {
            $check = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $check->execute([$user_id]);
            $target = $check->fetch();

            if (!$target) {
                $flash_error = 'User not found.';
            } elseif ($target['role'] === 'admin' && $new_role !== 'admin') {
                $flash_error = 'Admin accounts cannot be demoted. Delete the account instead if needed.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
                $stmt->execute([$new_role, $user_id]);
                log_activity('user_role_changed', 'User #' . $user_id . ': ' . $target['role'] . ' -> ' . $new_role);
                $flash = 'Role updated to ' . $new_role . '.';
            }
        }
    } elseif ($action === 'delete') {
        $check = $pdo->prepare("SELECT role, email FROM users WHERE id = ?");
        $check->execute([$user_id]);
        $target = $check->fetch();

        if (!$target) {
            $flash_error = 'User not found.';
        } elseif ($target['role'] === 'admin') {
            $flash_error = 'Admin accounts cannot be deleted.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$user_id]);
            log_activity('user_deleted', 'Deleted ' . $target['role'] . ' #' . $user_id . ' (' . $target['email'] . ')');
            $flash = 'User deleted along with their appointments and records.';
        }
    } else {
        $flash_error = 'Invalid action.';
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) > 100) {
    $q = mb_substr($q, 0, 100);
}

$sql = "SELECT u.*,
            (SELECT COUNT(*) FROM appointments a WHERE a.user_id = u.id) AS appointment_count
     FROM users u";
$params = [];
if ($q !== '') {
    $sql .= ' WHERE u.fullname LIKE ? OR u.email LIKE ? OR u.phone LIKE ?';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$sql .= ' ORDER BY u.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$role_counts = ['patient' => 0, 'admin' => 0, 'dentist' => 0, 'receptionist' => 0];
foreach ($pdo->query('SELECT role, COUNT(*) AS total FROM users GROUP BY role')->fetchAll() as $row) {
    $role_counts[$row['role']] = (int) $row['total'];
}

require 'header.php';
?>

<div class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title">Patient &amp; Staff Records</h1>
            <p class="page-sub">Manage user accounts, roles, and access.</p>
        </div>
        <a href="admin-dashboard.php" class="btn btn-outline">Admin Dashboard</a>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="alert alert-success"><?= e($flash) ?></div>
    <?php elseif ($flash_error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($flash_error) ?></div>
    <?php endif; ?>

    <div class="stat-grid">
        <div class="stat-card">
            <strong><?= (int) $role_counts['patient'] ?></strong>
            <span>Patients</span>
        </div>
        <div class="stat-card red">
            <strong><?= (int) $role_counts['admin'] ?></strong>
            <span>Admins</span>
        </div>
        <div class="stat-card green">
            <strong><?= (int) $role_counts['dentist'] ?></strong>
            <span>Dentists</span>
        </div>
        <div class="stat-card amber">
            <strong><?= (int) $role_counts['receptionist'] ?></strong>
            <span>Receptionists</span>
        </div>
    </div>

    <div class="panel">
        <h2>Add a Staff Account</h2>
        <p class="cell-muted">Create a receptionist or administrator login. Staff accounts sign in with their password only, without an email code.</p>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="form-grid">
                <div class="field">
                    <label for="staff_name">Full Name</label>
                    <input type="text" name="fullname" id="staff_name" class="input" maxlength="100" required>
                </div>
                <div class="field">
                    <label for="staff_email">Email Address</label>
                    <input type="email" name="email" id="staff_email" class="input" minlength="10" maxlength="30" autocomplete="off" required>
                </div>
            </div>
            <div class="form-grid-3">
                <div class="field">
                    <label for="staff_phone">Phone (optional)</label>
                    <input type="tel" name="phone" id="staff_phone" class="input" maxlength="30">
                </div>
                <div class="field">
                    <label for="staff_role">Role</label>
                    <select name="new_role" id="staff_role" class="select" required>
                        <option value="receptionist">Receptionist</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <div class="field">
                    <label for="staff_password">Initial Password</label>
                    <input type="password" name="password" id="staff_password" class="input" maxlength="20" autocomplete="new-password" required>
                </div>
            </div>
            <p class="field-hint">8 to 20 characters with at least one letter, one number, and one special character. Share it securely; the staff member can change it under Account.</p>
            <button type="submit" class="btn btn-success">Create Account</button>
        </form>
    </div>

    <div class="panel">
        <h2>All Accounts</h2>
        <form method="GET" class="panel-actions" style="margin-bottom:16px;">
            <input type="search" name="q" class="input" style="max-width:360px;" value="<?= e($q) ?>" placeholder="Name, email, or phone" aria-label="Search accounts">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <?php if ($q !== ''): ?>
                <a href="manage-users.php" class="btn btn-neutral btn-sm">Clear</a>
            <?php endif; ?>
        </form>
        <?php if (empty($users)): ?>
            <div class="empty-state"><p>No accounts match that search.</p></div>
        <?php else: ?>
        <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Appointments</th>
                    <th>Registered</th>
                    <th>Manage</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="cell-muted">#<?= (int) $u['id'] ?></td>
                    <td>
                        <div class="cell-strong"><?= e($u['fullname']) ?></div>
                        <?php if ((int) $u['id'] === (int) $_SESSION['user_id']): ?>
                            <div class="cell-muted">(you)</div>
                        <?php endif; ?>
                    </td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= e($u['phone'] ?? '-') ?></td>
                    <td><span class="badge badge-role-<?= e($u['role']) ?>"><?= e(ucfirst($u['role'])) ?></span></td>
                    <td><?= (int) $u['appointment_count'] ?></td>
                    <td class="cell-muted"><?= date("M j, Y", strtotime($u['created_at'])) ?></td>
                    <td>
                        <?php if ((int) $u['id'] === (int) $_SESSION['user_id']): ?>
                            <span class="cell-muted">Current session</span>
                        <?php else: ?>
                            <div class="panel-actions no-print">
                                <form method="POST" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="set_role">
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <select name="role" class="select">
                                        <?php foreach ($allowed_roles as $r): ?>
                                            <option value="<?= e($r) ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                </form>
                                <?php if ($u['role'] !== 'admin'): ?>
                                <form method="POST" onsubmit="return confirm('Delete this user and all their records?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'footer.php'; ?>
