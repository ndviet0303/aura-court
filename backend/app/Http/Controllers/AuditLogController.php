<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        return AuditLog::with('user:id,name,email')->when($request->action, fn ($query, $action) => $query->where('action', $action))->latest()->paginate(50);
    }
}
