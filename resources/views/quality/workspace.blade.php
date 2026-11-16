<section class="quality-shell" aria-label="إدارة الجودة">
<div class="quality-banner"><div><span class="quality-eyebrow">QUALITY / CONTROL & ACCOUNTABILITY</span><h2>كل دفعة لها فحص. وكل قرار له مسؤول.</h2><p>الإنتاج يسجل الناتج، والفاحص يوثق النتائج، والمعتمد المستقل يتخذ قرار الإفراج. تابع هنا مَن لديه المهمة والخطوة التالية.</p></div><span class="quality-seal" aria-hidden="true">✓</span></div>
<div id="quality-metrics" class="quality-metrics"></div>
<form id="quality-filters" class="quality-filters"><label>ابحث برقم الفحص أو الدفعة<input name="q" type="search" maxlength="100" placeholder="QA-… أو رقم الدفعة"></label><label>الحالة<select name="status"><option value="">جميع الحالات</option><option value="awaiting_inspection">بانتظار الفحص</option><option value="awaiting_approval">بانتظار الاعتماد</option><option value="approved">معتمد بالإفراج</option><option value="rejected">منتهٍ دون إفراج</option><option value="cancelled">ملغى بسبب تغيير الحالة</option></select></label><button class="primary-btn">تطبيق البحث</button><button type="button" class="secondary-btn" id="quality-access" hidden>مسؤولو الجودة ومواقعهم</button></form>
<div class="quality-hint">طابور العمل مشترك للمخولين بالموقع. ظهور اسم مسؤول يعني أن حسابه فعّال ومستقل عن الإنتاج والفحص المطلوب. الطلبات القديمة المعتمدة سابقًا لا تحصل على اعتماد جودة مصطنع.</div>
<div id="quality-results" aria-live="polite"><div class="loading">نحمّل طلبات الجودة…</div></div>
<div id="quality-pagination" class="quality-pagination"></div>
<section class="quality-panel"><h3>دفعات محجورة أو موقوفة بلا طلب فحص نشط</h3><p>طلب الفحص لا يغيّر رصيدًا أو يفرج عن دفعة. تظهر حتى ١٠ دفعات بكل صفحة.</p><div id="quality-held"></div><div id="quality-held-pagination" class="quality-pagination"></div></section>
</section>
<dialog id="quality-dialog" class="quality-dialog" aria-labelledby="quality-dialog-title"><div class="dialog-heading"><h2 id="quality-dialog-title">ملف الجودة</h2><button class="icon-btn" type="button" data-quality-close aria-label="إغلاق">×</button></div><div id="quality-dialog-content"></div></dialog>
<div id="quality-toast" class="toast" role="status" hidden></div>
