<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Students;
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
        // Raw SQL query to get all students
        $students = DB::select('SELECT * FROM students');
        return $students;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStudentsRequest $request)
    {
        $validated = $request->validated();

        // Check if student_id already exists
        $existingStudent = DB::select('SELECT * FROM students WHERE student_id = ?', [$validated['student_id']]);

        if (!empty($existingStudent)) {
            return response()->json([
                'message' => 'Student ID already exists',
                'error' => 'Duplicate student_id'
            ], 409);
        }

        // Extract password before creating student data
        $password = $validated['password'];
        unset($validated['password']); // Remove password from student data

        // Start database transaction to ensure both operations succeed or fail together
        DB::beginTransaction();

        try {
            // Build and execute insert query for student (without password)
            $studentColumns = implode(', ', array_keys($validated));
            $studentPlaceholders = implode(', ', array_fill(0, count($validated), '?'));

            DB::insert("INSERT INTO students ($studentColumns) VALUES ($studentPlaceholders)", array_values($validated));

            $lastStudentId = DB::getPdo()->lastInsertId();
            $student = DB::select('SELECT * FROM students WHERE id = ?', [$lastStudentId]);

            // Create user account for the student using the extracted password
            $userData = [
                'email' => $validated['email'],
                'password_hash' => Hash::make($password), // Hash the extracted password
                'role' => 'student'
            ];

            // Insert user
            $userColumns = implode(', ', array_keys($userData));
            $userPlaceholders = implode(', ', array_fill(0, count($userData), '?'));

            DB::insert("INSERT INTO users ($userColumns) VALUES ($userPlaceholders)", array_values($userData));

            $lastUserId = DB::getPdo()->lastInsertId();

            // Commit the transaction
            DB::commit();

            // Convert stdClass to array before passing to resource
            return new StudentsResource((array) $student[0]);

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
    public function show(Students $students)
    {
        // Raw SQL query to get specific student
        $student = DB::select('SELECT * FROM students WHERE id = ?', [$students->id]);

        if (empty($student)) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        return $student[0];
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudentsRequest $request, Students $students)
    {
        // Get validated data from request
        $validated = $request->validated();

        // If password is included in update, remove it from student data
        if (isset($validated['password'])) {
            unset($validated['password']);
        }

        // Build SET clause for prepared statement
        $setClause = implode(' = ?, ', array_keys($validated)) . ' = ?';

        // Execute raw SQL update with prepared statement
        DB::update(
            "UPDATE students SET $setClause WHERE id = ?",
            array_merge(array_values($validated), [$students->id])
        );

        // Fetch the updated student
        $updatedStudent = DB::select('SELECT * FROM students WHERE id = ?', [$students->id]);

        return $updatedStudent[0];
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Students $students)
    {
        // Raw SQL query to delete student
        DB::delete('DELETE FROM students WHERE id = ?', [$students->id]);

        return response()->json(['message' => 'Student deleted successfully'], 200);
    }
}