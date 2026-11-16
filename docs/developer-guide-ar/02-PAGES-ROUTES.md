# 02 — الصفحات والمسارات والإجراءات

## 1. قالب مشترك، وأقسام مختلفة

في نسختنا أربعة ملفات Blade فقط. الملف `resources/views/app.blade.php` هو الهيكل المشترك لأقسام الإدارة. يستقبل section و sections من Controller. القسم يحدد دالة الرسم في JS، و sections تحدد روابط القائمة التي يسمح بها الدور.

| الرابط | ما الذي يراه المستخدم؟ | موضع الرسم | أهم مصادر البيانات |
|---|---|---|---|
| /login | اسم المستخدم وكلمة المرور ورسالة الفشل | auth/login.blade.php | AuthController و users |
| /dashboard | مدير: مراجعة التشغيل والتنبيهات والموارد؛ مندوب: يومه ومحطاته؛ محاسب: ملخص مالي | app.js dashboard/repDashboard؛ administration.js dashboard؛ customer-journey.js repDashboard | ops/data؛ ops/my-day للمندوب |
| /tracking | الرحلة المختارة، النقاط المسجلة، آخر موقع وعمره واستعراض المسار زمنيًا | app.js tracking + drawMap | trips، visits، location_points، customers |
| /schedule | قوالب أيام الأسبوع وتوزيع العملاء ونشر رحلة بتاريخ | customer-journey.js schedule و form | weekly_plans والعملاء والمركبات |
| /trips | الرحلات وحالتها وعدد المحطات؛ إنشاء/تعديل/إلغاء وملف رحلة | app.js tripsPage و showTripFile | trips، visits، ops/trips/ID |
| /visits | مواعيد الزيارات وحالتها وإثباتها ونتيجتها | app.js visitsPage و visitButtons | visits مع customer و representative |
| /customers | دليل العملاء والمناطق وطلبات التسجيل/تصحيح الموقع | customer-journey.js customers؛ maintenance.js requests | customers، areas، customer_changes |
| /customers/ID | ملف عميل: تواصل، موقع، مسؤول، طلبات وأصناف، زيارات، تحصيل، متابعة وكشف حركة | customer-journey.js customerPage | ops/customers/ID؛ سجل صفحات 20 لكل تبويب |
| /team | أعضاء الدخول وصلاحياتهم وتغطيتهم، ودليل السائقين وسجل العهدة | administration.js team/record؛ maintenance.js repRecord | users، company_user، representative_areas، drivers، سجلات المركبات |
| /vehicles | الأنواع والبيانات والوثائق والحمولة والجاهزية والسائق وتاريخ الاستلام | administration.js fleet و vehicleArt | vehicles، drivers، vehicle_assignments، trips |
| /inventory | المنتجات والمستودعات وعهد المركبات والأرصدة والحركات والاستلام والتحويل | app.js inventoryPage | products، warehouses، stock_balances، stock_movements |
| /orders | طلبات متعددة الأصناف، مسودة/تسليم، نقدي/جزئي/آجل، رابط المستند | app.js ordersPage و orderLine | orders و order_items و products |
| /collections | إيصالات الاستلام والمبلغ والطريقة والفاتورة | app.js collectionsPage | collections و orders |
| /receivables | آجل السوق، حسب المندوب والعميل والفاتورة، الاستحقاق والمتابعة | app.js receivablesPage و debtSummary و receivablesInvoices | orders المسلمة ناقص paid_minor، و debt_followups |
| /settlements | نقد مستلم، تسليمات معلقة ومعتمدة، المتاح للتسليم، إجراءات المراجعة | app.js settlementsPage | CashCustody و cash_handovers و collections |
| /reports | فترة ومندوب ومنطقة، رسوم وأداء ووسائل تحصيل وأعمار آجل، CSV | app.js reportsPage/fetchReport؛ administration.js reports | ops/analytics؛ reports/export |
| /audit | آخر عمليات المساءلة والإجراءات و metadata | app.js auditPage | audit_logs |
| /settings | عرض معلومات الشركة والعملة والمنطقة الزمنية وحالة البيئة | app.js settingsPage | company ضمن ops/data؛ ليست شاشة إعداد شاملة للحفظ |
| /documents/orders/ID | مستند طلب وتسليم وتحصيل للطباعة | documents/order.blade.php و document.js | DocumentController، order_items، collections |

فتح الصفحة لا يعني السماح بكل أزرارها. قوائم وأزرار تختلف حسب الدور؛ وفحص الخادم هو المرجع النهائي. /schedule يعيد قالب app بقسم trips، وتعرف JS من pathname أنها شاشة الجدول. /customers/ID يستخدم أيضًا قسم customers لكن customerId يحوّل الرسم إلى الملف التفصيلي.

