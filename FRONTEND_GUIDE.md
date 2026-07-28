# دليل الفرونت اند الشامل — التعامل مع المنتجات (سلة وزد)

> الإصدار: 1.0.0 | تاريخ الإصدار: 2026-07-28
> المنصات: سلة (Salla) | زد (Zid)
> الباك اند: Laravel + Spatie Data DTO موحد

---

## الفهرس

1. مقدمة
2. بنية الـ API الموحدة
3. بنية بيانات المنتج الموحدة
4. جدول التوافق
5. الـ Pagination
6. الفلترة والبحث
7. عرض قائمة المنتجات
8. عرض تفاصيل المنتج
9. المتغيرات والخيارات
10. تعديل المنتج
11. حالات المنتج
12. الصور والمخزون
13. TypeScript Types
14. Hook كامل
15. قواعد ذهبية

---

## 1. مقدمة

النظام يدعم منصتين: سلة وزد. كل تاجر مربوط بمنصة واحدة.
الباك اند يحول بيانات كل منصة الى DTO موحد واحد قبل ارسالها للفرونت.

```
سلة API  > SallaProvider  > ProductData (موحد) > الفرونت
زد API   > ZidProvider    > ProductData (موحد) > الفرونت
```

### المبدأ الاساسي

```typescript
// خاطئ — لا تفرق منطق الرندر بالمنصة
if (platform === 'salla') { renderSallaProduct() }
if (platform === 'zid')   { renderZidProduct() }

// صحيح — تعامل مع البيانات الموحدة
// استخدم platform فقط لاخفاء/اظهار حقول بناء على null
const name   = product.nameAr;               // دائما موجود
const nameEn = product.nameEn;               // null في سلة
if (nameEn !== null) { showEnglishField(); } // اعرض فقط اذا كان موجودا
```

---

## 2. بنية الـ API الموحدة

### Base URL
```
https://your-backend.com/api/v1
```

### Authentication
```typescript
const headers = {
  'Authorization': `Bearer ${sanctumToken}`,
  'Content-Type': 'application/json',
  'Accept': 'application/json',
};
```

### Endpoints

| Method | Endpoint | الوصف |
|--------|----------|-------|
| GET | /api/v1/products | قائمة المنتجات + فلترة + pagination |
| GET | /api/v1/products/{id} | تفاصيل منتج |
| PUT | /api/v1/products/{id} | تعديل منتج |
| DELETE | /api/v1/products/{id} | حذف منتج |
| GET | /api/v1/categories | التصنيفات |
| GET | /api/v1/attributes | السمات (زد فقط) |
| GET | /api/v1/store/profile | بيانات المتجر |
| GET | /api/v1/user/profile | بيانات التاجر |
| GET | /api/v1/me | معلومات الحساب + المنصة |
| POST | /api/v1/logout | تسجيل الخروج |

---

## 3. بنية بيانات المنتج الموحدة

```json
{
  "id": "123456",
  "platform": "salla",
  "nameAr": "قميص قطني",
  "nameEn": null,
  "descriptionAr": "<p>وصف المنتج</p>",
  "descriptionEn": null,
  "shortDescriptionAr": "وصف قصير",
  "shortDescriptionEn": null,
  "sku": "SHIRT-001",
  "barcode": "6281234567890",
  "mpn": null,
  "gtin": null,
  "price": 150.00,
  "salePrice": 120.00,
  "costPrice": 80.00,
  "isDiscountActive": true,
  "discountStart": "2026-07-01",
  "discountEnd": "2026-07-31",
  "formattedPrice": "150.00 SAR",
  "formattedSalePrice": "120.00 SAR",
  "scopedPrices": null,
  "quantity": 45,
  "isUnlimited": false,
  "status": "sale",
  "isPublished": true,
  "weight": 0.5,
  "weightType": "kg",
  "requiresShipping": true,
  "isTaxable": false,
  "minOrderQuantity": 1,
  "maxOrderQuantity": 10,
  "maxItemsPerUser": null,
  "structure": "parent",
  "categories": [
    { "id": "cat_1", "name": "ملابس" }
  ],
  "images": [
    { "id": "img_1", "url": "https://...", "isMain": true },
    { "id": "img_2", "url": "https://...", "isMain": false }
  ],
  "stocks": [
    {
      "locationId": "loc_1",
      "locationName": "المستودع الرئيسي",
      "quantity": 45,
      "isUnlimited": false
    }
  ],
  "variants": [
    {
      "id": "var_1",
      "sku": "SHIRT-RED-L",
      "barcode": null,
      "mpn": null,
      "gtin": null,
      "price": 150.00,
      "salePrice": null,
      "costPrice": null,
      "quantity": 20,
      "isUnlimited": false,
      "weight": 0.5,
      "displayName": "اللون: احمر / الحجم: كبير",
      "formattedPrice": "150.00 SAR",
      "formattedSalePrice": null,
      "attributes": [
        { "id": "color", "valueId": null, "name": "اللون", "value": "احمر" },
        { "id": "size",  "valueId": null, "name": "الحجم", "value": "كبير" }
      ],
      "stocks": []
    }
  ],
  "customOptions": [
    {
      "id": "color",
      "type": "select",
      "label": "اللون",
      "isRequired": true,
      "choices": [
        { "id": "0", "label": "احمر" },
        { "id": "1", "label": "ازرق" }
      ]
    }
  ],
  "seoTitleAr": "قميص قطني فاخر",
  "seoTitleEn": null,
  "seoDescriptionAr": "وصف SEO بالعربية",
  "seoDescriptionEn": null,
  "seoSlug": "cotton-shirt",
  "keywords": ["قميص", "ملابس"],
  "htmlUrl": "https://store.salla.sa/products/cotton-shirt",
  "badge": null,
  "metafields": null,
  "productClass": "product"
}
```

