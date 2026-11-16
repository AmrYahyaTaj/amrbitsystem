# 08 — فهرس المصدر والدوال

جذر كل مسار نسبي هنا هو `C:\xampp\htdocs\fieldflow-next`. الأرقام حسب المصدر المفحوص في 25 أكتوبر 2026. الكود لم يعدل أثناء إعداد الدليل؛ أي تعديل لاحق قد يغير رقم السطر.

يشمل الفهرس دوال PHP المسماة ودوال JS المعلنة بصيغة function. دوال الوحدات المختصرة والمعالجات مجهولة الاسم مشروحة بحسب دورها في الفصل 04، والـ closures في PHP موضحة ضمن الدالة الحاوية في الفصل 03. هذا فهرس وصول؛ شرح معنى الدوال في الفصلين المذكورين.

## app

### app/Enums/CompanyRole.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Enums/CompanyRole.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 13 | [`label() : string`](C:/xampp/htdocs/fieldflow-next/app/Enums/CompanyRole.php:13) | public |
| 24 | [`managesOperations() : bool`](C:/xampp/htdocs/fieldflow-next/app/Enums/CompanyRole.php:24) | public |
| 29 | [`seesOperations() : bool`](C:/xampp/htdocs/fieldflow-next/app/Enums/CompanyRole.php:29) | public |

### app/Enums/VisitStatus.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Enums/VisitStatus.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 14 | [`label() : string`](C:/xampp/htdocs/fieldflow-next/app/Enums/VisitStatus.php:14) | public |

### app/Http/Controllers/AuthController.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/AuthController.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 11 | [`create()`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/AuthController.php:11) | public |
| 16 | [`store(Request $request)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/AuthController.php:16) | public |
| 28 | [`destroy(Request $request)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/AuthController.php:28) | public |

### app/Http/Controllers/CatalogController.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CatalogController.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 18 | [`areas()`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CatalogController.php:18) | public |
| 25 | [`storeArea(Request $request, CompanyContext $context, AuditRecorder $audit)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CatalogController.php:25) | public |
| 39 | [`customers(Request $request)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CatalogController.php:39) | public |
| 53 | [`storeCustomer(Request $request, CompanyContext $context, AuditRecorder $audit)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CatalogController.php:53) | public |
| 72 | [`vehicles()`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CatalogController.php:72) | public |
| 79 | [`storeVehicle(Request $request, CompanyContext $context, AuditRecorder $audit)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CatalogController.php:79) | public |
| 94 | [`team(CompanyContext $context)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CatalogController.php:94) | public |
| 101 | [`audit()`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CatalogController.php:101) | public |

### app/Http/Controllers/CompanyController.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CompanyController.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 9 | [`switch(Request $request)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CompanyController.php:9) | public |

### app/Http/Controllers/Controller.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/Controller.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### app/Http/Controllers/CustomerJourneyController.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CustomerJourneyController.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 28 | [`customer(int $id) : Customer`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CustomerJourneyController.php:28) | private |
| 37 | [`page(Request $request, int $id)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CustomerJourneyController.php:37) | public |
| 44 | [`schedule(Request $request)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CustomerJourneyController.php:44) | public |
| 51 | [`day(Request $request)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CustomerJourneyController.php:51) | public |
| 65 | [`show(Request $request, int $id)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CustomerJourneyController.php:65) | public |
| 110 | [`ledger($fulfilled, $ids)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CustomerJourneyController.php:110) | private |
| 118 | [`execute(Request $request, string $action, OperationOnce $once, WeeklyRouting $routing)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CustomerJourneyController.php:118) | public |
| 147 | [`update(Request $request) : Customer`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/CustomerJourneyController.php:147) | private |

### app/Http/Controllers/DashboardController.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/DashboardController.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 15 | [`__invoke(Request $request, CompanyContext $context)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/DashboardController.php:15) | public |

### app/Http/Controllers/DeviceAuthController.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/DeviceAuthController.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 13 | [`store(Request $request)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/DeviceAuthController.php:13) | public |
| 31 | [`destroy(Request $request)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/DeviceAuthController.php:31) | public |

### app/Http/Controllers/DocumentController.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/DocumentController.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 12 | [`order(int $id)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/DocumentController.php:12) | public |

### app/Http/Controllers/OperationsController.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/OperationsController.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 50 | [`role() : CompanyRole`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/OperationsController.php:50) | private |
| 55 | [`manager() : void`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/OperationsController.php:55) | private |
| 60 | [`finance() : void`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/OperationsController.php:60) | private |
| 65 | [`own($model) : void`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/OperationsController.php:65) | private |
| 70 | [`companyRule(string $table) : Exists`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/OperationsController.php:70) | private |
| 75 | [`representative(int $id) : void`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/OperationsController.php:75) | private |
| 80 | [`page(Request $request, string $section = 'dashboard')`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/OperationsController.php:80) | public |
| 93 | [`data(Request $request)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/OperationsController.php:93) | public |
| 165 | [`execute(Request $request, string $action, OperationOnce $once, StockLedger $stock, TradingWorkflow $trading, VisitWorkflow $visits, AuditRecorder $audit)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/OperationsController.php:165) | public |
| 453 | [`reportFilters(Request $request) : array`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/OperationsController.php:453) | private |
| 464 | [`analytics(Request $request, OperationalAnalytics $analytics)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/OperationsController.php:464) | public |
| 471 | [`report(Request $request)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/OperationsController.php:471) | public |

### app/Http/Controllers/TripFileController.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/TripFileController.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 16 | [`show(int $id, TripPlanning $planning)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/TripFileController.php:16) | public |

