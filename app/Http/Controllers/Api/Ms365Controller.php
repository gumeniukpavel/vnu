<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class Ms365Controller
{
    private function http()
    {
        $token = auth()->user()?->azure_access_token;
        if (!$token) abort(401, 'No access token');
        return Http::withToken($token)
            ->acceptJson()
            ->withHeaders([
                'ConsistencyLevel' => 'eventual',
                'Prefer' => 'outlook.timezone="Europe/Kyiv"',
            ]);
    }

    public function me()
    {
        $resp = $this->http()->get('https://graph.microsoft.com/v1.0/me');
        return response()->json($resp->json(), $resp->status());
    }

    public function photo()
    {
        $resp = $this->http()->get('https://graph.microsoft.com/v1.0/me/photo/$value');
        if ($resp->successful()) {
            return response($resp->body(), 200)->header('Content-Type', $resp->header('Content-Type','image/jpeg'));
        }
        return response('', Response::HTTP_NO_CONTENT);
    }

    public function calendarToday()
    {
        $start = now()->startOfDay()->toIso8601String();
        $end   = now()->endOfDay()->toIso8601String();

        $resp = $this->http()->get('https://graph.microsoft.com/v1.0/me/calendarView', [
            'startDateTime' => $start,
            'endDateTime'   => $end,
            '$orderby'      => 'start/dateTime',
        ]);

        if ($resp->failed()) {
            return response()->json(['error' => 'calendar', 'details' => $resp->json()], $resp->status());
        }
        return response()->json(['data' => $resp->json()['value'] ?? []]);
    }

    public function mailUnread()
    {
        $resp = $this->http()->get('https://graph.microsoft.com/v1.0/me/mailFolders/Inbox', [
            '$select' => 'unreadItemCount',
        ]);
        if ($resp->failed()) return response()->json(['error'=>'mail.unread','details'=>$resp->json()], $resp->status());
        return response()->json(['unread' => (int)($resp->json()['unreadItemCount'] ?? 0)]);
    }

    public function mailTop(Request $r)
    {
        $take = (int)($r->query('take', 10));
        $resp = $this->http()->get('https://graph.microsoft.com/v1.0/me/messages', [
            '$top'     => $take,
            '$select'  => 'id,subject,from,receivedDateTime,isRead',
            '$orderby' => 'receivedDateTime DESC',
        ]);
        if ($resp->failed()) return response()->json(['error'=>'mail.top','details'=>$resp->json()], $resp->status());
        return response()->json(['data' => $resp->json()['value'] ?? []]);
    }

    public function driveRecent()
    {
        $resp = $this->http()->get('https://graph.microsoft.com/v1.0/me/drive/recent');
        if ($resp->failed()) return response()->json(['error'=>'drive.recent','details'=>$resp->json()], $resp->status());
        return response()->json(['data' => $resp->json()['value'] ?? []]);
    }

    public function driveItem(string $id)
    {
        $resp = $this->http()->get("https://graph.microsoft.com/v1.0/me/drive/items/{$id}", [
            '$select' => 'id,name,size,lastModifiedDateTime,webUrl,@microsoft.graph.downloadUrl',
        ]);

        if ($resp->failed()) {
            return response()->json(['error'=>'drive.item','details'=>$resp->json()], $resp->status());
        }

        return response()->json($resp->json());
    }

    public function driveDownload(string $id)
    {
        $meta = $this->http()->get("https://graph.microsoft.com/v1.0/me/drive/items/{$id}", [
            '$select' => 'name,@microsoft.graph.downloadUrl',
        ]);

        if ($meta->failed()) {
            return response()->json(['error'=>'drive.download','details'=>$meta->json()], $meta->status());
        }

        $data = $meta->json();
        $name = $data['name'] ?? 'file';
        $url = $data['@microsoft.graph.downloadUrl'] ?? null;

        if (!$url) {
            return response()->json(['error'=>'no_download_url','message'=>'Файл не має доступного downloadUrl'], 404);
        }

        $stream = Http::withOptions(['stream' => true])->get($url);
        return response()->streamDownload(function() use ($stream) {
            foreach ($stream->body() as $chunk) {
                echo $chunk;
            }
        }, $name);
    }

    public function mailShow(string $id)
    {
        $resp = $this->http()->get("https://graph.microsoft.com/v1.0/me/messages/{$id}", [
            '$select' => 'id,subject,from,receivedDateTime,hasAttachments,bodyPreview,importance,isRead,webLink',
        ]);
        if ($resp->failed()) {
            return response()->json(['error'=>'mail.show','details'=>$resp->json()], $resp->status());
        }
        return response()->json($resp->json());
    }

    public function mailAttachments(string $id)
    {
        $resp = $this->http()->get("https://graph.microsoft.com/v1.0/me/messages/{$id}/attachments", [
            '$select' => 'id,name,contentType,size,isInline,@odata.type'
        ]);
        if ($resp->failed()) {
            return response()->json(['error'=>'mail.attachments','details'=>$resp->json()], $resp->status());
        }
        return response()->json(['data' => $resp->json()['value'] ?? []]);
    }

    public function mailAttachmentDownload(string $id, string $attId)
    {
        $resp = $this->http()->get("https://graph.microsoft.com/v1.0/me/messages/{$id}/attachments/{$attId}");
        if ($resp->failed()) {
            return response()->json(['error'=>'mail.attachment','details'=>$resp->json()], $resp->status());
        }

        $att = $resp->json();

        $odataType = $att['@odata.type'] ?? '';
        $name = $att['name'] ?? 'attachment';
        $ctype = $att['contentType'] ?? 'application/octet-stream';

        if (str_ends_with($odataType, 'fileAttachment') && !empty($att['contentBytes'])) {
            $binary = base64_decode($att['contentBytes']);
            return response($binary, 200)
                ->header('Content-Type', $ctype)
                ->header('Content-Disposition', 'attachment; filename="'.addslashes($name).'"');
        }

        if (str_ends_with($odataType, 'referenceAttachment') && !empty($att['contentType'])) {
            return response()->json([
                'error' => 'reference_attachment',
                'message' => 'Це referenceAttachment (посилання на хмарний файл). Відкрий лист у webLink або додатково запитуй sourceUrl через beta/розширення.'
            ], 400);
        }

        if (str_ends_with($odataType, 'itemAttachment')) {
            return response()->json([
                'error' => 'item_attachment',
                'message' => 'Це itemAttachment (вкладений лист/подія). Пряме скачування недоступне.'
            ], 400);
        }

        return response()->json([
            'error' => 'unsupported_attachment',
            'message' => 'Невідомий тип вкладення або відсутні дані.'
        ], 400);
    }


}
