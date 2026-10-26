<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\StringEncoding;

use Ingenerator\PHPUtils\StringEncoding\Base64Url;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function random_bytes;

final class Base64UrlTest extends TestCase
{
    /**
     * @return Iterator<int, array<int, string>>
     */
    public static function provider_encode_decode(): Iterator
    {
        yield ['1'];
        yield ['foo'];
        yield [random_bytes(55)];
    }

    #[DataProvider('provider_encode_decode')]
    public function testItCanEncodeAndDecodeAValue(string $original): void
    {
        $this->assertSame($original, Base64Url::decode(Base64Url::encode($original)));
    }

    #[DataProvider('provider_encode_decode')]
    public function testItsEncodedOutputIsAlwaysUrlSafe(string $value): void
    {
        $encoded = Base64Url::encode($value);
        $this->assertIsString($encoded);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9\-\_]+$/', $encoded);
    }
}
