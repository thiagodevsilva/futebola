<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ViteUseBuildCommand extends Command
{
    protected $signature = 'vite:use-build';
    protected $description = 'Remove o arquivo public/hot para o Laravel usar o build (public/build) em vez do dev server. Use quando a tela ficar em branco por estar sem o container node.';

    public function handle(): int
    {
        $hotFile = public_path('hot');
        if (File::exists($hotFile)) {
            File::delete($hotFile);
            $this->info('Arquivo public/hot removido. Dê refresh na página para carregar o build.');
        } else {
            $this->line('Nenhum arquivo public/hot encontrado. O app já deve estar usando o build.');
        }

        return self::SUCCESS;
    }
}
