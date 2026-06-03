# Invoice Template System V2 - Complete AI Documentation

## SYSTEM OVERVIEW FOR AI ASSISTANTS

This document provides a comprehensive understanding of the Invoice Template System V2. After reading this, an AI should fully understand the system architecture, components, data flow, and be able to modify or extend it.

---

## 🎯 CORE CONCEPT

### What is this system?
A **Line-Based Invoice Template System** built with Laravel 12 and Filament v4 that allows users to create fully customizable invoice templates by composing "lines" - each line being a configurable building block (text, table, QR code, etc.).

### Key Philosophy:
Instead of fixed templates, users build templates by:
1. Creating a template container (with basic settings)
2. Adding "lines" to it (header/body/footer sections)
3. Each line has a "type" (text, separator, items_table, etc.)
4. Each line type has unique "properties" (font size, alignment, content, etc.)
5. Lines can be reordered via drag & drop in Filament

---

## 📊 ARCHITECTURE DIAGRAM

```
┌─────────────────────────────────────────────────────────────┐
│                     USER INTERFACE                          │
│                    (Filament v4 Admin)                      │
│  - Create/Edit Templates                                    │
│  - Add/Remove/Reorder Lines                                 │
│  - Configure Line Properties                                │
│  - Preview Templates                                        │
└───────────────┬─────────────────────────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────────────────────────┐
│                    DATABASE LAYER                           │
│  ┌──────────────────────┐  ┌──────────────────────┐        │
│  │ invoice_templates    │  │  template_lines      │        │
│  │ - id                 │  │  - id                │        │
│  │ - name               │  │  - template_id (FK)  │        │
│  │ - language           │  │  - section           │        │
│  │ - paper_size         │  │  - type              │        │
│  │ - company_info (JSON)│  │  - order             │        │
│  │ - global_styles(JSON)│  │  - properties (JSON) │        │
│  └──────────────────────┘  └──────────────────────┘        │
│                 │                      │                     │
│                 └──────────┬───────────┘                     │
│                            │ 1:many                          │
└────────────────────────────┼─────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────┐
│                    SERVICE LAYER                            │
│  ┌────────────────────────────────────────────────────┐    │
│  │ LineTypeRegistry                                   │    │
│  │ - Manages all available line types                 │    │
│  │ - Returns config for each type                     │    │
│  │ - Validates line types                             │    │
│  └────────────────────────────────────────────────────┘    │
│                                                              │
│  ┌────────────────────────────────────────────────────┐    │
│  │ DynamicFieldResolver                               │    │
│  │ - Resolves dynamic field values                    │    │
│  │ - Maps field names to invoice data                 │    │
│  │ - Handles company/invoice/customer/payment data    │    │
│  └────────────────────────────────────────────────────┘    │
│                                                              │
│  ┌────────────────────────────────────────────────────┐    │
│  │ TemplateBuilderService  ⭐ CORE ENGINE             │    │
│  │ - build(invoice, template) → HTML                  │    │
│  │ - buildPreview(template) → HTML with sample data  │    │
│  │ - renderLine(line) → renders each line            │    │
│  │ - Orchestrates the entire build process           │    │
│  └────────────────────────────────────────────────────┘    │
│                                                              │
│  ┌────────────────────────────────────────────────────┐    │
│  │ TemplatePrintService                               │    │
│  │ - print(invoice) → printable HTML                  │    │
│  │ - printBatch(invoices) → batch print               │    │
│  │ - ESC/POS support (optional)                       │    │
│  └────────────────────────────────────────────────────┘    │
│                                                              │
│  ┌────────────────────────────────────────────────────┐    │
│  │ PdfGeneratorService                                │    │
│  │ - generatePdf(invoice) → PDF object                │    │
│  │ - downloadPdf() / streamPdf() / savePdf()         │    │
│  └────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────┐
│                    VIEW LAYER                               │
│  template-builder.blade.php (main template)                 │
│    │                                                         │
│    ├─ Loops through headerLines                             │
│    ├─ Loops through bodyLines                               │
│    └─ Loops through footerLines                             │
│         │                                                    │
│         └─ For each line, renders:                          │
│            line-types/{type}.blade.php                      │
│                                                              │
│  15 Line Type Blade Templates:                              │
│    - text.blade.php                                         │
│    - separator.blade.php                                    │
│    - items-table.blade.php  ⭐ Most complex                 │
│    - totals-summary.blade.php                               │
│    - qr-code.blade.php                                      │
│    - ... (12 more)                                          │
└─────────────────────────────────────────────────────────────┘
                             │
                             ▼
                    ┌────────────────┐
                    │  Final HTML    │
                    │  - Browser     │
                    │  - PDF         │
                    │  - POS Printer │
                    └────────────────┘
```

