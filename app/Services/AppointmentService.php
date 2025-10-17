<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Carbon\Carbon;

class AppointmentService
{
    /**
     * @param User $customer
     * @param array $filters
     * @return LengthAwarePaginator
     *
     * @throws InvalidArgumentException
     */
    public function getCustomerAppointments(User $customer, array $filters = []): LengthAwarePaginator
    {
        try {
            $query = Appointment::where('customer_id', $customer->id)
                ->with([
                    'provider:id,first_name,last_name,email,phone,avatar_url',
                    'services',
                    'services_record',
                ]);

            if (!empty($filters['status']) && $filters['status'] !== 'ALL') {
                $statusValue = $this->parseStatus($filters['status']);
                $query->where('status', $statusValue);
            }

            if (!empty($filters['payment_status'])) {
                $paymentStatusValue = $this->parsePaymentStatus($filters['payment_status']);
                $query->where('payment_status', $paymentStatusValue);
            }

            if (!empty($filters['date_from'])) {
                $dateFrom = Carbon::createFromFormat('Y-m-d', $filters['date_from'])
                    ->startOfDay();
                $query->where('appointment_date', '>=', $dateFrom);
            }

            if (!empty($filters['date_to'])) {
                $dateTo = Carbon::createFromFormat('Y-m-d', $filters['date_to'])
                    ->endOfDay();
                $query->where('appointment_date', '<=', $dateTo);
            }

            if (!empty($filters['type'])) {
                if ($filters['type'] === 'upcoming') {
                    $query->where('start_time', '>', now());
                } elseif ($filters['type'] === 'past') {
                    $query->where('start_time', '<', now());
                }
            }

            $sortBy = $filters['sort_by'] ?? 'appointment_date';
            $sortDirection = strtoupper($filters['sort_direction'] ?? 'desc');

            $validSortColumns = ['appointment_date', 'created_at', 'total_amount', 'start_time'];
            if (!in_array($sortBy, $validSortColumns)) {
                $sortBy = 'appointment_date';
            }

            if (!in_array($sortDirection, ['ASC', 'DESC'])) {
                $sortDirection = 'DESC';
            }

            $query->orderBy($sortBy, $sortDirection);

            $perPage = min((int)($filters['per_page'] ?? 15), 100);
            $perPage = max($perPage, 3);

            return $query->paginate($perPage);

        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                __('message.error_in') . $e->getMessage()
            );
        }
    }

    /**
     *
     * @param int $appointmentId
     * @param User $custome
     * @return Appointment
     *
     * @throws ModelNotFoundException
     * @throws InvalidArgumentException
     */
    public function getAppointmentDetails(int $appointmentId, User $customer): Appointment
    {
        try {
            $appointment = Appointment::with([
                'customer:id,first_name,last_name,email,phone,avatar_url,created_at',
                'provider:id,first_name,last_name,email,phone,avatar_url,branch_id',
                'provider.branch:id,name,adress,phone,email,latitude,longitude',
                'services:id,name,description,price,discount_price,duration_minutes,image_url,color_code',
                'services.category:id,name,description',
                'services_record',
            ])->findOrFail($appointmentId);


            if ($appointment->customer_id !== $customer->id) {
                throw new InvalidArgumentException(
                    __('message.you_have_authorize_to')
                );
            }

            return $appointment;

        } catch (ModelNotFoundException $e) {
            throw new ModelNotFoundException(
                'الحجز المطلوب غير موجود'
            );
        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                'حدث خطأ في جلب بيانات الحجز: ' . $e->getMessage()
            );
        }
    }

    /**
     * @param User $customer
     * @return array
     */
    public function getAppointmentStatistics(User $customer): array
    {
        try {
            $customerAppointments = Appointment::where('customer_id', $customer->id);

            return [
                'total_appointments' => (clone $customerAppointments)->count(),
                'pending_count' => (clone $customerAppointments)
                    ->where('status', AppointmentStatus::PENDING->value)
                    ->count(),
                'completed_count' => (clone $customerAppointments)
                    ->where('status', AppointmentStatus::COMPLETED->value)
                    ->count(),
                'cancelled_count' => (clone $customerAppointments)
                    ->whereIn('status', [
                        AppointmentStatus::USER_CANCELLED->value,
                        AppointmentStatus::ADMIN_CANCELLED->value,
                    ])
                    ->count(),
                'total_spent' => (clone $customerAppointments)
                    ->where('status', AppointmentStatus::COMPLETED->value)
                    ->sum('total_amount'),
                'upcoming_count' => (clone $customerAppointments)
                    ->where('status', AppointmentStatus::PENDING->value)
                    ->where('start_time', '>', now())
                    ->count(),
                'payment_pending_count' => (clone $customerAppointments)
                    ->where('payment_status', PaymentStatus::PENDING->value)
                    ->count(),
            ];

        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                'حدث خطأ في جلب الإحصائيات: ' . $e->getMessage()
            );
        }
    }

    /**
     * @param int $appointmentId
     * @param User $customer
     * @param string $reason
     * @return Appointment
     *
     * @throws InvalidArgumentException
     */
    public function cancelAppointment(int $appointmentId, User $customer, string $reason = ''): Appointment
    {
        try {
            $appointment = $this->getAppointmentDetails($appointmentId, $customer);

            if ($appointment->status !== AppointmentStatus::PENDING) {
                throw new InvalidArgumentException(
                    'لا يمكن إلغاء الحجز في حالته الحالية'
                );
            }

            if ($appointment->start_time <= now()) {
                throw new InvalidArgumentException(
                    'لا يمكن إلغاء الحجز بعد بدء الموعد'
                );
            }

            $appointment->cancel($reason);

            return $appointment;

        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                'حدث خطأ في إلغاء الحجز: ' . $e->getMessage()
            );
        }
    }

    /**
     *
     * @param User $customer
     * @param int $days
     * @return Collection
     */
    public function getUpcomingAppointments(User $customer, int $days = 7): Collection
    {
        try {
            $futureDate = now()->addDays($days);

            return Appointment::where('customer_id', $customer->id)
                ->where('status', AppointmentStatus::PENDING->value)
                ->where('start_time', '>=', now())
                ->where('start_time', '<=', $futureDate)
                ->with(['provider:id,first_name,last_name', 'services:id,name'])
                ->orderBy('start_time', 'asc')
                ->get();

        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                'حدث خطأ في جلب الحجوزات المقبلة: ' . $e->getMessage()
            );
        }
    }

    /**
     * @param User $customer
     * @param int $limit
     * @return Collection
     */
    public function getPastAppointments(User $customer, int $limit = 10): Collection
    {
        try {
            return Appointment::where('customer_id', $customer->id)
                ->where('status', AppointmentStatus::COMPLETED->value)
                ->with(['provider:id,first_name,last_name', 'services:id,name'])
                ->orderBy('start_time', 'desc')
                ->limit($limit)
                ->get();

        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                'حدث خطأ في جلب الحجوزات السابقة: ' . $e->getMessage()
            );
        }
    }

    /**
     * @param User $customer
     * @param string $search
     * @return Collection
     */
    public function searchAppointments(User $customer, string $search): Collection
    {
        try {
            $search = trim($search);

            if (strlen($search) < 2) {
                throw new InvalidArgumentException(
                    'يجب أن يكون البحث بأكثر من حرف واحد'
                );
            }

            return Appointment::where('customer_id', $customer->id)
                ->where(function ($query) use ($search) {
                    $query->where('number', 'like', "%{$search}%")
                        ->orWhereHas('provider', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                })
                ->with(['provider:id,first_name,last_name', 'services:id,name'])
                ->orderBy('appointment_date', 'desc')
                ->get();

        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                'حدث خطأ في البحث: ' . $e->getMessage()
            );
        }
    }

    /**
     * @param string $status
     * @return int
     */
    private function parseStatus(string $status): int
    {
        $statusMap = [
            'PENDING' => AppointmentStatus::PENDING->value,
            'COMPLETED' => AppointmentStatus::COMPLETED->value,
            'USER_CANCELLED' => AppointmentStatus::USER_CANCELLED->value,
            'ADMIN_CANCELLED' => AppointmentStatus::ADMIN_CANCELLED->value,
        ];

        if (!isset($statusMap[$status])) {
            throw new InvalidArgumentException(
                "حالة غير صحيحة: {$status}"
            );
        }

        return $statusMap[$status];
    }

    /**
     * @param string $paymentStatus
     * @return int
     */
    private function parsePaymentStatus(string $paymentStatus): int
    {
        $paymentStatusMap = [
            'PENDING' => PaymentStatus::PENDING->value,
            'PAID_ONLINE' => PaymentStatus::PAID_ONLINE->value,
            'PAID_ONSTIE_CASH' => PaymentStatus::PAID_ONSTIE_CASH->value,
            'PAID_ONSTIE_CARD' => PaymentStatus::PAID_ONSTIE_CARD->value,
            'FAILED' => PaymentStatus::FAILED->value,
            'REFUNDED' => PaymentStatus::REFUNDED->value,
            'PARTIALLY_REFUNDED' => PaymentStatus::PARTIALLY_REFUNDED->value,
        ];

        if (!isset($paymentStatusMap[$paymentStatus])) {
            throw new InvalidArgumentException(
                "حالة دفع غير صحيحة: {$paymentStatus}"
            );
        }

        return $paymentStatusMap[$paymentStatus];
    }
}
