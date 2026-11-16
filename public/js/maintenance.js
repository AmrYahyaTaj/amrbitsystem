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
  const field = (n, l, t = 'text', v = '', a = '') =>
    `<div class="field"><label for="maint-${n}">${e(l)}</label><input id="maint-${n}" name="${n}" type="${t}" value="${e(v)}" ${a}></div>`;
  const note = (n, l, v = '', required = false) =>
    `<div class="field"><label for="maint-${n}">${e(l)}</label><textarea id="maint-${n}" name="${n}" maxlength="1000" ${required ? 'required minlength="3"' : ''}>${e(v)}</textarea></div>`;
  const select = (n, l, options) =>
    `<div class="field"><label for="maint-${n}">${e(l)}</label><select id="maint-${n}" name="${n}" required>${options}</select></div>`;
  const customer = (id, c) =>
    window.AmrbitJourney.customer()?.id === Number(id)
      ? window.AmrbitJourney.customer()
      : c.data.customers.find((u) => u.id === Number(id));
  const member = (id, c) =>
    (c.role === 'manager' ? c.data.member_directory : c.data.team).find(
      (m) => m.id === Number(id),
    );
  const current = (id, c) =>
    (c.data.representative_vehicle_assignments || []).find(
      (a) => a.representative_id === Number(id) && !a.returned_at,
    );
  function customerActions(u, c) {
    if (!c.manager && c.role !== 'representative') return '';
    return (
      c.btn(
        'customer.location',
        u.id,
        c.manager ? 'تعديل موقع العميل' : 'تصحيح موقع العميل',
        'secondary-btn',
      ) +
      (!c.manager
        ? c.btn(
            'customer.contact',
            u.id,
            'تعديل بيانات التواصل',
            'secondary-btn',
          )
        : '')
    );
  }
  function requests(c) {
    const rows = c.data.customer_changes || [];
    if (!rows.length) return '';
    return `<section class="panel journey-panel"><div class="journey-panel-head"><h2>${c.manager ? 'طلبات العملاء والمواقع للمراجعة' : 'طلباتي للعملاء والمواقع'}</h2><span>${rows.filter((r) => r.status === 'pending').length} بانتظار الاعتماد</span></div>${c.table(
      [
        'العميل / الطلب',
        'النوع',
        'مرسل الطلب',
        'الحالة',
        'التفاصيل',
        'المراجعة',
      ],
      rows.map((r) =>
        c.tr([
          e(r.customer?.name || r.payload.name || 'عميل جديد'),
          r.kind === 'location' ? 'تصحيح موقع' : 'تسجيل عميل',
          e(r.submitter?.name),
          `<span class="pill ${r.status === 'pending' ? 'warning' : r.status === 'rejected' ? 'danger' : ''}">${e({ pending: 'بانتظار الاعتماد', approved: 'معتمد', rejected: 'مرفوض' }[r.status] || r.status)}</span>`,
          e(r.review_note || r.payload.reason || r.payload.address || '—'),
          c.manager && r.status === 'pending'
            ? c.btn('customer.review', r.id, 'مراجعة الطلب', 'secondary-btn')
            : e(r.reviewer?.name || 'لم يراجع بعد'),
        ]),
      ),
      'آخر 100 طلب، وتظهر الطلبات المعلقة أولًا. قبول الطلب أو رفضه محفوظ في سجل العمليات.',
    )}</section>`;
  }
  function repRecord(m, c) {
    if (m.role !== 'representative') return '';
    const assigned = current(m.id, c),
      trip = c.data.trips
        .filter(
          (t) =>
            t.representative_id === m.id &&
            ['active', 'planned'].includes(t.status),
        )
        .sort(
          (a, b) =>
            (a.status === 'active' ? 0 : 1) - (b.status === 'active' ? 0 : 1) ||
            String(a.scheduled_for).localeCompare(String(b.scheduled_for)),
        )[0],
      v = assigned?.vehicle || trip?.vehicle,
      history = (c.data.representative_vehicle_assignments || []).filter(
        (a) => a.representative_id === m.id,
      );
    return `<div class="member-file-actions">${c.manager ? c.btn('representative.details', m.id, 'تعديل بيانات المندوب', 'primary-btn') + c.btn('representative.vehicle', m.id, 'تخصيص / تغيير المركبة', 'secondary-btn') : ''}${c.role === 'manager' ? c.btn('member.update', m.id, 'الحساب والصلاحيات', 'secondary-btn') : ''}</div><section class="representative-vehicle"><div class="representative-vehicle-art">${v ? window.FieldFlowAdmin.vehicleArt(v.vehicle_type) : ''}</div><div><span class="eyebrow">${assigned ? 'المركبة المخصصة للمندوب' : trip ? (trip.status === 'active' ? 'مركبة الرحلة الحالية — دون تخصيص دائم' : 'مركبة الرحلة المخططة — دون تخصيص دائم') : 'مركبة العمل'}</span><h3>${e(v?.name || 'لم تخصص مركبة بعد')}</h3><p>${e(c.data.vehicle_types[v?.vehicle_type] || 'النوع غير محدد')} • ${e(v?.plate || 'دون لوحة مسجلة')}</p>${c.manager && v ? c.btn('vehicle.update', v.id, 'تعديل النوع واللوحة وبيانات المركبة', 'secondary-btn') : ''}</div></section><h3>تاريخ تخصيص المركبات للمندوب</h3>${
      history.length
        ? c.table(
            [
              'المركبة والنوع',
              'اللوحة',
              'من تاريخ',
              'إلى تاريخ',
              'المسؤول / السبب',
            ],
            history.map((a) =>
              c.tr([
                `${e(a.vehicle?.name)}<small>${e(c.data.vehicle_types[a.vehicle?.vehicle_type] || 'نوع غير محدد')}</small>`,
                e(a.vehicle?.plate),
                c.when(a.assigned_at),
                a.returned_at ? c.when(a.returned_at) : 'التخصيص الحالي',
                `${e(a.assigner?.name)}<small>${e(a.return_note || a.assignment_note)}</small>`,
              ]),
            ),
          )
        : '<p class="admin-note">لا يوجد تخصيص موثق حتى الآن. اسم المركبة في رحلة سابقة لا يعني تسليم عهدة السيارة. تخصيص العمل وسجل استلام السائق محفوظان كلٌ على حدة.</p>'
    }`;
  }
  function form(action, id, c) {
    if (action === 'customer.create')
      return {
        title: c.manager ? 'إضافة عميل وموقعه' : 'تسجيل عميل جديد للمراجعة',
        extra:
          c.role === 'representative'
            ? { representative_id: c.data.user.id }
            : {},
        html:
          field('name', 'اسم العميل', 'text', '', 'required maxlength="150"') +
          field('phone', 'رقم التواصل', 'tel', '', 'maxlength="30"') +
          field(
            'code',
            c.manager ? 'رمز العميل' : 'رمز العميل (اختياري)',
            'text',
            '',
            `${c.manager ? 'required' : ''} maxlength="40"`,
          ) +
          select(
            'area_id',
            'المنطقة',
            '<option value="">اختر المنطقة</option>' +
              c.data.areas
                .map(
                  (a) =>
                    `<option value="${a.id}">${e(a.city)} • ${e(a.name)}</option>`,
                )
                .join(''),
          ) +
          (c.role === 'representative'
            ? `<div class="info-box">سيُسند العميل لك بعد اعتماد المدير أو المشرف.</div>`
            : select(
                'representative_id',
                'المندوب المسؤول',
                '<option value="">اختر المندوب</option>' +
                  c.data.representatives
                    .map((r) => `<option value="${r.id}">${e(r.name)}</option>`)
                    .join(''),
              )) +
          field(
            'address',
            'العنوان والوصف للوصول',
            'text',
            '',
            'required maxlength="500"',
          ) +
          `<div class="field-grid">${field('latitude', 'خط العرض', 'number', '', 'required step="any" min="-90" max="90"')}${field('longitude', 'خط الطول', 'number', '', 'required step="any" min="-180" max="180"')}</div>` +
          (c.manager
            ? field(
                'geofence_radius_m',
                'نطاق إثبات الحضور بالمتر',
                'number',
                150,
                'required min="25" max="500"',
              )
            : '<input type="hidden" name="geofence_radius_m" value="150">') +
          note('service_notes', 'تعليمات خدمة العميل') +
          (!c.manager
            ? '<div class="info-box">هذا طلب تسجيل عميل بموقعه. يظهر في «طلباتي» إلى أن يعتمده المدير أو المشرف، ثم يدخل دليل العملاء والجدولة.</div>'
            : ''),
      };
    if (['customer.location', 'customer.contact'].includes(action)) {
      const u = customer(id, c);
      if (!u) return null;
      return {
        title:
          (action === 'customer.location'
            ? 'تعديل موقع • '
            : 'تعديل تواصل • ') + u.name,
        extra: { id: u.id, profile_version: u.profile_version },
        html:
          action === 'customer.location'
            ? `<div class="info-box">${c.manager ? 'اختر الموقع الصحيح ثم احفظ السبب. يبقى إثبات الزيارات السابقة محفوظًا.' : 'أرسل النقطة الصحيحة للمشرف لاعتمادها. لا تتغير نقطة إثبات الحضور قبل الاعتماد.'}</div><div class="field-grid">${field('latitude', 'خط العرض', 'number', u.latitude, 'required step="any" min="-90" max="90"')}${field('longitude', 'خط الطول', 'number', u.longitude, 'required step="any" min="-180" max="180"')}</div>` +
              note('reason', 'سبب تصحيح الموقع', '', true)
            : field(
                'name',
                'اسم العميل',
                'text',
                u.name,
                'required maxlength="150"',
              ) +
              field('phone', 'رقم التواصل', 'tel', u.phone, 'maxlength="30"') +
              field(
                'address',
                'العنوان والوصف',
                'text',
                u.address,
                'required maxlength="500"',
              ) +
              note('service_notes', 'تعليمات الخدمة', u.service_notes) +
              note('reason', 'سبب التعديل', '', true),
      };
    }
    if (action === 'customer.review') {
      const r = c.data.customer_changes.find((r) => r.id === Number(id));
      if (!r) return null;
      const p = r.payload;
      const existing = r.customer_id ? customer(r.customer_id, c) : null;
      const summary =
        r.kind === 'new_customer'
          ? `<dl class="journey-facts"><div><dt>الاسم</dt><dd>${e(p.name)}</dd></div><div><dt>رمز العميل</dt><dd>${e(p.code)}</dd></div><div><dt>الهاتف</dt><dd>${e(p.phone || 'غير مسجل')}</dd></div><div><dt>المنطقة</dt><dd>${e(c.data.areas.find((a) => a.id === Number(p.area_id))?.name || 'راجع المنطقة')}</dd></div><div><dt>نطاق إثبات الحضور</dt><dd>${e(p.geofence_radius_m)} متر</dd></div><div><dt>تعليمات الخدمة</dt><dd>${e(p.service_notes || '—')}</dd></div></dl>`
          : existing
            ? `<p>الموقع المعتمد حاليًا: <b dir="ltr">${e(existing.latitude)}, ${e(existing.longitude)}</b></p>`
            : '';
      return {
        title: 'مراجعة ' + (r.customer?.name || p.name),
        extra: { id: r.id },
        html:
          summary +
          `<div class="review-request"><strong>${r.kind === 'location' ? 'تصحيح موقع العميل' : 'تسجيل عميل جديد'}</strong><p>${e(p.address || p.reason)}</p><p dir="ltr">${e(p.latitude)}, ${e(p.longitude)}</p><a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(p.latitude + ',' + p.longitude)}" target="_blank" rel="noopener" class="secondary-btn">معاينة النقطة في الخرائط ↗</a><small>من ${e(r.submitter?.name)} • ${c.when(r.created_at)}</small></div>` +
          (r.kind === 'new_customer'
            ? select(
                'representative_id',
                'المندوب المسؤول عند الاعتماد',
                '<option value="">اختر المندوب عند الاعتماد</option>' +
                  c.data.representatives
                    .map(
                      (m) =>
                        `<option value="${m.id}" ${m.id === Number(p.representative_id) ? 'selected' : ''}>${e(m.name)}</option>`,
                    )
                    .join(''),
              )
            : '') +
          select(
            'decision',
            'قرار المراجعة',
            '<option value="">اختر القرار</option><option value="approved">اعتماد الطلب</option><option value="rejected">رفض مع السبب</option>',
          ) +
          note('review_note', 'ملاحظة المراجعة', '', true),
      };
    }
    if (action === 'representative.details') {
      const m = member(id, c);
      if (!m) return null;
      return {
        title: 'بيانات المندوب • ' + m.name,
        extra: { id: m.id, operations_version: m.operations_version },
        html:
          field(
            'name',
            'الاسم الكامل',
            'text',
            m.name,
            'required maxlength="150"',
          ) +
          field('phone', 'رقم التواصل', 'tel', m.phone, 'maxlength="30"') +
          field(
            'employee_code',
            'الرقم الوظيفي',
            'text',
            m.employee_code,
            'maxlength="40"',
          ) +
          note('notes', 'ملاحظات تشغيلية', m.notes) +
          note('reason', 'سبب التعديل', '', true) +
          '<div class="info-box">تعديل البيانات التشغيلية لا يغير اسم الدخول أو كلمة المرور أو صلاحيات الحساب.</div>',
      };
    }
    if (action === 'representative.vehicle') {
      const m = member(id, c);
      if (!m) return null;
      const assigned = current(id, c),
        occupied = new Set(
          (c.data.representative_vehicle_assignments || [])
            .filter((a) => !a.returned_at && a.representative_id !== Number(id))
            .map((a) => a.vehicle_id),
        );
      return {
        title: 'مركبة العمل • ' + m.name,
        extra: {
          representative_id: m.id,
          current_assignment_id: assigned?.id || 0,
        },
        html:
          `<div class="info-box">التخصيص الحالي: ${e(assigned?.vehicle?.name || 'لا يوجد تخصيص موثق')}. لا يغيّر هذا الإجراء عهدة السائق أو ينقل المخزون تلقائيًا.</div><div class="field"><label for="vehicle-type-filter">تصفية المركبات حسب النوع</label><select id="vehicle-type-filter"><option value="">كل الأنواع</option>${Object.entries(
            c.data.vehicle_types,
          )
            .map(
              ([key, label]) => `<option value="${key}">${e(label)}</option>`,
            )
            .join(
              '',
            )}</select></div><div class="field"><label for="maint-vehicle_id">المركبة المخصصة</label><select name="vehicle_id" id="maint-vehicle_id"><option value="">${assigned ? 'إنهاء التخصيص دون بديل' : 'اختر مركبة'}</option>${c.data.vehicles
            .filter(
              (v) =>
                v.status === 'available' &&
                !occupied.has(v.id) &&
                !c.data.trips.some(
                  (t) => t.vehicle_id === v.id && t.status === 'active',
                ),
            )
            .map(
              (v) =>
                `<option value="${v.id}" data-type="${e(v.vehicle_type)}" ${assigned?.vehicle_id === v.id ? 'selected' : ''}>${e(c.data.vehicle_types[v.vehicle_type] || 'نوع غير محدد')} • ${e(v.name)} • ${e(v.plate)}</option>`,
            )
            .join(
              '',
            )}</select></div><div id="rep-vehicle-preview"></div><label class="permission-check"><input type="checkbox" name="update_plans"><span>تطبيق المركبة الجديدة على الرحلات المخططة والجدول الأسبوعي<small>اختياري. التغيير يعيد ربط عهدة التسليم؛ راجع تحميل المخزون قبل التنفيذ.</small></span></label>` +
          note('reason', 'سبب التخصيص أو التغيير', '', true),
      };
    }
    return null;
  }
  window.AmrbitMaintenance = {
    form,
    customerActions,
    requests,
    repRecord,
    handles: (a) =>
      [
        'customer.location',
        'customer.contact',
        'customer.review',
        'representative.details',
        'representative.vehicle',
      ].includes(a),
    payload(a, f, p) {
      if (a === 'representative.vehicle') {
        p.vehicle_id = p.vehicle_id ? Number(p.vehicle_id) : null;
        p.update_plans = f.has('update_plans');
      }
    },
    afterForm(action, c) {
      window.AmrbitLocationPicker.attach(
        document.querySelector('#action-form'),
      );
      if (action === 'customer.review') {
        const decision = document.querySelector('#maint-decision'),
          owner = document.querySelector('#maint-representative_id');
        if (owner) {
          const update = () => {
            owner.required = decision.value === 'approved';
            owner.disabled = decision.value === 'rejected';
          };
          decision.onchange = update;
          update();
        }
      }
      if (action === 'representative.vehicle') {
        const select = document.querySelector('#maint-vehicle_id'),
          filter = document.querySelector('#vehicle-type-filter');
        const preview = () => {
          const v = c.data.vehicles.find((v) => v.id === Number(select.value));
          document.querySelector('#rep-vehicle-preview').innerHTML = v
            ? `<div class="representative-vehicle-art">${window.FieldFlowAdmin.vehicleArt(v.vehicle_type)}</div><div><strong>${e(v.name)}</strong><p>${e(c.data.vehicle_types[v.vehicle_type] || 'النوع غير محدد')} • ${e(v.plate)}</p><small>السعة: ${e(v.capacity_units)} وحدة${v.payload_kg ? ' • ' + e(v.payload_kg) + ' كجم' : ''}</small></div>`
            : '';
        };
        select.onchange = preview;
        filter.onchange = () => {
          for (const option of select.options)
            option.hidden = Boolean(
              option.value &&
              filter.value &&
              option.dataset.type !== filter.value,
            );
          if (select.selectedOptions[0]?.hidden) select.value = '';
          preview();
        };
        preview();
      }
    },
  };
})();
