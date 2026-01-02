<?php
namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;
use App\Notifications\PhoneNotification;
use App\Notifications\ToAdminNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotificationService
{

    public function __construct(
        protected OneSignalService $oneSignal
    ) {
    }

    public function sendNotificationToUser(
        Collection|array|User $users,
        string $titleKey,
        string $messageKey,
        array $params = [],
        array $data = []
    ) {

        if (empty($users)) {
            return;
        }

        if ($users instanceof Collection) {
            $deviceIds = $this->resolveDeviceIdsForUsers($users);
        } elseif (is_array($users)) {
            $usersCollection = User::whereIn('id', $users)->get();
            $deviceIds = $this->resolveDeviceIdsForUsers($usersCollection);
        } elseif ($users instanceof User) {
            $deviceIds = $this->resolveDeviceIdsForUsers(collect([$users]));
        }

        Log::info('Sending notification', [
            'title_key' => $titleKey,
            'message_key' => $messageKey,
            'params' => $params,
            'data' => $data,
            'device_ids_count' => count($deviceIds),
        ]);


        $translatedTitle = $this->translateKey($titleKey, $params);
        $translatedMessage = $this->translateKey($messageKey, $params);

        if (!empty($deviceIds)) {
            $this->sendToDeviceIds($deviceIds, $translatedTitle, $translatedMessage, $data);
        }
        $this->sendToPhoneUsersDatabase($users, $titleKey, $messageKey, $data, $params);


    }

    public function resolveDeviceIdsForUsers(Collection $users): array
    {
        return UserDevice::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->where('is_active', true)
            ->pluck('device_id')
            ->unique()
            ->values()
            ->toArray();
    }

    public function sendToDeviceIds(
        array $deviceIds,
        string $title,
        string $message,
        array $data = [],
        array $params = []
    ): void {
        if (empty($deviceIds)) {
            return;
        }

        try {

            $this->oneSignal->sendToMany($deviceIds, $title, $message, $data);
        } catch (\Throwable $e) {
            Log::error('Failed to send push notification', [
                'error' => $e->getMessage(),
                'device_ids_count' => count($deviceIds),
            ]);
        }
    }

    public function notificationTranslationResolve(string $key, array $data = [], ?string $locale = null)
    {
        $paramsKey = $key . '_param';

        $params = $data[$paramsKey] ?? [];

        return __($key, $params, $locale);
    }
    // protected function translateKey(string $key, array $params = [], ?string $locale = null): string
    // {
    //     if (is_null($locale)) {
    //         $locale = app()->getLocale();
    //     }
    //     return __($key, $params, $locale);
    // }

    public function translateKey(string $key, array $params = [], ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $resolvedParams = [];

        foreach ($params as $paramKey => $paramConfig) {
            // إذا لم تكن بنية جديدة → قيمة مباشرة
            if (!is_array($paramConfig) || !isset($paramConfig['type'])) {
                $resolvedParams[$paramKey] = $paramConfig;
                continue;
            }

            $resolvedParams[$paramKey] = $this->resolveParam(
                $paramConfig['type'],
                $paramConfig ?? null,
                $locale,
                $paramConfig
            );
        }

        return __($key, $resolvedParams, $locale);
    }

    public function sendToPhoneUsersDatabase($users, $title, $message, $data = [], $params = [])
    {
        Notification::send(
            $users,
            new PhoneNotification(
                $title,
                $message,
                $data,
                $params ?? [],
            )
        );
    }


    public function resolveParam(
        string $type,
        mixed $value,
        string $locale,
        array $config = []
    ): mixed {
        $resolverClass = '\\App\\Notifications\\Params\\' . ucfirst($type) . 'ParamResolver';
        if (!class_exists($resolverClass)) {
            throw new \RuntimeException("Notification param resolver [$type] not found.");
        }

        return app($resolverClass)->resolve($value, $locale, $config);
    }


    public function sendToAdminNotification(
        Collection|array|User $users,
        string $titleKey,
        string $messageKey,
        array $params = [],
        array $data = []
    ) {

        Notification::send(
            $users,
            new ToAdminNotification(
                $titleKey,
                $messageKey,
                $data,
                $params ?? [],
            )
        );
    }

}
