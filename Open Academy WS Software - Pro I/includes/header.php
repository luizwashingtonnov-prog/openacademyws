<?php
require_once __DIR__ . '/../config.php';
$flashMessages = get_flash();
$title = $pageTitle ?? APP_NAME;
$assetBase = BASE_URL === '/' ? '' : BASE_URL;
$bodyClasses = trim('min-h-screen font-sans text-slate-900 antialiased ' . ($bodyClass ?? ''));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e60000">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            red: '#B22222',
                            redDark: '#8B1A1A',
                            gray: '#2F2F2F'
                        }
                    },
                    fontFamily: {
                        sans: ['"Inter"', '"Segoe UI"', 'system-ui', 'sans-serif']
                    },
                    boxShadow: {
                        glow: '0 25px 50px -12px rgba(178, 34, 34, 0.35)'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('assets/css/app.css')); ?>">
    <style>
        body {
            background-attachment: fixed;
        }

        .glass-panel {
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars($bodyClasses); ?>">