### app/Http/Controllers/VisitController.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/VisitController.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 19 | [`index(Request $request, CompanyContext $context)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/VisitController.php:19) | public |
| 38 | [`store(Request $request, CompanyContext $context, AuditRecorder $audit)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/VisitController.php:38) | public |
| 53 | [`show(Visit $visit)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/VisitController.php:53) | public |
| 60 | [`checkIn(Request $request, Visit $visit, VisitWorkflow $workflow)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/VisitController.php:60) | public |
| 72 | [`finish(Request $request, Visit $visit, VisitWorkflow $workflow)`](C:/xampp/htdocs/fieldflow-next/app/Http/Controllers/VisitController.php:72) | public |

### app/Http/Middleware/SecurityHeaders.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Http/Middleware/SecurityHeaders.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 11 | [`handle(Request $request, Closure $next) : Response`](C:/xampp/htdocs/fieldflow-next/app/Http/Middleware/SecurityHeaders.php:11) | public |

### app/Http/Middleware/SetApiCompanyContext.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Http/Middleware/SetApiCompanyContext.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 13 | [`handle(Request $request, Closure $next) : Response`](C:/xampp/htdocs/fieldflow-next/app/Http/Middleware/SetApiCompanyContext.php:13) | public |

### app/Http/Middleware/SetCompanyContext.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Http/Middleware/SetCompanyContext.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 13 | [`handle(Request $request, Closure $next) : Response`](C:/xampp/htdocs/fieldflow-next/app/Http/Middleware/SetCompanyContext.php:13) | public |

### app/Models/Area.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/Area.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### app/Models/AuditLog.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/AuditLog.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 11 | [`casts() : array`](C:/xampp/htdocs/fieldflow-next/app/Models/AuditLog.php:11) | protected |

### app/Models/CashHandover.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/CashHandover.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 11 | [`casts() : array`](C:/xampp/htdocs/fieldflow-next/app/Models/CashHandover.php:11) | protected |
| 16 | [`collector() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/CashHandover.php:16) | public |
| 21 | [`reviewer() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/CashHandover.php:21) | public |

### app/Models/Collection.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/Collection.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 13 | [`casts() : array`](C:/xampp/htdocs/fieldflow-next/app/Models/Collection.php:13) | protected |
| 18 | [`order() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/Collection.php:18) | public |
| 23 | [`actor() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/Collection.php:23) | public |

### app/Models/Company.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/Company.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 14 | [`casts() : array`](C:/xampp/htdocs/fieldflow-next/app/Models/Company.php:14) | protected |
| 19 | [`users() : BelongsToMany`](C:/xampp/htdocs/fieldflow-next/app/Models/Company.php:19) | public |

### app/Models/Customer.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/Customer.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 11 | [`casts() : array`](C:/xampp/htdocs/fieldflow-next/app/Models/Customer.php:11) | protected |
| 16 | [`area() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/Customer.php:16) | public |
| 21 | [`representative() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/Customer.php:21) | public |

### app/Models/CustomerChange.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/CustomerChange.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 9 | [`casts() : array`](C:/xampp/htdocs/fieldflow-next/app/Models/CustomerChange.php:9) | protected |
| 14 | [`customer()`](C:/xampp/htdocs/fieldflow-next/app/Models/CustomerChange.php:14) | public |
| 19 | [`submitter()`](C:/xampp/htdocs/fieldflow-next/app/Models/CustomerChange.php:19) | public |
| 24 | [`reviewer()`](C:/xampp/htdocs/fieldflow-next/app/Models/CustomerChange.php:24) | public |

### app/Models/DebtFollowup.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/DebtFollowup.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 13 | [`casts() : array`](C:/xampp/htdocs/fieldflow-next/app/Models/DebtFollowup.php:13) | protected |
| 18 | [`order() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/DebtFollowup.php:18) | public |
| 23 | [`actor() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/DebtFollowup.php:23) | public |

### app/Models/Driver.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/Driver.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 9 | [`casts() : array`](C:/xampp/htdocs/fieldflow-next/app/Models/Driver.php:9) | protected |

### app/Models/LocationPoint.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/LocationPoint.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 11 | [`casts() : array`](C:/xampp/htdocs/fieldflow-next/app/Models/LocationPoint.php:11) | protected |

### app/Models/Order.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/Order.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 12 | [`casts() : array`](C:/xampp/htdocs/fieldflow-next/app/Models/Order.php:12) | protected |
| 17 | [`customer() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/Order.php:17) | public |
| 22 | [`representative() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/Order.php:22) | public |
| 27 | [`warehouse() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/Order.php:27) | public |
| 32 | [`items() : HasMany`](C:/xampp/htdocs/fieldflow-next/app/Models/Order.php:32) | public |

### app/Models/OrderItem.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/OrderItem.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 13 | [`product() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/OrderItem.php:13) | public |

### app/Models/Product.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/Product.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 9 | [`casts() : array`](C:/xampp/htdocs/fieldflow-next/app/Models/Product.php:9) | protected |

### app/Models/RepresentativeVehicleAssignment.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/RepresentativeVehicleAssignment.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 11 | [`casts() : array`](C:/xampp/htdocs/fieldflow-next/app/Models/RepresentativeVehicleAssignment.php:11) | protected |
| 16 | [`vehicle()`](C:/xampp/htdocs/fieldflow-next/app/Models/RepresentativeVehicleAssignment.php:16) | public |
| 21 | [`representative()`](C:/xampp/htdocs/fieldflow-next/app/Models/RepresentativeVehicleAssignment.php:21) | public |
| 26 | [`assigner()`](C:/xampp/htdocs/fieldflow-next/app/Models/RepresentativeVehicleAssignment.php:26) | public |

### app/Models/StockBalance.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/StockBalance.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 11 | [`casts() : array`](C:/xampp/htdocs/fieldflow-next/app/Models/StockBalance.php:11) | protected |
| 16 | [`warehouse() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/StockBalance.php:16) | public |
| 21 | [`product() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/StockBalance.php:21) | public |

### app/Models/StockMovement.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/StockMovement.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 13 | [`casts() : array`](C:/xampp/htdocs/fieldflow-next/app/Models/StockMovement.php:13) | protected |
| 18 | [`warehouse() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/StockMovement.php:18) | public |
| 23 | [`product() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/StockMovement.php:23) | public |

### app/Models/TenantModel.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/TenantModel.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 11 | [`booted() : void`](C:/xampp/htdocs/fieldflow-next/app/Models/TenantModel.php:11) | protected |

### app/Models/Trip.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/Trip.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 12 | [`casts() : array`](C:/xampp/htdocs/fieldflow-next/app/Models/Trip.php:12) | protected |
| 17 | [`vehicle() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/Trip.php:17) | public |
| 22 | [`representative() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/Trip.php:22) | public |
| 27 | [`warehouse() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/Trip.php:27) | public |
| 32 | [`visits() : HasMany`](C:/xampp/htdocs/fieldflow-next/app/Models/Trip.php:32) | public |
| 37 | [`driver() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/Trip.php:37) | public |

### app/Models/User.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/User.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 15 | [`companies() : BelongsToMany`](C:/xampp/htdocs/fieldflow-next/app/Models/User.php:15) | public |
| 50 | [`casts() : array`](C:/xampp/htdocs/fieldflow-next/app/Models/User.php:50) | protected |

### app/Models/Vehicle.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/Vehicle.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### app/Models/VehicleAssignment.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/VehicleAssignment.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 11 | [`driver()`](C:/xampp/htdocs/fieldflow-next/app/Models/VehicleAssignment.php:11) | public |
| 16 | [`casts() : array`](C:/xampp/htdocs/fieldflow-next/app/Models/VehicleAssignment.php:16) | protected |

### app/Models/Visit.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/Visit.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 14 | [`casts() : array`](C:/xampp/htdocs/fieldflow-next/app/Models/Visit.php:14) | protected |
| 26 | [`customer() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/Visit.php:26) | public |
| 31 | [`representative() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/Visit.php:31) | public |

### app/Models/Warehouse.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/Warehouse.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 11 | [`vehicle() : BelongsTo`](C:/xampp/htdocs/fieldflow-next/app/Models/Warehouse.php:11) | public |

### app/Models/WeeklyPlan.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Models/WeeklyPlan.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 9 | [`casts() : array`](C:/xampp/htdocs/fieldflow-next/app/Models/WeeklyPlan.php:9) | protected |
| 14 | [`representative()`](C:/xampp/htdocs/fieldflow-next/app/Models/WeeklyPlan.php:14) | public |
| 19 | [`vehicle()`](C:/xampp/htdocs/fieldflow-next/app/Models/WeeklyPlan.php:19) | public |

### app/Policies/VisitPolicy.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Policies/VisitPolicy.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 12 | [`view(User $user, Visit $visit) : bool`](C:/xampp/htdocs/fieldflow-next/app/Policies/VisitPolicy.php:12) | public |
| 21 | [`execute(User $user, Visit $visit) : bool`](C:/xampp/htdocs/fieldflow-next/app/Policies/VisitPolicy.php:21) | public |

### app/Providers/AppServiceProvider.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Providers/AppServiceProvider.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 17 | [`register() : void`](C:/xampp/htdocs/fieldflow-next/app/Providers/AppServiceProvider.php:17) | public |
| 25 | [`boot() : void`](C:/xampp/htdocs/fieldflow-next/app/Providers/AppServiceProvider.php:25) | public |