---

## 4. جدول التوافق

الرموز:
- موجود = حقل مملوء
- null = الباك اند يرجع null دائما

| الحقل | سلة | زد | ملاحظة |
|-------|-----|-----|-------|
| id | موجود | موجود | |
| platform | 'salla' | 'zid' | دائما موجود |
| nameAr | موجود | موجود | المصدر الاساسي دائما |
| nameEn | NULL | موجود | سلة عربية فقط |
| descriptionAr | موجود | موجود | |
| descriptionEn | NULL | موجود | سلة عربية فقط |
| shortDescriptionAr | موجود | موجود | |
| shortDescriptionEn | NULL | موجود | |
| sku | موجود | موجود | |
| barcode | موجود | موجود | |
| mpn | موجود | NULL | سلة فقط |
| gtin | موجود | NULL | سلة فقط |
| price | موجود | موجود | |
| salePrice | موجود | موجود | |
| costPrice | موجود | موجود | |
| isDiscountActive | موجود | موجود | |
| discountStart | موجود | موجود | |
| discountEnd | موجود | موجود | |
| formattedPrice | موجود | موجود | "150.00 SAR" |
| scopedPrices | موجود | NULL | اسعار متعددة سلة فقط |
| quantity | موجود | موجود | |
| isUnlimited | موجود | موجود | |
| stocks | موجود | موجود | |
| status | موجود | موجود | sale/hidden/out |
| isPublished | موجود | موجود | |
| weight | موجود | موجود | |
| requiresShipping | موجود | موجود | |
| isTaxable | موجود | موجود | |
| minOrderQuantity | موجود | موجود | |
| maxOrderQuantity | موجود | موجود | |
| maxItemsPerUser | موجود | NULL | سلة فقط |
| structure | موجود | موجود | standalone/parent |
| variants | موجود | موجود | |
| customOptions | موجود | موجود | |
| categories | موجود | موجود | |
| images | موجود | موجود | |
| seoTitleAr | موجود | موجود | |
| seoTitleEn | NULL | موجود | سلة عربية فقط |
| seoDescriptionAr | موجود | موجود | |
| seoDescriptionEn | NULL | موجود | |
| seoSlug | موجود | موجود | |
| keywords | موجود | موجود | |
| htmlUrl | موجود | موجود | |
| badge | NULL | موجود | زد فقط |
| metafields | NULL | موجود | زد فقط |
| productClass | موجود | موجود | |

### حقول المتغيرات:

| الحقل | سلة | زد | ملاحظة |
|-------|-----|-----|-------|
| attribute.id | option_id | slug | نوع مختلف |
| attribute.valueId | موجود | NULL | سلة فقط |
| attribute.name | موجود | موجود | |
| attribute.value | موجود | موجود | |
| variant.mpn | موجود | NULL | |
| variant.gtin | موجود | NULL | |
| variant.displayName | موجود | موجود | جاهز من الباك اند |

### حقول الخيارات:

| الحقل | سلة | زد |
|-------|-----|-----|
| option.id | رقمي | slug |
| option.choices[].id | رقمي | index رقمي |
| option.type | موجود | موجود |

---

## 5. الـ Pagination

### بنية الاستجابة

```json
{
  "success": true,
  "data": [ "...المنتجات..." ],
  "pagination": {
    "currentPage": 1,
    "totalPages": 10,
    "totalCount": 150,
    "perPage": 15,
    "hasNext": true,
    "hasPrev": false
  }
}
```

ملاحظة مهمة:
سلة لا ترجع totalCount الحقيقي — الباك اند يقدره.
اعتمد على hasNext و hasPrev للتنقل دائما، وليس على totalPages وحده.

