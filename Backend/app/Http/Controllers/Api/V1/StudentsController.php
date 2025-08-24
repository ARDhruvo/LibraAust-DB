<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Students;
use App\Http\Requests\UpdateStudentsRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\StudentsResource;
use App\Http\Requests\V1\StoreStudentsRequest;

class StudentsController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index()
    {
        //
        return Students::all();
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStudentsRequest $request)
    {
        return new StudentsResource(Students::create($request->all()));
    }

    /**
     * Display the specified resource.
     */
    public function show(Students $students)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudentsRequest $request, Students $students)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Students $students)
    {
        //
    }
}
