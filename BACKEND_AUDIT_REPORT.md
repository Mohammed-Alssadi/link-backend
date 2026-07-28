# تقرير الفحص الشامل للباك اند — منصتا سلة وزد

> تاريخ الفحص: 2026-07-28
> المفحوص: نظام التكامل الموحد بين منصتي سلة (Salla) وزد (Zid)
> الشدة: حرجة | عالية | متوسطة | منخفضة

---

## ملخص تنفيذي

تم فحص جميع ملفات الباك اند المتعلقة بالتكامل مع منصتي سلة وزد:

| الملف | الوصف |
|-------|-------|
| SallaProvider.php | مزود منصة سلة |
| ZidProvider.php | مزود منصة زد |
| ProductData.php | DTO موحد للمنتجات |
| StoreProfileData.php | DTO موحد لبيانات المتجر |
| UserProfileData.php | DTO موحد لبيانات التاجر |
| PlatformService.php | خدمة التنسيق بين المنصات |
| ProductController.php | كنترولر المنتجات |
| ProductFilterRequest.php | معالج الفلاتر والباجنيشن |
| PlatformProvider.php | الواجهة (Interface) |

---

## [CRITICAL-01] Pagination سلة: حقول خاطئة تماماً

**الملف:** SallaProvider.php — السطر 221-235

### المشكلة:
الكود الحالي يبحث في استجابة سلة عن حقول غير موجودة:

```php
// خاطئ — هذه الحقول لا ترجعها سلة
$currentPage = (int) ($sallaPagination['currentPage'] ?? $sallaPagination['current_page'] ?? ...);
$totalPages  = (int) ($sallaPagination['totalPages']  ?? $sallaPagination['total_pages']  ?? 1);
$totalCount  = (int) ($sallaPagination['total']       ?? $sallaPagination['count']        ?? 0);
$perPage     = (int) ($sallaPagination['perPage']     ?? $sallaPagination['per_page']     ?? ...);
```

### البنية الحقيقية لاستجابة Pagination في سلة (من التوثيق الرسمي):
```json
{
  "pagination": {
    "count": 10,
    "current": 1,
    "next": "https://api.salla.dev/admin/v2/products?page=2"
  }
}
```

### الحقول الحقيقية في سلة:
| الحقل | الاسم في سلة | الاسم المستخدم حالياً | الحالة |
|-------|-------------|----------------------|--------|
| الصفحة الحالية | current | currentPage / current_page | خاطئ |
| العدد في الصفحة | count | total / count | خاطئ |
| الصفحة التالية | next (رابط URL) | غير مستخدم | مفقود |
| إجمالي الصفحات | غير موجود في سلة | totalPages / total_pages | خاطئ |
| عدد لكل صفحة | غير موجود مباشرةً | perPage / per_page | خاطئ |

### النتيجة:
- currentPage يُرجع دائماً 1 بسبب فشل القراءة
- totalPages يُرجع دائماً 1 لأن الحقل غير موجود في الاستجابة
- hasNext يكون دائماً false رغم وجود صفحات أخرى
- الداشبورد لا يتنقل بين الصفحات لمستخدمي سلة

### الحل الصحيح:
```php
// صحيح — بناءً على التوثيق الرسمي لسلة
$currentPage = (int) ($sallaPagination['current'] ?? $filters['page'] ?? 1);
$countInPage = (int) ($sallaPagination['count'] ?? count($products));
$nextUrl     = $sallaPagination['next'] ?? null;
$hasNext     = !empty($nextUrl);
$hasPrev     = $currentPage > 1;
$perPage     = (int) ($filters['limit'] ?? 15);
$totalPages  = $hasNext ? ($currentPage + 1) : $currentPage; // تقديري
```

---

## [CRITICAL-02] فلتر البحث في زد: اسم المعامل خاطئ

**الملف:** ZidProvider.php — السطر 227

### المشكلة:
```php
// خاطئ — زد لا تدعم معامل "search"
'search' => $filters['search'] ?? null,
```

### من التوثيق الرسمي لزد:
زد تستخدم معامل "q" للبحث، وليس "search":
```
GET /v1/products/?q=اسم_المنتج
```

### الحل الصحيح:
```php
// صحيح
'q' => $filters['search'] ?? null,
```

---

## [CRITICAL-03] Pagination زد: قراءة من مكان خاطئ

**الملف:** ZidProvider.php — السطر 276-286