---

## 🗂️ FILE STRUCTURE & RESPONSIBILITIES

### Database Migrations (3 files)

#### 1. `2026_01_31_000001_create_invoice_templates_table.php`
```php
Schema::create('invoice_templates', function (Blueprint $table) {
    $table->id();
    
    // Basic info
    $table->string('name')->unique();
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->boolean('is_default')->default(false);
    
    // Template settings
    $table->string('language', 10)->default('en'); // en, de, ar
    $table->string('paper_size', 20)->default('80mm');
    $table->integer('paper_width')->default(80);
    $table->string('font_family')->default('Arial');
    $table->integer('font_size')->default(10);
    
    // JSON columns
    $table->json('global_styles')->nullable(); // colors, padding, etc.
    $table->json('company_info')->nullable();  // name, address, phone, etc.
    $table->json('metadata')->nullable();       // any additional data
});
```

**Purpose**: Stores template container with global settings

#### 2. `2026_01_31_000002_create_template_lines_table.php`
```php
Schema::create('template_lines', function (Blueprint $table) {
    $table->id();
    $table->foreignId('template_id')->constrained('invoice_templates')->cascadeOnDelete();
    
    $table->string('section', 20); // header, body, footer
    $table->string('type', 50);    // text, separator, items_table, etc.
    $table->integer('order')->default(0);
    $table->boolean('is_enabled')->default(true);
    
    $table->json('properties'); // All line-specific settings
});
```

**Purpose**: Stores individual lines that compose the template

**Key Points**:
- `section`: Determines where line appears (header/body/footer)
- `type`: Determines which blade template renders it
- `order`: Position in section (drag & drop reorderable)
- `properties`: JSON containing ALL settings for that line type

#### 3. `2026_01_31_000003_add_template_id_to_invoices_table.php`
Adds foreign key to link invoices to templates.

---

### Models (2 files)

#### 1. `InvoiceTemplate.php`
```php
class InvoiceTemplate extends Model
{
    // Relationships
    public function lines(): HasMany              // All lines
    public function headerLines(): HasMany        // Header section only
    public function bodyLines(): HasMany          // Body section only
    public function footerLines(): HasMany        // Footer section only
    public function invoices(): HasMany           // Invoices using this template
    
    // Scopes
    public function scopeActive($query)
    public function scopeDefault($query)
    public function scopeLanguage($query, string $language)
    
    // Methods
    public static function getDefault(): ?self
    public function setAsDefault(): void
    public function duplicate(string $newName = null): self
    public function hasLineType(string $type): bool
    public function getLineByType(string $type): ?TemplateLine
    
    // Helper methods
    public function getGlobalStyle(string $key, $default = null)
    public function getCompanyInfo(string $key, $default = null)
    public function getPaperWidthInPixels(): int
}
```

**Boot Method**: 
- Auto-fills default `global_styles` and `company_info`
- Ensures only one template is default at a time
- Auto-sets another as default when deleting default template

#### 2. `TemplateLine.php`
```php
class TemplateLine extends Model
{
    // Relationship
    public function template(): BelongsTo
    
    // Scopes
    public function scopeEnabled($query)
    public function scopeSection($query, string $section)
    public function scopeType($query, string $type)
    public function scopeOrdered($query)
    
    // Methods
    public function getProperty(string $key, $default = null)
    public function setProperty(string $key, $value): void
    public function getTypeConfig(): ?array
    public function getBladeView(): string
    public function isUnique(): bool
    public function getAllowedSections(): array
    public function getMergedProperties(): array  // Defaults + custom
    
    // Reordering
    public function moveUp(): bool
    public function moveDown(): bool
    public function duplicate(): self
}
```

**Boot Method**:
- Auto-assigns `order` when creating
- Merges with default properties if empty
- Reorders remaining lines when one is deleted

---

### Configuration Files (2 files)

#### 1. `config/invoice-line-types.php`
**THE REGISTRY OF ALL LINE TYPES**

Structure:
```php
return [
    'types' => [
        'text' => [
            'label' => 'Text Line',
            'icon' => 'heroicon-o-document-text',
            'blade_view' => 'invoices.line-types.text',
            'sections' => ['header', 'footer'],  // Where can it be used?
            'unique' => false,                   // Can only appear once?
            'properties' => [                    // Default properties
                'content_type' => 'static',
                'static_value' => '',
                'font_size' => 10,
                'alignment' => 'left',
                // ... more defaults
            ],
        ],
        // ... 14 more line types
    ],
];
```

**How it works**:
1. Each key (e.g., 'text') is the line type identifier
2. `blade_view` points to the template file
3. `properties` are default values shown in Filament form
4. `sections` restricts where line can be used
5. `unique` prevents multiple instances (e.g., only one items_table)