## 2. ملف routes/web.php كاملًا من ناحية العمل

| الطريقة والرابط | المعالج | الحماية/المخرج |
|---|---|---|
| GET / | redirect إلى dashboard | الضيف يمر بعدها إلى الدخول |
| GET /login | AuthController::create | guest؛ HTML |
| POST /login | AuthController::store | guest + throttle:login + CSRF |
| POST /logout | AuthController::destroy | auth + CSRF |
| POST /company/switch | CompanyController::switch | auth؛ تحقق العضوية داخل الدالة |
| GET /dashboard | OperationsController::page | auth + company؛ HTML |
| GET /{section} | OperationsController::page | قائمة sections محددة في route، ثم صلاحية الدور |
| GET /customers/{id} | CustomerJourneyController::page | id رقمي؛ التحقق من صاحب العميل |
| GET /schedule | CustomerJourneyController::schedule | مدير/مشرف/مندوب |
| GET /ops/data | OperationsController::data | 120 طلبًا/دقيقة؛ JSON تجميع عام |
| GET /ops/trips/{id} | TripFileController::show | 120/دقيقة؛ ملف الرحلة |
| GET /ops/customers/{id} | CustomerJourneyController::show | 120/دقيقة؛ ملف العميل |
| GET /ops/my-day?date=YYYY-MM-DD | CustomerJourneyController::day | مندوب فقط؛ يوم محدد؛ 120/دقيقة |
| POST /ops/action/{action} | OperationsController::execute | 60/دقيقة؛ JSON و CSRF و request_id |
| POST /ops/journey/{action} | CustomerJourneyController::execute | 60/دقيقة؛ JSON و CSRF و request_id |
| GET /ops/analytics | OperationsController::analytics | 30/دقيقة؛ فترة مطلوبة؛ ليس للمندوب |
| GET /reports/export | OperationsController::report | CSV وتفويض حسب نوعه |
| GET /documents/orders/{id} | DocumentController::order | مستند حسب الدور وملكية المندوب |

كل ما بعد logout/company switch في الجدول من صفحات التشغيل يمر بـ auth و company. المسار `/up` مضاف من bootstrap كفحص صحة Laravel؛ نجاحه وحده لا يثبت صحة البيع أو المخزون.

## 3. ملف routes/api.php

| الطريقة والرابط الفعلي | المعالج | ملاحظة |
|---|---|---|
| POST /api/v1/token | DeviceAuthController::store | محدد login؛ إصدار رمز جهاز |
| DELETE /api/v1/token | DeviceAuthController::destroy | auth:sanctum؛ إلغاء الحالي |
| GET /api/v1/workspace | OperationsController::data | auth:sanctum + api-company + throttle:120,1 |
| POST /api/v1/operations/{action} | OperationsController::execute | نفس منطق العمليات؛ throttle:60,1 |

لا تكرر `/api` داخل تعريف المسارات؛ Laravel يضيف البادئة. `X-Company-ID` يحدد الشركة صراحة، ولا يمنح إذنًا لدخول شركة لا يملكها الحساب. endpoint الأجهزة لا يوجه حاليًا إلى CustomerJourneyController.

## 4. قاموس إجراءات OperationsController::execute

اسم مثل `vehicle.create` ليس دالة PHP بهذا الاسم؛ هو نص action يختار فرعًا داخل switch. يرسل JS إلى `/ops/action/vehicle.create` مثلًا. جميع الفروع الناجحة داخل OperationOnce transaction، وترجع في العادة ok و id و message.

