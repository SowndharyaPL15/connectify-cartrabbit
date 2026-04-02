<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    /**
     * Add a user to contacts list.
     */
    public function store(Request $request)
    {
        $request->validate([
            'contact_id' => 'required|exists:users,id',
            'alias_name' => 'nullable|string|max:50',
        ]);

        $authUser = Auth::user();

        if ($request->contact_id == $authUser->id) {
            return response()->json(['error' => 'You cannot add yourself.'], 400);
        }

        $contact = Contact::updateOrCreate(
            ['user_id' => $authUser->id, 'contact_id' => $request->contact_id],
            ['alias_name' => $request->alias_name]
        );

        return response()->json(['success' => true, 'contact' => $contact]);
    }

    /**
     * Update an existing contact's alias.
     */
    public function update(Request $request, Contact $contact)
    {
        $request->validate([
            'alias_name' => 'nullable|string|max:50',
        ]);

        if ($contact->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $contact->update([
            'alias_name' => $request->alias_name,
        ]);

        return response()->json(['success' => true, 'contact' => $contact]);
    }

    /**
     * Remove a contact.
     */
    public function destroy(Contact $contact)
    {
        if ($contact->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $contact->delete();

        return response()->json(['success' => true]);
    }
}
