<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

class PublicationController extends Controller
{
    private function configureCloudinary()
    {
        Configuration::instance([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET')
            ],
            'url' => ['secure' => true]
        ]);
    }

    public function index(Request $request)
    {
        if ($request->has('type')) {
            $publications = DB::select('SELECT * FROM publications WHERE type = ?', [$request->type]);
        } else {
            $publications = DB::select('SELECT * FROM publications');
        }

        return response()->json($publications);
    }

    public function show($id)
    {
        $publication = DB::select('SELECT * FROM publications WHERE id = ?', [$id]);
        if (empty($publication)) {
            abort(404, 'Publication not found');
        }
        return response()->json($publication[0]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title'            => 'required|string|max:255',
                'author'           => 'required|string|max:255',
                'isbn'             => 'nullable|string|max:255',
                'publication_year' => 'nullable|integer',
                'publisher'        => 'nullable|string|max:255',
                'department'       => 'nullable|string|max:255',
                'type'             => 'required|in:book,thesis',
                'total_copies'     => 'nullable|integer|min:0',
                'available_copies' => 'nullable|integer|min:0',
                'shelf_location'   => 'nullable|string|max:255',
                'description'      => 'nullable|string',
                'cover'            => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:4096',
            ]);

            DB::beginTransaction();

            $cover_url = null;
            $cover_public_id = null;

            if ($request->hasFile('cover')) {
                $file = $request->file('cover');
                if (!$file->isValid()) {
                    DB::rollBack();
                    return response()->json(['error' => 'Invalid file upload'], 400);
                }

                $this->configureCloudinary();
                $uploadApi = new UploadApi();
                $result = $uploadApi->upload($file->getRealPath(), [
                    'folder' => 'library/publications',
                ]);

                $cover_url = $result['secure_url'];
                $cover_public_id = $result['public_id'];
            }

            // always insert full column list
            DB::insert(
                'INSERT INTO publications 
                 (title, author, isbn, publication_year, publisher, department, type, total_copies, available_copies, shelf_location, description, cover_url, cover_public_id) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $validated['title'],
                    $validated['author'],
                    $validated['isbn'] ?? null,
                    $validated['publication_year'] ?? null,
                    $validated['publisher'] ?? null,
                    $validated['department'] ?? null,
                    $validated['type'],
                    $validated['total_copies'] ?? null,
                    $validated['available_copies'] ?? null,
                    $validated['shelf_location'] ?? null,
                    $validated['description'] ?? null,
                    $cover_url,
                    $cover_public_id,
                ]
            );

            $id = DB::getPdo()->lastInsertId();
            $publication = DB::select('SELECT * FROM publications WHERE id = ?', [$id])[0];

            DB::commit();

            return response()->json($publication, 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Publication creation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create publication', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $pub = DB::select('SELECT * FROM publications WHERE id = ?', [$id]);
        if (empty($pub)) {
            abort(404, 'Publication not found');
        }
        $pub = $pub[0];

        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'author'           => 'required|string|max:255',
            'isbn'             => 'nullable|string|max:255',
            'publication_year' => 'nullable|integer',
            'publisher'        => 'nullable|string|max:255',
            'department'       => 'nullable|string|max:255',
            'type'             => 'required|in:book,thesis',
            'total_copies'     => 'nullable|integer|min:0',
            'available_copies' => 'nullable|integer|min:0',
            'shelf_location'   => 'nullable|string|max:255',
            'description'      => 'nullable|string',
            'cover'            => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:4096',
        ]);

        DB::beginTransaction();

        $cover_url = $pub->cover_url;
        $cover_public_id = $pub->cover_public_id;

        if ($request->hasFile('cover')) {
            $this->configureCloudinary();

            if (!empty($cover_public_id)) {
                try {
                    (new UploadApi())->destroy($cover_public_id);
                } catch (\Exception $e) {
                    \Log::warning("Failed to delete old Cloudinary image: " . $e->getMessage());
                }
            }

            try {
                $result = (new UploadApi())->upload($request->file('cover')->getRealPath(), [
                    'folder' => 'library/publications',
                ]);
                $cover_url = $result['secure_url'];
                $cover_public_id = $result['public_id'];
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['error' => 'Failed to upload image'], 500);
            }
        }

        DB::update(
            'UPDATE publications SET 
             title = ?, author = ?, isbn = ?, publication_year = ?, publisher = ?, department = ?, type = ?, total_copies = ?, available_copies = ?, shelf_location = ?, description = ?, cover_url = ?, cover_public_id = ?
             WHERE id = ?',
            [
                $validated['title'],
                $validated['author'],
                $validated['isbn'] ?? null,
                $validated['publication_year'] ?? null,
                $validated['publisher'] ?? null,
                $validated['department'] ?? null,
                $validated['type'],
                $validated['total_copies'] ?? null,
                $validated['available_copies'] ?? null,
                $validated['shelf_location'] ?? null,
                $validated['description'] ?? null,
                $cover_url,
                $cover_public_id,
                $id,
            ]
        );

        $updated = DB::select('SELECT * FROM publications WHERE id = ?', [$id])[0];

        DB::commit();

        return response()->json($updated);
    }

    public function destroy($id)
    {
        $pub = DB::select('SELECT * FROM publications WHERE id = ?', [$id]);
        if (empty($pub)) {
            abort(404, 'Publication not found');
        }
        $pub = $pub[0];

        DB::beginTransaction();

        if (!empty($pub->cover_public_id)) {
            try {
                $this->configureCloudinary();
                (new UploadApi())->destroy($pub->cover_public_id);
            } catch (\Exception $e) {
                \Log::warning("Failed to delete Cloudinary image: " . $e->getMessage());
            }
        }

        DB::delete('DELETE FROM publications WHERE id = ?', [$id]);

        DB::commit();

        return response()->json(['message' => 'Publication deleted']);
    }
}
