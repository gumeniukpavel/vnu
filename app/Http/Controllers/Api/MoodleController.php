<?php

namespace App\Http\Controllers\Api;

use App\Services\MoodleService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

final class MoodleController
{
    public function __construct(
        private MoodleService $moodleService,
        private UserService $userService
    ) {
    }

    public function allCourses(Request $request): JsonResponse
    {
        try {
            $courses = $this->moodleService->getAllCourses();
            $coursesArray = array_map(fn($dto) => $dto->toArray(), $courses);

            $user = $request->user();
            $userDto = $this->userService->getUserDto($user->id);
            
            if ($userDto && $userDto->email) {
                $moodleUserId = $this->moodleService->getMoodleUserIdByEmail($userDto->email);
                
                if ($moodleUserId) {
                    foreach ($coursesArray as &$course) {
                        try {
                            $contents = $this->moodleService->getCourseContents($course['id'], $moodleUserId);
                            $assignments = $this->extractAssignmentsFromContents($contents, $course['id'], $course['fullname'] ?? null);
                            $assignments = array_filter($assignments, fn($a) => !empty($a['due_at']));
                            $course['assignments'] = array_values($assignments);
                        } catch (\Exception $e) {
                            Log::debug('Failed to get contents for course in allCourses', [
                                'course_id' => $course['id'],
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            }

            return response()->json($coursesArray);
        } catch (\RuntimeException $e) {
            $errorMessage = $e->getMessage();

            if ($e->getCode() === 403 || strpos($errorMessage, 'Access denied') !== false) {
                return response()->json([
                    'error' => 'Access denied',
                    'message' => 'Токен не має доступу до перегляду всіх курсів. ' .
                        'Перевірте права доступу токена в Moodle: ' .
                        'Site administration → Server → Web services → Manage tokens. ' .
                        'Потрібні права: core/course:view',
                    'details' => $errorMessage,
                    'courses' => []
                ], 403);
            }

            return response()->json([
                'error' => 'Failed to fetch courses from Moodle',
                'message' => $errorMessage,
                'courses' => []
            ], 500);
        } catch (\Exception $e) {
            Log::error('Moodle courses fetch error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to fetch courses from Moodle',
                'message' => $e->getMessage(),
                'courses' => []
            ], 500);
        }
    }

    public function getCourse(Request $request, int $courseId): JsonResponse
    {
        try {
            $courseDto = $this->moodleService->getCourse($courseId);

            if (!$courseDto) {
                return response()->json([
                    'error' => 'Course not found',
                    'message' => 'Курс не знайдено'
                ], 404);
            }

            return response()->json($courseDto->toArray());
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch course',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getCourseContents(Request $request, int $courseId): JsonResponse
    {
        try {
            $user = $request->user();
            $userDto = $this->userService->getUserDto($user->id);

            if (!$userDto || !$userDto->email) {
                return response()->json([
                    'error' => 'User not found',
                    'message' => 'Користувач не знайдено'
                ], 404);
            }

            $moodleUserId = $this->moodleService->getMoodleUserIdByEmail($userDto->email);
            if (!$moodleUserId) {
                return response()->json([
                    'error' => 'Moodle user not found',
                    'message' => 'Користувач не знайдено в Moodle'
                ], 404);
            }

            $contents = $this->moodleService->getCourseContents($courseId, $moodleUserId);
            return response()->json($contents);
        } catch (\Exception $e) {
            Log::error('Moodle course contents fetch error', [
                'course_id' => $courseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to fetch course contents',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadFileToCourse(Request $request, int $courseId): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|max:10240',
                'name' => 'required|string|max:255',
                'section_id' => 'nullable|integer',
                'intro' => 'nullable|string',
            ]);

            $file = $request->file('file');
            $name = $request->input('name', $file->getClientOriginalName());
            $sectionId = $request->input('section_id', 0);
            $intro = $request->input('intro', '');

            $user = $request->user();
            $userDto = $this->userService->getUserDto($user->id);

            if (!$userDto || !$userDto->email) {
                return response()->json([
                    'error' => 'User not found',
                    'message' => 'Користувач не знайдено'
                ], 404);
            }

            $moodleUserId = $this->moodleService->getMoodleUserIdByEmail($userDto->email);
            if (!$moodleUserId) {
                return response()->json([
                    'error' => 'Moodle user not found',
                    'message' => 'Користувач не знайдено в Moodle. Потрібна авторизація через Moodle.'
                ], 404);
            }

            $fileContent = file_get_contents($file->getRealPath());
            $filename = $file->getClientOriginalName();

            $result = $this->moodleService->uploadFileToCourse(
                $courseId,
                $moodleUserId,
                $filename,
                $fileContent,
                $name,
                $sectionId,
                $intro
            );

            return response()->json([
                'success' => true,
                'message' => 'Файл успішно завантажено',
                'data' => $result
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'Помилка валідації',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('File upload error', [
                'course_id' => $courseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Upload failed',
                'message' => 'Не вдалося завантажити файл у Moodle. ' .
                    'Перевірте права доступу токена та налаштування Moodle. ' .
                    'Помилка: ' . $e->getMessage(),
                'details' => [
                    'required_permissions' => [
                        'moodle/course:managefiles',
                        'moodle/course:manageactivities',
                        'repository/upload:view'
                    ],
                    'note' => 'Можливо, потрібно налаштувати права доступу для токена в Moodle'
                ]
            ], 500);
        }
    }

    public function createAssignment(Request $request, int $courseId): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'intro' => 'required|string',
                'duedate' => 'nullable|date',
                'section_id' => 'required|integer',
            ]);

            $name = $request->input('name');
            $intro = $request->input('intro');
            $duedate = $request->input('duedate');
            $sectionId = $request->input('section_id');

            $response = $this->moodleService->createAssignmentInCourse(
                $courseId,
                $sectionId,
                $name,
                $intro,
                $duedate
            );

            return response()->json([
                'success' => true,
                'message' => 'Завдання успішно створено',
                'data' => $response
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'Помилка валідації',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Assignment creation error', [
                'course_id' => $courseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Creation failed',
                'message' => 'Не вдалося створити завдання у Moodle. ' .
                    'Перевірте права доступу токена та налаштування Moodle. ' .
                    'Помилка: ' . $e->getMessage(),
                'details' => [
                    'required_permissions' => [
                        'moodle/course:manageactivities',
                        'mod/assign:addinstance'
                    ],
                    'note' => 'Можливо, потрібно налаштувати права доступу для токена в Moodle'
                ]
            ], 500);
        }
    }

    public function uploadFileToAssignment(Request $request, int $courseId, int $assignmentId): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|max:10240',
            ]);

            $file = $request->file('file');
            $user = $request->user();
            $userDto = $this->userService->getUserDto($user->id);

            if (!$userDto || !$userDto->email) {
                return response()->json([
                    'error' => 'User not found',
                    'message' => 'Користувач не знайдено'
                ], 404);
            }

            $moodleUserId = $this->moodleService->getMoodleUserIdByEmail($userDto->email);
            if (!$moodleUserId) {
                return response()->json([
                    'error' => 'Moodle user not found',
                    'message' => 'Користувач не знайдено в Moodle. Потрібна авторизація через Moodle.'
                ], 404);
            }

            try {
                $course = $this->moodleService->getCourse($courseId);
                if (!$course) {
                    return response()->json([
                        'error' => 'Course not found',
                        'message' => 'Курс не знайдено'
                    ], 404);
                }
            } catch (\Exception $e) {
                Log::warning('Could not verify course access', [
                    'course_id' => $courseId,
                    'user_id' => $moodleUserId,
                    'error' => $e->getMessage()
                ]);
            }

            $fileContent = file_get_contents($file->getRealPath());
            $filename = $file->getClientOriginalName();

            Log::info('Attempting to upload file to assignment', [
                'course_id' => $courseId,
                'assignment_id' => $assignmentId,
                'moodle_user_id' => $moodleUserId,
                'user_email' => $userDto->email,
                'filename' => $filename
            ]);

            $result = $this->moodleService->uploadFileToAssignment(
                $courseId,
                $assignmentId,
                $moodleUserId,
                $filename,
                $fileContent
            );

            return response()->json([
                'success' => true,
                'message' => 'Файл успішно завантажено до завдання',
                'data' => $result
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'Помилка валідації',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Assignment file upload error', [
                'course_id' => $courseId,
                'assignment_id' => $assignmentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Upload failed',
                'message' => 'Не вдалося завантажити файл до завдання.'
            ], 500);
        }
    }

