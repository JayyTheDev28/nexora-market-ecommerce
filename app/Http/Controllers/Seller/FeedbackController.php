<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $sellerId = $request->user()->id;

        $query = Review::whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))
            ->with(['buyer', 'product', 'order'])
            ->latest();

        // Rating filter
        if ($rating = $request->query('rating')) {
            $query->where('rating', (int) $rating);
        }

        // Reply status filter
        if ($replyStatus = $request->query('reply_status')) {
            if ($replyStatus === 'replied') {
                $query->whereNotNull('seller_reply');
            } elseif ($replyStatus === 'pending') {
                $query->whereNull('seller_reply');
            }
        }

        $reviews = $query->paginate(15)->withQueryString();

        $allSellerReviews = Review::whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))->get();
        $averageRating = $allSellerReviews->isNotEmpty() ? round($allSellerReviews->avg('rating'), 1) : null;

        $ratingCounts = [
            'total' => $allSellerReviews->count(),
            '5_star' => $allSellerReviews->where('rating', 5)->count(),
            '4_star' => $allSellerReviews->where('rating', 4)->count(),
            '3_star' => $allSellerReviews->where('rating', 3)->count(),
            '2_star' => $allSellerReviews->where('rating', 2)->count(),
            '1_star' => $allSellerReviews->where('rating', 1)->count(),
            'pending_reply' => $allSellerReviews->whereNull('seller_reply')->count(),
        ];

        return view('seller.feedback.index', compact('reviews', 'averageRating', 'ratingCounts'));
    }

    public function reply(Request $request, Review $review): RedirectResponse
    {
        $this->authorizeOwner($request, $review);

        $validated = $request->validate([
            'reply' => ['required', 'string', 'max:1000'],
        ]);

        $review->update([
            'seller_reply' => $validated['reply'],
            'seller_replied_at' => now(),
        ]);

        return redirect()->route('seller.feedback.index')
            ->with('status', 'Your response to the buyer review has been posted.');
    }

    private function authorizeOwner(Request $request, Review $review): void
    {
        abort_unless($review->product && $review->product->seller_id === $request->user()->id, 403);
    }
}
