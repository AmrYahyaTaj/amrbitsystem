'use strict';
(() => {
  const button = document.querySelector('#roadmap-print');
  if (!button) return;
  document.querySelector('#page-title').textContent = 'خطة استكمال النظام';
  document.querySelector('#page-description').textContent =
    'المراحل والتبعيات وشروط القبول من تقرير المراجعة إلى التشغيل الفعلي.';
  document.querySelector('#section-code').textContent = 'ROADMAP';
  document.querySelector('#updated-at').textContent = 'نسخة الخطة: ٨ نوفمبر ٢٠٢٦';
  document.querySelector('#global-search').closest('label').hidden = true;
  document.querySelector('#refresh').onclick = () => location.reload();
  button.addEventListener('click', () => window.print());
})();