```typescript
// الطريقة الصحيحة
const canGoNext = pagination.hasNext;
const canGoPrev = pagination.hasPrev;

// الطريقة الخاطئة (تعمل فقط في زد)
const canGoNext = pagination.currentPage < pagination.totalPages;
```

### دالة جلب المنتجات

```typescript
interface ProductFilters {
  page?: number;
  limit?: number;
  search?: string;
  category_id?: string;
  status?: 'sale' | 'hidden' | 'out';
}

async function fetchProducts(filters: ProductFilters = {}): Promise<ProductListResponse> {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([k, v]) => {
    if (v !== null && v !== undefined && v !== '') params.set(k, String(v));
  });
  const res = await fetch(`/api/v1/products?${params}`, { headers });
  if (!res.ok) throw new Error(`HTTP Error: ${res.status}`);
  return res.json();
}
```

### كومبوننت Pagination

```typescript
function PaginationControls({ pagination, onPageChange }) {
  const { currentPage, totalPages, hasNext, hasPrev, totalCount } = pagination;
  return (
    <div className="pagination-controls">
      <button disabled={!hasPrev} onClick={() => onPageChange(currentPage - 1)}>
        السابق
      </button>
      <span>صفحة {currentPage} من {totalPages} ({totalCount} منتج)</span>
      <button disabled={!hasNext} onClick={() => onPageChange(currentPage + 1)}>
        التالي
      </button>
    </div>
  );
}
```

---

## 6. الفلترة والبحث

### Query Parameters

| Parameter | النوع | القيم | الوصف |
|-----------|-------|-------|-------|
| page | integer | min: 1 | رقم الصفحة |
| limit | integer | 1-100 | عدد النتائج |
| search | string | اي نص | البحث في الاسم |
| category_id | string | ID التصنيف | فلتر بالتصنيف |
| status | string | sale/hidden/out | فلتر بالحالة |

### امثلة

```
# الصفحة الاولى
GET /api/v1/products?page=1&limit=15

# بحث
GET /api/v1/products?search=احذية

# منتجات نافد مخزونها
GET /api/v1/products?status=out

# بحث + فلتر + صفحة ثانية
GET /api/v1/products?search=قميص&status=sale&page=2&limit=20
```

ملاحظة: عند تغيير اي فلتر — اعد page الى 1 دائما.

### كومبوننت الفلترة

```typescript
function FilterBar({ onFilterChange }) {
  const [filters, setFilters] = useState({
    search: '', status: '', category_id: ''
  });

  const update = (key: string, value: string) => {
    const next = { ...filters, [key]: value };
    setFilters(next);
    onFilterChange({ ...next, page: 1 }); // اعادة للصفحة 1 دائما
  };

  return (
    <div className="filter-bar">
      <input
        type="search"
        placeholder="ابحث عن منتج..."
        value={filters.search}
        onChange={e => update('search', e.target.value)}
      />
      <select value={filters.status} onChange={e => update('status', e.target.value)}>
        <option value="">جميع الحالات</option>
        <option value="sale">نشط</option>
        <option value="hidden">مخفي</option>
        <option value="out">نفد المخزون</option>
      </select>
    </div>
  );
}
```

---

## 7. عرض قائمة المنتجات

```typescript
function ProductCard({ product }) {
  const mainImg = product.images.find(i => i.isMain) ?? product.images[0] ?? null;
  const hasDiscount = product.isDiscountActive && product.salePrice !== null;

  return (
    <div className={`product-card ${product.status === 'out' ? 'out-of-stock' : ''}`}>

      {/* البادج — زد فقط، null في سلة */}
      {product.badge && (
        <span className="badge">{product.badge.label}</span>
      )}

      {/* الصورة مع fallback */}
      <img
        src={mainImg?.url ?? '/images/placeholder.png'}
        alt={product.nameAr}
        loading="lazy"
      />

      {/* الاسم — دائما nameAr */}
      <h3>{product.nameAr}</h3>

      {/* التصنيف */}
      {product.categories[0] && (
        <span className="category">{product.categories[0].name}</span>
      )}

      {/* السعر */}
      <div className="price">
        {hasDiscount ? (
          <>
            <span className="sale">{product.formattedSalePrice}</span>
            <span className="original line-through">{product.formattedPrice}</span>
            <span className="discount-pct">
              {Math.round((1 - product.salePrice / product.price) * 100)}% خصم
            </span>
          </>
        ) : (
          <span>{product.formattedPrice}</span>
        )}
      </div>

      {/* المخزون */}
      <div className="stock">
        {product.isUnlimited
          ? 'مخزون غير محدود'
          : `${product.quantity} قطعة`}
      </div>

      {/* الحالة */}
      <StatusBadge status={product.status} />

      {/* المتغيرات */}
      {product.structure === 'parent' && product.variants.length > 0 && (
        <span className="variants-count">{product.variants.length} متغير</span>
      )}
    </div>
  );
}

function StatusBadge({ status }) {
  const map = {
    sale:   { label: 'نشط',         color: 'green' },
    hidden: { label: 'مخفي',        color: 'gray'  },
    out:    { label: 'نفد المخزون', color: 'red'   },
  };
  const s = map[status] ?? map.sale;
  return <span className={`status-badge ${s.color}`}>{s.label}</span>;
}
```

