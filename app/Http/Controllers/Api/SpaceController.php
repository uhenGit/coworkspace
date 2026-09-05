<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SpaceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $spaces = Space::with('category')->where('is_active', true)->get();

        return response()->json($spaces);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Space $space)
    {
        return response()->json($space);
        /* $space->load('category');

        return response()->json($space); */
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Space $space)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Space $space)
    {
        //
    }

    public function test(Request $request)
    {
        $start = microtime(true);
        $spaces = Space::with('category')
            ->get()
            ->groupBy('categoty.name')
            ->keys();
        $end = microtime(true);
        Log::info('time');
        $time = ($end - $start) * 1000;
        Log::info($time);

        return [];
        /* return Space::with('category')
            ->get()
            ->groupBy('category.name')
            ->map(fn (Collection $group) =>
                $group->map(fn ($space) => [
                    'title' => $space->title,
                    'Price_per_hour' => $space->price_per_hour,
                ]),
            )
            ->pipe(fn ($spaces) => response()->json($spaces)); */
        /* $spaces = Space::with('category')
            ->get()
            ->groupBy('category.name');
            // ->keys();
        Log::info('request');
        $sorts = [
            'name' => 'title',
            'price' => 'price_per_hour',
            // '' => null, // do not do it like this
        ];
        $sort = $sorts[$request->sort] ?? null;

        Log::info($sort); */
        /* $spaces = Space::query()
            ->when(
                isset($sort),
                fn ($query) => $query->orderBy($sort))
            ->get(); */

        // return response()->json($spaces);
    }
}
