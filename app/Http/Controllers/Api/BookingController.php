<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Models\Booking;

class BookingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $bookings = Booking::with('space', 'user')->get();

        return response()->json($bookings);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBookingRequest $request)
    {
        $booking = auth()->user()->bookings()->create($request->validated());

        return response()->json([
            'message' => 'Booking created',
            'booking' => $booking->load('space'),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Booking $booking)
    {
        if ($booking->user_d !== auth()->id()) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        return response()->json($booking->load('space'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBookingRequest $request, Booking $booking)
    {
        $booking->update($request->validated());

        return response()->json($booking);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Booking $booking)
    {
        if ($booking->user_id !== auth()->id()) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $booking->delete();

        return response()->json(['message' => 'Booking cancelled']);
    }

    public function myBookings()
    {
        $bookings = auth()->user()->bookings()->with('space')->latest()->get();

        return response()->json($bookings);
    }
}
