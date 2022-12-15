<?php

namespace test\unit\Ingenerator\PHPUtils\Random;

use Ingenerator\PHPUtils\Random\ConsistentStringScrambler;
use PHPUnit\Framework\TestCase;
use function mt_rand;


class ConsistentStringScramblerTest extends TestCase
{

    public function test_it_is_initialisable(): void
    {
        $this->assertInstanceOf(ConsistentStringScrambler::class, $this->newSubject());
    }

    public function test_it_returns_null_for_null_string(): void
    {
        $this->assertNull($this->newSubject()->shuffleWords(NULL, 'anything'));
    }

    public function test_it_returns_null_for_empty_string(): void
    {
        $this->assertNull($this->newSubject()->shuffleWords('', 'anything'));
    }

    /**
     * @testWith ["Foo"]
     *           ["  Foo"]
     *           ["Foo "]
     *           ["\nFoo\n"]
     */
    public function test_it_returns_same_output_for_single_word_string_even_with_extra_whitespace(
        $input
    ): void {
        $this->assertSame('Foo', $this->newSubject()->shuffleWords($input, 'any'));
    }

    public function test_it_always_produces_same_shuffled_words_for_same_hash_input(): void
    {
        $input  = 'I am the eggman, I am the walrus';
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

    public function test_it_produces_different_shuffled_words_for_different_hash_input(): void
    {
        $input = 'I am the eggman, I am the walrus';
        $first = $this->newSubject()->shuffleWords($input, 'my-hash');
        $this->assertNotEquals(
            $first,
            $this->newSubject()->shuffleWords($input, 'other-hash'),
            'Different hash key should produce different sequence'
        );
    }

    /**
     * @testWith ["I,  robot am\t alive", "am I, robot alive"]
     *           ["Thus!\nspake Zarathustra  ", "spake Zarathustra Thus!"]
     *           [" 51 Niddry St\n", "Niddry St 51"]
     */
    public function test_it_strips_repeated_and_enclosing_whitespace_in_incoming_string($input, $expect): void
    {
        $this->assertSame($expect, $this->newSubject()->shuffleWords($input, 'any-hash'));
    }

    public function test_it_does_not_cause_subsequent_random_numbers_to_be_predictable(): void
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

    public function test_it_throws_with_empty_hash_input(): void
    {
        $subject = $this->newSubject();
        $this->expectException(\InvalidArgumentException::class);
        $subject->shuffleWords('anything', '');
    }

    private function newSubject(): ConsistentStringScrambler
    {
        return new ConsistentStringScrambler;
    }

}
