{{-- template-builder.blade.php --}}
<!DOCTYPE html>
<html lang="{{ $template->language }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - {{ $invoice->invoice_number ?? 'DRAFT' }}</title>

    {!! $styles !!}
</head>
<body>
    <div class="invoice-container">
        {{-- Header Section --}}
        @if($template->headerLines()->enabled()->count() > 0)
        <div class="invoice-section header-section">
            @foreach($template->headerLines()->enabled()->ordered()->get() as $line)
                {!! $builder->renderLine($line) !!}
            @endforeach
        </div>
        @endif

        {{-- Body Section --}}
        @if($template->bodyLines()->enabled()->count() > 0)
        <div class="invoice-section body-section">
            @foreach($template->bodyLines()->enabled()->ordered()->get() as $line)
                {!! $builder->renderLine($line) !!}
            @endforeach
        </div>
        @endif

        {{-- Footer Section --}}
        @if($template->footerLines()->enabled()->count() > 0)
        <div class="invoice-section footer-section">
            @foreach($template->footerLines()->enabled()->ordered()->get() as $line)
                {!! $builder->renderLine($line) !!}
            @endforeach
        </div>
        @endif
    </div>

    {{-- Print Script --}}
    <script>
        function printInvoice() {
            window.print();

        }

        window.onload = function() { window.print(); };
    </script>
</body>
</html>

