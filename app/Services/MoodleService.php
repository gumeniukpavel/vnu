<?php

namespace App\Services;

use App\Dto\AssignmentDto;
use App\Dto\MoodleCourseDto;
use App\Repositories\UserRepository;
use App\Services\MoodleClient as MoodleClientService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class MoodleService
{
    public function __construct(
        private MoodleClientService $moodleClient,
        private UserRepository $userRepository
    ) {
    }

    public function getAllCourses(): array
    {
        $cacheKey = 'moodle:courses:all';
        
        try {
            $courses = Cache::remember($cacheKey, now()->addMinutes(10), function () {
                return $this->moodleClient->getAllCourses();
            });

            if (isset($courses['courses']) && is_array($courses['courses'])) {
                $courses = $courses['courses'];
            }

            $courses = array_filter($courses, function ($course) {
                return isset($course['id']) &&
                    $course['id'] > 1 &&
                    !empty($course['fullname']) &&
                    ($course['visible'] ?? 1) == 1;
            });

            return array_map(function ($course) {
                return $this->mapCourseToDto($course);
            }, array_values($courses));
        } catch (\RuntimeException $e) {
            Log::warning('Cannot access core_course_get_courses', [
                'error' => $e->getMessage()
            ]);

            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'accessexception') !== false ||
                strpos($errorMessage, 'access') !== false ||
                strpos($errorMessage, 'Виняток з контролю доступу') !== false) {
                throw new \RuntimeException('Access denied: ' . $errorMessage, 403);
            }

            throw $e;
        }
    }

    public function getCourse(int $courseId): ?MoodleCourseDto
    {
        $course = $this->moodleClient->getCourse($courseId);

        if (empty($course)) {
            return null;
        }

        return $this->mapCourseToDto($course);
    }

    public function getCourseContents(int $courseId, int $moodleUserId): array
    {
        $contents = $this->moodleClient->getCourseContents($courseId, $moodleUserId);
        $baseUrl = rtrim(config('services.moodle.base_url'), '/');
        $token = config('services.moodle.token');

        return array_map(function ($section) use ($baseUrl, $token) {
            if (isset($section['modules']) && is_array($section['modules'])) {
                $section['modules'] = array_map(function ($module) use ($baseUrl, $token) {
                    if (isset($module['contents']) && is_array($module['contents'])) {
                        $moodle = $this->moodleClient;
                        $module['contents'] = array_map(function ($content) use ($moodle) {
                            if (isset($content['fileurl'])) {
                                $content['fileurl'] = $moodle->getFileUrl($content['fileurl']);
                            }
                            return $content;
                        }, $module['contents']);
                    }

                    if (isset($module['descriptionfiles']) && is_array($module['descriptionfiles'])) {
                        $moodle = $this->moodleClient;
                        $module['descriptionfiles'] = array_map(function ($file) use ($moodle) {
                            if (isset($file['fileurl'])) {
                                $file['fileurl'] = $moodle->getFileUrl($file['fileurl']);
                            }
                            return $file;
                        }, $module['descriptionfiles']);
                    }

                    return $module;
                }, $section['modules']);
            }

            return $section;
        }, $contents);
    }

    public function getMoodleUserIdByEmail(string $email): ?int
    {
        try {
            $moodleUsers = $this->moodleClient->getUsersByField('email', [$email]);
            if (!empty($moodleUsers) && isset($moodleUsers[0]['id'])) {
                return (int)$moodleUsers[0]['id'];
            }
        } catch (\Exception $e) {
            Log::debug('Could not get Moodle user ID', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    public function uploadFileToCourse(
        int $courseId,
        int $moodleUserId,
        string $filename,
        string $fileContent,
        string $name,
        int $sectionId = 0,
        string $intro = ''
    ): array {
        $uploadResponse = $this->moodleClient->uploadFileToUserRepository(
            $moodleUserId,
            $filename,
            $fileContent
        );

        $resourceResponse = $this->moodleClient->createResourceInCourse(
            $courseId,
            $sectionId,
            $name,
            $intro,
            []
        );

        return [
            'upload' => $uploadResponse,
            'resource' => $resourceResponse
        ];
    }

    public function createAssignmentInCourse(
        int $courseId,
        int $sectionId,
        string $name,
        string $intro = '',
        ?string $duedate = null
    ): array {
        return $this->moodleClient->createAssignmentInCourse(
            $courseId,
            $sectionId,
            $name,
            $intro,
            $duedate
        );
    }

    private function mapCourseToDto(array $course): MoodleCourseDto
    {
        $baseUrl = rtrim(config('services.moodle.base_url'), '/');
        $token = config('services.moodle.token');

        $imageUrl = null;
        if (isset($course['overviewfiles']) && is_array($course['overviewfiles']) && !empty($course['overviewfiles'])) {
            foreach ($course['overviewfiles'] as $file) {
                if (isset($file['filename']) && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file['filename'])) {
                    if (isset($file['fileurl'])) {
                        $imageUrl = $this->moodleClient->getFileUrl($file['fileurl']);
                        break;
                    }
                }
            }
        }

        $courseUrl = null;
        if (isset($course['id'])) {
            $courseUrl = $baseUrl . '/course/view.php?id=' . $course['id'];
        }

        return new MoodleCourseDto(
            id: (int)$course['id'],
            fullname: $course['fullname'] ?? '',
            shortname: $course['shortname'] ?? '',
            summary: $course['summary'] ?? '',
            summaryFormat: $course['summaryformat'] ?? 1,
            image: $imageUrl,
            courseUrl: $courseUrl,
            categoryId: $course['categoryid'] ?? null,
            startDate: $course['startdate'] ?? null,
            endDate: $course['enddate'] ?? null,
            visible: $course['visible'] ?? 1,
            assignments: []
        );
    }

    private function extractAssignmentsFromContents(array $contents, int $courseId, ?string $courseName = null): array
    {
        $assignments = [];

        foreach ($contents as $section) {
            if (!isset($section['modules']) || !is_array($section['modules'])) {
                continue;
            }

            foreach ($section['modules'] as $module) {
                if (isset($module['modname']) && $module['modname'] === 'assign') {
                    $dueDate = null;
                    $allowSubmissionsFromDate = null;

                    if (isset($module['customdata']) && !empty($module['customdata'])) {
                        $customData = json_decode($module['customdata'], true);
                        if (is_array($customData)) {
                            if (isset($customData['duedate'])) {
                                $duedateValue = $customData['duedate'];
                                if (is_numeric($duedateValue) && $duedateValue > 0) {
                                    $dueDate = date('c', (int)$duedateValue);
                                }
                            }
                            if (isset($customData['allowsubmissionsfromdate'])) {
                                $allowsubmissionsValue = $customData['allowsubmissionsfromdate'];
                                if (is_numeric($allowsubmissionsValue) && $allowsubmissionsValue > 0) {
                                    $allowSubmissionsFromDate = date('c', (int)$allowsubmissionsValue);
                                }
                            }
                        }
                    }

                    if (!$dueDate && isset($module['dates']) && is_array($module['dates'])) {
                        foreach ($module['dates'] as $date) {
                            if (isset($date['dataid']) && $date['dataid'] === 'duedate' && isset($date['timestamp'])) {
                                $dueDate = date('c', $date['timestamp']);
                            }
                            if (isset($date['dataid']) && $date['dataid'] === 'allowsubmissionsfromdate' && isset($date['timestamp'])) {
                                $allowSubmissionsFromDate = date('c', $date['timestamp']);
                            }
                        }
                    }

                    $status = 'pending';
                    if ($dueDate) {
                        $dueTimestamp = strtotime($dueDate);
                        if ($dueTimestamp < time()) {
                            $status = 'overdue';
                        }
                    }

                    $assignments[] = new AssignmentDto(
                        id: (int)($module['id'] ?? 0),
                        courseId: $courseId,
                        courseName: $courseName,
                        title: $module['name'] ?? 'Без назви',
                        intro: '',
                        dueAt: $dueDate,
                        allowSubmissionsFromDate: $allowSubmissionsFromDate,
                        status: $status,
                        submitted: false,
                        source: 'course_contents',
                    );
                }
            }
        }

        return $assignments;
    }

    public function uploadFileToAssignment(
        int $courseId,
        int $assignmentId,
        int $moodleUserId,
        string $filename,
        string $fileContent
    ): array {
        try {
            $assignments = $this->moodleClient->getAssignmentsForCourses([$courseId], $moodleUserId);
            $assignmentFound = false;
            foreach ($assignments as $assignment) {
                if (isset($assignment['id']) && (int)$assignment['id'] === $assignmentId) {
                    $assignmentFound = true;
                    Log::info('Assignment access verified', [
                        'user_id' => $moodleUserId,
                        'assignment_id' => $assignmentId
                    ]);
                    break;
                }
            }
            
            if (!$assignmentFound) {
                Log::warning('Assignment not found in user accessible assignments', [
                    'user_id' => $moodleUserId,
                    'assignment_id' => $assignmentId,
                    'course_id' => $courseId,
                    'accessible_assignments' => array_map(fn($a) => $a['id'] ?? null, $assignments)
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Could not verify assignment access (this is OK if token lacks mod/assign:view)', [
                'user_id' => $moodleUserId,
                'assignment_id' => $assignmentId,
                'error' => $e->getMessage()
            ]);
        }
        
        $draftItemId = time();
        
        $draftResponse = null;
        $finalDraftItemId = $draftItemId;
        
        try {
            $draftResponse = $this->moodleClient->uploadFileToDraftArea(
                $moodleUserId,
                $filename,
                $fileContent,
                $draftItemId
            );

            $finalDraftItemId = $draftResponse['itemid'] ?? $draftItemId;
            
            Log::info('File uploaded to draft area', [
                'user_id' => $moodleUserId,
                'draft_itemid' => $finalDraftItemId
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to upload file to draft area', [
                'user_id' => $moodleUserId,
                'assignment_id' => $assignmentId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Не вдалося завантажити файл до завдання.');
        }

        try {
            $submissionResponse = $this->moodleClient->saveAssignmentSubmission(
                $assignmentId,
                $moodleUserId,
                $finalDraftItemId
            );

            Log::info('Assignment submission saved successfully', [
                'user_id' => $moodleUserId,
                'assignment_id' => $assignmentId
            ]);

            return [
                'draft' => $draftResponse,
                'submission' => $submissionResponse
            ];
        } catch (\Exception $e) {
            Log::error('Failed to save assignment submission', [
                'user_id' => $moodleUserId,
                'assignment_id' => $assignmentId,
                'draft_itemid' => $finalDraftItemId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \RuntimeException('Не вдалося завантажити файл до завдання.');
        }
    }

    public function getAssignmentsForCourses(array $courseIds, int $moodleUserId): array
    {
        return $this->moodleClient->getAssignmentsForCourses($courseIds, $moodleUserId);
    }
}

