<div id="custody-workspace">
    <section class="custody-intro">
        <div><span class="eyebrow">TOGETHER, ACCOUNTABLE</span><h2>كل تسليم، بعلم الطرفين.</h2><p>راجع ما يصلك، وثّق ملاحظاتك، وأقرّ الاستلام من حسابك. يبقى الطلب والمحضر ومحادثته في مكان واحد.</p></div>
        <a class="secondary-btn" href="/notifications">صندوق الإشعارات <span aria-hidden="true">←</span></a>
    </section>
    <div id="custody-stats" class="custody-stats" aria-live="polite"></div>
    <div class="custody-workbench">
        <div class="custody-tabs" role="group" aria-label="عرض الطلبات"><button class="selected" data-custody-tab="all">كل الطلبات</button><button data-custody-tab="inbox">الوارد إليّ</button><button data-custody-tab="sent">الصادر مني</button><button data-custody-tab="notifications">الإشعارات</button></div>
        <button type="button" class="primary-btn" id="custody-create" hidden>+ طلب تسليم جديد</button>
    </div>
    <form class="custody-filters" id="custody-filters">
        <label>الحالة<select name="status"><option value="">كل الحالات</option><option value="pending">بانتظار الموافقة</option><option value="accepted">استلام معتمد</option><option value="rejected">مرفوض</option><option value="cancelled">ملغى</option></select></label>
        <label>نوع التسليم<select name="kind"><option value="">كل الأنواع</option><option value="vehicle_delivery">تسليم مركبة</option><option value="vehicle_return">إرجاع مركبة</option><option value="stock_transfer">حمولة ومخزون</option><option value="asset">عهدة ومعدات</option><option value="cash">تسليم نقد</option></select></label>
        <label class="custody-search">بحث برقم الطلب أو ملاحظاته<input name="q" type="search" maxlength="100" placeholder="HO-… أو ملاحظة التسليم"></label><button class="secondary-btn">تطبيق</button>
    </form>
    <div id="custody-results" aria-live="polite"><div class="loading">جارٍ تحميل طلباتك…</div></div>
    <div id="custody-pagination" class="custody-pagination"></div>
    <p class="custody-footnote">الموافقة إقرار بالحساب المسجل. اطبع المحضر للتوقيع اليدوي، ثم ارفع نسخته إلى الأرشيف الخاص. الرفض لا ينقل عهدة أو مخزونًا.</p>
</div>
