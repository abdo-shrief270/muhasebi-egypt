<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Actions;

use App\Modules\Customers\Contracts\CustomerAccounts;
use App\Modules\OnlineStore\Contracts\RepairBookings;
use App\Modules\OnlineStore\Events\RepairBookingPlaced;
use App\Modules\OnlineStore\Models\OnlineStore;
use App\Modules\OnlineStore\Models\RepairBooking;
use App\Support\Audit\Auditor;
use App\Support\Events\EventRecorder;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\ModuleAccess;
use App\Support\Numbering\DocumentNumbers;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * «احجز صيانة»: the customer books on the store; the shop calls them (contacted) or cancels; the
 * intake of the device turns it into a ticket (converted, via the RepairBookings contract).
 */
final class RepairBookingActions implements RepairBookings
{
    /** Bookings still «جديد» from one phone before the store says "wait for the shop to call". */
    public const MAX_NEW_PER_PHONE = 3;

    public function __construct(
        private readonly CustomerAccounts $customers,
        private readonly DocumentNumbers $numbers,
        private readonly EventRecorder $events,
        private readonly ModuleAccess $modules,
        private readonly Auditor $audit,
        private readonly Auth $auth,
    ) {}

    public function book(OnlineStore $store, ?string $id, string $name, string $phone, string $device, string $problem, ?string $preferredOn, bool $consent): RepairBooking
    {
        // The same form sent twice (a retry after a timeout) is saved once.
        if ($id !== null && ($existing = RepairBooking::query()->find($id)) !== null) {
            return $existing;
        }
        if (! self::offered($store, $this->modules)) {
            throw new DomainRuleException('المحل مش بياخد حجز صيانة من المتجر. كلّمه على واتساب.', 'repair_booking_off', 409);
        }
        if (RepairBooking::query()->where('customer_phone', $phone)->where('status', 'new')->count() >= self::MAX_NEW_PER_PHONE) {
            throw new DomainRuleException('عندك حجوزات لسه المحل ماكلّمكش فيها. استنى مكالمة المحل أو كلّمه على واتساب.', 'too_many_bookings', 429);
        }

        try {
            return DB::transaction(function () use ($store, $id, $name, $phone, $device, $problem, $preferredOn, $consent): RepairBooking {
                $customer = $consent ? $this->customers->findOrCreate($name, $phone, true) : null;
                $booking = new RepairBooking([
                    'tenant_id' => $store->tenant_id,
                    'number' => $this->numbers->next($store->tenant_id, 'repair_booking'),
                    'status' => 'new',
                    'customer_id' => $customer?->id,
                    'customer_name' => $name,
                    'customer_phone' => $phone,
                    'device' => $device,
                    'problem' => $problem,
                    'preferred_on' => $preferredOn,
                    'consent' => $consent,
                ]);
                if ($id !== null) {
                    $booking->id = $id;
                }
                $booking->save();
                $this->events->record(new RepairBookingPlaced($store->tenant_id, $booking->id, $booking->reference(), $device, $preferredOn));

                return $booking;
            });
        } catch (UniqueConstraintViolationException $e) {
            // The same id arrived twice at once: the other request saved it.
            return ($id !== null ? RepairBooking::query()->find($id) : null) ?? throw $e;
        }
    }

    /** The shop called the customer (contacted), or the booking is off (cancelled, with a reason). */
    public function move(RepairBooking $booking, string $to, ?string $reason): RepairBooking
    {
        return DB::transaction(function () use ($booking, $to, $reason): RepairBooking {
            $booking = RepairBooking::query()->lockForUpdate()->findOrFail($booking->id);
            $this->ensureOpen($booking);
            $reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;
            if ($to === 'cancelled' && $reason === null) {
                throw new DomainRuleException('اكتب سبب الإلغاء.', 'cancel_reason_required');
            }
            $booking->update([
                'status' => $to,
                'cancel_reason' => $to === 'cancelled' ? $reason : null,
                'handled_by_name' => $this->auth->guard('sanctum')->user()?->getAttribute('name'),
            ]);
            if ($to === 'cancelled') {
                $this->audit->record('online_store.booking_cancelled', "لغى حجز الصيانة {$booking->reference()}: {$reason}", $booking);
            }

            return $booking;
        });
    }

    public function converted(string $bookingId, string $ticketId, string $ticketReference): void
    {
        $booking = RepairBooking::query()->lockForUpdate()->find($bookingId)
            ?? throw new DomainRuleException('حجز الصيانة ده مش موجود.', 'booking_not_found', 404);
        $this->ensureOpen($booking);
        $booking->update([
            'status' => 'converted',
            'ticket_id' => $ticketId,
            'ticket_reference' => $ticketReference,
            'handled_by_name' => $this->auth->guard('sanctum')->user()?->getAttribute('name'),
        ]);
    }

    /** The store shows «احجز صيانة»: switched on, and the shop can use the repairs module. */
    public static function offered(OnlineStore $store, ModuleAccess $modules): bool
    {
        return $store->repair_booking && $store->isOpen() && $modules->enabled('repairs', $store->tenant_id);
    }

    private function ensureOpen(RepairBooking $booking): void
    {
        if (! $booking->isOpen()) {
            throw new DomainRuleException(
                $booking->status === 'converted' ? "الحجز {$booking->reference()} اتعمله تذكرة ({$booking->ticket_reference})." : "الحجز {$booking->reference()} اتلغى.",
                'booking_closed',
            );
        }
    }
}
