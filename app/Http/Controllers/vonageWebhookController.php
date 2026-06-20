<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class vonageWebhookController extends Controller
{
    // استقبال الرسائل الواردة
    public function inbound(Request $request) {
        $payload = $request->all();
        $message = $payload['text']; // نص الرسالة
        $from    = $payload['from']; // رقم المُرسل


        Log::info('Received inbound SMS from Vonage', [
            'from' => $from,
            'message' => $message,
        ]);
        return response()->json(['status' => 200]);
    }

    // استقبال تحديثات حالة الرسائل
    public function status(Request $request) {
        $payload = $request->all();
        $status  = $payload['status']; // delivered, rejected, etc.

        Log::info('Received SMS status update from Vonage', [
            'status' => $status,
            'payload' => $payload,
        ]);

        return response()->json(['status' => 200]);
    }
}
