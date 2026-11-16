'use strict';
(() => {
  let map = null;
  function destroy() {
    if (map) {
      map.remove();
      map = null;
    }
  }
  function attach(form) {
    destroy();
    const lat = form.querySelector(
      '[name="latitude"], [name="origin_latitude"]',
    );
    const lng = form.querySelector(
      '[name="longitude"], [name="origin_longitude"]',
    );
    if (!lat || !lng) return;
    const box = document.createElement('section');
    box.className = 'location-editor';
    box.innerHTML = `<div class="location-editor-title"><strong>حدد الموقع على الخريطة</strong><span>اضغط على المكان أو اسحب العلامة</span></div><div class="location-map" role="region" aria-label="خريطة اختيار موقع العميل"></div><div class="location-tools"><button type="button" class="secondary-btn" data-locate-device>استخدم موقعي الحالي</button><button type="button" class="quiet-btn" data-center-point>عرض النقطة المحددة</button></div><label class="location-paste-label">إحداثيات أو رابط خرائط كامل<div class="location-paste"><input type="text" data-location-paste dir="ltr" placeholder="24.7700, 46.6100" aria-label="إحداثيات أو رابط الموقع"><button type="button" class="secondary-btn" data-apply-location>تطبيق</button></div></label><p class="location-feedback" role="status"></p>`;
    const target = lat.closest('.field-grid') || lat.closest('.field');
    target.before(box);
    const feedback = box.querySelector('.location-feedback');
    const valid = (a, b) =>
      Number.isFinite(a) &&
      Number.isFinite(b) &&
      a >= -90 &&
      a <= 90 &&
      b >= -180 &&
      b <= 180;
    const current = () =>
      lat.value.trim() &&
      lng.value.trim() &&
      valid(Number(lat.value), Number(lng.value))
        ? [Number(lat.value), Number(lng.value)]
        : null;
    const point = current();
    feedback.textContent = point
      ? 'النقطة الحالية محفوظة. حرّك العلامة لتصحيحها ثم احفظ النموذج.'
      : 'لم يُحدد موقع بعد. عرض الخريطة وحده لا يحفظ نقطة؛ اختر المكان أولًا.';
    let marker = null;
    const markerOptions = () => ({
      draggable: true,
      title: 'موقع العميل — اسحب للتعديل',
      icon: L.divIcon({
        className: 'location-marker',
        html: '<svg viewBox="0 0 32 42" width="32" height="42" aria-hidden="true"><path d="M16 41C12 33 1 24 1 16a15 15 0 1 1 30 0c0 8-11 17-15 25Z" fill="#2476dc" stroke="white" stroke-width="2"/><circle cx="16" cy="16" r="5" fill="white"/></svg>',
        iconSize: [32, 42],
        iconAnchor: [16, 42],
      }),
    });
    function setPoint(a, b, pan = true) {
      if (!valid(a, b)) {
        feedback.textContent = 'الإحداثيات خارج النطاق الصحيح.';
        return;
      }
      lat.value = a.toFixed(7);
      lng.value = b.toFixed(7);
      if (map) {
        if (!marker) {
          marker = L.marker([a, b], markerOptions()).addTo(map);
          marker.on('dragend', () => {
            const p = marker.getLatLng();
            setPoint(p.lat, p.lng, false);
          });
        } else marker.setLatLng([a, b]);
        if (pan) map.setView([a, b], Math.max(map.getZoom(), 16));
      }
      feedback.textContent = `الموقع المختار: ${a.toFixed(6)}, ${b.toFixed(6)} — يُثبت عند حفظ النموذج.`;
    }
    if (window.L) {
      map = L.map(box.querySelector('.location-map'), {
        scrollWheelZoom: false,
      }).setView(point || [24.774, 46.738], point ? 16 : 11);
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution:
          '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
      }).addTo(map);
      map.on('click', (ev) => setPoint(ev.latlng.lat, ev.latlng.lng, false));
      if (point) {
        marker = L.marker(point, markerOptions()).addTo(map);
        marker.on('dragend', () => {
          const p = marker.getLatLng();
          setPoint(p.lat, p.lng, false);
        });
      }
      requestAnimationFrame(() => map?.invalidateSize());
    } else
      box.querySelector('.location-map').textContent =
        'تعذر تحميل الخريطة. يمكنك إدخال الإحداثيات أو لصقها أدناه.';
    box.querySelector('[data-center-point]').onclick = () => {
      const p = current();
      if (p && map) map.setView(p, 16);
      else feedback.textContent = 'اختر نقطة أولًا.';
    };
    box.querySelector('[data-apply-location]').onclick = () => {
      let value = box.querySelector('[data-location-paste]').value.trim(),
        match;
      try {
        value = decodeURIComponent(value);
      } catch {}
      if (/^https?:/i.test(value)) {
        try {
          const url = new URL(value);
          value =
            url.searchParams.get('query') ||
            url.searchParams.get('q') ||
            url.searchParams.get('destination') ||
            value;
        } catch {}
        match =
          value.match(/@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/) ||
          value.match(/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/);
      }
      match ||= value.match(
        /^\s*(-?\d+(?:\.\d+)?)\s*[,،]\s*(-?\d+(?:\.\d+)?)\s*$/,
      );
      if (!match) {
        feedback.textContent =
          'الصق خط العرض والطول أو رابطًا كاملًا يحتويهما. الروابط المختصرة لا تحتوي إحداثيات؛ افتحها في الخرائط وانسخ النقطة.';
        return;
      }
      setPoint(Number(match[1]), Number(match[2]));
    };
    box.querySelector('[data-locate-device]').onclick = () => {
      if (!window.isSecureContext || !navigator.geolocation) {
        feedback.textContent =
          'تحديد موقع الجهاز يحتاج اتصال HTTPS موثوقًا أو localhost. يمكنك اختيار النقطة على الخريطة الآن.';
        return;
      }
      feedback.textContent = 'نطلب موقع الجهاز بإذنك…';
      navigator.geolocation.getCurrentPosition(
        (position) => {
          setPoint(position.coords.latitude, position.coords.longitude);
          feedback.textContent += ` دقة الجهاز نحو ${Math.round(position.coords.accuracy)} متر؛ راجع موضع العلامة قبل الحفظ.`;
        },
        () => {
          feedback.textContent =
            'تعذر الحصول على الموقع. راجع إذن المتصفح أو اختر المكان يدويًا.';
        },
        { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 },
      );
    };
    [lat, lng].forEach((input) =>
      input.addEventListener('change', () => {
        const p = current();
        if (p) setPoint(...p);
      }),
    );
  }
  document.addEventListener('DOMContentLoaded', () =>
    document
      .querySelector('#action-dialog')
      ?.addEventListener('close', destroy),
  );
  window.AmrbitLocationPicker = { attach, destroy };
})();
