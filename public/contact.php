<?php
/** public/contact.php — หน้าติดต่อและประสานงาน + ฟอร์มเสนอความร่วมมือ (2.5) */
require_once __DIR__ . '/../includes/layout_public.php';

/** ตรวจ reCAPTCHA / Turnstile (ทำงานเมื่อกำหนดค่าใน config เท่านั้น) */
function verify_captcha(): bool
{
    if (CAPTCHA_PROVIDER === '' || CAPTCHA_SECRET_KEY === '') {
        return true;
    }
    $field = CAPTCHA_PROVIDER === 'turnstile' ? 'cf-turnstile-response' : 'g-recaptcha-response';
    $token = $_POST[$field] ?? '';
    if ($token === '') {
        return false;
    }
    $endpoint = CAPTCHA_PROVIDER === 'turnstile'
        ? 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
        : 'https://www.google.com/recaptcha/api/siteverify';
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'timeout' => 8,
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query(['secret' => CAPTCHA_SECRET_KEY, 'response' => $token, 'remoteip' => client_ip()]),
    ]]);
    $res = @file_get_contents($endpoint, false, $ctx);
    $j = $res ? json_decode($res, true) : null;
    return !empty($j['success']);
}

$programs = all_programs();
$cats     = all_categories();
$errors   = [];
$old      = [];
$done     = $_SESSION['proposal_done'] ?? null;
unset($_SESSION['proposal_done']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    // ค่าที่กรอกไว้ (ใช้เติมฟอร์มกลับ): รับเฉพาะข้อความ หรือรายการของข้อความ — กันคำขอปลอมที่ส่งค่าซ้อนกันมาจนเกิด Warning
    foreach ($_POST as $k => $v) {
        if (is_string($v)) {
            $old[$k] = $v;
        } elseif (is_array($v)) {
            $old[$k] = array_values(array_filter($v, 'is_string'));
        }
    }

    // --- ป้องกันสแปม ---
    if (post('website_url') !== '') {                       // honeypot: บอทมักกรอกช่องที่ซ่อนไว้
        redirect(route('contact'));
    }
    $formTime = (int) ($_SESSION['proposal_form_time'] ?? 0);
    if ($formTime && time() - $formTime < 4) {              // กรอกเร็วผิดปกติ (< 4 วินาที)
        $errors[] = 'ส่งแบบฟอร์มเร็วเกินไป กรุณาลองใหม่อีกครั้ง';
    }
    if (rate_exceeded('proposal', PROPOSAL_LIMIT_COUNT, PROPOSAL_LIMIT_MINUTES)) {
        $errors[] = 'ส่งข้อเสนอบ่อยเกินไป กรุณารอ ' . PROPOSAL_LIMIT_MINUTES . ' นาทีแล้วลองใหม่';
    }
    if (!verify_captcha()) {
        $errors[] = 'กรุณายืนยันว่าไม่ใช่โปรแกรมอัตโนมัติ';
    }

    // --- ตรวจข้อมูล ---
    $org   = mb_substr(post('org_name'), 0, 255);
    $type  = post('org_type');
    $cname = mb_substr(post('contact_name'), 0, 150);
    $phone = post('phone');
    $email = post('email');
    $prov  = mb_substr(post('province'), 0, 100);
    $addr  = mb_substr(post('address'), 0, 1000);
    $detail = mb_substr(post('details'), 0, 5000);
    // key = ชื่อช่อง → แสดงข้อความใต้ช่องนั้น + ลิงก์จากกล่องสรุป
    if ($org === '')   { $errors['org_name'] = 'กรุณากรอกชื่อหน่วยงาน'; }
    if (!isset(partner_types()[$type])) { $errors['org_type'] = 'กรุณาเลือกประเภทหน่วยงาน'; }
    if ($cname === '') { $errors['contact_name'] = 'กรุณากรอกชื่อผู้ติดต่อ'; }
    if (!valid_phone($phone)) { $errors['phone'] = 'เบอร์โทรศัพท์ไม่ถูกต้อง (ตัวเลข 8–20 หลัก เช่น 02-123-4567)'; }
    if (!valid_email($email)) { $errors['email'] = 'อีเมลไม่ถูกต้อง (เช่น name@example.com)'; }
    if (mb_strlen($detail) < 10) { $errors['details'] = 'กรุณาอธิบายรายละเอียดข้อเสนออย่างน้อย 10 ตัวอักษร'; }
    if (empty($_POST['pdpa'])) { $errors['pdpa'] = 'กรุณายอมรับการเก็บและใช้ข้อมูลเพื่อติดต่อกลับ'; }

    $validProg = array_column($programs, 'id');
    $progIds = array_values(array_intersect(array_map('intval', (array) ($_POST['programs'] ?? [])), array_map('intval', $validProg)));
    $validCat = array_column($cats, 'name');
    $coop = array_values(array_intersect(array_map('strval', (array) ($_POST['coop_type'] ?? [])), $validCat));
    $otherCoop = mb_substr(post('coop_other'), 0, 100);
    if ($otherCoop !== '') { $coop[] = $otherCoop; }

    // --- ไฟล์แนบ (PDF ≤ 5MB, ไม่บังคับ) ---
    $att = null;
    if (!$errors && !empty($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $att = save_upload($_FILES['attachment'], ['application/pdf' => 'pdf'], MAX_PROPOSAL_FILE_SIZE, 'proposals');
        if (isset($att['error'])) { $errors['attachment'] = $att['error']; $att = null; }
    }

    if (!$errors) {
        $tmpRef = 'TMP' . bin2hex(random_bytes(8));
        $pid = db_insert(
            'INSERT INTO proposals (ref_no, org_name, org_type, contact_name, phone, email, province, address,
                    programs_interest, coop_type, details, attachment, attachment_name, ip_address)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$tmpRef, $org, $type, $cname, $phone, $email, $prov ?: null, $addr ?: null,
             implode(',', $progIds) ?: null, implode(', ', $coop) ?: null, $detail,
             $att['stored'] ?? null, $att['name'] ?? null, client_ip()]
        );
        $ref = sprintf('PRP-%d-%05d', (int) date('Y') + 543, $pid);   // เลขอ้างอิง เช่น PRP-2569-00001
        db_exec('UPDATE proposals SET ref_no = ? WHERE id = ?', [$ref, $pid]);
        rate_hit('proposal');

        notify_staff('proposal', "ข้อเสนอความร่วมมือใหม่ $ref จาก $org", 'admin/proposal_view.php?id=' . $pid);
        $to = setting('notify_email');
        if ($to !== '' && valid_email($to)) {                 // อีเมลแจ้งเจ้าหน้าที่ (ถ้าตั้งค่าไว้)
            $body = "มีข้อเสนอความร่วมมือใหม่\nเลขอ้างอิง: $ref\nหน่วยงาน: $org\nผู้ติดต่อ: $cname\nโทร: $phone\n\nตรวจสอบที่: "
                  . url('admin/proposal_view.php?id=' . $pid);
            send_text_mail($to, "ข้อเสนอความร่วมมือใหม่ $ref", $body);
        }
        $_SESSION['proposal_done'] = $ref;
        unset($_SESSION['proposal_form_time']);
        redirect(route('contact') . '#proposal');             // PRG: กัน refresh แล้วส่งซ้ำ
    }
}
$_SESSION['proposal_form_time'] = time();