### المشكلة:
```php
// خاطئ — يبحث في كائن paging منفصل غير موجود
$zidPaging = $json['paging'] ?? ($json['pagination'] ?? []);
$currentPage = (int) ($zidPaging['page'] ?? ...);
$perPage     = (int) ($zidPaging['page_size'] ?? ...);
$totalCount  = (int) ($zidPaging['count'] ?? ... ?? $json['count'] ?? 0);
```

### البنية الحقيقية لاستجابة زد:
```json
{
  "page": 1,
  "page_size": 15,
  "count": 120,
  "results": [...]
}
```

زد تُرجع page و page_size و count في root الاستجابة مباشرةً، وليس في كائن فرعي منفصل.
فيكون $zidPaging = [] دائماً وتفشل جميع القراءات.

### الحل الصحيح:
```php
// صحيح — قراءة من root مباشرةً
$currentPage = (int) ($json['page'] ?? $filters['page'] ?? 1);
$perPage     = (int) ($json['page_size'] ?? $filters['limit'] ?? 15);
$totalCount  = (int) ($json['count'] ?? 0);
$totalPages  = $perPage > 0 ? (int) ceil($totalCount / $perPage) : 1;
```

---

## [CRITICAL-04] فلتر التصنيف في زد: معامل مكرر وغير صحيح

**الملف:** ZidProvider.php — السطر 228-229

### المشكلة:
```php
// خاطئ — إرسال نفس المعامل مرتين بأسماء مختلفة
'categories' => $catId,
'category'   => $catId,
```

### الحل الصحيح:
```php
// صحيح — زد تستخدم categories فقط
'categories' => $catId,
```

---

## [CRITICAL-05] فلتر التصنيف في سلة: إرسال مكرر

**الملف:** SallaProvider.php — السطر 173-180

### المشكلة:
```php
// خاطئ — إرسال نفس القيمة في معاملين مختلفين
'category_id' => $catId,
'category'    => $catId,
```

### الحل الصحيح:
```php
// صحيح — سلة تستخدم category_id فقط
'category_id' => $catId,
```

---

## [HIGH-01] Header مفقود في زد: Role: Manager

**الملف:** ZidProvider.php — السطر 483-501 (دالة buildZidHeaders)

### المشكلة:
```php
// ينقص header مهم
$headers = [
    'Authorization'   => 'Bearer ' . $accessToken,
    'X-Manager-Token' => $managerToken ?? '',
    'Accept-Language' => 'ar',
    'Accept'          => 'application/json',
    // Role: Manager مفقود!
];
```

### من التوثيق الرسمي لزد:
Header "Role: Manager" مطلوب عند الوصول لـ endpoints الخاصة بالمدير.
غيابه يُسبب:
- استجابة بصلاحيات customer وليس manager
- بيانات ناقصة (مثل created_at وupdated_at)
- احتمالية رفض الطلب (403 Forbidden)

### الحل الصحيح:
```php
$headers = [
    'Authorization'   => 'Bearer ' . $accessToken,
    'X-Manager-Token' => $managerToken ?? '',
    'Role'            => 'Manager',
    'Accept-Language' => 'ar',
    'Accept'          => 'application/json',
];
```

---

## [HIGH-02] حالة المنتج في زد: منطق ناقص

**الملف:** ProductData.php — السطر 432-433

### المشكلة:
```php
// منطق ناقص — "out of stock" غير مُعالج
$isPublished = (bool) ($rawProduct['is_published'] ?? true);
$status = $isPublished ? 'sale' : (($rawProduct['is_draft'] ?? false) ? 'hidden' : 'sale');
```

### التحليل:
| الحالة | قيمة زد | القيمة المُرجعة حالياً | القيمة الصحيحة |
|--------|---------|----------------------|----------------|
| منشور + مخزون | is_published: true | sale | sale |
| مخفي | is_published: false | hidden | hidden |
| نفد المخزون | quantity: 0 | sale (خاطئ) | out |
| مسودة | is_draft: true | منطق ملتوي | hidden |

عندما يُرسل الفرونت اند فلتر status=out لزد، يستلم منتجات بـ status="sale" وليس "out".

### الحل الصحيح:
```php
$isPublished = (bool) ($rawProduct['is_published'] ?? true);
$quantity    = (int) ($rawProduct['quantity'] ?? 0);
$isInfinite  = (bool) ($rawProduct['is_infinite'] ?? false);

if (!$isPublished) {
    $status = 'hidden';
} elseif (!$isInfinite && $quantity <= 0) {
    $status = 'out';
} else {
    $status = 'sale';
}
```

---

## [HIGH-03] بيانات سلة عند التعديل: حقول غير موجودة في سلة تُرسل للفرونت