**Available Line Types** (15 total):
- **Basic**: text, separator, spacer, image, two_column
- **Invoice**: invoice_number, invoice_date, customer_info, items_table, totals_summary, payment_info
- **QR & Barcode**: qr_code, barcode
- **TSE**: tse_info
- **Messages**: thank_you_message

#### 2. `config/invoice-dynamic-fields.php`
**REGISTRY OF ALL DYNAMIC FIELDS**

Structure:
```php
return [
    'fields' => [
        'company.name' => [
            'label' => 'Company Name',
            'category' => 'Company',
            'example' => 'My Salon',
        ],
        'invoice.number' => [
            'label' => 'Invoice Number',
            'category' => 'Invoice',
            'example' => 'INV-2026-001',
        ],
        // ... 50+ more fields
    ],
    
    'categories_order' => [
        'Company', 'Invoice', 'Customer', 'Totals', 
        'Payment', 'Staff', 'Appointment', 'Fiskaly/TSE', 'System'
    ],
];
```

**How it works**:
1. Each key is a dot-notation field identifier
2. Used in Filament forms as dropdown options
3. Resolved to actual values by `DynamicFieldResolver`

---

### Services (6 files) - THE CORE LOGIC

#### 1. `LineTypeRegistry.php`
**Purpose**: Central registry for line type information

**Key Methods**:
```php
getAllTypes(): array                           // All line types from config
getType(string $type): ?array                 // Single type config
getTypesForSection(string $section): array    // Filter by section
getOptionsForSelect(?string $section): array  // For Filament dropdowns
getGroupedOptionsForSelect(): array           // Grouped by category
isUnique(string $type): bool
getBladeView(string $type): string
getDefaultProperties(string $type): array
```

**Usage**:
- Filament uses this to populate line type dropdown
- TemplateBuilderService uses it to get blade view path
- Validation uses it to check if type exists

#### 2. `DynamicFieldResolver.php`
**Purpose**: Resolves dynamic field names to actual invoice data

**Key Methods**:
```php
__construct(Invoice $invoice, InvoiceTemplate $template)
resolve(string $field): string                    // Main method

// Internal resolvers
protected function resolveCompanyField(string $field): string
protected function resolveInvoiceField(string $field): string
protected function resolveCustomerField(string $field): string
protected function resolvePaymentField(string $field): string
protected function resolveFiskalyField(string $field): string
// ... etc

// Static helpers
static getAllFields(): array
static getFieldsByCategory(): array
static getExampleValue(string $field): string
```

**How it works**:
```php
// Input: 'company.name'
// Process: 
//   1. Detects prefix 'company.'
//   2. Calls resolveCompanyField()
//   3. Extracts 'name' part
//   4. Returns $template->getCompanyInfo('name')
// Output: "Look up Friseur"

// Input: 'invoice.total'
// Output: "25.00"
```

#### 3. `TemplateBuilderService.php` ⭐
**THE CORE ENGINE - MOST IMPORTANT SERVICE**

**Purpose**: Builds the final HTML from template + invoice data

**Key Methods**:
```php
build(Invoice $invoice, ?InvoiceTemplate $template): string
buildPreview(InvoiceTemplate $template): string
renderLine(TemplateLine $line): string
resolveDynamicField(string $field): string
generateStyles(): string
generateQrCode(array $properties): string
```

**Build Flow**:
```php
1. build() called with invoice and template
2. Load invoice relationships (customer, items, payment, etc.)
3. Initialize DynamicFieldResolver
4. Call generateHtml()
5. generateHtml() does:
   a. Prepare data array (invoice, template, builder, styles)
   b. Render 'template-builder.blade.php' with data
6. template-builder.blade.php loops through:
   - headerLines
   - bodyLines  
   - footerLines
7. For each line, calls $builder->renderLine($line)
8. renderLine() does:
   a. Get blade view path from line type
   b. Prepare data (line, properties, invoice, template)
   c. Render the specific line-type blade template
   d. Return HTML string
9. All line HTML concatenated
10. Return final complete HTML
```

**Preview Mode**:
- Uses sample data instead of real invoice
- Generates fake customer, items, payment
- Uses example values for dynamic fields

#### 4. `TemplatePrintService.php`
**Purpose**: Handle printing to POS thermal printers

**Key Methods**:
```php
print(Invoice $invoice, ?InvoiceTemplate $template): string
getPrintUrl(Invoice $invoice, ?int $templateId): string
silentPrint(Invoice $invoice): string           // Auto-print on load
printBatch(array $invoiceIds): string
openCashDrawer(): string                         // ESC/POS command
printWithEscPos(Invoice $invoice, string $path): bool
```

