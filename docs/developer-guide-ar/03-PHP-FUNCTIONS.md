# 03 — شرح دوال PHP في التطبيق

كل مسار يبدأ بـ`app/` هنا داخل `C:\xampp\htdocs\fieldflow-next`. أرقام أسطر التعريفات وروابطها في الفصل 08. هذا الفصل يشرح الدوال المسماة الخاصة بالتطبيق؛ دوال PHP/Laravel الشائعة مشروحة في آخره.

`public` يمكن استدعاؤها من خارج الكلاس؛ `private` مساعدة داخل الكلاس؛ `protected` للكلاس وما يرثه. `: void` لا ترجع قيمة، `: Customer` ترجع Model عميل، `?string` يقبل نصًا أو null. `__construct` يجهز احتياجات الخدمة عند إنشائها، و`__invoke` يسمح باستدعاء الكلاس كمعالج واحد.

## 1. Controllers المستخدمة الآن

### AuthController.php

| الدالة | المدخلات والعمل والمخرج |
|---|---|
| create | لا يحتاج بيانات؛ يعيد view auth.login |
| store | Request باسم مستخدم وكلمة مرور؛ يوحد الاسم، validates، يستدعي Auth::attempt، يفشل برسالة عامة أو يجدد الجلسة ويوجه dashboard |
| destroy | Request الجلسة الحالية؛ logout ثم invalidate و regenerateToken ثم redirect login |

### CompanyController.php

`switch(Request)`: يتحقق من company_id الصحيح، ومن عضوية المستخدم الفعالة وشركة فعالة. يجدد الجلسة ويضع الشركة فيها، ثم يعيد الرئيسية. لا ينقل سجلات شركة إلى أخرى.

### OperationsController.php

| الدالة | دورها |
|---|---|
| role | يحصل على CompanyRole من CompanyContext الحالي |
| manager | يمنع غير المدير/المشرف؛ الاسم هنا يعني إدارة العمليات، وليس manager فقط |
| finance | يسمح للمدير والمحاسب والمندوب؛ لبعض الإجراءات ضوابط أدق خارجها |
| own | يسمح للإدارة التشغيلية أو مندوب السجل نفسه، ويمنع التعامل مع مورد مندوب آخر |
| companyRule(table) | يصنع Rule::exists مقيدًا بجدول وشركة؛ رقم ID صحيح في شركة أخرى لا يمر |
| representative(id) | يتحقق من عضوية فعالة ودور representative داخل الشركة |
| page | يحدد sections المسموحة للدور، ويرفض القسم غير المسموح، ويعيد قالب app مع section |
| data | يبني JSON البيانات العامة، ويطبق نطاق المندوب وإخفاء المال للمشاهد/التشغيل للمحاسب، ويحسب المجاميع ويعيد الأنواع والصلاحيات |
| execute | يفحص action والدور وقدرات المندوب و request_id، ثم يمرر العمل إلى OperationOnce؛ switch يختار الخدمة أو خطوات العملية. جدول كل فروعه في الفصل 02 |
| reportFilters | يفحص from/to وترتيبهما، وفترة لا تتجاوز فرق 90 يومًا (91 يومًا شاملًا)، وانتماء المنطقة والعضو للشركة |
| analytics | يمنع المندوب، يستدعي OperationalAnalytics::build مع الفلاتر، ويعيد JSON |
| report | يفوض التصدير المالي أو التشغيلي، يبني الاستعلام، ويعيد CSV على دفعات lazyById(500) بدل تحميل كل السجل في الذاكرة |

داخل report توجد closure للتصدير المتدفق. تعيد سياق الشركة الموثق مؤقتًا لأن تنفيذ التدفق قد يحصل بعد رجوع Middleware ومسحه للسياق، ثم تمسحه في finally. يكتب BOM لـ UTF-8 لتسهيل عرض العربية، وتبدأ النصوص التي قد تفسرها برامج الشيت كصيغة بعلامة اقتباس للحماية. أرقام CSV المالية موسومة بالهللات في رؤوسها.

### CustomerJourneyController.php