### app/Services/AuditRecorder.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Services/AuditRecorder.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 10 | [`record(string $action, Model $subject, array $metadata = []) : void`](C:/xampp/htdocs/fieldflow-next/app/Services/AuditRecorder.php:10) | public |

### app/Services/CashCustody.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Services/CashCustody.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 14 | [`__construct(private AuditRecorder $audit)`](C:/xampp/htdocs/fieldflow-next/app/Services/CashCustody.php:14) | public |
| 16 | [`lockCollector(int $id) : void`](C:/xampp/htdocs/fieldflow-next/app/Services/CashCustody.php:16) | private |
| 22 | [`balances(?int $onlyUser = null) : array`](C:/xampp/htdocs/fieldflow-next/app/Services/CashCustody.php:22) | public |
| 38 | [`request(int $collectorId, int $amount, string $reference, ?string $note) : CashHandover`](C:/xampp/htdocs/fieldflow-next/app/Services/CashCustody.php:38) | public |
| 52 | [`review(CashHandover $handover, bool $accept, string $note) : void`](C:/xampp/htdocs/fieldflow-next/app/Services/CashCustody.php:52) | public |
| 66 | [`cancel(CashHandover $handover, string $note) : void`](C:/xampp/htdocs/fieldflow-next/app/Services/CashCustody.php:66) | public |

### app/Services/CustomerMaintenance.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Services/CustomerMaintenance.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 17 | [`own(Customer $customer) : void`](C:/xampp/htdocs/fieldflow-next/app/Services/CustomerMaintenance.php:17) | private |
| 23 | [`contact(Request $request) : Customer`](C:/xampp/htdocs/fieldflow-next/app/Services/CustomerMaintenance.php:23) | public |
| 38 | [`location(Request $request) : Customer\|CustomerChange`](C:/xampp/htdocs/fieldflow-next/app/Services/CustomerMaintenance.php:38) | public |
| 55 | [`applyLocation(Customer $customer, array $d) : Customer`](C:/xampp/htdocs/fieldflow-next/app/Services/CustomerMaintenance.php:55) | private |
| 75 | [`registration(Request $request) : CustomerChange`](C:/xampp/htdocs/fieldflow-next/app/Services/CustomerMaintenance.php:75) | public |
| 94 | [`customerData(Request $request) : array`](C:/xampp/htdocs/fieldflow-next/app/Services/CustomerMaintenance.php:94) | private |
| 101 | [`review(Request $request) : CustomerChange`](C:/xampp/htdocs/fieldflow-next/app/Services/CustomerMaintenance.php:101) | public |

### app/Services/FleetRegistry.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Services/FleetRegistry.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 16 | [`vehicle(Request $request, bool $creating) : Vehicle`](C:/xampp/htdocs/fieldflow-next/app/Services/FleetRegistry.php:16) | public |
| 50 | [`driver(Request $request, bool $creating) : Driver`](C:/xampp/htdocs/fieldflow-next/app/Services/FleetRegistry.php:50) | public |
| 68 | [`assign(Request $request) : VehicleAssignment`](C:/xampp/htdocs/fieldflow-next/app/Services/FleetRegistry.php:68) | public |
| 84 | [`release(Request $request) : VehicleAssignment`](C:/xampp/htdocs/fieldflow-next/app/Services/FleetRegistry.php:84) | public |

### app/Services/MemberAdministration.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Services/MemberAdministration.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 14 | [`save(Request $request, bool $creating) : User`](C:/xampp/htdocs/fieldflow-next/app/Services/MemberAdministration.php:14) | public |
| 74 | [`status(Request $request) : User`](C:/xampp/htdocs/fieldflow-next/app/Services/MemberAdministration.php:74) | public |

### app/Services/OperationalAnalytics.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Services/OperationalAnalytics.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 15 | [`orders(array $filters)`](C:/xampp/htdocs/fieldflow-next/app/Services/OperationalAnalytics.php:15) | public |
| 20 | [`visits(array $filters)`](C:/xampp/htdocs/fieldflow-next/app/Services/OperationalAnalytics.php:20) | public |
| 25 | [`build(array $filters) : array`](C:/xampp/htdocs/fieldflow-next/app/Services/OperationalAnalytics.php:25) | public |

### app/Services/OperationOnce.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Services/OperationOnce.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 10 | [`run(string $action, string $requestId, array $payload, callable $work) : array`](C:/xampp/htdocs/fieldflow-next/app/Services/OperationOnce.php:10) | public |

### app/Services/RepresentativeAccess.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Services/RepresentativeAccess.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 11 | [`membership(int $userId)`](C:/xampp/htdocs/fieldflow-next/app/Services/RepresentativeAccess.php:11) | public |
| 16 | [`assertCapability(int $userId, string $capability) : void`](C:/xampp/htdocs/fieldflow-next/app/Services/RepresentativeAccess.php:16) | public |
| 23 | [`assertCustomer(int $userId, Customer $customer) : void`](C:/xampp/htdocs/fieldflow-next/app/Services/RepresentativeAccess.php:23) | public |

### app/Services/RepresentativeRegistry.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Services/RepresentativeRegistry.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 17 | [`details(Request $request) : User`](C:/xampp/htdocs/fieldflow-next/app/Services/RepresentativeRegistry.php:17) | public |
| 34 | [`vehicle(Request $request) : RepresentativeVehicleAssignment`](C:/xampp/htdocs/fieldflow-next/app/Services/RepresentativeRegistry.php:34) | public |

### app/Services/StockLedger.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Services/StockLedger.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 14 | [`move(Warehouse $warehouse, Product $product, int $quantity, string $reason, string $reference) : void`](C:/xampp/htdocs/fieldflow-next/app/Services/StockLedger.php:14) | public |

### app/Services/TradingWorkflow.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Services/TradingWorkflow.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 16 | [`__construct(private StockLedger $stock, private AuditRecorder $audit)`](C:/xampp/htdocs/fieldflow-next/app/Services/TradingWorkflow.php:16) | public |
| 18 | [`create(array $data) : Order`](C:/xampp/htdocs/fieldflow-next/app/Services/TradingWorkflow.php:18) | public |
| 41 | [`fulfill(Order $order) : void`](C:/xampp/htdocs/fieldflow-next/app/Services/TradingWorkflow.php:41) | public |
| 64 | [`collect(Order $order, int $amount, string $method) : Collection`](C:/xampp/htdocs/fieldflow-next/app/Services/TradingWorkflow.php:64) | public |

### app/Services/TripPlanning.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Services/TripPlanning.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 22 | [`blockers(Trip $trip) : array`](C:/xampp/htdocs/fieldflow-next/app/Services/TripPlanning.php:22) | public |
| 41 | [`snapshot(Trip $trip) : array`](C:/xampp/htdocs/fieldflow-next/app/Services/TripPlanning.php:41) | public |
| 49 | [`editable(Request $request) : Trip`](C:/xampp/htdocs/fieldflow-next/app/Services/TripPlanning.php:49) | private |
| 60 | [`save(Request $request, bool $creating) : Trip`](C:/xampp/htdocs/fieldflow-next/app/Services/TripPlanning.php:60) | public |
| 112 | [`cancel(Request $request) : Trip`](C:/xampp/htdocs/fieldflow-next/app/Services/TripPlanning.php:112) | public |

