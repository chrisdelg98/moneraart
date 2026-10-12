@props(['meta'])

{{--
    Everything a crawler and a link preview read, in one place.

    The robots value comes from the page rather than from a global switch, so
    a cart can say noindex while the catalogue says index — and so a soft
    launch can keep the whole store out without editing a template. See §11.5.
--}}
<title>{{ $meta->title }}</title>

@if ($meta->description)
    <meta name="description" content="{{ $meta->description }}">
@endif

<meta name="robots" content="{{ $meta->robots }}">

@if ($meta->canonical)
    {{-- A filtered listing points at the unfiltered one, so thousands of
         facet combinations do not compete with the page that matters. --}}
    <link rel="canonical" href="{{ $meta->canonical }}">
@endif

<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ \App\Support\Facades\Settings::get('store.name') }}">
<meta property="og:title" content="{{ $meta->title }}">
<meta property="og:url" content="{{ $meta->canonical ?? url()->current() }}">

@if ($meta->description)
    <meta property="og:description" content="{{ $meta->description }}">
@endif

@if ($meta->image)
    <meta property="og:image" content="{{ $meta->image }}">
    <meta name="twitter:card" content="summary_large_image">
@else
    <meta name="twitter:card" content="summary">
@endif

@foreach ($meta->jsonLd as $schema)
    {{-- JSON_UNESCAPED_SLASHES keeps the URLs readable; the rest is escaped,
         because this block is the one place page data becomes script. --}}
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endforeach
