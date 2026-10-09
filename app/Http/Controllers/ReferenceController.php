<?php

namespace App\Http\Controllers;

use App\Models\Law;
use App\Models\Rule;
use App\Models\Sop;
use App\Models\UserManual;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    // ─── Laws ───────────────────────────────────────────
    public function lawsIndex()
    {
        return response()->json(Law::orderBy('created_at', 'desc')->paginate(15));
    }

    public function lawsStore(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'act_name' => 'required|string|max:255',
            'year' => 'nullable|string|max:10',
            'description' => 'nullable|string',
        ]);
        return response()->json(Law::create($data), 201);
    }

    public function lawsShow(Law $law)
    {
        return response()->json($law);
    }

    public function lawsUpdate(Request $request, Law $law)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'act_name' => 'required|string|max:255',
            'year' => 'nullable|string|max:10',
            'description' => 'nullable|string',
        ]);
        $law->update($data);
        return response()->json($law);
    }

    public function lawsDestroy(Law $law)
    {
        $law->delete();
        return response()->json(['message' => 'Law deleted']);
    }

    // ─── Rules ──────────────────────────────────────────
    public function rulesIndex()
    {
        return response()->json(Rule::orderBy('created_at', 'desc')->paginate(15));
    }

    public function rulesStore(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'effective_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);
        return response()->json(Rule::create($data), 201);
    }

    public function rulesShow(Rule $rule)
    {
        return response()->json($rule);
    }

    public function rulesUpdate(Request $request, Rule $rule)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'effective_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);
        $rule->update($data);
        return response()->json($rule);
    }

    public function rulesDestroy(Rule $rule)
    {
        $rule->delete();
        return response()->json(['message' => 'Rule deleted']);
    }

    // ─── SOPs ───────────────────────────────────────────
    public function sopsIndex()
    {
        return response()->json(Sop::orderBy('created_at', 'desc')->paginate(15));
    }

    public function sopsStore(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'version' => 'nullable|string|max:20',
            'effective_date' => 'nullable|date',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
        ]);
        if (isset($data['content']) && !isset($data['description'])) {
            $data['description'] = $data['content'];
        }
        unset($data['content']);
        return response()->json(Sop::create($data), 201);
    }

    public function sopsShow(Sop $sop)
    {
        return response()->json($sop);
    }

    public function sopsUpdate(Request $request, Sop $sop)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'version' => 'nullable|string|max:20',
            'effective_date' => 'nullable|date',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
        ]);
        if (isset($data['content']) && !isset($data['description'])) {
            $data['description'] = $data['content'];
        }
        unset($data['content']);
        $sop->update($data);
        return response()->json($sop);
    }

    public function sopsDestroy(Sop $sop)
    {
        $sop->delete();
        return response()->json(['message' => 'SOP deleted']);
    }

    // ─── User Manuals ──────────────────────────────────
    public function manualsIndex()
    {
        return response()->json(UserManual::orderBy('created_at', 'desc')->paginate(15));
    }

    public function manualsStore(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'audience' => 'required|string|max:255',
            'version' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
        ]);
        if (isset($data['content']) && !isset($data['description'])) {
            $data['description'] = $data['content'];
        }
        unset($data['content']);
        return response()->json(UserManual::create($data), 201);
    }

    public function manualsShow(UserManual $manual)
    {
        return response()->json($manual);
    }

    public function manualsUpdate(Request $request, UserManual $manual)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'audience' => 'required|string|max:255',
            'version' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
        ]);
        if (isset($data['content']) && !isset($data['description'])) {
            $data['description'] = $data['content'];
        }
        unset($data['content']);
        $manual->update($data);
        return response()->json($manual);
    }

    public function manualsDestroy(UserManual $manual)
    {
        $manual->delete();
        return response()->json(['message' => 'User manual deleted']);
    }
}
