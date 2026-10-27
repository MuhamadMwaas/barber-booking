{{--
    A leave over open bookings (LeaveReassignmentService), in Filament.
    Expects: $plan (LeaveReassignmentService::plan()), $step ('conflicts' | 'plan'), $changed (bool).
    The panel has no compiled theme, so the styles are scoped here (lrp-).
--}}
<style>
    .lrp { display: flex; flex-direction: column; gap: 1rem; font-size: 0.875rem; }
    .lrp-notice { border-radius: 0.5rem; border: 1px solid #fde68a; background: #fffbeb; color: #92400e; padding: 0.75rem 1rem; }
    .dark .lrp-notice { border-color: rgba(251, 191, 36, 0.3); background: rgba(251, 191, 36, 0.08); color: #fcd34d; }
    .lrp-heading { font-weight: 600; margin: 0; }
    .lrp-heading.is-move { color: #047857; }
    .lrp-heading.is-cancel { color: #dc2626; }
    .dark .lrp-heading.is-move { color: #34d399; }
    .dark .lrp-heading.is-cancel { color: #f87171; }
    .lrp-hint { margin: 0.125rem 0 0.5rem; font-size: 0.75rem; color: #6b7280; }
    .lrp-scroll { overflow-x: auto; }
    .lrp-table { width: 100%; border-collapse: collapse; }
    .lrp-table th { text-align: start; font-size: 0.75rem; font-weight: 500; color: #6b7280; padding: 0.5rem 0.75rem 0.5rem 0; border-bottom: 1px solid #e5e7eb; }
    .lrp-table td { padding: 0.5rem 0.75rem 0.5rem 0; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
    .dark .lrp-table th { border-color: rgba(255, 255, 255, 0.1); }
    .dark .lrp-table td { border-color: rgba(255, 255, 255, 0.05); }
    .lrp-time { white-space: nowrap; }
    .lrp-muted { color: #6b7280; }
    .lrp-target { font-weight: 600; color: #047857; }
    .dark .lrp-target { color: #34d399; }
</style>

@php
    $rows = collect($plan['rows'] ?? []);
    $groups = $step === 'conflicts'
        ? [['action' => null, 'rows' => $rows]]
        : collect(['move', 'cancel', 'blocked'])
            ->map(fn (string $action) => ['action' => $action, 'rows' => $rows->where('action', $action)])
            ->filter(fn (array $group) => $group['rows']->isNotEmpty())
            ->all();
    $headings = [
        'move' => ['dashboard.leave_conflicts.moves_heading', 'dashboard.leave_conflicts.moves_hint'],
        'cancel' => ['dashboard.leave_conflicts.cancellations_heading', 'dashboard.leave_conflicts.cancellations_hint'],
        'blocked' => ['dashboard.leave_conflicts.blocked_heading', 'dashboard.leave_conflicts.blocked_hint'],
    ];
@endphp

<div class="lrp">
    @if ($step === 'conflicts')
        <div class="lrp-notice">{{ __('dashboard.leave_conflicts.warning', ['count' => $rows->count()]) }}</div>
    @elseif ($changed)
        <div class="lrp-notice">{{ __('dashboard.leave_conflicts.changed') }}</div>
    @endif

    @foreach ($groups as $group)
        <div>
            @if ($group['action'])
                <p class="lrp-heading is-{{ $group['action'] }}">
                    {{ __($headings[$group['action']][0], ['count' => $group['rows']->count()]) }}</p>
                <p class="lrp-hint">{{ __($headings[$group['action']][1]) }}</p>
            @endif

            <div class="lrp-scroll">
                <table class="lrp-table">
                    <thead>
                        <tr>
                            <th>{{ __('dashboard.leave_conflicts.col_number') }}</th>
                            <th>{{ __('dashboard.leave_conflicts.col_time') }}</th>
                            <th>{{ __('dashboard.leave_conflicts.col_customer') }}</th>
                            <th>{{ __('dashboard.leave_conflicts.col_services') }}</th>
                            @if ($group['action'] === 'move')
                                <th>{{ __('dashboard.leave_conflicts.col_new_provider') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group['rows'] as $row)
                            <tr>
                                <td class="lrp-time lrp-muted" dir="ltr">#{{ $row['number'] }}</td>
                                <td class="lrp-time" dir="ltr">{{ $row['date_label'] }} {{ $row['start_time'] }}–{{ $row['end_time'] }}</td>
                                <td>{{ $row['customer_name'] }}</td>
                                <td class="lrp-muted">{{ $row['services'] }}</td>
                                @if ($group['action'] === 'move')
                                    <td class="lrp-target">{{ $row['target_provider_name'] }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
