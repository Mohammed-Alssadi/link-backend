# خطة الهيكلية القياسية مع توضيح أنماط التصميم (Design Patterns Deep-Dive Plan)

بناءً على طلبك، حافظنا على **نفس الهيكلية المعمارية القياسية لـ Laravel**، وقدمنا توضيحاً تفصيلياً في هذا المستند لكل ملف: **ما هو اسم نمط التصميم (Design Pattern Name) المستخدم فيه، وكيف يتضح ويظهر عمل هذا النمط برمجياً داخل الكود**.

---

## 1. الشجرة التنظيمية وأنماط التصميم المرتبطة بها

```text
app/
├── Contracts/
│   └── PlatformProvider.php          <-- [Strategy Pattern Interface] العقد الموحد للاستراتيجية
├── Data/
│   ├── StoreProfileData.php          <-- [Data Transfer Object - DTO Pattern]
│   └── UserProfileData.php           <-- [Data Transfer Object - DTO Pattern]
├── Http/
│   └── Controllers/
│       └── Api/
│           ├── AuthController.php     <-- [Front Controller Pattern]
│           ├── OAuthController.php    <-- [Polymorphic Action Controller Pattern]
│           └── ProfileController.php  <-- [Polymorphic Action Controller Pattern]
├── Services/
│   └── Platforms/
│       ├── SallaProvider.php         <-- [Concrete Strategy Pattern] تطبيق استراتيجية سلة
│       ├── ZidProvider.php           <-- [Concrete Strategy Pattern] تطبيق استراتيجية زد
│       └── PlatformManager.php       <-- [Manager / Factory Method Pattern] مصنع ومدير المنصات
└── Services/
    └── PlatformService.php           <-- [API Gateway / Service Layer Pattern] الخدمة المركزية والبوابة
```

---

## 2. تفصيل كل ملف: النمط المستخدم وكيف يتضح عمله برمجياً

---

### 🔷 1. الملف: `app/Contracts/PlatformProvider.php`

- **اسم نمط التصميم**: **Strategy Pattern (Interface / Contract)**
- **كيف يتضح عمل النمط برمجياً**:
  - يُنشئ هذا الملف واجهة برمجة (`Interface`) تفصل **"ماذا يجب أن تفعل المنصة"** عن **"كيف تفعل المنصة ذلك"**.
  - تجبر هذه الواجهة جميع كلاسات المنصات (سلة، زد، Shopify) على تقديم نفس الميثودز بنفس التواقيع (`getAuthUrl`, `handleCallback`, `refreshToken`, `getUserProfile`, `getStoreProfile`).
  - **النتيجة**: يستطيع النظام استدعاء أي منصة عبر هذه الواجهة الموحدة دون الحاجة لمعرفة تفاصيل الكود الداخلي لـ Salla أو Zid.

---

### 🔷 2. الملف: `app/Services/Platforms/SallaProvider.php`

- **اسم نمط التصميم**: **Concrete Strategy Pattern** (تطبيق استراتيجية سلة)
- **كيف يتضح عمل النمط برمجياً**:
  - يمثل هذا الكلاس التطبيق الفعلي والمحدد لاستراتيجية التعامل مع **منصة سلة** (`implements PlatformProvider`).
  - يحتوي داخله على طلبات الـ HTTP المباشرة لروابط endpoints سلة (`https://accounts.salla.sa`, `https://api.salla.dev/admin/v2`).
  - عند استدعاء `getUserProfile` ينفذ طلب سلة وياخذ الـ JSON المرجع ويمرره فوراً لـ `UserProfileData::fromSalla($json)`.

---

### 🔷 3. الملف: `app/Services/Platforms/ZidProvider.php`

- **اسم نمط التصميم**: **Concrete Strategy Pattern** (تطبيق استراتيجية زد)
- **كيف يتضح عمل النمط برمجياً**:
  - يمثل هذا الكلاس التطبيق الفعلي والمحدد لاستراتيجية التعامل مع **منصة زد** (`implements PlatformProvider`).
  - يحتوي داخله على طلبات الـ HTTP المباشرة لروابط زد (`https://api.zid.sa/v1/...`) باستخدام الترويسات الخاصة بزد (`Authorization`, `X-Manager-Token`).
  - يُنفذ 5 طلبات متوازية عبر `Http::pool` لجمع معلومات متجر زد وتمرير الناتج المجمع لـ `StoreProfileData::fromZid(...)`.

---

### 🔷 4. الملف: `app/Services/Platforms/PlatformManager.php`

