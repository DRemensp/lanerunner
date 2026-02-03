<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $items = $user->userItems()
            ->with('item')
            ->where('quantity', '>', 0)
            ->get()
            ->map(function ($entry) {
                return [
                    'item_id' => $entry->item_id,
                    'slug' => $entry->item?->slug,
                    'name' => $entry->item?->name,
                    'type' => $entry->item?->type,
                    'quantity' => $entry->quantity,
                ];
            });

        return response([
            'items' => $items,
        ]);
    }

    public function grant(Request $request, InventoryService $service): Response
    {
        $validated = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $user = $request->user();
        $item = Item::findOrFail($validated['item_id']);

        if (!$item->is_active) {
            return response([
                'message' => 'Item not available.',
            ], 403);
        }

        $entry = $service->grant($user, $item, (int) $validated['quantity']);

        return response([
            'item_id' => $entry->item_id,
            'quantity' => $entry->quantity,
        ]);
    }

    public function consume(Request $request, InventoryService $service): Response
    {
        $validated = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $user = $request->user();
        $item = Item::findOrFail($validated['item_id']);
        $entry = $service->consume($user, $item, (int) $validated['quantity']);

        return response([
            'item_id' => $entry->item_id,
            'quantity' => $entry->quantity,
        ]);
    }
}
