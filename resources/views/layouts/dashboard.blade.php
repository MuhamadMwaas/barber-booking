<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('dashboard.title') }} - {{ config('app.name') }}</title>
    {{-- <script src="https://cdn.tailwindcss.com"></script> --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @livewireStyles
    <style>
        [x-cloak] { display: none !important; }
        * { font-family: 'Inter', sans-serif; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #a1a1a1; }
        .timeline-grid-line { border-top: 1px solid #e5e7eb; }
        .timeline-grid-line-hour { border-top: 1px solid #d1d5db; }
        .appointment-card { transition: all 0.15s ease; cursor: pointer; }
        .appointment-card:hover { transform: scale(1.02); z-index: 20 !important; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .time-off-block { background: repeating-linear-gradient(45deg, transparent, transparent 5px, rgba(0,0,0,0.03) 5px, rgba(0,0,0,0.03) 10px); }
        .calendar-day { transition: all 0.1s ease; }
        .calendar-day:hover { background: #f3f4f6; }
        .calendar-day.selected { background: #fbbf24; color: #000; font-weight: 600; }
        .calendar-day.today { border: 2px solid #f59e0b; }
        .provider-check { transition: opacity 0.2s ease; }
        .modal-overlay { background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); }
        .status-pending { border-left: 4px solid #f59e0b; }
        .status-completed { border-left: 4px solid #10b981; }
        .status-cancelled { border-left: 4px solid #ef4444; }
        .status-no-show { border-left: 4px solid #6b7280; }
        .drag-selection { background: rgba(59, 130, 246, 0.15); border: 2px dashed #3b82f6; border-radius: 4px; pointer-events: none; }
        .toast-notification { animation: slideIn 0.3s ease, fadeOut 0.3s ease 2.7s; }
        @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
        [wire\:loading] { pointer-events: none; }
    </style>
</head>
<body class="bg-gray-100 h-screen overflow-hidden">
    <div id="toast-container" class="fixed top-4 right-4 z-[9999] space-y-2"></div>
    {{ $slot }}
    @livewireScripts
    <script>
        window.addEventListener('notify', (e) => {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            const bgColor = e.detail.type === 'error' ? 'bg-red-500' : 'bg-green-500';
            toast.className = `toast-notification ${bgColor} text-white px-4 py-3 rounded-lg shadow-lg text-sm max-w-sm`;
            toast.textContent = e.detail.message;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        });

        window.addEventListener('printInvoice', (e) => {
            const invoiceId = e.detail.invoiceId;
            if (invoiceId) {
                window.open(`/invoice/${invoiceId}/print`, '_blank', 'width=400,height=600');
            }
        });
    </script>
</body>
</html>
