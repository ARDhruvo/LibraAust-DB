<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Students;
use App\Http\Requests\UpdateStudentsRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\StudentsResource;
use App\Http\Requests\V1\StoreStudentsRequest;
use Illuminate\Support\Facades\DB;

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
    
    // Build and execute insert query
    $columns = implode(', ', array_keys($validated));
    $placeholders = implode(', ', array_fill(0, count($validated), '?'));
    
    DB::insert("INSERT INTO students ($columns) VALUES ($placeholders)", array_values($validated));
    
    $lastId = DB::getPdo()->lastInsertId();
    $student = DB::select('SELECT * FROM students WHERE id = ?', [$lastId]);
    
    // Convert stdClass to array before passing to resource
    return new StudentsResource((array)$student[0]);
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
        
        // Build SET clause for prepared statement
        $setClause = implode(' = ?, ', array_keys($validated)) . ' = ?';
        
        // Execute raw SQL update with prepared statement
        DB::update("UPDATE students SET $setClause WHERE id = ?", 
            array_merge(array_values($validated), [$students->id]));
        
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