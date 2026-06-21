import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/js/app.js",

                "resources/js/console/users/script.js",
                "resources/js/console/users/edit_script.js",
                "resources/js/console/users/show_script.js",

                "resources/js/console/schools/script.js",
                "resources/js/console/schools/create_script.js",
                "resources/js/console/schools/edit_script.js",

                "resources/js/console/categories/script.js",
                "resources/js/console/categories/create_script.js",
                "resources/js/console/categories/edit_script.js",

                "resources/js/console/admin_schools/script.js",
                "resources/js/console/admin_schools/create_script.js",
                "resources/js/console/admin_schools/edit_script.js",

                "resources/js/console/classrooms/script.js",
                "resources/js/console/classrooms/create_script.js",
                "resources/js/console/classrooms/edit_script.js",

                "resources/js/console/teachers/script.js",
                "resources/js/console/teachers/create_script.js",
                "resources/js/console/teachers/edit_script.js",
                "resources/js/auth/script.js",
                "resources/js/profile/script.js",
                "resources/js/main.js",
                "resources/js/app-logistics-dashboard.js",
            ],
            refresh: true,
        }),
    ],
});
