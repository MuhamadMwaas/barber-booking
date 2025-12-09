<?php

namespace App\Livewire;

use App\Models\ProviderScheduledWork;
use App\Models\User;
use Livewire\Component;

class ShiftManager extends Component
{
    public ?int $userId = null;
    public array $shifts = [];
    public bool $readOnly = false;

    // Days of the week
    public array $daysOfWeek = [
        0 => 'sunday',
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
    ];

    public function mount(?int $userId = null, bool $readOnly = false): void
    {
        $this->userId = $userId;
        $this->readOnly = $readOnly;

        if ($this->userId) {
            $this->loadShifts();
        }
    }

    public function loadShifts(): void
    {
        if (!$this->userId) {
            return;
        }

        // Initialize empty shifts array
        $this->shifts = [];

        foreach ($this->daysOfWeek as $dayNum => $dayName) {
            $this->shifts[$dayNum] = [
                'is_work_day' => false,
                'items' => [],
            ];
        }

        // Load existing shifts from database
        $existingShifts = ProviderScheduledWork::where('user_id', $this->userId)
            ->where('is_active', true)
            ->orderBy('day_of_week')
            ->get();

        foreach ($existingShifts as $shift) {
            $dayNum = $shift->day_of_week;

            if (!isset($this->shifts[$dayNum])) {
                continue;
            }

            $this->shifts[$dayNum]['is_work_day'] = $shift->is_work_day;

            if ($shift->is_work_day && $shift->start_time && $shift->end_time) {
                $this->shifts[$dayNum]['items'][] = [
                    'id' => $shift->id,
                    'start_time' => substr($shift->start_time, 0, 5), // HH:MM format
                    'end_time' => substr($shift->end_time, 0, 5),
                    'break_minutes' => $shift->break_minutes ?? 0,
                ];
            }
        }
    }

    public function addShift(int $dayNum): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->shifts[$dayNum]['is_work_day'] = true;
        $this->shifts[$dayNum]['items'][] = [
            'id' => null,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'break_minutes' => 0,
        ];
    }

    public function removeShift(int $dayNum, int $index): void
    {
        if ($this->readOnly) {
            return;
        }

        unset($this->shifts[$dayNum]['items'][$index]);
        $this->shifts[$dayNum]['items'] = array_values($this->shifts[$dayNum]['items']);

        if (empty($this->shifts[$dayNum]['items'])) {
            $this->shifts[$dayNum]['is_work_day'] = false;
        }
    }

    public function toggleDayOff(int $dayNum): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->shifts[$dayNum]['is_work_day'] = !$this->shifts[$dayNum]['is_work_day'];

        if (!$this->shifts[$dayNum]['is_work_day']) {
            $this->shifts[$dayNum]['items'] = [];
        }
    }

    public function saveShifts(): void
    {
        if ($this->readOnly || !$this->userId) {
            return;
        }

        // Validate
        $this->validate([
            'shifts.*.items.*.start_time' => 'required|date_format:H:i',
            'shifts.*.items.*.end_time' => 'required|date_format:H:i|after:shifts.*.items.*.start_time',
            'shifts.*.items.*.break_minutes' => 'nullable|integer|min:0',
        ]);

        // Delete all existing shifts for this user
        ProviderScheduledWork::where('user_id', $this->userId)->delete();

        // Create new shifts
        foreach ($this->shifts as $dayNum => $dayData) {
            if (!$dayData['is_work_day']) {
                // Create a day off record
                ProviderScheduledWork::create([
                    'user_id' => $this->userId,
                    'day_of_week' => $dayNum,
                    'is_work_day' => false,
                    'is_active' => true,
                ]);
                continue;
            }

            foreach ($dayData['items'] as $item) {
                ProviderScheduledWork::create([
                    'user_id' => $this->userId,
                    'day_of_week' => $dayNum,
                    'start_time' => $item['start_time'] . ':00',
                    'end_time' => $item['end_time'] . ':00',
                    'break_minutes' => $item['break_minutes'] ?? 0,
                    'is_work_day' => true,
                    'is_active' => true,
                ]);
            }
        }

        // Show success message
        session()->flash('success', __('schedule.messages.saved_successfully', ['count' => $this->countTotalShifts()]));

        // Reload shifts
        $this->loadShifts();
    }

    protected function countTotalShifts(): int
    {
        $count = 0;
        foreach ($this->shifts as $dayData) {
            $count += count($dayData['items'] ?? []);
        }
        return $count;
    }

    public function render()
    {
        return view('livewire.shift-manager');
    }
}
