(function(){
  var b=document.createElement('div');
  b.style.cssText='position:fixed;bottom:12px;left:50%;transform:translateX(-50%);z-index:99999;background:#0b2e6b;color:#fff;padding:8px 16px;border-radius:999px;font:14px/1.4 Sarabun,sans-serif;box-shadow:0 4px 16px rgba(0,0,0,.25)';
  b.innerHTML='โหมดสาธิต (Demo) — ข้อมูลสมมติ บันทึก/ส่งฟอร์มไม่ได้ · <a href="https://github.com/PrimeRvx/mou-system" id="demo-gh" style="color:#ffd54f">ดูโค้ดบน GitHub</a>';
  document.addEventListener('DOMContentLoaded',function(){document.body.appendChild(b);});
  document.addEventListener('submit',function(e){e.preventDefault();e.stopImmediatePropagation();alert('นี่คือเว็บสาธิต (HTML นิ่ง) จึงบันทึกหรือส่งข้อมูลไม่ได้\nติดตั้งระบบจริงด้วย XAMPP ตามคู่มือใน README');},true);
})();
