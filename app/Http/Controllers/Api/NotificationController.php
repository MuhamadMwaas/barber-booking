<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\OneSignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected OneSignalService $oneSignal,
        protected NotificationService $notificationService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:200',
            'cursor' => 'nullable|string',
            'status' => 'nullable|in:all,read,unread',
        ]);

        $perPage = $validated['per_page'] ?? 15;
        $status = $validated['status'] ?? 'all';

        $query = $request->user()->notifications()->latest();

        if ($status === 'unread') {
            $query->whereNull('read_at');
        } elseif ($status === 'read') {
            $query->whereNotNull('read_at');
        }

        $notifications = $query->cursorPaginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Notifications retrieved successfully',
            'data' => NotificationResource::collection(collect($notifications->items()))->resolve($request),
            'pagination' => [
                'per_page' => $notifications->perPage(),
                'next_cursor' => $notifications->nextCursor()?->encode(),
                'prev_cursor' => $notifications->previousCursor()?->encode(),
                'has_more_pages' => $notifications->hasMorePages(),
            ],
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Unread notifications count retrieved successfully',
            'data' => [
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $notificationId): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->whereKey($notificationId)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
            $notification->refresh();
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read successfully',
            'data' => (new NotificationResource($notification))->resolve($request),
        ]);
    }

    public function testSendToAll()
    {
        $this->oneSignal->sendToAll(
            'Test from Laravel',
            'هذه رسالة تجريبية من Backend Laravel.'
        );

        $user = request()->user();

        $notifications = $user
            ->notifications()
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Test notification sent successfully',
            'data' => NotificationResource::collection($notifications)->resolve(request()),
        ]);
    }

    public function testSendToAllCustomers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'   => 'nullable|string|max:255',
            'message' => 'nullable|string|max:1000',
        ]);

        $titleKey   = $validated['title']   ?? 'Test Notification';
        $messageKey = $validated['message'] ?? 'هذه رسالة تجريبية من Backend Laravel لجميع العملاء.';

        // 1. Get all customers
        $customers = User::role('customer')->get();

        // 2. Send database notification to every customer
        $this->notificationService->sendToPhoneUsersDatabase($customers, $titleKey, $messageKey, [], []);

        // 3. Build localized arrays for all configured push locales (en, ar, de ...)
        //    This mirrors exactly how sendNotificationToUser() works internally.
        $localizedTitle   = $this->notificationService->translateForPushLocales($titleKey);
        $localizedMessage = $this->notificationService->translateForPushLocales($messageKey);

        // 4. OneSignal broadcast to ALL subscribers with full locale support
        $this->oneSignal->sendToAll($localizedTitle, $localizedMessage);

        return response()->json([
            'success'          => true,
            'message'          => 'Test notification sent to all customers successfully',
            'customers_count'  => $customers->count(),
            'locales_sent'     => array_keys($localizedTitle),
        ]);
    }
}
