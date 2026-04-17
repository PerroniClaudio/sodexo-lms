import { globSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const viteInputs = [
    'resources/css/app.css',
    ...globSync('resources/js/**/*.js')
        .sort()
        .filter((path) => path !== 'resources/js/bootstrap.js'),
];

export default defineConfig({
    plugins: [
        laravel({
            input: viteInputs,
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