| action | الدور المسموح مبدئيًا | ماذا يفعل؟ وأين؟ |
|---|---|---|
| area.create | مدير/مشرف | يفحص الاسم الفريد داخل الشركة ويحفظ areas ويسجل audit |
| customer.create | كل الأدوار عدا المشاهد | مدير/مشرف ينشئ customers مباشرة؛ مندوب/محاسب يرسل CustomerMaintenance::registration للمراجعة |
| member.create / member.update | مدير | MemberAdministration::save؛ users وعضوية وصلاحيات ومناطق |
| member.status | مدير | تعليق/تفعيل العضوية دون محو التاريخ؛ لا المدير أو نفسه |
| vehicle.create / vehicle.update | مدير/مشرف | FleetRegistry::vehicle؛ إنشاء المركبة ينشئ مستودع عهدة لها |
| driver.create / driver.update | مدير/مشرف | FleetRegistry::driver؛ السائق سجل مستقل عن مستخدم الدخول |
| vehicle.assign / vehicle.release | مدير/مشرف | FleetRegistry::assign/release؛ تسليم فعلي لسائق وإرجاعه |
| trip.create / trip.update | مدير/مشرف | TripPlanning::save؛ حفظ خطة ومحطات؛ نسخة وسبب عند التعديل |
| trip.cancel | مدير/مشرف | TripPlanning::cancel؛ إلغاء الخطة والمحطات غير المنفذة مع حفظها |
| trip.start / trip.close | مدير/مشرف أو المندوب المسؤول | يوم التنفيذ والجاهزية وعدم ازدواج السيارة/المندوب؛ الإغلاق بعد معالجة كل المحطات |
| visit.checkin | المندوب المسؤول | رحلة نشطة، ترتيب في الرحلات الأسبوعية، GPS حديث ودقيق وداخل النطاق |
| visit.finish | المندوب المسؤول | completed/failed/skipped؛ الملاحظات والأسباب؛ لا يخصم المخزون |
| location.record | المندوب المسؤول | نقطة لرحلته النشطة: event_id، حداثة، دقة، ترتيب زمني وقفزة حركة منطقية |
| customer.credit | مدير | حد ائتماني nullable مع سبب؛ 0 يختلف عن null؛ لا يمحو دينًا سابقًا |
| product.create | مدير/مشرف | SKU وسعر هللات ووحدة ونقطة إعادة طلب |
| warehouse.create | مدير/مشرف | مستودع عادي، لا عهدة مركبة يدويًا |
| stock.receive | مدير/مشرف | استلام في مستودع عادي ثم StockLedger::move موجب |
| stock.transfer | مدير/مشرف | تحويل زوجي خارج/داخل برقم مرجع واحد وبدون سالب أو تجاوز سعة وحدات السيارة |
| order.create | مدير/مندوب | TradingWorkflow::create؛ مسودة وأسعار من الخادم؛ المندوب من رحلة نشطة فقط |
| order.fulfill | مدير/مندوب | TradingWorkflow::fulfill؛ خصم فعلي وحد ائتمان واستلام أولي حسب الطلب |
| collection.create | مدير/محاسب/مندوب | TradingWorkflow::collect؛ إيجابي لا يتجاوز المتبقي؛ ملكية الفاتورة للمندوب |
| debt.followup | مدير/مشرف/محاسب/مندوب | متابعة فاتورة مفتوحة وموعد ووعد ضمن الرصيد؛ المندوب لفواتيره |
| cash.request / cash.cancel | مدير/محاسب/مندوب | طلب تسليم نقد متاح أو إلغاء المعلق؛ الإلغاء لصاحبه/مقدمه فقط |
| cash.accept / cash.reject | مدير/محاسب | قبول أو رفض من شخص آخر غير حامل النقد أو مقدم الطلب |

القيد الخاص بالمندوب can_sell/can_collect و assertCustomer يأتي إضافة إلى الدور. مثلاً «مندوب» وحدها لا تكفي للبيع إذا منعت can_sell أو منطقة العميل ليست ضمن التغطية.

## 5. قاموس إجراءات CustomerJourneyController::execute

كل هذه ترسل إلى `/ops/journey/{action}` مع request_id و CSRF.

| action | الوصول | الخدمة/النتيجة |
|---|---|---|
| customer.update | مدير/مشرف | update داخل Controller؛ ملف كامل، profile_version، وفحص محطات وخطط قبل تغيير المسؤول/المنطقة/الحالة |
| customer.contact | مدير/مشرف أو المندوب المسؤول | CustomerMaintenance::contact؛ اسم وهاتف وعنوان وملاحظات فقط |
| customer.location | مدير/مشرف أو المندوب المسؤول | الإدارة تعتمد النقطة مباشرة؛ المندوب ينشئ طلب مراجعة ولا يغير النقطة المعتمدة |
| customer.review | مدير/مشرف | CustomerMaintenance::review؛ قبول/رفض عميل جديد أو موقع مع السبب وإعادة الفحص |
| representative.details | مدير/مشرف | RepresentativeRegistry::details؛ بيانات تشغيلية لا كلمة المرور أو صلاحية الحساب |
| representative.vehicle | مدير/مشرف | تخصيص مركبة عمل، تاريخ الإسناد، ويمكن تحديث الخطط غير المنفذة اختياريًا |
| weekly.save | مدير/مشرف | WeeklyRouting::save؛ قالب يوم لمندوب ونسخة وأصل انطلاق وعملاء مسندين له |
| weekly.publish | مدير/مشرف | WeeklyRouting::publish؛ ينشئ رحلة بتاريخ مطابق لليوم خلال 35 يومًا |

## 6. أربع عمليات كاملة للفهم

### أ. إضافة مركبة

`vehicles` → زر vehicle.create → `administration.js::form` يرسم بطاقات النوع وحقول اللوحة والملكية والحمولة والوثائق → نموذج app.js يرسل المدخلات → OperationsController يفحص الدور ويستدعي FleetRegistry::vehicle → يتأكد من النوع ضمن Vehicle::TYPES واللوحة الفريدة والحدود → يحفظ vehicles وينشئ warehouses من type=vehicle → AuditRecorder → نجاح → إعادة جلب ورسم.