---

## 8. عرض تفاصيل المنتج

```typescript
function ProductDetailPage({ productId }) {
  const [product, setProduct] = useState(null);

  useEffect(() => {
    fetch(`/api/v1/products/${productId}`, { headers })
      .then(r => r.json())
      .then(d => setProduct(d.data));
  }, [productId]);

  if (!product) return <Spinner />;

  const hasVariants = product.variants.length > 0;
  const hasOptions  = (product.customOptions?.length ?? 0) > 0;

  return (
    <div className="product-detail">

      {/* الصور */}
      <ProductGallery images={product.images} />

      {/* الهيدر */}
      <section>
        <span className="platform">
          {product.platform === 'salla' ? 'سلة' : 'زد'}
        </span>
        <h1>{product.nameAr}</h1>

        {/* الاسم الانجليزي — فقط اذا كان موجودا (زد) */}
        {product.nameEn && (
          <p dir="ltr" className="name-en">{product.nameEn}</p>
        )}

        <StatusBadge status={product.status} />

        {product.htmlUrl && (
          <a href={product.htmlUrl} target="_blank">عرض في المتجر</a>
        )}
      </section>

      {/* السعر */}
      <section className="pricing">
        {product.isDiscountActive && product.salePrice ? (
          <>
            <span className="sale-price">{product.formattedSalePrice}</span>
            <span className="original-price">{product.formattedPrice}</span>
            {product.discountStart && (
              <p>الخصم من {product.discountStart} حتى {product.discountEnd}</p>
            )}
          </>
        ) : (
          <span className="price">{product.formattedPrice}</span>
        )}
        {product.costPrice && (
          <p className="cost">سعر التكلفة: {product.costPrice} SAR</p>
        )}
      </section>

      {/* المخزون */}
      <StockDisplay product={product} />

      {/* الوصف */}
      <section>
        <h2>الوصف</h2>
        <div dangerouslySetInnerHTML={{ __html: product.descriptionAr }} />
        {product.descriptionEn && (
          <div dir="ltr">
            <h3>Description (EN)</h3>
            <div dangerouslySetInnerHTML={{ __html: product.descriptionEn }} />
          </div>
        )}
      </section>

      {/* الخيارات المتاحة */}
      {hasOptions && (
        <section>
          <h2>الخيارات المتاحة</h2>
          <OptionsDisplay options={product.customOptions} />
        </section>
      )}

      {/* المتغيرات */}
      {hasVariants && (
        <section>
          <h2>المتغيرات ({product.variants.length})</h2>
          <VariantsTable variants={product.variants} />
        </section>
      )}

      {/* التصنيفات */}
      {product.categories.length > 0 && (
        <section>
          <h2>التصنيفات</h2>
          {product.categories.map(c => (
            <span key={c.id} className="tag">{c.name}</span>
          ))}
        </section>
      )}

      {/* المعلومات التقنية */}
      <section>
        <h2>المعلومات التقنية</h2>
        <table>
          <tbody>
            {product.sku     && <tr><td>SKU</td><td>{product.sku}</td></tr>}
            {product.barcode && <tr><td>الباركود</td><td>{product.barcode}</td></tr>}
            {product.mpn     && <tr><td>MPN</td><td>{product.mpn}</td></tr>}
            {product.gtin    && <tr><td>GTIN</td><td>{product.gtin}</td></tr>}
            <tr><td>الوزن</td><td>{product.weight} {product.weightType}</td></tr>
            <tr><td>يتطلب شحن</td><td>{product.requiresShipping ? 'نعم' : 'لا'}</td></tr>
            <tr><td>خاضع للضريبة</td><td>{product.isTaxable ? 'نعم' : 'لا'}</td></tr>
            {product.minOrderQuantity && <tr><td>حد ادنى للطلب</td><td>{product.minOrderQuantity}</td></tr>}
            {product.maxOrderQuantity && <tr><td>حد اقصى للطلب</td><td>{product.maxOrderQuantity}</td></tr>}
            {product.maxItemsPerUser  && <tr><td>الحد لكل مستخدم</td><td>{product.maxItemsPerUser}</td></tr>}
          </tbody>
        </table>
      </section>

      {/* مخزون الفروع */}
      {product.stocks.length > 0 && (
        <section>
          <h2>المخزون بالمستودعات</h2>
          <StocksTable stocks={product.stocks} />
        </section>
      )}

      {/* SEO */}
      <section>
        <h2>اعدادات SEO</h2>
        <p>العنوان: {product.seoTitleAr}</p>
        {product.seoTitleEn && <p dir="ltr">Title EN: {product.seoTitleEn}</p>}
        <p>الوصف: {product.seoDescriptionAr}</p>
        {product.seoDescriptionEn && <p dir="ltr">Desc EN: {product.seoDescriptionEn}</p>}
        <p>الرابط: /{product.seoSlug}</p>
        {product.keywords.length > 0 && (
          <p>الكلمات المفتاحية: {product.keywords.join('، ')}</p>
        )}
      </section>

      {/* حقول زد الخاصة */}
      {product.badge && (
        <section>
          <h2>بادج المنتج</h2>
          <span className="badge">{product.badge.label}</span>
        </section>
      )}
    </div>
  );
}
```

