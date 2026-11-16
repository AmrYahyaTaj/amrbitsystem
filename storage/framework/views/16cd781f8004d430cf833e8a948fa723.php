<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>الدخول — <?php echo e(config('app.name')); ?></title><link rel="icon" href="/favicon.svg"><link rel="stylesheet" href="/css/app.css"><link rel="stylesheet" href="/css/workspace.css?v=<?php echo e(filemtime(public_path('css/workspace.css'))); ?>"><link rel="stylesheet" href="/css/light.css?v=<?php echo e(filemtime(public_path('css/light.css'))); ?>"></head><body class="login-body workspace-login"><main class="login-layout"><section class="login-story"><svg class="login-network" viewBox="0 0 700 750" aria-hidden="true"><defs><pattern id="network-grid" width="60" height="60" patternUnits="userSpaceOnUse"><path d="M60 0H0V60" fill="none" stroke="#78934d" opacity=".15"/></pattern></defs><rect width="700" height="750" fill="url(#network-grid)"/><path d="M600 590C450 680 450 250 270 400S150 620 50 490" fill="none" stroke="#c5dd91" stroke-width="2" stroke-dasharray="6 9"/><circle cx="600" cy="590" r="9" fill="#dce9ac"/><circle cx="270" cy="400" r="9" fill="#dce9ac"/><circle cx="50" cy="490" r="9" fill="#dce9ac"/><circle cx="600" cy="590" r="34" fill="none" stroke="#93af62"/><circle cx="270" cy="400" r="34" fill="none" stroke="#93af62"/><circle cx="50" cy="490" r="34" fill="none" stroke="#93af62"/></svg><a class="brand" href="/login"><span class="brand-symbol"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'route']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'route']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></span><span><?php echo e(config('app.name')); ?><small>SMART FIELD OPERATIONS</small></span></a><span class="login-label">الميدان أقرب مما تتخيل</span><h1>كل رحلة.<br>كل زيارة.<br><em>كل التفاصيل.</em></h1><p>من أول محطة إلى آخر تحصيل،<br>عمليات مترابطة وقرارات أوضح.</p><div class="story-route"><span>تخطيط الرحلة</span><i></i><span>تنفيذ الزيارة</span><i></i><span>تسليم وتحصيل</span></div><div class="login-foot">صُنع لعمليات التوزيع، بمختلف قطاعاتها. <b><?php echo e(config('app.name')); ?></b></div></section><section class="login-panel"><div class="login-form"><span class="eyebrow">مرحبًا بعودتك</span><h2>ابدأ يومك بوضوح.</h2><p>سجّل الدخول إلى مساحة عمل شركتك.</p><form method="post" action="/login"><?php echo csrf_field(); ?><label for="username">اسم المستخدم</label><input name="username" id="username" type="text" value="<?php echo e(old('username')); ?>" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="40" required placeholder="amr" dir="ltr"><label for="password">كلمة المرور</label><input name="password" id="password" type="password" autocomplete="current-password" required placeholder="••••••••••" dir="ltr"><?php if($errors->any()): ?><div class="form-error" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?><button class="primary-btn login-submit">دخول مساحة العمل <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
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
<?php endif; ?></button></form><div class="login-demo"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'shield']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'shield']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?><div><strong>نسخة محلية للتجربة</strong><p>حسابات وبيانات مصطنعة. بيانات الدخول محفوظة في ملف التجربة داخل مجلد المشروع.</p></div></div></div></section></main></body></html>
<?php /**PATH C:\xampp\htdocs\fieldflow-next\resources\views/auth/login.blade.php ENDPATH**/ ?>