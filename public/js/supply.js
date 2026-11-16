'use strict';
(() => {
  if (document.body.dataset.supplyWorkspace !== 'true') return;
  const $ = (s, r = document) => r.querySelector(s);
  const root = $('#supply-workspace'),
    dialog = $('#supply-dialog'),
    content = $('#supply-dialog-content');
  const section = document.body.dataset.section;
  const state = {
    data: null,
    page: 1,
    filters: {},
    sitePage: 1,
    current: null,
  };
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
  const n = (v) => Number(v || 0).toLocaleString('ar-SA');
  const date = (v) =>
    v
      ? new Date(v).toLocaleDateString('ar-SA', {
          calendar: 'gregory',
          timeZone: document.body.dataset.timezone || 'Asia/Riyadh',
        })
      : 'غير مسجل';
  const labels = {
    planned: 'مخطط',
    in_progress: 'قيد التشغيل',
    completed: 'مكتمل',
    cancelled: 'ملغى',
    released: 'مفرج عنها',
    quarantine: 'حجر جودة',
    blocked: 'موقوف',
    production: 'إنتاج',
    warehouse: 'مخزن',
    vehicle: 'عهدة مركبة',
    legacy: 'افتتاحي قديم',
    external: 'استلام خارجي',
    transfer_out: 'تسليم صادر',
    transfer_in: 'استلام معتمد',
    production_output: 'ناتج إنتاج',
    production_consumption: 'صرف خامات',
    sale: 'صرف فاتورة',
    receipt: 'استلام خارجي',
    legacy_opening: 'رصيد قبل التتبع',
    pending: 'بانتظار الإقرار',
    accepted: 'استلام معتمد',
    rejected: 'مرفوض',
  };
  const title = {
    production: ['الإنتاج', 'من خطة التشغيل إلى دفعة قابلة للتتبع.', 'PRODUCTION / CONTROL'],
    warehouses: ['المخازن والعُهد', 'ما لديك، وما حُجز، ومن يتحمل عهدته.', 'WAREHOUSE / CUSTODY'],
    traceability: ['تتبع الدفعات', 'كل دفعة لها بداية. وكل حركة لها أثر.', 'TRACE / EVERY STEP'],
  }[section];
  const status = (s) => `<span class="supply-badge ${esc(s)}">${esc(labels[s] || s)}</span>`;
  const btn = (label, act, id = '', cls = 'secondary-btn') =>
    `<button type="button" class="${cls}" data-supply="${act}" data-id="${id}">${esc(label)}</button>`;
  const empty = (title, text) =>
    `<div class="supply-empty"><span class="supply-empty-icon" aria-hidden="true">◇</span><h3>${esc(title)}</h3><p>${esc(text)}</p></div>`;
  const table = (heads, rows) =>
    `<div class="supply-table-wrap"><table class="supply-table"><thead><tr>${heads.map((h) => `<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.join('')}</tbody></table></div>`;
  const metric = (label, value, note, i) =>
    `<article class="supply-metric"><span class="supply-metric-index">0${i}</span><span>${esc(label)}</span><strong>${n(value)}</strong><small>${esc(note)}</small></article>`;
  const pager = (p, action = 'page', id = '') =>
    `<div class="supply-pagination">${p.current_page > 1 ? btn('السابق', action, `${id}:${p.current_page - 1}`) : '<span></span>'}<span>صفحة ${n(p.current_page)} من ${n(p.last_page)} · ${n(p.total)} سجل</span>${p.current_page < p.last_page ? btn('التالي', action, `${id}:${p.current_page + 1}`) : '<span></span>'}</div>`;
  const options = (items, label = 'name', value = 'id', chosen = '') =>
    items
      .map(
        (x) =>
          `<option value="${esc(x[value])}" ${String(x[value]) === String(chosen) ? 'selected' : ''}>${esc(x[label])}</option>`,
      )
      .join('');
  const input = (name, label, type = 'text', value = '', attrs = '') =>
    `<label>${esc(label)}<input name="${name}" type="${type}" value="${esc(value)}" ${attrs}></label>`;
  const select = (name, label, items, chosen = '', attrs = '') =>
    `<label>${esc(label)}<select name="${name}" ${attrs}><option value="">اختر…</option>${options(items, 'name', 'id', chosen)}</select></label>`;
  const note = (name, label, value = '') =>
    `<label class="supply-span">${esc(label)}<textarea name="${name}" required minlength="3" maxlength="2000">${esc(value)}</textarea></label>`;
  const facts = (items) =>
    `<dl class="supply-facts">${items.map(([k, v]) => `<div><dt>${esc(k)}</dt><dd>${esc(v ?? '—')}</dd></div>`).join('')}</dl>`;
  const uuid = () => {
    if (crypto.randomUUID) return crypto.randomUUID();
    const b = crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 15) | 64;
    b[8] = (b[8] & 63) | 128;
    const h = [...b].map((v) => v.toString(16).padStart(2, '0')).join('');
    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
  };
  async function api(url, data = null) {
    const opts = { headers: { Accept: 'application/json' } };
    if (data !== null) {
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
    let body;
    try {
      body = await r.json();
    } catch {
      throw new Error('تعذر قراءة الرد. تحقق من الاتصال وحجم المرفق.');
    }
    if (!r.ok)
      throw new Error(
        body.errors ? Object.values(body.errors).flat().join('\n') : body.message || 'تعذر حفظ العملية.',
      );
    return body;
  }
  function toast(text, error = false) {
    const t = $('#supply-toast');
    t.textContent = text;
    t.classList.toggle('error', error);
    t.hidden = false;
    clearTimeout(t.timer);
    t.timer = setTimeout(() => (t.hidden = true), 6500);
  }
  function show(title, html) {
    $('#supply-dialog-title').textContent = title;
    content.innerHTML = html;
    if (!dialog.open) dialog.showModal();
  }
  function form(action, html, id = '') {
    return `<form data-supply-form="${action}" data-id="${id}" data-request-id="${uuid()}"><div class="supply-form-grid">${html}</div><div class="supply-error" role="alert"></div><div class="supply-form-footer"><button class="primary-btn" type="submit">${action === 'complete' ? 'إثبات الناتج وإغلاق الأمر' : action === 'proof' ? 'حفظ الإثبات' : 'حفظ العملية'}</button><button class="secondary-btn" type="button" data-supply-close>إغلاق</button></div></form>`;
  }
  function filters() {
    const statuses =
      section === 'production'
        ? ['planned', 'in_progress', 'completed', 'cancelled']
        : section === 'traceability'
          ? ['released', 'quarantine', 'blocked']
          : [];
    return `<form id="supply-filters" class="supply-filters"><label>بحث<input type="search" name="q" value="${esc(state.filters.q || '')}" placeholder="${section === 'production' ? 'رقم الأمر، الصنف أو خط الإنتاج' : 'اسم الصنف، الرمز أو الدفعة'}"></label>${select('warehouse_id', 'الموقع', state.data.options.warehouses, state.filters.warehouse_id)}${select('product_id', 'الصنف', state.data.options.products, state.filters.product_id)}${
      statuses.length
        ? select(
            'status',
            'الحالة',
            statuses.map((id) => ({ id, name: labels[id] })),
            state.filters.status,
          )
        : ''
    }<button class="secondary-btn" type="submit">عرض النتائج</button>${btn('مسح الفلاتر', 'reset')}</form>`;
  }
  function hero() {
    const links = [
      ['production', '01', 'الإنتاج', 'خطة · خامات · جودة'],
      ['warehouses', '02', 'المخزن', 'استلام · حجز · صرف'],
      ['handovers', '03', 'التسليم', 'طلب · فحص · إقرار'],
      ['traceability', '04', 'أثر المنتج', 'دفعة · حركة · عميل'],
    ];
    const allowed = [...document.querySelectorAll('[data-nav-label]')].map((a) => a.getAttribute('href'));
    return `<section class="supply-hero"><div><span class="supply-kicker">ONE PRODUCT. ONE CONTINUOUS STORY.</span><h2>${esc(title[1])}</h2><p>عهدة واضحة، كميات قابلة للمراجعة، واستلام يعتمد من الطرف المعني.</p></div><span class="supply-orbit" aria-hidden="true">◈</span></section><nav class="supply-flow" aria-label="دورة المنتج">${links.map(([path, i, t, sub]) => (allowed.includes('/' + path) ? `<a href="/${path}" ${section === path ? 'aria-current="page"' : ''}><span>${i}</span><div><strong>${t}</strong><small>${sub}</small></div><b aria-hidden="true">←</b></a>` : `<div class="supply-flow-step"><span>${i}</span><div><strong>${t}</strong><small>${sub}</small></div></div>`)).join('')}</nav>`;
  }
  function production() {
    const d = state.data,
      s = d.summary;
    const metrics =
      metric('أوامر مخططة', s.planned, 'جاهزة لبدء التشغيل', 1) +
      metric('قيد التشغيل', s.active, 'متابعة التنفيذ الفعلي', 2) +
      metric('ناتج سليم', s.good, 'من جميع أوامرك المرئية', 3) +
      metric('تالف إنتاج', s.scrap, 'مسجل دون إضافته للمخزون', 4);
    const rows = d.records.data.map(
      (o) =>
        `<tr><td><button class="supply-record-link" data-supply="order" data-id="${o.id}">${esc(o.code)}</button><small>${date(o.scheduled_on)}</small></td><td><strong>${esc(o.product?.name)}</strong><small>${esc(o.product?.sku)} · ${esc(o.product?.unit)}</small></td><td>${esc(o.line)}<small>وردية ${esc(o.shift)}</small></td><td>${esc(o.warehouse?.name)}<small>${esc(o.operator?.name)}</small></td><td>${n(o.planned_quantity)}<small>سليم ${n(o.good_quantity)} · تالف ${n(o.scrap_quantity)}</small></td><td>${status(o.status)}</td><td>${btn('فتح الأمر', 'order', o.id)}</td></tr>`,
    );
    return `<div class="supply-metrics">${metrics}</div><section class="supply-panel"><div class="supply-panel-head"><div><h2>لوحة أوامر الإنتاج</h2><p>التخطيط لا يزيد المخزون. إثبات الناتج ينشئ الدفعة في عهدة الإنتاج.</p></div>${btn('+ أمر إنتاج', 'new-production', '', 'primary-btn')}</div>${filters()}${rows.length ? table(['الأمر والموعد', 'الصنف', 'الخط والوردية', 'الموقع والمسؤول', 'الكميات', 'الحالة', 'التفاصيل'], rows) : empty('لا توجد أوامر في هذا العرض', 'أنشئ أمرًا، حدّد موقع الإنتاج ومسؤوله، ثم ابدأ التشغيل وأثبت الناتج بعد المراجعة.')}${pager(d.records)}</section><div class="supply-info">الخامات تُصرف من عهدة الإنتاج عند إثبات الناتج، وتُحفظ دفعاتها المستخدمة. إذا لم تضف خامات، يظهر الأمر صراحةً دون خامات موثقة؛ لا يفترض النظام تكلفة أو وصفة تصنيع تلقائيًا.</div>`;
  }
  function sites() {
    const d = state.data,
      s = d.summary;
    const cards = d.options.warehouses
      .map(
        (w) =>
          `<article class="supply-site"><div class="supply-site-top"><span class="supply-site-symbol" aria-hidden="true">${w.type === 'production' ? '⚙' : '▤'}</span>${status(w.type)}${!w.is_active ? '<span class="supply-badge blocked">موقوف</span>' : ''}</div><h3>${esc(w.name)}</h3><p class="supply-code">${esc(w.code || 'رمز الموقع غير محدد')}</p><dl><div><dt>مسؤول العهدة</dt><dd>${esc(w.custodian?.name || 'غير مربوط بحساب — يربطه المدير')}</dd></div><div><dt>الموقع</dt><dd>${esc(w.address || 'العنوان غير مسجل')}</dd></div><div><dt>الهاتف</dt><dd>${esc(w.phone || 'غير مسجل')}</dd></div><div><dt>السعة</dt><dd>${w.capacity_units ? n(w.capacity_units) + ' وحدة' : 'غير محددة'}</dd></div></dl><div class="supply-site-actions">${btn('الحركة والطلبات', 'site', w.id)}${d.can_manage && w.type !== 'vehicle' ? btn('تعديل الموقع', 'edit-site', w.id) : ''}</div></article>`,
      )
      .join('');
    const rows = d.records.data.map(
      (b) =>
        `<tr><td><strong>${esc(b.product_name)}</strong><small>${esc(b.sku)} · ${esc(b.unit)}</small></td><td>${esc(b.warehouse_name)}</td><td>${n(b.quantity)}</td><td>${n(b.reserved)}</td><td>${n(b.unusable)}</td><td><strong class="${b.available <= b.reorder_level ? 'supply-warning' : 'supply-good'}">${n(b.available)}</strong><small>حد التنبيه ${n(b.reorder_level)}</small></td><td><a href="/traceability?product_id=${b.product_id}&warehouse_id=${b.warehouse_id}" class="supply-text-link">الدفعات ←</a></td></tr>`,
    );
    const exportParams = new URLSearchParams(
      Object.entries(state.filters).filter(([k, v]) => ['warehouse_id', 'product_id'].includes(k) && v),
    );
    return `<div class="supply-metrics">${metric('المواقع المرئية', s.sites, 'بحسب صلاحيتك ومسؤوليتك', 1)}${metric('الرصيد الفعلي', s.physical, 'مجموع الوحدات بمختلف الأصناف', 2)}${metric('محجوز للتسليم', s.reserved, 'ينتظر موافقة المستلمين', 3)}${metric('دفعات تحتاج انتباهًا', s.attention, 'حجر / إيقاف / صلاحية خلال 30 يومًا', 4)}</div><section class="supply-panel"><div class="supply-panel-head"><div><h2>المواقع ومسؤولو العهد</h2><p>كل مخزن أو موقع إنتاج مربوط بحساب المسؤول عن استلامه وصرفه.</p></div>${d.can_manage ? '<div class="supply-actions">' + btn('تعريف صنف', 'new-product') + btn('رصيد خارجي / افتتاحي', 'external-stock') + btn('+ موقع وعهدة', 'new-site', '', 'primary-btn') + '</div>' : ''}</div>${cards ? `<div class="supply-sites">${cards}</div>` : empty('لم يربط حسابك بموقع بعد', 'يحدد المدير موقعك ومسؤولية عهدتك من «إضافة موقع وعهدة».')}</section><section class="supply-panel"><div class="supply-panel-head"><div><h2>الرصيد القابل للتصرف</h2><p>المتاح يستبعد الحجز والدفعات المحجورة والموقوفة والمنتهية.</p></div><div class="supply-actions"><a href="/ops/supply/export?${exportParams}" class="secondary-btn">تصدير الأرصدة CSV</a><a href="/handovers?new=stock_transfer" class="primary-btn">طلب تسليم حمولة</a></div></div>${filters()}${rows.length ? table(['الصنف', 'الموقع', 'فعلي', 'محجوز', 'غير صالح للصرف', 'متاح', 'التتبع'], rows) : empty('لا توجد أرصدة لهذا العرض', 'استقبل طلب تسليم من الإنتاج، وافحص الأصناف ثم أقر استلامها من حسابك.')}${pager(d.records)}</section>`;
  }
  function traceList() {
    const d = state.data,
      s = d.summary;
    const rows = d.records.data.map(
      (l) =>
        `<tr><td><button class="supply-record-link" data-supply="lot" data-id="${l.id}">${esc(l.code)}</button><small>${esc(labels[l.source])}</small></td><td><strong>${esc(l.product?.name)}</strong><small>${esc(l.product?.sku)} · ${esc(l.product?.unit)}</small></td><td>${date(l.manufactured_on)}</td><td>${l.expires_on ? date(l.expires_on) : 'لا تاريخ مسجل'}${l.expires_on && l.expires_on.slice(0, 10) < d.today ? '<small class="supply-warning">منتهية — ممنوع الصرف</small>' : ''}</td><td>${status(l.status)}</td><td>${btn('تتبع المسار', 'lot', l.id)}</td></tr>`,
    );
    return `<div class="supply-metrics">${metric('دفعات مرئية', s.lots, 'تاريخ كل دفعة محفوظ', 1)}${metric('من الإنتاج', s.production, 'مرتبطة بأمر إنتاج', 2)}${metric('أرصدة سابقة', s.legacy, 'مصدرها قبل التفعيل غير موثق', 3)}${metric('تحت الحجز النوعي', s.held, 'حجر جودة أو إيقاف', 4)}</div><section class="supply-panel"><div class="supply-panel-head"><div><h2>دليل الدفعات ومسارها</h2><p>افتح الدفعة لمعرفة مصدرها، وأين رصيدها، ومن سلّم ومن استلم، والفواتير التي صُرفت منها.</p></div>${d.can_manage || document.body.dataset.role === 'representative' ? btn('إثبات تسليم عميل', 'deliveries') : ''}</div>${filters()}${rows.length ? table(['رقم الدفعة ومصدرها', 'الصنف', 'تاريخ الإنتاج', 'انتهاء الصلاحية', 'الجودة', 'المسار'], rows) : empty('لا توجد دفعات في هذا العرض', 'الدفعات الجديدة تبدأ من إثبات الإنتاج أو الاستلام الخارجي، وتحتفظ برقمها في كل تحويل.')}${pager(d.records)}</section><div class="supply-info">الأرصدة القديمة موسومة «افتتاحي قديم» من تاريخ تفعيل التتبع. لا يُنسب لها إنتاج أو تسليم تاريخي غير موجود. صرف الفاتورة وحده لا يمثل توقيع العميل؛ يرفق المندوب إثبات الاستلام الفعلي.</div>`;
  }
  async function load() {
    const params = new URLSearchParams({
      section,
      page: state.page,
      ...state.filters,
    });
    const d = await api('/ops/supply?' + params);
    state.data = d;
    root.innerHTML =
      hero() + (section === 'production' ? production() : section === 'warehouses' ? sites() : traceList());
    $('#updated-at').textContent =
      'آخر تحديث ' +
      new Date(d.as_of).toLocaleTimeString('ar-SA', {
        hour: '2-digit',
        minute: '2-digit',
      });
  }
  function newProduction(id = '') {
    const existing = id ? state.current : null;
    const d = state.data,
      ws = d.options.warehouses.filter((w) => w.type === 'production' && w.is_active);
    if (!ws.length) throw new Error('يجب أن يربط المدير حساب الإنتاج بموقع إنتاج فعال أولًا.');
    const ops = d.can_manage ? d.options.operators : [{ id: d.user_id, name: 'حسابي — مسؤول الإنتاج' }];
    show(
      existing ? 'تعديل خطة الإنتاج' : 'تخطيط أمر إنتاج',
      form(
        existing ? 'update' : 'create',
        `${select('warehouse_id', 'موقع الإنتاج', ws, '', 'required')}${select('operator_id', 'المسؤول عن التنفيذ', ops, '', 'required')}${select(
          'product_id',
          'الصنف الناتج',
          d.options.products.filter((p) => p.is_active),
          '',
          'required',
        )}${input('planned_quantity', 'الكمية المستهدفة', 'number', '', 'min="1" max="1000000" required')}${input('line', 'خط / ماكينة الإنتاج', 'text', '', 'required maxlength="100"')}${input('shift', 'الوردية', 'text', '', 'required maxlength="60"')}${input('scheduled_on', 'موعد التشغيل', 'date', d.today, 'required')}${note('notes', 'تعليمات التشغيل ومواصفات الناتج')}${existing ? '<input type="hidden" name="version" value="' + esc(existing.version) + '">' + note('reason', 'سبب تعديل الخطة') : ''}`,
        id,
      ),
    );
    if (existing) {
      for (const key of [
        'warehouse_id',
        'operator_id',
        'product_id',
        'planned_quantity',
        'line',
        'shift',
        'notes',
      ]) {
        $('[name="' + key + '"]', content).value = existing[key];
      }
      $('[name="scheduled_on"]', content).value = existing.scheduled_on.slice(0, 10);
    }
    $('[name="warehouse_id"]', content).addEventListener('change', (e) => {
      const w = ws.find((x) => x.id === Number(e.target.value));
      if (w) $('[name="operator_id"]', content).value = w.custodian_id;
    });
  }
  function siteForm(id) {
    const w = state.data.options.warehouses.find((x) => x.id === Number(id)) || {
      type: 'warehouse',
      is_active: true,
    };
    show(
      id ? 'تعديل الموقع ومسؤوله' : 'إضافة موقع وعهدة',
      form(
        'site',
        `${input('name', 'اسم الموقع', 'text', w.name || '', 'required maxlength="150"')}${input('code', 'رمز الموقع', 'text', w.code || '', 'required maxlength="40" pattern="[A-Za-z0-9][A-Za-z0-9._-]{1,39}"')}${select(
          'type',
          'نوع الموقع',
          [
            { id: 'warehouse', name: 'مخزن' },
            { id: 'production', name: 'موقع إنتاج' },
          ],
          w.type,
          'required',
        )}${select(
          'custodian_id',
          'الحساب المسؤول عن العهدة',
          state.data.options.custodians.filter(
            (u) => u.role === (w.type === 'production' ? 'production' : 'warehouse_keeper'),
          ),
          w.custodian_id,
          'required',
        )}${input('address', 'عنوان الموقع', 'text', w.address || '', 'maxlength="300"')}${input('phone', 'هاتف الموقع', 'tel', w.phone || '', 'maxlength="30"')}${input('capacity_units', 'السعة بالوحدات — اختيارية', 'number', w.capacity_units || '', 'min="1" max="10000000"')}<label>حالة الموقع<select name="is_active"><option value="1" ${w.is_active ? 'selected' : ''}>فعال</option><option value="0" ${!w.is_active ? 'selected' : ''}>موقوف</option></select></label>${note('notes', 'الملاحظات وسبب التعديل', w.notes || '')}<p class="supply-form-hint supply-span">تغيير صاحب عهدة بها رصيد يتطلب نقل الرصيد بتسليم معتمد أولًا. المواقع القديمة غير المربوطة يمكن ربطها أول مرة مع حفظ السبب.</p>`,
        id,
      ),
    );
    $('[name="type"]', content).onchange = (e) => {
      $('[name="custodian_id"]', content).innerHTML =
        '<option value="">اختر…</option>' +
        options(
          state.data.options.custodians.filter(
            (u) => u.role === (e.target.value === 'production' ? 'production' : 'warehouse_keeper'),
          ),
        );
    };
  }
  async function orderDetail(id) {
    const d = await api('/ops/supply/production/' + id),
      o = d.order;
    state.current = o;
    const qualityUrl = (lotId) => {
      const request = (d.quality_requests || []).find((q) => q.lot_id === lotId);
      return request ? '/quality?inspection=' + Number(request.id) : '/quality';
    };
    const materials = d.materials.map(
      (m) =>
        `<tr><td>${esc(m.name)}</td><td>${n(m.quantity)} ${esc(m.unit)}</td><td>${JSON.parse(m.lots)
          .map(
            (l) =>
              `<button class="supply-record-link" data-supply="lot" data-id="${l.lot_id}">${esc(l.code)} × ${n(l.quantity)}</button>`,
          )
          .join('<br>')}</td></tr>`,
    );
    show(
      'أمر ' + o.code,
      `${status(o.status)}${facts([
        ['الصنف', o.product?.name],
        ['رمز الصنف', o.product?.sku],
        ['وحدة القياس', o.product?.unit],
        ['الموقع', o.warehouse?.name],
        ['مسؤول التنفيذ', o.operator?.name],
        ['الخط / الماكينة', o.line],
        ['الوردية', o.shift],
        ['موعد التشغيل', date(o.scheduled_on)],
        ['المستهدف', n(o.planned_quantity)],
        ['سليم', n(o.good_quantity)],
        ['تالف', n(o.scrap_quantity)],
        ['بدأ', date(o.started_at)],
        ['أغلق', date(o.completed_at)],
      ])}<h3>تعليمات التشغيل</h3><p class="supply-pre">${esc(o.notes)}</p><div class="supply-actions">${o.status === 'planned' ? btn('تعديل الخطة', 'edit-order', o.id) : ''}${o.status === 'planned' ? btn('بدء التشغيل', 'start', o.id, 'primary-btn') : ''}${o.status === 'in_progress' ? btn('إثبات الناتج والخامات', 'finish', o.id, 'primary-btn') : ''}${['planned', 'in_progress'].includes(o.status) ? btn('إلغاء مع السبب', 'cancel', o.id) : ''}${d.lots.some((l) => l.status === 'released' && (!l.expires_on || l.expires_on.slice(0, 10) >= state.data.today)) ? `<a href="/handovers?new=stock_transfer&from_id=${o.warehouse_id}&product_id=${o.product_id}" class="primary-btn">تسليم صنف الأمر للمخزن ←</a>` : ''}</div><h3>الخامات المصروفة بالفعل</h3>${materials.length ? table(['الخامة', 'الكمية', 'الدفعات الأصلية'], materials) : '<p class="supply-muted">لا خامات موثقة لهذا الأمر حتى الآن. لا يحتسب النظام خامات افتراضية.</p>'}<h3>الدفعات الناتجة</h3>${d.lots.map((l) => `<div class="supply-lot-strip"><button class="supply-record-link" data-supply="lot" data-id="${l.id}">${esc(l.code)}</button>${status(l.status)}${l.status === 'quarantine' || l.status === 'blocked' ? `<span class="supply-warning">غير جاهزة للتسليم؛ تنتظر الفحص والاعتماد المستقل. <a href="${qualityUrl(l.id)}">متابعة المسؤول والخطوة التالية</a></span>` : ''}<span>إنتاج ${date(l.manufactured_on)} · انتهاء ${date(l.expires_on)}</span></div>`).join('') || '<p class="supply-muted">لا ناتج مثبت بعد.</p>'}<h3>ملاحظات الإنتاج وإغلاق الأمر</h3><p class="supply-pre">${esc(o.quality_note || 'لا ملاحظات إنتاج مسجلة.')}</p><p class="supply-pre">${esc(o.closing_note || '')}</p>`,
    );
  }
  function materialLine() {
    return `<div class="supply-material-row">${select(
      'material_product',
      'الخامة',
      state.data.options.products.filter((p) => p.is_active),
      '',
      'required',
    )}${input('material_quantity', 'الكمية الفعلية', 'number', '1', 'required min="1" max="1000000"')}<button type="button" class="icon-btn" data-supply="remove-material" aria-label="حذف سطر الخام">×</button></div>`;
  }
  function finish(id) {
    const o = state.current;
    if (!o || o.id !== Number(id)) return;
    show(
      'إثبات ناتج ' + o.code,
      form(
        'complete',
        `${input('good_quantity', 'الكمية السليمة الفعلية', 'number', o.planned_quantity, 'required min="1" max="1000000"')}${input('scrap_quantity', 'كمية التالف الفعلية', 'number', '0', 'required min="0" max="1000000"')}${input('lot_code', 'رقم دفعة فريد', 'text', '', 'required maxlength="80" pattern="[A-Za-z0-9][A-Za-z0-9._-]{1,79}" placeholder="P-20261104-001"')}${input('manufactured_on', 'تاريخ الإنتاج', 'date', state.data.today, 'required')}${input('expires_on', 'انتهاء الصلاحية — إن وجد', 'date', '', '')}<div class="supply-info supply-span">الناتج يسجل تحت حجر الجودة تلقائيًا، ثم يرسل لفاحص مستقل ومعتمد مستقل. <a href="/quality">متابعة مهام الجودة</a></div><input type="hidden" name="quality" value="quarantine">${note('quality_note', 'ملاحظات الإنتاج عن المواصفات والحالة')}${note('note', 'ملاحظات الناتج والتالف والفروقات عن الخطة')}<fieldset class="supply-span"><legend>الخامات المستخدمة بالفعل</legend><p>الصرف من موقع الإنتاج نفسه. حمّل الخامات إليه بطلب من المخزن يوافق عليه مسؤول الإنتاج أولًا.</p><div id="supply-materials"></div>${btn('+ خامة مستهلكة', 'material')}<label class="supply-check"><input type="checkbox" name="materials_reviewed" required> راجعت الخامات الفعلية؛ عدم إضافة سطور يعني عدم وجود خامات موثقة لهذا الأمر.</label></fieldset><p class="supply-form-hint supply-span">الحفظ يقفل الأمر ويسجل الدفعة والمخزون مرة واحدة. التالف لا يضاف إلى الناتج السليم. حجر الجودة يمنع تسليم الدفعة حتى اعتماد الجودة بعد الفحص. تابع المسؤول والخطوة التالية في قسم الجودة.</p>`,
        id,
      ),
    );
  }
  async function siteDetail(id, page = 1) {
    const d = await api('/ops/supply/warehouses/' + id + '?page=' + page),
      w = d.warehouse;
    show(
      w.name,
      `${status(w.type)}${facts([
        ['الرمز', w.code],
        ['المسؤول', w.custodian?.name],
        ['اسم الدخول', w.custodian?.username],
        ['العنوان', w.address],
        ['الهاتف', w.phone],
        ['السعة', w.capacity_units ? n(w.capacity_units) : 'غير محددة'],
        ['الحالة', w.is_active ? 'فعال' : 'موقوف'],
      ])}<p class="supply-pre">${esc(w.notes)}</p><div class="supply-actions"><a href="/handovers?new=stock_transfer" class="primary-btn">إرسال طلب تسليم</a><a href="/traceability?warehouse_id=${w.id}" class="secondary-btn">دفعات الموقع</a></div><h3>طلبات تنتظر رد الطرف الآخر</h3>${d.pending.length ? d.pending.map((h) => `<a href="/handovers?request=${h.id}" class="supply-pending"><strong>${esc(h.code)}</strong><span>${esc(h.sender?.name)} ← ${esc(h.recipient?.name)} · ${n(h.reserved_units)} وحدة</span></a>`).join('') : '<p class="supply-muted">لا توجد طلبات معلقة مرئية لك.</p>'}<h3>سجل الحركة الفعلية</h3>${
        d.movements.data.length
          ? table(
              ['التاريخ', 'الصنف', 'نوع الحركة', 'الكمية', 'المرجع / المنفذ'],
              d.movements.data.map(
                (m) =>
                  `<tr><td>${date(m.created_at)}</td><td>${esc(m.product_name)}</td><td>${esc(labels[m.reason] || m.reason)}</td><td class="${m.quantity < 0 ? 'supply-warning' : 'supply-good'}">${n(m.quantity)} ${esc(m.unit)}</td><td>${esc(m.reference)}<small>${esc(m.actor_name || 'افتتاحي')}</small></td></tr>`,
              ),
            )
          : empty('لا توجد حركة بعد', 'الطلب المعلق لا يسجل حركة حتى إقرار المستلم.')
      }${pager(d.movements, 'site-page', id)}`,
    );
  }
  async function lotDetail(id, page = 1) {
    const d = await api('/ops/supply/lots/' + id + '?page=' + page),
      l = d.lot;
    const trace = d.entries.data.map((e) => {
      const h = ['transfer_in', 'transfer_out'].includes(e.reason)
          ? d.handovers.find((x) => x.code === e.reference)
          : null,
        so = e.reason === 'sale' ? d.sales.find((x) => x.code === e.reference) : null;
      return `<li class="supply-timeline-item"><span class="supply-timeline-dot"></span><div><span class="supply-timeline-date">${date(e.created_at)}</span><h4>${esc(labels[e.reason] || e.reason)} <b class="${e.quantity < 0 ? 'supply-warning' : 'supply-good'}">${n(e.quantity)}</b></h4><p>${esc(e.warehouse_name)} · ${esc(e.actor_name || 'رصيد تاريخي')}</p><span class="supply-code">${esc(e.reference)}</span>${h ? `<p>${esc(h.sender?.name)} ← ${esc(h.recipient?.name)} · ${status(h.status)} · إقرار ${date(h.decided_at)}</p>` : ''}${so ? `<p>العميل: ${esc(so.customer?.name)} · ${esc(so.customer?.code)}</p><small>${d.proofs.some((p) => p.order_id === so.id) ? 'يوجد إثبات تسليم مرفوع باسم المستلم' : 'صرف فاتورة؛ لا يوجد إثبات استلام عميل مرفوع'}</small>` : ''}</div></li>`;
    });
    const raw = d.materials
      .map(
        (m) =>
          `<div class="supply-lot-strip"><strong>${esc(m.name)} · ${n(m.quantity)} ${esc(m.unit)}</strong>${JSON.parse(
            m.lots,
          )
            .map(
              (x) =>
                `<button class="supply-record-link" data-supply="lot" data-id="${x.lot_id}">${esc(x.code)} × ${n(x.quantity)}</button>`,
            )
            .join('')}</div>`,
      )
      .join('');
    show(
      'مسار الدفعة ' + l.code,
      `<div class="supply-lot-heading"><div><span class="supply-kicker">BATCH / FULL JOURNEY</span><h3>${esc(l.product?.name)}</h3><span class="supply-code">${esc(l.product?.sku)} / ${esc(l.code)}</span></div>${status(l.status)}</div>${facts(
        [
          ['المصدر', labels[l.source]],
          ['أمر الإنتاج', l.production?.code || 'لا يوجد'],
          ['الإنتاج', date(l.manufactured_on)],
          ['الانتهاء', date(l.expires_on)],
          ['الوحدة', l.product?.unit],
        ],
      )}<p class="supply-pre">${esc(l.notes || '')}</p>${l.source === 'legacy' ? '<div class="supply-info">هذه دفعة رصيد سابق. يبدأ تتبعها من الرصيد الافتتاحي ولا يثبت لها تاريخ إنتاج غير مسجل.</div>' : ''}${state.data.can_manage ? btn('حجر / إيقاف الصرف', 'quality', l.id) : ''}<a href="/quality" class="secondary-btn">متابعة الفحص والاعتماد</a><h3>أين الرصيد الآن؟</h3><div class="supply-balance-strips">${d.balances.map((b) => `<div><span>${esc(b.name)}</span><strong>${n(b.quantity)} ${esc(l.product?.unit)}</strong></div>`).join('') || '<p>لا رصيد متبقٍ؛ راجع حركة الصرف أدناه.</p>'}</div>${raw ? '<h3>من أي خامات جاءت؟</h3>' + raw : ''}${d.descendants.length ? '<h3>دخلت في إنتاج دفعات أخرى</h3>' + d.descendants.map((x) => btn(x.code, 'lot', x.id)).join('') : ''}${d.quality_history?.length ? '<h3>آخر قرارات الجودة (حتى 50 قرارًا)</h3>' + d.quality_history.map((q) => '<div class="supply-lot-strip"><span>' + date(q.created_at) + ' · ' + esc(labels[q.metadata.before] || q.metadata.before) + ' ← ' + esc(labels[q.metadata.after] || q.metadata.after) + '</span><span>' + esc(q.metadata.note) + '</span></div>').join('') : ''}<h3>سجل مسار الدفعة</h3><ol class="supply-timeline">${trace.join('')}</ol>${pager(d.entries, 'lot-page', id)}<p class="supply-form-hint">الموافقة على حمولة تثبت عهدة الحساب المستلم. إثبات العميل مرفق مستقل يسجله المندوب، ولا يمثل موافقة من حساب عميل داخل النظام.</p>`,
    );
  }
  async function deliveries(page = 1, q = '') {
    const d = await api('/ops/supply/deliveries?page=' + page + '&q=' + encodeURIComponent(q));
    show(
      'إثباتات تسليم العملاء',
      `<form id="delivery-search" class="supply-filters"><label>رقم الفاتورة<input name="q" value="${esc(q)}" type="search"></label><button class="secondary-btn">بحث</button></form><p class="supply-form-hint">ارفع سند التسليم الموقّع من العميل. نسجل اسم المستلم، ومن رفعه، وبصمة الملف، دون إعادة صرف أو تحصيل.</p>${
        d.orders.data.length
          ? table(
              ['الفاتورة', 'العميل', 'الإثباتات', 'رفع الإثبات'],
              d.orders.data.map(
                (o) =>
                  `<tr><td>${esc(o.code)}</td><td>${esc(o.customer?.name)}</td><td>${
                    d.proofs
                      .filter((p) => p.order_id === o.id)
                      .map(
                        (p) =>
                          `<a class="supply-text-link" href="/ops/supply/proofs/${p.id}">${esc(p.receiver_name)} · ${date(p.created_at)}</a>`,
                      )
                      .join('<br>') || 'لم يرفع إثبات'
                  }</td><td>${btn('رفع سند', 'proof', o.id)}</td></tr>`,
              ),
            )
          : empty('لا توجد فواتير مصروفة', 'الفواتير المسودة لا تقبل إثبات التسليم.')
      }${pager(d.orders, 'deliveries-page')}`,
    );
  }
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-supply],[data-supply-close]');
    if (!b) return;
    try {
      if (b.hasAttribute('data-supply-close')) {
        dialog.close();
        return;
      }
      const a = b.dataset.supply,
        id = b.dataset.id;
      if (a === 'new-production') newProduction();
      if (a === 'edit-order') newProduction(id);
      if (a === 'new-product')
        show(
          'تعريف صنف',
          form(
            'product',
            `${input('name', 'اسم الصنف', 'text', '', 'required maxlength="150"')}${input('sku', 'رمز الصنف SKU', 'text', '', 'required maxlength="40"')}${input('unit', 'وحدة المخزون والبيع', 'text', '', 'required maxlength="30" placeholder="كرتون / رول / قطعة"')}${input('price', 'سعر البيع بعملة الشركة', 'number', '', 'required min="0.01" max="100000" step="0.01"')}${input('reorder_level', 'حد تنبيه إعادة الطلب', 'number', '20', 'required min="0" max="100000"')}<p class="supply-span supply-form-hint">تعريف الصنف لا ينشئ رصيدًا. استخدم الإنتاج للناتج أو الرصيد الخارجي للمشتريات والأرصدة الافتتاحية.</p>`,
          ),
        );
      if (a === 'external-stock')
        show(
          'إدخال رصيد خارجي / افتتاحي',
          form(
            'external',
            `${select(
              'warehouse_id',
              'المخزن',
              state.data.options.warehouses.filter((w) => w.type === 'warehouse' && w.is_active),
              '',
              'required',
            )}${select(
              'product_id',
              'الصنف',
              state.data.options.products.filter((p) => p.is_active),
              '',
              'required',
            )}${input('quantity', 'الكمية المستلمة', 'number', '', 'required min="1" max="100000"')}${input('reference', 'مرجع الشراء أو الاستلام الخارجي', 'text', '', 'required minlength="3" maxlength="100"')}${input('supplier', 'المورد / مصدر الافتتاحي', 'text', '', 'maxlength="150"')}${input('lot_code', 'رقم الدفعة لدى المورد — اختياري', 'text', '', 'maxlength="80" pattern="[A-Za-z0-9][A-Za-z0-9._-]{1,79}"')}${input('manufactured_on', 'تاريخ الإنتاج إن كان معروفًا', 'date')}${input('expires_on', 'انتهاء الصلاحية إن وجد', 'date')}<p class="supply-span supply-form-hint">هذه عملية إدارة لرصيد من خارج الدورة الداخلية، وليست تسليمًا من الإنتاج أو من مستخدم آخر. التسليم الداخلي يتم بطلب الطرفين فقط.</p>`,
          ),
        );
      if (a === 'new-site') siteForm();
      if (a === 'edit-site') siteForm(id);
      if (a === 'order') await orderDetail(id);
      if (a === 'lot') await lotDetail(id);
      if (a === 'site') await siteDetail(id);
      if (a === 'finish') finish(id);
      if (a === 'material') {
        if ($('#supply-materials').children.length >= 50) throw new Error('الحد 50 خامة.');
        $('#supply-materials').insertAdjacentHTML('beforeend', materialLine());
      }
      if (a === 'remove-material') b.closest('.supply-material-row').remove();
      if (a === 'start')
        show(
          'تأكيد بدء التشغيل',
          form(
            'start',
            '<p class="supply-span supply-form-hint">يسجل هذا الإجراء وقت البدء الفعلي. لا يزيد المخزون ولا يصرف خامات حتى إثبات الناتج.</p>',
            id,
          ),
        );
      if (a === 'cancel') show('إلغاء أمر الإنتاج', form('cancel', note('note', 'سبب إلغاء الأمر'), id));
      if (a === 'quality')
        show(
          'قرار الجودة للدفعة',
          form(
            'quality',
            `${select(
              'status',
              'حالة الجودة',
              [
                { id: 'quarantine', name: 'حجر للفحص' },
                { id: 'blocked', name: 'إيقاف الصرف' },
              ],
              '',
              'required',
            )}${note('note', 'سبب القرار ونتيجة الفحص')}<p class="supply-span supply-form-hint">لا يزيل القرار أي كميات. حجز الدفعة يمنع صرفها أو اعتماد تحويل معلق يحتوي عليها.</p>`,
            id,
          ),
        );
      if (a === 'page') {
        state.page = Number(id.split(':').pop());
        await load();
      }
      if (a === 'site-page') {
        const [site, p] = id.split(':');
        await siteDetail(site, p);
      }
      if (a === 'lot-page') {
        const [lot, p] = id.split(':');
        await lotDetail(lot, p);
      }
      if (a === 'reset') {
        state.filters = {};
        state.page = 1;
        await load();
      }
      if (a === 'deliveries') await deliveries();
      if (a === 'deliveries-page') await deliveries(Number(id.split(':').pop()));
      if (a === 'proof')
        show(
          'رفع سند تسليم العميل',
          form(
            'proof',
            `${input('receiver_name', 'اسم الشخص المستلم لدى العميل', 'text', '', 'required minlength="2" maxlength="150"')}${note('note', 'ملاحظات الاستلام والفروقات إن وجدت')}<label class="supply-span">سند التسليم الموقّع — PDF / JPEG / PNG، حتى 10 MB<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required></label><label class="supply-span supply-check"><input name="acknowledge" type="checkbox" required> أقر بأنني راجعت المرفق واسم المستلم قبل رفع الإثبات من حسابي.</label>`,
            id,
          ),
        );
    } catch (err) {
      toast(err.message, true);
    }
  });
  document.addEventListener('submit', async (e) => {
    if (e.target.id === 'supply-filters') {
      e.preventDefault();
      state.filters = Object.fromEntries([...new FormData(e.target)].filter(([, v]) => v));
      state.page = 1;
      load().catch((err) => toast(err.message, true));
      return;
    }
    if (e.target.id === 'delivery-search') {
      e.preventDefault();
      deliveries(1, new FormData(e.target).get('q')).catch((err) => toast(err.message, true));
      return;
    }
    const f = e.target.closest('[data-supply-form]');
    if (!f) return;
    e.preventDefault();
    if (f.dataset.busy === 'true') return;
    f.dataset.busy = 'true';
    const submit = $('[type="submit"]', f),
      err = $('.supply-error', f);
    submit.disabled = true;
    err.textContent = '';
    const a = f.dataset.supplyForm,
      id = f.dataset.id,
      fd = new FormData(f);
    let data = Object.fromEntries(fd);
    data.request_id = f.dataset.requestId;
    let url;
    try {
      if (a === 'product') {
        url = '/ops/action/product.create';
        data.price_minor = Math.round(Number(data.price) * 100);
        delete data.price;
        data.reorder_level = Number(data.reorder_level);
      } else if (a === 'external') {
        url = '/ops/action/stock.receive';
        for (const key of ['warehouse_id', 'product_id', 'quantity']) data[key] = Number(data[key]);
        for (const key of ['supplier', 'lot_code', 'manufactured_on', 'expires_on'])
          if (!data[key]) delete data[key];
      } else if (a === 'create' || a === 'update') {
        url = '/ops/supply/production' + (a === 'update' ? '/' + id + '/update' : '');
        if (a === 'update') data.version = Number(data.version);
        for (const k of ['warehouse_id', 'operator_id', 'product_id', 'planned_quantity'])
          data[k] = Number(data[k]);
      } else if (a === 'site') {
        url = '/ops/supply/warehouses';
        data.custodian_id = Number(data.custodian_id);
        data.capacity_units = data.capacity_units ? Number(data.capacity_units) : null;
        data.is_active = data.is_active === '1';
        if (id) data.id = Number(id);
      } else if (a === 'quality') url = '/ops/supply/lots/' + id + '/status';
      else if (a === 'proof') {
        url = '/ops/supply/deliveries/' + id;
        fd.set('request_id', data.request_id);
        fd.set('acknowledge', '1');
        if (fd.get('document').size > 10 * 1024 * 1024) throw new Error('الحد الأقصى 10 MB.');
        data = fd;
      } else {
        url = '/ops/supply/production/' + id + '/' + a;
        if (a === 'complete') {
          data.good_quantity = Number(data.good_quantity);
          data.scrap_quantity = Number(data.scrap_quantity);
          data.expires_on = data.expires_on || null;
          data.materials = [...f.querySelectorAll('.supply-material-row')].map((r) => ({
            product_id: Number($('[name="material_product"]', r).value),
            quantity: Number($('[name="material_quantity"]', r).value),
          }));
          delete data.material_product;
          delete data.material_quantity;
          delete data.materials_reviewed;
        }
      }
      const result = await api(url, data);
      dialog.close();
      toast(result.message);
      await load();
      if (['create', 'update', 'complete', 'start', 'cancel'].includes(a)) await orderDetail(result.id);
      if (a === 'proof') await deliveries();
    } catch (error) {
      err.textContent = error.message;
    } finally {
      f.dataset.busy = 'false';
      submit.disabled = false;
    }
  });
  $('#page-title').textContent = title[0];
  $('#page-description').textContent = title[1];
  $('#section-code').textContent = title[2];
  $('.global-search').hidden = true;
  $('#main-action').hidden = true;
  $('#refresh').onclick = () => load().catch((e) => toast(e.message, true));
  document.addEventListener('amrbit:handover-changed', () => {
    if (!dialog.open) load().catch((e) => toast(e.message, true));
  });
  const params = new URLSearchParams(location.search);
  for (const k of ['q', 'warehouse_id', 'product_id', 'status'])
    if (params.get(k)) state.filters[k] = params.get(k);
  load()
    .then(() => {
      if (params.get('lot') && /^\d+$/.test(params.get('lot'))) return lotDetail(params.get('lot'));
    })
    .catch((e) => {
      root.innerHTML = empty('تعذر تحميل مساحة العمل', e.message);
    });
})();
