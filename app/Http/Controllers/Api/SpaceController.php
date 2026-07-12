<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\Http\Request;

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
        $space->load('category');

        return response()->json($space);
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
}
