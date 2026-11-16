# 07 — قاموس البنية الفعلية لقاعدة البيانات

مستخرج للقراءة فقط بتاريخ 25 أكتوبر 2026 من `C:\xampp\htdocs\fieldflow-next\database\database.sqlite`، وعدد الجداول **35**. الأعداد لقطة وقد تتغير. لا توجد بيانات أشخاص أو Hashes أو محتويات جلسات ضمن هذا الملف.

يعرض SQLite أنواعًا مثل varchar و integer و datetime كما عرّفتها Migrations؛ قواعد validation وطول النصوص والحالات في PHP تبقى جزءًا من الضبط. عمود «يسمح null؟» مأخوذ من PRAGMA table_info، والمفتاح الأساسي موسوم منفصلًا. default=— يعني لا تعبير افتراضي معرف في البنية، وليس بالضرورة القيمة الحالية للسجل.

**العلاقة المركبة تُقرأ ككتلة واحدة:** مثل (vehicle_id,company_id) → vehicles(id,company_id)، لا كعلاقتين مستقلتين. NOT NULL والتفرد والمفتاح الأجنبي قواعد بنية، أما is_active/role/can_sell فشروط عمل تفحصها الخدمات.

| الجدول | عدد السجلات وقت الفحص | المعنى |
|---|---:|---|
| areas | 8 | المناطق داخل الشركة؛ ليست مضلعات جغرافية مرسومة حاليًا. |
| audit_logs | 27 | سجل الإجراءات والفاعل والمورد والبيانات المفسرة؛ ليس سجل أخطاء PHP. |
| cache | 48 | قيم كاش Laravel ومنها ما تستخدمه محددات المحاولات؛ ليست بيانات عميل أصلية. |
| cache_locks | 0 | أقفال الكاش المساعدة. |
| cash_handovers | 1 | طلبات تسليم النقد ومراجعتها، منفصلة عن استلام العميل. |
| collections | 88 | إيصالات استلام مبالغ على طلبات مسلمة؛ كل إيصال له فاعل وطريقة وتاريخ. |
| companies | 2 | الشركات وبيئة كل شركة: اسم وصناعة وعملة ومنطقة زمنية وحالة. |
| company_user | 16 | عضوية هوية المستخدم داخل الشركة ودورها وقدراتها وبياناتها الوظيفية. |
| customer_changes | 0 | طلبات تسجيل عميل جديد أو تصحيح نقطة الموقع وقرار المراجعة. |
| customers | 48 | الملف الأساسي للعميل ومسؤوله وموقعه ونطاقه وحده الائتماني. |
| debt_followups | 2 | تواصل ووعد وموعد متابعة؛ لا تسدد رصيدًا بمجرد تسجيلها. |
| drivers | 1 | دليل السائقين ورخصهم وحالتهم؛ لا يمثل حسابات دخول. |
| failed_jobs | 0 | أخطاء مهام الطابور عند استعماله؛ البنية موجودة ولو لم توجد مهمة فاشلة. |
| job_batches | 0 | مجموعات المهام وأعدادها وحالتها. |
| jobs | 0 | مهام طابور قاعدة البيانات ووقت إتاحتها ومحاولاتها. |
| location_points | 180 | نقاط رحلة بوقت التقاط واستلام ومصدر ودقة، مستقلة عن نقطة العميل. |
| migrations | 15 | تاريخ ملفات تطوير البنية التي طبقها Laravel. |
| operation_receipts | 20 | حفظ نتيجة action بمعرف UUID وبصمة محتوى لمنع تكرار العملية. |
| order_items | 86 | أسطر الطلب؛ سعر الوحدة محفوظ وقت إنشاء السطر. |
| orders | 85 | رأس الطلب: عميل ومندوب وعهدة ورحلة وشروط وإجمالي ومدفوع وحالة. |
| password_reset_tokens | 0 | بنية Laravel لاسترجاع كلمة المرور؛ لا توجد شاشة استرجاع مفعلة في المسارات الحالية. |
| personal_access_tokens | 0 | رموز Sanctum المجزأة وصلاحياتها وانتهاؤها؛ لا تحفظ الرمز الواضح هنا. |
| products | 9 | الأصناف وأسعارها ووحداتها ونقطة إعادة الطلب. |
| representative_areas | 0 | مناطق مسموحة للمندوب عندما area_access=selected؛ فراغه ليس خطأ إن التغطية all. |
| representative_vehicle_assignments | 0 | تاريخ تخصيص مركبة عمل للمندوب؛ ليس محضر استلام السائق. |
| sessions | 3 | جلسات دخول الويب؛ محتوى payload ليس بيانات تقارير أعمال. |
| stock_balances | 57 | الرصيد الحالي حسب الشركة والموقع والصنف. |
| stock_movements | 143 | دفتر مخزون بكميات موجبة وسالبة ومرجع وفاعل؛ راجع مطابقته مع الرصيد. |
| trips | 14 | رحلات مؤرخة بموارد وحالة وخطة ونسخة وأوقات تنفيذ/إلغاء. |
| users | 15 | هويات الدخول العامة؛ الدور موجود في company_user وليس عمود role هنا. |
| vehicle_assignments | 1 | سجل تسليم المركبة لسائق وإرجاعه، مع مفاتيح تمنع الازدواج النشط. |
| vehicles | 13 | المركبات وأنواعها ولوحاتها وملكيتها وحمولتها ووثائقها وجاهزيتها. |
| visits | 54 | محطات العملاء في الرحلات وحالة التنفيذ وإثبات القرب والنتائج. |
| warehouses | 15 | مستودع عادي أو عهدة مركبة؛ vehicle_id للعهدة المرتبطة بمركبة. |
| weekly_plans | 1 | قالب يوم في الأسبوع لمندوب، وقائمة عملائه ومركبته ونقطة الانطلاق؛ ليس رحلة مؤرخة. |

## areas

المناطق داخل الشركة؛ ليست مضلعات جغرافية مرسومة حاليًا.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| name | varchar | لا | — | — | الاسم الظاهر. |
| city | varchar | لا | — | — | المدينة. |
| created_at | datetime | نعم | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |
| updated_at | datetime | نعم | — | — | آخر وقت تحديث للسجل. |

**المفاتيح الأجنبية**

- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `areas_id_company_id_unique` على `(id, company_id)`؛ UNIQUE.
- `areas_company_id_name_unique` على `(company_id, name)`؛ UNIQUE.

## audit_logs