### app/Services/VisitWorkflow.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Services/VisitWorkflow.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 14 | [`__construct(private AuditRecorder $audit)`](C:/xampp/htdocs/fieldflow-next/app/Services/VisitWorkflow.php:14) | public |
| 16 | [`checkIn(Visit $visit, array $location) : void`](C:/xampp/htdocs/fieldflow-next/app/Services/VisitWorkflow.php:16) | public |
| 51 | [`finish(Visit $visit, VisitStatus $status, ?string $note, ?string $reasonCode = null) : void`](C:/xampp/htdocs/fieldflow-next/app/Services/VisitWorkflow.php:51) | public |
| 68 | [`expect(Visit $visit, VisitStatus $expected) : void`](C:/xampp/htdocs/fieldflow-next/app/Services/VisitWorkflow.php:68) | private |
| 75 | [`distance(float $lat1, float $lon1, float $lat2, float $lon2) : float`](C:/xampp/htdocs/fieldflow-next/app/Services/VisitWorkflow.php:75) | public |

### app/Services/WeeklyRouting.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Services/WeeklyRouting.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 17 | [`save(Request $request) : WeeklyPlan`](C:/xampp/htdocs/fieldflow-next/app/Services/WeeklyRouting.php:17) | public |
| 52 | [`publish(Request $request) : Trip`](C:/xampp/htdocs/fieldflow-next/app/Services/WeeklyRouting.php:52) | public |
| 85 | [`nearest(array $customers, float $latitude, float $longitude) : array`](C:/xampp/htdocs/fieldflow-next/app/Services/WeeklyRouting.php:85) | public |

### app/Support/CompanyContext.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/app/Support/CompanyContext.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 14 | [`set(Company $company, CompanyRole $role) : void`](C:/xampp/htdocs/fieldflow-next/app/Support/CompanyContext.php:14) | public |
| 20 | [`id() : int`](C:/xampp/htdocs/fieldflow-next/app/Support/CompanyContext.php:20) | public |
| 25 | [`clear() : void`](C:/xampp/htdocs/fieldflow-next/app/Support/CompanyContext.php:25) | public |

## bootstrap

### bootstrap/app.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/bootstrap/app.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### bootstrap/providers.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/bootstrap/providers.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

## routes

### routes/api.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/routes/api.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### routes/console.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/routes/console.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### routes/web.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/routes/web.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

## resources

### resources/views/app.blade.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/resources/views/app.blade.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### resources/views/auth/login.blade.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/resources/views/auth/login.blade.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### resources/views/components/icon.blade.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/resources/views/components/icon.blade.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### resources/views/documents/order.blade.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/resources/views/documents/order.blade.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

## public/js

### public/js/administration.js

[فتح الملف](C:/xampp/htdocs/fieldflow-next/public/js/administration.js)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 67 | [`vehicleArt(type)`](C:/xampp/htdocs/fieldflow-next/public/js/administration.js:67) | JavaScript |
| 82 | [`vehicleTypePicker(types, selected)`](C:/xampp/htdocs/fieldflow-next/public/js/administration.js:82) | JavaScript |
| 123 | [`team(c)`](C:/xampp/htdocs/fieldflow-next/public/js/administration.js:123) | JavaScript |
| 225 | [`fleet(c)`](C:/xampp/htdocs/fieldflow-next/public/js/administration.js:225) | JavaScript |
| 350 | [`dashboard(c)`](C:/xampp/htdocs/fieldflow-next/public/js/administration.js:350) | JavaScript |
| 409 | [`form(action, id, c)`](C:/xampp/htdocs/fieldflow-next/public/js/administration.js:409) | JavaScript |
| 746 | [`payload(action, f, p)`](C:/xampp/htdocs/fieldflow-next/public/js/administration.js:746) | JavaScript |
| 760 | [`record(kind, id, c)`](C:/xampp/htdocs/fieldflow-next/public/js/administration.js:760) | JavaScript |
| 836 | [`reports(stats, c)`](C:/xampp/htdocs/fieldflow-next/public/js/administration.js:836) | JavaScript |

### public/js/app.js