**الملف:** ProductData.php — fromSalla()

### المشكلة:
يُرسل الـ DTO لحقول موجودة في زد فقط ولا وجود لها في سلة:

| الحقل في DTO | الوجود في سلة | الوجود في زد |
|-------------|--------------|-------------|
| nameEn | لا يوجد | موجود |
| descriptionEn | لا يوجد (سلة عربية فقط) | موجود |
| shortDescriptionEn | لا يوجد | موجود |
| seoTitleEn | لا يوجد | موجود |
| seoDescriptionEn | لا يوجد | موجود |
| branding | لا يوجد | موجود |
| localization | لا يوجد | موجود |
| business | لا يوجد | موجود |
| metafields | لا يوجد | موجود |
| badge | لا يوجد | موجود |

### النتيجة:
الفرونت اند يعرض حقل "الاسم بالإنجليزي" في نموذج تعديل منتج سلة، لكن:
1. سلة عربية فقط (لا دعم إنجليزي)
2. الحقل يُرسل null ويُعرض فارغاً مُربكاً للمستخدم
3. إرسال nameEn في طلب تحديث سلة قد يُسبب خطأ

### الحل:
الكود يُرجع null بالفعل، لكن يجب توثيق ذلك بوضوح حتى يتحقق الفرونت من platform قبل عرض هذه الحقول.

---

## [HIGH-04] Stocks المخزون: سلة ترجع مصفوفة مشتركة للمتغيرات

**الملف:** ProductData.php — fromSalla() السطر 205

### المشكلة:
```php
// يُعطي كل variant نفس stocks المنتج الأصلي
$variants[] = new ProductVariantData(
    ...
    stocks: $stocks  // هذه stocks المنتج الأصلي، وليس stocks الـ variant
);
```

في سلة، كل SKU (variant) قد يكون له مخزون في فروع مختلفة، لكن الكود يُعطي جميع المتغيرات نفس المخزون الإجمالي للمنتج مما يُظهر بيانات مخزون مكررة وخاطئة.

---

## [HIGH-05] Zid Categories: استخراج خاطئ للبيانات

**الملف:** ZidProvider.php — السطر 407

### المشكلة:
استخراج التصنيفات من الاستجابة يعمل، لكن التصنيفات المتداخلة (sub-categories) لا تُعالج.

---

## [HIGH-06] الداشبورد الموحد: بيانات خاصة بزد تظهر في الفرونت كـ null لسلة

### المشكلة:
الكود يُرجع null بشكل صحيح للحقول غير المدعومة في سلة، لكن:
- الفرونت قد يعرض هذه الحقول فارغة بدلاً من إخفائها
- يجب أن يتحقق الفرونت من قيمة "platform" في الاستجابة قبل عرض أي حقل

---

## [MED-01] ProductFilterRequest: صفحة رقم 0 مُقبولة

**الملف:** ProductFilterRequest.php — السطر 32

### المشكلة:
```php
'page' => (int) $this->query('page', 1),
// إذا أرسل المستخدم page=0 يُصبح 0 وليس 1
```

### الحل:
```php
'page' => max(1, (int) $this->query('page', 1)),
```

---

## [MED-02] سلة: البحث يستخدم "keyword" لكن يجب التأكيد

**الملف:** SallaProvider.php — السطر 176

الكود يُرسل "keyword" لسلة وهو صحيح حسب التوثيق. لكن يجب التأكد من أن الاستجابة تعمل بشكل سليم.

---

## [MED-03] زد: page_size يجب أن يُرسل كـ int صريح

**الملف:** ZidProvider.php — السطر 226

```php
// قد يُرسل كـ string إذا جاء من query string
'page_size' => $filters['limit'] ?? 15,

// الحل:
'page_size' => (int) ($filters['limit'] ?? 15),
```

---

## [MED-04] seoTitleEn في زد: يُعطي string فارغ بدل null

**الملف:** ProductData.php — السطر 466-468

```php
// خاطئ — (string) على null يُعطي "" وليس null
seoTitleEn: (string) ($rawProduct['seo']['title']['en'] ?? null),

// الحل الصحيح:
seoTitleEn: !empty($rawProduct['seo']['title']['en']) ? (string)$rawProduct['seo']['title']['en'] : null,
```

---

## [MED-05] StoreSocialData في زد: خلط بين phone وwhatsapp

**الملف:** StoreProfileData.php — السطر 177

