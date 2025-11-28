<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class MoodleClient
{
    private Client $http;
    private string $token;
    private string $endpoint;

    public function __construct()
    {
        $base = rtrim(config('services.moodle.base_url'), '/');
        $this->token = config('services.moodle.token');
        $this->endpoint = $base . '/webservice/rest/server.php';
        $this->http = new Client([
            'timeout' => 10,
        ]);
    }

    public function call(string $function, array $params = [], string $method = 'GET'): array
    {
        $query = array_merge($params, [
            'wstoken' => $this->token,
            'wsfunction' => $function,
            'moodlewsrestformat' => 'json',
        ]);

        try {
            if ($method === 'POST') {
                $res = $this->http->post($this->endpoint, ['form_params' => $query]);
            } else {
                $res = $this->http->get($this->endpoint, ['query' => $query]);
            }
        } catch (GuzzleException $e) {
            \Log::error('Moodle API request failed', [
                'function' => $function,
                'method' => $method,
                'endpoint' => $this->endpoint,
                'error' => $e->getMessage(),
                'params' => array_diff_key($params, ['wstoken' => '', 'wsfunction' => '', 'moodlewsrestformat' => ''])
            ]);
            throw new \RuntimeException('Moodle request error: ' . $e->getMessage(), 0, $e);
        }

        $statusCode = $res->getStatusCode();
        $body = $res->getBody()->getContents();

        if ($statusCode < 200 || $statusCode >= 300) {
            \Log::error('Moodle API returned non-2xx status', [
                'function' => $function,
                'status_code' => $statusCode,
                'body' => $body
            ]);
            throw new \RuntimeException("Moodle API returned status {$statusCode}: {$body}");
        }

        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            \Log::error('Moodle API returned invalid JSON', [
                'function' => $function,
                'json_error' => json_last_error_msg(),
                'body' => substr($body, 0, 500)
            ]);
            throw new \RuntimeException('Moodle API returned invalid JSON: ' . json_last_error_msg());
        }

        if (isset($data['exception'])) {
            $errorCode = $data['errorcode'] ?? 'unknown';
            $errorMessage = $data['message'] ?? 'No message';
            
            \Log::warning('Moodle API exception', [
                'function' => $function,
                'errorcode' => $errorCode,
                'message' => $errorMessage,
                'params' => array_diff_key($params, ['wstoken' => '', 'wsfunction' => '', 'moodlewsrestformat' => ''])
            ]);
            
            throw new \RuntimeException(sprintf(
                'Moodle exception %s: %s',
                $errorCode,
                $errorMessage
            ));
        }

        return $data ?? [];
    }

    public function getAllCourses(): array
    {
        return $this->call('core_course_get_courses');
    }

    public function getCoursesByField(string $field, string $value): array
    {
        return $this->call('core_course_get_courses_by_field', compact('field', 'value'));
    }

    public function getUserCourses(int $userId): array
    {
        return $this->call('core_enrol_get_users_courses', ['userid' => $userId]);
    }

    public function getUsersByField(string $field, array $values): array
    {
        $params = ['field' => $field];
        foreach ($values as $i => $val) {
            $params["values[$i]"] = $val;
        }
        return $this->call('core_user_get_users_by_field', $params);
    }

    public function getAssignmentsForCourses(array $courseIds, int $userId): array
    {
        if (empty($courseIds)) {
            return [];
        }

        try {
            $params = [
                'courseids' => $courseIds,
                'includenotenrolledcourses' => false,
            ];

            $response = $this->call('mod_assign_get_assignments', $params);

            $assignments = [];
            if (isset($response['courses']) && is_array($response['courses'])) {
                foreach ($response['courses'] as $course) {
                    if (isset($course['assignments']) && is_array($course['assignments'])) {
                        foreach ($course['assignments'] as $assignment) {
                            $assignment['course'] = $course['id'] ?? null;
                            $assignment['submissions'] = $this->getUserSubmissions($assignment['id'], $userId);
                            $assignments[] = $assignment;
                        }
                    }
                }
            }

            return $assignments;
        } catch (\Exception $e) {
            \Log::warning('Failed to fetch Moodle assignments', [
                'course_ids' => $courseIds,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function getUserSubmissions(int $assignmentId, int $userId): array
    {
        try {
            $params = [
                'assignmentids' => [$assignmentId],
                'status' => 'submitted',
                'userids' => [$userId],
            ];
            $response = $this->call('mod_assign_get_submissions', $params);

            if (isset($response['assignments']) && is_array($response['assignments']) && !empty($response['assignments'])) {
                return $response['assignments'][0]['submissions'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getCourseContents(int $courseId, int $userId = 0): array
    {
        $params = [
            'courseid' => $courseId,
        ];

        return $this->call('core_course_get_contents', $params);
    }

    public function getCourse(int $courseId): array
    {
        $response = $this->call('core_course_get_courses_by_field', [
            'field' => 'id',
            'value' => (string)$courseId
        ]);

        if (isset($response['courses']) && is_array($response['courses']) && !empty($response['courses'])) {
            return $response['courses'][0];
        }

        return [];
    }

    public function getFileUrl(string $fileUrl, bool $addToken = true): string
    {
        if (!$addToken || strpos($fileUrl, 'token=') !== false) {
            return $fileUrl;
        }

        $baseUrl = rtrim(config('services.moodle.base_url'), '/');
        $token = config('services.moodle.token');

        if (strpos($fileUrl, 'http') !== 0) {
            $fileUrl = $baseUrl . '/' . ltrim($fileUrl, '/');
        }

        $separator = strpos($fileUrl, '?') !== false ? '&' : '?';
        return $fileUrl . $separator . 'token=' . $token;
    }

    public function getCourseContext(int $courseId): ?int
    {
        try {
            $response = $this->call('core_course_get_courses_by_field', [
                'field' => 'id',
                'value' => (string)$courseId
            ]);

            if (isset($response['courses'][0]['id'])) {
                return null;
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to get course context', [
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    public function uploadFileToUserRepository(int $userId, string $filename, string $fileContent): array
    {
        try {
            $response = $this->call('core_files_upload', [
                'contextid' => 1,
                'component' => 'user',
                'filearea' => 'private',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => $filename,
                'filecontent' => base64_encode($fileContent)
            ], 'POST');

            return $response;
        } catch (\Exception $e) {
            \Log::error('Failed to upload file to Moodle', [
                'user_id' => $userId,
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function createResourceInCourse(
        int $courseId,
        int $sectionId,
        string $name,
        string $intro = '',
        array $files = []
    ): array {
        try {
            $params = [
                'course' => $courseId,
                'name' => $name,
                'intro' => $intro,
                'introformat' => 1,
                'section' => $sectionId,
                'display' => 0,
                'showsize' => 1,
                'showtype' => 1,
            ];

            if (!empty($files)) {
                $params['files'] = $files;
            }

            $response = $this->call('mod_resource_add_instance', $params, 'POST');

            return $response;
        } catch (\Exception $e) {
            \Log::error('Failed to create resource in Moodle course', [
                'course_id' => $courseId,
                'section_id' => $sectionId,
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function createAssignmentInCourse(
        int $courseId,
        int $sectionId,
        string $name,
        string $intro = '',
        ?string $duedate = null
    ): array {
        try {
            $params = [
                'course' => $courseId,
                'name' => $name,
                'intro' => $intro,
                'introformat' => 1,
                'section' => $sectionId,
                'assignsubmission_onlinetext_enabled' => 1,
                'assignsubmission_file_enabled' => 1,
                'assignsubmission_file_maxfiles' => 5,
                'assignsubmission_file_maxsizebytes' => 10485760,
            ];

            if ($duedate) {
                $params['duedate'] = strtotime($duedate);
            }

            $response = $this->call('mod_assign_add_instance', $params, 'POST');

            return $response;
        } catch (\Exception $e) {
            \Log::error('Failed to create assignment in Moodle course', [
                'course_id' => $courseId,
                'section_id' => $sectionId,
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function uploadFileToDraftArea(int $userId, string $filename, string $fileContent, int $itemId = 0): array
    {
        try {
            if ($itemId === 0) {
                $itemId = time();
            }

            $userContext = $this->getUserContext($userId);
            if (!$userContext) {
                throw new \RuntimeException('Failed to get user context for user ID: ' . $userId);
            }

            \Log::debug('Uploading file to draft area', [
                'user_id' => $userId,
                'user_context' => $userContext,
                'filename' => $filename,
                'itemid' => $itemId
            ]);

            $response = $this->call('core_files_upload', [
                'contextid' => $userContext,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $itemId,
                'filepath' => '/',
                'filename' => $filename,
                'filecontent' => base64_encode($fileContent)
            ], 'POST');

            if (!isset($response['itemid'])) {
                $response['itemid'] = $itemId;
            }

            \Log::debug('File uploaded to draft area successfully', [
                'user_id' => $userId,
                'itemid' => $response['itemid']
            ]);

            return $response;
        } catch (\Exception $e) {
            \Log::error('Failed to upload file to draft area', [
                'user_id' => $userId,
                'filename' => $filename,
                'itemid' => $itemId,
                'user_context' => $this->getUserContext($userId),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function getUserContext(int $userId): ?int
    {
        try {
            $users = $this->getUsersByField('id', [$userId]);
            
            if (!empty($users) && isset($users[0]['id'])) {
                $userContextId = $userId + 1;
                return $userContextId;
            }
            
            return $userId + 1;
        } catch (\Exception $e) {
            \Log::warning('Failed to get user context, using approximate value', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return $userId + 1;
        }
    }

    private function getAssignmentContext(int $assignmentId): ?int
    {
        try {
            $response = $this->call('mod_assign_get_assignments', [
                'assignmentids' => [$assignmentId]
            ]);

            if (isset($response['courses'][0]['assignments'][0]['cmid'])) {
                return null;
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to get assignment context', [
                'assignment_id' => $assignmentId,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    public function saveAssignmentSubmission(int $assignmentId, int $userId, int $draftItemId): array
    {
        try {
            $params = [
                'assignmentid' => $assignmentId,
                'userid' => $userId,
            ];

            if ($draftItemId > 0) {
                $params['plugindata'] = [
                    'assignsubmission_file' => [
                        'files_filemanager' => $draftItemId
                    ]
                ];
            }

            \Log::debug('Saving assignment submission', [
                'assignment_id' => $assignmentId,
                'user_id' => $userId,
                'draft_itemid' => $draftItemId,
                'params' => $params
            ]);

            try {
                $response = $this->call('mod_assign_save_submission', $params, 'POST');
                return $response;
            } catch (\Exception $e1) {
                \Log::warning('First structure failed, trying alternative', [
                    'error' => $e1->getMessage()
                ]);
                
                $params['plugindata'] = [
                    'files_filemanager' => $draftItemId
                ];
                
                try {
                    $response = $this->call('mod_assign_save_submission', $params, 'POST');
                    return $response;
                } catch (\Exception $e2) {
                    \Log::warning('Second structure failed, trying without files', [
                        'error' => $e2->getMessage()
                    ]);
                    
                    unset($params['plugindata']);
                    $response = $this->call('mod_assign_save_submission', $params, 'POST');
                    
                    throw $e1;
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to save assignment submission', [
                'assignment_id' => $assignmentId,
                'user_id' => $userId,
                'draft_itemid' => $draftItemId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}
