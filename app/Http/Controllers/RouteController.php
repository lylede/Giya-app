<?php

namespace App\Http\Controllers;

use App\Services\RouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    public function __construct(protected RouteService $routes)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'stops'         => ['required', 'array', 'min:2', 'max:12'],
            'stops.*.lat'   => ['required', 'numeric', 'between:-90,90'],
            'stops.*.lng'   => ['required', 'numeric', 'between:-180,180'],
            'profile'       => ['nullable', 'in:foot-walking,driving-car,cycling-regular'],
        ]);

        // Never an error status: the map falls back to straight lines quietly
        // rather than showing the devotee a broken feature.
        return response()->json(
            $this->routes->between($data['stops'], $data['profile'] ?? null)
        );
    }
}
