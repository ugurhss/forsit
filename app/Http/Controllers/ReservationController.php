<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Services\Reservation\ReservationService;
use App\Traits\ApiResponse;

class ReservationController extends Controller
{
    use ApiResponse;

    public function store(CreateReservationRequest $request, ReservationService $reservationService)
    {
        $reservation = $reservationService->create($request->validated());

        return $this->success(
            ReservationResource::make($reservation)->resolve(),
            'Reservation created successfully.',
            201,
        );
    }

    public function release(Reservation $reservation, ReservationService $reservationService)
    {
        $releasedReservation = $reservationService->release($reservation);

        return $this->success(
            ReservationResource::make($releasedReservation)->resolve(),
            'Reservation released successfully.',
            200,
        );
    }
}
