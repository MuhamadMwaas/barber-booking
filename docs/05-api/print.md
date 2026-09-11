# Print API

> **الملفات:** `app/Http/Controllers/PrintController.php:1`، `app/Services/Print/PrintService.php:1`

---

## 1. `POST /api/invoice/{invoice}/print`

```
POST /api/invoice/42/print
Authorization: Bearer ...
→ 200 HTML للطباعة (أو JSON مع url)
→ يزيد print_count + PrintLog
```

## 2. `POST /api/invoices/print-batch` — دفعي

```json
POST /api/invoices/print-batch { "invoice_ids": [42, 43] }
```

## 3. `GET /api/invoice/{invoice}/print-url`

```
GET /api/invoice/42/print-url → { url: "/invoice/42/print?token=..." }
```

## 4. `GET /api/print/statistics` + `GET /api/print/logs`

```
GET /api/print/statistics → { total_prints, today, by_printer }
GET /api/print/logs → paginated PrintLog
```

## 5. Web Print — `routes/web.php:1`

```
GET /invoice/{invoice}/print (auth) → PrintController@print
GET /invoices/print-batch
GET /appointment/{appointment}/print → AppointmentPrintController
```

- `PrintService` يبني HTML عبر `TemplateBuilderService` + `PrinterSetting`.
- `print_count` → `COPY` label: `""` → `"(COPY)"` → `"(COPY 2)"` — `Invoice.php:140`.

---

*التالي: [`06-booking-flow/README.md`](../06-booking-flow/README.md)*
