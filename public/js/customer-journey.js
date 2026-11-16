'use strict';
(() => {
  const e = (v) =>
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
  const days = [
    'الأحد',
    'الإثنين',
    'الثلاثاء',
    'الأربعاء',
    'الخميس',
    'الجمعة',
    'السبت',
  ];
  const reasons = {
    no_answer: 'لم يرد العميل',
    called_no_answer: 'تم الاتصال ولم يرد',
    closed: 'المحل مغلق',
    refused: 'العميل اعتذر أو رفض',
    no_time: 'لم يسعفني الوقت',
    vehicle_issue: 'تعطل المركبة',
    no_sale: 'زيارة دون بيع',
    other: 'سبب آخر',
  };
  const customerId = Number(
    location.pathname.match(/^\/customers\/(\d+)$/)?.[1],
  );
  const isSchedule = location.pathname === '/schedule';
  const params = new URLSearchParams(location.search);
  let profile = null;
  const link = (id, name) =>
    `<a class="record-link" href="/customers/${Number(id)}">${e(name)} ←</a>`;
  const box = (name, content, extra = '') =>
    `<section class="panel journey-panel"><div class="journey-panel-head"><h2>${e(name)}</h2>${extra}</div>${content}</section>`;
  const blank = (text) => `<div class="journey-empty">${e(text)}</div>`;
  const input = (name, label, type, value = '', attrs = '') =>
    `<div class="field"><label for="j-${name}">${e(label)}</label><input id="j-${name}" name="${name}" type="${type}" value="${e(value)}" ${attrs}></div>`;
  const select = (name, label, html) =>
    `<div class="field"><label for="j-${name}">${e(label)}</label><select id="j-${name}" name="${name}" required>${html}</select></div>`;
  const notes = (name, label, value = '', required = false, max = 1000) =>
    `<div class="field"><label for="j-${name}">${e(label)}</label><textarea id="j-${name}" name="${name}" maxlength="${max}" ${required ? 'required minlength="3"' : ''}>${e(value)}</textarea></div>`;
  function datePlus(date, count) {
    const d = new Date(date + 'T12:00:00Z');
    d.setUTCDate(d.getUTCDate() + count);
    return d.toISOString().slice(0, 10);
  }
  const weekday = (date) => new Date(date + 'T12:00:00Z').getUTCDay();
  function selectedDay(today) {
    const requested = params.get('date');
    return requested &&
      /^\d{4}-\d{2}-\d{2}$/.test(requested) &&
      !Number.isNaN(Date.parse(requested + 'T12:00:00Z')) &&
      new Date(requested + 'T12:00:00Z').toISOString().slice(0, 10) ===
        requested
      ? requested
      : today;
  }
  const currentRep = (c) =>
    Number(params.get('rep')) || c.data.representatives[0]?.id;
  function orderCard(o, c) {
    return `<article class="purchase-card"><header><a href="/documents/orders/${o.id}" target="_blank" rel="noopener" class="record-link">${e(o.code)} ↗</a>${c.pill(o.status)}<span>${c.when(o.created_at)}</span></header><div class="purchase-items">${(o.items || []).map((i) => `<div><strong>${e(i.product?.name || 'صنف سابق')}</strong><span>${e(i.quantity)} ${e(i.product?.unit || 'وحدة')}</span><b>${c.amount(i.total_minor)}</b></div>`).join('')}</div><footer><span>الإجمالي <b>${c.amount(o.total_minor)}</b></span><span>المستلم <b>${c.amount(o.paid_minor)}</b></span><span>المتبقي <b>${c.amount(o.status === 'fulfilled' ? o.total_minor - o.paid_minor : 0)}</b></span>${o.status === 'draft' ? c.btn('order.fulfill', o.id, 'تأكيد التسليم', 'primary-btn') : ''}</footer></article>`;
  }
  function visitCard(v, c) {
    return `<article class="history-visit"><div class="history-marker ${e(v.status)}">${v.location_verified ? '✓' : '•'}</div><div><strong>${c.day(v.scheduled_for)}</strong><small>${e(v.representative?.name || 'المندوب')} • محطة ${v.sequence}</small>${v.reason_code ? `<span class="reason-label">${e(reasons[v.reason_code] || v.reason_code)}</span>` : ''}<p>${e(v.outcome_note || 'لا توجد ملاحظة مسجلة.')}</p><small>${v.location_verified ? `حضور موثّق • ${e(v.distance_m)} م • دقة ${e(v.accuracy_m)} م` : 'لم يُسجل إثبات حضور'}</small></div>${c.pill(v.status)}</article>`;
  }
  function customerPage(c) {
    if (!profile) return blank('جارٍ تحميل ملف العميل…');
    const p = profile,
      u = p.customer,
      s = p.summary,
      tab = params.get('tab') || 'overview';
    const tabs = [
      ['overview', 'نظرة شاملة'],
      ...(p.financial
        ? [
            ['orders', `الطلبات (${s.orders})`],
            ['ledger', 'كشف الحركة'],
            ['collections', 'التحصيل'],
            ['followups', 'متابعة الآجل'],
          ]
        : []),
      ['visits', `الزيارات (${s.visits + s.cancelled_visits})`],
    ];
    let content = '';
    if (tab === 'overview') {
      content = `<div class="customer-columns"><div>${box(
        'ماذا يطلب العميل؟',
        p.products.length
          ? c.table(
              ['المنتج', 'الوحدات المسلّمة', 'عدد الفواتير', 'قيمة المبيعات'],
              p.products.map((i) =>
                c.tr([
                  e(i.product?.name),
                  `${e(i.quantity)} ${e(i.product?.unit)}`,
                  e(i.orders_count),
                  c.amount(i.total_minor),
                ]),
              ),
            )
          : blank('لا توجد أصناف مسلّمة في نطاق صلاحيتك.'),
      )}${box('آخر الطلبات', p.recent_orders.map((o) => orderCard(o, c)).join('') || blank('لا توجد طلبات مسجلة.'), `<a href="?tab=orders">كل الطلبات ←</a>`)}</div><div>${box('بيانات الخدمة', `<dl class="journey-facts"><div><dt>مسؤول العميل</dt><dd>${e(u.representative?.name || 'غير مسند — يحتاج مراجعة')}</dd></div><div><dt>المنطقة</dt><dd>${e(u.area?.city)} • ${e(u.area?.name)}</dd></div><div><dt>أيام الزيارة الأسبوعية</dt><dd>${p.weekly_days.map((x) => days[x]).join('، ') || 'لم يحددها المشرف بعد'}</dd></div><div><dt>آخر زيارة مكتملة</dt><dd>${c.when(s.last_visit)}</dd></div><div><dt>آخر شراء مسلّم</dt><dd>${c.when(s.last_order)}</dd></div><div><dt>نطاق إثبات الحضور</dt><dd>${e(u.geofence_radius_m)} متر</dd></div><div><dt>الحد الائتماني</dt><dd>${u.credit_limit_minor === null ? 'غير محدد' : c.amount(u.credit_limit_minor)}</dd></div></dl><div class="service-note"><strong>تعليمات خدمة العميل</strong><p>${e(u.service_notes || 'لم تُسجل تعليمات خاصة.')}</p></div>`)}${box('سجل الزيارات الأخير', p.recent_visits.map((v) => visitCard(v, c)).join('') || blank('لا توجد زيارات مسجلة.'), '<a href="?tab=visits">السجل الكامل ←</a>')}</div></div>`;
    } else {
      const h = p.history,
        rows = h?.data || [];
      if (tab === 'orders')
        content =
          rows.map((o) => orderCard(o, c)).join('') || blank('لا توجد طلبات.');
      if (tab === 'visits')
        content = box(
          'نتائج الزيارات وإثبات الحضور',
          rows.map((v) => visitCard(v, c)).join('') || blank('لا توجد زيارات.'),
        );
      if (tab === 'collections')
        content = c.table(
          ['الإيصال', 'الفاتورة', 'المستلم', 'الطريقة', 'المبلغ', 'التاريخ'],
          rows.map((r) =>
            c.tr([
              e(r.receipt),
              e(r.order?.code),
              e(r.actor?.name),
              e(
                { cash: 'نقدي', bank: 'تحويل', card: 'بطاقة' }[r.method] ||
                  r.method,
              ),
              c.amount(r.amount_minor),
              c.when(r.collected_at),
            ]),
          ),
        );
      if (tab === 'ledger')
        content = box(
          'كشف حركة العميل',
          c.table(
            ['الحركة', 'المرجع', 'التاريخ', 'مدين — فاتورة', 'دائن — سداد'],
            rows.map((r) =>
              c.tr([
                r.kind === 'invoice' ? 'فاتورة مسلّمة' : 'إيصال تحصيل',
                e(r.reference),
                c.when(r.occurred_at),
                c.amount(r.debit_minor),
                c.amount(r.credit_minor),
              ]),
            ),
          ) +
            '<p class="admin-note">رصيد العميل أعلاه يشمل كامل السجل؛ الصفحات هنا تعرض الحركات بترتيب الأحدث. المسودات لا تدخل المديونية.</p>',
        );
      if (tab === 'followups')
        content = c.table(
          [
            'الفاتورة',
            'النتيجة',
            'الملاحظة',
            'المتابعة القادمة',
            'المبلغ الموعود',
            'المتابع',
          ],
          rows.map((r) =>
            c.tr([
              e(r.order?.code),
              e(
                {
                  promised: 'وعد بالسداد',
                  no_answer: 'لم يرد',
                  disputed: 'اعتراض',
                  contacted: 'تم التواصل',
                }[r.outcome] || r.outcome,
              ),
              e(r.note),
              c.day(r.next_followup_date),
              c.amount(r.promised_minor),
              e(r.actor?.name),
            ]),
          ),
        );
      if (h && h.last_page > 1)
        content += `<nav class="journey-pagination" aria-label="صفحات السجل">${h.current_page > 1 ? `<a class="secondary-btn" href="?tab=${tab}&page=${h.current_page - 1}">السابق</a>` : ''}<span>صفحة ${h.current_page} من ${h.last_page} • ${h.total} سجل</span>${h.current_page < h.last_page ? `<a class="secondary-btn" href="?tab=${tab}&page=${h.current_page + 1}">التالي</a>` : ''}</nav>`;
    }
    return `<a class="journey-back" href="/customers">العملاء / ملف العميل</a><section class="customer-cover"><div class="customer-monogram">${e(u.name.slice(0, 1))}</div><div class="customer-identity"><span class="eyebrow">CUSTOMER 360 • ${e(u.code)}</span><h2>${e(u.name)}</h2><p>${e(u.address)}</p><div class="contact-chips">${u.phone ? `<a href="tel:${e(u.phone)}" dir="ltr">${e(u.phone)}</a>` : '<span>الهاتف غير مسجل</span>'}<span>${e(u.representative?.name || 'مسؤول غير محدد')}</span><span>${u.is_active ? 'عميل نشط' : 'عميل موقوف'}</span></div></div><div class="customer-cover-actions"><a class="secondary-btn" href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(u.latitude + ',' + u.longitude)}" target="_blank" rel="noopener">موقع العميل ↗</a>${window.AmrbitMaintenance.customerActions(u, c)}${c.manager ? c.btn('customer.update', u.id, 'تعديل بيانات العميل', 'primary-btn') : ''}</div></section><section class="metrics">${c.metric('إجمالي المبيعات', s.sales_minor === null ? '—' : c.amount(s.sales_minor), '', 'طلبات مسلّمة فقط', 'bag')}${c.metric('إجمالي السداد', s.paid_minor === null ? '—' : c.amount(s.paid_minor), '', 'إيصالات معتمدة', 'wallet')}${c.metric('مديونية العميل', s.balance_minor === null ? '—' : c.amount(s.balance_minor), '', s.overdue_minor ? `متأخر ${(s.overdue_minor / 100).toFixed(2)} ر.س` : 'ضمن آجال السداد', 'clock')}${c.metric('الزيارات المكتملة', s.completed, 'زيارة', `${s.visits} غير ملغاة • ${s.cancelled_visits} ملغاة`, 'check')}</section><p class="scope-note">${e(p.financial_scope)}</p><nav class="journey-tabs" aria-label="تفاصيل العميل">${tabs.map(([key, label]) => `<a href="?tab=${key}" class="${tab === key ? 'selected' : ''}" ${tab === key ? 'aria-current="page"' : ''}>${e(label)}</a>`).join('')}</nav>${content}`;
  }
  function customers(c) {
    const rows = c.data.customers.filter(c.match),
      all = c.data.customers;
    return `${window.AmrbitMaintenance.requests(c)}<section class="journey-intro"><div><span class="eyebrow">CUSTOMER RELATIONSHIPS</span><h2>${c.role === 'representative' ? 'عملاؤك، بكل التفاصيل.' : 'كل عميل له مسؤول. وكل حركة لها سجل.'}</h2><p>افتح ملف العميل لمراجعة طلباته وأصنافه وزياراته ومديونيته، ثم تابع الخدمة من نفس المكان.</p></div>${c.manager || c.role === 'representative' ? `<a href="/schedule" class="secondary-btn">${c.manager ? 'توزيع الأسبوع' : 'جدول زياراتي'} ←</a>` : ''}</section><section class="metrics">${c.metric('العملاء المسندون', all.filter((x) => x.representative_id).length, 'عميل', 'مسؤول واضح لكل عميل', 'users')}${c.metric('عملاء نشطون', all.filter((x) => x.is_active).length, 'عميل', 'جاهزون للخدمة والتخطيط', 'check')}${c.metric('يحتاجون إسنادًا', all.filter((x) => !x.representative_id).length, 'عميل', 'راجع المسؤول قبل الجدولة', 'pin')}${c.metric('عملاء عليهم آجل', c.data.debt_customers.length, 'عميل', 'بحسب الفواتير المسموح لك بها', 'wallet')}</section><div class="filters"><span>${rows.length} عميل معروض • الدليل حتى 500 عميل</span>${c.manager ? c.btn('area.create', '', 'إضافة منطقة', 'secondary-btn') : ''}</div>${c.table(
      [
        'العميل',
        'المندوب المسؤول',
        'المنطقة والتواصل',
        'المديونية',
        'الحالة',
        'ملف العميل',
      ],
      rows.map((u) =>
        c.tr([
          `<strong>${link(u.id, u.name)}</strong><small>${e(u.code)}</small>`,
          e(u.representative?.name || 'يحتاج إسنادًا'),
          `${e(u.area?.name || '—')}<small dir="ltr">${e('phone' in u ? u.phone || 'الهاتف غير مسجل' : 'التواصل من ملف العميل')}</small>`,
          c.canMoney
            ? c.amount(
                c.data.debt_customers.find((x) => x.customer_id === u.id)
                  ?.balance_minor || 0,
              )
            : '—',
          c.pill(
            u.is_active ? 'active' : 'closed',
            u.is_active ? 'نشط' : 'موقوف',
          ),
          `<a href="/customers/${u.id}" class="secondary-btn">فتح الملف ←</a>`,
        ]),
      ),
      'اسم العميل يفتح ملفه الكامل. المندوب يرى العملاء المسندين له فقط.',
    )}`;
  }
  function repMoney(c) {
    return `<div class="journey-panel-head"><h2>ملخص حسابي الحالي</h2><a href="/receivables">فواتيري والآجل ←</a></div><section class="metrics">${c.metric('المبيعات المسلّمة', c.amount(c.data.metrics.sales_minor), '', 'كامل سجل فواتيري', 'bag')}${c.metric('الآجل المتبقي', c.amount(c.data.metrics.unpaid_minor), '', 'فواتيري المسلّمة غير المسددة', 'wallet')}${c.metric('الآجل المتأخر', c.amount(c.data.metrics.overdue_minor), '', 'بعد موعد الاستحقاق', 'clock')}${c.metric('عملائي', c.data.customers.length, 'عميل', 'ملفات العملاء المسندين لي', 'users')}</section>`;
  }
  function published(c, repId) {
    const trips = c.data.trips
      .filter((t) => t.representative_id === repId && t.weekly_plan_id)
      .sort((a, b) =>
        String(b.scheduled_for).localeCompare(String(a.scheduled_for)),
      );
    if (!trips.length) return '';
    return box(
      'الرحلات المنشورة من الجدول',
      c.table(
        ['الرحلة', 'التاريخ', 'الحالة', 'المحطات', 'فتح الرحلة'],
        trips.map((t) =>
          c.tr([
            e(t.code),
            c.day(t.scheduled_for),
            c.pill(t.status),
            `${t.completed_stops_count} مكتملة / ${t.stops_count}`,
            c.role === 'representative'
              ? `<a class="secondary-btn" href="/dashboard?date=${String(t.scheduled_for).slice(0, 10)}">يوم العمل ←</a>`
              : `<button class="secondary-btn" data-trip-file="${t.id}">ملف التنفيذ ←</button>`,
          ]),
        ),
      ),
    );
  }
  function schedule(c) {
    const repId = currentRep(c),
      plans = c.data.weekly_plans.filter((p) => p.representative_id === repId),
      customers = c.data.customers.filter(
        (x) => x.representative_id === repId && x.is_active,
      );
    const total = plans
      .filter((p) => p.is_active)
      .reduce((s, p) => s + p.customer_ids.length, 0);
    return `<section class="journey-intro"><div><span class="eyebrow">WEEKLY FIELD PLANNER</span><h2>${c.manager ? 'أسبوع مرتب، وميدان أوضح.' : 'أسبوعك في الميدان.'}</h2><p>قالب أسبوعي متكرر. ينشر المشرف الرحلة بتاريخها، ثم ينفذها المندوب محطةً بمحطة.</p></div><a class="secondary-btn" href="/trips">سجل الرحلات ←</a></section><div class="schedule-toolbar">${c.manager ? `<label for="schedule-rep">جدول المندوب</label><select id="schedule-rep">${c.data.representatives.map((r) => `<option value="${r.id}" ${repId === r.id ? 'selected' : ''}>${e(r.name)}</option>`).join('')}</select>` : `<strong>${e(c.data.user.name)}</strong>`}<span>${customers.length} عميل نشط • ${total} محطة خلال الأسبوع</span></div>${published(c, repId)}<div class="week-plan-grid">${[
      6, 0, 1, 2, 3, 4, 5,
    ]
      .map((d) => {
        const p = plans.find((x) => x.weekday === d),
          active = p?.is_active;
        let next = datePlus(c.data.today, (d - weekday(c.data.today) + 7) % 7);
        return `<article class="week-plan-card ${weekday(c.data.today) === d ? 'is-today' : ''}"><header><span>${days[d]}</span>${weekday(c.data.today) === d ? '<b>اليوم</b>' : ''}</header><div class="week-plan-number">${active ? p.customer_ids.length : '—'}<small>عميل</small></div><p>${p ? e(p.vehicle?.name) : 'لم توزّع رحلة هذا اليوم بعد'}</p>${p ? `<small class="origin-label">الانطلاق: ${e(p.origin_label)}</small>` : ''}<ul>${(active
          ? p.customer_ids
          : []
        )
          .map((id) => {
            const u = c.data.customers.find((x) => x.id === id);
            return `<li>${u ? link(id, u.name) : '<span>عميل يحتاج مراجعة الإسناد</span>'}</li>`;
          })
          .join(
            '',
          )}</ul><footer>${p && !active ? '<span class="pill neutral">يوم غير نشط</span>' : ''}${c.manager ? c.btn('weekly.save', d, p ? 'تعديل اليوم' : 'توزيع العملاء', 'secondary-btn') : ''}${c.manager && active ? c.btn('weekly.publish', p.id, 'نشر رحلة بتاريخ', 'primary-btn') : ''}${!c.manager && active ? `<a href="/dashboard?date=${next}" class="secondary-btn">فتح رحلة اليوم المحدد ←</a>` : ''}</footer></article>`;
      })
      .join(
        '',
      )}</div><div class="info-box"><strong>كيف يتحول الجدول إلى رحلة؟</strong><p>حدد العملاء ونقطة الانطلاق مرة في قالب اليوم، ثم انشر الرحلة بالتاريخ المطلوب. النشر يرتب الأقرب فالأقرب بالمسافة المباشرة، ويحفظ نسخة من الخطة. تعديل القالب لاحقًا لا يغير رحلة سبق نشرها. لا يوجد نشر تلقائي خفي.</p></div>`;
  }
  function repDashboard(c) {
    const date = selectedDay(c.data.today),
      today = c.data.today;
    const start = datePlus(date, -((weekday(date) + 1) % 7));
    const trips = c.data.trips.filter(
      (t) =>
        String(t.scheduled_for).slice(0, 10) === date &&
        t.status !== 'cancelled',
    );
    const prior = c.data.trips.find(
      (t) =>
        t.status === 'active' && String(t.scheduled_for).slice(0, 10) !== date,
    );
    let html = `<section class="journey-intro"><div><span class="eyebrow">YOUR FIELD DAY</span><h2>هلا ${e(c.data.user.name.split(' ')[0])}، خلّنا نرتب يومك.</h2><p>رحلتك، عملاؤك، وما أنجزته اليوم — في مكان واحد.</p></div><a class="secondary-btn" href="/customers">عملائي ←</a></section><div class="day-navigation"><a href="?date=${datePlus(date, -7)}" aria-label="الأسبوع السابق">→</a>${Array.from(
      { length: 7 },
      (_, i) => {
        const d = datePlus(start, i);
        return `<a href="?date=${d}" class="${d === date ? 'selected' : ''}" ${d === date ? 'aria-current="date"' : ''}><span>${days[weekday(d)]}</span><b>${Number(d.slice(-2))}</b><small>${d === today ? 'اليوم' : d.slice(5, 7) + ' / ' + d.slice(0, 4)}</small></a>`;
      },
    ).join(
      '',
    )}<a href="?date=${datePlus(date, 7)}" aria-label="الأسبوع التالي">←</a></div>${prior ? `<div class="info-box warning">عندك رحلة نشطة بتاريخ سابق. عالج كل محطة متبقية أو سجل سبب تعذرها ثم أغلقها. <a href="?date=${String(prior.scheduled_for).slice(0, 10)}">فتح الرحلة ${e(prior.code)} ←</a></div>` : ''}`;
    if (!trips.length)
      return (
        html +
        box(
          'رحلات ' + days[weekday(date)],
          blank(
            'لم ينشر المشرف رحلة لهذا التاريخ بعد. العملاء المسندون لك موجودون في صفحة «عملائي».',
          ),
          '<a href="/schedule">جدولي الأسبوعي ←</a>',
        ) +
        repMoney(c)
      );
    for (const t of trips) {
      const stops = c.data.visits
          .filter((v) => v.trip_id === t.id)
          .sort((a, b) => a.sequence - b.sequence || a.id - b.id),
        next = stops.find((v) => ['planned', 'in_progress'].includes(v.status)),
        done = stops.filter(
          (v) => !['planned', 'in_progress'].includes(v.status),
        ).length;
      const route =
        typeof t.route_snapshot === 'string'
          ? JSON.parse(t.route_snapshot)
          : t.route_snapshot;
      html += `<section class="day-trip-summary"><div><span class="eyebrow">${e(t.code)} • ${e(t.vehicle?.name)}</span><h2>${days[weekday(date)]}، ${c.day(date)}</h2><p>${e(t.planning_notes || 'تعليمات المشرف تظهر هنا عند إضافتها.')}</p>${route ? `<small>من ${e(route.origin_label)} • ترتيب الأقرب بمسافة مباشرة تقريبية ${(route.distance_m / 1000).toFixed(1)} كم</small>` : ''}</div><div class="day-progress"><strong>${done}<small> / ${stops.length}</small></strong><span>محطات حُسمت نتيجتها</span><progress max="${Math.max(stops.length, 1)}" value="${done}"></progress><small>${stops.filter((v) => v.status === 'completed').length} مكتملة • ${stops.filter((v) => ['failed', 'skipped'].includes(v.status)).length} متعذرة</small></div><div>${t.status === 'planned' && date === today ? c.btn('trip.start', t.id, 'بدء رحلة اليوم', 'primary-btn') : t.status === 'active' && !next ? c.btn('trip.close', t.id, 'إنهاء الرحلة بعد مراجعتها', 'primary-btn') : c.pill(t.status)}${t.status === 'active' ? c.btn('location.record', t.id, 'إرسال موقع الرحلة', 'secondary-btn') : ''}</div></section>`;
      html += `<div class="rep-day-layout"><section class="stop-timeline">${stops
        .map((v) => {
          const active = next?.id === v.id && t.status === 'active',
            orders = c.data.orders.filter(
              (o) => o.customer_id === v.customer_id && o.trip_id === t.id,
            ),
            fulfilled = orders.filter((o) => o.status === 'fulfilled');
          return `<article class="journey-stop ${active ? 'current-stop' : ''} ${!['planned', 'in_progress'].includes(v.status) ? 'resolved-stop' : ''}" id="rep-stop-${v.id}" tabindex="-1"><div class="stop-sequence">${v.sequence}</div><div class="stop-main"><header><div>${active ? '<span class="current-label">محطتك الحالية</span>' : ''}<h3>${link(v.customer_id, v.customer.name)}</h3></div>${c.pill(v.status)}</header><p>${e(v.customer.address)}</p><div class="stop-contact"><span>${e(v.customer.phone || 'الهاتف غير مسجل')}</span><a href="https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(v.customer.latitude + ',' + v.customer.longitude)}" target="_blank" rel="noopener">الانتقال إلى العميل ↗</a>${v.customer.phone ? `<a href="tel:${e(v.customer.phone)}">اتصال</a>` : ''}</div>${v.customer.service_notes ? `<p class="service-note">${e(v.customer.service_notes)}</p>` : ''}${
            fulfilled.length
              ? `<div class="delivery-summary"><strong>الكميات المسلّمة فعليًا</strong>${fulfilled
                  .flatMap((o) => o.items || [])
                  .map(
                    (i) =>
                      `<span>${e(i.product?.name)}: <b>${i.quantity} ${e(i.product?.unit)}</b></span>`,
                  )
                  .join('')}</div>`
              : ''
          }${orders
            .filter((o) => o.status === 'draft')
            .map((o) => orderCard(o, c))
            .join(
              '',
            )}${v.outcome_note ? `<p class="outcome-text">${e(reasons[v.reason_code] || 'نتيجة الزيارة')}: ${e(v.outcome_note)}</p>` : ''}<div class="row-actions">${active ? (v.status === 'planned' ? c.btn('visit.checkin', v.id, 'وصلت — إثبات الحضور', 'primary-btn') + c.btn('visit.skip', v.id, 'تعذر الوصول') : c.btn('visit.sale', v.id, 'تسجيل أصناف وتسليم', 'secondary-btn') + c.btn('visit.complete', v.id, 'حفظ النتيجة والعميل التالي', 'primary-btn') + c.btn('visit.fail', v.id, 'تعذر إتمام الزيارة')) : ''}${!active && v.status === 'planned' ? '<small>تفتح بعد بدء الرحلة وحسم المحطات السابقة.</small>' : ''}</div></div></article>`;
        })
        .join(
          '',
        )}</section><aside>${box('خطوات الزيارة', '<ol class="visit-guide"><li><b>وصلت إلى العميل؟</b><span>أثبت الحضور بموقع حديث داخل نطاق العميل.</span></li><li><b>ماذا سلّمت؟</b><span>سجل الأصناف والكميات ثم أكّد تسليم الطلب من عهدة السيارة.</span></li><li><b>نقدي أم آجل؟</b><span>طريقة السداد تنشئ التحصيل أو المديونية عند التسليم.</span></li><li><b>احفظ النتيجة</b><span>اكتب التفاصيل، أو اختر سبب التعذر. بعدها تُفتح المحطة التالية.</span></li></ol>')}<div class="info-box"><strong>إثبات حقيقي للموقع</strong><p>نحتاج إذن الموقع ودقة مناسبة. على جوال متصل بالشبكة يحتاج الرابط HTTPS موثوقًا حتى يسمح المتصفح بالموقع.</p></div></aside></div>`;
    }
    return html + repMoney(c);
  }
  function form(action, id, c) {
    if (action === 'customer.update') {
      const u = profile?.customer;
      if (!u || u.id !== Number(id)) return null;
      return {
        title: 'تعديل ملف العميل ومسؤوله',
        extra: { id: u.id, profile_version: u.profile_version },
        html:
          input(
            'name',
            'اسم العميل',
            'text',
            u.name,
            'required maxlength="150"',
          ) +
          input('phone', 'رقم التواصل', 'tel', u.phone, 'maxlength="30"') +
          select(
            'area_id',
            'المنطقة',
            c.data.areas
              .map(
                (a) =>
                  `<option value="${a.id}" ${a.id === u.area_id ? 'selected' : ''}>${e(a.name)}</option>`,
              )
              .join(''),
          ) +
          select(
            'representative_id',
            'المندوب المسؤول',
            `<option value="">اختر المندوب</option>` +
              c.data.representatives
                .map(
                  (r) =>
                    `<option value="${r.id}" ${r.id === u.representative_id ? 'selected' : ''}>${e(r.name)}</option>`,
                )
                .join(''),
          ) +
          input(
            'address',
            'العنوان',
            'text',
            u.address,
            'required maxlength="500"',
          ) +
          `<div class="field-grid">${input('latitude', 'خط العرض', 'number', u.latitude, 'required step="any" min="-90" max="90"')}${input('longitude', 'خط الطول', 'number', u.longitude, 'required step="any" min="-180" max="180"')}</div>` +
          input(
            'geofence_radius_m',
            'نطاق الحضور بالمتر',
            'number',
            u.geofence_radius_m,
            'required min="25" max="500"',
          ) +
          select(
            'is_active',
            'حالة العميل',
            `<option value="1" ${u.is_active ? 'selected' : ''}>نشط</option><option value="0" ${!u.is_active ? 'selected' : ''}>موقوف</option>`,
          ) +
          notes(
            'service_notes',
            'تعليمات الخدمة والطلب المعتاد',
            u.service_notes,
            false,
            2000,
          ) +
          notes('reason', 'سبب التعديل', '', true, 500) +
          '<div class="info-box">نقل المسؤولية يحفظ كل الحركات السابقة. لا يُسمح بالنقل أثناء وجود محطات مفتوحة أو جدول أسبوعي نشط لهذا العميل.</div>',
      };
    }
    if (action === 'weekly.save') {
      const repId = currentRep(c),
        d = Number(id),
        p = c.data.weekly_plans.find(
          (p) => p.representative_id === repId && p.weekday === d,
        ),
        own = c.data.customers.filter(
          (u) => u.representative_id === repId && u.is_active,
        );
      return {
        title: 'توزيع يوم ' + days[d],
        extra: {
          representative_id: repId,
          weekday: d,
          version: p?.version || 0,
        },
        html:
          select(
            'vehicle_id',
            'سيارة الرحلة',
            c.data.vehicles
              .filter((v) => v.status === 'available')
              .map(
                (v) =>
                  `<option value="${v.id}" ${(p?.vehicle_id || (c.data.representative_vehicle_assignments || []).find((a) => a.representative_id === repId && !a.returned_at)?.vehicle_id) === v.id ? 'selected' : ''}>${e(v.name)} • ${e(v.plate)}</option>`,
              )
              .join(''),
          ) +
          select(
            'is_active',
            'تشغيل هذا اليوم',
            `<option value="1" ${p?.is_active !== false ? 'selected' : ''}>نشط</option><option value="0" ${p?.is_active === false ? 'selected' : ''}>غير نشط</option>`,
          ) +
          input(
            'origin_label',
            'اسم نقطة الانطلاق',
            'text',
            p?.origin_label || '',
            'required maxlength="120" placeholder="مثال: مستودع الشركة"',
          ) +
          `<div class="field-grid">${input('origin_latitude', 'خط عرض الانطلاق', 'number', p?.origin_latitude || '', 'required step="any" min="-90" max="90"')}${input('origin_longitude', 'خط طول الانطلاق', 'number', p?.origin_longitude || '', 'required step="any" min="-180" max="180"')}</div><div class="field"><label>عملاء ${e(c.data.representatives.find((r) => r.id === repId)?.name)}</label><input type="search" id="weekly-customer-search" aria-label="بحث في عملاء اليوم" placeholder="ابحث بالاسم أو الرمز"><div class="weekly-customer-checks">${own.map((u) => `<label class="check-label" data-weekly-search="${e(u.name + ' ' + u.code)}"><input type="checkbox" name="weekly_customer_ids" value="${u.id}" ${p?.customer_ids.includes(u.id) ? 'checked' : ''}> <span>${e(u.name)}<small>${e(u.area?.name)}</small></span></label>`).join('') || blank('لا يوجد عملاء نشطون مسندون للمندوب. أسندهم من ملفات العملاء أولًا.')}</div></div>` +
          notes('notes', 'تعليمات المشرف', p?.notes || '') +
          '<div class="info-box">الترتيب يُحسب عند نشر الرحلة من نقطة الانطلاق التي تدخلها. تكرار العميل في أيام مختلفة مسموح؛ مرة واحدة داخل اليوم. التعديل يخص القالب فقط.</div>',
      };
    }
    if (action === 'weekly.publish') {
      const p = c.data.weekly_plans.find((p) => p.id === Number(id));
      if (!p) return null;
      const date = datePlus(
        c.data.today,
        (p.weekday - weekday(c.data.today) + 7) % 7,
      );
      return {
        title: 'نشر رحلة ' + days[p.weekday],
        extra: { id: p.id, version: p.version },
        html:
          input(
            'date',
            'تاريخ التنفيذ',
            'date',
            date,
            `required min="${c.data.today}" max="${datePlus(c.data.today, 35)}"`,
          ) +
          `<div class="info-box">${p.customer_ids.length} عميل • ${e(p.representative?.name)} • ${e(p.vehicle?.name)}<p>ستنشأ رحلة بتاريخ مستقل، بمحطات مرتبة من ${e(p.origin_label)}. إعادة النشر لن تنشئ نسخة مكررة.</p></div>`,
      };
    }
    if (['visit.complete', 'visit.skip', 'visit.fail'].includes(action)) {
      const v = c.data.visits.find((v) => v.id === Number(id));
      if (!v) return null;
      const complete = action === 'visit.complete',
        delivered = c.data.orders.filter(
          (o) =>
            o.trip_id === v.trip_id &&
            o.customer_id === v.customer_id &&
            o.status === 'fulfilled',
        );
      const allowed = complete
        ? ['no_sale', 'other']
        : [
            'no_answer',
            'called_no_answer',
            'closed',
            'refused',
            'no_time',
            'vehicle_issue',
            'other',
          ];
      return {
        title: complete
          ? 'نتيجة الزيارة • ' + v.customer.name
          : 'سبب تعذر زيارة ' + v.customer.name,
        action: 'visit.finish',
        extra: {
          id: v.id,
          status: complete
            ? 'completed'
            : action === 'visit.skip'
              ? 'skipped'
              : 'failed',
        },
        html:
          (complete
            ? `<div class="info-box">${delivered.length ? `تم تسليم ${delivered.length} طلب. الكميات محفوظة من أصناف الطلبات؛ لن تُخصم من المخزون مرة ثانية.` : 'لم يسجّل تسليم أصناف. اختر سبب الزيارة دون بيع واكتب التفاصيل.'}</div>`
            : '') +
          `<div class="field"><label for="j-reason_code">${complete ? 'نوع النتيجة عند عدم البيع' : 'سبب التعذر'}</label><select name="reason_code" id="j-reason_code" ${!complete || !delivered.length ? 'required' : ''}><option value="">${complete && delivered.length ? 'تم التسليم حسب الطلبات' : 'اختر السبب'}</option>${allowed.map((k) => `<option value="${k}">${e(reasons[k])}</option>`).join('')}</select></div>` +
          notes(
            'note',
            'تفاصيل الزيارة وما تم مع العميل',
            '',
            !complete || !delivered.length,
          ),
      };
    }
    return null;
  }
  window.AmrbitJourney = {
    customer: () => profile?.customer,
    customerId,
    isSchedule,
    customers,
    schedule,
    repDashboard,
    customerPage,
    form,
    endpoint: (action) =>
      ['customer.update', 'weekly.save', 'weekly.publish'].includes(action)
        ? '/ops/journey/'
        : '/ops/action/',
    async hydrate(data) {
      if (
        document.body.dataset.role === 'representative' &&
        location.pathname === '/dashboard'
      ) {
        const response = await fetch(
          '/ops/my-day?date=' + encodeURIComponent(selectedDay(data.today)),
          { headers: { Accept: 'application/json' } },
        );
        const result = await response.json();
        if (!response.ok)
          throw new Error(result.message || 'تعذر تحميل رحلة اليوم.');
        for (const key of ['trips', 'visits', 'orders'])
          data[key] = [
            ...new Map(
              [...data[key], ...result[key]].map((row) => [row.id, row]),
            ).values(),
          ];
        data.trips = [
          ...new Map(
            [...data.trips, ...result.open_trips].map((row) => [row.id, row]),
          ).values(),
        ];
      }
      if (!customerId) return;
      const response = await fetch(
        `/ops/customers/${customerId}?tab=${encodeURIComponent(params.get('tab') || 'overview')}&page=${encodeURIComponent(params.get('page') || 1)}`,
        { headers: { Accept: 'application/json' } },
      );
      const result = await response.json();
      if (!response.ok)
        throw new Error(result.message || 'تعذر فتح ملف العميل.');
      profile = result;
    },
    signature: () => profile,
    payload(action, f, p) {
      if (action === 'weekly.save') {
        p.customer_ids = f.getAll('weekly_customer_ids').map(Number);
        delete p.weekly_customer_ids;
        p.is_active = Boolean(Number(p.is_active));
      }
      if (action === 'customer.update')
        p.is_active = Boolean(Number(p.is_active));
    },
    focusNext(c) {
      const next = document.querySelector('.current-stop');
      if (next) {
        next.focus({ preventScroll: true });
        next.scrollIntoView({
          behavior: matchMedia('(prefers-reduced-motion: reduce)').matches
            ? 'instant'
            : 'smooth',
          block: 'center',
        });
      }
    },
    title() {
      if (customerId)
        return [
          'ملف العميل',
          'التواصل، الخدمة، الطلبات والمديونية في ملف مترابط.',
          'CUSTOMER 360',
        ];
      if (isSchedule)
        return [
          'الجدول الأسبوعي',
          'وزّع العملاء ثم انشر رحلة اليوم بتاريخها.',
          'WEEKLY PLANNER',
        ];
      if (
        document.body.dataset.role === 'representative' &&
        location.pathname === '/dashboard'
      )
        return [
          'يومي في الميدان',
          'رحلة اليوم وعملاؤك ونتيجة كل محطة، خطوة بخطوة.',
          'FIELD WORKSPACE',
        ];
      return null;
    },
  };
  document.addEventListener('change', (ev) => {
    if (ev.target.id === 'schedule-rep')
      location.href = '/schedule?rep=' + encodeURIComponent(ev.target.value);
  });
  document.addEventListener('input', (ev) => {
    if (ev.target.id === 'weekly-customer-search') {
      const q = ev.target.value.trim().toLocaleLowerCase();
      document
        .querySelectorAll('[data-weekly-search]')
        .forEach(
          (label) =>
            (label.hidden = !label.dataset.weeklySearch
              .toLocaleLowerCase()
              .includes(q)),
        );
    }
  });
})();