- **اسم نمط التصميم**: **Manager Pattern / Factory Method Pattern**
- **كيف يتضح عمل النمط برمجياً**:
  - يتولى هذا الملف دور **المصنع والمدير الديناميكي** (المتبع في لارفل مثل `SocialiteManager` و `AuthManager`).
  - بدلاً من كتابة شروط `if ($platform === 'salla')` في كل مكان، تحوي الميثود `driver($platform)` منطق إنشاء أو جلب الكلاس المناسب (`SallaProvider` أو `ZidProvider`) عبر الـ `Container` الخاص بـ Laravel.
  - **النتيجة**: يلغي تكرار الشروط نهائياً من الكنترولرات والخدمات الأخرى.

---

### 🔷 5. الملف: `app/Services/PlatformService.php`

- **اسم نمط التصميم**: **API Gateway Pattern / Service Layer Pattern**
- **كيف يتضح عمل النمط برمجياً**:
  - يعمل كبوابة وسيطة ورئيسية بين الكنترولرات ومصنع المنصات.
  - يتولى إدارة دورة حياة الجلسة: تفقد توكن التاجر، والتحقق مما إذا كان توكن المنصة قد انتهى أو قارب على الانتهاء لتنفيذ التجديد التلقائي (**Auto-Refresh Strategy**) مسبقاً قبل الطلب.
  - يستدعي `PlatformManager` للوصول إلى الاستراتيجية المناسبة، ثم يعيد الـ DTO الموحد إلى المتحكم.

---

### 🔷 6. الكلاسات: `app/Data/UserProfileData.php` و `app/Data/StoreProfileData.php`

- **اسم نمط التصميم**: **Data Transfer Object (DTO) Pattern**
- **كيف يتضح عمل النمط برمجياً**:
  - كلاسات مبنية باستخدام مكتبة `Spatie\LaravelData\Data`.
  - تقوم بتوحيد وتحويل الهياكل المختلفة المرجعة من سلة وزد إلى كائن موحد وثابت يحتوي على نفس الخصائص والأنواع (`id`, `name`, `email`, `avatar`...) بغض النظر عن المنصة المصدر، ثم تخرج كـ JSON منظم للفرونت اند.

---

### 🔷 7. المتحكمات: `OAuthController.php` و `ProfileController.php`

- **اسم نمط التصميم**: **Polymorphic Controller / Thin Action Controller Pattern**
- **كيف يتضح عمل النمط برمجياً**:
  - متحكمات ديناميكية ونحيفة (Thin Controllers).
  - تُستغل المسارات الديناميكية (Route Parameters مثل `{platform}`)، وتقوم باستقبال الطلب وتفويضه مباشرة لـ `PlatformService` دون احتواء أي كود أعمال أو شروط منطقية (Business Logic) داخل المتحكم.

---

## 3. ملخص حركة البيانات عبر الأنماط (Data Flow Across Patterns)

```text
Request: GET /v1/user/profile
   │
   ▼
ProfileController [Thin Action Controller Pattern]
   │
   ▼
PlatformService [API Gateway Pattern]
   ├── 1. يفحص توكن المستخدم واقتراب انتهائه (Auto-Refresh).
   ├── 2. يطلب المزود من PlatformManager.
   ▼
PlatformManager [Manager / Factory Pattern]
   ├── ينشئ الكلاس المناسب ديناميكياً (SallaProvider / ZidProvider)
   ▼
SallaProvider / ZidProvider [Concrete Strategy Pattern]
   ├── ينفذ طلب HTTP Mappings لـ APIs المنصة
   ▼
UserProfileData DTO [Data Transfer Object Pattern]
   ├── يحول النتيجة لـ JSON قياسي وموحد
   ▼
Response sent to FrontEnd
```

---

## 4. قائمة الملفات وتحديثاتها (Files Checklist)

### 🟢 ملفات جديدة (NEW):
1. `app/Contracts/PlatformProvider.php`
2. `app/Services/Platforms/SallaProvider.php`
3. `app/Services/Platforms/ZidProvider.php`
4. `app/Services/Platforms/PlatformManager.php`
5. `app/Services/PlatformService.php`
6. `app/Http/Controllers/Api/OAuthController.php`
7. `app/Http/Controllers/Api/ProfileController.php`

### 🗑️ ملفات سيتم حذفها (DELETE):
1. `app/Http/Controllers/Api/SallaAuthApiController.php`
2. `app/Http/Controllers/Api/ZidAuthApiController.php`
3. `app/Http/Controllers/Api/StoreProfileController.php`
4. `app/Http/Controllers/Api/UserProfileController.php`
5. `app/Services/SallaService.php`
6. `app/Services/ZidService.php`
7. `app/Services/StoreProfileService.php`
8. `app/Services/UserProfileService.php`

### ✏️ مسارات سيتم تعديلها (MODIFY):
1. `routes/api.php`
