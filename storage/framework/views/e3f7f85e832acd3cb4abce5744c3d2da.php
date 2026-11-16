<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="<?php echo e(csrf_token()); ?>"><meta name="theme-color" content="#141511">
<title><?php echo e(config('app.name')); ?> — مركز العمليات</title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="/css/app.css?v=<?php echo e(filemtime(public_path('css/app.css'))); ?>"><link rel="stylesheet" href="/css/workspace.css?v=<?php echo e(filemtime(public_path('css/workspace.css'))); ?>">
<link rel="stylesheet" href="/css/administration.css?v=<?php echo e(filemtime(public_path('css/administration.css'))); ?>">
<link rel="stylesheet" href="/css/light.css?v=<?php echo e(filemtime(public_path('css/light.css'))); ?>">
<link rel="stylesheet" href="/css/custody.css?v=<?php echo e(filemtime(public_path('css/custody.css'))); ?>">
<script src="/js/custody.js?v=<?php echo e(filemtime(public_path('js/custody.js'))); ?>" defer></script>
<?php if($supplyWorkspace ?? false): ?><link rel="stylesheet" href="/css/supply.css?v=<?php echo e(filemtime(public_path('css/supply.css'))); ?>"><script src="/js/supply.js?v=<?php echo e(filemtime(public_path('js/supply.js'))); ?>" defer></script><?php endif; ?>
<?php if(($qualityWorkspace ?? false) || ($roadmapWorkspace ?? false) || ($archiveWorkspace ?? false) || ($delegationWorkspace ?? false)): ?><link rel="stylesheet" href="/css/quality.css?v=<?php echo e(filemtime(public_path('css/quality.css'))); ?>"><?php endif; ?>
<?php if($qualityWorkspace ?? false): ?><script src="/js/quality.js?v=<?php echo e(filemtime(public_path('js/quality.js'))); ?>" defer></script><?php endif; ?>
<?php if($roadmapWorkspace ?? false): ?><script src="/js/roadmap.js?v=<?php echo e(filemtime(public_path('js/roadmap.js'))); ?>" defer></script><?php endif; ?>
<?php if($archiveWorkspace ?? false): ?><script src="/js/archive.js?v=<?php echo e(filemtime(public_path('js/archive.js'))); ?>" defer></script><?php endif; ?>
<?php if($delegationWorkspace ?? false): ?><script src="/js/delegations.js?v=<?php echo e(filemtime(public_path('js/delegations.js'))); ?>" defer></script><?php endif; ?>
<?php if (! (($handoverWorkspace ?? false) || ($supplyWorkspace ?? false) || ($qualityWorkspace ?? false) || ($roadmapWorkspace ?? false) || ($archiveWorkspace ?? false) || ($delegationWorkspace ?? false))): ?>
<script src="/js/customer-journey.js?v=<?php echo e(filemtime(public_path('js/customer-journey.js'))); ?>" defer></script>
<script src="/js/location-picker.js?v=<?php echo e(filemtime(public_path('js/location-picker.js'))); ?>" defer></script>
<script src="/js/maintenance.js?v=<?php echo e(filemtime(public_path('js/maintenance.js'))); ?>" defer></script>
<script src="/vendor/leaflet/leaflet.js" defer></script><script src="/js/administration.js?v=<?php echo e(filemtime(public_path('js/administration.js'))); ?>" defer></script><script src="/js/app.js?v=<?php echo e(filemtime(public_path('js/app.js'))); ?>" defer></script>
<?php endif; ?>
</head>
<body class="workspace-app" data-section="<?php echo e($section); ?>" data-role="<?php echo e($companyRole->value); ?>" data-timezone="<?php echo e($activeCompany->timezone); ?>" data-supply-workspace="<?php echo e((($supplyWorkspace ?? false) || ($qualityWorkspace ?? false) || ($roadmapWorkspace ?? false) || ($archiveWorkspace ?? false) || ($delegationWorkspace ?? false)) ? 'true' : 'false'); ?>" data-custody-workspace="<?php echo e(($handoverWorkspace ?? false) ? 'true' : 'false'); ?>">
<?php
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
?>
<div class="workspace-shell">
<header class="workspace-header">
<div class="brand-cluster"><button class="icon-btn mobile-toggle" id="menu-toggle" aria-label="فتح القائمة" aria-controls="sidebar" aria-expanded="false"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'grid']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'grid']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></button><a class="brand" href="/dashboard"><span class="brand-symbol"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'route','width' => '24','height' => '24']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'route','width' => '24','height' => '24']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></span><span dir="ltr"><?php echo e(config('app.name')); ?></span></a></div>
<nav class="primary-navigation" aria-label="مجموعات العمل"><?php $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(count($group['items'])): ?><a href="/<?php echo e($group['items'][0][0]); ?>" class="<?php echo e($key===$activeGroup?'selected':''); ?>" <?php if($key===$activeGroup): ?> aria-current="true" <?php endif; ?>><?php echo e($group['label']); ?></a><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></nav>
<div class="workspace-tools"><button class="icon-btn" id="open-launcher" aria-label="البحث والتنقل بين الأقسام" title="البحث والتنقل • Ctrl K"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'search']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'search']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></button><button type="button" class="icon-btn notification-trigger" id="notifications-trigger" aria-label="إشعاراتي" aria-controls="notifications-dialog"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'bell']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'bell']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?><span id="notifications-count" class="notification-count" hidden></span></button><details class="account-menu"><summary aria-label="حسابي"><span class="avatar"><?php echo e(mb_substr(auth()->user()->name,0,1)); ?></span><span class="account-name"><strong><?php echo e(auth()->user()->name); ?></strong><small><?php echo e($companyRole->label()); ?></small></span><span class="chevron">⌄</span></summary><div class="account-dropdown"><strong><?php echo e(auth()->user()->name); ?></strong><small><?php echo e($activeCompany->name); ?></small><form action="/logout" method="post"><?php echo csrf_field(); ?><button type="submit" aria-label="تسجيل الخروج"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'exit']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'exit']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>تسجيل الخروج</button></form></div></details></div>
</header>
<aside class="sidebar" id="sidebar" aria-label="كل أقسام النظام" inert aria-hidden="true"><button class="icon-btn mobile-nav-close" id="menu-close" aria-label="إغلاق القائمة">×</button><a class="brand" href="/dashboard"><span dir="ltr"><?php echo e(config('app.name')); ?></span></a><div class="workspace"><span class="company-avatar"><?php echo e(mb_substr($activeCompany->name,0,1)); ?></span><div><strong><?php echo e($activeCompany->name); ?></strong><small><?php echo e($companyRole->label()); ?></small></div></div><nav><?php $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(count($group['items'])): ?><span class="nav-caption"><?php echo e($group['label']); ?></span><?php $__currentLoopData = $group['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$route,$icon,$label]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="/<?php echo e($route); ?>" class="nav-item <?php echo e($route===$navigationSection?'active':''); ?>" <?php if($route===$navigationSection): ?> aria-current="page" <?php endif; ?>><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $icon]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?><span><?php echo e($label); ?></span></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?> <?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></nav><div class="nav-bottom"><small><?php echo e(config('app.name')); ?> • مساحة تجريبية</small></div></aside>
<button class="mobile-backdrop" id="menu-backdrop" aria-label="إغلاق القائمة" hidden></button>
<main class="main">
<div class="context-navigation"><nav class="section-navigation" aria-label="أقسام <?php echo e($groups[$activeGroup]['label']); ?>"><?php $__currentLoopData = $groups[$activeGroup]['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$route,$icon,$label]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="/<?php echo e($route); ?>" class="<?php echo e($route===$navigationSection?'selected':''); ?>" <?php if($route===$navigationSection): ?> aria-current="page" <?php endif; ?>><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $icon]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?><span><?php echo e($label); ?></span></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></nav><form method="post" action="/company/switch" class="company-switch"><?php echo csrf_field(); ?><label class="sr-only" for="company_id">الشركة</label><span class="company-status-dot" aria-hidden="true"></span><select name="company_id" id="company_id"><?php $__currentLoopData = $availableCompanies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $company): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($company->id); ?>" <?php if($company->id===$activeCompany->id): echo 'selected'; endif; ?>><?php echo e($company->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select><button type="submit" class="icon-btn" aria-label="تبديل الشركة" title="تبديل الشركة"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'arrow']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'arrow']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></button></form></div>
<div class="page">
<div class="page-heading"><div><div class="eyebrow"><span><?php echo e($groups[$activeGroup]['label']); ?></span><span class="slash">/</span><span id="section-code">OPERATIONS</span></div><h1 id="page-title">مركز العمليات</h1><p id="page-description"></p><span id="breadcrumb-title" hidden></span></div><div class="heading-actions"><label class="global-search"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'search']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'search']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?><input id="global-search" placeholder="ابحث في هذه الصفحة…" aria-label="بحث"></label><button class="icon-btn" id="refresh" aria-label="تحديث البيانات"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'refresh']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'refresh']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></button><button class="primary-btn" id="main-action" hidden><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'plus']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'plus']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?><span>إجراء جديد</span></button></div></div>
<div class="demo-strip"><span class="demo-tag"><span class="status-dot"></span>بيئة تجريبية</span><span>كل الأسماء والأرصدة والمواقع هنا مصطنعة.</span><span class="update-time" id="updated-at">جارٍ تحميل البيانات…</span></div>
<div id="app-content"><?php if($delegationWorkspace ?? false): ?> <?php echo $__env->make('delegations.workspace', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php elseif($archiveWorkspace ?? false): ?> <?php echo $__env->make('archive.workspace', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php elseif($qualityWorkspace ?? false): ?> <?php echo $__env->make('quality.workspace', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php elseif($roadmapWorkspace ?? false): ?> <?php echo $__env->make('roadmap.workspace', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php elseif($supplyWorkspace ?? false): ?> <?php echo $__env->make('supply.workspace', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php elseif($handoverWorkspace ?? false): ?> <?php echo $__env->make('handovers.workspace', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php else: ?> <div class="loading"><span class="loading-ring"></span>نجهّز مساحة عملك…</div> <?php endif; ?></div>
<footer class="page-footer"><span><?php echo e(config('app.name')); ?></span><span>ميدان واحد. عمليات مترابطة.</span></footer>
</div></main></div>
<dialog id="launcher-dialog" aria-labelledby="launcher-title"><div class="dialog-heading"><div><span class="eyebrow">WORKSPACE NAVIGATOR</span><h2 id="launcher-title">إلى أين تريد الانتقال؟</h2></div><button type="button" id="close-launcher" class="icon-btn" aria-label="إغلاق البحث">×</button></div><label class="launcher-search"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'search']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'search']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?><input id="launcher-search" type="search" autocomplete="off" aria-label="ابحث عن قسم" placeholder="رحلات، عملاء، آجل، مخزون…"><kbd>Esc</kbd></label><div class="launcher-results"><?php $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php $__currentLoopData = $group['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$route,$icon,$label]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="/<?php echo e($route); ?>" data-nav-label="<?php echo e($label); ?> <?php echo e($group['label']); ?>"><span class="launcher-icon"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $icon]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></span><span><strong><?php echo e($label); ?></strong><small><?php echo e($group['label']); ?></small></span><span class="launcher-arrow">←</span></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><div id="launcher-empty" class="empty" hidden>لا يوجد قسم بهذا الاسم.</div></div><div class="launcher-footer">كل الأقسام التي يسمح بها دورك • Ctrl / ⌘ K للوصول السريع</div></dialog>
<dialog id="action-dialog" aria-labelledby="dialog-title"><div class="dialog-heading"><div><span class="eyebrow">إجراء جديد</span><h2 id="dialog-title"></h2></div><button type="button" class="icon-btn" id="close-dialog" aria-label="إغلاق">×</button></div><form id="action-form"><div id="form-fields"></div><div class="form-error" id="form-error" role="alert"></div><div class="dialog-footer"><button type="button" id="cancel-dialog" class="secondary-btn">إلغاء</button><button class="primary-btn" type="submit" id="submit-action">حفظ العملية</button></div></form></dialog>
<dialog id="record-dialog" aria-labelledby="record-title"><div class="dialog-heading"><div><span class="eyebrow">ملف مرتبط بالعمليات</span><h2 id="record-title"></h2></div><button type="button" class="icon-btn" id="close-record" aria-label="إغلاق الملف">×</button></div><div id="record-content"></div></dialog>
<div class="toast" id="toast" role="status" hidden></div>
<?php echo $__env->make('handovers.dialogs', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php if($supplyWorkspace ?? false): ?> <?php echo $__env->make('supply.dialog', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php endif; ?>
</body></html>
<?php /**PATH C:\xampp\htdocs\fieldflow-next\resources\views/app.blade.php ENDPATH**/ ?>