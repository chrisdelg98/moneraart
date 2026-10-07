<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property string $title @property string $body */
class LegalDocumentBody extends Model
{
    protected $guarded = ['id'];
}
