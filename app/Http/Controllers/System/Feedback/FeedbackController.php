<?php

namespace App\Http\Controllers\System\Feedback;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $query = Feedback::with('user')->orderBy('created_at', 'desc');

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        if ($request->has('category') && !empty($request->category)) {
            $query->where('category', $request->category);
        }

        $feedbacks = $query->get();

        $stats = [
            'total'      => Feedback::count(),
            'pending'    => Feedback::where('status', 'pending')->count(),
            'reviewed'   => Feedback::where('status', 'reviewed')->count(),
            'resolved'   => Feedback::where('status', 'resolved')->count(),
            'avg_rating' => round(Feedback::avg('rating') ?: 5.0, 1),
        ];

        return view('admin.feedbacks.index', compact('feedbacks', 'stats'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject'  => 'required|string|max:255',
            'category' => 'required|in:bug,feature_request,general_inquiry,complaint',
            'message'  => 'required|string',
            'rating'   => 'required|integer|min:1|max:5',
        ]);

        $user = Auth::user();

        $feedback = Feedback::create([
            'user_id'  => $user->id,
            'name'     => $user->name,
            'email'    => $user->email,
            'subject'  => $request->subject,
            'category' => $request->category,
            'message'  => $request->message,
            'rating'   => $request->rating,
            'status'   => 'pending',
        ]);

        audit_log('Submitted feedback #' . $feedback->id, 'create', 'feedback');
        send_notification('Feedback Submitted', 'Thank you for your feedback! Our team will review it shortly.');

        $msg = 'Thank you! Your feedback has been submitted successfully.';

        return response()->json([
            'success'  => true,
            'message'  => $msg,
            'redirect' => route('admin.feedbacks.index'),
            'feedback' => $feedback
        ]);
    }

    public function updateStatus(Request $request, string $id)
    {
        $request->validate([
            'status'      => 'required|in:pending,reviewed,resolved',
            'admin_notes' => 'nullable|string',
        ]);

        $feedback = Feedback::findOrFail($id);
        $feedback->update([
            'status'      => $request->status,
            'admin_notes' => $request->admin_notes,
        ]);

        audit_log('Updated feedback #' . $feedback->id . ' status to ' . $request->status, 'update', 'feedback');

        $msg = 'Feedback status updated to ' . ucfirst($request->status) . '!';

        return response()->json([
            'success'  => true,
            'message'  => $msg,
            'redirect' => route('admin.feedbacks.index'),
            'feedback' => $feedback
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $feedback = Feedback::findOrFail($id);
        $feedback->delete();

        audit_log('Deleted feedback #' . $id, 'delete', 'feedback');

        $msg = 'Feedback entry deleted successfully.';

        return response()->json([
            'success'  => true,
            'message'  => $msg,
            'redirect' => route('admin.feedbacks.index')
        ]);
    }
}