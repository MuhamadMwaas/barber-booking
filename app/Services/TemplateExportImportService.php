<?php

namespace App\Services\InvoiceTemplate;

use App\Models\InvoiceTemplate;
use App\Models\TemplateLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemplateExportImportService
{
    /**
     * Export template to JSON
     */
    public function export(InvoiceTemplate $template): string
    {
        $data = [
            'template' => [
                'name' => $template->name,
                'description' => $template->description,
                'language' => $template->language,
                'paper_size' => $template->paper_size,
                'paper_width' => $template->paper_width,
                'font_family' => $template->font_family,
                'font_size' => $template->font_size,
                'global_styles' => $template->global_styles,
                'company_info' => $template->company_info,
            ],
            'lines' => $template->lines->map(function ($line) {
                return [
                    'section' => $line->section,
                    'type' => $line->type,
                    'order' => $line->order,
                    'is_enabled' => $line->is_enabled,
                    'properties' => $line->properties,
                ];
            })->toArray(),
            'metadata' => [
                'exported_at' => now()->toIso8601String(),
                'version' => '1.0',
                'system' => 'Invoice Template System V2',
            ],
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Export template to file
     */
    public function exportToFile(InvoiceTemplate $template, ?string $filename = null): string
    {
        $filename = $filename ?? 'template-' . $template->id . '-' . date('Y-m-d') . '.json';
        $path = 'template-exports/' . $filename;

        $json = $this->export($template);

        Storage::disk('local')->put($path, $json);

        return $path;
    }

    /**
     * Import template from JSON
     */
    public function import(string $json, bool $setAsActive = false): InvoiceTemplate
    {
        $data = json_decode($json, true);

        if (!$data || !isset($data['template']) || !isset($data['lines'])) {
            throw new \Exception('Invalid template JSON format');
        }

        DB::beginTransaction();

        try {
            // Create template
            $templateData = $data['template'];
            $templateData['is_active'] = $setAsActive;
            $templateData['is_default'] = false; // Never set imported as default automatically

            // Make name unique if needed
            $originalName = $templateData['name'];
            $counter = 1;
            while (InvoiceTemplate::where('name', $templateData['name'])->exists()) {
                $templateData['name'] = $originalName . ' (Imported ' . $counter . ')';
                $counter++;
            }

            $template = InvoiceTemplate::create($templateData);

            // Create lines
            foreach ($data['lines'] as $lineData) {
                $lineData['template_id'] = $template->id;
                TemplateLine::create($lineData);
            }

            DB::commit();

            return $template;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Failed to import template: ' . $e->getMessage());
        }
    }

    /**
     * Import template from file
     */
    public function importFromFile(string $path, bool $setAsActive = false): InvoiceTemplate
    {
        if (!Storage::disk('local')->exists($path)) {
            throw new \Exception('Template file not found: ' . $path);
        }

        $json = Storage::disk('local')->get($path);

        return $this->import($json, $setAsActive);
    }

    /**
     * Import template from uploaded file
     */
    public function importFromUpload($file, bool $setAsActive = false): InvoiceTemplate
    {
        $json = file_get_contents($file->getRealPath());

        return $this->import($json, $setAsActive);
    }

    /**
     * List available template exports
     */
    public function listExports(): array
    {
        $files = Storage::disk('local')->files('template-exports');

        return collect($files)->map(function ($file) {
            return [
                'filename' => basename($file),
                'path' => $file,
                'size' => Storage::disk('local')->size($file),
                'modified' => Storage::disk('local')->lastModified($file),
            ];
        })->toArray();
    }

    /**
     * Download template export
     */
    public function download(InvoiceTemplate $template): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $json = $this->export($template);
        $filename = 'template-' . Str::slug($template->name) . '-' . date('Y-m-d') . '.json';

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Validate template JSON before import
     */
    public function validate(string $json): array
    {
        $errors = [];

        try {
            $data = json_decode($json, true);

            if (!$data) {
                $errors[] = 'Invalid JSON format';
                return $errors;
            }

            // Check required fields
            if (!isset($data['template'])) {
                $errors[] = 'Missing template data';
            }

            if (!isset($data['lines'])) {
                $errors[] = 'Missing lines data';
            }

            // Validate template fields
            $required = ['name', 'language', 'paper_size', 'paper_width'];
            foreach ($required as $field) {
                if (!isset($data['template'][$field])) {
                    $errors[] = "Missing required field: {$field}";
                }
            }

            // Validate lines
            foreach ($data['lines'] as $index => $line) {
                if (!isset($line['type'])) {
                    $errors[] = "Line {$index}: Missing type";
                }
                if (!isset($line['section'])) {
                    $errors[] = "Line {$index}: Missing section";
                }
            }

        } catch (\Exception $e) {
            $errors[] = 'Error parsing JSON: ' . $e->getMessage();
        }

        return $errors;
    }

    /**
     * Clone template with all lines
     */
    public function clone(InvoiceTemplate $template, ?string $newName = null): InvoiceTemplate
    {
        $json = $this->export($template);
        $data = json_decode($json, true);

        if ($newName) {
            $data['template']['name'] = $newName;
        } else {
            $data['template']['name'] = $template->name . ' (Copy)';
        }

        return $this->import(json_encode($data));
    }
}
