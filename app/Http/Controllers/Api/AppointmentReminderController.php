<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AppointmentReminderStoreRequest;
use App\Http\Resources\AppointmentReminderResource;
use App\Models\Appointment;
use App\Services\AppointmentReminderService;
use App\Services\Reminders\ReminderChannelResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * The customer's reminder control for a booking.
 *
 *   GET    /api/appointments/reminders/options   the lead times + their labels
 *   POST   /api/appointments/reminders           set or change the reminder
 *   GET    /api/appointments/{id}/reminders      read the live reminder
 *   DELETE /api/appointments/{id}/reminders      switch the reminder off
 *
 * Together these back the toggle-plus-dropdown on the booking screen: `options`
 * fills the dropdown, `GET` restores the saved state when the screen reopens,
 * `POST` saves a choice, and `DELETE` is the toggle being switched off. Before
 * this, only POST existed — so the control could be set but never read back or
 * turned off.
 *
 * WHICH channels carry the reminder is not decided here. It is read from the
 * customer's notification settings when the reminder fires; the responses report
 * the CURRENT picture so the app can warn that a saved reminder has nowhere to
 * go.
 */
class AppointmentReminderController extends Controller
{
    public function __construct(
        protected AppointmentReminderService $reminderService,
        protected ReminderChannelResolver $channels
    ) {
    }

    /**
     * The lead times the customer may choose, already translated.
     *
     * Served from the backend rather than hard-coded in the app so the salon can
     * change the wording or the set of options without shipping an app update —
     * the same data-driven approach the settings screen already uses.
     */
    public function options(Request $request): JsonResponse
    {
        $locale = $this->resolveLocale($request);

        $options = collect($this->reminderService->allowedOffsetHours())
            ->map(function (int $hours) use ($locale) {
                $key = "appointment_reminder.options.{$hours}";
                $label = __($key, [], $locale);

                return [
                    'offset_hours' => $hours,
                    // __() returns the key itself when a lead time was added to
                    // the config without a matching label; fall back to a
                    // generated string rather than showing the raw key.
                    'label' => $label === $key
                        ? __('appointment_reminder.option_fallback', ['hours' => $hours], $locale)
                        : $label,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'options' => $options,
                'default_offset_hours' => config('appointment_reminders.default_offset_hours'),
                'texts' => [
                    'title' => __('appointment_reminder.screen.title', [], $locale),
                    'subtitle' => __('appointment_reminder.screen.subtitle', [], $locale),
                    'question' => __('appointment_reminder.screen.question', [], $locale),
                ],
                'channels' => $request->user()
                    ? $this->channels->resolve($request->user())
                    : [],
            ],
        ]);
    }

