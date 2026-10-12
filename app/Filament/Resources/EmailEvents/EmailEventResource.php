<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailEvents;

use App\Filament\Resources\EmailEvents\Pages\ListEmailEvents;
use App\Filament\Resources\EmailEvents\Tables\EmailEventsTable;
use App\Models\EmailEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class EmailEventResource extends Resource
{
    protected static ?string $model = EmailEvent::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope-open';

    protected static UnitEnum|string|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Email log';

    protected static ?string $modelLabel = 'email';

    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return EmailEventsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListEmailEvents::route('/')];
    }

    /** A record of what happened, never something typed in. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }
}
