<?php
declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\Encryption\CryptoBox;

use Ingenerator\PHPUtils\Encryption\CryptoBox\CryptoBoxKeypair;
use Ingenerator\PHPUtils\Encryption\CryptoBox\CryptoBoxKeypairSet;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;

class CryptoBoxKeypairSetTest extends TestCase
{

    public function test_it_can_return_known_keypair_by_id()
    {
        $subject = new CryptoBoxKeypairSet(
            CryptoBoxKeypair::generate('key-a'),
            $expected = CryptoBoxKeypair::generate('key-b'),
            CryptoBoxKeypair::generate('key-f'),
        );

        $this->assertSame(
            $expected,
            $subject->getKeypair('key-b')
        );
    }

    public function test_it_throws_on_construction_with_duplicate_key_ids()
    {
        $a1 = CryptoBoxKeypair::generate('key-a');
        $a2 = CryptoBoxKeypair::generate('key-a');
        $this->expectException(\UnexpectedValueException::class);
        new CryptoBoxKeypairSet($a1, $a2);
    }

    public function test_it_throws_on_request_for_undefined_key()
    {
        $subject = new CryptoBoxKeypairSet(
            CryptoBoxKeypair::generate('one'),
            CryptoBoxKeypair::generate('two'),
            CryptoBoxKeypair::generate('three'),
        );

        $this->expectException(OutOfBoundsException::class);
        $subject->getKeypair('four');
    }

    public function test_it_provides_shortcut_to_decrypt_a_value_with_known_key()
    {
        $subject = new CryptoBoxKeypairSet(
            CryptoBoxKeypair::generate('a'),
            $keypair = CryptoBoxKeypair::generate('b'),
            CryptoBoxKeypair::generate('c'),
        );

        $encrypted = $keypair->getPublicKey()->encrypt('so secret');

        $this->assertSame('so secret', $subject->decrypt($encrypted));
        // Actual failure cases for decrypt are tested on the keypair itself
    }

    public function test_it_can_be_constructed_from_strings()
    {
        $kp1 = CryptoBoxKeypair::generate('one');
        $kp2 = CryptoBoxKeypair::generate('two');
        $kp3 = CryptoBoxKeypair::generate('three');

        $subject = CryptoBoxKeypairSet::fromStrings(
            $kp1->exportToString(),
            $kp2->exportToString(),
            $kp3->exportToString(),
        );

        $this->assertEquals($kp1, $subject->getKeypair('one'));
        $this->assertEquals($kp2, $subject->getKeypair('two'));
        $this->assertEquals($kp3, $subject->getKeypair('three'));
    }

}
