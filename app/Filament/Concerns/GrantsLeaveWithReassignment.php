<?php

namespace App\Filament\Concerns;

use App\Exceptions\LeaveReassignmentRequiredException;
use App\Models\ProviderTimeOff;
use App\Models\User;
use App\Services\LeaveReassignmentService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * The Filament half of LeaveReassignmentService, shared by every admin surface
 * that writes a leave (ViewProvider's two header actions, the leaves relation
 * manager's create + edit).
 *
 * A leave over open bookings is not saved. Instead the mounted action is
 * swapped for "resolveLeaveConflicts" (which bookings are in the way), whose
 * submit swaps to "confirmLeaveReassignment" (who takes which, what gets
 * cancelled), whose submit grants the leave.
 *
 * The leave travels between the modals as action ARGUMENTS — client state, so
 * nothing in them is trusted for who or what is written:
 *  - the provider is always the page's own (leaveOwner()), never `user_id`
 *    from the arguments;
 *  - an edited leave is looked up among that provider's leaves only;
 *  - create vs edit follows from whether a leave id is present, and the
 *    matching ProviderTimeOff permission is required — at mount (authorize)
 *    and again when the action runs;
 *  - the leave's shape is re-validated by the service on every step.
 */
trait GrantsLeaveWithReassignment
{
    /** The provider whose page this is — the only one a leave here can be for. */
    abstract protected function leaveOwner(): User;

    /**
     * May the current user write a leave? Leave management is admin-only
     * (PermissionsSeeder grants ProviderTimeOff:* to `admin` alone), and that
     * permission also covers the cancellations a leave may bring with it.
     *
     * @param  'create'|'edit'  $operation
     */
    protected function mayWriteLeave(string $operation): bool
    {
        $user = filament()->auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('SuperAdmin') || $user->can("ProviderTimeOff:{$operation}");
    }

    /**
     * Save the leave, or hand over to the conflict modals.
     *
     * @param  array<string, mixed>  $data  the leave's columns, as they would be saved
     * @return ProviderTimeOff|null the saved leave; null when a modal took over
     */
    protected function grantLeaveOrResolve(array $data, ?ProviderTimeOff $record, Action $action): ?ProviderTimeOff
    {
        $arguments = [
            'leave' => $this->leaveColumns($data),
            'time_off_id' => $record?->getKey(),
        ];

        try {
            return app(LeaveReassignmentService::class)
                ->grant($this->leaveFromArguments($arguments))['time_off'];
        } catch (LeaveReassignmentRequiredException $e) {
            $this->replaceMountedAction('resolveLeaveConflicts', [...$arguments, 'plan' => $e->plan]);

            // Ends this action without its success notification; the swapped-in
            // modal stays open.
            $action->halt();
        } catch (InvalidArgumentException $e) {
            $this->leaveRejected($e, $action);
        }

        return null;
    }

    public function resolveLeaveConflictsAction(): Action
    {
        return Action::make('resolveLeaveConflicts')
            ->authorize(fn (array $arguments): bool => $this->mayWriteLeave($this->operationOf($arguments)))
            ->modalHeading(__('dashboard.leave_conflicts.title'))
            ->modalWidth(Width::TwoExtraLarge)
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->modalContent(fn (array $arguments) => view('filament.modals.leave-reassignment-plan', [
                'plan' => $arguments['plan'] ?? [],
                'step' => 'conflicts',
                'changed' => false,
            ]))
            ->modalSubmitActionLabel(__('dashboard.leave_conflicts.try_reassign'))
            ->action(function (array $arguments, Action $action) {
                $this->ensureMayWriteLeave($arguments);

                try {
                    // Fresh plan: the user may have looked at the list for a while.
                    $plan = app(LeaveReassignmentService::class)->plan($this->leaveFromArguments($arguments));
                } catch (InvalidArgumentException $e) {
                    $this->leaveRejected($e, $action);
                }

                $this->replaceMountedAction('confirmLeaveReassignment', [
                    'leave' => $arguments['leave'] ?? [],
                    'time_off_id' => $arguments['time_off_id'] ?? null,
                    'plan' => $plan,
                    'changed' => false,
                ]);

                $action->halt();
            });
    }

    public function confirmLeaveReassignmentAction(): Action
    {
        return Action::make('confirmLeaveReassignment')
            ->authorize(fn (array $arguments): bool => $this->mayWriteLeave($this->operationOf($arguments)))
            ->modalHeading(__('dashboard.leave_conflicts.plan_title'))
            ->modalWidth(Width::TwoExtraLarge)
            ->modalContent(fn (array $arguments) => view('filament.modals.leave-reassignment-plan', [
                'plan' => $arguments['plan'] ?? [],
                'step' => 'plan',
                'changed' => (bool) ($arguments['changed'] ?? false),
            ]))
            ->color(fn (array $arguments) => ($arguments['plan']['cancellations'] ?? 0) > 0 ? 'danger' : 'primary')
            ->modalSubmitActionLabel(fn (array $arguments) => ($arguments['plan']['cancellations'] ?? 0) > 0
                ? __('dashboard.leave_conflicts.confirm_with_cancel')
                : __('dashboard.leave_conflicts.confirm'))
            ->modalSubmitAction(fn (Action $submit, array $arguments) => $submit
                ->disabled(($arguments['plan']['blocked'] ?? 0) > 0))
            ->action(function (array $arguments, Action $action) {
                $this->ensureMayWriteLeave($arguments);

                try {
                    $result = app(LeaveReassignmentService::class)->grant(
                        $this->leaveFromArguments($arguments),
                        (string) ($arguments['plan']['signature'] ?? ''),
                    );
                } catch (LeaveReassignmentRequiredException $e) {
                    // The calendar moved under the plan: show the fresh one.
                    $this->replaceMountedAction('confirmLeaveReassignment', [
                        'leave' => $arguments['leave'] ?? [],
                        'time_off_id' => $arguments['time_off_id'] ?? null,
                        'plan' => $e->plan,
                        'changed' => true,
                    ]);

                    $action->halt();

                    return;
                } catch (InvalidArgumentException $e) {
                    $this->leaveRejected($e, $action);
                }

                Notification::make()
                    ->title(__('dashboard.leave_conflicts.granted', [
                        'moved' => $result['moved'],
                        'cancelled' => $result['cancelled'],
                    ]))
                    ->success()
                    ->send();
            });
    }

    /** @param  array<string, mixed>  $arguments */
    private function operationOf(array $arguments): string
    {
        return ($arguments['time_off_id'] ?? null) !== null ? 'edit' : 'create';
    }

    /**
     * The second check, inside the action itself: authorize() already refuses
     * to mount, this keeps the rule next to the write it protects.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function ensureMayWriteLeave(array $arguments): void
    {
        if (! $this->mayWriteLeave($this->operationOf($arguments))) {
            throw new AuthorizationException(__('dashboard.permission_denied'));
        }
    }

    /**
     * The leave the arguments describe, bound to this page's provider.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @throws InvalidArgumentException for an unparsable date or time.
     */
    private function leaveFromArguments(array $arguments): ProviderTimeOff
    {
        $owner = $this->leaveOwner();
        $timeOffId = $arguments['time_off_id'] ?? null;

        // Another provider's leave id simply is not found here.
        $leave = $timeOffId !== null
            ? $owner->timeOffs()->findOrFail($timeOffId)
            : new ProviderTimeOff;

        $leave->fill($this->leaveColumns((array) ($arguments['leave'] ?? [])));
        $leave->user_id = $owner->getKey();

        return $leave;
    }

    /**
     * Only the columns a leave form writes — never `user_id` or anything else
     * a crafted request might add.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function leaveColumns(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'type', 'start_date', 'end_date', 'start_time', 'end_time',
            'duration_hours', 'duration_days', 'reason_id',
        ]));
    }

    private function leaveRejected(InvalidArgumentException $e, Action $action): never
    {
        Notification::make()
            ->title($e->getMessage())
            ->danger()
            ->send();

        $action->halt();
    }
}