| الدالة | دورها |
|---|---|
| customer(id) | يجلب العميل مع المنطقة والمندوب ضمن الشركة؛ يمنع المندوب من ملف عميل غيره |
| page | يفحص العميل ثم يعيد قالب صفحة customers المشتركة |
| schedule | يفحص مدير/مشرف/مندوب ثم يعيد القالب بقسم trips؛ JS تعرف شاشة schedule من الرابط |
| day | للمندوب فقط؛ يفحص date، يجلب رحلات اليوم ومحطاتها وطلباتها كاملة، ويضيف رحلاته النشطة حتى إن كانت بتاريخ آخر |
| show | يجمع ملف العميل والملخص والمنتجات والأيام الأسبوعية، ويقدم تبويب تاريخ واحد مع paginate(20). المندوب يرى تاريخ ماله وفواتيره فقط؛ المشاهد لا يرى التبويبات المالية |
| ledger | يبني unionAll للفواتير كمدين والإيصالات كدائن، ويرتب ويقسم النتيجة؛ سجل حركة تشغيلي وليس دفتر محاسبة عام مزدوج |
| execute | يفوض إجراءات العميل/المندوب/الجدول ثم OperationOnce؛ يختار الخدمة بـ match ويعيد رسالة مناسبة للطلب المعلق أو نشر الرحلة |
| update | تعديل ملف العميل الكامل: يقفل السجل، يفحص profile_version، ويمنع نقل المسؤول/إيقاف العميل مع محطات أو قوالب متعارضة، ويعيد التحقق من تغطية المندوب. تغيير الإحداثيات يمر عبر CustomerMaintenance::location، ثم يحفظ باقي الحقول ويسجل before/after |

تغيير مسؤول العميل لا يعيد كتابة representative_id لفواتيره السابقة؛ لذلك scope المالي للمندوب الحالي مختلف عن كل مديونية العميل لدى الشركة. لا تضف تحديثًا جماعيًا للفواتير القديمة عند نقل العميل دون تصميم نقل مسؤولية مالي مستقل.

### TripFileController.php

`show(id, TripPlanning)`: يمنع المحاسب، ويمنع المندوب من رحلة غيره. يعيد الرحلة مع المركبة والسائق، كل المحطات بما فيها الملغاة، موانع التعديل، رصيد العهدة **الحالي**، آخر نقطة، الطلبات والمجاميع حسب الدور، وأحداث الرحلة وأسماء الفاعلين. رصيد العهدة ليس لقطة مخزون عند بداية هذه الرحلة أو نهايتها.

### DocumentController.php

`order(id)`: يسمح للأدوار المالية/الإشرافية، ويقيد المندوب بطلبه، ثم يجلب العميل والمندوب والعهدة والأسطر والإيصالات ويعيد documents.order. المستند كشف تشغيلي، لا فاتورة ضريبية معتمدة.

### DeviceAuthController.php

- `store(Request)`: توحيد username والتحقق منه وكلمة المرور و device_name؛ Hash::check؛ عضويات فعالة؛ تنظيف الرموز المنتهية؛ حد عشرة رموز؛ إصدار رمز لسبعة أيام، وإرجاعه والشركات المتاحة.
- `destroy(Request)`: يطلب أن يكون currentAccessToken من نوع PersonalAccessToken، يحذفه ويرجع ok. لا يسجل خروج كل الأجهزة.

### Controller.php

كلاس أساسي مجرد ترثه Controllers. ليس شاشة ولهذا لا تبحث عن HTML بداخله. لا يضيف حاليًا دوال تطبيق خاصة.

## 2. Controllers قديمة غير موصولة بالمسارات الحالية

البحث في routes و bootstrap والاختبارات الحالية لا يظهر ربطًا لـ CatalogController أو DashboardController أو VisitController. كما أن قوالبها القديمة غير موجودة ضمن ملفات views الأربعة. وجود الملف لا يعني أنه ينفذ عند فتح القسم. لا تربطها كبديل سريع؛ بعض قواعدها أقدم من ضوابط الرحلة الحالية.

