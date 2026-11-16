'use strict';
(() => {
  const $ = (s, root = document) => root.querySelector(s);
  const esc = (value) =>
    String(value ?? '').replace(
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
  const workspace = document.body.dataset.custodyWorkspace === 'true';
  const role = document.body.dataset.role;
  const states = {
    pending: 'بانتظار الموافقة',
    accepted: 'استلام معتمد',
    rejected: 'مرفوض',
    cancelled: 'ملغى',
  };
  const kinds = {
    vehicle_delivery: 'تسليم مركبة',
    vehicle_return: 'إرجاع مركبة',
    stock_transfer: 'حمولة ومخزون',
    asset: 'عهدة ومعدات',
    cash: 'تسليم نقد',
  };
  const roles = {
    manager: 'مدير',
    supervisor: 'مشرف',
    representative: 'مندوب',
    accountant: 'محاسب',
    driver: 'سائق',
    production: 'مسؤول الإنتاج',
    quality_inspector: 'فاحص الجودة',
    quality_manager: 'معتمد الجودة',
    warehouse_keeper: 'أمين مستودع',
  };
  const state = {
    tab: location.pathname === '/notifications' ? 'notifications' : 'all',
    page: 1,
    notificationPage: 1,
    response: null,
    options: null,
    notifications: null,
    currentId: null,
    stock: [],
    stockSource: null,
    stockLoading: false,
    sourceVersion: 0,
  };
  const when = (value) =>
    value
      ? new Date(value).toLocaleString('ar-SA', {
          calendar: 'gregory',
          dateStyle: 'medium',
          timeStyle: 'short',
          timeZone: document.body.dataset.timezone || 'Asia/Riyadh',
        })
      : '—';
  const badge = (h) => `<span class="custody-state ${esc(h.status)}">${esc(states[h.status])}</span>`;
  const uuid = () => {
    if (crypto.randomUUID) return crypto.randomUUID();
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
  };
  async function api(url, data = null) {
    const options = { headers: { Accept: 'application/json' } };
    if (data !== null) {
      options.method = 'POST';
      options.headers['X-CSRF-TOKEN'] = $('meta[name="csrf-token"]').content;
      if (data instanceof FormData) options.body = data;
      else {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(data);
      }
    }
    const response = await fetch(url, options);
    if ([401, 419].includes(response.status)) {
      location.href = '/login';
      throw new Error('انتهت الجلسة؛ سجل الدخول مجددًا.');
    }
    let body;
    try {
      body = await response.json();
    } catch {
      throw new Error('تعذر قراءة الرد. حدّث الصفحة وتحقق من الاتصال وحجم المرفق.');
    }
    if (!response.ok)
      throw new Error(
        body.errors ? Object.values(body.errors).flat().join('\n') : body.message || 'تعذر تنفيذ الإجراء.',
      );
    return body;
  }
  function toast(message, error = false) {
    const node = $('#custody-toast');
    node.textContent = message;
    node.classList.toggle('error', error);
    node.hidden = false;
    clearTimeout(node.timer);
    node.timer = setTimeout(() => {
      node.hidden = true;
    }, 6500);
  }
  const empty = (title, text) =>
    `<div class="custody-empty"><strong>${esc(title)}</strong><p>${esc(text)}</p></div>`;
  const field = (name, label, type = 'text', attrs = '') =>
    `<label>${esc(label)}<input name="${name}" type="${type}" ${attrs}></label>`;
  const select = (name, label, options, attrs = '') =>
    `<label>${esc(label)}<select name="${name}" required ${attrs}><option value="">اختر…</option>${options}</select></label>`;
  const note = (name, label) =>
    `<label class="wide">${esc(label)}<textarea name="${name}" required minlength="3" maxlength="2000" rows="3" placeholder="اكتب التفاصيل والحالة والملاحظات بوضوح…"></textarea></label>`;
  const userOptions = (users) =>
    users
      .filter((u) => u.id !== state.response?.user_id)
      .map(
        (u) =>
          `<option value="${u.id}">${esc(u.name)} • ${esc(roles[u.role] || u.role)} • ${esc(u.username)}</option>`,
      )
      .join('');

  function notificationRows(rows) {
    return rows.length
      ? rows
          .map(
            (n) =>
              `<button type="button" class="notification-item ${n.read_at ? '' : 'unread'}" data-notification="${n.id}" data-handover="${n.handover_id || ''}" data-quality="${n.quality_inspection_id || ''}" data-archive="${n.archive_document_id || ''}" data-delegation-id="${n.delegation_id || ''}"><strong>${esc(n.title)}</strong><p>${esc(n.body)}</p><small>${esc(when(n.created_at))}${n.read_at ? ' • مقروء' : ' • جديد'}</small></button>`,
          )
          .join('')
      : empty(
          'لا توجد إشعارات بعد',
          'طلبات الجودة والتسليم والردود والرسائل تظهر هنا فور تسجيلها في النظام.',
        );
  }
  async function notifications(page = 1, paint = true) {
    const result = await api('/ops/notifications?page=' + page);
    state.notifications = result;
    const count = $('#notifications-count');
    count.hidden = result.unread === 0;
    count.textContent = result.unread > 99 ? '99+' : result.unread;
    $('#notifications-trigger').setAttribute('aria-label', `إشعاراتي، ${result.unread} غير مقروء`);
    if ($('#notifications-dialog').open)
      $('#notification-preview').innerHTML = notificationRows(result.notifications.data.slice(0, 6));
    if (workspace && state.tab === 'notifications' && paint) {
      $('#custody-results').innerHTML =
        `<div class="notification-toolbar"><span>${result.unread} إشعار غير مقروء</span><button type="button" class="secondary-btn" data-read-notifications>تعليم الكل كمقروء</button></div>` +
        notificationRows(result.notifications.data);
      pagination(result.notifications, true);
    }
    return result;
  }
  $('#notifications-trigger').addEventListener('click', async () => {
    $('#notifications-dialog').showModal();
    $('#notification-preview').innerHTML = '<div class="loading">جارٍ تحميل الإشعارات…</div>';
    try {
      await notifications(1, false);
    } catch (error) {
      $('#notification-preview').innerHTML = empty('تعذر تحميل الإشعارات', error.message);
    }
  });
  async function readAll() {
    await api('/ops/notifications/read', { all: true });
    await notifications(state.tab === 'notifications' ? state.notificationPage : 1);
  }
  $('#notifications-read-all').addEventListener('click', () =>
    readAll().catch((e) => toast(e.message, true)),
  );

  function pagination(page, isNotifications = false) {
    $('#custody-pagination').innerHTML =
      `<button type="button" class="secondary-btn" data-custody-page="${page.current_page - 1}" data-notifications-page="${isNotifications}" ${page.current_page <= 1 ? 'disabled' : ''}>السابق</button><span>صفحة ${page.current_page} من ${page.last_page} • ${page.total} سجل</span><button type="button" class="secondary-btn" data-custody-page="${page.current_page + 1}" data-notifications-page="${isNotifications}" ${page.current_page >= page.last_page ? 'disabled' : ''}>التالي</button>`;
  }
  function cards(rows) {
    if (!rows.length)
      return empty(
        'لا توجد طلبات تطابق العرض',
        'الطلب الجديد يصل للمستلم المحدد، ويظل معلقًا حتى يوافق أو يرفض. يمكنك تغيير الفلاتر أو إنشاء طلب جديد بصلاحيتك.',
      );
    return `<div class="custody-cards">${rows
      .map((h) => {
        const details = h.snapshot.details;
        const subject = details.vehicle
          ? `${details.vehicle.name} • ${details.vehicle.plate}`
          : details.items
            ? `${details.items.length} أصناف / عناصر • ${details.items.reduce((n, i) => n + Number(i.quantity), 0)} وحدة`
            : details.reference || '';
        return `<article class="custody-card ${h.can_respond ? 'waiting-me' : ''}"><div class="custody-card-head"><div><span class="custody-code">${esc(h.code)}</span><h3>${esc(h.kind_label)}</h3></div>${badge(h)}</div><div class="custody-parties"><div><small>من</small><strong>${esc(h.snapshot.sender.name)}</strong></div><span class="party-arrow">←</span><div><small>إلى</small><strong>${esc(h.snapshot.recipient?.name || h.receipt?.accepted_by.name || 'مستلم من الحسابات')}</strong></div></div><p class="custody-note">${esc(subject)}</p><p class="custody-note">${esc(h.sender_note.slice(0, 140))}</p><div class="custody-card-footer"><time>${esc(when(h.created_at))}</time><button type="button" class="${h.can_respond ? 'primary-btn' : 'secondary-btn'}" data-open-handover="${h.id}">${h.can_respond ? 'راجع وأقرّ الاستلام ←' : h.status === 'accepted' ? 'المحضر والأرشيف ←' : 'تفاصيل الطلب ←'}</button></div></article>`;
      })
      .join('')}</div>`;
  }
  async function load() {
    const params = new URLSearchParams(new FormData($('#custody-filters')));
    params.set('direction', state.tab === 'notifications' ? 'all' : state.tab);
    params.set('page', state.page);
    const result = await api('/ops/handovers?' + params);
    state.response = result;
    $('#custody-stats').innerHTML = [
      ['بانتظار إقرارك', result.summary.awaiting_me],
      ['طلبات معلقة', result.summary.pending],
      ['محاضر معتمدة', result.summary.accepted],
      ['محاضر لها مرفقات مؤرشفة', result.summary.archived],
    ]
      .map(
        ([label, value]) =>
          `<div class="custody-stat"><span>${esc(label)}</span><strong>${value}</strong></div>`,
      )
      .join('');
    $('#custody-create').hidden = !result.can_create;
    $('#custody-filters').hidden = state.tab === 'notifications';
    document.querySelectorAll('[data-custody-tab]').forEach((b) => {
      b.classList.toggle('selected', b.dataset.custodyTab === state.tab);
      b.setAttribute('aria-pressed', String(b.dataset.custodyTab === state.tab));
    });
    if (state.tab === 'notifications') await notifications(state.notificationPage);
    else {
      $('#custody-results').innerHTML = cards(result.requests.data);
      pagination(result.requests);
    }
    $('#updated-at').textContent =
      'آخر تحديث ' +
      new Date().toLocaleTimeString('ar-SA', {
        hour: '2-digit',
        minute: '2-digit',
      });
  }

  function detailItems(details) {
    let html = '';
    if (details.vehicle) {
      const v = details.vehicle;
      html += `<dl class="custody-facts">${[
        ['المركبة', v.name],
        ['اللوحة', v.plate],
        [
          'النوع',
          state.options?.vehicle_types?.[v.vehicle_type] ||
            {
              van: 'فان',
              light_truck: 'دينا',
              tractor_trailer: 'تريلا',
              truck: 'شاحنة',
              pickup: 'وانيت',
              car: 'سيارة صغيرة',
              motorcycle: 'دراجة',
              other: 'نوع آخر',
            }[v.vehicle_type] ||
            'غير محدد',
        ],
        ['السائق', details.driver.name],
        ['رقم الهيكل', v.vin || 'غير مسجل'],
        ['قراءة العداد وقت الطلب', v.odometer_km ?? 'غير مسجلة'],
      ]
        .map(([key, value]) => `<div><dt>${esc(key)}</dt><dd>${esc(value)}</dd></div>`)
        .join(
          '',
        )}</dl>${v.notes ? `<div class="custody-hint">ملاحظات المركبة وقت الطلب: ${esc(v.notes)}</div>` : ''}`;
    }
    if (details.from)
      html += `<div class="custody-parties"><div><small>مصدر الحمولة</small><strong>${esc(details.from.name)}</strong></div><span class="party-arrow">←</span><div><small>وجهة الحمولة</small><strong>${esc(details.to.name)}</strong></div></div>`;
    if (details.items)
      html += `<div class="custody-section"><h3>العناصر المطلوب فحصها واستلامها</h3><div class="custody-table-scroll"><table class="custody-detail-table"><thead><tr><th>العنصر</th><th>الرمز / التسلسل</th><th>الكمية</th><th>الوحدة / الحالة</th></tr></thead><tbody>${details.items.map((line) => `<tr><td>${esc(line.name)}</td><td>${esc(line.sku || line.serial || '—')}${line.lots?.length ? '<br><small>' + line.lots.map((l) => esc(l.code) + ' × ' + esc(l.quantity)).join('، ') + '</small>' : ''}</td><td>${esc(line.quantity)}</td><td>${esc(line.unit || line.condition || '—')}</td></tr>`).join('')}</tbody></table></div></div>`;
    if (details.amount_minor !== undefined)
      html += `<div class="custody-facts"><div><dt>المبلغ</dt><dd>${(details.amount_minor / 100).toFixed(2)}</dd></div><div><dt>المرجع</dt><dd>${esc(details.reference)}</dd></div><div><dt>رقم تسليم النقد</dt><dd>${esc(details.cash_code)}</dd></div></div>`;
    return html;
  }
  async function openRequest(id, messagesPage = 1) {
    state.currentId = Number(id);
    $('#notifications-dialog').close();
    const dialog = $('#custody-dialog');
    if (!dialog.open) dialog.showModal();
    $('#custody-dialog-title').textContent = 'تفاصيل الطلب';
    $('#custody-detail').innerHTML = '<div class="loading">نحمّل الطلب وتاريخه…</div>';
    const result = await api(`/ops/handovers/${Number(id)}?messages_page=${messagesPage}`);
    const h = result.request;
    $('#custody-dialog-title').textContent = `${h.kind_label} • ${h.code}`;
    const people = h.snapshot;
    const decisionForm = h.can_respond
      ? `<form class="custody-decision" data-custody-form="decision" data-request-id="${uuid()}"><h3>${h.kind === 'vehicle_return' ? 'هل تؤكد إرجاع المركبة بهذه الملاحظات؟' : 'راجع التفاصيل قبل إقرار الاستلام'}</h3><p>القبول يثبت العملية ويصدر محضرًا. إذا وجدت اختلافًا في الأصناف أو الكميات أو الحالة، اكتب السبب وارفض ليصدر الطرف الآخر طلبًا صحيحًا.</p>${note('note', 'ملاحظاتك عن الحالة والاستلام أو سبب الرفض')}<label class="custody-ack"><input type="checkbox" name="acknowledge"><span>${h.kind === 'vehicle_return' ? 'أقر بإرجاع المركبة المذكورة وفق الملاحظات الواردة.' : 'أقر بأنني راجعت التفاصيل والكميات والحالة المذكورة، واستلمت ما يخصني وفق ملاحظاتي.'}</span></label><div class="custody-buttons"><button class="primary-btn" name="decision" value="accepted">${h.kind === 'vehicle_return' ? 'أقرّ الإرجاع وأصدر المحضر' : 'أقرّ الاستلام وأصدر المحضر'}</button><button class="custody-danger" name="decision" value="rejected">رفض مع السبب</button></div><div class="custody-error" role="alert"></div></form>`
      : '';
    $('#custody-detail').innerHTML =
      `<div class="custody-detail-top"><span class="custody-code">${esc(h.code)}</span>${badge(h)}</div><div class="custody-parties"><div><small>مقدم التسليم / طلب الإرجاع</small><strong>${esc(people.sender.name)}</strong><small>${esc(people.sender.username)}</small></div><span class="party-arrow">←</span><div><small>الطرف المقر بالاستلام / الإرجاع</small><strong>${esc(people.recipient?.name || h.receipt?.accepted_by.name || 'الحسابات — مستلم آخر')}</strong><small>${esc(people.recipient?.username || '')}</small></div></div><p class="custody-footnote">صدر الطلب ${esc(when(h.created_at))}${h.decided_at ? ` • القرار ${esc(when(h.decided_at))} بواسطة ${esc(h.receipt?.accepted_by.name || h.decider?.name || 'الطرف المخول')}` : ''}</p>${detailItems(people.details)}${h.status === 'pending' ? `<div class="custody-hint">${h.kind === 'stock_transfer' ? 'الكمية محجوزة في المصدر؛ لم تُنقل إلى عهدة المستلم بعد. القبول ينفذ النقل مرة واحدة.' : 'هذا طلب معلق. لا يُعدّ إقرار استلام ولا يصدر عنه محضر نهائي قبل قبول الطرف الآخر.'}</div>` : ''}<div class="custody-section custody-detail-notes"><div class="custody-note-box"><strong>ملاحظات مقدم الطلب</strong><p>${esc(h.sender_note)}</p></div><div class="custody-note-box"><strong>رد الطرف الآخر / سبب القرار</strong><p>${esc(h.response_note || 'لم يرد الطرف الآخر بعد.')}</p></div></div>${decisionForm}${h.can_cancel ? `<details class="custody-cancel"><summary>إلغاء الطلب قبل اعتماده</summary><form data-custody-form="cancel" data-request-id="${uuid()}">${note('note', 'سبب إلغاء الطلب')}<div class="custody-buttons"><button class="custody-danger">إلغاء وتحرير الحجز</button></div><div class="custody-error" role="alert"></div></form></details>` : ''}${h.status === 'accepted' ? `<div class="custody-archive-receipt"><div><strong>صدر المحضر من بيانات الاستلام المحفوظة</strong><small>${esc(h.receipt_hash)}</small></div><a class="primary-btn" href="${h.document_url}" target="_blank" rel="noopener">طباعة محضر الاستلام ↗</a></div><div class="custody-section"><h3>أرشيف النسخ الموقعة</h3><p class="custody-footnote">اطبع المحضر، ووقّعه يدويًا، ثم ارفع نسخة PDF أو صورة واضحة. تبقى كل النسخ بتاريخها؛ الرفع لا يثبت صحة التوقيع آليًا.</p><div class="custody-files">${result.files.map((file) => `<div class="custody-file"><div><a href="${file.download_url}">${esc(file.original_name)} ↓</a><small>${esc(when(file.created_at))} • ${Math.ceil(file.size / 1024)} KB</small><small class="file-sha">SHA-256 ${esc(file.sha256)}</small></div></div>`).join('') || empty('لا توجد نسخة مؤرشفة بعد', 'المحضر جاهز للطباعة؛ أرفق نسخته بعد توقيع الطرفين.')}</div>${role !== 'viewer' ? `<form class="custody-upload" data-custody-form="archive" data-request-id="${uuid()}"><label>النسخة الموقعة<input name="document" type="file" accept="application/pdf,image/jpeg,image/png" required></label><button class="secondary-btn">رفع إلى الأرشيف</button><div class="custody-error" role="alert"></div></form><small class="custody-footnote">PDF / JPEG / PNG • حتى 10 MB حسب إعداد الخادم.</small>` : ''}</div>` : ''}<div class="custody-section"><h3>محادثة الطلب وتوضيح الملاحظات</h3><p class="custody-footnote">الرسائل محفوظة بأصحابها وأوقاتها. إضافة توضيح بعد الموافقة لا تغيّر نص المحضر المعتمد.</p><div class="custody-messages">${
        result.messages.data
          .slice()
          .reverse()
          .map(
            (m) =>
              `<article class="custody-message ${m.user_id === state.response?.user_id ? 'mine' : ''}"><header><strong>${esc(m.author?.name || 'عضو الشركة')}</strong><span>${esc(when(m.created_at))}</span></header><p>${esc(m.body)}</p></article>`,
          )
          .join('') || '<p class="custody-footnote">لا توجد رسائل بعد.</p>'
      }</div>${result.messages.last_page > 1 ? `<div class="custody-buttons"><button type="button" class="secondary-btn" data-message-page="${messagesPage - 1}" ${messagesPage <= 1 ? 'disabled' : ''}>رسائل أحدث</button><span>صفحة ${messagesPage} من ${result.messages.last_page}</span><button type="button" class="secondary-btn" data-message-page="${messagesPage + 1}" ${messagesPage >= result.messages.last_page ? 'disabled' : ''}>رسائل أقدم</button></div>` : ''}${role !== 'viewer' ? `<form class="custody-message-form" data-custody-form="messages" data-request-id="${uuid()}"><label>رسالة للطرف الآخر<textarea name="body" required maxlength="2000" rows="2" placeholder="اكتب استفسارًا أو توضيحًا يخص هذا الطلب…"></textarea></label><div class="custody-buttons"><button class="secondary-btn">إرسال وحفظ الرسالة</button></div><div class="custody-error" role="alert"></div></form>` : ''}</div>`;
    if (workspace)
      history.replaceState(
        null,
        '',
        `${state.tab === 'notifications' ? '/notifications' : '/handovers'}?request=${Number(id)}`,
      );
  }

  const stockProducts = () =>
    '<option value="">اختر الصنف من رصيد المصدر…</option>' +
    state.stock
      .map(
        (p) =>
          `<option value="${p.id}">${esc(p.name)} • ${esc(p.sku)} • متاح ${p.available} ${esc(p.unit)} / فعلي ${p.physical}</option>`,
      )
      .join('');
  function stockLine() {
    return `<div class="custody-line"><label>الصنف من رصيد المصدر<select name="product_id" required ${!state.stockSource ? 'disabled' : ''}>${stockProducts()}</select></label>${field('quantity', 'الكمية', 'number', 'min="1" max="100000" value="1" required')}<button type="button" class="remove-line" data-remove-custody-line aria-label="إزالة الصنف">×</button><p class="custody-stock-feedback" role="status"></p></div>`;
  }
  function stockFeedback() {
    const form = $('[data-custody-form="create"]');
    if (!form || $('[name="kind"]', form)?.value !== 'stock_transfer') return;
    let valid = !!state.stockSource && !state.stockLoading;
    const seen = new Set();
    form.querySelectorAll('#custody-lines .custody-line').forEach((row) => {
      const select = $('[name="product_id"]', row),
        qty = $('[name="quantity"]', row),
        hint = $('.custody-stock-feedback', row);
      const p = state.stock.find((x) => x.id === Number(select.value));
      qty.max = String(p ? Math.min(p.available, 100000) : 100000);
      const enough =
        p &&
        p.available > 0 &&
        Number(qty.value) >= 1 &&
        Number(qty.value) <= p.available &&
        Number(qty.value) <= 100000;
      const duplicate = p && seen.has(p.id);
      if (p) seen.add(p.id);
      valid = valid && enough && !duplicate;
      if (!p) {
        hint.textContent = state.stockLoading
          ? 'جارٍ قراءة رصيد المصدر…'
          : state.stockSource
            ? 'اختر صنفًا للاطلاع على الفعلي والمتاح وحالة الجودة.'
            : 'اختر مصدر الحمولة أولًا.';
        return;
      }
      const reasons = [];
      if (p.quarantine) reasons.push(`حجر جودة ${p.quarantine}`);
      if (p.blocked) reasons.push(`موقوف ${p.blocked}`);
      if (p.expired) reasons.push(`منتهي الصلاحية ${p.expired}`);
      if (p.reserved) reasons.push(`محجوز لطلبات تسليم ${p.reserved}`);
      hint.textContent =
        `فعلي ${p.physical} ${p.unit} • متاح للصرف ${p.available}` +
        (reasons.length ? ' • ' + reasons.join(' • ') : '') +
        (p.quarantine || p.blocked ? ' — الإفراج عن الجودة من المدير بعد الفحص.' : '') +
        (duplicate ? ' — الصنف مكرر؛ اجمع كميته في سطر واحد.' : '') +
        (Number(qty.value) > p.available ? ' — الكمية المطلوبة أكبر من المتاح.' : '');
      hint.classList.toggle('stock-unavailable', !enough || duplicate);
    });
    $('button[type="submit"], button.primary-btn', form).disabled = !valid;
  }
  async function stockSource(prefill = null) {
    const id = Number($('#custody-source')?.value),
      version = ++state.sourceVersion;
    state.stock = [];
    state.stockSource = null;
    state.stockLoading = !!id;
    $('#custody-stock-source').textContent = id
      ? 'جارٍ قراءة رصيد المصدر وحالة الجودة…'
      : 'اختر المصدر؛ ستظهر أصنافه وكمياته الفعلية بدل أصناف الشركة كلها.';
    document.querySelectorAll('#custody-lines [name="product_id"]').forEach((select) => {
      select.innerHTML = stockProducts();
      select.disabled = true;
    });
    stockFeedback();
    if (!id) return;
    try {
      const result = await api('/ops/handovers/stock?warehouse_id=' + id);
      if (
        version !== state.sourceVersion ||
        !$('#custody-source') ||
        Number($('#custody-source').value) !== id
      )
        return;
      state.stock = result.products;
      state.stockSource = id;
      state.stockLoading = false;
      document.querySelectorAll('#custody-lines [name="product_id"]').forEach((select) => {
        select.innerHTML = stockProducts();
        select.disabled = false;
      });
      const first = $('#custody-lines [name="product_id"]');
      const chosen = state.stock.find((p) => p.id === Number(prefill));
      if (chosen) first.value = String(chosen.id);
      else if (!prefill && state.stock.length === 1) first.value = String(state.stock[0].id);
      const ready = state.stock.filter((p) => p.available > 0).length;
      $('#custody-stock-source').textContent =
        `رصيد «${result.warehouse.name}»: ${state.stock.length} أصناف لها رصيد فعلي، منها ${ready} متاحة للصرف.` +
        (!ready ? ' لا توجد كمية جاهزة للتسليم؛ راجع الجودة والصلاحية والحجوزات أدناه.' : '') +
        (prefill && !chosen
          ? ' الصنف القادم من أمر الإنتاج لا يملك رصيدًا في هذا المصدر؛ لا تستبدله بصنف آخر دون مراجعة الأمر.'
          : '');
      stockFeedback();
    } catch (error) {
      if (version !== state.sourceVersion || !$('#custody-stock-source')) return;
      state.stockLoading = false;
      $('#custody-stock-source').textContent = error.message;
      stockFeedback();
    }
  }
  function assetLine() {
    return `<div class="custody-line asset-line">${field('asset_name', 'العهدة / المعدة', 'text', 'required maxlength="150"')}${field('serial', 'رقم تسلسلي (اختياري)', 'text', 'maxlength="100"')}${field('quantity', 'الكمية', 'number', 'required min="1" max="100000" value="1"')}<button type="button" class="remove-line" data-remove-custody-line aria-label="إزالة العهدة">×</button><label class="asset-condition">الحالة والملحقات<input name="condition" required minlength="3" maxlength="500" placeholder="الحالة الحالية، الرقم، الشاحن أو المفاتيح…"></label></div>`;
  }
  function createFields(kind) {
    const opts = state.options;
    let html = '';
    if (kind === 'vehicle_delivery')
      html = `<div class="custody-create-grid">${select(
        'vehicle_id',
        'المركبة',
        opts.vehicles
          .filter((v) => v.status === 'available')
          .map(
            (v) =>
              `<option value="${v.id}">${esc(opts.vehicle_types[v.vehicle_type] || 'نوع غير محدد')} • ${esc(v.name)} • ${esc(v.plate)}</option>`,
          )
          .join(''),
      )}${select(
        'driver_id',
        'السائق وحساب الاستلام',
        opts.drivers
          .filter((d) => d.user_id)
          .map(
            (d) =>
              `<option value="${d.id}">${esc(d.name)} • ${esc(opts.users.find((u) => u.id === d.user_id)?.username || 'راجع الحساب')}</option>`,
          )
          .join(''),
      )}</div><p class="custody-footnote">إذا لم يظهر السائق، أنشئ له حساب «سائق» من الفريق واربط الحساب بملف السائق عند تعديله. الجاهزية والرخصة والعهد المعلقة تفحص عند الإرسال والموافقة.</p>`;
    else if (kind === 'vehicle_return')
      html =
        select(
          'id',
          'عهدة المركبة المطلوب إرجاعها',
          opts.assignments
            .map(
              (a) =>
                `<option value="${a.id}">${esc(opts.vehicles.find((v) => v.id === a.vehicle_id)?.name)} • ${esc(a.driver?.name)}</option>`,
            )
            .join(''),
        ) +
        '<p class="custody-footnote">يصل للسائق طلب إقرار الإرجاع؛ لا ينتهي الاستلام الحالي إلا بعد موافقته، ولا يسمح بالإرجاع أثناء رحلة نشطة.</p>';
    else if (kind === 'stock_transfer')
      html = `<div class="custody-create-grid">${select(
        'from_id',
        'مصدر الحمولة',
        opts.warehouses
          .filter((w) => opts.source_warehouse_ids.includes(w.id))
          .map((w) => `<option value="${w.id}">${esc(w.name)}</option>`)
          .join(''),
        'id="custody-source"',
      )}${select('to_id', 'وجهة الحمولة / العهدة', opts.warehouses.map((w) => `<option value="${w.id}">${esc(w.name)}</option>`).join(''), 'id="custody-destination"')}${select('recipient_id', 'المستلم الذي يقر بالكميات', userOptions(opts.users), 'id="custody-recipient"')}</div><div id="custody-stock-source" class="custody-hint" role="status"></div><div class="custody-section"><h3>تفاصيل الحمولة</h3><div id="custody-lines">${stockLine()}</div><button type="button" class="secondary-btn" data-add-custody-line="stock">+ صنف آخر</button></div><div class="custody-hint">تحجز الكميات في المصدر حتى الرد. القبول ينقل الأصناف معًا، والرفض أو الإلغاء يحرر الحجز. تختار الدفعات الصالحة الأقرب انتهاءً تلقائيًا وتظهر أرقامها للمراجعة. إذا اختلفت الكمية، لا تقبل المحضر؛ اذكر الفرق ليصدر طلب صحيح.</div>`;
    else
      html = `<div class="custody-create-grid">${select('recipient_id', 'صاحب حساب استلام العهدة', userOptions(opts.users))}</div><div class="custody-section"><h3>المعدات والعناصر</h3><div id="custody-lines">${assetLine()}</div><button type="button" class="secondary-btn" data-add-custody-line="asset">+ عنصر آخر</button></div><p class="custody-footnote">هذا إقرار عهدة للمعدات الموصوفة؛ الأصناف التي لها رصيد مخزون تُسلّم من «حمولة ومخزون» حتى ترتبط حركتها بالدفتر.</p>`;
    $('#custody-create-fields').innerHTML =
      html +
      '<div class="custody-section">' +
      note('note', 'ملاحظات التسليم والحالة والشروط المذكورة') +
      '</div>';
    if (kind === 'stock_transfer') {
      const params = new URLSearchParams(location.search);
      const source = Number(params.get('from_id'));
      const valid = opts.source_warehouse_ids.includes(source);
      $('#custody-source').value = valid
        ? String(source)
        : opts.source_warehouse_ids.length === 1
          ? String(opts.source_warehouse_ids[0])
          : '';
      stockSource(valid ? params.get('product_id') : null);
    }
  }
  async function createRequest(kind = null) {
    if (!workspace) {
      location.href = '/handovers' + (kind ? '?new=' + encodeURIComponent(kind) : '');
      return;
    }
    ++state.sourceVersion;
    state.stock = [];
    state.stockSource = null;
    state.stockLoading = false;
    state.options = await api('/ops/handovers/options');
    const allowed =
      role === 'warehouse_keeper'
        ? ['stock_transfer', 'asset']
        : role === 'production'
          ? ['stock_transfer']
          : ['representative', 'driver'].includes(role)
            ? ['stock_transfer']
            : ['vehicle_delivery', 'stock_transfer', 'asset', 'vehicle_return'];
    const selected = allowed.includes(kind) ? kind : allowed[0];
    $('#custody-dialog-title').textContent = 'طلب تسليم جديد';
    $('#custody-detail').innerHTML =
      `<form data-custody-form="create" data-request-id="${uuid()}"><div class="custody-create-grid"><label>نوع التسليم<select name="kind" id="custody-kind">${allowed.map((k) => `<option value="${k}" ${k === selected ? 'selected' : ''}>${esc(kinds[k])}</option>`).join('')}</select></label></div><div id="custody-create-fields"></div><div class="custody-buttons"><button class="primary-btn">إرسال للمستلم</button><button type="button" class="secondary-btn" data-close-custody="custody-dialog">رجوع</button></div><div class="custody-error" role="alert"></div></form>`;
    createFields(selected);
    if (!$('#custody-dialog').open) $('#custody-dialog').showModal();
    $('#custody-kind').addEventListener('change', (event) => createFields(event.target.value));
  }

  document.addEventListener('click', async (event) => {
    const button = event.target.closest('button,[data-open-handover]');
    if (!button) return;
    try {
      if (button.dataset.closeCustody) {
        $('#' + button.dataset.closeCustody).close();
        return;
      }
      if (button.dataset.notification) {
        await api('/ops/notifications/read', {
          id: Number(button.dataset.notification),
        });
        if (button.dataset.delegationId && /^\d+$/.test(button.dataset.delegationId)) {
          location.href = '/delegations?delegation=' + button.dataset.delegationId;
          return;
        }
        if (button.dataset.archive && /^\d+$/.test(button.dataset.archive)) {
          location.href = '/archive?document=' + button.dataset.archive;
          return;
        }
        if (button.dataset.quality && /^\d+$/.test(button.dataset.quality)) {
          location.href = '/quality?inspection=' + button.dataset.quality;
          return;
        }
        if (button.dataset.handover) await openRequest(button.dataset.handover);
        await notifications(1, false);
        return;
      }
      if (button.dataset.openHandover) {
        await openRequest(button.dataset.openHandover);
        return;
      }
      if (button.hasAttribute('data-read-notifications')) {
        await readAll();
        return;
      }
      if (button.dataset.custodyTab) {
        state.tab = button.dataset.custodyTab;
        state.page = state.notificationPage = 1;
        history.replaceState(null, '', state.tab === 'notifications' ? '/notifications' : '/handovers');
        await load();
        return;
      }
      if (button.dataset.custodyPage) {
        if (button.dataset.notificationsPage === 'true') {
          state.notificationPage = Number(button.dataset.custodyPage);
          await notifications(state.notificationPage);
        } else {
          state.page = Number(button.dataset.custodyPage);
          await load();
        }
        return;
      }
      if (button.dataset.messagePage) {
        await openRequest(state.currentId, Number(button.dataset.messagePage));
        return;
      }
      if (button.dataset.addCustodyLine) {
        if ($('#custody-lines').children.length >= 50) {
          toast('الحد 50 عنصرًا للطلب الواحد.', true);
          return;
        }
        $('#custody-lines').insertAdjacentHTML(
          'beforeend',
          button.dataset.addCustodyLine === 'stock' ? stockLine() : assetLine(),
        );
        stockFeedback();
        return;
      }
      if (button.hasAttribute('data-remove-custody-line') && $('#custody-lines').children.length > 1) {
        button.closest('.custody-line').remove();
        stockFeedback();
      }
    } catch (error) {
      toast(error.message, true);
    }
  });
  document.addEventListener('input', (event) => {
    if (event.target.closest('#custody-lines')) stockFeedback();
  });
  document.addEventListener('change', (event) => {
    if (event.target.id === 'custody-source') {
      stockSource();
      return;
    }
    if (event.target.closest('#custody-lines')) stockFeedback();
    if (event.target.id === 'custody-destination') {
      const destination = state.options.warehouses.find((w) => w.id === Number(event.target.value));
      const type = destination?.type;
      const allowed =
        type === 'vehicle'
          ? ['representative', 'driver']
          : type === 'production'
            ? ['production', 'manager', 'supervisor']
            : ['warehouse_keeper', 'manager', 'supervisor'];
      $('#custody-recipient').innerHTML =
        '<option value="">اختر المستلم…</option>' +
        userOptions(
          state.options.users.filter(
            (u) =>
              allowed.includes(u.role) && (!destination?.custodian_id || u.id === destination.custodian_id),
          ),
        );
    }
  });
  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-custody-form]');
    if (!form) return;
    event.preventDefault();
    if (form.dataset.busy === 'true') return;
    const errorBox = $('.custody-error', form);
    errorBox.textContent = '';
    const action = form.dataset.custodyForm;
    const f = new FormData(form);
    let payload = Object.fromEntries(f);
    payload.request_id = form.dataset.requestId;
    form.dataset.busy = 'true';
    const buttons = [...form.querySelectorAll('button')];
    buttons.forEach((b) => {
      b.disabled = true;
    });
    try {
      let url = '/ops/handovers';
      if (action === 'create') {
        if (payload.kind === 'stock_transfer') {
          if (state.stockLoading || Number(payload.from_id) !== state.stockSource)
            throw new Error('انتظر قراءة رصيد المصدر قبل الإرسال.');
          payload.items = [...form.querySelectorAll('.custody-line')].map((row) => ({
            product_id: Number($('[name="product_id"]', row).value),
            quantity: Number($('[name="quantity"]', row).value),
          }));
          delete payload.product_id;
          delete payload.quantity;
        } else if (payload.kind === 'asset') {
          payload.items = [...form.querySelectorAll('.custody-line')].map((row) => ({
            name: $('[name="asset_name"]', row).value,
            serial: $('[name="serial"]', row).value,
            quantity: Number($('[name="quantity"]', row).value),
            condition: $('[name="condition"]', row).value,
          }));
          delete payload.asset_name;
          delete payload.serial;
          delete payload.quantity;
          delete payload.condition;
        }
        for (const key of ['recipient_id', 'vehicle_id', 'driver_id', 'from_id', 'to_id', 'id'])
          if (key in payload) payload[key] = Number(payload[key]);
      } else {
        url += '/' + state.currentId + '/' + action;
        if (action === 'decision') {
          payload.decision = event.submitter?.value;
          if (payload.decision === 'accepted') {
            if (!f.has('acknowledge'))
              throw new Error('ضع علامة الإقرار بعد مراجعة التفاصيل، أو اختر الرفض مع السبب.');
            payload.acknowledge = true;
          } else delete payload.acknowledge;
        } else if (action === 'archive') {
          const file = f.get('document');
          if (file.size > 10 * 1024 * 1024) throw new Error('الحد الأعلى للمرفق 10 MB.');
          f.set('request_id', form.dataset.requestId);
          payload = f;
        }
      }
      const result = await api(url, payload);
      document.dispatchEvent(new CustomEvent('amrbit:handover-changed'));
      toast(result.message);
      if (action === 'create') {
        await load();
        await openRequest(result.id);
      } else {
        await openRequest(state.currentId);
        if (workspace) await load();
      }
      await notifications(1, false);
    } catch (error) {
      errorBox.textContent = error.message;
    } finally {
      form.dataset.busy = 'false';
      buttons.forEach((b) => {
        b.disabled = false;
      });
      if (action === 'create') stockFeedback();
    }
  });

  if (workspace) {
    $('#page-title').textContent = 'التسليم والاستلام';
    $('#page-description').textContent = 'طلبات واضحة، موافقة من الحساب المعني، ومحاضر محفوظة بين الطرفين.';
    $('#section-code').textContent = 'HANDOVER CENTER';
    $('.global-search').hidden = true;
    $('#refresh').addEventListener('click', () => load().catch((e) => toast(e.message, true)));
    $('#custody-filters').addEventListener('submit', (event) => {
      event.preventDefault();
      state.page = 1;
      load().catch((e) => toast(e.message, true));
    });
    $('#custody-create').addEventListener('click', () =>
      createRequest().catch((e) => toast(e.message, true)),
    );
  }
  if (workspace || document.body.dataset.supplyWorkspace === 'true') {
    // The regular workspace script is intentionally absent on this focused portal.
    const menu = (open) => {
      const mobile = matchMedia('(max-width:760px)').matches;
      $('#sidebar').classList.toggle('open', open);
      $('#sidebar').inert = !mobile || !open;
      $('.main').inert = mobile && open;
      $('#sidebar').setAttribute('aria-hidden', String(!mobile || !open));
      $('#menu-backdrop').hidden = !mobile || !open;
      $('#menu-toggle').setAttribute('aria-expanded', String(open));
    };
    $('#menu-toggle').onclick = () => menu(!$('#sidebar').classList.contains('open'));
    $('#menu-close').onclick = $('#menu-backdrop').onclick = () => menu(false);
    window.addEventListener('resize', () => menu(false));
    menu(false);
    const launcher = $('#launcher-dialog');
    const openLauncher = () => {
      menu(false);
      launcher.showModal();
      $('#launcher-search').focus();
    };
    $('#open-launcher').onclick = openLauncher;
    $('#close-launcher').onclick = () => launcher.close();
    $('#launcher-search').oninput = (event) => {
      let count = 0;
      document.querySelectorAll('[data-nav-label]').forEach((a) => {
        a.hidden = !a.dataset.navLabel.includes(event.target.value.trim());
        if (!a.hidden) count++;
      });
      $('#launcher-empty').hidden = count > 0;
    };
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') menu(false);
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k' && !$('#custody-dialog').open) {
        event.preventDefault();
        launcher.open ? launcher.close() : openLauncher();
      }
    });
    if (workspace)
      load()
        .then(async () => {
          const params = new URLSearchParams(location.search);
          if (params.get('request') && /^\d+$/.test(params.get('request')))
            await openRequest(params.get('request'));
          else if (params.has('new')) await createRequest(params.get('new'));
        })
        .catch((error) => {
          $('#custody-results').innerHTML = empty('تعذر تحميل الطلبات', error.message);
        });
  }
  notifications(1, false).catch(() => {});
  setInterval(() => {
    if (!document.hidden) notifications(1, false).catch(() => {});
  }, 20000);
  setInterval(() => {
    if (workspace && !document.hidden && !document.querySelector('dialog[open]')) load().catch(() => {});
  }, 20000);
})();
