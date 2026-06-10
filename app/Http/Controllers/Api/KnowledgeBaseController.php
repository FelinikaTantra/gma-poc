<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBase;
use Illuminate\Http\Request;

class KnowledgeBaseController extends Controller
{
    public function index()
    {
        return response()->json(KnowledgeBase::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string',
            'title' => 'required|string',
            'content' => 'required|string',
        ]);

        $entry = KnowledgeBase::create($validated);
        return response()->json($entry, 201);
    }

    public function destroy($id)
    {
        $entry = KnowledgeBase::findOrFail($id);
        $entry->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