| الملف والدالة | ما كتبت لتفعله قديمًا |
|---|---|
| CatalogController::areas | قائمة مناطق paginated و Gate view-directory ثم catalog.areas |
| CatalogController::storeArea | فحص الاسم والمدينة ثم إنشاء منطقة داخل transaction وسجل audit |
| CatalogController::customers | بحث اسم/رمز وقائمة عملاء مع المنطقة ثم catalog.customers |
| CatalogController::storeCustomer | فحص عميل وإحداثيات ثم إنشاء وأثر؛ لا تدفق المراجعة والملكية الحالي كاملًا |
| CatalogController::vehicles | قائمة مركبات ثم catalog.vehicles |
| CatalogController::storeVehicle | إضافة اسم ولوحة وحالة فقط؛ لا النموذج الشامل الحالي |
| CatalogController::team | قائمة أعضاء ثم catalog.team |
| CatalogController::audit | سجل عمليات ثم catalog.audit |
| DashboardController::__invoke | إحصاء زيارات اليوم وعملاء/مركبات وآخر نشاط ثم dashboard القديم |
| VisitController::index | قائمة زيارات بفلاتر مع scope المندوب ثم visits.index |
| VisitController::store | جدولة زيارة منفردة قديمة وإنشاء audit |
| VisitController::show | Gate view ثم visits.show |
| VisitController::checkIn | Gate execute ثم validation واستدعاء VisitWorkflow::checkIn |
| VisitController::finish | Gate execute ثم استدعاء VisitWorkflow::finish؛ لا يطبق وحده كل ضوابط الرحلة الأسبوعية في OperationsController |

هذه الملفات تفسر لماذا تعديل storeVehicle القديم لن يغير زر «إضافة مركبة» الحالي. في إعادة تنظيم لاحقة يمكن أرشفتها/حذفها بعد مراجعة المراجع؛ لم تحذف أثناء إعداد هذا الدليل.

## 3. Services: منطق العمل

### AuditRecorder.php

`record(action, Model subject, metadata=[])`: ينشئ AuditLog بفاعل العملية الحالي ونوع جدول subject ومعرفه والوقت. metadata قد تحتوي before/after/reason بحسب المستدعي. لا تجمع بيانات حساسة فيها تلقائيًا؛ لا تمرر كلمات المرور عند إضافة إجراء.

### OperationOnce.php

`run(action, requestId, payload, callable work)`: closure داخلية ترتب مفاتيح الكائنات تكراريًا مع حفظ ترتيب القوائم، ثم SHA-256 للمدخلات. مفتاح السجل company_id+action+request_id. داخل DB transaction يضيف سجل الاستلام أو يجلبه، ويفحص تطابق المستخدم والمحتوى؛ إن توجد نتيجة يرجعها، وإلا ينفذ work ويحفظ نتيجته. يحاول المعاملة حتى ثلاث مرات للأخطاء القابلة لإعادة المحاولة. لا يعد هذا نظام مزامنة Offline كاملًا.

### RepresentativeAccess.php

| الدالة | دورها |
|---|---|
| membership(userId) | قراءة عضوية المستخدم في الشركة الحالية باستخدام Query Builder |
| assertCapability(userId, capability) | عضوية فعالة ودور مندوب ثم can_sell أو can_collect؛ يرفض إذا القدرة مغلقة |
| assertCustomer(userId, Customer) | مندوب فعال، والعميل غير مسند لآخر، ومنطقة العميل ضمن representative_areas عند area_access=selected |

إتاحة المنطقة شيء، ومسؤولية العميل شيء آخر. وصول المندوب إلى الدليل وملف العميل يعتمد على إسناده له أيضًا؛ لا تجعل كل عميل في منطقة مسموحة يظهر تلقائيًا باعتباره عميله.

### MemberAdministration.php

- `save(Request, creating)`: إنشاء أو تعديل حساب. عند التعديل يقفل العضوية و User، ويمنع تعديل المدير/النفس من شاشة الفريق. يفحص الاسم وال username الفريد وصيغة الحروف اللاتينية والأرقام وبعض الرموز، وكلمة المرور وتأكيدها؛ الحد 6 في local/testing مع demo، وإلا 12. يمنع تغيير الهوية العامة أو كلمة مرور حساب مشترك من إدارة شركة واحدة. يحفظ User، ثم pivot role/status/phone/employee_code/coverage/capabilities/notes و operations_version، ويعيد بناء تغطية المناطق، ويسجل الأثر. تغيير دور مندوب له مركبة أو رحلات مفتوحة يحتاج معالجة تلك الروابط.
- `status(Request)`: تعليق/تفعيل عضوية داخل الشركة مع سبب. يمنع المدير وحساب الفاعل نفسه. التاريخ محفوظ؛ التعليق ليس حذف users. عند كل طلب company middleware يعيد فحص العضوية.

