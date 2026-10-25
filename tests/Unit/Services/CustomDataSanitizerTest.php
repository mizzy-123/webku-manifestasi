<?php

use App\Services\CustomDataSanitizer;

beforeEach(function () {
    $this->sanitizer = new CustomDataSanitizer;
});

test('it keeps safe formatting tags and strips scripts and attributes', function () {
    $clean = $this->sanitizer->sanitizeHtml('Halo <b onclick="x()">dunia</b><script>alert(1)</script><iframe src="//evil"></iframe><br><img src=x onerror=alert(1)>');

    expect($clean)
        ->toContain('<b>dunia</b>')
        ->toContain('<br>')
        ->not->toContain('script')
        ->not->toContain('iframe')
        ->not->toContain('onclick')
        ->not->toContain('onerror')
        ->not->toContain('<img');
});

test('it blocks dangerous url schemes', function (string $url) {
    expect($this->sanitizer->sanitizeUrl($url))->toBe('');
})->with(['javascript:alert(1)', ' JaVa script:alert(1)', 'data:text/html;base64,AAAA', 'vbscript:x', '//evil.test/x']);

test('it allows web, mail, anchor and relative urls', function (string $url) {
    expect($this->sanitizer->sanitizeUrl($url))->toBe($url);
})->with(['https://wa.me/6281', 'mailto:a@b.test', 'tel:+62812', '#kontak', 'about.html', '/storage/custom-uploads/abc/x.webp']);

test('it rejects data urls for images', function () {
    expect($this->sanitizer->sanitizeImageUrl('data:image/png;base64,AAAA'))->toBe('');
});

test('it sanitizes a whole custom data payload', function () {
    $clean = $this->sanitizer->sanitize([
        'colors' => ['--color-primary' => '#9275f7', '--bad' => 'red', 'x y' => '#fff'],
        'global' => ['texts' => ['footer' => '<script>x</script>Hak cipta']],
        'pages' => [
            'home' => [
                'images' => ['hero' => 'javascript:alert(1)', 'logo' => '/storage/custom-uploads/abcdefghij/a.webp'],
                'links' => ['cta' => ['text' => 'Pesan', 'href' => 'javascript:alert(1)']],
            ],
            '../etc' => ['texts' => ['a' => 'b']],
        ],
    ]);

    expect($clean['colors'])->toBe(['--color-primary' => '#9275f7'])
        ->and($clean['global']['texts']['footer'])->toBe('Hak cipta')
        ->and($clean['pages'])->toHaveKey('home')->not->toHaveKey('../etc')
        ->and($clean['pages']['home']['images'])->toBe(['logo' => '/storage/custom-uploads/abcdefghij/a.webp'])
        ->and($clean['pages']['home']['links']['cta'])->toBe(['text' => 'Pesan', 'href' => '']);
});