سجل الإجراءات والفاعل والمورد والبيانات المفسرة؛ ليس سجل أخطاء PHP.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| actor_id | INTEGER | نعم | — | — | فاعل الحدث؛ في collections هو مستلم المبلغ. |
| action | varchar | لا | — | — | اسم الإجراء. |
| subject_type | varchar | لا | — | — | اسم جدول المورد الذي يتعلق به الأثر. |
| subject_id | INTEGER | لا | — | — | معرف المورد المتأثر. |
| metadata | TEXT | نعم | — | — | JSON تفاصيل الأثر مثل before/after/reason؛ لا يضم IP تلقائيًا. |
| created_at | datetime | لا | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |

**المفاتيح الأجنبية**

- `(actor_id) → users(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `audit_logs_company_id_created_at_index` على `(company_id, created_at)`؛ INDEX.

## cache

قيم كاش Laravel ومنها ما تستخدمه محددات المحاولات؛ ليست بيانات عميل أصلية.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| key | varchar | لا | — | نعم | مفتاح قيمة كاش/قفل. |
| value | TEXT | لا | — | — | تمثيل قيمة الكاش. |
| expiration | INTEGER | لا | — | — | انتهاء الكاش/القفل كعدد ثوان Unix. |

**المفاتيح الأجنبية**

لا توجد مفاتيح أجنبية معلنة في SQLite لهذا الجدول؛ أي تحقق منطقي في الخدمة يظل مهمًا.

**الفهارس والتفرد**

- `cache_expiration_index` على `(expiration)`؛ INDEX.
- `sqlite_autoindex_cache_1` على `(key)`؛ UNIQUE.

## cache_locks

أقفال الكاش المساعدة.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| key | varchar | لا | — | نعم | مفتاح قيمة كاش/قفل. |
| owner | varchar | لا | — | — | مالك قفل الكاش. |
| expiration | INTEGER | لا | — | — | انتهاء الكاش/القفل كعدد ثوان Unix. |

**المفاتيح الأجنبية**

لا توجد مفاتيح أجنبية معلنة في SQLite لهذا الجدول؛ أي تحقق منطقي في الخدمة يظل مهمًا.

**الفهارس والتفرد**

- `cache_locks_expiration_index` على `(expiration)`؛ INDEX.
- `sqlite_autoindex_cache_locks_1` على `(key)`؛ UNIQUE.

## cash_handovers

طلبات تسليم النقد ومراجعتها، منفصلة عن استلام العميل.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| code | varchar | لا | — | — | رمز المورد/المستند ضمن الشركة. |
| collector_id | INTEGER | لا | — | — | حامل عهدة النقد المراد تسليمها. |
| requested_by | INTEGER | لا | — | — | مقدم طلب تسليم النقد. |
| amount_minor | INTEGER | لا | — | — | المبلغ بالوحدة الصغيرة. |
| status | varchar | لا | 'pending' | — | حالة المورد؛ القيم حسب الجدول وموضحة أسفل القاموس. |
| reference | varchar | لا | — | — | مرجع المستند/الحركة للمراجعة. |
| note | varchar | نعم | — | — | ملاحظة العملية. |
| reviewed_by | INTEGER | نعم | — | — | هوية المراجع. |
| reviewed_at | datetime | نعم | — | — | وقت المراجعة. |
| review_note | varchar | نعم | — | — | سبب أو ملاحظة القرار. |
| created_at | datetime | نعم | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |
| updated_at | datetime | نعم | — | — | آخر وقت تحديث للسجل. |

**المفاتيح الأجنبية**

- `(company_id, collector_id) → company_user(company_id, user_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(reviewed_by) → users(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(requested_by) → users(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `cash_handovers_company_id_collector_id_status_index` على `(company_id, collector_id, status)`؛ INDEX.
- `cash_handovers_company_id_code_unique` على `(company_id, code)`؛ UNIQUE.

## collections

إيصالات استلام مبالغ على طلبات مسلمة؛ كل إيصال له فاعل وطريقة وتاريخ.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| order_id | INTEGER | لا | — | — | الطلب المرتبط. |
| actor_id | INTEGER | لا | — | — | فاعل الحدث؛ في collections هو مستلم المبلغ. |
| receipt | varchar | لا | — | — | رقم إيصال الاستلام. |
| amount_minor | INTEGER | لا | — | — | المبلغ بالوحدة الصغيرة. |
| method | varchar | لا | — | — | cash/bank/card؛ لا يثبت تحققًا مصرفيًا خارجيًا. |
| collected_at | datetime | لا | — | — | وقت استلام العميل. |

**المفاتيح الأجنبية**

- `(order_id, company_id) → orders(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(actor_id) → users(id)`؛ حذف: NO ACTION؛ تحديث: NO ACTION.
- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `collections_company_id_receipt_unique` على `(company_id, receipt)`؛ UNIQUE.

## companies

الشركات وبيئة كل شركة: اسم وصناعة وعملة ومنطقة زمنية وحالة.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| name | varchar | لا | — | — | الاسم الظاهر. |
| slug | varchar | لا | — | — | مفتاح نصي للشركة؛ تغيير الاسم الظاهر لا يستلزم تغييره. |
| industry | varchar | لا | 'distribution' | — | تصنيف نشاط الشركة؛ البيانات التجريبية قد تحمل مثال القطاع القديم. |
| timezone | varchar | لا | 'Asia/Riyadh' | — | اسم المنطقة الزمنية مثل Asia/Riyadh. |
| currency | varchar | لا | 'SAR' | — | رمز العملة مثل SAR. |
| is_active | tinyint(1) | لا | '1' | — | نشاط المورد أو العضوية؛ ليس حذفًا لتاريخه. |
| created_at | datetime | نعم | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |
| updated_at | datetime | نعم | — | — | آخر وقت تحديث للسجل. |

**المفاتيح الأجنبية**

لا توجد مفاتيح أجنبية معلنة في SQLite لهذا الجدول؛ أي تحقق منطقي في الخدمة يظل مهمًا.

**الفهارس والتفرد**

- `companies_slug_unique` على `(slug)`؛ UNIQUE.

## company_user

عضوية هوية المستخدم داخل الشركة ودورها وقدراتها وبياناتها الوظيفية.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| user_id | INTEGER | لا | — | — | هوية المستخدم المرتبط؛ راجع الجدول لتحديد دوره في هذا السجل. |
| role | varchar | لا | 'viewer' | — | دور العضوية: manager/supervisor/representative/accountant/viewer. |
| is_active | tinyint(1) | لا | '1' | — | نشاط المورد أو العضوية؛ ليس حذفًا لتاريخه. |
| created_at | datetime | نعم | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |
| updated_at | datetime | نعم | — | — | آخر وقت تحديث للسجل. |
| phone | varchar | نعم | — | — | رقم تواصل نصي للحفاظ على + والأصفار. |
| employee_code | varchar | نعم | — | — | الرقم الوظيفي الفريد داخل الشركة بحسب الجدول. |
| area_access | varchar | لا | 'all' | — | all أو selected؛ الثانية تستخدم representative_areas. |
| can_sell | tinyint(1) | لا | '1' | — | إتاحة البيع للمندوب في الشركة. |
| can_collect | tinyint(1) | لا | '1' | — | إتاحة استلام الأموال للمندوب. |
| notes | varchar | نعم | — | — | ملاحظات تشغيلية. |
| operations_version | INTEGER | لا | '1' | — | نسخة بيانات عضوية المندوب لكشف التعديل المتعارض. |

**المفاتيح الأجنبية**

- `(user_id) → users(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `company_user_company_id_employee_code_unique` على `(company_id, employee_code)`؛ UNIQUE.
- `company_user_company_id_user_id_unique` على `(company_id, user_id)`؛ UNIQUE.

## customer_changes

طلبات تسجيل عميل جديد أو تصحيح نقطة الموقع وقرار المراجعة.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| customer_id | INTEGER | نعم | — | — | العميل المرتبط؛ قد يكون فارغًا في طلب تسجيل لم يعتمد بعد. |
| submitted_by | INTEGER | لا | — | — | هوية مقدم طلب التغيير. |
| kind | varchar | لا | — | — | نوع الطلب/الحركة؛ في customer_changes هو location أو new_customer. |
| status | varchar | لا | 'pending' | — | حالة المورد؛ القيم حسب الجدول وموضحة أسفل القاموس. |
| payload | TEXT | لا | — | — | محتوى؛ JSON في طلب العميل، وتمثيل تقني في الجلسات والمهام؛ لا تعرض المحتويات الحساسة. |
| reviewed_by | INTEGER | نعم | — | — | هوية المراجع. |
| review_note | TEXT | نعم | — | — | سبب أو ملاحظة القرار. |
| reviewed_at | datetime | نعم | — | — | وقت المراجعة. |
| created_at | datetime | نعم | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |
| updated_at | datetime | نعم | — | — | آخر وقت تحديث للسجل. |

**المفاتيح الأجنبية**

- `(customer_id, company_id) → customers(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(reviewed_by) → users(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(submitted_by) → users(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `customer_changes_company_id_status_submitted_by_index` على `(company_id, status, submitted_by)`؛ INDEX.

## customers

الملف الأساسي للعميل ومسؤوله وموقعه ونطاقه وحده الائتماني.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| area_id | INTEGER | لا | — | — | المنطقة المرتبطة. |
| code | varchar | لا | — | — | رمز المورد/المستند ضمن الشركة. |
| name | varchar | لا | — | — | الاسم الظاهر. |
| phone | varchar | نعم | — | — | رقم تواصل نصي للحفاظ على + والأصفار. |
| address | varchar | لا | — | — | وصف العنوان والوصول. |
| latitude | numeric | لا | — | — | خط العرض. |
| longitude | numeric | لا | — | — | خط الطول. |
| geofence_radius_m | INTEGER | لا | '150' | — | نصف قطر إثبات القرب بالمتر؛ الفحص عند الزيارة. |
| is_active | tinyint(1) | لا | '1' | — | نشاط المورد أو العضوية؛ ليس حذفًا لتاريخه. |
| created_at | datetime | نعم | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |
| updated_at | datetime | نعم | — | — | آخر وقت تحديث للسجل. |
| credit_limit_minor | INTEGER | نعم | — | — | حد الدين الجديد بالوحدة الصغيرة؛ null بلا حد معين و 0 يمنع دينًا جديدًا. |
| representative_id | INTEGER | نعم | — | — | المندوب المسؤول في سياق السجل؛ لا يعيد تعيين كل التاريخ عند تغيير العميل. |
| service_notes | TEXT | نعم | — | — | تعليمات خدمة العميل. |
| profile_version | INTEGER | لا | '1' | — | نسخة ملف العميل للتعديل الآمن. |

**المفاتيح الأجنبية**

- `(company_id, representative_id) → company_user(company_id, user_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(area_id, company_id) → areas(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `customers_company_id_representative_id_is_active_index` على `(company_id, representative_id, is_active)`؛ INDEX.
- `customers_id_company_id_unique` على `(id, company_id)`؛ UNIQUE.
- `customers_company_id_code_unique` على `(company_id, code)`؛ UNIQUE.
- `customers_company_id_area_id_index` على `(company_id, area_id)`؛ INDEX.

## debt_followups

تواصل ووعد وموعد متابعة؛ لا تسدد رصيدًا بمجرد تسجيلها.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| order_id | INTEGER | لا | — | — | الطلب المرتبط. |
| actor_id | INTEGER | لا | — | — | فاعل الحدث؛ في collections هو مستلم المبلغ. |
| outcome | varchar | لا | — | — | نتيجة متابعة الدين: contacted/no_answer/promised. |
| note | varchar | لا | — | — | ملاحظة العملية. |
| next_followup_date | date | لا | — | — | موعد المتابعة التالية. |
| promised_minor | INTEGER | نعم | — | — | مبلغ وعد بالسداد، وليس مبلغًا مستلمًا. |
| created_at | datetime | لا | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |

**المفاتيح الأجنبية**

- `(order_id, company_id) → orders(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(actor_id) → users(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `debt_followups_company_id_next_followup_date_index` على `(company_id, next_followup_date)`؛ INDEX.

## drivers

دليل السائقين ورخصهم وحالتهم؛ لا يمثل حسابات دخول.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| name | varchar | لا | — | — | الاسم الظاهر. |
| phone | varchar | نعم | — | — | رقم تواصل نصي للحفاظ على + والأصفار. |
| employee_code | varchar | لا | — | — | الرقم الوظيفي الفريد داخل الشركة بحسب الجدول. |
| license_number | varchar | نعم | — | — | رقم رخصة السائق. |
| license_expires_on | date | نعم | — | — | تاريخ انتهاء الرخصة. |
| status | varchar | لا | 'active' | — | حالة المورد؛ القيم حسب الجدول وموضحة أسفل القاموس. |
| notes | varchar | نعم | — | — | ملاحظات تشغيلية. |
| created_at | datetime | نعم | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |
| updated_at | datetime | نعم | — | — | آخر وقت تحديث للسجل. |

**المفاتيح الأجنبية**

- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `drivers_id_company_id_unique` على `(id, company_id)`؛ UNIQUE.
- `drivers_company_id_employee_code_unique` على `(company_id, employee_code)`؛ UNIQUE.

## failed_jobs

أخطاء مهام الطابور عند استعماله؛ البنية موجودة ولو لم توجد مهمة فاشلة.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| uuid | varchar | لا | — | — | معرف المهمة الفاشلة. |
| connection | TEXT | لا | — | — | اتصال المهمة. |
| queue | TEXT | لا | — | — | اسم الطابور. |
| payload | TEXT | لا | — | — | محتوى؛ JSON في طلب العميل، وتمثيل تقني في الجلسات والمهام؛ لا تعرض المحتويات الحساسة. |
| exception | TEXT | لا | — | — | تفاصيل فشل المهمة؛ قد تكون حساسة. |
| failed_at | datetime | لا | CURRENT_TIMESTAMP | — | وقت الفشل. |

**المفاتيح الأجنبية**

لا توجد مفاتيح أجنبية معلنة في SQLite لهذا الجدول؛ أي تحقق منطقي في الخدمة يظل مهمًا.

**الفهارس والتفرد**

- `failed_jobs_uuid_unique` على `(uuid)`؛ UNIQUE.

## job_batches

مجموعات المهام وأعدادها وحالتها.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | varchar | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| name | varchar | لا | — | — | الاسم الظاهر. |
| total_jobs | INTEGER | لا | — | — | إجمالي مهام الدفعة. |
| pending_jobs | INTEGER | لا | — | — | المتبقي. |
| failed_jobs | INTEGER | لا | — | — | عدد الفاشل. |
| failed_job_ids | TEXT | لا | — | — | قائمة معرفات الفشل. |
| options | TEXT | نعم | — | — | خيارات تقنية للدفعة. |
| cancelled_at | INTEGER | نعم | — | — | وقت الإلغاء؛ صيغة التخزين بحسب الجدول. |
| created_at | INTEGER | لا | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |
| finished_at | INTEGER | نعم | — | — | وقت إنهاء الزيارة. |

**المفاتيح الأجنبية**

لا توجد مفاتيح أجنبية معلنة في SQLite لهذا الجدول؛ أي تحقق منطقي في الخدمة يظل مهمًا.

**الفهارس والتفرد**

- `sqlite_autoindex_job_batches_1` على `(id)`؛ UNIQUE.

## jobs

مهام طابور قاعدة البيانات ووقت إتاحتها ومحاولاتها.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| queue | varchar | لا | — | — | اسم الطابور. |
| payload | TEXT | لا | — | — | محتوى؛ JSON في طلب العميل، وتمثيل تقني في الجلسات والمهام؛ لا تعرض المحتويات الحساسة. |
| attempts | INTEGER | لا | — | — | عدد محاولات المهمة. |
| reserved_at | INTEGER | نعم | — | — | وقت حجز المهمة Unix إن حجزت. |
| available_at | INTEGER | لا | — | — | وقت إتاحة المهمة Unix. |
| created_at | INTEGER | لا | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |

**المفاتيح الأجنبية**

لا توجد مفاتيح أجنبية معلنة في SQLite لهذا الجدول؛ أي تحقق منطقي في الخدمة يظل مهمًا.

**الفهارس والتفرد**

- `jobs_queue_index` على `(queue)`؛ INDEX.

## location_points

نقاط رحلة بوقت التقاط واستلام ومصدر ودقة، مستقلة عن نقطة العميل.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| trip_id | INTEGER | لا | — | — | الرحلة المرتبطة. |
| user_id | INTEGER | لا | — | — | هوية المستخدم المرتبط؛ راجع الجدول لتحديد دوره في هذا السجل. |
| event_id | varchar | لا | — | — | UUID نقطة التتبع لمنع تكرارها. |
| latitude | numeric | لا | — | — | خط العرض. |
| longitude | numeric | لا | — | — | خط الطول. |
| accuracy_m | INTEGER | لا | — | — | دقة الموقع بالمتر. |
| speed_kmh | numeric | نعم | — | — | سرعة اختيارية مرسلة بالكيلومتر/ساعة. |
| captured_at | datetime | لا | — | — | وقت التقاط نقطة الموقع على الجهاز. |
| received_at | datetime | لا | — | — | وقت استلام الخادم للنقطة. |
| source | varchar | لا | 'web' | — | مصدر نقطة الموقع: demo/web/device بحسب المسار. |
| quality | varchar | لا | 'accepted' | — | حالة جودة النقطة المقبولة. |

**المفاتيح الأجنبية**

- `(trip_id, company_id) → trips(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(user_id) → users(id)`؛ حذف: NO ACTION؛ تحديث: NO ACTION.
- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `location_points_company_id_trip_id_captured_at_index` على `(company_id, trip_id, captured_at)`؛ INDEX.
- `location_points_company_id_event_id_unique` على `(company_id, event_id)`؛ UNIQUE.

## migrations

تاريخ ملفات تطوير البنية التي طبقها Laravel.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| migration | varchar | لا | — | — | اسم ملف Migration المطبق. |
| batch | INTEGER | لا | — | — | دفعة تطبيق Migration. |

**المفاتيح الأجنبية**

لا توجد مفاتيح أجنبية معلنة في SQLite لهذا الجدول؛ أي تحقق منطقي في الخدمة يظل مهمًا.

**الفهارس والتفرد**

لا فهارس إضافية مدرجة بواسطة PRAGMA index_list؛ قد يخدم المفتاح الأساسي الفهرسة بذاته.

## operation_receipts

حفظ نتيجة action بمعرف UUID وبصمة محتوى لمنع تكرار العملية.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| user_id | INTEGER | لا | — | — | هوية المستخدم المرتبط؛ راجع الجدول لتحديد دوره في هذا السجل. |
| request_id | varchar | لا | — | — | UUID العملية الواحدة. |
| action | varchar | لا | — | — | اسم الإجراء. |
| payload_hash | varchar | لا | — | — | SHA-256 لمحتوى العملية بعد توحيد ترتيب مفاتيح الكائنات. |
| result | TEXT | نعم | — | — | JSON نتيجة الإجراء لإعادتها عند المحاولة المكررة. |
| created_at | datetime | نعم | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |
| updated_at | datetime | نعم | — | — | آخر وقت تحديث للسجل. |

**المفاتيح الأجنبية**

- `(user_id) → users(id)`؛ حذف: NO ACTION؛ تحديث: NO ACTION.
- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `operation_receipts_company_id_action_request_id_unique` على `(company_id, action, request_id)`؛ UNIQUE.

## order_items

أسطر الطلب؛ سعر الوحدة محفوظ وقت إنشاء السطر.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| order_id | INTEGER | لا | — | — | الطلب المرتبط. |
| product_id | INTEGER | لا | — | — | الصنف. |
| quantity | INTEGER | لا | — | — | كمية؛ في الحركة موجب زيادة وسالب خصم، وفي السطر/الرصيد معناها حسب الجدول. |
| unit_price_minor | INTEGER | لا | — | — | سعر وحدة محفوظ في سطر الطلب. |
| total_minor | INTEGER | لا | — | — | الإجمالي بالوحدة الصغيرة. |

**المفاتيح الأجنبية**

- `(product_id, company_id) → products(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(order_id, company_id) → orders(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

لا فهارس إضافية مدرجة بواسطة PRAGMA index_list؛ قد يخدم المفتاح الأساسي الفهرسة بذاته.

## orders

رأس الطلب: عميل ومندوب وعهدة ورحلة وشروط وإجمالي ومدفوع وحالة.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| code | varchar | لا | — | — | رمز المورد/المستند ضمن الشركة. |
| customer_id | INTEGER | لا | — | — | العميل المرتبط؛ قد يكون فارغًا في طلب تسجيل لم يعتمد بعد. |
| representative_id | INTEGER | لا | — | — | المندوب المسؤول في سياق السجل؛ لا يعيد تعيين كل التاريخ عند تغيير العميل. |
| trip_id | INTEGER | نعم | — | — | الرحلة المرتبطة. |
| warehouse_id | INTEGER | لا | — | — | موقع المخزون/العهدة. |
| status | varchar | لا | 'draft' | — | حالة المورد؛ القيم حسب الجدول وموضحة أسفل القاموس. |
| total_minor | INTEGER | لا | '0' | — | الإجمالي بالوحدة الصغيرة. |
| paid_minor | INTEGER | لا | '0' | — | المدفوع الحالي من الإيصالات؛ لا تعدله دون دفتر التحصيل. |
| created_at | datetime | نعم | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |
| updated_at | datetime | نعم | — | — | آخر وقت تحديث للسجل. |
| payment_terms | varchar | لا | 'credit' | — | cash/partial/credit. |
| due_date | date | نعم | — | — | تاريخ الاستحقاق؛ ليس تاريخ السداد الفعلي. |
| initial_paid_minor | INTEGER | لا | '0' | — | المبلغ المزمع استلامه عند تأكيد التسليم؛ لا يساوي paid قبل التنفيذ. |

**المفاتيح الأجنبية**

- `(warehouse_id, company_id) → warehouses(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(trip_id, company_id) → trips(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(customer_id, company_id) → customers(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(representative_id) → users(id)`؛ حذف: NO ACTION؛ تحديث: NO ACTION.
- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `orders_company_id_due_date_status_index` على `(company_id, due_date, status)`؛ INDEX.
- `orders_id_company_id_unique` على `(id, company_id)`؛ UNIQUE.
- `orders_company_id_code_unique` على `(company_id, code)`؛ UNIQUE.

## password_reset_tokens

بنية Laravel لاسترجاع كلمة المرور؛ لا توجد شاشة استرجاع مفعلة في المسارات الحالية.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| email | varchar | لا | — | نعم | بريد اختياري في بنية الهوية؛ ليس مفتاح الدخول الحالي. |
| token | varchar | لا | — | — | رمز مجزأ/تقني؛ ليس كلمة المرور ولا الرمز الواضح المعروض عند الإصدار. |
| created_at | datetime | نعم | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |

**المفاتيح الأجنبية**

لا توجد مفاتيح أجنبية معلنة في SQLite لهذا الجدول؛ أي تحقق منطقي في الخدمة يظل مهمًا.

**الفهارس والتفرد**

- `sqlite_autoindex_password_reset_tokens_1` على `(email)`؛ UNIQUE.

## personal_access_tokens

رموز Sanctum المجزأة وصلاحياتها وانتهاؤها؛ لا تحفظ الرمز الواضح هنا.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| tokenable_type | varchar | لا | — | — | نوع مالك رمز Sanctum. |
| tokenable_id | INTEGER | لا | — | — | معرف مالك الرمز. |
| name | TEXT | لا | — | — | اسم الجهاز عند إصدار الرمز. |
| token | varchar | لا | — | — | رمز مجزأ/تقني؛ ليس كلمة المرور ولا الرمز الواضح المعروض عند الإصدار. |
| abilities | TEXT | نعم | — | — | قدرات الرمز بصيغة JSON. |
| last_used_at | datetime | نعم | — | — | آخر استعمال للرمز. |
| expires_at | datetime | نعم | — | — | انتهاء الرمز. |
| created_at | datetime | نعم | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |
| updated_at | datetime | نعم | — | — | آخر وقت تحديث للسجل. |

**المفاتيح الأجنبية**

لا توجد مفاتيح أجنبية معلنة في SQLite لهذا الجدول؛ أي تحقق منطقي في الخدمة يظل مهمًا.

**الفهارس والتفرد**

- `personal_access_tokens_expires_at_index` على `(expires_at)`؛ INDEX.
- `personal_access_tokens_token_unique` على `(token)`؛ UNIQUE.
- `personal_access_tokens_tokenable_type_tokenable_id_index` على `(tokenable_type, tokenable_id)`؛ INDEX.

## products

الأصناف وأسعارها ووحداتها ونقطة إعادة الطلب.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| sku | varchar | لا | — | — | رمز الصنف الفريد بالشركة. |
| name | varchar | لا | — | — | الاسم الظاهر. |
| unit | varchar | لا | 'قطعة' | — | وحدة بيع الصنف. |
| price_minor | INTEGER | لا | — | — | السعر بالوحدة الصغيرة. |
| reorder_level | INTEGER | لا | '20' | — | عتبة التنبيه لنقص الرصيد. |
| is_active | tinyint(1) | لا | '1' | — | نشاط المورد أو العضوية؛ ليس حذفًا لتاريخه. |
| created_at | datetime | نعم | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |
| updated_at | datetime | نعم | — | — | آخر وقت تحديث للسجل. |

**المفاتيح الأجنبية**

- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `products_id_company_id_unique` على `(id, company_id)`؛ UNIQUE.
- `products_company_id_sku_unique` على `(company_id, sku)`؛ UNIQUE.

## representative_areas

مناطق مسموحة للمندوب عندما area_access=selected؛ فراغه ليس خطأ إن التغطية all.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| user_id | INTEGER | لا | — | — | هوية المستخدم المرتبط؛ راجع الجدول لتحديد دوره في هذا السجل. |
| area_id | INTEGER | لا | — | — | المنطقة المرتبطة. |

**المفاتيح الأجنبية**

- `(area_id, company_id) → areas(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(company_id, user_id) → company_user(company_id, user_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `representative_areas_company_id_user_id_area_id_unique` على `(company_id, user_id, area_id)`؛ UNIQUE.

## representative_vehicle_assignments

تاريخ تخصيص مركبة عمل للمندوب؛ ليس محضر استلام السائق.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| representative_id | INTEGER | لا | — | — | المندوب المسؤول في سياق السجل؛ لا يعيد تعيين كل التاريخ عند تغيير العميل. |
| vehicle_id | INTEGER | لا | — | — | المركبة الأصلية المرتبطة. |
| active_representative_id | INTEGER | نعم | — | — | معرف المندوب أثناء النشاط لمنع الازدواج؛ يفرغ عند الإغلاق/الإرجاع. |
| active_vehicle_id | INTEGER | نعم | — | — | نسخة معرف المركبة أثناء الإسناد/الرحلة النشطة؛ تفرد، ثم null عند الإغلاق. |
| assigned_by | INTEGER | لا | — | — | فاعل الإسناد. |
| returned_by | INTEGER | نعم | — | — | فاعل الإرجاع. |
| assigned_at | datetime | لا | — | — | بداية الإسناد. |
| returned_at | datetime | نعم | — | — | نهاية الإسناد؛ null يعني لم يرجع بعد. |
| assignment_note | TEXT | لا | — | — | سبب تخصيص المركبة للمندوب. |
| return_note | TEXT | نعم | — | — | سبب/ملاحظة الإرجاع. |

**المفاتيح الأجنبية**

- `(vehicle_id, company_id) → vehicles(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(company_id, representative_id) → company_user(company_id, user_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(returned_by) → users(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(assigned_by) → users(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `rep_vehicle_active_vehicle_unique` على `(company_id, active_vehicle_id)`؛ UNIQUE.
- `rep_vehicle_active_rep_unique` على `(company_id, active_representative_id)`؛ UNIQUE.

## sessions

جلسات دخول الويب؛ محتوى payload ليس بيانات تقارير أعمال.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | varchar | لا | — | نعم | معرف جلسة نصي؛ ليس users.id. |
| user_id | INTEGER | نعم | — | — | هوية المستخدم المرتبط؛ راجع الجدول لتحديد دوره في هذا السجل. |
| ip_address | varchar | نعم | — | — | IP محفوظ في جلسة Laravel، وليس عمود audit. |
| user_agent | TEXT | نعم | — | — | بيانات متصفح الجلسة. |
| payload | TEXT | لا | — | — | محتوى؛ JSON في طلب العميل، وتمثيل تقني في الجلسات والمهام؛ لا تعرض المحتويات الحساسة. |
| last_activity | INTEGER | لا | — | — | آخر نشاط بالثواني Unix. |

**المفاتيح الأجنبية**

لا توجد مفاتيح أجنبية معلنة في SQLite لهذا الجدول؛ أي تحقق منطقي في الخدمة يظل مهمًا.

**الفهارس والتفرد**

- `sessions_last_activity_index` على `(last_activity)`؛ INDEX.
- `sessions_user_id_index` على `(user_id)`؛ INDEX.
- `sqlite_autoindex_sessions_1` على `(id)`؛ UNIQUE.

## stock_balances

الرصيد الحالي حسب الشركة والموقع والصنف.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| warehouse_id | INTEGER | لا | — | — | موقع المخزون/العهدة. |
| product_id | INTEGER | لا | — | — | الصنف. |
| quantity | INTEGER | لا | '0' | — | الرصيد الحالي غير السالب؛ يجب مطابقته مع دفتر الحركات. |
| created_at | datetime | نعم | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |
| updated_at | datetime | نعم | — | — | آخر وقت تحديث للسجل. |

**المفاتيح الأجنبية**

- `(product_id, company_id) → products(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(warehouse_id, company_id) → warehouses(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `stock_balances_company_id_warehouse_id_product_id_unique` على `(company_id, warehouse_id, product_id)`؛ UNIQUE.

## stock_movements

دفتر مخزون بكميات موجبة وسالبة ومرجع وفاعل؛ راجع مطابقته مع الرصيد.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| warehouse_id | INTEGER | لا | — | — | موقع المخزون/العهدة. |
| product_id | INTEGER | لا | — | — | الصنف. |
| quantity | INTEGER | لا | — | — | كمية؛ في الحركة موجب زيادة وسالب خصم، وفي السطر/الرصيد معناها حسب الجدول. |
| reason | varchar | لا | — | — | سبب حركة المخزون أو الوصف التشغيلي. |
| reference | varchar | لا | — | — | مرجع المستند/الحركة للمراجعة. |
| actor_id | INTEGER | نعم | — | — | فاعل الحدث؛ في collections هو مستلم المبلغ. |
| created_at | datetime | لا | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |

**المفاتيح الأجنبية**

- `(product_id, company_id) → products(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(warehouse_id, company_id) → warehouses(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(actor_id) → users(id)`؛ حذف: NO ACTION؛ تحديث: NO ACTION.
- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `stock_movements_company_id_warehouse_id_created_at_index` على `(company_id, warehouse_id, created_at)`؛ INDEX.

## trips

رحلات مؤرخة بموارد وحالة وخطة ونسخة وأوقات تنفيذ/إلغاء.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| code | varchar | لا | — | — | رمز المورد/المستند ضمن الشركة. |
| representative_id | INTEGER | لا | — | — | المندوب المسؤول في سياق السجل؛ لا يعيد تعيين كل التاريخ عند تغيير العميل. |
| vehicle_id | INTEGER | لا | — | — | المركبة الأصلية المرتبطة. |
| warehouse_id | INTEGER | لا | — | — | موقع المخزون/العهدة. |
| scheduled_for | date | لا | — | — | تاريخ التنفيذ المخطط وفق يوم الشركة. |
| status | varchar | لا | 'planned' | — | حالة المورد؛ القيم حسب الجدول وموضحة أسفل القاموس. |
| started_at | datetime | نعم | — | — | وقت بدء الرحلة. |
| closed_at | datetime | نعم | — | — | وقت إغلاق الرحلة. |
| active_representative_id | INTEGER | نعم | — | — | معرف المندوب أثناء النشاط لمنع الازدواج؛ يفرغ عند الإغلاق/الإرجاع. |
| active_vehicle_id | INTEGER | نعم | — | — | نسخة معرف المركبة أثناء الإسناد/الرحلة النشطة؛ تفرد، ثم null عند الإغلاق. |
| created_at | datetime | نعم | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |
| updated_at | datetime | نعم | — | — | آخر وقت تحديث للسجل. |
| driver_id | INTEGER | نعم | — | — | السائق المرتبط. |
| plan_version | INTEGER | لا | '1' | — | نسخة خطة الرحلة. |
| planning_notes | TEXT | نعم | — | — | تعليمات التخطيط. |
| cancelled_at | datetime | نعم | — | — | وقت الإلغاء؛ صيغة التخزين بحسب الجدول. |
| cancelled_by | INTEGER | نعم | — | — | فاعل إلغاء الرحلة. |
| cancellation_reason | varchar | نعم | — | — | سبب إلغاء الخطة. |
| weekly_plan_id | INTEGER | نعم | — | — | القالب الأسبوعي الذي ولّد الرحلة إن وجد. |
| weekly_plan_version | INTEGER | نعم | — | — | نسخة القالب لحظة النشر. |
| route_snapshot | TEXT | نعم | — | — | JSON لأصل الانطلاق والمسافة وطريقة الترتيب؛ يبطل عند إعادة تخطيط يدوي/تغيير موقع مؤثر. |

**المفاتيح الأجنبية**

- `(weekly_plan_id, company_id) → weekly_plans(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(driver_id, company_id) → drivers(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(company_id, representative_id) → company_user(company_id, user_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(vehicle_id, company_id) → vehicles(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(warehouse_id, company_id) → warehouses(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(cancelled_by) → users(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `trips_company_id_weekly_plan_id_scheduled_for_unique` على `(company_id, weekly_plan_id, scheduled_for)`؛ UNIQUE.
- `trips_id_company_id_unique` على `(id, company_id)`؛ UNIQUE.
- `trips_company_id_scheduled_for_status_index` على `(company_id, scheduled_for, status)`؛ INDEX.
- `trips_company_id_code_unique` على `(company_id, code)`؛ UNIQUE.
- `trips_company_id_active_vehicle_id_unique` على `(company_id, active_vehicle_id)`؛ UNIQUE.
- `trips_company_id_active_representative_id_unique` على `(company_id, active_representative_id)`؛ UNIQUE.

## users

هويات الدخول العامة؛ الدور موجود في company_user وليس عمود role هنا.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| name | varchar | لا | — | — | الاسم الظاهر. |
| email | varchar | نعم | — | — | بريد اختياري في بنية الهوية؛ ليس مفتاح الدخول الحالي. |
| email_verified_at | datetime | نعم | — | — | تاريخ تحقق بريد اختياري من بنية Laravel. |
| password | varchar | لا | — | — | Hash كلمة المرور؛ لا تكتب كلمة مرور واضحة في هذا العمود. |
| remember_token | varchar | نعم | — | — | حقل Laravel لتذكر الدخول؛ ليس كلمة المرور. |
| created_at | datetime | نعم | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |
| updated_at | datetime | نعم | — | — | آخر وقت تحديث للسجل. |
| username | varchar | نعم | — | — | اسم الدخول الفريد؛ يوحد إلى حروف صغيرة في مسارات الدخول والإدارة. |

**المفاتيح الأجنبية**

لا توجد مفاتيح أجنبية معلنة في SQLite لهذا الجدول؛ أي تحقق منطقي في الخدمة يظل مهمًا.

**الفهارس والتفرد**

- `users_username_unique` على `(username)`؛ UNIQUE.
- `users_email_unique` على `(email)`؛ UNIQUE.

## vehicle_assignments

سجل تسليم المركبة لسائق وإرجاعه، مع مفاتيح تمنع الازدواج النشط.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| vehicle_id | INTEGER | لا | — | — | المركبة الأصلية المرتبطة. |
| driver_id | INTEGER | لا | — | — | السائق المرتبط. |
| active_vehicle_id | INTEGER | نعم | — | — | نسخة معرف المركبة أثناء الإسناد/الرحلة النشطة؛ تفرد، ثم null عند الإغلاق. |
| active_driver_id | INTEGER | نعم | — | — | معرف السائق أثناء الاستلام النشط؛ يفرغ عند الإرجاع. |
| assigned_by | INTEGER | لا | — | — | فاعل الإسناد. |
| returned_by | INTEGER | نعم | — | — | فاعل الإرجاع. |
| assigned_at | datetime | لا | — | — | بداية الإسناد. |
| returned_at | datetime | نعم | — | — | نهاية الإسناد؛ null يعني لم يرجع بعد. |
| handover_note | varchar | لا | — | — | ملاحظة تسليم السائق. |
| return_note | varchar | نعم | — | — | سبب/ملاحظة الإرجاع. |

**المفاتيح الأجنبية**

- `(driver_id, company_id) → drivers(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(vehicle_id, company_id) → vehicles(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(returned_by) → users(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(assigned_by) → users(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `vehicle_assignments_company_id_active_driver_id_unique` على `(company_id, active_driver_id)`؛ UNIQUE.
- `vehicle_assignments_company_id_active_vehicle_id_unique` على `(company_id, active_vehicle_id)`؛ UNIQUE.

## vehicles

المركبات وأنواعها ولوحاتها وملكيتها وحمولتها ووثائقها وجاهزيتها.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| plate | varchar | لا | — | — | لوحة المركبة. |
| name | varchar | لا | — | — | الاسم الظاهر. |
| status | varchar | لا | 'available' | — | حالة المورد؛ القيم حسب الجدول وموضحة أسفل القاموس. |
| created_at | datetime | نعم | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |
| updated_at | datetime | نعم | — | — | آخر وقت تحديث للسجل. |
| capacity_units | INTEGER | لا | '500' | — | سعة عدد وحدات؛ مستخدمة في فحص تحويل المخزون للسيارة. |
| vehicle_type | varchar | لا | 'unspecified' | — | van/pickup/light_truck/truck/tractor_trailer/car/motorcycle/other. |
| make | varchar | نعم | — | — | المصنّع. |
| model | varchar | نعم | — | — | طراز المركبة. |
| model_year | INTEGER | نعم | — | — | سنة الموديل. |
| vin | varchar | نعم | — | — | رقم الهيكل. |
| ownership | varchar | لا | 'owned' | — | owned/leased/rented. |
| payload_kg | INTEGER | نعم | — | — | حمولة بالكيلو؛ محفوظة كمعلومة وليست محرك أوزان كاملًا. |
| volume_m3 | numeric | نعم | — | — | الحجم بالمتر المكعب. |
| odometer_km | INTEGER | نعم | — | — | عداد كيلومترات؛ التعديل المعتاد لا يخفض القراءة. |
| registration_expires_on | date | نعم | — | — | انتهاء الاستمارة. |
| insurance_expires_on | date | نعم | — | — | انتهاء التأمين. |
| notes | varchar | نعم | — | — | ملاحظات تشغيلية. |

**المفاتيح الأجنبية**

- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `vehicles_id_company_id_unique` على `(id, company_id)`؛ UNIQUE.
- `vehicles_company_id_plate_unique` على `(company_id, plate)`؛ UNIQUE.

## visits

محطات العملاء في الرحلات وحالة التنفيذ وإثبات القرب والنتائج.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| customer_id | INTEGER | لا | — | — | العميل المرتبط؛ قد يكون فارغًا في طلب تسجيل لم يعتمد بعد. |
| representative_id | INTEGER | لا | — | — | المندوب المسؤول في سياق السجل؛ لا يعيد تعيين كل التاريخ عند تغيير العميل. |
| scheduled_for | date | لا | — | — | تاريخ التنفيذ المخطط وفق يوم الشركة. |
| status | varchar | لا | 'planned' | — | حالة المورد؛ القيم حسب الجدول وموضحة أسفل القاموس. |
| checked_in_at | datetime | نعم | — | — | وقت قبول بداية الزيارة. |
| finished_at | datetime | نعم | — | — | وقت إنهاء الزيارة. |
| checkin_latitude | numeric | نعم | — | — | إحداثي إثبات الوصول المحفوظ وقت التنفيذ. |
| checkin_longitude | numeric | نعم | — | — | إحداثي إثبات الوصول المحفوظ وقت التنفيذ. |
| accuracy_m | numeric | نعم | — | — | دقة الموقع بالمتر. |
| distance_m | numeric | نعم | — | — | مسافة نقطة الإثبات من موقع العميل بالمتر وقت القبول. |
| location_captured_at | datetime | نعم | — | — | وقت التقاط دليل الوصول على الجهاز. |
| location_verified | tinyint(1) | لا | '0' | — | قبول دليل القرب وفق قواعد النظام. |
| outcome_note | varchar | نعم | — | — | تفاصيل نتيجة الزيارة. |
| created_at | datetime | نعم | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |
| updated_at | datetime | نعم | — | — | آخر وقت تحديث للسجل. |
| trip_id | INTEGER | نعم | — | — | الرحلة المرتبطة. |
| sequence | INTEGER | لا | '1' | — | ترتيب المحطة. |
| window_start | time | نعم | — | — | بداية نافذة خدمة محفوظة؛ ليست وحدها محرك جدولة مواعيد كاملًا. |
| window_end | time | نعم | — | — | نهاية نافذة خدمة محفوظة. |
| service_minutes | INTEGER | لا | '10' | — | وقت خدمة متوقع محفوظ. |
| reason_code | varchar | نعم | — | — | سبب تعذر/عدم بيع من قائمة أسباب الزيارة. |

**المفاتيح الأجنبية**

- `(trip_id, company_id) → trips(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(customer_id, company_id) → customers(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(company_id, representative_id) → company_user(company_id, user_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `visits_id_company_id_unique` على `(id, company_id)`؛ UNIQUE.
- `visits_company_id_scheduled_for_status_index` على `(company_id, scheduled_for, status)`؛ INDEX.
- `visits_company_id_representative_id_scheduled_for_index` على `(company_id, representative_id, scheduled_for)`؛ INDEX.

## warehouses

مستودع عادي أو عهدة مركبة؛ vehicle_id للعهدة المرتبطة بمركبة.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| name | varchar | لا | — | — | الاسم الظاهر. |
| type | varchar | لا | 'warehouse' | — | نوع موقع المخزون: warehouse أو vehicle. |
| vehicle_id | INTEGER | نعم | — | — | المركبة الأصلية المرتبطة. |
| created_at | datetime | نعم | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |
| updated_at | datetime | نعم | — | — | آخر وقت تحديث للسجل. |

**المفاتيح الأجنبية**

- `(vehicle_id, company_id) → vehicles(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `warehouses_id_company_id_unique` على `(id, company_id)`؛ UNIQUE.

## weekly_plans

قالب يوم في الأسبوع لمندوب، وقائمة عملائه ومركبته ونقطة الانطلاق؛ ليس رحلة مؤرخة.

| العمود | نوع SQLite | يسمح null؟ | default | مفتاح أساسي | التفسير |
|---|---|---|---|---|---|
| id | INTEGER | لا | — | نعم | معرف السجل؛ المفتاح الأساسي، ونوعه قد يختلف في جداول البنية. |
| company_id | INTEGER | لا | — | — | الشركة المالكة؛ لا تغيره لنقل سجل بين الشركات. |
| representative_id | INTEGER | لا | — | — | المندوب المسؤول في سياق السجل؛ لا يعيد تعيين كل التاريخ عند تغيير العميل. |
| vehicle_id | INTEGER | لا | — | — | المركبة الأصلية المرتبطة. |
| weekday | INTEGER | لا | — | — | 0 الأحد إلى 6 السبت؛ ترتيب العرض لا يغير الترميز. |
| customer_ids | TEXT | لا | — | — | JSON لقائمة IDs عملاء القالب؛ تتحقق الخدمة من كل عميل عند الحفظ والنشر. |
| origin_latitude | numeric | لا | — | — | خط عرض أصل انطلاق الترتيب. |
| origin_longitude | numeric | لا | — | — | خط طول أصل الانطلاق. |
| origin_label | varchar | لا | — | — | اسم أصل الانطلاق. |
| notes | TEXT | نعم | — | — | ملاحظات تشغيلية. |
| is_active | tinyint(1) | لا | '1' | — | نشاط المورد أو العضوية؛ ليس حذفًا لتاريخه. |
| version | INTEGER | لا | '1' | — | نسخة القالب الأسبوعي. |
| created_at | datetime | نعم | — | — | وقت إنشاء السجل؛ جداول Laravel التقنية قد تستخدم Unix timestamp بدل تاريخ نصي. |
| updated_at | datetime | نعم | — | — | آخر وقت تحديث للسجل. |

**المفاتيح الأجنبية**

- `(vehicle_id, company_id) → vehicles(id, company_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(company_id, representative_id) → company_user(company_id, user_id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.
- `(company_id) → companies(id)`؛ حذف: RESTRICT؛ تحديث: NO ACTION.

**الفهارس والتفرد**

- `weekly_plans_id_company_id_unique` على `(id, company_id)`؛ UNIQUE.
- `weekly_plans_company_id_representative_id_weekday_unique` على `(company_id, representative_id, weekday)`؛ UNIQUE.

## تفسير حالات العمل

- trips: planned ثم active ثم closed، أو cancelled قبل التنفيذ وفق الضوابط.
- visits: planned →in_progress →completed/failed، أو skipped من planned، و cancelled للمحطات الملغاة من الخطة.
- orders: draft لا يمثل دينًا مؤكدًا؛ fulfilled بعد التسليم.
- cash_handovers: pending ثم accepted/rejected/cancelled؛ لا يعاد اعتماد سجل معالج.
- customer_changes: pending ثم approved/rejected.
- vehicles: available/maintenance/inactive؛ drivers: active/inactive.
- جداول representative_vehicle_assignments و vehicle_assignments تحفظ التاريخ؛ returned_at=null يحدد الإسناد الجاري مع مفاتيح active الفريدة.

## المرجع الآلي

[schema.json](schema.json) يحتوي وصف البنية و SQL إنشاء الجداول والفهارس والأعداد فقط. لا يحتوي سجلات العملاء أو كلمات المرور أو رموز الأجهزة. هو لقطة مرجعية؛ لا تستخدمه بدل Migrations لنقل إصدار جديد.
