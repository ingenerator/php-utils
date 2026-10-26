<?php

namespace test\unit\Ingenerator\PHPUtils\Random;

use Ingenerator\PHPUtils\Random\ConsistentStringScrambler;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

use function mt_rand;

class ConsistentStringScramblerTest extends TestCase
{
    public function testItIsInitialisable(): void
    {
        $this->assertInstanceOf(ConsistentStringScrambler::class, $this->newSubject());
    }

    public function testItReturnsNullForNullString(): void
    {
        $this->assertNull($this->newSubject()->shuffleWords(null, 'anything'));
    }

    public function testItReturnsNullForEmptyString(): void
    {
        $this->assertNull($this->newSubject()->shuffleWords('', 'anything'));
    }

    #[TestWith(['Foo'])]
    #[TestWith(['  Foo'])]
    #[TestWith(['Foo '])]
    #[TestWith(['
Foo
'])]
    public function testItReturnsSameOutputForSingleWordStringEvenWithExtraWhitespace(
        $input,
    ): void {
        $this->assertSame('Foo', $this->newSubject()->shuffleWords($input, 'any'));
    }

    public function testItAlwaysProducesSameShuffledWordsForSameHashInput(): void
    {
        $input = 'I am the eggman, I am the walrus';
        $result = $this->newSubject()->shuffleWords($input, 'my-hash');

        $this->assertSame(
            $result,
            $this->newSubject()->shuffleWords($input, 'my-hash'),
            'Should give same result on every run'
        );

        $this->assertSame(
            'the am eggman, the I I am walrus',
            $this->newSubject()->shuffleWords($input, 'my-hash'),
            'Should be expected value across all platforms'
        );
    }

    public function testItProducesDifferentShuffledWordsForDifferentHashInput(): void
    {
        $input = 'I am the eggman, I am the walrus';
        $first = $this->newSubject()->shuffleWords($input, 'my-hash');
        $this->assertNotEquals(
            $first,
            $this->newSubject()->shuffleWords($input, 'other-hash'),
            'Different hash key should produce different sequence'
        );
    }

    #[TestWith(['I,  robot am	 alive', 'am I, robot alive'])]
    #[TestWith(['Thus!
spake Zarathustra  ', 'spake Zarathustra Thus!'])]
    #[TestWith([' 51 Niddry St
', 'Niddry St 51'])]
    public function testItStripsRepeatedAndEnclosingWhitespaceInIncomingString($input, $expect): void
    {
        $this->assertSame($expect, $this->newSubject()->shuffleWords($input, 'any-hash'));
    }

    public function testItDoesNotCauseSubsequentRandomNumbersToBePredictable(): void
    {
        // Seeding the PRNG inside the class will of course affect randomness generated subsequently
        // anywhere in the code, we need to make sure it's randomy again before returning to avoid
        // affecting any later randomisation from other places.
        //
        // There is, of course, a very tiny chance that it would randomly produce the next number
        // in sequence even with a random seed, producing a false failure. So compare the next
        // three "random" numbers, it should be virtually impossible that we'd get these actual
        // numbers actually at random.
        $result = $this->newSubject()->shuffleWords('whatever I said', 'some-seed');

        // If this sanity check fails, the internal seed / random sequence is now changed and
        // therefore the three numbers in the next check will need to be updated to be the ones
        // that the PRNG would give if the seed hadn't been reset.
        $this->assertSame(
            'I whatever said',
            $result,
            'Should be using expected seed internally'
        );

        $this->assertNotSame(
            [
                1753721728,
                1187560983,
                1193164547,
            ],
            [
                mt_rand(0, 2140000000),
                mt_rand(0, 2140000000),
                mt_rand(0, 2140000000),
            ],
            'Later random numbers are random again'
        );
    }

    public function testItThrowsWithEmptyHashInput(): void
    {
        $subject = $this->newSubject();
        $this->expectException(InvalidArgumentException::class);
        $subject->shuffleWords('anything', '');
    }

    private function newSubject(): ConsistentStringScrambler
    {
        return new ConsistentStringScrambler();
    }
}
