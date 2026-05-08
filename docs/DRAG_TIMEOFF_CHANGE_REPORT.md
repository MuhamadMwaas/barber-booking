# تقرير التعديل: سحب Timeline → إجازة (Time Off)

**التاريخ:** 2026-05-08

---

## ملخص التعديل

تغيير سلوك السحب (Drag) على الـ timeline في `StaffDashboard` بحيث:

| التفاعل | قبل التعديل | بعد التعديل |
|---|---|---|
| ضغط بدون سحب | مودال الحجز | مودال الحجز ← بدون تغيير |
| سحب ≤ 15 دقيقة | مودال الحجز | مودال الحجز ← بدون تغيير |
| سحب > 15 دقيقة | مودال الحجز | **مودال الإجازة (Time Off)** مع بيانات جاهزة |

---

## الملفات المعدّلة

### 1. `app/Livewire/StaffDashboard.php`

**ما تم:** إضافة method جديد `openTimeOffModalFromTimeline(int $providerId, string $startTime, string $endTime)`

**التفاصيل:**
- يُعيد ضبط نموذج الإجازة (`resetTimeOffForm`)
- يُعيّن `timeOffProviderId` من الـ provider الذي تم السحب على عموده
- يضبط النوع تلقائياً على `hourly` (`timeOffType = '0'`)
- يضبط تاريخ البداية والنهاية على `selectedDate`
- يملأ `timeOffStartTime` و `timeOffEndTime` من نطاق السحب
- يفتح مودال الإجازة

**الموقع:** بعد `openTimeOffModal()` مباشرة (سطر ~173)

---

### 2. `resources/views/livewire/staff-dashboard.blade.php`

**ما تم:** 3 تعديلات في Alpine/JavaScript

#### 2.1 إضافة دالتين جديدتين

- **`dragDurationMinutes()`**: تحسب مدة السحب بالدقائق بناءً على المسافة بالبكسل و `pixelsPerMinute()`
- **`isDragTimeOff()`**: ترجع `true` إذا مدة السحب > 15 دقيقة

#### 2.2 تعديل drag overlay element

إضافة `:class="{ 'is-timeoff': isDragTimeOff() }"` للـ drag selection overlay حتى يتغيّر اللون أثناء السحب عند تجاوز 15 دقيقة.

#### 2.3 تعديل `finishDragFromDocument()`

- حساب `draggedMinutes` من فرق البكسل ÷ `pixelsPerMinute()`
- إذا `draggedMinutes > 15` → حساب `endTime` من نقطة نهاية السحب مع snap على الـ scale → استدعاء `$wire.openTimeOffModalFromTimeline(providerId, startTime, endTime)`
- غير ذلك (ضغطة أو سحب قصير) → `openBookingModalLocal(providerId, startTime)` كالسابق

---

### 3. `resources/views/layouts/dashboard.blade.php`

**ما تم:** إضافة CSS class `.drag-selection.is-timeoff`

```css
.drag-selection.is-timeoff { background: rgba(245, 158, 11, 0.18); border-color: #f59e0b; }
```

**التأثير:** لون overlay السحب يتحوّل من أزرق إلى برتقالي/ذهبي عند تجاوز مسافة السحب 15 دقيقة، كإشارة بصرية للمستخدم أن العملية ستفتح مودال الإجازة وليس الحجز.

---

## ملاحظات تقنية

- الحد الأدنى (15 دقيقة) **ثابت** ولا يتأثر بـ timeline scale
- أوقات البداية والنهاية للإجازة تعمل **snap** على الـ scale الحالي (مثل الحجز)
- الـ method الأصلي `openTimeOffModal()` لم يُعدّل — زر "Add Time Off" من الـ sidebar يعمل كالسابق
- لا يوجد تغيير في منطق حفظ الإجازة (`saveTimeOff()`) — الـ method الجديد فقط يملأ الحقول مسبقاً
