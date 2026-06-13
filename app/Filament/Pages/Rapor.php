<?php

namespace App\Filament\Pages;
use Illuminate\Support\Facades\Auth;
use App\Support\RaporService;
use Filament\Actions\Action;
use Filament\Pages\Page;

class Rapor extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.rapor';
    protected static ?string $title = 'Rapor Saya';

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->isSiswa() ?? false;
    }

    public function unduhRapor()
    {
        return RaporService::unduh(Auth::user()->siswa);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('unduh')->label('Unduh Rapor PDF')
                ->icon('heroicon-o-document-arrow-down')->action('unduhRapor'),
        ];
    }
}
