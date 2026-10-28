<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;


class BookingController extends Controller
{
    // Straight-line distance x 1.3 to approximate road distance
    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)) * 1.3, 2);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'customer') {
            return response()->json(['message' => 'Only customers can book.'], 403);
        }

        $d = $request->validate([
            'item_description' => 'required|string|max:500',
            'pickup_name' => 'required|string|max:255',
            'pickup_address' => 'nullable|string',
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'dropoff_name' => 'required|string|max:255',
            'dropoff_address' => 'nullable|string',
            'dropoff_lat' => 'required|numeric|between:-90,90',
            'dropoff_lng' => 'required|numeric|between:-180,180',
        ]);

        $km = $this->distanceKm(
            $d['pickup_lat'], $d['pickup_lng'], $d['dropoff_lat'], $d['dropoff_lng']
        );

        $booking = Booking::create($d + [
            'customer_id' => $request->user()->id,
            'distance_km' => $km,
            'duration_min' => (int) max(5, round($km / 20 * 60)),
            'fee' => round(19 + 10 * $km, 2), // ₱19 base + ₱10 per km
            'status' => 'pending',
        ]);

        return response()->json($booking, 201);
    }

    // Logged-in customer's own bookings, newest first
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Booking::where('customer_id', $request->user()->id)
                ->latest()
                ->limit(50)
                ->get()
        );
    }

    //show 
    public function show(Request $request, $id)
    {
        $booking = Booking::where('customer_id', $request->user()->id)
            ->findOrFail($id);
    
        $data = $booking->toArray();
    
        $rider = $booking->rider_id ? User::find($booking->rider_id) : null;
    
        $data['rider'] = $rider ? [
            'id'    => $rider->id,
            'name'  => $rider->name,
            'phone' => $rider->phone ?? null,
        ] : null;
    
        return response()->json(['data' => $data]);
    }
}