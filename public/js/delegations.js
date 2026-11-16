'use strict';
(() => {
  const $ = (s, r = document) => r.querySelector(s);
  if (!$('#delegation-workspace')) return;
  const esc = (v) =>
    String(v ?? '').replace(
      /[&<>"']/g,
      (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c],
    );
  const state = { page: 1, data: null, options: null };
  const dialog = $('#delegation-dialog'),
    content = $('#delegation-dialog-content');
  const labels = {
    pending: 'بانتظار إقرار البديل',
    active: 'نافذ',
    scheduled: 'مقبول - يبدأ لاحقًا',
    expired: 'انتهت الفترة',
    invalid: 'غير نافذ - تغير مصدر الصلاحية',
    revoked: 'ملغى',
    rejected: 'مرفوض',
    internal: 'داخلي',
    confidential: 'سري',
    restricted: 'مقيد',
  };
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
      : 'غير مسجل';
  const local = (v) =>
    new Intl.DateTimeFormat('sv-SE', {
      timeZone: document.body.dataset.timezone || 'Asia/Riyadh',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    })
      .format(new Date(v))
      .replace(' ', 'T');
  const badge = (s) =>
    `<span class="quality-badge ${s === 'active' ? 'approved' : s === 'invalid' ? 'rejected' : ''}">${esc(labels[s] || s)}</span>`;
  const button = (label, action, id = '') =>
    `<button type="button" class="secondary-btn" data-delegation="${action}" data-id="${id}">${esc(label)}</button>`;
  const select = (name, label, rows, chosen = '') =>
    `<label>${esc(label)}<select name="${name}" required><option value="">اختر…</option>${rows.map(([k, v]) => `<option value="${esc(k)}" ${String(k) === String(chosen) ? 'selected' : ''}>${esc(v)}</option>`).join('')}</select></label>`;
  const note = (name, label) =>
    `<label>${esc(label)}<textarea name="${name}" required minlength="3" maxlength="2000"></textarea></label>`;
  const form = (action, id, body, buttons = '') =>
    `<form class="quality-form" data-delegation-form="${action}" data-id="${id}" data-request-id="${uuid()}">${body}<div class="quality-error" role="alert"></div><div class="quality-actions">${buttons || '<button class="primary-btn" type="submit">حفظ</button>'}<button type="button" class="secondary-btn" data-delegation-close>إغلاق</button></div></form>`;
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
    const d = await r.json();
    if (!r.ok)
      throw new Error(
        d.errors ? Object.values(d.errors).flat().join('\n') : d.message || 'تعذر إكمال الإجراء.',
      );
    return d;
  }
  function show(title, body) {
    $('#delegation-dialog-title').textContent = title;
    content.innerHTML = body;
    if (!dialog.open) dialog.showModal();
  }
  function toast(text) {
    const t = $('#delegation-toast');
    t.textContent = text;
    t.hidden = false;
    clearTimeout(t.timer);
    t.timer = setTimeout(() => (t.hidden = true), 6500);
  }
  async function load() {
    const params = new URLSearchParams(new FormData($('#delegation-filters')));
    params.set('page', state.page);
    state.data = await api('/ops/delegations?' + params);
    const d = state.data;
    $('#delegation-create').hidden = !d.can_manage;
    $('#delegation-results').innerHTML =
      d.records.data
        .map(
          (r) =>
            `<article class="quality-card"><div class="quality-card-top"><div><h3>${esc(r.domain_label)} • ${esc(r.scope_label)}</h3><small>${esc(r.code)} • ${esc(r.principal.name)} ← ${esc(r.delegate.name)}</small></div>${badge(r.effective_status)}</div><p>من ${esc(date(r.starts_at))} إلى ${esc(date(r.ends_at))}</p><p>${esc(r.next_step)}</p><div class="quality-card-bottom"><span class="quality-owners">أنشأه ${esc(r.creator.name)}</span>${button('فتح ملف التفويض', 'open', r.id)}</div></article>`,
        )
        .join('') ||
      '<div class="quality-empty">لا تفويضات ضمن البحث. المدير ينشئ الطلب، والبديل يقره من حسابه قبل نفاذه.</div>';
    const p = d.records;
    $('#delegation-pagination').innerHTML =
      `${p.current_page > 1 ? button('السابق', 'page', p.current_page - 1) : ''}<span>صفحة ${p.current_page} من ${p.last_page} • ${p.total} سجل</span>${p.current_page < p.last_page ? button('التالي', 'page', p.current_page + 1) : ''}`;
    $('#updated-at').textContent = 'آخر تحديث: ' + date(d.as_of);
  }
  async function open(id) {
    const r = (await api('/ops/delegations/' + Number(id))).delegation;
    const facts = [
      ['الأصل', r.principal.name],
      ['البديل', r.delegate.name],
      ['المهمة', r.domain_label],
      ['النطاق', r.scope_label],
      ['البداية', date(r.starts_at)],
      ['النهاية', date(r.ends_at)],
      ['المنشئ', r.creator.name],
      ['إقرار البديل', date(r.responded_at)],
      ['حد سرية الأرشيف', r.max_confidentiality ? labels[r.max_confidentiality] : 'غير منطبق'],
    ];
    let html = `${badge(r.effective_status)}<dl class="quality-facts">${facts.map(([k, v]) => `<div><dt>${esc(k)}</dt><dd>${esc(v)}</dd></div>`).join('')}</dl><div class="quality-hint">${esc(r.next_step)}</div><h3>سبب التفويض وملاحظاته</h3><p class="quality-pre">${esc(r.reason)}</p>${r.response_note ? `<h3>رد البديل</h3><p class="quality-pre">${esc(r.response_note)}</p>` : ''}${r.revocation_note ? `<h3>سبب الإلغاء</h3><p class="quality-pre">${esc(r.revocation_note)}</p>` : ''}`;
    if (r.can_accept || r.can_reject)
      html += form(
        'respond',
        r.id,
        `<input type="hidden" name="version" value="${r.version}">${note('note', 'ردك وملاحظاتك عن النطاق والفترة')}<label class="quality-ack"><input type="checkbox" name="acknowledge"><span>أقر بفهم المهمة والنطاق والفترة والمسؤوليات المحددة.</span></label>`,
        `${r.can_accept ? '<button type="submit" class="primary-btn" name="decision" value="accepted">أقبل التفويض</button>' : ''}${r.can_reject ? '<button type="submit" class="secondary-btn" name="decision" value="rejected" formnovalidate>أرفض مع السبب</button>' : ''}`,
      );
    if (r.can_revoke)
      html += `<details><summary>إلغاء التفويض بواسطة المدير</summary>${form('revoke', r.id, `<input type="hidden" name="version" value="${r.version}">${note('note', 'سبب الإلغاء')}`)}</details>`;
    if (r.effective_status === 'active')
      html += `<div class="quality-actions"><a class="primary-btn" href="${r.domain === 'archive_review' ? '/archive' : '/quality'}">فتح قائمة العمل</a></div>`;
    show('تفويض • ' + r.code, html);
    history.replaceState(null, '', '/delegations?delegation=' + r.id);
  }
  function scopeFields() {
    const f = $('[data-delegation-form="create"]'),
      domain = f.elements.domain.value;
    const options = state.options;
    const archive = domain === 'archive_review';
    const roles =
      domain === 'quality_inspect'
        ? ['quality_inspector']
        : domain === 'quality_approve'
          ? ['quality_manager', 'manager', 'supervisor']
          : null;
    const eligible = options.users.filter((u) => (archive ? u.role !== 'viewer' : roles?.includes(u.role)));
    $('#delegation-scope-fields').innerHTML = `${select(
      'principal_id',
      'صاحب الصلاحية الأصلية',
      eligible.map((u) => [u.id, u.name + ' • ' + u.username]),
    )}${select(
      'delegate_id',
      'البديل الذي سيقر التفويض',
      eligible.map((u) => [u.id, u.name + ' • ' + u.username]),
    )}${
      archive
        ? select(
            'department_id',
            'قسم الأرشيف',
            options.departments.map((s) => [s.id, s.name]),
          ) +
          select(
            'max_confidentiality',
            'حد السرية',
            [
              ['internal', 'داخلي'],
              ['confidential', 'سري'],
              ['restricted', 'مقيد'],
            ],
            'internal',
          )
        : select(
            'warehouse_id',
            'موقع الجودة',
            options.warehouses.map((s) => [s.id, s.name]),
          )
    }`;
  }
  async function create() {
    state.options = await api('/ops/delegations/options');
    const start = local(state.data.as_of),
      end = local(new Date(new Date(state.data.as_of).getTime() + 86400000));
    show(
      'إنشاء تفويض مؤقت',
      form(
        'create',
        '',
        `${select('domain', 'المهمة المفوضة', Object.entries(state.options.domains), 'quality_inspect')}<div id="delegation-scope-fields" class="archive-form-grid"></div><div class="archive-form-grid"><label>البداية (${esc(state.options.timezone)})<input name="starts_at" type="datetime-local" required value="${start}"></label><label>النهاية<input name="ends_at" type="datetime-local" required value="${end}"></label></div>${note('reason', 'سبب الغياب والتغطية المطلوبة')}<div class="quality-hint">اختر أصلًا لديه صلاحية أصلية لهذا النطاق. مراجعة الأرشيف تفوض العرض والمعاينة والاعتماد فقط، وسجل الإجراءات إن كان مصرحًا للأصل. لا تفوض الرفع أو التنزيل أو الطباعة أو التصدير.</div>`,
      ),
    );
    scopeFields();
  }
  content.addEventListener('change', (e) => {
    if (e.target.name === 'domain') scopeFields();
  });
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-delegation],[data-delegation-close]');
    if (!b) return;
    try {
      if (b.hasAttribute('data-delegation-close')) {
        dialog.close();
        return;
      }
      if (b.dataset.delegation === 'open') await open(b.dataset.id);
      if (b.dataset.delegation === 'page') {
        state.page = Number(b.dataset.id);
        await load();
      }
    } catch (error) {
      toast(error.message);
    }
  });
  content.addEventListener('submit', async (e) => {
    const f = e.target.closest('[data-delegation-form]');
    if (!f) return;
    e.preventDefault();
    if (f.dataset.busy === 'true') return;
    const error = $('.quality-error', f);
    error.textContent = '';
    const d = Object.fromEntries(new FormData(f));
    d.request_id = f.dataset.requestId;
    const action = f.dataset.delegationForm;
    const buttons = [...f.querySelectorAll('button')];
    try {
      if (action === 'respond') {
        d.decision = e.submitter.value;
        d.acknowledge = f.elements.acknowledge.checked;
        if (d.decision === 'accepted' && !d.acknowledge)
          throw new Error('راجع المهمة وضع علامة الإقرار قبل القبول.');
      }
      f.dataset.busy = 'true';
      buttons.forEach((b) => (b.disabled = true));
      const url = '/ops/delegations' + (action === 'create' ? '' : '/' + Number(f.dataset.id) + '/' + action);
      const result = await api(url, d);
      toast(result.message);
      await load();
      await open(result.id);
    } catch (ex) {
      error.textContent = ex.message;
    } finally {
      f.dataset.busy = 'false';
      buttons.forEach((b) => (b.disabled = false));
    }
  });
  $('#delegation-create').onclick = () => create().catch((e) => toast(e.message));
  $('#delegation-filters').onsubmit = (e) => {
    e.preventDefault();
    state.page = 1;
    load().catch((e) => toast(e.message));
  };
  $('#refresh').onclick = () => load().catch((e) => toast(e.message));
  $('#global-search').closest('label').hidden = true;
  $('#page-title').textContent = 'التفويض والبدلاء';
  $('#page-description').textContent = 'صلاحيات مؤقتة محددة، بإقرار البديل وسجل للمسؤولية.';
  $('#section-code').textContent = 'DELEGATION';
  load()
    .then(() => {
      const id = new URLSearchParams(location.search).get('delegation');
      if (id && /^\d+$/.test(id)) return open(id);
    })
    .catch((e) => {
      $('#delegation-results').innerHTML = `<div class="quality-empty">${esc(e.message)}</div>`;
    });
})();