    /**
     * Set — or replace — the appointment's reminder.
     *
     * Idempotent by design: an appointment holds at most one live reminder, so
     * posting again simply moves it. The app does not have to delete first.
     */
    public function store(AppointmentReminderStoreRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            $appointment = Appointment::where('id', $request->validated('appointment_id'))
                ->where('customer_id', $user->id)
                ->firstOrFail();

            $remindAt = $this->resolveRemindAt($request, $appointment);

            [$params, $data] = $this->reminderService->buildPayload($appointment);

            $reminder = $this->reminderService->rescheduleReminder(
                $appointment,
                $remindAt,
                $params,
                $data
            );

            // Loaded so the resource can derive offset_hours from start_time and
            // resolve the channel picture without extra queries.
            $reminder->setRelation('appointment', $appointment);
            $reminder->setRelation('user', $user);

            return response()->json([
                'success' => true,
                'message' => __('main.appointment.success.reminder_created'),
                'data' => new AppointmentReminderResource($reminder),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => 'business_error',
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => __('main.appointment.errors.not_found'),
                'error_type' => 'not_found',
            ], 404);
        } catch (\Throwable $e) {
            // Throwable, not Exception: a TypeError is an Error and would
            // otherwise escape as a bare 500 with no envelope (BOOK-09).
            Log::error('Failed to create appointment reminder', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('main.appointment.server_error'),
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => 'server_error',
            ], 500);
        }
    }

    /**
     * The appointment's live reminder, or an explicit "none".
     *
     * Returns 200 with `data: null` rather than 404 when there is no reminder:
     * "this booking has no reminder" is a normal state the screen renders (the
     * toggle is simply off), not an error worth an exception path in the app.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();

            $appointment = Appointment::where('id', $id)
                ->where('customer_id', $user->id)
                ->firstOrFail();

            $reminder = $this->reminderService->activeReminderFor($appointment);

            if ($reminder) {
                $reminder->setRelation('appointment', $appointment);
                $reminder->setRelation('user', $user);
            }

            return response()->json([
                'success' => true,
                'data' => $reminder ? new AppointmentReminderResource($reminder) : null,
                // Present either way, so the app can warn about dead channels
                // before the customer sets a reminder as well as after.
                'channels' => $this->channels->resolve($user),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => __('main.appointment.errors.not_found'),
                'error_type' => 'not_found',
            ], 404);
        }
    }

    /**
     * Switch the reminder off.
     *
     * 404 when there is nothing live to cancel, so a double tap on the toggle is
     * distinguishable from a cancellation that did something. The row is retired,
     * never deleted — the history is what lets support answer "did they turn it
     * off, or did we fail to send?".
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();

            $appointment = Appointment::where('id', $id)
                ->where('customer_id', $user->id)
                ->firstOrFail();

            $cancelled = $this->reminderService->cancelRemindersForAppointment($appointment);

            if ($cancelled === 0) {
                return response()->json([
                    'success' => false,
                    'message' => __('main.appointment.errors.reminder_not_found'),
                    'error_type' => 'not_found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => __('main.appointment.success.reminder_deleted'),
                'data' => null,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => __('main.appointment.errors.not_found'),
                'error_type' => 'not_found',
            ], 404);
        }
    }

    /**
     * Which language to render the dropdown labels and screen texts in.
     *
     * AN EXPLICIT REQUEST BEATS THE SAVED PROFILE. `SetApiLocale` has already
     * resolved `?lang=` / `?locale=` / `Accept-Language` into the app locale for
     * every endpoint in the API, and this one must not be the exception: the app
     * can be displaying a language the customer never saved to their account,
     * and a screen where the labels disagree with the rest of the UI is worse
     * than one that ignores a stale profile value.
     *
     * The profile is the fallback for a client that asked for nothing — a
     * customer who set Arabic on their account still sees Arabic.
     *
     * Note this is the locale of the SCREEN, not of the reminder itself: the
     * reminder text is resolved from `appointment_reminders.locale`, captured
     * from the customer's profile when it was scheduled, because the job has no
     * request to read a header from.
     */
    protected function resolveLocale(Request $request): string
    {
        $asked = $request->query('lang')
            ?? $request->query('locale')
            ?? $request->header('Accept-Language');

        if (filled($asked)) {
            // Already validated against the supported list by the middleware —
            // an unsupported value has fallen back to the default by now.
            return app()->getLocale();
        }

        return $request->user()?->locale ?? app()->getLocale();
    }

    /**
     * Turn whichever form the client sent into the moment the reminder fires.
     *
     * `offset_hours` is resolved against the appointment's own start_time in the
     * server's timezone, which is why it is the form new clients should send.
     * `remind_at` is parsed as-is for backward compatibility.
     */
    protected function resolveRemindAt(AppointmentReminderStoreRequest $request, Appointment $appointment): Carbon
    {
        if ($request->filled('offset_hours')) {
            return $this->reminderService->remindAtFromOffset(
                $appointment,
                (int) $request->validated('offset_hours')
            );
        }

        return Carbon::parse($request->validated('remind_at'));
    }
}
