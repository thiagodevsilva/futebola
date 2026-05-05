<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->get('per_page', 15), 50));
        $query = News::with('feed')->latest('published_at');

        if ($request->filled('source_id')) {
            $query->where('feed_id', $request->get('source_id'));
        }
        if ($request->filled('category')) {
            $query->whereHas('feed', fn ($q) => $q->where('category', $request->get('category')));
        }
        if ($request->filled('topic')) {
            $topic = trim((string) $request->get('topic'));
            if ($topic !== '') {
                $query->whereHas('feed', fn ($q) => $q->where('topic', $topic));
            }
        }

        $news = $query->paginate($perPage);

        return response()->json([
            'data' => $news->items(),
            'meta' => [
                'current_page' => $news->currentPage(),
                'last_page' => $news->lastPage(),
                'per_page' => $news->perPage(),
                'total' => $news->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $news = News::with('feed')->findOrFail($id);
        return response()->json(['data' => $news]);
    }
}
