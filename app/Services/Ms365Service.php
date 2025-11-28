<?php

namespace App\Services;

use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class Ms365Service
{
    public function __construct(
        private UserRepository $userRepository
    ) {
    }

    public function ensureValidToken(int $userId): string
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new \RuntimeException('Not authenticated', 401);
        }

        $accessToken = $user->azure_access_token;
        if (!$accessToken) {
            throw new \RuntimeException('No access token', 401);
        }

        $expiresAt = $user->azure_token_expires_at;
        if ($expiresAt && ($expiresAt->isPast() || $expiresAt->isBefore(now()->addMinutes(5)))) {
            $this->refreshToken($user);
            $user->refresh();
            $accessToken = $user->azure_access_token;
        }

        return $accessToken;
    }

    private function refreshToken($user): void
    {
        $refreshToken = $user->azure_refresh_token;
        if (!$refreshToken) {
            throw new \RuntimeException('No refresh token available. Please re-authenticate.', 401);
        }

        $tenantId = $user->azure_tenant_id ?? config('services.azure.tenant', 'organizations');
        $clientId = config('services.azure.client_id');
        $clientSecret = config('services.azure.client_secret');

        if (!$clientId || !$clientSecret) {
            throw new \RuntimeException('Azure OAuth configuration is missing', 500);
        }

        $tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";

        $response = Http::asForm()->post($tokenUrl, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => 'openid profile email offline_access User.Read Calendars.Read Mail.Read Mail.Send Contacts.Read Files.Read',
        ]);

        if ($response->failed()) {
            $error = $response->json();
            throw new \RuntimeException(
                'Failed to refresh token: ' . ($error['error_description'] ?? $error['error'] ?? 'Unknown error'),
                401
            );
        }

        $data = $response->json();
        $newAccessToken = $data['access_token'] ?? null;
        $newRefreshToken = $data['refresh_token'] ?? $refreshToken;
        $expiresIn = (int)($data['expires_in'] ?? 3600);

        if (!$newAccessToken) {
            throw new \RuntimeException('No access token in refresh response', 500);
        }

        $this->userRepository->updateAzureTokens(
            $user->id,
            $newAccessToken,
            $newRefreshToken,
            now()->addSeconds($expiresIn)
        );
    }

    private function http(int $userId)
    {
        $token = $this->ensureValidToken($userId);
        return Http::withToken($token)
            ->acceptJson()
            ->withHeaders([
                'ConsistencyLevel' => 'eventual',
                'Prefer' => 'outlook.timezone="Europe/Kyiv"',
            ]);
    }

    public function getMe(int $userId): array
    {
        $resp = $this->http($userId)->get('https://graph.microsoft.com/v1.0/me');

        if ($resp->failed()) {
            throw new \RuntimeException('Failed to get user info', $resp->status());
        }

        return $resp->json();
    }

    public function getPhoto(int $userId): ?string
    {
        $resp = $this->http($userId)->get('https://graph.microsoft.com/v1.0/me/photo/$value');

        if ($resp->successful()) {
            return $resp->body();
        }

        return null;
    }

    public function getCalendarToday(int $userId): array
    {
        $start = now()->startOfDay()->toIso8601String();
        $end = now()->endOfDay()->toIso8601String();

        $resp = $this->http($userId)->get('https://graph.microsoft.com/v1.0/me/calendarView', [
            'startDateTime' => $start,
            'endDateTime' => $end,
            '$orderby' => 'start/dateTime',
        ]);

        if ($resp->failed()) {
            throw new \RuntimeException('Failed to get calendar', $resp->status());
        }

        return $resp->json()['value'] ?? [];
    }

    public function getMailUnread(int $userId): int
    {
        $resp = $this->http($userId)->get('https://graph.microsoft.com/v1.0/me/mailFolders/Inbox', [
            '$select' => 'unreadItemCount',
        ]);

        if ($resp->failed()) {
            $error = $resp->json();
            $errorMessage = $error['error']['message'] ?? $error['error']['code'] ?? 'Failed to get unread count';
            throw new \RuntimeException($errorMessage, $resp->status());
        }

        return (int)($resp->json()['unreadItemCount'] ?? 0);
    }

    public function getMailTop(int $userId, int $take = 10, string $folder = 'Inbox', int $skip = 0): array
    {
        $folderPath = $folder === 'SentItems' 
            ? 'https://graph.microsoft.com/v1.0/me/mailFolders/SentItems/messages'
            : 'https://graph.microsoft.com/v1.0/me/messages';
        
        $selectFields = $folder === 'SentItems'
            ? 'id,subject,toRecipients,sentDateTime,isRead'
            : 'id,subject,from,receivedDateTime,isRead';

        $orderByField = $folder === 'SentItems' ? 'sentDateTime' : 'receivedDateTime';

        $params = [
            '$top' => $take,
            '$select' => $selectFields,
            '$orderby' => $orderByField . ' DESC',
            '$count' => 'true',
        ];

        if ($skip > 0) {
            $params['$skip'] = $skip;
        }

        $resp = $this->http($userId)->get($folderPath, $params);

        if ($resp->failed()) {
            throw new \RuntimeException('Failed to get messages', $resp->status());
        }

        $json = $resp->json();
        return [
            'messages' => $json['value'] ?? [],
            'total' => $json['@odata.count'] ?? null,
        ];
    }

    public function getDriveRecent(int $userId): array
    {
        $resp = $this->http($userId)->get('https://graph.microsoft.com/v1.0/me/drive/recent');

        if ($resp->failed()) {
            throw new \RuntimeException('Failed to get recent files', $resp->status());
        }

        return $resp->json()['value'] ?? [];
    }

    public function getDriveItem(int $userId, string $id): array
    {
        $resp = $this->http($userId)->get("https://graph.microsoft.com/v1.0/me/drive/items/{$id}", [
            '$select' => 'id,name,size,lastModifiedDateTime,webUrl,@microsoft.graph.downloadUrl,file',
        ]);

        if ($resp->failed()) {
            throw new \RuntimeException('Failed to get drive item', $resp->status());
        }

        return $resp->json();
    }

    public function getDriveDownloadUrl(int $userId, string $id): ?string
    {
        $meta = $this->getDriveItem($userId, $id);
        if (isset($meta['@microsoft.graph.downloadUrl'])) {
            return $meta['@microsoft.graph.downloadUrl'];
        }
        return null;
    }

    public function getDriveItemContent(int $userId, string $id): ?string
    {
        try {
            $resp = $this->http($userId)->get("https://graph.microsoft.com/v1.0/me/drive/items/{$id}/content");
            if ($resp->successful()) {
                return $resp->body();
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to get drive item content', [
                'item_id' => $id,
                'error' => $e->getMessage()
            ]);
        }
        return null;
    }

    public function getMailMessage(int $userId, string $id): array
    {
        $resp = $this->http($userId)->get("https://graph.microsoft.com/v1.0/me/messages/{$id}", [
            '$select' => 'id,subject,from,toRecipients,receivedDateTime,sentDateTime,hasAttachments,bodyPreview,importance,isRead,webLink',
        ]);

        if ($resp->failed()) {
            throw new \RuntimeException('Failed to get message', $resp->status());
        }

        return $resp->json();
    }

    public function getMailAttachments(int $userId, string $id): array
    {
        $resp = $this->http($userId)->get("https://graph.microsoft.com/v1.0/me/messages/{$id}/attachments", [
            '$select' => 'id,name,contentType,size,isInline,@odata.type'
        ]);

        if ($resp->failed()) {
            throw new \RuntimeException('Failed to get attachments', $resp->status());
        }

        return $resp->json()['value'] ?? [];
    }

    public function getMailAttachment(int $userId, string $id, string $attId): array
    {
        $resp = $this->http($userId)->get("https://graph.microsoft.com/v1.0/me/messages/{$id}/attachments/{$attId}");

        if ($resp->failed()) {
            throw new \RuntimeException('Failed to get attachment', $resp->status());
        }

        return $resp->json();
    }

    public function sendMailReply(int $userId, string $messageId, string $body, ?string $subject = null): array
    {
        $originalMessage = $this->getMailMessage($userId, $messageId);
        
        $replySubject = $subject ?? 'Re: ' . ($originalMessage['subject'] ?? '');
        
        $formattedBody = '<div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e0e0e0;">' .
            '<p>' . nl2br(htmlspecialchars($body)) . '</p>' .
            '</div>';
        
        $payload = [
            'message' => [
                'subject' => $replySubject,
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $formattedBody
                ]
            ],
            'comment' => ''
        ];

        $resp = $this->http($userId)->post("https://graph.microsoft.com/v1.0/me/messages/{$messageId}/reply", $payload);

        if ($resp->failed()) {
            $error = $resp->json();
            $errorMessage = $error['error']['message'] ?? $error['error']['code'] ?? 'Failed to send reply';
            throw new \RuntimeException($errorMessage, $resp->status());
        }

        return $resp->json() ?? [];
    }
}