```php
// خاطئ — يضع phone المتجر كـ whatsapp
$social = new StoreSocialData(
    whatsapp: (string) ($store['phone'] ?? ''),  // خاطئ
    ...
);

// الحل الصحيح:
$social = new StoreSocialData(
    whatsapp: (string) ($socialData['whatsapp'] ?? $store['phone'] ?? ''),
    ...
);
```

---

## [MED-06] StoreSocialData في زد: حقول telegram وyoutube وmaroof مفقودة

**الملف:** StoreProfileData.php — السطر 176-183

### المشكلة:
```php
// حقول telegram وyoutube وmaroof غير موجودة في fromZid
$social = new StoreSocialData(
    whatsapp: ...,
    twitter: ...,
    instagram: ...,
    snapchat: ...,
    facebook: ...,
    tiktok: ...
    // telegram: مفقود!
    // youtube: مفقود!
    // maroof: مفقود!
);
```

### النتيجة:
الداشبورد يعرض telegram="" وyoutube="" لمستخدمي زد حتى لو كانت موجودة.

### الحل الصحيح:
```php
$social = new StoreSocialData(
    whatsapp:  (string) ($socialData['whatsapp']  ?? $store['phone'] ?? ''),
    twitter:   (string) ($socialData['twitter']   ?? ''),
    instagram: (string) ($socialData['instagram'] ?? ''),
    snapchat:  (string) ($socialData['snapchat']  ?? ''),
    telegram:  (string) ($socialData['telegram']  ?? ''),
    youtube:   (string) ($socialData['youtube']   ?? ''),
    maroof:    (string) ($socialData['maroof']    ?? ''),
    facebook:  (string) ($socialData['facebook']  ?? ''),
    tiktok:    (string) ($socialData['tiktok']    ?? ''),
);
```

---

## [MED-07] حالة فلتر status=out لا تعمل في زد بشكل كامل

**الملف:** ZidProvider.php — السطر 210-220

### المشكلة:
الكود يُرسل in_stock=false لزد عند status=out (صحيح في الفلترة)
لكن ProductData::fromZid() لا يُحسب "out" من quantity:
```php
$status = $isPublished ? 'sale' : (($rawProduct['is_draft'] ?? false) ? 'hidden' : 'sale');
// لا يُحسب "out" من quantity أبداً
```

النتيجة: منتجات out تُفلتر من زد بشكل صحيح، لكن status المُرجع يكون "sale" وليس "out".
الفرونت لا يعرف أنها out وقد يعرضها بشكل خاطئ.

---

## [MED-08] تكرار route واحدة برابطين مختلفين

**الملف:** routes/api.php — السطر 35-36

```php
// نفس الـ action برابطين مختلفين
Route::get('/store/profile',       [ProfileController::class, 'store']);
Route::get('/store/store-profile', [ProfileController::class, 'store']);
```

ليس خطأ تقنياً لكن يُسبب ازدواجية. يُنصح بالإبقاء على route واحدة.

---

## [LOW-01] getAttributes وcreateAttribute في Contract

**الملف:** PlatformProvider.php

دوال getAttributes وcreateAttribute وaddAttributePreset موجودة في PlatformService
لكن غير موجودة في الـ Interface، مما يُخالف مبدأ البرمجة بالعقود.

الحل: إضافة هذه الدوال للـ Interface أو إنشاء Interface منفصل ZidSpecificProvider.

---

## [LOW-02] htmlUrl في سلة: قراءة غير متسقة

**الملف:** ProductData.php — السطر 268

```php
htmlUrl: (string) ($rawProduct['urls']['customer'] ?? $rawProduct['url'] ?? ''),
```

في حال عدم وجود أي منهما يُرجع '' بدل null. يُفضل:
```php
htmlUrl: (string) ($rawProduct['urls']['customer'] ?? $rawProduct['url'] ?? ''),
// صحيح كـ fallback، لكن يجب أن يتحقق الفرونت من القيمة الفارغة
```

---

## [LOW-03] barcode في DTO: String إلزامي بدل Nullable

**الملف:** ProductData.php — السطر 17

```php
public string $barcode = '',
// الأفضل:
public ?string $barcode = null,
```

---

## ملخص المشاكل

| الشدة | العدد | الوصف |
|-------|-------|-------|
| حرجة (CRITICAL) | 5 | تُسبب فشل الفلترة والـ pagination بشكل كامل |
| عالية (HIGH) | 6 | تُسبب بيانات خاطئة في الداشبورد |
| متوسطة (MED) | 8 | تُسبب سلوكاً غير متوقع |
| منخفضة (LOW) | 3 | تحسينات وتوثيق |

---

## خطة الإصلاح المقترحة بالأولوية

