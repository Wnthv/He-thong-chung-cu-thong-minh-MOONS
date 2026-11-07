/* Moons – tương tác phía trình duyệt (không phụ thuộc framework) */
(function () {
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const post = (url, data) => fetch(url, {
    method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-Token': APP.csrf },
    body: data instanceof FormData ? data : new URLSearchParams(Object.assign({ _csrf: APP.csrf }, data))
  }).then(r => r.json());

  /* ---- Modal ---- */
  function openModal(id) { const m = document.getElementById(id); if (m) { m.classList.add('open'); document.body.classList.add('modal-open'); } return m; }
  const closeModal = m => { m.classList.remove('open'); if (!document.querySelector('.modal.open')) document.body.classList.remove('modal-open'); };
  document.addEventListener('click', e => {
    const o = e.target.closest('[data-modal]');
    if (o) {
      const m = openModal(o.dataset.modal);
      if (m && o.dataset.fill) {           // điền dữ liệu từ thuộc tính data-fill='{"id":..}'
        const d = JSON.parse(o.dataset.fill);
        Object.keys(d).forEach(k => $$(`[data-f="${k}"]`, m).forEach(el => {
          if ('value' in el && el.tagName !== 'DIV' && el.tagName !== 'SPAN' && el.tagName !== 'B') el.value = d[k]; else el.textContent = d[k];
        }));
        if (d.qr) renderQR($('[data-qr-target]', m), d.qr);
      }
    }
    if (e.target.closest('[data-close]')) closeModal(e.target.closest('.modal'));
    if (e.target.classList.contains('modal')) closeModal(e.target);
  });

  /* ---- QR code ---- */
  function renderQR(el, text, size) {
    if (!el || typeof qrcode === 'undefined') return;
    const q = qrcode(0, 'M'); q.addData(text); q.make();
    el.innerHTML = q.createSvgTag({ cellSize: size || 5, margin: 0, scalable: false });
  }
  $$('[data-qr]').forEach(el => renderQR(el, el.dataset.qr, +el.dataset.size || 5));
  window.renderQR = renderQR;

  /* ---- Ẩn/hiện mật khẩu ---- */
  $$('[data-toggle-pw]').forEach(b => b.addEventListener('click', () => {
    const i = b.parentElement.querySelector('input'); i.type = i.type === 'password' ? 'text' : 'password';
  }));

  /* ---- Xem trước ảnh tải lên ---- */
  $$('.drop input[type=file]').forEach(inp => inp.addEventListener('change', () => {
    const f = inp.files[0], box = inp.closest('.drop'), span = $('.drop-t', box);
    if (!f) return;
    if (f.size > 5 * 1024 * 1024) { alert('Ảnh vượt quá 5MB.'); inp.value = ''; return; }
    let img = $('img', box); if (!img) { img = document.createElement('img'); box.insertBefore(img, span); }
    img.src = URL.createObjectURL(f); if (span) span.textContent = f.name;
  }));

  /* ---- OTP ---- */
  const otpForm = $('#otpForm');
  if (otpForm) {
    const boxes = $$('.otp-boxes input');
    boxes.forEach((b, i) => {
      b.addEventListener('input', () => { b.value = b.value.replace(/\D/g, '').slice(-1); if (b.value && boxes[i + 1]) boxes[i + 1].focus(); });
      b.addEventListener('keydown', e => { if (e.key === 'Backspace' && !b.value && boxes[i - 1]) boxes[i - 1].focus(); });
      b.addEventListener('paste', e => { const t = (e.clipboardData.getData('text') || '').replace(/\D/g, ''); if (t.length === 6) { e.preventDefault(); boxes.forEach((x, j) => x.value = t[j]); } });
    });
    let timer;
    const send = () => {
      const login = $('#login').value.trim();
      if (!login) { alert('Vui lòng nhập số điện thoại hoặc email đã đăng ký.'); return; }
      post(otpForm.dataset.send, { login }).then(r => {
        if (!r.ok) { alert(r.error); return; }
        $('#otpTo').textContent = r.to; openModal('otpModal'); boxes[0].focus();
        if (r.otp) { const bn = $('#otpBanner'); $('b', bn).textContent = r.otp; bn.classList.add('show'); setTimeout(() => bn.classList.remove('show'), 15000); }
        let s = r.ttl; clearInterval(timer); $('#otpLeft').textContent = s;
        timer = setInterval(() => { s--; $('#otpLeft').textContent = Math.max(0, s); if (s <= 0) clearInterval(timer); }, 1000);
      });
    };
    $('#otpLink').addEventListener('click', send);
    $('#otpResend').addEventListener('click', send);
    otpForm.addEventListener('submit', e => {
      e.preventDefault();
      post(otpForm.action, { otp: boxes.map(b => b.value).join('') }).then(r => { if (r.ok) location = r.redirect; else { $('#otpErr').textContent = r.error; } });
    });
  }

  /* ---- Thanh toán VNPay ---- */
  const payBtn = $('#payBtn');
  if (payBtn) payBtn.addEventListener('click', () => {
    payBtn.disabled = true;
    post(payBtn.dataset.url, { id: payBtn.dataset.id }).then(r => {
      payBtn.disabled = false;
      if (!r.ok) { alert(r.error); return; }
      const m = openModal('vnpModal'); renderQR($('[data-qr-target]', m), r.url, 4);
      $('#vnpGo').onclick = () => location = r.url;
    });
  });

  /* ---- Chọn khung giờ tiện ích ---- */
  $$('.slot.free').forEach(s => s.addEventListener('click', () => {
    $$('.slot.sel').forEach(x => x.classList.remove('sel')); s.classList.add('sel');
    $('#startInput').value = s.dataset.start; $('#bookBtn').disabled = false;
  }));

  /* ---- Trợ lý AI ---- */
  const chat = $('#chat');
  if (chat) {
    const body = $('#chatBody'), form = $('#chatForm');
    $$('[data-open-chat]').forEach(b => b.addEventListener('click', () => { chat.classList.add('open'); $('input', form).focus(); }));
    $('[data-close-chat]').addEventListener('click', () => chat.classList.remove('open'));
    const add = (cls, text, src) => {
      const d = document.createElement('div'); d.className = 'msg ' + cls; d.textContent = text;
      if (src) { const s = document.createElement('div'); s.className = 'src'; s.textContent = 'Nguồn: ' + src; d.appendChild(s); }
      body.appendChild(d); body.scrollTop = body.scrollHeight; return d;
    };
    const ask = q => {
      if (!q.trim()) return;
      add('me', q); const t = add('bot typing', 'Moons Bot đang soạn câu trả lời...');
      post(APP.ask, { q }).then(r => { t.remove(); add('bot', r.ok ? r.answer : r.error, r.source); })
        .catch(() => { t.remove(); add('bot', 'Không kết nối được máy chủ. Vui lòng thử lại.'); });
    };
    form.addEventListener('submit', e => { e.preventDefault(); const i = $('input', form); ask(i.value); i.value = ''; });
    $$('[data-chip]').forEach(c => c.addEventListener('click', () => ask(c.textContent)));
  }

  /* ---- Kanban kéo thả (kỹ thuật viên) ---- */
  let dragId = null;
  $$('.kcard[draggable=true]').forEach(c => c.addEventListener('dragstart', () => { dragId = c.dataset.id; }));
  $$('.kcol[data-col]').forEach(col => {
    col.addEventListener('dragover', e => { e.preventDefault(); col.classList.add('over'); });
    col.addEventListener('dragleave', () => col.classList.remove('over'));
    col.addEventListener('drop', e => {
      e.preventDefault(); col.classList.remove('over'); if (!dragId) return;
      const to = col.dataset.col;
      if (to === 'cho_nghiem_thu') { const card = $(`.kcard[data-id="${dragId}"]`); $('[data-f="id"]', openModal('acceptModal')).value = dragId; $('[data-f="title"]', $('#acceptModal')).textContent = card.dataset.title; return; }
      let note = '';
      if (to === 'cho_linh_kien') { note = prompt('Tên vật tư/linh kiện cần chờ:') || ''; if (!note) return; }
      post(APP.move, { id: dragId, to, note }).then(r => { if (r.ok) location.reload(); else alert(r.error); });
    });
  });

  /* ---- Gạch nợ thủ công: tải hóa đơn chưa thanh toán theo mã căn hộ ---- */
  const aptInp = $('#manualApt');
  if (aptInp) aptInp.addEventListener('change', () => {
    fetch(APP.unpaid + '&apt=' + encodeURIComponent(aptInp.value.trim()), { headers: { Accept: 'application/json' } }).then(r => r.json()).then(list => {
      const sel = $('#manualInvoice'); sel.innerHTML = '';
      if (!list.length) { sel.innerHTML = '<option value="">Không có hóa đơn chưa thanh toán</option>'; $('#manualAmount').value = ''; return; }
      list.forEach(h => { const o = document.createElement('option'); o.value = h.ID_hoa_don; o.dataset.amount = Math.round(h.tong_tien); o.textContent = `${h.ma_hoa_don} – ${Number(h.tong_tien).toLocaleString('vi-VN')} đ`; sel.appendChild(o); });
      sel.dispatchEvent(new Event('change'));
    });
  });
  const invSel = $('#manualInvoice');
  if (invSel) invSel.addEventListener('change', () => { const o = invSel.selectedOptions[0]; $('#manualAmount').value = o && o.dataset.amount ? Number(o.dataset.amount).toLocaleString('vi-VN') : ''; });

  /* ---- Bảng nhập chỉ số: cảnh báo thời gian thực ---- */
  $$('tr[data-reading]').forEach(tr => {
    const oldD = +tr.dataset.oldD, oldW = +tr.dataset.oldW, avgD = +tr.dataset.avgD, avgW = +tr.dataset.avgW;
    const d = $('[data-k=dien]', tr), w = $('[data-k=nuoc]', tr), warn = $('.warn', tr), note = $('[data-k=ghi_chu]', tr);
    const check = () => {
      const msgs = []; let bad = false;
      if (d.value !== '' && +d.value < oldD) { msgs.push('Điện mới < điện cũ'); bad = true; }
      if (w.value !== '' && +w.value < oldW) { msgs.push('Nước mới < nước cũ'); bad = true; }
      const anomaly = (avgD > 0 && d.value !== '' && +d.value - oldD > 3 * avgD) || (avgW > 0 && w.value !== '' && +w.value - oldW > 3 * avgW);
      if (anomaly) msgs.push('Tiêu thụ tăng vọt trên 300% – nhập ghi chú xác minh');
      d.classList.toggle('err', d.value !== '' && +d.value < oldD); w.classList.toggle('err', w.value !== '' && +w.value < oldW);
      tr.classList.toggle('err', bad || anomaly); warn.textContent = msgs.join('; '); note.classList.toggle('hidden', !anomaly && !note.value);
    };
    [d, w].forEach(i => i.addEventListener('input', check)); check();
  });

  /* ---- Biểu đồ Dashboard ---- */
  const rev = $('#revChart');
  if (rev && window.Chart) {
    Chart.defaults.font.family = '"Be Vietnam Pro", system-ui, sans-serif';
    new Chart(rev, { type: 'bar', data: { labels: JSON.parse(rev.dataset.labels), datasets: [{ label: 'Doanh thu thực thu (Tỷ VNĐ)', data: JSON.parse(rev.dataset.values), backgroundColor: '#5F7B6F' }] },
      options: { maintainAspectRatio: false, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } } });
    const dn = $('#debtChart');
    new Chart(dn, { type: 'doughnut', data: { labels: JSON.parse(dn.dataset.labels), datasets: [{ data: JSON.parse(dn.dataset.values), backgroundColor: ['#5F7B6F', '#A3C9AA', '#B7DCC0', '#D8CDA3'] }] },
      options: { maintainAspectRatio: false, cutout: '55%', plugins: { legend: { position: 'top' } } } });
  }
})();
