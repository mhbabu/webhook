<?php

namespace App\Http\Controllers\Api\Message;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    public function incomingMsg(Request $request)
    {
        $data = $request->all();

        // Log the full request data as JSON
        Log::info('Incoming Message Data:', $data);

        // Or if you want it as a JSON string
        Log::info('Incoming Message Data: ' . json_encode($data, JSON_PRETTY_PRINT));

        return response()->json(['status' => 'success', 'data'   => $data]);
    }
}
