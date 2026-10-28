<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


class RiderController extends Controller
{
    // Statuses that count as a finished delivery (matches the app)
    private const DONE = ['delivered', 'completed'];

    /**
     * Rider info for a booking: { id, name, phone } or null.
     * Only returns a user whose role is "rider".
     */
    private function riderPayload($riderId): ?array
    {
        if (! $riderId) {
            return null;
        }

        $user = User::whereRaw('LOWER(role) = ?', ['rider'])->find($riderId);

        if (! $user) {
            return null;
        }

        return [
            'id'    => $user->id,
            'name'  => $user->name,
            'phone' => $user->phone, // null if the column doesn't exist
        ];
    }

    /**
     * GET /api/rider/dashboard
     * Stats for today + open requests the rider can accept.
     */
    public function dashboard(Request $request)
    {
        $rider = $request->user();
        abort_unless(strtolower((string) $rider->role) === 'rider', 403, 'Riders only.');

        // Read the flag fresh from the database so it's always current
        $isOnline = (bool) User::whereKey($rider->id)->value('is_online');

        // Deliveries finished by this rider today
        $doneToday = Booking::where('rider_id', $rider->id)
            ->whereIn('status', self::DONE)
            ->whereDate('updated_at', today());

        $deliveriesToday = (clone $doneToday)->count();
        $earningsToday   = (clone $doneToday)->sum('fee');

        // Open requests: nobody has accepted them yet.
        // Offline riders don't get any.
        $pending = [];
        if ($isOnline) {
            $pending = Booking::where('status', 'pending')
                ->whereNull('rider_id')
                ->latest()
                ->take(10)
                ->get([
                    'id',
                    'item_description',
                    'pickup_address',
                    'dropoff_address',
                    'pickup_lat',
                    'pickup_lng',
                    'dropoff_lat',
                    'dropoff_lng',
                    'fee',
                    'status',
                    'created_at',
                ]);
        }

        return response()->json([
            'data' => [
                'is_online'        => $isOnline,
                'deliveries_today' => $deliveriesToday,
                'earnings_today'   => (float) $earningsToday,
                'pending'          => $pending,
            ],
        ]);
    }

    /**
     * POST /api/rider/status
     * Body: { "is_online": true|false }
     */
    public function setStatus(Request $request)
    {
        $rider = $request->user();
        abort_unless(strtolower((string) $rider->role) === 'rider', 403, 'Riders only.');

        $validated = $request->validate([
            'is_online' => ['required', 'boolean'],
        ]);

        // Clear message instead of a vague 500 if the migration wasn't run
        if (! Schema::hasColumn('users', 'is_online')) {
            return response()->json(
                ['message' => "users.is_online column is missing. Run: php artisan migrate"],
                500
            );
        }

        $isOnline = (bool) $validated['is_online'];

        // forceFill so this works even if is_online isn't in $fillable
        $rider->forceFill(['is_online' => $isOnline])->save();

        return response()->json([
            'data' => ['is_online' => $isOnline],
        ]);
    }

