<?php


namespace test\unit\Ingenerator\PHPUtils\StringEncoding;


use PHPUnit\Framework\TestCase;
use Ingenerator\PHPUtils\StringEncoding\Base64Url;

class Base64UrlTest extends TestCase
{

    public function provider_encode_decode()
    {
        return [
            ['1'],
            ['foo'],
            [\random_bytes(55)],
        ];
    }

    /**
     * @dataProvider provider_encode_decode
     */
    public function test_it_can_encode_and_decode_a_value($original)
    {
        $this->assertSame($original, Base64Url::decode(Base64Url::encode($original)));
    }

    /**
     * @dataProvider provider_encode_decode
     */
    public function test_its_encoded_output_is_always_url_safe($value)
    {
        $encoded = Base64Url::encode($value);
        $this->assertIsString($encoded);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9\-\_]+$/', $encoded);
    }

}

