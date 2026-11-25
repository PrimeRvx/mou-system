<?php
/** admin/users.php — จัดการผู้ใช้ (เฉพาะ Admin) */
require_once __DIR__ . '/../includes/layout_admin.php';
require_permission('user');

function active_admin_count(int $excludeId = 0): int
{
    return (int) db_val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 1 AND id <> ?", [$excludeId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('user');
    $act = post('action') ?: 'save'; $id = (int) post('id'); $me = (int) $_SESSION['user']['id'];
    if (!in_array($act, ['save', 'toggle'], true)) { http_response_code(400); exit('ไม่รู้จักคำสั่งนี้'); }
    if ($act === 'toggle') {
        $u = db_one('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$u) { flash('error', 'ไม่พบผู้ใช้'); }
        elseif ($id === $me) { flash('error', 'ไม่สามารถระงับบัญชีของตนเองได้'); }
        elseif ($u['role'] === 'admin' && $u['status'] && active_admin_count($id) < 1) { flash('error', 'ต้องมีผู้ดูแลระบบที่ใช้งานได้อย่างน้อย 1 คน'); }
        else { db_exec('UPDATE users SET status = 1 - status WHERE id = ?', [$id]); log_activity('user_toggle', 'users#' . $id, $u['username']); flash('success', 'เปลี่ยนสถานะผู้ใช้แล้ว'); }
        redirect('admin/users.php');
    }
    // บันทึก (เพิ่ม/แก้ไข)
    $username = post('username'); $full = mb_substr(post('full_name'), 0, 150); $email = post('email');
    $role = post('role'); $pw = post_raw('password');
    $err = null;
    if (!$id && !preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) { $err = 'ชื่อผู้ใช้ต้องเป็น a-z 0-9 _ . - ยาว 3–50 ตัว'; }
    elseif ($full === '') { $err = 'กรุณากรอกชื่อ-นามสกุล'; }
    elseif (!isset(role_labels()[$role])) { $err = 'บทบาทไม่ถูกต้อง'; }
    elseif ($email !== '' && !valid_email($email)) { $err = 'อีเมลไม่ถูกต้อง'; }
    elseif (!$id && ($p = password_problem($pw))) { $err = $p; }
    elseif ($id && $pw !== '' && ($p = password_problem($pw))) { $err = $p; }
    elseif (!$id && db_val('SELECT id FROM users WHERE username = ?', [$username])) { $err = 'ชื่อผู้ใช้นี้ถูกใช้แล้ว'; }
    elseif ($id && $id === $me && $role !== 'admin') { $err = 'ไม่สามารถลดสิทธิ์ตนเองได้'; }
    if ($err) {
        form_keep(['username' => $username, 'full_name' => $full, 'email' => $email, 'role' => $role]);   // ไม่เก็บรหัสผ่าน
        flash('error', $err);
        redirect('admin/users.php?' . ($id ? 'edit=' . $id : 'new=1'));
    }

    if ($id) {
        db_exec('UPDATE users SET full_name = ?, email = ?, role = ? WHERE id = ?', [$full, $email ?: null, $role, $id]);
        if ($pw !== '') {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            db_exec('UPDATE users SET password = ? WHERE id = ?', [$hash, $id]);   // เซสชันเดิมของบัญชีนั้นจะถูกบังคับออก
            if ($id === $me) { $_SESSION['user']['pw'] = password_signature($hash); }
        }
    } else {
        $id = db_insert('INSERT INTO users (username, password, full_name, email, role) VALUES (?,?,?,?,?)',
            [$username, password_hash($pw, PASSWORD_DEFAULT), $full, $email ?: null, $role]);
    }
    log_activity('user_save', 'users#' . $id, $username ?: $full);
    flash('success', 'บันทึกผู้ใช้เรียบร้อยแล้ว');
    redirect('admin/users.php');
}

admin_header('จัดการผู้ใช้งาน', 'user', 'user');
$edit = get('edit') ? db_one('SELECT * FROM users WHERE id = ?', [(int) get('edit')]) : null;
$showForm = $edit || get('new');
$old = form_old();   // ค่าที่กรอกค้างไว้จากการบันทึกที่ไม่ผ่าน
$fv = fn(string $k, string $d = '') => (string) ($old[$k] ?? $edit[$k] ?? $d);
$rows = db_all('SELECT * FROM users ORDER BY id');
?>
<?php if ($showForm): ?>
<div class="panel scale-in" style="max-width:640px"><h2><i class="bi bi-person-gear" aria-hidden="true"></i> <?= $edit ? 'แก้ไขผู้ใช้ ' . e($edit['username']) : 'เพิ่มผู้ใช้' ?></h2>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>"><div class="row g-3">
    <?php if (!$edit): ?><div class="col-md-6"><label class="form-label" for="f-username">ชื่อผู้ใช้ *</label><input id="f-username" name="username" class="form-control" required pattern="[A-Za-z0-9_.\-]{3,50}" autocomplete="off" value="<?= e($fv('username')) ?>"></div><?php endif; ?>
    <div class="col-md-6"><label class="form-label" for="f-full_name">ชื่อ-นามสกุล *</label><input id="f-full_name" name="full_name" class="form-control" required maxlength="150" value="<?= e($fv('full_name')) ?>"></div>
    <div class="col-md-6"><label class="form-label" for="f-email">อีเมล</label><input id="f-email" type="email" name="email" class="form-control" value="<?= e($fv('email')) ?>"></div>
    <div class="col-md-6"><label class="form-label" for="f-role">บทบาท *</label><select id="f-role" name="role" class="form-select">
      <?php foreach (role_labels() as $k => $l): ?><option value="<?= e($k) ?>" <?= $fv('role', 'staff') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><label class="form-label" for="f-password"><?= $edit ? 'ตั้งรหัสผ่านใหม่ (เว้นว่างถ้าไม่เปลี่ยน)' : 'รหัสผ่าน *' ?> <span class="text-muted small">(≥ 8 ตัว มีตัวอักษรและตัวเลข)</span></label>
      <input id="f-password" type="password" name="password" class="form-control" autocomplete="new-password" <?= $edit ? '' : 'required' ?>></div>
  </div><div class="mt-3 d-flex gap-2"><button class="btn btn-navy"><i class="bi bi-save" aria-hidden="true"></i> บันทึก</button><a class="btn btn-outline-secondary" href="users.php">ยกเลิก</a></div></form></div>
<?php else: ?>
<div class="page-head fade-up"><div><div class="sub">ทั้งหมด <?= count($rows) ?> บัญชี</div><h2 class="title">ผู้ใช้งาน</h2></div><a class="btn btn-gold" href="users.php?new=1"><i class="bi bi-person-plus" aria-hidden="true"></i> เพิ่มผู้ใช้</a></div>
<div class="panel fade-up delay-1"><div class="table-responsive"><table class="table table-sm align-middle">
  <thead><tr><th>ชื่อผู้ใช้</th><th>ชื่อ-นามสกุล</th><th>บทบาท</th><th>เข้าสู่ระบบล่าสุด</th><th>สถานะ</th><th><span class="visually-hidden">การจัดการ</span></th></tr></thead><tbody>
  <?php foreach ($rows as $u): ?><tr><td><span class="d-inline-flex align-items-center gap-2"><span class="user-chip p-0 border-0"><span class="avatar"><?= e(initial_of($u['full_name'])) ?></span></span><?= e($u['username']) ?></span></td><td><?= e($u['full_name']) ?><div class="small text-muted"><?= e($u['email']) ?></div></td>
    <td><?= e(role_labels()[$u['role']]) ?></td><td><?= $u['last_login'] ? e(thai_datetime($u['last_login'], true)) : '-' ?></td>
    <td><?= $u['status'] ? '<span class="badge bg-success">ใช้งาน</span>' : '<span class="badge bg-secondary">ระงับ</span>' ?></td>
    <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="users.php?edit=<?= (int) $u['id'] ?>" title="แก้ไข" aria-label="แก้ไขบัญชี <?= e($u['username']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a>
      <?php if ((int) $u['id'] !== (int) $_SESSION['user']['id']): ?><form class="d-inline" method="post" data-confirm="เปลี่ยนสถานะบัญชี <?= e($u['username']) ?> ?"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-secondary" title="เปิด/ระงับ" aria-label="เปิดหรือระงับบัญชี <?= e($u['username']) ?>"><i class="bi bi-person-slash" aria-hidden="true"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div>
<?php endif; admin_footer();