[فتح الملف](C:/xampp/htdocs/fieldflow-next/public/js/app.js)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 16 | [`requestUuid()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:16) | JavaScript |
| 248 | [`metric(     label,     value,     unit,     foot,     ico = 'chart',     cls = '',     percent = null,   )`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:248) | JavaScript |
| 261 | [`match(r)`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:261) | JavaScript |
| 266 | [`filter(rows)`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:266) | JavaScript |
| 278 | [`filters(statuses = [], withDate = false)`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:278) | JavaScript |
| 283 | [`freshness(t)`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:283) | JavaScript |
| 300 | [`repRows()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:300) | JavaScript |
| 313 | [`alerts()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:313) | JavaScript |
| 360 | [`salesChart()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:360) | JavaScript |
| 380 | [`showCustomer(id)`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:380) | JavaScript |
| 384 | [`dashboard()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:384) | JavaScript |
| 389 | [`repDashboard()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:389) | JavaScript |
| 392 | [`tracking()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:392) | JavaScript |
| 407 | [`tripsPage()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:407) | JavaScript |
| 466 | [`readTripFile(id)`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:466) | JavaScript |
| 476 | [`showTripFile(id, edit = false)`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:476) | JavaScript |
| 566 | [`visitButtons(v)`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:566) | JavaScript |
| 596 | [`visitsPage()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:596) | JavaScript |
| 627 | [`customersPage()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:627) | JavaScript |
| 630 | [`inventoryPage()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:630) | JavaScript |
| 722 | [`ordersPage()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:722) | JavaScript |
| 760 | [`collectionsPage()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:760) | JavaScript |
| 792 | [`debtSummary()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:792) | JavaScript |
| 810 | [`receivablesInvoices()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:810) | JavaScript |
| 848 | [`bars(rows, asMoney = false)`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:848) | JavaScript |
| 857 | [`reportQuery()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:857) | JavaScript |
| 867 | [`fetchReport()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:867) | JavaScript |
| 891 | [`reportChart()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:891) | JavaScript |
| 942 | [`reportsPage()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:942) | JavaScript |
| 1135 | [`auditPage()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:1135) | JavaScript |
| 1155 | [`settingsPage()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:1155) | JavaScript |
| 1193 | [`receivablesPage()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:1193) | JavaScript |
| 1311 | [`adminContext()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:1311) | JavaScript |
| 1352 | [`render()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:1352) | JavaScript |
| 1387 | [`drawMap()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:1387) | JavaScript |
| 1471 | [`load(initial = false)`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:1471) | JavaScript |
| 1510 | [`toast(message, error = false)`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:1510) | JavaScript |
| 1518 | [`execute(action, payload, id = null)`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:1518) | JavaScript |
| 1562 | [`orderLine()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:1562) | JavaScript |
| 1566 | [`updateOrderPreview()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:1566) | JavaScript |
| 1607 | [`settlementsPage()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:1607) | JavaScript |
| 1679 | [`openForm(action, id)`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:1679) | JavaScript |
| 2114 | [`action(action, id)`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:2114) | JavaScript |
| 2208 | [`updateTripCoverage()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:2208) | JavaScript |
| 2255 | [`renderTripStopOrder()`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:2255) | JavaScript |
| 2510 | [`setMenu(open)`](C:/xampp/htdocs/fieldflow-next/public/js/app.js:2510) | JavaScript |

### public/js/customer-journey.js

[فتح الملف](C:/xampp/htdocs/fieldflow-next/public/js/customer-journey.js)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 51 | [`datePlus(date, count)`](C:/xampp/htdocs/fieldflow-next/public/js/customer-journey.js:51) | JavaScript |
| 57 | [`selectedDay(today)`](C:/xampp/htdocs/fieldflow-next/public/js/customer-journey.js:57) | JavaScript |
| 69 | [`orderCard(o, c)`](C:/xampp/htdocs/fieldflow-next/public/js/customer-journey.js:69) | JavaScript |
| 72 | [`visitCard(v, c)`](C:/xampp/htdocs/fieldflow-next/public/js/customer-journey.js:72) | JavaScript |
| 75 | [`customerPage(c)`](C:/xampp/htdocs/fieldflow-next/public/js/customer-journey.js:75) | JavaScript |
| 189 | [`customers(c)`](C:/xampp/htdocs/fieldflow-next/public/js/customer-journey.js:189) | JavaScript |
| 222 | [`repMoney(c)`](C:/xampp/htdocs/fieldflow-next/public/js/customer-journey.js:222) | JavaScript |
| 225 | [`published(c, repId)`](C:/xampp/htdocs/fieldflow-next/public/js/customer-journey.js:225) | JavaScript |
| 250 | [`schedule(c)`](C:/xampp/htdocs/fieldflow-next/public/js/customer-journey.js:250) | JavaScript |
| 282 | [`repDashboard(c)`](C:/xampp/htdocs/fieldflow-next/public/js/customer-journey.js:282) | JavaScript |
| 359 | [`form(action, id, c)`](C:/xampp/htdocs/fieldflow-next/public/js/customer-journey.js:359) | JavaScript |

### public/js/document.js

[فتح الملف](C:/xampp/htdocs/fieldflow-next/public/js/document.js)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### public/js/location-picker.js

[فتح الملف](C:/xampp/htdocs/fieldflow-next/public/js/location-picker.js)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 4 | [`destroy()`](C:/xampp/htdocs/fieldflow-next/public/js/location-picker.js:4) | JavaScript |
| 10 | [`attach(form)`](C:/xampp/htdocs/fieldflow-next/public/js/location-picker.js:10) | JavaScript |
| 53 | [`setPoint(a, b, pan = true)`](C:/xampp/htdocs/fieldflow-next/public/js/location-picker.js:53) | JavaScript |

### public/js/maintenance.js

[فتح الملف](C:/xampp/htdocs/fieldflow-next/public/js/maintenance.js)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 33 | [`customerActions(u, c)`](C:/xampp/htdocs/fieldflow-next/public/js/maintenance.js:33) | JavaScript |
| 52 | [`requests(c)`](C:/xampp/htdocs/fieldflow-next/public/js/maintenance.js:52) | JavaScript |
| 79 | [`repRecord(m, c)`](C:/xampp/htdocs/fieldflow-next/public/js/maintenance.js:79) | JavaScript |
| 120 | [`form(action, id, c)`](C:/xampp/htdocs/fieldflow-next/public/js/maintenance.js:120) | JavaScript |

## public/css

### public/css/administration.css

[فتح الملف](C:/xampp/htdocs/fieldflow-next/public/css/administration.css)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### public/css/app.css

[فتح الملف](C:/xampp/htdocs/fieldflow-next/public/css/app.css)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### public/css/document.css

[فتح الملف](C:/xampp/htdocs/fieldflow-next/public/css/document.css)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### public/css/light.css

[فتح الملف](C:/xampp/htdocs/fieldflow-next/public/css/light.css)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### public/css/workspace.css

[فتح الملف](C:/xampp/htdocs/fieldflow-next/public/css/workspace.css)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

## config

### config/app.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/config/app.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### config/auth.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/config/auth.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### config/cache.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/config/cache.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### config/database.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/config/database.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### config/fieldflow.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/config/fieldflow.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### config/filesystems.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/config/filesystems.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### config/logging.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/config/logging.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### config/mail.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/config/mail.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### config/queue.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/config/queue.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### config/sanctum.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/config/sanctum.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### config/services.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/config/services.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### config/session.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/config/session.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

## lang

### lang/ar/fieldflow.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/lang/ar/fieldflow.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### lang/ar/validation.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/lang/ar/validation.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

## database/migrations

### database/migrations/0001_01_01_000000_create_users_table.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/migrations/0001_01_01_000000_create_users_table.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 12 | [`up() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/0001_01_01_000000_create_users_table.php:12) | public |
| 43 | [`down() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/0001_01_01_000000_create_users_table.php:43) | public |

### database/migrations/0001_01_01_000001_create_cache_table.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/migrations/0001_01_01_000001_create_cache_table.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 12 | [`up() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/0001_01_01_000001_create_cache_table.php:12) | public |
| 30 | [`down() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/0001_01_01_000001_create_cache_table.php:30) | public |

### database/migrations/0001_01_01_000002_create_jobs_table.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/migrations/0001_01_01_000002_create_jobs_table.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 12 | [`up() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/0001_01_01_000002_create_jobs_table.php:12) | public |
| 51 | [`down() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/0001_01_01_000002_create_jobs_table.php:51) | public |

### database/migrations/2026_10_12_000100_create_field_operations_tables.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_12_000100_create_field_operations_tables.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 9 | [`up() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_12_000100_create_field_operations_tables.php:9) | public |
| 103 | [`down() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_12_000100_create_field_operations_tables.php:103) | public |

### database/migrations/2026_10_12_000200_create_operations_core.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_12_000200_create_operations_core.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 9 | [`up() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_12_000200_create_operations_core.php:9) | public |
| 169 | [`down() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_12_000200_create_operations_core.php:169) | public |

### database/migrations/2026_10_13_000300_add_credit_terms.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_13_000300_add_credit_terms.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 9 | [`up() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_13_000300_add_credit_terms.php:9) | public |
| 22 | [`down() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_13_000300_add_credit_terms.php:22) | public |

### database/migrations/2026_10_13_000400_create_debt_followups.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_13_000400_create_debt_followups.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 9 | [`up() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_13_000400_create_debt_followups.php:9) | public |
| 26 | [`down() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_13_000400_create_debt_followups.php:26) | public |

### database/migrations/2026_10_13_000500_create_cash_handovers.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_13_000500_create_cash_handovers.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 9 | [`up() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_13_000500_create_cash_handovers.php:9) | public |
| 31 | [`down() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_13_000500_create_cash_handovers.php:31) | public |

### database/migrations/2026_10_13_071027_create_personal_access_tokens_table.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_13_071027_create_personal_access_tokens_table.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 12 | [`up() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_13_071027_create_personal_access_tokens_table.php:12) | public |
| 29 | [`down() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_13_071027_create_personal_access_tokens_table.php:29) | public |

### database/migrations/2026_10_18_000001_add_resource_administration.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_18_000001_add_resource_administration.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 9 | [`up() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_18_000001_add_resource_administration.php:9) | public |
| 82 | [`down() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_18_000001_add_resource_administration.php:82) | public |

### database/migrations/2026_10_20_000001_add_trip_plan_controls.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_20_000001_add_trip_plan_controls.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 9 | [`up() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_20_000001_add_trip_plan_controls.php:9) | public |
| 20 | [`down() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_20_000001_add_trip_plan_controls.php:20) | public |

### database/migrations/2026_10_22_000001_add_username_login.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_22_000001_add_username_login.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 10 | [`up() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_22_000001_add_username_login.php:10) | public |
| 18 | [`down() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_22_000001_add_username_login.php:18) | public |

### database/migrations/2026_10_24_000001_add_customer_journeys.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_24_000001_add_customer_journeys.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 10 | [`up() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_24_000001_add_customer_journeys.php:10) | public |
| 69 | [`down() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_24_000001_add_customer_journeys.php:69) | public |

### database/migrations/2026_10_24_000002_add_customer_profile_version.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_24_000002_add_customer_profile_version.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 9 | [`up() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_24_000002_add_customer_profile_version.php:9) | public |
| 14 | [`down() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_24_000002_add_customer_profile_version.php:14) | public |

### database/migrations/2026_10_24_000003_add_customer_changes_and_rep_vehicles.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_24_000003_add_customer_changes_and_rep_vehicles.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 9 | [`up() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_24_000003_add_customer_changes_and_rep_vehicles.php:9) | public |
| 47 | [`down() : void`](C:/xampp/htdocs/fieldflow-next/database/migrations/2026_10_24_000003_add_customer_changes_and_rep_vehicles.php:47) | public |

