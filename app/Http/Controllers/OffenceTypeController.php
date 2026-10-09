<?php

namespace App\Http\Controllers;

use App\Models\OffenceType;
use Illuminate\Http\Request;

class OffenceTypeController extends Controller
{
    public function index()
    {
        $types = OffenceType::orderBy('group')->orderBy('name')->paginate(15);

        if (request()->expectsJson()) {
            return response()->json($types);
        }

        return view('offence-types.index', compact('types'));
    }

    public function create()
    {
        return view('offence-types.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'value' => 'required|string|max:255|unique:offence_types,value',
            'group' => 'nullable|string|max:255',
        ]);

        $type = OffenceType::create($data);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Offence type added successfully', 'data' => $type], 201);
        }

        return redirect()->route('offence-types.index')
            ->with('success', 'Offence type added successfully');
    }

    public function edit(OffenceType $offenceType)
    {
        return view('offence-types.edit', compact('offenceType'));
    }

    public function update(Request $request, OffenceType $offenceType)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'value' => 'required|string|max:255|unique:offence_types,value,' . $offenceType->id,
            'group' => 'nullable|string|max:255',
        ]);

        $offenceType->update($data);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Offence type updated successfully', 'data' => $offenceType]);
        }

        return redirect()->route('offence-types.index')
            ->with('success', 'Offence type updated successfully');
    }

    public function destroy(OffenceType $offenceType)
    {
        $offenceType->delete();

        if (request()->expectsJson()) {
            return response()->json(['message' => 'Offence type deleted successfully']);
        }

        return redirect()->route('offence-types.index')
            ->with('success', 'Offence type deleted successfully');
    }
}
