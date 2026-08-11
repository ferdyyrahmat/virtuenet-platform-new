<?php

namespace App\Http\Controllers\System\Ticket;

use App\Http\Controllers\Controller;
use App\Models\Developer;
use App\Models\User;
use Illuminate\Http\Request;

class DeveloperController extends Controller
{
    public function index()
    {
        $developers = Developer::with('user')->orderBy('created_at', 'desc')->get();
        $users = User::all();
        return view('admin.tickets.developers', compact('developers', 'users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|max:255',
            'user_id'          => 'nullable|exists:users,id',
        ]);

        $dev = Developer::create([
            'user_id'          => $request->user_id,
            'name'             => $request->name,
            'email'            => $request->email,
            'is_active'        => true,
        ]);

        audit_log("Added Developer '{$dev->name}' ({$dev->email})", 'create', 'developer');

        return response()->json([
            'success'  => true,
            'message'  => "Developer '{$dev->name}' registered successfully!",
            'redirect' => route('admin.tickets.developers.index')
        ]);
    }

    public function update(Request $request, string $id)
    {
        $dev = Developer::findOrFail($id);

        $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|max:255',
            'user_id'          => 'nullable|exists:users,id',
            'is_active'        => 'required|boolean',
        ]);

        $dev->update([
            'user_id'          => $request->user_id,
            'name'             => $request->name,
            'email'            => $request->email,
            'is_active'        => (bool) $request->is_active,
        ]);

        audit_log("Updated Developer '{$dev->name}' details", 'update', 'developer');

        return response()->json([
            'success'  => true,
            'message'  => "Developer '{$dev->name}' updated successfully!",
            'redirect' => route('admin.tickets.developers.index')
        ]);
    }

    public function destroy(string $id)
    {
        $dev = Developer::findOrFail($id);
        $name = $dev->name;
        $dev->delete();

        audit_log("Deleted Developer '{$name}'", 'delete', 'developer');

        return response()->json([
            'success'  => true,
            'message'  => "Developer '{$name}' deleted successfully.",
            'redirect' => route('admin.tickets.developers.index')
        ]);
    }
}
