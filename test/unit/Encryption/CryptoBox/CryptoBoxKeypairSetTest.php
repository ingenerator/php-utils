<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\Encryption\CryptoBox;

use Ingenerator\PHPUtils\Encryption\CryptoBox\CryptoBoxKeypair;
use Ingenerator\PHPUtils\Encryption\CryptoBox\CryptoBoxKeypairSet;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class CryptoBoxKeypairSetTest extends TestCase
{
    public function testItCanReturnKnownKeypairById(): void
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

    public function testItThrowsOnConstructionWithDuplicateKeyIds(): void
    {
        $a1 = CryptoBoxKeypair::generate('key-a');
        $a2 = CryptoBoxKeypair::generate('key-a');
        $this->expectException(UnexpectedValueException::class);
        new CryptoBoxKeypairSet($a1, $a2);
    }

    public function testItThrowsOnRequestForUndefinedKey(): void
    {
        $subject = new CryptoBoxKeypairSet(
            CryptoBoxKeypair::generate('one'),
            CryptoBoxKeypair::generate('two'),
            CryptoBoxKeypair::generate('three'),
        );

        $this->expectException(OutOfBoundsException::class);
        $subject->getKeypair('four');
    }

    public function testItProvidesShortcutToDecryptAValueWithKnownKey(): void
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

    public function testItCanBeConstructedFromStrings(): void
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