### FleetRegistry.php

| الدالة | دورها |
|---|---|
| vehicle(Request, creating) | فحص المركبة والنوع واللوحة والملكية والحمولة والعداد والوثائق؛ يمنع تخفيض العداد وتغيير الجاهزية أثناء رحلة نشطة؛ ينشئ عهدة للمركبة الجديدة ويحفظ audit |
| driver(Request, creating) | فحص هوية السائق ورخصته وحالته؛ يمنع تعطيل سائق قبل إرجاع عهدته |
| assign(Request) | يقفل السائق والمركبة، ويفحص التوفر والرخصة السارية وعدم وجود رحلة نشطة أو إسناد قائم؛ ينشئ vehicle_assignments |
| release(Request) | يقفل الأطراف، ويفحص عدم الإرجاع وعدم الرحلة النشطة؛ يحفظ returned_at/by/note ويفرغ مفاتيح active دون حذف التاريخ |

### RepresentativeRegistry.php

- `details(Request)`: يعدل اسم المندوب وتواصل العضوية ورقمه الوظيفي وملاحظاته، مع operations_version وسبب. إذا كان الحساب مشتركًا يمنع تغيير الاسم العام من هذا التدفق.
- `vehicle(Request)`: تخصيص مركبة عمل للمندوب أو إرجاعها. يفحص current_assignment_id لمنع التعارض، ويمنع الرحلة النشطة والإسناد المزدوج. إذا update_plans=true يطبق المركبة على الرحلات المخططة القابلة للتعديل والقوالب الأسبوعية داخل نفس المعاملة؛ رحلة مخططة قديمة أو غير قابلة للتعديل تمنع العملية كلها. يغلق التخصيص السابق بتاريخه ثم ينشئ الجديد. لا ينقل البضاعة ولا يغيّر استلام السائق تلقائيًا.

### CustomerMaintenance.php

| الدالة | دورها |
|---|---|
| own(Customer) | مدير/مشرف أو المندوب المسؤول؛ منع غير ذلك |
| contact(Request) | قفل العميل، وفحص الملكية و profile_version والحقول وسبب؛ تعديل الاسم والهاتف والعنوان والملاحظات وزيادة النسخة |
| location(Request) | فحص النسخة والإحداثيات؛ الإدارة إلى applyLocation؛ المندوب ينشئ طلب location في customer_changes مع owner_id، ويمنع طلبًا معلقًا ثانيًا لنفس العميل |
| applyLocation(Customer, data) | فحص النسخة وغياب زيارة in_progress؛ يحفظ النقطة ويزيد النسخة، ويبطل route_snapshot للرحلات المخططة/النشطة المتأثرة ويزيد plan_version، ويسجل before/after |
| registration(Request) | مندوب/محاسب؛ يثبت المندوب لنفسه إن كان هو مقدم الطلب؛ ينشئ code إن غاب؛ يفحص customerData والتغطية ويحفظ طلب new_customer معلقًا |
| customerData(Request) | قواعد موحدة للعميل الجديد: كود فريد في الشركة، المنطقة، المسؤول، الإحداثيات، نطاق 25–500 م، بيانات التواصل |
| review(Request) | مدير/مشرف؛ يقفل الطلب، ويتأكد pending. في الموافقة يعيد التحقق من نسخة الموقع/مسؤول العميل أو بيانات العميل الجديد والتغطية؛ ثم يحفظ القرار والمراجع والوقت والملاحظة ويسجل audit |

### TripPlanning.php

