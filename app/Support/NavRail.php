<?php

namespace App\Support;

use App\Models\User;

class NavRail
{
    /**
     * Icon-rail items for the given user's role, keyed by the item to mark active.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(User $user, ?string $activeKey = null): array
    {
        return match ($user->role) {
            'admin' => self::admin($activeKey),
            'teacher' => self::teacher($activeKey),
            default => self::student($activeKey),
        };
    }

    /**
     * The icon-rail logo destination for the given user's role.
     */
    public static function logoHrefForUser(User $user): string
    {
        return match ($user->role) {
            'admin' => route('admin.teacher-applications.index'),
            'teacher' => route('teacher.progress'),
            default => route('home'),
        };
    }

    /**
     * Icon-rail items for the teacher/admin shell.
     *
     * @return array<int, array{key: string, route: string, label: string, icon: string, target?: string, active?: bool}>
     */
    public static function teacher(?string $activeKey = null): array
    {
        return self::withActive([
            [
                'key' => 'home',
                'route' => route('teacher.progress'),
                'label' => 'Home',
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z\'/><polyline points=\'9 22 9 12 15 12 15 22\'/></svg>',
            ],
            [
                'key' => 'classes',
                'route' => route('teacher.classes.index'),
                'label' => 'Classes',
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><rect x=\'3.5\' y=\'5\' width=\'17\' height=\'14\' rx=\'2\' /><path d=\'M3.5 9.5h17M8 5v-1M16 5v-1\' stroke-linecap=\'round\' /></svg>',
            ],
            [
                'key' => 'reports',
                'route' => route('teacher.assignments.index'),
                'label' => 'Reports',
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M5 3v18h16\' stroke-linecap=\'round\' /><rect x=\'8\' y=\'12\' width=\'3\' height=\'6\' /><rect x=\'13\' y=\'8\' width=\'3\' height=\'10\' /><rect x=\'18\' y=\'5\' width=\'3\' height=\'13\' /></svg>',
            ],
            [
                'key' => 'practice',
                'route' => route('home.practice'),
                'label' => 'Test Library',
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M12 6.5c-1.6-1.2-3.7-1.8-6-1.8-.7 0-1.4.05-2 .15v13.5c.6-.1 1.3-.15 2-.15 2.3 0 4.4.6 6 1.8m0-13.5c1.6-1.2 3.7-1.8 6-1.8.7 0 1.4.05 2 .15v13.5c-.6-.1-1.3-.15-2-.15-2.3 0-4.4.6-6 1.8m0-13.5v13.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /></svg>',
            ],
            [
                'key' => 'scores',
                'route' => route('student.scores.index'),
                'label' => 'My Scores',
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><line x1=\'18\' y1=\'20\' x2=\'18\' y2=\'10\'/><line x1=\'12\' y1=\'20\' x2=\'12\' y2=\'4\'/><line x1=\'6\' y1=\'20\' x2=\'6\' y2=\'14\'/></svg>',
            ],
            [
                'key' => 'test-builder',
                'route' => route('home-dashboard.index'),
                'label' => 'Test Builder',
                'target' => '_blank',
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M14.7 6.3a3 3 0 0 0 4 4L14 15l-4 1 1-4Z\' stroke-linejoin=\'round\' /></svg>',
            ],
        ], $activeKey);
    }

    /**
     * Icon-rail items for the admin shell. Admins only manage teacher
     * verification and the test builder — no classroom/progress access.
     *
     * @return array<int, array{key: string, route: string, label: string, icon: string, active?: bool}>
     */
    public static function admin(?string $activeKey = null): array
    {
        return self::withActive([
            [
                'key' => 'applications',
                'route' => route('admin.teacher-applications.index'),
                'label' => 'Teacher applications',
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /><circle cx=\'8.5\' cy=\'7\' r=\'4\' /><polyline points=\'17 11 19 13 23 9\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /></svg>',
            ],
            [
                'key' => 'test-builder',
                'route' => route('home-dashboard.index'),
                'label' => 'Test Builder',
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M14.7 6.3a3 3 0 0 0 4 4L14 15l-4 1 1-4Z\' stroke-linejoin=\'round\' /></svg>',
            ],
        ], $activeKey);
    }

    /**
     * Icon-rail items for the student shell.
     *
     * @return array<int, array{key: string, route: string, label: string, icon: string, active?: bool}>
     */
    public static function student(?string $activeKey = null): array
    {
        return self::withActive([
            [
                'key' => 'home',
                'route' => route('home'),
                'label' => __('classroom.nav_dashboard'),
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z\'/><polyline points=\'9 22 9 12 15 12 15 22\'/></svg>',
            ],
            [
                'key' => 'classes',
                'route' => route('student.classes.index'),
                'label' => __('classroom.nav_my_classes'),
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><rect x=\'3.5\' y=\'5\' width=\'17\' height=\'14\' rx=\'2\' /><path d=\'M3.5 9.5h17M8 5v-1M16 5v-1\' stroke-linecap=\'round\' /></svg>',
            ],
            [
                'key' => 'assignments',
                'route' => route('student.assignments.index'),
                'label' => 'Assignments',
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><rect x=\'3\' y=\'4\' width=\'18\' height=\'16\' rx=\'2\' /><path d=\'M7 8h10M7 12h10M7 16h6\' stroke-linecap=\'round\' /></svg>',
            ],
            [
                'key' => 'progress',
                'route' => route('student.progress'),
                'label' => __('classroom.nav_progress'),
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M4 19V5M4 19h16M8 15l3-4 3 3 5-7\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /></svg>',
            ],
            [
                'key' => 'practice',
                'route' => route('home.practice'),
                'label' => __('classroom.nav_practice'),
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M12 6.5c-1.6-1.2-3.7-1.8-6-1.8-.7 0-1.4.05-2 .15v13.5c.6-.1 1.3-.15 2-.15 2.3 0 4.4.6 6 1.8m0-13.5c1.6-1.2 3.7-1.8 6-1.8.7 0 1.4.05 2 .15v13.5c-.6-.1-1.3-.15-2-.15-2.3 0-4.4.6-6 1.8m0-13.5v13.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /></svg>',
            ],
            [
                'key' => 'scores',
                'route' => route('student.scores.index'),
                'label' => __('classroom.nav_scores'),
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><line x1=\'18\' y1=\'20\' x2=\'18\' y2=\'10\'/><line x1=\'12\' y1=\'20\' x2=\'12\' y2=\'4\'/><line x1=\'6\' y1=\'20\' x2=\'6\' y2=\'14\'/></svg>',
            ],
        ], $activeKey);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function withActive(array $items, ?string $activeKey): array
    {
        if ($activeKey === null) {
            return $items;
        }

        foreach ($items as &$item) {
            if ($item['key'] === $activeKey) {
                $item['active'] = true;
            }
        }

        return $items;
    }
}
