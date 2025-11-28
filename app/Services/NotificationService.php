<?php

namespace App\Services;

use App\Dto\NotificationDto;
use App\Repositories\NotificationRepository;

final class NotificationService
{
    public function __construct(
        private NotificationRepository $notificationRepository
    ) {
    }

    public function getNotificationsForUser(int $userId, int $limit = 20): array
    {
        $notifications = $this->notificationRepository->findByUserId($userId, $limit);
        
        return $notifications->map(function ($notification) {
            return new NotificationDto(
                id: $notification->id,
                userId: $notification->user_id,
                title: $notification->title ?? '',
                content: $notification->content ?? null,
                type: $notification->type ?? null,
                createdAt: $notification->created_at?->toIso8601String(),
            );
        })->toArray();
    }

    public function getNotificationsForUserAsArray(int $userId, int $limit = 20): array
    {
        return array_map(
            fn(NotificationDto $dto) => $dto->toArray(),
            $this->getNotificationsForUser($userId, $limit)
        );
    }

    public function getNews(): array
    {
        return [
            [
                'id' => 1,
                'title' => 'Важливе оголошення: Зміни в розкладі занять',
                'content' => 'Звертаємо увагу студентів на зміни в розкладі занять на наступний тиждень. Деякі пари перенесені на інші аудиторії. Детальну інформацію дивіться в особистому кабінеті.',
                'author' => 'Деканат факультету',
                'published_at' => now()->subHours(2)->toIso8601String(),
                'type' => 'announcement',
                'priority' => 'high',
            ],
            [
                'id' => 2,
                'title' => 'Нові матеріали навчальних курсів доступні',
                'content' => 'На платформі Moodle опубліковані нові навчальні матеріали з дисциплін "Веб-програмування" та "Бази даних". Рекомендуємо ознайомитися з ними до наступного заняття.',
                'author' => 'Кафедра інформатики',
                'published_at' => now()->subHours(5)->toIso8601String(),
                'type' => 'news',
                'priority' => 'normal',
            ],
            [
                'id' => 3,
                'title' => 'Реєстрація на курси за вибором',
                'content' => 'Розпочато реєстрацію на курси за вибором на наступний семестр. Реєстрація триватиме до 15 грудня. Оберіть курси в особистому кабінеті.',
                'author' => 'Відділ навчальної роботи',
                'published_at' => now()->subDays(1)->toIso8601String(),
                'type' => 'announcement',
                'priority' => 'high',
            ],
            [
                'id' => 4,
                'title' => 'Конкурс наукових робіт студентів',
                'content' => 'Оголошено конкурс наукових робіт серед студентів. Переможці отримають грошові премії та можливість опублікувати роботи в науковому журналі. Дедлайн подачі - 20 грудня.',
                'author' => 'Науково-дослідний відділ',
                'published_at' => now()->subDays(2)->toIso8601String(),
                'type' => 'news',
                'priority' => 'normal',
            ],
            [
                'id' => 5,
                'title' => 'Оновлення системи електронного навчання',
                'content' => 'Проведено оновлення платформи Moodle. Додано нові функції для спільної роботи та покращено інтерфейс. Якщо виникли проблеми, зверніться до технічної підтримки.',
                'author' => 'Відділ інформаційних технологій',
                'published_at' => now()->subDays(3)->toIso8601String(),
                'type' => 'announcement',
                'priority' => 'normal',
            ],
            [
                'id' => 6,
                'title' => 'Бібліотека: нові надходження',
                'content' => 'Університетська бібліотека поповнила фонд новими підручниками з програмування, баз даних та веб-розробки. Книги доступні для використання в читальному залі та на абонементі.',
                'author' => 'Наукова бібліотека',
                'published_at' => now()->subDays(4)->toIso8601String(),
                'type' => 'news',
                'priority' => 'normal',
            ],
        ];
    }
}



