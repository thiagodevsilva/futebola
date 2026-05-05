<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Feed::active()->ordered();
        if ($request->filled('category')) {
            $query->where('category', $request->get('category'));
        }
        if ($request->filled('topic')) {
            $query->where('topic', $request->get('topic'));
        }
        $sources = $query->get()->map(fn ($f) => [
            'id' => $f->id,
            'name' => $f->name,
            'url' => $f->url,
            'category' => $f->category,
            'topic' => $f->topic,
            'priority' => $f->priority,
            'language' => $f->language,
        ]);
        return response()->json(['data' => $sources]);
    }

    /**
     * Lista os temas (topic) disponíveis para filtro. Ex.: sao-paulo, corinthians, geral.
     */
    public function topics(): JsonResponse
    {
        $topics = Feed::active()->whereNotNull('topic')->where('topic', '!=', '')->distinct()->orderBy('topic')->pluck('topic');
        return response()->json(['data' => $topics->values()->all()]);
    }
}
