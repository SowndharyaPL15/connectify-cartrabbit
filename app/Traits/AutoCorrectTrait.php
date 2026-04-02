<?php

namespace App\Traits;

trait AutoCorrectTrait
{
    public function autoCorrect(string $message): string
    {
        // Add more common typos as needed
        $typos = [
            'wors'  => 'words',
            'hel'   => 'hello',
            'u'     => 'you',
            'r'     => 'are',
            'y'     => 'why',
            'gona'  => 'going to',
            'wanna' => 'want to',
            'idk'   => 'I do not know',
            'im'    => "I'm",
            'dont'  => "don't",
            'cant'  => "can't",
            'thx'   => 'thanks',
            'plz'   => 'please',
        ];

        foreach ($typos as $bad => $good) {
            // Regex to match whole words only, case-insensitive
            $message = preg_replace('/\b' . preg_quote($bad) . '\b/i', $good, $message);
        }

        return $message;
    }
}