## database/seeders

### database/seeders/CreditDemoSeeder.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/seeders/CreditDemoSeeder.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 13 | [`run() : void`](C:/xampp/htdocs/fieldflow-next/database/seeders/CreditDemoSeeder.php:13) | public |

### database/seeders/DatabaseSeeder.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/seeders/DatabaseSeeder.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 16 | [`run() : void`](C:/xampp/htdocs/fieldflow-next/database/seeders/DatabaseSeeder.php:16) | public |

### database/seeders/DemoSeeder.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/seeders/DemoSeeder.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 19 | [`run() : void`](C:/xampp/htdocs/fieldflow-next/database/seeders/DemoSeeder.php:19) | public |
| 73 | [`demoUser(string $name, string $email, string $password, array &$credentials) : User`](C:/xampp/htdocs/fieldflow-next/database/seeders/DemoSeeder.php:73) | private |

### database/seeders/OperationsDemoSeeder.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/seeders/OperationsDemoSeeder.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 30 | [`run() : void`](C:/xampp/htdocs/fieldflow-next/database/seeders/OperationsDemoSeeder.php:30) | public |

## database/factories

### database/factories/UserFactory.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/database/factories/UserFactory.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 25 | [`definition() : array`](C:/xampp/htdocs/fieldflow-next/database/factories/UserFactory.php:25) | public |
| 40 | [`unverified() : static`](C:/xampp/htdocs/fieldflow-next/database/factories/UserFactory.php:40) | public |

## scripts

### scripts/archive/unzip.cmd

[فتح الملف](C:/xampp/htdocs/fieldflow-next/scripts/archive/unzip.cmd)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### scripts/archive/unzip.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/scripts/archive/unzip.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### scripts/backup.ps1

[فتح الملف](C:/xampp/htdocs/fieldflow-next/scripts/backup.ps1)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### scripts/composer.ps1

[فتح الملف](C:/xampp/htdocs/fieldflow-next/scripts/composer.ps1)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### scripts/demo.ps1

[فتح الملف](C:/xampp/htdocs/fieldflow-next/scripts/demo.ps1)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### scripts/enable-lan-firewall.ps1

[فتح الملف](C:/xampp/htdocs/fieldflow-next/scripts/enable-lan-firewall.ps1)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### scripts/lan-address.ps1

[فتح الملف](C:/xampp/htdocs/fieldflow-next/scripts/lan-address.ps1)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### scripts/php.ps1

[فتح الملف](C:/xampp/htdocs/fieldflow-next/scripts/php.ps1)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### scripts/snapshot.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/scripts/snapshot.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### scripts/start-lan.ps1

[فتح الملف](C:/xampp/htdocs/fieldflow-next/scripts/start-lan.ps1)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### scripts/start.ps1

[فتح الملف](C:/xampp/htdocs/fieldflow-next/scripts/start.ps1)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

## tests

### tests/Feature/AnalyticsTest.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/tests/Feature/AnalyticsTest.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 19 | [`setUp() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/AnalyticsTest.php:19) | protected |
| 29 | [`test_daily_sales_reconcile_to_period_total_and_visits() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/AnalyticsTest.php:29) | public |
| 38 | [`test_representative_filter_matches_export() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/AnalyticsTest.php:38) | public |
| 48 | [`test_collections_during_period_can_include_old_invoices_without_new_sales() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/AnalyticsTest.php:48) | public |
| 54 | [`test_foreign_filters_are_rejected_and_viewer_cannot_read_money() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/AnalyticsTest.php:54) | public |
| 62 | [`test_performance_and_payment_methods_reconcile_to_filtered_period_totals() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/AnalyticsTest.php:62) | public |
| 76 | [`test_viewer_performance_does_not_expose_financial_breakdown() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/AnalyticsTest.php:76) | public |

### tests/Feature/CashCustodyTest.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/tests/Feature/CashCustodyTest.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 33 | [`setUp() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CashCustodyTest.php:33) | protected |
| 55 | [`act(string $action, array $payload, ?string $key = null)`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CashCustodyTest.php:55) | private |
| 60 | [`requestCash(int $amount = 500) : int`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CashCustodyTest.php:60) | private |
| 65 | [`test_pending_handover_reserves_cash_without_reducing_custody_or_customer_payment() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CashCustodyTest.php:65) | public |
| 73 | [`test_acceptance_and_replay_reduce_custody_exactly_once() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CashCustodyTest.php:73) | public |
| 88 | [`test_reject_and_cancel_release_reservation_but_keep_history() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CashCustodyTest.php:88) | public |
| 100 | [`test_initiator_cannot_approve_own_handover_even_as_manager() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CashCustodyTest.php:100) | public |
| 109 | [`test_representative_cannot_request_cash_of_others_or_approve() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CashCustodyTest.php:109) | public |
| 116 | [`test_company_switch_hides_handover_and_prevents_review() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CashCustodyTest.php:116) | public |
| 126 | [`test_order_document_is_protected_by_company_and_representative() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CashCustodyTest.php:126) | public |

### tests/Feature/CustomerJourneyTest.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/tests/Feature/CustomerJourneyTest.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 41 | [`setUp() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CustomerJourneyTest.php:41) | protected |
| 60 | [`actor(User $user) : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CustomerJourneyTest.php:60) | private |
| 65 | [`journey(string $action, array $data, ?string $key = null)`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CustomerJourneyTest.php:65) | private |
| 70 | [`action(string $action, array $data)`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CustomerJourneyTest.php:70) | private |
| 75 | [`template(array $override = []) : array`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CustomerJourneyTest.php:75) | private |
| 80 | [`publish() : int`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CustomerJourneyTest.php:80) | private |
| 87 | [`test_customer_ownership_is_enforced_for_data_profile_and_direct_requests() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CustomerJourneyTest.php:87) | public |
| 106 | [`test_profile_totals_cover_full_history_and_history_is_paginated() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CustomerJourneyTest.php:106) | public |
| 120 | [`test_weekly_publish_orders_stops_and_is_idempotent_without_rewriting_occurrences() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CustomerJourneyTest.php:120) | public |
| 137 | [`test_weekly_plans_reject_other_owners_foreign_customers_and_wrong_weekday() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CustomerJourneyTest.php:137) | public |
| 145 | [`test_rep_must_resolve_each_stop_with_reason_and_real_location_before_closing() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CustomerJourneyTest.php:145) | public |
| 166 | [`test_customer_edit_preserves_history_and_rejects_stale_or_unsafe_reassignment() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CustomerJourneyTest.php:166) | public |
| 179 | [`test_visit_result_does_not_deduct_delivered_stock_twice() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CustomerJourneyTest.php:179) | public |
| 199 | [`test_manual_trip_edit_removes_outdated_distance_without_deleting_weekly_origin() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CustomerJourneyTest.php:199) | public |
| 210 | [`test_dated_day_endpoint_returns_full_own_stops_and_no_other_representative_records() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/CustomerJourneyTest.php:210) | public |

