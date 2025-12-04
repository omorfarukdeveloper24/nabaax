<?php

namespace App\Services;

use App\Models\PostBoost;

class BoostService
{
    public static function deductClickCost(PostBoost $boost)
    {
        if ($boost->status !== 'active') {
            return;
        }

        $boost->remaining_amount -= $boost->click_cost;
        $boost->total_click += 1;

        if ($boost->remaining_amount <= 0) {
            $boost->remaining_amount = 0;
            $boost->status = 'inactive';
        }

        $boost->save();
    }
    
    public static function hasActiveBoost($memberId)
    {
        return PostBoost::where('member_id', $memberId)
                        ->where('status', 'active')
                        ->exists();
    }
    
}