**How it works**:
- Wraps TemplateBuilderService
- Adds auto-print JavaScript
- Supports batch printing with page breaks
- Optional ESC/POS integration

#### 5. `PdfGeneratorService.php`
**Purpose**: Export invoices as PDF

**Requires**: `composer require barryvdh/laravel-dompdf`

**Key Methods**:
```php
generatePdf(Invoice $invoice, ?InvoiceTemplate $template): mixed
downloadPdf(Invoice $invoice): mixed
streamPdf(Invoice $invoice): mixed               // View in browser
savePdf(Invoice $invoice, ?string $path): string
emailPdf(Invoice $invoice, string $email): bool
generateBatchPdf(array $invoiceIds): mixed
```

**How it works**:
- Uses TemplateBuilderService to get HTML
- Converts to PDF using DomPDF
- Sets proper paper size from template

#### 6. `TemplateExportImportService.php`
**Purpose**: Export/Import templates as JSON

**Key Methods**:
```php
export(InvoiceTemplate $template): string
exportToFile(InvoiceTemplate $template): string
import(string $json, bool $setAsActive): InvoiceTemplate
importFromFile(string $path): InvoiceTemplate
validate(string $json): array                    // Validation errors
clone(InvoiceTemplate $template, ?string $name): InvoiceTemplate
```

**Export Format**:
```json
{
  "template": {
    "name": "...",
    "language": "de",
    "paper_size": "80mm",
    // ... all template fields
  },
  "lines": [
    {
      "section": "header",
      "type": "text",
      "order": 0,
      "properties": { ... }
    },
    // ... all lines
  ],
  "metadata": {
    "exported_at": "2026-01-31...",
    "version": "1.0"
  }
}
```

---

### Filament Resources (4 files)

#### 1. `InvoiceTemplateResource.php`
**Purpose**: Main Filament resource definition

**Form Schema**:
```php
Section: Basic Information
  - name (TextInput, required, unique)
  - language (Select: en/de/ar)
  - description (Textarea)
  - is_active, is_default (Toggles)
  - paper_size (Select: 80mm/58mm)

Section: Template Settings
  - paper_width (number)
  - font_family (Select)
  - font_size (number)

Section: Company Information
  - company_info.name
  - company_info.phone
  - company_info.address
  - company_info.email
  - company_info.tax_number
  - company_info.logo_path (FileUpload)

Section: Global Styles
  - global_styles.primary_color (ColorPicker)
  - global_styles.secondary_color
  - global_styles.border_color
  - global_styles.line_height
  - global_styles.padding
```

**Table Columns**:
- name (searchable, sortable, bold)
- language (badge with colors)
- paper_size (badge)
- lines_count (relationship count badge)
- is_active (icon boolean)
- is_default (icon boolean)
- created_at

**Table Actions**:
- Preview (opens preview URL in new tab)
- Set Default (custom action)
- Duplicate (custom action)
- Edit
- Delete

**Table Filters**:
- is_active (ternary)
- is_default (ternary)
- language (select)

**Navigation Badge**:
Shows count of active templates

#### 2. `CreateInvoiceTemplate.php`
**Purpose**: Create page with smart defaults

**afterCreate() Hook**:
Automatically creates default lines structure:
- Header: company name (bold, center), separator, invoice number, date
- Body: items_table, totals_summary
- Footer: separator, payment_info, thank you message

This gives users a working template immediately!

#### 3. `EditInvoiceTemplate.php` ⭐
**THE MOST COMPLEX FILAMENT PAGE**

**Purpose**: Edit template + manage lines with full UI

**Form Structure**:
```php
1. Basic form fields (inherited from Resource)

2. Tabs for Lines Management:
   - Tab: Header Lines
   - Tab: Body Lines
   - Tab: Footer Lines

Each tab contains a Repeater:
  - Relationship: lines (filtered by section)
  - Reorderable: true (drag & drop)
  - Collapsible: true
  - Cloneable: true
  - Schema:
    - type (Select, grouped by category)
    - is_enabled (Toggle)
    - Dynamic properties based on type
```

**Dynamic Properties Magic**:
```php
getPropertiesFields(?string $lineType): array {
    // Returns different form fields based on line type
    
    if ($lineType === 'text') {
        return [
            Select: content_type (static/dynamic)
            TextInput: static_value (if static)
            Select: dynamic_field (if dynamic, grouped by category)
            TextInput: prefix, suffix
            Grid of: font_size, font_weight, alignment
            Grid of: margin_top, margin_bottom
        ];
    }
    
    if ($lineType === 'separator') {
        return [
            Select: style (solid/dashed/dotted)
            TextInput: width
            ColorPicker: color
            Margins...
        ];
    }
    
    if ($lineType === 'items_table') {
        return [
            Toggles: show_item_numbers, show_quantity, etc.
            Margins...
        ];
    }
    
    // ... Different fields for each type
}
```