### الأسبوع الأول — الحرجة:
1. إصلاح SallaProvider::getProducts() — pagination fields الصحيحة (current, count, next)
2. إصلاح ZidProvider::getProducts() — قراءة paging من root الاستجابة مباشرةً
3. تصحيح معامل البحث في زد من "search" إلى "q"
4. حذف المعاملات المكررة (category) في كلا المنصتين
5. إضافة header "Role: Manager" في buildZidHeaders()

### الأسبوع الثاني — العالية:
6. إصلاح منطق حالة المنتج "out of stock" في fromZid() و fromSalla()
7. إصلاح StoreSocialData في زد (telegram وyoutube وmaroof وwhatsapp)
8. توثيق الحقول الخاصة بكل منصة في DTO (null للحقول غير المدعومة)

### الأسبوع الثالث — المتوسطة:
9. إصلاح seoTitleEn وseoDescriptionEn في fromZid (null بدل "")
10. إضافة max(1, ...) في validatedFilters()
11. إرسال page_size كـ int صريح في زد

---

## ملفات تحتاج تعديلاً

| الملف | المشاكل | الأولوية |
|-------|---------|---------|
| ZidProvider.php | CRITICAL-02, 03, 04, HIGH-01, MED-03, 07 | حرجة |
| SallaProvider.php | CRITICAL-01, 05 | حرجة |
| ProductData.php | HIGH-02, 03, 04, MED-04, 07 | عالية |
| StoreProfileData.php | MED-05, MED-06 | متوسطة |
| ProductFilterRequest.php | MED-01 | متوسطة |
| PlatformProvider.php | LOW-01 | منخفضة |

---

ملاحظة: تم الفحص بناءً على التوثيق الرسمي لـ Salla API Admin v2 وZid API v1 مع مراجعة الكود مصدراً.
جميع المشاكل موثقة بأدلة من الاستجابات الحقيقية المتوقعة من المنصتين.

---

## [CRITICAL-06] fromZid: بنية Variants وOptions خاطئة تماماً (مكتشفة من التوثيق الرسمي)

**الملف:** ProductData.php — السطر 326-414  
**تاريخ الاكتشاف:** 2026-07-28 (من التوثيق الرسمي لزد)

### البنية الحقيقية لزد (من التوثيق الرسمي):

```json
{
  "options": [
    {
      "name": {"en": "Color", "ar": "اللون"},
      "slug": "color",
      "values": {"en": ["Red", "Blue"], "ar": ["أحمر", "أزرق"]}
    }
  ],
  "variants": [
    {
      "attributes": [
        {
          "name": {"en": "Color", "ar": "اللون"},
          "slug": "color",
          "value": {"en": "Red", "ar": "أحمر"}
        }
      ]
    }
  ]
}
```

### الأخطاء في الكود القديم:

#### في Variants (المتغيرات):

| الحقل | الكود القديم | البنية الحقيقية | الخطأ |
|-------|-------------|----------------|-------|
| attribute.name | (string)($a['name']) | {"en":"Color","ar":"اللون"} | PHP Warning: Array to string |
| attribute.id | $a['attribute_id'] ?? $a['id'] | غير موجود في البنية | دائماً '' |
| attribute.valueId | $a['id'] | غير موجود | دائماً '' |
| المعرف الصحيح | - | slug | $a['slug'] هو الصحيح |

#### في Options (الخيارات):

| الحقل | الكود القديم | البنية الحقيقية | الخطأ |
|-------|-------------|----------------|-------|
| values | $opt['choices'] ?? $opt['options'] | {"en":[...],"ar":[...]} | دائماً [] (فارغ!) |
| option.id | $opt['id'] | غير موجود في البنية | دائماً '' |
| المعرف الصحيح | - | slug | $opt['slug'] هو الصحيح |
| type | $opt['type'] ?? 'radio' | غير موجود | افتراضي خاطئ |

### النتيجة:
- customOptions كانت تُرجع دائماً [] (بلا خيارات) لأن 'choices'/'options' غير موجودة
- attributes كانت تُرجع name فارغة ويصدر PHP Warning
- displayName المتغير كان يظهر فارغاً

### تم الإصلاح:
- attribute.name: استخدام $a['name']['ar'] ?? $a['name']['en']
- attribute.id: استخدام $a['slug'] (المعرف الوحيد)
- option.values: قراءة من $opt['values']['ar'] و $opt['values']['en'] بالفهرس
- option.id: استخدام $opt['slug'] بدلاً من $opt['id']
- إضافة Fallback للبنى القديمة إذا وُجدت