---

## 9. المتغيرات والخيارات

### فهم البنية

```
منتج بسيط (structure = 'standalone'):
  variants = []
  customOptions = []

منتج بمتغيرات (structure = 'parent'):
  variants     = [متغير احمر كبير, متغير ازرق صغير, ...]
  customOptions = [{ label: 'اللون', choices: [...] }, ...]
```

### جدول المتغيرات

```typescript
function VariantsTable({ variants }) {
  return (
    <table>
      <thead>
        <tr>
          <th>المتغير</th>
          <th>SKU</th>
          <th>السعر</th>
          <th>الكمية</th>
          <th>الحالة</th>
        </tr>
      </thead>
      <tbody>
        {variants.map(v => (
          <tr key={v.id}>
            {/* displayName جاهز: "اللون: احمر / الحجم: كبير" */}
            <td>{v.displayName}</td>
            <td>{v.sku}</td>
            <td>
              {v.salePrice ? (
                <>
                  <span className="text-success">{v.formattedSalePrice}</span>
                  <span className="line-through text-muted">{v.formattedPrice}</span>
                </>
              ) : v.formattedPrice}
            </td>
            <td>
              {v.isUnlimited ? 'غير محدود' : v.quantity}
            </td>
            <td>
              {(v.quantity === 0 && !v.isUnlimited)
                ? <span className="text-danger">نفد</span>
                : <span className="text-success">متاح</span>}
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}
```

### عرض الخيارات

```typescript
function OptionsDisplay({ options }) {
  return (
    <div className="options-grid">
      {options.map(option => (
        <div key={option.id} className="option-group">
          <div className="option-label">
            <strong>{option.label}</strong>
            {option.isRequired && <span className="required">*</span>}
          </div>
          <div className="choices">
            {option.choices.map(choice => (
              <span key={choice.id} className="choice-chip">
                {choice.label}
              </span>
            ))}
          </div>
          <small className="type-info">النوع: {option.type}</small>
        </div>
      ))}
    </div>
  );
}
```

---

## 10. تعديل المنتج

