<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#141511">
<title>{{ config('app.name') }} — مركز العمليات</title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="/css/app.css?v={{ filemtime(public_path('css/app.css')) }}"><link rel="stylesheet" href="/css/workspace.css?v={{ filemtime(public_path('css/workspace.css')) }}">
<link rel="stylesheet" href="/css/administration.css?v={{ filemtime(public_path('css/administration.css')) }}">
<link rel="stylesheet" href="/css/light.css?v={{ filemtime(public_path('css/light.css')) }}">
<link rel="stylesheet" href="/css/custody.css?v={{ filemtime(public_path('css/custody.css')) }}">
<script src="/js/custody.js?v={{ filemtime(public_path('js/custody.js')) }}" defer></script>
@if($supplyWorkspace ?? false)<link rel="stylesheet" href="/css/supply.css?v={{ filemtime(public_path('css/supply.css')) }}"><script src="/js/supply.js?v={{ filemtime(public_path('js/supply.js')) }}" defer></script>@endif
@if(($qualityWorkspace ?? false) || ($roadmapWorkspace ?? false) || ($archiveWorkspace ?? false) || ($delegationWorkspace ?? false))<link rel="stylesheet" href="/css/quality.css?v={{ filemtime(public_path('css/quality.css')) }}">@endif
@if($qualityWorkspace ?? false)<script src="/js/quality.js?v={{ filemtime(public_path('js/quality.js')) }}" defer></script>@endif
@if($roadmapWorkspace ?? false)<script src="/js/roadmap.js?v={{ filemtime(public_path('js/roadmap.js')) }}" defer></script>@endif
@if($archiveWorkspace ?? false)<script src="/js/archive.js?v={{ filemtime(public_path('js/archive.js')) }}" defer></script>@endif
@if($delegationWorkspace ?? false)<script src="/js/delegations.js?v={{ filemtime(public_path('js/delegations.js')) }}" defer></script>@endif
@unless(($handoverWorkspace ?? false) || ($supplyWorkspace ?? false) || ($qualityWorkspace ?? false) || ($roadmapWorkspace ?? false) || ($archiveWorkspace ?? false) || ($delegationWorkspace ?? false))
<script src="/js/customer-journey.js?v={{ filemtime(public_path('js/customer-journey.js')) }}" defer></script>
<script src="/js/location-picker.js?v={{ filemtime(public_path('js/location-picker.js')) }}" defer></script>
<script src="/js/maintenance.js?v={{ filemtime(public_path('js/maintenance.js')) }}" defer></script>
<script src="/vendor/leaflet/leaflet.js" defer></script><script src="/js/administration.js?v={{ filemtime(public_path('js/administration.js')) }}" defer></script><script src="/js/app.js?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
@endunless
</head>
<body class="workspace-app" data-section="{{ $section }}" data-role="{{ $companyRole->value }}" data-timezone="{{ $activeCompany->timezone }}" data-supply-workspace="{{ (($supplyWorkspace ?? false) || ($qualityWorkspace ?? false) || ($roadmapWorkspace ?? false) || ($archiveWorkspace ?? false) || ($delegationWorkspace ?? false)) ? 'true' : 'false' }}" data-custody-workspace="{{ ($handoverWorkspace ?? false) ? 'true' : 'false' }}">
@php
$navigationSection=request()->is('schedule')?'schedule':$section;
$groups=[
'home'=>['label'=>'الرئيسية','items'=>[['dashboard','grid','مركز العمليات']]],
'operations'=>['label'=>'الميدان','items'=>[['tracking','map','متابعة الميدان'],['schedule','route','الجدول الأسبوعي'],['trips','route','الرحلات والمسارات'],['visits','check','الزيارات']]],
'sales'=>['label'=>'المبيعات','items'=>[['customers','pin','العملاء والمناطق'],['orders','bag','الطلبات والمبيعات']]],
'resources'=>['label'=>'الموارد','items'=>[['vehicles','truck','الأسطول'],['team','users','الفريق'],['production','box','الإنتاج'],['quality','shield','الجودة والاعتمادات'],['warehouses','box','المخازن ومسؤولو العهد'],['traceability','route','تتبع الدفعات'],['handovers','check','التسليم والاستلام']]],
'finance'=>['label'=>'المالية','items'=>[['receivables','wallet','الآجل والمديونيات'],['collections','wallet','التحصيل'],['settlements','wallet','عهدة النقد']]],
'insights'=>['label'=>'التحليل والإدارة','items'=>[['reports','chart','التقارير والتحليل'],['audit','shield','سجل العمليات'],['archive','box','الأرشيف الإداري'],['delegations','users','التفويض والبدلاء'],['settings','settings','إعدادات الشركة'],['roadmap','route','خطة استكمال النظام']]],
];
$activeGroup='home';
foreach($groups as $key=>&$group){
$group['items']=array_values(array_filter($group['items'],fn($item)=>in_array($item[0],$sections)));
if(in_array($navigationSection,array_column($group['items'],0))) $activeGroup=$key;
}
unset($group);
@endphp
<div class="workspace-shell">
<header class="workspace-header">
<div class="brand-cluster"><button class="icon-btn mobile-toggle" id="menu-toggle" aria-label="فتح القائمة" aria-controls="sidebar" aria-expanded="false"><x-icon name="grid"/></button><a class="brand" href="/dashboard"><span class="brand-symbol"><x-icon name="route" width="24" height="24"/></span><span dir="ltr">{{ config('app.name') }}</span></a></div>
<nav class="primary-navigation" aria-label="مجموعات العمل">@foreach($groups as $key=>$group)@if(count($group['items']))<a href="/{{ $group['items'][0][0] }}" class="{{ $key===$activeGroup?'selected':'' }}" @if($key===$activeGroup) aria-current="true" @endif>{{ $group['label'] }}</a>@endif @endforeach</nav>
<div class="workspace-tools"><button class="icon-btn" id="open-launcher" aria-label="البحث والتنقل بين الأقسام" title="البحث والتنقل • Ctrl K"><x-icon name="search"/></button><button type="button" class="icon-btn notification-trigger" id="notifications-trigger" aria-label="إشعاراتي" aria-controls="notifications-dialog"><x-icon name="bell"/><span id="notifications-count" class="notification-count" hidden></span></button><details class="account-menu"><summary aria-label="حسابي"><span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span><span class="account-name"><strong>{{ auth()->user()->name }}</strong><small>{{ $companyRole->label() }}</small></span><span class="chevron">⌄</span></summary><div class="account-dropdown"><strong>{{ auth()->user()->name }}</strong><small>{{ $activeCompany->name }}</small><form action="/logout" method="post">@csrf<button type="submit" aria-label="تسجيل الخروج"><x-icon name="exit"/>تسجيل الخروج</button></form></div></details></div>
</header>
<aside class="sidebar" id="sidebar" aria-label="كل أقسام النظام" inert aria-hidden="true"><button class="icon-btn mobile-nav-close" id="menu-close" aria-label="إغلاق القائمة">×</button><a class="brand" href="/dashboard"><span dir="ltr">{{ config('app.name') }}</span></a><div class="workspace"><span class="company-avatar">{{ mb_substr($activeCompany->name,0,1) }}</span><div><strong>{{ $activeCompany->name }}</strong><small>{{ $companyRole->label() }}</small></div></div><nav>@foreach($groups as $group)@if(count($group['items']))<span class="nav-caption">{{ $group['label'] }}</span>@foreach($group['items'] as [$route,$icon,$label])<a href="/{{ $route }}" class="nav-item {{ $route===$navigationSection?'active':'' }}" @if($route===$navigationSection) aria-current="page" @endif><x-icon :name="$icon"/><span>{{ $label }}</span></a>@endforeach @endif @endforeach</nav><div class="nav-bottom"><small>{{ config('app.name') }} • مساحة تجريبية</small></div></aside>
<button class="mobile-backdrop" id="menu-backdrop" aria-label="إغلاق القائمة" hidden></button>
<main class="main">
<div class="context-navigation"><nav class="section-navigation" aria-label="أقسام {{ $groups[$activeGroup]['label'] }}">@foreach($groups[$activeGroup]['items'] as [$route,$icon,$label])<a href="/{{ $route }}" class="{{ $route===$navigationSection?'selected':'' }}" @if($route===$navigationSection) aria-current="page" @endif><x-icon :name="$icon"/><span>{{ $label }}</span></a>@endforeach</nav><form method="post" action="/company/switch" class="company-switch">@csrf<label class="sr-only" for="company_id">الشركة</label><span class="company-status-dot" aria-hidden="true"></span><select name="company_id" id="company_id">@foreach($availableCompanies as $company)<option value="{{ $company->id }}" @selected($company->id===$activeCompany->id)>{{ $company->name }}</option>@endforeach</select><button type="submit" class="icon-btn" aria-label="تبديل الشركة" title="تبديل الشركة"><x-icon name="arrow"/></button></form></div>
<div class="page">
<div class="page-heading"><div><div class="eyebrow"><span>{{ $groups[$activeGroup]['label'] }}</span><span class="slash">/</span><span id="section-code">OPERATIONS</span></div><h1 id="page-title">مركز العمليات</h1><p id="page-description"></p><span id="breadcrumb-title" hidden></span></div><div class="heading-actions"><label class="global-search"><x-icon name="search"/><input id="global-search" placeholder="ابحث في هذه الصفحة…" aria-label="بحث"></label><button class="icon-btn" id="refresh" aria-label="تحديث البيانات"><x-icon name="refresh"/></button><button class="primary-btn" id="main-action" hidden><x-icon name="plus"/><span>إجراء جديد</span></button></div></div>
<div class="demo-strip"><span class="demo-tag"><span class="status-dot"></span>بيئة تجريبية</span><span>كل الأسماء والأرصدة والمواقع هنا مصطنعة.</span><span class="update-time" id="updated-at">جارٍ تحميل البيانات…</span></div>
<div id="app-content">@if($delegationWorkspace ?? false) @include('delegations.workspace') @elseif($archiveWorkspace ?? false) @include('archive.workspace') @elseif($qualityWorkspace ?? false) @include('quality.workspace') @elseif($roadmapWorkspace ?? false) @include('roadmap.workspace') @elseif($supplyWorkspace ?? false) @include('supply.workspace') @elseif($handoverWorkspace ?? false) @include('handovers.workspace') @else <div class="loading"><span class="loading-ring"></span>نجهّز مساحة عملك…</div> @endif</div>
<footer class="page-footer"><span>{{ config('app.name') }}</span><span>ميدان واحد. عمليات مترابطة.</span></footer>
</div></main></div>
<dialog id="launcher-dialog" aria-labelledby="launcher-title"><div class="dialog-heading"><div><span class="eyebrow">WORKSPACE NAVIGATOR</span><h2 id="launcher-title">إلى أين تريد الانتقال؟</h2></div><button type="button" id="close-launcher" class="icon-btn" aria-label="إغلاق البحث">×</button></div><label class="launcher-search"><x-icon name="search"/><input id="launcher-search" type="search" autocomplete="off" aria-label="ابحث عن قسم" placeholder="رحلات، عملاء، آجل، مخزون…"><kbd>Esc</kbd></label><div class="launcher-results">@foreach($groups as $group)@foreach($group['items'] as [$route,$icon,$label])<a href="/{{ $route }}" data-nav-label="{{ $label }} {{ $group['label'] }}"><span class="launcher-icon"><x-icon :name="$icon"/></span><span><strong>{{ $label }}</strong><small>{{ $group['label'] }}</small></span><span class="launcher-arrow">←</span></a>@endforeach @endforeach<div id="launcher-empty" class="empty" hidden>لا يوجد قسم بهذا الاسم.</div></div><div class="launcher-footer">كل الأقسام التي يسمح بها دورك • Ctrl / ⌘ K للوصول السريع</div></dialog>
<dialog id="action-dialog" aria-labelledby="dialog-title"><div class="dialog-heading"><div><span class="eyebrow">إجراء جديد</span><h2 id="dialog-title"></h2></div><button type="button" class="icon-btn" id="close-dialog" aria-label="إغلاق">×</button></div><form id="action-form"><div id="form-fields"></div><div class="form-error" id="form-error" role="alert"></div><div class="dialog-footer"><button type="button" id="cancel-dialog" class="secondary-btn">إلغاء</button><button class="primary-btn" type="submit" id="submit-action">حفظ العملية</button></div></form></dialog>
<dialog id="record-dialog" aria-labelledby="record-title"><div class="dialog-heading"><div><span class="eyebrow">ملف مرتبط بالعمليات</span><h2 id="record-title"></h2></div><button type="button" class="icon-btn" id="close-record" aria-label="إغلاق الملف">×</button></div><div id="record-content"></div></dialog>
<div class="toast" id="toast" role="status" hidden></div>
@include('handovers.dialogs')
@if($supplyWorkspace ?? false) @include('supply.dialog') @endif
</body></html>