public_header('ติดต่อและเสนอความร่วมมือ', 'ข้อมูลติดต่อฝ่ายประสานงานความร่วมมือ ขั้นตอนการทำ MOU และแบบฟอร์มเสนอความร่วมมือสำหรับหน่วยงานภายนอก', ['active' => 'contact']);
$lat = setting('map_lat', '13.6685'); $lng = setting('map_lng', '100.6047');
$o = fn(string $k, string $d = '') => e(is_string($old[$k] ?? null) ? $old[$k] : $d);
// attribute ของช่อง: id + class ตอนผิด + aria — $fa('email') → ' id="f-email" class="form-control is-invalid" aria-invalid=…'
$fa = fn(string $k, string $cls = 'form-control') => ' id="' . fid($k) . '" class="' . $cls . field_err_class($errors, $k) . '"' . field_err_attrs($errors, $k);
$hadFile = !empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE;
?>
<div class="container mt-4">
  <nav aria-label="breadcrumb" class="fade-down"><ol class="breadcrumb mb-2">
    <li class="breadcrumb-item"><a href="<?= e(route('home')) ?>"><i class="bi bi-house" aria-hidden="true"></i> หน้าหลัก</a></li>
    <li class="breadcrumb-item active" aria-current="page">ติดต่อ/เสนอความร่วมมือ</li></ol></nav>
  <div class="section-kicker">ติดต่อและเสนอความร่วมมือ</div>
  <h1 class="section-title h3 fade-up">ติดต่อและประสานงาน</h1>
  <div class="row g-4">
    <div class="col-lg-5 stagger">
      <div class="detail-box">
        <h2 class="box-title"><i class="bi bi-person-lines-fill" aria-hidden="true"></i> ฝ่ายประสานงานความร่วมมือ</h2>
        <p class="mb-2"><i class="bi text-gold me-1 bi-person" aria-hidden="true"></i> <?= e(setting('contact_name')) ?></p>
        <p class="mb-2"><i class="bi text-gold me-1 bi-telephone" aria-hidden="true"></i> <span class="visually-hidden">โทรศัพท์: </span><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a></p>
        <p class="mb-2"><i class="bi text-gold me-1 bi-envelope" aria-hidden="true"></i> <span class="visually-hidden">อีเมล: </span><a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></p>
        <p class="mb-2"><i class="bi text-gold me-1 bi-geo-alt" aria-hidden="true"></i> <span class="visually-hidden">ที่อยู่: </span><?= e(setting('contact_address')) ?></p>
        <p class="mb-0"><i class="bi text-gold me-1 bi-clock" aria-hidden="true"></i> <span class="visually-hidden">เวลาทำการ: </span><?= e(setting('office_hours')) ?></p>
      </div>
      <div class="detail-box p-2">
        <iframe title="แผนที่ที่ตั้ง <?= e(setting('college_name', 'วิทยาลัยพณิชยการบางนา')) ?>" width="100%" height="280" style="border:0;border-radius:var(--r-md)" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
          src="https://www.google.com/maps?q=<?= e(rawurlencode($lat . ',' . $lng)) ?>&z=16&output=embed"></iframe>
      </div>
      <div class="detail-box">
        <h2 class="box-title"><i class="bi bi-signpost-split" aria-hidden="true"></i> ขั้นตอนการทำ MOU กับวิทยาลัย</h2>
        <ol class="timeline list-unstyled mb-0">
          <?php foreach ([
            ['ติดต่อ/เสนอความร่วมมือ', 'ส่งแบบฟอร์มเสนอความร่วมมือในหน้านี้ หรือติดต่อฝ่ายประสานงานโดยตรง'],
            ['พิจารณาข้อเสนอ', 'วิทยาลัยพิจารณาความเหมาะสมและความต้องการของทั้งสองฝ่าย'],
            ['ประชุมหารือและร่างข้อตกลง', 'กำหนดขอบเขต สิทธิประโยชน์ และระยะเวลาความร่วมมือ'],
            ['ตรวจสอบและขออนุมัติ', 'ตรวจร่างข้อตกลงและขออนุมัติตามขั้นตอนของวิทยาลัย'],
            ['ลงนามและเผยแพร่', 'ลงนามบันทึกข้อตกลง และเผยแพร่ข้อมูลบนเว็บไซต์นี้'],
            ['ดำเนินกิจกรรมและติดตามผล', 'ดำเนินการตามข้อตกลง รายงานผล และต่ออายุเมื่อครบกำหนด'],
          ] as $i => $st): ?>
          <li class="step" data-n="<?= $i + 1 ?>"><strong><?= e($st[0]) ?></strong><div class="small text-muted"><?= e($st[1]) ?></div></li>
          <?php endforeach; ?>
        </ol>
      </div>
    </div>

    <div class="col-lg-7" id="proposal">
      <div class="detail-box p-md-4 fade-up delay-2">
        <h2 class="box-title"><i class="bi bi-send" aria-hidden="true"></i> แบบฟอร์มเสนอความร่วมมือสำหรับหน่วยงานภายนอก</h2>
        <?php if ($done): ?>
          <div class="alert alert-success scale-in text-center py-4" role="status" tabindex="-1" data-autofocus>
            <i class="bi bi-check-circle-fill display-4 d-block mb-2" aria-hidden="true"></i><h3 class="h5 alert-heading">ส่งข้อเสนอเรียบร้อยแล้ว</h3>
            <p class="mb-1">เลขอ้างอิงคำขอของท่านคือ <strong class="fs-4 chip chip-gold px-3"><?= e($done) ?></strong></p>
            <p class="mb-0 small">กรุณาเก็บเลขนี้ไว้อ้างอิง เจ้าหน้าที่จะติดต่อกลับตามอีเมล/เบอร์โทรที่ท่านระบุ</p>
          </div>
        <?php else: ?>
        <p class="small text-muted mb-3"><span class="text-danger" aria-hidden="true">*</span> หมายถึงช่องที่ต้องกรอก</p>
        <?= error_summary($errors, $hadFile ? 'หากแนบไฟล์ไว้ กรุณาเลือกไฟล์ใหม่อีกครั้ง (เบราว์เซอร์ไม่เก็บไฟล์ไว้หลังส่งไม่สำเร็จ)' : '') ?>
        <form method="post" enctype="multipart/form-data" id="proposalForm" novalidate data-dirty-guard>
          <?= csrf_field() ?>
          <div class="hp-field" aria-hidden="true"><label>เว็บไซต์ (ห้ามกรอก)<input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>
          <div class="row g-3">
            <div class="col-md-8"><label class="form-label" for="<?= fid('org_name') ?>">ชื่อหน่วยงาน <span class="text-danger" aria-hidden="true">*</span></label>
              <input name="org_name"<?= $fa('org_name') ?> maxlength="255" required autocomplete="organization" value="<?= $o('org_name') ?>"><?= field_err_msg($errors, 'org_name') ?></div>
            <div class="col-md-4"><label class="form-label" for="<?= fid('org_type') ?>">ประเภท <span class="text-danger" aria-hidden="true">*</span></label>
              <select name="org_type"<?= $fa('org_type', 'form-select') ?> required><option value="">เลือก...</option>
                <?php foreach (partner_types() as $k => $l): ?><option value="<?= e($k) ?>" <?= ($old['org_type'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= field_err_msg($errors, 'org_type') ?></div>
            <div class="col-md-6"><label class="form-label" for="<?= fid('contact_name') ?>">ชื่อผู้ติดต่อ <span class="text-danger" aria-hidden="true">*</span></label>
              <input name="contact_name"<?= $fa('contact_name') ?> maxlength="150" required autocomplete="name" value="<?= $o('contact_name') ?>"><?= field_err_msg($errors, 'contact_name') ?></div>
            <div class="col-md-6"><label class="form-label" for="<?= fid('phone') ?>">เบอร์โทรศัพท์ <span class="text-danger" aria-hidden="true">*</span></label>
              <input name="phone" type="tel"<?= $fa('phone') ?> maxlength="20" required autocomplete="tel" inputmode="tel" value="<?= $o('phone') ?>"><?= field_err_msg($errors, 'phone') ?></div>
            <div class="col-md-6"><label class="form-label" for="<?= fid('email') ?>">อีเมล <span class="text-danger" aria-hidden="true">*</span></label>
              <input name="email" type="email"<?= $fa('email') ?> maxlength="150" required autocomplete="email" value="<?= $o('email') ?>"><?= field_err_msg($errors, 'email') ?></div>
            <div class="col-md-6"><label class="form-label" for="<?= fid('province') ?>">จังหวัด</label>
              <input name="province"<?= $fa('province') ?> maxlength="100" autocomplete="address-level1" value="<?= $o('province') ?>"></div>
            <div class="col-12"><label class="form-label" for="<?= fid('address') ?>">ที่อยู่หน่วยงาน</label>
              <textarea name="address"<?= $fa('address') ?> rows="2" maxlength="1000" autocomplete="street-address"><?= $o('address') ?></textarea></div>
            <fieldset class="col-12 form-group-set"><legend class="form-label">สาขาวิชาที่สนใจ</legend><div class="row">
              <?php foreach ($programs as $p): ?><div class="col-sm-6"><div class="form-check">
                <input class="form-check-input" type="checkbox" name="programs[]" id="pg<?= (int) $p['id'] ?>" value="<?= (int) $p['id'] ?>" <?= in_array((string) $p['id'], array_map('strval', (array) ($old['programs'] ?? [])), true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="pg<?= (int) $p['id'] ?>"><?= e($p['level'] . ' ' . $p['name']) ?></label></div></div><?php endforeach; ?></div></fieldset>
            <fieldset class="col-12 form-group-set"><legend class="form-label">รูปแบบความร่วมมือที่ต้องการ</legend><div class="row">
              <?php foreach ($cats as $c): ?><div class="col-sm-6"><div class="form-check">
                <input class="form-check-input" type="checkbox" name="coop_type[]" id="ct<?= (int) $c['id'] ?>" value="<?= e($c['name']) ?>" <?= in_array($c['name'], (array) ($old['coop_type'] ?? []), true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="ct<?= (int) $c['id'] ?>"><?= e($c['name']) ?></label></div></div><?php endforeach; ?></div>
              <label class="form-label small mt-2 mb-1" for="<?= fid('coop_other') ?>">รูปแบบอื่นๆ (ถ้ามี)</label>
              <input name="coop_other"<?= $fa('coop_other', 'form-control form-control-sm') ?> maxlength="100" value="<?= $o('coop_other') ?>"></fieldset>
            <div class="col-12"><label class="form-label" for="<?= fid('details') ?>">รายละเอียดข้อเสนอ <span class="text-danger" aria-hidden="true">*</span></label>
              <textarea name="details"<?= $fa('details') ?> rows="5" maxlength="5000" required aria-describedby="<?= fid('details') ?>-help<?= isset($errors['details']) ? ' ' . fid('details') . '-err' : '' ?>"><?= $o('details') ?></textarea>
              <div class="form-text" id="<?= fid('details') ?>-help">อย่างน้อย 10 ตัวอักษร — เช่น วัตถุประสงค์ รูปแบบความร่วมมือ และช่วงเวลาที่สะดวก</div><?= field_err_msg($errors, 'details') ?></div>
            <div class="col-12"><label class="form-label" for="<?= fid('attachment') ?>">แนบไฟล์ (PDF ไม่เกิน 5 MB)</label>
              <input type="file" name="attachment"<?= $fa('attachment') ?> accept="application/pdf,.pdf" aria-describedby="<?= fid('attachment') ?>-help<?= isset($errors['attachment']) ? ' ' . fid('attachment') . '-err' : '' ?>">
              <div class="form-text" id="<?= fid('attachment') ?>-help"><i class="bi bi-info-circle" aria-hidden="true"></i> ไม่บังคับ — เช่น หนังสือแนะนำหน่วยงาน หรือร่างข้อเสนอ</div><?= field_err_msg($errors, 'attachment') ?></div>
            <?php if (CAPTCHA_PROVIDER === 'turnstile'): ?>
              <div class="col-12"><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><div class="cf-turnstile" data-sitekey="<?= e(CAPTCHA_SITE_KEY) ?>"></div></div>
            <?php elseif (CAPTCHA_PROVIDER === 'recaptcha'): ?>
              <div class="col-12"><script src="https://www.google.com/recaptcha/api.js" async defer></script><div class="g-recaptcha" data-sitekey="<?= e(CAPTCHA_SITE_KEY) ?>"></div></div>
            <?php endif; ?>
            <div class="col-12"><div class="form-check">
              <input<?= $fa('pdpa', 'form-check-input') ?> type="checkbox" name="pdpa" value="1" required <?= !empty($old['pdpa']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="<?= fid('pdpa') ?>">ข้าพเจ้ายินยอมให้วิทยาลัยเก็บและใช้ข้อมูลที่กรอกเพื่อติดต่อกลับและพิจารณาความร่วมมือเท่านั้น <span class="text-danger" aria-hidden="true">*</span></label><?= field_err_msg($errors, 'pdpa') ?></div></div>
            <div class="col-12"><button type="submit" class="btn btn-gold btn-lg px-4"><i class="bi bi-send" aria-hidden="true"></i> ส่งข้อเสนอ</button></div>
          </div>
        </form>
        <script nonce="<?= e(csp_nonce()) ?>">
        // ตรวจฝั่งไคลเอนต์ (เซิร์ฟเวอร์ตรวจซ้ำเสมอ) — แสดงข้อความใต้ช่อง + กล่องสรุปที่รับ focus (app.js: MouForm)
        document.getElementById('proposalForm').addEventListener('submit', function (ev) {
          var f = ev.target, errs = [];
          if (!f.org_name.value.trim()) errs.push(['org_name', 'กรุณากรอกชื่อหน่วยงาน']);
          if (!f.org_type.value) errs.push(['org_type', 'กรุณาเลือกประเภทหน่วยงาน']);
          if (!f.contact_name.value.trim()) errs.push(['contact_name', 'กรุณากรอกชื่อผู้ติดต่อ']);
          if (!/^[0-9+\-\s()#,.extEXT]{8,20}$/.test(f.phone.value.trim())) errs.push(['phone', 'เบอร์โทรศัพท์ไม่ถูกต้อง (ตัวเลข 8–20 หลัก เช่น 02-123-4567)']);
          if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(f.email.value.trim())) errs.push(['email', 'อีเมลไม่ถูกต้อง (เช่น name@example.com)']);
          if (f.details.value.trim().length < 10) errs.push(['details', 'กรุณาอธิบายรายละเอียดข้อเสนออย่างน้อย 10 ตัวอักษร']);
          var fl = f.attachment.files[0];
          if (fl && (fl.size > 5 * 1024 * 1024 || !/\.pdf$/i.test(fl.name))) errs.push(['attachment', 'ไฟล์แนบต้องเป็น PDF ไม่เกิน 5 MB']);
          if (!f.pdpa.checked) errs.push(['pdpa', 'กรุณายอมรับการเก็บและใช้ข้อมูลเพื่อติดต่อกลับ']);
          if (errs.length) { ev.preventDefault(); ev.stopImmediatePropagation(); MouForm.showErrors(f, errs); }
        });
        </script>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php public_footer();