```typescript
function EditProductForm({ product, onSuccess }) {
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({
    nameAr:          product.nameAr,
    nameEn:          product.nameEn ?? '',
    descriptionAr:   product.descriptionAr,
    descriptionEn:   product.descriptionEn ?? '',
    price:           product.price,
    salePrice:       product.salePrice ?? '',
    // لا ترسل 'out' — يحدد تلقائيا من الكمية
    status:          product.status === 'out' ? 'sale' : product.status,
    quantity:        product.quantity,
    isUnlimited:     product.isUnlimited,
    sku:             product.sku,
    barcode:         product.barcode,
    seoTitleAr:      product.seoTitleAr,
    seoTitleEn:      product.seoTitleEn ?? '',
    seoDescriptionAr: product.seoDescriptionAr,
    seoDescriptionEn: product.seoDescriptionEn ?? '',
  });

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      const payload = {
        nameAr:        form.nameAr,
        descriptionAr: form.descriptionAr,
        price:         Number(form.price),
        salePrice:     form.salePrice !== '' ? Number(form.salePrice) : null,
        status:        form.status,
        quantity:      Number(form.quantity),
        isUnlimited:   form.isUnlimited,
        sku:           form.sku,
        barcode:       form.barcode,
        seoTitleAr:    form.seoTitleAr,
        seoDescriptionAr: form.seoDescriptionAr,
      };

      // حقول زد الاضافية — فقط اذا كانت موجودة اصلا
      if (product.nameEn !== null)           payload.nameEn           = form.nameEn || null;
      if (product.descriptionEn !== null)    payload.descriptionEn    = form.descriptionEn || null;
      if (product.seoTitleEn !== null)       payload.seoTitleEn       = form.seoTitleEn || null;
      if (product.seoDescriptionEn !== null) payload.seoDescriptionEn = form.seoDescriptionEn || null;

      const res = await fetch(`/api/v1/products/${product.id}`, {
        method: 'PUT',
        headers,
        body: JSON.stringify(payload),
      });
      if (!res.ok) throw new Error('فشل الحفظ');
      onSuccess();
    } finally {
      setSaving(false);
    }
  };

  const set = (key, val) => setForm(f => ({ ...f, [key]: val }));

  return (
    <form onSubmit={handleSubmit}>

      {/* الاسم العربي — كلا المنصتين */}
      <div className="form-group">
        <label>الاسم بالعربية *</label>
        <input value={form.nameAr} onChange={e => set('nameAr', e.target.value)} required />
      </div>

      {/* الاسم الانجليزي — زد فقط */}
      {product.nameEn !== null && (
        <div className="form-group">
          <label>الاسم بالانجليزية <span className="badge-zid">زد فقط</span></label>
          <input dir="ltr" value={form.nameEn} onChange={e => set('nameEn', e.target.value)} />
        </div>
      )}

      {/* السعر */}
      <div className="form-row">
        <div className="form-group">
          <label>السعر *</label>
          <input type="number" min="0" step="0.01"
            value={form.price} onChange={e => set('price', e.target.value)} required />
        </div>
        <div className="form-group">
          <label>سعر الخصم</label>
          <input type="number" min="0" step="0.01"
            value={form.salePrice} onChange={e => set('salePrice', e.target.value)}
            placeholder="فارغ = لا يوجد خصم" />
        </div>
      </div>

      {/* الحالة — لا تضف 'out' */}
      <div className="form-group">
        <label>الحالة</label>
        <select value={form.status} onChange={e => set('status', e.target.value)}>
          <option value="sale">نشط</option>
          <option value="hidden">مخفي</option>
        </select>
        {product.status === 'out' && (
          <p className="text-warning">
            المنتج نافد المخزون — سيتغير تلقائيا اذا الكمية صفر
          </p>
        )}
      </div>

      {/* المخزون */}
      <div className="form-group">
        <label>
          <input type="checkbox"
            checked={form.isUnlimited}
            onChange={e => set('isUnlimited', e.target.checked)} />
          مخزون غير محدود
        </label>
        {!form.isUnlimited && (
          <input type="number" min="0"
            value={form.quantity} onChange={e => set('quantity', e.target.value)} />
        )}
      </div>

      {/* SEO */}
      <div className="form-group">
        <label>عنوان SEO</label>
        <input value={form.seoTitleAr} onChange={e => set('seoTitleAr', e.target.value)} />
      </div>

      {product.seoTitleEn !== null && (
        <div className="form-group">
          <label>SEO Title (EN) <span className="badge-zid">زد فقط</span></label>
          <input dir="ltr" value={form.seoTitleEn} onChange={e => set('seoTitleEn', e.target.value)} />
        </div>
      )}

      {/* حقول سلة الخاصة */}
      {product.mpn && (
        <div className="form-group">
          <label>MPN <span className="badge-salla">سلة فقط</span></label>
          <input value={product.mpn} readOnly className="readonly" />
        </div>
      )}

      <button type="submit" disabled={saving}>
        {saving ? 'جاري الحفظ...' : 'حفظ التعديلات'}
      </button>
    </form>
  );
}
```

---

## 11. حالات المنتج

| القيمة | المعنى | كيف يحدد |
|--------|--------|---------|
| 'sale' | نشط للبيع | منشور + مخزون متاح او غير محدود |
| 'hidden' | مخفي | غير منشور |
| 'out' | نفد المخزون | منشور + الكمية = 0 + ليس infinite |

### قاعدة الاولوية

```
hidden > out > sale

if (!isPublished)               → 'hidden'
if (quantity <= 0 && !infinite) → 'out'
else                            → 'sale'
```

### ملاحظة للفرونت

```typescript
// الفرونت يرسل فقط:
const validStatuses = ['sale', 'hidden'];

// لا ترسل 'out' — يحدد تلقائيا من الكمية
// اذا quantity = 0 والمنتج منشور سيصبح 'out' تلقائيا
```

---

## 12. الصور والمخزون

### عرض الصور

