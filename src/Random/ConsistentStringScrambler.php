<?php

namespace Ingenerator\PHPUtils\Random;

use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;
use function array_filter;
use function array_values;
use function crc32;
use function implode;
use function preg_split;

class ConsistentStringScrambler
{
    /**
     * Consistently "randomise" the words in a string to be the same for the same random seed value
     */
    public function shuffleWords(?string $input, string $random_seed): ?string
    {
        if (empty($random_seed)) {
            throw new InvalidArgumentException('No seed value provided to '.__METHOD__);
        }

        // Break the string into words (and return null if there is no content / only whitespace)
        $words = array_filter(preg_split('/\s+/', $input ?? ''));

        if (empty($words)) {
            return NULL;
        }


        // Convert the arbitrary seed input into an integer suitable for seeding the PRNG - doesn't
        // need to be complex, just enough to give the randomness a bit of variety
        $seed = crc32($random_seed);

        return implode(' ', $this->seededShuffle($words, $seed));

    }

    /**
     * Seeded Fisher-Yates shuffle implemented as per https://stackoverflow.com/a/19658344
     */
    private function seededShuffle(array $items, int $seed): array
    {
        $randomizer = new Randomizer(new MT19937($seed));

        // Ensure the array is 0-indexed
        $items = array_values($items);

        for ($i = count($items) - 1; $i > 0; $i--) {
            // Swap each item with an item from a random position (which may mean some values
            // are swapped more than once).
            $rnd = $randomizer->getInt(0, $i);
            $old_item_i = $items[$i];
            $items[$i] = $items[$rnd];
            $items[$rnd] = $old_item_i;
        }

        return $items;
    }
}
