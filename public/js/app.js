'use strict';
(() => {
  const $ = (s) => document.querySelector(s),
    e = (v) =>
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
  function requestUuid() {
    if (typeof globalThis.crypto?.randomUUID === 'function') return globalThis.crypto.randomUUID();
    // LAN HTTP still provides getRandomValues, even where randomUUID requires HTTPS.
    const bytes = new Uint8Array(16);
    globalThis.crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
  }
  const titles = {
    dashboard: ['مركز العمليات', 'الصورة الكاملة للميدان. من الخطة إلى آخر تحصيل.', 'COMMAND CENTER'],
    tracking: ['متابعة الميدان', 'مسارات الرحلات، عمر آخر موقع، وتفاصيل كل محطة.', 'FIELD VIEW'],
    trips: ['الرحلات والمسارات', 'خطّط محطاتك، وزّع العمل، وتابع التنفيذ.', 'ROUTE PLANNING'],
    visits: ['الزيارات', 'من الموعد إلى إثبات الحضور ونتيجة التنفيذ.', 'VISITS'],
    customers: ['العملاء والمناطق', 'دليل العملاء، نطاق الخدمة، وإثبات الموقع.', 'CUSTOMERS'],
    team: ['الفريق والصلاحيات', 'أضف المناديب، حدد تغطيتهم، وأدر الدخول والسائقين.', 'TEAM'],
    vehicles: ['الأسطول وعُهد السيارات', 'الأنواع والوثائق والسائق المستلم وتاريخ الإسناد.', 'FLEET'],
    inventory: ['المخزون والعُهد', 'كل وحدة لها مصدر وحركة ورصيد يمكن مراجعته.', 'INVENTORY'],
    orders: ['الطلبات والمبيعات', 'أسعار محسوبة داخل النظام، وتسليم مرتبط بالمخزون.', 'SALES'],
    collections: ['التحصيل', 'استلام الأموال، إيصالاتها، وما تبقّى للعميل.', 'COLLECTIONS'],
    settlements: ['عهدة النقد', 'النقد المستلم، طلبات تسليمه، واعتماد الحسابات.', 'CASH CUSTODY'],
    receivables: ['الآجل والمديونيات', 'دين كل عميل ومندوب، والاستحقاقات التي تحتاج متابعة.', 'RECEIVABLES'],
    reports: ['التقارير والتحليل', 'اقرأ أداء الفترة، ثم انتقل إلى العمليات التي صنعت الأرقام.', 'ANALYTICS'],
    audit: ['سجل العمليات', 'من نفّذ العملية؟ متى؟ وعلى أي سجل؟', 'AUDIT TRAIL'],
    settings: ['إعدادات الشركة', 'مساحة عمل مستقلة، صلاحيات واضحة، وخصوصية الموقع.', 'WORKSPACE'],
  };
  const section = document.body.dataset.section,
    role = document.body.dataset.role,
    manager = ['manager', 'supervisor'].includes(role),
    canMoney = ['manager', 'supervisor', 'accountant', 'representative'].includes(role),
    canCollect = ['manager', 'accountant', 'representative'].includes(role),
    rep = role === 'representative';
  let data = null,
    map = null,
    mapLayers = [],
    playMarker = null,
    selectedTrip = null,
    query = new URLSearchParams(location.search).get('q') || '',
    queueStatus = '',
    adminTab = new URLSearchParams(location.search).get('tab') || 'members',
    tripStops = [],
    fleetStatus = '',
    selectedVehicle = Number(new URLSearchParams(location.search).get('vehicle')) || null,
    inventoryWarehouse = new URLSearchParams(location.search).get('warehouse') || '',
    statusFilter = '',
    dateFilter = '',
    tab = 'balances',
    mapCenter = null,
    mapZoom = null,
    dialogAction = '',
    dialogExtra = {},
    currentRequestId = '',
    busy = false,
    signature = '';
  const colors = ['#4d673b', '#627c48', '#9b8649', '#4e7770', '#8b6b43', '#556638'];
  const statusNames = {
    planned: 'مخطط',
    active: 'نشطة',
    closed: 'مغلقة',
    completed: 'مكتملة',
    in_progress: 'قيد التنفيذ',
    failed: 'لم تُنفذ',
    skipped: 'تجاوز مبرر',
    cancelled: 'ملغاة',
    draft: 'بانتظار التسليم',
    fulfilled: 'تم التسليم',
    available: 'متاحة',
    maintenance: 'صيانة',
  };
  const namesRole = {
    manager: 'مدير الشركة',
    supervisor: 'مشرف العمليات',
    representative: 'مندوب',
    accountant: 'محاسب',
    viewer: 'مشاهد',
    driver: 'سائق',
    production: 'مسؤول الإنتاج',
    quality_inspector: 'فاحص الجودة',
    quality_manager: 'معتمد الجودة',
    warehouse_keeper: 'أمين المستودع',
  };
  const money = (n) =>
    Number(n || 0).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }) + ' <small class="currency">ر.س</small>';
  const amount = (n) => money(Number(n) / 100),
    num = (n) => Number(n || 0).toLocaleString('en-US');
  const shortName = (s) =>
      String(s || '')
        .split(' ')
        .slice(0, 2)
        .join(' '),
    initial = (s) =>
      String(s || '')
        .trim()
        .slice(0, 1);
  const when = (v) =>
    v
      ? new Date(v).toLocaleString('ar-SA', {
          calendar: 'gregory',
          month: 'short',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit',
          timeZone: data?.company.timezone || 'Asia/Riyadh',
        })
      : '—';
  const day = (v) =>
    v
      ? new Date(v).toLocaleDateString('ar-SA', {
          calendar: 'gregory',
          month: 'short',
          day: 'numeric',
          timeZone: data?.company.timezone || 'Asia/Riyadh',
        })
      : 'غير محدد';
  const isoDay = (v) =>
    new Intl.DateTimeFormat('en-CA', {
      timeZone: data.company.timezone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).format(new Date(v));
  const pill = (status, text) =>
    `<span class="pill ${['failed'].includes(status) ? 'danger' : ['planned', 'draft'].includes(status) ? 'neutral' : status === 'in_progress' ? 'purple' : ''}">${e(text || statusNames[status] || status)}</span>`;
  const svg = (name) =>
    ({
      route:
        '<path d="M5 7a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm14 16a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM5 7v8a4 4 0 0 0 8 0V9a4 4 0 0 1 8 0"/>',
      check: '<rect x="3" y="3" width="18" height="18" rx="5"/><path d="m7 12 4 4 6-8"/>',
      wallet: '<rect x="3" y="5" width="18" height="15" rx="3"/><path d="M15 10h6v5h-6Z"/>',
      clock: '<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/>',
      box: '<path d="m12 3 9 5v9l-9 5-9-5V8ZM3 8l9 5 9-5M12 13v9"/>',
      pin: '<path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
      users:
        '<circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 4a3 3 0 0 1 0 6M18 15a5 5 0 0 1 3 6"/>',
    })[name] || '<path d="M4 19V5m0 14h16M9 15v-4m5 4V7m5 8V9"/>';
  const icon = (name) =>
    `<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${svg(name)}</svg>`;
  const btn = (action, id, label, kind = 'quiet-btn') => {
    if (
      rep &&
      data?.my_permissions &&
      ((['order.create', 'order.fulfill'].includes(action) && !data.my_permissions.can_sell) ||
        (action === 'collection.create' && !data.my_permissions.can_collect))
    )
      return '';
    return `<button class="${kind}" data-action="${e(action)}" data-id="${e(id)}">${e(label)}</button>`;
  };
  const empty = (text = 'لا توجد سجلات تطابق اختيارك.') =>
    `<div class="empty"><strong>${e(text)}</strong>غيّر البحث أو الفلاتر، أو أضف عملية جديدة.</div>`;
  const table = (heads, rows, note = '') =>
    `<div class="panel"><div class="table-wrap"><table><thead><tr>${heads.map((x) => `<th>${e(x)}</th>`).join('')}</tr></thead><tbody>${rows.join('')}</tbody></table></div>${!rows.length ? empty() : ''}${note ? `<div class="pagination-note">${e(note)}</div>` : ''}</div>`;
  const tr = (values) => `<tr>${values.map((v) => `<td>${v}</td>`).join('')}</tr>`;
  function metric(label, value, unit, foot, ico = 'chart', cls = '', percent = null) {
    return `<div class="metric"><div class="metric-top"><span class="metric-label">${e(label)}</span><span class="metric-icon ${cls}">${icon(ico)}</span></div><div class="metric-value">${value}<span>${e(unit)}</span></div><div class="metric-foot">${foot}</div>${percent !== null ? `<div class="progress"><i style="width:${Math.max(0, Math.min(100, percent))}%"></i></div>` : ''}</div>`;
  }
  const heading = (title, sub = '', link = '', label = 'عرض التفاصيل') =>
    `<div class="panel-heading"><div><h2>${e(title)}</h2><p>${e(sub)}</p></div>${link ? `<a class="text-link" href="${e(link)}">${e(label)} ←</a>` : ''}</div>`;
  function match(r) {
    return !query || JSON.stringify(r).toLowerCase().includes(query.toLowerCase());
  }
  function filter(rows) {
    return rows.filter(
      (r) =>
        match(r) &&
        (!statusFilter || r.status === statusFilter) &&
        (!dateFilter ||
          String(r.scheduled_for || r.created_at || r.collected_at || '').slice(0, 10) === dateFilter),
    );
  }
  function filters(statuses = [], withDate = false) {
    return `<div class="filters"><div class="filters-left">${statuses.length ? `<select id="status-filter" class="filter-select" aria-label="الحالة"><option value="">كل الحالات</option>${statuses.map((s) => `<option value="${s}" ${statusFilter === s ? 'selected' : ''}>${e(statusNames[s])}</option>`).join('')}</select>` : ''}${withDate ? `<input class="filter-date" id="date-filter" type="date" value="${e(dateFilter)}" aria-label="تاريخ العمليات"><button class="quiet-btn" data-clear="filters">كل التواريخ</button>` : ''}<span class="results-count">البحث من أعلى الصفحة</span></div><span class="results-count">الشركة: ${e(data.company.name)}</span></div>`;
  }
  const lastPoint = (id) => data.points.filter((p) => Number(p.trip_id) === Number(id)).at(-1);
  function freshness(t) {
    const p = lastPoint(t.id);
    if (!p) return ['neutral', 'لم يُرسل موقعًا', '—'];
    const mins = Math.max(0, Math.floor((Date.now() - new Date(p.captured_at)) / 60000));
    return [
      mins <= 2 && t.status === 'active' ? '' : 'warning',
      p.source === 'demo' ? 'موقع تجريبي' : mins <= 2 ? 'موقع حديث' : 'موقع قديم',
      mins < 1 ? 'قبل أقل من دقيقة' : `قبل ${mins} دقيقة`,
    ];
  }
  function repRows() {
    return (
      data.trips
        .filter((t) => t.status === 'active' || t.status === 'planned')
        .map((t, i) => {
          const s = freshness(t),
            v = data.visits.filter((v) => v.trip_id === t.id),
            done = v.filter((v) => v.status === 'completed').length;
          return `<div class="rep-row" data-trip="${t.id}"><span class="rep-initial color${i % 4}">${e(initial(t.representative.name))}</span><div><strong>${e(shortName(t.representative.name))}</strong><small>${e(t.vehicle.name)}</small></div><div class="rep-meta"><b>${done} / ${v.length} محطات</b><small>${e(s[2])}</small></div></div>`;
        })
        .join('') || empty('لا توجد رحلات مفتوحة.')
    );
  }
  function alerts() {
    const stale = data.trips.filter((t) => t.status === 'active' && freshness(t)[0] === 'warning'),
      low = data.balances.filter((b) => (b.available_quantity ?? b.quantity) < b.product.reorder_level),
      failed = data.visits.filter(
        (v) => v.status === 'failed' && v.scheduled_for.slice(0, 10) === data.today,
      );
    let a = [];
    if (stale.length)
      a.push([
        'clock',
        `${stale.length} رحلة ببيانات موقع قديمة`,
        'راجع آخر نقطة قبل الاعتماد على موقع المندوب.',
        'tracking',
      ]);
    if (low.length)
      a.push([
        'box',
        `${low.length} رصيد تحت حد إعادة الطلب`,
        'تحتاج هذه الأصناف متابعة المستودع أو العهدة.',
        'inventory',
      ]);
    if (canMoney && Number(data.metrics.unpaid_minor) > 0)
      a.push([
        'wallet',
        `${amount(data.metrics.unpaid_minor)} آجل بالسوق`,
        'راجع الاستحقاقات والسداد الجزئي لكل عميل.',
        'receivables',
      ]);
    if (failed.length)
      a.push(['pin', `${failed.length} زيارة لم تُنفذ`, 'راجع سبب التعذر وجدولة متابعة للعميل.', 'visits']);
    return a.length
      ? a
          .map(
            ([ico, title, desc, link]) =>
              `<div class="attention-item"><span class="attention-icon">${icon(ico)}</span><div><strong>${ico === 'wallet' ? title : e(title)}</strong><p>${e(desc)}</p></div><a href="/${link}">مراجعة ←</a></div>`,
          )
          .join('')
      : '<div class="summary-note">لا توجد استثناءات ضمن السجلات المعروضة.</div>';
  }
  function salesChart() {
    const days = [];
    for (let i = 6; i >= 0; i--) {
      let d = new Date();
      d.setDate(d.getDate() - i);
      const key = isoDay(d);
      days.push({
        day: d,
        key,
        value: data.orders
          .filter((o) => o.status === 'fulfilled' && isoDay(o.created_at) === key)
          .reduce((s, o) => s + o.total_minor, 0),
      });
    }
    const max = Math.max(1, ...days.map((x) => x.value)),
      pts = days.map((d, i) => `${15 + i * 76},${116 - (d.value / max) * 90}`);
    return `<div class="chart-content"><div class="chart-legend"><span><i class="legend-dot"></i>مبيعات مسلّمة</span><span>آخر 7 أيام • السجلات المعروضة</span></div><div class="chart-value">${amount(days.reduce((s, d) => s + d.value, 0))}</div><svg viewBox="0 0 486 140" class="chart-svg" aria-label="اتجاه المبيعات لآخر سبعة أيام" role="img"><defs><linearGradient id="chart-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#9b89db" stop-opacity=".20"/><stop offset="1" stop-color="#9b89db" stop-opacity="0"/></linearGradient></defs>${[25, 55, 85, 115].map((y) => `<path d="M15 ${y}H471" stroke="#eeedf3" stroke-dasharray="3 5"/>`).join('')}<path d="M${pts.join(' L')} L471 135 L15 135Z" fill="url(#chart-fill)"/><path d="M${pts.join(' L')}" stroke="#8e7ad6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>${pts.map((p, i) => `<circle cx="${p.split(',')[0]}" cy="${p.split(',')[1]}" r="3.5" fill="#fff" stroke="#8e7ad6" stroke-width="2"><title>${e(days[i].key)}: ${days[i].value / 100} ر.س</title></circle>`).join('')}</svg><div class="chart-labels" dir="ltr">${days.map((d) => `<span>${e(d.day.toLocaleDateString('ar-SA', { weekday: 'short' }))}</span>`).join('')}</div></div>`;
  }
  function showCustomer(id) {
    location.href = '/customers/' + Number(id);
  }

  function dashboard() {
    if (role === 'accountant') return receivablesPage();
    return repDashboard();
  }

  function repDashboard() {
    return window.AmrbitJourney.repDashboard(adminContext());
  }
  function tracking() {
    const t =
      data.trips.find((t) => t.id === Number(selectedTrip)) ||
      data.trips.find((t) => t.status === 'active') ||
      data.trips[0];
    selectedTrip = t?.id;
    const s = t ? freshness(t) : [],
      vs = t ? data.visits.filter((v) => v.trip_id === t.id).sort((a, b) => a.sequence - b.sequence) : [],
      points = t ? data.points.filter((p) => p.trip_id === t.id) : [];
    return `<div class="filters"><div class="filters-left"><select id="trip-filter" class="filter-select" aria-label="اختيار رحلة">${data.trips.map((x) => `<option value="${x.id}" ${x.id === t?.id ? 'selected' : ''}>${e(x.representative.name)} • ${e(x.code)}</option>`).join('')}</select>${t ? pill(t.status) : ''}</div><span class="results-count">تحديث تلقائي كل 15 ثانية • عند اتصال الصفحة</span></div><section class="content-grid"><div class="panel"><div id="ops-map" class="map-shell large"></div><div class="playback"><div class="playback-head"><span>استعراض نقاط الرحلة المسجّلة</span><span id="playback-time">${points.length} نقطة • ${points.length ? when(points.at(-1).captured_at) : 'لا توجد نقاط'}</span></div><input id="playback-range" type="range" min="0" max="${Math.max(0, points.length - 1)}" value="${Math.max(0, points.length - 1)}" ${!points.length ? 'disabled' : ''} aria-label="وقت نقطة المسار"><small>الخط يصل النقاط المسجّلة، ولا يمثل حساب طريق أو توقع وقت وصول.</small></div></div><div class="panel">${heading('تفاصيل الرحلة', t?.code || 'لا توجد رحلة')}<div class="rep-detail"><div class="card-header"><span class="rep-initial">${e(initial(t?.representative.name))}</span><div><strong>${e(t?.representative.name)}</strong><small>${e(t?.vehicle.name)}</small></div></div>${t ? `<div class="detail-row"><span>آخر موقع</span><b>${e(s[2])}</b></div><div class="detail-row"><span>جودة القراءة</span><span class="pill ${s[0]}">${e(s[1])}</span></div><div class="detail-row"><span>دقة آخر نقطة</span><b>${lastPoint(t.id)?.accuracy_m ?? '—'} متر</b></div>` : ''}</div><div class="trip-stops"><h3>محطات الرحلة</h3>${vs.map((v) => `<div class="stop ${v.status === 'completed' ? 'stop-done' : ''}"><span class="stop-number">${v.sequence}</span><div><strong>${e(v.customer.name.replace(' (تجريبي)', ''))}</strong><small>${e(v.customer.area?.name || 'محطة عميل')}</small></div>${pill(v.status)}</div>`).join('') || empty()}</div></div></section>`;
  }
  function tripsPage() {
    const rows = filter(data.trips),
      planned = data.trips.filter((t) => t.status === 'planned'),
      overdue = planned.filter((t) => String(t.scheduled_for).slice(0, 10) < data.today),
      active = data.trips.filter((t) => t.status === 'active');
    return (
      `<section class="trip-overview"><div><span class="eyebrow">من التخطيط إلى التنفيذ</span><h2>خطة واضحة. وكل تغيير له سبب.</h2><p>افتح ملف الرحلة للمحطات والطلبات وعهدة السيارة وسجل القرارات. عدّل الخطة قبل البدء، وتابع التنفيذ من مكان واحد.</p></div><div class="trip-overview-mark">${icon('route')}</div></section>` +
      `<section class="decision-grid">${[
        ['خطط تنتظر التنفيذ', planned.length, 'يمكن تعديلها قبل البدء'],
        ['خطط تجاوزت موعدها', overdue.length, 'راجع الموعد أو ألغِ الخطة مع السبب'],
        ['رحلات قيد التنفيذ', active.length, 'استكمل الزيارات ثم أغلق الرحلة'],
        ['خطط ملغاة', data.trips.filter((t) => t.status === 'cancelled').length, 'السجل محفوظ للمراجعة'],
      ]
        .map(
          ([label, count, note]) =>
            `<div class="decision-card"><span>${e(label)}</span><strong class="decision-count">${num(count)}</strong><small>${e(note)}</small></div>`,
        )
        .join('')}</section>` +
      filters(['planned', 'active', 'closed', 'cancelled'], true) +
      table(
        ['الرحلة', 'المندوب', 'السيارة', 'موعدها', 'المحطات', 'الحالة', 'الإجراءات'],
        rows.map((t) => {
          const done = t.completed_stops_count,
            total = t.stops_count,
            old = t.status === 'planned' && String(t.scheduled_for).slice(0, 10) < data.today;
          return tr([
            `<button class="record-link" data-trip-file="${t.id}">${e(t.code)} ←</button><small class="trip-cell-note">نسخة الخطة ${num(t.plan_version)}</small>`,
            `<strong>${e(t.representative.name)}</strong>`,
            `<strong>${e(t.vehicle.name)}</strong><small class="trip-cell-note">${e(t.vehicle.plate)}</small>`,
            `${day(t.scheduled_for)}${old ? '<small class="trip-cell-note trip-warning">تحتاج إعادة جدولة</small>' : ''}`,
            `<strong><bdi dir="ltr">${done} / ${total}</bdi></strong><div class="trip-progress"><i style="width:${total ? Math.round((done / total) * 100) : 0}%"></i></div>`,
            pill(t.status),
            `<div class="row-actions"><button class="quiet-btn" data-trip-file="${t.id}">ملف الرحلة</button>${manager && t.status === 'planned' ? `<button class="quiet-btn" data-trip-edit="${t.id}">تعديل الخطة</button>` : ''}${(manager || rep) && t.status === 'planned' && String(t.scheduled_for).slice(0, 10) === data.today ? btn('trip.start', t.id, 'بدء') : (manager || rep) && t.status === 'active' ? btn('trip.close', t.id, 'إغلاق') : ''}</div>`,
          ]);
        }),
        `تظهر ${rows.length} من آخر ${data.trips.length} رحلة محمّلة (الحد 300). المؤشرات لهذا السجل؛ ملف كل رحلة يجلب محطاتها كاملة.`,
      )
    );
  }
  async function readTripFile(id) {
    const response = await fetch('/ops/trips/' + Number(id), {
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) throw new Error('تعذر فتح ملف الرحلة. تحقق من الاتصال والصلاحيات وحدّث الصفحة.');
    return response.json();
  }
  async function showTripFile(id, edit = false) {
    if (busy) return;
    busy = true;
    try {
      const file = await readTripFile(id),
        t = file.trip;
      window.FieldFlowTripFile = file;
      if (edit) {
        if (!file.can_plan || file.blockers.length)
          throw new Error(file.blockers.join(' ') || 'لا تملك صلاحية تعديل الخطة.');
        $('#record-dialog').close();
        openForm('trip.update', t.id);
        return;
      }
      const stops = file.visits.filter((v) => v.status !== 'cancelled'),
        removed = file.visits.filter((v) => v.status === 'cancelled'),
        done = stops.filter((v) => v.status === 'completed').length,
        eventNames = {
          'trip.planned': 'إنشاء الخطة',
          'trip.replanned': 'تعديل الخطة',
          'trip.cancelled': 'إلغاء الخطة',
          'trip.start': 'بدء الرحلة',
          'trip.close': 'إغلاق الرحلة',
        },
        stopRow = (v) =>
          `<li class="trip-stop ${v.status === 'cancelled' ? 'trip-stop-cancelled' : ''}"><span class="trip-stop-number">${v.status === 'cancelled' ? '—' : num(v.sequence)}</span><div><strong>${e(v.customer?.name || 'عميل غير متاح')}</strong><small>${e(v.customer?.code)} • ${e(v.customer?.area?.name || 'منطقة غير مسجلة')}</small><small>${e(v.customer?.address)}</small>${v.outcome_note ? `<p>${e(v.outcome_note)}</p>` : ''}${v.checked_in_at ? `<small>الحضور: ${when(v.checked_in_at)} • ${v.location_verified ? 'موقع موثق' : 'دون إثبات موقع'}</small>` : ''}${v.finished_at ? `<small>نهاية الزيارة: ${when(v.finished_at)}</small>` : ''}</div>${pill(v.status)}</li>`,
        actorName = (id) => data.team.find((m) => m.id === Number(id))?.name || 'عضو #' + id,
        vehicleName = (id) => data.vehicles.find((v) => v.id === Number(id))?.name || 'مركبة #' + id,
        planText = (p) =>
          p
            ? `${e(p.scheduled_for)} • ${e(actorName(p.representative_id))} • ${e(vehicleName(p.vehicle_id))}<br>المحطات: ${
                p.stops
                  .filter((s) => s.status !== 'cancelled')
                  .map((s) =>
                    e(
                      file.visits.find((v) => v.customer_id === s.customer_id)?.customer?.name ||
                        'عميل #' + s.customer_id,
                    ),
                  )
                  .join(' ← ') || 'لا توجد محطات'
              }`
            : '';
      $('#record-title').textContent = 'ملف الرحلة • ' + t.code;
      $('#record-content').innerHTML = `<div class="trip-file">
        <div class="trip-file-header"><div>${pill(t.status)}<span class="trip-file-version">نسخة الخطة ${num(t.plan_version)} • ${day(t.scheduled_for)}</span></div><div class="row-actions">${file.can_plan && !file.blockers.length ? `<button class="primary-btn" data-trip-edit="${t.id}">تعديل الخطة</button>${btn('trip.cancel', t.id, 'إلغاء الخطة')}` : ''}${!rep && t.status !== 'cancelled' ? `<a class="secondary-btn" href="/tracking?trip=${t.id}">عرض التتبع ←</a>` : ''}</div></div>
        ${t.cancellation_reason ? `<div class="info-box"><strong>سبب الإلغاء</strong><p>${e(t.cancellation_reason)}</p><small>${when(t.cancelled_at)}</small></div>` : ''}
        <div class="trip-file-summary"><div><small>المندوب المسؤول</small><strong>${e(t.representative?.name)}</strong></div><div><small>السيارة واللوحة</small><strong>${e(t.vehicle?.name)}</strong><span>${e(t.vehicle?.plate)}</span></div><div><small>السائق المحفوظ للرحلة</small><strong>${e(t.driver?.name || 'لم يُحدد')}</strong></div><div><small>إنجاز المحطات</small><strong><bdi dir="ltr">${num(done)} / ${num(stops.length)}</bdi></strong></div></div>
        ${t.planning_notes ? `<div class="trip-plan-note"><strong>تعليمات الرحلة</strong><p>${e(t.planning_notes)}</p></div>` : ''}
        <div class="trip-file-columns"><section class="trip-file-section"><h3>المحطات وترتيب التنفيذ</h3>${stops.length ? `<ol class="trip-stops">${stops.map(stopRow).join('')}</ol>` : '<p class="admin-note">لا توجد محطات ضمن خطة التنفيذ الحالية.</p>'}${removed.length ? `<details><summary>محطات ملغاة محفوظة (${num(removed.length)})</summary><ol class="trip-stops">${removed.map(stopRow).join('')}</ol></details>` : ''}</section>
        <section class="trip-file-section"><h3>حالة التشغيل والعهدة</h3><div class="detail-row"><span>بدء التنفيذ</span><b>${when(t.started_at)}</b></div><div class="detail-row"><span>إغلاق الرحلة</span><b>${when(t.closed_at)}</b></div><div class="detail-row"><span>آخر نقطة موقع</span><b>${file.last_point ? when(file.last_point.captured_at) : 'لا توجد نقاط'}</b></div>${file.last_point ? `<p class="admin-note">المصدر: ${e(file.last_point.source)} • الدقة: ${num(file.last_point.accuracy_m)} متر. وجود نقطة لا يعني اتصالًا مباشرًا الآن.</p>` : ''}<h4>${e(t.warehouse?.name || 'عهدة السيارة')}</h4>${file.stock.length ? file.stock.map((b) => `<div class="detail-row"><span>${e(b.product?.name)}</span><b>${num(b.quantity)} ${e(b.product?.unit)}</b></div>`).join('') : '<p class="admin-note">لا توجد أرصدة مسجلة لهذه العهدة.</p>'}<p class="admin-note">${e(file.stock_definition)}</p>${file.blockers.length ? `<div class="trip-blockers"><strong>حالة تعديل الخطة</strong><ul>${file.blockers.map((r) => `<li>${e(r)}</li>`).join('')}</ul></div>` : '<p class="admin-note">الخطة قابلة للتعديل قبل البدء. لا يُنقل المخزون عند تغيير السيارة.</p>'}</section></div>
        ${
          file.financial
            ? `<section class="trip-file-section"><h3>الطلبات والمبيعات المرتبطة</h3><p class="admin-note">مبيعات مسلّمة ${amount(file.financial.sales_minor)} • مدفوع حالي ${amount(file.financial.paid_minor)} • متبقٍ حالي ${amount(file.financial.sales_minor - file.financial.paid_minor)} • مسودات ${num(file.financial.drafts)}. المدفوع قد يتضمن سدادًا بعد انتهاء الرحلة.</p>${
                file.orders.length
                  ? table(
                      ['الطلب', 'العميل', 'الإجمالي', 'المدفوع الحالي', 'الحالة'],
                      file.orders.map((o) =>
                        tr([
                          `<a href="/documents/orders/${o.id}" class="record-link" target="_blank" rel="noopener">${e(o.code)} ↗</a>`,
                          e(o.customer?.name),
                          amount(o.total_minor),
                          amount(o.paid_minor),
                          pill(o.status),
                        ]),
                      ),
                    )
                  : '<p class="admin-note">لا توجد طلبات مرتبطة بهذه الرحلة.</p>'
              }</section>`
            : ''
        }
        <section class="trip-file-section"><h3>سجل قرارات الرحلة</h3><ol class="trip-events">${file.events.map((ev) => `<li><div><strong>${e(eventNames[ev.action] || ev.action)}</strong><small>${e(ev.actor)} • ${when(ev.at)}</small></div>${ev.reason ? `<p>${e(ev.reason)}</p>` : ''}${ev.before && ev.after ? `<details><summary>ما الذي تغير؟</summary><div class="trip-change"><div><small>قبل</small><p>${planText(ev.before)}</p></div><div><small>بعد</small><p>${planText(ev.after)}</p></div></div></details>` : ''}</li>`).join('') || '<li>لا توجد أحداث مسجلة للرحلة.</li>'}</ol></section>
      </div>`;
      $('#record-dialog').showModal();
    } catch (err) {
      toast(err.message, true);
    } finally {
      busy = false;
    }
  }
  function visitButtons(v) {
    if (!rep)
      return v.location_verified
        ? `<span class="pill">موقع موثّق</span>`
        : '<span class="pill neutral">لم يُثبت بعد</span>';
    const trip = data.trips.find((t) => t.id === v.trip_id);
    if (!trip || trip.status !== 'active') return '<small>ابدأ رحلة يوم الزيارة أولًا.</small>';
    if (trip.weekly_plan_id) {
      const next = data.visits
        .filter((x) => x.trip_id === trip.id && ['planned', 'in_progress'].includes(x.status))
        .sort((a, b) => a.sequence - b.sequence || a.id - b.id)[0];
      if (next?.id !== v.id) return '<small>بانتظار المحطة السابقة.</small>';
    }
    if (v.status === 'planned')
      return (
        btn('visit.checkin', v.id, 'إثبات الحضور', 'primary-btn') + btn('visit.skip', v.id, 'تعذر الزيارة')
      );
    if (v.status === 'in_progress')
      return btn('visit.complete', v.id, 'إكمال', 'primary-btn') + btn('visit.fail', v.id, 'لم تُنفذ');
    return '';
  }
  function visitsPage() {
    return (
      filters(['planned', 'in_progress', 'completed', 'failed', 'skipped'], true) +
      table(
        ['العميل', 'المندوب', 'تاريخ الزيارة', 'الحالة', 'دليل الموقع', 'النتيجة / الإجراء'],
        filter(data.visits).map((v) =>
          tr([
            `<strong>${e(v.customer.name)}</strong><small>${e(v.customer.code)} • محطة ${v.sequence}</small>`,
            e(v.representative.name),
            day(v.scheduled_for),
            pill(v.status),
            v.location_verified
              ? `<span class="pill">موثّق</span><small>${num(v.distance_m)} م • دقة ${num(v.accuracy_m)} م</small>`
              : '<span class="pill neutral">غير مسجل</span>',
            `<div class="row-actions">${rep ? visitButtons(v) : e(v.outcome_note || '—')}</div>`,
          ]),
        ),
        'الزيارة تتطلب رحلة نشطة، موقعًا حديثًا ودقيقًا، وتطابقًا مع نطاق العميل.',
      )
    );
  }
  function customersPage() {
    return window.AmrbitJourney.customers(adminContext());
  }
  function inventoryPage() {
    return (
      `<div class="filters"><label class="results-count" for="inventory-warehouse">المستودع أو العهدة</label><select id="inventory-warehouse" class="filter-select"><option value="">كل المواقع</option>${data.warehouses.map((w) => `<option value="${w.id}" ${Number(inventoryWarehouse) === w.id ? 'selected' : ''}>${e(w.name)}</option>`).join('')}</select></div>` +
      (manager || role === 'warehouse_keeper'
        ? '<div class="filters"><span class="results-count">مخزون عام متعدد المنتجات والوحدات</span><div class="filters-right">' +
          btn('stock.receive', '', 'استلام مخزون', 'secondary-btn') +
          (manager
            ? btn('product.create', '', 'صنف جديد', 'secondary-btn') +
              btn('warehouse.create', '', 'مستودع جديد', 'secondary-btn')
            : '') +
          '</div></div>'
        : '') +
      `<div class="section-tabs"><button data-tab="balances" class="${tab === 'balances' ? 'selected' : ''}">الأرصدة الحالية</button><button data-tab="movements" class="${tab === 'movements' ? 'selected' : ''}">سجل الحركات</button><button data-tab="products" class="${tab === 'products' ? 'selected' : ''}">دليل المنتجات</button></div>` +
      (tab === 'products'
        ? table(
            ['المنتج', 'رمز الصنف', 'الوحدة', 'سعر البيع', 'حد إعادة الطلب'],
            data.products
              .filter(match)
              .map((p) =>
                tr([
                  `<strong>${e(p.name)}</strong>`,
                  e(p.sku),
                  e(p.unit),
                  `<span class="money">${amount(p.price_minor)}</span>`,
                  num(p.reorder_level),
                ]),
              ),
          )
        : tab === 'movements'
          ? table(
              ['المكان', 'الصنف', 'التغيير', 'سبب الحركة', 'المرجع', 'وقت الحركة'],
              data.movements
                .filter(
                  (m) => match(m) && (!inventoryWarehouse || m.warehouse_id === Number(inventoryWarehouse)),
                )
                .map((m) =>
                  tr([
                    e(m.warehouse.name),
                    e(m.product.name),
                    `<strong class="${m.quantity < 0 ? 'negative' : 'positive'}">${m.quantity > 0 ? '+' : ''}${num(m.quantity)}</strong>`,
                    e(
                      {
                        sale: 'تسليم طلب',
                        transfer_out: 'تحويل صادر',
                        transfer_in: 'تحويل وارد',
                        demo_opening: 'رصيد افتتاحي تجريبي',
                        receipt: 'استلام للمستودع',
                      }[m.reason] || m.reason,
                    ),
                    `<span class="mono">${e(m.reference)}</span>`,
                    when(m.created_at),
                  ]),
                ),
              'آخر 100 حركة • الرصيد الحالي محفوظ مستقلًا عن حد العرض.',
            )
          : table(
              [
                'المخزن / العهدة',
                'الصنف',
                'الرصيد الفعلي',
                'محجوز للموافقة',
                'المتاح للصرف',
                'الوحدة',
                'إعادة الطلب',
                'الحالة',
              ],
              data.balances
                .filter(
                  (b) => match(b) && (!inventoryWarehouse || b.warehouse_id === Number(inventoryWarehouse)),
                )
                .map((b) =>
                  tr([
                    `<strong>${e(b.warehouse.name)}</strong><small>${b.warehouse.type === 'vehicle' ? 'عهدة سيارة' : 'مستودع'}</small>`,
                    e(b.product.name),
                    `<strong>${num(b.quantity)}</strong>`,
                    `<span>${num(b.reserved_quantity || 0)}</span>`,
                    `<strong>${num(b.available_quantity ?? b.quantity)}</strong>`,
                    e(b.product.unit),
                    num(b.product.reorder_level),
                    `<span class="pill ${(b.available_quantity ?? b.quantity) < b.product.reorder_level ? 'warning' : ''}">${(b.available_quantity ?? b.quantity) < b.product.reorder_level ? 'يحتاج دعم' : 'رصيد مناسب'}</span>`,
                  ]),
                ),
            ))
    );
  }
  function ordersPage() {
    const draftCount = data.orders.filter((o) => o.status === 'draft').length;
    return (
      `<div class="finance-intro"><div><div class="eyebrow">ORDER WORKSPACE</div><h2>من طلب العميل إلى تسليمه.</h2><p>${draftCount} مسودة ضمن السجل المحمّل • السعر والمخزون والسداد في دورة واحدة.</p></div><div class="order-flow"><span>01 اختيار الأصناف</span><i>←</i><span>02 التسليم</span><i>←</i><span>03 التحصيل والمتابعة</span></div></div>` +
      filters(['draft', 'fulfilled'], true) +
      table(
        ['الطلب والعميل', 'المندوب', 'الإجمالي', 'المدفوع', 'المتبقي / الآجل', 'الدفع', 'الحالة / الإجراء'],
        filter(data.orders).map((o) =>
          tr([
            `<strong>${e(o.customer.name)}</strong><small class="mono">${e(o.code)}</small>`,
            e(o.representative.name),
            `<span class="money">${amount(o.total_minor)}</span>`,
            amount(o.paid_minor),
            o.status === 'fulfilled'
              ? `<span class="money">${amount(o.total_minor - o.paid_minor)}</span><small>استحقاق ${day(o.due_date)}</small>`
              : '—',
            e(
              {
                cash: 'استلام كامل',
                partial: 'استلام جزئي',
                credit: 'آجل',
              }[o.payment_terms],
            ),
            `<div class="row-actions">${pill(o.status)}<a class="quiet-btn" href="/documents/orders/${o.id}" target="_blank" rel="noopener">كشف الطلب</a>${o.status === 'draft' && (rep || role === 'manager') ? btn('order.fulfill', o.id, 'تسليم') : ''}</div>`,
          ]),
        ),
        'الإجمالي محسوب من أسعار الأصناف. تسليم الطلب يخصم المخزون ويثبت المبلغ المستلم مرة واحدة.',
      )
    );
  }
  function collectionsPage() {
    return (
      `<section class="metrics">${metric('التحصيل المسجل', num(data.metrics.paid_minor / 100), 'ر.س', 'كامل سجل الطلبات المسلّمة', 'wallet', 'green')}${['cash', 'bank', 'card'].map((method) => metric({ cash: 'نقدي', bank: 'تحويل بنكي', card: 'بطاقة' }[method], num(data.collections.filter((c) => c.method === method).reduce((s, c) => s + c.amount_minor, 0) / 100), 'ر.س', 'الإيصالات المعروضة', 'wallet')).join('')}</section>` +
      table(
        ['الإيصال', 'العميل', 'الطلب', 'المبلغ المستلم', 'طريقة الدفع', 'وقت الاستلام'],
        data.collections.filter(match).map((c) =>
          tr([
            `<span class="mono">${e(c.receipt)}</span>`,
            `<strong>${e(c.order?.customer?.name || '—')}</strong>`,
            e(data.orders.find((o) => o.id === c.order_id)?.code || c.order_id),
            `<span class="money">${amount(c.amount_minor)}</span>`,
            e(
              {
                cash: 'نقدي',
                bank: 'تحويل بنكي',
                card: 'بطاقة',
              }[c.method],
            ),
            when(c.collected_at),
          ]),
        ),
        'كل إيصال يخفض المتبقي على طلب محدد؛ لا يُقبل مبلغ يتجاوز المستحق.',
      )
    );
  }
  function debtSummary() {
    return `<div class="split"><div class="panel">${heading('الآجل بحسب المندوب', 'منسوب إلى المندوب الذي أنشأ الطلب')}${bars(
      data.debt_representatives.map((r) => [shortName(r.name), Number(r.balance_minor) / 100]),
      true,
    )}</div><div class="panel">${heading('أرصدة العملاء', 'المبيعات المسلّمة ناقص السداد')}${bars(
      [...data.debt_customers]
        .sort((a, b) => b.balance_minor - a.balance_minor)
        .slice(0, 6)
        .map((c) => [c.name.replace(' (تجريبي)', ''), Number(c.balance_minor) / 100]),
      true,
    )}</div></div>`;
  }
  function receivablesInvoices() {
    const debt = data.orders.filter((o) => o.status === 'fulfilled' && o.total_minor > o.paid_minor);
    return (
      `<section class="metrics">${metric(rep ? 'آجل عملائك' : 'إجمالي الآجل بالسوق', num(data.metrics.unpaid_minor / 100), 'ر.س', 'الرصيد الحالي من كامل قاعدة البيانات', 'wallet', 'orange')}${metric('آجل تجاوز الاستحقاق', num(data.metrics.overdue_minor / 100), 'ر.س', 'موعده قبل اليوم ولم يُسدّد', 'clock', 'orange')}${metric('عملاء عليهم آجل', num(data.debt_customers.length), 'عميل', 'أرصدة غير مسددة', 'users')}${metric('مندوبون لديهم آجل', num(data.debt_representatives.length), 'مندوب', 'حسب مصدر الطلب', 'route')}</section>${debtSummary()}<div class="info-box"><h3>تذكير المندوب</h3>راجع العميل قبل زيارته، وافتح «استلام سداد» عند التحصيل. القائمة تعرض المديونيات والاستحقاقات؛ لا ترسل رسائل تلقائية إلى العملاء.</div><div class="filters"><strong>الفواتير المفتوحة</strong><span class="results-count">ابحث باسم العميل أو المندوب</span></div>` +
      table(
        ['العميل / الفاتورة', 'المندوب', 'الإجمالي', 'المستلم', 'الآجل المتبقي', 'الاستحقاق', 'المتابعة'],
        debt
          .filter(match)
          .map((o) =>
            tr([
              `<strong>${e(o.customer.name)}</strong><small class="mono">${e(o.code)}</small>`,
              e(o.representative.name),
              amount(o.total_minor),
              amount(o.paid_minor),
              `<strong class="money">${amount(o.total_minor - o.paid_minor)}</strong>`,
              o.due_date
                ? `<span class="pill ${o.due_date < data.today ? 'warning' : ''}">${day(o.due_date)}</span>`
                : '<span class="pill neutral">يلزم تحديد موعد</span>',
              (canCollect
                ? btn('collection.create', o.id, 'استلام سداد', 'primary-btn')
                : '<span class="pill neutral">للمراجعة</span>') + btn('debt.followup', o.id, 'تسجيل متابعة'),
            ]),
          ),
        'كل دين مرتبط بفاتورة وعميل ومندوب. السداد الجزئي يبقي الباقي مفتوحًا، والكامل يغلق رصيد الفاتورة.',
      )
    );
  }
  function bars(rows, asMoney = false) {
    const max = Math.max(1, ...rows.map((r) => r[1]));
    return `<div class="report-bars">${rows.map(([name, n]) => `<div class="bar-row"><span>${e(name)}</span><div class="bar-track"><i style="width:${(n / max) * 100}%"></i></div><b>${asMoney ? num(n) : n}</b></div>`).join('') || empty()}</div>`;
  }

  let reportState = { from: '', to: '', representative: '', area: '' },
    reportStats = null,
    reportBusy = false,
    reportError = '';
  function reportQuery() {
    const params = new URLSearchParams({
      from: reportState.from,
      to: reportState.to,
    });
    if (reportState.representative) params.set('representative_id', reportState.representative);
    if (reportState.area) params.set('area_id', reportState.area);
    return params.toString();
  }
  async function fetchReport() {
    if (reportBusy) return;
    reportBusy = true;
    reportError = '';
    if (section === 'reports') render();
    try {
      const r = await fetch('/ops/analytics?' + reportQuery(), {
        headers: { Accept: 'application/json' },
      });
      const body = await r.json();
      if (!r.ok)
        throw new Error(
          body.errors ? Object.values(body.errors).flat().join(' ') : body.message || 'تعذر تحديث التقرير.',
        );
      reportStats = body;
    } catch (err) {
      reportError = err.message;
    } finally {
      reportBusy = false;
      if (section === 'reports') render();
    }
  }
  function reportChart() {
    const days = reportStats.daily,
      financial = canMoney,
      values = days.map((d) => (financial ? d.sales_minor : d.completed)),
      maximum = Math.max(1, ...values),
      spacing = 456 / Math.max(1, days.length - 1),
      points = values.map((v, i) => [15 + i * spacing, 125 - (v / maximum) * 100]);
    const path = points.map((p) => p.join(',')).join(' L'),
      step = Math.max(1, Math.ceil(days.length / 7));
    return (
      '<div class="chart-content"><div class="chart-value">' +
      (financial ? amount(reportStats.summary.sales_minor) : num(reportStats.summary.completed) + ' زيارة') +
      '</div><svg class="chart-svg report-chart" viewBox="0 0 486 150" role="img" aria-label="مؤشر كل يوم خلال الفترة المختارة">' +
      [25, 58, 91, 125]
        .map((y) => '<path d="M15 ' + y + 'H471" stroke="#eceaf4" stroke-dasharray="4 6"/>')
        .join('') +
      '<path d="M' +
      path +
      '" fill="none" stroke="#8a75d3" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>' +
      points
        .map(
          (p, i) =>
            '<circle cx="' +
            p[0] +
            '" cy="' +
            p[1] +
            '" r="3" fill="#fff" stroke="#8a75d3"><title>' +
            e(days[i].date) +
            ': ' +
            num(financial ? values[i] / 100 : values[i]) +
            '</title></circle>',
        )
        .join('') +
      '</svg><div class="chart-labels" dir="ltr">' +
      days
        .filter((d, i) => i % step === 0 || i === days.length - 1)
        .map((d) => '<span>' + e(d.date.slice(5)) + '</span>')
        .join('') +
      '</div></div>'
    );
  }
  function reportsPage() {
    if (!reportState.from) {
      const from = new Date();
      from.setDate(from.getDate() - 6);
      reportState.from = isoDay(from);
      reportState.to = data.today;
    }
    if (!reportStats && !reportBusy && !reportError) queueMicrotask(fetchReport);
    const filters =
      '<div class="filters report-filters"><div class="filters-left"><label>من <input id="report-from" class="filter-date" type="date" value="' +
      reportState.from +
      '"></label><label>إلى <input id="report-to" class="filter-date" type="date" value="' +
      reportState.to +
      '"></label><select id="report-rep" class="filter-select" aria-label="مندوب التقرير"><option value="">كل المناديب</option>' +
      (data.team.length ? data.team.filter((r) => r.role === 'representative') : data.representatives)
        .map(
          (r) =>
            '<option value="' +
            r.id +
            '" ' +
            (String(r.id) === reportState.representative ? 'selected' : '') +
            '>' +
            e(r.name) +
            '</option>',
        )
        .join('') +
      '</select><select id="report-area" class="filter-select" aria-label="منطقة التقرير"><option value="">كل المناطق</option>' +
      data.areas
        .map(
          (a) =>
            '<option value="' +
            a.id +
            '" ' +
            (String(a.id) === reportState.area ? 'selected' : '') +
            '>' +
            e(a.name) +
            '</option>',
        )
        .join('') +
      '</select><button class="primary-btn" data-report-apply ' +
      (reportBusy ? 'disabled' : '') +
      '>تحديث التقرير</button></div><div class="filters-right">' +
      (role !== 'accountant'
        ? '<button class="secondary-btn" data-export="visits">CSV زيارات</button>'
        : '') +
      (canMoney ? '<button class="secondary-btn" data-export="sales">CSV مبيعات</button>' : '') +
      '</div></div>';
    if (reportBusy)
      return filters + '<div class="loading"><span class="loading-ring"></span>نحسب الفترة المختارة…</div>';
    if (reportError) return filters + '<div class="disconnected" role="alert">' + e(reportError) + '</div>';
    if (!reportStats) return filters;
    const m = reportStats.summary,
      statuses = reportStats.visit_statuses;
    const metrics = canMoney
      ? metric(
          'مبيعات الفترة',
          num(m.sales_minor / 100),
          'ر.س',
          'الطلبات المسلّمة المنشأة خلال الفترة',
          'wallet',
          'green',
        ) +
        metric(
          'تحصيل خلال الفترة',
          num(m.collections_minor / 100),
          'ر.س',
          'يشمل سداد فواتير سابقة',
          'wallet',
          'green',
        ) +
        metric(
          'آجل طلبات الفترة',
          num(m.remaining_period_orders_minor / 100),
          'ر.س',
          'المتبقي الحالي، وليس نقد العهدة',
          'clock',
          'orange',
        ) +
        metric(
          role === 'accountant' ? 'طلبات مسلّمة' : 'زيارات مكتملة',
          num(role === 'accountant' ? m.orders : m.completed),
          role === 'accountant' ? 'طلب' : 'زيارة',
          role === 'accountant' ? 'ضمن الفترة والفلاتر' : m.visits + ' زيارة مجدولة',
          'check',
        )
      : metric('زيارات الفترة', num(m.visits), 'زيارة', 'كل الحالات', 'pin') +
        metric('مكتملة', num(m.completed), 'زيارة', 'الزيارات المنفذة', 'check', 'green') +
        metric(
          'لم تنفذ',
          num((statuses.failed || 0) + (statuses.skipped || 0)),
          'زيارة',
          'التعذر والتجاوز',
          'clock',
          'orange',
        ) +
        metric('مخططة', num(statuses.planned || 0), 'زيارة', 'بانتظار التنفيذ', 'route');
    const agingNames = {
      not_due: 'لم يحِن استحقاقها',
      '1_30': 'متأخر 1–30 يوم',
      '31_60': 'متأخر 31–60 يوم',
      '61_90': 'متأخر 61–90 يوم',
      over_90: 'أكثر من 90 يوم',
      unspecified: 'بلا موعد محدد',
    };
    const aging = Object.entries(agingNames).map(([key, name]) => [
      name,
      (reportStats.aging.find((r) => r.bucket === key)?.balance_minor || 0) / 100,
    ]);
    return (
      filters +
      '<section class="metrics">' +
      metrics +
      '</section><section class="split"><div class="panel">' +
      heading(
        canMoney ? 'مبيعات كل يوم' : 'زيارات مكتملة كل يوم',
        'من كامل قاعدة البيانات • الفلاتر الحالية',
      ) +
      reportChart() +
      '</div><div class="panel">' +
      heading(
        canMoney ? 'أعمار الآجل المفتوح' : 'نتائج الزيارات',
        canMoney ? 'الرصيد الآن، بكل تاريخه • نفس المندوب والمنطقة' : 'خلال الفترة المختارة',
      ) +
      bars(
        canMoney
          ? aging
          : ['completed', 'planned', 'in_progress', 'failed', 'skipped'].map((status) => [
              statusNames[status],
              statuses[status] || 0,
            ]),
        canMoney,
      ) +
      '</div></section>' +
      window.FieldFlowAdmin.reports(reportStats, adminContext()) +
      '<section class="report-definitions">' +
      Object.values(reportStats.definitions)
        .map((text) => '<div>' + icon('check') + '<span>' + e(text) + '</span></div>')
        .join('') +
      '</section><div class="summary-note">آخر حساب ' +
      when(reportStats.as_of) +
      ' • الفترة ' +
      e(reportState.from) +
      ' إلى ' +
      e(reportState.to) +
      '. الرسم وملف التصدير يطبقان الفترة والمندوب والمنطقة نفسها.</div>'
    );
  }
  document.addEventListener('click', (ev) => {
    if (ev.target.closest('[data-report-apply]')) {
      reportState = {
        from: $('#report-from').value,
        to: $('#report-to').value,
        representative: $('#report-rep').value,
        area: $('#report-area').value,
      };
      fetchReport();
    }
  });
  function auditPage() {
    return table(
      ['العملية', 'المنفذ', 'نوع السجل', 'معرف السجل', 'وقت العملية'],
      data.audit
        .filter(match)
        .map((a) =>
          tr([
            `<span class="mono">${e(a.action)}</span>`,
            e(data.team.find((t) => t.id === a.actor_id)?.name || 'مستخدم #' + a.actor_id),
            e(a.subject_type),
            num(a.subject_id),
            when(a.created_at),
          ]),
        ),
      'آخر 100 عملية • لا توجد واجهة لحذف سجل المراجعة.',
    );
  }
  function settingsPage() {
    return `<div class="split"><div class="panel">${heading('مساحة الشركة', 'هذه القيم للعرض في النسخة الحالية')}<div class="detail-list">${[
      ['الشركة', data.company.name],
      [
        'القطاع',
        data.company.industry === 'water'
          ? 'توزيع مياه'
          : data.company.industry === 'food'
            ? 'مواد غذائية'
            : 'توزيع عام',
      ],
      ['العملة', data.company.currency],
      ['المنطقة الزمنية', data.company.timezone],
      ['صلاحيتك', namesRole[role]],
    ]
      .map(([k, v]) => `<div class="detail-row"><span>${e(k)}</span><b>${e(v)}</b></div>`)
      .join(
        '',
      )}</div></div><div class="panel">${heading('خصوصية الموقع وإثبات الحضور')}<div class="detail-list">${[
      ['التتبع', 'أثناء رحلة نشطة فقط'],
      ['حداثة النقطة المقبولة', 'حتى دقيقتين'],
      ['دقة نقطة الحضور', 'حتى 100 متر'],
      ['نطاق العميل', 'محدد في سجل كل عميل'],
      ['نقل البيانات', 'محلي للتجربة؛ HTTPS مطلوب للإنتاج'],
      ['تطبيق Android', 'مرحلة لاحقة لاختبار التتبع بالخلفية'],
    ]
      .map(([k, v]) => `<div class="detail-row"><span>${e(k)}</span><b>${e(v)}</b></div>`)
      .join(
        '',
      )}</div></div></div><div class="info-box"><h3>عزل الشركات</h3>كل طلب يمر بعضوية فعّالة وصلاحية، ثم نطاق الشركة. تبديل الشركة يغيّر جميع البيانات المعروضة. إعدادات الاحتفاظ بالمواقع، الموافقات، النسخ والاستعادة والتكاملات موثقة في خطة التنفيذ، وتحتاج تهيئة إنتاج.</div>`;
  }
  let debtTab = 'invoices';
  function receivablesPage() {
    const base = receivablesInvoices(),
      start = base.indexOf('<div class="info-box"><h3>تذكير المندوب');
    const tabs =
      '<div class="section-tabs debt-tabs"><button data-debt-tab="invoices" class="' +
      (debtTab === 'invoices' ? 'selected' : '') +
      '">الفواتير المفتوحة</button><button data-debt-tab="customers" class="' +
      (debtTab === 'customers' ? 'selected' : '') +
      '">رصيد كل عميل</button><button data-debt-tab="representatives" class="' +
      (debtTab === 'representatives' ? 'selected' : '') +
      '">رصيد كل مندوب</button><button data-debt-tab="followups" class="' +
      (debtTab === 'followups' ? 'selected' : '') +
      '">سجل المتابعة</button></div>';
    if (debtTab === 'invoices') return base.slice(0, start) + tabs + base.slice(start);
    if (debtTab === 'followups')
      return (
        base.slice(0, start) +
        tabs +
        table(
          [
            'العميل / الفاتورة',
            'نتيجة التواصل',
            'ملاحظة المندوب',
            'وعد بالسداد',
            'المتابعة القادمة',
            'سجلها',
          ],
          data.followups.filter(match).map((f) =>
            tr([
              '<strong>' + e(f.order.customer.name) + '</strong><small>' + e(f.order.code) + '</small>',
              e(
                {
                  contacted: 'تم التواصل',
                  no_answer: 'لا يوجد رد',
                  promised: 'وعد بالسداد',
                }[f.outcome],
              ),
              `<div class="followup-note">${e(f.note)}</div>`,
              f.promised_minor ? amount(f.promised_minor) : '—',
              '<span class="pill ' +
                (f.next_followup_date < data.today ? 'warning' : 'neutral') +
                '">' +
                day(f.next_followup_date) +
                '</span>',
              e(f.actor.name) + '<small>' + when(f.created_at) + '</small>',
            ]),
          ),
          'آخر 100 ملاحظة، محفوظة دون تعديل رصيد الدين. الوعد بالسداد ليس مبلغًا مستلمًا.',
        )
      );
    if (debtTab === 'customers')
      return (
        base.slice(0, start) +
        tabs +
        table(
          ['العميل', 'الفواتير المفتوحة', 'إجمالي الآجل المتبقي', 'تفاصيل الفواتير'],
          data.debt_customers
            .filter(match)
            .map((c) =>
              tr([
                '<strong>' + e(c.name) + '</strong>',
                num(c.invoices),
                '<span class="money">' + amount(c.balance_minor) + '</span>',
                '<button class="quiet-btn" data-debt-name="' + e(c.name) + '">عرض الفواتير</button>',
              ]),
            ),
          'الأرصدة محسوبة من كامل السجل؛ السداد على الفاتورة يغيّر رصيد العميل مباشرة.',
        )
      );
    return (
      base.slice(0, start) +
      tabs +
      table(
        ['المندوب', 'عملاء عليهم آجل', 'إجمالي الآجل', 'تفاصيل الفواتير'],
        data.debt_representatives
          .filter(match)
          .map((r) =>
            tr([
              '<strong>' + e(r.name) + '</strong>',
              num(r.customers),
              '<span class="money">' + amount(r.balance_minor) + '</span>',
              '<button class="quiet-btn" data-debt-name="' + e(r.name) + '">عرض الفواتير</button>',
            ]),
          ),
        'آجل المندوب هو آجل الطلبات التي أنشأها؛ ليس نقدًا مستلمًا في عهدته.',
      )
    );
  }
  document.addEventListener('click', (ev) => {
    const t = ev.target.closest('[data-debt-tab]');
    if (t) {
      debtTab = t.dataset.debtTab;
      query = '';
      $('#global-search').value = '';
      render();
    }
    const n = ev.target.closest('[data-debt-name]');
    if (n) {
      debtTab = 'invoices';
      query = n.dataset.debtName;
      $('#global-search').value = query;
      render();
    }
  });
  function adminContext() {
    return {
      data,
      role,
      manager,
      canMoney,
      btn,
      table,
      tr,
      metric,
      amount,
      match,
      when,
      day,
      icon,
      pill,
      selectedVehicle,
      fleetStatus,
      tab: adminTab,
    };
  }
  const renderers = {
    dashboard: () =>
      rep || role === 'accountant' ? dashboard() : window.FieldFlowAdmin.dashboard(adminContext()),
    tracking,
    trips: tripsPage,
    visits: visitsPage,
    customers: customersPage,
    team: () => window.FieldFlowAdmin.team(adminContext()),
    vehicles: () => window.FieldFlowAdmin.fleet(adminContext()),
    inventory: inventoryPage,
    orders: ordersPage,
    collections: collectionsPage,
    settlements: settlementsPage,
    receivables: receivablesPage,
    reports: reportsPage,
    audit: auditPage,
    settings: settingsPage,
  };
  function render() {
    if (map) {
      mapCenter = map.getCenter();
      mapZoom = map.getZoom();
      map.remove();
      map = null;
    }
    $('#app-content').innerHTML = window.AmrbitJourney.customerId
      ? window.AmrbitJourney.customerPage(adminContext())
      : window.AmrbitJourney.isSchedule
        ? window.AmrbitJourney.schedule(adminContext())
        : renderers[section]();
    const journeyTitle = window.AmrbitJourney.title();
    if (journeyTitle) {
      $('#page-title').textContent = journeyTitle[0];
      $('#page-description').textContent = journeyTitle[1];
      $('#section-code').textContent = journeyTitle[2];
      $('#main-action').hidden = true;
      $('.global-search').hidden = true;
    }
    if (manager && section === 'trips' && !window.AmrbitJourney.isSchedule)
      $('#app-content').insertAdjacentHTML(
        'afterbegin',
        '<div class="filters"><a class="secondary-btn" href="/schedule">توزيع العملاء وجدول الأسبوع ←</a></div>',
      );
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches)
      $('#app-content').animate(
        [
          { opacity: 0.65, transform: 'translateY(5px)' },
          { opacity: 1, transform: 'none' },
        ],
        { duration: 220, easing: 'ease-out' },
      );
    if (['vehicles', 'inventory', 'settlements'].includes(section)) {
      $('#app-content').insertAdjacentHTML(
        'afterbegin',
        `<div class="custody-workflow-banner"><span>التسليم بين الطرفين: ${Number(data.pending_handovers_count || 0)} طلب معلق. اعتمد التفاصيل من حساب المستلم، ثم اطبع المحضر وأرشف توقيعه.</span><a href="/handovers">مركز التسليم والاستلام ←</a></div>`,
      );
    }
    if ($('#ops-map')) drawMap();
  }
  function drawMap() {
    if (!window.L) return;
    map = L.map('ops-map', { zoomControl: true }).setView([24.785, 46.648], 12);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);
    const bounds = [];
    const customerIds =
      section === 'tracking' || section === 'vehicles'
        ? data.visits.filter((v) => v.trip_id === Number(selectedTrip)).map((v) => v.customer_id)
        : null;
    data.customers
      .filter((c) => !customerIds || customerIds.includes(c.id))
      .forEach((c) => {
        const pos = [Number(c.latitude), Number(c.longitude)];
        bounds.push(pos);
        L.marker(pos, {
          title: c.name,
          icon: L.divIcon({
            className: '',
            html: '<span class="customer-marker"></span>',
            iconSize: [10, 10],
            iconAnchor: [5, 5],
          }),
        })
          .addTo(map)
          .bindPopup(`<strong>${e(c.name)}</strong><br>${e(c.address)}`);
        if (section === 'tracking')
          L.circle(pos, {
            radius: c.geofence_radius_m,
            color: '#9b91be',
            weight: 1,
            fillOpacity: 0.08,
          }).addTo(map);
      });
    const trips =
      section === 'tracking' || section === 'vehicles'
        ? data.trips.filter((t) => t.id === Number(selectedTrip))
        : data.trips.filter((t) => t.status === 'active');
    mapLayers = [];
    trips.forEach((t, i) => {
      const ps = data.points.filter((p) => p.trip_id === t.id),
        p = ps.at(-1);
      if (!p) return;
      const coords = ps.map((p) => [Number(p.latitude), Number(p.longitude)]);
      bounds.push(...coords);
      L.polyline(coords, {
        color: colors[i % 6],
        weight: 3,
        opacity: 0.7,
        dashArray: section === 'tracking' ? null : '4 7',
      }).addTo(map);
      const fresh = freshness(t);
      const marker = L.marker(coords.at(-1), {
        title: t.representative.name,
        icon: L.divIcon({
          className: '',
          html: `<span class="rep-marker ${fresh[0] ? 'stale' : ''}">${e(initial(t.representative.name))}</span>`,
          iconSize: [35, 35],
          iconAnchor: [17, 17],
        }),
      })
        .addTo(map)
        .bindPopup(
          `<strong>${e(t.representative.name)}</strong><br>${e(fresh[2])}<br>${e(p.source === 'demo' ? 'مصدر: بيانات تجريبية' : 'مصدر: المتصفح')}<br>الدقة ${p.accuracy_m} متر`,
        );
      marker.on('click', () => {
        if (section !== 'tracking') location.href = `/tracking?trip=${t.id}`;
      });
      mapLayers.push({ trip: t, marker });
      if (section === 'tracking') playMarker = marker;
    });
    if (mapCenter && mapZoom) map.setView(mapCenter, mapZoom);
    else if (bounds.length) map.fitBounds(bounds, { padding: [35, 35], maxZoom: 14 });
    const info = document.createElement('div');
    info.className = 'map-info';
    info.innerHTML = `<strong>${section === 'tracking' ? 'مسار من النقاط المسجّلة' : 'الميدان من نظرة واحدة'}</strong><small>نقاط مسجّلة • المصدر والعمر في تفاصيل الرحلة</small>`;
    $('#ops-map').appendChild(info);
  }
  async function load(initial = false) {
    try {
      const response = await fetch('/ops/data', {
        headers: { Accept: 'application/json' },
      });
      if (response.status === 401 || response.status === 419) {
        location.href = '/login';
        return;
      }
      if (!response.ok) throw new Error('تعذر تحميل البيانات.');
      data = await response.json();
      await window.AmrbitJourney.hydrate(data);
      $('#main-action').disabled = false;
      if (rep && ['orders', 'collections', 'receivables'].includes(section)) {
        $('#main-action').hidden =
          section === 'orders' ? !data.my_permissions.can_sell : !data.my_permissions.can_collect;
      }
      $('#updated-at').textContent =
        'آخر تحديث ' +
        new Date().toLocaleTimeString('ar-SA', {
          hour: '2-digit',
          minute: '2-digit',
        });
      const next = JSON.stringify({
        ...data,
        as_of: null,
        customer_profile: window.AmrbitJourney.signature(),
      });
      if (initial || next !== signature) {
        signature = next;
        if (!document.querySelector('dialog[open]')) render();
      }
    } catch (err) {
      if (initial) $('#app-content').innerHTML = empty(err.message);
      else toast('انقطع الاتصال. البيانات الظاهرة من آخر تحديث ناجح.', true);
    }
  }
  function toast(message, error = false) {
    const t = $('#toast');
    t.textContent = message;
    t.classList.toggle('error', error);
    t.hidden = false;
    clearTimeout(t.timer);
    t.timer = setTimeout(() => (t.hidden = true), 6500);
  }
  async function execute(action, payload, id = null) {
    const response = await fetch(
      (window.AmrbitMaintenance.handles(action) ? '/ops/journey/' : window.AmrbitJourney.endpoint(action)) +
        action,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': $('meta[name=csrf-token]').content,
        },
        body: JSON.stringify({
          request_id: id || requestUuid(),
          ...payload,
        }),
      },
    );
    let body;
    try {
      body = await response.json();
    } catch {
      throw new Error('تعذر معالجة الرد؛ حدّث الصفحة.');
    }
    if (!response.ok) {
      if (response.status === 401 || response.status === 419) {
        location.href = '/login';
        return;
      }
      throw new Error(
        body.errors ? Object.values(body.errors).flat().join(' ') : body.message || 'تعذر تنفيذ العملية.',
      );
    }
    return body;
  }
  const options = (rows, key = 'name') =>
    rows.map((r) => `<option value="${r.id}">${e(r[key])}</option>`).join('');
  const field = (name, label, type = 'text', value = '', extra = '') =>
    `<div class="field"><label for="f-${name}">${e(label)}</label><input id="f-${name}" name="${name}" type="${type}" value="${e(value)}" ${extra}></div>`;
  const select = (name, label, opts) =>
    `<div class="field"><label for="f-${name}">${e(label)}</label><select name="${name}" id="f-${name}" ${name === 'trip_id' && !rep ? '' : 'required'}>${opts}</select></div>`;
  let orderLineCounter = 0;
  function orderLine() {
    const index = ++orderLineCounter;
    return `<div class="order-line"><div class="field"><label for="line-product-${index}">الصنف</label><select id="line-product-${index}" name="line_product_id" required>${data.products.map((p) => `<option value="${p.id}">${e(p.name)} • ${(p.price_minor / 100).toFixed(2)} ر.س / ${e(p.unit)}</option>`).join('')}</select></div><div class="field"><label for="line-qty-${index}">الكمية</label><input id="line-qty-${index}" name="line_quantity" type="number" min="1" max="10000" value="1" required></div><button type="button" class="icon-btn" data-order-remove aria-label="حذف هذا الصنف من مسودة الطلب">×</button></div>`;
  }
  function updateOrderPreview() {
    if (!$('#order-total-preview')) return;
    let total = 0;
    document.querySelectorAll('.order-line').forEach((row) => {
      const product = data.products.find((p) => p.id === Number(row.querySelector('select').value));
      total += (product?.price_minor || 0) * Number(row.querySelector('input').value || 0);
    });
    $('#order-total-preview').innerHTML =
      'إجمالي تقريبي من الكتالوج: <strong>' +
      amount(total) +
      '</strong><small>يعيد الخادم حساب الأسعار والإجمالي عند الحفظ.</small>';
  }
  document.addEventListener('click', (ev) => {
    if (ev.target.closest('[data-order-add]')) {
      if (document.querySelectorAll('.order-line').length >= 50) {
        toast('الحد 50 صنفًا للطلب.', true);
        return;
      }
      $('#order-lines').insertAdjacentHTML('beforeend', orderLine());
      updateOrderPreview();
    }
    const remove = ev.target.closest('[data-order-remove]');
    if (remove) {
      if (document.querySelectorAll('.order-line').length <= 1) {
        toast('الطلب يحتاج صنفًا واحدًا على الأقل.', true);
        return;
      }
      remove.closest('.order-line').remove();
      updateOrderPreview();
    }
  });
  document.addEventListener('input', (ev) => {
    if (ev.target.closest('.order-line')) updateOrderPreview();
  });
  document.addEventListener('change', (ev) => {
    if (ev.target.closest('.order-line')) updateOrderPreview();
  });
  function settlementsPage() {
    const balances = data.cash_balances.filter(match),
      totals = data.cash_balances.reduce(
        (s, r) => ({
          received: s.received + r.received_minor,
          accepted: s.accepted + r.accepted_minor,
          balance: s.balance + r.balance_minor,
          pending: s.pending + r.pending_minor,
        }),
        { received: 0, accepted: 0, balance: 0, pending: 0 },
      );
    const label = {
      pending: 'بانتظار الاستلام',
      accepted: 'تم الاستلام',
      rejected: 'مرفوض',
      cancelled: 'ملغي',
    };
    return (
      `<section class="cash-hero"><div><span class="eyebrow">من التحصيل إلى الحسابات</span><h2>كل مبلغ معروف، وكل تسليم مُراجع.</h2><p>تأكيد سداد العميل شيء، وتسليم نقد المندوب للحسابات خطوة مستقلة.</p></div><span class="cash-hero-symbol">${icon('wallet')}</span></section><section class="metrics">${metric('النقد المستلم', num(totals.received / 100), 'ر.س', 'الإيصالات النقدية فقط', 'wallet', 'green')}${metric('استلمته الحسابات', num(totals.accepted / 100), 'ر.س', 'طلبات تم اعتماد استلامها', 'check', 'green')}${metric('عهدة النقد الحالية', num(totals.balance / 100), 'ر.س', 'المستلم ناقص التسليم المعتمد', 'wallet', 'orange')}${metric('تسليمات معلقة', num(totals.pending / 100), 'ر.س', 'محجوزة، ولم تعتمد بعد', 'clock')}</section>` +
      `<div class="section-label"><h2>${rep ? 'عهدتك النقدية' : 'أرصدة المستلمين'}</h2><small>تُنسب العهدة لمن سجّل إيصال الاستلام، وليس لمندوب الطلب تلقائيًا.</small></div>` +
      table(
        ['المستلم', 'نقد مستلم', 'سُلّم للحسابات', 'العهدة الحالية', 'قيد المراجعة', 'متاح لطلب التسليم'],
        balances.map((r) =>
          tr([
            `<div class="table-person"><span class="rep-initial">${e(initial(r.name))}</span><strong>${e(r.name)}</strong></div>`,
            amount(r.received_minor),
            amount(r.accepted_minor),
            `<strong class="money">${amount(r.balance_minor)}</strong>`,
            amount(r.pending_minor),
            amount(r.available_minor),
          ]),
        ),
      ) +
      `<div class="section-label"><h2>طلبات تسليم النقد</h2><small>لا يُحذف أثر التسليم، وتظهر نتيجة مراجعته ومن استلمه.</small></div>` +
      table(
        ['رقم الطلب / المرجع', 'صاحب العهدة', 'المبلغ', 'الحالة', 'المراجعة', 'الإجراء'],
        data.cash_handovers
          .filter(match)
          .map((h) =>
            tr([
              `<strong class="mono">${e(h.code)}</strong><small>${e(h.reference)}</small>`,
              e(h.collector.name),
              `<strong class="money">${amount(h.amount_minor)}</strong>`,
              `<span class="pill ${h.status === 'pending' ? 'warning' : h.status === 'rejected' ? 'danger' : h.status === 'cancelled' ? 'neutral' : ''}">${label[h.status]}</span>`,
              h.reviewer
                ? e(h.reviewer.name) + `<small>${e(h.review_note || '')} • ${when(h.reviewed_at)}</small>`
                : 'بانتظار مستلم آخر',
              h.status === 'pending'
                ? `<div class="row-actions">${['manager', 'accountant'].includes(role) && h.requested_by !== data.user.id && h.collector_id !== data.user.id ? btn('cash.accept', h.id, 'تأكيد الاستلام', 'primary-btn') + btn('cash.reject', h.id, 'رفض') : ''}${h.requested_by === data.user.id || h.collector_id === data.user.id ? btn('cash.cancel', h.id, 'إلغاء طلبي') : ''}</div>`
                : '—',
            ]),
          ),
        'آخر 200 طلب تسليم. التحويل والبطاقة لا يدخلان عهدة النقد.',
      ) +
      `<div class="info-box"><h3>اعتماد الاستلام</h3>التأكيد يعني أن المستلم الآخر استلم النقد فعليًا وفق إجراء الشركة. لا يحوّل النظام أموالًا بنكية، ولا يغيّر دين العميل بسبب تسليم العهدة.</div>`
    );
  }
  function openForm(action, id) {
    dialogAction = action;
    dialogExtra = {};
    currentRequestId = requestUuid();
    let title = '',
      html = '';
    const administrationForm =
      window.AmrbitMaintenance.form(action, id, adminContext()) ||
      window.AmrbitJourney.form(action, id, adminContext()) ||
      window.FieldFlowAdmin.form(action, id, adminContext());
    if (administrationForm) {
      title = administrationForm.title;
      html = administrationForm.html;
      dialogExtra = administrationForm.extra;
      if (administrationForm.action) dialogAction = administrationForm.action;
    } else
      switch (action) {
        case 'product.create':
          title = 'إضافة صنف توزيع';
          html =
            field('name', 'اسم المنتج', 'text', '', 'required') +
            field('sku', 'رمز المنتج', 'text', '', 'required maxlength="40"') +
            field('unit', 'وحدة التخزين والبيع', 'text', 'كرتون', 'required') +
            field('price', 'سعر الوحدة • بالريال', 'number', '', 'required min="0.01" step="0.01"') +
            field('reorder_level', 'حد إعادة الطلب بالوحدات', 'number', 20, 'required min="0" max="100000"');
          break;
        case 'warehouse.create':
          title = 'إضافة مستودع';
          html = field('name', 'اسم المستودع', 'text', '', 'required');
          break;
        case 'stock.receive':
          title = 'استلام مخزون للمستودع';
          html =
            select(
              'warehouse_id',
              'المستودع',
              options(data.warehouses.filter((w) => w.type === 'warehouse')),
            ) +
            select('product_id', 'المنتج', options(data.products)) +
            field('quantity', 'الكمية المستلمة', 'number', 100, 'required min="1" max="100000"') +
            field('reference', 'مرجع الاستلام', 'text', '', 'required minlength="3" maxlength="100"') +
            '<div class="info-box">استلام تشغيلي للمخزون. فاتورة المورد وتكلفة الشراء ليستا ضمن هذا الإجراء.</div>';
          break;
        case 'customer.credit': {
          const c = data.customers.find((c) => c.id === Number(id));
          title = 'ضبط الحد الائتماني • ' + c.name;
          dialogExtra = { id: Number(id) };
          html =
            field(
              'credit_limit',
              'أعلى آجل مسموح • بالريال',
              'number',
              c.credit_limit_minor === null ? '' : c.credit_limit_minor / 100,
              'min="0" max="1000000" step="0.01"',
            ) +
            field('reason', 'سبب تغيير الحد', 'text', '', 'required minlength="3" maxlength="300"') +
            '<div class="info-box">اترك الحد فارغًا إذا لم ترغب بحد. صفر يعني منع آجل جديد. تغيير الحد لا يمسح الديون القائمة، ويُحفظ سببه في سجل العمليات.</div>';
          break;
        }
        case 'trip.cancel': {
          const file = window.FieldFlowTripFile;
          if (!file || file.trip.id !== Number(id)) return;
          title = 'إلغاء خطة الرحلة ' + file.trip.code;
          dialogExtra = {
            id: file.trip.id,
            plan_version: file.trip.plan_version,
          };
          html = `<div class="info-box">سيُحفظ سجل الرحلة ومحطاتها بحالة ملغاة. هذا الإجراء لا يرجع مخزون السيارة ولا يسقط أي دين.</div><div class="field"><label for="f-reason">سبب الإلغاء</label><textarea name="reason" id="f-reason" required minlength="3" maxlength="500" placeholder="مثال: تم إلغاء جولة اليوم بناء على طلب الإدارة"></textarea></div>`;
          break;
        }
        case 'trip.create':
        case 'trip.update': {
          const t = action === 'trip.update' ? window.FieldFlowTripFile?.trip : null;
          if (action === 'trip.update' && (!t || t.id !== Number(id))) return;
          tripStops = t
            ? window.FieldFlowTripFile.visits
                .filter((v) => v.status !== 'cancelled')
                .map((v) => v.customer_id)
            : [];
          title = t ? 'تعديل خطة ' + t.code : 'تخطيط رحلة جديدة';
          if (t)
            dialogExtra = {
              id: t.id,
              plan_version: t.plan_version,
            };
          const planCustomers = [
            ...new Map(
              [...data.customers, ...(t ? window.FieldFlowTripFile.visits.map((v) => v.customer) : [])]
                .filter(Boolean)
                .map((c) => [c.id, c]),
            ).values(),
          ];
          html =
            select('representative_id', 'المندوب', options(data.representatives)) +
            select(
              'vehicle_id',
              'السيارة المتاحة',
              data.vehicles
                .filter((v) => v.status === 'available')
                .map(
                  (v) =>
                    `<option value="${v.id}">${e(v.name)} • ${e(data.vehicle_types[v.vehicle_type] || 'النوع غير محدد')} • ${e(v.plate)}</option>`,
                )
                .join(''),
            ) +
            field(
              'scheduled_for',
              'موعد الرحلة',
              'date',
              t ? String(t.scheduled_for).slice(0, 10) : data.today,
              `required min="${data.today}"`,
            ) +
            `<div class="field"><label>العملاء • اختر محطات الرحلة</label><input type="search" id="trip-customer-search" placeholder="ابحث باسم العميل أو رمزه" aria-label="بحث عملاء الخطة"><p id="trip-coverage-note" class="admin-note"></p><div class="field-checks">${planCustomers
              .filter((c) => c.is_active || tripStops.includes(c.id))
              .map(
                (c) =>
                  `<label class="check-label" data-customer-area="${c.area_id}" data-customer-search="${e(c.name + ' ' + c.code)}"><input type="checkbox" name="customer_ids" value="${c.id}" ${tripStops.includes(c.id) ? 'checked' : ''}>${e(c.name)}${!c.is_active ? ' (غير نشط؛ أزله قبل الحفظ)' : ''} <b data-stop-order="${c.id}"></b></label>`,
              )
              .join(
                '',
              )}</div><small>المعروض الدليل المحمّل وكل محطات هذه الرحلة. البحث يخفي النتائج فقط، ويحفظ اختياراتك.</small><div id="trip-selected-stops" aria-live="polite"></div></div>` +
            `<div class="field"><label for="f-planning-notes">تعليمات الرحلة للمندوب</label><textarea id="f-planning-notes" name="planning_notes" maxlength="1000" placeholder="ملاحظات التحميل أو أولوية العملاء…">${e(t?.planning_notes || '')}</textarea></div>` +
            (t
              ? '<div class="field"><label for="f-reason">سبب تعديل الخطة</label><textarea id="f-reason" name="reason" required minlength="3" maxlength="500"></textarea></div>'
              : '') +
            '<div class="info-box">الموعد يوم كامل، وليس حجزًا زمنيًا أو مسارًا محسّنًا. بدء الرحلة يتحقق من تفرغ المندوب والسيارة. تغيير السيارة يربط عهدتها الحالية ولا ينقل مخزونًا تلقائيًا.</div>';
          break;
        }
        case 'customer.create':
          title = 'إضافة عميل';
          html =
            field('name', 'اسم العميل', 'text', '', 'required maxlength="150"') +
            field('code', 'رمز العميل', 'text', '', 'required maxlength="40"') +
            select(
              'representative_id',
              'المندوب المسؤول',
              '<option value="">اختر مسؤول العميل</option>' + options(data.representatives),
            ) +
            select('area_id', 'منطقة العميل', options(data.areas)) +
            field('address', 'العنوان', 'text', '', 'required') +
            `<div class="field-grid">${field('latitude', 'خط العرض', 'number', '', 'required step="any" min="-90" max="90"')}${field('longitude', 'خط الطول', 'number', '', 'required step="any" min="-180" max="180"')}</div>` +
            field(
              'geofence_radius_m',
              'نطاق إثبات الزيارة بالمتر',
              'number',
              150,
              'required min="25" max="500"',
            ) +
            field('phone', 'الهاتف الاختياري', 'tel');
          break;
        case 'area.create':
          title = 'إضافة منطقة خدمة';
          html =
            field('name', 'اسم المنطقة', 'text', '', 'required') +
            field('city', 'المدينة', 'text', 'الرياض', 'required');
          break;
        case 'vehicle.create':
          title = 'إضافة سيارة وعهدتها';
          html =
            field('name', 'اسم السيارة', 'text', '', 'required') +
            field('plate', 'اللوحة / الرمز', 'text', '', 'required') +
            field('capacity_units', 'السعة بوحدات التخزين', 'number', 600, 'required min="1" max="100000"');
          break;
        case 'order.create': {
          title = 'طلب بيع جديد';
          const trips = data.trips.filter((t) => t.status === 'active');
          html =
            select(
              'customer_id',
              'العميل',
              options(
                rep
                  ? data.customers.filter((c) =>
                      data.visits.some(
                        (v) => v.customer_id === c.id && trips.some((t) => t.id === v.trip_id),
                      ),
                    )
                  : data.customers,
              ),
            ) +
            select('representative_id', 'المندوب', options(data.representatives)) +
            select('warehouse_id', 'مخزون التسليم', options(data.warehouses)) +
            select(
              'trip_id',
              'ربط برحلة',
              (!rep ? '<option value="">طلب إداري بدون رحلة</option>' : '') +
                trips
                  .map((t) => `<option value="${t.id}">${e(t.code)} • ${e(t.representative.name)}</option>`)
                  .join(''),
            ) +
            '<div id="order-lines">' +
            orderLine() +
            '</div><button type="button" class="secondary-btn" data-order-add>إضافة صنف آخر</button><div class="info-box" id="order-total-preview"></div>' +
            select(
              'payment_terms',
              'المبلغ عند التسليم',
              '<option value="credit">آجل • لم أستلم مبلغًا</option><option value="cash">تم استلام كامل المبلغ نقدًا</option><option value="partial">تم استلام جزء نقدًا والباقي آجل</option>',
            ) +
            field('initial_paid', 'المستلم جزئيًا • بالريال', 'number', '', 'min="0.01" step="0.01"') +
            field('due_date', 'موعد استحقاق الآجل', 'date', data.today, '') +
            '<div class="info-box">اختيار الدفع هنا مسودة. يتثبت التحصيل عند «تسليم الطلب» فقط. الأسعار والإجمالي تُحسب داخل النظام؛ لا يطلب منك إدخال الإجمالي.</div>';
          break;
        }
        case 'cash.request': {
          const owners = data.cash_balances.filter(
            (r) => r.available_minor > 0 && (role !== 'representative' || r.id === data.user.id),
          );
          if (!owners.length) {
            toast('لا توجد عهدة نقدية متاحة لطلب تسليمها.', true);
            return;
          }
          title = 'طلب تسليم نقد للحسابات';
          html =
            select(
              'collector_id',
              'صاحب العهدة',
              owners
                .map(
                  (r) =>
                    `<option value="${r.id}">${e(r.name)} • متاح ${(r.available_minor / 100).toFixed(2)} ر.س</option>`,
                )
                .join(''),
            ) +
            field(
              'cash_amount',
              'المبلغ المطلوب تسليمه • بالريال',
              'number',
              '',
              'required min="0.01" step="0.01"',
            ) +
            field('reference', 'مرجع التسليم', 'text', '', 'required minlength="3" maxlength="100"') +
            '<div class="field"><label for="f-note">ملاحظة اختيارية</label><textarea id="f-note" name="note" maxlength="500"></textarea></div><div class="info-box">يحجز الطلب المبلغ للمراجعة. ينقص من العهدة عند اعتماد مستلم آخر فقط.</div>';
          break;
        }
        case 'cash.accept':
        case 'cash.reject':
        case 'cash.cancel': {
          const h = data.cash_handovers.find((h) => h.id === Number(id));
          title =
            action === 'cash.accept'
              ? 'تأكيد استلام النقد'
              : action === 'cash.reject'
                ? 'رفض طلب التسليم'
                : 'إلغاء طلب تسليمي';
          dialogExtra = { id: Number(id) };
          html = `<div class="info-box"><strong>${e(h.collector.name)} • ${amount(h.amount_minor)}</strong><br>${e(h.code)} • ${e(h.reference)}</div><div class="field"><label for="f-note">${action === 'cash.accept' ? 'مرجع أو ملاحظة الاستلام الفعلي' : 'سبب الإجراء'}</label><textarea id="f-note" name="note" required minlength="3" maxlength="500"></textarea></div>`;
          break;
        }
        case 'debt.followup': {
          title = 'تسجيل متابعة دين';
          const o = data.orders.find((o) => o.id === Number(id));
          dialogExtra = { order_id: Number(id) };
          html =
            '<div class="info-box"><strong>' +
            e(o.customer.name) +
            '</strong><br>المتبقي ' +
            amount(o.total_minor - o.paid_minor) +
            '</div>' +
            select(
              'outcome',
              'نتيجة المتابعة',
              '<option value="contacted">تم التواصل</option><option value="no_answer">لا يوجد رد</option><option value="promised">وعد العميل بالسداد</option>',
            ) +
            '<div class="field"><label for="f-note">ماذا حدث؟</label><textarea id="f-note" name="note" required minlength="3" maxlength="1000"></textarea></div>' +
            field('next_followup_date', 'موعد المتابعة القادمة', 'date', data.today, 'required') +
            field(
              'promised_amount',
              'المبلغ الموعود • عند اختيار وعد بالسداد',
              'number',
              '',
              'min="0.01" step="0.01"',
            ) +
            '<small>الملاحظة والوعد لا ينقصان الدين. سجل إيصالًا عند استلام المبلغ فعليًا.</small>';
          break;
        }
        case 'collection.create': {
          title = 'استلام سداد آجل';
          const debts = data.orders.filter((o) => o.status === 'fulfilled' && o.paid_minor < o.total_minor);
          html =
            select(
              'order_id',
              'فاتورة العميل',
              debts
                .map(
                  (o) =>
                    `<option value="${o.id}" ${Number(id) === o.id ? 'selected' : ''}>${e(o.customer.name)} • ${e(o.code)} • متبقي ${(o.total_minor - o.paid_minor) / 100} ر.س</option>`,
                )
                .join(''),
            ) +
            field(
              'amount',
              'المبلغ المستلم • بالريال',
              'number',
              '',
              'required min="0.01" max="1000000" step="0.01"',
            ) +
            select(
              'method',
              'طريقة الاستلام',
              '<option value="cash">نقدي</option><option value="bank">تحويل بنكي</option><option value="card">بطاقة</option>',
            ) +
            '<div class="info-box">سيخفض السداد رصيد هذه الفاتورة فقط ويُنشئ إيصالًا، مع حفظ اسم المستلم ووقت العملية.</div>';
          break;
        }
        case 'visit.skip':
        case 'visit.fail':
          title = 'تسجيل تعذر الزيارة';
          dialogAction = 'visit.finish';
          dialogExtra = {
            id: Number(id),
            status: action === 'visit.skip' ? 'skipped' : 'failed',
          };
          html =
            '<div class="field"><label for="f-note">سبب التعذر</label><textarea name="note" id="f-note" required minlength="3" maxlength="1000" placeholder="مثال: العميل مغلق"></textarea></div>';
          break;
        default:
          return;
      }
    $('#dialog-title').textContent = title;
    $('#form-fields').innerHTML = html;
    if (['trip.create', 'trip.update'].includes(action)) {
      if (action === 'trip.update') {
        const t = window.FieldFlowTripFile.trip;
        $('#action-form [name="representative_id"]').value = String(t.representative_id);
        $('#action-form [name="vehicle_id"]').value = String(t.vehicle_id);
      }
      updateTripCoverage();
    }
    if (action === 'order.create') updateOrderPreview();
    $('#form-error').textContent = '';
    $('#submit-action').disabled = false;
    $('#action-dialog').showModal();
    window.AmrbitMaintenance.afterForm(action, adminContext());
  }
  async function action(action, id) {
    if ($('#record-dialog').open) $('#record-dialog').close();
    if (busy) return;
    if (!data) {
      toast('انتظر تحميل بيانات الشركة.');
      return;
    }
    if (action === 'stock.transfer') {
      location.href = '/handovers?new=stock_transfer';
      return;
    }
    if (action === 'visit.sale') {
      const visit = data.visits.find((v) => v.id === Number(id));
      const trip = data.trips.find((t) => t.id === visit?.trip_id);
      if (!visit || !trip) return;
      if (!data.my_permissions.can_sell) {
        toast('صلاحية البيع غير مفعلة لحسابك.', true);
        return;
      }
      openForm('order.create', null);
      for (const [key, value] of Object.entries({
        customer_id: visit.customer_id,
        trip_id: trip.id,
        representative_id: trip.representative_id,
        warehouse_id: trip.warehouse_id,
      })) {
        const input = document.querySelector('#action-form [name="' + key + '"]');
        if (input) input.value = String(value);
      }
      updateOrderPreview();
      return;
    }
    if (['trip.start', 'trip.close', 'order.fulfill', 'visit.checkin', 'location.record'].includes(action)) {
      busy = true;
      try {
        let payload = { id: Number(id) },
          actual = action;
        if (action === 'visit.complete') {
          actual = 'visit.finish';
          payload.status = 'completed';
        }
        if (action === 'visit.checkin' || action === 'location.record') {
          if (!window.isSecureContext)
            throw new Error(
              'تحديد الموقع من جهاز آخر يحتاج رابط HTTPS موثوقًا. رابط HTTP الحالي مناسب لتجربة الإدارة والبيانات.',
            );
          if (!navigator.geolocation) throw new Error('المتصفح لا يدعم الموقع.');
          toast('جارٍ الحصول على الموقع بإذنك…');
          const position = await new Promise((resolve, reject) =>
            navigator.geolocation.getCurrentPosition(
              resolve,
              () => reject(new Error('تعذر الحصول على الموقع. فعّل الإذن وخدمات الموقع وحاول مجددًا.')),
              {
                enableHighAccuracy: true,
                timeout: 20000,
                maximumAge: 0,
              },
            ),
          );
          payload = {
            ...payload,
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy_m: Math.ceil(position.coords.accuracy),
            captured_at: new Date(position.timestamp).toISOString(),
          };
          if (actual === 'location.record') {
            delete payload.id;
            payload.trip_id = Number(id);
            payload.event_id = requestUuid();
          }
        }
        const result = await execute(actual, payload);
        toast(result.message);
        await load(true);
      } catch (err) {
        toast(err.message, true);
      } finally {
        busy = false;
      }
    } else openForm(action, id);
  }
  function updateTripCoverage() {
    const input = $('#action-form [name="representative_id"]');
    if (!input || !$('#trip-coverage-note')) return;
    const member = data.team.find((m) => m.id === Number(input.value));
    if (!member) return;
    if (dialogAction === 'trip.create' && input.dataset.vehicleRep !== input.value) {
      const assigned = (data.representative_vehicle_assignments || []).find(
        (a) => a.representative_id === Number(input.value) && !a.returned_at,
      );
      if (assigned) $('#action-form [name="vehicle_id"]').value = String(assigned.vehicle_id);
      input.dataset.vehicleRep = input.value;
    }
    const allowed = member.area_access === 'all' ? null : member.area_ids;
    $('#trip-coverage-note').textContent =
      allowed === null
        ? 'التغطية: كل مناطق الشركة.'
        : `التغطية: ${
            data.areas
              .filter((a) => allowed.includes(a.id))
              .map((a) => a.name)
              .join('، ') || 'لا توجد مناطق مسموحة؛ حدّدها من إدارة الفريق أولًا.'
          }`;
    document.querySelectorAll('[data-customer-area]').forEach((label) => {
      const visible = allowed === null || allowed.includes(Number(label.dataset.customerArea));
      const search = $('#trip-customer-search')?.value.trim().toLowerCase() || '';
      label.hidden = !visible || !label.dataset.customerSearch.toLowerCase().includes(search);
      const checkbox = label.querySelector('input');
      checkbox.disabled = !visible;
      if (!visible) {
        checkbox.checked = false;
        tripStops = tripStops.filter((id) => id !== Number(checkbox.value));
      }
    });
    renderTripStopOrder();
  }
  function renderTripStopOrder() {
    document.querySelectorAll('[data-stop-order]').forEach((el) => {
      const i = tripStops.indexOf(Number(el.dataset.stopOrder));
      el.textContent = i < 0 ? '' : `المحطة ${i + 1}`;
    });
    const selected = $('#trip-selected-stops');
    if (!selected) return;
    selected.innerHTML = `<h4>ترتيب المحطات (${num(tripStops.length)} / 50)</h4>${
      tripStops.length
        ? `<ol class="trip-order-list">${tripStops
            .map((id, index) => {
              const checkbox = document.querySelector(`#action-form [name="customer_ids"][value="${id}"]`),
                name = checkbox?.closest('label').dataset.customerSearch || 'عميل';
              return `<li><span class="trip-stop-number">${index + 1}</span><strong>${e(name)}</strong><button type="button" class="icon-btn" data-stop-move="${id}" data-direction="-1" aria-label="تقديم ${e(name)}" ${index === 0 ? 'disabled' : ''}>↑</button><button type="button" class="icon-btn" data-stop-move="${id}" data-direction="1" aria-label="تأخير ${e(name)}" ${index === tripStops.length - 1 ? 'disabled' : ''}>↓</button><button type="button" class="icon-btn" data-stop-remove="${id}" aria-label="إزالة ${e(name)}">×</button></li>`;
            })
            .join('')}</ol>`
        : '<p class="admin-note">اختر عميلًا من القائمة؛ ستظهر محطاته هنا لتقديمها أو تأخيرها.</p>'
    }`;
  }
  $('#action-form').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const submit = $('#submit-action');
    if (submit.disabled) return;
    submit.disabled = true;
    $('#form-error').textContent = '';
    try {
      const f = new FormData(ev.target),
        p = Object.fromEntries(f);
      Object.assign(p, dialogExtra);
      window.FieldFlowAdmin.payload(dialogAction, f, p);
      window.AmrbitJourney.payload(dialogAction, f, p);
      window.AmrbitMaintenance.payload(dialogAction, f, p);
      if (dialogAction === 'product.create') {
        if (!/^\d+(\.\d{1,2})?$/.test(p.price)) throw new Error('السعر يقبل منزلتين عشريتين.');
        const [w, d = ''] = p.price.split('.');
        p.price_minor = Number(w) * 100 + Number(d.padEnd(2, '0'));
        p.reorder_level = Number(p.reorder_level);
        delete p.price;
      }
      if (dialogAction === 'customer.credit') {
        if (p.credit_limit) {
          if (!/^\d+(\.\d{1,2})?$/.test(p.credit_limit)) throw new Error('الحد يقبل منزلتين عشريتين.');
          const [w, d = ''] = p.credit_limit.split('.');
          p.credit_limit_minor = Number(w) * 100 + Number(d.padEnd(2, '0'));
        } else p.credit_limit_minor = null;
        delete p.credit_limit;
      }
      if (['trip.create', 'trip.update'].includes(dialogAction)) {
        p.customer_ids = tripStops.filter((id) => f.getAll('customer_ids').includes(String(id)));
        if (!p.customer_ids.length) throw new Error('اختر عميلًا واحدًا على الأقل ضمن تغطية المندوب.');
        delete p['customer_ids[]'];
      }
      for (const key of [
        'id',
        'customer_id',
        'representative_id',
        'vehicle_id',
        'area_id',
        'warehouse_id',
        'from_id',
        'to_id',
        'product_id',
        'quantity',
        'geofence_radius_m',
        'capacity_units',
        'latitude',
        'longitude',
      ])
        if (key in p && p[key] !== null) p[key] = Number(p[key]);
      if (dialogAction === 'order.create') {
        const lineProducts = f.getAll('line_product_id'),
          lineQuantities = f.getAll('line_quantity');
        p.items = lineProducts.map((productId, index) => ({
          product_id: Number(productId),
          quantity: Number(lineQuantities[index]),
        }));
        delete p.line_product_id;
        delete p.line_quantity;
        p.trip_id = p.trip_id ? Number(p.trip_id) : null;
        const toMinor = (value) => {
          if (!/^\d+(\.\d{1,2})?$/.test(String(value)))
            throw new Error('اكتب المبلغ برقم صحيح أو منزلتين عشريتين.');
          const [whole, decimal = ''] = String(value).split('.');
          return Number(whole) * 100 + Number(decimal.padEnd(2, '0'));
        };
        if (p.initial_paid) p.initial_paid_minor = toMinor(p.initial_paid);
        if (p.payment_terms === 'cash') p.due_date = null;
        delete p.initial_paid;
        delete p.product_id;
        delete p.quantity;
      }
      if (dialogAction === 'debt.followup') {
        if (p.outcome === 'promised') {
          if (!/^\d+(\.\d{1,2})?$/.test(p.promised_amount))
            throw new Error('أدخل المبلغ الموعود بمنزلتين عشريتين كحد أقصى.');
          const [whole, decimal = ''] = p.promised_amount.split('.');
          p.promised_minor = Number(whole) * 100 + Number(decimal.padEnd(2, '0'));
        }
        delete p.promised_amount;
      }
      if (dialogAction === 'cash.request') {
        if (!/^\d+(\.\d{1,2})?$/.test(p.cash_amount)) throw new Error('المبلغ يقبل منزلتين عشريتين فقط.');
        const [whole, decimal = ''] = p.cash_amount.split('.');
        p.amount_minor = Number(whole) * 100 + Number(decimal.padEnd(2, '0'));
        p.collector_id = Number(p.collector_id);
        delete p.cash_amount;
      }
      if (dialogAction === 'collection.create') {
        if (!/^\d+(\.\d{1,2})?$/.test(p.amount)) throw new Error('المبلغ يقبل منزلتين عشريتين فقط.');
        const [whole, decimal = ''] = p.amount.split('.');
        p.amount_minor = Number(whole) * 100 + Number(decimal.padEnd(2, '0'));
        p.order_id = Number(p.order_id);
        delete p.amount;
      }
      const result = await execute(dialogAction, p, currentRequestId);
      $('#action-dialog').close();
      toast(result.message);
      await load(true);
      if (dialogAction === 'visit.finish') window.AmrbitJourney.focusNext(adminContext());
    } catch (err) {
      $('#form-error').textContent = err.message;
    } finally {
      submit.disabled = false;
    }
  });
  $('#close-dialog').onclick = $('#cancel-dialog').onclick = () => $('#action-dialog').close();
  document.addEventListener('click', (ev) => {
    const stopButton = ev.target.closest('[data-stop-move], [data-stop-remove]');
    if (stopButton) {
      const id = Number(stopButton.dataset.stopMove || stopButton.dataset.stopRemove),
        index = tripStops.indexOf(id);
      if (index < 0) return;
      if (stopButton.dataset.stopRemove) {
        tripStops.splice(index, 1);
        const checkbox = document.querySelector(`#action-form [name="customer_ids"][value="${id}"]`);
        if (checkbox) checkbox.checked = false;
      } else {
        const next = index + Number(stopButton.dataset.direction);
        if (next >= 0 && next < tripStops.length)
          [tripStops[index], tripStops[next]] = [tripStops[next], tripStops[index]];
      }
      renderTripStopOrder();
      return;
    }
    const tripFile = ev.target.closest('[data-trip-file], [data-trip-edit]');
    if (tripFile) {
      showTripFile(tripFile.dataset.tripFile || tripFile.dataset.tripEdit, !!tripFile.dataset.tripEdit);
      return;
    }
    const b = ev.target.closest('[data-action]');
    if (b) {
      if (b.dataset.action === 'trip.cancel') $('#record-dialog').close();
      action(b.dataset.action, b.dataset.id);
      return;
    }
    const row = ev.target.closest('[data-trip]');
    if (row) {
      location.href = '/tracking?trip=' + row.dataset.trip;
      return;
    }
    const t = ev.target.closest('[data-tab]');
    if (t) {
      tab = t.dataset.tab;
      render();
    }
    if (ev.target.closest('[data-clear]')) {
      statusFilter = '';
      dateFilter = '';
      render();
    }
    const exportBtn = ev.target.closest('[data-export]');
    if (exportBtn) location.href = '/reports/export?type=' + exportBtn.dataset.export + '&' + reportQuery();
  });
  document.addEventListener('change', (ev) => {
    if (['trip.create', 'trip.update'].includes(dialogAction) && ev.target.name === 'representative_id')
      updateTripCoverage();
    if (ev.target.name === 'customer_ids') {
      const id = Number(ev.target.value);
      tripStops = tripStops.filter((x) => x !== id);
      if (ev.target.checked) tripStops.push(id);
      renderTripStopOrder();
    }
    if (ev.target.id === 'status-filter') {
      statusFilter = ev.target.value;
      render();
    }
    if (ev.target.id === 'date-filter') {
      dateFilter = ev.target.value;
      render();
    }
    if (ev.target.id === 'trip-filter') {
      selectedTrip = Number(ev.target.value);
      mapCenter = null;
      mapZoom = null;
      render();
    }
  });
  document.addEventListener('input', (ev) => {
    if (ev.target.id === 'trip-customer-search') updateTripCoverage();
  });
  document.addEventListener('input', (ev) => {
    if (ev.target.id === 'playback-range') {
      const p = data.points.filter((p) => p.trip_id === Number(selectedTrip))[Number(ev.target.value)];
      if (p && playMarker) {
        playMarker.setLatLng([Number(p.latitude), Number(p.longitude)]);
        $('#playback-time').textContent = when(p.captured_at) + ' • دقة ' + p.accuracy_m + ' م';
      }
    }
  });
  let searchTimer;
  $('#global-search').oninput = (ev) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      query = ev.target.value;
      render();
    }, 180);
  };
  $('#refresh').onclick = () => (section === 'reports' ? fetchReport() : load(true));
  function setMenu(open) {
    const mobile = window.matchMedia('(max-width:760px)').matches;
    $('#sidebar').classList.toggle('open', open);
    $('#sidebar').inert = !mobile || !open;
    $('.main').inert = mobile && open;
    $('#sidebar').setAttribute('aria-hidden', String(!mobile || !open));
    $('#menu-toggle').setAttribute('aria-expanded', String(open));
    $('#menu-backdrop').hidden = !mobile || !open;
  }
  $('#menu-toggle').onclick = () => setMenu(!$('#sidebar').classList.contains('open'));
  $('#menu-close').onclick = $('#menu-backdrop').onclick = () => {
    setMenu(false);
    $('#menu-toggle').focus();
  };
  window.addEventListener('resize', () => setMenu(false));
  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape') setMenu(false);
  });
  setMenu(false);
  const title = titles[section];
  $('#page-title').textContent = $('#breadcrumb-title').textContent = title[0];
  $('#page-description').textContent = title[1];
  $('#section-code').textContent = title[2];
  selectedTrip = new URLSearchParams(location.search).get('trip');
  const mainActions = {
    dashboard: manager ? ['trip.create', 'رحلة جديدة'] : null,
    trips: manager ? ['trip.create', 'رحلة جديدة'] : null,
    customers: role !== 'viewer' ? ['customer.create', manager ? 'عميل جديد' : 'تسجيل عميل جديد'] : null,
    vehicles: manager ? ['vehicle.create', 'إضافة مركبة'] : null,
    team: role === 'manager' ? ['member.create', 'إضافة مندوب / عضو'] : null,
    inventory: manager || role === 'warehouse_keeper' ? ['stock.transfer', 'إرسال حمولة للاستلام'] : null,
    orders: role === 'manager' || rep ? ['order.create', 'طلب جديد'] : null,
    collections: canCollect ? ['collection.create', 'استلام مبلغ'] : null,
    settlements: canCollect ? ['cash.request', 'طلب تسليم نقد'] : null,
    receivables: canCollect ? ['collection.create', 'استلام سداد'] : null,
  };
  if (mainActions[section]) {
    const b = $('#main-action');
    b.hidden = false;
    b.disabled = true;
    b.dataset.action = mainActions[section][0];
    b.querySelector('span').textContent = mainActions[section][1];
  }

  document.addEventListener('click', (ev) => {
    const adminTabButton = ev.target.closest('[data-admin-tab]');
    if (adminTabButton) {
      adminTab = adminTabButton.dataset.adminTab;
      render();
    }
    const recordButton = ev.target.closest('[data-admin-record]');
    if (recordButton) {
      const record = window.FieldFlowAdmin.record(
        recordButton.dataset.adminRecord,
        recordButton.dataset.id,
        adminContext(),
      );
      if (record) {
        $('#record-title').textContent = record.title;
        $('#record-content').innerHTML = '<div class="detail-sheet">' + record.html + '</div>';
        $('#record-dialog').showModal();
      }
    }
    const vehicle = ev.target.closest('[data-vehicle]');
    if (vehicle) {
      selectedVehicle = Number(vehicle.dataset.vehicle);
      const address = new URL(location.href);
      address.searchParams.set('vehicle', String(selectedVehicle));
      history.replaceState(null, '', address);
      mapCenter = null;
      mapZoom = null;
      render();
    }
    const fleetButton = ev.target.closest('[data-fleet-status]');
    if (fleetButton) {
      fleetStatus = fleetButton.dataset.fleetStatus;
      mapCenter = null;
      mapZoom = null;
      render();
    }
    const queueButton = ev.target.closest('[data-queue-status]');
    if (queueButton) {
      queueStatus = queueButton.dataset.queueStatus;
      render();
    }
    const customer = ev.target.closest('[data-customer]');
    if (customer) showCustomer(customer.dataset.customer);
  });
  document.addEventListener('change', (ev) => {
    if (ev.target.id === 'inventory-warehouse') {
      inventoryWarehouse = ev.target.value;
      render();
    }
  });
  $('#close-record').onclick = () => $('#record-dialog').close();
  const launcher = $('#launcher-dialog');
  const resetLauncher = () => {
    $('#launcher-search').value = '';
    document.querySelectorAll('[data-nav-label]').forEach((a) => (a.hidden = false));
    $('#launcher-empty').hidden = true;
  };
  const openLauncher = () => {
    setMenu(false);
    resetLauncher();
    launcher.showModal();
    $('#launcher-search').focus();
  };
  $('#open-launcher').onclick = openLauncher;
  $('#close-launcher').onclick = () => launcher.close();
  $('#launcher-search').oninput = (ev) => {
    let count = 0;
    const value = ev.target.value.trim().toLowerCase();
    document.querySelectorAll('[data-nav-label]').forEach((a) => {
      a.hidden = !a.dataset.navLabel.toLowerCase().includes(value);
      if (!a.hidden) count++;
    });
    $('#launcher-empty').hidden = count > 0;
  };
  $('#launcher-search').onkeydown = (ev) => {
    if (ev.key === 'Enter') {
      const link = document.querySelector('[data-nav-label]:not([hidden])');
      if (link) {
        ev.preventDefault();
        link.click();
      }
    }
  };
  document.addEventListener('keydown', (ev) => {
    if (
      (ev.ctrlKey || ev.metaKey) &&
      ev.key.toLowerCase() === 'k' &&
      !$('#action-dialog').open &&
      !$('#record-dialog').open
    ) {
      ev.preventDefault();
      if (launcher.open) launcher.close();
      else openLauncher();
    }
  });
  $('#global-search').value = query;

  load(true);
  setInterval(() => {
    if (!document.hidden && !document.querySelector('dialog[open]') && section !== 'reports') load();
  }, 15000);
})();
