'use strict';
window.FieldFlowAdmin = (() => {
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
  const input = (name, label, type = 'text', value = '', attrs = '') =>
    `<div class="field"><label for="admin-${name}">${esc(label)}</label><input id="admin-${name}" name="${name}" type="${type}" value="${esc(value)}" ${attrs}></div>`;
  const choose = (name, label, entries, value, blank = false) =>
    `<div class="field"><label for="admin-${name}">${esc(label)}</label><select id="admin-${name}" name="${name}" required>${blank ? '<option value="">اختر…</option>' : ''}${Object.entries(
      entries,
    )
      .map(
        ([k, v]) => `<option value="${esc(k)}" ${String(value) === k ? 'selected' : ''}>${esc(v)}</option>`,
      )
      .join('')}</select></div>`;
  const note = (name, label, value = '', required = false) =>
    `<div class="field"><label for="admin-${name}">${esc(label)}</label><textarea id="admin-${name}" name="${name}" maxlength="${name === 'notes' ? 1000 : 500}" ${required ? 'required minlength="3"' : ''}>${esc(value)}</textarea></div>`;
  const pair = (html) => `<div class="form-row">${html}</div>`;
  const block = (title, html, text = '') =>
    `<fieldset class="admin-fieldset"><legend>${esc(title)}</legend>${text ? `<p>${esc(text)}</p>` : ''}${html}</fieldset>`;
  const checkbox = (name, label, checked) =>
    `<label class="permission-check"><input type="checkbox" name="${name}" value="1" ${checked ? 'checked' : ''}><span>${esc(label)}</span></label>`;
  const roles = {
    representative: 'مندوب',
    supervisor: 'مشرف عمليات',
    accountant: 'محاسب',
    viewer: 'مشاهد',
    driver: 'سائق',
    production: 'مسؤول الإنتاج',
    quality_inspector: 'فاحص الجودة',
    quality_manager: 'معتمد الجودة',
    warehouse_keeper: 'أمين المستودع',
  };
  const states = {
    available: 'متاحة',
    maintenance: 'صيانة',
    inactive: 'موقوفة',
  };
  const listOptions = (items) => Object.fromEntries(items.map((x) => [x.id, x.name]));
  const badge = (text, good = true) =>
    `<span class="admin-badge ${good ? '' : 'warning'}">${esc(text)}</span>`;
  const details = (entries) =>
    `<dl class="admin-facts">${entries.map(([k, v]) => `<div><dt>${esc(k)}</dt><dd>${v}</dd></div>`).join('')}</dl>`;
  const panel = (title, body, subtitle = '') =>
    `<section class="panel admin-panel"><div class="admin-panel-heading"><div><h2>${esc(title)}</h2>${subtitle ? `<p>${esc(subtitle)}</p>` : ''}</div></div>${body}</section>`;
  const blankState = (title, text, action = '') =>
    `<div class="admin-empty"><strong>${esc(title)}</strong><p>${esc(text)}</p>${action}</div>`;
  const scope = (member, data) =>
    member.area_access === 'all'
      ? 'كل مناطق الشركة'
      : member.area_ids?.length
        ? data.areas
            .filter((a) => member.area_ids.includes(a.id))
            .map((a) => a.name)
            .join('، ')
        : 'لا توجد مناطق مسموحة';
  const activeAssignment = (data, vehicleId) =>
    data.vehicle_assignments.find((a) => a.vehicle_id === vehicleId && !a.returned_at);
  function vehicleArt(type) {
    const wheel = (x, y = 65, r = 8) =>
      `<circle cx="${x}" cy="${y}" r="${r}" fill="#191e17" stroke="#a4b18e" stroke-width="2"/><circle cx="${x}" cy="${y}" r="${r / 3}" fill="#a4b18e" stroke="none"/>`;
    const body = {
      van: `<path d="M18 60V47l15-21h76c11 0 20 10 20 21v15H18Z" fill="#aabd8b"/><path d="m24 46 12-15h24v15Z" fill="#303e32"/><path d="M68 31h23v15H68Z" fill="#303e32"/><path d="M99 31h9c8 0 14 7 15 15H99Z" fill="#303e32"/><path d="M62 29v32m33-33v33M18 52h111"/><path d="M49 50h7m21 0h6"/>${wheel(41)}${wheel(108)}`,
      pickup: `<path d="M16 60V45l17-18h28l13 18h56v17H16Z" fill="#aabd8b"/><path d="m24 44 12-12h21l8 12Z" fill="#303e32"/><path d="M75 44V32h55v15M70 47v16M16 51h114"/><path d="M52 49h7"/>${wheel(37)}${wheel(109)}`,
      light_truck: `<rect x="60" y="20" width="69" height="40" rx="3" fill="#aabd8b"/><path d="M14 61V40l14-13h25v35Z" fill="#c6d5a8"/><path d="m20 42 11-10h15v15H20Z" fill="#303e32"/><path d="M62 61H130M55 57h5M68 28h53M68 34h53M39 51h8"/>${wheel(34)}${wheel(108)}`,
      truck: `<rect x="57" y="13" width="76" height="47" rx="3" fill="#aabd8b"/><path d="M10 62V33l15-13h26v43Z" fill="#c6d5a8"/><path d="m17 35 11-10h15v20H17Z" fill="#303e32"/><path d="M59 63h75M65 22h60M65 29h60M35 51h9M51 57h7"/>${wheel(30, 66, 9)}${wheel(105, 66, 9)}${wheel(125, 66, 9)}`,
      tractor_trailer: `<rect x="54" y="14" width="100" height="42" rx="3" fill="#aabd8b"/><path d="M4 62V37l11-13h26v38Z" fill="#c6d5a8"/><path d="m10 39 9-10h15v17H10Z" fill="#303e32"/><path d="M40 60h114M49 55v8M62 22h84M62 29h84M26 51h8"/>${wheel(22, 66, 7)}${wheel(47, 66, 7)}${wheel(116, 66, 7)}${wheel(133, 66, 7)}${wheel(150, 66, 7)}`,
      car: `<path d="m15 58 7-13 22-6 16-17h36l20 18 17 7 5 14H15Z" fill="#aabd8b"/><path d="m49 39 14-12h12v12Zm32-12h12l14 12H81Z" fill="#303e32"/><path d="M43 45h73M77 41v20M61 47h7M96 47h7"/>${wheel(40)}${wheel(114)}`,
      motorcycle: `${wheel(36, 62, 14)}${wheel(119, 62, 14)}<path d="m36 61 23-27 20 28H36l29-18h33l-19 18M97 27l22 35M94 27h17M66 35h21" fill="none" stroke="#c6d5a8" stroke-width="4"/><path d="m64 34 12-12h18l5 19H72Z" fill="#aabd8b"/><path d="M47 30h21" stroke-width="5"/>`,
      other: `<rect x="24" y="24" width="103" height="37" rx="8" fill="#37422e" stroke-dasharray="5 4"/><path d="M68 34a8 8 0 0 1 16 1c0 7-9 6-9 13M75 53h.1" fill="none" stroke="#c6d5a8" stroke-width="3"/>${wheel(44)}${wheel(108)}`,
    };
    return `<svg class="vehicle-illustration" viewBox="0 0 160 84" fill="none" stroke="#536448" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M8 75h144" stroke="#58644a" opacity=".45"/>${body[type] || body.other}</svg>`;
  }
  function vehicleTypePicker(types, selected) {
    const hints = {
      van: 'هيكل مغلق مدمج',
      light_truck: 'كابينة وصندوق خفيف',
      tractor_trailer: 'رأس سحب ومقطورة',
      pickup: 'حوض تحميل خلفي',
      truck: 'شاحنة بصندوق كبير',
      car: 'مركبة خفيفة',
      motorcycle: 'توصيل خفيف',
      other: 'حدّد التفاصيل في الطراز',
    };
    const order = ['van', 'light_truck', 'tractor_trailer', 'pickup', 'truck', 'car', 'motorcycle', 'other'];
    return `<fieldset class="vehicle-type-fieldset"><legend>نوع المركبة</legend><p class="admin-note">اختر الشكل الأقرب لمركبتك. الرسوم توضيحية للنوع؛ أدخل الصانع والطراز والحمولة الفعلية في حقولها.</p><div class="vehicle-type-grid">${order
      .filter((key) => types[key])
      .map(
        (key) =>
          `<label class="vehicle-type-card"><input type="radio" name="vehicle_type" value="${key}" required ${key === selected ? 'checked' : ''}><span class="vehicle-type-check" aria-hidden="true">✓</span>${vehicleArt(key)}<span class="vehicle-type-name">${esc(types[key])}</span><small>${esc(hints[key])}</small></label>`,
      )
      .join(
        '',
      )}</div>${selected === 'unspecified' ? '<p class="vehicle-type-warning">نوع هذه المركبة غير مسجل. اختر النوع الصحيح لإكمال التعديل.</p>' : ''}</fieldset>`;
  }
  const driverSummary = (data, driverId) => {
    const records = data.vehicle_assignments.filter((a) => a.driver_id === driverId);
    return {
      records,
      current: records.find((a) => !a.returned_at),
      cars: new Set(records.map((a) => a.vehicle_id)).size,
    };
  };
  function team(c) {
    const { data: d, btn, table, tr, metric, amount, match, tab } = c;
    const members = c.role === 'manager' ? d.member_directory : d.team;
    const reps = members.filter((m) => m.role === 'representative');
    const counts = `<div class="metrics">${metric('مناديب فعالون', reps.filter((m) => m.is_active).length, 'مندوب', 'متاحون للإسناد بحسب التغطية', 'users')}${metric('حسابات معلقة', members.filter((m) => !m.is_active).length, 'حساب', 'محفوظة مع تاريخ العمل', 'shield')}${metric('دليل السائقين', d.drivers.length, 'سائق', 'ملفات مستقلة عن حسابات المناديب', 'users')}${metric('تغطية محددة', reps.filter((m) => m.area_access === 'selected').length, 'مندوب', 'المناطق يحددها مدير الشركة', 'pin')}</div>`;
    const tabs = `<div class="admin-toolbar"><div class="queue-tabs" aria-label="فئات الفريق">${[
      ['members', 'كل الحسابات'],
      ['representatives', 'المناديب'],
      ['suspended', 'المعلقون'],
      ['drivers', 'السائقون'],
    ]
      .map(
        ([k, v]) =>
          `<button data-admin-tab="${k}" class="${tab === k ? 'selected' : ''}" aria-pressed="${tab === k}">${v}</button>`,
      )
      .join('')}</div>${c.manager ? btn('driver.create', '', 'إضافة سائق', 'secondary-btn') : ''}</div>`;
    if (tab === 'drivers') {
      const rows = d.drivers.filter(match).map((driver) => {
        const s = driverSummary(d, driver.id),
          car = d.vehicles.find((v) => v.id === s.current?.vehicle_id);
        return tr([
          `<button class="record-link" data-admin-record="driver" data-id="${driver.id}">${esc(driver.name)}</button><small>${esc(driver.employee_code)}</small>`,
          esc(driver.phone || 'غير مسجل'),
          driver.license_number
            ? `${esc(driver.license_number)}<small>تنتهي ${esc(driver.license_expires_on || 'غير محدد')}</small>`
            : badge('الرخصة غير مكتملة', false),
          car ? esc(car.name) : 'دون عهدة سيارة',
          `${s.cars} سيارة / ${s.records.length} إسناد`,
          badge(driver.status === 'active' ? 'فعال' : 'موقوف', driver.status === 'active'),
          c.manager ? btn('driver.update', driver.id, 'تعديل') : '—',
        ]);
      });
      return (
        counts +
        tabs +
        panel(
          'السائقون وعُهد السيارات',
          `<p class="admin-note">السائق ملف تشغيلي مستقل؛ إضافته لا تنشئ حساب دخول. افتح ملفه لمعرفة السيارة الحالية وكل الإسنادات السابقة.</p>${rows.length ? table(['السائق / الرمز', 'الهاتف', 'الرخصة', 'العهدة الحالية', 'سجل الاستلام', 'الحالة', 'الإجراء'], rows) : blankState('لم تُضف سائقين بعد', 'أضف ملف السائق ورخصته، ثم سلّمه سيارة من قسم الأسطول.', c.manager ? btn('driver.create', '', 'إضافة أول سائق', 'primary-btn') : '')}`,
        )
      );
    }
    const filtered = members.filter(
      (m) =>
        match(m) &&
        (tab !== 'representatives' || m.role === 'representative') &&
        (tab !== 'suspended' || !m.is_active),
    );
    const rows = filtered.map((m) => {
      const t = d.trips.find((t) => t.representative_id === m.id && t.status === 'active');
      const debt = d.debt_representatives.find((r) => r.representative_id === m.id);
      const assigned = (d.representative_vehicle_assignments || []).find(
        (a) => a.representative_id === m.id && !a.returned_at,
      );
      const repActions =
        c.manager && m.role === 'representative'
          ? btn('representative.details', m.id, 'تعديل البيانات') +
            btn('representative.vehicle', m.id, 'تغيير المركبة')
          : '';
      const actions =
        c.role === 'manager' && m.role !== 'manager' && m.id !== d.user.id
          ? btn('member.update', m.id, 'تعديل وصلاحيات') +
            btn('member.status', m.id, m.is_active ? 'تعليق الدخول' : 'إعادة التفعيل')
          : badge(m.role === 'manager' ? 'حساب مدير' : 'عرض');
      return tr([
        `<button class="record-link" data-admin-record="member" data-id="${m.id}">${esc(m.name)}</button><small>${esc(m.username || m.employee_code || '')}</small>`,
        esc(roles[m.role] || 'مدير الشركة'),
        badge(m.is_active ? 'فعال' : 'معلق', m.is_active),
        m.role === 'representative' ? esc(scope(m, d)) : 'بحسب الدور',
        assigned
          ? `${esc(assigned.vehicle?.name)}<small>${esc(d.vehicle_types[assigned.vehicle?.vehicle_type] || 'نوع غير محدد')} • ${esc(assigned.vehicle?.plate)}</small>`
          : 'دون تخصيص دائم',
        m.role === 'representative' && c.role === 'manager'
          ? `<div class="badge-list">${badge(m.can_sell ? 'بيع مسموح' : 'البيع مغلق', m.can_sell)}${badge(m.can_collect ? 'تحصيل مسموح' : 'التحصيل مغلق', m.can_collect)}</div>`
          : 'بحسب الدور',
        m.role === 'representative'
          ? `${t ? esc(t.code) : 'دون رحلة نشطة'}<small>آجل فواتيره: ${c.canMoney ? amount(debt?.balance_minor || 0) : 'غير مخول'}</small>`
          : '—',
        `<div class="row-actions">${repActions}${actions}</div>`,
      ]);
    });
    return (
      counts +
      tabs +
      panel(
        'حسابات الفريق والتغطية',
        `<p class="admin-note">إضافة الحساب وتغطيته وصلاحياته من مكان واحد. التعليق يمنع الدخول والعمل في هذه الشركة، ويحفظ المبيعات والديون والرحلات السابقة.</p>${rows.length ? table(['الاسم / التواصل', 'الدور', 'الدخول', 'مناطق الزيارة والبيع', 'مركبة العمل', 'الصلاحيات', 'الرحلة / الآجل', 'الإدارة'], rows) : blankState('لا توجد حسابات بهذه التصفية', 'غيّر البحث أو انتقل إلى كل الحسابات.')}`,
      )
    );
  }
  function fleet(c) {
    const { data: d, metric, btn, table, tr, match, when, day } = c;
    const currentStatus = (v) =>
      d.trips.some((t) => t.vehicle_id === v.id && t.status === 'active') ? 'active' : v.status;
    const filtered = d.vehicles
      .filter(match)
      .filter((v) => !c.fleetStatus || currentStatus(v) === c.fleetStatus);
    const v = filtered.find((v) => v.id === c.selectedVehicle) || filtered[0];
    const expired = d.vehicles.filter((v) =>
      [v.registration_expires_on, v.insurance_expires_on].some((date) => date && date < d.today),
    );
    const summary = `<div class="metrics">${metric('سيارات الشركة', d.vehicles.length, 'سيارة', 'سجل موحد للأنواع والبيانات', 'truck')}${metric('تحتاج مراجعة النوع', d.vehicles.filter((v) => v.vehicle_type === 'unspecified').length, 'سيارة', 'لا نفترض نوع السيارات القديمة', 'clock')}${metric('دون سائق مستلم', d.vehicles.filter((v) => !activeAssignment(d, v.id)).length, 'سيارة', 'إسناد السائق مستقل عن مندوب الرحلة', 'users')}${metric('وثائق منتهية', expired.length, 'سيارة', 'بحسب التواريخ المسجلة', 'clock')}</div>`;
    const tabs = `<div class="admin-toolbar"><div class="queue-tabs">${[
      ['', 'كل السيارات'],
      ['available', 'متاحة'],
      ['active', 'في رحلة'],
      ['maintenance', 'صيانة'],
      ['inactive', 'موقوفة'],
    ]
      .map(
        ([k, v]) =>
          `<button data-fleet-status="${k}" class="${c.fleetStatus === k ? 'selected' : ''}">${v}</button>`,
      )
      .join('')}</div><a href="/team?tab=drivers" class="secondary-btn">دليل السائقين ←</a></div>`;
    const rows = filtered.map((car) => {
      const a = activeAssignment(d, car.id),
        t = d.trips.find((t) => t.vehicle_id === car.id && t.status === 'active');
      return tr([
        `<button class="record-link" data-vehicle="${car.id}" aria-pressed="${car.id === v?.id}">${esc(car.name)}</button><small dir="ltr">${esc(car.plate)}</small>`,
        `<div class="fleet-type-cell">${vehicleArt(car.vehicle_type)}${badge(d.vehicle_types[car.vehicle_type] || 'غير محدد', car.vehicle_type !== 'unspecified')}</div>`,
        esc([car.make, car.model, car.model_year].filter(Boolean).join(' • ') || 'لم تُستكمل'),
        a ? esc(a.driver.name) : 'دون عهدة سائق حالية',
        car.payload_kg ? `${car.payload_kg} كجم` : 'غير مسجلة',
        badge(t ? 'في رحلة' : states[car.status], !['maintenance', 'inactive'].includes(car.status)),
        c.manager ? btn('vehicle.update', car.id, 'تعديل') : '—',
      ]);
    });
    if (!v)
      return (
        summary +
        tabs +
        blankState(
          'لا توجد سيارات بهذه التصفية',
          'أضف سيارة ببياناتها ونوعها، أو غيّر الفلاتر.',
          c.manager ? btn('vehicle.create', '', 'إضافة سيارة', 'primary-btn') : '',
        )
      );
    const a = activeAssignment(d, v.id),
      history = d.vehicle_assignments.filter((a) => a.vehicle_id === v.id),
      t = d.trips.find((t) => t.vehicle_id === v.id && t.status === 'active'),
      warehouse = d.warehouses.find((w) => w.vehicle_id === v.id);
    const current = `<div class="admin-record-title"><span class="fleet-record-art">${vehicleArt(v.vehicle_type)}</span><div><small>ملف السيارة المحددة</small><h2>${esc(v.name)}</h2><span dir="ltr">${esc(v.plate)}</span></div>${badge(d.vehicle_types[v.vehicle_type] || 'غير محدد', v.vehicle_type !== 'unspecified')}</div>`;
    const facts = details([
      ['الصانع / الطراز', esc([v.make, v.model, v.model_year].filter(Boolean).join(' • ') || 'غير مسجل')],
      ['رقم الهيكل', esc(v.vin || 'غير مسجل')],
      [
        'الملكية',
        esc(
          {
            owned: 'مملوكة',
            leased: 'تأجير تمويلي',
            rented: 'مستأجرة',
          }[v.ownership],
        ),
      ],
      ['العداد', v.odometer_km != null ? `${v.odometer_km} كم` : 'غير مسجل'],
      ['حمولة معلنة', v.payload_kg ? `${v.payload_kg} كجم` : 'غير مسجلة'],
      ['حجم معلن', v.volume_m3 ? `${v.volume_m3} م³` : 'غير مسجل'],
      ['الاستمارة', esc(v.registration_expires_on || 'غير مسجلة')],
      ['التأمين', esc(v.insurance_expires_on || 'غير مسجل')],
      ['السائق المستلم', a ? esc(a.driver.name) : 'لا يوجد إسناد قائم'],
      ['مندوب الرحلة', t ? esc(t.representative.name) : 'لا توجد رحلة نشطة'],
    ]);
    const actions = `<div class="admin-toolbar">${c.manager ? btn('vehicle.update', v.id, 'تعديل البيانات', 'secondary-btn') + (a ? btn('vehicle.release', a.id, 'استرجاع من السائق', 'secondary-btn') : btn('vehicle.assign', v.id, 'تسليم لسائق', 'primary-btn')) : ''}${warehouse ? `<a href="/inventory?warehouse=${warehouse.id}" class="secondary-btn">مخزون السيارة</a>` : ''}${t ? `<a href="/tracking?trip=${t.id}" class="secondary-btn">تتبع الرحلة</a>` : ''}</div>`;
    const historyRows = history.map((a) =>
      tr([
        esc(a.driver.name),
        when(a.assigned_at),
        a.returned_at ? when(a.returned_at) : badge('عهدة قائمة'),
        esc(a.handover_note),
        esc(a.return_note || '—'),
      ]),
    );
    return (
      summary +
      tabs +
      panel(
        'دليل المركبات',
        table(
          ['السيارة / اللوحة', 'النوع', 'الصانع والطراز', 'السائق المستلم', 'الحمولة', 'الحالة', 'الإدارة'],
          rows,
          'اختر اسم السيارة لعرض ملفها وسجل تسليمها أدناه.',
        ),
      ) +
      `<div class="admin-detail-layout"><section class="panel admin-panel">${current}${facts}${v.notes ? `<p class="admin-note">${esc(v.notes)}</p>` : ''}${actions}<p class="admin-note">حد المخزون العددي: ${v.capacity_units} وحدة. الوزن والحجم هنا بيانات معلنة؛ حساب الحمولة الفعلية يحتاج أوزان وأحجام المنتجات.</p></section>${panel('سجل استلام السيارة وإرجاعها', historyRows.length ? table(['السائق', 'التسليم', 'الاسترجاع', 'محضر التسليم', 'محضر الإرجاع'], historyRows) : blankState('لا يوجد إسناد موثق بعد', 'اسم مندوب الرحلة لا يُعد سجل استلام سيارة. سلّم السيارة لسائق لحفظ أول محضر.'))}</div>`
    );
  }
  function dashboard(c) {
    const { data: d, metric, btn, table, tr, amount, day } = c;
    const stale = d.trips.filter((t) => t.status === 'active' && t.scheduled_for.slice(0, 10) < d.today);
    const drafts = d.orders.filter((o) => o.status === 'draft'),
      pending = d.cash_handovers.filter((h) => h.status === 'pending');
    const unknown = d.vehicles.filter((v) => v.vehicle_type === 'unspecified');
    const actions = [
      [
        stale.length,
        'رحلات نشطة من أيام سابقة',
        'تحتاج مراجعة حالة التنفيذ والزيارات قبل الإغلاق.',
        '/trips',
        'مراجعة الرحلات',
      ],
      [drafts.length, 'طلبات لم تُسلّم', 'راجع المسودات والمخزون ومسؤول التسليم.', '/orders', 'فتح الطلبات'],
      [
        pending.length,
        'تسليمات نقد تنتظر القرار',
        'طابق المبلغ المستلم ثم اعتمد أو ارفض مع السبب.',
        '/settlements',
        'مراجعة النقد',
      ],
      [
        unknown.length,
        'سيارات بيانات نوعها ناقصة',
        'أكمل النوع والوثائق والسعة من ملف السيارة.',
        '/vehicles',
        'إكمال الأسطول',
      ],
    ];
    const work = actions
      .map(
        ([n, title, text, url, label]) =>
          `<article class="decision-card"><div><span class="decision-count">${n}</span>${badge(n ? 'تحتاج مراجعة' : 'لا توجد حالات', !n)}</div><h3>${esc(title)}</h3><p>${esc(text)}</p><a class="secondary-btn" href="${url}">${esc(label)} ←</a></article>`,
      )
      .join('');
    const rows = d.trips
      .filter((t) => t.scheduled_for.slice(0, 10) === d.today)
      .map((t) => {
        const visits = d.visits.filter((v) => v.trip_id === t.id);
        return tr([
          esc(t.code),
          esc(t.representative.name),
          esc(t.vehicle.name),
          `${visits.filter((v) => v.status === 'completed').length} / ${visits.length}`,
          c.pill(t.status),
          `<a class="record-link" href="/tracking?trip=${t.id}">تفاصيل التنفيذ ←</a>`,
        ]);
      });
    return `<div class="admin-intro"><div><span class="eyebrow">عمل اليوم • ${esc(day(d.today + 'T12:00:00'))}</span><h2>اعرف ما يحتاج تدخلك، ثم ابدأ منه.</h2><p>أعمال اليوم مستقلة عن الرحلات المتراكمة. الأرصدة المالية الحالية تشمل تاريخ الشركة.</p></div><a class="secondary-btn" href="/tracking">فتح مركز التتبع ←</a></div><div class="metrics">${metric('زيارات اليوم', d.metrics.today_visits, 'زيارة', `${d.metrics.completed_today} مكتملة`, 'check')}${metric('رحلات نشطة', d.metrics.active_trips, 'رحلة', `${stale.length} منها بتاريخ سابق`, 'route')}${metric('مناديب متاحون', d.representatives.length, 'مندوب', 'حسابات فعالة في الشركة', 'users')}${c.canMoney ? metric('الآجل الحالي', amount(d.metrics.unpaid_minor), '', `المتأخر: ${amount(d.metrics.overdue_minor)}`, 'wallet') : metric('مناطق الخدمة', d.areas.length, 'منطقة', 'ضمن الدليل المحمّل', 'pin')}</div><section id="attention"><div class="admin-section-title"><h2>قائمة مراجعة المدير</h2><span>من السجلات المحمّلة • افتح القسم لمراجعة الحالة</span></div><div class="decision-grid">${work}</div></section>${panel('خطة اليوم ومسؤول التنفيذ', rows.length ? table(['الرحلة', 'المندوب', 'السيارة', 'الإنجاز', 'الحالة', 'المتابعة'], rows) : blankState('لا توجد رحلات مجدولة لهذا اليوم', 'البيانات التجريبية السابقة محفوظة بتاريخها. خطط رحلة اليوم لتظهر هنا.', c.manager ? btn('trip.create', '', 'تخطيط رحلة اليوم', 'primary-btn') : ''))}<div class="admin-shortcuts"><a href="/team"><strong>الفريق والصلاحيات</strong><span>حسابات المناديب، المناطق، والسائقون ←</span></a><a href="/vehicles"><strong>الأسطول والعُهد</strong><span>الأنواع، البيانات، وتسليم السيارات ←</span></a><a href="/reports"><strong>مراجعة أداء الفترة</strong><span>المبيعات والتحصيل والزيارات ←</span></a></div>`;
  }
  function form(action, id, c) {
    const d = c.data,
      edit = action.endsWith('.update'),
      reason = edit ? note('reason', 'سبب التعديل', '', true) : '';
    if (['member.create', 'member.update'].includes(action)) {
      const m = edit
        ? d.member_directory.find((m) => m.id === Number(id))
        : {
            role: 'representative',
            area_access: 'selected',
            area_ids: [],
            is_active: true,
            can_sell: true,
            can_collect: true,
          };
      if (!m) return null;
      const locked = m.shared_account ? 'readonly' : '';
      const identity =
        pair(
          input('name', 'الاسم الكامل', 'text', m.name || '', `required maxlength="150" ${locked}`) +
            input(
              'username',
              'اسم المستخدم',
              'text',
              m.username || '',
              `required minlength="2" maxlength="40" pattern="[a-z0-9][a-z0-9._\\-]{1,39}" autocomplete="off" autocapitalize="none" dir="ltr" ${locked}`,
            ),
        ) +
        pair(
          input('phone', 'الهاتف', 'tel', m.phone || '', 'maxlength="30"') +
            input('employee_code', 'الرقم الوظيفي', 'text', m.employee_code || '', 'maxlength="40"'),
        );
      const password = m.shared_account
        ? '<p class="admin-note">حساب مشترك مع شركة أخرى؛ تعديل الهوية وكلمة المرور يحتاج إدارة الحساب العامة.</p>'
        : pair(
            input(
              'password',
              edit ? 'كلمة مرور جديدة (اختياري)' : 'كلمة المرور',
              'password',
              '',
              `${edit ? '' : 'required'} minlength="${d.password_min_length}" maxlength="128" autocomplete="new-password"`,
            ) +
              input(
                'password_confirmation',
                'تأكيد كلمة المرور',
                'password',
                '',
                `minlength="${d.password_min_length}" maxlength="128" autocomplete="new-password"`,
              ),
          );
      const coverage =
        choose(
          'area_access',
          'نطاق مناطق الزيارة والبيع',
          { selected: 'مناطق محددة فقط', all: 'كل مناطق الشركة' },
          m.area_access,
        ) +
        `<div class="field"><span>المناطق المسموحة عند اختيار «مناطق محددة»</span><div class="area-picker">${d.areas.map((a) => `<label class="permission-check"><input type="checkbox" name="area_ids" value="${a.id}" ${m.area_ids.includes(a.id) ? 'checked' : ''}><span>${esc(a.name)}<small>${esc(a.city)}</small></span></label>`).join('') || '<p>أضف مناطق أولًا من قسم العملاء والمناطق.</p>'}</div><small>عدم اختيار مناطق يعني منع إسناد زيارات أو مبيعات جديدة. الاطلاع على مستنداته التاريخية وتسوية عهدته يبقيان محفوظين.</small></div>`;
      return {
        title: edit ? `تعديل وصلاحيات • ${m.name}` : 'إضافة مندوب / عضو فريق',
        extra: edit ? { id: Number(id) } : {},
        html:
          block('بيانات الحساب', identity + password) +
          block(
            'الدور وحالة الدخول',
            pair(
              choose('role', 'الدور', roles, m.role) +
                choose('is_active', 'الدخول إلى الشركة', { 1: 'فعال', 0: 'معلق' }, m.is_active ? '1' : '0'),
            ),
          ) +
          block(
            'صلاحيات المندوب',
            checkbox('can_sell', 'السماح بإنشاء وتسليم المبيعات', m.can_sell) +
              checkbox('can_collect', 'السماح باستلام مبالغ من العملاء', m.can_collect) +
              coverage,
            'تطبق خيارات هذا القسم على دور المندوب، وتتحقق منها واجهة الخادم عند التنفيذ.',
          ) +
          note('notes', 'ملاحظات إدارية', m.notes || '') +
          reason,
      };
    }
    if (action === 'member.status') {
      const m = d.member_directory.find((m) => m.id === Number(id));
      if (!m) return null;
      return {
        title: `${m.is_active ? 'تعليق دخول' : 'تفعيل'} • ${m.name}`,
        extra: { id: m.id, is_active: !m.is_active },
        html:
          `<div class="info-box">${m.is_active ? 'سيُمنع الحساب من العمل في هذه الشركة من الطلب التالي، مع بقاء المبيعات والآجل والرحلات والعهد محفوظة. التعليق لا يُغلق رحلة ولا يسقط دينًا.' : 'سيتمكن العضو من الدخول مجددًا وفق دوره وصلاحياته الحالية.'}</div>` +
          note('reason', 'سبب القرار', '', true),
      };
    }
    if (['vehicle.create', 'vehicle.update'].includes(action)) {
      const v = edit
        ? d.vehicles.find((v) => v.id === Number(id))
        : { status: 'available', ownership: 'owned' };
      if (!v) return null;
      return {
        title: edit ? `تعديل المركبة • ${v.name}` : 'إضافة مركبة',
        extra: edit ? { id: v.id } : {},
        html:
          vehicleTypePicker(d.vehicle_types, v.vehicle_type) +
          block(
            'تعريف المركبة',
            pair(
              input('name', 'اسم تعريفي', 'text', v.name || '', 'required maxlength="150"') +
                input('plate', 'رقم اللوحة', 'text', v.plate || '', 'required maxlength="40"'),
            ) +
              choose('status', 'حالة التشغيل', states, v.status) +
              pair(
                input('make', 'الشركة الصانعة', 'text', v.make || '', 'maxlength="80"') +
                  input('model', 'الطراز', 'text', v.model || '', 'maxlength="80"'),
              ) +
              pair(
                input('model_year', 'سنة الصنع', 'number', v.model_year || '', 'min="1950" max="2100"') +
                  input('vin', 'رقم الهيكل', 'text', v.vin || '', 'maxlength="80"'),
              ),
          ) +
          block(
            'السعة والملكية',
            pair(
              input(
                'payload_kg',
                'الحمولة المعلنة • كجم',
                'number',
                v.payload_kg || '',
                'min="1" max="200000"',
              ) +
                input(
                  'volume_m3',
                  'حجم التحميل المعلن • م³',
                  'number',
                  v.volume_m3 || '',
                  'min="0.01" max="1000" step="0.01"',
                ),
            ) +
              pair(
                choose(
                  'ownership',
                  'الملكية',
                  {
                    owned: 'مملوكة',
                    leased: 'تأجير تمويلي',
                    rented: 'مستأجرة',
                  },
                  v.ownership,
                ) +
                  input(
                    'odometer_km',
                    'قراءة العداد • كم',
                    'number',
                    v.odometer_km ?? '',
                    'min="0" max="9999999"',
                  ),
              ) +
              input(
                'capacity_units',
                'حد وحدات المخزون العددي',
                'number',
                v.capacity_units || '',
                'required min="1" max="100000"',
              ),
            'الوزن والحجم بيانات معلنة. حد الوحدات قيد مخزني عددي، وليس حساب وزن فعلي للحمولة.',
          ) +
          block(
            'الوثائق',
            pair(
              input('registration_expires_on', 'انتهاء الاستمارة', 'date', v.registration_expires_on || '') +
                input('insurance_expires_on', 'انتهاء التأمين', 'date', v.insurance_expires_on || ''),
            ),
          ) +
          note('notes', 'ملاحظات', v.notes || '') +
          reason +
          '<p class="admin-note">يُسلّم السائق السيارة بإجراء مستقل من ملفها، ليظل تاريخ العهدة محفوظًا.</p>',
      };
    }
    if (['driver.create', 'driver.update'].includes(action)) {
      const v = edit ? d.drivers.find((v) => v.id === Number(id)) : { status: 'active' };
      if (!v) return null;
      return {
        title: edit ? `تعديل السائق • ${v.name}` : 'إضافة ملف سائق',
        extra: edit ? { id: v.id } : {},
        html:
          block(
            'بيانات السائق',
            pair(
              input('name', 'الاسم', 'text', v.name || '', 'required maxlength="150"') +
                input(
                  'employee_code',
                  'الرقم الوظيفي',
                  'text',
                  v.employee_code || '',
                  'required maxlength="40"',
                ),
            ) +
              pair(
                input('phone', 'الهاتف', 'tel', v.phone || '', 'maxlength="30"') +
                  choose('status', 'الحالة', { active: 'فعال', inactive: 'موقوف' }, v.status),
              ),
          ) +
          block(
            'حساب الاستلام',
            choose(
              'user_id',
              'حساب دخول السائق',
              Object.fromEntries((d.driver_accounts || []).map((u) => [u.id, `${u.name} • ${u.username}`])),
              v.user_id,
              true,
            ),
            'أنشئ حسابًا بدور سائق من الفريق ثم اربطه هنا. الموافقة تصدر من هذا الحساب فقط.',
          ) +
          block(
            'رخصة القيادة',
            pair(
              input('license_number', 'رقم الرخصة', 'text', v.license_number || '', 'maxlength="80"') +
                input('license_expires_on', 'انتهاء الرخصة', 'date', v.license_expires_on || ''),
            ),
            'يمكن حفظ الملف أولًا، ويُمنع تسليم سيارة قبل اكتمال الرخصة وسريانها.',
          ) +
          note('notes', 'ملاحظات', v.notes || '') +
          reason,
      };
    }
    if (action === 'vehicle.assign') {
      const v = d.vehicles.find((v) => v.id === Number(id));
      if (!v) return null;
      return {
        title: `تسليم سيارة • ${v.name}`,
        extra: { vehicle_id: v.id },
        html:
          '<div class="info-box">سيصل طلب للسائق في إشعارات حسابه. لا تثبت عهدة السيارة حتى يراجع حالتها ويوافق، ثم يصدر محضر للطباعة والتوقيع. اربط السائق بحساب دخوله أولًا.</div>' +
          choose(
            'driver_id',
            'السائق المستلم',
            listOptions(d.drivers.filter((x) => x.status === 'active' && x.user_id)),
            null,
            true,
          ) +
          note('note', 'محضر التسليم / الحالة والملاحظات', '', true),
      };
    }
    if (action === 'vehicle.release')
      return {
        title: 'استرجاع السيارة من السائق',
        extra: { id: Number(id) },
        html:
          note('note', 'محضر الإرجاع / الحالة والملاحظات', '', true) +
          '<p class="admin-note">يصل طلب إقرار الإرجاع للسائق. ينتهي الإسناد بعد موافقته، مع بقاء تاريخه ومحضر الإرجاع.</p>',
      };
    return null;
  }
  function payload(action, f, p) {
    if (['member.create', 'member.update'].includes(action)) {
      p.area_ids = f.getAll('area_ids').map(Number);
      p.is_active = p.is_active === '1';
      p.can_sell = f.has('can_sell');
      p.can_collect = f.has('can_collect');
      if (!p.password) {
        delete p.password;
        delete p.password_confirmation;
      }
    }
    if (action === 'vehicle.assign') p.driver_id = Number(p.driver_id);
    if (['driver.create', 'driver.update'].includes(action) && 'user_id' in p)
      p.user_id = p.user_id ? Number(p.user_id) : null;
    return p;
  }
  function record(kind, id, c) {
    const d = c.data;
    if (kind === 'member') {
      const m = (c.role === 'manager' ? d.member_directory : d.team).find((m) => m.id === Number(id));
      if (!m) return null;
      const trips = d.trips.filter((t) => t.representative_id === m.id),
        orders = d.orders.filter((o) => o.representative_id === m.id && o.status === 'fulfilled');
      return {
        title: m.name,
        html:
          window.AmrbitMaintenance.repRecord(m, c) +
          details([
            ['الدور', esc(roles[m.role] || 'مدير الشركة')],
            ['الدخول', badge(m.is_active ? 'فعال' : 'معلق', m.is_active)],
            ['اسم المستخدم', esc(m.username || 'بحسب صلاحيتك')],
            ['الهاتف', esc(m.phone || 'غير مسجل')],
            ['التغطية', m.role === 'representative' ? esc(scope(m, d)) : 'بحسب الدور'],
          ]) +
          `<h3>الرحلات ضمن السجل المحمّل</h3>` +
          (trips.length
            ? c.table(
                ['الرحلة', 'الموعد', 'السيارة', 'الحالة'],
                trips.map((t) =>
                  c.tr([esc(t.code), c.day(t.scheduled_for), esc(t.vehicle.name), c.pill(t.status)]),
                ),
              )
            : blankState('لا توجد رحلات مسجلة لهذا العضو', 'تظهر إسنادات المندوب هنا عند التخطيط.')) +
          (m.role === 'representative' && c.canMoney
            ? `<p class="admin-note">مبيعات مسلّمة ضمن السجل المحمّل: ${c.amount(orders.reduce((s, o) => s + o.total_minor, 0))}. الإجماليات الكاملة من التقارير بفلاتر المندوب.</p>`
            : ''),
      };
    }
    const driver = d.drivers.find((x) => x.id === Number(id));
    if (!driver) return null;
    const s = driverSummary(d, driver.id);
    return {
      title: driver.name,
      html:
        details([
          ['الرقم الوظيفي', esc(driver.employee_code)],
          ['الرخصة', esc(driver.license_number || 'غير مسجلة')],
          ['انتهاء الرخصة', esc(driver.license_expires_on || 'غير مسجل')],
          ['عدد السيارات المختلفة', s.cars],
          ['مرات استلام سيارة', s.records.length],
        ]) +
        c.table(
          ['السيارة', 'اللوحة', 'التسليم', 'الإرجاع', 'ملاحظات'],
          s.records.map((a) => {
            const v = d.vehicles.find((v) => v.id === a.vehicle_id);
            return c.tr([
              esc(v?.name || '—'),
              esc(v?.plate || '—'),
              c.when(a.assigned_at),
              a.returned_at ? c.when(a.returned_at) : 'عهدة قائمة',
              esc(a.handover_note),
            ]);
          }),
          'التاريخ يشمل الإسنادات السابقة والحالية.',
        ),
    };
  }
  function reports(stats, c) {
    const rows = (stats.performance || []).map((r) =>
      c.tr([
        esc(r.name),
        r.visits ?? '—',
        r.completed ?? '—',
        r.unsuccessful ?? '—',
        r.orders ?? '—',
        c.canMoney ? c.amount(r.sales_minor) : 'غير مخول',
        c.canMoney ? c.amount(r.collections_minor) : 'غير مخول',
        c.canMoney ? c.amount(r.remaining_minor) : 'غير مخول',
      ]),
    );
    const performance = panel(
      'أداء المندوبين خلال الفترة',
      rows.length
        ? c.table(
            [
              'المندوب',
              'الزيارات',
              'المكتملة',
              'متعذرة / متجاوزة',
              'الطلبات المسلّمة',
              'المبيعات',
              'تحصيل فواتيره خلال الفترة',
              'متبقي طلبات الفترة الآن',
            ],
            rows,
            'المبيعات حسب إنشاء الطلب المسلّم؛ التحصيل حسب تاريخ الإيصال على فواتير المندوب، وليس بالضرورة اسم مستلم المال.',
          )
        : blankState(
            'لا توجد حركة ضمن الفترة والفلاتر',
            'اختر فترة تحتوي نشاطًا؛ لا نستبدل الصفر ببيانات من تاريخ آخر.',
          ),
    );
    const methods = c.canMoney
      ? panel(
          'التحصيل حسب وسيلة الاستلام',
          c.table(
            ['الوسيلة', 'عدد الإيصالات', 'المبلغ'],
            (stats.collection_methods || []).map((r) =>
              c.tr([
                esc(
                  {
                    cash: 'نقدي',
                    bank: 'تحويل بنكي',
                    card: 'بطاقة',
                  }[r.method] || r.method,
                ),
                r.receipts,
                c.amount(r.amount_minor),
              ]),
            ),
            'هذه وسائل الإيصالات المسجلة؛ المطابقة المصرفية التفصيلية ليست منفذة بعد.',
          ),
        )
      : '';
    return performance + methods;
  }
  return {
    team,
    fleet,
    dashboard,
    form,
    payload,
    record,
    reports,
    vehicleArt,
  };
})();