| الدالة | دورها |
|---|---|
| blockers(Trip) | قائمة أسباب تمنع إعادة التخطيط: ليست planned أو بدأت/أغلقت، زيارة نفذت/إثبات، طلب مرتبط، نقطة تتبع |
| snapshot(Trip) | يبني تمثيلًا للموارد والتاريخ والمحطات والحالات للمقارنة في audit |
| editable(Request) | يقفل الرحلة، ويفحص id/plan_version/reason وموانع blockers؛ يعيد الرحلة القابلة للتعديل |
| save(Request, creating) | فحص المندوب والمركبة والتاريخ والعملاء والتغطية؛ ربط عهدة المركبة وسائقها الحالي؛ حفظ الرحلة والمحطات وترتيبها. المحطات المحذوفة من الخطة تصبح cancelled ولا تمحى، والتعديل يبطل مسافة الترتيب القديمة |
| cancel(Request) | editable أولًا؛ حالة cancelled وسبب وفاعل ووقت، وزيادة النسخة، وإلغاء المحطات planned وتسجيل أثر قبل/بعد |

### WeeklyRouting.php

- `save(Request)`: قالب فريد للشركة/المندوب/weekday، ومعه نسخة ومركبة وأصل انطلاق وعملاء حتى 50. يفحص أن العملاء نشطون ومسندون للمندوب وتغطيتهم مسموحة. اليوم النشط لا يكون بلا عملاء.
- `publish(Request)`: تاريخ من اليوم إلى 35 يومًا يطابق weekday، وخطة فعالة بالنسخة نفسها. يعيد الرحلة المنشورة سابقًا إن وجدت، وإلا يمنع رحلة مفتوحة أخرى لذلك اليوم، ويحسب ترتيب nearest، ثم TripPlanning::save ويحفظ مرجع القالب و route_snapshot والأثر.
- `nearest(customers, lat, lng)`: يكرر ترتيب العملاء حسب قربهم من النقطة الحالية، يأخذ الأقرب ويجعله أصل الخطوة التالية حتى النهاية. عند تساوي المسافة يحسم بمعرف العميل. يعيد IDs مرتبة وإجمالي أمتار مستقيمة، وليس أفضل مسار على شبكة طرق أو مسافة عودة للمستودع.

### VisitWorkflow.php

| الدالة | دورها |
|---|---|
| __construct | يستقبل AuditRecorder تلقائيًا |
| checkIn(Visit, location) | transaction وقفل؛ expect planned؛ عميل فعال ويوم الزيارة اليوم؛ موقع عمره≤120 ث وليس مستقبليًا بأكثر من 15 ث، ودقة≤100 م؛ distance≤geofence؛ حفظ الإثبات والحالة in_progress |
| finish(Visit, VisitStatus, note, reasonCode) | transaction وقفل؛ skipped من planned، أما completed/failed فمن in_progress؛ completed يحتاج إثباتًا؛ حفظ النتيجة والوقت والأثر |
| expect(Visit, expected) | ValidationException إذا الحالة الحالية لا تطابق المنتظرة؛ يمنع انتقالًا غير صالح |
| distance(lat1,lon1,lat2,lon2) | حساب Haversine بقطر أرض تقريبي، يعيد المسافة بالمتر؛ يستخدم في النطاق وترتيب العملاء وفحص القفزات |

توجد ضوابط رحلة وترتيب وأسباب في Controller قبل الخدمة. استدعاء finish مباشرة من مكان جديد لا يطبق تلك الضوابط تلقائيًا؛ إما توحدها في خدمة أشمل مستقبلًا أو تراعيها عند أي مدخل جديد.

### StockLedger.php

`move(Warehouse, Product, quantity, reason, reference)`: المستدعي يمسك transaction. ينشئ الرصيد إن غاب ويقفله، وخصم الكمية يجري بشرط الرصيد الكافي؛ وإلا validation ولا حركة. الموجب يزيد الرصيد. بعدها يكتب StockMovement بالكمية ذات الإشارة والمرجع والفاعل. تعديل quantity في balance يدويًا دون حركة يكسر تطابق الدفتر.

### TradingWorkflow.php

