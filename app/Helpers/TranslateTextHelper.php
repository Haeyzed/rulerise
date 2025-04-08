<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stichoza\GoogleTranslate\Exceptions\LargeTextException;
use Stichoza\GoogleTranslate\Exceptions\RateLimitException;
use Stichoza\GoogleTranslate\Exceptions\TranslationRequestException;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslateTextHelper
{
    /**
     * @var string Default source language.
     */
    private static string $source = 'en';

    /**
     * @var string Default target language.
     */
    private static string $target = 'en';

    /**
     * Translate the given text from the source language to the target language.
     *
     * @param string $text The text to be translated.
     * @return string The translated text.
     */
    public static function translate(string $text): string
    {
        $translatedText = '';

        $targetLanguage = request()->header('Accept-Language') ?? 'en';

//         Ensure $targetLanguage is always a string
//        $targetLanguage = 'fr';
        $targetLanguage = $targetLanguage ?: 'en';

        $cacheKey = self::getCacheKey($text, $targetLanguage);

        // Check cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $translator = new GoogleTranslate;
            $translator->setSource(self::$source);
            $translator->setTarget(self::$target);

            $translatedText = $translator->translate($text);
            Cache::put($cacheKey, $translatedText, 3600); // Cache for 1 hour
        } catch (LargeTextException|RateLimitException|TranslationRequestException $ex) {
            Log::error('TranslateTextHelperException', [
                'message' => $ex->getMessage(),
            ]);
        }

        return $translatedText;
    }

    /**
     * Generate a cache key for the given text and language.
     *
     * This function creates a unique cache key by combining the text and language
     * and applying the MD5 hashing algorithm.
     *
     * @param string $text The text for which the cache key is generated.
     * @param string $language The language code associated with the text.
     * @return string The generated cache key as an MD5 hash.
     */
    private static function getCacheKey(string $text, string $language): string
    {
        return md5($text . '_' . $language);
    }

    /**
     * Set the source language.
     *
     * @param string $source The source language code.
     * @return self This instance of the class.
     */
    public static function setSource(string $source): self
    {
        self::$source = $source;

        return new self;
    }

    /**
     * Set the target language.
     *
     * @param string $target The target language code.
     * @return self This instance of the class.
     */
    public static function setTarget(string $target): self
    {
        self::$target = $target;

        return new self;
    }
}