```typescript
function ProductGallery({ images }) {
  const mainImage = images.find(i => i.isMain) ?? images[0];
  const [selected, setSelected] = useState(mainImage);

  if (images.length === 0) {
    return <img src="/placeholder.png" alt="لا توجد صورة" />;
  }

  return (
    <div className="gallery">
      <div className="main-image">
        <img src={selected?.url} alt="المنتج" />
      </div>
      {images.length > 1 && (
        <div className="thumbnails">
          {images.map(img => (
            <img
              key={img.id}
              src={img.url}
              className={img === selected ? 'active' : ''}
              onClick={() => setSelected(img)}
            />
          ))}
        </div>
      )}
    </div>
  );
}
```

### عرض المخزون

```typescript
function StockDisplay({ product }) {
  if (product.isUnlimited) {
    return <span className="stock">مخزون غير محدود</span>;
  }

  if (product.status === 'out') {
    return <span className="stock out">نفد المخزون</span>;
  }

  if (product.stocks.length > 0) {
    return (
      <div className="stock-breakdown">
        {product.stocks.map(s => (
          <div key={s.locationId}>
            <span>{s.locationName}</span>
            <span>{s.isUnlimited ? 'غير محدود' : `${s.quantity} قطعة`}</span>
          </div>
        ))}
        <strong>الاجمالي: {product.quantity}</strong>
      </div>
    );
  }

  return <span className="stock">{product.quantity} قطعة متاحة</span>;
}
```

---

## 13. TypeScript Types

```typescript
// src/types/platform.ts

export type Platform        = 'salla' | 'zid';
export type ProductStatus   = 'sale' | 'hidden' | 'out';
export type ProductStructure = 'standalone' | 'parent';

export interface ProductAttributeData {
  id: string | null;       // option slug او attribute_id
  valueId: string | null;  // سلة فقط، null في زد
  name: string;
  value: string;
}

export interface ProductCustomOptionData {
  id: string;
  type: string;            // 'select' | 'radio' | 'text' ...
  label: string;
  isRequired: boolean;
  choices: Array<{ id: string; label: string }>;
}

export interface ProductCategoryData {
  id: string;
  name: string;
}

export interface ProductImageData {
  id: string;
  url: string;
  isMain: boolean;
}

export interface ProductLocationStockData {
  locationId: string;
  locationName: string;
  quantity: number;
  isUnlimited: boolean;
}

export interface ProductVariantData {
  id: string;
  sku: string;
  barcode: string | null;
  mpn: string | null;       // سلة فقط
  gtin: string | null;      // سلة فقط
  price: number;
  salePrice: number | null;
  costPrice: number | null;
  quantity: number;
  isUnlimited: boolean;
  weight: number | null;
  displayName: string;      // جاهز من الباك اند
  formattedPrice: string | null;
  formattedSalePrice: string | null;
  attributes: ProductAttributeData[];
  stocks: ProductLocationStockData[];
}

export interface ProductData {
  id: string;
  platform: Platform;
  nameAr: string;
  nameEn: string | null;
  descriptionAr: string;
  descriptionEn: string | null;
  shortDescriptionAr: string | null;
  shortDescriptionEn: string | null;
  sku: string;
  barcode: string;
  mpn: string | null;
  gtin: string | null;
  price: number;
  salePrice: number | null;
  costPrice: number | null;
  isDiscountActive: boolean;
  discountStart: string | null;
  discountEnd: string | null;
  isUnlimited: boolean;
  quantity: number;
  weight: number;
  weightType: string | null;
  isPublished: boolean;
  status: ProductStatus;
  requiresShipping: boolean;
  isTaxable: boolean;
  structure: ProductStructure | null;
  categories: ProductCategoryData[];
  images: ProductImageData[];
  stocks: ProductLocationStockData[];
  variants: ProductVariantData[];
  customOptions: ProductCustomOptionData[] | null;
  minOrderQuantity: number | null;
  maxOrderQuantity: number | null;
  maxItemsPerUser: number | null;
  seoTitleAr: string;
  seoTitleEn: string | null;
  seoDescriptionAr: string;
  seoDescriptionEn: string | null;
  seoSlug: string;
  keywords: string[];
  htmlUrl: string;
  formattedPrice: string | null;
  formattedSalePrice: string | null;
  scopedPrices: Record<string, unknown> | null;
  badge: { label: string; icon: string | null } | null;
  metafields: Record<string, unknown> | null;
  productClass: string | null;
}

export interface PaginationData {
  currentPage: number;
  totalPages: number;
  totalCount: number;
  perPage: number;
  hasNext: boolean;
  hasPrev: boolean;
}

export interface ProductListResponse {
  success: boolean;
  data: ProductData[];
  pagination: PaginationData;
}

export interface ProductShowResponse {
  success: boolean;
  data: ProductData;
}

// Helper functions
export const isSallaProduct = (p: ProductData) => p.platform === 'salla';
export const isZidProduct   = (p: ProductData) => p.platform === 'zid';
export const hasVariants    = (p: ProductData) => p.variants.length > 0;
export const isOutOfStock   = (p: ProductData) => p.status === 'out';
export const isActive       = (p: ProductData) => p.status === 'sale';
export const isHidden       = (p: ProductData) => p.status === 'hidden';
export const mainImage      = (p: ProductData) =>
  p.images.find(i => i.isMain) ?? p.images[0] ?? null;
export const hasDiscount    = (p: ProductData) =>
  p.isDiscountActive && p.salePrice !== null && p.salePrice > 0;
```

