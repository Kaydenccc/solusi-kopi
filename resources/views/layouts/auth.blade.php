<!doctype html>

<html lang="en" class="light-style layout-wide customizer-hide" dir="ltr" data-theme="theme-default"
    data-assets-path="{{ asset('/materialize') }}/assets/" data-template="vertical-menu-template-no-customizer"
    data-style="light">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>@yield('title') | {{ config('app.name') }}</title>

    <meta name="description"
        content="
    System Management Inventory is a web-based application that is used to manage the inventory of goods in a company. This application is built using the Laravel 8 framework and the {{ config('app.name') }} CSS framework. This application is equipped with features that can help companies manage their goods inventory. This application is also equipped with a role-based access control system that can help companies manage their users. This application is also equipped with a feature to manage the purchase and sale of goods. This application is also equipped with a feature to manage the goods that have been entered into the warehouse. This application is also equipped with a feature to manage the goods that have been sold. This application is also equipped with a feature to manage the goods that have been purchased
    ">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('/materialize') }}/assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&ampdisplay=swap"
        rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="{{ asset('/materialize') }}/assets/vendor/fonts/remixicon/remixicon.css" />
    <link rel="stylesheet" href="{{ asset('/materialize') }}/assets/vendor/fonts/flag-icons.css" />

    <!-- Menu waves for no-customizer fix -->
    <link rel="stylesheet" href="{{ asset('/materialize') }}/assets/vendor/libs/node-waves/node-waves.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('/materialize') }}/assets/vendor/css/rtl/core.css" />
    <link rel="stylesheet" href="{{ asset('/materialize') }}/assets/vendor/css/rtl/theme-default.css" />
    <link rel="stylesheet" href="{{ asset('/materialize') }}/assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet"
        href="{{ asset('/materialize') }}/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="{{ asset('/materialize') }}/assets/vendor/libs/typeahead-js/typeahead.css" />
    <!-- Vendor -->
    <link rel="stylesheet" href="{{ asset('/materialize') }}/assets/vendor/libs/@form-validation/form-validation.css" />

    <!-- Page CSS -->
    <!-- Page -->
    <link rel="stylesheet" href="{{ asset('/materialize') }}/assets/vendor/css/pages/page-auth.css" />

    <!-- Helpers -->
    <script src="{{ asset('/materialize') }}/assets/vendor/js/helpers.js"></script>
    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="{{ asset('/materialize') }}/assets/js/config.js"></script>

    <link rel="stylesheet" href="{{ asset('/materialize') }}/assets/vendor/libs/toastr/toastr.css" />

    @stack('styles')
</head>

<body>
    <!-- Content -->

    @yield('content')

    <!-- / Content -->

    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="{{ asset('/materialize') }}/assets/vendor/libs/jquery/jquery.js"></script>
    <script src="{{ asset('/materialize') }}/assets/vendor/libs/popper/popper.js"></script>
    <script src="{{ asset('/materialize') }}/assets/vendor/js/bootstrap.js"></script>
    <script src="{{ asset('/materialize') }}/assets/vendor/libs/node-waves/node-waves.js"></script>
    <script src="{{ asset('/materialize') }}/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="{{ asset('/materialize') }}/assets/vendor/libs/hammer/hammer.js"></script>
    <script src="{{ asset('/materialize') }}/assets/vendor/libs/i18n/i18n.js"></script>
    <script src="{{ asset('/materialize') }}/assets/vendor/libs/typeahead-js/typeahead.js"></script>
    <script src="{{ asset('/materialize') }}/assets/vendor/js/menu.js"></script>

    <!-- endbuild -->

    <!-- Vendors JS -->
    <script src="{{ asset('/materialize') }}/assets/vendor/libs/@form-validation/popular.js"></script>
    <script src="{{ asset('/materialize') }}/assets/vendor/libs/@form-validation/bootstrap5.js"></script>
    <script src="{{ asset('/materialize') }}/assets/vendor/libs/@form-validation/auto-focus.js"></script>

    <!-- Main JS -->
    <script src="{{ asset('/materialize') }}/assets/js/main.js"></script>

    <!-- Page JS -->
    @vite('resources/js/auth/script.js')

    <!-- Password Toggle Handler -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.form-password-toggle .input-group-text').forEach(function (toggle) {
                toggle.addEventListener('click', function () {
                    var input = toggle.closest('.input-group').querySelector('input');
                    var icon = toggle.querySelector('i');
                    if (!input) return;
                    if (input.type === 'password') {
                        input.type = 'text';
                        if (icon) { icon.classList.remove('ri-eye-off-line'); icon.classList.add('ri-eye-line'); }
                    } else {
                        input.type = 'password';
                        if (icon) { icon.classList.remove('ri-eye-line'); icon.classList.add('ri-eye-off-line'); }
                    }
                });
            });
        });
    </script>

    <script src="{{ asset('/materialize') }}/assets/vendor/libs/toastr/toastr.js"></script>
    <script>
        @if (session('success'))
            toastr.success('{{ session('success') }}', 'Success!')
        @elseif (session('error'))
            toastr.error('{{ session('error') }}', 'Error!')
        @elseif (session('info'))
            toastr.info('{{ session('info') }}', 'Info!')
        @elseif (session('status'))
            toastr.info('{{ session('status') }}', 'Info!')
        @elseif (session('warning'))
            toastr.warning('{{ session('warning') }}', 'Warning!')
        @endif
    </script>

    @stack('scripts')
</body>

</html>