- `__construct`: يحتاج StockLedger و AuditRecorder.
- `create(data)`: ينشئ كود SO عشوائي ومسودة الطلب؛ لكل صنف فعال يقرأ السعر من Product، يحسب سطرًا ويحفظ سعره وكمية وإجماليًا. يجمع total_minor ويحدد initial_paid_minor من cash/partial/credit، ويرفض جزئيًا يساوي أو يتجاوز الإجمالي، ويحفظ audit. لا يخصم المخزون هنا.
- `fulfill(Order)`: قفل الطلب والتأكد draft، وقفل العميل وحساب دينه المفتوح، ثم مقارنة الدين الجديد مع credit_limit_minor إن كان محددًا. يخصم كل صنف من عهدته عبر StockLedger، ويغير الحالة fulfilled ويسجل الأثر، ثم ينادي collect إذا هناك مبلغ أولي.
- `collect(Order, amount, method)`: قفل الطلب، ويطلب fulfilled ومبلغًا لا يتجاوز المتبقي؛ ينشئ إيصال RC ويزيد paid_minor ويسجل الأثر. الحد الأدنى وطريقة الدفع يتحقق منهما Controller قبل النداء. لا تعزلها عن transaction والصلاحيات عند إعادة استخدامها.

### CashCustody.php

| الدالة | دورها |
|---|---|
| __construct | يستقبل AuditRecorder |
| lockCollector(id) | يقفل عضوية حامل النقد ويتأكد أنه عضو بالشركة؛ لتنظيم المعالجة المتزامنة |
| balances(onlyUser?) | يحسب cash collections − accepted handovers، والمعلق محجوز؛ يرجع received/accepted/pending/balance/available لكل عضو |
| request(collector,amount,reference,note) | يقفل العضوية، ويحسب المتاح بعد المعلق والمعتمد، ويرفض التجاوز، وينشئ طلب CH معلقًا ويؤرخه |
| review(handover,accept,note) | قفل؛ pending فقط؛ المراجع مختلف عن الحامل والمقدم؛ accepted أو rejected مع هوية المراجع والوقت |
| cancel(handover,note) | الحامل أو مقدم الطلب فقط، pending فقط؛ cancelled مع السبب، ويبقى التاريخ |

### OperationalAnalytics.php

- `orders(filters)`: Query Builder لموديل Order مع مرشحات المندوب والمنطقة عبر العميل؛ لا ينفذ get بعد.
- `visits(filters)`: مثلها للزيارات مع استبعاد cancelled.
- `build(filters)`: يبني الأيام وفق timezone الشركة وحدود UTC، ويحسب المبيعات من طلبات fulfilled **أُنشئت** داخل الفترة، والتحصيل من collected_at داخل الفترة ولو لفواتير قديمة، وأداء المندوب ووسائل الاستلام. أعمار الآجل هي الرصيد المفتوح الحالي عبر التاريخ ضمن المرشحات، لا snapshot تاريخية لنهاية الفترة. يحجب التفاصيل المالية للمشاهد والتشغيلية للمحاسب. يرجع definitions لتفسير اختلاف المقاييس.

## 4. Middleware و Support و Provider و Policy و Enums

| الملف/الدالة | التفسير |
|---|---|
| SetCompanyContext::handle | حساب جلسة الويب: شركة فعالة وعضوية فعالة ودور صحيح؛ تحديد السياق ومشاركته مع Views ثم clear في finally |
| SetApiCompanyContext::handle | قراءة X-Company-ID والتحقق من العضوية ومن ability الرمز read/write ثم سياق مؤقت |
| SecurityHeaders::handle | بعد المعالجة يضيف nosniff ومنع framing و Referrer/Permissions/CSP و no-store. يسمح ببلاطات OSM وبالموقع من نفس الأصل، ويمنع كاميرا/ميكروفون؛ إعداد HTTPS والخادم مسؤولية نشر منفصلة |
| CompanyContext::set | تعيين Company و CompanyRole لدورة الطلب |
| CompanyContext::id | معرف الشركة؛ يرمي LogicException إن لم تحدد شركة بدل افتراض شركة عشوائية |
| CompanyContext::clear | إعادة company و role إلى null |
| AppServiceProvider::register | ربط CompanyContext scoped في حاوية الخدمات |
| AppServiceProvider::boot | تعريف Gates و RateLimiter للدخول |
| VisitPolicy::view | نفس الشركة ودور يرى التشغيل أو المندوب المسؤول |
| VisitPolicy::execute | نفس الشركة والمندوب المسؤول فقط؛ الإدارة تخطط ولا تزور باسمه. الاستخدام المباشر ظاهر بال Controller القديم، أما المدخل الحالي فينفذ فحصه داخل OperationsController |
| CompanyRole::label | اسم عربي للقيمة الإنجليزية |
| CompanyRole::managesOperations | true للمدير والمشرف |
| CompanyRole::seesOperations | true للمدير والمشرف والمشاهد |
| VisitStatus::label | ترجمة planned/in_progress/completed/failed/skipped/cancelled |

