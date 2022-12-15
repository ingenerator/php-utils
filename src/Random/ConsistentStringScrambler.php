<?php

namespace Ingenerator\PHPUtils\Random;

use InvalidArgumentException;
use function array_filter;
use function array_values;
use function crc32;
use function implode;
use function mt_rand;
use function mt_srand;
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
        mt_srand($seed);
        // Ensure the array is 0-indexed
        $items = array_values($items);

        try {

            for ($i = count($items) - 1; $i > 0; $i--) {
                // Swap each item with an item from a random position (which may mean some values
                // are swapped more than once).
                $rnd         = mt_rand(0, $i);
                $old_item_i  = $items[$i];
                $items[$i]   = $items[$rnd];
                $items[$rnd] = $old_item_i;
            }

            return $items;
        } finally {
            // Reset the random seed to be random again so that this does not impact on later
            // random numbers from elsewhere in the app.
            mt_srand();
        }
    }
}
