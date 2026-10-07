<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Models\LegalDocument;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LegalController
{
    public function __invoke(string $slug): View
    {
        $document = LegalDocument::where('slug', $slug)->firstOrFail();
        $version = $document->current();

        if ($version === null) {
            throw new HttpException(404, 'That page is not published yet.');
        }

        $body = $version->body();

        abort_if($body === null, 404);

        return view('storefront.legal', [
            'document' => $document,
            'version' => $version,
            'body' => $body,
        ]);
    }
}
