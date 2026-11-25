<?php
/**
 * admin/partners_json.php — รายชื่อหน่วยงานคู่ความร่วมมือ (JSON) สำหรับปุ่ม "โหลดรายชื่อหน่วยงานใหม่" ในฟอร์ม MOU
 * ให้เจ้าหน้าที่เพิ่มหน่วยงานในแท็บอื่นแล้วกลับมาเลือกได้ทันที โดยข้อมูลที่กรอกค้างไว้ไม่หาย
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/mou_model.php';
header('Cache-Control: no-store');
if (!session_is_valid() || !can('mou')) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo '[]';
    exit;
}
header('Content-Type: application/json; charset=utf-8');
$out = [];
foreach (all_partners() as $p) {
    $out[] = ['id' => (int) $p['id'], 'label' => $p['name'] . ' (' . partner_type_label($p['type']) . ')'];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