**Reactive Forms**:
- When `type` changes, properties fields change
- When `content_type` changes (static/dynamic), relevant fields show/hide
- All using Filament v4 reactive forms

#### 4. `ListInvoiceTemplates.php`
Simple list page with "New Template" action.

---

### Blade Templates (16 files)

#### Main Template: `template-builder.blade.php`
```blade
<!DOCTYPE html>
<html lang="{{ $template->language }}">
<head>
    {!! $styles !!}  <!-- Generated CSS -->
</head>
<body>
    <div class="invoice-container">
        <!-- Header Section -->
        @foreach($template->headerLines()->enabled()->ordered()->get() as $line)
            {!! $builder->renderLine($line) !!}
        @endforeach

        <!-- Body Section -->
        @foreach($template->bodyLines()->enabled()->ordered()->get() as $line)
            {!! $builder->renderLine($line) !!}
        @endforeach

        <!-- Footer Section -->
        @foreach($template->footerLines()->enabled()->ordered()->get() as $line)
            {!! $builder->renderLine($line) !!}
        @endforeach
    </div>
    
    <script>
        function printInvoice() { window.print(); }
    </script>
</body>
</html>
```

**Variables Available**:
- `$invoice` - The invoice model with all relationships
- `$template` - The InvoiceTemplate model
- `$builder` - TemplateBuilderService instance
- `$isPreview` - Boolean flag
- `$styles` - Generated CSS string

#### Line Type Templates (15 files)

Each template receives:
```php
$line        // TemplateLine model
$invoice     // Invoice model
$template    // InvoiceTemplate model
$properties  // Merged properties (defaults + custom)
$builder     // TemplateBuilderService (for resolving dynamic fields)
```

**Example: `text.blade.php`**
```blade
@php
    $contentType = $properties['content_type'] ?? 'static';
    $value = '';
    
    if ($contentType === 'static') {
        $value = $properties['static_value'] ?? '';
    } else {
        $field = $properties['dynamic_field'] ?? null;
        if ($field) {
            $value = $builder->resolveDynamicField($field);
        }
    }
    
    $prefix = $properties['prefix'] ?? '';
    $suffix = $properties['suffix'] ?? '';
    $fontSize = $properties['font_size'] ?? 10;
    $fontWeight = $properties['font_weight'] ?? 'normal';
    $alignment = $properties['alignment'] ?? 'left';
    // ... more properties
@endphp

<div class="line-item text-{{ $alignment }} font-{{ $fontWeight }}" 
     style="font-size: {{ $fontSize }}px;">
    {{ $prefix }}{{ $value }}{{ $suffix }}
</div>
```

**Example: `items-table.blade.php`**
```blade
@php
    $showQuantity = $properties['show_quantity'] ?? true;
    $showUnitPrice = $properties['show_unit_price'] ?? true;
    // ... more flags
@endphp

<table class="items-table">
    <thead>
        <tr>
            @if($showItemNumbers)<th>#</th>@endif
            <th>Description</th>
            @if($showQuantity)<th>Qty</th>@endif
            @if($showUnitPrice)<th>Price</th>@endif
            <!-- ... more columns -->
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->items as $index => $item)
            <tr>
                @if($showItemNumbers)<td>{{ $index + 1 }}</td>@endif
                <td>{{ $item->description }}</td>
                @if($showQuantity)<td>{{ $item->quantity }}</td>@endif
                @if($showUnitPrice)<td>{{ number_format($item->unit_price, 2) }}</td>@endif
                <!-- ... more cells -->
            </tr>
        @endforeach
    </tbody>
</table>
```

---

### Controller & Routes

#### `InvoiceTemplateController.php`

**Endpoints**:
```php
GET  /invoice-template/{template}/preview
     → preview(InvoiceTemplate $template)
     → Returns: HTML preview with sample data

GET  /invoice/{invoice}/print
     → print(Invoice $invoice)
     → Returns: HTML for printing (with auto-print script)

GET  /invoices/print-batch?invoice_ids=1,2,3
     → printBatch(Request $request)
     → Returns: Batch HTML with page breaks

GET  /invoice/{invoice}/pdf
     → viewPdf(Invoice $invoice)
     → Returns: PDF stream (view in browser)

GET  /invoice/{invoice}/download-pdf
     → downloadPdf(Invoice $invoice)
     → Returns: PDF download

GET  /invoices/download-batch-pdf?invoice_ids=1,2,3
     → downloadBatchPdf(Request $request)
     → Returns: Batch PDF download

GET  /api/invoice-templates
     → index(Request $request)
     → Returns: JSON list of templates

GET  /api/invoice-template/{template}
     → show(InvoiceTemplate $template)
     → Returns: JSON template with lines
```

