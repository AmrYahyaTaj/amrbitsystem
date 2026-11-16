<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name'=>'grid']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['name'=>'grid']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<svg <?php echo e($attributes->merge(['width'=>20,'height'=>20,'viewBox'=>'0 0 24 24','fill'=>'none','stroke'=>'currentColor','stroke-width'=>'1.7','stroke-linecap'=>'round','stroke-linejoin'=>'round','aria-hidden'=>'true'])); ?>>
<?php switch($name):
case ('grid'): ?><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/><?php break; ?>
<?php case ('map'): ?><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3Z"/><path d="M9 3v15M15 6v15"/><?php break; ?>
<?php case ('route'): ?><circle cx="6" cy="5" r="3"/><circle cx="18" cy="19" r="3"/><path d="M6 8v7a4 4 0 0 0 8 0V9a4 4 0 0 1 7-2"/><?php break; ?>
<?php case ('check'): ?><path d="m8 12 3 3 5-6"/><rect x="3" y="3" width="18" height="18" rx="5"/><?php break; ?>
<?php case ('users'): ?><circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 4a3 3 0 0 1 0 6M18 15a5 5 0 0 1 3 6"/><?php break; ?>
<?php case ('pin'): ?><path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/><?php break; ?>
<?php case ('truck'): ?><path d="M3 5h11v12H3ZM14 9h4l3 4v4h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/><?php break; ?>
<?php case ('box'): ?><path d="m12 3 9 5v9l-9 5-9-5V8ZM3 8l9 5 9-5M12 13v9M7 5l10 6"/><?php break; ?>
<?php case ('bag'): ?><path d="M5 7h14l2 14H3ZM8 7V6a4 4 0 0 1 8 0v1"/><?php break; ?>
<?php case ('wallet'): ?><path d="M20 7H4a2 2 0 0 1 0-4h14v4M3 5v14a2 2 0 0 0 2 2h15V7M20 11h-5v6h5"/><path d="M16 14h1"/><?php break; ?>
<?php case ('chart'): ?><path d="M4 3v18h17M8 16v-5M13 16V7M18 16v-9"/><?php break; ?>
<?php case ('shield'): ?><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6Z"/><path d="m8 12 3 3 5-6"/><?php break; ?>
<?php case ('settings'): ?><circle cx="12" cy="12" r="3"/><path d="m9 3-1 3-3 1-2 3 2 2-1 4 3 2 3-1 2 4 3-1 1-3 3-1 2-3-2-3 1-3-3-2-3 1-2-3Z"/><?php break; ?>
<?php case ('search'): ?><circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/><?php break; ?>
<?php case ('plus'): ?><path d="M12 5v14M5 12h14"/><?php break; ?>
<?php case ('arrow'): ?><path d="M19 12H5m6-6-6 6 6 6"/><?php break; ?>
<?php case ('refresh'): ?><path d="M20 7v5h-5M4 17v-5h5M6 5a8 8 0 0 1 14 7M18 19a8 8 0 0 1-14-7"/><?php break; ?>
<?php case ('bell'): ?><path d="M5 17h14l-2-3V9a5 5 0 0 0-10 0v5ZM10 21h4"/><?php break; ?>
<?php case ('exit'): ?><path d="M10 3H4v18h6M9 12h12m-4-4 4 4-4 4"/><?php break; ?>
<?php case ('clock'): ?><circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/><?php break; ?>
<?php endswitch; ?>
</svg>
<?php /**PATH C:\xampp\htdocs\fieldflow-next\resources\views/components/icon.blade.php ENDPATH**/ ?>