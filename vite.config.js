import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/auth.css',
                'resources/css/student/dashboard.css',
                'resources/css/student/analytics.css',
                'resources/css/student/practice.css',
                'resources/css/student/scores.css',
                'resources/css/admin/test-builder.css',
                'resources/css/engine/main.css',
                'resources/sass/app.scss',
                'resources/js/app.js',
                'resources/js/auth.js',
                'resources/js/test.js',
                'resources/js/test-dashboard.js'
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