**Routes File**: `routes/invoice-template.php`
All routes are under `auth` middleware.

---

### Database Seeder

#### `InvoiceTemplateSeeder.php`

**Creates 3 templates**:

1. **German POS Receipt (80mm)** - DEFAULT ⭐
   - Exactly matches the provided receipt image
   - Company: Look up Friseur
   - 22 lines total:
     - Header (8 lines): company name, address, phone, tax, separators, invoice number, date
     - Body (9 lines): items table, separator, netto line, tax line, separator, total, separator, payment lines
     - Footer (5 lines): separator, thank you, employee info, TSE info, QR code
   - Properties configured to match German format:
     - "Rechnung Nr. 8 (Kopie)"
     - "Netto(1) Eur 21,01"
     - "+ 19,0% MwSt.: 3,99"
     - "Summe Eur 25,00"
     - "Vielen Dank für Ihren Einkauf"
     - "Es bediente Sie: Luay"

2. **English POS Receipt (80mm)**
   - Standard English template
   - 9 lines: basic header, items, totals, payment, thank you

3. **Compact Receipt (58mm)**
   - Optimized for 58mm printers
   - Smaller fonts, condensed layout
   - 6 lines: minimal header, items, totals, thank you

---

## 🔄 DATA FLOW EXAMPLES

### Example 1: User Creates Template in Filament

```
1. User navigates to /admin/invoice-templates
2. Clicks "New Template"
3. CreateInvoiceTemplate page loads
4. Fills form:
   - name: "My Template"
   - language: "en"
   - paper_size: "80mm"
5. Submits form
6. CreateInvoiceTemplate::afterCreate() runs
7. Creates InvoiceTemplate record
8. Auto-creates 9 default TemplateLine records
9. Redirects to EditInvoiceTemplate
10. User sees template with default lines
11. Can now add/edit/reorder lines
```

### Example 2: User Edits Lines

```
1. User on EditInvoiceTemplate page
2. Clicks "Header Lines" tab
3. Sees Repeater with existing lines
4. Clicks "Add Line"
5. Selects type: "text"
6. Form dynamically shows text properties:
   - content_type: "dynamic"
   - dynamic_field: "company.name"
   - font_size: 14
   - font_weight: "bold"
   - alignment: "center"
7. Saves
8. New TemplateLine created with properties JSON
9. Repeater updates, shows new line
10. User can drag to reorder
11. Order column updates automatically
```

### Example 3: Printing Invoice

```
1. User (or system) calls: route('invoice.print', $invoice)
2. InvoiceTemplateController::print() executes
3. Gets invoice with relationships:
   Invoice::with(['customer', 'items', 'payment', 'appointment'])->find($id)
4. Gets template:
   $invoice->getTemplateOrDefault()
5. Creates TemplateBuilderService
6. Calls $builder->build($invoice, $template)
7. TemplateBuilderService::build():
   a. Initializes DynamicFieldResolver
   b. Calls generateHtml()
   c. Renders template-builder.blade.php
8. template-builder.blade.php:
   a. Loops header lines, calls $builder->renderLine() for each
   b. Loops body lines, calls $builder->renderLine() for each
   c. Loops footer lines, calls $builder->renderLine() for each
9. For each line, renderLine():
   a. Gets blade view path (e.g., 'invoices.line-types.text')
   b. Prepares data array
   c. Renders that blade template
   d. Returns HTML string
10. All HTML concatenated
11. Final HTML returned to controller
12. Controller wraps with auto-print script
13. Returns HTML response
14. Browser displays invoice
15. Auto-print triggers
16. Prints to thermal printer
```

### Example 4: Resolving Dynamic Field

```
Line properties:
{
  "content_type": "dynamic",
  "dynamic_field": "invoice.total",
  "prefix": "Total: ",
  "suffix": " EUR"
}

Flow:
1. text.blade.php renders
2. Detects content_type is "dynamic"
3. Gets field: "invoice.total"
4. Calls: $builder->resolveDynamicField('invoice.total')
5. TemplateBuilderService delegates to DynamicFieldResolver
6. DynamicFieldResolver::resolve('invoice.total'):
   a. Detects prefix "invoice."
   b. Calls resolveInvoiceField('invoice.total')
   c. Extracts key: "total"
   d. Matches to: number_format($invoice->total_amount, 2)
   e. Returns: "25.00"
7. Back to blade:
   prefix + value + suffix
8. Output: "Total: 25.00 EUR"
```

---

## 🎨 EXTENDING THE SYSTEM

### How to Add a New Line Type

