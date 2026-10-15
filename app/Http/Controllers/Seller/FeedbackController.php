<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $sellerId = $request->user()->id;

        $reviews = Review::whereHas('order.items.product', fn ($q) => $q->where('seller_id', $sellerId))
            ->with(['buyer', 'order.items' => fn ($q) => $q->whereHas('product', fn ($q2) => $q2->where('seller_id', $sellerId))])
            ->latest()
            ->get();

        $averageRating = $reviews->isNotEmpty() ? round($reviews->avg('rating'), 1) : null;

        return view('seller.feedback.index', compact('reviews', 'averageRating'));
    }
}
