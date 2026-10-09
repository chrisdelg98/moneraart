<?php

declare(strict_types=1);

namespace App\Filament\Resources\Downloads;

use App\Filament\Resources\Downloads\Pages\ListDownloads;
use App\Filament\Resources\Downloads\Tables\DownloadsTable;
use App\Models\DownloadGrant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DownloadResource extends Resource
{
    protected static ?string $model = DownloadGrant::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static UnitEnum|string|null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Downloads';

    protected static ?string $modelLabel = 'download link';

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return DownloadsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListDownloads::route('/')];
    }

    /** A link exists because an order was paid for. */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Deleting a grant would take its download logs with it — the evidence in
     * an "I never received it" dispute. Revoke stops the link and keeps both.
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
