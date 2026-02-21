<?php

namespace Database\Seeders;

use App\Models\PrinterSetting;
use Illuminate\Database\Seeder;

class PrinterSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {
        // Default USB Printer
        PrinterSetting::create([
            'name' => 'Main POS Printer (USB)',
            'printer_name' => 'EPSON TM-T20III',
            'description' => 'Main thermal printer connected via USB',
            'connection_type' => 'usb',
            'paper_size' => '80mm',
            'default_copies' => 1,
            'print_method' => 'browser',
            'is_active' => true,
            'is_default' => true,
        ]);

        // Network Printer Example
        PrinterSetting::create([
            'name' => 'Kitchen Printer (Network)',
            'printer_name' => 'Star TSP143',
            'description' => 'Kitchen printer connected via network',
            'connection_type' => 'network',
            'ip_address' => '192.168.1.100',
            'port' => 9100,
            'paper_size' => '80mm',
            'default_copies' => 1,
            'print_method' => 'browser',
            'is_active' => true,
            'is_default' => false,
        ]);

        // Compact 58mm Printer
        PrinterSetting::create([
            'name' => 'Mobile POS (58mm)',
            'printer_name' => 'Bixolon SRP-275',
            'description' => 'Compact 58mm receipt printer',
            'connection_type' => 'usb',
            'paper_size' => '58mm',
            'default_copies' => 1,
            'print_method' => 'browser',
            'is_active' => false,
            'is_default' => false,
        ]);
    }
}
