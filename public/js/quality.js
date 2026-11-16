'use strict';
(() => {
  const $ = (s, r = document) => r.querySelector(s),
    root = $('#quality-results');
  if (!root) return;
  const esc = (v) =>
    String(v ?? '').replace(
      /[&<>"']/g,
      (c) =>
        ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#39;',
        })[c],
    );
  const labels = {
    awaiting_inspection: 'بانتظار الفحص',
    awaiting_approval: 'بانتظار الاعتماد',
    approved: 'معتمد بالإفراج',
    rejected: 'منتهٍ دون إفراج',
    cancelled: 'ملغى',
    released: 'مفرج عنها',
    quarantine: 'حجر جودة',
    blocked: 'موقوف',
    pass: 'ناجح',
    fail: 'غير مطابق',
    not_applicable: 'لا ينطبق',
  };
  const state = { page: 1, heldPage: 1, data: null, access: null };
  const dialog = $('#quality-dialog'),
    content = $('#quality-dialog-content');
  const uuid = () => {
    if (crypto.randomUUID) return crypto.randomUUID();
    const b = crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 15) | 64;
    b[8] = (b[8] & 63) | 128;
    const h = [...b].map((v) => v.toString(16).padStart(2, '0')).join('');
    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
  };
  const date = (v) =>
    v
      ? new Date(v).toLocaleString('ar-SA', {
          calendar: 'gregory',
          timeZone: document.body.dataset.timezone || 'Asia/Riyadh',
        })
      : 'لم يسجل';
  const badge = (s) => `<span class="quality-badge ${esc(s)}">${esc(labels[s] || s)}</span>`;
  const button = (text, action, id = '') =>
    `<button type="button" class="secondary-btn" data-quality="${action}" data-id="${id}">${esc(text)}</button>`;
  const note = (name, label) =>
    `<label>${esc(label)}<textarea name="${name}" required minlength="3" maxlength="2000"></textarea></label>`;
  const form = (action, id, body) =>
    `<form class="quality-form" data-quality-form="${action}" data-id="${id}" data-request-id="${uuid()}">${body}<div class="quality-error" role="alert"></div><div class="quality-actions"><button class="primary-btn" type="submit">${action === 'inspect' ? 'حفظ النتائج وإرسالها للاعتماد' : action === 'decide' ? 'تسجيل القرار وإشعار الأطراف' : 'حفظ'}</button><button type="button" class="secondary-btn" data-quality-close>إغلاق</button></div></form>`;
  async function api(url, data) {
    const opts = { headers: { Accept: 'application/json' } };
    if (data !== undefined) {
      opts.method = 'POST';
      opts.headers['Content-Type'] = 'application/json';
      opts.headers['X-CSRF-TOKEN'] = $('meta[name="csrf-token"]').content;
      opts.body = JSON.stringify(data);
    }
    const r = await fetch(url, opts);
    if ([401, 419].includes(r.status)) {
      location.href = '/login';
      throw new Error('انتهت الجلسة.');
    }
    let d;
    try {
      d = await r.json();
    } catch {
      throw new Error('تعذر قراءة الرد؛ أعد المحاولة بعد التحقق من الاتصال.');
    }
    if (!r.ok)
      throw new Error(
        d.errors ? Object.values(d.errors).flat().join('\n') : d.message || 'تعذر تنفيذ الإجراء.',
      );
    return d;
  }
  function toast(text) {
    const t = $('#quality-toast');
    t.textContent = text;
    t.hidden = false;
    clearTimeout(t.timer);
    t.timer = setTimeout(() => (t.hidden = true), 6500);
  }
  function show(title, body) {
    $('#quality-dialog-title').textContent = title;
    content.innerHTML = body;
    if (!dialog.open) dialog.showModal();
  }
  function pager(p, held = false) {
    return `${p.current_page > 1 ? button('السابق', held ? 'held-page' : 'page', p.current_page - 1) : ''}<span>صفحة ${p.current_page} من ${p.last_page} • ${p.total} سجل</span>${p.current_page < p.last_page ? button('التالي', held ? 'held-page' : 'page', p.current_page + 1) : ''}`;
  }
  function card(i) {
    return `<article class="quality-card"><div class="quality-card-top"><div><h3>${esc(i.lot.product.name)}</h3><small>${esc(i.code)} • دفعة ${esc(i.lot.code)} • ${esc(i.warehouse.name)}</small></div>${badge(i.status)}</div><p>${esc(i.next_step)}</p><div class="quality-card-bottom"><span class="quality-owners">المسؤول: ${i.responsible.map((u) => esc(u.name)).join('، ') || (i.needs_assignment ? 'تدخل مدير الشركة مطلوب' : 'اكتملت المعالجة')}${i.overdue ? ' <span class="quality-alert">• تجاوز موعد المتابعة</span>' : ''}</span>${button('فتح ملف الفحص', 'open', i.id)}</div></article>`;
  }
  async function load() {
    const q = new URLSearchParams(new FormData($('#quality-filters')));
    q.set('page', state.page);
    q.set('held_page', state.heldPage);
    state.data = await api('/ops/quality?' + q);
    const d = state.data;
    $('#quality-metrics').innerHTML = [
      ['awaiting_inspection', 'بانتظار فاحص مستقل'],
      ['awaiting_approval', 'بانتظار قرار الاعتماد'],
      ['approved', 'طلبات معتمدة بالإفراج'],
      ['overdue', 'طلبات تجاوزت المتابعة'],
    ]
      .map(
        ([k, t]) =>
          `<article class="quality-metric"><span>${t}</span><strong>${Number(d.summary[k]).toLocaleString('ar-SA')}</strong></article>`,
      )
      .join('');
    root.innerHTML =
      d.records.data.map(card).join('') ||
      '<div class="quality-empty">لا توجد طلبات ضمن نطاقك ومرشحات البحث. إذا كنت مسؤول جودة فتأكد من إسناد مواقع لك بواسطة مدير الشركة.</div>';
    $('#quality-pagination').innerHTML = pager(d.records);
    $('#quality-access').hidden = !d.can_manage_access;
    $('#quality-held').innerHTML =
      d.held.data
        .map(
          (l) =>
            `<article class="quality-card"><div class="quality-card-top"><div><strong>${esc(l.product.name)}</strong><small>دفعة ${esc(l.code)}</small></div>${badge(l.status)}${d.can_request ? button('طلب فحص مستقل', 'request', l.id) : ''}</div></article>`,
        )
        .join('') || '<div class="quality-empty">لا توجد دفعات ظاهرة تحتاج فتح طلب فحص جديد.</div>';
    $('#quality-held-pagination').innerHTML = pager(d.held, true);
    $('#updated-at').textContent = 'آخر تحديث: ' + date(d.as_of);
  }
  function checkRow() {
    return `<div class="quality-check-row"><label>بند الفحص<input name="check_name" required minlength="2" maxlength="200" placeholder="مثل سلامة التغليف"></label><label>النتيجة<select name="check_result" required><option value="">اختر</option><option value="pass">ناجح</option><option value="fail">غير مطابق</option><option value="not_applicable">لا ينطبق</option></select></label><label>الملاحظة / القياس<input name="check_observation" required minlength="3" maxlength="1000" placeholder="القياس والمواصفة الفعلية"></label><button type="button" class="icon-btn" data-quality="remove-check" aria-label="حذف بند الفحص">×</button></div>`;
  }
  async function open(id) {
    const i = (await api('/ops/quality/' + Number(id))).inspection;
    const facts = [
      ['الصنف', i.lot.product.name],
      ['الدفعة', i.lot.code],
      ['الموقع', i.warehouse.name],
      ['طالب الفحص', i.requester.name],
      ['الفاحص', i.inspector?.name || 'لم يفحص بعد'],
      ...(i.inspection_authority
        ? [['تفويض الفحص', i.inspection_authority.principal.name + ' • ' + i.inspection_authority.code]]
        : []),
      ...(i.decision_authority
        ? [['تفويض الاعتماد', i.decision_authority.principal.name + ' • ' + i.decision_authority.code]]
        : []),
      ['المعتمد', i.decider?.name || 'لم يعتمد بعد'],
      ['حالة الدفعة', labels[i.lot.status]],
      ['موعد المتابعة', date(i.due_at)],
      ['حجم العينة', i.sample_size || 'لم يسجل'],
    ];
    let body = `${badge(i.status)}<dl class="quality-facts">${facts.map(([k, v]) => `<div><dt>${esc(k)}</dt><dd>${esc(v)}</dd></div>`).join('')}</dl><div class="quality-hint">${esc(i.next_step)}<br>المخولون الآن: ${i.responsible.map((u) => esc(u.name)).join('، ') || 'راجع مسؤول الشركة عند وجود طلب مفتوح.'}</div><h3>سبب الطلب</h3><p class="quality-pre">${esc(i.request_note)}</p>`;
    if (i.checks?.length)
      body += `<h3>نتائج الفحص المحفوظة</h3><table class="quality-checks"><thead><tr><th>الفحص</th><th>النتيجة</th><th>الملاحظة</th></tr></thead><tbody>${i.checks.map((c) => `<tr><td>${esc(c.name)}</td><td>${esc(labels[c.result])}</td><td>${esc(c.observation)}</td></tr>`).join('')}</tbody></table><p class="quality-pre">${esc(i.inspection_note)}</p><p>توصية الفاحص: ${badge(i.recommendation)} • ${esc(date(i.inspected_at))}</p>`;
    if (i.decision_note)
      body += `<h3>القرار وملاحظاته</h3><p class="quality-pre">${esc(i.decision_note)}</p><p>${esc(date(i.decided_at))}</p>`;
    if (i.can_inspect)
      body += form(
        'inspect',
        i.id,
        `<input type="hidden" name="version" value="${i.version}"><div class="quality-hint">استخدم المواصفات المعتمدة لدى مصنعك، واكتب لكل بند نتيجته والقياس أو الملاحظة المستند إليها.</div><label>حجم العينة<input name="sample_size" type="number" min="1" max="1000000" required></label><div id="quality-check-rows">${checkRow()}</div>${button('إضافة بند فحص', 'add-check')}<label>التوصية<select name="recommendation" required><option value="">اختر</option><option value="released">يوصى بالإفراج</option><option value="quarantine">استمرار الحجر</option><option value="blocked">إيقاف الصرف</option></select></label>${note('inspection_note', 'تفاصيل العينة والمواصفات ونتيجة الفحص')}`,
      );
    if (i.can_decide)
      body += form(
        'decide',
        i.id,
        `<input type="hidden" name="version" value="${i.version}"><label>القرار<select name="decision" required><option value="">اختر القرار</option>${i.recommendation === 'released' ? '<option value="released">اعتماد الإفراج</option>' : ''}<option value="quarantine">إبقاء الحجر</option><option value="blocked">إيقاف الصرف</option></select></label>${note('decision_note', 'سبب القرار ونتيجة مراجعة الفحص')}<label class="quality-ack"><input name="acknowledge" type="checkbox" required><span>راجعت نتائج الفحص والملاحظات، وأقر بالقرار ضمن صلاحيتي المستقلة.</span></label>`,
      );
    show('ملف الجودة • ' + i.code, body);
    history.replaceState(null, '', '/quality?inspection=' + i.id);
  }
  async function access() {
    state.access = await api('/ops/quality/access');
    const d = state.access;
    show(
      'مسؤولو الجودة ومواقع صلاحياتهم',
      `<p class="quality-pre">أنشئ فاحصًا أو معتمدًا من قسم الفريق، ثم حدد مواقعه هنا. دون مواقع لا يرى مسؤول الجودة دفعات الشركة. سحب الموقع يمنع الوصول إليه فورًا.</p>${form('access', '', `<label>حساب الجودة<select name="user_id" id="quality-access-user" required><option value="">اختر المستخدم</option>${d.users.map((u) => `<option value="${u.id}">${esc(u.name)} • ${u.role === 'quality_inspector' ? 'فاحص' : 'معتمد'} • ${esc(u.username)}</option>`).join('')}</select></label><div class="quality-site-list">${d.sites.map((s) => `<label><input type="checkbox" name="warehouse_ids" value="${s.id}"><span>${esc(s.name)}</span></label>`).join('')}</div>${note('reason', 'سبب منح أو تعديل نطاق الوصول')}`)}`,
    );
  }
  document.addEventListener('click', async (event) => {
    const b = event.target.closest('[data-quality],[data-quality-close]');
    if (!b) return;
    if (b.hasAttribute('data-quality-close')) {
      dialog.close();
      return;
    }
    try {
      const a = b.dataset.quality,
        id = Number(b.dataset.id);
      if (a === 'open') await open(id);
      if (a === 'page' || a === 'held-page') {
        state[a === 'page' ? 'page' : 'heldPage'] = id;
        await load();
      }
      if (a === 'add-check') $('#quality-check-rows').insertAdjacentHTML('beforeend', checkRow());
      if (a === 'remove-check') {
        if (document.querySelectorAll('.quality-check-row').length > 1)
          b.closest('.quality-check-row').remove();
      }
      if (a === 'request')
        show('طلب فحص الدفعة', form('request', id, note('note', 'سبب الفحص والملاحظات المتعلقة بالدفعة')));
    } catch (e) {
      toast(e.message);
    }
  });
  content.addEventListener('change', (event) => {
    if (event.target.id !== 'quality-access-user') return;
    const assigned = state.access.assignments
      .filter((a) => a.user_id === Number(event.target.value))
      .map((a) => a.warehouse_id);
    content
      .querySelectorAll('[name="warehouse_ids"]')
      .forEach((c) => (c.checked = assigned.includes(Number(c.value))));
  });
  content.addEventListener('submit', async (event) => {
    const f = event.target.closest('[data-quality-form]');
    if (!f) return;
    event.preventDefault();
    const submit = $('[type="submit"]', f),
      error = $('.quality-error', f);
    submit.disabled = true;
    error.textContent = '';
    const d = Object.fromEntries(new FormData(f));
    d.request_id = f.dataset.requestId;
    const a = f.dataset.qualityForm,
      id = Number(f.dataset.id);
    try {
      let url = '/ops/quality/' + id + '/' + a;
      if (a === 'inspect') {
        d.checks = [...f.querySelectorAll('.quality-check-row')].map((row) => ({
          name: $('[name="check_name"]', row).value,
          result: $('[name="check_result"]', row).value,
          observation: $('[name="check_observation"]', row).value,
        }));
        delete d.check_name;
        delete d.check_result;
        delete d.check_observation;
      }
      if (a === 'decide') d.acknowledge = $('[name="acknowledge"]', f).checked;
      if (a === 'request') url = '/ops/quality/lots/' + id + '/request';
      if (a === 'access') {
        url = '/ops/quality/access';
        d.warehouse_ids = [...f.querySelectorAll('[name="warehouse_ids"]:checked')].map((c) =>
          Number(c.value),
        );
      }
      const result = await api(url, d);
      toast(result.message);
      await load();
      if (a === 'access') {
        dialog.close();
      } else await open(result.id);
    } catch (e) {
      error.textContent = e.message;
    } finally {
      submit.disabled = false;
    }
  });
  $('#quality-filters').addEventListener('submit', (event) => {
    event.preventDefault();
    state.page = 1;
    load().catch((e) => toast(e.message));
  });
  $('#quality-access').addEventListener('click', () => access().catch((e) => toast(e.message)));
  $('#refresh').onclick = () => load().catch((e) => toast(e.message));
  $('#global-search').closest('label').hidden = true;
  $('#page-title').textContent = 'الجودة والاعتمادات';
  $('#page-description').textContent = 'طلبات الفحص، نتائج موثقة، ومسؤول مستقل عن قرار الإفراج.';
  $('#section-code').textContent = 'QUALITY';
  load()
    .then(() => {
      const id = new URLSearchParams(location.search).get('inspection');
      if (id && /^\d+$/.test(id)) return open(id);
    })
    .catch((e) => {
      root.innerHTML = `<div class="quality-empty">${esc(e.message)}</div>`;
    });
})();
