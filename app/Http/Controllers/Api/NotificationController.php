<?php
namespace App\Http\Controllers\Api;

use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\OneSignalService;

use function PHPSTORM_META\type;

class NotificationController
{
    public function __construct(
        protected OneSignalService $oneSignal,
        protected NotificationService $notificationService,
    ) {
    }

    public function testSendToAll()
    {
        // $response = $this->oneSignal->sendToAll(
        //     'Test from Laravel',
        //     'هذه رسالة تجريبية من Backend Laravel.'
        // );
        $user = request()->user();

              $notifications = $user
            ->notifications()
            ->latest()
            ->paginate(15);


        return NotificationResource::collection($notifications);

        $this->notificationService->sendNotificationToUser(
            $user,
            "notification.new_apointment",
            'notification.new_apointment_message',
            [
                'service' => [
                    'type' => 'value',
                    'value' => 'العناية بالبشرة'
                ],
                'date' => [
                    'type' => 'value',
                    'value' => '2023-04-15'
                ],
                'time' => [
                    'type' => 'value',
                    'value' => '15:30'
                ],
                'payment_type' => [
                    'type' => 'translate',
                    'value' => 'payment_type.cash'
                ],
            ],
            [
                'tamplate' => 'new_apointment',
                'appointment_id' => 1,
                'user_id' => $user->id,
                'service_id' => 1,

            ]

        );

    }

}
