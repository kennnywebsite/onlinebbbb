<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SettingsCont;
use App\Notifications\PostForexSignal;
use App\Notifications\UpdateForexSignalResult;
use App\Traits\PingServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class SignalProvderController extends Controller
{
    use PingServer;

    public function tradeSignals(Request $request)
    {
        $page = $request->query('page', 1);
        $response = $this->fetctApi('/trading-signals?page=' . $page);
        $info = json_decode($response);

        return response()->json([
            'status' => 200,
            'data' => $info->data->signals ?? []
        ]);
    }

    public function settings()
    {
        $response = $this->fetctApi('/signal-settings');
        $info = json_decode($response);

        return response()->json([
            'status' => 200,
            'data' => $info->data->settings ?? null
        ]);
    }

    public function subscribers()
    {
        $response = $this->fetctApi('/signal-subscribers');
        if ($response->failed()) {
            return response()->json(['status' => 'error', 'message' => $response['message'] ?? 'Failed'], 400);
        }
        
        $info = json_decode($response);
        return response()->json(['status' => 200, 'data' => $info->data->subscribers ?? []]);
    }

    public function addSignals(Request $request)
    {
        $response = $this->fetctApi('/post-signals', $request->all(), 'POST');
        return $this->apiResponse($response);
    }

    public function publishSignals($signal)
    {
        $response = $this->fetctApi("/publish-signals/$signal");
        $info = json_decode($response);

        if ($response->successful() && !isset($info->error)) {
            Notification::send($info->data->chat_id, new PostForexSignal($info->data->message));
        }

        return $this->apiResponse($response);
    }

    public function updateResult(Request $request)
    {
        $response = $this->fetctApi('/update-result', [
            'signalId' => $request->signalId,
            'result' => $request->result
        ], 'POST');

        if ($response->successful()) {
            $info = json_decode($response);
            Notification::send($info->data->chat_id, new UpdateForexSignalResult($info->data->message));
        }

        return $this->apiResponse($response);
    }

    public function deleteSignal($signal)
    {
        $response = $this->fetctApi("/delete-signal/$signal");
        return $this->apiResponse($response);
    }

    public function saveSettings(Request $request)
    {
        $settings = SettingsCont::find(1);
        $website = url('/get-started');

        $response = $this->fetctApi("/save-signal-settings", [
            'website' => $website,
            'monthly' => $request->monthly,
            'quaterly' => $request->quaterly,
            'yearly' => $request->yearly,
            'telegram_link' => $request->telegram_link,
            'telegram_bot_api' => $request->telegram_bot_api
        ], 'PUT');

        if ($response->successful() && $settings) {
            $settings->update(['telegram_bot_api' => $request->telegram_bot_api]);
        }

        return $this->apiResponse($response);
    }

    public function getChatId()
    {
        return $this->apiResponse($this->fetctApi("/chat-id"));
    }

    public function deleteChatId()
    {
        return $this->apiResponse($this->fetctApi("/delete-id"));
    }

    /**
     * Unified response handler for API
     */
    private function apiResponse($response)
    {
        if ($response->failed()) {
            return response()->json([
                'status' => 'error', 
                'message' => $response['message'] ?? 'Action failed'
            ], 400);
        }
        return response()->json([
            'status' => 'success', 
            'message' => $response['message'] ?? 'Action successful'
        ]);
    }
}