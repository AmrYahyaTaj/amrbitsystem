# تشغيل FieldFlow Next على Windows

التحقق المحلي: 13 أكتوبر 2026. الإصدارات الفعلية المستخدمة: Laravel **13.35.0**، PHP **8.4.26 NTS x64**، Node **24.19.0**، Composer **2.10.2**، Sanctum **4.3.3**. ملفا القفل يحددان التبعيات بدقة؛ لا يعني هذا أن كل رقم هو آخر تحديث عالمي.

Laravel 13 يدعم PHP 8.3–8.5 وفق [سياسة Laravel الرسمية](https://laravel.com/framework/docs/13.x/releases). PHP 8.2 الخاص بـXAMPP لا يشغله. [حزم PHP الرسمية لـWindows](https://www.php.net/downloads.php?os=windows) و[متطلبات التثبيت](https://laravel.com/framework/docs/13.x/installation) هما مرجع أي ترقية لاحقة.

## المسارات

| المكوّن | المسار | الاستخدام |
|---|---|---|
| PHP XAMPP الأصلي | `C:\xampp\php\php.exe` | مشاريعك السابقة ومرجع Yii، لم تتغير إعداداته |
| PHP 8.4 المستقل الموجود | `C:\xampp\htdocs\fieldflow\.runtime\php84\php.exe` | تشغيل المشروع الجديد عبر wrappers، دون تغيير PATH الدائم |
| Composer | `C:\composer\composer.phar` | wrapper يشغله بـPHP 8.4، ويعيد PATH بعد الانتهاء |
| الجديد | `C:\xampp\htdocs\fieldflow-next` | مصدر وقاعدة بيانات وإعدادات مستقلة |
| قاعدة العرض | `database/database.sqlite` داخل الجديد | لا تُشاركها النسخة السابقة |
| منفذ الجديد | `127.0.0.1:8090` | استماع loopback فقط |
| منفذ المرجع | `127.0.0.1:8088` | خدمة مستقلة، لا تتعارض مع الجديد |

المشروع مستقل في الكود والبيانات، ويستخدم تشغيل PHP المستقل نفسه الذي جُهّز سابقًا. إذا نقلته إلى جهاز آخر، ثبّت PHP المناسب وحدّث المسار في `scripts/php.ps1` و`scripts/composer.ps1`. لا تحذف مجلد runtime السابق قبل توفير بديل.

## التشغيل المعتاد

1. افتح `start-fieldflow.cmd` من مجلد الجديد.
2. انتظر رسالة عنوان `http://127.0.0.1:8090` ثم افتحه.
3. افتح `.runtime/demo-access.txt` للحصول على كلمة مرور الحساب المطلوب.
4. أوقف هذه الخدمة بـCtrl+C في نافذتها عندما تنتهي. هذا لا يوقف Apache أو Yii أو المشاريع الأخرى.

على نسخة مصدر جديدة فقط: انسخ `.env.example` إلى `.env` دون استبدال إعدادات موجودة، أنشئ ملف SQLite فارغًا عند غيابه، شغّل Composer install بالـwrapper ثم key:generate، وبعدها `scripts/demo.ps1`. لا تعِد توليد المفتاح فوق بيانات مشفرة محفوظة.

## التحقق

```powershell
.\scripts\php.ps1 -v
.\scripts\php.ps1 artisan --version
.\scripts\composer.ps1 check-platform-reqs
.\scripts\php.ps1 artisan migrate:status
```

امتدادات SQLite وmbstring وOpenSSL وغيرها المطلوبة مفعّلة في PHP المستقل. الـwrapper الخاص بفك ملفات Composer يعتمد على ZIP في PHP XAMPP الأصلي؛ هذا لتثبيت التبعيات، ولا يخلط بيئتي التطبيقين. MySQL عبر PHP 8.4 والانتقال للإنتاج ليسا مفعّلين تلقائيًا في هذه النسخة؛ لا تغيّر XAMPP لتحقيقهما.

`.htaccess` في جذر المشروع يمنع Apache من تقديم الملفات الخاصة؛ لا تتوقع تشغيله من `localhost/fieldflow-next`. الوجهة المسموح تقديمها هي `public/` عبر الخادم المحلي أو virtual host يُهيّأ لاحقًا. لا تفتح مجلد المشروع كاملًا للويب.

الإعداد الحالي local/debug وHTTP على الجهاز نفسه للتجربة. قبل نشر فعلي: HTTPS، debug=false، secure cookies، قاعدة إنتاج، مزود خرائط مناسب، ومراجعة الأمان والنسخ والخصوصية كما في خطة التنفيذ. لم يُنشر المشروع خارجيًا.
