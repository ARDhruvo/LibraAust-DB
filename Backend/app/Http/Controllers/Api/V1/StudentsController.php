<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Students;
use App\Models\Users; // Assuming your Userss model is named 'Users'
use App\Http\Requests\UpdateStudentsRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\StudentsResource;
use App\Http\Requests\V1\StoreStudentsRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Use Eloquent to get all students
        return Students::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStudentsRequest $request)
    {
        $validated = $request->validated();

        // Check if student_id already exists using Eloquent

        $student = DB::select('SELECT * FROM students WHERE student_id = ?', [$validated['student_id']]);

        if (!empty($student)) {
            return response()->json([
                'message' => 'Student ID already exists',
                'error' => 'Duplicate student_id'
            ], 409);
        }

        // Extract password before creating student
        $password = $validated['password'];
        unset($validated['password']); // Remove password from student data

        // Start database transaction
        DB::beginTransaction();

        try {
            // Get all the columns to insert
            // $studentColumns = implode(', ', array_keys($validated));
            $studentColumns = implode(', ', array_keys($validated));
            $studentPlaceholders = implode(', ', array_fill(0, count($validated), '?'));
            // $studentPlaceholders = implode(', ', array_fill(0, count($validated), '?'));

            // Debugging bc I hate this
            // return response()->json([
            //     'message' => 'Debugging',
            //     'student_values' => $validated
            // ], 500);

            // Create the student using Eloquent
            // $student = Students::create($validated);
            DB::insert("INSERT INTO students ($studentColumns) VALUES ($studentPlaceholders)", array_values($validated));

            // Create Users account for the student

            $userData = [
                'email' => $validated['email'],
                'password_hash' => Hash::make($password),
                'role' => 'student'
            ];

            $userColumns = implode(', ', array_keys($userData));
            $userPlaceholders = implode(', ', array_fill(0, count($userData), '?'));

            DB::insert("INSERT INTO users ($userColumns) VALUES ($userPlaceholders)", array_values($userData));

            // Commit the transaction
            DB::commit();

            // Return the student resource
            return response()->json([
                'message' => 'Student registered successfully',
            ], 201);
        } catch (\Exception $e) {
            // Rollback the transaction on error
            DB::rollBack();

            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Students $student) // Renamed parameter to $student for clarity
    {
        // Eloquent automatically injects the model instance based on the route ID
        // No need to manually fetch it
        return $student;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudentsRequest $request, Students $student) // Renamed parameter to $student
    {
        // Get validated data from request
        $validated = $request->validated();

        // If password is included in update, remove it from student data
        if (isset($validated['password'])) {
            unset($validated['password']);
        }

        // Use Eloquent to update the student
        $student->update($validated);

        // Return the updated student (fresh instance from the database)
        return $student->fresh();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Students $student) // Renamed parameter to $student
    {
        // Use Eloquent to delete the student
        $student->delete();

        return response()->json(['message' => 'Student deleted successfully'], 200);
    }
}