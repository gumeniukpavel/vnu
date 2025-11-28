<?php

namespace App\Http\Controllers\Api;

use App\Services\Ms365Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class Ms365Controller
{
    public function __construct(
        private Ms365Service $ms365Service
    ) {
    }

    public function me(Request $request): JsonResponse
    {
        try {
            $data = $this->ms365Service->getMe($request->user()->id);
            return response()->json($data);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function photo(Request $request): Response
    {
        try {
            $photo = $this->ms365Service->getPhoto($request->user()->id);
            if ($photo) {
                return response($photo, 200)->header('Content-Type', 'image/jpeg');
            }
            return response('', Response::HTTP_NO_CONTENT);
        } catch (\RuntimeException $e) {
            return response('', Response::HTTP_NO_CONTENT);
        }
    }

    public function calendarToday(Request $request): JsonResponse
    {
        try {
            $data = $this->ms365Service->getCalendarToday($request->user()->id);
            return response()->json(['data' => $data]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'calendar', 'details' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function mailUnread(Request $request): JsonResponse
    {
        try {
            $unread = $this->ms365Service->getMailUnread($request->user()->id);
            return response()->json(['unread' => $unread]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'mail.unread', 'details' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function mailTop(Request $request): JsonResponse
    {
        try {
            $take = (int)($request->query('take', 10));
            $skip = (int)($request->query('skip', 0));
            $folder = $request->query('folder', 'Inbox'); // Inbox або SentItems
            $result = $this->ms365Service->getMailTop($request->user()->id, $take, $folder, $skip);
            return response()->json([
                'data' => $result['messages'] ?? [],
                'total' => $result['total'] ?? null,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'mail.top', 'details' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function driveRecent(Request $request): JsonResponse
    {
        try {
            $data = $this->ms365Service->getDriveRecent($request->user()->id);
            return response()->json(['data' => $data]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'drive.recent', 'details' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function driveDownload(Request $request, string $id): Response|JsonResponse
    {
        try {
            $meta = $this->ms365Service->getDriveItem($request->user()->id, $id);
            $name = $meta['name'] ?? 'file';
            $url = $this->ms365Service->getDriveDownloadUrl($request->user()->id, $id);

            if ($url) {
                $stream = \Illuminate\Support\Facades\Http::withOptions(['stream' => true])->get($url);
                return response()->streamDownload(function () use ($stream) {
                    foreach ($stream->body() as $chunk) {
                        echo $chunk;
                    }
                }, $name);
            }

            $content = $this->ms365Service->getDriveItemContent($request->user()->id, $id);
            if ($content !== null) {
                $contentType = $meta['file']['mimeType'] ?? 'application/octet-stream';
                return response($content, 200)
                    ->header('Content-Type', $contentType)
                    ->header('Content-Disposition', 'attachment; filename="' . addslashes($name) . '"');
            }

            if (isset($meta['webUrl'])) {
                return redirect($meta['webUrl']);
            }

            return response()->json([
                'error' => 'no_download_url',
                'message' => 'Файл не має доступного downloadUrl'
            ], 404);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'drive.download', 'details' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function mailShow(Request $request, string $id): JsonResponse
    {
        try {
            $data = $this->ms365Service->getMailMessage($request->user()->id, $id);
            return response()->json($data);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'mail.show', 'details' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function mailAttachments(Request $request, string $id): JsonResponse
    {
        try {
            $data = $this->ms365Service->getMailAttachments($request->user()->id, $id);
            return response()->json(['data' => $data]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'mail.attachments', 'details' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function mailAttachmentDownload(Request $request, string $id, string $attId): Response|JsonResponse
    {
        try {
            $att = $this->ms365Service->getMailAttachment($request->user()->id, $id, $attId);

            $odataType = $att['@odata.type'] ?? '';
            $name = $att['name'] ?? 'attachment';
            $ctype = $att['contentType'] ?? 'application/octet-stream';

            if (str_ends_with($odataType, 'fileAttachment') && !empty($att['contentBytes'])) {
                $binary = base64_decode($att['contentBytes']);
                return response($binary, 200)
                    ->header('Content-Type', $ctype)
                    ->header('Content-Disposition', 'attachment; filename="' . addslashes($name) . '"');
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
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'mail.attachment', 'details' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function mailReply(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'body' => 'required|string',
                'subject' => 'nullable|string|max:255',
            ]);

            $this->ms365Service->sendMailReply(
                $request->user()->id,
                $id,
                $request->input('body'),
                $request->input('subject')
            );

            return response()->json([
                'success' => true,
                'message' => 'Відповідь успішно відправлено'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'mail.reply', 'details' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}