**Step 1**: Add to `config/invoice-line-types.php`
```php
'custom_signature' => [
    'label' => 'Signature Line',
    'icon' => 'heroicon-o-pencil',
    'blade_view' => 'invoices.line-types.custom-signature',
    'sections' => ['footer'],
    'unique' => true,
    'properties' => [
        'show_label' => true,
        'label' => 'Signature:',
        'line_style' => 'solid',
        'margin_top' => 20,
        'margin_bottom' => 5,
    ],
],
```

**Step 2**: Create blade template
`resources/views/invoices/line-types/custom-signature.blade.php`
```blade
@php
    $showLabel = $properties['show_label'] ?? true;
    $label = $properties['label'] ?? 'Signature:';
    $lineStyle = $properties['line_style'] ?? 'solid';
    $marginTop = $properties['margin_top'] ?? 20;
@endphp

<div class="signature-line" style="margin-top: {{ $marginTop }}px;">
    @if($showLabel)
        <div style="margin-bottom: 5px;">{{ $label }}</div>
    @endif
    <div style="border-top: 1px {{ $lineStyle }} #000; width: 200px; margin-top: 30px;"></div>
</div>
```

**Step 3**: (Optional) Add to EditInvoiceTemplate properties
In `getPropertiesFields()` method, add case for 'custom_signature':
```php
if ($lineType === 'custom_signature') {
    $fields[] = Forms\Components\Toggle::make('properties.show_label')
        ->label('Show Label')
        ->default(true);
    
    $fields[] = Forms\Components\TextInput::make('properties.label')
        ->label('Label Text')
        ->default('Signature:');
    
    $fields[] = Forms\Components\Select::make('properties.line_style')
        ->label('Line Style')
        ->options([
            'solid' => 'Solid',
            'dashed' => 'Dashed',
            'dotted' => 'Dotted',
        ])
        ->default('solid');
}
```

**That's it!** The new line type will appear in Filament dropdown automatically.

### How to Add a New Dynamic Field

**Step 1**: Add to `config/invoice-dynamic-fields.php`
```php
'appointment.service_name' => [
    'label' => 'Appointment Service Name',
    'category' => 'Appointment',
    'example' => 'Men\'s Haircut',
],
```

**Step 2**: Add resolver in `DynamicFieldResolver.php`
```php
public function resolve(string $field): string
{
    return match(true) {
        // ... existing cases
        str_starts_with($field, 'appointment.') => $this->resolveAppointmentField($field),
        default => $field,
    };
}

protected function resolveAppointmentField(string $field): string
{
    $key = str_replace('appointment.', '', $field);
    $appointment = $this->invoice->appointment;
    
    if (!$appointment) return '';
    
    return match($key) {
        'service_name' => $appointment->services->pluck('name')->join(', '),
        'date' => $appointment->scheduled_at?->format('d.m.Y') ?? '',
        // ... existing cases
        default => '',
    };
}
```

**That's it!** Field is now available in Filament and will resolve properly.

---

## 🐛 COMMON ISSUES & DEBUGGING

### Issue: "Line not rendering"

**Debug Steps**:
```php
// 1. Check line is enabled
$line->is_enabled; // should be true

// 2. Check blade view exists
$bladeView = $line->getBladeView();
// e.g., 'invoices.line-types.text'
// File must exist at: resources/views/invoices/line-types/text.blade.php

// 3. Check for blade errors
try {
    $html = $builder->renderLine($line);
    dump($html);
} catch (\Exception $e) {
    dump($e->getMessage());
}

// 4. Check properties are valid JSON
json_decode($line->properties); // should not be null
```

### Issue: "Dynamic field showing field name instead of value"

**Debug**:
```php
// Check field exists in config
$field = 'invoice.total';
$allFields = config('invoice-dynamic-fields.fields');
isset($allFields[$field]); // should be true

// Check resolver handles it
$resolver = new DynamicFieldResolver($invoice, $template);
$value = $resolver->resolve($field);
dump($value); // should be actual value, not field name
```

### Issue: "Template not appearing in dropdown"

