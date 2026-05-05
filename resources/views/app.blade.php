<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Futebola - Portal de futebol brasileiro. Notícias agregadas, tabela do Brasileirão e próximos jogos.">
    <title>Futebola - Futebol Brasileiro</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    @php
        // Se existir build, sempre usa ele. Evita tela branca (ex.: hot existe mas node não está rodando).
        $manifestPath = public_path('build/manifest.json');
        $useBuild = file_exists($manifestPath);
        if ($useBuild) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            $cssEntry = $manifest['resources/css/app.css'] ?? null;
            $jsEntry = $manifest['resources/js/app.js'] ?? null;
            if ($cssEntry) {
                echo '<link rel="stylesheet" href="' . e(asset('build/' . $cssEntry['file'])) . '">';
            }
            if ($jsEntry) {
                foreach ($jsEntry['css'] ?? [] as $cssFile) {
                    echo '<link rel="stylesheet" href="' . e(asset('build/' . $cssFile)) . '">';
                }
                echo '<script type="module" src="' . e(asset('build/' . $jsEntry['file'])) . '"></script>';
            }
        }
    @endphp
    @if(!$useBuild)
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-[#FFFFFF] text-neutral-800">
    <div id="app"></div>
</body>
</html>
