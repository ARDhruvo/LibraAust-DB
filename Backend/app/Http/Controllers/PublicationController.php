<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicationController extends Controller
{
    // Get all publications
    public function index(Request $request)
    {
        if ($request->has('type')) {
            $publications = DB::select(
                "SELECT * FROM publications WHERE type = ?",
                [$request->type]
            );
        } else {
            $publications = DB::select("SELECT * FROM publications");
        }

        return response()->json($publications);
    }

    // Get single publication
    public function show($id)
    {
        $publication = DB::select(
            "SELECT * FROM publications WHERE id = ? LIMIT 1",
            [$id]
        );

        if (!$publication) {
            return response()->json(['message' => 'Publication not found'], 404);
        }

        return response()->json($publication[0]);
    }

    // Insert new publication
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'author' => 'required|string',
            'isbn' => 'nullable|string',
            'publication_year' => 'nullable|integer',
            'publisher' => 'nullable|string',
            'department' => 'nullable|string',
            'type' => 'required|in:book,thesis',
            'total_copies' => 'nullable|integer',
            'available_copies' => 'nullable|integer',
            'shelf_location' => 'nullable|string',
            'description' => 'nullable|string',
            'cover_url' => 'nullable|string',
        ]);

        DB::insert(
            "INSERT INTO publications 
                (title, author, isbn, publication_year, publisher, department, type, total_copies, available_copies, shelf_location, description, cover_url, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $validated['title'],
                $validated['author'],
                $validated['isbn'] ?? null,
                $validated['publication_year'] ?? null,
                $validated['publisher'] ?? null,
                $validated['department'] ?? null,
                $validated['type'],
                $validated['total_copies'] ?? 1,
                $validated['available_copies'] ?? 1,
                $validated['shelf_location'] ?? null,
                $validated['description'] ?? null,
                $validated['cover_url'] ?? null,
            ]
        );

        $publication = DB::select("SELECT * FROM publications ORDER BY id DESC LIMIT 1");

        return response()->json($publication[0], 201);
    }

    // Update publication
    public function update(Request $request, $id)
{
    $affected = DB::update(
        "UPDATE publications 
         SET title = ?, 
             author = ?, 
             department = ?, 
             cover_url = ?, 
             description = ?, 
             publication_year = ?, 
             publisher = ?, 
             total_copies = ?, 
             available_copies = ?, 
             shelf_location = ?, 
             updated_at = NOW()
         WHERE id = ? AND type = ?",
        [
            $request->title,
            $request->author,
            $request->department,
            $request->cover_url,
            $request->description,
            $request->publication_year,
            $request->publisher,
            $request->total_copies,
            $request->available_copies,
            $request->shelf_location,
            $id,
            $request->type
        ]
    );

    if ($affected === 0) {
        return response()->json(['message' => 'Publication not found or not updated'], 404);
    }

    return response()->json(['message' => 'Publication updated successfully']);
}

    // Delete publication
    public function destroy($id)
    {
        $deleted = DB::delete("DELETE FROM publications WHERE id = ?", [$id]);

        if ($deleted) {
            return response()->json(['message' => 'Publication deleted']);
        } else {
            return response()->json(['message' => 'Publication not found'], 404);
        }
    }

    // Borrow a book (decrease available copies)
    public function borrow($id)
    {
        $updated = DB::update(
            "UPDATE publications SET available_copies = available_copies - 1, updated_at = NOW()
             WHERE id = ? AND available_copies > 0",
            [$id]
        );

        if ($updated) {
            $publication = DB::select("SELECT * FROM publications WHERE id = ? LIMIT 1", [$id]);
            return response()->json($publication[0]);
        } else {
            return response()->json(['message' => 'Book not available'], 400);
        }
    }
}