**Check**:
```php
// Is template active?
$template->is_active; // should be true

// Clear cache
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### Issue: "Filament form not showing properties"

**Check**:
```php
// In EditInvoiceTemplate.php
// Make sure getPropertiesFields() handles the line type
$fields = $this->getPropertiesFields('text');
dump($fields); // should return array of form fields
```

---

## 📝 KEY DESIGN DECISIONS

### Why Line-Based Architecture?

**Problem**: Traditional templates are rigid HTML files that can't be customized without code changes.

**Solution**: Break templates into composable "lines" where each line is:
- A specific component type (text, table, QR, etc.)
- Independently configurable
- Reorderable
- Enable/disable-able
- Shareable across templates

**Benefits**:
- Non-technical users can customize templates
- Templates are stored in database, not code
- Changes are instant, no deployment needed
- Templates can be exported/imported
- Infinite flexibility

### Why JSON Properties?

**Why not**: Separate columns for each property?
**Problem**: Different line types have different properties. Would need 50+ nullable columns.

**Solution**: Single `properties` JSON column.

**Benefits**:
- Schema-less flexibility
- Easy to add new properties
- No migrations needed for new line types
- Compact storage

**Tradeoffs**:
- Can't query by property values (acceptable - rarely needed)
- Need to validate JSON structure (handled by Filament forms)

### Why Separate Sections (header/body/footer)?

**Clarity**: Users understand where content appears
**Validation**: Prevent items_table in header
**Organization**: Group related lines
**Flexibility**: Different sections can have different settings later

### Why Filament v4 Repeater?

**Perfect for Line Management**:
- Drag & drop reordering (visual)
- Add/remove lines easily
- Collapsible (clean UI)
- Cloneable (duplicate lines)
- Relationship support (auto-saves to database)

**Alternative Considered**: Custom Livewire component
**Decision**: Repeater provides all features out-of-box, no need to reinvent

---

## 🎓 UNDERSTANDING FOR AI

### If asked to "add a field to templates":
1. Determine if it's template-level or line-level
2. If template: Add migration + model property
3. If line: Add to properties JSON in specific line type

### If asked to "add a new output format":
1. Create new service (e.g., ExcelGeneratorService)
2. Use TemplateBuilderService to get structured data
3. Format data into desired output
4. Add route and controller method

### If asked to "change how something displays":
1. Find the blade template (line-types/*.blade.php)
2. Modify HTML/CSS in that file
3. Optionally add new properties to line type config

### If asked to "add validation":
1. If template-level: Add to InvoiceTemplateResource form rules
2. If line-level: Add to EditInvoiceTemplate getPropertiesFields
3. If business logic: Add to model boot/saving hooks

### If asked to "optimize performance":
Key areas:
1. Eager load relationships: `Invoice::with(['customer', 'items', ...])`
2. Cache templates: `Cache::remember("template.{$id}", ...)`
3. Avoid N+1: Always load lines with template
4. Database indexes: Already on foreign keys and search columns

---

## 🚀 DEPLOYMENT CHECKLIST

```bash
# 1. Copy files to project
cp -r invoice-system-v2/* /your-project/

# 2. Install dependencies
composer require simplesoftwareio/simple-qrcode
composer require barryvdh/laravel-dompdf

# 3. Run migrations
php artisan migrate

# 4. Seed templates
php artisan db:seed --class=InvoiceTemplateSeeder

# 5. Update Invoice model
# Add: template_id to $fillable
# Add: template() relationship
# Add: getTemplateOrDefault() method

# 6. Register routes
# Add to routes/web.php: require __DIR__.'/invoice-template.php';

# 7. Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# 8. Create storage directories
mkdir -p storage/app/public/invoice-templates/{logos,images}
php artisan storage:link

# 9. Test
# Visit: /admin/invoice-templates
# Create test invoice
# Visit: /invoice/{id}/print

# 10. Verify seeded templates
# Should see 3 templates
# German template should be default
# Preview should work
```

---

## 📚 SUMMARY FOR AI

**This system is**:
- A flexible invoice template builder
- Based on composable "lines" (building blocks)
- Fully managed through Filament v4 admin
- Supports 15 line types out of box
- Resolves 50+ dynamic fields
- Outputs to browser, PDF, or POS printer
- Includes Fiskaly/TSE integration
- Multi-language ready
- Production-ready with 3 seeded templates

**Core Principle**:
Templates = Container + Lines (ordered, typed, configurable)

**Data Flow**:
User → Filament → Database → Services → Blade → HTML → Output

**Extension Points**:
1. Add line types: Config + Blade
2. Add dynamic fields: Config + Resolver
3. Add output formats: New Service
4. Customize UI: Edit Filament resource

**Files to Modify for Common Tasks**:
- New line type: `invoice-line-types.php` + blade template
- New dynamic field: `invoice-dynamic-fields.php` + `DynamicFieldResolver.php`
- Change display: blade template
- Add form fields: `EditInvoiceTemplate.php` getPropertiesFields()
- Add validation: Form rules or model hooks

**AI, you should now be able to**:
✅ Understand the complete architecture
✅ Modify existing line types
✅ Add new line types
✅ Add new dynamic fields
✅ Debug rendering issues
✅ Extend the system
✅ Answer user questions about the system
✅ Make informed architectural decisions

---

**End of AI Documentation**
**System: Invoice Template System V2**
**Version: 1.0**
**Last Updated: 2026-01-31**