### tests/Feature/DeviceApiTest.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/tests/Feature/DeviceApiTest.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 16 | [`fixture() : array`](C:/xampp/htdocs/fieldflow-next/tests/Feature/DeviceApiTest.php:16) | private |
| 25 | [`test_device_login_returns_expiring_token_and_requires_explicit_membership() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/DeviceApiTest.php:25) | public |
| 39 | [`test_revoked_membership_and_expired_tokens_are_rejected() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/DeviceApiTest.php:39) | public |
| 51 | [`test_device_logout_revokes_only_the_current_token() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/DeviceApiTest.php:51) | public |

### tests/Feature/LedgerReconciliationTest.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/tests/Feature/LedgerReconciliationTest.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 15 | [`test_synthetic_dataset_reconciles_stocks_payments_and_company_debts() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/LedgerReconciliationTest.php:15) | public |

### tests/Feature/LocationAndRepresentativeTest.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/tests/Feature/LocationAndRepresentativeTest.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 46 | [`setUp() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/LocationAndRepresentativeTest.php:46) | protected |
| 67 | [`context() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/LocationAndRepresentativeTest.php:67) | private |
| 72 | [`actor(User $u) : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/LocationAndRepresentativeTest.php:72) | private |
| 77 | [`car(string $plate, string $type) : Vehicle`](C:/xampp/htdocs/fieldflow-next/tests/Feature/LocationAndRepresentativeTest.php:77) | private |
| 85 | [`action(string $a, array $p = [], bool $ops = false, ?string $key = null)`](C:/xampp/htdocs/fieldflow-next/tests/Feature/LocationAndRepresentativeTest.php:85) | private |
| 90 | [`location() : array`](C:/xampp/htdocs/fieldflow-next/tests/Feature/LocationAndRepresentativeTest.php:90) | private |
| 95 | [`assignment(?int $vehicleId = null) : array`](C:/xampp/htdocs/fieldflow-next/tests/Feature/LocationAndRepresentativeTest.php:95) | private |
| 100 | [`test_manager_can_correct_location_with_planned_stops_without_deleting_history() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/LocationAndRepresentativeTest.php:100) | public |
| 110 | [`test_in_progress_visit_freezes_the_proof_point() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/LocationAndRepresentativeTest.php:110) | public |
| 117 | [`test_rep_can_edit_own_contacts_but_location_needs_review_and_other_customer_is_forbidden() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/LocationAndRepresentativeTest.php:117) | public |
| 134 | [`test_rep_registration_stays_pending_then_creates_one_owned_customer_when_approved() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/LocationAndRepresentativeTest.php:134) | public |
| 150 | [`test_supervisor_edits_operational_member_fields_without_changing_login_or_roles() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/LocationAndRepresentativeTest.php:150) | public |
| 162 | [`test_vehicle_allocation_updates_selected_plans_and_preserves_replacement_history_and_stock() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/LocationAndRepresentativeTest.php:162) | public |
| 177 | [`test_active_trip_blocks_vehicle_change_and_foreign_company_resources_are_rejected() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/LocationAndRepresentativeTest.php:177) | public |

### tests/Feature/OperationsTest.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 46 | [`setUp() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:46) | protected |
| 71 | [`context() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:71) | private |
| 76 | [`postAction(string $action, array $payload = [], ?string $key = null)`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:76) | private |
| 81 | [`orderData(string $terms = 'credit') : array`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:81) | private |
| 86 | [`makeOrder(string $terms = 'credit') : int`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:86) | private |
| 91 | [`test_prices_are_computed_on_server_and_credit_is_posted_only_on_delivery() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:91) | public |
| 102 | [`test_cash_delivery_records_full_payment_exactly_once() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:102) | public |
| 114 | [`test_partial_delivery_and_later_settlement_close_credit_without_rounding() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:114) | public |
| 127 | [`test_collection_replay_does_not_duplicate_and_changed_payload_is_rejected() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:127) | public |
| 141 | [`test_overpayment_and_unfulfilled_collection_leave_no_receipt() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:141) | public |
| 150 | [`test_insufficient_stock_rolls_back_delivery_and_ledger() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:150) | public |
| 160 | [`test_credit_limit_prevents_delivery_without_changing_inventory() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:160) | public |
| 168 | [`test_representative_cannot_read_or_collect_another_representatives_credit() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:168) | public |
| 177 | [`test_supervisor_can_see_credit_but_cannot_collect() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:177) | public |
| 188 | [`test_switching_company_does_not_leak_orders_or_aggregates() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:188) | public |
| 199 | [`test_visit_requires_location_and_active_trip_and_assigned_user() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:199) | public |
| 215 | [`test_visit_rejects_stale_inaccurate_and_outside_locations() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:215) | public |
| 228 | [`test_location_history_rejects_jump_and_replays_safely() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:228) | public |
| 243 | [`test_trip_cannot_close_with_pending_visits() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:243) | public |
| 249 | [`test_transfer_is_balanced_and_capacity_is_enforced() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:249) | public |
| 263 | [`test_inactive_membership_cannot_read() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:263) | public |
| 269 | [`test_catalog_receipt_is_audited_and_does_not_allow_direct_vehicle_receiving() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:269) | public |
| 284 | [`test_credit_limit_change_requires_manager_and_preserves_existing_debt() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:284) | public |
| 295 | [`test_role_revocation_also_blocks_cached_operation_replay() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:295) | public |
| 305 | [`test_payment_promise_is_a_recorded_followup_not_a_collection() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:305) | public |
| 325 | [`test_supervisor_can_follow_up_credit_without_receiving_money() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:325) | public |
| 336 | [`test_multi_product_delivery_rolls_back_all_lines_if_one_product_has_insufficient_stock() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:336) | public |
| 351 | [`test_export_uses_company_timezone_at_midnight() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:351) | public |
| 364 | [`test_report_exports_requested_range_and_escapes_spreadsheet_formulas() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/OperationsTest.php:364) | public |

### tests/Feature/ResourceAdministrationTest.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/tests/Feature/ResourceAdministrationTest.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 41 | [`setUp() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/ResourceAdministrationTest.php:41) | protected |
| 57 | [`action(string $name, array $payload, ?string $requestId = null)`](C:/xampp/htdocs/fieldflow-next/tests/Feature/ResourceAdministrationTest.php:57) | private |
| 62 | [`memberData() : array`](C:/xampp/htdocs/fieldflow-next/tests/Feature/ResourceAdministrationTest.php:62) | private |
| 67 | [`customer(int $areaId) : Customer`](C:/xampp/htdocs/fieldflow-next/tests/Feature/ResourceAdministrationTest.php:67) | private |
| 74 | [`tripData(int $customerId) : array`](C:/xampp/htdocs/fieldflow-next/tests/Feature/ResourceAdministrationTest.php:74) | private |
| 79 | [`test_manager_creates_member_with_scoped_permissions_and_password_is_never_returned() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/ResourceAdministrationTest.php:79) | public |
| 95 | [`test_foreign_area_and_existing_username_do_not_create_or_take_over_an_account() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/ResourceAdministrationTest.php:95) | public |
| 109 | [`test_supervisor_cannot_manage_accounts_or_replay_a_previously_authorized_creation() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/ResourceAdministrationTest.php:109) | public |
| 118 | [`test_suspension_revokes_existing_session_and_preserves_historical_membership() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/ResourceAdministrationTest.php:118) | public |
| 129 | [`test_self_suspension_and_cross_company_account_updates_are_rejected() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/ResourceAdministrationTest.php:129) | public |
| 136 | [`test_representative_area_policy_is_checked_on_planning_and_again_on_start() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/ResourceAdministrationTest.php:136) | public |
| 148 | [`test_revoking_sales_or_collection_capability_blocks_actions_before_validation() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/ResourceAdministrationTest.php:148) | public |
| 157 | [`test_manager_updates_rep_and_shared_identity_cannot_be_changed_by_one_company() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/ResourceAdministrationTest.php:157) | public |
| 172 | [`test_vehicle_type_is_explicit_and_legacy_records_are_not_invented_as_vans() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/ResourceAdministrationTest.php:172) | public |
| 191 | [`test_vehicle_handover_history_is_exclusive_and_survives_return_and_reassignment() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/ResourceAdministrationTest.php:191) | public |
| 204 | [`test_expired_or_foreign_driver_cannot_receive_vehicle() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/ResourceAdministrationTest.php:204) | public |
| 216 | [`test_direct_visit_and_order_requests_cannot_bypass_area_restrictions() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/ResourceAdministrationTest.php:216) | public |
| 230 | [`test_active_trip_blocks_vehicle_return_and_assigned_driver_cannot_be_disabled() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/ResourceAdministrationTest.php:230) | public |

### tests/Feature/TripPlanningTest.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/tests/Feature/TripPlanningTest.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 39 | [`setUp() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/TripPlanningTest.php:39) | protected |
| 57 | [`vehicle(string $plate) : Vehicle`](C:/xampp/htdocs/fieldflow-next/tests/Feature/TripPlanningTest.php:57) | private |
| 65 | [`action(string $action, array $payload, ?string $key = null)`](C:/xampp/htdocs/fieldflow-next/tests/Feature/TripPlanningTest.php:65) | private |
| 70 | [`planData() : array`](C:/xampp/htdocs/fieldflow-next/tests/Feature/TripPlanningTest.php:70) | private |
| 75 | [`plan() : Trip`](C:/xampp/htdocs/fieldflow-next/tests/Feature/TripPlanningTest.php:75) | private |
| 83 | [`revision(Trip $trip) : array`](C:/xampp/htdocs/fieldflow-next/tests/Feature/TripPlanningTest.php:83) | private |
| 88 | [`test_replanning_preserves_stops_reassigns_resources_and_records_before_and_after() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/TripPlanningTest.php:88) | public |
| 117 | [`test_stale_revision_and_missing_reason_are_rejected_without_changing_plan() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/TripPlanningTest.php:117) | public |
| 128 | [`test_cancellation_preserves_records_and_excludes_stops_from_execution_metrics_and_exports() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/TripPlanningTest.php:128) | public |
| 147 | [`test_active_closed_and_started_plans_cannot_be_rewritten_or_cancelled() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/TripPlanningTest.php:147) | public |
| 159 | [`test_linked_orders_and_visit_evidence_block_edits_even_if_trip_status_is_planned() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/TripPlanningTest.php:159) | public |
| 171 | [`test_selected_coverage_and_inactive_customer_validation_are_atomic() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/TripPlanningTest.php:171) | public |
| 183 | [`test_representative_cannot_change_plan_or_view_another_representatives_file() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/TripPlanningTest.php:183) | public |
| 194 | [`test_viewer_has_operational_file_without_financial_details_and_accountant_cannot_open_it() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/TripPlanningTest.php:194) | public |
| 208 | [`test_foreign_company_trip_is_not_found_and_foreign_customer_is_rejected() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/TripPlanningTest.php:208) | public |
| 224 | [`test_removed_stop_can_be_restored_with_same_id_and_is_not_started_as_a_cancelled_stop() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/TripPlanningTest.php:224) | public |
| 237 | [`test_saved_tracking_points_block_cancellation_and_empty_or_unordered_stop_payloads_are_rejected() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/TripPlanningTest.php:237) | public |
| 248 | [`test_supervisor_can_replan_and_unavailable_vehicle_cannot_be_assigned() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/TripPlanningTest.php:248) | public |

