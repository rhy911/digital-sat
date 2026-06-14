import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/auth.css',
                'resources/css/home.css',
                'resources/css/home-progress.css',
                'resources/css/practice.css',
                'resources/css/score-details.css',
                'resources/css/test-dashboard-admin.css',
                'resources/css/test/test-main.css',
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