    /**
     * POST /api/rider/bookings/{id}/accept
     * First rider to accept wins; everyone else gets a 409.
     */
    public function accept(Request $request, $id)
    {
        $rider = $request->user();
        abort_unless(strtolower((string) $rider->role) === 'rider', 403, 'Riders only.');
        abort_unless(
            User::whereKey($rider->id)->value('is_online'),
            422,
            'Go online to accept requests.'
        );

        $booking = DB::transaction(function () use ($id, $rider) {
            // lock the row so two riders can't accept at the same time
            $booking = Booking::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($booking->status !== 'pending' || $booking->rider_id !== null) {
                return null;
            }

            // forceFill so this works even if these columns aren't in $fillable
            $fields = ['rider_id' => $rider->id, 'status' => 'accepted'];
            if (Schema::hasColumn('bookings', 'accepted_at')) {
                $fields['accepted_at'] = now();
            }
            $booking->forceFill($fields)->save();

            return $booking;
        });

        if (! $booking) {
            return response()->json(
                ['message' => 'This request was already taken.'],
                409
            );
        }

        return response()->json([
            'data' => array_merge($booking->toArray(), [
                'rider' => $this->riderPayload($booking->rider_id),
            ]),
        ]);
    }
    /**
     * GET /api/rider/bookings/{id}
     * A booking the rider may look at: an open request, or one they accepted.
     */
    public function showBooking(Request $request, $id)
    {
        $rider = $request->user();
        abort_unless(strtolower((string) $rider->role) === 'rider', 403, 'Riders only.');

        $booking = Booking::whereKey($id)
            ->where(function ($q) use ($rider) {
                $q->where(function ($open) {
                    $open->where('status', 'pending')->whereNull('rider_id');
                })->orWhere('rider_id', $rider->id);
            })
            ->firstOrFail();

        $data = $booking->toArray();

        // Rider assigned to this booking (null for open requests)
        $data['rider'] = $this->riderPayload($booking->rider_id);

        // Add the customer's name/phone (for the "Call Customer" button).
        // Works with whichever customer column your bookings table uses.
        $customerCol = collect(['customer_id', 'user_id', 'client_id'])
            ->first(fn ($col) => Schema::hasColumn('bookings', $col));

        if ($customerCol) {
            $customer = User::find($booking->{$customerCol});
            if ($customer) {
                $data['customer'] = [
                    'name'  => $customer->name,
                    'phone' => $customer->phone ?? $booking->customer_phone ?? null,
                ];
            }
        }

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/rider/active-booking
     * The delivery the rider is working on right now (or null).
     */
    public function active(Request $request)
    {
        $rider = $request->user();
        abort_unless(strtolower((string) $rider->role) === 'rider', 403, 'Riders only.');

        $booking = Booking::where('rider_id', $rider->id)
            ->whereIn('status', ['accepted', 'picked_up', 'in_transit'])
            ->latest('updated_at')
            ->first();

        $data = $booking
            ? array_merge($booking->toArray(), [
                'rider' => $this->riderPayload($booking->rider_id),
            ])
            : null;

        return response()->json(['data' => $data]);
    }

    /**
     * POST /api/rider/bookings/{id}/pickup
     * The rider picked up the item: accepted -> picked_up.
     * Safe to call twice (it won't change a booking that's already past pickup).
     */
    public function confirmPickup(Request $request, $id)
    {
        $rider = $request->user();
        abort_unless(strtolower((string) $rider->role) === 'rider', 403, 'Riders only.');

        $booking = Booking::where('rider_id', $rider->id)->whereKey($id)->firstOrFail();

        if ($booking->status === 'accepted') {
            $fields = ['status' => 'picked_up'];
            if (Schema::hasColumn('bookings', 'picked_up_at')) {
                $fields['picked_up_at'] = now();
            }
            $booking->forceFill($fields)->save();
        } elseif (! in_array($booking->status, ['picked_up', 'in_transit'], true)) {
            return response()->json(
                ['message' => 'This booking can no longer be picked up.'],
                422
            );
        }

        return response()->json(['data' => $booking->fresh()]);
    }

    /**
     * POST /api/rider/bookings/{id}/dropoff
     * The rider delivered the item: picked_up/in_transit -> delivered.
     */
    public function confirmDropoff(Request $request, $id)
    {
        $rider = $request->user();
        abort_unless(strtolower((string) $rider->role) === 'rider', 403, 'Riders only.');

        $booking = Booking::where('rider_id', $rider->id)->whereKey($id)->firstOrFail();

        if (in_array($booking->status, ['picked_up', 'in_transit'], true)) {
            $fields = ['status' => 'delivered'];
            if (Schema::hasColumn('bookings', 'delivered_at')) {
                $fields['delivered_at'] = now();
            }
            $booking->forceFill($fields)->save();
        } elseif (! in_array($booking->status, self::DONE, true)) {
            return response()->json(
                ['message' => 'Pick up the item before confirming the drop-off.'],
                422
            );
        }

        return response()->json(['data' => $booking->fresh()]);
    }

    /**
     * GET /api/rider/earnings
     * Today's earnings, delivery count, average, and the list of finished deliveries.
     */
    public function earnings(Request $request)
    {
        $rider = $request->user();
        abort_unless(strtolower((string) $rider->role) === 'rider', 403, 'Riders only.');

        $done = Booking::where('rider_id', $rider->id)
            ->whereIn('status', self::DONE)
            ->whereDate('updated_at', today())
            ->latest('updated_at')
            ->get(['id', 'fee', 'updated_at']);

        $count = $done->count();
        $total = (float) $done->sum('fee');

        return response()->json([
            'data' => [
                'earnings_today'   => $total,
                'deliveries_today' => $count,
                'average'          => $count ? round($total / $count, 2) : 0,
                'transactions'     => $done->map(fn ($b) => [
                    'id'           => $b->id,
                    'ref'          => sprintf('#DLG-%s-%03d', $b->updated_at->format('Ymd'), $b->id),
                    'completed_at' => $b->updated_at->toIso8601String(),
                    'fee'          => (float) $b->fee,
                ])->values(),
            ],
        ]);
    }
}