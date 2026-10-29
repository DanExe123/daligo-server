<?php

namespace App\Http\Controllers;
 
use App\Models\Booking;
use App\Models\Rating;
use Illuminate\Http\Request;
 
class RatingController extends Controller
{
    // Statuses that count as a finished delivery
    private const DONE = ['delivered', 'completed'];
 
    /**
     * POST /api/bookings/{id}/rating
     * Body: { "rating": 1-5, "feedback": "optional text" }
     */
    public function store(Request $request, $id)
    {
        $validated = $request->validate([
            'rating'   => ['required', 'integer', 'between:1,5'],
            'feedback' => ['nullable', 'string', 'max:500'],
        ]);
 
        // Only the customer who made the booking can rate it
        $booking = Booking::where('customer_id', $request->user()->id)->findOrFail($id);
 
        if (! in_array($booking->status, self::DONE, true)) {
            return response()->json(
                ['message' => 'You can rate a delivery once it is completed.'],
                422
            );
        }
 
        if (! $booking->rider_id) {
            return response()->json(
                ['message' => 'This booking has no rider to rate.'],
                422
            );
        }
 
        if (Rating::where('booking_id', $booking->id)->exists()) {
            return response()->json(
                ['message' => 'You already rated this delivery.'],
                409
            );
        }
 
        $feedback = isset($validated['feedback']) ? trim($validated['feedback']) : null;
 
        $rating = Rating::create([
            'booking_id'  => $booking->id,
            'customer_id' => $request->user()->id,
            'rider_id'    => $booking->rider_id,
            'rating'      => $validated['rating'],
            'feedback'    => $feedback !== '' ? $feedback : null,
        ]);
 
        return response()->json(['data' => $rating], 201);
    }
}