### tests/Feature/UsernameLoginTest.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/tests/Feature/UsernameLoginTest.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 16 | [`test_username_login_accepts_trimmed_case_and_does_not_require_email() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/UsernameLoginTest.php:16) | public |
| 23 | [`test_wrong_password_and_email_only_login_are_rejected() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/UsernameLoginTest.php:23) | public |
| 31 | [`test_login_template_requests_username_as_text() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/UsernameLoginTest.php:31) | public |
| 36 | [`test_demo_seed_uses_unique_named_logins_without_emails_and_does_not_reset_existing_passwords() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/UsernameLoginTest.php:36) | public |
| 51 | [`test_short_demo_password_is_accepted_only_in_enabled_demo_mode() : void`](C:/xampp/htdocs/fieldflow-next/tests/Feature/UsernameLoginTest.php:51) | public |

### tests/TestCase.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/tests/TestCase.php)

ملف قالب/إعداد/تنسيق/سكربت، أو لا يحتوي دالة معلنة مسماة بهذه الصيغة؛ راجع فصل المجلد ووظيفته.

### tests/Unit/ExampleTest.php

[فتح الملف](C:/xampp/htdocs/fieldflow-next/tests/Unit/ExampleTest.php)

| السطر | التعريف | الوصول/النوع |
|---:|---|---|
| 12 | [`test_that_true_is_true() : void`](C:/xampp/htdocs/fieldflow-next/tests/Unit/ExampleTest.php:12) | public |

## ملفات الجذر

| الملف | الوظيفة |
|---|---|
| [public/index.php](C:/xampp/htdocs/fieldflow-next/public/index.php) | مدخل الويب |
| [artisan](C:/xampp/htdocs/fieldflow-next/artisan) | مدخل أوامر Laravel |
| [composer.json](C:/xampp/htdocs/fieldflow-next/composer.json) | تعريف متطلبات PHP والتحميل والأوامر |
| [composer.lock](C:/xampp/htdocs/fieldflow-next/composer.lock) | الإصدارات المثبتة |
| [package.json](C:/xampp/htdocs/fieldflow-next/package.json) | أوامر تنسيق وفحص JS |
| [package-lock.json](C:/xampp/htdocs/fieldflow-next/package-lock.json) | تثبيت تبعيات Node |
| [phpunit.xml](C:/xampp/htdocs/fieldflow-next/phpunit.xml) | بيئة الاختبار المعزولة |
| [README.md](C:/xampp/htdocs/fieldflow-next/README.md) | نظرة عامة وروابط الأدلة |
| [start-fieldflow.cmd](C:/xampp/htdocs/fieldflow-next/start-fieldflow.cmd) | تشغيل محلي |
| [start-amrbit-lan.cmd](C:/xampp/htdocs/fieldflow-next/start-amrbit-lan.cmd) | تشغيل للشبكة |
| [enable-amrbit-lan.cmd](C:/xampp/htdocs/fieldflow-next/enable-amrbit-lan.cmd) | استثناء Firewall مقيد؛ يحتاج مسؤولًا |
| [.env.example](C:/xampp/htdocs/fieldflow-next/.env.example) | قالب إعداد دون اعتماد قيمه كإعدادك الفعلي |
| [.gitignore](C:/xampp/htdocs/fieldflow-next/.gitignore) | قواعد تجاهل ملفات خاصة/مولدة |
| [public/.htaccess](C:/xampp/htdocs/fieldflow-next/public/.htaccess) | توجيه Apache إلى index.php |