الرسم في vehicleArt يتبع vehicle_type المحفوظ. إضافة نوع جديد تتطلب تعديل ثابت الأنواع والرسم والتحقق وأي فلتر، وليس استبدال صورة فان فقط. السعة بالوحدات مستخدمة في التحويل؛ payload_kg و volume_m3 معلومات محفوظة حاليًا، وليستا نظام حساب حمولة متعددة الأصناف والأوزان.

### ب. طلب آجل ثم تحصيل ثم تسليم نقد

1. order.create ينشئ مسودة مع أصناف وكميات وسعر محفوظ لكل سطر. لا بيع مؤكد ولا حركة مخزون بعد.
2. order.fulfill يفحص حد العميل والرصيد؛ يخفض المخزون، ويغير الحالة إلى fulfilled. في cash يحصّل الإجمالي؛ partial يحصّل initial_paid_minor؛ credit لا يحصّل أوليًا.
3. الآجل = total_minor − paid_minor على الطلبات fulfilled فقط. لا يوجد عمود إجمالي دين منفصل يجب تعديله يدويًا لكل عميل.
4. collection.create يضيف إيصالًا في collections ويزيد paid_minor. مجرد قول «اتصلت بالعميل ووعد بالدفع» لا يسدد شيئًا.
5. إن كانت method=cash يصبح على المستلم نقد محمول. cash.request يحجز جزءًا للمراجعة ولا يخفض النقد المؤكد.
6. cash.accept من مستلم آخر يخفض عهدة النقد. لا يخفض دين العميل مرة ثانية؛ ذلك حدث عند تسجيل الإيصال.

### ج. الأسبوع ثم رحلة اليوم

weekly.save يحفظ قالبًا، وليس رحلة تلقائية لكل أسبوع. weekly.publish يختار تاريخًا مطابقًا لليوم، ويرتب العملاء بطريقة أقرب نقطة تالية بمسافة جغرافية مستقيمة، ثم يستدعي TripPlanning::save لإنشاء trips و visits. يحفظ route_snapshot بأصل الانطلاق وطريقة الحساب. لا يستخدم أزمنة طرق أو ازدحامًا أو خدمة تحسين مسارات تجارية.

لذلك هناك فرق بين **قالب** و**رحلة مؤرخة**. تعديل القالب لا يعيد كتابة رحلة منشورة. تكرار النشر لنفس الخطة والتاريخ يرجع الرحلة الموجودة، حتى مع اختلاف request_id، ولا ينشئ نسخة ثانية.

### د. زيارة موثقة

بدء الرحلة → اختيار المحطة → visit.checkin يطلب إذن الموقع فعليًا → الخادم يفحص تاريخ الزيارة وحداثة الموقع ودقته ومسافته من العميل → تصبح in_progress. تسجيل البيع/التسليم إن وجد يحصل بإجراءات الطلب؛ ثم visit.finish يسجل النتيجة والتفاصيل. إذا لا بيع في رحلة أسبوعية، يلزم سبب no_sale أو other وملاحظة. تعذر الخدمة/تجاوز محطة يحتاج تفسيرًا. الرحلة لا تغلق مع بقاء planned أو in_progress.

إثبات GPS يثبت قرب الإحداثيات المرسلة وفق الشروط، وليس ضمانًا مطلقًا ضد انتحال الموقع. تاريخ إثبات الزيارة محفوظ في visits ولا يعاد حسابه كإثبات جديد بمجرد تغيير نقطة العميل.

## 7. حجم البيانات المعروضة

ops/data يجمع قوائم محدودة: العملاء 500، المناطق 500، المركبات 500، الرحلات 300، الزيارات 1000، نقاط آخر يومين حتى 2000، الطلبات 500، الإيصالات 500، الحركات 100، المتابعات 100، سجل العمليات 100، طلبات العملاء 100، تسليمات النقد 200.

البحث العام داخل معظم هذه الصفحات يبحث في البيانات المحملة، وليس بحثًا غير محدود في كامل القاعدة. أرقام الديون المجمعة و metrics تحسب باستعلامات تجميع مستقلة ولا تعتمد كلها على عدد صفوف القائمة. ملف العميل يقدم سجلًا كاملًا مقسمًا إلى صفحات 20، و my-day يقدم كامل محطات يوم المندوب المحدد؛ هذان ليسا مقيدين بالحد العام للزيارات/الطلبات. التقارير والتصدير يستعلمان من الخادم بالفترة المطلوبة.

هذه تفاصيل مهمة عند التوسع؛ الخطوة التالية تكون بحثًا وتقسيم صفحات على الخادم لكل دليل، لا رفع الحدود بلا حساب.