## 5. Models وكل دوال علاقاتها وتحويلها

أغلب الكلاسات أدناه ترث TenantModel، بينما User يرث Authenticatable و Company يرث Model العادي. العلاقة دالة تعيد تعريف العلاقة؛ `with('area')` يجلبها مسبقًا، و`$customer->area` يستخدم السجل المرتبط.

| الكلاس | الدوال وما تعنيه |
|---|---|
| TenantModel | booted: global scope للشركة، creating لإسناد company_id، updating لمنع تغيير الشركة. هذه closures أحداث على Eloquent |
| User | companies: belongsToMany عبر company_user و role/is_active والتواريخ؛ casts: email_verified_at تاريخ و password hashed. HasApiTokens للرموز و HasFactory للاختبارات و Notifiable أساس إشعارات، لا دليل على إرسال إشعارات فعلية |
| Company | users: علاقة متعددة مع User و pivot؛ casts: is_active منطقي؛ attributes افتراضية SAR/AsiaRiyadh/distribution/active |
| Area | لا دوال خاصة؛ fillable name/city |
| Customer | area: belongsTo Area؛ representative: belongsTo User؛ casts: إحداثيات 7 منازل ومنطقي للحالة |
| CustomerChange | customer/submitter/reviewer: أصحاب الروابط؛ casts: payload array و reviewed_at datetime |
| Driver | casts: license_expires_on تاريخ؛ بيانات سائق لا حساب مصادقة |
| Vehicle | لا دوال خاصة؛ TYPES قائمة 8 أنواع، و fillable الحقول المسموح تعبئتها |
| VehicleAssignment | driver: سائق العهدة؛ casts: assigned_at/returned_at datetime |
| RepresentativeVehicleAssignment | vehicle/representative/assigner: المركبة والمندوب والمسند؛ casts: أوقات الإسناد والإرجاع |
| Warehouse | vehicle: المركبة إن كان مخزن عهدة، وإلا قد تكون null |
| Product | casts: price_minor/reorder_level integers و is_active boolean |
| StockBalance | warehouse/product: طرفا الرصيد؛ casts quantity integer |
| StockMovement | warehouse/product: طرفا الحركة؛ casts created_at datetime و quantity integer؛ timestamps=false لأن التصميم يكتب created_at فقط |
| Order | customer/representative/warehouse: belongsTo؛ items: hasManyOrderItem؛ casts: total/paid/initial_paid integers |
| OrderItem | product: belongsTo؛ لا timestamps تلقائية |
| Collection | order/actor: الفاتورة والمستلم؛ casts amount integer و collected_at datetime؛ لا timestamps تلقائية |
| DebtFollowup | order/actor: الفاتورة وكاتب المتابعة؛ casts promised_minor integer و created_at datetime |
| CashHandover | collector/reviewer: حامل النقد ومراجع التسليم؛ casts amount integer و reviewed_at datetime |
| Trip | vehicle/representative/warehouse/driver: belongsTo؛ visits: hasMany؛ casts: تاريخ التنفيذ والأوقات و plan_version integer |
| Visit | customer/representative: belongsTo؛ casts: status إلى VisitStatus، تواريخ تنفيذ وإثبات وحالة تحقق boolean |
| LocationPoint | casts: captured_at و received_at datetime؛ لا created_at/update تلقائيين |
| WeeklyPlan | representative/vehicle: belongsTo؛ casts: customer_ids array و weekday/version integers والحالة boolean وإحداثيات الأصل decimal7 |
| AuditLog | casts: metadata array و created_at datetime؛ لا timestamps تلقائية |

