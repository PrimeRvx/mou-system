<?php
/** admin/proposal_view.php — รายละเอียดข้อเสนอ: เปลี่ยนสถานะ/ตอบกลับ/แปลงเป็น MOU/ลบ (สแปม) */
require_once __DIR__ . '/../includes/layout_admin.php';
require_permission('proposal');
$id = (int) get('id');
$p = db_one('SELECT * FROM proposals WHERE id = ?', [$id]);
if (!$p) { flash('error', 'ไม่พบข้อเสนอ'); redirect('admin/proposals.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('proposal');
    $act = post('action');
    if ($act === 'update') {
        $st = post('status');
        if (!isset(proposal_statuses()[$st])) { $st = $p['status']; }
        db_exec('UPDATE proposals SET status = ?, staff_note = ?, reply_note = ? WHERE id = ?', [$st, post('staff_note') ?: null, post('reply_note') ?: null, $id]);
        db_exec("UPDATE notifications SET is_read = 1 WHERE type = 'proposal' AND link = ?", ['admin/proposal_view.php?id=' . $id]);
        log_activity('proposal_update', 'proposals#' . $id, $p['ref_no'] . ' → ' . $st);
        if (!empty($_POST['send_reply'])) {
            // ส่งข้อความตอบกลับถึงผู้เสนอทางอีเมลทันที (ต้องตั้ง SMTP ของ PHP ไว้ — ถ้าส่งไม่ได้จะแจ้งให้ใช้ปุ่มเปิดโปรแกรมอีเมลแทน)
            $reply = trim(post('reply_note'));
            if ($reply === '') {
                flash('warning', 'บันทึกแล้ว แต่ยังไม่ได้ส่งอีเมล เพราะไม่มีข้อความตอบกลับ');
            } elseif (send_text_mail($p['email'], 'ตอบกลับข้อเสนอความร่วมมือ ' . $p['ref_no'],
                          'เรียน คุณ' . $p['contact_name'] . "\n\n" . $reply . "\n\n" . setting('college_name')
                          . (setting('contact_phone') !== '' ? "\nโทร. " . setting('contact_phone') : '') . "\n")) {
                log_activity('proposal_reply_mail', 'proposals#' . $id, $p['ref_no'] . ' → ' . $p['email']);
                flash('success', 'บันทึกและส่งอีเมลตอบกลับถึง ' . $p['email'] . ' แล้ว');
            } else {
                flash('warning', 'บันทึกแล้ว แต่ระบบส่งอีเมลไม่สำเร็จ (ยังไม่ได้ตั้งค่า SMTP ของ PHP) — ใช้ปุ่ม "เปิดโปรแกรมอีเมล" เพื่อส่งเองแทน');
            }
            redirect('admin/proposal_view.php?id=' . $id);
        }
        flash('success', 'บันทึกเรียบร้อยแล้ว');
    } elseif ($act === 'convert') {
        // แปลงเป็น MOU ในคลิกเดียว: สร้าง/ใช้หน่วยงานเดิม แล้วไปหน้าฟอร์ม MOU ที่เติมข้อมูลให้
        if ($p['converted_mou_id']) { redirect('admin/mou_view.php?id=' . (int) $p['converted_mou_id']); }
        $pid = db_val('SELECT id FROM partners WHERE name = ? LIMIT 1', [$p['org_name']]);
        if (!$pid) {
            $pid = db_insert('INSERT INTO partners (name, type, address, province, contact_name, phone, email) VALUES (?,?,?,?,?,?,?)',
                [$p['org_name'], $p['org_type'], $p['address'], $p['province'], $p['contact_name'], $p['phone'], $p['email']]);
        }
        db_exec("UPDATE proposals SET status = 'reviewing' WHERE id = ? AND status = 'new'", [$id]);
        log_activity('proposal_convert', 'proposals#' . $id, $p['ref_no']);
        redirect('admin/mou_form.php?proposal_id=' . $id . '&partner_id=' . (int) $pid);
    } elseif ($act === 'delete') {
        // ลบข้อเสนอ (เช่น สแปม/ส่งซ้ำ) พร้อมไฟล์แนบและแจ้งเตือนที่เกี่ยวข้อง — MOU ที่แปลงไปแล้วไม่ถูกกระทบ
        db_exec('DELETE FROM proposals WHERE id = ?', [$id]);
        if ($p['attachment']) { delete_upload('proposals', $p['attachment']); }
        db_exec("DELETE FROM notifications WHERE type = 'proposal' AND link = ?", ['admin/proposal_view.php?id=' . $id]);
        log_activity('proposal_delete', 'proposals#' . $id, $p['ref_no'] . ' ' . $p['org_name']);
        flash('success', 'ลบข้อเสนอ ' . $p['ref_no'] . ' เรียบร้อยแล้ว');
        redirect('admin/proposals.php');
    }
    redirect('admin/proposal_view.php?id=' . $id);
}

// เปิดอ่านแล้ว = แจ้งเตือนของข้อเสนอนี้ถือว่าอ่านแล้ว
db_exec("UPDATE notifications SET is_read = 1 WHERE type = 'proposal' AND is_read = 0 AND link = ?", ['admin/proposal_view.php?id=' . $id]);

admin_header('ข้อเสนอ ' . $p['ref_no'], 'proposal', 'proposal');
$canWrite = can_write();
$progNames = [];
$ids = array_filter(array_map('intval', explode(',', (string) $p['programs_interest'])));
if ($ids) { $progNames = array_column(db_all('SELECT CONCAT(level, " ", name) n FROM programs WHERE id IN (' . implode(',', $ids) . ')'), 'n'); }
?>
<a class="btn btn-outline-secondary mb-3" href="proposals.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> กลับ</a>
<div class="row g-3 stagger">
  <div class="col-lg-7"><div class="panel"><h2><i class="bi bi-building" aria-hidden="true"></i> <?= e($p['org_name']) ?> <small class="text-muted"><?= e($p['ref_no']) ?></small></h2>
    <dl class="row mb-0 kv">
      <dt class="col-sm-4">ประเภท</dt><dd class="col-sm-8"><?= e(partner_type_label($p['org_type'])) ?></dd>
      <dt class="col-sm-4">ผู้ติดต่อ</dt><dd class="col-sm-8"><?= e($p['contact_name']) ?></dd>
      <dt class="col-sm-4">โทรศัพท์</dt><dd class="col-sm-8"><?= e($p['phone']) ?></dd>
      <dt class="col-sm-4">อีเมล</dt><dd class="col-sm-8"><a href="mailto:<?= e($p['email']) ?>"><?= e($p['email']) ?></a></dd>
      <dt class="col-sm-4">ที่อยู่/จังหวัด</dt><dd class="col-sm-8"><?= e($p['address']) ?> <?= e($p['province']) ?></dd>
      <dt class="col-sm-4">สาขาที่สนใจ</dt><dd class="col-sm-8"><?= e(implode(', ', $progNames) ?: '-') ?></dd>
      <dt class="col-sm-4">รูปแบบความร่วมมือ</dt><dd class="col-sm-8"><?= e($p['coop_type'] ?: '-') ?></dd>
      <dt class="col-sm-4">รายละเอียด</dt><dd class="col-sm-8"><?= nl2br(e($p['details'])) ?></dd>
      <dt class="col-sm-4">ไฟล์แนบ</dt><dd class="col-sm-8"><?php if ($p['attachment']): ?><a href="proposal_file.php?id=<?= (int) $p['id'] ?>"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> <?= e($p['attachment_name']) ?></a><?php else: ?>-<?php endif; ?></dd>
      <dt class="col-sm-4">ส่งเมื่อ</dt><dd class="col-sm-8"><?= e(thai_datetime($p['created_at'])) ?> <span class="text-muted small">(IP <?= e($p['ip_address']) ?>)</span></dd>
    </dl></div></div>
  <div class="col-lg-5">
    <?php if ($canWrite): ?>
    <div class="panel"><h2><i class="bi bi-clipboard-check" aria-hidden="true"></i> การพิจารณา</h2>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update">
        <div class="mb-2"><label class="form-label" for="f-status">สถานะ</label><select id="f-status" name="status" class="form-select">
          <?php foreach (proposal_statuses() as $k => $s): ?><option value="<?= e($k) ?>" <?= $p['status'] === $k ? 'selected' : '' ?>><?= e($s[0]) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label" for="f-staff_note">หมายเหตุเจ้าหน้าที่ (ภายใน)</label><textarea id="f-staff_note" name="staff_note" class="form-control" rows="3"><?= e($p['staff_note']) ?></textarea></div>
        <div class="mb-2"><label class="form-label" for="f-reply_note">ข้อความตอบกลับผู้เสนอ</label><textarea id="f-reply_note" name="reply_note" class="form-control" rows="3"><?= e($p['reply_note']) ?></textarea>
          <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="send_reply" value="1" id="sendReply"><label class="form-check-label" for="sendReply">ส่งข้อความนี้ถึงผู้เสนอทางอีเมลทันทีเมื่อกดบันทึก (<?= e($p['email']) ?>)</label></div>
          <div class="form-text">ถ้าเซิร์ฟเวอร์ยังส่งอีเมลไม่ได้ ให้กดบันทึกก่อน แล้วใช้ปุ่ม "เปิดโปรแกรมอีเมล" เพื่อส่งจากอีเมลของท่านเอง</div></div>
        <div class="d-flex flex-wrap gap-2"><button class="btn btn-navy"><i class="bi bi-save" aria-hidden="true"></i> บันทึก</button>
          <?php if ((string) $p['reply_note'] !== ''): ?><a class="btn btn-outline-primary" data-no-loader href="mailto:<?= e($p['email']) ?>?subject=<?= e(rawurlencode('ตอบกลับข้อเสนอความร่วมมือ ' . $p['ref_no'])) ?>&amp;body=<?= e(rawurlencode(mb_substr("เรียน คุณ" . $p['contact_name'] . "\n\n" . $p['reply_note'] . "\n\n" . setting('college_name'), 0, 1500))) ?>"><i class="bi bi-envelope" aria-hidden="true"></i> เปิดโปรแกรมอีเมล</a><?php endif; ?></div></form></div>
    <div class="panel"><h2><i class="bi bi-arrow-right-circle" aria-hidden="true"></i> แปลงเป็น MOU</h2>
      <?php if ($p['converted_mou_id']): ?><a class="btn btn-info" href="mou_view.php?id=<?= (int) $p['converted_mou_id'] ?>">ดู MOU ที่แปลงแล้ว</a>
      <?php else: ?><p class="small text-muted">สร้าง/ใช้หน่วยงานคู่ความร่วมมือให้อัตโนมัติ แล้วเปิดฟอร์ม MOU ที่เติมข้อมูลจากข้อเสนอ</p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="convert"><button class="btn btn-gold"><i class="bi bi-arrow-right-circle" aria-hidden="true"></i> แปลงเป็น MOU</button></form><?php endif; ?></div>
    <div class="panel"><h2><i class="bi bi-trash3" aria-hidden="true"></i> ลบข้อเสนอ</h2>
      <p class="small text-muted">ใช้กับข้อเสนอสแปมหรือส่งซ้ำ — ลบข้อมูลผู้เสนอและไฟล์แนบออกจากระบบถาวร<?= $p['converted_mou_id'] ? ' (MOU ที่แปลงไปแล้วไม่ถูกลบ)' : '' ?></p>
      <form method="post" data-confirm="ลบข้อเสนอ <?= e($p['ref_no']) ?> ถาวร?  กู้คืนไม่ได้"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-outline-danger"><i class="bi bi-trash3" aria-hidden="true"></i> ลบข้อเสนอนี้</button></form></div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer();