    public function checkAssignmentAccess(Request $request, int $courseId, int $assignmentId): JsonResponse
    {
        try {
            $user = $request->user();
            $userDto = $this->userService->getUserDto($user->id);

            if (!$userDto || !$userDto->email) {
                return response()->json([
                    'error' => 'User not found',
                    'message' => 'Користувач не знайдено'
                ], 404);
            }

            $moodleUserId = $this->moodleService->getMoodleUserIdByEmail($userDto->email);
            if (!$moodleUserId) {
                return response()->json([
                    'error' => 'Moodle user not found',
                    'message' => 'Користувач не знайдено в Moodle'
                ], 404);
            }

            $assignments = $this->moodleService->getAssignmentsForCourses([$courseId], $moodleUserId);
            $assignmentFound = false;
            $assignmentInfo = null;
            
            foreach ($assignments as $assignment) {
                if (isset($assignment['id']) && (int)$assignment['id'] === $assignmentId) {
                    $assignmentFound = true;
                    $assignmentInfo = $assignment;
                    break;
                }
            }

            return response()->json([
                'success' => true,
                'user_id' => $moodleUserId,
                'user_email' => $userDto->email,
                'assignment_found' => $assignmentFound,
                'assignment_info' => $assignmentInfo,
                'all_assignments_in_course' => array_map(function($a) {
                    return [
                        'id' => $a['id'] ?? null,
                        'name' => $a['name'] ?? null,
                        'course' => $a['course'] ?? null
                    ];
                }, $assignments)
            ]);
        } catch (\Exception $e) {
            Log::error('Assignment access check error', [
                'course_id' => $courseId,
                'assignment_id' => $assignmentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Check failed',
                'message' => 'Не вдалося перевірити доступ: ' . $e->getMessage()
            ], 500);
        }
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

                    $assignments[] = [
                        'id' => (int)($module['id'] ?? 0),
                        'course_id' => $courseId,
                        'course_name' => $courseName,
                        'title' => $module['name'] ?? 'Без назви',
                        'intro' => '',
                        'due_at' => $dueDate,
                        'allowsubmissionsfromdate' => $allowSubmissionsFromDate,
                        'status' => $status,
                        'submitted' => false,
                        'url' => $module['url'] ?? null,
                        'source' => 'course_contents',
                    ];
                }
            }
        }

        return $assignments;
    }
}
