'use strict';
(() => {
  const $ = (s, r = document) => r.querySelector(s);
  if (!$('#archive-workspace')) return;
  const esc = (v) =>
    String(v ?? '').replace(
      /[&<>"']/g,
      (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c],
    );
  const labels = {
    pending_review: 'ينتظر المراجعة',
    approved: 'معتمد',
    returned: 'معاد للاستكمال',
    internal: 'داخلي',
    confidential: 'سري',
    restricted: 'مقيد',
    view: 'عرض البيانات',
    preview: 'معاينة الملفات',
    upload: 'رفع وإصدار نسخ',
    download: 'تنزيل',
    print: 'طباعة',
    approve: 'مراجعة واعتماد',
    audit: 'سجل الاطلاع والإجراءات',
    export: 'تصدير بيانات',
  };
  const state = { page: 1, data: null, permissions: null, document: null };
  const dialog = $('#archive-dialog'),
    content = $('#archive-dialog-content');
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
  const badge = (s) => `<span class="quality-badge ${esc(s)}">${esc(labels[s] || s)}</span>`;
  const button = (label, action, id = '') =>
    `<button type="button" class="secondary-btn" data-archive="${action}" data-id="${id}">${esc(label)}</button>`;
  const input = (name, label, type = 'text', value = '', attrs = '') =>
    `<label>${esc(label)}<input name="${name}" type="${type}" value="${esc(value)}" ${attrs}></label>`;
  const note = (name, label, value = '') =>
    `<label>${esc(label)}<textarea name="${name}" required minlength="3" maxlength="2000">${esc(value)}</textarea></label>`;
  const select = (name, label, rows, value = '') =>
    `<label>${esc(label)}<select name="${name}" required><option value="">اختر…</option>${rows.map(([k, v]) => `<option value="${esc(k)}" ${String(k) === String(value) ? 'selected' : ''}>${esc(v)}</option>`).join('')}</select></label>`;
  const form = (action, id, body) =>
    `<form class="quality-form" data-archive-form="${action}" data-id="${id}" data-request-id="${uuid()}">${body}<div class="quality-error" role="alert"></div><div class="quality-actions"><button type="submit" class="primary-btn">${action === 'upload' ? 'حفظ النسخة وإرسالها للمراجعة' : 'حفظ'}</button><button type="button" class="secondary-btn" data-archive-close>إغلاق</button></div></form>`;
  async function api(url, data) {
    const opts = { headers: { Accept: 'application/json' } };
    if (data !== undefined) {
      opts.method = 'POST';
      opts.headers['X-CSRF-TOKEN'] = $('meta[name="csrf-token"]').content;
      if (data instanceof FormData) opts.body = data;
      else {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(data);
      }
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
      throw new Error('تعذر قراءة الرد. تحقق من الاتصال وحجم المرفق.');
    }
    if (!r.ok)
      throw new Error(
        d.errors ? Object.values(d.errors).flat().join('\n') : d.message || 'تعذر تنفيذ الإجراء.',
      );
    return d;
  }
  function show(title, body) {
    $('#archive-dialog-title').textContent = title;
    content.innerHTML = body;
    if (!dialog.open) dialog.showModal();
  }
  function toast(text) {
    const t = $('#archive-toast');
    t.textContent = text;
    t.hidden = false;
    clearTimeout(t.timer);
    t.timer = setTimeout(() => (t.hidden = true), 6500);
  }
  function pager(p, action = 'page') {
    return `${p.current_page > 1 ? button('السابق', action, p.current_page - 1) : ''}<span>صفحة ${p.current_page} من ${p.last_page} • ${p.total} سجل</span>${p.current_page < p.last_page ? button('التالي', action, p.current_page + 1) : ''}`;
  }
  async function load() {
    const params = new URLSearchParams(new FormData($('#archive-filters')));
    params.set('page', state.page);
    state.data = await api('/ops/archive?' + params);
    const d = state.data;
    const chosen = $('[name="department_id"]', $('#archive-filters')).value;
    $('[name="department_id"]', $('#archive-filters')).innerHTML =
      '<option value="">الأقسام المسموحة</option>' +
      d.departments
        .map(
          (s) =>
            `<option value="${s.id}" ${String(s.id) === chosen ? 'selected' : ''}>${esc(s.name)}${s.is_active ? '' : ' • موقوف'}</option>`,
        )
        .join('');
    $('#archive-departments').innerHTML = d.departments
      .map(
        (s) =>
          `<button type="button" class="archive-department-card" data-archive="filter" data-id="${s.id}"><strong>${esc(s.name)}</strong><small>${s.is_active ? 'قسم فعال' : 'موقوف'} • ${
            ['upload', 'approve', 'download']
              .filter((k) => s.rights[k])
              .map((k) => labels[k])
              .join('، ') || 'عرض ضمن صلاحياتك'
          }</small></button>`,
      )
      .join('');
    $('#archive-metrics').innerHTML = [
      ['documents', 'مستندات ضمن البحث'],
      ['pending', 'بانتظار المراجعة'],
      ['approved', 'نسخ حالية معتمدة'],
      ['expired', 'مستندات تجاوزت الانتهاء'],
    ]
      .map(
        ([k, label]) =>
          `<article class="quality-metric"><span>${label}</span><strong>${Number(d.summary[k]).toLocaleString('ar-SA')}</strong></article>`,
      )
      .join('');
    $('#archive-upload').hidden = !d.departments.some((s) => s.rights.upload);
    for (const id of ['permissions', 'department', 'import']) $('#archive-' + id).hidden = !d.can_manage;
    $('#archive-export').hidden = !d.departments.some((s) => s.rights.export);
    $('#archive-results').innerHTML =
      d.documents.data
        .map(
          (doc) =>
            `<article class="quality-card"><div class="quality-card-top"><div><h3>${esc(doc.title)}</h3><small>${esc(doc.code)} • ${esc(doc.department.name)} • ${esc(doc.document_type)}</small></div><div>${badge(doc.status)} ${badge(doc.confidentiality)}</div></div><p>المرجع: ${esc(doc.reference || 'غير مسجل')} • تاريخ المستند: ${esc(doc.document_date?.slice(0, 10))} • النسخة الحالية: ${doc.current_version}</p><div class="quality-card-bottom"><span class="quality-owners">أضيف بواسطة ${esc(doc.creator.name)}</span>${button('فتح ملف المستند', 'open', doc.id)}</div></article>`,
        )
        .join('') ||
      '<div class="quality-empty">لا توجد مستندات ضمن البحث والصلاحيات. يستطيع المدير إضافة مستند أو ربط محضر سابق، ومنح المستخدمين الوصول إلى الأقسام.</div>';
    $('#archive-pagination').innerHTML = pager(d.documents);
    $('#updated-at').textContent = 'آخر تحديث: ' + date(d.as_of);
  }
  async function open(id) {
    const data = await api('/ops/archive/documents/' + Number(id));
    state.document = data;
    const d = data.document;
    const facts = [
      ['القسم', d.department.name],
      ['نوع المستند', d.document_type],
      ['السرية', labels[d.confidentiality]],
      ['تاريخ المستند', d.document_date?.slice(0, 10)],
      ['الانتهاء', d.expires_on?.slice(0, 10)],
      ['الاحتفاظ حتى', d.retain_until?.slice(0, 10)],
      ['المرجع', d.reference],
      ['مكان النسخة الورقية', d.physical_reference],
      ['المنشئ', d.creator.name],
    ];
    show(
      'ملف المستند • ' + d.code,
      `${badge(d.status)}<dl class="quality-facts">${facts.map(([k, v]) => `<div><dt>${esc(k)}</dt><dd>${esc(v || 'غير مسجل')}</dd></div>`).join('')}</dl><h3>${esc(d.title)}</h3><p class="quality-pre">${esc(d.notes)}</p>${d.status === 'pending_review' ? `<div class="quality-hint">ينتظر اعتماد مستخدم مستقل. المخولون: ${data.reviewers.map((u) => esc(u.name)).join('، ') || 'مدير الشركة: امنح مستخدمًا مستقلًا صلاحية الاعتماد لهذا القسم والسرية.'}</div>` : ''}<div class="quality-actions">${data.can_upload ? button('إضافة نسخة وتصحيح بياناتها', 'version', d.id) : ''}${data.can_audit ? button('سجل الاطلاع والإجراءات', 'audit', d.id) : ''}</div><h3>الإصدارات المحفوظة</h3>${data.versions
        .map(
          (v) =>
            `<article class="quality-card"><div class="quality-card-top"><strong>النسخة ${v.number} • ${esc(v.original_name)}</strong>${badge(v.status)}</div><p>${esc(v.uploader.name)} • ${esc(date(v.created_at))} • ${Math.ceil(v.size / 1024)} KB${v.signed_copy ? ' • نسخة وصفها الرافع بالموقعة' : ''}</p><p class="quality-pre">${esc(v.note)}</p><div class="quality-actions">${[
              ['preview', 'معاينة'],
              ['download', 'تنزيل'],
              ['print', 'فتح للطباعة'],
            ]
              .filter(([k]) => v[k + '_url'])
              .map(
                ([k, t]) =>
                  `<a class="secondary-btn" href="${v[k + '_url']}" target="_blank" rel="noopener">${t}</a>`,
              )
              .join(
                '',
              )}</div>${v.decision_note ? `<p class="quality-pre">قرار ${esc(v.decider?.name)}: ${esc(v.decision_note)}</p>` : ''}${v.decision_authority ? `<p>بتفويض من ${esc(v.decision_authority.principal.name)} • ${esc(v.decision_authority.code)}</p>` : ''}<details><summary>بيانات توثيق النسخة</summary><p class="archive-hash">SHA-256 ${esc(v.sha256)}</p>${v.metadata_snapshot ? `<p class="quality-pre">العنوان وقت الرفع: ${esc(v.metadata_snapshot.title)}</p>` : ''}</details></article>`,
        )
        .join('')}${
        data.can_approve
          ? form(
              'decision',
              d.id,
              `<input name="version" type="hidden" value="${d.version}">${select(
                'decision',
                'قرار المراجعة',
                [
                  ['approved', 'اعتماد النسخة الحالية'],
                  ['returned', 'إعادة للاستكمال'],
                ],
              )}${note('note', 'ملاحظات القرار وسبب الاعتماد أو الإعادة')}<label class="quality-ack"><input name="acknowledge" type="checkbox" required><span>راجعت النسخة الحالية ومعلوماتها وأقر بالقرار من حسابي المستقل.</span></label>`,
            )
          : ''
      }`,
    );
    history.replaceState(null, '', '/archive?document=' + d.id);
  }
  function upload(id = '') {
    const d = id ? state.document.document : {};
    const sites = state.data.departments.filter((s) => s.rights.upload && s.is_active);
    const fields = `${
      id
        ? `<input type="hidden" name="version" value="${d.version}"><div class="quality-hint">تُحفظ بيانات النسخة السابقة. التعديلات التالية تخص الإصدار الجديد. القسم والسرية ثابتان.</div>`
        : select(
            'department_id',
            'القسم',
            sites.map((s) => [s.id, s.name]),
          )
    }<div class="archive-form-grid">${input('title', 'عنوان المستند', 'text', d.title || '', 'required minlength="3" maxlength="200"')}${input('document_type', 'نوع المستند', 'text', d.document_type || '', 'required minlength="2" maxlength="80"')}${
      !id
        ? select(
            'confidentiality',
            'السرية',
            [
              ['internal', 'داخلي'],
              ['confidential', 'سري'],
              ['restricted', 'مقيد'],
            ],
            'internal',
          )
        : ''
    }${input('document_date', 'تاريخ المستند', 'date', d.document_date?.slice(0, 10) || new Date().toLocaleDateString('en-CA', { timeZone: document.body.dataset.timezone || 'Asia/Riyadh' }), 'required')}${input('reference', 'مرجع المعاملة', 'text', d.reference || '', 'maxlength="150"')}${input('expires_on', 'انتهاء المستند إن وجد', 'date', d.expires_on?.slice(0, 10) || '')}${input('retain_until', 'الاحتفاظ حتى - دون حذف تلقائي', 'date', d.retain_until?.slice(0, 10) || '')}${input('physical_reference', 'موقع النسخة الورقية', 'text', d.physical_reference || '', 'maxlength="200"')}</div>${note('notes', 'وصف المستند والمعلومات المرتبطة', d.notes || '')}${note('version_note', id ? 'سبب النسخة الجديدة والتعديلات' : 'ملاحظات النسخة المرفوعة')}<label>ملف النسخة<input name="document" type="file" accept="application/pdf,image/jpeg,image/png" required></label><label class="quality-ack"><input name="signed_copy" type="checkbox"><span>الملف نسخة موقعة بحسب مراجعتي له.</span></label><p class="quality-pre">PDF أو JPEG/PNG، حتى 10 MB. تحفظ النسخة وتنتظر مراجعة مستقلة.</p>`;
    show(id ? 'إصدار نسخة جديدة' : 'إضافة مستند إلى قسم', form('upload', id, fields));
  }
  async function permissions() {
    state.permissions = await api('/ops/archive/permissions');
    const p = state.permissions;
    show(
      'صلاحيات مستخدمي الأرشيف',
      `<p class="quality-pre">المدير يحدد لكل مستخدم قسمًا وإجراءات ومستوى سرية. سحب العرض يلغي وصوله إلى ملفات القسم من الواجهة والروابط المباشرة.</p>${form(
        'permissions',
        '',
        `${select(
          'user_id',
          'المستخدم',
          p.users.filter((u) => u.role !== 'manager').map((u) => [u.id, u.name + ' • ' + u.username]),
        )}${select(
          'department_id',
          'القسم',
          p.departments.map((s) => [s.id, s.name]),
        )}<input type="hidden" name="version" value="0"><div class="quality-site-list">${p.rights.map((k) => `<label><input type="checkbox" name="can_${k}"><span>${esc(labels[k])}</span></label>`).join('')}</div>${select(
          'max_confidentiality',
          'أقصى سرية مسموحة',
          [
            ['internal', 'داخلي'],
            ['confidential', 'سري'],
            ['restricted', 'مقيد'],
          ],
          'internal',
        )}${note('reason', 'سبب منح أو سحب الصلاحيات')}`,
      )}`,
    );
  }
  function permissionSelection() {
    const f = $('[data-archive-form="permissions"]', content);
    if (!f) return;
    const g = state.permissions.grants.find(
      (g) =>
        g.user_id === Number(f.elements.user_id.value) &&
        g.department_id === Number(f.elements.department_id.value),
    );
    for (const k of state.permissions.rights) f.elements['can_' + k].checked = !!g?.['can_' + k];
    f.elements.max_confidentiality.value = g?.max_confidentiality || 'internal';
    f.elements.version.value = g?.version || 0;
  }
  function department(id = '') {
    const d = state.data.departments.find((d) => d.id === Number(id)) || {};
    show(
      'إدارة أقسام الأرشيف',
      `<div class="quality-actions">${state.data.departments.map((s) => button(s.name, 'edit-department', s.id)).join('')}</div>${form(
        'department',
        id,
        `${id ? `<input type="hidden" name="id" value="${id}"><input type="hidden" name="version" value="${d.version}">` : ''}${input('name', 'اسم القسم', 'text', d.name || '', 'required minlength="2" maxlength="150"')}${input('code', 'رمز القسم', 'text', d.code || '', 'required pattern="[a-z][a-z0-9_-]{1,39}" placeholder="production"')}${note('description', 'وصف القسم', d.description || '')}${select(
          'is_active',
          'حالة القسم',
          [
            ['1', 'فعال'],
            ['0', 'موقوف'],
          ],
          String(d.is_active === false ? 0 : 1),
        )}${note('reason', 'سبب الإضافة أو التعديل')}`,
      )}`,
    );
  }
  async function imports(page = 1) {
    const d = await api('/ops/archive/imports?page=' + page);
    show(
      'ربط محاضر سابقة بالأرشيف',
      `<p class="quality-pre">الربط يحفظ الملف الأصلي ومرجعه ويطلب المراجعة؛ لا يغير محضر الاستلام أو صاحبه ولا يمنح صلاحيات للمستخدمين.</p>${d.files.data.map((f) => `<article class="quality-card"><strong>${esc(f.original_name)}</strong><p>ملف ${f.id} • ${esc(date(f.created_at))}</p>${form('import', f.id, note('note', 'سبب ربط المحضر بالقسم'))}</article>`).join('') || '<div class="quality-empty">كل المحاضر المرفوعة مسجلة، أو لا توجد ملفات سابقة.</div>'}<div class="quality-pagination">${pager(d.files, 'imports-page')}</div>`,
    );
  }
  async function audit(id, page = 1) {
    const actions = {
      'archive.viewed': 'فتح ملف المستند',
      'archive.preview': 'معاينة نسخة',
      'archive.download': 'تنزيل نسخة',
      'archive.print_requested': 'فتح نسخة للطباعة',
      'archive.version_uploaded': 'رفع إصدار جديد',
      'archive.approved': 'اعتماد نسخة',
      'archive.returned': 'إعادة نسخة للاستكمال',
      'archive.handover_registered': 'ربط محضر تسليم',
      'archive.import_requested': 'طلب فهرسة محضر سابق',
    };
    const d = await api('/ops/archive/documents/' + id + '/audit?page=' + page);
    show(
      'سجل المستند',
      `${d.events.data.map((e) => `<article class="quality-card"><strong>${esc(actions[e.action] || e.action)}</strong><p>${esc(e.actor_name)} • ${esc(date(e.created_at))}</p>${e.metadata?.note ? `<p class="quality-pre">${esc(e.metadata.note)}</p>` : ''}<details><summary>تفاصيل التوثيق</summary><pre class="quality-pre">${esc(JSON.stringify(e.metadata, null, 2))}</pre></details></article>`).join('')}<div class="quality-pagination">${pager(d.events, 'audit-page:' + id)}</div>`,
    );
  }
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-archive],[data-archive-close]');
    if (!b) return;
    try {
      if (b.hasAttribute('data-archive-close')) {
        dialog.close();
        return;
      }
      const a = b.dataset.archive,
        id = b.dataset.id;
      if (a === 'open') await open(id);
      if (a === 'version') upload(id);
      if (a === 'page') {
        state.page = Number(id);
        await load();
      }
      if (a === 'filter') {
        $('[name="department_id"]', $('#archive-filters')).value = id;
        state.page = 1;
        await load();
      }
      if (a === 'edit-department') department(id);
      if (a === 'imports-page') await imports(id);
      if (a === 'audit') await audit(id);
      if (a.startsWith('audit-page:')) await audit(a.split(':')[1], id);
    } catch (error) {
      toast(error.message);
    }
  });
  content.addEventListener('change', (e) => {
    if (
      e.target.matches('[name="user_id"],[name="department_id"]') &&
      e.target.closest('[data-archive-form="permissions"]')
    )
      permissionSelection();
  });
  content.addEventListener('submit', async (e) => {
    const f = e.target.closest('[data-archive-form]');
    if (!f) return;
    e.preventDefault();
    if (f.dataset.busy === 'true') return;
    f.dataset.busy = 'true';
    const submit = $('[type="submit"]', f),
      error = $('.quality-error', f);
    submit.disabled = true;
    error.textContent = '';
    const action = f.dataset.archiveForm,
      id = Number(f.dataset.id);
    let d = Object.fromEntries(new FormData(f));
    d.request_id = f.dataset.requestId;
    try {
      let url = '/ops/archive/' + action;
      if (action === 'upload') {
        const fd = new FormData(f);
        if (fd.get('document').size > 10 * 1024 * 1024) throw new Error('حجم الملف يتجاوز 10 MB.');
        fd.set('request_id', f.dataset.requestId);
        fd.set('signed_copy', f.elements.signed_copy.checked ? '1' : '0');
        d = fd;
        url = '/ops/archive/documents' + (id ? '/' + id + '/versions' : '');
      }
      if (action === 'permissions') {
        for (const k of state.permissions.rights) d['can_' + k] = f.elements['can_' + k].checked;
      }
      if (action === 'department') {
        url = '/ops/archive/departments';
        d.is_active = d.is_active === '1';
      }
      if (action === 'decision') {
        url = '/ops/archive/documents/' + id + '/decision';
        d.acknowledge = f.elements.acknowledge.checked;
      }
      if (action === 'import') {
        url = '/ops/archive/imports';
        d.file_id = id;
      }
      const result = await api(url, d);
      toast(result.message);
      await load();
      if (['upload', 'decision', 'import'].includes(action)) await open(result.id);
      else dialog.close();
    } catch (errorValue) {
      error.textContent = errorValue.message;
    } finally {
      submit.disabled = false;
      f.dataset.busy = 'false';
    }
  });
  $('#archive-upload').onclick = () => upload();
  $('#archive-permissions').onclick = () => permissions().catch((e) => toast(e.message));
  $('#archive-department').onclick = () => department();
  $('#archive-import').onclick = () => imports().catch((e) => toast(e.message));
  $('#archive-export').onclick = () => {
    location.href = '/ops/archive/export?' + new URLSearchParams(new FormData($('#archive-filters')));
  };
  $('#archive-filters').onsubmit = (e) => {
    e.preventDefault();
    state.page = 1;
    load().catch((e) => toast(e.message));
  };
  $('#refresh').onclick = () => load().catch((e) => toast(e.message));
  $('#global-search').closest('label').hidden = true;
  $('#page-title').textContent = 'الأرشيف الإداري';
  $('#page-description').textContent = 'أقسام وصلاحيات وإصدارات ومراجعة، ومحاضر مرتبطة بعملياتها.';
  $('#section-code').textContent = 'ARCHIVE';
  load()
    .then(() => {
      const id = new URLSearchParams(location.search).get('document');
      if (id && /^\d+$/.test(id)) return open(id);
    })
    .catch((e) => {
      $('#archive-results').innerHTML = `<div class="quality-empty">${esc(e.message)}</div>`;
    });
})();