`fillable` قائمة ما يقبل mass assignment في create/fill، وليست كل أعمدة الجدول. الأعمدة مثل paid_minor أو profile_version قد تُسنَد صراحة في الكود. `guarded=['id','company_id']` يمنع تعبئة مفاتيح النظام جماعيًا في بعض Models؛ يبقى واجبًا تمرير المدخلات المعتمدة فقط. `hidden` يحجب حقولًا عند JSON، ولا يحذفها من القاعدة ولا يعوض التحقق من الصلاحيات.

## 6. دوال Laravel/PHP التي تتكرر وكيف تقرأها

| التعبير | المعنى |
|---|---|
| Request $request | الطلب وحقوله و Headers والجلسة؛ Laravel يمرره تلقائيًا |
| $request->validate([...]) | فحص القيم؛ يرجع البيانات المطابقة أويرد بأخطاء validation |
| required / nullable / present | مطلوب بقيمة؛ يمكن null؛ المفتاح يجب أن يوجد حتى لو null/قائمة فارغة بحسب بقية القواعد |
| Rule::exists / unique / in / enum | وجود مفتاح، تفرد، قائمة قيم، Enum؛ Query Builder لا يضيف companyscope لهذه القواعد وحده |
| app(Class::class) | حل خدمة من الحاوية؛ Class::class نص الاسم الكامل للكلاس |
| auth()->id()/user() | المستخدم الحالي من guard؛ ليس هو CompanyId |
| abort_unless/abort_if | إيقاف الطلب بكود HTTP عندما شرط الإذن/العمل غير متحقق |
| ValidationException::withMessages | رسالة تخص حقلًا مثل amount أو status |
| Model::query()/where/whereIn/whereHas | بناء استعلام؛ لا يقرأ كل البيانات بمجرد كل where |
| findOrFail/firstOrFail | أول سجل أو 404؛ scope الشركة ما زال مطبقًا في TenantModel |
| get/first/value/pluck/count/sum | تنفيذ الاستعلام: قائمة، سجل، قيمة، عمود، عدد، مجموع |
| with/withCount | جلب العلاقات/عددها مبكرًا لتقليل الاستعلامات المتكررة |
| when | إضافة شرط اختياري للاستعلام حسب الدور/الفلتر |
| create/fill/save/update | إنشاء، تعبئة، حفظ، تحديث؛ الفرق بين Model و DB::table مهم للأحداث وال casts |
| lockForUpdate | طلب قفل حسب محرك القاعدة داخل المعاملة؛ SQLite لا يملك قفل صفوف مطابقًا لـ PostgreSQL، فلا تعتبر نجاح اختبار محلي دليل تحمل إنتاجي |
| DB::transaction(fn...) | تنفيذ كتلة كاملة أوالتراجع عند فشل؛ المعاملات تمنع حفظ نصف بيع ونصف حركة |
| clone $query | نسخة استعلام لتضيف شروطًا مستقلة دون تغيير الأصل |
| map/filter/groupBy/keyBy/values | عمليات Laravel Collection في الذاكرة؛ ليست كلها SQL إذاجاءت بعد get |
| response()->json / view / redirect | ردبيانات، أو HTML، أوتوجيه لرابط |
| now/today/CarbonImmutable | الوقت والتاريخ؛ راع UTC للتخزين و timezone الشركة للعرض واليوم |
| fn($x)=>... | دالة مختصرة؛ closure function()use(...) تحمل متغيرات من النطاق الخارجي |
| match / switch | اختيار فرع بناءعلى قيمة؛ match يرجع قيمة وتطابقه صارم |
| try/finally | تنفيذ تنظيف حتى عندخطأ؛ هنا مسح سياق الشركة |
| Str::random / uuid | إنشاءرموز؛ لا تستخدم time وحده بديل UUID لمنع التكرار |
| hash / hash_equals / json_encode | بصمةمحتوى ومقارنةآمنة وتمثيل JSON؛ ليست كلهاهاش كلمةمرور |
| Hash::check / cast hashed | الأول يتحقق من كلمةمرور، والثاني يحفظهابتمثيل Hash |

مرجع الاستعلامات والعلاقات الأساسي [Eloquent الرسمي](https://laravel.com/docs/13.x/eloquent). أمثلة هذا الفصل تصف مواضع استعمالها في amrbit تحديدًا.