---

## 14. Hook كامل للمنتجات

```typescript
// src/hooks/useProducts.ts
import { useState, useEffect, useCallback } from 'react';
import type { ProductData, PaginationData, ProductListResponse } from '../types/platform';

interface UseProductsOptions {
  page?: number;
  limit?: number;
  search?: string;
  category_id?: string;
  status?: string;
}

export function useProducts(initial: UseProductsOptions = {}) {
  const [products,   setProducts]   = useState<ProductData[]>([]);
  const [pagination, setPagination] = useState<PaginationData | null>(null);
  const [loading,    setLoading]    = useState(false);
  const [error,      setError]      = useState<string | null>(null);
  const [filters,    setFilters]    = useState({
    page: 1, limit: 15, search: '', category_id: '', status: '', ...initial
  });

  const fetchProducts = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams();
      Object.entries(filters).forEach(([k, v]) => {
        if (v !== null && v !== undefined && v !== '') params.set(k, String(v));
      });

      const res = await fetch(`/api/v1/products?${params}`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Accept': 'application/json',
        },
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json: ProductListResponse = await res.json();

      setProducts(json.data ?? []);
      setPagination(json.pagination ?? null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'حدث خطا');
      setProducts([]);
    } finally {
      setLoading(false);
    }
  }, [filters]);

  useEffect(() => { fetchProducts(); }, [fetchProducts]);

  // تحديث الفلاتر مع اعادة page الى 1
  const updateFilters = useCallback((next: Partial<typeof filters>) => {
    setFilters(prev => ({ ...prev, ...next, page: 1 }));
  }, []);

  // التنقل للصفحة المحددة
  const goToPage = useCallback((page: number) => {
    setFilters(prev => ({ ...prev, page: Math.max(1, page) }));
  }, []);

  return { products, pagination, loading, error, filters, updateFilters, goToPage, refetch: fetchProducts };
}
```

---

## 15. قواعد ذهبية

```
القواعد العشر الذهبية — لا تنساها ابدا:

1. nameAr هو الاسم الاساسي دائما
   nameEn = null في سلة — لا تعتمد عليه كـ primary

2. اظهر الحقل فقط اذا لم يكن null
   if (product.nameEn !== null) { show... }

3. hasNext/hasPrev هما مصدر التنقل الحقيقي
   totalCount في سلة هو تقدير فقط

4. status = 'out' لا يرسل عند التعديل
   الباك اند يحدده تلقائيا من quantity

5. displayName جاهز من الباك اند — لا تعيد بناءه
   variant.displayName = "اللون: احمر / الحجم: كبير"

6. mainImage = find(isMain) ?? images[0] ?? null
   دائما fallback لصورة placeholder

7. isUnlimited = true → تجاهل quantity
   لا تعرض "0 قطعة" اذا كان المخزون غير محدود

8. platform موجود في كل استجابة منتج
   لا تحتاج لمعرفته مسبقا — هو في البيانات

9. badge و metafields موجودان في زد فقط
   دائما: if (product.badge) { ... }

10. عند تغيير اي فلتر → اعد page الى 1
    onFilterChange({ ...filters, page: 1 })
```

### قائمة التحقق للتطوير

```
قبل تطوير اي ميزة جديدة — تحقق من:
- هل هذا الحقل موجود في كلا المنصتين؟ (راجع جدول التوافق)
- هل قيمة null ممكنة؟ اذن اضف null check
- هل البيانات Arabic-first؟ nameAr اولا دائما
- هل الـ pagination يستخدم hasNext/hasPrev؟
- هل form reset يعيد page = 1 عند تغيير الفلاتر؟
- هل الصور لديها fallback للـ placeholder؟
- هل المنتجات ذات variants تعرض displayName مباشرة؟
```

---

> تاريخ اخر تحديث: 2026-07-28
> مرجع الباك اند: ProductData.php + SallaProvider.php + ZidProvider.php
> التوثيق الرسمي: Salla API (docs.salla.dev) | Zid API (api.zid.sa)
