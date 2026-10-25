<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RiderController extends Controller
{
    // Statuses that count as a finished delivery (matches the app)
    private const DONE = ['delivered', 'completed'];

    /**
     * GET /api/rider/dashboard
     * Stats for today + open requests the rider can accept.
     */
    public function dashboard(Request $request)
    {
        $rider = $request->user();
        abort_unless(strtolower((string) $rider->role) === 'rider', 403, 'Riders only.');

        // Read the flag straight from the users table so it's always current
        $isOnline = (bool) DB::table('users')
            ->where('id', $rider->id)
            ->value('is_online');

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

        // Write straight to the users table (no $fillable needed)
        DB::table('users')
            ->where('id', $rider->id)
            ->update([
                'is_online'  => $isOnline,
                'updated_at' => now(),
            ]);

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
            DB::table('users')->where('id', $rider->id)->value('is_online'),
            422,
            'Go online to accept requests.'
        );

        $booking = DB::transaction(function () use ($id, $rider) {
            // lock the row so two riders can't accept at the same time
            $booking = Booking::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($booking->status !== 'pending' || $booking->rider_id !== null) {
                return null;
            }

            $booking->update([
                'rider_id' => $rider->id,
                'status'   => 'accepted',
            ]);

            return $booking;
        });

        if (! $booking) {
            return response()->json(
                ['message' => 'This request was already taken.'],
                409
            );
        }

        return response()->json(['data' => $booking]);
    }
}