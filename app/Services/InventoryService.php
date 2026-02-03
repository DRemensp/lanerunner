<?php

namespace App\Services;

use App\Models\Item;
use App\Models\User;
use App\Models\UserItem;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function grant(User $user, Item $item, int $quantity): UserItem
    {
        return DB::transaction(function () use ($user, $item, $quantity) {
            $entry = UserItem::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'item_id' => $item->id,
                ],
                [
                    'quantity' => 0,
                ],
            );

            $entry->quantity = min(
                $item->max_stack,
                $entry->quantity + max(0, $quantity),
            );
            $entry->last_acquired_at = now();
            $entry->save();

            return $entry;
        });
    }

    public function consume(User $user, Item $item, int $quantity): UserItem
    {
        return DB::transaction(function () use ($user, $item, $quantity) {
            $entry = UserItem::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'item_id' => $item->id,
                ],
                [
                    'quantity' => 0,
                ],
            );

            $entry->quantity = max(0, $entry->quantity - max(0, $quantity));
            $entry->save();

            return $entry;
        });
    }
}
